/* -- main.js (Versión Corregida con Inicialización de Pestañas) -- */

// Soluciona el problema del botón "Atrás" del navegador (bfcache).
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        const loader = document.getElementById('futbolin-loader-overlay');
        if (loader) {
            loader.classList.add('futbolin-loader-hidden');
        }
    }
});

jQuery(function($) {

    // --- Generar Campeones (AJAX) ---
    const $btnChamp = $('#futbolin-run-champions');
    if ($btnChamp.length) {
        $btnChamp.on('click', function(e){
            e.preventDefault();
            if (window.confirm('¿Seguro que quieres generar la lista de Campeones de España?')) {
                const $btn = $(this);
                $btn.prop('disabled', true).text('Generando...');
                const loader = document.getElementById('futbolin-loader-overlay');
                if (loader) loader.classList.remove('futbolin-loader-hidden');
                $.post(ajaxurl, {
                    action: 'futbolin_generate_champions',
                    nonce: (window.futbolin_ajax_obj && futbolin_ajax_obj.nonce) ? futbolin_ajax_obj.nonce : ''
                }).done(function(resp){
                    alert(resp && resp.data && resp.data.message ? resp.data.message : 'Proceso finalizado.');
                }).fail(function(){
                    alert('Error al generar la lista de Campeones. Revisa el log.');
                }).always(function(){
                    if (loader) loader.classList.add('futbolin-loader-hidden');
                    $btn.prop('disabled', false).text('Generar Lista de Campeones');
                });
            }
        });
    }


    // --- Lógica para Pestañas (Tabs) en el perfil de jugador ---
    const tabContainer = $('.player-profile-container');
    if (tabContainer.length) {
        const tabLinks = tabContainer.find('.futbolin-tabs-nav a[href^="#"]');
        const tabContents = tabContainer.find('.futbolin-tab-content');

        // Función para cambiar de pestaña
        function switchTab(target) {
            tabLinks.removeClass('active');
            tabContents.removeClass('active');

            const activeLink = tabLinks.filter('[href="' + target + '"]');
            activeLink.addClass('active');
            $(target).addClass('active');
        }

        // Evento de clic
        tabLinks.on('click', function(e) {
            e.preventDefault();
            const target = $(this).attr('href');
            switchTab(target);
            // Actualiza la URL sin recargar la página para poder copiar/pegar el enlace
            if (history.pushState) {
                history.pushState(null, null, target);
            }
        });

        // --- INICIALIZACIÓN AÑADIDA ---
        // Al cargar la página, decidimos qué pestaña mostrar
        function initializeTabs() {
            const hash = window.location.hash;
            // Si hay un #hash en la URL y existe una pestaña para él, la mostramos
            if (hash && tabLinks.filter('[href="' + hash + '"]').length > 0) {
                switchTab(hash);
            } else {
                // Si no, mostramos la primera pestaña que tenga la clase .active por defecto
                const defaultActiveTab = tabLinks.filter('.active').first();
                if (defaultActiveTab.length) {
                    switchTab(defaultActiveTab.attr('href'));
                } else {
                    // Si ninguna es activa por defecto, activamos la primera de todas
                    const firstTab = tabLinks.first();
                    if (firstTab.length) {
                        switchTab(firstTab.attr('href'));
                    }
                }
            }
        }

        initializeTabs(); // ¡Ejecutamos la inicialización!
    }

    // --- Lógica para Búsqueda con Sugerencias (AJAX) para búsqueda general y H2H ---
    let debounceTimer;
    $('body').on('keyup', '.futbolin-live-search', function(e) {
        const input = $(this);
        const wrapper = input.closest('.search-wrapper');
        const resultsContainer = wrapper.find('.search-results-dropdown');
        const searchTerm = input.val();
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter', 'Escape'].includes(e.key)) { return; }
        clearTimeout(debounceTimer);
        if (searchTerm.length < 3) { resultsContainer.empty().hide(); return; }
        debounceTimer = setTimeout(() => {
            $.ajax({
                url: futbolin_ajax_obj.ajax_url,
                type: 'POST',
                data: { action: 'futbolin_search_players', security: futbolin_ajax_obj.nonce, term: searchTerm },
                beforeSend: function() { resultsContainer.html('<div class="no-results">Buscando...</div>').show(); },
                success: function(response) {
                    resultsContainer.empty();
                    const players = response.success ? response.data : [];
                    if (players.length > 0) {
                        const ul = $('<ul>');
                        players.forEach(player => {
                            const form = input.closest('form');
                            let link_href = '#';
                            if (form.find('input[name="search_h2h"]').length) {
                                const base_url = new URL(window.location.href);
                                base_url.searchParams.set('compare_id', player.jugadorId);
                                base_url.searchParams.delete('search_h2h');
                                link_href = base_url.href + '#tab-h2h';
                            } else if (futbolin_ajax_obj.profile_url) {
                                const base_url = new URL(futbolin_ajax_obj.profile_url);
                                base_url.searchParams.set('jugador_id', player.jugadorId);
                                link_href = base_url.href;
                            }
                            const li = $('<li>');
                            const a = $('<a>').attr('href', link_href).text(player.nombreJugador);
                            li.append(a);
                            ul.append(li);
                        });
                        resultsContainer.append(ul);
                    } else {
                        resultsContainer.html('<div class="no-results"><p>No se encontraron jugadores.</p></div>');
                    }
                },
                error: function() { resultsContainer.html('<div class="no-results">Error de conexión.</div>'); }
            });
        }, 300);
    });

    $('body').on('keydown', '.futbolin-live-search', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
        }
    });

    // --- Lógica unificada para mostrar el Loader (enlaces y formularios) ---
    $('body').on('click', 'a', function(e) {
        // Excluye enlaces de tabs, paginación y formularios de tamaño de página
        if ($(this).parent().hasClass('futbolin-tabs-nav') || $(this).closest('.futbolin-paginacion').length || $(this).closest('.page-size-form').length || $(this).attr('href').startsWith('#')) {
            return;
        }
        $('#futbolin-loader-overlay').removeClass('futbolin-loader-hidden');
    });

    $('body').on('submit', 'form', function(e) {
        const form = $(this);
        // Si el formulario NO es el del H2H o el del Hall of Fame, mostramos el loader
        if (form.find('input[name="search_h2h"]').length === 0 && !form.hasClass('futbolin-search-hall-of-fame')) {
            $('#futbolin-loader-overlay').removeClass('futbolin-loader-hidden');
        }
    });

    // --- Lógica para el botón "Buscar" del formulario principal ---
    $('body').on('submit', '.futbolin-search-form', function(e) {
        // Obtenemos el valor de 'view' de la URL actual
        const urlParams = new URLSearchParams(window.location.search);
        const currentView = urlParams.get('view');

        // Si el formulario está en la vista del Hall of Fame, ignoramos esta lógica
        if (currentView === 'hall-of-fame') {
            return;
        }

        e.preventDefault();
        const form = $(this);
        const searchTerm = form.find('input[name="jugador_busqueda"]').val();
        if (searchTerm.length > 2) {
            $('#futbolin-loader-overlay').removeClass('futbolin-loader-hidden');
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('jugador_busqueda', searchTerm);
            currentUrl.searchParams.set('view', 'ranking');
            window.location.href = currentUrl.href;
        }
    });

    // Oculta los resultados al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-wrapper').length) {
            $('.search-results-dropdown').hide();
        }
    });

    // --- Lógica de CÁLCULO INTERACTIVO EN EL PANEL DE ADMINISTRACIÓN ---
    let calculationIntervalId = {};

    function disableAllCalculationButtons(disable) {
        $('#futbolin-run-full-calc, #futbolin-calculate-seasons, #futbolin-run-hall-of-fame-calc, #futbolin-run-finals-calc').prop('disabled', disable);
    }

    // Funciones y eventos de cálculo. Se mantienen las genéricas y las específicas que no son del Hall of Fame.

    function startAsyncCalculation(buttonId, startAction, stepAction, cancelAction, intervalTime = 3000) {
        const progressContainer = $(`#${buttonId}-progress-container`);
        const statusBar = $(`#${buttonId}-progress-status`);
        const startButton = $(`#${buttonId}`);
        const cancelButton = $(`#cancel-${buttonId}`);

        disableAllCalculationButtons(true);
        startButton.text('Calculando...');
        cancelButton.show();
        progressContainer.show();
        statusBar.text('Iniciando...');

        $.ajax({
            url: futbolin_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: startAction, security: futbolin_ajax_obj.admin_nonce },
            success: function(response) {
                if (response.success) {
                    calculationIntervalId[buttonId] = setInterval(() => {
                        runAsyncCalculationStep(buttonId, stepAction);
                    }, intervalTime);
                } else {
                    statusBar.css('color', 'red').text('Error al iniciar: ' + (response.data.message || 'Error desconocido.'));
                    disableAllCalculationButtons(false);
                    cancelButton.hide();
                    startButton.text(startButton.data('original-text') || 'Recalcular Ranking');
                }
            },
            error: function() {
                statusBar.css('color', 'red').text('Error de conexión al iniciar el proceso.');
                disableAllCalculationButtons(false);
                cancelButton.hide();
                startButton.text(startButton.data('original-text') || 'Recalcular Ranking');
            }
        });
    }

    function runAsyncCalculationStep(buttonId, stepAction) {
        const progressBar = $(`#${buttonId}-progress-bar`);
        const statusBar = $(`#${buttonId}-progress-status`);
        const startButton = $(`#${buttonId}`);
        const cancelButton = $(`#cancel-${buttonId}`);

        $.ajax({
            url: futbolin_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: stepAction, security: futbolin_ajax_obj.admin_nonce },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const progress = data.progress;
                    const message = data.message;

                    progressBar.css('width', progress + '%').text(progress + '%');
                    statusBar.text(message);

                    if (data.finished) {
                        clearInterval(calculationIntervalId[buttonId]);
                        statusBar.text('¡Proceso completado con éxito!');
                        progressBar.css('width', '100%').text('100%');
                        setTimeout(() => location.reload(), 2000);
                    }
                } else {
                    clearInterval(calculationIntervalId[buttonId]);
                    statusBar.css('color', 'red').text('Error: ' + response.data.message);
                    disableAllCalculationButtons(false);
                    startButton.text(startButton.data('original-text') || 'Recalcular Ranking');
                    cancelButton.hide();
                }
            },
            error: function() {
                clearInterval(calculationIntervalId[buttonId]);
                statusBar.css('color', 'red').text('Error de conexión. Proceso detenido.');
                disableAllCalculationButtons(false);
                startButton.text(startButton.data('original-text') || 'Recalcular Ranking');
                cancelButton.hide();
            }
        });
    }

    function cancelAsyncCalculation(buttonId, cancelAction) {
        const startButton = $(`#${buttonId}`);
        const cancelButton = $(`#cancel-${buttonId}`);

        if (!confirm('¿Estás seguro de que quieres cancelar el proceso de cálculo? Los datos parciales se borrarán.')) {
            return;
        }

        clearInterval(calculationIntervalId[buttonId]);
        disableAllCalculationButtons(true);
        cancelButton.text('Cancelando...');

        $.ajax({
            url: futbolin_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: cancelAction, security: futbolin_ajax_obj.admin_nonce },
            success: function(response) {
                if (response.success) { alert('Proceso cancelado. Los datos han sido borrados.'); } else { alert('Error al cancelar: ' + (response.data.message || 'Error desconocido.')); }
                location.reload();
            },
            error: function() {
                alert('Error de conexión al cancelar el proceso.');
                location.reload();
            }
        });
    }

    // --- Eventos de Clic para los botones de cálculo ---
    $('body').on('click', '#futbolin-run-full-calc', function(e) {
        e.preventDefault();
        startAsyncCalculation('futbolin-run-full-calc', 'futbolin_start_calculation', 'futbolin_run_calculation_step', 'futbolin_cancel_full_calc');
    });

    $('body').on('click', '#cancel-futbolin-run-full-calc', function(e) {
        e.preventDefault();
        cancelAsyncCalculation('futbolin-run-full-calc', 'futbolin_cancel_full_calc');
    });

    // NO hay eventos de clic para el Hall of Fame aquí.

    // --- Lógica de CÁLCULO RÁPIDO (Cálculo de Temporadas) ---
    $('body').on('click', '#futbolin-calculate-seasons', function(e) {
        e.preventDefault();
        const button = $(this);
        const originalText = button.text();
        const statsMessage = button.closest('.stats-control-group').find('.stats-message');
        disableAllCalculationButtons(true);
        button.prop('disabled', true).text('Calculando...');
        statsMessage.text('Iniciando cálculo de temporadas...');
        $.ajax({
            url: futbolin_ajax_obj.ajax_url,
            type: 'POST',
            data: { action: 'futbolin_calculate_seasons', security: futbolin_ajax_obj.admin_nonce },
            success: function(response) {
                if (response.success) { alert(response.data.message); } else { alert('Error: ' + (response.data.message || 'Ha ocurrido un error inesperado.')); }
            },
            error: function() { alert('Error de conexión al calcular las temporadas.'); },
            complete: function() {
                button.prop('disabled', false).text(originalText);
                disableAllCalculationButtons(false);
                location.reload();
            }
        });
    });

    // --- NUEVO: Eventos de Clic para el cálculo de Finales ---
    $('body').on('click', '#futbolin-run-finals-calc', function(e) {
        e.preventDefault();
        startAsyncCalculation('futbolin-run-finals-calc', 'futbolin_start_finals_calculation', 'futbolin_run_finals_calculation_step', 'futbolin_cancel_finals_calc');
    });
    $('body').on('click', '#futbolin-run-hall-of-fame-calc', function(e) {
        e.preventDefault();
        startAsyncCalculation(
            'futbolin-run-hall-of-fame-calc',
            'futbolin_start_hof_calculation', // acción AJAX de inicio
            'futbolin_run_hof_calculation_step', // acción AJAX de step
            'futbolin_cancel_hof_calc' // acción AJAX de cancelar
        );
    });

    $('body').on('click', '#cancel-futbolin-run-hall-of-fame-calc', function(e) {
        e.preventDefault();
        cancelAsyncCalculation('futbolin-run-hall-of-fame-calc', 'futbolin_cancel_hof_calc');
    });
    $('body').on('click', '#cancel-futbolin-run-finals-calc', function(e) {
        e.preventDefault();
        cancelAsyncCalculation('futbolin-run-finals-calc', 'futbolin_cancel_finals_calc');
    });

    // Lógica para inicializar los estados de los botones al cargar la página
    function initializeCalculationButtons() {
        const fullCalcStatus = $('#futbolin-run-full-calc').attr('disabled') === 'disabled';
        const hallOfFameStatus = $('#futbolin-run-hall-of-fame-calc').attr('disabled') === 'disabled';
        const finalsCalcStatus = $('#futbolin-run-finals-calc').attr('disabled') === 'disabled';

        if (fullCalcStatus) {
            $('#futbolin-run-full-calc-progress-container').show();
            $('#cancel-futbolin-run-full-calc').show();
            startAsyncCalculation(
                'futbolin-run-hall-of-fame-calc',
                'futbolin_start_hof_calculation', // acción AJAX inicio
                'futbolin_run_hof_calculation_step', // acción AJAX step
                'futbolin_cancel_hof_calc' // acción AJAX cancelar
            );
        }

        if (hallOfFameStatus) {
            $('#futbolin-run-hall-of-fame-calc-progress-container').show();
            $('#cancel-futbolin-run-hall-of-fame-calc').show();
            startAsyncCalculation(
                'futbolin-run-hall-of-fame-calc',
                'futbolin_start_hof_calculation',
                'futbolin_run_hof_calculation_step',
                'futbolin_cancel_hof_calc'
            );
        }

        if (finalsCalcStatus) {
            $('#futbolin-run-finals-calc-progress-container').show();
            $('#cancel-futbolin-run-finals-calc').show();
            startAsyncCalculation(
                'futbolin-run-finals-calc',
                'futbolin_start_finals_calculation',
                'futbolin_run_finals_calculation_step',
                'futbolin_cancel_finals_calc'
            );
        }
    }
    initializeCalculationButtons();
});
jQuery(function($){
  // Mapa de modalidades -> ID
  const MODE_MAP = {
    'dobles': 2,
    'individual': 1,
    'mujeres dobles': 7,
    'mujeres individual': 8,
    'mixto': 10,
    'senior dobles': 3,
    'senior individual': 4,
    'junior dobles': 5,
    'junior individual': 6
  };

  // Intenta detectar una URL base de ranking de tu propio menú lateral
  function getRankingBaseURL() {
    const a = document.querySelector('.submenu a[href*="view=ranking"]');
    if (a) {
      // Devuelve la ruta sin los parámetros
      const u = new URL(a.href, window.location.origin);
      u.search = ''; // limpiamos params
      return u.origin + u.pathname + '?view=ranking';
    }
    // Fallback razonable si no encuentra nada
    return '/ranking-futbolin/?view=ranking';
  }

  $('body').off('click.playerlink', '.ranking-player-details'); // por si hubiera handler previo
  $('body').on('click.playerlink', '.ranking-player-details', function () {
    const raw = $(this).find('h4').text().trim().toLowerCase();
    const label = raw
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'') // sin acentos
      .replace(/\s+/g,' ')                              // espacios normalizados
      .trim();

    const modalidadId = MODE_MAP[label];
    if (!modalidadId) {
      console.warn('Modalidad no reconocida desde:', raw);
      return; // no sabemos a dónde ir
    }

    const base = getRankingBaseURL();
    const url  = new URL(base, window.location.origin);
    url.searchParams.set('modalidad', String(modalidadId));
    url.searchParams.set('view', 'ranking');

    // Muestra loader
    $('#futbolin-loader-overlay').removeClass('futbolin-loader-hidden');

    // (Opcional) Cambia animación del loader cuando vamos al ranking
    const lp = document.querySelector('#futbolin-loader-overlay lottie-player');
    if (lp && window.futbolin_ajax_obj && futbolin_ajax_obj.loader_ranking) {
      lp.setAttribute('src', futbolin_ajax_obj.loader_ranking);
    }

    // Navega sí o sí
    window.location.assign(url.href);

    // Fallback por si algún JS externo intercepta
    setTimeout(function(){
      if (document.visibilityState === 'visible') {
        window.location.href = url.href;
      }
    }, 150);
  });

  // Cambiar animación también al pulsar "Volver a principal"
  $('body').off('click.backbtnswap', '.futbolin-back-button');
  $('body').on('click.backbtnswap', '.futbolin-back-button', function(){
    const lp = document.querySelector('#futbolin-loader-overlay lottie-player');
    if (lp && window.futbolin_ajax_obj && futbolin_ajax_obj.loader_ranking) {
      lp.setAttribute('src', futbolin_ajax_obj.loader_ranking);
    }
    // El overlay ya se muestra con tu handler global para <a>
  });
});
// === Escanear tipos de competición ===
jQuery(document).on('click', '#futbolin-scan-comp-types', function(){
  const $btn = jQuery(this);
  const $st  = jQuery('#futbolin-scan-status');
  $btn.prop('disabled', true).text('Escaneando…');
  jQuery.post(futbolin_ajax_obj.ajax_url, {
    action: 'futbolin_scan_comp_types',
    admin_nonce: futbolin_ajax_obj.admin_nonce
  }).done(function(res){
    if (res && res.success) {
      const n = res.data && res.data.types ? Object.keys(res.data.types).length : 0;
      $st.html('Detectados: <strong>'+ n +'</strong>');
    } else {
      alert('No se pudo escanear tipos.');
    }
  }).fail(function(){
    alert('Error de red.');
  }).always(function(){
    $btn.prop('disabled', false).text('Escanear tipos de competición');
  });
});

// === Generar informes por IDs ===
jQuery(document).on('click', '#futbolin-build-reports-by-types', function(){
  const $btn = jQuery(this);
  const $st  = jQuery('#futbolin-build-by-types-status');
  $btn.prop('disabled', true).text('Generando…');
  $st.text('');
  jQuery.post(futbolin_ajax_obj.ajax_url, {
    action: 'futbolin_build_reports_by_types',
    admin_nonce: futbolin_ajax_obj.admin_nonce
  }).done(function(res){
    if (res && res.success) {
      $st.text(res.data.message || 'OK');
    } else {
      $st.text('Fallo generando informes.');
    }
  }).fail(function(){
    $st.text('Error de red.');
  }).always(function(){
    $btn.prop('disabled', false).text('Generar informes por IDs');
  });
});


/* ===== Acciones de Datos (NUEVO) ===== */
jQuery(function($){
  function logLine(s){
    var $pre = $('#futbolin-data-actions-log');
    if ($pre.length){ $pre.show().append('['+(new Date()).toLocaleTimeString()+'] '+s+"\n").scrollTop($pre[0].scrollHeight); }
  }
  function post(action, extra){
    extra = extra || {};
    return $.ajax({
      url: (window.futbolin_ajax_obj ? futbolin_ajax_obj.ajax_url : ajaxurl),
      method: 'POST',
      dataType: 'json',
      data: Object.assign({ action: action, admin_nonce: (window.futbolin_ajax_obj ? futbolin_ajax_obj.admin_nonce : '') }, extra)
    });
  }

  $('#futbolin-sync-tournaments').on('click', function(){
    var $b=$(this).prop('disabled', true).text('Sincronizando…');
    logLine('Sincronizando torneos (local)…');
    post('futbolin_sync_tournaments').done(function(r){
      if (r && r.success){
        logLine('OK: ' + (r.data.count||0) + ' torneos. Última sync: ' + (r.data.last_sync||''));
      } else {
        logLine('ERROR: ' + (r && r.data && r.data.message ? r.data.message : 'Respuesta desconocida'));
      }
    }).fail(function(xhr){
      logLine('ERROR AJAX: ' + (xhr && xhr.responseText ? xhr.responseText : xhr.statusText));
    }).always(function(){ $b.prop('disabled', false).text('Sincronizar torneos (local)'); });
  });

  $('#futbolin-clear-tournaments-cache').on('click', function(){
    var $b=$(this).prop('disabled', true).text('Vaciando…');
    logLine('Vaciando caché de torneos…');
    post('futbolin_clear_tournaments_cache').done(function(r){
      logLine(r && r.success ? 'OK' : ('ERROR: ' + (r && r.data && r.data.message ? r.data.message : '')));
    }).fail(function(xhr){
      logLine('ERROR AJAX: ' + (xhr && xhr.responseText ? xhr.responseText : xhr.statusText));
    }).always(function(){ $b.prop('disabled', false).text('Vaciar caché de torneos'); });
  });

  $('#futbolin-clear-players-cache').on('click', function(){
    var $b=$(this).prop('disabled', true).text('Vaciando…');
    logLine('Vaciando caché de jugadores…');
    post('futbolin_clear_players_cache').done(function(r){
      logLine(r && r.success ? 'OK' : ('ERROR: ' + (r && r.data && r.data.message ? r.data.message : '')));
    }).fail(function(xhr){
      logLine('ERROR AJAX: ' + (xhr && xhr.responseText ? xhr.responseText : xhr.statusText));
    }).always(function(){ $b.prop('disabled', false).text('Vaciar caché de jugadores'); });
  });
});


// HECTOR-PATCH back-to-top

jQuery(function($){
  var $btn = $('#futbolin-backtotop');
  if (!$btn.length){
    // fallback: in case wrapper wasn't loaded for alguna ruta
    var $main = $('.futbolin-main-content');
    if ($main.length){
      $btn = $('<div class="fbtp-sticky-anchor" aria-hidden="true"><button type="button" id="futbolin-backtotop" class="futbolin-backtotop" aria-label="Volver arriba" title="Volver arriba"><span class="fbtp-icon" aria-hidden="true">↑</span></button></div>');
      $main.prepend($btn);
      $btn = $main.find('#futbolin-backtotop');
    }
  }
  if (!$btn.length) return;

  var threshold = 180;
  function onScroll(){
    if (window.scrollY > threshold) $btn.addClass('is-visible');
    else $btn.removeClass('is-visible');
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();

  $(document).on('click', '#futbolin-backtotop', function(e){
    e.preventDefault();
    window.scrollTo({top:0, behavior:'smooth'});
  });
});


// === Podio universal (sin iconos; sólo color) ==============================
(function(){
  function parsePos(text){
    if(!text) return 0;
    var m = String(text).match(/\d+/);
    return m ? parseInt(m[0],10) : 0;
  }
  function first(el, sel){
    if(!el) return null;
    var q = el.querySelector(sel);
    return q || null;
  }
  function findPosEl(row){
    return first(row, '.pos-cell') ||
           first(row, '.ranking-position') ||
           first(row, '.ranking-cell.pos') ||
           first(row, '.position') ||
           first(row, '.pos') ||
           first(row, '.ranking-pos');
  }
  function decorateRow(row){
    if(!row || row.__podiumApplied) return;
    var posEl = findPosEl(row);
    var pos = posEl ? parsePos(posEl.textContent) : 0;
    if(pos>=1 && pos<=3){
      row.classList.add('podium-'+pos);
      row.__podiumApplied = true;
    }
  }
  function apply(container){
    (container || document).querySelectorAll('.ranking-row').forEach(decorateRow);
  }
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', function(){ apply(); });
  } else {
    apply();
  }
  var mo = new MutationObserver(function(muts){
    muts.forEach(function(m){
      m.addedNodes && m.addedNodes.forEach(function(n){
        if(!(n instanceof HTMLElement)) return;
        if(n.classList && n.classList.contains('ranking-row')){
          decorateRow(n);
        }else{
          var rows = n.querySelectorAll ? n.querySelectorAll('.ranking-row') : [];
          rows.forEach(decorateRow);
        }
      });
    });
  });
  mo.observe(document.body, {childList:true, subtree:true});
})();


// === Estrellas HOF/Torneos robustas =======================================
(function(){
  function parsePos(text){ var m = String(text||"").match(/\d+/); return m ? parseInt(m[0],10) : 0; }
  function hasStar(el){ return !!(el && el.querySelector && el.querySelector('.pos-star')); }
  function q(el, sel){ return el ? el.querySelector(sel) : null; }
  function findPosEl(row){
    return q(row,'.pos-cell') || q(row,'.ranking-position') || q(row,'.ranking-cell.pos') || q(row,'.position') || q(row,'.pos') || q(row,'.ranking-pos');
  }
  function inHOF(row){
    return !!(row.closest('.futbolin-hall-of-fame-wrapper') ||
              row.closest('.hall-of-fame-table-container') ||
              row.closest('#hof-rows') || row.closest('#hof-rows-prerender') ||
              document.getElementById('hof-rows') || document.getElementById('hof-rows-prerender'));
  }
  function inTournamentResults(row){
    return !!(row.closest('#tournament-detail') ||
              row.closest('.torneo-comp') ||
              row.classList.contains('tournament-row'));
  }
  function decorate(row){
    var posEl = findPosEl(row); if(!posEl) return;
    var pos = parsePos(posEl.textContent);
    if(pos>=1 && pos<=3){
      if( (inHOF(row) || inTournamentResults(row)) && !hasStar(posEl) ){
        var star = document.createElement('span');
        star.className = 'pos-star';
        star.setAttribute('aria-hidden','true');
        star.textContent = '★';
        posEl.appendChild(star);
      }
    }
  }
  function apply(container){ (container||document).querySelectorAll('.ranking-row').forEach(decorate); }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', function(){ apply(); }); } else { apply(); }
  new MutationObserver(function(m){
    m.forEach(function(x){
      x.addedNodes && x.addedNodes.forEach(function(n){
        if(!(n instanceof HTMLElement)) return;
        if(n.classList && n.classList.contains('ranking-row')){ decorate(n); }
        else { var rs = n.querySelectorAll ? n.querySelectorAll('.ranking-row') : []; rs.forEach(decorate); }
      });
    });
  }).observe(document.body,{childList:true,subtree:true});
})();
