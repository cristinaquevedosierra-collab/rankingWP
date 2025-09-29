<?php
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

require_once FUTBOLIN_API_PATH . 'includes/core/class-futbolin-normalizer.php';
include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/**
 * Variables esperadas (las provee el controlador/shortcode):
 * - $data: array con 'mode' => 'list'|'detail', y datos asociados
 * - $profile_page_url: URL base de la página de perfil (si existe en opciones)
 */

$mode = isset($data['mode']) ? $data['mode'] : 'list';
?>

<div class="futbolin-tournaments-wrapper">

<?php if ($mode === 'list'): ?>

  <?php
  // ---- Parámetros coherentes con ranking-display ----
  $q_raw     = isset($_GET['q']) ? wp_unslash($_GET['q']) : '';
  $q         = sanitize_text_field($q_raw);

  $page_size_raw = isset($_GET['pageSize']) ? (int)$_GET['pageSize']
                 : (isset($_GET['page_size']) ? (int)$_GET['page_size']
                 : (isset($_GET['tpage_size']) ? (int)$_GET['tpage_size'] : -1));

  $page      = (int)($_GET['fpage'] ?? $_GET['page'] ?? $_GET['tpage'] ?? ($data['page'] ?? 1));
  $page      = max(1, $page);

  // Fuente completa SIEMPRE (endpoint no paginado) — más fiable para filtrar en cliente
  $all = is_array($data['list'] ?? null) ? $data['list'] : [];
  // Orden por fecha DESC
  usort($all, function($a,$b){
    $fa = isset($a->fecha) ? strtotime($a->fecha) : 0;
    $fb = isset($b->fecha) ? strtotime($b->fecha) : 0;
    return $fb <=> $fa;
  });

  if ($q !== '') {
    $needle = mb_strtolower($q, 'UTF-8');
    $all = array_values(array_filter($all, function($t) use ($needle){
      $name = isset($t->nombreTorneo) ? mb_strtolower($t->nombreTorneo, 'UTF-8') : '';
      return ($needle === '' || mb_strpos($name, $needle) !== false);
    }));
  }

  $totalCount = count($all);
  $page_size = $page_size_raw;
  if ($page_size === 0) $page_size = 25;
  if ((int)$page_size_raw === -1) $page_size = max(1, $totalCount);
  $effSize    = max(1, (int)$page_size);
  $totalPages = max(1, (int)ceil($totalCount / $effSize));
  $page       = min(max(1, (int)$page), $totalPages);
  $offset     = ($page - 1) * $effSize;
  $show_items = array_slice($all, $offset, $effSize);
  ?>

  <div class="ranking-top-bar">
    <div class="ranking-title-wrap column">
      <h2>Torneos disputados</h2>
      <div class="sub-count js-total-count"><?php echo number_format_i18n($totalCount); ?> torneos</div>
    </div>

    <div class="ranking-controls-right tournaments-controls-row">
      <form class="futbolin-search-form" onsubmit="return false;">
        <div class="search-wrapper">
          <input type="text" id="tournaments-filter"
                 class="futbolin-live-filter futbolin-live-search"
                 placeholder="Escribe para filtrar…" value="<?php echo esc_attr($q); ?>" autocomplete="off">
        </div>
      </form>

      <div class="page-size-form">
        <?php
          $curr = (int)$page_size_raw;
          foreach ([25=>'25', 50=>'50', 100=>'100', -1=>'Todos'] as $val => $label):
            $isActive = ((int)$curr === (int)$val) || ($val === -1 && (int)$page_size_raw === -1);
            $url = _futb_url_tournaments(['pageSize'=>$val]);
        ?>
          <a class="button<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo $url; ?>">
            <?php echo esc_html($label); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php if (empty($show_items)): ?>
    <div class="futbolin-card"><p>No se encontraron datos para mostrar.</p></div>
  <?php else: ?>
    
      <?php /* HECTOR-PATCH: TOP pagination clone */ ?>
      <div class="futbolin-paginacion" role="navigation" aria-label="Paginación torneos (top HECTOR)">
  <?php if ($page > 1): ?>
    <a class="button prev" href="<?php echo _futb_url_tournaments(['fpage'=>$page-1, 'pageSize'=>$page_size_raw]); ?>">← Anterior</a>
  <?php endif; ?>
  <span class="page-indicator">Página <?php echo (int)$page; ?> de <?php echo (int)$totalPages; ?></span>
  <?php if ($page < $totalPages): ?>
    <a class="button next" href="<?php echo _futb_url_tournaments(['fpage'=>$page+1, 'pageSize'=>$page_size_raw]); ?>">Siguiente →</a>
  <?php endif; ?>
</div>
  <div id="tournaments-list" class="futbolin-tournaments-list tournaments-list">
      <?php
        // Agrupar por año (usar $anio si viene de API; si no, derivar de fecha)
        $by_year = [];
        foreach ($show_items as $t) {
          if (!is_object($t)) continue;
          $fecha = isset($t->fecha) ? $t->fecha : '';
          $anio  = isset($t->temporada) && $t->temporada !== '' ? (string)$t->temporada : (
                    ($fecha ? date_i18n('Y', strtotime($fecha)) : ''));
          if ($anio === '') $anio = 'Sin año';
          if (!isset($by_year[$anio])) $by_year[$anio] = [];
          $by_year[$anio][] = $t;
        }
        // Ordenar secciones de año descendentemente (numérico si aplica, 'Sin año' al final)
        uksort($by_year, function($a,$b){
          if ($a === 'Sin año') return 1;
          if ($b === 'Sin año') return -1;
          return (int)$b <=> (int)$a;
        });

        foreach ($by_year as $year => $items_for_year):
      ?>
        <section class="t-year">
          <div class="futbolin-card" style="padding:12px 12px 10px;">
            <div class="t-year-banner">
              <div class="t-year-badge">Temporada <?php echo esc_html($year); ?></div>
              <div class="t-year-sub">(<?php echo number_format_i18n(count($items_for_year)); ?> torneos)</div>
            </div>
            <div class="tournaments-list">
      <?php foreach ($items_for_year as $t):
        if (!is_object($t)) continue;
        $tid   = isset($t->torneoId) ? (int)$t->torneoId : 0;
        $name  = isset($t->nombreTorneo) ? (string)$t->nombreTorneo : 'Torneo';
        $fecha = isset($t->fecha) ? $t->fecha : '';
        $anio  = isset($t->temporada) ? $t->temporada : ($fecha ? date_i18n('Y', strtotime($fecha)) : '');
        $loc   = isset($t->lugar) ? $t->lugar : (isset($t->localidad) ? $t->localidad : '');
        $when  = $fecha ? date_i18n('d/m/Y', strtotime($fecha)) : '';
        $view_url = _futb_build_url_view('tournament-stats', ['torneo_id'=>$tid]);
      ?>
        <div class="t-item tournament-list-item" data-name="<?php echo esc_attr(mb_strtolower($name, 'UTF-8')); ?>">
          <a href="<?php echo esc_url($view_url); ?>" class="t-link">
            <div class="t-name tournament-details">
              <h3>&zwj;<?php echo esc_html($name); ?></h3>
              <div class="tournament-info">
                <?php if ($anio !== ''): ?><span class="chip chip-year"><?php echo esc_html($anio); ?></span><?php endif; ?>
                <?php if ($when): ?><span class="chip chip-date"><?php echo esc_html($when); ?></span><?php endif; ?>
                <?php if ($loc):   ?><span class="chip chip-loc"><?php echo esc_html($loc); ?></span><?php endif; ?>
              </div>
            </div>
            <div class="t-date" aria-hidden="true">›</div>
          </a>
        </div>
      <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="futbolin-paginacion" role="navigation" aria-label="Paginación torneos">
  <?php if ($page > 1): ?>
    <a class="button prev" href="<?php echo _futb_url_tournaments(['fpage'=>$page-1, 'pageSize'=>$page_size_raw]); ?>">← Anterior</a>
  <?php endif; ?>
  <span class="page-indicator">Página <?php echo (int)$page; ?> de <?php echo (int)$totalPages; ?></span>
  <?php if ($page < $totalPages): ?>
    <a class="button next" href="<?php echo _futb_url_tournaments(['fpage'=>$page+1, 'pageSize'=>$page_size_raw]); ?>">Siguiente →</a>
  <?php endif; ?>
</div>



    <?php endif; ?>
  <?php endif; ?>

  <script>
  (function(){
    var input = document.getElementById('tournaments-filter');
    var list  = document.getElementById('tournaments-list');
    if (input && list) {
      function applyFilter(){
        var term = (input.value || '').trim().toLowerCase();
        list.querySelectorAll('.tournament-list-item').forEach(function(item){
          var hay = (item.getAttribute('data-name') || '').toLowerCase();
          item.style.display = (!term || hay.indexOf(term) !== -1) ? '' : 'none';
        });
      }
      input.addEventListener('input', applyFilter);
      input.addEventListener('change', applyFilter);
      if (input.value) applyFilter();
    }
  })();
  </script>

<?php else: /* ============== DETAIL ============== */ ?>

  <?php
  $entries = $data['entries'] ?? [];
  $back_url = _futb_url_tournaments([]);
  echo '<p style="margin:0 0 12px 0;"><a class="futbolin-back-button" href="' . esc_url($back_url) . '">← Volver a Torneos</a></p>';

  if (empty($entries)) {
      echo '<div class="futbolin-card"><p>No se encontraron datos para este campeonato.</p></div>';
  } else {
      $first = $entries[0];
      $torneo_titulo = isset($first->nombreTorneo) ? $first->nombreTorneo : 'Campeonato';
      echo '<h2>' . esc_html($torneo_titulo) . '</h2>';

      // 1) Agrupar por competicionId y calcular key/prio
      $groups = [];
      foreach ($entries as $row) {
          $cid   = isset($row->competicionId) ? (int)$row->competicionId : 0;
          $cname = isset($row->nombreCompeticion) ? $row->nombreCompeticion : 'Competición';

          $map = Futbolin_Normalizer::map_competicion($cname);
          if (!isset($groups[$cid])) {
              $groups[$cid] = [
                  'cid'    => $cid,
                  'nombre' => $cname,
                  'key'    => $map['key'],
                  'prio'   => $map['prio'],
                  'rows'   => []
              ];
          }
          $jug = isset($row->equipoJugadores) ? $row->equipoJugadores : '';
          $team_str = '';
          if (is_string($jug)) {
              $team_str = $jug;
          } elseif (is_array($jug)) {
              $norm = Futbolin_Normalizer::normalize_players($jug);
              $names = array_map(function($p){ return is_array($p) ? ($p['jugador'] ?? '') : (is_object($p) ? ($p->jugador ?? '') : ''); }, $norm);
              $team_str = implode(' / ', array_filter($names));
          }
          $pos = isset($row->posicion) ? (int)$row->posicion : 0;

          $groups[$cid]['rows'][] = (object)[
              'equipoJugadores' => $team_str,
              'posicion'        => $pos
          ];
      }

      // 2) Orden por prio, fallback nombre
      uasort($groups, function($a,$b){
          if ($a['prio'] === $b['prio']) return strnatcasecmp($a['nombre'], $b['nombre']);
          return $a['prio'] <=> $b['prio'];
      });

      // 3) Chips por key
      $first_anchor_by_key = [];
      foreach ($groups as $cid => $g) {
          if (!isset($first_anchor_by_key[$g['key']])) $first_anchor_by_key[$g['key']] = '#comp-' . $cid;
      }
      $chip_order = [];
      foreach ($first_anchor_by_key as $key => $href) {
          $min = 999;
          foreach ($groups as $g) if ($g['key'] === $key && $g['prio'] < $min) $min = $g['prio'];
          $chip_order[] = ['key'=>$key,'href'=>$href,'prio'=>$min];
      }
      usort($chip_order, function($a,$b){ return $a['prio'] <=> $b['prio']; });

    echo '<div class="comp-chips">';
      foreach ($chip_order as $c) {
          $label = '';
          foreach ($groups as $__g) { if ($__g['key'] === $c['key']) { $label = $__g['nombre']; break; } }
          if ($label === '') { $label = $c['key']; }
          echo '<a class="comp-chip" href="'.esc_attr($c['href']).'">'.esc_html($label).'</a>';
      }
      echo '</div>';
   // JS robusto para anclas: detecta contenedor de scroll, offset dinámico y fuerza scroll incluso si el tema intercepta clics
   echo '<script>(function(){'
  . 'function log(){}'
  . 'function cssEsc(s){ try{ return (window.CSS && CSS.escape) ? CSS.escape(s) : s.replace(/([ #.;?+*~\\:\\"\^\$\[\]\(\)=>|\/@])/g, "\\$1"); }catch(_){ return s; } }'
     . 'function getByIdDeep(id){ if(!id) return null; var seen=new Set(); var q=[document]; while(q.length){ var root=q.shift(); if(!root) continue; try{ var el=null; if(root.getElementById){ el=root.getElementById(id); if(el) return el; } if(root.querySelector){ el=root.querySelector("#"+cssEsc(id)); if(el) return el; var all=root.querySelectorAll("*"); for(var i=0;i<all.length;i++){ var n=all[i]; if(n && n.shadowRoot && !seen.has(n.shadowRoot)){ seen.add(n.shadowRoot); q.push(n.shadowRoot); } } } }catch(e){} } return null; }'
     . 'function computeOffset(){ var off=0; var ab=document.getElementById("wpadminbar"); if(ab && getComputedStyle(ab).position==="fixed"){ off += ab.offsetHeight||32; } var hdr=document.querySelector(".site-header, #masthead, header[role=\\"banner\\"]"); if(hdr && getComputedStyle(hdr).position==="fixed"){ off += hdr.offsetHeight||0; } log("[chips] offset=", off||72); return off||72; }'
     . 'function pickTargetEl(el){ if(!el) return null; var p=el.closest? (el.closest(".torneo-comp, .tournament-competicion-block")||el) : el; return p; }'
     . 'function getScrollContainer(start){ var el = start && start.parentElement; var docEl = document.scrollingElement || document.documentElement; var list = []; while(el){ list.push(el); el = el.parentElement; } list.push(docEl, document.body); for(var i=0;i<list.length;i++){ var c=list[i]; if(!c) continue; try{ var cs=getComputedStyle(c); if((/auto|scroll|overlay/).test(cs.overflowY) && c.scrollHeight > (c.clientHeight+2)) return c; }catch(e){} } return window; }'
  . 'function scrollToEl(el){ if(!el) return; var tgt=pickTargetEl(el); var sc=getScrollContainer(tgt); var OFFSET=computeOffset(); var baseTop; if(sc===window){ baseTop=(tgt.getBoundingClientRect().top + (window.pageYOffset||document.documentElement.scrollTop)); } else { var scRect=sc.getBoundingClientRect(); baseTop=(tgt.getBoundingClientRect().top - scRect.top + sc.scrollTop); } var startPos = sc===window ? (window.pageYOffset||document.documentElement.scrollTop) : sc.scrollTop; var top=Math.max(0, baseTop - OFFSET); if(sc===window){ window.scrollTo({top:top, behavior:"smooth"}); setTimeout(function(){ var delta=tgt.getBoundingClientRect().top - OFFSET; if(Math.abs(delta)>2){ window.scrollBy(0, delta); } }, 300); setTimeout(function(){ var endPos=(window.pageYOffset||document.documentElement.scrollTop); if(Math.abs(endPos - startPos) < 2){ try{ tgt.scrollIntoView({block:"start", inline:"nearest"}); }catch(_) { tgt.scrollIntoView(true); } setTimeout(function(){ window.scrollBy(0, -OFFSET); }, 0); } }, 600); } else { if(sc.scrollTo){ sc.scrollTo({top:top, behavior:"smooth"}); setTimeout(function(){ var scRect2=sc.getBoundingClientRect(); var delta=tgt.getBoundingClientRect().top - scRect2.top - OFFSET; if(Math.abs(delta)>2){ sc.scrollTop += delta; } }, 300); setTimeout(function(){ var endPos=sc.scrollTop; if(Math.abs(endPos - startPos) < 2){ try{ tgt.scrollIntoView({block:"start", inline:"nearest"}); }catch(_) { tgt.scrollIntoView(true); } setTimeout(function(){ sc.scrollTop = Math.max(0, sc.scrollTop - OFFSET); }, 0); } }, 600); } else { sc.scrollTop = top; } } }'
  . 'function scrollToHash(target){ if(!target) return; var raw=(target||"").trim(); var id=raw.replace(/^#/,"").trim(); if(!id){ return; } var el=getByIdDeep(id); if(el){ scrollToEl(el); return; } var tries=0; (function tick(){ tries++; var el2=getByIdDeep(id); if(el2){ scrollToEl(el2); return; } if(tries<60){ setTimeout(tick, 50); } })(); }'
  . 'function onChipClick(e){ var a=e.target && e.target.closest? e.target.closest(".comp-chips a.comp-chip") : null; if(!a) return; var h=(a.getAttribute("href")||"").trim(); if(!h || h.charAt(0) !== "#"){ return; } e.preventDefault(); try{ location.hash = h; }catch(_e){ window.location.hash = h; } requestAnimationFrame(function(){ scrollToHash(h); }); setTimeout(function(){ scrollToHash(h); }, 120); }'
  . 'var chips=document.querySelectorAll(".comp-chips a.comp-chip"); chips.forEach(function(a){ a.addEventListener("click", onChipClick, true); }); document.addEventListener("click", onChipClick, true);'
  . 'window.addEventListener("hashchange", function(){ scrollToHash(location.hash); });'
  . 'function ensureInitial(){ if(location.hash){ var tries=0; (function tick(){ tries++; var id=location.hash.slice(1); var el=getByIdDeep(id); if(el){ scrollToEl(el); return; } if(tries<40){ setTimeout(tick, 50);} })(); }}'
     . 'if(document.readyState==="loading"){ document.addEventListener("DOMContentLoaded", ensureInitial); } else { ensureInitial(); }'
     . '})();</script>';
      ?>

      <div class="tournament-controls" style="margin-top:12px;">
        <div class="search-wrapper">
          <input type="text" id="tournament-player-filter" class="futbolin-live-search" placeholder="Filtrar por nombre de jugador…">
        </div>
      </div>

      <?php
      echo '<div id="tournament-detail">';
    foreach ($groups as $cid => $group) {
      echo '<section class="torneo-comp" id="comp-' . esc_attr($cid) . '">';
          echo '  <h3 class="comp-title">' . esc_html($group['nombre']) . '</h3>';
          echo '  <div class="finals-table-container">';
          echo '    <div class="ranking-header" role="row">';
          echo '      <div class="ranking-th">Pareja / Jugador</div>';
          echo '      <div class="ranking-th">Pos.</div>';
          echo '    </div>';
          echo '    <div class="ranking-table-content">';
          $i=0;
          foreach ($group['rows'] as $r) {
              $row_cls = ($i%2===0)?'even':'odd';
              $pos     = (int)$r->posicion;
              $pos_html = ($pos > 0) ? (string)$pos : '—';
              if ($pos >= 1 && $pos <= 3) {
                  $cls = ($pos === 1) ? 'pos-1' : (($pos === 2) ? 'pos-2' : 'pos-3');
                  $pos_html = '<span class="badge '.$cls.'">'.$pos.'</span>';
              }
              echo '  <div class="ranking-row ' . esc_attr($row_cls) . '" data-jugs="' . esc_attr(mb_strtolower((string)($r->equipoJugadores ?? ''), 'UTF-8')) . '">';
              echo '    <div class="ranking-cell ranking-player-name-cell jug">' . esc_html($r->equipoJugadores ?? '') . '</div>';
              echo '    <div class="ranking-cell pos-cell">'.$pos_html.'</div>';
              echo '  </div>';
              $i++;
          }
          echo '    </div>';
          echo '  </div>';
          echo '</section>';
      }
      echo '</div>';
  }
  ?>

  <script>
  (function(){
    var input = document.getElementById('tournament-player-filter');
    var container = document.getElementById('tournament-detail');
    if (!input || !container) return;

    function applyFilter() {
      var term = input.value.trim().toLowerCase();
      container.querySelectorAll('.torneo-comp').forEach(function(sec){
        var any = false;
        sec.querySelectorAll('.ranking-row').forEach(function(row){
          var hay = row.getAttribute('data-jugs') || '';
          var show = !term || hay.indexOf(term) !== -1;
          row.style.display = show ? '' : 'none';
          if (show) any = true;
        });
        sec.style.display = any ? '' : 'none';
      });
    }
    input.addEventListener('input', applyFilter);
    input.addEventListener('change', applyFilter);
  })();
  </script>



<?php endif; ?>

</div>
