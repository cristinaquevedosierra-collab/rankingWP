(() => {
  const CFG = window.futbolin_ajax_obj || {};
  const AJAX_URL = CFG.ajax_url || (window.ajaxurl) || '/wp-admin/admin-ajax.php';
  const NONCE = CFG.nonce || '';
  const DEBUG = /[?&]rf_lazy_debug=1\b/.test(window.location.search || '');
  const URL_QS = window.location.search || '';
  const QS_HAS = (name) => new RegExp(`[?&]${name}=1\\b`).test(URL_QS);

  // Fallback seguro para CSS.escape
  function escSel(id){
    try {
      if (typeof CSS !== 'undefined' && CSS && typeof CSS.escape === 'function') return CSS.escape(id);
    } catch{}
    // escape mínimo para IDs sencillos
    return String(id || '').replace(/[^a-zA-Z0-9_\-]/g, '_');
  }

  function $$all(root, sel){ try { return Array.from(root.querySelectorAll(sel)); } catch { return []; } }
  function $one(root, sel){ try { return root.querySelector(sel); } catch { return null; } }

  function executeScripts(scope){
    try {
      const scripts = $$all(scope, 'script');
      for (const old of scripts) {
        const neo = document.createElement('script');
        // Copiar atributos relevantes
        if (old.type) neo.type = old.type;
        if (old.noModule) neo.noModule = true;
        if (old.defer) neo.defer = true;
        if (old.async) neo.async = true;
        // data-attrs
        for (const a of (old.attributes || [])) {
          const name = a.name;
          if (name && name.startsWith('data-')) neo.setAttribute(name, a.value);
        }
        if (old.src) {
          neo.src = old.src;
        } else {
          neo.textContent = old.textContent || '';
        }
        // Insertar en el mismo lugar para mantener orden de ejecución
        old.parentNode && old.parentNode.insertBefore(neo, old.nextSibling);
        old.parentNode && old.parentNode.removeChild(old);
      }
    } catch (e) {
      if (DEBUG) console.warn('[RF Lazy] executeScripts error', e);
    }
  }

  async function fetchTabHTML(playerId, tab){
    if (DEBUG) console.debug('[RF Lazy] fetchTabHTML start', { playerId, tab });
    const fd = new FormData();
    fd.append('action', 'futbolin_load_player_tab');
    fd.append('security', NONCE);
    fd.append('player_id', String(playerId));
    fd.append('tab', String(tab));
  // Propagar flags de depuración/caché desde la URL, si existen
  if (QS_HAS('rf_tab_cache_bypass')) fd.append('rf_tab_cache_bypass', '1');
  if (QS_HAS('rf_tab_cache_reset'))  fd.append('rf_tab_cache_reset', '1');
  if (QS_HAS('rf_lazy_debug'))       fd.append('rf_lazy_debug', '1');
  // Seguridad: timeout de 10s para no dejar el skeleton infinito
  const ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
  const to = setTimeout(() => { try { ctrl && ctrl.abort(); } catch {} }, 10000);
  let res;
  try {
    res = await fetch(AJAX_URL, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: ctrl ? ctrl.signal : undefined });
  } finally {
    clearTimeout(to);
  }
    const ct = res.headers.get('content-type') || '';
    // WordPress wp_send_json_* => { success: boolean, data: {...} }
    if (ct.includes('application/json')) {
      const data = await res.json();
      if (!data || data.success === false) {
        const msg = (data && data.data && (data.data.message || data.data.error)) || (data && data.message) || 'Error';
        throw new Error(msg);
      }
      const payload = (data && data.data) ? data.data : data;
      const html = (payload && typeof payload.html === 'string') ? payload.html : '';
      if (DEBUG) console.debug('[RF Lazy] fetchTabHTML ok', { tab, bytes: (html||'').length });
      return html;
    } else {
      const text = await res.text();
      const t = (text||'').trim();
      if (t === '-1' || t === '0') {
        // WordPress check_ajax_referer falló o respuesta vacía; forzar error claro
        throw new Error('Sesión caducada o seguridad inválida. Recarga la página.');
      }
      if (DEBUG) console.debug('[RF Lazy] fetchTabHTML ok(text)', { tab, bytes: t.length });
      return t;
    }
  }

  const inflight = new WeakSet();

  // CSS mínimo inline para el skeleton (fallback si los assets no están disponibles en el root actual)
  const SKELETON_CSS = `/* rf-skeleton inline */
  .rf-skeleton{position:relative;overflow:hidden;background:#f6f7f8;border:1px solid #e5e7eb;border-radius:12px;padding:16px;min-height:96px}
  .rf-skel-line{height:14px;background:linear-gradient(90deg,#eee 25%,#f5f5f5 37%,#eee 63%);background-size:400% 100%;animation:rf-shimmer 1.2s ease-in-out infinite;border-radius:8px;margin-bottom:10px}
  .rf-skel-line.short{width:45%}
  .rf-skel-msg{margin:8px 0 0 0;color:#666;font-size:13px;display:flex;align-items:center;gap:8px}
  .rf-skel-msg:before{content:"";display:inline-block;width:14px;height:14px;border:2px solid #999;border-top-color:transparent;border-radius:50%;animation:rf-spin .9s linear infinite}
  @keyframes rf-shimmer{0%{background-position:100% 0}100%{background-position:0 0}}
  @keyframes rf-spin{to{transform:rotate(360deg)}}`;

  function ensureSkeletonStyles(root){
    try {
      const scope = (root && typeof root.querySelector === 'function') ? root : document;
      if (scope.__rfSkeletonCSS) return;
      // Evitar duplicados si ya existe el style marker
      const existing = scope.querySelector && scope.querySelector('style[data-rf-skeleton="1"]');
      if (existing) { scope.__rfSkeletonCSS = true; return; }
      const st = document.createElement('style');
      st.type = 'text/css';
      st.setAttribute('data-rf-skeleton','1');
      st.textContent = SKELETON_CSS;
      // En ShadowRoot no hay <head>; el root es el propio shadow/document
      if (scope instanceof ShadowRoot) {
        scope.appendChild(st);
      } else if (scope.head && typeof scope.head.appendChild === 'function') {
        scope.head.appendChild(st);
      } else if (scope.appendChild) {
        scope.appendChild(st);
      }
      scope.__rfSkeletonCSS = true;
    } catch {}
  }

  function injectSkeleton(pane, key){
    try {
      if (!pane) return;
      // Garantizar estilos del skeleton en el root actual (document o ShadowRoot)
      const root = (pane.getRootNode && pane.getRootNode()) || document;
      ensureSkeletonStyles(root);
      if ((pane.innerHTML || '').trim() !== '') return; // ya tiene algo (skeleton propio o contenido)
      const sk = document.createElement('div');
      sk.className = 'rf-skeleton rf-skel-generic';
      sk.innerHTML = '<div class="rf-skel-line"></div><div class="rf-skel-line"></div><div class="rf-skel-line short"></div><p class="rf-skel-msg">Cargando…</p>';
      pane.appendChild(sk);
    } catch {}
  }

  async function hydratePane(container, pane, playerId){
    if (!pane || pane.__rfHydrated || inflight.has(pane)) return;
    const key = pane.getAttribute('data-rf-lazy');
    if (!key) return;
    inflight.add(pane);
    try {
      pane.setAttribute('aria-busy', 'true');
      if (DEBUG) console.debug('[RF Lazy] hydratePane begin', { key, playerId });
      // Asegura un placeholder visible mientras llega el HTML
      injectSkeleton(pane, key);
      const html = await fetchTabHTML(playerId, key);
      if (!html || (typeof html === 'string' && html.trim() === '')) {
        throw new Error('Contenido no disponible en este momento. (pestaña: ' + key + ')');
      }
      pane.innerHTML = html;
      // Ejecutar scripts embebidos en el HTML inyectado (necesario para plantillas con JS inline)
      executeScripts(pane);
      // Re-inicializa wiring de filtros/buscadores dentro del root actual (document o ShadowRoot)
      try {
        var root = (pane.getRootNode && pane.getRootNode()) || document;
        if (typeof window.rfInitShadowFilters === 'function') { window.rfInitShadowFilters(root); }
        else if (typeof window.rfInitVanillaFilters === 'function') { window.rfInitVanillaFilters(root); }
      } catch(_){}
      // Dispara un evento para que otros scripts puedan engancharse a la hidratación
      try {
        // Burbujea y es composed para atravesar límites de Shadow DOM y permitir re-cableado
        const ev = new CustomEvent('rf:tab:hydrated', {
          detail: { key, playerId, container, pane },
          bubbles: true,
          composed: true,
          cancelable: false
        });
        pane.dispatchEvent(ev);
      } catch {}
      pane.__rfHydrated = true;
      if (DEBUG) console.debug('[RF Lazy] hydratePane done', { key });
    } catch (e) {
      const msg = (e && e.message) ? e.message : 'No disponible.';
      const safeKey = (key || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
      pane.innerHTML = '<div class="futbolin-card" style="padding:16px">'
        + '<p style="margin:0 0 8px 0">' + msg + '</p>'
        + '<button type="button" class="button button-primary rf-retry" data-tab="' + safeKey + '">Reintentar</button>'
        + '</div>';
      // Wire reintento directo en el botón
      try {
        const btn = pane.querySelector('.rf-retry');
        if (btn) btn.addEventListener('click', () => { hydratePane(container, pane, playerId); });
      } catch {}
      if (DEBUG) console.warn('[RF Lazy] hydratePane error', { key, error: e });
    } finally {
      pane.removeAttribute('aria-busy');
      inflight.delete(pane);
    }
  }

  async function hydrateTabs(container){
    const playerId = Number(container.getAttribute('data-player-id') || '0');
    // No abort if playerId is 0: proceed to show explicit error cards instead of leaving skeletons forever
    if (!playerId && DEBUG) console.warn('[RF Lazy] Missing playerId; will still attempt hydration to surface errors');
    const panes = $$all(container, '.futbolin-tab-content[data-rf-lazy]');
    if (DEBUG) console.debug('[RF Lazy] hydrateTabs scope', { playerId, panes: panes.length });

    // Construye cola: activa primero si es lazy, luego el resto
    const active = panes.find(p => p.classList.contains('active'));
    const rest = panes.filter(p => p !== active);
    const queue = [];
    if (active) queue.push(active);
    queue.push(...rest);

    let delay = 350; // primer tramo rápido tras paint
    for (const pane of queue) {
      if (!pane || !pane.getAttribute('data-rf-lazy')) continue;
      if (pane.__rfHydrated) continue;
      await new Promise(r => setTimeout(r, delay));
      delay = Math.min(delay + 250, 1250); // pacing suave hasta ~1.25s
      await hydratePane(container, pane, playerId);
    }
  }

  function boot(rootDoc){
    const scopes = $$all(rootDoc || document, '[data-rf-player-tabs]');
    scopes.forEach((c) => {
      if (c.__rfLazyBound) return;
      c.__rfLazyBound = true;
      // Hidratación en segundo plano
      requestAnimationFrame(() => hydrateTabs(c));
      // Hidratación inmediata al activar una pestaña
      var nav = null;
      try {
        var pp = c.closest ? c.closest('.player-profile-container') : null;
        if (pp && pp.querySelector) nav = pp.querySelector('.futbolin-tabs-nav');
      } catch {}
      if (nav) {
        nav.addEventListener('click', (e) => {
          const a = e.target.closest('a[href^="#"]');
          if (!a) return;
          const id = a.getAttribute('href').replace('#','');
          if (!id) return;
          e.preventDefault();
          // Activación inmediata de la pestaña para garantizar visibilidad del contenedor
          try {
            // Toggle en links
            $$all(nav, 'a[href^="#"]').forEach(link => link.classList.remove('active'));
            a.classList.add('active');
            // Toggle en paneles
            $$all(c, '.futbolin-tab-content').forEach(p => p.classList.remove('active'));
            const targetPane = $one(c, '#' + escSel(id));
            if (targetPane) targetPane.classList.add('active');
            // Actualiza hash sin scroll jump (si está disponible)
            if (history && typeof history.replaceState === 'function') {
              history.replaceState(null, '', '#' + id);
            } else {
              window.location.hash = '#' + id;
            }
          } catch {}
          // Hidratación bajo demanda
          const pane = $one(c, '#' + escSel(id));
          const playerId = Number(c.getAttribute('data-player-id') || '0');
          if (pane && pane.hasAttribute('data-rf-lazy') && !pane.__rfHydrated) {
            if (DEBUG) console.debug('[RF Lazy] click-trigger hydrate', { id });
            hydratePane(c, pane, playerId);
          }
        }, { capture: true });
      }
    });
  }

  // soporta Shadow host rf-shadow
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => boot(document), { once: true });
  } else {
    boot(document);
  }

  // Si existe el web component, engancha también ahí
  const tryBindShadow = () => {
    const hosts = document.querySelectorAll('ranking-futbolin-app');
    hosts.forEach(h => { if (h.shadowRoot) boot(h.shadowRoot); });
  };
  tryBindShadow();
  const mo = new MutationObserver((muts) => {
    let needs = false;
    for (const m of muts) {
      if (!m.addedNodes) continue;
      m.addedNodes.forEach(n => {
        try {
          if (!n) return;
          const isScope = (typeof n.matches === 'function' && n.matches('[data-rf-player-tabs]'))
                       || (typeof n.querySelector === 'function' && n.querySelector('[data-rf-player-tabs]'));
          if (isScope) needs = true;
        } catch {}
      });
    }
    if (needs) { tryBindShadow(); boot(document); }
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });
})();

// Exponer un hook explícito para arrancar el lazy loader dentro de un root (document o ShadowRoot)
// Úsalo desde el Web Component/Shadow tras mover el DOM al shadowRoot.
try {
  window.rfPlayerTabsLazyBoot = function(root){
    try { (root && root.querySelector) ? boot(root) : boot(document); } catch(e){ if (DEBUG) console.warn('[RF Lazy] rfPlayerTabsLazyBoot error', e); }
  };
} catch(_){}
