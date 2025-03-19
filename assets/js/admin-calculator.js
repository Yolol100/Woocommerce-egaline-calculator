// 2025 Modernized Version
document.addEventListener("DOMContentLoaded", () => {
    const [modeSelect, labelKgPerMm] = [
        document.getElementById("calculation_mode"),
        document.getElementById("label_kg_per_mm")
    ];

    modeSelect?.addEventListener("change", ({ target }) => {
        labelKgPerMm?.replaceChildren(
            target.value === "kg_per_mm" ? "Kg per mm" : "Lagen per mm"
        );
    });
});
