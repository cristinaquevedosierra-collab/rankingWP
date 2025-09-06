<?php
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
$page = isset($page) ? (int)$page : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
?>
<?php
/**
 * Archivo: includes/template-parts/hall-of-fame-display.php
 * Descripción: Tabla interactiva del Hall of Fame (filtro, orden y paginación en cliente).
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/* ===== CSS / JS ===== */
if ( ! wp_style_is('futbolin-hof', 'enqueued') ) {
  $css_url = plugins_url('assets/css/23-hall-of-fame-styles.css', dirname(__DIR__, 2) . '/ranking-futbolin.php');
  // (centralizado) wp_enqueue_style('futbolin-hof', $css_url, [], '1.0');
}
if ( ! wp_script_is('futbolin-hof-search', 'enqueued') ) {
  $js_search = plugins_url('assets/js/hall-of-fame-search.js', dirname(__DIR__, 2) . '/ranking-futbolin.php');
  // (centralizado) wp_enqueue_script('futbolin-hof-search', $js_search, ['jquery'], '1.0', true);
}
if ( ! wp_script_is('futbolin-hof-pager', 'enqueued') ) {
  $js_pager = plugins_url('assets/js/hall-of-fame-pager.js', dirname(__DIR__, 2) . '/ranking-futbolin.php');
  // (centralizado) wp_enqueue_script('futbolin-hof-pager', $js_pager, ['jquery'], '1.0', true);
}

/* ===== Parámetros / datos ===== */
$profile_page_url = isset($profile_page_url) ? (string)$profile_page_url : '';
$busqueda = isset($busqueda) ? (string)$busqueda : sanitize_text_field( isset($_GET['jugador_busqueda']) ? wp_unslash($_GET['jugador_busqueda']) : '' );

// tamaño por página (0 = todos)
if (isset($page_size)) {
  $page_size = (int)$page_size;
} else {
  $page_size = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : (isset($_GET['page_size']) ? (int)$_GET['page_size'] : 25);
}
if ($page_size < 0) $page_size = 25; // normaliza

// Normaliza $rankingData
if (!isset($rankingData) && isset($hall_of_fame_data)) $rankingData = $hall_of_fame_data;

// Items prerender
$items = [];
if (isset($rankingData)) {
  if (is_object($rankingData) && isset($rankingData->items)) {
    $items = is_array($rankingData->items) ? $rankingData->items : [];
  } elseif (is_array($rankingData)) {
    $items = $rankingData;
  }
}

// Dataset completo para JS (allItems si existe; sino items)
$__hof_all_items = (isset($rankingData->allItems) && is_array($rankingData->allItems))
  ? array_values($rankingData->allItems)
  : array_values($items);

// Back URL opcional (por si se usa standalone)
$options = get_option('mi_plugin_futbolin_options', []);
$ranking_page_id = isset($options['ranking_page_id']) ? (int)$options['ranking_page_id'] : 0;
$back_url = '';
if ($ranking_page_id > 0) {
  $ranking_permalink = get_permalink($ranking_page_id);
  $back_url = esc_url(add_query_arg(['view'=>'ranking'], $ranking_permalink));
} else {
  $back_url = function_exists('_futb_url_ranking') ? _futb_url_ranking([]) : esc_url(add_query_arg(['view'=>'ranking']));
}
?>

<div class="futbolin-hall-of-fame-wrapper">

  <h2>Hall of Fame</h2>
  <p class="hall-disclaimer">
    (La posición es estática y no cambia al filtrar u ordenar. Orden inicial por % Partidos Ganados.)<br>
    Para aparecer en la clasificación, es necesario haber ganado un mínimo de 100 partidas. Solo se contabilizan las victorias en las siguientes modalidades: Open Dobles, Open Individual, España Dobles, España Individual, Mixto y Pro Dobles.
  </p>

  <div class="ranking-top-bar">
    <form class="futbolin-search-form" onsubmit="return false;">
      <div class="search-wrapper">
        <input type="text" name="jugador_busqueda" class="futbolin-live-search"
               placeholder="Escribe para filtrar…" value="<?php echo esc_attr($busqueda); ?>" autocomplete="off">
      </div>
    </form>

    <div class="page-size-form">
      <?php
        $sizes = [25 => '25', 50 => '50', 100 => '100', 'all' => 'Todos'];
        foreach ($sizes as $val => $label):
          $isActive = ((string)$page_size === (string)$val) || ($val === 'all' && (int)$page_size === 0);
      ?>
        <button type="button" data-size="<?php echo esc_attr($val); ?>" class="<?php echo $isActive ? 'active psize-btn' : 'psize-btn'; ?>">
          <?php echo esc_html($label); ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="hall-of-fame-table-container">
    <!-- Cabecera -->
    <div class="ranking-header">
      <div class="ranking-th"><span class="sortable-header" data-sort="posicion_estatica" data-default="desc">Posición <span class="sort-arrow"></span></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="nombre" data-default="asc">Jugador <span class="sort-arrow"></span></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="partidas_jugadas" data-default="desc">Partidas <span class="sort-arrow"></span></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="partidas_ganadas" data-default="desc">Ganadas <span class="sort-arrow"></span></span></div>
      <div class="ranking-th"><span class="sortable-header active desc" data-sort="win_rate_partidos" data-default="desc">% Ganados <span class="sort-arrow">▼</span></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="competiciones_jugadas" data-default="desc">Comp. jugadas <span class="sort-arrow"></span></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="competiciones_ganadas" data-default="desc">Comp. ganadas <span class="sort-arrow"></span></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="win_rate_competiciones" data-default="desc">% Comp. <span class="sort-arrow"></span></span></div>
    </div>

    <?php
      // ¡Clave! El JS lee este script JSON:
      echo '<script type="application/json" id="hof-data-all-json">'
     . wp_json_encode($__hof_all_items, JSON_UNESCAPED_UNICODE)
     . '</script>';
    ?>

    <!-- Contenido -->
    <div class="ranking-table-content" data-profile-url="<?php echo esc_attr($profile_page_url); ?>">
      <!-- Filas prerenderizadas (fallback SEO / primera carga) -->
            <div class="ranking-rows" id="hof-rows-prerender"></div>
<!-- Contenedor dinámico (JS sustituye aquí el contenido) -->
      <div class="ranking-rows" id="hof-rows" style="display:none;"></div>
    </div>
  </div>

  <!-- Paginación (controlada por JS) -->
  <div class="futbolin-paginacion" id="hof-pager" style="display:none;">
    <a href="#" class="button prev" id="hof-btn-prev">← Anterior</a>
    <span class="page-indicator">
      Página <span id="hof-page-now">1</span> de <span id="hof-page-total">1</span>
    </span>
    <a href="#" class="button next" id="hof-btn-next">Siguiente →</a>
  </div>
</div>