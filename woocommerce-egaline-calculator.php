<?php
/**
 * Plugin Name: WooCommerce Egaline Calculator
 * Plugin URI: https://example.com/egaline-calculator
 * Description: Voegt een professionele egalinecalculator toe aan WooCommerce-producten
 * Version: 1.0.0.
 * Author: Webactueel
 * Author URI: https://example.com.
 * Text Domain: egaline-calculator
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

final class Egaline_Calculator_Init {
    /** @var string Plugin versie */
    private const VERSION = '1.0.0';

    /** @var string Text domain voor internationalisatie */
    private const TEXT_DOMAIN = 'egaline-calculator';

    /** @var array Bestanden die vereist zijn */
    private const REQUIRED_FILES = [
        'includes/class-calculator-display.php',
        'includes/class-calculator-metabox.php',
        'includes/class-calculator-cart.php',
    ];

    /** @var string Plugin directory pad */
    private readonly string $plugin_path;

    /** @var string Plugin URL */
    private readonly string $plugin_url;

    /**
     * Constructor.
     *
     * Initialiseert de plugin door pad- en URL-variabelen in te stellen, vereiste bestanden te laden en hooks te registreren.
     */
    public function __construct() {
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url  = plugin_dir_url( __FILE__ );

        $this->includes();
        $this->register_hooks();
    }

    /**
     * Laadt de vereiste bestanden van de plugin.
     *
     * @return void
     */
    private function includes(): void {
        foreach ( self::REQUIRED_FILES as $file ) {
            $file_path = $this->plugin_path . $file;
            if ( is_readable( $file_path ) ) {
                require_once $file_path;
            } else {
                $this->log_error( "Bestand niet gevonden of niet leesbaar: {$file_path}." );
            }
        }
    }

    /**
     * Registreert de benodigde hooks voor scripts en dependency-check.
     *
     * @return void
     */
    private function register_hooks(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'plugins_loaded', [ $this, 'check_dependencies' ] );
    }

    /**
     * Laadt de benodigde CSS- en JavaScript-bestanden op productpagina's.
     *
     * @return void
     */
    public function enqueue_assets(): void {
        if ( ! is_product() ) {
            return;
        }

        $this->enqueue_script( 'assets/js/calculator.js', [ 'jquery' ], true );
        $this->enqueue_style( 'assets/css/calculator.css' );
    }

    /**
     * Registreert en enqueue't een JavaScript-bestand.
     *
     * @param string $relative_path De relatieve pad naar het bestand.
     * @param array  $dependencies  Een array met afhankelijkheden.
     * @param bool   $in_footer     Of het script in de footer geladen wordt.
     * @return void
     */
    private function enqueue_script( string $relative_path, array $dependencies = [], bool $in_footer = false ): void {
        $file_path = $this->plugin_path . $relative_path;
        $file_url  = $this->plugin_url . $relative_path;

        if ( ! file_exists( $file_path ) ) {
            $this->log_error( "JS bestand ontbreekt: {$file_path}." );
            return;
        }

        wp_enqueue_script(
            $this->generate_handle( $relative_path ),
            $file_url,
            $dependencies,
            filemtime( $file_path ),
            $in_footer
        );
    }

    /**
     * Registreert en enqueue't een CSS-bestand.
     *
     * @param string $relative_path De relatieve pad naar het bestand.
     * @return void
     */
    private function enqueue_style( string $relative_path ): void {
        $file_path = $this->plugin_path . $relative_path;
        $file_url  = $this->plugin_url . $relative_path;

        if ( ! file_exists( $file_path ) ) {
            $this->log_error( "CSS bestand ontbreekt: {$file_path}." );
            return;
        }

        wp_enqueue_style(
            $this->generate_handle( $relative_path ),
            $file_url,
            [],
            filemtime( $file_path )
        );
    }

    /**
     * Genereert een veilige handle voor een bestand op basis van de bestandsnaam.
     *
     * @param string $file_path Het relatieve pad naar het bestand.
     * @return string De gegenereerde handle.
     */
    private function generate_handle( string $file_path ): string {
        return sanitize_key( self::TEXT_DOMAIN . '-' . pathinfo( $file_path, PATHINFO_FILENAME ) );
    }

    /**
     * Logt foutmeldingen naar de error log.
     *
     * @param string $message De foutmelding.
     * @return void
     */
    private function log_error( string $message ): void {
        error_log( "[Egaline Calculator] {$message}" );
    }

    /**
     * Controleert of WooCommerce is geïnstalleerd.
     *
     * Toont een admin-notice als WooCommerce niet aanwezig is.
     *
     * @return void
     */
    public function check_dependencies(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', fn() => $this->display_dependency_notice() );
        }
    }

    /**
     * Toont een admin-notice wanneer WooCommerce ontbreekt.
     *
     * @return void
     */
    private function display_dependency_notice(): void {
        $message = __( 'Egaline Calculator vereist WooCommerce om te werken.', self::TEXT_DOMAIN );
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html( $message )
        );
    }
}

// Plugin initialiseren.
new Egaline_Calculator_Init();