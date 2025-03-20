<?php
/**
 * Handles custom product metabox for Egaline Calculator integration with WooCommerce.
 * 
 * @package Egaline
 */

final class Egaline_Calculator_Metabox {
    private $meta_key_enable = '_enable_calculator';
    private $meta_key_kg_per_bag = '_kg_per_bag';
    private $meta_key_kg_per_mm = '_kg_per_mm';
    private $meta_key_kg_per_m2 = '_kg_per_m2';
    private $meta_key_calculation_mode = '_calculation_mode';
    private $meta_key_discount_threshold = '_discount_threshold';
    private $meta_key_discount_percentage = '_discount_percentage';
    private $nonce_name = 'egaline_calculator_nonce';
    private $nonce_action = 'egaline_save_calculator_settings';
    private $plugin_path;

    public function __construct($plugin_path = __DIR__) {
        $this->plugin_path = $plugin_path;
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_metabox_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'persist_metabox_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script(
            'egaline-calculator-metabox',
            plugin_dir_url($this->plugin_path) . '../assets/js/admin-calculator.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    public function render_metabox_fields() {
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

    public function persist_metabox_data($post_id) {
        if (!$this->is_valid_request()) return;

        $this->update_meta_field($post_id, $this->meta_key_enable, $_POST[$this->meta_key_enable] ?? 'no');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_bag, $_POST[$this->meta_key_kg_per_bag] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_mm, $_POST[$this->meta_key_kg_per_mm] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_m2, $_POST[$this->meta_key_kg_per_m2] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_calculation_mode, $_POST[$this->meta_key_calculation_mode] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_discount_threshold, $_POST[$this->meta_key_discount_threshold] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_discount_percentage, $_POST[$this->meta_key_discount_percentage] ?? '');
    }

    private function is_valid_request() {
        return isset($_POST[$this->nonce_name]) 
            && wp_verify_nonce($_POST[$this->nonce_name], $this->nonce_action)
            && current_user_can('edit_post', get_the_ID());
    }

    private function update_meta_field($post_id, $meta_key, $value) {
        if ($meta_key === $this->meta_key_discount_threshold || $meta_key === $this->meta_key_kg_per_bag) {
            $sanitized = $this->sanitize_int($value);
        } elseif (in_array($meta_key, [$this->meta_key_discount_percentage, $this->meta_key_kg_per_mm, $this->meta_key_kg_per_m2])) {
            $sanitized = $this->sanitize_float($value);
        } else {
            $sanitized = sanitize_text_field((string) $value);
        }

        update_post_meta($post_id, $meta_key, $sanitized);
    }

    private function sanitize_int($value) {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    private function sanitize_float($value) {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}

new Egaline_Calculator_Metabox();
