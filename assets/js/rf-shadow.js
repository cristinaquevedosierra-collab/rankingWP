(() => {
  const CFG = (window.rfShadowSettings || {});
  const cssUrls = Array.isArray(CFG.cssUrls) ? CFG.cssUrls : [];
  const selector = CFG.wrapperSelector || '.futbolin-full-bleed-wrapper';
  const hostTag = 'ranking-futbolin-app';

  const RF_AUTOWIRE_SELECTOR = '.rf-search-form, form[data-rf-search]';
  function autowireAjaxSearch(rootOrDoc){
    const scope = rootOrDoc;
    const AJAX_URL = (window.ajaxurl || (window.rfShadowSettings && rfShadowSettings.ajaxUrl) || (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url) || '/wp-admin/admin-ajax.php');

    function closestResultsContainer(form){
      const attr = form.getAttribute('data-results-target');
      if (attr) {
        return (scope.querySelector ? scope.querySelector(attr) : null) || form.querySelector(attr);
      }
      return form.closest('.futbolin-full-bleed-wrapper')?.querySelector('.rf-search-results, [data-rf-results]')
          || form.querySelector('.rf-search-results, [data-rf-results]')
          || null;
    }

    function serializeForm(form){
      const fd = new FormData(form);
      if (!fd.has('action')) {
        const action = form.getAttribute('data-action');
        if (action) fd.append('action', action);
      }
      return fd;
    }

    function wire(form){
      if (!form || typeof form.matches !== 'function') return;
      if (form.getAttribute && form.getAttribute('data-rf-autowire') === '0') return;
      if (!form.matches(RF_AUTOWIRE_SELECTOR)) return;
if (form.dataset.rfAutowire === '1') return;
      form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const fd = serializeForm(form);
        const results = closestResultsContainer(form);
        form.classList.add('is-loading');
        fetch(form.getAttribute('action') || AJAX_URL, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
          const ct = r.headers.get('content-type') || '';
          if (ct.includes('application/json')) return r.json();
          return r.text();
        })
        .then(res => {
          if (!results) return;
          if (typeof res === 'string') {
            results.innerHTML = res;
          } else if (res && typeof res === 'object') {
            if (res.html) {
              results.innerHTML = res.html;
            } else {
              // Graceful fallback: extract message fields if present
              let msg = null;
              if (typeof res.message === 'string' && res.message.trim()) msg = res.message.trim();
              if (!msg && res.data && typeof res.data.message === 'string' && res.data.message.trim()) msg = res.data.message.trim();
              if (!msg && Array.isArray(res.errors) && res.errors.length && typeof res.errors[0] === 'string') msg = res.errors[0];
              if (!msg) msg = 'No se encontraron jugadores.';
              results.textContent = msg;
            }
          } else {
            results.textContent = 'No se encontraron jugadores.';
          }
        })
        .catch(err => {
          if (results) results.innerHTML = '<div class="rf-error">Error de búsqueda</div>';
          console.error('RF search error:', err);
        })
        .finally(() => form.classList.remove('is-loading'));
      });
      form.dataset.rfAutowire = '1';
    }

    function scan(){
      const forms = (scope.querySelectorAll ? scope.querySelectorAll(RF_AUTOWIRE_SELECTOR) : []);
      forms.forEach(wire);
    }

    scan();
    // Also watch interactions
    (scope.addEventListener ? scope : document).addEventListener('click', (e) => {
      const btn = e.target.closest('button, input[type="submit"]');
      if (!btn || !btn.form) return;
      if (btn.form && btn.form.matches && btn.form.matches(RF_AUTOWIRE_SELECTOR)) wire(btn.form);
    });
    (scope.addEventListener ? scope : document).addEventListener('focusin', (e) => {
      const form = e.target && e.target.form;
      if (form && form.matches && form.matches(RF_AUTOWIRE_SELECTOR)) wire(form);
    });
  }


  function shouldSkip(wrap){
    // Solo saltar Shadow si se indica explícitamente en el wrapper
    return wrap.hasAttribute('data-rf-no-shadow');
  }


  function cssEscapeIdent(id){
    if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(id);
    return id.replace(/[^a-zA-Z0-9_\-]/g, s => '\\' + s.charCodeAt(0).toString(16) + ' ');
  }

  function initTabs(scope){
    const nav = scope.querySelector('.futbolin-tabs-nav');
    // En la plantilla actual, cada panel es un .futbolin-tab-content (no .tab-pane)
    const panes = scope.querySelectorAll('.futbolin-tab-content');
    if (!nav || panes.length === 0) return;

    const links = nav.querySelectorAll('a[href^="#"]');

    function activate(id){
      const target = scope.querySelector('#' + cssEscapeIdent(id));
      if (!target) return;
      panes.forEach(p => p.classList.toggle('active', p === target));
      links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + id));
    }

    links.forEach(a => {
      a.addEventListener('click', (e) => {
        const href = a.getAttribute('href') || '';
        if (!href.startsWith('#')) return;
        e.preventDefault();
        const id = href.slice(1);
        activate(id);
        try {
          if (location.hash !== '#' + id) history.replaceState(null, '', '#' + id);
        } catch(_e){ /* noop */ }
      });
    });

    function syncFromHash(){
      const h = (location.hash || '').replace(/^#/, '');
      if (!h) return;
      const target = scope.querySelector('#' + cssEscapeIdent(h));
      if (target) activate(h);
    }

    window.addEventListener('hashchange', syncFromHash);
    // inicial
    syncFromHash();
  }

  // Filtros en vivo genéricos dentro del Shadow (para tablas/listas)
  function initLiveFilters(scope){
    function norm(s){ try { return String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim(); } catch(_) { return String(s||'').toLowerCase().trim(); } }

    // 1) Filtros genéricos .futbolin-live-filter (ranking/tablitas)
    (scope.querySelectorAll ? scope.querySelectorAll('.futbolin-live-filter') : []).forEach((input)=>{
      if (input.__rfFilterWired) return; input.__rfFilterWired = true;
      const root = input.closest('.futbolin-card, .futbolin-tab-content') || scope;
      const rowsWrap = root.querySelector('#ranking-rows, .ranking-rows') || root.querySelector('table tbody') || root;
      let rows = [];
      if (rowsWrap && (rowsWrap.matches && (rowsWrap.matches('tbody') || rowsWrap.tagName === 'TBODY'))) rows = Array.from(rowsWrap.querySelectorAll('tr'));
      else if (rowsWrap) rows = Array.from(rowsWrap.querySelectorAll('.ranking-row, .tournament-row, .rf-row, li, .row, tr'));
      if (!rows.length) return;
      const cache = new WeakMap();
      const getRowText = (r) => { const attrs = [r.getAttribute('data-player'), r.getAttribute('data-name'), r.getAttribute('data-category')].filter(Boolean).join(' '); return norm(attrs + ' ' + (r.textContent||'')); };
      const match = (r, q) => { let t = cache.get(r); if (!t) { t = getRowText(r); cache.set(r,t); } return !q || t.indexOf(q)!==-1; };
      const apply = (qRaw) => { const q = norm(qRaw); rows.forEach(r => { r.style.display = match(r,q) ? '' : 'none'; }); };
      const onInput = () => apply(input.value);
      input.addEventListener('input', onInput); input.addEventListener('keyup', onInput);
      if (input.value) apply(input.value);
    });

    // 2) Torneos – distribución
    (function(){
      const input = scope.querySelector ? scope.querySelector('#torneos-distribution-search') : null;
      if (!input || input.__rfFilterWired) return; input.__rfFilterWired = true;
      const sections = Array.from(scope.querySelectorAll('#tab-torneos .torneos-distribution-group'));
      const emptyMsg = scope.querySelector ? scope.querySelector('#torneos-distribution-empty') : null;
      function tokenize(s){ const t = norm(s); return t ? t.split(/\s+/).filter(Boolean) : []; }
      function filter(){
        const termTokens = tokenize(input.value); // reacciona desde la 1ª tecla
        let visibleRows = 0;
        sections.forEach((section)=>{
          const groupText = norm(section.getAttribute('data-search')||'');
          const groupTokens = groupText ? groupText.split(/\s+/).filter(Boolean) : [];
          const rows = Array.from(section.querySelectorAll('tbody tr'));
          let sectionHasMatch = false;
          rows.forEach((row)=>{
            const rowText = norm(row.getAttribute('data-search-row')||'');
            const rowTokens = rowText ? rowText.split(/\s+/).filter(Boolean) : [];
            const matches = !termTokens.length || termTokens.every((token)=>{ if(!token) return true; if (/^\d+$/.test(token)) { return rowTokens.indexOf(token)!==-1 || groupTokens.indexOf(token)!==-1; } return rowText.indexOf(token)!==-1 || groupText.indexOf(token)!==-1; });
            row.style.display = matches ? '' : 'none'; if (matches) { sectionHasMatch = true; visibleRows++; }
          });
          section.style.display = sectionHasMatch ? '' : 'none';
        });
        if (emptyMsg) emptyMsg.style.display = (termTokens.length && visibleRows===0) ? '' : 'none';
      }
      filter(); input.addEventListener('input', filter); input.addEventListener('change', filter);
    })();

    // 3) Torneos – participaciones
    (function(){
      const input = scope.querySelector ? scope.querySelector('#torneos-participation-search') : null;
      if (!input || input.__rfFilterWired) return; input.__rfFilterWired = true;
      const sections = Array.from(scope.querySelectorAll('#tab-torneos .torneos-participation-group'));
      const emptyMsg = scope.querySelector ? scope.querySelector('#torneos-participation-empty') : null;
      function filter(){
        const term = norm((input.value||'').trim()); // reacciona desde la 1ª tecla
        let visibleRows = 0;
        sections.forEach((section)=>{
          const groupText = norm(section.getAttribute('data-search')||'');
          const rows = Array.from(section.querySelectorAll('tbody tr'));
          let sectionHasMatch = false;
          rows.forEach((row)=>{
            const rowText = norm((row.getAttribute('data-search-row')||'')) + ' ' + norm(row.textContent||'');
            const matches = !term || rowText.indexOf(term)!==-1 || groupText.indexOf(term)!==-1;
            row.style.display = matches ? '' : 'none'; if (matches) { sectionHasMatch = true; visibleRows++; }
          });
          section.style.display = sectionHasMatch ? '' : 'none';
        });
        if (emptyMsg) emptyMsg.style.display = (term && visibleRows===0) ? '' : 'none';
      }
      filter(); input.addEventListener('input', filter); input.addEventListener('change', filter);
    })();

    // 4) Historial – buscador avanzado con cuadro Compañero/Rival (desde la 1ª pulsación)
    (function(){
      const wrap = scope.querySelector('#tab-historial'); if (!wrap) return;
      const box = wrap.querySelector('.history-summary-search'); if (!box) return;
      const input = box.querySelector('#history-search, input[type="search"], input[type="text"]'); if (!input) return;
      if (input.__rfFilterWired) return; input.__rfFilterWired = true;
  const getRows = () => Array.from(wrap.querySelectorAll('.ranking-row.history-match-row'));
      const flag = box.querySelector('.filter-active-flag, .filter-active-flag-inline');
      const playerNameEl = wrap.querySelector('#history-player-name');
      // Normalización específica para español: distingue "ñ"; simplifica solo vocales acentuadas
      const normEs = (s) => {
        let t = String(s||'').toLowerCase();
        t = t.replace(/[áàäâ]/g,'a').replace(/[éèëê]/g,'e').replace(/[íìïî]/g,'i').replace(/[óòöô]/g,'o').replace(/[úùüû]/g,'u');
        // Nota: no tocamos ñ/Ñ para mantener distinción con "n"
        return t.trim();
      };
      const playerName = playerNameEl ? normEs(playerNameEl.value) : '';

      // Crear dropdown
      let dd = box.querySelector('.history-search-dropdown');
      if (!dd) {
        dd = document.createElement('div');
        dd.className = 'history-search-dropdown rf-live-inline';
        // Estático debajo del buscador para que no lo tape
        dd.style.display = 'none';
        dd.style.marginTop = '8px';
        dd.style.background = '#fff';
        dd.style.border = '1px solid rgba(0,0,0,.12)';
        dd.style.borderRadius = '10px';
        dd.style.boxShadow = '0 10px 30px rgba(0,0,0,.12)';
        dd.style.maxHeight = '320px';
        dd.style.overflow = 'auto';
        const holder = box.querySelector('.history-search-box') || box;
        if (holder.nextSibling) box.insertBefore(dd, holder.nextSibling); else box.appendChild(dd);
      }
      // Panel inferior “Como compañero” duplicando el diseño de las tarjetas
      let split = box.querySelector('.history-split-counters');
      if (!split) {
        split = document.createElement('div');
        split.className = 'history-split-counters';
        split.style.display = 'none';
        split.style.marginTop = '12px';
        // Asegurar que cae en línea completa en layouts flex del contenedor
        split.style.width = '100%';
        split.style.flexBasis = '100%';
        split.innerHTML = ''+
          '<div class="split-block split-comp">'
          +  '<div class="split-title" style="font-weight:700;color:#374151;margin:12px 2px 8px;">RESULTADOS COMO COMPAÑERO</div>'
          +  '<div class="history-summary-cards">'
          +    '<div class="hs-item hs-total"><span>Jugadas</span><strong class="v" data-k="comp-total">0</strong></div>'
          +    '<div class="hs-item hs-won"><span>Ganadas</span><strong class="v" data-k="comp-won">0</strong></div>'
          +    '<div class="hs-item hs-lost"><span>Perdidas</span><strong class="v" data-k="comp-lost">0</strong></div>'
          +    '<div class="hs-item hs-rate"><span>% Victorias</span><strong class="v" data-k="comp-rate">0%</strong></div>'
          +  '</div>'
          +'</div>';
        // Colocar el bloque inferior DESPUÉS de la caja de búsqueda para no mover el input
        const searchBox = box.querySelector('.history-search-box');
        if (searchBox && searchBox.parentNode === box) {
          if (searchBox.nextSibling) box.insertBefore(split, searchBox.nextSibling);
          else box.appendChild(split);
        } else {
          box.appendChild(split);
        }
      }

      // Guardar los contadores globales iniciales para restaurar después
      const elTotal0 = wrap.querySelector('#hs-count-total');
      const elWon0   = wrap.querySelector('#hs-count-won');
      const elLost0  = wrap.querySelector('#hs-count-lost');
      const elRate0  = wrap.querySelector('#hs-count-rate');
      const parseNum = (s) => { const n = parseFloat(String(s||'').replace(/[^0-9.,-]/g,'').replace(',','.')); return isNaN(n)?0:n; };
      const baseInitial = {
        total: parseNum(elTotal0 && elTotal0.textContent),
        won:   parseNum(elWon0 && elWon0.textContent),
        lost:  parseNum(elLost0 && elLost0.textContent),
        rate:  parseNum(elRate0 && elRate0.textContent)
      };
      // Fallback: si los contadores base están a 0 pero hay filas válidas, recalcular desde las filas
      (function ensureBaseFromRows(){
        try {
          const hasRows = rows && rows.length > 0;
          const needs = (hasRows && (!baseInitial || !baseInitial.total));
          if (!needs) return;
          let t=0, wc=0, lc=0;
          rows.forEach((r)=>{
            const valid = (r.getAttribute('data-valid') === '1');
            if (!valid) return;
            t++;
            const w = r.getAttribute('data-win');
            if (w === '1') wc++; else if (w === '0') lc++;
          });
          if (t>0){
            baseInitial.total = t; baseInitial.won = wc; baseInitial.lost = lc; baseInitial.rate = (t? Math.round((wc*1000/t))/10 : 0);
            if (elTotal0) elTotal0.textContent = String(baseInitial.total);
            if (elWon0)   elWon0.textContent   = String(baseInitial.won);
            if (elLost0)  elLost0.textContent  = String(baseInitial.lost);
            if (elRate0)  elRate0.textContent  = (typeof baseInitial.rate==='number'? baseInitial.rate : 0) + '%';
          }
        } catch(_) {}
      })();

  function winnerText(row){
        const attr = row.getAttribute('data-names-winner');
        if (attr && attr.trim()) return normEs(attr);
        const el = row.querySelector('.history-match-winner');
        return normEs(el? el.textContent: '');
      }
      function loserText(row){
        const attr = row.getAttribute('data-names-loser');
        if (attr && attr.trim()) return normEs(attr);
        const el = row.querySelector('.history-match-loser');
        return normEs(el? el.textContent: '');
      }

      function categorizeRow(row, qTerm){
        const W = winnerText(row), L = loserText(row);
        const hasQinW = W.indexOf(qTerm) !== -1;
        const hasQinL = L.indexOf(qTerm) !== -1;
        if (!hasQinW && !hasQinL) return null;
        // Usa el lado del jugador cuando está disponible para exactitud
        const side = (row.getAttribute('data-player-side') || '').toUpperCase(); // 'W' (jugó en equipo ganador) o 'L' (en perdedor)
        if (side === 'W') {
          // Buscas un nombre: si aparece en el mismo lado ganador → compañero; si aparece en el lado perdedor → rival
          return hasQinW ? 'companero' : (hasQinL ? 'rival' : null);
        }
        if (side === 'L') {
          return hasQinL ? 'companero' : (hasQinW ? 'rival' : null);
        }
        // Fallback 2: si no hay side, usa data-win (1 ganó el propio jugador; 0 perdió el propio jugador)
        const wAttr = row.getAttribute('data-win');
        if (wAttr === '1') { return hasQinW ? 'companero' : (hasQinL ? 'rival' : null); }
        if (wAttr === '0') { return hasQinL ? 'companero' : (hasQinW ? 'rival' : null); }
        // Fallback si no hay side: usa playerName si está
        const hasPlayerInW = playerName && W.indexOf(playerName) !== -1;
        const hasPlayerInL = playerName && L.indexOf(playerName) !== -1;
        if (hasPlayerInW && hasQinW) return 'companero';
        if (hasPlayerInL && hasQinL) return 'companero';
        if (hasPlayerInW && hasQinL) return 'rival';
        if (hasPlayerInL && hasQinW) return 'rival';
        return null;
      }

      function renderDropdown(q){
        const qn = normEs(q);
        if (!qn) { dd.style.display='none'; dd.innerHTML=''; return; }
        let comp=0, riv=0, hits=0;
  getRows().forEach(r => { const cat = categorizeRow(r, qn); const txt = normEs(r.textContent||''); if (txt.indexOf(qn)!==-1) hits++; if (cat==='companero') comp++; else if (cat==='rival') riv++; });
        const emptyState = (!comp && !riv && !hits) ? '<div class="rf-empty" style="padding:.6rem .8rem;opacity:.8;color:#6b7280;">Sin coincidencias</div>' : '';
        // Si hay hits pero ninguno es nombre de jugador (ni en ganador ni en perdedor), oculta el dropdown para evitar una línea vacía
        if (!comp && !riv && hits) { dd.innerHTML=''; dd.style.display='none'; return; }
        dd.innerHTML = (
          ((comp||riv)?'<div class="rf-hint" style="padding:.5rem .8rem .2rem;color:#6b7280;font-size:.9em;">Elige cómo filtrar las partidas</div>':'')+
          (comp?('<div class="rf-group"><div class="rf-head" style="font-weight:600;padding:.5rem .8rem;color:#374151;display:flex;justify-content:space-between;align-items:center;">Compañero <span style="opacity:.75;font-weight:500">('+comp+')</span></div></div>'):'')+
          (riv?('<div class="rf-group"><div class="rf-head" style="font-weight:600;padding:.5rem .8rem;color:#374151;display:flex;justify-content:space-between;align-items:center;">Rival <span style="opacity:.75;font-weight:500">('+riv+')</span></div></div>'):'')+
          emptyState
        );
        // Añade botones de acción para aplicar filtro
        const addAction = (label, rel) => {
          const a = document.createElement('button');
          a.type = 'button'; a.textContent = 'Filtrar partidas como '+label; a.className='rf-act';
          a.style.margin='.25rem .8rem .8rem'; a.style.padding='.45rem .7rem'; a.style.borderRadius='999px';
          a.style.border='1px solid #d1d5db'; a.style.background='#f9fafb'; a.style.cursor='pointer'; a.style.transition='all .15s ease'; a.style.fontWeight='500';
          a.addEventListener('mouseenter', ()=>{ a.style.background='#eef2ff'; a.style.borderColor='#c7d2fe'; });
          a.addEventListener('mouseleave', ()=>{ a.style.background='#f9fafb'; a.style.borderColor='#d1d5db'; });
          a.addEventListener('click', ()=> applyFilter(qn, rel));
          return a;
        };
        const groups = dd.querySelectorAll('.rf-group');
        if (groups[0] && comp) groups[0].appendChild(addAction('Compañero', 'companero'));
        if (groups[groups.length-1] && riv) groups[groups.length-1].appendChild(addAction('Rival', 'rival'));
        dd.style.display = 'block';
      }

      function updateSplitCounters(q){
        const qn = normEs(q);
        let compT=0, compW=0, compL=0;
        let rivT=0,  rivW=0,  rivL=0;
        if (qn) {
          getRows().forEach((r)=>{
            const cat = categorizeRow(r, qn);
            if (!cat) return;
            const w = r.getAttribute('data-win');
            const won = (w === '1'); const lost = (w === '0');
            if (cat === 'companero') { compT++; if (won) compW++; if (lost) compL++; }
            else if (cat === 'rival') { rivT++; if (won) rivW++; if (lost) rivL++; }
          });
        }
        const compRate = compT>0 ? Math.round((compW*1000/compT))/10 : 0;
        const rivRate  = rivT>0  ? Math.round((rivW*1000/rivT))/10  : 0;
        const setSplit = (k, val) => { const el = split && split.querySelector('[data-k="'+k+'"]'); if (el) el.textContent = String(val); };
        // Actualizar bloque inferior (Como compañero)
        if (split) {
          setSplit('comp-total', compT); setSplit('comp-won', compW); setSplit('comp-lost', compL); setSplit('comp-rate', compRate+'%');
          // Siempre visible durante búsqueda, aunque 0
          split.style.display = qn ? '' : 'none';
        }
        // Actualizar SIEMPRE el bloque superior con título y cifras (Global o Rival)
        const globalLabel = box.querySelector('#history-global-label');
        const elTotal = wrap.querySelector('#hs-count-total');
        const elWon   = wrap.querySelector('#hs-count-won');
        const elLost  = wrap.querySelector('#hs-count-lost');
        const elRate  = wrap.querySelector('#hs-count-rate');
    // Determinar si hay filas visibles tras el filtro actual
    const anyVisible = getRows().some(r => !r.classList.contains('rf-hidden'));
        if (qn && anyVisible) {
          // Mostrar cifras de Rival en el bloque superior (aunque sean 0)
          if (globalLabel) { globalLabel.textContent = 'RESULTADOS COMO RIVAL'; globalLabel.style.display='block'; globalLabel.style.width='100%'; globalLabel.style.flexBasis='100%'; }
          if (elTotal) elTotal.textContent = String(rivT);
          if (elWon)   elWon.textContent   = String(rivW);
          if (elLost)  elLost.textContent  = String(rivL);
          if (elRate)  elRate.textContent  = (rivT>0 ? (Math.round((rivW*1000/rivT))/10) : 0) + '%';
        } else {
          // Restaurar resultados globales
          if (globalLabel) { globalLabel.textContent = 'RESULTADOS GLOBALES'; globalLabel.style.display='block'; globalLabel.style.width='100%'; globalLabel.style.flexBasis='100%'; }
          if (elTotal) elTotal.textContent = String(baseInitial.total);
          if (elWon)   elWon.textContent   = String(baseInitial.won);
          if (elLost)  elLost.textContent  = String(baseInitial.lost);
          if (elRate)  elRate.textContent  = (typeof baseInitial.rate==='number'? baseInitial.rate : 0) + '%';
        }
      }

      function applyFilter(q, relation){
        const on = !!q;
        let any=0, won=0, lost=0;
        getRows().forEach((r)=>{
          const txt = normEs(r.textContent||'');
          const hitTxt = !q || txt.indexOf(q)!==-1;
          let show = hitTxt;
          if (show && relation) {
            const cat = categorizeRow(r, q);
            show = (cat === relation);
          }
          if (show){
            r.classList.remove('rf-hidden');
            try { r.style.removeProperty('display'); } catch(_){ r.style.display=''; }
          } else {
            r.classList.add('rf-hidden');
            try { r.style.setProperty('display','none','important'); } catch(_){ r.style.display='none'; }
          }
          if (show) {
            any++;
            const w = r.getAttribute('data-win');
            // data-win es respecto al jugador filtrado (no al término). Si filtramos por compañero, las W/L se mantienen.
            if (w === '1') won++; else if (w === '0') lost++;
          }
        });
        if (box && box.classList) box.classList.toggle('filter-on', on);
        if (flag) flag.style.display = on ? '' : 'none';
        // Actualizar panel desdoblado
        updateSplitCounters(q);
        const relationOn = !!relation; // ahora no oculta siempre; se usa si hiciera falta
        // Mostrar encabezados solo en secciones con resultados visibles; ocultar los vacíos
        const lists = wrap.querySelectorAll('.history-matches-list');
        lists.forEach((list)=>{
          const anyVis = Array.from(list.querySelectorAll('.history-match-row')).some(r => !r.classList.contains('rf-hidden'));
          const title = (()=>{ const prev=list.previousElementSibling; if(prev && prev.classList && prev.classList.contains('history-competition-title')) return prev; return list.querySelector('.history-competition-title'); })();
          if (title) title.style.display = anyVis ? '' : 'none';
          list.style.display = anyVis ? '' : 'none';
        });
        const tBlocks = wrap.querySelectorAll('.history-tournament-block');
        tBlocks.forEach((block)=>{
          const any = Array.from(block.querySelectorAll('.history-matches-list')).some(list => list.style.display !== 'none');
          const th = block.querySelector('.tournament-header'); if (th) th.style.display = any ? '' : 'none';
          block.style.display = any ? '' : 'none';
        });
      }

      function onInput(){
        const val = (input.value||'').trim();
        if (!val) {
          dd.style.display='none'; dd.innerHTML='';
          if (split) split.style.display='none';
          const globalLabel = box.querySelector('#history-global-label');
          if (globalLabel) { globalLabel.textContent = 'RESULTADOS GLOBALES'; globalLabel.style.display = 'block'; globalLabel.style.width='100%'; globalLabel.style.flexBasis='100%'; }
          // Restaurar cifras globales en el bloque superior
          const elTotal = wrap.querySelector('#hs-count-total');
          const elWon   = wrap.querySelector('#hs-count-won');
          const elLost  = wrap.querySelector('#hs-count-lost');
          const elRate  = wrap.querySelector('#hs-count-rate');
          if (elTotal) elTotal.textContent = String(baseInitial.total);
          if (elWon)   elWon.textContent   = String(baseInitial.won);
          if (elLost)  elLost.textContent  = String(baseInitial.lost);
          if (elRate)  elRate.textContent  = (typeof baseInitial.rate==='number'? baseInitial.rate : 0) + '%';
          // Asegurar que todas las filas vuelven a mostrarse
          getRows().forEach((r)=>{ r.classList.remove('rf-hidden'); try{ r.style.removeProperty('display'); }catch(_){ r.style.display=''; } });
          if (box && box.classList) box.classList.remove('filter-on');
          applyFilter('', null);
          // No hace falta recalcular split aquí
          return; }
        // Mostrar dropdown siempre desde la 1ª pulsación
        renderDropdown(val);
        // Aplicar filtro base (texto) para retroalimentar visualmente mientras decides relación
        applyFilter(val, null);
      }

      input.addEventListener('input', onInput);
      input.addEventListener('keyup', onInput);
      // Click fuera para cerrar
      document.addEventListener('click', (e)=>{
        const path = (e.composedPath && e.composedPath()) || [];
        if (path.indexOf(dd) === -1 && path.indexOf(input) === -1) { dd.style.display='none'; }
      }, { passive:true, capture:true });
      // Inicial
      onInput();
    })();
  }

  class RankingFutbolinApp extends HTMLElement {
    constructor() {
      super();
      this._root = this.attachShadow({ mode: 'open' });
      // Inyectar CSS con <link> por robustez
      if (CFG.debugCss) { try { console.groupCollapsed('[RF Shadow] CSS links'); cssUrls.forEach((u,i)=>console.log(`#${i+1}`, u)); console.groupEnd(); } catch(_){} }
      try {
        cssUrls.forEach((u)=>{
          try {
            if (!u) return;
            const link = document.createElement('link');
            link.setAttribute('rel', 'stylesheet');
            link.setAttribute('href', String(u));
            this._root.appendChild(link);
          } catch(_){ }
        });
      } catch(_){ }
      // Base CSS mínimo inline
      const base = document.createElement('style');
      base.textContent = `:host{display:block;contain:content;}*,*::before,*::after{box-sizing:border-box}
      .futbolin-tabs-nav a{display:inline-flex;align-items:center;gap:6px;text-decoration:none;padding:8px 12px;border-radius:9999px;font-weight:600;color:var(--futbolin-text-muted,#6b7280);background:#f4f5f7;transition:background-color .15s ease,color .15s ease,box-shadow .15s ease}
      .futbolin-tabs-nav a:hover{background-color:#e1effe;color:#0b5ed7}
      .futbolin-tabs-nav a.active{background-color:var(--futbolin-color-primary,#2271b1);color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.12)}
      .futbolin-tabs-nav a .dashicons{color:#8c8f94;font-size:18px;line-height:1}
      .futbolin-tabs-nav a.active .dashicons{color:#fff}`;
      this._root.appendChild(base);
      const slot = document.createElement('div');
      slot.className = 'rf-shadow-slot';
      this._root.appendChild(slot);
      this._slot = slot;
    }
    mountFrom(el) {
      // Move original children into shadow slot, but skip the host element itself
      if (CFG.debugCss) { try { console.debug('[RF Shadow] mountFrom: envolviendo wrapper', el); } catch(_){} }
      const nodes = Array.from(el.childNodes);
      for (const n of nodes) {
        if (n === this) continue;
        this._slot.appendChild(n);
      }
      // Inicializa comportamientos dentro del Shadow
      initTabs(this._root);
      autowireAjaxSearch(this._root);
  initLiveFilters(this._root);
      // Arrancar lazy loader de pestañas dentro del shadowRoot (si está disponible)
      try { if (window.rfPlayerTabsLazyBoot) { window.rfPlayerTabsLazyBoot(this._root); } } catch(_){ }
      // Observa cambios dentro del Shadow (hidratación de pestañas) para re-cablear automáticamente
      try {
        if (!this._mo) {
          const debounced = (() => {
            let t = null;
            return () => { clearTimeout(t); t = setTimeout(() => { try { initTabs(this._root); autowireAjaxSearch(this._root); initLiveFilters(this._root); if (window.rfLiveWireBind) { try { window.rfLiveWireBind(this._root); } catch(_){} } else if (window.rfLiveWireForce) { try { window.rfLiveWireForce(); } catch(_){} } } catch(_){} }, 60); };
          })();
          this._mo = new MutationObserver((muts) => {
            let should = false;
            for (const m of muts) {
              if (m.addedNodes && m.addedNodes.length) {
                m.addedNodes.forEach((node) => {
                  try {
                    if (!node) return;
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches('#tab-historial, #tab-torneos, .futbolin-tab-content')) should = true;
                    else if (node.querySelector && (node.querySelector('#tab-historial') || node.querySelector('#tab-torneos') || node.querySelector('.history-summary-search'))) should = true;
                  } catch(_){ }
                });
              }
            }
            if (should) debounced();
          });
          this._mo.observe(this._slot, { childList: true, subtree: true });
        }
      } catch(_){ }
      // Wiring del buscador live dentro del Shadow:
      try {
        // Si existe el cargador global, fuerce wiring dentro del shadowRoot
        if (window.rfLiveWireForce) {
          // Ejecutar sobre el shadowRoot: clonar función con binding local
          const list = this._root.querySelectorAll('.futbolin-live-search, input[name="jugador_busqueda"]');
          list.forEach(() => { try { window.rfLiveWireForce(); } catch(_){} });
        } else {
          // Fallback mínimo: mismo que fuera, pero scoped al shadowRoot
          const inputs = Array.prototype.slice.call(this._root.querySelectorAll('.futbolin-live-search, input[name="jugador_busqueda"]'));
          inputs.forEach((input) => {
            if (input.___rfShadowLiveFallback) return;
            input.___rfShadowLiveFallback = true;
            const box = (input.parentElement && input.parentElement.querySelector('.search-results-dropdown')) || (function(){ const d = document.createElement('div'); d.className = 'search-results-dropdown rf-live-inline'; input.parentElement && input.parentElement.appendChild(d); return d; })();
            const ensureHintCss = function(){
              if (document.getElementById('rf-live-inline-style')) return;
              const st = document.createElement('style'); st.id='rf-live-inline-style'; st.textContent = '.rf-live-inline{background:#fff;border:1px solid rgba(0,0,0,.12);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.12);} .rf-live-inline .rf-empty{padding:.6rem .8rem; opacity:.8; color:#6b7280;}'; document.head.appendChild(st);
            };
            const show = function(html){ ensureHintCss(); box.innerHTML = html; box.classList.add('rf-open'); };
            const hide = function(){ box.classList.remove('rf-open'); box.innerHTML=''; };
            const onInput = function(){ const val = (input.value||'').trim(); if (val.length < 3) { show('<div class="rf-empty">Teclea al menos 3 letras…</div>'); } else { hide(); } };
            input.addEventListener('input', onInput);
            input.addEventListener('focus', onInput);
          });
        }
      } catch(_){ }
    }
  }

  if (!customElements.get(hostTag)) {
    customElements.define(hostTag, RankingFutbolinApp);
  }

  // Exponer hooks globales para re-inicializar wiring desde otros módulos (p. ej., hidratación lazy)
  try {
    // Re-inicializa filtros y buscadores dentro de un root dado (document o ShadowRoot)
    window.rfInitShadowFilters = function(root){
      try {
        const scope = (root && root.querySelector) ? root : document;
        initTabs(scope);
        autowireAjaxSearch(scope);
        initLiveFilters(scope);
        if (window.rfLiveWireBind) { try { window.rfLiveWireBind(scope); } catch(_){} }
        else if (window.rfLiveWireForce) { try { window.rfLiveWireForce(); } catch(_){} }
      } catch(e) { try { console.warn('[RF Shadow] rfInitShadowFilters error', e); } catch(_){} }
    };
  } catch(_){ }

  const boot = () => {
    const wrappers = document.querySelectorAll(selector);
    if (CFG.debugCss) { try { console.debug('[RF Shadow] boot: wrappers encontrados', wrappers.length, selector); } catch(_){} }
    wrappers.forEach((wrap) => {
      if (wrap.dataset.rfShadow === '1') return;
      if (shouldSkip(wrap)) { if (CFG.debugCss) { try { console.debug('[RF Shadow] skip (data-rf-no-shadow)', wrap); } catch(_){} } wrap.dataset.rfShadow = 'skip'; return; }
      const host = document.createElement(hostTag);
      wrap.appendChild(host);
      if (CFG.debugCss) { try { console.debug('[RF Shadow] host creado y anexado', host); } catch(_){} }
      host.mountFrom(wrap);
      wrap.dataset.rfShadow = '1';
    });

    // Reforzar wiring del buscador live (umbral 3) si no está el cargador global
    try {
      if (!window.rfLiveWireReady) {
        // Escanear inputs de búsqueda y proveer un fallback mínimo (mensaje <3 y disparo >=3)
        const inputs = Array.prototype.slice.call(document.querySelectorAll('.futbolin-live-search'));
        inputs.forEach((input) => {
          if (input.___rfShadowLiveFallback) return;
          input.___rfShadowLiveFallback = true;
          const box = (input.parentElement && input.parentElement.querySelector('.search-results-dropdown')) || (function(){
            const d = document.createElement('div'); d.className = 'search-results-dropdown rf-live-inline'; input.parentElement && input.parentElement.appendChild(d); return d; })();
          const place = function(){ try { const r = input.getBoundingClientRect(); box.style.position='absolute'; box.style.left='0'; box.style.right='0'; box.style.marginTop='4px'; } catch(_){} };
          const ensureHintCss = function(){
            if (document.getElementById('rf-live-inline-style')) return;
            const st = document.createElement('style'); st.id='rf-live-inline-style'; st.textContent = '.rf-live-inline{background:#fff;border:1px solid rgba(0,0,0,.12);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.12);} .rf-live-inline .rf-empty{padding:.6rem .8rem; opacity:.8; color:#6b7280;}'; document.head.appendChild(st);
          };
          const show = function(html){ ensureHintCss(); box.innerHTML = html; box.classList.add('rf-open'); place(); };
          const hide = function(){ box.classList.remove('rf-open'); box.innerHTML=''; };
          const onInput = function(){ const val = (input.value||'').trim(); if (val.length < 3) { show('<div class="rf-empty">Teclea al menos 3 letras…</div>'); } else { hide(); /* el wiring completo (rf-live-wiring.js) hará la búsqueda */ } };
          input.addEventListener('input', onInput);
          input.addEventListener('focus', onInput);
        });
      }
    } catch(_){ }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { boot(); autowireAjaxSearch(document); }, { once: true });
  } else {
    boot(); autowireAjaxSearch(document);
  }

  // Observa wrappers añadidos después del boot para envolverlos automáticamente
  try {
    const moWrap = new MutationObserver((muts)=>{
      let candidates = [];
      muts.forEach((m)=>{
        if (m.addedNodes) {
          m.addedNodes.forEach((n)=>{
            try {
              if (!n || n.nodeType !== 1) return;
              if (n.matches && n.matches(selector)) candidates.push(n);
              if (n.querySelectorAll) candidates.push(...n.querySelectorAll(selector));
            } catch(_){ }
          });
        }
      });
      if (!candidates.length) return;
      candidates.forEach((wrap)=>{
        try {
          if (!wrap || wrap.dataset.rfShadow === '1' || wrap.dataset.rfShadow === 'skip') return;
          if (shouldSkip(wrap)) { wrap.dataset.rfShadow = 'skip'; return; }
          const host = document.createElement(hostTag);
          wrap.appendChild(host);
          host.mountFrom(wrap);
          wrap.dataset.rfShadow = '1';
          if (CFG.debugCss) { try { console.debug('[RF Shadow] host creado (observer)', host); } catch(_){} }
        } catch(_){ }
      });
    });
    const obsTarget = document.body || document.documentElement;
    if (obsTarget) moWrap.observe(obsTarget, { childList: true, subtree: true });
  } catch(_){ }
})();

// === RF Overlay (minimal) — show existing #futbolin-loader-overlay on heavy navigations ===
(function(){
  const OVERLAY_ID = 'futbolin-loader-overlay';
  const HIDDEN_CLASS = 'futbolin-loader-hidden';
  const RANK_PREFIXES = ['/futbolin-ranking', '/futbolin-ranking/'];
  const PROFILE_PREFIXES = ['/perfil-jugador', '/perfil-jugador/'];
  const H2H_PREFIXES = ['/h2h', '/h2h/'];
  // Aumentado a 15s para cubrir navegaciones pesadas; configurable vía rfShadowSettings.safetyMs
  const SAFETY_MS = (window.rfShadowSettings && Number(window.rfShadowSettings.safetyMs)) || 15000;

  const pathBeginsWith = (pathname, prefixes) => prefixes.some(p => pathname === p || pathname.startsWith(p));
  const sameOrigin = (url) => url && url.origin === window.location.origin;
  const toURL = (href) => { try { return new URL(href, window.location.href); } catch { return null; } };
  const currentIsRanking = () => pathBeginsWith(window.location.pathname, RANK_PREFIXES);
  const isModifiedClick = (e, el) => (
    e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ||
    e.button === 1 || e.which === 2 ||
    (el && (el.target === '_blank' || el.getAttribute('target') === '_blank'))
  );
  const getOverlay = () => document.getElementById(OVERLAY_ID);
  const showOverlay = () => {
    const el = getOverlay(); if (!el) return;
    el.classList.remove(HIDDEN_CLASS);
    clearTimeout(showOverlay._t);
    showOverlay._t = setTimeout(() => { hideOverlay(); }, SAFETY_MS);
  };
  const hideOverlay = () => {
    const el = getOverlay(); if (!el) return;
    el.classList.add(HIDDEN_CLASS);
    clearTimeout(showOverlay._t);
  };
  const isHeavyTarget = (url) => {
    if (!url || !sameOrigin(url)) return false;
    if (pathBeginsWith(url.pathname, PROFILE_PREFIXES)) return true;
    if (url.searchParams.has('jugador_id')) return true;
    if (pathBeginsWith(url.pathname, H2H_PREFIXES)) return true;
    if (pathBeginsWith(url.pathname, RANK_PREFIXES)) {
      const cur = new URL(window.location.href);
      const curView = cur.searchParams.get('view') || '';
      const nextView = url.searchParams.get('view') || '';
      if (nextView && nextView !== curView) return true;
    }
    return false;
  };
  window.addEventListener('pageshow', hideOverlay, { passive: true });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') hideOverlay();
  }, { passive: true });
  window.addEventListener('popstate', hideOverlay, { passive: true });

  const findAnchorInPath = (pathArr) => {
    for (const n of pathArr) {
      if (!n) continue;
      if (n.tagName === 'A' && n.href) return n;
      if (n.nodeType === 1 && typeof n.closest === 'function') {
        const a = n.closest('a[href]');
        if (a) return a;
      }
    }
    return null;
  };

  const clickHandler = (e) => {
    if (!currentIsRanking()) return;
    const path = e.composedPath ? e.composedPath() : (e.path || []);
    const a = findAnchorInPath(path);
    if (!a) return;
    if (isModifiedClick(e, a)) return;
    const url = toURL(a.href);
    if (!url) return;
    if (url.href === window.location.href || url.hash) return;
    if (url.protocol === 'javascript:') return;
    if (isHeavyTarget(url)) showOverlay();
  };

  const buildGetUrlFromForm = (form) => {
    const action = form.getAttribute('action') || window.location.href;
    const base = toURL(action); if (!base) return null;
    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    if (method !== 'GET') return null;
    const fd = new FormData(form);
    for (const [k,v] of fd.entries()) base.searchParams.set(k, v);
    return base;
  };

  const submitHandler = (e) => {
    if (!currentIsRanking()) return;
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const url = buildGetUrlFromForm(form);
    if (!url) return;
    if (isHeavyTarget(url)) showOverlay();
  };

  const tryBindIframe = (iframe) => {
    try {
      const doc = iframe.contentDocument; if (!doc) return;
      if (doc.__rfOverlayBound) return;
      doc.__rfOverlayBound = true;
      doc.addEventListener('click', clickHandler, { capture: true, passive: true });
      doc.addEventListener('submit', submitHandler, { capture: true, passive: true });
    } catch {}
  };
  const bindExistingIframes = () => {
    document.querySelectorAll('iframe').forEach(tryBindIframe);
  };
  const observeIframes = () => {
    const mo = new MutationObserver((muts) => {
      muts.forEach(m => {
        m.addedNodes && m.addedNodes.forEach(n => {
          if (n && n.tagName === 'IFRAME') tryBindIframe(n);
          if (n && n.querySelectorAll) n.querySelectorAll('iframe').forEach(tryBindIframe);
        });
      });
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  };
  const wire = () => {
    document.addEventListener('click', clickHandler, { capture: true, passive: true });
    document.addEventListener('submit', submitHandler, { capture: true, passive: true });
    bindExistingIframes();
    observeIframes();
    // Re-wiring para contenidos lazy dentro de Shadow (cuando rf-player-tabs hidrata):
    document.addEventListener('rf:tab:hydrated', (e) => {
      try {
        const pane = e && e.detail && e.detail.pane;
        const root = pane && pane.getRootNode && pane.getRootNode();
        if (root && root.host && root.host.tagName && root.host.tagName.toLowerCase() === 'ranking-futbolin-app') {
          initTabs(root);
          autowireAjaxSearch(root);
          initLiveFilters(root);
          if (window.rfLiveWireBind) { try { window.rfLiveWireBind(root); } catch(_){} }
          else if (window.rfLiveWireForce) { try { window.rfLiveWireForce(); } catch(_){} }
        }
      } catch(_){ }
    }, { passive: true });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire, { once: true });
  } else {
    wire();
  }
})();
