"use strict";

jQuery(document).ready(($) => {
  // Configuratie-object met defaults
  const config = {
    defaultKgPerBag: 15,
  };

  // ---------------------------------------------------------------------------
  // CALCULATOR INITIALISATIE & FUNCTIES (per instantie)
  // ---------------------------------------------------------------------------
  const initializeCalculator = (calculator) => {
    // Cache alle belangrijke DOM-elementen binnen de calculator
    const $inputs = {
      egalineMm: calculator.find('.egaline-mm'),
      egalineM2: calculator.find('.egaline-m2'),
      resultBags: calculator.find('.result-bags'),
      resultKg: calculator.find('.result-kg'),
      totalPrice: calculator.find('.total-price span'),
      variationId: calculator.find('.calculator-variation-id'),
      qtyBtns: calculator.find('.qty-btn'),
      labelEgalineMm: calculator.find('.egaline-mm-label'),
      labelEgalineM2: calculator.find('.egaline-m2-label'),
      labelResultBags: calculator.find('.result-bags-label'),
    };

    // Haal configuratie uit data-attributen (fallback naar defaults)
    const KG_PER_MM = parseFloat(calculator.data('kg-per-mm')) || 0;
    const KG_PER_M2 = parseFloat(calculator.data('kg-per-m2')) || 0;
    const BAG_WEIGHT = parseFloat(calculator.data('kg-per-bag')) || config.defaultKgPerBag;
    const calculationMode = calculator.data('calculation-mode') || 'kg_per_mm';

    // Houd bij welk veld als laatste is bewerkt
    let lastEdited = null;

    // Formatteer een getal naar een afgeronde string
    const formatNumber = (number) => Math.round(number).toString();

    // Update de labels op basis van de rekenmodus
    const updateLabel = () => {
      $inputs.labelEgalineMm.text(
        calculationMode === 'kg_per_mm' ? "Hoe dik egaliseren in mm?" : "Aantal lagen in mm"
      );
      $inputs.labelEgalineM2.text("Aantal m² egaliseren?");
      $inputs.labelResultBags.text(`Aantal zakken (${BAG_WEIGHT}kg)`);
    };

    // Zet standaardwaarden in de invoervelden
    const initializeDefaults = () => {
      $inputs.egalineMm.val(0);
      $inputs.egalineM2.val(0);
      $inputs.resultBags.val(0);
      $inputs.resultKg.text('0');
      $inputs.totalPrice.text('0 EUR');
    };

    // Bereken de benodigde hoeveelheid kg op basis van dikte en oppervlakte
    const calculateNeededKg = () => {
      const thickness = parseFloat($inputs.egalineMm.val()) || 0;
      const area = parseFloat($inputs.egalineM2.val()) || 0;
      return (thickness * area * KG_PER_MM) + (area * KG_PER_M2);
    };

    // Bereken en update de resultaten (gewicht, aantal zakken, totaalprijs)
    const updateResults = () => {
      let totalKg, calculatedBags, totalPriceValue;
      let pricePerBag = parseFloat(calculator.data('regular-price')) || 0;
      const varPrice = parseFloat(calculator.data('variation-price')) || pricePerBag;
      pricePerBag = varPrice;

      if (lastEdited === "mm" || lastEdited === "m2" || !lastEdited) {
        totalKg = calculateNeededKg();
        calculatedBags = Math.ceil(totalKg / BAG_WEIGHT);
        totalPriceValue = calculatedBags * pricePerBag;
        if (!$inputs.resultBags.is(':focus')) {
          $inputs.resultBags.val(totalKg > 0 ? calculatedBags : 0);
        }
      } else if (lastEdited === "sacks") {
        calculatedBags = parseInt($inputs.resultBags.val(), 10) || 0;
        totalKg = calculatedBags * BAG_WEIGHT;
        totalPriceValue = calculatedBags * pricePerBag;
        const thickness = parseFloat($inputs.egalineMm.val()) || 0;
        const factor = (thickness * KG_PER_MM) + KG_PER_M2;
        if (factor > 0 && !$inputs.egalineM2.is(':focus')) {
          const computedArea = totalKg / factor;
          $inputs.egalineM2.val(Math.round(computedArea));
        }
      }

      $inputs.resultKg.text(formatNumber(totalKg));
      $inputs.totalPrice.text(totalPriceValue.toFixed(2) + ' EUR');
      updateLabel();
    };

    // Bind events aan de invoervelden
    $inputs.egalineMm.on('input change', () => {
      lastEdited = "mm";
      updateResults();
    });
    $inputs.egalineM2.on('input change', () => {
      lastEdited = "m2";
      updateResults();
    });
    $inputs.resultBags.on('input change', () => {
      lastEdited = "sacks";
      updateResults();
    });

    // Bind de plus/minus knoppen met een namespace om dubbele binding te voorkomen
    $inputs.qtyBtns.off('click.qty').on('click.qty', (e) => {
      e.preventDefault();
      const $button = $(e.currentTarget);
      const $input = $button.closest('.input-wrapper').find('input');
      const currentVal = parseFloat($input.val()) || 0;
      const step = parseFloat($input.attr('step')) || 1;
      const isPlus = $button.hasClass('plus');
      const newVal = isPlus ? currentVal + step : Math.max(0, currentVal - step);
      $input.val(step < 1 ? newVal.toFixed(1) : Math.round(newVal)).trigger('input');
    });

    // Initialiseer labels, defaults en resultaten
    updateLabel();
    initializeDefaults();
    updateResults();
  };

  // ---------------------------------------------------------------------------
  // INITIALISEER ALLE CALCULATOREN (desktop en mobiel)
  // ---------------------------------------------------------------------------
  const initCalculators = () => {
    $('.egaline-calculator').each(function() {
      initializeCalculator($(this));
      console.log("Calculator instance initialized:", $(this));
    });
  };

  initCalculators();

  // ---------------------------------------------------------------------------
  // GLOBALE SUBMIT-HANDLER VOOR HET WINKELWAGEN-FORMULIER MET INPUT-VALIDATIE
  // ---------------------------------------------------------------------------
  $('form.cart').off('submit.calculatorSubmit').on('submit.calculatorSubmit', function() {
    const $form = $(this);
    // Verwijder bestaande hidden inputs (voorkomt duplicatie bij meerdere submits)
    $form.find('input.egaline-hidden').remove();

    // Zoek de zichtbare calculator; als geen zichtbaar is, pak de eerste.
    let $calculator = $('.egaline-calculator:visible').first();
    if (!$calculator.length) {
      $calculator = $('.egaline-calculator').first();
    }

    // Verkrijg de inputwaarden en converteer ze naar getallen.
    const thickness = parseFloat($calculator.find('.egaline-mm').val()) || 0;
    const area = parseFloat($calculator.find('.egaline-m2').val()) || 0;

    // Controleer of beide waarden groter zijn dan 0.
    if (thickness <= 0 || area <= 0) {
      alert("Voer a.u.b. de vereiste gegevens in de calculator in voordat u het product toevoegt aan de winkelwagen.");
      return false; // Voorkom verzending van het formulier.
    }

    // Indien validatie slaagt, haal overige velden op.
    const bags = $calculator.find('.result-bags').val();
    const variation_id = $calculator.find('.calculator-variation-id').val();

    // Voeg de benodigde hidden inputs toe aan het formulier.
    $('<input>', {
      type: 'hidden',
      name: 'egaline_mm',
      value: thickness,
      class: 'egaline-hidden'
    }).appendTo($form);

    $('<input>', {
      type: 'hidden',
      name: 'egaline_m2',
      value: area,
      class: 'egaline-hidden'
    }).appendTo($form);

    $('<input>', {
      type: 'hidden',
      name: 'result_bags',
      value: bags,
      class: 'egaline-hidden'
    }).appendTo($form);

    if (variation_id) {
      $('<input>', {
        type: 'hidden',
        name: 'variation_id',
        value: variation_id,
        class: 'egaline-hidden'
      }).appendTo($form);
    }
  });

  // ---------------------------------------------------------------------------
  // VARIATIEFORMULIER & PRIJSUPDATES (update ALLE calculator-instanties)
  // ---------------------------------------------------------------------------
  const $variationsForm = $('form.variations_form');
  if ($variationsForm.length) {
    $variationsForm.on('woocommerce_variations_loaded', function() {
      const variations = $variationsForm.data('product_variations');
      if (variations && variations.length > 0) {
        const defaultVariation = variations[0];
        if (defaultVariation && defaultVariation.display_price) {
          const tempDiv = document.createElement("div");
          tempDiv.innerHTML = defaultVariation.price_html;
          let cleanPrice = tempDiv.querySelector(".woocommerce-Price-amount");
          cleanPrice = cleanPrice ? cleanPrice.textContent.trim() : defaultVariation.price_html;
          $('.egaline-calculator').each(function() {
            $(this).data('variation-price', defaultVariation.display_price);
            $(this).find('.calc-variation-price').html(` (${cleanPrice})`).show();
          });
        }
      }
    });

    $variationsForm.on('found_variation', function(event, variation) {
      if (variation.display_price) {
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = variation.price_html;
        let cleanPrice = tempDiv.querySelector(".woocommerce-Price-amount");
        cleanPrice = cleanPrice ? cleanPrice.textContent.trim() : variation.price_html;
        $('.egaline-calculator').each(function() {
          $(this).data('variation-price', variation.display_price);
          $(this).find('.calc-variation-price').html(` (${cleanPrice})`).show();
          $(this).find('.calculator-variation-id').val(variation.variation_id);
          $(this).find('.egaline-mm, .egaline-m2, .result-bags').val(0).trigger('change');
          $(this).find('.result-kg').text('0');
          $(this).find('.total-price span').text('0 EUR');
        });
        console.log("Nieuwe variatie geselecteerd: " + variation.variation_id);
      }
    });
  }
});