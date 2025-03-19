document.addEventListener("DOMContentLoaded", () => {
  const modeSelect = document.querySelector("#calculation_mode");
  const labelKgPerMm = document.querySelector("#label_kg_per_mm");

  if (modeSelect && labelKgPerMm) {
    modeSelect.addEventListener("change", (event) => {
      labelKgPerMm.textContent = event.target.value === "kg_per_mm" 
        ? "Kg per mm" 
        : "Lagen per mm";
    });
  }
});
