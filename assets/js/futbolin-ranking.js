// assets/js/futbolin-ranking.js
(function () {
  function strip(s) {
    return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  }

  function initCard(card) {
    const input = card.querySelector(".futbolin-live-filter");
    const rowsWrap = card.querySelector("#ranking-rows, .ranking-rows");
    if (!input || !rowsWrap) return;

    const rows = Array.from(rowsWrap.querySelectorAll(".ranking-row"));
    if (rows.length === 0) return;

    function applyFilter(qRaw) {
      const q = strip((qRaw || "").trim());
      rows.forEach((r) => {
        // lee data-player o, si no existe, data-name (por compat)
        const dp = r.getAttribute("data-player");
        const dn = r.getAttribute("data-name");
        const n = strip(dp || dn || "");
        const c = strip(r.getAttribute("data-category") || "");
        r.style.display = !q || n.indexOf(q) !== -1 || c.indexOf(q) !== -1 ? "" : "none";
      });
    }

    // input funciona en teclado, pegar y móvil; keyup como respaldo
    input.addEventListener("input", function () { applyFilter(this.value); });
    input.addEventListener("keyup", function () { applyFilter(this.value); });
  }

  function initAll() {
    document.querySelectorAll(".futbolin-card").forEach(initCard);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }
})();
