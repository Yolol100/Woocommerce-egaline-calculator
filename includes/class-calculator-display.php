<?php
if (!class_exists('Egaline_Calculator_Display')) {
    final class Egaline_Calculator_Display {
        private $template_path;
        private $plugin_path;

        public function __construct($plugin_path = __DIR__) {
            $this->plugin_path = $plugin_path;
            $this->template_path = "{$this->plugin_path}/../templates/calculator-display.php";
            add_action('woocommerce_before_add_to_cart_form', [$this, 'display_calculator']);
        }

        public function display_calculator() {
            global $product;

            if (!$this->is_valid_product($product)) return;

            $product_id = $product->get_id();
            if (get_post_meta($product_id, '_enable_calculator', true) !== 'yes') return;

            $metadata = $this->get_product_metadata($product);
            if (!$this->is_valid_metadata($metadata)) {
                $this->display_error_message();
                return;
            }

            $this->render_template($metadata);
        }

        private function is_valid_product($product) {
            return $product instanceof WC_Product;
        }

        private function get_product_metadata($product) {
            $product_id = $product->get_id();
            $metadata = [
                'kg_per_bag' => max(1, (float) get_post_meta($product_id, '_kg_per_bag', true)),
                'kg_per_mm' => (float) (get_post_meta($product_id, '_kg_per_mm', true) ?: 0),
                'kg_per_m2' => (float) (get_post_meta($product_id, '_kg_per_m2', true) ?: 0),
                'calculation_mode' => get_post_meta($product_id, '_calculation_mode', true) ?: 'kg_per_mm',
                'regular_price' => 0,
                'variation_id' => '',
                'discount_threshold' => (int) (get_post_meta($product_id, '_discount_threshold', true) ?: 0),
                'discount_percentage' => (float) (get_post_meta($product_id, '_discount_percentage', true) ?: 0.0),
            ];

            if ($product->is_type('variable')) {
                $default_variation = $this->get_default_variation($product);
                if ($default_variation) {
                    $metadata['regular_price'] = (float) $default_variation['display_price'];
                    $metadata['variation_id'] = (string) $default_variation['variation_id'];
                }
            } else {
                $metadata['regular_price'] = (float) $product->get_price();
            }

            return $metadata;
        }

        private function get_default_variation($product) {
            $variations = $product->get_available_variations();
            $filtered = array_filter($variations, function($v) {
                return !empty($v['is_default']);
            });
            return !empty($filtered) ? reset($filtered) : ($variations[0] ?? null);
        }

        private function is_valid_metadata($metadata) {
            return !in_array(null, $metadata, true);
        }

        private function display_error_message() {
            echo '<p class="error">', 
                esc_html__('Calculator kan niet worden weergegeven vanwege ontbrekende productinstellingen.', 'egaline'), 
                '</p>';
        }

        private function render_template($metadata) {
            if (!file_exists($this->template_path)) {
                $this->display_template_error();
                return;
            }

            $price = $metadata['regular_price'];
            $threshold = $metadata['discount_threshold'];
            $percentage = $metadata['discount_percentage'];

            $template_data = [
                'metadata' => $metadata,
                'discounted_price' => $this->calculate_discounted_price($price, $threshold, $percentage),
                'show_discounted' => $threshold > 0 && $percentage > 0,
                'formatted_prices' => [
                    'regular' => $this->format_price($price),
                    'discounted' => $this->format_price($price - ($price * ($percentage / 100)))
                ]
            ];

            extract($template_data, EXTR_SKIP);
            include $this->template_path;
        }

        private function calculate_discounted_price($price, $threshold, $percentage) {
            return $threshold > 0 && $percentage > 0 
                ? $price - ($price * ($percentage / 100))
                : $price;
        }

        private function format_price($value) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }

        private function display_template_error() {
            echo '<p class="error">',
                esc_html__('Calculator-templatebestand niet gevonden.', 'egaline'),
                '</p>';
        }
    }

    new Egaline_Calculator_Display();
}
