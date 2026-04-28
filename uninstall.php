<?php
/**
 * Cleanup plugin data on uninstall.
 *
 * @package EgalineCalculator
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$meta_keys = [
    '_enable_calculator',
    '_kg_per_bag',
    '_kg_per_mm',
    '_kg_per_m2',
    '_calculation_mode',
    '_discount_threshold',
    '_discount_percentage',
];

foreach ($meta_keys as $meta_key) {
    $wpdb->delete(
        $wpdb->postmeta,
        ['meta_key' => $meta_key],
        ['%s']
    );
}

$options = [
    'egaline_calculator_admin_bar_enabled',
    'egaline_calculator_items_per_page',
    'egaline_calculator_settings',
    // Kept for cleanup when upgrading from v1.0.7, where this option existed.
    
];

foreach ($options as $option) {
    delete_option($option);
}
