/* ============================================================================
 * Hall of Fame (filtro, ordenación, render, paginación 25/50/100/Todos)
 * Orden SIEMPRE sobre todo el dataset (hof-data-all-json); sin recargas.
 * ============================================================================ */
(function() {
  document.addEventListener('DOMContentLoaded', function() {
    var wrapper       = document.querySelector('.futbolin-hall-of-fame-wrapper');
    if (!wrapper) return;

    var tableContent  = wrapper.querySelector('.ranking-table-content');
    var rowsContainer = wrapper.querySelector('.ranking-rows');
    var searchInput   = wrapper.querySelector('.futbolin-live-search');
    var sortHeaders   = wrapper.querySelectorAll('.sortable-header');
    var pageForm      = wrapper.querySelector('.page-size-form');
    var pageButtons   = pageForm ? pageForm.querySelectorAll('button') : [];

    // -- Carga SIEMPRE el dataset completo --
    function tryParseJSON(s, fb){ try { return JSON.parse(s); } catch(e){ return fb; } }
    var allNode  = document.getElementById('hof-data-all-json');
    var pageNode = document.getElementById('hof-data-json');

    var playersAll  = allNode  ? tryParseJSON(allNode.textContent, [])  : [];
    var playersPage = pageNode ? tryParseJSON(pageNode.textContent, []) : [];
    var players     = Array.isArray(playersAll) && playersAll.length ? playersAll : playersPage;

    // -- Estado UI --
    var SORT_ALIAS = { 'posicion_estatica': 'win_rate_partidos' }; // Posición apunta a % ganados
    function effectiveSortKey(k){ return SORT_ALIAS[k] || k; }
    var orderBy  = 'win_rate_partidos';
    var orderDir = 'desc';

    function getInitialSize() {
      var activeBtn = pageForm ? pageForm.querySelector('button.active') : null;
      if (activeBtn) {
        var ds = activeBtn.dataset.size || activeBtn.value || activeBtn.textContent.trim();
        if (String(ds).toLowerCase() === 'todos') return 'all';
        var n = Number(ds); return (isFinite(n) && n > 0) ? n : 25;
      }
      return 25;
    }
    var currentSize = getInitialSize();

    // -- Orden y render SIEMPRE sobre el dataset completo --
    function num(v){ return (v == null || v === '') ? 0 : Number(v); }
    function str(v){ return (v == null) ? '' : String(v); }

    function applySort(list) {
      var key = effectiveSortKey(orderBy);
      return list.slice().sort(function(a,b){
        if (key === 'nombre') {
          var sa = str(a[key]).toLowerCase(), sb = str(b[key]).toLowerCase();
          if (sa < sb) return (orderDir === 'asc') ? -1 : 1;
          if (sa > sb) return (orderDir === 'asc') ?  1 : -1;
          return 0;
        } else {
          var na = num(a[key]), nb = num(b[key]);
          return (orderDir === 'asc') ? (na - nb) : (nb - na);
        }
      });
    }

    function render(list, size) {
      if (!rowsContainer) return;

      if (!Array.isArray(list) || list.length === 0) {
        rowsContainer.innerHTML = '<div class="no-results">No se encontraron resultados.</div>';
        return;
      }

      var limit = (size === 'all') ? list.length : parseInt(size, 10);
      if (!isFinite(limit) || limit <= 0) limit = 25;

      var profileUrl = tableContent ? tableContent.getAttribute('data-profile-url') : '';
      var html = '';
      for (var i = 0; i < Math.min(limit, list.length); i++) {
        var p = list[i];

        var posBadgeClass = 'badge';
        if (+p.posicion_estatica === 1) posBadgeClass += ' pos-1';
        else if (+p.posicion_estatica === 2) posBadgeClass += ' pos-2';
        else if (+p.posicion_estatica === 3) posBadgeClass += ' pos-3';

        var pid = p.id || p.jugador_id || '';
        var playerLink = (profileUrl && pid)
          ? profileUrl + (profileUrl.indexOf('?') > -1 ? '&' : '?') + 'jugador_id=' + encodeURIComponent(pid)
          : '#';

        html += ''
          + '<div class="ranking-row">'
          +   '<div class="ranking-cell pos"><span class="' + posBadgeClass + '">' + (p.posicion_estatica ?? '-') + '</span></div>'
          +   '<div class="ranking-cell ranking-player-name-cell"><a href="' + playerLink + '">' + (p.nombre || 'N/D') + '</a></div>'
          +   '<div class="ranking-cell">' + (p.partidas_jugadas ?? '-') + '</div>'
          +   '<div class="ranking-cell">' + (p.partidas_ganadas ?? '-') + '</div>'
          +   '<div class="ranking-cell">' + (p.win_rate_partidos ?? '-') + '<span>%</span></div>'
          +   '<div class="ranking-cell">' + (p.competiciones_jugadas ?? '-') + '</div>'
          +   '<div class="ranking-cell">' + (p.competiciones_ganadas ?? '-') + '</div>'
          +   '<div class="ranking-cell">' + (p.win_rate_competiciones ?? '-') + '<span>%</span></div>'
          + '</div>';
      }
      rowsContainer.innerHTML = html;
    }

    function setActiveSortHeader() {
      sortHeaders.forEach(function(h){
        h.classList.remove('active','asc','desc');
        var arrow = h.querySelector('.sort-arrow'); if (arrow) arrow.textContent = '';
      });
      var key = effectiveSortKey(orderBy);
      sortHeaders.forEach(function(h){
        if (h.getAttribute('data-sort') === key) {
          h.classList.add('active', orderDir);
          var arrow = h.querySelector('.sort-arrow'); if (arrow) arrow.textContent = (orderDir === 'asc' ? '▲' : '▼');
        }
      });
      // Alias: marcar también Posición cuando el orden real es por % ganados
      if (key === 'win_rate_partidos') {
        sortHeaders.forEach(function(h){
          if (h.getAttribute('data-sort') === 'posicion_estatica') {
            h.classList.add('active', orderDir);
            var arrow = h.querySelector('.sort-arrow'); if (arrow) arrow.textContent = (orderDir === 'asc' ? '▲' : '▼');
          }
        });
      }
    }

    // -- Filtro + listeners --
    function handleFilter() {
      var q = (searchInput ? searchInput.value : '').toLowerCase().trim();
      var base = q ? players.filter(function(p){ return (p.nombre || '').toLowerCase().includes(q); }) : players;
      var sorted = applySort(base);
      setActiveSortHeader();
      render(sorted, currentSize);
    }

    if (searchInput) {
      var debounce;
      searchInput.addEventListener('input', function(){
        clearTimeout(debounce);
        debounce = setTimeout(handleFilter, 200);
      });
    }

    sortHeaders.forEach(function(h){
      h.addEventListener('click', function(e){
        e.preventDefault();
        var clickedKey = this.getAttribute('data-sort');
        var newKey     = effectiveSortKey(clickedKey);
        if (effectiveSortKey(orderBy) === newKey) {
          orderDir = (orderDir === 'asc') ? 'desc' : 'asc';
          orderBy  = clickedKey;
        } else {
          orderBy  = clickedKey;
          orderDir = this.getAttribute('data-default') || 'asc';
        }
        handleFilter();
      });
    });

    // Evita que el formulario de tamaños haga submit
    if (pageForm) {
      pageForm.addEventListener('submit', function(e){ e.preventDefault(); });
    }
    pageButtons.forEach(function(btn){
      if (!btn.dataset.size) {
        var v = (btn.value || btn.textContent || '').trim().toLowerCase();
        btn.dataset.size = (v === 'todos' || v === 'all' || v === '0') ? 'all' : v;
      }
      btn.addEventListener('click', function(e){
        e.preventDefault();
        e.stopPropagation();
        pageButtons.forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');

        var ds = btn.dataset.size;
        if (ds === 'all') currentSize = 'all';
        else { var n = Number(ds); currentSize = (isFinite(n) && n > 0) ? n : 25; }
        handleFilter();
      });
    });

    // Init
    handleFilter();
  });
})();
