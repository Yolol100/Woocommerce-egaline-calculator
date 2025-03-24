<?php
/**
 * Handles custom product metabox for Egaline Calculator integration with WooCommerce.
 *
 * @package Egaline
 */

declare(strict_types=1);

final class Egaline_Calculator_Metabox
{
    private const META_KEYS = [
        'enable'              => '_enable_calculator',
        'kg_bag'              => '_kg_per_bag',
        'kg_mm'               => '_kg_per_mm',
        'kg_m2'               => '_kg_per_m2',
        'mode'                => '_calculation_mode',
        'discount_threshold'  => '_discount_threshold',
        'discount_percentage' => '_discount_percentage',
    ];

    private const NONCE_CONFIG = [
        'name'   => 'egaline_calculator_nonce',
        'action' => 'egaline_save_calculator_settings',
    ];

    // Vervanging voor enum CalculationMode.
    private const CALCULATION_MODES = [
        'PER_MM' => 'kg_per_mm',
        'PER_M2' => 'kg_per_m2',
    ];

    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_metabox_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'persist_metabox_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts(): void
    {
        wp_enqueue_script(
            'egaline-calculator-metabox',
            plugin_dir_url(__FILE__) . '../assets/js/admin-calculator.js',
            ['jquery'],
            (string) filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin-calculator.js'),
            true
        );
    }

    public function render_metabox_fields(): void
    {
        global $post;
        
        $meta_values = [
            'enable_calculator'   => $this->get_meta_value($post->ID, self::META_KEYS['enable']),
            'kg_per_bag'          => $this->get_meta_value($post->ID, self::META_KEYS['kg_bag'], 1.0),
            'kg_per_mm'           => $this->get_meta_value($post->ID, self::META_KEYS['kg_mm']),
            'kg_per_m2'           => $this->get_meta_value($post->ID, self::META_KEYS['kg_m2']),
            'calculation_mode'    => $this->get_meta_value(
                $post->ID,
                self::META_KEYS['mode'],
                self::CALCULATION_MODES['PER_MM']
            ),
            'discount_threshold'  => $this->get_meta_value($post->ID, self::META_KEYS['discount_threshold']),
            'discount_percentage' => $this->get_meta_value($post->ID, self::META_KEYS['discount_percentage']),
        ];

        require_once plugin_dir_path(__FILE__) . '../templates/metabox-calculator.php';
    }

    public function persist_metabox_data(int $post_id): void
    {
        if (!$this->validate_nonce() || !current_user_can('edit_post', $post_id)) {
            return;
        }

        foreach (self::META_KEYS as $key => $meta_key) {
            $value = $_POST[$meta_key] ?? match ($meta_key) {
                self::META_KEYS['enable'] => 'no',
                default => ''
            };
            $this->update_meta_field($post_id, $meta_key, $value);
        }
    }

    private function get_meta_value(int $post_id, string $key, mixed $default = ''): mixed
    {
        $value = get_post_meta($post_id, $key, true);
        return $value !== '' ? $value : $default;
    }

    private function validate_nonce(): bool
    {
        return isset($_POST[self::NONCE_CONFIG['name']])
            && wp_verify_nonce($_POST[self::NONCE_CONFIG['name']], self::NONCE_CONFIG['action']);
    }

    private function update_meta_field(int $post_id, string $meta_key, mixed $value): void
    {
        $sanitized = match ($meta_key) {
            self::META_KEYS['kg_bag'], self::META_KEYS['discount_threshold'] =>
                filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]),
            self::META_KEYS['kg_mm'], self::META_KEYS['kg_m2'], self::META_KEYS['discount_percentage'] =>
                filter_var($value, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]),
            self::META_KEYS['mode'] =>
                in_array($value, array_values(self::CALCULATION_MODES), true) ? $value : self::CALCULATION_MODES['PER_MM'],
            default =>
                sanitize_text_field((string)$value)
        };

        if ($sanitized !== null) {
            update_post_meta($post_id, $meta_key, $sanitized);
        }
    }
}

new Egaline_Calculator_Metabox();