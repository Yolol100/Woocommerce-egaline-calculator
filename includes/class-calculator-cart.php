<?php
declare(strict_types=1);

/**
 * Voeg extra gegevens toe aan de winkelwagen en verwerk calculator-data.
 */
final class Egaline_Calculator_Cart {

    public function __construct() {
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_calculator_data_to_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 99);
        add_filter('woocommerce_get_item_data', [$this, 'display_calculator_data_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_calculator_data_to_order_items'], 10, 4);
        add_filter('woocommerce_cart_item_class', [$this, 'add_cart_item_class'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 10, 3);
        add_filter('woocommerce_calculated_total', [$this, 'filter_calculated_total'], 99, 2);
        add_filter('woocommerce_cart_item_tax_class', [$this, 'filter_cart_item_tax_class'], 10, 3);
        add_filter('woocommerce_cart_id', [$this, 'generate_custom_cart_id'], 10, 5);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item_data'], 20, 2);
        add_filter('woocommerce_get_cart_contents', [$this, 'fix_minicart_price_display'], 20);
    }

    public function add_calculator_data_to_cart(array $cart_item_data, int $product_id): array {
        if (get_post_meta($product_id, '_enable_calculator', true) !== 'yes') {
            return $cart_item_data;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return $cart_item_data;
        }

        $kg_per_bag = (float) (get_post_meta($product_id, '_kg_per_bag', true) ?: 15);
        $kg_per_mm = (float) (get_post_meta($product_id, '_kg_per_mm', true) ?: 0);
        $kg_per_m2 = (float) (get_post_meta($product_id, '_kg_per_m2', true) ?: 0);

        $thickness = isset($_POST['egaline_mm']) ? (float) sanitize_text_field((string) $_POST['egaline_mm']) : 0.0;
        $area = isset($_POST['egaline_m2']) ? (float) sanitize_text_field((string) $_POST['egaline_m2']) : 0.0;

        $needed_kg = ($thickness * $area * $kg_per_mm) + ($area * $kg_per_m2);
        $bags = (int) ceil($needed_kg / $kg_per_bag);

        $regular_price = (float) $product->get_price();
        if (isset($_POST['variation_id']) && (int) $_POST['variation_id'] !== 0) {
            $variation = wc_get_product((int) $_POST['variation_id']);
            if ($variation instanceof WC_Product) {
                $regular_price = (float) $variation->get_price();
            }
        }

        $original_total = $bags * $regular_price;
        $discount_threshold = (int) get_post_meta($product_id, '_discount_threshold', true);
        $discount_percentage = (float) get_post_meta($product_id, '_discount_percentage', true);
        $discount_amount = 0.0;
        $final_total = $original_total;

        if ($discount_threshold > 0 && $bags >= $discount_threshold && $discount_percentage > 0) {
            $discount_amount = $original_total * ($discount_percentage / 100);
            $final_total = $original_total - $discount_amount;
        }

        return array_merge($cart_item_data, [
            'calculator_data' => [
                'needed_kg' => $needed_kg,
                'bags' => $bags,
                'total_price' => $final_total,
                'kg_per_bag' => $kg_per_bag,
                'thickness' => $thickness,
                'area' => $area,
                'discount_threshold' => $discount_threshold,
                'discount_percentage' => $discount_percentage,
                'discount_amount' => $discount_amount,
            ],
            'unique_key' => uniqid('egaline_', true),
        ]);
    }

    public function update_cart_item_price(WC_Cart $cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['calculator_data'])) {
                continue;
            }

            $product = clone $cart_item['data'];
            $unit_price = (float) $cart_item['calculator_data']['total_price'];
            
            $product->set_price($unit_price);
            $product->set_tax_class('');

            $cart->cart_contents[$cart_item_key]['data'] = $product;
            $cart->cart_contents[$cart_item_key]['line_total'] = $unit_price * $cart_item['quantity'];
            $cart->cart_contents[$cart_item_key]['line_subtotal'] = $unit_price * $cart_item['quantity'];
        }
    }

    public function display_calculator_data_in_cart(array $item_data, array $cart_item): array {
        if (!isset($cart_item['calculator_data'])) {
            return $item_data;
        }

        $data = $cart_item['calculator_data'];
        $quantity = $cart_item['quantity'] ?? 1;

        return array_merge($item_data, [
            [
                'name' => __('Benodigde hoeveelheid (kg)', 'egaline'),
                'value' => sprintf('%.2f kg', $data['needed_kg'] * $quantity),
            ],
            [
                'name' => __('Aantal zakken', 'egaline'),
                'value' => sprintf('%d (%d kg per zak)', $data['bags'] * $quantity, $data['kg_per_bag']),
            ],
            ...($data['discount_amount'] > 0 ? [[
                'name' => __('Korting toegepast', 'egaline'),
                'value' => sprintf('-€ %.2f', $data['discount_amount'] * $quantity),
            ]] : []),
        ]);
    }

    public function add_calculator_data_to_order_items(WC_Order_Item_Product $item, string $cart_item_key, array $values, WC_Order $order): void {
        if (!isset($values['calculator_data'])) {
            return;
        }

        $data = $values['calculator_data'];
        $quantity = $values['quantity'] ?? 1;

        $item->update_meta_data(__('Benodigde hoeveelheid (kg)', 'egaline'), sprintf('%.2f kg', $data['needed_kg'] * $quantity));
        $item->update_meta_data(__('Aantal zakken', 'egaline'), sprintf('%d (%d kg per zak)', $data['bags'] * $quantity, $data['kg_per_bag']));
        $item->update_meta_data(__('Totaalprijs', 'egaline'), sprintf('€ %.2f', $data['total_price'] * $quantity));

        if ($data['discount_amount'] > 0) {
            $item->update_meta_data(__('Korting toegepast', 'egaline'), sprintf('-€ %.2f', $data['discount_amount'] * $quantity));
        }
    }

    public function filter_cart_item_price(string $price, array $cart_item, string $cart_item_key): string {
        if (!isset($cart_item['calculator_data'])) {
            return $price;
        }

        $unit_price = (float) $cart_item['calculator_data']['total_price'];
        return wp_doing_ajax() ? wc_price($unit_price) : $price;
    }

    public function filter_calculated_total(float $total, WC_Cart $cart): float {
        return array_reduce($cart->get_cart(), function(float $carry, array $item) {
            return $carry + ($item['calculator_data']['total_price'] ?? $item['data']->get_price()) * $item['quantity'];
        }, 0.0);
    }

    public function generate_custom_cart_id(string $cart_id, int $product_id, int $variation_id, array $variation, array $cart_item_data): string {
        return isset($cart_item_data['calculator_data'])
            ? $cart_id . '_' . hash('xxh128', json_encode($cart_item_data['calculator_data']))
            : $cart_id;
    }

    public function restore_cart_item_data(array $cart_item, array $values): array {
        if (isset($values['calculator_data'])) {
            $cart_item['calculator_data'] = $values['calculator_data'];
            $cart_item['data'] = clone wc_get_product($cart_item['product_id']);
            $cart_item['data']->set_price((float) $values['calculator_data']['total_price']);
        }
        return $cart_item;
    }

    public function add_cart_item_class(string $class, array $cart_item, string $cart_item_key): string {
        return isset($cart_item['calculator_data']) ? "{$class} has-calculator-price" : $class;
    }

    public function filter_cart_item_subtotal(string $subtotal, array $cart_item, string $cart_item_key): string {
        return isset($cart_item['calculator_data'])
            ? wc_price($cart_item['calculator_data']['total_price'] * $cart_item['quantity'])
            : $subtotal;
    }

    public function filter_cart_item_tax_class(string $tax_class, array $cart_item, string $cart_item_key): string {
        return isset($cart_item['calculator_data']) ? '' : $tax_class;
    }

    public function fix_minicart_price_display(array $cart_contents): array {
        array_walk($cart_contents, function(array &$item) {
            if (isset($item['calculator_data'])) {
                $item['data']->set_price((float) $item['calculator_data']['total_price']);
            }
        });
        return $cart_contents;
    }
}

new Egaline_Calculator_Cart();
