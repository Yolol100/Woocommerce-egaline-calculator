<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

readonly final class Egaline_Calculator_Cart
{
    public function __construct()
    {
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_calculator_data_to_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 99);
        add_filter('woocommerce_get_item_data', [$this, 'display_calculator_data_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_calculator_data_to_order_items'], 10, 4);
        add_action('woocommerce_cart_item_class', [$this, 'add_cart_item_class'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 10, 3);
        add_filter('woocommerce_calculated_total', [$this, 'filter_calculated_total'], 99, 2);
        add_filter('woocommerce_cart_item_tax_class', [$this, 'filter_cart_item_tax_class'], 10, 3);
        add_filter('woocommerce_cart_id', [$this, 'custom_cart_id'], 10, 4);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item_data'], 20, 2);
        add_filter('woocommerce_get_cart_contents', [$this, 'fix_minicart_price_display'], 20, 1);
    }

    public function custom_cart_id(string $cart_id, int $product_id, int $variation_id, array $cart_item_data): string
    {
        if (!empty($cart_item_data['calculator_data'])) {
            $data = $cart_item_data['calculator_data'];
            $hash_input = implode('|', [
                $product_id,
                number_format((float) $data['thickness'], 2, '.', ''),
                number_format((float) $data['area'], 2, '.', ''),
                number_format((float) $data['total_price'], 2, '.', ''),
            ]);
            $cart_id .= '_' . md5($hash_input);
        }
        return $cart_id;
    }

    public function add_calculator_data_to_cart(array $cart_item_data, int $product_id): array
    {
        if (get_post_meta($product_id, '_enable_calculator', true) !== 'yes') {
            return $cart_item_data;
        }

        $kg_per_bag = (float) get_post_meta($product_id, '_kg_per_bag', true) ?: 15.0;
        $kg_per_mm = (float) get_post_meta($product_id, '_kg_per_mm', true) ?: 0.0;
        $kg_per_m2 = (float) get_post_meta($product_id, '_kg_per_m2', true) ?: 0.0;

        $thickness = isset($_POST['egaline_mm'])
            ? (float) sanitize_text_field((string) $_POST['egaline_mm'])
            : 0.0;
        $area = isset($_POST['egaline_m2'])
            ? (float) sanitize_text_field((string) $_POST['egaline_m2'])
            : 0.0;

        $needed_kg = ($thickness * $area * $kg_per_mm) + ($area * $kg_per_m2);
        $bags = (int) ceil($needed_kg / $kg_per_bag);

        $product = wc_get_product($product_id);
        $regular_price = (float) $product->get_price();

        if (isset($_POST['variation_id']) && (int) $_POST['variation_id'] !== 0) {
            $variation = wc_get_product((int) $_POST['variation_id']);
            if ($variation) {
                $regular_price = (float) $variation->get_price();
            }
        }

        $original_total = $bags * $regular_price;
        $discount_threshold = (int) get_post_meta($product_id, '_discount_threshold', true);
        $discount_percentage = (float) get_post_meta($product_id, '_discount_percentage', true);
        $discount_amount = 0.0;
        $final_total = $original_total;

        if ($bags >= $discount_threshold && $discount_percentage > 0.0) {
            $discount_amount = $original_total * ($discount_percentage / 100);
            $final_total = $original_total - $discount_amount;
        }

        $cart_item_data['calculator_data'] = [
            'needed_kg' => $needed_kg,
            'bags' => $bags,
            'total_price' => $final_total,
            'kg_per_bag' => $kg_per_bag,
            'thickness' => $thickness,
            'area' => $area,
            'discount_threshold' => $discount_threshold,
            'discount_percentage' => $discount_percentage,
            'discount_amount' => $discount_amount,
        ];

        return $cart_item_data;
    }

    public function update_cart_item_price(WC_Cart $cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        foreach ($cart->get_cart() as &$cart_item) {
            if (isset($cart_item['calculator_data']['total_price'])) {
                $unit_price = (float) $cart_item['calculator_data']['total_price'];
                $cart_item['data'] = clone $cart_item['data'];
                $cart_item['data']->set_price($unit_price);
                $cart_item['data']->set_tax_class('');
                $cart_item['line_total'] = $unit_price * $cart_item['quantity'];
                $cart_item['line_subtotal'] = $unit_price * $cart_item['quantity'];
            }
        }
    }

    public function fix_minicart_price_display(array $cart_contents): array
    {
        foreach ($cart_contents as &$cart_item) {
            if (isset($cart_item['calculator_data']['total_price'])) {
                $cart_item['data']->set_price((float) $cart_item['calculator_data']['total_price']);
            }
        }
        return $cart_contents;
    }

    public function filter_cart_item_price(string $price, array $cart_item, string $cart_item_key): string
    {
        if (isset($cart_item['calculator_data']['total_price'])) {
            $unit_price = (float) $cart_item['calculator_data']['total_price'];
            if (defined('DOING_AJAX') && DOING_AJAX) {
                return wc_price($unit_price);
            }
        }
        return $price;
    }

    public function filter_cart_item_subtotal(string $subtotal, array $cart_item, string $cart_item_key): string
    {
        if (isset($cart_item['calculator_data']['total_price'])) {
            $unit_price = (float) $cart_item['calculator_data']['total_price'];
            $line_total = $unit_price * $cart_item['quantity'];
            return wc_price($line_total);
        }
        return $subtotal;
    }

    public function filter_calculated_total(float $total, WC_Cart $cart_object): float
    {
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

    public function filter_cart_item_tax_class(string $tax_class, array $cart_item, string $cart_item_key): string
    {
        return isset($cart_item['calculator_data']) ? '' : $tax_class;
    }

    public function add_cart_item_class(string $class, array $cart_item, string $cart_item_key): string
    {
        if (isset($cart_item['calculator_data'])) {
            $class .= ' has-calculator-price';
        }
        return $class;
    }

    public function display_calculator_data_in_cart(array $item_data, array $cart_item): array
    {
        if (isset($cart_item['calculator_data'])) {
            $d = $cart_item['calculator_data'];
            $q = (int) ($cart_item['quantity'] ?? 1);

            if (isset($d['needed_kg'])) {
                $item_data[] = [
                    'name' => __('Benodigde hoeveelheid (kg)', 'egaline-calculator'),
                    'value' => sprintf('%.2f kg', $d['needed_kg'] * $q),
                ];
            }

            if (isset($d['bags'])) {
                $item_data[] = [
                    'name' => __('Aantal zakken', 'egaline-calculator'),
                    'value' => sprintf('%d (%d kg per zak)', $d['bags'] * $q, $d['kg_per_bag']),
                ];
            }

            if (isset($d['discount_amount']) && $d['discount_amount'] > 0) {
                $item_data[] = [
                    'name' => __('Korting toegepast', 'egaline-calculator'),
                    'value' => sprintf('-€ %.2f', $d['discount_amount'] * $q),
                ];
            }
        }
        return $item_data;
    }

    public function add_calculator_data_to_order_items(
        WC_Order_Item_Product $item,
        string $cart_item_key,
        array $values,
        WC_Order $order
    ): void {
        if (isset($values['calculator_data'])) {
            $d = $values['calculator_data'];
            $q = (int) ($values['quantity'] ?? 1);

            if (isset($d['needed_kg'])) {
                $item->add_meta_data(__('Benodigde hoeveelheid (kg)', 'egaline-calculator'), sprintf('%.2f kg', $d['needed_kg'] * $q));
            }

            if (isset($d['bags'])) {
                $item->add_meta_data(__('Aantal zakken', 'egaline-calculator'), sprintf('%d (%d kg per zak)', $d['bags'] * $q, $d['kg_per_bag']));
            }

            if (isset($d['total_price'])) {
                $item->add_meta_data(__('Totaalprijs', 'egaline-calculator'), sprintf('€ %.2f', $d['total_price'] * $q));
            }

            if (isset($d['discount_amount']) && $d['discount_amount'] > 0) {
                $item->add_meta_data(__('Korting toegepast', 'egaline-calculator'), sprintf('-€ %.2f', $d['discount_amount'] * $q));
            }
        }
    }

    public function restore_cart_item_data(array $cart_item, array $values): array
    {
        if (isset($values['calculator_data'])) {
            $cart_item['calculator_data'] = $values['calculator_data'];
            $cart_item['data'] = clone wc_get_product($cart_item['product_id']);
            $cart_item['data']->set_price((float) $values['calculator_data']['total_price']);
        }
        return $cart_item;
    }
}

new Egaline_Calculator_Cart();