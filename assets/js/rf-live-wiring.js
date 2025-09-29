
/*! RF Live Wiring (v7) */
(function(){
  try {
    console.log('[RF] rf-live-wiring v7');
    try { window.rfLiveWireReady = true; } catch(_){ }
    var wired = false;

    // Resolve the base URL for player profile links once (configurable)
    var PROFILE_BASE = (function(){
      try {
        // Prefer explicitly set base (if any)
        if (typeof window.rfProfileBase === 'string' && window.rfProfileBase) return window.rfProfileBase;
        // Next, prefer localized profile_url
        if (window.futbolin_ajax_obj && typeof window.futbolin_ajax_obj.profile_url === 'string' && window.futbolin_ajax_obj.profile_url) return window.futbolin_ajax_obj.profile_url;
      } catch(_){ }
      try { return new URL('/perfil-jugador/', location.origin).toString(); } catch(_){ return '/perfil-jugador/'; }
    })();
    try { if (!window.rfProfileBase) window.rfProfileBase = PROFILE_BASE; } catch(_){ }

    function qsAll(sel, root){ try{ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }catch(e){ return []; } }
    function getAjaxUrl(){
      return (window.futbolin_ajax_obj && window.futbolin_ajax_obj.ajax_url) ||
             window.ajaxurl || '/wp-admin/admin-ajax.php';
    }
    function getNonce(form){
      var val = (window.futbolin_ajax_obj && window.futbolin_ajax_obj.nonce) || '';
      if (!val && form && form.querySelector){
        var n = form.querySelector('input[name="security"], input[name*="nonce"], input[name="_wpnonce"], input[name="_ajax_nonce"]');
        if (n) val = n.value || '';
      }
      return { name: val ? 'security' : '', value: val };
    }
    function debounce(fn, ms){ var t; return function(){ var a=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(null,a); }, ms||180); }; }
    function esc(s){ return String(s).replace(/[&<>"]/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); }); }

    function jsonToHtml(raw){
  try {
    var j = JSON.parse(raw);
    // If backend returns a list (or {data:[...]}) => build anchors
    var list = Array.isArray(j) ? j : (Array.isArray(j.data) ? j.data : null);
    if (list) {
      var base = (PROFILE_BASE && String(PROFILE_BASE)) || (function(){ try { return new URL('/perfil-jugador/', location.origin).toString(); } catch(_){ return '/perfil-jugador/'; } })();
      var out = list.map(function(it){
        var id = it.jugadorId || it.id || it.jugador_id;
        var name = it.nombreJugador || it.nombre || it.name || it.title || '';
        if (!id || !name) return '';
        var href = base + (base.includes('?') ? '&' : '?') + 'jugador_id=' + encodeURIComponent(id);
        return '<div class="rf-item"><a href="'+ href +'" data-id="'+ esc(id) +'">' + esc(name) + '</a></div>';
      }).filter(Boolean).join('');
      return out || null;
    }
    // Else: extract human message from JSON error objects and return plain text
    var msg = (j && typeof j.message === 'string' && j.message.trim()) ? j.message.trim() : null;
    if (!msg && j && j.data && typeof j.data.message === 'string' && j.data.message.trim()) msg = j.data.message.trim();
    if (msg) return esc(msg);
    return null;
  } catch(_){ return null; }
}

function needsPortal(box){
      try {
        var cs = getComputedStyle(box);
        if (/(hidden|clip)/.test(cs.overflow + cs.overflowY + cs.overflowX)) return true;
        // si está visible pero alto 0 (recortado)
        var r = box.getBoundingClientRect();
        if (r.height < 4 && box.innerHTML.trim()) return true;
        // si un ancestro recorta
        var p = box.parentElement; var hops=0;
        while (p && hops<6){
          var csp = getComputedStyle(p);
          if (/(hidden|clip)/.test(csp.overflow + csp.overflowY + csp.overflowX)) return true;
          p = p.parentElement; hops++;
        }
      } catch(_){}
      return false;
    }

    function createPortal(){
      var portal = document.createElement('div');
      portal.className = 'rf-live-portal';
      portal.classList.add('rf-live-inline');
      // Estilos inline mínimos para asegurar visibilidad incluso si rf-live.css no carga
      portal.style.position = 'fixed';
      portal.style.maxHeight = '320px';
      portal.style.overflow = 'auto';
      portal.style.background = '#fff';
      portal.style.border = '1px solid rgba(0,0,0,.12)';
      portal.style.borderRadius = '8px';
      portal.style.boxShadow = '0 6px 24px rgba(0,0,0,.18)';
      portal.style.zIndex = '2147483647';
      portal.style.display = 'none';
      document.body.appendChild(portal);
      return portal;
    }

    function ensureInlineGlobalStyle(){
      try{
        if (document.getElementById('rf-live-inline-style')) return;
        var st = document.createElement('style');
        st.id = 'rf-live-inline-style';
        st.textContent = ''+
          '.search-results-dropdown{display:none !important;}' +
          '.search-results-dropdown.rf-open{display:block !important;}' +
          '.search-results-dropdown.rf-live-inline{position:absolute;left:0;right:0;margin-top:4px;z-index:2147483646;max-height:320px;overflow:auto;}' +
          '.rf-live-inline{background:#fff;border:1px solid rgba(0,0,0,.12);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.12);}'+
          '.rf-live-inline .rf-item{display:block !important;border-bottom:1px solid rgba(0,0,0,.06) !important;}' +
          '.rf-live-inline a{display:flex !important;align-items:center;gap:.5rem;padding:.55rem .8rem !important;text-decoration:none;color:inherit;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-bottom:1px solid rgba(0,0,0,.06) !important;width:100% !important;box-sizing:border-box !important;}'+
          '.rf-live-inline .rf-item:last-child, .rf-live-inline a:last-child{border-bottom:0 !important;}'+
          '.rf-live-inline a:hover,.rf-live-inline a:focus{background:#eef2ff !important;outline:none;text-decoration:none;}'+
          '.rf-live-inline .rf-empty{padding:.6rem .8rem !important;opacity:.8;color:#6b7280;}';
        document.head.appendChild(st);
      }catch(_){ }
    }

    function placePortal(portal, input){
      var r = input.getBoundingClientRect();
      portal.style.left   = Math.round(r.left) + 'px';
      portal.style.top    = Math.round(r.bottom + 4) + 'px';
      portal.style.width  = Math.round(r.width) + 'px';
      portal.style.maxWidth = Math.round(r.width) + 'px';
    }

    function wireOne(input){
      if (!input) return;
      // Robustez: ignorar atributo data-rf-live-wired existente (puede venir del server o de una ejecución previa)
      if (input.___rfWired === true) return;
      var form = input.form || (input.closest && input.closest('form')) || document;
      var box  = (form && form.querySelector) ? form.querySelector('.search-results-dropdown') : null;
      if (!box && input.parentElement) {
        // fallback: buscar en el wrapper más cercano
        box = input.parentElement.querySelector && input.parentElement.querySelector('.search-results-dropdown');
      }
      if (!box) {
        // último recurso: crearlo detrás del input
        try {
          box = document.createElement('div');
          box.className = 'search-results-dropdown';
          box.classList.add('rf-live-inline');
          (input.parentElement || document.body).appendChild(box);
        } catch(_){ return; }
      }
      // si existe, asegurar clase de estilo
      try { box.classList.add('rf-live-inline'); } catch(_){ }
      // asegurar estilos inline globales
      ensureInlineGlobalStyle();
      // marcar como cableado (prop interna, no dependemos de dataset)
      input.___rfWired = true;
      try { input.dataset.rfLiveWired = '1'; } catch(_){ }

      var ajaxUrl = getAjaxUrl();
      var nonce   = getNonce(form);
      var ACTION = 'futbolin_search_players';
      var PARAM  = 'term';

      // Portal fallback
      var portal = null;
      var usePortal = false;

      function show(){
        if (usePortal) { if (portal){ portal.classList.add('rf-open'); portal.style.display='block'; } }
        else { box.classList.add('rf-open'); }
      }
      function hide(){
        if (usePortal) { if (portal){ portal.classList.remove('rf-open'); portal.style.display='none'; portal.innerHTML=''; } }
        else { box.classList.remove('rf-open'); box.innerHTML = ''; }
      }

      async function request(params){
        var r = await fetch(ajaxUrl, {
          method:'POST', credentials:'same-origin',
          headers:{'X-Requested-With':'XMLHttpRequest'},
          body: params
        });
        var status = r.status;
        var raw = await r.text();
        return { status: status, raw: raw };
      }

      // --- Restaurar comportamiento original: mínimo 3 caracteres ---
      var MIN_CHARS = 3;
      var SEARCH_DELAY = 200; // debounce original

      // Cache simple en memoria por término normalizado
      var queryCache = {};
      var lastTerm = '';
      var lastResults = [];// array de {id,name,norm,html}
      var inflight = null; // AbortController

      function normTxt(s){
        try { return String(s).normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase(); } catch(_){
          return String(s).toLowerCase();
        }
      }

      function renderFromList(list){
        return list.map(function(o){ return o.html; }).join('');
      }

      function quickFilter(term){
        var n = normTxt(term);
        if (!n) return '';
        // si el nuevo término es prefijo del anterior podemos filtrar localmente primero
        if (lastResults.length && normTxt(lastTerm).length && n.startsWith(normTxt(lastTerm))) {
            var subset = lastResults.filter(function(r){ return r.norm.indexOf(n) !== -1; });
            if (subset.length){
              var html = renderFromList(subset);
              if (usePortal) { if (!portal) portal = createPortal(); placePortal(portal, input); portal.innerHTML = html; }
              else { box.innerHTML = html; }
              show();
            }
        }
      }

      function showSpinner(){
        var spin = '<div class="rf-empty">Buscando…</div>';
        usePortal = needsPortal(box);
        if (usePortal) { if (!portal) portal = createPortal(); placePortal(portal, input); portal.innerHTML = spin; }
        else { box.innerHTML = spin; }
        show();
      }

      var performSearch = async function(val){
        val = (val==null? '' : String(val)).trim();
        if (val.length < MIN_CHARS) {
          var hint = '<div class="rf-empty">Teclea al menos 3 letras…</div>';
          usePortal = needsPortal(box);
          if (usePortal) {
            if (!portal) portal = createPortal();
            placePortal(portal, input);
            portal.innerHTML = hint;
          } else {
            box.innerHTML = hint;
          }
          show();
          return;
        } else {
          // ocultar inmediatamente el cartel antes de la petición para que desaparezca visualmente
          hide();
        }

        var normTerm = normTxt(val);
        lastTerm = val;

        // Cache hit
        if (queryCache[normTerm]) {
          var cachedHtml = queryCache[normTerm];
          usePortal = needsPortal(box);
          if (usePortal) { if (!portal) portal = createPortal(); placePortal(portal, input); portal.innerHTML = cachedHtml; }
          else { box.innerHTML = cachedHtml; }
          show();
          return;
        }

        // Mostrar spinner y lanzar búsqueda remota (abortando la previa)
        showSpinner();
        if (inflight) { try { inflight.abort(); } catch(_){ } }
        inflight = new AbortController();
        var controller = inflight;

        var p = new URLSearchParams();
        p.set('action', ACTION);
        p.set(PARAM, val);
        if (nonce.name && nonce.value) p.set(nonce.name, nonce.value);

        var resp;
        try {
          resp = await request(p, controller.signal);
        } catch(err){
          if (controller.signal.aborted) return; // abortado, ignorar
          resp = { status: 0, raw: '<div class="rf-empty">Error de red</div>'};
        }
        if (resp.status === 403 && nonce.name && nonce.value) {
          var p2 = new URLSearchParams();
          p2.set('action', ACTION);
          p2.set(PARAM, val);
          try { resp = await request(p2, controller.signal); } catch(_){ }
        }

        // Si otra búsqueda se lanzó después, ignorar esta
        if (controller !== inflight) return;

        var html = jsonToHtml(resp.raw);
        if (!html) html = resp.raw;
        if (!html || !String(html).trim()) {
          html = '<div class="rf-empty">No se encontraron jugadores.</div>';
        }

        // Parsear para crear índice local
        try {
          var temp = document.createElement('div');
          temp.innerHTML = html;
          var items = temp.querySelectorAll('.rf-item a');
          var list = [];
            items.forEach(function(a){
              var nm = a.textContent || '';
              list.push({ id: a.getAttribute('data-id')||'', name: nm, norm: normTxt(nm), html: '<div class="rf-item">'+a.outerHTML+'</div>' });
            });
          lastResults = list;
          // Guardar ya normalizado
          queryCache[normTerm] = html;
        } catch(_){ lastResults = []; }

        usePortal = needsPortal(box);
        if (usePortal) {
          if (!portal) portal = createPortal();
          placePortal(portal, input);
          portal.innerHTML = html;
        } else {
          box.innerHTML = html;
        }
        show();
      };

  var doSearch = debounce(performSearch, SEARCH_DELAY);

      input.addEventListener('input', function(ev){
        var val = (ev && ev.target && ev.target.value) || (ev && ev.currentTarget && ev.currentTarget.value) || input.value || '';
        // Filtrado local rápido (no bloquea la búsqueda remota)
        quickFilter(val);
        doSearch(val);
      });
      input.addEventListener('focus', function(){ doSearch(input && input.value); });
      if (form && form.addEventListener) {
        form.addEventListener('submit', function(e){ e.preventDefault(); doSearch(input && input.value); });
      }
      document.addEventListener('click', function(e){
        var target = (typeof e.composedPath === 'function' && e.composedPath().length) ? e.composedPath()[0] : e.target;
        var inside = (usePortal ? (portal && portal.contains(target)) : (box && box.contains && box.contains(target)));
        if (!inside && target !== input) hide();
      });
      (box).addEventListener('click', function(e){
        var a = e.target && e.target.closest && e.target.closest('a');
        if (a) hide();
      });
      document.addEventListener('scroll', function(){
        if (usePortal && portal) placePortal(portal, input);
      }, true);
      window.addEventListener('resize', function(){
        if (usePortal && portal) placePortal(portal, input);
      });

      wired = true;
      console.info('[RF] live search ON (v7, portal='+ (usePortal?'yes':'auto') +') →', input);
    }

    // Shadow-aware wiring helpers
    function wireScope(root){
      var inputs = [];
      try {
        var sb = (root && root.querySelector) ? root.querySelector('.futbolin-sidebar-block') : null;
        if (sb) inputs = qsAll('.futbolin-live-search', sb).concat(qsAll('input[name="jugador_busqueda"]', sb));
        if (!inputs.length) inputs = qsAll('.futbolin-live-search', root).concat(qsAll('input[name="jugador_busqueda"]', root));
        inputs.forEach(wireOne);
      } catch(_){ }
    }

    function wireAllShadows(){
      try {
        var hosts = document.querySelectorAll('ranking-futbolin-app');
        hosts.forEach(function(h){ if (h && h.shadowRoot) wireScope(h.shadowRoot); });
      } catch(_){ }
    }

    // Initial wiring in main document and any existing shadow hosts
    wireScope(document);
    wireAllShadows();

    // Short retry loop to catch late DOM
    var t = setInterval(function(){ if (wired) { clearInterval(t); return; } wireScope(document); wireAllShadows(); }, 400);
    setTimeout(function(){ try{ clearInterval(t); }catch(_){ } }, 7000);

    // Expose helpers for manual/async wiring (e.g., after lazy hydration)
    try {
      window.rfLiveWireForce = function(){ wireScope(document); wireAllShadows(); };
      window.rfLiveWireBind = function(root){ try { wireScope(root || document); } catch(_){ } };
    } catch(_){ }

    // Re-bind when lazy tabs hydrate content inside Shadow
    try {
      document.addEventListener('rf:tab:hydrated', function(e){
        try {
          var pane = e && e.detail && e.detail.pane;
          var root = pane && pane.getRootNode && pane.getRootNode();
          if (root) { wireScope(root); }
          else { wireScope(document); }
        } catch(_){ }
      }, { passive: true });
    } catch(_){ }

    // Observe DOM for dynamically added Shadow hosts
    try {
      var mo = new MutationObserver(function(muts){
        var seen = false;
        muts.forEach(function(m){
          if (!m.addedNodes) return;
          m.addedNodes.forEach(function(n){
            try {
              if (n && n.tagName && String(n.tagName).toLowerCase() === 'ranking-futbolin-app') seen = true;
            } catch(_){ }
          });
        });
        if (seen) wireAllShadows();
      });
      mo.observe(document.documentElement, { childList: true, subtree: true });
    } catch(_){ }
  } catch(e){ console.warn('[RF] error en live wiring v7', e); }
})();


// RF: Add .top-[1..3] classes for rows with .pos-1/2/3 (fallback for browsers without :has)
(function(){
  try{
    var addTopClasses = function(root){
      var rows = (root||document).querySelectorAll('.ranking-row, .champion-row, li.ranking-row');
      rows.forEach(function(row){
        var badge = row.querySelector('.ranking-position.pos-1, .ranking-position.pos-2, .ranking-position.pos-3, .badge.pos-1, .badge.pos-2, .badge.pos-3');
        if (!badge) return;
        ['1','2','3'].forEach(function(n){
          if (badge.classList.contains('pos-'+n)) row.classList.add('top-'+n);
        });
      });
    };
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function(){ addTopClasses(document); });
    } else {
      addTopClasses(document);
    }
    // In case of live updates / ajax fragments:
    document.addEventListener('rf:content:updated', function(e){ addTopClasses(e.target || document); }, {passive:true});
  }catch(e){ console.warn('[RF] top-classes fallback error', e); }
})();
