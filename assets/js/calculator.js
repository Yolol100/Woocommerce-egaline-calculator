"use strict";

const { __ } = window.wp && wp.i18n ? wp.i18n : { __: (text) => text };

jQuery(document).ready(($) => {
  const config = {
    defaultKgPerBag: 15, // Standaard gewicht per zak (kg)
  };

  const calculatorStorageKey = "egaline_calculator_data";
  const darkModeStorageKey = "egaline_calculator_dark";

  const showCalcError = (calculator, message) => {
    let $msg = calculator.find(".calc-error");
    if (!$msg.length) {
      $msg = $('<p id="egaline-calculator-error" class="calc-error" role="alert" aria-live="polite"></p>').prependTo(calculator);
    }
    $msg.text(message).show();
    setTimeout(() => {
      $msg.fadeOut();
    }, 5000);
  };

  /**
   * Initialiseert een enkele calculator-instantie.
   * @param {jQuery} calculator - Het jQuery-element van de calculator.
   */
  const initializeCalculator = (calculator) => {
    // Cache belangrijke DOM-elementen binnen de calculator
    const $inputs = {
      egalineMm: calculator.find(".egaline-mm"),              // Invoer: dikte of aantal lagen in mm
      egalineM2: calculator.find(".egaline-m2"),              // Invoer: oppervlakte in m²
      resultBags: calculator.find(".result-bags"),            // Weergave: aantal benodigde zakken
      resultKg: calculator.find(".result-kg"),                // Weergave: totaal gewicht (kg)
      totalPrice: calculator.find(".total-price-value"),       // Weergave: totaalprijs (kan korting bevatten)
      variationId: calculator.find(".calculator-variation-id"), // Verborgen veld: variatie-ID
      qtyBtns: calculator.find(".qty-btn"),                   // Plus/min knoppen
      labelEgalineMm: calculator.find(".egaline-mm-label"),   // Label mm invoer
      labelEgalineM2: calculator.find(".egaline-m2-label"),   // Label m2 invoer
      labelResultBags: calculator.find(".result-bags-label"), // Label zakken
    };

    // Data-attributen ophalen met fallback
    const KG_PER_MM = parseFloat(calculator.data("kg-per-mm")) || 0;
    const KG_PER_M2 = parseFloat(calculator.data("kg-per-m2")) || 0;
    const BAG_WEIGHT = parseFloat(calculator.data("kg-per-bag")) || config.defaultKgPerBag;
    const discountThreshold = parseInt(calculator.data("discount-threshold"), 10) || 0;
    const discountPercentage = parseFloat(calculator.data("discount-percentage")) || 0;

    let manualOverride = false;

    const formatNumber = (number) => Math.round(number).toString();

    // Update labels volgens rekenmodus
    const updateLabel = () => {
      $inputs.labelEgalineMm.text("Hoe dik egaliseren in mm?");
      $inputs.labelEgalineM2.text("Aantal m² egaliseren?");
      $inputs.labelResultBags.text(`Aantal zakken (${BAG_WEIGHT}kg)`);
    };

    // Standaardwaarden resetten
    const initializeDefaults = () => {
      $inputs.egalineMm.val(0);
      $inputs.egalineM2.val(0);
      $inputs.resultBags.val(0);
      $inputs.resultKg.text("0");
      $inputs.totalPrice.text("0 EUR");
      manualOverride = false;
    };

    // Gewicht berekenen op basis van dikte en oppervlakte
    const calculateNeededKg = () => {
      const thickness = parseFloat($inputs.egalineMm.val()) || 0;
      const area = parseFloat($inputs.egalineM2.val()) || 0;
      return thickness * area * KG_PER_MM + area * KG_PER_M2;
    };

    // Resultaten berekenen en bijwerken
    const updateResults = () => {
      let totalKg, calculatedBags;

      let pricePerBag = parseFloat(calculator.data("regular-price")) || 0;
      pricePerBag = parseFloat(calculator.data("variation-price")) || pricePerBag;

      const $form = calculator.closest("form.cart");
      const quantity = $form.find("input.qty").length
        ? parseInt($form.find("input.qty").val(), 10) || 1
        : 1;

      totalKg = calculateNeededKg();
      const computedBags = Math.ceil(totalKg / BAG_WEIGHT);
      const currentSacks = parseInt($inputs.resultBags.val(), 10) || 0;

      if (manualOverride && currentSacks > 0) {
        calculatedBags = currentSacks < computedBags ? computedBags : currentSacks;
      } else {
        calculatedBags = computedBags;
      }

      const multipliedTotalKg = totalKg * quantity;
      const multipliedBags = calculatedBags * quantity;
      const originalTotal = calculatedBags * pricePerBag;
      const multipliedOriginalTotal = originalTotal * quantity;

      if (multipliedBags >= discountThreshold && discountPercentage > 0) {
        const discountAmount = multipliedOriginalTotal * (discountPercentage / 100);
        const discountedTotal = multipliedOriginalTotal - discountAmount;
        $inputs.totalPrice.text(`Korting: ${discountPercentage}% (${discountedTotal.toFixed(2)}) EUR`);
      } else {
        $inputs.totalPrice.text(`${multipliedOriginalTotal.toFixed(2)} EUR`);
      }

      $inputs.resultKg.text(formatNumber(multipliedTotalKg));
      if (!$inputs.resultBags.is(":focus")) {
        $inputs.resultBags.val(calculatedBags);
      }
      updateLabel();
    };

    const getRequiredKgFactor = () => KG_PER_M2 + ((parseFloat($inputs.egalineMm.val()) || 0) * KG_PER_MM);

    // Calculator uitschakelen (voor variabele producten zonder geselecteerde variatie)
    const disableCalculator = () => {
      calculator.addClass("calculator-disabled");
      $inputs.egalineMm.prop("disabled", true).css("opacity", "0.5");
      $inputs.egalineM2.prop("disabled", true).css("opacity", "0.5");
      $inputs.resultBags.prop("disabled", true).css("opacity", "0.5");
      $inputs.qtyBtns.prop("disabled", true).css("opacity", "0.5");
    };

    // Calculator inschakelen
    const enableCalculator = () => {
      calculator.removeClass("calculator-disabled");
      $inputs.egalineMm.prop("disabled", false).css("opacity", "1");
      $inputs.egalineM2.prop("disabled", false).css("opacity", "1");
      $inputs.resultBags.prop("disabled", false).css("opacity", "1");
      $inputs.qtyBtns.prop("disabled", false).css("opacity", "1");
    };

    const productType = calculator.data("product-type");
    if (productType === "variable" && !$inputs.variationId.val()) {
      disableCalculator();
    }

    // Waarschuwing bij focus als calculator uitgeschakeld is
    const warnIfDisabled = function () {
      if (productType === "variable" && !$inputs.variationId.val()) {
        showCalcError(
          calculator,
          __("Kies eerst een variatie voordat u de calculator gebruikt.", "egaline-calculator")
        );
        $(this).blur();
        return false;
      }
      return true;
    };

    $inputs.egalineMm.on("focus", warnIfDisabled);
    $inputs.egalineM2.on("focus", warnIfDisabled);
    $inputs.resultBags.on("focus", warnIfDisabled);

    // Event handlers voor invoervelden
    $inputs.egalineMm.on("input change", () => {
      const thickness = parseFloat($inputs.egalineMm.val()) || 0;
      let areaVal = parseFloat($inputs.egalineM2.val()) || 0;
      let sacksVal = parseInt($inputs.resultBags.val(), 10) || 0;
      if (thickness > 0) {
        if (areaVal <= 0 && sacksVal <= 0) {
          areaVal = 1;
          $inputs.egalineM2.val("1").trigger("change");
        } else {
          const requiredKgFactor = getRequiredKgFactor();
          if (requiredKgFactor > 0) {
            const newM2 = (sacksVal * BAG_WEIGHT) / requiredKgFactor;
            $inputs.egalineM2.val(parseFloat(newM2.toFixed(2)).toString()).trigger("change");
          }
        }
      }
      updateResults();
    });

    $inputs.egalineM2.on("input change", () => {
      updateResults();
    });

    $inputs.resultBags.on("input change", () => {
      const val = parseInt($inputs.resultBags.val(), 10) || 0;
      manualOverride = val > 0;
      const requiredKgFactor = getRequiredKgFactor();
      const newM2 = requiredKgFactor > 0 ? (val * BAG_WEIGHT) / requiredKgFactor : NaN;
      if (!isNaN(newM2) && isFinite(newM2) && newM2 > 0) {
        $inputs.egalineM2.val(parseFloat(newM2.toFixed(2)).toString()).trigger("change");
      }
      updateResults();
    });

    // Plus/min knoppen
    $inputs.qtyBtns.off("click.qty").on("click.qty", (e) => {
      e.preventDefault();
      const $button = $(e.currentTarget);
      const $input = $button.closest(".input-wrapper").find("input");
      const currentVal = parseFloat($input.val()) || 0;
      const step = parseFloat($input.attr("step")) || 1;
      const isPlus = $button.hasClass("plus");
      const newVal = isPlus ? currentVal + step : Math.max(0, currentVal - step);
      $input.val(step < 1 ? parseFloat(newVal.toFixed(1)).toString() : Math.round(newVal)).trigger("input");
    });

    // Producthoeveelheid verandert
    calculator.closest("form.cart").find("input.qty").on("change", () => {
      updateResults();
    });

    // Reset event
    calculator.on("resetCalculator", function () {
      initializeDefaults();
      updateResults();
    });

    calculator.find(".calculator-reset").on("click", () => {
      calculator.trigger("resetCalculator");
    });

    calculator.find(".toggle-dark-mode").on("click", () => {
      calculator.toggleClass("dark-mode");
      const isDark = calculator.hasClass("dark-mode");
      try {
        window.localStorage.setItem(darkModeStorageKey, isDark ? "1" : "0");
      } catch (error) {
        // Storage can be unavailable in private browsing or restricted contexts.
      }
    });

    if (!$inputs.egalineMm.length || !$inputs.egalineM2.length || !$inputs.resultBags.length) {
      return;
    }

    updateLabel();
    initializeDefaults();
    updateResults();
  };

  // Initialiseer alle calculators
  const initCalculators = () => {
    $(".egaline-calculator").each(function () {
      if ($(this).data("calculator-initialized")) return;
      $(this).data("calculator-initialized", true);
      initializeCalculator($(this));
    });
  };

  initCalculators();

  const saveCalculatorData = () => {
    try {
      const $calculator = $(".egaline-calculator").first();
      const calculatorData = {
        mm: $(".egaline-mm").val(),
        m2: $(".egaline-m2").val(),
        bags: $(".result-bags").val(),
      };
      window.localStorage.setItem(calculatorStorageKey, JSON.stringify(calculatorData));
      window.localStorage.setItem(darkModeStorageKey, $calculator.hasClass("dark-mode") ? "1" : "0");
    } catch (error) {
      // Storage can be unavailable in private browsing or restricted contexts.
    }
  };

  const loadCalculatorData = () => {
    try {
      const savedData = window.localStorage.getItem(calculatorStorageKey);
      if (savedData) {
        const { mm, m2, bags } = JSON.parse(savedData);
        $(".egaline-mm").val(mm).trigger("change");
        $(".egaline-m2").val(m2).trigger("change");
        $(".result-bags").val(bags).trigger("change");
      }
      if (window.localStorage.getItem(darkModeStorageKey) === "1") {
        $(".egaline-calculator").addClass("dark-mode");
      }
    } catch (error) {
      // Invalid JSON or unavailable storage should not break the calculator.
    }
  };

  $(".egaline-mm, .egaline-m2, .result-bags").on("input change", saveCalculatorData);
  loadCalculatorData();


  // Submit handler voor winkelwagen formulier met validatie en hidden inputs
  $("form.cart")
    .off("submit.calculatorSubmit")
    .on("submit.calculatorSubmit", function () {
      const $form = $(this);
      $form.find("input.egaline-hidden").remove();

      let $calculator = $(".egaline-calculator:visible").first();
      if (!$calculator.length) {
        $calculator = $(".egaline-calculator").first();
      }

      const quantity = parseInt($form.find("input.qty").val(), 10) || 1;
      const thickness = parseFloat($calculator.find(".egaline-mm").val()) || 0;
      const area = parseFloat($calculator.find(".egaline-m2").val()) || 0;
      const bags = parseInt($calculator.find(".result-bags").val(), 10) || 0;

      let isValid = true;

      if (thickness <= 0 || area <= 0 || bags <= 0) {
        if (thickness <= 0) $calculator.find(".egaline-mm").css("border", "1px solid red").attr("aria-invalid", "true");
        if (area <= 0) $calculator.find(".egaline-m2").css("border", "1px solid red").attr("aria-invalid", "true");
        if (bags <= 0) $calculator.find(".result-bags").css("border", "1px solid red").attr("aria-invalid", "true");
        showCalcError(
          $calculator,
          __(
            "Vul alle vereiste gegevens in de calculator in voordat u het product toevoegt aan de winkelwagen.",
            "egaline-calculator"
          )
        );
        isValid = false;
      } else {
        $calculator.find(".egaline-mm, .egaline-m2, .result-bags").css("border", "").attr("aria-invalid", "false");
      }

      if (!isValid) return false;

      $("<input>", {
        type: "hidden",
        name: "egaline_mm",
        value: thickness,
        class: "egaline-hidden",
      }).appendTo($form);

      $("<input>", {
        type: "hidden",
        name: "egaline_m2",
        value: area,
        class: "egaline-hidden",
      }).appendTo($form);

      $("<input>", {
        type: "hidden",
        name: "result_bags",
        value: bags,
        class: "egaline-hidden",
      }).appendTo($form);
    });

  // Variatieformulier en prijsupdates
  const $variationsForm = $("form.variations_form");
  if ($variationsForm.length) {
    $variationsForm.on("woocommerce_variations_loaded", () => {
      const variations = $variationsForm.data("product_variations");
      if (variations && variations.length > 0) {
        const defaultVariation = variations[0];
        if (defaultVariation && defaultVariation.display_price) {
          const tempDiv = document.createElement("div");
          tempDiv.innerHTML = defaultVariation.price_html;
          let cleanPrice = tempDiv.querySelector(".woocommerce-Price-amount");
          cleanPrice = cleanPrice ? cleanPrice.textContent.trim() : defaultVariation.price_html;
          $(".egaline-calculator").each(function () {
            $(this).data("variation-price", defaultVariation.display_price);
            $(this).find(".calc-variation-price").html(`(${cleanPrice})`).show();
          });
        }
      }
    });

    $variationsForm.on("found_variation", (event, variation) => {
      if (variation.display_price) {
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = variation.price_html;
        let cleanPrice = tempDiv.querySelector(".woocommerce-Price-amount");
        cleanPrice = cleanPrice ? cleanPrice.textContent.trim() : variation.price_html;
        $(".egaline-calculator").each(function () {
          $(this).data("variation-price", variation.display_price);
          $(this).find(".calc-variation-price").html(`(${cleanPrice})`).show();
          $(this).find(".calculator-variation-id").val(variation.variation_id);
          $(this).removeClass("calculator-disabled");
          $(this).find(".egaline-mm, .egaline-m2, .result-bags, .qty-btn").prop("disabled", false).css("opacity", "1");
          $(this).find(".egaline-mm, .egaline-m2, .result-bags").val(0).trigger("change");
          $(this).find(".result-kg").text("0");
          $(this).find(".total-price-value").text("0 EUR");
        });
        $(".calculator-warning").hide();
      }
    });
  }

  // Ajax: Mini-cart verversen na toevoegen/update, alleen wanneer WooCommerce cart fragments beschikbaar zijn.
  $(document.body).on("added_to_cart updated_cart_totals", () => {
    if (typeof window.wc_cart_fragments_params === "undefined" || !window.wc_cart_fragments_params.wc_ajax_url) {
      return;
    }

    $.ajax({
      url: window.wc_cart_fragments_params.wc_ajax_url.replace("%%endpoint%%", "get_refreshed_fragments"),
      method: "POST",
      success: (response) => {
        if (response && response.fragments) {
          $.each(response.fragments, (key, value) => {
            $(key).replaceWith(value);
          });
        }
      },
    });
  });

  // Reset calculator bij variatie reset
  $variationsForm.on("reset_data", () => {
    $(".egaline-calculator").each(function () {
      $(this).trigger("resetCalculator");
      if ($(this).data("product-type") === "variable") {
        $(this).addClass("calculator-disabled");
        $(this).find(".calculator-variation-id").val("");
        $(this).find(".egaline-mm, .egaline-m2, .result-bags, .qty-btn").prop("disabled", true).css("opacity", "0.5");
      }
    });
    $(".calculator-warning").show();
  });
});
