<?php
/**
 * Handles custom product metabox for Egaline Calculator integration with WooCommerce.
 *
 * @package Egaline
 */

declare(strict_types=1);

namespace Webactueel\EgalineCalculator;

use function add_action;
use function current_user_can;
use function filter_var;
use function filemtime;
use function get_post_meta;
use function plugin_dir_path;
use function plugin_dir_url;
use function sanitize_text_field;
use function update_post_meta;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_verify_nonce;

final readonly class EgalineCalculatorMetabox
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

    private const CALCULATION_MODES = [
        'PER_MM'       => 'kg_per_mm',
        'PER_M2'       => 'kg_per_m2',
        'LAYERS_PER_MM' => 'layers_per_mm',
    ];

    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', $this->renderMetaboxFields(...));
        add_action('woocommerce_process_product_meta', $this->persistMetaboxData(...));
        add_action('admin_enqueue_scripts', $this->enqueueAdminScripts(...));
        add_action('admin_enqueue_scripts', $this->enqueueAdminStyles(...));
    }

    public function enqueueAdminScripts(): void
    {
        wp_enqueue_script(
            'egaline-calculator-metabox',
            plugin_dir_url(__FILE__) . '../assets/js/admin-calculator.js',
            ['jquery'],
            (string) filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin-calculator.js'),
            true
        );
    }

    public function enqueueAdminStyles(): void
    {
        wp_enqueue_style(
            'egaline-calculator-metabox',
            plugin_dir_url(__FILE__) . '../assets/css/admin-calculator.css',
            [],
            (string) filemtime(plugin_dir_path(__FILE__) . '../assets/css/admin-calculator.css')
        );
    }


    public function renderMetaboxFields(): void
    {
        global $post;

        $metaValues = [
            'enable_calculator'   => $this->getMetaValue($post->ID, self::META_KEYS['enable']),
            'kg_per_bag'          => $this->getMetaValue($post->ID, self::META_KEYS['kg_bag'], 1.0),
            'kg_per_mm'           => $this->getMetaValue($post->ID, self::META_KEYS['kg_mm']),
            'kg_per_m2'           => $this->getMetaValue($post->ID, self::META_KEYS['kg_m2']),
            'calculation_mode'    => $this->getMetaValue($post->ID, self::META_KEYS['mode'], self::CALCULATION_MODES['PER_MM']),
            'discount_threshold'  => $this->getMetaValue($post->ID, self::META_KEYS['discount_threshold']),
            'discount_percentage' => $this->getMetaValue($post->ID, self::META_KEYS['discount_percentage']),
        ];

        require plugin_dir_path(__FILE__) . '../templates/metabox-calculator.php';
    }

    public function persistMetaboxData(int $postId): void
    {
        if (!$this->validateNonce() || !current_user_can('edit_post', $postId)) {
            return;
        }

        foreach (self::META_KEYS as $key => $metaKey) {
            $value = $_POST[$metaKey] ?? ($metaKey === self::META_KEYS['enable'] ? 'no' : '');
            $this->updateMetaField($postId, $metaKey, $value);
        }
    }

    private function getMetaValue(int $postId, string $key, mixed $default = ''): mixed
    {
        $value = get_post_meta($postId, $key, true);
        return $value !== '' ? $value : $default;
    }

    private function validateNonce(): bool
    {
        return isset($_POST[self::NONCE_CONFIG['name']])
            && wp_verify_nonce($_POST[self::NONCE_CONFIG['name']], self::NONCE_CONFIG['action']);
    }

    private function updateMetaField(int $postId, string $metaKey, mixed $value): void
    {
        $sanitized = match ($metaKey) {
            self::META_KEYS['kg_bag'],
            self::META_KEYS['discount_threshold'] => filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]),

            self::META_KEYS['kg_mm'],
            self::META_KEYS['kg_m2'],
            self::META_KEYS['discount_percentage'] => filter_var($value, FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]),

            self::META_KEYS['mode'] => in_array($value, self::CALCULATION_MODES, true) ? $value : self::CALCULATION_MODES['PER_MM'],

            default => sanitize_text_field((string) $value),
        };

        if ($sanitized !== null) {
            update_post_meta($postId, $metaKey, $sanitized);
        }
    }
}

new EgalineCalculatorMetabox();
