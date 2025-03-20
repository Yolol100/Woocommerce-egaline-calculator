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
 * Requires PHP: 8.2
 * Requires at least: 6.4
 * WC requires at least: 8.6
 * WC tested up to: 9.0
 */

defined('ABSPATH') || exit;

final class Egaline_Calculator_Init {
    private $version = '1.0.0';
    private $text_domain = 'egaline-calculator';
    private $required_files = [
        'includes/class-calculator-display.php',
        'includes/class-calculator-metabox.php',
        'includes/class-calculator-cart.php',
    ];
    
    private $plugin_path;
    private $plugin_url;

    public function __construct($plugin_path = null, $plugin_url = null) {
        $this->plugin_path = $plugin_path ? $plugin_path : plugin_dir_path(__FILE__);
        $this->plugin_url = $plugin_url ? $plugin_url : plugin_dir_url(__FILE__);
        $this->include_files();
        $this->register_hooks();
    }

    private function include_files() {
        array_map(
            function($file) {
                $this->require_file($this->plugin_path . $file);
            },
            $this->required_files
        );
    }

    private function require_file($file_path) {
        if (is_readable($file_path)) {
            require_once $file_path;
        } else {
            $this->log_error("Bestand niet gevonden: {$file_path}");
        }
    }

    private function register_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('plugins_loaded', [$this, 'verify_dependencies']);
    }

    public function enqueue_assets() {
        if (!is_product()) return;

        $this->enqueue_script('assets/js/calculator.js', ['jquery'], true);
        $this->enqueue_style('assets/css/calculator.css');
    }

    private function enqueue_script($relative_path, $dependencies = [], $in_footer = false) {
        $this->validate_file($relative_path, 'JS') && wp_enqueue_script(
            $this->generate_handle($relative_path),
            $this->plugin_url . $relative_path,
            $dependencies,
            $this->get_file_version($relative_path),
            $in_footer
        );
    }

    private function enqueue_style($relative_path) {
        $this->validate_file($relative_path, 'CSS') && wp_enqueue_style(
            $this->generate_handle($relative_path),
            $this->plugin_url . $relative_path,
            [],
            $this->get_file_version($relative_path)
        );
    }

    private function validate_file($relative_path, $type) {
        if (!file_exists($this->plugin_path . $relative_path)) {
            $this->log_error("{$type} bestand ontbreekt: {$relative_path}");
            return false;
        }
        return true;
    }

    private function get_file_version($relative_path) {
        return (string)filemtime($this->plugin_path . $relative_path);
    }

    private function generate_handle($file_path) {
        return sanitize_key($this->text_domain . '-' . pathinfo($file_path, PATHINFO_FILENAME));
    }

    private function log_error($message) {
        error_log("[Egaline Calculator] {$message}");
    }

    public function verify_dependencies() {
        class_exists('WooCommerce') || add_action('admin_notices', function() {
            printf('<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('Egaline Calculator vereist WooCommerce', $this->text_domain)
            );
        });
    }
}

new Egaline_Calculator_Init();
