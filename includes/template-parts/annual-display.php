<?php
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Espera variables:
// - $annual_groups: array de grupos [{ mod_id, mod_label, ranking_full }]
// - $last_season: int temporada usada
// - $profile_page_url: url base del perfil jugador (opcional)

$groups = isset($annual_groups) && is_array($annual_groups) ? $annual_groups : [];
$last_season = isset($last_season) ? (int)$last_season : 0;

?>

<div class="futbolin-card">
  <div class="ranking-top-bar">
    <div class="ranking-title-wrap column">
      <h2>Ranking anual</h2>
      <?php if ($last_season > 0): ?>
        <div class="sub-count js-total-count">Temporada <?php echo (int)$last_season; ?></div>
      <?php endif; ?>
    </div>
  </div>
  <p class="ranking-disclaimer">Este ranking anual muestra todas las modalidades con datos en la última temporada disponible.</p>
</div>

<?php if (empty($groups)): ?>
  <div class="futbolin-card"><p>No hay datos de ranking anual para mostrar.</p></div>
<?php else: ?>
  <?php foreach ($groups as $group):
    if (!is_array($group)) continue;
    $mod_id    = isset($group['mod_id']) ? (int)$group['mod_id'] : 0;
    $mod_label = isset($group['mod_label']) ? (string)$group['mod_label'] : '';
    $full      = isset($group['ranking_full']) && is_object($group['ranking_full']) ? $group['ranking_full'] : null;
    if (!$full || !isset($full->items) || !is_array($full->items) || count($full->items) === 0) continue;
    $items      = $full->items;
    $totalCount = (int)($full->totalCount ?? count($items));
  ?>
  <?php
    // Parámetros de paginación por modalidad
    $qs_page_key = 'fpage_' . $mod_id;
    $qs_size_key = 'pageSize_' . $mod_id;
    $page_size   = isset($_GET[$qs_size_key]) ? (int)$_GET[$qs_size_key] : 25;
    $page_size   = ($page_size === -1) ? -1 : max(1, $page_size);
    if ($page_size === -1) { $totalPages = 1; $page = 1; $page_items = $items; $base_pos = 1; }
    else {
      $totalPages = max(1, (int)ceil($totalCount / $page_size));
      $page       = isset($_GET[$qs_page_key]) ? max(1, (int)$_GET[$qs_page_key]) : 1;
      $page       = min($page, $totalPages);
      $offset     = ($page - 1) * $page_size;
      $page_items = array_slice($items, $offset, $page_size);
      $base_pos   = $offset + 1;
    }
  ?>
  <div class="futbolin-card no-category">
    <div class="ranking-top-bar">
      <div class="ranking-title-wrap column">
        <h3>Ranking anual <?php echo esc_html($mod_label); ?></h3>
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
              $url = _futb_build_url_view('annual', [$qs_size_key=>$val, $qs_page_key=>1]);
          ?>
            <a class="button<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo $url; ?>"><?php echo esc_html($label); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="ranking-header">
      <div class="ranking-th">Posición</div>
      <div class="ranking-th">Jugador</div>
      <div class="ranking-th">Puntos</div>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="futbolin-paginacion" role="navigation" aria-label="Paginación ranking anual (top)">
        <?php if ($page > 1): ?>
          <a class="button prev" href="<?php echo _futb_build_url_view('annual', [$qs_page_key=>$page-1, $qs_size_key=>$page_size]); ?>">← Anterior</a>
        <?php endif; ?>
        <span class="page-indicator">Página <?php echo (int)$page; ?> de <?php echo (int)$totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
          <a class="button next" href="<?php echo _futb_build_url_view('annual', [$qs_page_key=>$page+1, $qs_size_key=>$page_size]); ?>">Siguiente →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="futbolin-ranking">
      <div class="ranking-rows" id="ranking-rows-mod-<?php echo (int)$mod_id; ?>">
        <?php foreach ($page_items as $idx => $jugador):
          if (!is_object($jugador)) continue;
          $pos       = $base_pos + $idx;
          $nombre    = $jugador->nombreJugador ?? '';
          $pointsRaw = $jugador->puntos ?? ($jugador->puntuacion ?? 0);
          $pointsNum = is_numeric($pointsRaw) ? (int)$pointsRaw : 0;
          $pointsFmt = number_format_i18n($pointsNum);
          $pos_class = 'ranking-position' . ($pos <= 3 ? ' pos-' . $pos : '');
        ?>
        <div class="ranking-row" data-player="<?php echo esc_attr(mb_strtolower($nombre, 'UTF-8')); ?>">
          <div class="<?php echo esc_attr($pos_class); ?>"><?php echo (int)$pos; ?></div>
          <div class="ranking-player-details">
            <?php echo _futb_link_player($jugador, $profile_page_url ?? ''); ?>
          </div>
          <div class="ranking-points">
            <div class="points-pill"><span class="points-value"><?php echo esc_html($pointsFmt); ?></span></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($totalPages > 1): ?>
      <div class="futbolin-paginacion" role="navigation" aria-label="Paginación ranking anual (bottom)">
        <?php if ($page > 1): ?>
          <a class="button prev" href="<?php echo _futb_build_url_view('annual', [$qs_page_key=>$page-1, $qs_size_key=>$page_size]); ?>">← Anterior</a>
        <?php endif; ?>
        <span class="page-indicator">Página <?php echo (int)$page; ?> de <?php echo (int)$totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
          <a class="button next" href="<?php echo _futb_build_url_view('annual', [$qs_page_key=>$page+1, $qs_size_key=>$page_size]); ?>">Siguiente →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
