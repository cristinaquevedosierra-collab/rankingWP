/* ============================================================================
 * Hall of Fame: filtro + orden + paginación (25/50/100/Todos + páginas 1..N)
 * Basado en tu script antiguo, manteniendo alias de orden y disclaimers.
 * ============================================================================ */
(function () {
  function strip(s) {
    return (s || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  }
  function num(v){ return (v == null || v === '') ? 0 : Number(v); }
  function str(v){ return (v == null) ? '' : String(v); }

  function tryParseJSON(s, fb){ try { return JSON.parse(s); } catch(e){ return fb; } }

  function renderRows(rowsEl, list, profileUrl) {
    const frag = document.createDocumentFragment();
    list.forEach(p => {
      const pos = parseInt(p.posicion_estatica || 0, 10) || 0;
      const posC = pos >= 1 && pos <= 3 ? (" pos-" + pos) : "";
      const pid = p.id || p.jugador_id || null;
      const name = p.nombre || "N/D";
      const partidas = p.partidas_jugadas ?? "-";
      const ganadas  = p.partidas_ganadas ?? "-";
      const wrPart   = p.win_rate_partidos ?? "-";
      const compJug  = p.competiciones_jugadas ?? "-";
      const compGan  = p.competiciones_ganadas ?? "-";
      const wrComp   = p.win_rate_competiciones ?? "-";
      const perfil   = (pid && profileUrl) ? (profileUrl + (profileUrl.includes("?") ? "&" : "?") + "jugador_id=" + pid) : "#";

      const row = document.createElement("div");
      row.className = "ranking-row";
      row.innerHTML = `
        <div class="ranking-cell pos"><span class="badge${posC}">${pos || "-"}</span></div>
        <div class="ranking-cell ranking-player-name-cell">${perfil !== "#" ? `<a href="${perfil}">${name}</a>` : name}</div>
        <div class="ranking-cell">${partidas}</div>
        <div class="ranking-cell">${ganadas}</div>
        <div class="ranking-cell">${wrPart}<span>%</span></div>
        <div class="ranking-cell">${compJug}</div>
        <div class="ranking-cell">${compGan}</div>
        <div class="ranking-cell">${wrComp}<span>%</span></div>
      `;
      frag.appendChild(row);
    });
    rowsEl.innerHTML = "";
    rowsEl.appendChild(frag);
  }

  function init() {
    const wrapper = document.querySelector(".futbolin-hall-of-fame-wrapper");
    if (!wrapper) return;
    // Ignorar wrappers usados por Rankgen u otras vistas que no sean Hall of Fame
    try {
      if (wrapper.getAttribute && (wrapper.getAttribute('data-rankgen') === '1' || wrapper.getAttribute('data-rf-no-hof') === '1')) {
        return;
      }
      if (wrapper.id && wrapper.id.indexOf('rf-rankgen-') === 0) { return; }
    } catch(_e) {}

    const tableContent  = wrapper.querySelector(".ranking-table-content");
    const rowsPre       = wrapper.querySelector("#hof-rows-prerender");
    const rowsEl        = wrapper.querySelector("#hof-rows");
    const searchInput   = wrapper.querySelector(".futbolin-live-search, .futbolin-live-filter");
    const sortHeaders   = wrapper.querySelectorAll(".sortable-header");
    const pageForm      = wrapper.querySelector(".page-size-form");
    const pageButtons   = pageForm ? pageForm.querySelectorAll(".psize-btn, button") : [];
    const pager         = document.getElementById("hof-pager");
    const pagerNumbers  = document.getElementById("hof-pager-numbers");
    const btnPrev       = document.getElementById("hof-btn-prev");
    const btnNext       = document.getElementById("hof-btn-next");
    const pageNowEl     = document.getElementById("hof-page-now");
    const pageTotEl     = document.getElementById("hof-page-total");
    const profileUrl    = (tableContent && tableContent.getAttribute("data-profile-url")) || "";

    // dataset completo
    const allNode  = document.getElementById("hof-data-all-json");
    let players    = allNode ? tryParseJSON(allNode.textContent, []) : [];
    if (!players || !players.length) {
      // si por lo que sea falta, usa prerender
      players = [];
      rowsPre?.querySelectorAll(".ranking-row").forEach(r => {
        const name = r.querySelector(".ranking-player-name-cell")?.textContent.trim() || "";
        const pos  = parseInt(r.querySelector(".badge")?.textContent || "0", 10) || 0;
        players.push({ nombre: name, posicion_estatica: pos });
      });
    }
    if (!players.length) return;

    // estado
    const SORT_ALIAS = { 'posicion_estatica': 'win_rate_partidos' }; // igual que antes
    function effectiveSortKey(k){ return SORT_ALIAS[k] || k; }
    let orderBy  = "win_rate_partidos";
    let orderDir = "desc";

    function getInitialSize() {
      const activeBtn = pageForm ? pageForm.querySelector(".active") : null;
      if (activeBtn) {
        const ds = (activeBtn.dataset.size || activeBtn.value || activeBtn.textContent || "").trim().toLowerCase();
        if (ds === "all" || ds === "todos" || ds === "0") return -1;
        const n = Number(ds); return (isFinite(n) && n > 0) ? n : 25;
      }
      return 25;
    }
    let pageSize = getInitialSize();       // 25/50/100 o -1 para "Todos"
    let currentPage = 1;                   // 1..N
    let q = (searchInput && searchInput.value) ? searchInput.value.trim() : "";

    // helpers ordenación/filtrado
    function applySort(list) {
      const key = effectiveSortKey(orderBy);
      return list.slice().sort((a,b) => {
        if (key === "nombre") {
          const sa = str(a[key]).toLowerCase(), sb = str(b[key]).toLowerCase();
          if (sa < sb) return (orderDir === 'asc') ? -1 : 1;
          if (sa > sb) return (orderDir === 'asc') ?  1 : -1;
          return 0;
        } else {
          const na = num(a[key]), nb = num(b[key]);
          return (orderDir === 'asc') ? (na - nb) : (nb - na);
        }
      });
    }
    function getFiltered() {
      if (!q) return players;
      const s = strip(q);
      return players.filter(p => strip(p.nombre || "").includes(s));
    }

    // paginación
    function buildNumberedPager(totalPages) {
      if (!pagerNumbers) return;
      pagerNumbers.innerHTML = "";
      if (totalPages <= 1) { pagerNumbers.style.display = "none"; return; }
      pagerNumbers.style.display = "";

      const frag = document.createDocumentFragment();
      for (let i=1; i<=totalPages; i++) {
        const a = document.createElement("a");
        a.href = "#";
        a.className = "button hof-page-num" + (i === currentPage ? " active" : "");
        a.textContent = String(i);
        a.addEventListener("click", (e) => {
          e.preventDefault();
          if (currentPage !== i) { currentPage = i; render(); }
        });
        frag.appendChild(a);
      }
      pagerNumbers.appendChild(frag);
    }

    // render principal
    function render() {
      const filtered = applySort(getFiltered());
      const total = filtered.length;

      const totalPages = (pageSize <= 0) ? 1 : Math.max(1, Math.ceil(total / pageSize));
      if (currentPage > totalPages) currentPage = totalPages;

      // mostrar/ocultar pagers
      if (pager) {
        pager.style.display = (totalPages > 1) ? "" : "none";
        if (pageNowEl) pageNowEl.textContent = String(currentPage);
        if (pageTotEl) pageTotEl.textContent = String(totalPages);
      }
      buildNumberedPager(totalPages);

      // filas
      if (pageSize <= 0) {
        rowsEl.style.display = "";
        rowsPre && (rowsPre.style.display = "none");
        renderRows(rowsEl, filtered, profileUrl);
      } else {
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;
        rowsEl.style.display = "";
        rowsPre && (rowsPre.style.display = "none");
        renderRows(rowsEl, filtered.slice(start, end), profileUrl);
      }
    }

    // eventos
    if (searchInput) {
      let t; searchInput.addEventListener("input", function(){
        clearTimeout(t);
        t = setTimeout(() => { q = this.value.trim(); currentPage = 1; render(); }, 180);
      });
    }
    sortHeaders.forEach(h => {
      h.addEventListener("click", function(e){
        e.preventDefault();
        const clickedKey = this.getAttribute("data-sort");
        const newKey = effectiveSortKey(clickedKey);
        if (effectiveSortKey(orderBy) === newKey) {
          orderDir = (orderDir === "asc") ? "desc" : "asc";
          orderBy  = clickedKey;
        } else {
          orderBy  = clickedKey;
          orderDir = this.getAttribute("data-default") || "asc";
        }
        // UI de flechas
        sortHeaders.forEach(sh => { sh.classList.remove("active","asc","desc"); sh.querySelector(".sort-arrow") && (sh.querySelector(".sort-arrow").textContent=""); });
        const arrow = this.querySelector(".sort-arrow");
        this.classList.add("active", orderDir);
        if (arrow) arrow.textContent = (orderDir === "asc" ? "▲" : "▼");

        // alias: marca también “Posición” cuando el real es % ganados
        if (effectiveSortKey(orderBy) === "win_rate_partidos") {
          sortHeaders.forEach(sh => {
            if (sh.getAttribute("data-sort") === "posicion_estatica") {
              sh.classList.add("active", orderDir);
              sh.querySelector(".sort-arrow") && (sh.querySelector(".sort-arrow").textContent = (orderDir === "asc" ? "▲" : "▼"));
            }
          });
        }
        currentPage = 1;
        render();
      });
    });
    if (pageForm) pageForm.addEventListener("submit", e => e.preventDefault());
    pageButtons.forEach(btn => {
      // normaliza dataset.size
      if (!btn.dataset.size) {
        const v = (btn.value || btn.textContent || "").trim().toLowerCase();
        btn.dataset.size = (v === "todos" || v === "all" || v === "0") ? "all" : v;
      }
      btn.addEventListener("click", (e) => {
        e.preventDefault(); e.stopPropagation();
        pageButtons.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        const ds = btn.dataset.size;
        pageSize = (ds === "all") ? -1 : (Number(ds) > 0 ? Number(ds) : 25);
        currentPage = 1;
        render();
      });
    });
    btnPrev && btnPrev.addEventListener("click", (e) => {
      e.preventDefault();
      if (currentPage > 1) { currentPage--; render(); }
    });
    btnNext && btnNext.addEventListener("click", (e) => {
      e.preventDefault();
      const filteredLen = applySort(getFiltered()).length;
      const totalPages = (pageSize <= 0) ? 1 : Math.max(1, Math.ceil(filteredLen / pageSize));
      if (currentPage < totalPages) { currentPage++; render(); }
    });

    // init
    render();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else { init(); }
})();
