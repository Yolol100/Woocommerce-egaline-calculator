<?php
declare(strict_types=1);

if (!class_exists('Egaline_Calculator_Display')) {
    final class Egaline_Calculator_Display
    {
        /**
         * Constructor.
         *
         * Zorgt ervoor dat de calculator vóór het add-to-cart formulier getoond wordt.
         */
        public function __construct()
        {
            add_action('woocommerce_before_add_to_cart_form', [$this, 'display_calculator']);
        }

        /**
         * Controleert of het product geldig is, of de calculator ingeschakeld is en laadt vervolgens het template.
         *
         * @return void
         */
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

            /** @var array{
             *   kg_per_bag: float,
             *   kg_per_mm: float,
             *   kg_per_m2: float,
             *   calculation_mode: string,
             *   regular_price: float,
             *   variation_id: string,
             *   discount_threshold: int,
             *   discount_percentage: float
             * } $metadata */
            $metadata = $this->get_product_metadata($product);
            if (!$this->is_valid_metadata($metadata)) {
                $this->display_error_message();
                return;
            }

            $this->load_template($metadata);
        }

        /**
         * Controleert of $product een geldig WooCommerce-product is.
         *
         * @param mixed $product
         * @return bool
         */
        private function is_valid_product($product): bool
        {
            return $product instanceof WC_Product;
        }

        /**
         * Haalt alle benodigde metadata op.
         *
         * We halen onder meer de “kg per bag”, “kg per mm”, “kg per m²” en (voor variabele producten)
         * de standaardprijs en variation_id op.
         *
         * @param WC_Product $product
         * @return array{
         *   kg_per_bag: float,
         *   kg_per_mm: float,
         *   kg_per_m2: float,
         *   calculation_mode: string,
         *   regular_price: float,
         *   variation_id: string,
         *   discount_threshold: int,
         *   discount_percentage: float
         * }
         */
        private function get_product_metadata(WC_Product $product): array
        {
            $product_id = $product->get_id();
            $metadata = [
                'kg_per_bag'       => max(1, (float) (get_post_meta($product_id, '_kg_per_bag', true) ?? 1)),
                'kg_per_mm'        => (float) (get_post_meta($product_id, '_kg_per_mm', true) ?? 0.0),
                'kg_per_m2'        => (float) (get_post_meta($product_id, '_kg_per_m2', true) ?? 0.0),
                'calculation_mode' => (string) (get_post_meta($product_id, '_calculation_mode', true) ?? 'kg_per_mm'),
                'regular_price'    => 0, // Wordt hieronder ingesteld
                'variation_id'     => '',
                'discount_threshold' => (int) (get_post_meta($product_id, '_discount_threshold', true) ?? 0),
                'discount_percentage' => (float) (get_post_meta($product_id, '_discount_percentage', true) ?? 0.0),
            ];

            if ($product->is_type('variable')) {
                $default_variation = $this->get_default_variation($product);
                if ($default_variation) {
                    $metadata['regular_price'] = (float) ($default_variation['display_price'] ?? $product->get_price());
                    $metadata['variation_id']  = (string) ($default_variation['variation_id'] ?? '');
                }
            } else {
                $metadata['regular_price'] = (float) $product->get_price();
            }

            return $metadata;
        }

        /**
         * Haalt de standaard variatie op voor een variabel product.
         *
         * @param WC_Product $product
         * @return array<string, mixed>|null
         */
        private function get_default_variation(WC_Product $product): ?array
        {
            foreach ($product->get_available_variations() as $variation) {
                if (!empty($variation['is_default'])) {
                    return $variation;
                }
            }
            return $product->get_available_variations()[0] ?? null;
        }

        /**
         * Controleert of alle vereiste metadata aanwezig is.
         *
         * @param array $metadata
         * @return bool
         */
        private function is_valid_metadata(array $metadata): bool
        {
            return !in_array(null, $metadata, true);
        }

        /**
         * Toont een foutmelding wanneer de calculator niet geladen kan worden.
         *
         * @return void
         */
        private function display_error_message(): void
        {
            esc_html_e('Calculator kan niet worden weergegeven vanwege ontbrekende productinstellingen.', 'egaline');
        }

        /**
         * Laadt het templatebestand en maakt de metadata-variabelen beschikbaar.
         *
         * @param array{
         *   kg_per_bag: float,
         *   kg_per_mm: float,
         *   kg_per_m2: float,
         *   calculation_mode: string,
         *   regular_price: float,
         *   variation_id: string,
         *   discount_threshold: int,
         *   discount_percentage: float
         * } $metadata
         * @return void
         */
        private function load_template(array $metadata): void
        {
            $template_path = plugin_dir_path(__FILE__) . '../templates/calculator-display.php';
            if (!file_exists($template_path)) {
                esc_html_e('Calculator-templatebestand niet gevonden.', 'egaline');
                return;
            }

            // Bereken de initiële weergave voor de korting.
            $discounted_price = $this->calculate_discounted_price($metadata);
            $show_discounted_price = ($metadata['discount_threshold'] > 0 && $metadata['discount_percentage'] > 0);

            // Format de prijzen zodat er geen onnodige nullen achter de komma of punt verschijnen.
            $metadata['regular_price'] = $this->format_price((float) $metadata['regular_price']);
            $metadata['discounted_price'] = $discounted_price;

            extract($metadata);
            include $template_path;
        }

        /**
         * Bereken de prijs na korting.
         *
         * @param array{
         *   regular_price: float,
         *   discount_threshold: int,
         *   discount_percentage: float
         * } $metadata
         * @return string
         */
        private function calculate_discounted_price(array $metadata): string
        {
            $discounted = (float) $metadata['regular_price'];
            if ($metadata['discount_threshold'] > 0 && $metadata['discount_percentage'] > 0) {
                $discounted -= $metadata['regular_price'] * ($metadata['discount_percentage'] / 100);
            }
            return $this->format_price($discounted);
        }

        /**
         * Formatteert een prijs zodat er geen onnodige nullen achter de komma of punt verschijnen.
         *
         * @param float $price
         * @return string
         */
        private function format_price(float $price): string
        {
            return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
        }
    }

    new Egaline_Calculator_Display();
}