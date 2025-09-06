<?php
if (!isset($player) && isset($jugador)) { $player = $jugador; }
$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
?>
<?php
/**
 * Archivo: includes/template-parts/player-profile-wrapper.php
 * Descripción: Plantilla principal del perfil de jugador, con visibilidad modular según opciones del admin.
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/* ========= Normalizaciones y fallbacks ========= */

// Processor y datos base (compat: basic_data | player_data)
$processor = (isset($processor) && is_object($processor)) ? $processor : null;

$player_details = null;
if ($processor) {
  if (isset($processor->basic_data) && is_object($processor->basic_data)) {
    $player_details = $processor->basic_data;
  } elseif (isset($processor->player_data) && is_object($processor->player_data)) {
    $player_details = $processor->player_data;
  }
}
if (!$player_details) { $player_details = (object)[]; }

// Nombre del jugador
$nombre_jugador = '';
if ($processor && method_exists($processor, 'get_player_name')) {
  $nombre_jugador = (string)$processor->get_player_name();
} elseif (isset($player_details->nombreJugador)) {
  $nombre_jugador = (string)$player_details->nombreJugador;
} else {
  $nombre_jugador = 'Jugador';
}

// Visibilidad modular (desde shortcode/admin)
$visual = (isset($player_visual) && is_array($player_visual)) ? $player_visual : [
  'summary' => true,
  'stats'   => true,
  'history' => true,
  'h2h'     => true,
  'glicko'  => false, // por defecto off para evitar mensaje si no hay API
];

// Categorías y labels (si el shortcode las definió)
$categoria_dobles         = isset($categoria_dobles) ? (string)$categoria_dobles : '';
$categoria_individual     = isset($categoria_individual) ? (string)$categoria_individual : '';
$categoria_dobles_display = isset($categoria_dobles_display) ? (string)$categoria_dobles_display : '';

// Clase de cabecera por categoría
$header_cat_class = $categoria_dobles !== '' ? sanitize_html_class($categoria_dobles) : 'nc';

// URL “Volver”
if (empty($ranking_page_url)) {
  $opts = get_option('mi_plugin_futbolin_options', []);
  if (!empty($opts['ranking_page_id'])) {
    $ranking_permalink = get_permalink((int)$opts['ranking_page_id']);
    $ranking_page_url  = esc_url(add_query_arg(['view' => 'ranking'], $ranking_permalink));
  } elseif (function_exists('_futb_url_ranking')) {
    $ranking_page_url = _futb_url_ranking([]);
  } else {
    $ranking_page_url = esc_url(add_query_arg(['view' => 'ranking']));
  }
}

/* ========= Tab activa (primera visible) ========= */
$__tab_map = [
  'glicko'  => 'tab-glicko-rankings',
  'summary' => 'tab-general',
  'stats'   => 'tab-estadisticas',
  'history' => 'tab-historial',
  'h2h'     => 'tab-h2h',
];
$__active_tab = '';
foreach ($__tab_map as $k => $id) {
  if (!empty($visual[$k])) { $__active_tab = $id; break; }
}
if ($__active_tab === '') { $__active_tab = 'tab-general'; } // fallback
?>
<div class="futbolin-full-bleed-wrapper theme-light">
  <div class="futbolin-content-container">

    <!-- Cabecera propia del perfil (NO la del wrapper) -->
    <header class="futbolin-main-header header-<?php echo esc_attr($header_cat_class); ?>">
      <div class="header-branding">
        <div class="header-side left">
          <?php if ($categoria_dobles === 'gm' || $categoria_individual === 'gm') : ?>
            <div class="gm-badge-wrapper"><span class="gm-badge">GM</span></div>
          <?php endif; ?>
          <img src="https://fefm.es/wp-content/uploads/2025/05/2.png" alt="Logo FEFM" class="header-logo"/>
        </div>
        <div class="header-text">
          <h1><?php echo esc_html($nombre_jugador); ?></h1>
          <h2>Perfil del Jugador</h2>
        </div>
        <div class="header-side right">
          <img src="https://flagcdn.com/w160/es.png" alt="Bandera de España" class="header-flag"/>
          <?php if ($categoria_dobles_display !== ''): ?>
            <div class="player-main-category">
              <?php echo esc_html($categoria_dobles_display); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="player-profile-container">
      <nav class="futbolin-tabs-nav">
        <a href="<?php echo esc_url($ranking_page_url); ?>" class="back-to-ranking-button">
          <span class="icon">←</span> <span class="text">Volver a principal</span>
        </a>

        <?php if (!empty($visual['glicko'])): ?>
          <a href="#tab-glicko-rankings" class="<?php echo ($__active_tab==='tab-glicko-rankings'?'active':''); ?>">Clasificación</a>
        <?php endif; ?>
        <?php if (!empty($visual['summary'])): ?>
          <a href="#tab-general" class="<?php echo ($__active_tab==='tab-general'?'active':''); ?>">General</a>
        <?php endif; ?>
        <?php if (!empty($visual['stats'])): ?>
          <a href="#tab-estadisticas" class="<?php echo ($__active_tab==='tab-estadisticas'?'active':''); ?>">Estadísticas</a>
        <?php endif; ?>
        <?php if (!empty($visual['history'])): ?>
          <a href="#tab-historial" class="<?php echo ($__active_tab==='tab-historial'?'active':''); ?>">Historial</a>
        <?php endif; ?>
        <?php if (!empty($visual['h2h'])): ?>
          <a href="#tab-h2h" class="<?php echo ($__active_tab==='tab-h2h'?'active':''); ?>">H2H</a>
        <?php endif; ?>
      </nav>

      <div class="futbolin-tabs-content">
        <?php if (!empty($visual['glicko'])): ?>
          <div id="tab-glicko-rankings" class="futbolin-tab-content <?php echo ($__active_tab==='tab-glicko-rankings'?'active':''); ?>">
            <?php
            $tpl = FUTBOLIN_API_PATH . 'includes/template-parts/player-glicko-rankings-tab.php';
            if (file_exists($tpl)) { include $tpl; }
            else { echo '<p>Esta sección está actualmente en mantenimiento, volveremos lo antes posible. ¡Gracias por tu paciencia!</p>'; }
            ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($visual['summary'])): ?>
          <div id="tab-general" class="futbolin-tab-content <?php echo ($__active_tab==='tab-general'?'active':''); ?>">
            <?php
            $tpl = FUTBOLIN_API_PATH . 'includes/template-parts/player-summary.php';
            if (file_exists($tpl)) { include $tpl; }
            else { echo '<p>Esta sección está actualmente en mantenimiento, volveremos lo antes posible. ¡Gracias por tu paciencia!</p>'; }
            ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($visual['stats'])): ?>
          <div id="tab-estadisticas" class="futbolin-tab-content <?php echo ($__active_tab==='tab-estadisticas'?'active':''); ?>">
            <?php
            $tpl = FUTBOLIN_API_PATH . 'includes/template-parts/player-stats.php';
            if (file_exists($tpl)) { include $tpl; }
            else { echo '<p>Esta sección está actualmente en mantenimiento, volveremos lo antes posible. ¡Gracias por tu paciencia!</p>'; }
            ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($visual['history'])): ?>
          <div id="tab-historial" class="futbolin-tab-content <?php echo ($__active_tab==='tab-historial'?'active':''); ?>">
            <?php
            $tpl = FUTBOLIN_API_PATH . 'includes/template-parts/player-history.php';
            if (file_exists($tpl)) { include $tpl; }
            else { echo '<p>Esta sección está actualmente en mantenimiento, volveremos lo antes posible. ¡Gracias por tu paciencia!</p>'; }
            ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($visual['h2h'])): ?>
          <div id="tab-h2h" class="futbolin-tab-content <?php echo ($__active_tab==='tab-h2h'?'active':''); ?>">
            <?php
            // player-h2h-tab.php espera: $search_term, $search_results, $h2h_processor
            $tpl = FUTBOLIN_API_PATH . 'includes/template-parts/player-h2h-tab.php';
            if (file_exists($tpl)) { include $tpl; }
            else { echo '<p>Esta sección está actualmente en mantenimiento, volveremos lo antes posible. ¡Gracias por tu paciencia!</p>'; }
            ?>
          </div>
        <?php endif; ?>

        <?php
        if (empty($visual['glicko']) && empty($visual['summary']) && empty($visual['stats']) && empty($visual['history']) && empty($visual['h2h'])) {
          echo '<div class="futbolin-card" style="margin-top:12px;"><p>No hay secciones activas para este perfil.</p></div>';
        }
        ?>
      </div>
    </div>
  </div>
</div>