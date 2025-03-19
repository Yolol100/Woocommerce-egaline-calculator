<?php
/**
 * Handles custom product metabox for Egaline Calculator integration with WooCommerce.
 * 
 * @package Egaline
 */

declare(strict_types=1);

final class Egaline_Calculator_Metabox {
    /** @var string */
    private string $meta_key_enable             = '_enable_calculator';
    
    /** @var string */
    private string $meta_key_kg_per_bag         = '_kg_per_bag';
    
    /** @var string */
    private string $meta_key_kg_per_mm          = '_kg_per_mm';
    
    /** @var string */
    private string $meta_key_kg_per_m2          = '_kg_per_m2';
    
    /** @var string */
    private string $meta_key_calculation_mode   = '_calculation_mode';
    
    /** @var string */
    private string $meta_key_discount_threshold = '_discount_threshold';
    
    /** @var string */
    private string $meta_key_discount_percentage= '_discount_percentage';
    
    /** @var string */
    private string $nonce_name                  = 'egaline_calculator_nonce';
    
    /** @var string */
    private string $nonce_action                = 'egaline_save_calculator_settings';

    /**
     * Constructor.
     *
     * Registreert de benodigde acties voor het weergeven en opslaan van metabox-gegevens.
     */
    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', [$this, 'render_metabox_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'persist_metabox_data']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Laadt de benodigde JavaScript-bestanden voor de metabox in het WordPress-dashboard.
     *
     * @return void
     */
    public function enqueue_admin_scripts(): void {
        wp_enqueue_script(
            'egaline-calculator-metabox',
            plugin_dir_url(__FILE__) . '../assets/js/admin-calculator.js',
            ['jquery'],
            '1.0.0',
            in_footer: true
        );
    }

    /**
     * Laadt het HTML-template voor de metabox en haalt de huidige meta-waarden op.
     *
     * @return void
     */
    public function render_metabox_fields(): void {
        global $post;
        
        $meta_values = [
            'enable_calculator'   => get_post_meta($post->ID, $this->meta_key_enable, true),
            'kg_per_bag'          => get_post_meta($post->ID, $this->meta_key_kg_per_bag, true),
            'kg_per_mm'           => get_post_meta($post->ID, $this->meta_key_kg_per_mm, true),
            'kg_per_m2'           => get_post_meta($post->ID, $this->meta_key_kg_per_m2, true),
            'calculation_mode'    => get_post_meta($post->ID, $this->meta_key_calculation_mode, true),
            'discount_threshold'  => get_post_meta($post->ID, $this->meta_key_discount_threshold, true),
            'discount_percentage' => get_post_meta($post->ID, $this->meta_key_discount_percentage, true),
        ];

        require_once plugin_dir_path(__FILE__) . '../templates/metabox-calculator.php';
    }

    /**
     * Verwerkt en slaat de metabox-data op tijdens het opslaan van het product.
     *
     * @param int $post_id Het ID van het opgeslagen product.
     * @return void
     */
    public function persist_metabox_data(int $post_id): void {
        if (
            !isset($_POST[$this->nonce_name]) ||
            !wp_verify_nonce($_POST[$this->nonce_name], $this->nonce_action)
        ) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->update_meta_field($post_id, $this->meta_key_enable, $_POST[$this->meta_key_enable] ?? 'no');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_bag, $_POST[$this->meta_key_kg_per_bag] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_mm, $_POST[$this->meta_key_kg_per_mm] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_kg_per_m2, $_POST[$this->meta_key_kg_per_m2] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_calculation_mode, $_POST[$this->meta_key_calculation_mode] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_discount_threshold, $_POST[$this->meta_key_discount_threshold] ?? '');
        $this->update_meta_field($post_id, $this->meta_key_discount_percentage, $_POST[$this->meta_key_discount_percentage] ?? '');
    }

    /**
     * Helper functie voor het updaten van meta-velden met sanitization.
     *
     * @param int $post_id
     * @param string $meta_key
     * @param string $value
     * @return void
     */
    private function update_meta_field(int $post_id, string $meta_key, string $value): void {
        if ($meta_key === $this->meta_key_discount_threshold || $meta_key === $this->meta_key_kg_per_bag) {
            update_post_meta($post_id, $meta_key, (int) $value);
        } elseif ($meta_key === $this->meta_key_discount_percentage || $meta_key === $this->meta_key_kg_per_mm || $meta_key === $this->meta_key_kg_per_m2) {
            update_post_meta($post_id, $meta_key, (float) $value);
        } else {
            update_post_meta($post_id, $meta_key, sanitize_text_field($value));
        }
    }
}

new Egaline_Calculator_Metabox();
