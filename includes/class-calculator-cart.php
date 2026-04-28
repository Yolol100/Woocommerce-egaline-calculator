<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

readonly final class Egaline_Calculator_Cart
{
    public function __construct()
    {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_calculator_add_to_cart'], 10, 4);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_calculator_data_to_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 99);
        add_filter('woocommerce_get_item_data', [$this, 'display_calculator_data_in_cart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_calculator_data_to_order_items'], 10, 4);
        add_action('woocommerce_cart_item_class', [$this, 'add_cart_item_class'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'filter_cart_item_price'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'filter_cart_item_subtotal'], 10, 3);
        add_filter('woocommerce_cart_id', [$this, 'custom_cart_id'], 10, 5);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'restore_cart_item_data'], 20, 2);
        add_filter('woocommerce_get_cart_contents', [$this, 'fix_minicart_price_display'], 20, 1);
    }

    public function validate_calculator_add_to_cart(bool $passed, int $product_id, int $quantity, int $variation_id = 0): bool
    {
        if (!$passed || get_post_meta($product_id, '_enable_calculator', true) !== 'yes') {
            return $passed;
        }

        $calculator_data = $this->build_calculator_data_from_request($product_id, $variation_id, $quantity);

        if ($calculator_data !== null && $this->is_valid_calculator_data($calculator_data)) {
            return true;
        }

        if (!$this->get_bool_setting('require_valid_calculation', true)) {
            return true;
        }

        wc_add_notice(
            __('Vul geldige calculatorwaarden in voordat u dit product toevoegt aan de winkelwagen.', 'egaline-calculator'),
            'error'
        );

        return false;
    }

    public function custom_cart_id(string $cart_id, int $product_id, int $variation_id, array $variation, array $cart_item_data): string
    {
        if (!empty($cart_item_data['calculator_data'])) {
            $data = $cart_item_data['calculator_data'];
            $hash_input = implode('|', [
                $product_id,
                $variation_id,
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

        $variation_id = $this->read_int_from_post('variation_id');
        $quantity = max(1, $this->read_int_from_post('quantity') ?: 1);
        $calculator_data = $this->build_calculator_data_from_request($product_id, $variation_id, $quantity);

        if ($calculator_data !== null && $this->is_valid_calculator_data($calculator_data)) {
            $cart_item_data['calculator_data'] = $calculator_data;
        }

        return $cart_item_data;
    }

    public function update_cart_item_price(WC_Cart $cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as &$cart_item) {
            if (!$this->cart_item_has_valid_calculator_data($cart_item)) {
                continue;
            }

            $quantity = max(1, (int) ($cart_item['quantity'] ?? 1));
            $cart_item['calculator_data'] = $this->recalculate_calculator_data_for_quantity(
                $cart_item['calculator_data'],
                $quantity
            );
            $cart_item['data'] = clone $cart_item['data'];
            $cart_item['data']->set_price((float) $cart_item['calculator_data']['total_price']);
        }
        unset($cart_item);
    }

    public function fix_minicart_price_display(array $cart_contents): array
    {
        foreach ($cart_contents as &$cart_item) {
            if ($this->cart_item_has_valid_calculator_data($cart_item)) {
                $quantity = max(1, (int) ($cart_item['quantity'] ?? 1));
                $cart_item['calculator_data'] = $this->recalculate_calculator_data_for_quantity(
                    $cart_item['calculator_data'],
                    $quantity
                );
                $cart_item['data'] = clone $cart_item['data'];
                $cart_item['data']->set_price((float) $cart_item['calculator_data']['total_price']);
            }
        }
        unset($cart_item);
        return $cart_contents;
    }

    public function filter_cart_item_price(string $price, array $cart_item, string $cart_item_key): string
    {
        if ($this->cart_item_has_valid_calculator_data($cart_item) && defined('DOING_AJAX') && DOING_AJAX) {
            return wc_price((float) $cart_item['calculator_data']['total_price']);
        }
        return $price;
    }

    public function filter_cart_item_subtotal(string $subtotal, array $cart_item, string $cart_item_key): string
    {
        if ($this->cart_item_has_valid_calculator_data($cart_item)) {
            $unit_price = (float) $cart_item['calculator_data']['total_price'];
            $line_total = $unit_price * (int) $cart_item['quantity'];
            return wc_price($line_total);
        }
        return $subtotal;
    }

    public function add_cart_item_class(string $class, array $cart_item, string $cart_item_key): string
    {
        if ($this->cart_item_has_valid_calculator_data($cart_item)) {
            $class .= ' has-calculator-price';
        }
        return $class;
    }

    public function display_calculator_data_in_cart(array $item_data, array $cart_item): array
    {
        if ((is_cart() && !$this->get_bool_setting('show_cart_data', true)) || (is_checkout() && !$this->get_bool_setting('show_checkout_data', true))) {
            return $item_data;
        }

        if ($this->cart_item_has_valid_calculator_data($cart_item)) {
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
        if (!$this->get_bool_setting('show_order_data', true)) {
            return;
        }

        if ($this->cart_item_has_valid_calculator_data($values)) {
            $d = $values['calculator_data'];
            $q = (int) ($values['quantity'] ?? 1);
            $order_label = $this->get_string_setting('order_label', __('Egaline berekening', 'egaline-calculator'));
            if ($order_label !== '') {
                $item->add_meta_data($order_label, __('Ja', 'egaline-calculator'));
            }

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
        if (isset($values['calculator_data']) && $this->is_valid_calculator_data($values['calculator_data'])) {
            $cart_item['calculator_data'] = $values['calculator_data'];
            $product = wc_get_product($cart_item['variation_id'] ?: $cart_item['product_id']);

            if ($product instanceof WC_Product) {
                $quantity = max(1, (int) ($cart_item['quantity'] ?? 1));
                $cart_item['calculator_data'] = $this->recalculate_calculator_data_for_quantity(
                    $cart_item['calculator_data'],
                    $quantity
                );
                $cart_item['data'] = clone $product;
                $cart_item['data']->set_price((float) $cart_item['calculator_data']['total_price']);
            }
        }
        return $cart_item;
    }

    private function build_calculator_data_from_request(int $product_id, int $variation_id = 0, int $quantity = 1): ?array
    {
        $kg_per_bag = (float) get_post_meta($product_id, '_kg_per_bag', true);
        $kg_per_mm = (float) get_post_meta($product_id, '_kg_per_mm', true);
        $kg_per_m2 = (float) get_post_meta($product_id, '_kg_per_m2', true);

        if ($kg_per_bag <= 0 || ($kg_per_mm <= 0 && $kg_per_m2 <= 0)) {
            return null;
        }

        $thickness = $this->read_decimal_from_post('egaline_mm');
        $area = $this->read_decimal_from_post('egaline_m2');

        if ($thickness === null || $area === null || $thickness <= 0 || $area <= 0) {
            return null;
        }

        if ($thickness > $this->get_float_setting('max_mm', 100000.0) || $area > $this->get_float_setting('max_m2', 100000.0)) {
            return null;
        }

        $needed_kg = ($thickness * $area * $kg_per_mm) + ($area * $kg_per_m2);
        $raw_bags = $needed_kg / $kg_per_bag;
        $bags = $this->get_bool_setting('round_up_bags', true) ? (int) ceil($raw_bags) : (int) round($raw_bags);
        $bags = min($this->get_int_setting('max_bags', 100000), max($this->get_int_setting('min_bags', 1), $bags));

        if ($needed_kg <= 0 || $bags < 1) {
            return null;
        }

        $product = wc_get_product($variation_id > 0 ? $variation_id : $product_id);
        if (!$product instanceof WC_Product) {
            return null;
        }

        $regular_price = (float) $product->get_price();
        if ($regular_price <= 0) {
            return null;
        }

        $quantity = max(1, $quantity);
        $original_total = $bags * $regular_price;
        $discount_threshold = max(0, (int) get_post_meta($product_id, '_discount_threshold', true));
        $discount_percentage = max(0.0, (float) get_post_meta($product_id, '_discount_percentage', true));
        $discount_percentage = min(100.0, $discount_percentage);
        $discount_amount_total = 0.0;
        $final_total_total = $original_total * $quantity;

        if ($discount_threshold > 0 && ($bags * $quantity) >= $discount_threshold && $discount_percentage > 0.0) {
            $discount_amount_total = $final_total_total * ($discount_percentage / 100);
            $final_total_total -= $discount_amount_total;
        }

        $final_total = $final_total_total / $quantity;
        $discount_amount = $discount_amount_total / $quantity;

        if ($final_total <= 0) {
            return null;
        }

        return [
            'needed_kg' => $needed_kg,
            'bags' => $bags,
            'total_price' => $final_total,
            'regular_price' => $regular_price,
            'original_total' => $original_total,
            'kg_per_bag' => $kg_per_bag,
            'thickness' => $thickness,
            'area' => $area,
            'discount_threshold' => $discount_threshold,
            'discount_percentage' => $discount_percentage,
            'discount_amount' => $discount_amount,
        ];
    }

    private function recalculate_calculator_data_for_quantity(array $data, int $quantity): array
    {
        $quantity = max(1, $quantity);
        $bags = max(1, (int) ($data['bags'] ?? 1));
        $regular_price = isset($data['regular_price']) ? (float) $data['regular_price'] : 0.0;

        if ($regular_price <= 0.0) {
            return $data;
        }

        $original_total = $bags * $regular_price;
        $line_original_total = $original_total * $quantity;
        $discount_threshold = max(0, (int) ($data['discount_threshold'] ?? 0));
        $discount_percentage = min(100.0, max(0.0, (float) ($data['discount_percentage'] ?? 0.0)));
        $discount_amount_total = 0.0;

        if ($discount_threshold > 0 && ($bags * $quantity) >= $discount_threshold && $discount_percentage > 0.0) {
            $discount_amount_total = $line_original_total * ($discount_percentage / 100);
        }

        $line_final_total = max(0.0, $line_original_total - $discount_amount_total);
        $data['original_total'] = $original_total;
        $data['total_price'] = $line_final_total / $quantity;
        $data['discount_amount'] = $discount_amount_total / $quantity;

        return $data;
    }

    private function read_decimal_from_post(string $key): ?float
    {
        if (!isset($_POST[$key])) {
            return null;
        }

        $raw = wc_clean(wp_unslash($_POST[$key]));
        $value = is_scalar($raw) ? str_replace(',', '.', (string) $raw) : '';

        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (!is_finite($number) || $number < 0 || $number > 100000) {
            return null;
        }

        return $number;
    }

    private function read_int_from_post(string $key): int
    {
        if (!isset($_POST[$key])) {
            return 0;
        }

        $raw = wc_clean(wp_unslash($_POST[$key]));
        return is_numeric($raw) ? absint($raw) : 0;
    }


    private function get_settings(): array
    {
        $defaults = [
            'show_cart_data' => true,
            'show_checkout_data' => true,
            'show_order_data' => true,
            'require_valid_calculation' => true,
            'round_up_bags' => true,
            'min_bags' => 1,
            'max_bags' => 100000,
            'max_m2' => 100000.0,
            'max_mm' => 100000.0,
            'order_label' => __('Egaline berekening', 'egaline-calculator'),
        ];
        $stored = get_option('egaline_calculator_settings', []);
        return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
    }

    private function get_bool_setting(string $key, bool $default): bool
    {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? (bool) $settings[$key] : $default;
    }

    private function get_int_setting(string $key, int $default): int
    {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? max(0, absint($settings[$key])) : $default;
    }

    private function get_float_setting(string $key, float $default): float
    {
        $settings = $this->get_settings();
        return isset($settings[$key]) && is_numeric($settings[$key]) ? (float) $settings[$key] : $default;
    }

    private function get_string_setting(string $key, string $default): string
    {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? (string) $settings[$key] : $default;
    }

    private function cart_item_has_valid_calculator_data(array $cart_item): bool
    {
        return isset($cart_item['calculator_data']) && $this->is_valid_calculator_data($cart_item['calculator_data']);
    }

    private function is_valid_calculator_data(array $data): bool
    {
        return isset($data['needed_kg'], $data['bags'], $data['total_price'], $data['kg_per_bag'], $data['thickness'], $data['area'])
            && (float) $data['needed_kg'] > 0
            && (int) $data['bags'] >= 1
            && (float) $data['total_price'] > 0
            && (float) $data['kg_per_bag'] > 0
            && (float) $data['thickness'] > 0
            && (float) $data['area'] > 0;
    }
}

new Egaline_Calculator_Cart();
