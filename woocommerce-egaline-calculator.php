<?php
/**
 * Plugin Name: WooCommerce Egaline Calculator
 * Plugin URI: https://example.com/egaline-calculator
 * Description: Voegt een professionele egalinecalculator toe aan WooCommerce-producten
 * Version: 1.0.0
 * Author: Webactueel
 * Author URI: https://example.com
 * Text Domain: egaline-calculator
 * Domain Path: /languages
 * Requires PHP: 8.3
 * Requires at least: 6.4
 * WC requires at least: 8.6
 * WC tested up to: 9.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

final class Egaline_Calculator_Init {
    private const VERSION = '1.0.0';
    private const TEXT_DOMAIN = 'egaline-calculator';
    private const REQUIRED_FILES = [
        'includes/class-calculator-display.php',
        'includes/class-calculator-metabox.php',
        'includes/class-calculator-cart.php',
    ];

    public function __construct(
        private readonly string $plugin_path = plugin_dir_path(__FILE__),
        private readonly string $plugin_url = plugin_dir_url(__FILE__)
    ) {
        $this->include_files();
        $this->register_hooks();
    }

    private function include_files(): void {
        array_map(
            fn($file) => $this->require_file($this->plugin_path . $file),
            self::REQUIRED_FILES
        );
    }

    private function require_file(string $file_path): void {
        if (is_readable($file_path)) {
            require_once $file_path;
        } else {
            $this->log_error("Bestand niet gevonden: {$file_path}");
        }
    }

    private function register_hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('plugins_loaded', [$this, 'verify_dependencies']);
    }

    public function enqueue_assets(): void {
        if (!is_product()) return;

        $this->enqueue_script('assets/js/calculator.js', ['jquery'], true);
        $this->enqueue_style('assets/css/calculator.css');
    }

    private function enqueue_script(
        string $relative_path,
        array $dependencies = [],
        bool $in_footer = false
    ): void {
        $this->validate_file($relative_path, 'JS') && wp_enqueue_script(
            $this->generate_handle($relative_path),
            $this->plugin_url . $relative_path,
            $dependencies,
            $this->get_file_version($relative_path),
            $in_footer
        );
    }

    private function enqueue_style(string $relative_path): void {
        $this->validate_file($relative_path, 'CSS') && wp_enqueue_style(
            $this->generate_handle($relative_path),
            $this->plugin_url . $relative_path,
            [],
            $this->get_file_version($relative_path)
        );
    }

    private function validate_file(string $relative_path, string $type): bool {
        if (!file_exists($this->plugin_path . $relative_path)) {
            $this->log_error("{$type} bestand ontbreekt: {$relative_path}");
            return false;
        }
        return true;
    }

    private function get_file_version(string $relative_path): string {
        return (string)filemtime($this->plugin_path . $relative_path);
    }

    private function generate_handle(string $file_path): string {
        return sanitize_key(self::TEXT_DOMAIN . '-' . pathinfo($file_path, PATHINFO_FILENAME));
    }

    private function log_error(string $message): void {
        error_log("[Egaline Calculator] {$message}");
    }

    public function verify_dependencies(): void {
        class_exists('WooCommerce') || add_action('admin_notices', fn() => 
            printf('<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Egaline Calculator vereist WooCommerce', self::TEXT_DOMAIN)
            )
        );
    }
}

new Egaline_Calculator_Init();
