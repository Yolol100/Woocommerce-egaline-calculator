<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

global $product;
$product ??= wc_get_product(get_the_ID());

if (!$product instanceof WC_Product) {
    return;
}

$isVariableProduct = $product->is_type('variable');
$calculatorDisabledClass = $isVariableProduct ? 'calculator-disabled' : '';

$kg_per_bag ??= '';
$kg_per_mm ??= '';
$kg_per_m2 ??= '';
$regular_price ??= '';
$variation_id ??= '';
$discount_threshold ??= '';
$discount_percentage ??= '';
$calculation_mode ??= 'kg_per_mm';
?>

<?php if ($isVariableProduct): ?>
    <p class="calculator-warning" style="color: red; font-weight: bold;">
        <?= esc_html__('Selecteer eerst een variatie om de calculator te gebruiken.', 'egaline') ?>
    </p>
<?php endif ?>

<form class="cart" method="post" enctype="multipart/form-data">
    <div class="egaline-calculator <?= esc_attr($calculatorDisabledClass) ?>"
         data-product-type="<?= esc_attr($product->get_type()) ?>"
         data-kg-per-bag="<?= esc_attr($kg_per_bag) ?>"
         data-kg-per-mm="<?= esc_attr($kg_per_mm) ?>"
         data-kg-per-m2="<?= esc_attr($kg_per_m2) ?>"
         data-regular-price="<?= esc_attr($regular_price) ?>"
         data-variation-price="<?= esc_attr($regular_price) ?>"
         data-variation-id="<?= esc_attr($variation_id) ?>"
         data-discount-threshold="<?= esc_attr($discount_threshold) ?>"
         data-discount-percentage="<?= esc_attr($discount_percentage) ?>"
    >
        <h3><?= esc_html__('Bereken het aantal zakken', 'egaline') ?></h3>
        <input type="hidden" name="variation_id" class="calculator-variation-id" value="<?= esc_attr($variation_id) ?>">

        <div class="inputs-container">
            <div class="form-group">
                <label class="egaline-mm-label" for="egaline-mm">
                    <?= esc_html($calculation_mode === 'kg_per_mm' ? 'Hoe dik egaliseren in mm?' : 'Aantal lagen in mm') ?>
                </label>
                <div class="input-wrapper">
                    <button type="button" class="qty-btn minus"
                            aria-label="<?= esc_attr__('Verminder aantal millimeter', 'egaline') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>−</button>
                    <input type="number"
                           id="egaline-mm"
                           name="egaline_mm"
                           value="0"
                           min="0"
                           step="1"
                           class="egaline-mm egaline-input"
                           aria-describedby="egaline-mm-help"
                           <?= $isVariableProduct ? 'disabled' : '' ?>>
                    <button type="button" class="qty-btn plus"
                            aria-label="<?= esc_attr__('Verhoog aantal millimeter', 'egaline') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>+</button>
                </div>
            </div>

            <div class="form-group">
                <label class="egaline-m2-label" for="egaline-m2">
                    <?= esc_html__('Aantal m² egaliseren?', 'egaline') ?>
                </label>
                <div class="input-wrapper">
                    <button type="button" class="qty-btn minus"
                            aria-label="<?= esc_attr__('Verminder aantal m²', 'egaline') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>−</button>
                    <input type="number"
                           id="egaline-m2"
                           name="egaline_m2"
                           value="0"
                           min="0"
                           step="1"
                           class="egaline-m2 egaline-input"
                           aria-describedby="egaline-m2-help"
                           <?= $isVariableProduct ? 'disabled' : '' ?>>
                    <button type="button" class="qty-btn plus"
                            aria-label="<?= esc_attr__('Verhoog aantal m²', 'egaline') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>+</button>
                </div>
            </div>
        </div>

        <div class="result">
            <h3><?= esc_html__('Uw resultaat', 'egaline') ?></h3>
            <div class="result-container">
                <div class="result-section benodigde-hoeveelheid">
                    <h4><?= esc_html__('Benodigde hoeveelheid', 'egaline') ?></h4>
                    <p><span class="result-kg">0.00</span> kg Egaline</p>
                </div>
                <div class="result-section aantal-zakken">
                    <h4 class="result-bags-label">
                        <?= esc_html__('Aantal zakken', 'egaline') ?> (<?= esc_html($kg_per_bag) ?>kg)
                    </h4>
                    <div class="input-wrapper">
                        <button type="button" class="qty-btn minus zakken-minus"
                                aria-label="<?= esc_attr__('Verlaag aantal zakken', 'egaline') ?>"
                                <?= $isVariableProduct ? 'disabled' : '' ?>>−</button>
                        <input type="number"
                               name="result_bags"
                               value="0"
                               min="0"
                               step="1"
                               class="result-bags egaline-input"
                               aria-describedby="result-bags-help"
                               <?= $isVariableProduct ? 'disabled' : '' ?>>
                        <button type="button" class="qty-btn plus zakken-plus"
                                aria-label="<?= esc_attr__('Verhoog aantal zakken', 'egaline') ?>"
                                <?= $isVariableProduct ? 'disabled' : '' ?>>+</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="total-price">
            <div class="total-price-row">
                <h4><?= esc_html__('Totaalprijs:', 'egaline') ?></h4>
                <span class="total-price-value">0,00 EUR</span>
            </div>
            <p class="incl-btw styled-btw"><?= esc_html__('Inclusief BTW', 'egaline') ?></p>
        </div>
    </div>
</form>