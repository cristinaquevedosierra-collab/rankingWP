/* Admin JS – Cache Ranking Futbolín */
jQuery(function($){
  if (typeof RFHITOSCACHE === 'undefined') { return; }
  const logBox = $('#rf-hitos-cache-log');
  const bar = $('#rf-hitos-cache-progress');
  const meta = $('#rf-hitos-cache-meta');
  const statsBox = $('#rf-hitos-cache-stats');
  // Insertar botón de refresco rápido si no existe
  if ($('#rf-hitos-cache-refresh').length===0 && $('#rf-hitos-cache-start').length){
    $('<button id="rf-hitos-cache-refresh" class="button" type="button" style="margin-left:4px;">Refrescar Estado</button>')
      .insertAfter('#rf-hitos-cache-purge');
  }
  let running = false;
  let lastDone = 0;
  function log(msg){ const ts=new Date().toLocaleTimeString(); logBox.prepend('<div>['+ts+'] '+msg+'</div>'); }
  function paint(s){
    bar.css('width', s.progress+'%');
    let extra = '';
    if (typeof s.rankings_enqueued !== 'undefined') { extra += ' | rankings añadidos: '+s.rankings_enqueued; }
    if (typeof s.mods_found !== 'undefined') { extra += ' | mods: '+s.mods_found; }
    if (typeof s.temps_found !== 'undefined') { extra += ' | temps: '+s.temps_found; }
    if (typeof s.tasks_pending !== 'undefined') { extra += ' | pendientes: '+s.tasks_pending; }
    if (typeof s.token_fp !== 'undefined' && s.token_fp) { extra += ' | token:'+s.token_fp; }
    // Resumen agregado
    if (typeof s.players_cached !== 'undefined') {
      extra += ' | jugadores:'+s.players_cached;
    }
    if (typeof s.rankings_base_completed !== 'undefined') {
      extra += ' | rankings(base:'+s.rankings_base_completed+', temp:'+s.rankings_temp_completed+')';
    }
    if (typeof s.modalidades_count !== 'undefined') {
      extra += ' | mod/torneos/campeones:'+s.modalidades_count+'/'+s.torneos_count+'/'+s.campeones_count;
    }
    if (typeof s.players_detected !== 'undefined' && typeof s.coverage_pct !== 'undefined') {
      extra += ' | jugadores:'+s.players_cached+'/'+s.players_detected+' ('+s.coverage_pct+'%)';
    }
    if (s.players_index_meta) {
      const pim = s.players_index_meta;
      if (pim.players_index_players) {
        const ageSec = Math.max(0, Math.round(Date.now()/1000 - (pim.players_index_generated||0)));
        const ageFmt = ageSec < 90 ? ageSec+'s' : Math.round(ageSec/60)+'m';
        extra += ' | índice:'+pim.players_index_players+' jugadores, movs:'+pim.top_movements_count+' ('+ageFmt+')';
      }
    }
    meta.html('<strong>Estado:</strong> '+s.status+' | '+s.done+'/'+s.total+' ('+s.progress+'%)'+(s.ready?' ✅':'') + (s.enabled?'':' — deshabilitada') + extra );
    // Panel detallado
    if (statsBox.length){
      const cells = [];
      const push = (label,val,tip)=>{ cells.push('<div style="background:#f1f5f9;border:1px solid #cbd5e1;padding:6px;border-radius:4px;"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#475569;">'+label+'</div><div style="font-weight:600;color:#0f172a;" title="'+(tip||'')+'">'+val+'</div></div>'); };
      push('Progreso', s.done+'/'+s.total+' ('+s.progress+'%)');
      if (typeof s.modalidades_count !== 'undefined') push('Modalidades', s.modalidades_count);
      if (typeof s.torneos_count !== 'undefined') push('Torneos', s.torneos_count);
      if (typeof s.campeones_count !== 'undefined') push('Campeones', s.campeones_count);
      if (typeof s.rankings_base_completed !== 'undefined') push('Rankings Base', s.rankings_base_completed + (s.mods_found? ' / '+s.mods_found : ''));
      if (typeof s.rankings_temp_completed !== 'undefined') push('Rankings Temp', s.rankings_temp_completed + (s.temps_found? ' / '+s.temps_found : ''));
      if (typeof s.players_cached !== 'undefined') push('Perfiles', s.players_cached + (s.players_detected? ' / '+s.players_detected : ''));
      if (typeof s.coverage_pct !== 'undefined') push('Cobertura', s.coverage_pct+'%');
      if (s.players_index_meta && s.players_index_meta.players_index_players){
        const pim = s.players_index_meta; const ageSec = Math.max(0, Math.round(Date.now()/1000 - (pim.players_index_generated||0)));
        const ageFmt = ageSec < 90 ? ageSec+'s' : Math.round(ageSec/60)+'m';
        push('Índice Jugadores', pim.players_index_players + ' ('+ageFmt+')', 'Movimientos top: '+pim.top_movements_count);
      }
      if (typeof s.rankings_enqueued !== 'undefined') push('Rankings Encolados', s.rankings_enqueued);
      if (typeof s.tasks_pending !== 'undefined') push('Tareas Pendientes', s.tasks_pending);
      if (s.token_fp) push('Token', s.token_fp);
      statsBox.html(cells.join(''));
    }
    if (typeof s.players_cached !== 'undefined') {
      $('#rf-hitos-cache-players').text('Perfiles cacheados: '+s.players_cached);
    }
    const chk = $('#rf-hitos-cache-enabled');
    if (chk.length) { chk.prop('checked', !!s.enabled); }
    if (s.errors && s.errors.length){
      s.errors.slice(-3).forEach(e=>log('Error '+e.k+': '+e.msg));
    }
    if (s.done !== lastDone) {
      if (s.last_completed) {
        var lc = s.last_completed;
        log('Paso '+s.done+'/'+s.total+': '+(lc.t||'?')+' → '+(lc.k||'?')+(lc.error?' (error)':'')+' ('+s.progress+'%)');
      } else {
        log('Paso completado: '+s.done+'/'+s.total+' ('+s.progress+'%)');
      }
      lastDone = s.done;
    }
  }
  function step(){
    if(!running) return;
    $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_step',_n:RFHITOSCACHE.nonce})
      .done(r=>{
        if(!r.success){ log('Error step: '+(r.data&&r.data.msg||'desconocido')); running=false; return; }
        paint(r.data);
        if (r.data.status==='done'){ running=false; log('Proceso completado.'); }
        else setTimeout(step, 180);
      })
      .fail(()=>{ log('Fallo conexión, reintento en 5s'); setTimeout(step,5000); });
  }
  $('#rf-hitos-cache-start').on('click', function(){
    if(running) { log('Ya en progreso...'); return; }
    running=true; lastDone=0; log('Inicializando generación de cache...');
    $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_init',_n:RFHITOSCACHE.nonce})
      .done(r=>{
        if(!r.success){ log('Error init: '+(r.data&&r.data.msg||'desconocido')); running=false; return; }
        paint(r.data);
        if (r.data.status!=='running') { log('Estado inesperado tras init: '+r.data.status); running=false; return; }
        log('Manifiesto iniciado con '+r.data.total+' tareas.');
        step();
      })
      .fail(()=>{ log('Fallo init AJAX'); running=false; });
  });
  $('#rf-hitos-cache-purge').on('click', function(){
    if(!confirm('¿Purgar cache completa?')) return; running=false; lastDone=0;
    $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_purge',_n:RFHITOSCACHE.nonce})
      .done(r=>{ log('Cache purgada. Listo para iniciar.'); if(r.success) paint(r.data); });
  });
  $('#rf-hitos-cache-enabled').on('change', function(){
    const on = $(this).is(':checked') ? 1 : 0;
    $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_toggle',on:on,_n:RFHITOSCACHE.nonce})
      .done(r=>{ if (r.success) { paint(r.data); log('Cache '+(on?'activada':'desactivada')); } });
  });
  // Refresco manual
  $(document).on('click','#rf-hitos-cache-refresh', function(){
    $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_status',_n:RFHITOSCACHE.nonce})
      .done(r=>{ if(r.success){ paint(r.data); log('Estado actualizado manualmente.'); }});
  });
  // Estado inicial (si había cache previa)
  if (window.__RF_HITOS_STATUS){ paint(window.__RF_HITOS_STATUS); log('Estado inicial cargado.'); }
  else {
    $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_status',_n:RFHITOSCACHE.nonce})
      .done(r=>{ if(r.success){ paint(r.data); log('Estado consultado: '+r.data.status); if(r.data.status==='done'){ log('Cache ya completa. Pulsa Generar para rehacer o Purgar para limpiar.'); } } });
  }

  // AUTO-ARRANQUE: si la cache está habilitada, status idle/done con muy pocas tareas y no se está ejecutando
  function maybeAutoStart(){
    $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_status',_n:RFHITOSCACHE.nonce})
      .done(r=>{
        if(!r.success) return;
        const s=r.data; paint(s);
        const noWork = (s.done<=s.total && s.total<=6); // solo base mínima
        const can = s.enabled && (s.status==='idle' || s.status==='done') && noWork && !running;
        if (can){
          log('Auto-arranque: iniciando cache masiva...');
          $('#rf-hitos-cache-start').trigger('click');
        }
      });
  }
  setTimeout(maybeAutoStart, 1200);
  // Polling periódico para mantener métricas visibles aun en idle/done
  function periodicRefresh(){
    if(!running){
      $.post(RFHITOSCACHE.ajax,{action:'rfhitos_cache_status',_n:RFHITOSCACHE.nonce})
        .done(r=>{ if(r.success){ paint(r.data); }});
    }
    setTimeout(periodicRefresh, 10000); // cada 10s
  }
  setTimeout(periodicRefresh, 5000);
});
