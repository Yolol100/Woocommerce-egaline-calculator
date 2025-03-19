"use strict";

jQuery(($) => {
  const initializeCalculator = (calculator) => {
    const $inputs = {
      egalineMm: calculator.find(".egaline-mm"),
      egalineM2: calculator.find(".egaline-m2"),
      resultBags: calculator.find(".result-bags"),
      resultKg: calculator.find(".result-kg"),
      totalPrice: calculator.find(".total-price span"),
      variationId: calculator.find(".calculator-variation-id"),
      qtyBtns: calculator.find(".qty-btn"),
      labelEgalineMm: calculator.find(".egaline-mm-label"),
      labelEgalineM2: calculator.find(".egaline-m2-label"),
      labelResultBags: calculator.find(".result-bags-label"),
    };

    const config = {
      defaultKgPerBag: 15,
      calculatorStorageKey: "egaline_calculator_data",
    };

    const { defaultKgPerBag, calculatorStorageKey } = config;
    const KG_PER_MM = Number(calculator.data("kg-per-mm")) || 0;
    const KG_PER_M2 = Number(calculator.data("kg-per-m2")) || 0;
    const BAG_WEIGHT = Number(calculator.data("kg-per-bag")) || defaultKgPerBag;
    const calculationMode = calculator.data("calculation-mode") ?? "kg_per_mm";

    const discountThreshold = Number(calculator.data("discount-threshold")) || 0;
    const discountPercentage = Number(calculator.data("discount-percentage")) || 0;

    let lastEdited = null;
    let manualOverride = false;

    // Helper functions
    const formatNumber = (number) => Math.round(number).toString();
    const getFloatValue = (element) => Number(element.val()) || 0;
    const getIntValue = (element) => parseInt(element.val(), 10) || 0;

    // LocalStorage handlers
    const saveCalculatorData = () => {
      const calculatorData = {
        mm: $inputs.egalineMm.val(),
        m2: $inputs.egalineM2.val(),
        bags: $inputs.resultBags.val(),
      };
      localStorage.setItem(calculatorStorageKey, JSON.stringify(calculatorData));
    };

    const loadCalculatorData = () => {
      const savedData = localStorage.getItem(calculatorStorageKey);
      if (!savedData) return;

      const { mm, m2, bags } = JSON.parse(savedData);
      $inputs.egalineMm.val(mm).trigger("change");
      $inputs.egalineM2.val(m2).trigger("change");
      $inputs.resultBags.val(bags).trigger("change");
    };

    // Label management
    const updateLabels = () => {
      const mmLabel = calculationMode === "kg_per_mm" 
        ? "Hoe dik egaliseren in mm?" 
        : "Aantal lagen in mm?";
      
      $inputs.labelEgalineMm.text(mmLabel);
      $inputs.labelEgalineM2.text("Aantal m² egaliseren?");
      $inputs.labelResultBags.text(`Aantal zakken (${BAG_WEIGHT}kg)`);
    };

    // Initialization
    const initializeDefaults = () => {
      [$inputs.egalineMm, $inputs.egalineM2, $inputs.resultBags].forEach(el => el.val(0));
      $inputs.resultKg.text("0");
      $inputs.totalPrice.text("0 EUR");
      manualOverride = false;
    };

    // Core calculations
    const calculateNeededKg = () => {
      const thickness = getFloatValue($inputs.egalineMm);
      const area = getFloatValue($inputs.egalineM2);
      return (thickness * area * KG_PER_MM) + (area * KG_PER_M2);
    };

    const updateResults = () => {
      const totalKg = calculateNeededKg();
      const pricePerBag = Number(calculator.data("variation-price")) 
        || Number(calculator.data("regular-price")) 
        || 0;

      const $form = calculator.closest("form.cart");
      const quantity = getIntValue($form.find("input.qty")) || 1;

      const calculatedBags = manualOverride
        ? Math.max(getIntValue($inputs.resultBags), Math.ceil(totalKg / BAG_WEIGHT))
        : Math.ceil(totalKg / BAG_WEIGHT);

      const multipliedTotalKg = totalKg * quantity;
      const multipliedBags = calculatedBags * quantity;
      const totalPrice = multipliedBags * pricePerBag;

      const showDiscount = multipliedBags >= discountThreshold && discountPercentage > 0;
      const discountAmount = showDiscount ? totalPrice * (discountPercentage / 100) : 0;
      
      $inputs.totalPrice.text(showDiscount
        ? `Korting: ${discountPercentage}% (${(totalPrice - discountAmount).toFixed(2)} EUR)`
        : `${totalPrice.toFixed(2)} EUR`
      );

      $inputs.resultKg.text(formatNumber(multipliedTotalKg));
      if (!$inputs.resultBags.is(":focus")) {
        $inputs.resultBags.val(calculatedBags);
      }
    };

    // UI state management
    const toggleCalculatorState = (enabled) => {
      calculator.toggleClass("calculator-disabled", !enabled);
      const elements = [$inputs.egalineMm, $inputs.egalineM2, $inputs.resultBags, $inputs.qtyBtns];
      elements.forEach(el => el.prop("disabled", !enabled).css("opacity", enabled ? 1 : 0.5));
    };

    // Event handlers
    const handleInputChange = ({ target }) => {
      const $target = $(target);
      const thickness = getFloatValue($inputs.egalineMm);
      const area = getFloatValue($inputs.egalineM2);
      const bags = getIntValue($inputs.resultBags);

      if ($target.is(".egaline-mm")) {
        lastEdited = "mm";
        if (thickness > 0 && area <= 0 && bags <= 0) {
          $inputs.egalineM2.val(1).trigger("change");
        }
      }

      if ($target.is(".result-bags")) {
        lastEdited = "sacks";
        manualOverride = bags > 0;
        const newM2 = (bags * BAG_WEIGHT) / (thickness * KG_PER_MM + KG_PER_M2);
        $inputs.egalineM2.val(newM2.toFixed(2)).trigger("change");
      }

      updateResults();
      saveCalculatorData();
    };

    const handleQtyButton = (e) => {
      e.preventDefault();
      const $button = $(e.currentTarget);
      const $input = $button.closest(".input-wrapper").find("input");
      const currentVal = getFloatValue($input);
      const step = Number($input.attr("step")) || 1;
      const newVal = $button.hasClass("plus") ? currentVal + step : Math.max(0, currentVal - step);
      
      $input.val(step < 1 ? newVal.toFixed(1) : Math.round(newVal)).trigger("input");
    };

    // Initial setup
    const setupEventListeners = () => {
      const inputs = [$inputs.egalineMm, $inputs.egalineM2, $inputs.resultBags];
      inputs.forEach(input => input.on("input change", handleInputChange));

      $inputs.qtyBtns.on("click", handleQtyButton);
      calculator.closest("form.cart").find("input.qty").on("change", updateResults);
      
      $("form.variations_form")
        .on("found_variation reset_data", (event, variation) => {
          toggleCalculatorState(!!variation);
          if (event.type === "reset_data") initializeDefaults();
        });
    };

    // Boot process
    const init = () => {
      setupEventListeners();
      loadCalculatorData();
      updateLabels();
      updateResults();
      
      if (calculator.data("product-type") === "variable" && !$inputs.variationId.val()) {
        toggleCalculatorState(false);
      }
    };

    init();
  };

  // Initialize all calculators
  $(".egaline-calculator").each((i, el) => {
    const $calculator = $(el);
    if (!$calculator.data("initialized")) {
      $calculator.data("initialized", true);
      initializeCalculator($calculator);
    }
  });

  // Form submission handler
  $("form.cart").on("submit", function(e) {
    const $form = $(this);
    const $calculator = $(".egaline-calculator:visible").first();
    const requiredFields = ["egaline-mm", "egaline-m2", "result-bags"];

    const invalidFields = requiredFields
      .map(field => $calculator.find(`.${field}`))
      .filter(field => getFloatValue(field) <= 0);

    if (invalidFields.length) {
      e.preventDefault();
      invalidFields.forEach(field => field.css("border", "1px solid red"));
      alert("Vul alle vereiste velden correct in.");
      return false;
    }

    // Add hidden fields
    const fieldsToAdd = [
      { name: "egaline_mm", value: getFloatValue($calculator.find(".egaline-mm")) },
      { name: "egaline_m2", value: getFloatValue($calculator.find(".egaline-m2")) },
      { name: "result_bags", value: getIntValue($calculator.find(".result-bags")) },
      { name: "variation_id", value: $calculator.find(".calculator-variation-id").val() }
    ];

    fieldsToAdd.forEach(({ name, value }) => {
      $form.append(`<input type="hidden" name="${name}" value="${value}" class="egaline-hidden">`);
    });
  });

  // Price updates and variation handling
  const handlePriceUpdates = () => {
    $("form.variations_form").on("woocommerce_variations_loaded found_variation", (e, data) => {
      const variation = Array.isArray(data) ? data[0] : data;
      if (!variation?.display_price) return;

      const priceHTML = new DOMParser()
        .parseFromString(variation.price_html, "text/html")
        .querySelector(".woocommerce-Price-amount")?.textContent 
        || variation.price_html;

      $(".egaline-calculator").each((i, el) => {
        const $calc = $(el);
        $calc.data("variation-price", variation.display_price)
          .find(".calc-variation-price")
          .html(`(${priceHTML})`)
          .toggle(!!variation);
      });
    });
  };

  handlePriceUpdates();
});
