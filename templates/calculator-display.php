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
$settings ??= [];
$introText = (string) ($settings['intro_text'] ?? '');
$labelMm = (string) ($settings['label_mm'] ?? __('Hoe dik egaliseren in mm?', 'egaline-calculator'));
$labelM2 = (string) ($settings['label_m2'] ?? __('Aantal m² egaliseren?', 'egaline-calculator'));
$labelBags = (string) ($settings['label_bags'] ?? __('Aantal zakken', 'egaline-calculator'));
$taxLabel = (string) ($settings['tax_label'] ?? __('Inclusief BTW', 'egaline-calculator'));
$showTotalPrice = !empty($settings['show_total_price']);
$showResetButton = !empty($settings['show_reset_button']);
$showDarkModeButton = !empty($settings['show_dark_mode_button']);
$defaultDarkMode = !empty($settings['default_dark_mode']);
$maxMm = (float) ($settings['max_mm'] ?? 100000);
$maxM2 = (float) ($settings['max_m2'] ?? 100000);
$minBags = (int) ($settings['min_bags'] ?? 1);
$maxBags = (int) ($settings['max_bags'] ?? 100000);
?>

<?php if ($isVariableProduct): ?>
    <p class="egaline-calculator-warning" role="alert">
        <?= esc_html__('Selecteer eerst een variatie om de calculator te gebruiken.', 'egaline-calculator') ?>
    </p>
<?php endif ?>

    <div class="egaline-calculator <?= esc_attr(trim($calculatorDisabledClass . ($defaultDarkMode ? ' dark-mode' : ''))) ?>"
         data-product-type="<?= esc_attr($product->get_type()) ?>"
         data-kg-per-bag="<?= esc_attr($kg_per_bag) ?>"
         data-kg-per-mm="<?= esc_attr($kg_per_mm) ?>"
         data-kg-per-m2="<?= esc_attr($kg_per_m2) ?>"
         data-regular-price="<?= esc_attr($regular_price) ?>"
         data-variation-price="<?= esc_attr($regular_price) ?>"
         data-variation-id="<?= esc_attr($variation_id) ?>"
         data-discount-threshold="<?= esc_attr($discount_threshold) ?>"
         data-discount-percentage="<?= esc_attr($discount_percentage) ?>"
         data-max-mm="<?= esc_attr((string) $maxMm) ?>"
         data-max-m2="<?= esc_attr((string) $maxM2) ?>"
         data-min-bags="<?= esc_attr((string) $minBags) ?>"
         data-max-bags="<?= esc_attr((string) $maxBags) ?>"
    >
        <h3><?= esc_html__('Bereken het aantal zakken', 'egaline-calculator') ?></h3>
        <?php if ($introText !== '') : ?>
            <p class="egaline-calculator-intro-text"><?= esc_html($introText) ?></p>
        <?php endif; ?>
        <input type="hidden" class="calculator-variation-id" value="<?= esc_attr($variation_id) ?>">

        <div class="inputs-container">
            <div class="form-group">
                <label class="egaline-mm-label" for="egaline-mm">
                    <?= esc_html($labelMm) ?>
                </label>
                <p id="egaline-mm-help" class="screen-reader-text"><?= esc_html__('Vul de gewenste laagdikte in millimeters in.', 'egaline-calculator') ?></p>
                <div class="input-wrapper">
                    <button type="button" class="qty-btn minus"
                            aria-label="<?= esc_attr__('Verminder aantal millimeter', 'egaline-calculator') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>−</button>
                    <input type="number"
                           id="egaline-mm"
                           name="egaline_mm"
                           value="0"
                           min="0"
                           max="<?= esc_attr((string) $maxMm) ?>"
                           step="1"
                           class="egaline-mm egaline-input"
                           aria-describedby="egaline-mm-help egaline-calculator-error" aria-invalid="false"
                           <?= $isVariableProduct ? 'disabled' : '' ?>>
                    <button type="button" class="qty-btn plus"
                            aria-label="<?= esc_attr__('Verhoog aantal millimeter', 'egaline-calculator') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>+</button>
                </div>
            </div>

            <div class="form-group">
                <label class="egaline-m2-label" for="egaline-m2">
                    <?= esc_html($labelM2) ?>
                </label>
                <p id="egaline-m2-help" class="screen-reader-text"><?= esc_html__('Vul het aantal vierkante meters in.', 'egaline-calculator') ?></p>
                <div class="input-wrapper">
                    <button type="button" class="qty-btn minus"
                            aria-label="<?= esc_attr__('Verminder aantal m²', 'egaline-calculator') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>−</button>
                    <input type="number"
                           id="egaline-m2"
                           name="egaline_m2"
                           value="0"
                           min="0"
                           max="<?= esc_attr((string) $maxM2) ?>"
                           step="1"
                           class="egaline-m2 egaline-input"
                           aria-describedby="egaline-m2-help egaline-calculator-error" aria-invalid="false"
                           <?= $isVariableProduct ? 'disabled' : '' ?>>
                    <button type="button" class="qty-btn plus"
                            aria-label="<?= esc_attr__('Verhoog aantal m²', 'egaline-calculator') ?>"
                            <?= $isVariableProduct ? 'disabled' : '' ?>>+</button>
                </div>
            </div>
        </div>

        <div class="result">
            <h3><?= esc_html__('Uw resultaat', 'egaline-calculator') ?></h3>
            <div class="result-container">
                <div class="result-section benodigde-hoeveelheid">
                    <h4><?= esc_html__('Benodigde hoeveelheid', 'egaline-calculator') ?></h4>
                    <p><span class="result-kg">0.00</span> kg Egaline</p>
                </div>
                <div class="result-section aantal-zakken">
                    <h4 class="result-bags-label">
                        <?= esc_html($labelBags) ?> (<?= esc_html($kg_per_bag) ?>kg)
                    </h4>
                    <p id="result-bags-help" class="screen-reader-text"><?= esc_html__('Het berekende aantal zakken. U kunt dit aantal verhogen indien nodig.', 'egaline-calculator') ?></p>
                    <div class="input-wrapper">
                        <button type="button" class="qty-btn minus zakken-minus"
                                aria-label="<?= esc_attr__('Verlaag aantal zakken', 'egaline-calculator') ?>"
                                <?= $isVariableProduct ? 'disabled' : '' ?>>−</button>
                        <input type="number"
                               id="result-bags"
                               name="result_bags"
                               value="0"
                               min="<?= esc_attr((string) $minBags) ?>"
                               max="<?= esc_attr((string) $maxBags) ?>"
                               step="1"
                               class="result-bags egaline-input"
                               aria-describedby="result-bags-help egaline-calculator-error" aria-invalid="false"
                               <?= $isVariableProduct ? 'disabled' : '' ?>>
                        <button type="button" class="qty-btn plus zakken-plus"
                                aria-label="<?= esc_attr__('Verhoog aantal zakken', 'egaline-calculator') ?>"
                                <?= $isVariableProduct ? 'disabled' : '' ?>>+</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($showTotalPrice) : ?>
        <div class="total-price">
            <div class="total-price-row">
                <h4><?= esc_html__('Totaalprijs:', 'egaline-calculator') ?></h4>
                <span class="total-price-value">0,00 EUR</span>
                <span class="calc-variation-price" hidden></span>
            </div>
            <?php if ($taxLabel !== '') : ?>
                <p class="incl-btw styled-btw"><?= esc_html($taxLabel) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($showResetButton || $showDarkModeButton) : ?>
        <div class="calculator-actions">
            <?php if ($showResetButton) : ?>
            <button type="button" class="calculator-reset">
                <?= esc_html__('Reset', 'egaline-calculator') ?>
            </button>
            <?php endif; ?>
            <?php if ($showDarkModeButton) : ?>
            <button type="button" class="toggle-dark-mode">
                <?= esc_html__('Dark Mode', 'egaline-calculator') ?>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <p id="egaline-calculator-error" class="calc-error" role="alert" aria-live="polite" hidden></p>
    </div>
