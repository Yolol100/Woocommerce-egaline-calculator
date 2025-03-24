document.addEventListener("DOMContentLoaded", () => {
  // Gebruik querySelector met nullish coalescing voor een moderne selectie
  const modeSelect = document.querySelector("#calculation_mode") ?? null;
  const labelKgPerMm = document.querySelector("#label_kg_per_mm") ?? null;

  // Geef een waarschuwing als een of beide elementen niet gevonden worden en stop de uitvoering
  if (!modeSelect || !labelKgPerMm) {
    console.warn("Een of beide elementen (#calculation_mode, #label_kg_per_mm) niet gevonden in de DOM.");
    return;
  }

  // Voeg een eventlistener toe met destructuring en template literal
  modeSelect.addEventListener("change", ({ target: { value } }) => {
    labelKgPerMm.textContent = value === "kg_per_mm"
      ? "Kg per mm"
      : "Lagen per mm";
  });
});