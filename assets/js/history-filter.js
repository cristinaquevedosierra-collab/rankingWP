
(function(){
  // Shadow-aware v32: wire per-root (document and any ShadowRoot)
  var __wired = (typeof WeakSet !== 'undefined') ? new WeakSet() : { has: function(){return false;}, add: function(){} };

  function bootHistoryFilter(root){
    try { if (!root) root = document; } catch(_){ root = document; }
    try { if (__wired.has(root)) return; __wired.add(root); } catch(_){ }

    function $all(sel, ctx){
      try { return Array.prototype.slice.call((ctx||root).querySelectorAll(sel)); } catch(_){ return []; }
    }
    function $one(sel, ctx){ try { return (ctx||root).querySelector(sel); } catch(_){ return null; } }
    function lower(s){ return (s||'').toString().toLowerCase(); }
    function norm(s){
      // Preservar la ñ/Ñ durante la normalización
      s = lower(s);
      try {
        s = s
          .replace(/ñ/g, '__enye__')
          .replace(/Ñ/g, '__ENYE__')
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .replace(/__enye__/g, 'ñ')
          .replace(/__ENYE__/g, 'ñ');
      } catch(_){ }
      return s.replace(/\s+/g,' ').trim();
    }
    // Detectar si el UI avanzado Shadow (rf-shadow.js) está activo en este root
    var __shadowEnhanced = false;
    function __detectShadowEnhanced(){
      try {
        var hasSplit = (root.querySelector && root.querySelector('.history-split-counters'));
        var hasDD = (root.querySelector && root.querySelector('.history-search-dropdown.rf-live-inline'));
        __shadowEnhanced = !!(hasSplit || hasDD);
      } catch(_){ __shadowEnhanced = false; }
    }
    __detectShadowEnhanced();
    function text(el){ return el && el.textContent ? norm(el.textContent) : ''; }
    function getPlayerName(){
      var el = $one('#history-player-name');
      var v = el ? (el.value||'') : '';
      return norm(v);
    }
    function ensureGlobalLabel(){
      var host = $one('.history-summary-search');
      if (!host) return null;
      var label = $one('#history-global-label');
      if (!label){
        label = document.createElement('div');
        label.id = 'history-global-label';
        label.className = 'history-global-label';
        label.setAttribute('role','heading');
        label.setAttribute('aria-level','2');
        // Siempre visible por defecto: el SSR ya la muestra y nunca se debe ocultar en carga
        label.style.display = 'block';
        label.style.marginBottom = '6px';
        label.style.fontWeight = '700';
        // Texto estándar requerido
        label.textContent = 'RESULTADOS GLOBALES';
        var cards = host.querySelector('.history-summary-cards');
        if (cards) host.insertBefore(label, cards);
        else host.insertBefore(label, host.firstChild);
      }
      return label;
    }
    function showGlobalLabel(){
      var label = ensureGlobalLabel();
      if (label) label.style.display = '';
    }
    function hideGlobalLabel(){
      // No ocultar la etiqueta global nunca; restaurar texto por defecto
      var label = $one('#history-global-label');
      if (label){
        label.style.display = '';
        if (!label.textContent || /datos globales/i.test(label.textContent)){
          label.textContent = 'RESULTADOS GLOBALES';
        }
      }
    }

    // -------- Context helpers
    function detailsText(row){
      var d = row.querySelector('.history-match-details');
      return d ? text(d) : text(row);
    }
    function winnerText(row){
      var w = row.querySelector('.history-match-winner');
      return text(w);
    }
    function loserText(row){
      var l = row.querySelector('.history-match-loser');
      return text(l);
    }
    function getPlayerId(){
      var el = $one('#history-player-id');
      var v = el ? parseInt(el.value,10) : NaN;
      return isNaN(v) ? null : v;
    }
    function rowHasPlayer(row, pid){
      if (!pid) return true;
      var ds = row.getAttribute('data-players') || '';
      var parts = ds.split(/[,;\s]+/);
      for (var i=0;i<parts.length;i++){ if (parseInt(parts[i],10) === pid) return true; }
      return false;
    }
    function compTitleForList(list){
      var prev = list.previousElementSibling;
      if (prev && prev.classList && prev.classList.contains('history-competition-title')) return prev;
      var inner = list.querySelector('.history-competition-title');
      return inner || null;
    }
    function torneoHeader(block){
      return block ? block.querySelector('.tournament-header') : null;
    }
    function hide(el){ if (el) try{ el.style.setProperty('display','none','important'); }catch(e){} }
    function show(el){ if (el) try{ el.style.removeProperty('display'); }catch(e){} }

    // === Top counters helpers (sumario principal)
    var __baseCountsCaptured = false;
    var __baseCounts = { total: 0, won: 0, lost: 0, rate: 0 };
    function captureBaseCounts(){
      if (__baseCountsCaptured) return;
      try {
        var host = $one('.history-summary-search');
        if (!host) return;
        var t = $one('#hs-count-total', host);
        var w = $one('#hs-count-won', host);
        var l = $one('#hs-count-lost', host);
        var r = $one('#hs-count-rate', host);
        var tV = t ? parseInt((t.textContent||'0').replace(/[^0-9]/g,''), 10) : 0;
        var wV = w ? parseInt((w.textContent||'0').replace(/[^0-9]/g,''), 10) : 0;
        var lV = l ? parseInt((l.textContent||'0').replace(/[^0-9]/g,''), 10) : 0;
        var rV = 0;
        if (r) {
          var m = (r.textContent||'').match(/([0-9]+(?:\.[0-9]+)?)/);
          rV = m ? parseFloat(m[1]) : 0;
        }
        __baseCounts = { total: tV||0, won: wV||0, lost: lV||0, rate: rV||0 };
        __baseCountsCaptured = true;
      } catch(_){ }
    }
    function setTopStats(labelText, counts){
      var host = $one('.history-summary-search');
      if (!host) return;
      var label = $one('#history-global-label', host);
      if (label){
        label.textContent = labelText || 'RESULTADOS GLOBALES';
        try { label.style.display='block'; label.style.width='100%'; label.style.flexBasis='100%'; } catch(_){ }
      }
      try {
        var t = $one('#hs-count-total', host);
        var w = $one('#hs-count-won', host);
        var l = $one('#hs-count-lost', host);
        var r = $one('#hs-count-rate', host);
        if (counts){
          if (t) t.textContent = String(counts.total||0);
          if (w) w.textContent = String(counts.won||0);
          if (l) l.textContent = String(counts.lost||0);
          var rateVal = (counts.total > 0) ? Math.round((counts.won*1000.0 / counts.total))/10 : 0;
          if (typeof counts.rate === 'number') rateVal = counts.rate;
          if (r) r.textContent = rateVal + '%';
        }
      } catch(_){ }
    }

    // v16 minimal helpers: separator + 'FILTRO ACTIVO' flag (no reflow, no CSS reorg)
    function ensureFilterSep(){
      var host = $one('.history-summary-search');
      if (!host) return null;
      var sep = $one('#history-v-sep');
      var counters = $one('#history-filter-counters');
      var cards = host && host.querySelector('.history-summary-cards');
      if (!sep){
        sep = document.createElement('div');
        sep.id = 'history-v-sep';
        sep.className = 'history-v-sep';
      }
      // make sure it's between cards and counters
      if (sep.parentNode !== host || (counters && sep.nextSibling !== counters)){
        if (counters) host.insertBefore(sep, counters);
        else if (cards && cards.nextSibling) host.insertBefore(sep, cards.nextSibling);
        else host.appendChild(sep);
      }
      // match height to counters box
      if (counters){
        requestAnimationFrame(function(){
          try { sep.style.height = counters.getBoundingClientRect().height + 'px'; sep.style.alignSelf='flex-start'; } catch(_){ }
        });
      }
      return sep;
    }
    function ensureFilterFlag(){
      var host = $one('.history-summary-search');
      if (!host) return null;
      var flag = $one('#history-filter-flag');
      if (!flag){
        flag = document.createElement('div');
        flag.id = 'history-filter-flag';
        flag.className = 'filter-active-flag';
        flag.textContent = 'FILTRO ACTIVO';
        host.appendChild(flag);
      }
      return flag;
    }
    function showFilterUI(){
      var host = $one('.history-summary-search');
      if (!host) return;
      host.classList.add('filter-on');
      ensureFilterSep();
      ensureFilterFlag();
    }
    function hideFilterUI(){
      var host = $one('.history-summary-search');
      if (!host) return;
      host.classList.remove('filter-on');
    }
    // -------- Counter UI
    function ensureCounterBox(){
      var host = $one('.history-summary-search');
      if (!host) return null;
      var box = $one('#history-filter-counters');
      if (!box){
        box = document.createElement('div');
        box.id = 'history-filter-counters';
        box.setAttribute('aria-live','polite');
        box.style.marginTop = '8px';
        box.style.border = '1px dashed #ddd';
        box.style.padding = '10px';
        box.style.borderRadius = '8px';
        box.style.fontSize = '14px';
        var cards = host.querySelector('.history-summary-cards');
        if (cards && cards.nextSibling) host.insertBefore(box, cards.nextSibling);
        else host.appendChild(box);
      }
      return box;
    }
    function clearBox(){
      var box = $one('#history-filter-counters');
      if (box){ box.remove(); }
      hideFilterUI();
      // Restaurar etiqueta superior a "RESULTADOS GLOBALES" y mantenerla visible
      __detectShadowEnhanced();
      if (!__shadowEnhanced){
        var label = $one('#history-global-label');
        if (label){
          label.textContent = 'RESULTADOS GLOBALES';
          try { label.style.display = 'block'; label.style.width = '100%'; label.style.flexBasis = '100%'; } catch(_){ }
        }
      }
    }
    function renderH2HCounters(query, stats){
      showGlobalLabel(); showFilterUI();
      // Durante búsqueda con coincidencias, la etiqueta debe indicar Rival
      __detectShadowEnhanced();
      if (!__shadowEnhanced){
        var label = $one('#history-global-label');
        if (label){
          label.textContent = 'RESULTADOS COMO RIVAL';
          try { label.style.display = 'block'; label.style.width = '100%'; label.style.flexBasis = '100%'; } catch(_){ }
        }
      }
      var box = ensureCounterBox();
      if (!box) return;
      var q = (query||'').trim();
      var rival = stats.rival;
      var mate  = stats.mate;
      var total = rival.j + mate.j;
      box.innerHTML = ''
        + '<div style="font-weight:600;margin-bottom:6px;">Filtro (H2H): “'+ q.replace(/</g,'&lt;').replace(/>/g,'&gt;') +'”</div>'
        + '<div style="margin-bottom:4px;"><strong>Apariciones totales:</strong> '+ total +'</div>'
        + '<div style="margin-bottom:2px;"><strong>Como rival:</strong> jugadas '+ rival.j +' · ganadas '+ rival.g +' · perdidas '+ rival.p +'</div>'
        + '<div><strong>Como compañero:</strong> jugadas '+ mate.j +' · ganadas '+ mate.g +' · perdidas '+ mate.p +'</div>';
    }

    function computeH2HStats(query){
      var selfName = getPlayerName();
      var _q = norm(query||"");
      var qIsSelf = !!_q && (selfName.indexOf(_q) !== -1 || _q.indexOf(selfName) !== -1);
      var q = norm(query||'');
      var pid = getPlayerId();
      var rival = { j:0, g:0, p:0 };
      var mate  = { j:0, g:0, p:0 };
      var rows = $all('.history-match-row');
      for (var i=0;i<rows.length;i++){
        var row = rows[i];
        if (row.style.display === 'none') continue;
        var cs = window.getComputedStyle ? getComputedStyle(row) : null;
        if (cs && cs.display === 'none') continue;
        // Consideramos H2H solo cuando el término aparece en ganador o perdedor
        var wTxt = winnerText(row);
        var lTxt = loserText(row);
        var nameHit = (!!q && (wTxt.indexOf(q)!==-1 || lTxt.indexOf(q)!==-1));
        if (!nameHit) continue;
        if (!rowHasPlayer(row, pid)) continue;
        var winSide = (row.getAttribute('data-win') === '1');
        var nameOnW = (wTxt.indexOf(q) !== -1);
        var nameOnL = (lTxt.indexOf(q) !== -1);
        var sameSide = (winSide && nameOnW) || (!winSide && nameOnL);
        var bucket = sameSide ? mate : rival;
        if (qIsSelf) { continue; }
        bucket.j += 1;
        if (winSide) bucket.g += 1; else bucket.p += 1;
      }
      return { rival:rival, mate:mate };
    }

    // -------- Main filtering (same as v28, with call to counters)
    function applyFilter(query){
  __detectShadowEnhanced(); if (__shadowEnhanced) return; // En Shadow, lo gestiona rf-shadow.js
  var q = norm(query||'');
      var lists = $all('.history-matches-list');
      if (!lists.length){
        var table = $one('.futbolin-table');
        clearBox();
        if (table){
          var tbody = table.tBodies && table.tBodies[0] ? table.tBodies[0] : table.querySelector('tbody');
          if (!tbody) return;
          var rows = $all('tr', tbody);
          for (var i=0;i<rows.length;i++){
            var showRow = (q.length < 2) ? true : (text(rows[i]).indexOf(q) !== -1);
            if (showRow) show(rows[i]); else hide(rows[i]);
          }
        }
        return;
      }

      var pid = getPlayerId();
      var isName = false;
      if (q.length >= 2){
        outer: for (var a=0;a<lists.length;a++){
          var rowsA = $all('.history-match-row', lists[a]);
          for (var b=0;b<rowsA.length;b++){
            var dt = detailsText(rowsA[b]);
            var rt = text(rowsA[b]);
            if (dt.indexOf(q) !== -1 || rt.indexOf(q) !== -1){ isName = true; break outer; }
          }
        }
      }

      for (var i=0;i<lists.length;i++){
        var list = lists[i];
        var any = false;
        var rows = $all('.history-match-row', list);
        for (var j=0;j<rows.length;j++){
          var row = rows[j];
          var showRow;
          if (q.length < 2){
            showRow = true;
          } else if (isName){
            showRow = (detailsText(row).indexOf(q) !== -1) && rowHasPlayer(row, pid);
          } else {
            var block = row.closest('.history-tournament-block');
            var tor = text(torneoHeader(block));
            var comp = text(compTitleForList(list));
            var rowT = text(row);
            showRow = (tor && tor.indexOf(q)!==-1) || (comp && comp.indexOf(q)!==-1) || (rowT.indexOf(q)!==-1);
          }
          if (showRow){ show(row); any = true; } else { hide(row); }
        }
        if (any){ show(list); } else { hide(list); }
        var title = compTitleForList(list);
        if (title){ if (any) show(title); else hide(title); }
      }

      var blocks = $all('.history-tournament-block');
      for (var x=0;x<blocks.length;x++){
        var block = blocks[x];
        var listsIn = $all('.history-matches-list', block);
        var anyList = false;
        for (var k=0;k<listsIn.length;k++){ if (listsIn[k].style.display !== 'none') { anyList = true; break; } }
        var th = torneoHeader(block);
        if (th){ if (anyList) show(th); else hide(th); }
        if (anyList) show(block); else hide(block);
      }

      // Capturar contadores base en la primera ejecución
      captureBaseCounts();

      // Helper: stats de filas visibles (cualquier filtro)
      function computeVisibleStats(){
        var rows = $all('.history-match-row');
        var t=0,w=0,l=0;
        for (var i=0;i<rows.length;i++){
          var row = rows[i];
          if (row.style.display === 'none') continue;
          var cs = window.getComputedStyle ? getComputedStyle(row) : null;
          if (cs && cs.display === 'none') continue;
          t++;
          var winSide = (row.getAttribute('data-win') === '1');
          if (winSide) w++; else if (row.getAttribute('data-win') === '0') l++;
        }
        var rate = t>0 ? Math.round((w*1000.0/t))/10 : 0;
        return { total:t, won:w, lost:l, rate:rate };
      }

      if (q.length >= 1 && isName){
        var stats = computeH2HStats(q);
        var total = stats.rival.j + stats.mate.j;
        if (total > 0){
          renderH2HCounters(query, stats);
          // Actualizamos contadores superiores con Rival durante la búsqueda
          setTopStats('RESULTADOS COMO RIVAL', { total: stats.rival.j, won: stats.rival.g, lost: stats.rival.p });
        } else {
          clearBox();
          // Restaurar contadores globales
          setTopStats('RESULTADOS GLOBALES', __baseCounts);
        }
      } else {
        // Filtro genérico: limpiar box H2H y actualizar contadores con visibles
        clearBox();
        if (q.length >= 1){
          var vis = computeVisibleStats();
          setTopStats('RESULTADOS GLOBALES', vis);
        } else {
          // Restaurar contadores globales
          setTopStats('RESULTADOS GLOBALES', __baseCounts);
        }
      }
    }

    // Polling + event bindings scoped to this root
    var lastVal = null;
    function poll(){
      try{
        __detectShadowEnhanced(); if (__shadowEnhanced) return;
        var input = $one('#history-search');
        var v = input ? (input.value || '') : '';
        if (v !== lastVal){
          lastVal = v;
          // Aplicar desde la primera tecla
          if (v.length === 0 || v.length >= 1) applyFilter(v);
        }
      }catch(e){}
    }
    try { setInterval(poll, 250); } catch(_){ }

    try { (root.addEventListener||document.addEventListener).call(root, 'input', function(ev){ if (ev && ev.target && ev.target.id === 'history-search'){ lastVal = null; } }, true); } catch(_){ }
    try { (root.addEventListener||document.addEventListener).call(root, 'keyup', function(ev){ if (ev && ev.target && ev.target.id === 'history-search'){ lastVal = null; } }, true); } catch(_){ }
  }

  // Boot for main document
  bootHistoryFilter(document);
  // Boot for any existing Shadow hosts
  try {
    var hosts = document.querySelectorAll('ranking-futbolin-app');
    hosts.forEach(function(h){ if (h && h.shadowRoot) bootHistoryFilter(h.shadowRoot); });
  } catch(_){ }
  // Rebind on lazy hydration inside Shadow
  try {
    document.addEventListener('rf:tab:hydrated', function(e){
      try {
        var pane = e && e.detail && e.detail.pane;
        var root = pane && pane.getRootNode && pane.getRootNode();
        if (root) bootHistoryFilter(root);
      } catch(_){ }
    }, { passive: true });
  } catch(_){ }
})();
