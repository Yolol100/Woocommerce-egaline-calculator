document.addEventListener("DOMContentLoaded", () => {
  const modeSelect = document.querySelector("#calculation_mode");
  const labelKgPerMm = document.querySelector("#label_kg_per_mm");
  const fieldKgPerMm = document.querySelector("#field_kg_per_mm");
  const fieldKgPerM2 = document.querySelector("#field_kg_per_m2");

  if (!modeSelect) {
    console.warn("Element #calculation_mode niet gevonden in de DOM.");
    return;
  }

  const updateFields = () => {
    const value = modeSelect.value;

    if (labelKgPerMm) {
      labelKgPerMm.textContent = value === "layers_per_mm" ? "Lagen per mm" : "Kg per mm";
    }

    if (fieldKgPerMm && fieldKgPerM2) {
      if (value === "kg_per_m2") {
        fieldKgPerMm.style.display = "none";
        fieldKgPerM2.style.display = "";
      } else {
        fieldKgPerMm.style.display = "";
        fieldKgPerM2.style.display = "none";
      }
    }
  };

  modeSelect.addEventListener("change", updateFields);
  updateFields();
});
