<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Voorkom directe toegang
}
?>

<div class="options-group" role="group" aria-labelledby="calculator-settings-heading">
    <h3 id="calculator-settings-heading" class="sr-only">Calculator Instellingen</h3>

    <!-- Activeer Egaline Calculator -->
    <div class="form-field">
        <label for="enable_calculator" class="form-label">
            <?php esc_html_e('Activeer Egaline Calculator', 'egaline-calculator'); ?>
        </label>
        <input
            type="checkbox"
            id="enable_calculator"
            name="_enable_calculator"
            value="yes"
            class="form-checkbox"
            <?php checked($meta_values['enable_calculator'], 'yes'); ?>
            aria-checked="<?php echo esc_attr($meta_values['enable_calculator'] === 'yes' ? 'true' : 'false'); ?>"
        />
    </div>

    <!-- Rekenmethode -->
    <div class="form-field">
        <label for="calculation_mode" class="form-label">
            <?php esc_html_e('Rekenmethode', 'egaline-calculator'); ?>
        </label>
        <select 
            id="calculation_mode" 
            name="_calculation_mode" 
            class="form-select"
            aria-describedby="calculation_mode-help"
        >
            <option value="kg_per_mm" <?php selected($meta_values['calculation_mode'], 'kg_per_mm'); ?>>
                <?php esc_html_e('Kg per mm', 'egaline-calculator'); ?>
            </option>
            <option value="kg_per_m2" <?php selected($meta_values['calculation_mode'], 'kg_per_m2'); ?>>
                <?php esc_html_e('Kg per m²', 'egaline-calculator'); ?>
            </option>
            <option value="layers_per_mm" <?php selected($meta_values['calculation_mode'], 'layers_per_mm'); ?>>
                <?php esc_html_e('Lagen per mm', 'egaline-calculator'); ?>
            </option>
        </select>
        <div id="calculation_mode-help" class="form-description">
            <?php esc_html_e('Kies de eenheid voor de rekensom.', 'egaline-calculator'); ?>
        </div>
    </div>

    <!-- Label en input voor Kg per mm of Lagen per mm -->
    <div class="form-field" id="field_kg_per_mm">
        <label for="kg_per_mm" id="label_kg_per_mm" class="form-label">
            <?php echo ($meta_values['calculation_mode'] === 'kg_per_mm')
                ? esc_html__('Kg per mm', 'egaline-calculator')
                : esc_html__('Lagen per mm', 'egaline-calculator'); ?>:
        </label>
        <input
            type="number"
            id="kg_per_mm"
            name="_kg_per_mm"
            value="<?php echo esc_attr($meta_values['kg_per_mm']); ?>"
            step="0.01"
            min="0"
            class="form-input"
            aria-label="<?php esc_attr_e('Voer de waarde in voor kg per mm of lagen per mm', 'egaline-calculator'); ?>"
        />
    </div>

    <!-- Kg per zak -->
    <div class="form-field">
        <label for="kg_per_bag" class="form-label">
            <?php esc_html_e('Kg per zak', 'egaline-calculator'); ?>
        </label>
        <input
            type="number"
            id="kg_per_bag"
            name="_kg_per_bag"
            value="<?php echo esc_attr($meta_values['kg_per_bag']); ?>"
            step="0.1"
            min="1"
            class="form-input"
            aria-label="<?php esc_attr_e('Aantal kg per zak', 'egaline-calculator'); ?>"
        />
    </div>

    <!-- Kg per m² -->
    <div class="form-field" id="field_kg_per_m2">
        <label for="kg_per_m2" class="form-label">
            <?php esc_html_e('Kg per m²', 'egaline-calculator'); ?>
        </label>
        <input
            type="number"
            id="kg_per_m2"
            name="_kg_per_m2"
            value="<?php echo esc_attr($meta_values['kg_per_m2']); ?>"
            step="0.01"
            min="0"
            class="form-input"
            aria-label="<?php esc_attr_e('Aantal kg per vierkante meter', 'egaline-calculator'); ?>"
        />
    </div>

    <!-- Aantal zakken voor korting -->
    <div class="form-field">
        <label for="discount_threshold" class="form-label">
            <?php esc_html_e('Aantal zakken voor korting', 'egaline-calculator'); ?>
        </label>
        <input
            type="number"
            id="discount_threshold"
            name="_discount_threshold"
            value="<?php echo esc_attr($meta_values['discount_threshold']); ?>"
            step="1"
            min="1"
            class="form-input"
            aria-label="<?php esc_attr_e('Minimaal aantal zakken voor korting', 'egaline-calculator'); ?>"
        />
    </div>

    <!-- Kortingspercentage -->
    <div class="form-field">
        <label for="discount_percentage" class="form-label">
            <?php esc_html_e('Kortingspercentage (%)', 'egaline-calculator'); ?>
        </label>
        <input
            type="number"
            id="discount_percentage"
            name="_discount_percentage"
            value="<?php echo esc_attr($meta_values['discount_percentage']); ?>"
            step="0.1"
            min="0"
            max="100"
            class="form-input"
            aria-label="<?php esc_attr_e('Kortingspercentage voor bulkbestellingen', 'egaline-calculator'); ?>"
        />
    </div>

    <!-- Nonce veld -->
    <?php wp_nonce_field('egaline_save_calculator_settings', 'egaline_calculator_nonce'); ?>

</div>
