/*! HECTOR FIX 9: Mantener scroll global + back-to-top alineado al contenedor principal (versión saneada) */
(function(){
  function onReady(cb){ if(document.readyState!=='loading'){ cb(); } else { document.addEventListener('DOMContentLoaded', cb, {once:true}); } }
  function isFormControl(el){ return el && (el.tagName==='INPUT' || el.tagName==='TEXTAREA' || el.tagName==='SELECT' || el.isContentEditable); }
  function insideLayout(el){ return !!(el && el.closest('.futbolin-layout-container.with-sidebar')); }

  function stripInlineScrollAttrs(){
    try{
      var root = document.querySelector('.futbolin-layout-container.with-sidebar');
      if(!root) return;
      var sel = '.futbolin-main-content, .futbolin-card, .futbolin-ranking, .ranking-rows, .ranking-header';
      root.querySelectorAll(sel).forEach(function(el){
        ['overflow','overflowY','overflowX','maxHeight','height','-webkit-overflow-scrolling'].forEach(function(prop){ el.style.removeProperty(prop); });
        el.style.setProperty('overflow','visible','important');
        el.style.setProperty('overflow-y','visible','important');
        el.style.setProperty('overflow-x','visible','important');
        el.style.setProperty('max-height','none','important');
        el.style.setProperty('-webkit-overflow-scrolling','auto','important');
      });
    }catch(_){}
  }

  onReady(function(){
    // 1) Blindaje anti-scroll interno
    try{
      stripInlineScrollAttrs();
      requestAnimationFrame(stripInlineScrollAttrs);
      window.addEventListener('resize', stripInlineScrollAttrs);
    }catch(_){}

    // 2) Scroll global (rueda + teclado) SOLO dentro del layout del ranking
    try{
      var wheelHandler = function(e){
        if(!insideLayout(e.target)) return;
        if(isFormControl(e.target)) return;
        var dy = (typeof e.deltaY === 'number') ? e.deltaY : (e.wheelDelta ? -e.wheelDelta : (e.detail || 0));
        var dx = (typeof e.deltaX === 'number') ? e.deltaX : 0;
        if(!dy && !dx) return;
        e.preventDefault();
        e.stopPropagation();
        window.scrollBy({ top: dy, left: dx, behavior: 'auto' });
      };
      ['wheel','mousewheel','DOMMouseScroll'].forEach(function(type){
        document.addEventListener(type, wheelHandler, {passive:false, capture:true});
      });

      var keyHandler = function(e){
        if(!insideLayout(e.target) || isFormControl(e.target)) return;
        var code = e.code || e.key, delta = 0;
        var vh = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
        switch(code){
          case 'ArrowDown': case 'Down': delta = 40; break;
          case 'ArrowUp':   case 'Up':   delta = -40; break;
          case 'PageDown': delta = vh * 0.9; break;
          case 'PageUp':   delta = -vh * 0.9; break;
          case 'Space':
          case ' ': delta = e.shiftKey ? -vh * 0.9 : vh * 0.9; break;
          case 'Home': window.scrollTo({top:0, behavior:'auto'}); e.preventDefault(); return;
          case 'End':  window.scrollTo({top:document.documentElement.scrollHeight, behavior:'auto'}); e.preventDefault(); return;
        }
        if(delta !== 0){
          e.preventDefault(); e.stopPropagation();
          window.scrollBy({ top: delta, left: 0, behavior: 'auto' });
        }
      };
      document.addEventListener('keydown', keyHandler, true);
    }catch(_){}

    // 3) Back-to-top: alineado al contenedor y visible tras scroll
    try{
      var btt = document.querySelector('.rf-back-to-top, #rf-back-to-top');
      function updateBttPosition(){
        if(!btt) return;
        var cont = document.querySelector('.futbolin-layout-container.with-sidebar');
        if(!cont) return;
        var rect = cont.getBoundingClientRect();
        var rightPad = Math.max(16, (window.innerWidth - rect.right) + 16);
        btt.style.right = rightPad + 'px';
      }
      function toggleBttVisibility(){
        if(!btt) return;
        var y = (window.pageYOffset||document.documentElement.scrollTop);
        if(y > 300){
          btt.classList.add('is-visible');
          btt.classList.remove('is-hidden');
          btt.removeAttribute('aria-hidden');
          btt.removeAttribute('tabindex');
        } else {
          btt.classList.remove('is-visible');
          btt.classList.add('is-hidden');
          btt.setAttribute('aria-hidden','true');
          btt.setAttribute('tabindex','-1');
        }
      }
      var onSR = function(){ updateBttPosition(); toggleBttVisibility(); };
      window.addEventListener('scroll', onSR, {passive:true});
      window.addEventListener('resize', function(){ updateBttPosition(); }, {passive:true});
      updateBttPosition();
      toggleBttVisibility();
      if(btt){
        btt.addEventListener('click', function(e){ e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
      }
    }catch(_){}
  });
})();
