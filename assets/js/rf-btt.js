(function(){
  function onReady(cb){
    if(document.readyState!=='loading'){ cb(); }
    else { document.addEventListener('DOMContentLoaded', cb, {once:true}); }
  }

  function ensureStyle(){
    if(document.getElementById('rf-btt-style')) return;
    var st=document.createElement('style'); st.id='rf-btt-style';
    st.textContent = [
      '#rf-btt{position:fixed;right:16px;bottom:16px;left:auto;z-index:2147483642;display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:999px;background:rgba(33,37,41,.68);color:#fff;text-decoration:none;font:600 16px/1 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;border:1px solid rgba(255,255,255,.08);box-shadow:0 4px 12px rgba(0,0,0,.25);opacity:0;pointer-events:none;transform:translateY(6px);transition:opacity .2s ease,transform .2s ease,background-color .2s ease;}',
      '#rf-btt.show{opacity:1;pointer-events:auto;transform:translateY(0);}',
      '#rf-btt:hover{transform:translateY(-1px);background:rgba(33,37,41,.78);}'
    ].join('');
    document.head.appendChild(st);
  }

  function ensureBtn(){
    var el=document.getElementById('rf-btt');
    if(!el){
      el=document.createElement('a');
      el.id='rf-btt';
      el.href='#top';
      el.setAttribute('role','button');
      el.setAttribute('aria-label','Volver arriba');
      el.textContent='▲';
      document.body.appendChild(el);
      el.addEventListener('click', function(e){
        e.preventDefault();
        try{ window.scrollTo({top:0, behavior:'smooth'}); }catch(_){ window.scrollTo(0,0); }
      });
    }
    return el;
  }

  // Contenedores preferidos para “pegar” el botón al borde derecho
  var SELECTORS = [
    '#player-profile-container', '.player-profile-container',
    '#futbolin-content-container', '.futbolin-content-container',
    '.futbolin-layout-container.with-sidebar',
    '.futbolin-full-bleed-wrapper.theme-light',
    '.futbolin-full-bleed-wrapper',
    '.futbolin-layout-container'
  ];

  function findInShadows(selList){
    try{
      var all = document.querySelectorAll('*');
      for (var i=0;i<all.length;i++){
        var el = all[i];
        if (el && el.shadowRoot){
          for (var j=0;j<selList.length;j++){
            var n = el.shadowRoot.querySelector(selList[j]);
            if (n) return n;
          }
        }
      }
    }catch(_){}
    return null;
  }

  function getWrapper(){
    for (var i=0;i<SELECTORS.length;i++){
      var n = document.querySelector(SELECTORS[i]);
      if (n) return n;
    }
    var inShadow = findInShadows(SELECTORS);
    return inShadow || document.body;
  }

  function isRankingView(){
    try{
      var p = (window.location && window.location.pathname) || '';
      return p === '/futbolin-ranking' || p === '/futbolin-ranking/' || p.indexOf('/futbolin-ranking/') === 0;
    }catch(_){ return false; }
  }

  function isPlayerView(){
    try{
      var u = new URL(window.location.href);
      return u.pathname === '/perfil-jugador' ||
             u.pathname.startsWith('/perfil-jugador/') ||
             u.searchParams.has('jugador_id');
    }catch(_){ return false; }
  }

  // Elimina cualquier botón legado con id="futbolin-backtotop" (en DOM normal o Shadow)
  function removeLegacyButtons(){
    try{
      var legacy = document.getElementById('futbolin-backtotop');
      if (legacy && legacy.parentNode) legacy.parentNode.removeChild(legacy);
      var all = document.querySelectorAll('*');
      for (var i=0;i<all.length;i++){
        var el = all[i];
        if (el && el.shadowRoot){
          var old = el.shadowRoot.getElementById && el.shadowRoot.getElementById('futbolin-backtotop');
          if (old && old.parentNode) old.parentNode.removeChild(old);
        }
      }
    }catch(_){}
  }

  function positionBtn(){
    var btn = document.getElementById('rf-btt'); if(!btn) return;
    var wrap = getWrapper();
    var rect = (wrap && wrap.getBoundingClientRect) ? wrap.getBoundingClientRect() : {right:(window.innerWidth||0), left:0};
    var vw = window.innerWidth || document.documentElement.clientWidth || 0;
    var margin = 16;
    var btnW = btn.offsetWidth || 44;

    // Base: pegado al borde derecho del wrapper
    var offsetRight = Math.max(margin, Math.round((vw - rect.right) + margin));

    // RANKING: desplazar hacia la izquierda el valor que estabas usando
    if (isRankingView()){
      var SHIFT_PX = 580; // tu valor actual para ranking
      offsetRight += SHIFT_PX;
      var maxRight = Math.max(margin, Math.round((vw - rect.left) - btnW - margin));
      if (offsetRight > maxRight) offsetRight = maxRight;
    }
    // PERFIL: mover 3 cm hacia la derecha (más pegado al borde) → reducir 'right'
    else if (isPlayerView()){
      var PX_PER_CM = 96/2.54; // ~37.8 px por cm
      var SHIFT_PX_PROFILE = Math.round(PX_PER_CM * 3); // ≈ 113 px
      offsetRight = Math.max(margin, offsetRight - SHIFT_PX_PROFILE);
    }

    btn.style.right = offsetRight + 'px';
    btn.style.left = 'auto';
    btn.style.bottom = margin + 'px';
  }

  function toggle(){
    var y = window.pageYOffset || document.documentElement.scrollTop || 0;
    var el = document.getElementById('rf-btt'); if(!el) return;
    if(y <= 40){
      el.classList.remove('show');
      el.setAttribute('aria-hidden','true');
      el.tabIndex = -1;
    } else {
      el.classList.add('show');
      el.removeAttribute('aria-hidden');
      el.removeAttribute('tabindex');
    }
  }

  function init(){
    ensureStyle();
    removeLegacyButtons();
    ensureBtn();
    positionBtn();
    toggle();
    window.addEventListener('scroll', function(){ positionBtn(); toggle(); }, {passive:true});
    window.addEventListener('resize', function(){ positionBtn(); toggle(); }, {passive:true});
  }

  onReady(init);
})();
