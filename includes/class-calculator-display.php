<?php
declare(strict_types=1);

if (!class_exists(Egaline_Calculator_Display::class)) {
    final class Egaline_Calculator_Display {

        /**
         * Constructor.
         *
         * Zorgt ervoor dat de calculator vóór het add-to-cart formulier getoond wordt.
         */
        public function __construct() {
            add_action('woocommerce_before_add_to_cart_form', [$this, 'display_calculator']);
        }

        /**
         * Controleert of het product geldig is, of de calculator ingeschakeld is en laadt vervolgens het template.
         *
         * @return void
         */
        public function display_calculator(): void {
            global $product;

            // Controleer of het product een geldig WooCommerce-product is.
            if (!$this->is_valid_product($product)) {
                return;
            }

            $product_id = $product->get_id();

            // Controleer of de calculator voor dit product is ingeschakeld.
            $enabled = get_post_meta($product_id, '_enable_calculator', true);
            if ('yes' !== $enabled) {
                return;
            }

            // Haal de benodigde metadata op en controleer of alle vereiste waarden aanwezig zijn.
            $metadata = $this->get_product_metadata($product);
            if (!$this->is_valid_metadata($metadata)) {
                $this->display_error_message();
                return;
            }

            // Laad de template (de template bevat de container en form)
            $this->load_template($metadata);
        }

        /**
         * Controleert of $product een geldig WooCommerce-product is.
         *
         * @param mixed $product
         * @return bool
         */
        private function is_valid_product($product): bool {
            return $product instanceof WC_Product;
        }

        /**
         * Haalt alle benodigde metadata op.
         *
         * We halen onder meer de “kg per bag”, “kg per mm”, “kg per m²” en (voor variabele producten)
         * de standaardprijs en variation_id op.
         *
         * @param WC_Product $product
         * @return array
         */
        private function get_product_metadata(WC_Product $product): array {
            $product_id = $product->get_id();
            $metadata = [
                'kg_per_bag'       => max(1, (float) get_post_meta($product_id, '_kg_per_bag', true)),
                'kg_per_mm'        => (float) get_post_meta($product_id, '_kg_per_mm', true) ?: 0,
                'kg_per_m2'        => (float) get_post_meta($product_id, '_kg_per_m2', true) ?: 0,
                'calculation_mode' => get_post_meta($product_id, '_calculation_mode', true) ?: 'kg_per_mm',
                'regular_price'    => 0,  // Wordt hieronder ingesteld
                'variation_id'     => '',
            ];

            if ($product->is_type('variable')) {
                // Zoek naar de default variatie via het is_default attribuut.
                $default_variation = $this->get_default_variation($product);
                if ($default_variation) {
                    $metadata['regular_price'] = (float) $default_variation['display_price'];
                    $metadata['variation_id']  = $default_variation['variation_id'];
                }
            } else {
                $metadata['regular_price'] = (float) $product->get_price();
            }

            // Toegevoegde discount metadata:
            $metadata['discount_threshold'] = (int) get_post_meta($product_id, '_discount_threshold', true) ?: 0;
            $metadata['discount_percentage'] = (float) get_post_meta($product_id, '_discount_percentage', true) ?: 0.0;

            return $metadata;
        }

        /**
         * Haalt de standaard variatie op voor een variabel product.
         *
         * @param WC_Product $product
         * @return array|null
         */
        private function get_default_variation(WC_Product $product): ?array {
            foreach ($product->get_available_variations() as $variation) {
                if (!empty($variation['is_default'])) {
                    return $variation;
                }
            }

            // Als er geen default is, gebruik de eerste beschikbare variatie.
            return reset($product->get_available_variations()) ?: null;
        }

        /**
         * Controleert of alle vereiste metadata aanwezig is.
         *
         * @param array $metadata
         * @return bool
         */
        private function is_valid_metadata(array $metadata): bool {
            return !in_array(null, $metadata, true);
        }

        /**
         * Toont een foutmelding wanneer de calculator niet geladen kan worden.
         *
         * @return void
         */
        private function display_error_message(): void {
            esc_html_e('Calculator kan niet worden weergegeven vanwege ontbrekende productinstellingen.', 'egaline');
        }

        /**
         * Laadt het templatebestand en maakt de metadata-variabelen beschikbaar.
         *
         * @param array $metadata
         * @return void
         */
        private function load_template(array $metadata): void {
            $template_path = plugin_dir_path(__FILE__) . '../templates/calculator-display.php';
            if (file_exists($template_path)) {
                // Maak de metadata beschikbaar in het template.
                extract($metadata);

                // Bereken de initiële weergave voor de korting.
                $discounted_price = $regular_price;
                $show_discounted_price = false;
                
                if ($discount_threshold > 0 && $discount_percentage > 0) {
                    $discounted_price = $regular_price - ($regular_price * ($discount_percentage / 100));
                    $show_discounted_price = true;
                }

                // Wijziging: Format de prijzen zodat er geen onnodige nullen achter de komma of punt verschijnen.
                $regular_price = rtrim(rtrim(number_format($regular_price, 2, '.', ''), '0'), '.');
                $discounted_price = rtrim(rtrim(number_format($discounted_price, 2, '.', ''), '0'), '.');

                include $template_path;
            } else {
                esc_html_e('Calculator-templatebestand niet gevonden.', 'egaline');
            }
        }
    }

    new Egaline_Calculator_Display();
}
