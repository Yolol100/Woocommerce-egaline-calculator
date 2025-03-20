<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $meta_values */
/** @var WC_Product $product */
global $product;
?>
<div class="options-group">

    <!-- Dynamic Calculator Toggle -->
    <p class="form-field">
        <label for="enable_calculator">
            <?= esc_html__('Activeer Egaline Calculator', 'egaline') ?>
        </label>
        <input type="checkbox" id="enable_calculator" name="_enable_calculator" value="yes"
            <?= checked($meta_values['enable_calculator'] ?? '', 'yes') ?>
            aria-checked="<?= ($meta_values['enable_calculator'] ?? '') === 'yes' ? 'true' : 'false' ?>">
    </p>

    <!-- Calculation Method Selector -->
    <p class="form-field">
        <label for="calculation_mode"><?= esc_html__('Rekenmethode', 'egaline') ?></label>
        <select id="calculation_mode" name="_calculation_mode" aria-describedby="calculation_mode-help">
            <?= implode('', array_map(
                fn($option) => sprintf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($option['value']),
                    selected($meta_values['calculation_mode'] ?? '', $option['value'], false),
                    esc_html($option['label'])
                ),
                [
                    ['value' => 'kg_per_mm', 'label' => __('Kg per mm', 'egaline')],
                    ['value' => 'layers_per_mm', 'label' => __('Lagen per mm', 'egaline')]
                ]
            )) ?>
        </select>
        <p id="calculation_mode-help" class="description">
            <?= esc_html__('Kies de eenheid voor de rekensom.', 'egaline') ?>
        </p>
    </p>

    <!-- Context-Sensitive Input Field -->
    <p class="form-field">
        <label for="kg_per_mm" id="label_kg_per_mm">
            <?= match($meta_values['calculation_mode'] ?? '') {
                'layers_per_mm' => esc_html__('Lagen per mm:', 'egaline'),
                default => esc_html__('Kg per mm:', 'egaline')
            } ?>
        </label>
        <input type="number" id="kg_per_mm" name="_kg_per_mm" 
               value="<?= esc_attr($meta_values['kg_per_mm'] ?? '') ?>" 
               step="0.01" min="0"
               aria-label="<?= esc_attr__('Waarde voor berekening', 'egaline') ?>">
    </p>

    <!-- Configurable Input Generator -->
    <?php foreach ([
        [
            'id' => 'kg_per_bag',
            'label' => __('Kg per zak', 'egaline'),
            'attrs' => ['step' => '0.1', 'min' => '1']
        ],
        [
            'id' => 'kg_per_m2',
            'label' => __('Kg per m²', 'egaline'),
            'attrs' => ['step' => '0.01', 'min' => '0']
        ],
        [
            'id' => 'discount_threshold',
            'label' => __('Aantal zakken voor korting', 'egaline'),
            'attrs' => ['step' => '1', 'min' => '1']
        ],
        [
            'id' => 'discount_percentage',
            'label' => __('Kortingspercentage (%)', 'egaline'),
            'attrs' => ['step' => '0.1', 'min' => '0', 'max' => '100']
        ]
    ] as $field) : ?>
        <p class="form-field">
            <label for="<?= esc_attr($field['id']) ?>">
                <?= esc_html($field['label']) ?>
            </label>
            <input type="number" id="<?= esc_attr($field['id']) ?>" 
                   name="_<?= esc_attr($field['id']) ?>" 
                   value="<?= esc_attr($meta_values[$field['id']] ?? '') ?>"
                   <?= implode(' ', array_map(
                       fn($k, $v) => sprintf('%s="%s"', $k, $v),
                       array_keys($field['attrs']),
                       $field['attrs']
                   )) ?>
                   aria-label="<?= esc_attr($field['label']) ?>">
        </p>
    <?php endforeach; ?>

    <!-- Security -->
    <?php wp_nonce_field('egaline_save_calculator_settings', 'egaline_calculator_nonce') ?>

</div>
