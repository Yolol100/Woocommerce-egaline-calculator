document.addEventListener("DOMContentLoaded", () => {
  const modeSelect = document.querySelector("#calculation_mode");
  const labelKgPerMm = document.querySelector("#label_kg_per_mm");
  
  if (!modeSelect || !labelKgPerMm) {
    console.warn("Elementen #calculation_mode of #label_kg_per_mm niet gevonden in de DOM.");
    return;
  }
  
  const updateLabel = ({ target: { value } }) => {
    labelKgPerMm.textContent = value === "kg_per_mm" 
      ? "Kg per mm" 
      : "Lagen per mm";
  };
  
  modeSelect.addEventListener("change", updateLabel);
});
