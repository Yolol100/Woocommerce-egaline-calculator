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
 * Requires PHP: 8.2
 * Requires at least: 6.4
 * Tested up to: 6.7
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

namespace Webactueel\EgalineCalculator;

use function add_action;
use function class_exists;
use function esc_html;
use function error_log;
use function file_exists;
use function filemtime;
use function is_product;
use function is_readable;
use function pathinfo;
use function plugin_dir_path;
use function plugin_dir_url;
use function printf;
use function sanitize_key;
use function wp_enqueue_script;
use function wp_enqueue_style;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin initialization class
 * 
 * @since 1.0.0
 */
final readonly class EgalineCalculatorInit
{
    /**
     * Plugin version
     */
    private const VERSION = '1.0.0';
    
    /**
     * Text domain for translations
     */
    private const TEXT_DOMAIN = 'egaline-calculator';
    
    /**
     * Required plugin files
     */
    private const REQUIRED_FILES = [
        'includes/class-calculator-display.php',
        'includes/class-calculator-metabox.php',
        'includes/class-calculator-cart.php',
    ];

    /**
     * Plugin directory path
     */
    private readonly string $pluginPath;
    
    /**
     * Plugin directory URL
     */
    private readonly string $pluginUrl;

    /**
     * Initialize the plugin
     */
    public function __construct()
    {
        $this->pluginPath = plugin_dir_path(__FILE__);
        $this->pluginUrl = plugin_dir_url(__FILE__);

        $this->includeRequiredFiles();
        $this->registerHooks();
    }

    /**
     * Include all required plugin files
     * 
     * @return void
     */
    private function includeRequiredFiles(): void
    {
        foreach (self::REQUIRED_FILES as $file) {
            $filePath = $this->pluginPath . $file;
            
            if (is_readable($filePath)) {
                require_once $filePath;
            } else {
                $this->logError("Bestand niet gevonden of niet leesbaar: {$filePath}");
            }
        }
    }

    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    private function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('plugins_loaded', [$this, 'checkDependencies']);
    }

    /**
     * Enqueue plugin assets on product pages
     * 
     * @return void
     */
    public function enqueueAssets(): void
    {
        if (!is_product()) {
            return;
        }

        $handle = $this->enqueueScript(
            relativePath: 'assets/js/calculator.js',
            dependencies: ['jquery', 'wp-i18n'],
            inFooter: true
        );

        if ($handle !== '') {
            wp_set_script_translations($handle, self::TEXT_DOMAIN, $this->pluginPath . 'languages');
        }
        
        $this->enqueueStyle('assets/css/calculator.css');
    }

    /**
     * Enqueue a JavaScript file
     * 
     * @param string $relativePath Path relative to plugin directory
     * @param array $dependencies Script dependencies
     * @param bool $inFooter Whether to load in footer
     * @return string Script handle
     */
    private function enqueueScript(
        string $relativePath,
        array $dependencies = [],
        bool $inFooter = false
    ): string {
        $filePath = $this->pluginPath . $relativePath;
        $fileUrl = $this->pluginUrl . $relativePath;

        if (!file_exists($filePath)) {
            $this->logError("JS-bestand ontbreekt: {$filePath}");
            return '';
        }

        $handle = $this->generateAssetHandle($relativePath);

        wp_enqueue_script(
            $handle,
            $fileUrl,
            $dependencies,
            (string) filemtime($filePath),
            $inFooter
        );

        return $handle;
    }

    /**
     * Enqueue a CSS file
     * 
     * @param string $relativePath Path relative to plugin directory
     * @return void
     */
    private function enqueueStyle(string $relativePath): void
    {
        $filePath = $this->pluginPath . $relativePath;
        $fileUrl = $this->pluginUrl . $relativePath;

        if (!file_exists($filePath)) {
            $this->logError("CSS-bestand ontbreekt: {$filePath}");
            return;
        }

        wp_enqueue_style(
            $this->generateAssetHandle($relativePath),
            $fileUrl,
            [],
            (string) filemtime($filePath)
        );
    }

    /**
     * Generate a unique handle for assets
     * 
     * @param string $filePath File path to generate handle from
     * @return string Sanitized handle
     */
    private function generateAssetHandle(string $filePath): string
    {
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        return sanitize_key(self::TEXT_DOMAIN . '-' . $filename);
    }

    /**
     * Log error messages
     * 
     * @param string $message Error message to log
     * @return void
     */
    private function logError(string $message): void
    {
        error_log("[Egaline Calculator] {$message}");
    }

    /**
     * Check if required dependencies are available
     * 
     * @return void
     */
    public function checkDependencies(): void
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'displayDependencyNotice']);
        }
    }

    /**
     * Display admin notice for missing dependencies
     * 
     * @return void
     */
    public function displayDependencyNotice(): void
    {
        $message = __('Egaline Calculator vereist WooCommerce om te werken.', self::TEXT_DOMAIN);
        
        printf(
            '<div class="notice notice-error is-dismissible"><p><strong>%s</strong></p></div>',
            esc_html($message)
        );
    }
}

// Initialize the plugin
new EgalineCalculatorInit();
