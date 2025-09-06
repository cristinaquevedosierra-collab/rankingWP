<?php
/**
 * Archivo: ranking-display.php
 * Ruta: includes/template-parts/ranking-display.php
 *
 * - TORNEOS (tournaments):
 *   * Top-bar: Título + contador; a la derecha botones 25/50/100/Todos
 *   * Paginación con ?view=tournaments&page=N (mantiene pageSize)
 *
 * - RANKING:
 *   * Título por modalidad (1=Individual, 2=Dobles)
 *   * Columna categoría solo en Individual/Dobles
 *   * Contador de jugadores
 *   * Botones 25/50/100/Todos (reinician fpage=1)
 *   * Paginación con fpage
 */

if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/* ===== Helpers mínimos (con guardas anti-duplicado) ===== */

/* ===== Parámetros comunes ===== */
$current_view     = isset($current_view) ? $current_view : sanitize_key($_GET['view'] ?? 'ranking');
$page_size        = isset($page_size) ? (int)$page_size : (int)($_GET['pageSize'] ?? 25);
$profile_page_url = isset($profile_page_url) ? $profile_page_url : '';

/* ===== Errores globales ===== */
if (!empty($ranking_error)): ?>
  <div class="futbolin-card"><div class="futbolin-inline-notice"><?php echo esc_html($ranking_error); ?></div></div>
<?php
/* ========================================================================
 * VISTA: TORNEOS
 * ======================================================================*/
elseif ($current_view === 'tournaments'):
  // Esperamos datos del controlador en $tournament_data (paginado de API) o items directos
  $container    = _futb_extract_container($tournament_data ?? null, 'torneos');
  $items        = (is_object($container) && isset($container->items) && is_array($container->items)) ? $container->items : [];
  $totalCount   = (int)($container->totalCount ?? count($items));
  $apiPageIndex = (int)($container->pageIndex ?? (int)($_GET['page'] ?? 1)); // la API suele usar "page"
  $apiPageSize  = (int)($container->pageSize  ?? $page_size);
  if ($apiPageSize <= 0) $apiPageSize = 25;
  $totalPages   = isset($container->totalPages) ? (int)$container->totalPages : max(1, (int)ceil($totalCount / $apiPageSize));
?>
  <div class="futbolin-card">
    <div class="ranking-top-bar">
      <div class="ranking-title-wrap column">
        <h2>Campeonatos disputados</h2>
        <div class="sub-count js-total-count"><?php echo number_format_i18n($totalCount); ?> campeonatos</div>
      </div>
      <div class="ranking-controls-right tournaments-controls-row">
        <div class="page-size-form">
          <?php
            $curr = ($page_size === -1) ? -1 : max(1, (int)$page_size);
            foreach ([25=>'25', 50=>'50', 100=>'100', -1=>'Todos'] as $val => $label):
              $isActive = ((int)$curr === (int)$val);
              // al cambiar tamaño, volvemos a pág 1
              $url = _futb_build_url_view('tournaments', ['pageSize'=>$val, 'page'=>1]);
          ?>
            <a class="button<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo $url; ?>"><?php echo esc_html($label); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php if (empty($items)): ?>
      <p>No se encontraron torneos para mostrar.</p>
    <?php else: ?>
      <div class="futbolin-tournaments-list">
        <?php foreach ($items as $torneo):
          if (!is_object($torneo)) continue;
          $fecha = isset($torneo->fecha) ? date_i18n('d/m/Y', strtotime($torneo->fecha)) : '';
          $anio  = isset($torneo->temporada) ? $torneo->temporada : '';
          $loc   = isset($torneo->lugar) ? $torneo->lugar : (isset($torneo->localidad) ? $torneo->localidad : '');
          $ver_url = esc_url(add_query_arg(['view'=>'tournament-stats','torneo_id'=>$torneo->torneoId]));
        ?>
          <div class="tournament-list-item">
            <a href="<?php echo $ver_url; ?>">
              <div class="tournament-details">
                <h3><?php echo esc_html($torneo->nombreTorneo ?? ''); ?></h3>
                <div class="tournament-info">
                  <?php if ($anio !== ''): ?><span><?php echo esc_html($anio); ?></span><?php endif; ?>
                  <?php if ($fecha): ?> | <span><?php echo esc_html($fecha); ?></span><?php endif; ?>
                  <?php if ($loc):   ?> | <span><?php echo esc_html($loc); ?></span><?php endif; ?>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
  <div class="futbolin-paginacion" role="navigation" aria-label="Paginación torneos">
    <?php
      // Conservar el pageSize actual si existe
      $keep = [];
      if (isset($_GET['pageSize']) && $_GET['pageSize'] !== '') {
        // No normalizamos aquí: respetamos lo que llega (25/50/100/-1)
        $keep['pageSize'] = sanitize_text_field(wp_unslash($_GET['pageSize']));
      }
    ?>

    <?php if ($apiPageIndex > 1): ?>
      <a class="button prev"
         href="<?php echo _futb_build_url_view('tournaments', array_merge($keep, ['page' => $apiPageIndex - 1])); ?>">
         ← Anterior
      </a>
    <?php endif; ?>

    <span class="page-indicator">
      Página <?php echo (int) $apiPageIndex; ?> de <?php echo (int) $totalPages; ?>
    </span>

    <?php if ($apiPageIndex < $totalPages): ?>
      <a class="button next"
         href="<?php echo _futb_build_url_view('tournaments', array_merge($keep, ['page' => $apiPageIndex + 1])); ?>">
         Siguiente →
      </a>
    <?php endif; ?>
  </div>
<?php endif; ?>
  <?php endif;?>
  </div>

<?php
/* ========================================================================
 * VISTA: RANKING
 * ======================================================================*/
else:
  // 1) Modalidad
  $mod_id        = isset($modalidad_id) ? (int)$modalidad_id : (int)($_GET['modalidad_id'] ?? $_GET['modalidad'] ?? 0);
  $show_category = in_array($mod_id, [1,2], true); // Solo Individual (1) y Dobles (2)
  $base_type     = ($mod_id === 1) ? 'individual' : (($mod_id === 2) ? 'dobles' : null);

  // 2) Datos: preferimos $ranking_full (no paginado API) si está
  $full = (isset($ranking_full) && is_object($ranking_full) && !empty($ranking_full->items) && is_array($ranking_full->items)) ? $ranking_full : null;

  if ($full) {
    $all_items        = $full->items;
    $totalCount       = (int)($full->totalCount ?? count($all_items));
    $titulo_modalidad = isset($full->modalidad) ? (string)$full->modalidad : '';
    $page             = max(1, (int)($_GET['fpage'] ?? 1));
    $effective        = ($page_size === -1) ? $totalCount : max(1, (int)$page_size);
    $totalPages       = ($page_size === -1) ? 1 : max(1, (int)ceil($totalCount / $effective));
    $page             = min($page, $totalPages);
    $offset           = ($page - 1) * $effective;
    $page_items       = array_slice($all_items, $offset, $effective);
    $base_pos         = $offset + 1;
  } else {
    $container        = _futb_extract_container($ranking_data ?? null, 'ranking');
    $page_items       = (is_object($container) && isset($container->items) && is_array($container->items)) ? $container->items : [];
    $totalCount       = (int)($container->totalCount ?? count($page_items));
    $titulo_modalidad = isset($container->modalidad) ? (string)$container->modalidad : '';
    $apiPageIndex     = (int)($container->pageIndex ?? (int)($_GET['page'] ?? 1));
    $apiPageSize      = (int)($container->pageSize  ?? (int)($_GET['pageSize'] ?? 25));
    if ($apiPageSize <= 0) $apiPageSize = 25;
    $totalPages       = isset($container->totalPages) ? (int)$container->totalPages : max(1, (int)ceil($totalCount / $apiPageSize));
    $base_pos         = (($apiPageIndex - 1) * $apiPageSize) + 1;
    $page             = max(1, (int)($_GET['fpage'] ?? $apiPageIndex)); // seguimos usando fpage en UI
  }

  // 3) Título
  $title_label = 'Ranking';
  if     ($base_type === 'individual') { $title_label .= ' Individual'; }
  elseif ($base_type === 'dobles')     { $title_label .= ' Dobles'; }
  elseif (!empty($titulo_modalidad))   { $title_label .= ' ' . $titulo_modalidad; }

  // 4) Clase extra para ocultar columna categoría si no aplica
  $card_extra_class = $show_category ? '' : ' no-category';
?>
  <div class="futbolin-card<?php echo $card_extra_class; ?>">
    <?php if (!empty($page_items)): ?>
      <div class="ranking-top-bar">
        <div class="ranking-title-wrap column">
          <h2><?php echo esc_html($title_label); ?></h2>
          <?php if ($base_type): ?>
            <span class="ranking-type-badge"><?php echo esc_html(ucfirst($base_type)); ?></span>
          <?php endif; ?>
          <div class="sub-count js-total-count"><?php echo number_format_i18n($totalCount); ?> jugadores</div>
        </div>

        <div class="ranking-controls-right">
          <form class="futbolin-search-form" onsubmit="return false;">
            <div class="search-wrapper">
              <input type="text" class="futbolin-live-filter" placeholder="Escribe para filtrar…" autocomplete="off">
            </div>
          </form>
          <div class="page-size-form">
            <?php
              $curr = ($page_size === -1) ? -1 : max(1, (int)$page_size);
              foreach ([25=>'25', 50=>'50', 100=>'100', -1=>'Todos'] as $val => $label):
                $isActive = ((int)$curr === (int)$val);
                // al cambiar tamaño en ranking: resetea fpage=1 y preserva modalidad
                $url = _futb_build_url_view('ranking', ['pageSize'=>$val, 'fpage'=>1, 'modalidad'=>$mod_id ?: null]);
            ?>
              <a class="button<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo $url; ?>"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Cabecera -->
      
      <?php /* HECTOR-PATCH: TOP pagination clone */ ?>
      <div class="futbolin-paginacion" role="navigation" aria-label="Paginación ranking (top HECTOR)">
          <?php if ($page > 1): ?>
            <a class="button prev" href="<?php echo _futb_build_url_view('ranking', ['fpage'=>$page-1, 'modalidad'=>$mod_id ?: null, 'pageSize'=>$page_size]); ?>">← Anterior</a>
          <?php endif; ?>
          <span class="page-indicator">Página <?php echo (int)$page; ?> de <?php echo (int)$totalPages; ?></span>
          <?php if ($page < $totalPages): ?>
            <a class="button next" href="<?php echo _futb_build_url_view('ranking', ['fpage'=>$page+1, 'modalidad'=>$mod_id ?: null, 'pageSize'=>$page_size]); ?>">Siguiente →</a>
          <?php endif; ?>
        </div>
      <div class="ranking-header">
        <div class="ranking-th">Posición</div>
        <div class="ranking-th">Jugador</div>
        <?php if ($show_category): ?><div class="ranking-th">Categoría</div><?php endif; ?>
        <div class="ranking-th">Puntos</div>
      </div>

      <div class="futbolin-ranking">
        <div class="ranking-rows" id="ranking-rows">
          <?php foreach ($page_items as $idx => $jugador):
            if (!is_object($jugador)) continue;
            $pos       = $base_pos + $idx;
            $nombre    = $jugador->nombreJugador ?? '';
            $categoria = $jugador->categoria ?? '';
            $cat_slug  = _futb_slugify($categoria);
            $pointsRaw = $jugador->puntos ?? ($jugador->puntuacion ?? 0);
            $pointsNum = is_numeric($pointsRaw) ? (int)$pointsRaw : 0;
            $pointsFmt = number_format_i18n($pointsNum);
            $pos_class = 'ranking-position' . ($pos <= 3 ? ' pos-' . $pos : '');
          ?>
          <div class="ranking-row"
               data-player="<?php echo esc_attr(mb_strtolower($nombre, 'UTF-8')); ?>"
               data-category="<?php echo esc_attr($cat_slug); ?>">
            <div class="<?php echo esc_attr($pos_class); ?>"><?php echo (int)$pos; ?></div>
            <div class="ranking-player-details">
              <?php
                // 👉 Enlace por ID, usando helper centralizado
                echo _futb_link_player($jugador, $profile_page_url);
              ?>
            </div>
            <?php if ($show_category): ?>
            <div class="ranking-player-category">
              <?php if ($categoria !== ''): ?>
                <span class="category-pill category-<?php echo esc_attr($cat_slug); ?>" data-category="<?php echo esc_attr($cat_slug); ?>">
                  <?php echo esc_html($categoria); ?>
                </span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="ranking-points">
              <div class="points-pill"><span class="points-value"><?php echo esc_html($pointsFmt); ?></span></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($totalPages > 1): ?>
        <div class="futbolin-paginacion" role="navigation" aria-label="Paginación ranking">
          <?php if ($page > 1): ?>
            <a class="button prev" href="<?php echo _futb_build_url_view('ranking', ['fpage'=>$page-1, 'modalidad'=>$mod_id ?: null, 'pageSize'=>$page_size]); ?>">← Anterior</a>
          <?php endif; ?>
          <span class="page-indicator">Página <?php echo (int)$page; ?> de <?php echo (int)$totalPages; ?></span>
          <?php if ($page < $totalPages): ?>
            <a class="button next" href="<?php echo _futb_build_url_view('ranking', ['fpage'=>$page+1, 'modalidad'=>$mod_id ?: null, 'pageSize'=>$page_size]); ?>">Siguiente →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <p class="ranking-disclaimer">No aparecerás en este ranking si llevas más de dos años sin competir.</p>

    <?php else: ?>
      <p>No se encontraron datos para mostrar.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>