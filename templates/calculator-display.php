<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Stop als het bestand direct wordt opgeroepen.
}

// Zorg ervoor dat $product correct is ingesteld
global $product;
if ( ! isset( $product ) || ! is_object( $product ) ) {
    $product = wc_get_product( get_the_ID() );
}

// Controleer of het een variabel product is
$is_variable_product = $product->is_type('variable');
$calculator_disabled_class = $is_variable_product ? 'calculator-disabled' : '';

// Voeg een melding toe als er nog geen variatie is geselecteerd voor variabele producten
if ( $is_variable_product ) {
    echo '<p class="calculator-warning" style="color: red; font-weight: bold;">Selecteer eerst een variatie om de calculator te gebruiken.</p>';
}
?>

<form class="cart" method="post" enctype="multipart/form-data">
    <div class="egaline-calculator <?php echo esc_attr( $calculator_disabled_class ); ?>" style="display: block;"
         data-product-type="<?php echo esc_attr( $product->get_type() ); ?>"
         data-kg-per-bag="<?php echo esc_attr( $kg_per_bag ); ?>"
         data-kg-per-mm="<?php echo esc_attr( $kg_per_mm ); ?>"
         data-kg-per-m2="<?php echo esc_attr( $kg_per_m2 ); ?>"
         data-regular-price="<?php echo esc_attr( $regular_price ); ?>"
         data-variation-price="<?php echo esc_attr( $regular_price ); ?>"
         data-variation-id="<?php echo esc_attr( $variation_id ); ?>"
         data-discount-threshold="<?php echo esc_attr( $discount_threshold ); ?>"
         data-discount-percentage="<?php echo esc_attr( $discount_percentage ); ?>">

        <h3>Bereken het aantal zakken</h3>

        <!-- Verborgen veld voor de variation ID -->
        <input type="hidden" name="variation_id" class="calculator-variation-id" value="<?php echo esc_attr( $variation_id ); ?>">

        <div class="inputs-container">
            <div class="form-group">
                <label class="egaline-mm-label" for="egaline-mm">
                    <?php echo esc_html( $calculation_mode === 'kg_per_mm' ? 'Hoe dik egaliseren in mm?' : 'Aantal lagen in mm' ); ?>
                </label>
                <div class="input-wrapper">
                    <button type="button" class="qty-btn minus" aria-label="Verminder aantal millimeter" disabled>−</button>
                    <input type="number" id="egaline-mm" name="egaline_mm" value="0" min="0" step="1" class="egaline-mm egaline-input" aria-describedby="egaline-mm-help" disabled>
                    <button type="button" class="qty-btn plus" aria-label="Verhoog aantal millimeter" disabled>+</button>
                </div>
            </div>
            <div class="form-group">
                <label class="egaline-m2-label" for="egaline-m2"><?php esc_html_e( 'Aantal m² egaliseren?', 'egaline' ); ?></label>
                <div class="input-wrapper">
                    <button type="button" class="qty-btn minus" aria-label="Verminder aantal m²" disabled>−</button>
                    <input type="number" id="egaline-m2" name="egaline_m2" value="0" min="0" step="1" class="egaline-m2 egaline-input" aria-describedby="egaline-m2-help" disabled>
                    <button type="button" class="qty-btn plus" aria-label="Verhoog aantal m²" disabled>+</button>
                </div>
            </div>
        </div>

        <div class="result">
            <h3>Uw resultaat</h3>
            <div class="result-container">
                <div class="result-section benodigde-hoeveelheid">
                    <h4>Benodigde hoeveelheid</h4>
                    <p><span class="result-kg">0.00</span> kg Egaline</p>
                </div>
                <div class="result-section aantal-zakken">
                    <h4 class="result-bags-label">Aantal zakken (<?php echo esc_html( $kg_per_bag ); ?>kg)</h4>
                    <div class="input-wrapper">
                        <button type="button" class="qty-btn minus zakken-minus" aria-label="Verlaag aantal zakken" disabled>−</button>
                        <input type="number" name="result_bags" value="0" min="0" step="1" class="result-bags egaline-input" aria-describedby="result-bags-help" disabled>
                        <button type="button" class="qty-btn plus zakken-plus" aria-label="Verhoog aantal zakken" disabled>+</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="total-price">
            <div class="total-price-row">
                <h4>Totaalprijs:</h4>
                <span class="total-price-value"><?php echo number_format( 0.00, 2, ',', '' ); ?></span>
            </div>
            <div class="total-price-note">
                <p class="incl-btw"><?php esc_html_e( 'Inclusief BTW', 'egaline' ); ?></p>
            </div>
        </div>
    </div>
</form>