(function(){
  if (window.__rfH2H24) return; window.__rfH2H24 = true;

  function $all(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
  function $(sel, ctx){ return (ctx||document).querySelector(sel); }
  function norm(s){
    s = (s||'').toString().toLowerCase();
    try{ s = s.normalize('NFD').replace(/[\u0300-\u036f]/g,''); }catch(e){}
    s = s.replace(/\s+/g,' ').trim();
    return s;
  }

  function ensureGlobalLabel(){
    var host = $('.history-summary-search');
    if (!host) return null;
    var label = $('#history-global-label');
    if (!label){
      label = document.createElement('div');
      label.id = 'history-global-label';
      label.className = 'history-global-label';
      label.setAttribute('role','heading');
      label.setAttribute('aria-level','2');
      // Siempre visible por defecto
      label.style.display = 'block';
      label.style.marginBottom = '6px';
      label.style.fontWeight = '700';
      label.style.width = '100%';
      label.textContent = 'RESULTADOS GLOBALES';
      var cards = host.querySelector('.history-summary-cards');
      if (cards) host.insertBefore(label, cards); else host.insertBefore(label, host.firstChild);
    } else {
      // fuerza bloque y ancho completo en caso de estilos de tema flex
      label.style.width = '100%';
      label.style.display = 'block';
      // Alinear el texto por defecto
      if (!label.textContent || /DATOS GLOBALES/i.test(label.textContent)) {
        label.textContent = 'RESULTADOS GLOBALES';
      }
    }
    return label;
  }
  function showGlobal(){ var l = ensureGlobalLabel(); if (l) l.style.display = 'block'; }
  // No ocultar nunca el rótulo global por defecto
  function hideGlobal(){ /* noop: mantenemos visible el rótulo global */ }

  function applyH2HBoxStyles(box){
    if (!box) return;
    box.setAttribute('aria-live','polite');
    box.style.marginTop = '8px';
    box.style.border = 'none';
    box.style.borderLeft = '1px solid #e5e7eb';
    box.style.padding = '10px';
    box.style.paddingLeft = '12px';
    box.style.marginLeft = '12px';
    box.style.borderRadius = '8px';
    box.style.fontSize = '14px';
}
function ensureH2HBox(){
    var host = $('.history-summary-search');
    if (!host) return null;
    var box = $('#history-filter-counters');
    if (!box){
      box = document.createElement('div');
      box.id = 'history-filter-counters';
      box.className = 'history-filter-counters';
    }
    applyH2HBoxStyles(box);
    // Colocar SIEMPRE antes de la caja de búsqueda, para no moverla
    var search = host.querySelector('.history-search-box');
    if (search && box.parentNode !== host) host.insertBefore(box, search);
    else if (search && box.nextSibling !== search) host.insertBefore(box, search);
    else if (!search && !box.parentNode) host.appendChild(box);
    if (!box.style.display) box.style.display = 'none';
    applyH2HBoxStyles(box);
    return box;
  }
  function clearH2H(){
    var box = $('#history-filter-counters');
    if (box){ box.style.display='none'; box.innerHTML=''; }
    // Mantener visible “RESULTADOS GLOBALES”
    showGlobal();
  }

  // --- Precompute rows snapshot ---
  var ctx = { pid:null, rows:[] };

  function parseRow(row){
    var d = row.getAttribute('data-players') || '';
    var ids = d.split(/[,;\s]+/).map(function(x){ return parseInt(x,10); }).filter(function(x){ return !isNaN(x); });
    var w = row.querySelector('.history-match-winner');
    var l = row.querySelector('.history-match-loser');
    var wt = norm(w ? w.textContent : '');
    var lt = norm(l ? l.textContent : '');
    var det = row.querySelector('.history-match-details');
    var all = norm((w?w.textContent:'')+' '+(l?l.textContent:'')+' '+(det?det.textContent:''));
    return {ids:ids, wt:wt, lt:lt, all:all};
  }

  function bootstrapSnapshot(){
    ctx.rows = $all('.history-match-row').map(parseRow);
    var pidEl = $('#history-player-id'); 
    var pid = pidEl ? parseInt(pidEl.value,10) : NaN;
    ctx.pid = isNaN(pid) ? null : pid;

    // backfill player name si viene vacío
    var nameEl = $('#history-player-name');
    if (nameEl && !nameEl.value && ctx.pid){
      var own = ctx.rows.filter(function(r){ return r.ids.indexOf(ctx.pid)!==-1; });
      var freq = Object.create(null), total = own.length;
      own.forEach(function(r){
        r.all.split(/[^a-z0-9áéíóúñü]+/i).forEach(function(tok){
          tok = norm(tok);
          if (!tok || tok.length<3) return;
          if (tok==='vs'||tok==='los'||tok==='las'||tok==='del'||tok==='de'||tok==='el'||tok==='la'||tok==='y') return;
          freq[tok] = (freq[tok]||0)+1;
        });
      });
      var tokens = Object.keys(freq).filter(function(k){ return freq[k] >= Math.max(3, Math.ceil(0.7*(total||1))); });
      tokens.sort(function(a,b){ return freq[b]-freq[a]; });
      nameEl.value = tokens.slice(0,3).join(' ');
    }
  }

  function sideOfPid(r, pid){
    if (!pid || !r.ids || !r.ids.length) return null;
    var half = Math.floor(r.ids.length/2) || 1;
    var winners = r.ids.slice(0,half);
    var losers  = r.ids.slice(half);
    if (winners.indexOf(pid)!==-1) return 'W';
    if (losers.indexOf(pid)!==-1)  return 'L';
    return null;
  }
  function sideOfQuery(r, qn){
    var inW = qn && r.wt.indexOf(qn)!==-1;
    var inL = qn && r.lt.indexOf(qn)!==-1;
    if (inW && !inL) return 'W';
    if (inL && !inW) return 'L';
    return null; // ambiguo
  }
  function readSelfName(){
    var el = $('#history-player-name'); 
    return norm(el && el.value ? el.value : '');
  }

  // Evitar falsos positivos (p.ej. "jor" coincide con "jorge" y "jordan").
  function isSelfTerm(qn){
    if (!qn || qn.length < 4) return false; // regla nueva: mínimo 4 chars para self
    var s = readSelfName();
    if (s && (s.indexOf(qn)!==-1 || qn.indexOf(s)!==-1)) return true;
    // Heurística de lados (sólo si el término no es demasiado corto)
    var withPid=0, sameSide=0;
    for (var i=0;i<ctx.rows.length;i++){
      var r = ctx.rows[i];
      if (r.all.indexOf(qn)===-1) continue;
      var sidePid = sideOfPid(r, ctx.pid); if (!sidePid) continue;
      var sideQ = sideOfQuery(r, qn); if (!sideQ) continue; // si ambiguo, no suma
      withPid++; if (sidePid===sideQ) sameSide++;
    }
    return (withPid>=5 && sameSide/withPid>=0.9);
  }

  function computeH2H(q){
    var qn = norm(q||''); 
    var pid = ctx.pid;
    var stats = { total:0, mate:{j:0,g:0,p:0}, rival:{j:0,g:0,p:0} };
    if (!qn || qn.length<2 || !pid) return stats;

    var selfQ = isSelfTerm(qn);
    for (var i=0;i<ctx.rows.length;i++){
      var r = ctx.rows[i];
      var inW = r.wt.indexOf(qn)!==-1;
      var inL = r.lt.indexOf(qn)!==-1;
      if (!inW && !inL) continue;

      var sidePid = sideOfPid(r, pid);
      if (!sidePid) continue;
      var winSide = (sidePid==='W');

      if (selfQ) continue; // ignorar self

      var sideQ = inW && !inL ? 'W' : (inL && !inW ? 'L' : null);
      if (!sideQ) continue; // fila ambigua, no se cuenta
      var sameSide = (sideQ===sidePid);
      var bucket = sameSide ? stats.mate : stats.rival;
      bucket.j += 1;
      if (winSide) bucket.g += 1; else bucket.p += 1;
      stats.total += 1;
    }
    return stats;
  }

  function render(query, stats){
    var box = ensureH2HBox();
    if (!box) return;
    box.style.display='block';
    showGlobal();
    var esc = function(s){
      return (s||'').toString()
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
    };
    box.innerHTML = ''
      + '<div class="h2h-title">Filtro H2H: “'+esc(query)+'”</div>'
      + '<div class="h2h-cards">'
      + '  <div class="h2h-item h2h-total"><span>Apariciones totales:</span> <strong>'+stats.total+'</strong></div>'
      + '  <div class="h2h-item h2h-rival"><span>Como rival:</span> <strong>jugadas '+stats.rival.j+' · ganadas '+stats.rival.g+' · perdidas '+stats.rival.p+'</strong></div>'
      + '  <div class="h2h-item h2h-mate"><span>Como compañero:</span> <strong>jugadas '+stats.mate.j+' · ganadas '+stats.mate.g+' · perdidas '+stats.mate.p+'</strong></div>'
      + '</div>';
  }

  function debounced(fn, ms){
    var t=null;
    return function(){ var self=this, args=arguments;
      if (t) clearTimeout(t);
      t = setTimeout(function(){ fn.apply(self, args); }, ms||140);
    };
  }

  function bind(){
    var input = $('#history-search');
    if (!input) return;
    // Preparar snapshot y asegurar rótulo y caja (persistentes)
    bootstrapSnapshot();
    ensureGlobalLabel();
    showGlobal();
    var box = ensureH2HBox();

    var onChange = debounced(function(){
      var v = input.value || '';
      if (!v || v.trim().length<2){ clearH2H(); return; }
      var stats = computeH2H(v);
      render(v, stats);
    }, 120);

    input.addEventListener('input', onChange, {passive:true});
    input.addEventListener('change', onChange, {passive:true});

    if (input.value && input.value.trim().length>=2){ onChange(); }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();