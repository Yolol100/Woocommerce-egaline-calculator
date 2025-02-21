"use strict";

jQuery(document).ready(($) => {
  // Configuratie-object met defaults
  const config = {
    defaultKgPerBag: 15, // Default gewicht per zak
  };

  // ---------------------------------------------------------------------------
  // CALCULATOR INITIALISATIE & FUNCTIES (per instantie)
  // ---------------------------------------------------------------------------
  const initializeCalculator = (calculator) => {
    // Cache alle belangrijke DOM-elementen binnen de calculator
    const $inputs = {
      egalineMm: calculator.find('.egaline-mm'), // Dikte van de egaline (mm)
      egalineM2: calculator.find('.egaline-m2'), // Oppervlakte in m²
      resultBags: calculator.find('.result-bags'), // Aantal zakken
      resultKg: calculator.find('.result-kg'), // Gewicht (kg)
      totalPrice: calculator.find('.total-price span'), // Totaalprijs
      variationId: calculator.find('.calculator-variation-id'), // Variatie ID
      qtyBtns: calculator.find('.qty-btn'), // Plus/minus knoppen
      labelEgalineMm: calculator.find('.egaline-mm-label'), // Label voor dikte
      labelEgalineM2: calculator.find('.egaline-m2-label'), // Label voor oppervlakte
      labelResultBags: calculator.find('.result-bags-label'), // Label voor zakken
    };

    // Haal configuratie uit data-attributen (fallback naar defaults)
    const KG_PER_MM = parseFloat(calculator.data('kg-per-mm')) || 0; // Gewicht per mm
    const KG_PER_M2 = parseFloat(calculator.data('kg-per-m2')) || 0; // Gewicht per m²
    const BAG_WEIGHT = parseFloat(calculator.data('kg-per-bag')) || config.defaultKgPerBag; // Gewicht per zak
    const calculationMode = calculator.data('calculation-mode') || 'kg_per_mm'; // Rekenmodus (kg per mm)

    // Houd bij welk veld als laatste is bewerkt
    let lastEdited = null;

    // Formatteer een getal naar een afgeronde string
    const formatNumber = (number) => Math.round(number).toString();

    // Update de labels op basis van de rekenmodus
    const updateLabel = () => {
      $inputs.labelEgalineMm.text(
        calculationMode === 'kg_per_mm' ? __("Hoe dik egaliseren in mm?", 'text-domain') : __("Aantal lagen in mm", 'text-domain')
      );
      $inputs.labelEgalineM2.text(__("Aantal m² egaliseren?", 'text-domain'));
      $inputs.labelResultBags.text(__('Aantal zakken', 'text-domain') + ` (${BAG_WEIGHT}kg)`); // Veranderd de label naar het gewicht per zak
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
      return (thickness * area * KG_PER_MM) + (area * KG_PER_M2); // Gewicht in kg berekenen
    };

    // Bereken en update de resultaten (gewicht, aantal zakken, totaalprijs)
    const updateResults = () => {
      let totalKg, calculatedBags, totalPriceValue;
      let pricePerBag = parseFloat(calculator.data('regular-price')) || 0;
      const varPrice = parseFloat(calculator.data('variation-price')) || pricePerBag;
      pricePerBag = varPrice;

      if (lastEdited === "mm" || lastEdited === "m2" || !lastEdited) {
        totalKg = calculateNeededKg(); // Bereken het totale gewicht
        calculatedBags = Math.ceil(totalKg / BAG_WEIGHT); // Bereken aantal zakken
        totalPriceValue = calculatedBags * pricePerBag; // Bereken de totaalprijs
        if (!$inputs.resultBags.is(':focus')) {
          $inputs.resultBags.val(totalKg > 0 ? calculatedBags : 0); // Update het aantal zakken als het veld niet gefocust is
        }
      } else if (lastEdited === "sacks") {
        calculatedBags = parseInt($inputs.resultBags.val(), 10) || 0; // Aantal zakken via invoer
        totalKg = calculatedBags * BAG_WEIGHT; // Totaalgewicht berekenen
        totalPriceValue = calculatedBags * pricePerBag; // Totaalprijs op basis van zakken
        const thickness = parseFloat($inputs.egalineMm.val()) || 0;
        const factor = (thickness * KG_PER_MM) + KG_PER_M2;
        if (factor > 0 && !$inputs.egalineM2.is(':focus')) {
          const computedArea = totalKg / factor; // Bereken het oppervlakte
          $inputs.egalineM2.val(Math.round(computedArea));
        }
      }

      $inputs.resultKg.text(formatNumber(totalKg)); // Toon het totale gewicht
      $inputs.totalPrice.text(totalPriceValue.toFixed(2) + ' EUR'); // Toon de totaalprijs
      updateLabel(); // Update de labels
    };

    // Voeg validatie toe bij klikken op de 'Calculate' knop of wanneer de invoervelden leeg zijn
    const validateFields = () => {
      let isValid = true;

      // Controleer of velden leeg zijn en voeg rode rand toe
      $inputs.egalineMm.each(function() {
        if ($(this).val() === '') {
          $(this).css('border', '1px solid red');
          isValid = false;
        } else {
          $(this).css('border', '');
        }
      });

      $inputs.egalineM2.each(function() {
        if ($(this).val() === '') {
          $(this).css('border', '1px solid red');
          isValid = false;
        } else {
          $(this).css('border', '');
        }
      });

      $inputs.resultBags.each(function() {
        if ($(this).val() === '') {
          $(this).css('border', '1px solid red');
          isValid = false;
        } else {
          $(this).css('border', '');
        }
      });

      if (!isValid) {
        alert(__("Vul alle velden in voordat je verder gaat!", 'text-domain')); // Laat een waarschuwing zien als velden leeg zijn
      }
      return isValid;
    };

    // Bind de validate functie voor het submitten van de calculator
    $('#calculate_button').on('click', function() {
      if (!validateFields()) {
        return false; // Voorkom verdere verwerking als velden leeg zijn
      }
    });

    // Bind events aan de invoervelden
    $inputs.egalineMm.on('input change', () => {
      lastEdited = "mm"; // Laatste bewerkte veld is mm
      updateResults();
    });
    $inputs.egalineM2.on('input change', () => {
      lastEdited = "m2"; // Laatste bewerkte veld is m²
      updateResults();
    });
    $inputs.resultBags.on('input change', () => {
      lastEdited = "sacks"; // Laatste bewerkte veld is zakken
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
      initializeCalculator($(this)); // Initialiseert iedere calculator
      console.log("Calculator instance initialized:", $(this)); // Log de geïnitieerde calculator
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
      $calculator = $('.egaline-calculator').first(); // Als geen zichtbare calculator is, pak de eerste
    }

    // Verkrijg de inputwaarden en converteer ze naar getallen.
    const thickness = parseFloat($calculator.find('.egaline-mm').val()) || 0;
    const area = parseFloat($calculator.find('.egaline-m2').val()) || 0;
    const bags = parseInt($calculator.find('.result-bags').val(), 10) || 0;

    // VALIDATIE: Controleer of de velden leeg zijn en voeg een rode rand toe
    let isValid = true;

    // Voeg validatie toe voor de invoervelden
    if (thickness <= 0 || area <= 0 || bags <= 0) {
      // Voeg een rode rand toe als de velden leeg zijn
      if (thickness <= 0) {
        $calculator.find('.egaline-mm').css('border', '1px solid red');
      }
      if (area <= 0) {
        $calculator.find('.egaline-m2').css('border', '1px solid red');
      }
      if (bags <= 0) {
        $calculator.find('.result-bags').css('border', '1px solid red');
      }

      alert(__("Vul alle vereiste gegevens in de calculator in voordat u het product toevoegt aan de winkelwagen.", 'text-domain'));
      isValid = false;
    } else {
      // Reset de randkleur als de velden geldig zijn
      $calculator.find('.egaline-mm, .egaline-m2, .result-bags').css('border', '');
    }

    // Voorkom verzending van het formulier als de validatie niet slaagt
    if (!isValid) {
      return false; // Stop met verzenden
    }

    // Indien validatie slaagt, haal overige velden op.
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
            $(this).find('.calc-variation-price').html(`(${cleanPrice})`).show();
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
          $(this).find('.calc-variation-price').html(`(${cleanPrice})`).show();
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