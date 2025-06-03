<?php
/**
 * Admin metabox for configuring Egaline Calculator settings.
 *
 * @package Egaline
 */

declare(strict_types=1);

use function esc_html_e;
use function wp_nonce_field;
use function woocommerce_wp_checkbox;
use function woocommerce_wp_select;
use function woocommerce_wp_text_input;

if (!defined('ABSPATH')) {
    exit;
}

$metaValues = $metaValues ?? [];

?>
<div class="options-group" role="group" aria-labelledby="calculator-settings-heading">
    <h3 id="calculator-settings-heading" class="screen-reader-text">
        <?php esc_html_e('Calculator Instellingen', 'egaline-calculator'); ?>
    </h3>

    <?php
    woocommerce_wp_checkbox([
        'id'      => '_enable_calculator',
        'label'   => __('Activeer Egaline Calculator', 'egaline-calculator'),
        'value'   => $metaValues['enable_calculator'],
        'cbvalue' => 'yes',
        'wrapper_class' => 'form-field',
    ]);

    woocommerce_wp_select([
        'id'            => '_calculation_mode',
        'label'         => __('Rekenmethode', 'egaline-calculator'),
        'value'         => $metaValues['calculation_mode'],
        'options'       => [ 'kg_per_mm' => __('Kg per mm', 'egaline-calculator') ],
        'wrapper_class' => 'form-field',
    ]);

    woocommerce_wp_text_input([
        'id'            => '_kg_per_mm',
        'label'         => __('Kg per mm', 'egaline-calculator'),
        'value'         => $metaValues['kg_per_mm'],
        'type'          => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        'wrapper_class' => 'form-field',
    ]);

    woocommerce_wp_text_input([
        'id'            => '_kg_per_bag',
        'label'         => __('Kg per zak', 'egaline-calculator'),
        'value'         => $metaValues['kg_per_bag'],
        'type'          => 'number',
        'custom_attributes' => ['step' => '0.1', 'min' => '1'],
        'wrapper_class' => 'form-field',
    ]);

    woocommerce_wp_text_input([
        'id'            => '_kg_per_m2',
        'label'         => __('Kg per m²', 'egaline-calculator'),
        'value'         => $metaValues['kg_per_m2'],
        'type'          => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0'],
        'wrapper_class' => 'form-field',
    ]);

    woocommerce_wp_text_input([
        'id'            => '_discount_threshold',
        'label'         => __('Aantal zakken voor korting', 'egaline-calculator'),
        'value'         => $metaValues['discount_threshold'],
        'type'          => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '1'],
        'wrapper_class' => 'form-field',
    ]);

    woocommerce_wp_text_input([
        'id'            => '_discount_percentage',
        'label'         => __('Kortingspercentage (%)', 'egaline-calculator'),
        'value'         => $metaValues['discount_percentage'],
        'type'          => 'number',
        'custom_attributes' => ['step' => '0.1', 'min' => '0', 'max' => '100'],
        'wrapper_class' => 'form-field',
    ]);
    ?>

    <?php wp_nonce_field('egaline_save_calculator_settings', 'egaline_calculator_nonce'); ?>
</div>
