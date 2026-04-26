<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!class_exists('Egaline_Calculator_Display')) {
    readonly final class Egaline_Calculator_Display
    {
        public function __construct()
        {
            add_action('woocommerce_before_add_to_cart_button', [$this, 'display_calculator']);
        }

        public function display_calculator(): void
        {
            global $product;

            if (!$this->is_valid_product($product)) {
                return;
            }

            $product_id = $product->get_id();

            if ('yes' !== get_post_meta($product_id, '_enable_calculator', true)) {
                return;
            }

            $metadata = $this->get_product_metadata($product);

            if (!$this->is_valid_metadata($metadata)) {
                $this->display_error_message();
                return;
            }

            $this->load_template($metadata);
        }

        private function is_valid_product(mixed $product): bool
        {
            return $product instanceof WC_Product;
        }

        private function get_product_metadata(WC_Product $product): array
        {
            $product_id = $product->get_id();

            $metadata = [
                'kg_per_bag' => max(1.0, (float) (get_post_meta($product_id, '_kg_per_bag', true) ?: 1.0)),
                'kg_per_mm' => max(0.0, (float) (get_post_meta($product_id, '_kg_per_mm', true) ?: 0.0)),
                'kg_per_m2' => max(0.0, (float) (get_post_meta($product_id, '_kg_per_m2', true) ?: 0.0)),
                'calculation_mode' => (string) (get_post_meta($product_id, '_calculation_mode', true) ?: 'kg_per_mm'),
                'regular_price' => 0.0,
                'variation_id' => '',
                'discount_threshold' => max(0, (int) (get_post_meta($product_id, '_discount_threshold', true) ?: 0)),
                'discount_percentage' => min(100.0, max(0.0, (float) (get_post_meta($product_id, '_discount_percentage', true) ?: 0.0))),
            ];

            if ($product->is_type('variable')) {
                $default_variation = $this->get_default_variation($product);
                if ($default_variation !== null) {
                    $metadata['regular_price'] = (float) ($default_variation['display_price'] ?? $product->get_price());
                    $metadata['variation_id'] = (string) ($default_variation['variation_id'] ?? '');
                }
            } else {
                $metadata['regular_price'] = (float) $product->get_price();
            }

            return $metadata;
        }

        private function get_default_variation(WC_Product $product): ?array
        {
            foreach ($product->get_available_variations() as $variation) {
                if (!empty($variation['is_default'])) {
                    return $variation;
                }
            }

            $variations = $product->get_available_variations();

            return $variations[0] ?? null;
        }

        private function is_valid_metadata(array $metadata): bool
        {
            return !in_array(null, $metadata, true)
                && (float) $metadata['kg_per_bag'] > 0
                && ((float) $metadata['kg_per_mm'] > 0 || (float) $metadata['kg_per_m2'] > 0);
        }

        private function display_error_message(): void
        {
            esc_html_e('Calculator kan niet worden weergegeven vanwege ontbrekende productinstellingen.', 'egaline-calculator');
        }

        private function load_template(array $metadata): void
        {
            $template_path = plugin_dir_path(__FILE__) . '../templates/calculator-display.php';

            if (!file_exists($template_path)) {
                esc_html_e('Calculator-templatebestand niet gevonden.', 'egaline-calculator');
                return;
            }

            $kg_per_bag = $metadata['kg_per_bag'];
            $kg_per_mm = $metadata['kg_per_mm'];
            $kg_per_m2 = $metadata['kg_per_m2'];
            $calculation_mode = $metadata['calculation_mode'];
            $regular_price = $this->format_price((float) $metadata['regular_price']);
            $variation_id = $metadata['variation_id'];
            $discount_threshold = $metadata['discount_threshold'];
            $discount_percentage = $metadata['discount_percentage'];
            $discounted_price = $this->calculate_discounted_price($metadata);
            $show_discounted_price = ($discount_threshold > 0 && $discount_percentage > 0);

            include $template_path;
        }

        private function calculate_discounted_price(array $metadata): string
        {
            $discounted = (float) $metadata['regular_price'];

            if ($metadata['discount_threshold'] > 0 && $metadata['discount_percentage'] > 0) {
                $discounted -= $metadata['regular_price'] * ($metadata['discount_percentage'] / 100);
            }

            return $this->format_price($discounted);
        }

        private function format_price(float $price): string
        {
            return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
        }
    }

    new Egaline_Calculator_Display();
}
