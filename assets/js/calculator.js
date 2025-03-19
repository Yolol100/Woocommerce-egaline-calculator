"use strict";

// Wanneer de DOM volledig geladen is, voer de code uit.
jQuery(document).ready(($) => {
  // Configuratie-object met standaardwaarden
  const config = {
    defaultKgPerBag: 15, // Standaard gewicht per zak (kg)
  };

  // ---------------------------------------------------------------------------
  // CALCULATOR INITIALISATIE & FUNCTIES (per instantie)
  // ---------------------------------------------------------------------------
  /**
   * Initialiseert een enkele calculator-instantie.
   *
   * @param {jQuery} calculator - Het jQuery-element van de calculator.
   */
  const initializeCalculator = (calculator) => {
    // Cache de belangrijke DOM-elementen binnen de calculator voor later gebruik
    const $inputs = {
      egalineMm: calculator.find(".egaline-mm"), // Invoer: dikte of aantal lagen in mm
      egalineM2: calculator.find(".egaline-m2"), // Invoer: oppervlakte in m²
      resultBags: calculator.find(".result-bags"), // Weergave: aantal benodigde zakken
      resultKg: calculator.find(".result-kg"), // Weergave: totaal gewicht (kg)
      totalPrice: calculator.find(".total-price span"), // Weergave: totaalprijs (kan korting bevatten)
      variationId: calculator.find(".calculator-variation-id"), // Verborgen veld: variatie-ID
      qtyBtns: calculator.find(".qty-btn"), // Knoppen voor plus/minus bewerkingen
      labelEgalineMm: calculator.find(".egaline-mm-label"), // Label voor de mm-invoer
      labelEgalineM2: calculator.find(".egaline-m2-label"), // Label voor de m²-invoer
      labelResultBags: calculator.find(".result-bags-label"), // Label voor het aantal zakken
    };

    // Haal configuratie uit data-attributen, met fallback naar standaardwaarden
    const KG_PER_MM = parseFloat(calculator.data("kg-per-mm")) || 0;
    const KG_PER_M2 = parseFloat(calculator.data("kg-per-m2")) || 0;
    const BAG_WEIGHT =
      parseFloat(calculator.data("kg-per-bag")) || config.defaultKgPerBag;
    const calculationMode = calculator.data("calculation-mode") || "kg_per_mm";

    // Lees kortingsinstellingen uit de data-attributen
    const discountThreshold =
      parseInt(calculator.data("discount-threshold"), 10) || 0;
    const discountPercentage =
      parseFloat(calculator.data("discount-percentage")) || 0;

    // Houd bij welk veld als laatste is bewerkt (dit beïnvloedt de berekening)
    let lastEdited = null;
    // Flag om aan te geven dat de gebruiker handmatig het aantal zakken heeft aangepast
    let manualOverride = false;

    // Hulpfunctie: formatteert een getal naar een afgeronde string
    const formatNumber = (number) => Math.round(number).toString();

    // Update de labels op basis van de ingestelde rekenmodus
    const updateLabel = () => {
      $inputs.labelEgalineMm.text(
        calculationMode === "kg_per_mm"
          ? "Hoe dik egaliseren in mm?"
          : "Aantal lagen in mm?"
      );
      $inputs.labelEgalineM2.text("Aantal m² egaliseren?");
      $inputs.labelResultBags.text(`Aantal zakken (${BAG_WEIGHT}kg)`);
    };

    // Zet de standaardwaarden in de invoervelden (reset)
    const initializeDefaults = () => {
      $inputs.egalineMm.val(0);
      $inputs.egalineM2.val(0);
      $inputs.resultBags.val(0);
      $inputs.resultKg.text("0");
      $inputs.totalPrice.text("0 EUR");
      manualOverride = false;
    };

    // Bereken het benodigde gewicht (in kg) op basis van de ingevoerde dikte en oppervlakte
    const calculateNeededKg = () => {
      const thickness = parseFloat($inputs.egalineMm.val()) || 0;
      const area = parseFloat($inputs.egalineM2.val()) || 0;
      return thickness * area * KG_PER_MM + area * KG_PER_M2;
    };

    /**
     * Bereken en update de resultaten: totaalgewicht, aantal zakken en totaalprijs.
     * Hierbij wordt de producthoeveelheid (quantity) van het omringende formulier meegenomen.
     */
    const updateResults = () => {
      let totalKg, calculatedBags;
      // Haal de reguliere prijs per zak uit data-attributen (of variatieprijs als beschikbaar)
      let pricePerBag = parseFloat(calculator.data("regular-price")) || 0;
      const varPrice =
        parseFloat(calculator.data("variation-price")) || pricePerBag;
      pricePerBag = varPrice;

      // Haal de producthoeveelheid op uit het omringende formulierelement (standaard 1 als niet gevonden)
      const $form = calculator.closest("form.cart");
      const quantity = $form.find("input.qty").length
        ? parseInt($form.find("input.qty").val(), 10) || 1
        : 1;

      // Bepaal de nieuwe berekening op basis van mm en m²
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
        $inputs.totalPrice.text(
          `Korting: ${discountPercentage}% (${discountedTotal.toFixed(2)}) EUR`
        );
      } else {
        $inputs.totalPrice.text(multipliedOriginalTotal.toFixed(2) + " EUR");
      }

      $inputs.resultKg.text(formatNumber(multipliedTotalKg));
      if (!$inputs.resultBags.is(":focus")) {
        $inputs.resultBags.val(calculatedBags);
      }
      updateLabel();
    };

    // ---------------------------
    // FUNCTIES VOOR UIT-/INSCHAKELEN VAN DE CALCULATOR (voor variabele producten)
    // ---------------------------
    const disableCalculator = () => {
      calculator.addClass("calculator-disabled");
      $inputs.egalineMm.prop("disabled", true).css("opacity", "0.5");
      $inputs.egalineM2.prop("disabled", true).css("opacity", "0.5");
      $inputs.resultBags.prop("disabled", true).css("opacity", "0.5");
      $inputs.qtyBtns.prop("disabled", true).css("opacity", "0.5");
    };

    const enableCalculator = () => {
      calculator.removeClass("calculator-disabled");
      $inputs.egalineMm.prop("disabled", false).css("opacity", "1");
      $inputs.egalineM2.prop("disabled", false).css("opacity", "1");
      $inputs.resultBags.prop("disabled", false).css("opacity", "1");
      $inputs.qtyBtns.prop("disabled", false).css("opacity", "1");
    };

    // Controleer bij initialisatie of het een variabel product betreft zonder geselecteerde variatie.
    const productType = calculator.data("product-type"); // Dit attribuut dient in PHP toegevoegd te worden.
    if (productType === "variable" && !$inputs.variationId.val()) {
      disableCalculator();
    }

    // Wanneer een variatie wordt geselecteerd, schakel de calculator in, verwijder de disabled-class en reset de waarden.
    $("form.variations_form").on("found_variation", (event, variation) => {
      $(".egaline-calculator").each(function () {
        const $calc = $(this);
        if ($calc.data("product-type") === "variable") {
          enableCalculator();
          $calc.find(".calculator-variation-id").val(variation.variation_id);
          // Reset de invoervelden en update de resultaten bij variatiewijziging.
          $calc.find(".egaline-mm, .egaline-m2, .result-bags").val(0).trigger("change");
          $calc.find(".result-kg").text("0");
          $calc.find(".total-price span").text("0 EUR");
        }
      });
      // Verberg de waarschuwing als er een variatie is gekozen
      $(".calculator-warning").hide();
      console.log(`Nieuwe variatie geselecteerd: ${variation.variation_id}`);
    });

    // Extra waarschuwing: als de gebruiker focust op een invoerveld terwijl de calculator nog is uitgeschakeld,
    // geven we een melding en voorkomen we dat er ingevoerd wordt.
    const warnIfDisabled = function () {
      if (productType === "variable" && !$inputs.variationId.val()) {
        alert("Kies eerst een variatie voordat u de calculator gebruikt.");
        $(this).blur();
        return false;
      }
      return true;
    };

    $inputs.egalineMm.on("focus", warnIfDisabled);
    $inputs.egalineM2.on("focus", warnIfDisabled);
    $inputs.resultBags.on("focus", warnIfDisabled);
    // ---------------------------
    // EINDE FUNCTIES UIT-/INSCHAKELEN
    // ---------------------------

    // Valideer de invoervelden; lege velden krijgen een rode rand
    const validateFields = () => {
      let isValid = true;
      $inputs.egalineMm.each(function () {
        if ($(this).val() === "") {
          $(this).css("border", "1px solid red");
          isValid = false;
        } else {
          $(this).css("border", "");
        }
      });
      $inputs.egalineM2.each(function () {
        if ($(this).val() === "") {
          $(this).css("border", "1px solid red");
          isValid = false;
        } else {
          $(this).css("border", "");
        }
      });
      $inputs.resultBags.each(function () {
        if ($(this).val() === "") {
          $(this).css("border", "1px solid red");
          isValid = false;
        } else {
          $(this).css("border", "");
        }
      });
      if (!isValid) {
        alert("Vul alle velden in voordat u verder gaat!");
      }
      return isValid;
    };

    // Bind een click-event aan de 'Calculate'-knop voor extra validatie
    $("#calculate_button").on("click", () => {
      if (!validateFields()) return false;
    });

    // ---------------------------
    // EVENT-HANDLERS VOOR DE INVOERVELDEN
    // ---------------------------

    // Wanneer de gebruiker de dikte (of aantal lagen) wijzigt:
    // - Als de oppervlakte en het aantal zakken nog 0 zijn, stel dan een startwaarde (bijv. 1 m²) in.
    // - Anders wordt de oppervlakte herberekend op basis van de huidige waarde van Aantal zakken.
    $inputs.egalineMm.on("input change", () => {
      lastEdited = "mm";
      const thickness = parseFloat($inputs.egalineMm.val()) || 0;
      let areaVal = parseFloat($inputs.egalineM2.val()) || 0;
      let sacksVal = parseInt($inputs.resultBags.val(), 10) || 0;
      if (thickness > 0) {
        if (areaVal <= 0 && sacksVal <= 0) {
          // Als beide nog 0 zijn, gebruik een standaard startwaarde
          areaVal = 1;
          $inputs.egalineM2.val("1").trigger("change");
        } else {
          // Anders: recalc de oppervlakte zodat de huidige hoeveelheid zakken behouden blijft
          const newM2 = (sacksVal * BAG_WEIGHT) / (thickness * KG_PER_MM + KG_PER_M2);
          $inputs.egalineM2.val(parseFloat(newM2.toFixed(2)).toString()).trigger("change");
        }
      }
      updateResults();
    });

    // Wanneer de gebruiker direct de oppervlakte wijzigt, update alleen de berekening.
    $inputs.egalineM2.on("input change", () => {
      lastEdited = "m2";
      updateResults();
    });

    // Wanneer de gebruiker het aantal zakken wijzigt, herbereken de oppervlakte (zodat deze consistent is met de huidige dikte)
    $inputs.resultBags.on("input change", () => {
      lastEdited = "sacks";
      const val = parseInt($inputs.resultBags.val(), 10) || 0;
      manualOverride = val > 0;
      const thickness = parseFloat($inputs.egalineMm.val()) || 0;
      const newM2 = (val * BAG_WEIGHT) / (thickness * KG_PER_MM + KG_PER_M2);
      if (!isNaN(newM2) && newM2 > 0) {
        $inputs.egalineM2.val(parseFloat(newM2.toFixed(2)).toString()).trigger("change");
      }
      updateResults();
    });

    // Verbind plus/minus knoppen voor het aanpassen van de waarden
    $inputs.qtyBtns
      .off("click.qty")
      .on("click.qty", (e) => {
        e.preventDefault();
        const $button = $(e.currentTarget);
        const $input = $button.closest(".input-wrapper").find("input");
        const currentVal = parseFloat($input.val()) || 0;
        const step = parseFloat($input.attr("step")) || 1;
        const isPlus = $button.hasClass("plus");
        const newVal = isPlus ? currentVal + step : Math.max(0, currentVal - step);
        $input
          .val(step < 1 ? parseFloat(newVal.toFixed(1)).toString() : Math.round(newVal))
          .trigger("input");
      });

    // Bind ook een change-event op de producthoeveelheid zodat de calculator direct herberekent wanneer dit verandert
    calculator
      .closest("form.cart")
      .find("input.qty")
      .on("change", () => {
        updateResults();
      });

    // Voeg een custom event toe aan de calculator om deze te resetten
    calculator.on("resetCalculator", function () {
      initializeDefaults();
      updateResults();
      console.log("Calculator gereset via resetCalculator event.");
    });

    // Initialiseer de labels, standaardwaarden en bereken de eerste resultaten
    updateLabel();
    initializeDefaults();
    updateResults();
  };

  // ---------------------------------------------------------------------------
  // INITIALISEER ALLE CALCULATOREN (desktop en mobiel)
  // ---------------------------------------------------------------------------
  const initCalculators = () => {
    $(".egaline-calculator").each(function () {
      if ($(this).data("calculator-initialized")) {
        return;
      }
      $(this).data("calculator-initialized", true);
      initializeCalculator($(this));
      console.log("Calculator instance initialized:", $(this));
    });
  };

  initCalculators();

  // ---------------------------------------------------------------------------
  // GLOBALE SUBMIT-HANDLER VOOR HET WINKELWAGEN-FORMULIER MET INPUT-VALIDATIE
  // ---------------------------------------------------------------------------
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
        if (thickness <= 0) {
          $calculator.find(".egaline-mm").css("border", "1px solid red");
        }
        if (area <= 0) {
          $calculator.find(".egaline-m2").css("border", "1px solid red");
        }
        if (bags <= 0) {
          $calculator.find(".result-bags").css("border", "1px solid red");
        }
        alert(
          "Vul alle vereiste gegevens in de calculator in voordat u het product toevoegt aan de winkelwagen."
        );
        isValid = false;
      } else {
        $calculator.find(".egaline-mm, .egaline-m2, .result-bags").css("border", "");
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

      const variation_id = $calculator.find(".calculator-variation-id").val();
      if (variation_id) {
        $("<input>", {
          type: "hidden",
          name: "variation_id",
          value: variation_id,
          class: "egaline-hidden",
        }).appendTo($form);
      }
    });

  // ---------------------------------------------------------------------------
  // VARIATIEFORMULIER & PRIJSUPDATES (update ALLE calculator-instanties)
  // ---------------------------------------------------------------------------
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
          cleanPrice = cleanPrice
            ? cleanPrice.textContent.trim()
            : defaultVariation.price_html;
          $(".egaline-calculator").each(function () {
            $(this).data("variation-price", defaultVariation.display_price);
            $(this)
              .find(".calc-variation-price")
              .html(`(${cleanPrice})`)
              .show();
          });
        }
      }
    });

    $variationsForm.on("found_variation", (event, variation) => {
      if (variation.display_price) {
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = variation.price_html;
        let cleanPrice = tempDiv.querySelector(".woocommerce-Price-amount");
        cleanPrice = cleanPrice
          ? cleanPrice.textContent.trim()
          : variation.price_html;
        $(".egaline-calculator").each(function () {
          $(this).data("variation-price", variation.display_price);
          $(this)
            .find(".calc-variation-price")
            .html(`(${cleanPrice})`)
            .show();
          $(this).find(".calculator-variation-id").val(variation.variation_id);
          $(this).removeClass("calculator-disabled");
          $(this)
            .find(".egaline-mm, .egaline-m2, .result-bags")
            .val(0)
            .trigger("change");
          $(this).find(".result-kg").text("0");
          $(this).find(".total-price span").text("0 EUR");
        });
        // Verberg de waarschuwing zodra er een variatie is gekozen
        $(".calculator-warning").hide();
        console.log(`Nieuwe variatie geselecteerd: ${variation.variation_id}`);
      }
    });
  }

  // ---------------------------------------------------------------------------
  // AJAX: Vernieuw de mini-cart na toevoegen van een product of update van de cart
  // ---------------------------------------------------------------------------
  jQuery(($) => {
    $(document.body).on("added_to_cart updated_cart_totals", () => {
      $.ajax({
        url: wc_cart_fragments_params.wc_ajax_url.replace(
          "%%endpoint%%",
          "get_refreshed_fragments"
        ),
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
  });

  // ---------------------------------------------------------------------------
  // RESET CALCULATOR BIJ "WIJZEN" VAN VARIATIES
  // ---------------------------------------------------------------------------
  $("form.variations_form").on("reset_data", function () {
    $(".egaline-calculator").each(function () {
      const calculator = $(this);
      calculator.trigger("resetCalculator");
      console.log("Calculator gereset na variatie-wissen.");
    });
    // Toon de waarschuwing als er geen variatie is geselecteerd
    $(".calculator-warning").show();
  });
});

const calculatorStorageKey = "egaline_calculator_data";

const saveCalculatorData = () => {
  const calculatorData = {
    mm: $(".egaline-mm").val(),
    m2: $(".egaline-m2").val(),
    bags: $(".result-bags").val(),
  };
  localStorage.setItem(calculatorStorageKey, JSON.stringify(calculatorData));
};

const loadCalculatorData = () => {
  const savedData = localStorage.getItem(calculatorStorageKey);
  if (savedData) {
    const { mm, m2, bags } = JSON.parse(savedData);
    $(".egaline-mm").val(mm).trigger("change");
    $(".egaline-m2").val(m2).trigger("change");
    $(".result-bags").val(bags).trigger("change");
  }
};

$(".egaline-mm, .egaline-m2, .result-bags").on("input change", saveCalculatorData);

$(document).ready(loadCalculatorData);
