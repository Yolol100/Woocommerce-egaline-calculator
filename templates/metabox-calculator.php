<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Stop als het bestand direct wordt opgeroepen.
}
?>
<div class="options-group">

    <!-- Optiegroep: Activeer Egaline Calculator -->
    <p class="form-field">
        <label for="enable_calculator">
            <?php esc_html_e( 'Activeer Egaline Calculator', 'egaline' ); ?>
        </label>
        <input 
            type="checkbox" 
            id="enable_calculator" 
            name="_enable_calculator" 
            value="yes" 
            <?php checked( $meta_values['enable_calculator'], 'yes' ); ?>
            aria-checked="<?php echo esc_attr( $meta_values['enable_calculator'] === 'yes' ? 'true' : 'false' ); ?>"
        >
    </p>

    <!-- Optiegroep: Rekenmethode -->
    <p class="form-field">
        <label for="calculation_mode">
            <?php esc_html_e( 'Rekenmethode', 'egaline' ); ?>
        </label>
        <select id="calculation_mode" name="_calculation_mode" aria-describedby="calculation_mode-help">
            <option value="kg_per_mm" <?php selected( $meta_values['calculation_mode'], 'kg_per_mm' ); ?>>
                <?php esc_html_e( 'Kg per mm', 'egaline' ); ?>
            </option>
            <option value="layers_per_mm" <?php selected( $meta_values['calculation_mode'], 'layers_per_mm' ); ?>>
                <?php esc_html_e( 'Lagen per mm', 'egaline' ); ?>
            </option>
        </select>
        <p id="calculation_mode-help" class="description">
            <?php esc_html_e( 'Kies de eenheid voor de rekensom.', 'egaline' ); ?>
        </p>
    </p>

    <!-- Optiegroep: Label en input voor Kg per mm of Lagen per mm -->
    <p class="form-field">
        <label for="kg_per_mm" id="label_kg_per_mm">
            <?php echo ( 'kg_per_mm' === $meta_values['calculation_mode'] ) ? esc_html__( 'Kg per mm', 'egaline' ) : esc_html__( 'Lagen per mm', 'egaline' ); ?>:
        </label>
        <input 
            type="number" 
            id="kg_per_mm" 
            name="_kg_per_mm" 
            value="<?php echo esc_attr( $meta_values['kg_per_mm'] ); ?>" 
            step="0.01" 
            min="0" 
            aria-label="<?php esc_attr_e( 'Voer de waarde in voor kg per mm of lagen per mm', 'egaline' ); ?>"
        >
    </p>

    <!-- Optiegroep: Kg per zak -->
    <p class="form-field">
        <label for="kg_per_bag">
            <?php esc_html_e( 'Kg per zak', 'egaline' ); ?>
        </label>
        <input 
            type="number" 
            id="kg_per_bag" 
            name="_kg_per_bag" 
            value="<?php echo esc_attr( $meta_values['kg_per_bag'] ); ?>" 
            step="0.1" 
            min="1"
            aria-label="<?php esc_attr_e( 'Aantal kg per zak', 'egaline' ); ?>"
        >
    </p>

    <!-- Optiegroep: Kg per m² -->
    <p class="form-field">
        <label for="kg_per_m2">
            <?php esc_html_e( 'Kg per m²', 'egaline' ); ?>
        </label>
        <input 
            type="number" 
            id="kg_per_m2" 
            name="_kg_per_m2" 
            value="<?php echo esc_attr( $meta_values['kg_per_m2'] ); ?>" 
            step="0.01" 
            min="0"
            aria-label="<?php esc_attr_e( 'Aantal kg per vierkante meter', 'egaline' ); ?>"
        >
    </p>
    
    <!-- Optiegroep: Aantal zakken voor korting -->
    <p class="form-field">
        <label for="discount_threshold">
            <?php esc_html_e( 'Aantal zakken voor korting', 'egaline' ); ?>
        </label>
        <input 
            type="number" 
            id="discount_threshold" 
            name="_discount_threshold" 
            value="<?php echo esc_attr( $meta_values['discount_threshold'] ); ?>" 
            step="1" 
            min="1"
            aria-label="<?php esc_attr_e( 'Minimaal aantal zakken voor korting', 'egaline' ); ?>"
        >
    </p>

    <!-- Optiegroep: Kortingspercentage -->
    <p class="form-field">
        <label for="discount_percentage">
            <?php esc_html_e( 'Kortingspercentage (%)', 'egaline' ); ?>
        </label>
        <input 
            type="number" 
            id="discount_percentage" 
            name="_discount_percentage" 
            value="<?php echo esc_attr( $meta_values['discount_percentage'] ); ?>" 
            step="0.1" 
            min="0"
            max="100"
            aria-label="<?php esc_attr_e( 'Kortingspercentage voor bulkbestellingen', 'egaline' ); ?>"
        >
    </p>

    <!-- Nonce veld voor beveiliging -->
    <?php wp_nonce_field( 'egaline_save_calculator_settings', 'egaline_calculator_nonce' ); ?>

</div>