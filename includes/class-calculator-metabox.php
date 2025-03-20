<?php
/**
 * Handles custom product metabox for Egaline Calculator integration with WooCommerce.
 * 
 * @package Egaline
 */

declare(strict_types=1);

final class Egaline_Calculator_Metabox {
    public function __construct(
        private string $meta_key_enable = '_enable_calculator',
        private string $meta_key_kg_per_bag = '_kg_per_bag',
        private string $meta_key_kg_per_mm = '_kg_per_mm',
        private string $meta_key_kg_per_m2 = '_kg_per_m2',
        private string $meta_key_calculation_mode = '_calculation_mode',
        private string $meta_key_discount_threshold = '_discount_threshold',
        private string $meta_key_discount_percentage = '_discount_percentage',
        private string $nonce_name = 'egaline_calculator_nonce',
        private string $nonce_action = 'egaline_save_calculator_settings',
        private string $plugin_path = __DIR__
    ) {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_metabox_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'persist_metabox_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts(): void {
        wp_enqueue_script(
            'egaline-calculator-metabox',
            plugin_dir_url($this->plugin_path) . '../assets/js/admin-calculator.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    public function render_metabox_fields(): void {
        global $post;

        $meta_values = [
            'enable_calculator' => get_post_meta($post->ID, $this->meta_key_enable, true),
            'kg_per_bag' => get_post_meta($post->ID, $this->meta_key_kg_per_bag, true),
            'kg_per_mm' => get_post_meta($post->ID, $this->meta_key_kg_per_mm, true),
            'kg_per_m2' => get_post_meta($post->ID, $this->meta_key_kg_per_m2, true),
            'calculation_mode' => get_post_meta($post->ID, $this->meta_key_calculation_mode, true),
            'discount_threshold' => get_post_meta($post->ID, $this->meta_key_discount_threshold, true),
            'discount_percentage' => get_post_meta($post->ID, $this->meta_key_discount_percentage, true),
        ];

        require_once "{$this->plugin_path}/../templates/metabox-calculator.php";
    }

    public function persist_metabox_data(int $post_id): void {
        if (!$this->is_valid_request()) return;

        $this->update_meta_field($post_id, $this->meta_key_enable, $_POST[$this->meta_key_enable] ?? 'no');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_bag, $_POST[$this->meta_key_kg_per_bag] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_mm, $_POST[$this->meta_key_kg_per_mm] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_m2, $_POST[$this->meta_key_kg_per_m2] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_calculation_mode, $_POST[$this->meta_key_calculation_mode] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_discount_threshold, $_POST[$this->meta_key_discount_threshold] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_discount_percentage, $_POST[$this->meta_key_discount_percentage] ?? '');
    }

    private function is_valid_request(): bool {
        return isset($_POST[$this->nonce_name]) 
            && wp_verify_nonce($_POST[$this->nonce_name], $this->nonce_action)
            && current_user_can('edit_post', get_the_ID());
    }

    private function update_meta_field(int $post_id, string $meta_key, mixed $value): void {
        $sanitized = match($meta_key) {
            $this->meta_key_discount_threshold, 
            $this->meta_key_kg_per_bag => $this->sanitize_int($value),
            
            $this->meta_key_discount_percentage,
            $this->meta_key_kg_per_mm,
            $this->meta_key_kg_per_m2 => $this->sanitize_float($value),
            
            default => sanitize_text_field((string) $value)
        };

        update_post_meta($post_id, $meta_key, $sanitized);
    }

    private function sanitize_int(mixed $value): int {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    private function sanitize_float(mixed $value): float {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}

new Egaline_Calculator_Metabox();
