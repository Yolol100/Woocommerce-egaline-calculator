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
use function file_exists;
use function filemtime;
use function get_current_screen;
use function get_post_meta;
use function get_post_type;
use function plugin_dir_path;
use function plugin_dir_url;
use function sanitize_text_field;
use function update_post_meta;
use function wp_enqueue_style;
use function wp_is_post_autosave;
use function wp_is_post_revision;
use function wp_unslash;
use function wp_verify_nonce;

defined('ABSPATH') || exit;

final readonly class EgalineCalculatorMetabox
{
    private const VERSION = '1.0.0';

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
        'PER_MM'        => 'kg_per_mm',
        'PER_M2'        => 'kg_per_m2',
        'LAYERS_PER_MM' => 'layers_per_mm',
    ];

    public function __construct()
    {
        add_action('woocommerce_product_options_general_product_data', [$this, 'renderMetaboxFields']);
        add_action('woocommerce_process_product_meta', [$this, 'persistMetaboxData']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    public function enqueueAdminAssets(string $hookSuffix): void
    {
        if (!$this->isProductEditScreen($hookSuffix)) {
            return;
        }

        $css_path = plugin_dir_path(__FILE__) . '../assets/css/admin-calculator.css';
        $css_url = plugin_dir_url(__FILE__) . '../assets/css/admin-calculator.css';

        if (!file_exists($css_path)) {
            return;
        }

        wp_enqueue_style(
            'egaline-calculator-metabox',
            $css_url,
            [],
            (string) filemtime($css_path)
        );
    }

    public function renderMetaboxFields(): void
    {
        global $post;

        if (!$post || get_post_type($post) !== 'product') {
            return;
        }

        $metaValues = [
            'enable_calculator'   => $this->getMetaValue((int) $post->ID, self::META_KEYS['enable']),
            'kg_per_bag'          => $this->getMetaValue((int) $post->ID, self::META_KEYS['kg_bag'], 1.0),
            'kg_per_mm'           => $this->getMetaValue((int) $post->ID, self::META_KEYS['kg_mm']),
            'kg_per_m2'           => $this->getMetaValue((int) $post->ID, self::META_KEYS['kg_m2']),
            'calculation_mode'    => $this->getMetaValue((int) $post->ID, self::META_KEYS['mode'], self::CALCULATION_MODES['PER_MM']),
            'discount_threshold'  => $this->getMetaValue((int) $post->ID, self::META_KEYS['discount_threshold']),
            'discount_percentage' => $this->getMetaValue((int) $post->ID, self::META_KEYS['discount_percentage']),
        ];

        require plugin_dir_path(__FILE__) . '../templates/metabox-calculator.php';
    }

    public function persistMetaboxData(int $postId): void
    {
        if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
            return;
        }

        if (get_post_type($postId) !== 'product') {
            return;
        }

        if (!$this->validateNonce() || !current_user_can('edit_product', $postId)) {
            return;
        }

        foreach (self::META_KEYS as $key => $metaKey) {
            $default = $metaKey === self::META_KEYS['enable'] ? 'no' : '';
            $value = $_POST[$metaKey] ?? $default;
            $this->updateMetaField($postId, $metaKey, $value);
        }
    }

    private function isProductEditScreen(string $hookSuffix): bool
    {
        if (!in_array($hookSuffix, ['post.php', 'post-new.php'], true)) {
            return false;
        }

        $screen = get_current_screen();

        return $screen && $screen->post_type === 'product';
    }

    private function getMetaValue(int $postId, string $key, mixed $default = ''): mixed
    {
        $value = get_post_meta($postId, $key, true);
        return $value !== '' ? $value : $default;
    }

    private function validateNonce(): bool
    {
        if (!isset($_POST[self::NONCE_CONFIG['name']])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_CONFIG['name']]));

        return (bool) wp_verify_nonce($nonce, self::NONCE_CONFIG['action']);
    }

    private function updateMetaField(int $postId, string $metaKey, mixed $value): void
    {
        $value = is_scalar($value) ? sanitize_text_field(wp_unslash((string) $value)) : '';

        $sanitized = match ($metaKey) {
            self::META_KEYS['enable'] => $value === 'yes' ? 'yes' : 'no',

            self::META_KEYS['kg_bag'] => $this->sanitizeFloat($value, 1.0, null, 1.0),

            self::META_KEYS['kg_mm'],
            self::META_KEYS['kg_m2'] => $this->sanitizeFloat($value, 0.0, null, 0.0),

            self::META_KEYS['discount_threshold'] => $this->sanitizeInt($value, 0, null, 0),

            self::META_KEYS['discount_percentage'] => $this->sanitizeFloat($value, 0.0, 100.0, 0.0),

            self::META_KEYS['mode'] => in_array($value, self::CALCULATION_MODES, true) ? $value : self::CALCULATION_MODES['PER_MM'],

            default => $value,
        };

        update_post_meta($postId, $metaKey, $sanitized);
    }

    private function sanitizeFloat(string $value, float $min, ?float $max, float $fallback): float
    {
        $value = str_replace(',', '.', $value);

        if ($value === '' || !is_numeric($value)) {
            return $fallback;
        }

        $number = max($min, (float) $value);

        return $max !== null ? min($max, $number) : $number;
    }

    private function sanitizeInt(string $value, int $min, ?int $max, int $fallback): int
    {
        if ($value === '' || !is_numeric($value)) {
            return $fallback;
        }

        $number = max($min, absint($value));

        return $max !== null ? min($max, $number) : $number;
    }
}

new EgalineCalculatorMetabox();
