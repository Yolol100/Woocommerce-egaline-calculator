<?php
/**
 * Plugin Name: WooCommerce Egaline Calculator
 * Plugin URI: https://example.com/egaline-calculator
 * Description: Voegt een professionele egalinecalculator toe aan WooCommerce-producten.
 * Version: 1.0.0
 * Author: Webactueel
 * Author URI: https://example.com
 * Text Domain: egaline-calculator
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

declare(strict_types=1);

namespace Webactueel\EgalineCalculator;

use function add_action;
use function is_product;
use function is_readable;
use function plugin_dir_path;
use function plugin_dir_url;
use function sanitize_key;
use function wp_enqueue_script;
use function wp_enqueue_style;

defined('ABSPATH') || exit;

final class Egaline_Calculator_Init
{
    private const VERSION = '1.0.0';
    private const TEXT_DOMAIN = 'egaline-calculator';
    private const REQUIRED_FILES = [
        'includes/class-calculator-display.php',
        'includes/class-calculator-metabox.php',
        'includes/class-calculator-cart.php',
    ];

    private readonly string $plugin_path;
    private readonly string $plugin_url;

    public function __construct()
    {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url  = plugin_dir_url(__FILE__);

        $this->includes();
        $this->register_hooks();
    }

    private function includes(): void
    {
        foreach (self::REQUIRED_FILES as $file) {
            $file_path = $this->plugin_path . $file;
            if (is_readable($file_path)) {
                require_once $file_path;
            } else {
                $this->log_error("Bestand niet gevonden of niet leesbaar: {$file_path}");
            }
        }
    }

    private function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('plugins_loaded', [$this, 'check_dependencies']);
    }

    public function enqueue_assets(): void
    {
        if (!is_product()) {
            return;
        }

        $this->enqueue_script('assets/js/calculator.js', ['jquery'], true);
        $this->enqueue_style('assets/css/calculator.css');
    }

    private function enqueue_script(string $relative_path, array $dependencies = [], bool $in_footer = false): void
    {
        $file_path = $this->plugin_path . $relative_path;
        $file_url  = $this->plugin_url . $relative_path;

        if (!file_exists($file_path)) {
            $this->log_error("JS-bestand ontbreekt: {$file_path}");
            return;
        }

        wp_enqueue_script(
            $this->generate_handle($relative_path),
            $file_url,
            $dependencies,
            filemtime($file_path),
            $in_footer
        );
    }

    private function enqueue_style(string $relative_path): void
    {
        $file_path = $this->plugin_path . $relative_path;
        $file_url  = $this->plugin_url . $relative_path;

        if (!file_exists($file_path)) {
            $this->log_error("CSS-bestand ontbreekt: {$file_path}");
            return;
        }

        wp_enqueue_style(
            $this->generate_handle($relative_path),
            $file_url,
            [],
            filemtime($file_path)
        );
    }

    private function generate_handle(string $file_path): string
    {
        return sanitize_key(self::TEXT_DOMAIN . '-' . pathinfo($file_path, PATHINFO_FILENAME));
    }

    private function log_error(string $message): void
    {
        error_log("[Egaline Calculator] {$message}");
    }

    public function check_dependencies(): void
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', fn() => $this->display_dependency_notice());
        }
    }

    private function display_dependency_notice(): void
    {
        $message = __('Egaline Calculator vereist WooCommerce om te werken.', self::TEXT_DOMAIN);
        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    }
}

new Egaline_Calculator_Init();
