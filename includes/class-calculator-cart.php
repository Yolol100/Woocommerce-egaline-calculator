<?php
declare(strict_types=1);

/**
 * Voeg extra gegevens toe aan de winkelwagen en verwerk calculator-data.
 */
final class Egaline_Calculator_Cart {

    /**
     * Constructor.
     *
     * Registreert filters en acties voor de calculatorgegevens in de winkelwagen en orderitems.
     */
    public function __construct() {
        // Voeg calculatorgegevens toe aan cart item data tijdens het toevoegen aan de winkelwagen.
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_calculator_data_to_cart'], 10, 2);
        // Gebruik een hogere prioriteit zodat de prijswijziging als laatste wordt toegepast.
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 99, 1);
        // Zorg dat de calculatorgegevens worden weergegeven in de winkelwagen.
        add_filter('woocommerce_get_item_data', [$this, 'display_calculator_data_in_cart'], 10, 2);
        // Voeg calculatorgegevens toe aan orderitems tijdens de checkout.
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_calculator_data_to_order_items'], 10, 4);
        // Voeg een CSS-class toe aan cart items met calculatorgegevens.
        add_action('woocommerce_cart_item_class', [$this, 'add_cart_item_class'], 10, 3);

        // Extra filters voor de weergave van de prijs in de winkelwagen.
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 10, 3);

        // Zorg dat het totaalbedrag van de winkelwagen (inclusief fees, verzendkosten en belastingen) wordt aangepast.
        add_filter('woocommerce_calculated_total', [$this, 'filter_calculated_total'], 99, 2);

        // Zorg dat voor items met calculatorgegevens de standaard (lege) tax class wordt gebruikt.
        add_filter('woocommerce_cart_item_tax_class', [$this, 'filter_cart_item_tax_class'], 10, 3);

        // WooCommerce Fix: Voorkom dat identieke producten worden samengevoegd in de winkelwagen.
        add_filter(
            'woocommerce_cart_id',
            function (string $cart_id, int $product_id, int $variation_id, array $cart_item_data, string $cart_item_key = ''): string {
                if (!empty($cart_item_data['calculator_data'])) {
                    // Voeg een unieke hash toe op basis van de calculator data.
                    $cart_id .= '_' . md5(json_encode($cart_item_data['calculator_data']));
                }
                return $cart_id;
            },
            10,
            4
        );

        // Herstel custom cart item data uit de sessie.
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item_data'], 20, 2);

        // Zorg dat de minicart de juiste prijzen weergeeft.
        add_filter('woocommerce_get_cart_contents', [$this, 'fix_minicart_price_display'], 20, 1);
    }

    /**
     * Voegt calculatorgegevens toe aan de winkelwagen en verwerkt POST-gegevens.
     *
     * De verborgen inputs op de productpagina dienen per eenheid te zijn (zonder vermenigvuldiging met de producthoeveelheid).
     *
     * @param array $cart_item_data De originele cart item data.
     * @param int   $product_id     Het product-ID.
     * @return array Aangepaste cart item data met calculatorgegevens.
     */
    public function add_calculator_data_to_cart(array $cart_item_data, int $product_id): array {
        if (get_post_meta($product_id, '_enable_calculator', true) !== 'yes') {
            return $cart_item_data;
        }

        $kg_per_bag = (float) get_post_meta($product_id, '_kg_per_bag', true) ?: 15;
        $kg_per_mm  = (float) get_post_meta($product_id, '_kg_per_mm', true) ?: 0;
        $kg_per_m2  = (float) get_post_meta($product_id, '_kg_per_m2', true) ?: 0;

        $thickness = isset($_POST['egaline_mm']) ? (float) sanitize_text_field((string) $_POST['egaline_mm']) : 0;
        $area      = isset($_POST['egaline_m2'])  ? (float) sanitize_text_field((string) $_POST['egaline_m2'])  : 0;

        // Bereken de benodigde hoeveelheid en het aantal zakken per eenheid
        $needed_kg = ($thickness * $area * $kg_per_mm) + ($area * $kg_per_m2);
        $bags      = (int) ceil($needed_kg / $kg_per_bag);

        $product = wc_get_product($product_id);
        $regular_price = (float) $product->get_price();

        if (isset($_POST['variation_id']) && $_POST['variation_id'] != 0) {
            $variation = wc_get_product((int) $_POST['variation_id']);
            if ($variation) {
                $regular_price = (float) $variation->get_price();
            }
        }

        // Bereken de originele totaalprijs per eenheid
        $original_total = $bags * $regular_price;

        $discount_threshold  = (int) get_post_meta($product_id, '_discount_threshold', true);
        $discount_percentage = (float) get_post_meta($product_id, '_discount_percentage', true);
        $discount_amount = 0;
        $final_total = $original_total;
        if ($bags >= $discount_threshold && $discount_percentage > 0) {
            $discount_amount = $original_total * ($discount_percentage / 100);
            $final_total = $original_total - $discount_amount;
        }

        $cart_item_data['calculator_data'] = [
            'needed_kg'           => $needed_kg,      // Per eenheid
            'bags'                => $bags,           // Per eenheid
            'total_price'         => $final_total,    // Unitprijs per eenheid (inclusief korting)
            'kg_per_bag'          => $kg_per_bag,
            'thickness'           => $thickness,
            'area'                => $area,
            'discount_threshold'  => $discount_threshold,
            'discount_percentage' => $discount_percentage,
            'discount_amount'     => $discount_amount,
        ];

        $cart_item_data['unique_key'] = uniqid('', true);

        return $cart_item_data;
    }

    /**
     * Update de prijs van winkelwagenitems op basis van de calculatorgegevens.
     *
     * Aangezien de calculatorgegevens per eenheid zijn opgeslagen, stelt deze functie de unitprijs in op
     * calculator_data['total_price'] en laat WooCommerce vervolgens de line total berekenen (unitprijs * quantity).
     *
     * @param WC_Cart $cart Het winkelwagenobject.
     */
    public function update_cart_item_price(WC_Cart $cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        foreach ($cart->get_cart() as $cart_item_key => &$cart_item) {
            if (isset($cart_item['calculator_data']['total_price'])) {
                $unit_price = (float) $cart_item['calculator_data']['total_price'];
                $cart_item['data'] = clone $cart_item['data'];
                $cart_item['data']->set_price($unit_price);
                $cart_item['data']->set_tax_class('');
                $cart_item['line_total']    = $unit_price * $cart_item['quantity'];
                $cart_item['line_subtotal'] = $unit_price * $cart_item['quantity'];
            }
        }
    }

    /**
     * Fix voor de minicart-weergave: Zorgt dat de juiste unitprijzen worden gebruikt.
     *
     * @param array $cart_contents De huidige winkelwageninhoud.
     * @return array De bijgewerkte winkelwageninhoud.
     */
    public function fix_minicart_price_display(array $cart_contents): array {
        foreach ($cart_contents as &$cart_item) {
            if (isset($cart_item['calculator_data']['total_price'])) {
                $cart_item['data']->set_price((float) $cart_item['calculator_data']['total_price']);
            }
        }
        return $cart_contents;
    }

    /**
     * Filtert de weergave van de prijs in de winkelwagen.
     *
     * Omdat de calculator_data al per eenheid is opgeslagen, geven we hier de unitprijs weer.
     *
     * @param string $price         De originele prijsweergave.
     * @param array  $cart_item     Het winkelwagenitem.
     * @param string $cart_item_key De sleutel van het winkelwagenitem.
     * @return string De gefilterde prijsweergave.
     */
    public function filter_cart_item_price(string $price, array $cart_item, string $cart_item_key): string {
        if (isset($cart_item['calculator_data']['total_price'])) {
            $unit_price = (float) $cart_item['calculator_data']['total_price'];
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return wc_price($unit_price);
            }
        }
        return $price;
    }

    /**
     * Filtert de weergave van het subtotaal in de winkelwagen.
     *
     * Het subtotaal wordt berekend als de unitprijs vermenigvuldigd met de hoeveelheid.
     *
     * @param string $subtotal      Het originele subtotaal.
     * @param array  $cart_item     Het winkelwagenitem.
     * @param string $cart_item_key De sleutel van het winkelwagenitem.
     * @return string Het gefilterde subtotaal.
     */
    public function filter_cart_item_subtotal(string $subtotal, array $cart_item, string $cart_item_key): string {
        if (isset($cart_item['calculator_data']['total_price'])) {
            $unit_price = (float) $cart_item['calculator_data']['total_price'];
            $line_total = $unit_price * $cart_item['quantity'];
            return wc_price($line_total);
        }
        return $subtotal;
    }

    /**
     * Bereken het winkelwagentotaal op basis van de (aangepaste) line totals.
     *
     * Omdat de line_total voor elk item al de correcte waarde bevat, telt deze functie deze gewoon op.
     *
     * @param float   $total       Het originele totaal.
     * @param WC_Cart $cart_object Het winkelwagenobject.
     * @return float Het aangepaste totaal.
     */
    public function filter_calculated_total(float $total, WC_Cart $cart_object): float {
        $calc_total = 0.0;
        foreach ($cart_object->get_cart() as $cart_item) {
            if (isset($cart_item['calculator_data']['total_price'])) {
                $unit_price = (float) $cart_item['calculator_data']['total_price'];
                $calc_total += $unit_price * $cart_item['quantity'];
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
     * De getoonde waarden (benodigde hoeveelheid, aantal zakken en korting) worden vermenigvuldigd met de winkelwagenhoeveelheid.
     *
     * @param array $item_data  De originele item data.
     * @param array $cart_item  Het winkelwagenitem.
     * @return array De uitgebreide item data.
     */
    public function display_calculator_data_in_cart(array $item_data, array $cart_item): array {
        if (isset($cart_item['calculator_data'])) {
            $calculator_data = $cart_item['calculator_data'];
            $quantity = isset($cart_item['quantity']) ? (int)$cart_item['quantity'] : 1;
            if (isset($calculator_data['needed_kg'])) {
                $item_data[] = [
                    'name'  => __('Benodigde hoeveelheid (kg)', 'egaline'),
                    'value' => sprintf('%.2f kg', $calculator_data['needed_kg'] * $quantity),
                ];
            }
            if (isset($calculator_data['bags'])) {
                $item_data[] = [
                    'name'  => __('Aantal zakken', 'egaline'),
                    'value' => sprintf('%d (%d kg per zak)', $calculator_data['bags'] * $quantity, $calculator_data['kg_per_bag']),
                ];
            }
            if (isset($calculator_data['discount_amount']) && $calculator_data['discount_amount'] > 0) {
                $item_data[] = [
                    'name'  => __('Korting toegepast', 'egaline'),
                    'value' => sprintf('-€ %.2f', $calculator_data['discount_amount'] * $quantity),
                ];
            }
        }
        return $item_data;
    }

    /**
     * Voegt calculatorgegevens toe aan orderitems tijdens de checkout.
     *
     * De waarden worden aangepast aan de hoeveelheid in het orderitem.
     *
     * @param WC_Order_Item_Product $item         Het order item.
     * @param string                $cart_item_key De sleutel van het winkelwagenitem.
     * @param array                 $values        De waarden van het winkelwagenitem.
     * @param WC_Order              $order         Het orderobject.
     */
    public function add_calculator_data_to_order_items(
        WC_Order_Item_Product $item,
        string $cart_item_key,
        array $values,
        WC_Order $order
    ): void {
        if (isset($values['calculator_data'])) {
            $calculator_data = $values['calculator_data'];
            $quantity = isset($values['quantity']) ? (int) $values['quantity'] : 1;
            if (isset($calculator_data['needed_kg'])) {
                $item->add_meta_data(__('Benodigde hoeveelheid (kg)', 'egaline'), sprintf('%.2f kg', $calculator_data['needed_kg'] * $quantity));
            }
            if (isset($calculator_data['bags'])) {
                $item->add_meta_data(__('Aantal zakken', 'egaline'), sprintf('%d (%d kg per zak)', $calculator_data['bags'] * $quantity, $calculator_data['kg_per_bag']));
            }
            if (isset($calculator_data['total_price'])) {
                $item->add_meta_data(__('Totaalprijs', 'egaline'), sprintf('€ %.2f', $calculator_data['total_price'] * $quantity));
            }
            if (isset($calculator_data['discount_amount']) && $calculator_data['discount_amount'] > 0) {
                $item->add_meta_data(__('Korting toegepast', 'egaline'), sprintf('-€ %.2f', $calculator_data['discount_amount'] * $quantity));
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
            $cart_item['data'] = clone wc_get_product($cart_item['product_id']);
            $cart_item['data']->set_price((float) $values['calculator_data']['total_price']);
            if (isset($values['unique_key'])) {
                $cart_item['unique_key'] = $values['unique_key'];
            }
        }
        return $cart_item;
    }
}

// Initialiseer de plugin.
new Egaline_Calculator_Cart();
