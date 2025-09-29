<?php
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/**
 * Resolver la plantilla a cargar respetando variables del que incluye
 * y endureciendo entradas externas.
 */
$__tpl = null;

// 1) prioridad: $template_to_load (explícito desde el controlador)
if (!empty($template_to_load)) {
  $__tpl = basename((string)$template_to_load);

// 2) compat: $content_partial (nombres antiguos)
} elseif (!empty($content_partial)) {
  $__tpl = basename((string)$content_partial);

// 3) por defecto
} else {
  $__tpl = 'ranking-display.php';
}

// Normaliza la vista recibida por query o del controlador
$__view_qs = isset($current_view) && $current_view !== ''
  ? sanitize_key($current_view)
  : sanitize_key($_GET['view'] ?? '');

// 4) si NO vino nada explícito y la vista es 'info', forzar info-display.php
if ($__tpl === 'ranking-display.php' && $__view_qs === 'info') {
  $__tpl = 'info-display.php';
}

// Aviso: si se intenta forzar el antiguo 'annual-display.php', normalizamos y avisamos en log
if ($__tpl === 'annual-display.php') {
  $__tpl = 'ranking-display.php';
  if (function_exists('error_log')) {
    error_log('[Futbolin] Aviso: annual-display.php fue eliminado. Redirigiendo a ranking-display.php');
  }
}

// Whitelist básica de plantillas conocidas para evitar sorpresas
$__allowed_tpls = [
    'ranking-display.php',
    'tournaments-display.php',
    'tournament-stats-display.php',
    'player-summary.php',
    'player-stats.php',
    'player-profile-wrapper.php',
    'player-history.php',
    'player-glicko-rankings-tab.php',
    'info-display.php',
    'hall-of-fame-display.php',
    'finals-reports-display.php',
    'general-stats-display.php',
    'sidebar-stats.php',
    'about-display.php',
  'campeones-espana-display.php',
  'global-stats-display.php',
  'module-disabled-display.php',
  'maintenance-display.php',
  'rankgen-list-display.php'
];
if (!in_array($__tpl, $__allowed_tpls, true)) {
  $__tpl = 'ranking-display.php';
}

// dejamos la variable que ya usa el include
$template_to_load = $__tpl;

// Layout helper para ocultar el sidebar si se pide
$layout_has_sidebar = empty($hide_sidebar) ? true : false;

/**
 * URL del botón volver
 * - Permite override explícito desde quien incluye el wrapper
 * - Si existe página de ranking en admin, va allí con view=ranking
 * - Si no, fallback limpia parámetros que no deben persistir
 */
if (!empty($back_url_override)) {
  $back_url = esc_url((string)$back_url_override);
} else {
  $opts = get_option('mi_plugin_futbolin_options', []);
  if (!empty($opts['ranking_page_id'])) {
    $ranking_permalink = get_permalink((int)$opts['ranking_page_id']);
    // Forzamos view=ranking y limpiamos paginadores
    $back_url = esc_url(add_query_arg(['view' => 'ranking'], $ranking_permalink));
  } else {
    // Fallback: limpiar query y forzar view=ranking
    $back_url = esc_url( add_query_arg(
      ['view' => 'ranking'],
      remove_query_arg([
        'jugador_busqueda','page','page_size','order_by','order_dir',
        'info_type','torneo_id','compare_id','search_h2h','fpage','tpage',
        'pageSize','tpage_size'
      ])
    ));
  }
}
?>
<div class="futbolin-full-bleed-wrapper">
  <div class="futbolin-content-container">
    <?php
      // Banner de mantenimiento (solo admin) si el modo mantenimiento está activo
      $opts_banner = get_option('mi_plugin_futbolin_options', []);
      $rf_is_admin = function_exists('current_user_can') && current_user_can('manage_options');
      $rf_maint_on = isset($opts_banner['maintenance_mode']) && $opts_banner['maintenance_mode'] === 'on';
      if ($rf_is_admin && $rf_maint_on) {
        $settings_url = admin_url('admin.php?page=futbolin-api-settings&tab=configuracion');
    echo '<div class="rf-admin-maint-banner" role="alert" aria-live="assertive" style="position:fixed;top:0;left:0;right:0;z-index:2147483647;background:#d60000;color:#fff;padding:10px 0;">'
      . '<div class="rf-inner">'
      . '<span class="rf-alert-icon">⚠️</span>'
      . '<span class="rf-alert-text">MODO MANTENIMIENTO ACTIVADO — SOLO ADMIN VE EL CONTENIDO</span>'
        . '<span class="rf-alert-actions">'
        . '<a href="'.esc_url($settings_url).'">Ir a ajustes</a>'
        . '</span>'
      . '</div>'
      . '</div>';
      }
    ?>

    <header class="futbolin-main-header">
      <div class="header-branding">
        <div class="header-side left">
          <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/fefm.png' ); ?>" alt="Logo FEFM" class="header-logo" />
        </div>
        <div class="header-text">
          <?php
            $___title_main = 'Ranking ELO Futbolín';
            if (isset($current_view) && $current_view === 'annual') {
              $___title_main = 'Ranking anual Futbolín';
            }
          ?>
          <h1><?php echo esc_html($___title_main); ?></h1>
          <h2>Una Pierna en España</h2>
        </div>
        <div class="header-side right">
          <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/es.webp' ); ?>" alt="Bandera de España" class="header-flag" height="48" />
        </div>
      </div>
    </header>

    <div class="futbolin-layout-container <?php echo $layout_has_sidebar ? 'with-sidebar' : 'no-sidebar'; ?>">

      <?php if ($layout_has_sidebar): ?>
        <aside class="futbolin-sidebar">
          <?php
          $sidebar_path = FUTBOLIN_API_PATH . 'includes/template-parts/sidebar-menu.php';
          if (file_exists($sidebar_path)) {
            include $sidebar_path;
          } else {
            error_log('[Futbolin] sidebar-menu.php no encontrado: ' . $sidebar_path);
          }
          ?>
        </aside>
      <?php endif; ?>

      <main class="futbolin-main-content">

        <!-- Back-to-top: eliminado en favor de #rf-btt -->

        <?php if (!empty($show_back_btn)): ?>
          <p style="margin:0 0 12px 0;">
            <a class="futbolin-back-button" href="<?php echo $back_url; ?>">
              Volver a principal
            </a>
          </p>
        <?php endif; ?>

        <?php
        $tpl_path = FUTBOLIN_API_PATH . 'includes/template-parts/' . $template_to_load;
        if (is_file($tpl_path)) {
          include $tpl_path;
        } else {
          error_log('[Futbolin] plantilla no encontrada: ' . $tpl_path . ' (fallback a ranking-display.php)');
          include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-display.php';
        }
        ?>
      </main>
    </div>

  </div>
</div>