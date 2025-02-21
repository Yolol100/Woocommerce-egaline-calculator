<?php
declare(strict_types=1);

/**
 * Voeg extra gegevens toe aan de winkelwagen en verwerk calculator-data.
 */
class Egaline_Calculator_Cart {

    /**
     * Constructor.
     *
     * Registreert filters en acties voor de calculatorgegevens in de winkelwagen en orderitems.
     */
    public function __construct() {
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_calculator_data_to_cart'], 10, 2);
        // Gebruik een hogere prioriteit zodat de prijswijziging als laatste wordt toegepast.
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 99, 1);
        add_filter('woocommerce_get_item_data', [$this, 'display_calculator_data_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_calculator_data_to_order_items'], 10, 4);
        add_action('woocommerce_cart_item_class', [$this, 'add_cart_item_class'], 10, 3);

        // Extra filters voor de weergave van de prijs in de winkelwagen.
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 10, 3);

        // Filter voor het totaal van de winkelwagen zodat ook fees, verzendkosten en belastingen worden meegerekend.
        add_filter('woocommerce_calculated_total', [$this, 'filter_calculated_total'], 99, 2);

        // Filter zodat de tax class voor items met calculator-data de standaard (lege) tax class gebruikt.
        add_filter('woocommerce_cart_item_tax_class', [$this, 'filter_cart_item_tax_class'], 10, 3);

        // WooCommerce Fix: Voorkom dat identieke producten worden samengevoegd in de winkelwagen.
        add_filter(
            'woocommerce_cart_id',
            function (string $cart_id, int $product_id, int $variation_id, array $cart_item_data, string $cart_item_key = ''): string {
                if (!empty($cart_item_data['calculator_data'])) {
                    $cart_id .= '_' . md5(json_encode($cart_item_data['calculator_data']));
                }
                return $cart_id;
            },
            10,
            4
        );

        // Herstel custom cart item data uit de sessie.
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item_data'], 20, 2);
    }

    /**
     * Voegt calculatorgegevens toe aan de winkelwagen en verwerkt POST-gegevens.
     *
     * @param array $cart_item_data De originele cart item data.
     * @param int   $product_id     Het product-ID.
     * @return array Aangepaste cart item data met calculatorgegevens.
     */
    public function add_calculator_data_to_cart(array $cart_item_data, int $product_id): array {
        if (get_post_meta($product_id, '_enable_calculator', true) !== 'yes') {
            return $cart_item_data;
        }

        // Haal configuratie uit de product meta (met fallback waarden).
        $kg_per_bag = (float) get_post_meta($product_id, '_kg_per_bag', true) ?: 15;
        $kg_per_mm  = (float) get_post_meta($product_id, '_kg_per_mm', true) ?: 0;
        $kg_per_m2  = (float) get_post_meta($product_id, '_kg_per_m2', true) ?: 0;

        // Ontvang POST-data (sanitizen en casten naar float).
        $thickness = isset($_POST['egaline_mm']) ? (float) sanitize_text_field((string) $_POST['egaline_mm']) : 0;
        $area      = isset($_POST['egaline_m2'])  ? (float) sanitize_text_field((string) $_POST['egaline_m2'])  : 0;

        // Bereken de benodigde hoeveelheid en het aantal zakken.
        $needed_kg = ($thickness * $area * $kg_per_mm) + ($area * $kg_per_m2);
        $bags      = (int) ceil($needed_kg / $kg_per_bag);

        // Haal het product op en bepaal de reguliere prijs.
        $product = wc_get_product($product_id);
        $regular_price = (float) $product->get_price();

        // Haal de variatieprijs op (indien van toepassing)
        if (isset($_POST['variation_id']) && $_POST['variation_id'] != 0) {
            $variation = wc_get_product((int) $_POST['variation_id']);
            if ($variation) {
                $regular_price = (float) $variation->get_price();
            }
        }

        // Bereken de totale prijs op basis van het aantal zakken.
        $total_price = $bags * $regular_price;

        // Voeg de calculatorgegevens toe aan de cart item data.
        $cart_item_data['calculator_data'] = [
            'needed_kg'   => $needed_kg,
            'bags'        => $bags,
            'total_price' => $total_price, // Prijs per item volgens de calculator.
            'kg_per_bag'  => $kg_per_bag,
            'thickness'   => $thickness,
            'area'        => $area,
        ];

        // Voeg een unieke sleutel toe zodat elk item afzonderlijk wordt behandeld.
        $cart_item_data['unique_key'] = uniqid('', true);

        return $cart_item_data;
    }

    /**
     * Update de prijs van winkelwagenitems op basis van de calculatorgegevens.
     *
     * @param WC_Cart $cart Het winkelwagenobject.
     */
    public function update_cart_item_price(WC_Cart $cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        foreach ($cart->get_cart() as $cart_item_key => &$cart_item) {
            if (isset($cart_item['calculator_data']['total_price'])) {
                $calc_price = (float) $cart_item['calculator_data']['total_price'];
                // Clone het productobject zodat dit item een uniek object heeft.
                $cart_item['data'] = clone $cart_item['data'];
                // Stel de eenheidsprijs in op de calculatorprijs.
                $cart_item['data']->set_price($calc_price);
                // Stel de tax class in op leeg zodat de standaard tax wordt toegepast.
                $cart_item['data']->set_tax_class('');
                // Update de line totals.
                $line_total = $calc_price * $cart_item['quantity'];
                $cart_item['line_total']    = $line_total;
                $cart_item['line_subtotal'] = $line_total;
            }
        }
    }

    /**
     * Filtert de weergave van de prijs in de winkelwagen.
     *
     * @param string $price         De originele prijsweergave.
     * @param array  $cart_item     Het winkelwagenitem.
     * @param string $cart_item_key De sleutel van het winkelwagenitem.
     * @return string De gefilterde prijsweergave.
     */
    public function filter_cart_item_price(string $price, array $cart_item, string $cart_item_key): string {
        if (isset($cart_item['calculator_data']['total_price'])) {
            $calc_price = (float) $cart_item['calculator_data']['total_price'];
            return wc_price($calc_price);
        }
        return $price;
    }

    /**
     * Filtert de weergave van het subtotaal in de winkelwagen.
     *
     * @param string $subtotal      Het originele subtotaal.
     * @param array  $cart_item     Het winkelwagenitem.
     * @param string $cart_item_key De sleutel van het winkelwagenitem.
     * @return string Het gefilterde subtotaal.
     */
    public function filter_cart_item_subtotal(string $subtotal, array $cart_item, string $cart_item_key): string {
        if (isset($cart_item['calculator_data']['total_price'])) {
            $calc_price = (float) $cart_item['calculator_data']['total_price'];
            $line_total = $calc_price * $cart_item['quantity'];
            return wc_price($line_total);
        }
        return $subtotal;
    }

    /**
     * Bereken het winkelwagentotaal op basis van de (aangepaste) line totals.
     *
     * @param float     $total       Het originele totaal.
     * @param WC_Cart   $cart_object Het winkelwagenobject.
     * @return float Het aangepaste totaal.
     */
    public function filter_calculated_total(float $total, WC_Cart $cart_object): float {
        $calc_total = 0.0;
        foreach ($cart_object->get_cart() as $cart_item) {
            if (isset($cart_item['calculator_data']['total_price'])) {
                $calc_total += (float) $cart_item['calculator_data']['total_price'] * $cart_item['quantity'];
            } else {
                $calc_total += $cart_item['data']->get_price() * $cart_item['quantity'];
            }
        }
        return $calc_total;
    }

    /**
     * Voor items met calculator-data gebruiken we de standaard (lege) tax class.
     *
     * @param string $tax_class     De originele tax class.
     * @param array  $cart_item     Het winkelwagenitem.
     * @param string $cart_item_key De sleutel van het winkelwagenitem.
     * @return string De aangepaste tax class.
     */
    public function filter_cart_item_tax_class(string $tax_class, array $cart_item, string $cart_item_key): string {
        return isset($cart_item['calculator_data']) ? '' : $tax_class;
    }

    /**
     * Voegt een CSS-class toe aan winkelwagenitems met actieve calculatorgegevens.
     *
     * @param string $class         De originele class.
     * @param array  $cart_item     Het winkelwagenitem.
     * @param string $cart_item_key De sleutel van het winkelwagenitem.
     * @return string De aangepaste class.
     */
    public function add_cart_item_class(string $class, array $cart_item, string $cart_item_key): string {
        if (isset($cart_item['calculator_data'])) {
            $class .= ' has-calculator-price';
        }
        return $class;
    }

    /**
     * Toont calculatorgegevens in de winkelwagenweergave.
     *
     * @param array $item_data  De originele item data.
     * @param array $cart_item  Het winkelwagenitem.
     * @return array De uitgebreide item data.
     */
    public function display_calculator_data_in_cart(array $item_data, array $cart_item): array {
        if (isset($cart_item['calculator_data'])) {
            $calculator_data = $cart_item['calculator_data'];
            if (isset($calculator_data['needed_kg'])) {
                $item_data[] = [
                    'name'  => __('Benodigde hoeveelheid (kg)', 'egaline'),
                    'value' => sprintf('%.2f kg', $calculator_data['needed_kg']),
                ];
            }
            if (isset($calculator_data['bags'])) {
                $item_data[] = [
                    'name'  => __('Aantal zakken', 'egaline'),
                    'value' => sprintf('%d (%d kg per zak)', $calculator_data['bags'], $calculator_data['kg_per_bag']),
                ];
            }
        }
        return $item_data;
    }

    /**
     * Voegt calculatorgegevens toe aan orderitems tijdens de checkout.
     *
     * @param WC_Order_Item_Product $item         Het order item.
     * @param string                $cart_item_key De sleutel van het winkelwagenitem.
     * @param array                 $values        De waarden van het winkelwagenitem.
     * @param WC_Order              $order         Het orderobject.
     */
    public function add_calculator_data_to_order_items($item, $cart_item_key, array $values, $order): void {
        if (isset($values['calculator_data'])) {
            $calculator_data = $values['calculator_data'];
            if (isset($calculator_data['needed_kg'])) {
                $item->add_meta_data(__('Benodigde hoeveelheid (kg)', 'egaline'), sprintf('%.2f kg', $calculator_data['needed_kg']));
            }
            if (isset($calculator_data['bags'])) {
                $item->add_meta_data(__('Aantal zakken', 'egaline'), sprintf('%d (%d kg per zak)', $calculator_data['bags'], $calculator_data['kg_per_bag']));
            }
            if (isset($calculator_data['total_price'])) {
                $item->add_meta_data(__('Totaalprijs', 'egaline'), sprintf(__('€ %.2f', 'egaline'), $calculator_data['total_price']));
            }
        }
    }

    /**
     * Herstelt de custom calculator_data wanneer een cart item uit de sessie wordt geladen.
     *
     * @param array $cart_item De winkelwagen item data.
     * @param array $values    De opgeslagen waarden.
     * @return array Het aangepaste winkelwagenitem.
     */
    public function restore_cart_item_data(array $cart_item, array $values): array {
        if (isset($values['calculator_data'])) {
            $cart_item['calculator_data'] = $values['calculator_data'];
            // Clone het productobject en stel de aangepaste prijs opnieuw in.
            $cart_item['data'] = clone wc_get_product($cart_item['product_id']);
            $cart_item['data']->set_price((float) $values['calculator_data']['total_price']);
            // Zorg ervoor dat de unieke key behouden blijft.
            if (isset($values['unique_key'])) {
                $cart_item['unique_key'] = $values['unique_key'];
            }
        }
        return $cart_item;
    }
}

new Egaline_Calculator_Cart();