document.addEventListener("DOMContentLoaded", () => {
    const modeSelect = document.getElementById("calculation_mode");
    const labelKgPerMm = document.getElementById("label_kg_per_mm");

    // Controleer of beide elementen aanwezig zijn
    if (modeSelect && labelKgPerMm) {
        modeSelect.addEventListener("change", (event) => {
            labelKgPerMm.textContent = (event.target.value === "kg_per_mm") 
                ? __("Kg per mm", 'text-domain') 
                : __("Lagen per mm", 'text-domain');
        });
    }
});