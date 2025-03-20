<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var WC_Product $product */
global $product;

$product = ($product instanceof WC_Product) ? $product : wc_get_product(get_the_ID());
$product = $product ?: wc_get_product();

$is_variable_product = $product?->is_type('variable') ?? false;
$calculator_disabled_class = $is_variable_product ? 'calculator-disabled' : '';

// Prepare data attributes using modern array syntax
$data_attributes = [
    'data-product-type' => $product?->get_type() ?? '',
    'data-kg-per-bag' => $kg_per_bag ?? 0,
    'data-kg-per-mm' => $kg_per_mm ?? 0,
    'data-kg-per-m2' => $kg_per_m2 ?? 0,
    'data-regular-price' => $regular_price ?? 0,
    'data-variation-price' => $regular_price ?? 0,
    'data-variation-id' => $variation_id ?? 0,
    'data-discount-threshold' => $discount_threshold ?? 0,
    'data-discount-percentage' => $discount_percentage ?? 0
];
?>

<?php if ($is_variable_product) : ?>
    <p class="calculator-warning" role="alert" style="color: red; font-weight: bold;">
        <?= esc_html__('Selecteer eerst een variatie om de calculator te gebruiken.', 'egaline') ?>
    </p>
<?php endif; ?>

<form class="cart" method="post" enctype="multipart/form-data">
    <div class="egaline-calculator <?= esc_attr($calculator_disabled_class) ?>" 
        style="display: block;"
        <?= implode(' ', array_map(
            fn($k, $v) => sprintf('%s="%s"', $k, esc_attr((string)$v)), 
            array_keys($data_attributes), 
            $data_attributes
        )) ?>>

        <h3><?= esc_html__('Bereken het aantal zakken', 'egaline') ?></h3>

        <input type="hidden" name="variation_id" class="calculator-variation-id" 
               value="<?= esc_attr($variation_id ?? '') ?>">

        <div class="inputs-container">
            <?php // Input group for mm ?>
            <div class="form-group">
                <label class="egaline-mm-label" for="egaline-mm">
                    <?= esc_html(
                        ($calculation_mode ?? 'kg_per_mm') === 'kg_per_mm' 
                            ? __('Hoe dik egaliseren in mm?', 'egaline')
                            : __('Aantal lagen in mm', 'egaline')
                    ) ?>
                </label>
                <div class="input-wrapper">
                    <?php foreach (['minus', 'plus'] as $type) : ?>
                        <button type="button" class="qty-btn <?= $type ?>" 
                                aria-label="<?= sprintf(
                                    esc_attr__('%s aantal millimeter', 'egaline'), 
                                    ucfirst($type)
                                ) ?>" 
                                <?= $is_variable_product ? 'disabled' : '' ?>>
                            <?= $type === 'minus' ? '−' : '+' ?>
                        </button>
                    <?php endforeach; ?>
                    <input type="number" id="egaline-mm" name="egaline_mm" value="0" 
                           min="0" step="1" class="egaline-mm egaline-input"
                           aria-describedby="egaline-mm-help"
                           <?= $is_variable_product ? 'disabled' : '' ?>>
                </div>
            </div>

            <?php // Input group for m² ?>
            <div class="form-group">
                <label class="egaline-m2-label" for="egaline-m2">
                    <?= esc_html__('Aantal m² egaliseren?', 'egaline') ?>
                </label>
                <div class="input-wrapper">
                    <?php foreach (['minus', 'plus'] as $type) : ?>
                        <button type="button" class="qty-btn <?= $type ?>" 
                                aria-label="<?= sprintf(
                                    esc_attr__('%s aantal m²', 'egaline'), 
                                    ucfirst($type)
                                ) ?>"
                                <?= $is_variable_product ? 'disabled' : '' ?>>
                            <?= $type === 'minus' ? '−' : '+' ?>
                        </button>
                    <?php endforeach; ?>
                    <input type="number" id="egaline-m2" name="egaline_m2" value="0" 
                           min="0" step="1" class="egaline-m2 egaline-input"
                           aria-describedby="egaline-m2-help"
                           <?= $is_variable_product ? 'disabled' : '' ?>>
                </div>
            </div>
        </div>

        <?php // Result section ?>
        <div class="result">
            <h3><?= esc_html__('Uw resultaat', 'egaline') ?></h3>
            <div class="result-container">
                <div class="result-section benodigde-hoeveelheid">
                    <h4><?= esc_html__('Benodigde hoeveelheid', 'egaline') ?></h4>
                    <p><span class="result-kg">0.00</span> kg Egaline</p>
                </div>
                <div class="result-section aantal-zakken">
                    <h4 class="result-bags-label">
                        <?= sprintf(
                            esc_html__('Aantal zakken (%dkg)', 'egaline'),
                            (int)($kg_per_bag ?? 0)
                        ) ?>
                    </h4>
                    <div class="input-wrapper">
                        <?php foreach (['minus', 'plus'] as $type) : ?>
                            <button type="button" class="qty-btn zakken-<?= $type ?>" 
                                    aria-label="<?= sprintf(
                                        esc_attr__('%s aantal zakken', 'egaline'), 
                                        ucfirst($type)
                                    ) ?>"
                                    disabled>
                                <?= $type === 'minus' ? '−' : '+' ?>
                            </button>
                        <?php endforeach; ?>
                        <input type="number" name="result_bags" value="0" 
                               min="0" step="1" class="result-bags egaline-input"
                               aria-describedby="result-bags-help" disabled>
                    </div>
                </div>
            </div>
        </div>

        <?php // Pricing section ?>
        <div class="total-price">
            <div class="total-price-row">
                <h4><?= esc_html__('Totaalprijs:', 'egaline') ?></h4>
                <span class="total-price-value">
                    <?= number_format(0.00, 2, ',', '') ?>
                </span>
            </div>
            <div class="total-price-note">
                <p class="incl-btw">
                    <?= esc_html__('Inclusief BTW', 'egaline') ?>
                </p>
            </div>
        </div>
    </div>
</form>
