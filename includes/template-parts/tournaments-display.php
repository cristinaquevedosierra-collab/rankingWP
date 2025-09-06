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
      <div id="tournaments-list" class="futbolin-tournaments-list">
      <?php foreach ($show_items as $t):
        if (!is_object($t)) continue;
        $tid   = isset($t->torneoId) ? (int)$t->torneoId : 0;
        $name  = isset($t->nombreTorneo) ? (string)$t->nombreTorneo : 'Torneo';
        $fecha = isset($t->fecha) ? $t->fecha : '';
        $anio  = isset($t->temporada) ? $t->temporada : '';
        $loc   = isset($t->lugar) ? $t->lugar : (isset($t->localidad) ? $t->localidad : '');
        $when  = $fecha ? date_i18n('d/m/Y', strtotime($fecha)) : '';
        $view_url = _futb_build_url_view('tournament-stats', ['torneo_id'=>$tid]);
      ?>
        <div class="tournament-list-item" data-name="<?php echo esc_attr(mb_strtolower($name, 'UTF-8')); ?>">
          <a href="<?php echo esc_url($view_url); ?>">
            <div class="tournament-details">
              <h3><?php echo esc_html($name); ?></h3>
              <div class="tournament-info">
                <?php if ($anio !== ''): ?><span><?php echo esc_html($anio); ?></span><?php endif; ?>
                <?php if ($when): ?> | <span><?php echo esc_html($when); ?></span><?php endif; ?>
                <?php if ($loc):   ?> | <span><?php echo esc_html($loc); ?></span><?php endif; ?>
              </div>
            </div>
          </a>
        </div>
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
      ?>

      <div class="tournament-controls" style="margin-top:12px;">
        <div class="search-wrapper">
          <input type="text" id="tournament-player-filter" class="futbolin-live-search" placeholder="Filtrar por nombre de jugador…">
        </div>
      </div>

      <?php
      echo '<div id="tournament-detail">';
      foreach ($groups as $cid => $group) {
          echo '<section id="comp-' . esc_attr($cid) . '" class="torneo-comp">';
          echo '  <h3 class="comp-title">' . esc_html($group['nombre']) . '</h3>';
          echo '  <div class="finals-table-container">';
          echo '    <div class="ranking-header" role="row">';
          echo '      <div class="ranking-th">Pareja / Equipo</div>';
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
