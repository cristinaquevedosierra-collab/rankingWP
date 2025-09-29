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

// Polyfills no-op para análisis/CLI (WordPress las define en runtime)
if (!function_exists('sanitize_html_class')) {
  function sanitize_html_class($class, $fallback = '') {
    $san = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$class);
    return ($san === '' && $fallback !== '') ? $fallback : $san;
  }
}
if (!function_exists('get_option')) {
  function get_option($option, $default = false) {
    return $default;
  }
}
if (!function_exists('sanitize_text_field')) {
  function sanitize_text_field($str){
    $str = (string)$str;
    $str = filter_var($str, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
    return trim($str);
  }
}
if (!function_exists('wp_unslash')) {
  function wp_unslash($value){ return is_string($value) ? stripslashes($value) : $value; }
}

// Helper para extraer player_id de distintas formas de datos
if (!function_exists('rf_extract_basic_player_id')) {
  function rf_extract_basic_player_id($src) {
    if (!$src) return 0;
    $o = is_object($src) ? $src : (object)$src;
    foreach (['jugadorId','JugadorId','playerId','PlayerId','id','Id'] as $k) {
      if (isset($o->$k) && is_numeric($o->$k)) return (int)$o->$k;
    }
    foreach (['jugador','Jugador','player','Player'] as $jk) {
      if (isset($o->$jk)) {
        $j = $o->$jk;
        if (is_object($j)) { foreach (['jugadorId','JugadorId','id','Id'] as $ik) { if (isset($j->$ik) && is_numeric($j->$ik)) return (int)$j->$ik; } }
        if (is_array($j))   { foreach (['jugadorId','JugadorId','id','Id'] as $ik) { if (isset($j[$ik]) && is_numeric($j[$ik])) return (int)$j[$ik]; } }
      }
    }
    return 0;
  }
}

// SVG de corona minimal para reuso en el header (aislado de Hitos)
if (!function_exists('rf_header_svg_crown')) {
  function rf_header_svg_crown($tone = 'gold', $size = 20){
    $gid = 'rf-hcrown-'.sanitize_html_class($tone).'-'.substr(md5(uniqid('', true)), 0, 6);
    $pal = [
      'gold'   => ['#fff7c2','#f6b73c','#d19900'],
      'silver' => ['#ffffff','#cfd6e1','#9aa3b1'],
      'bronze' => ['#ffe6cf','#d7935b','#b46535'],
    ];
    $gg = isset($pal[$tone]) ? $pal[$tone] : $pal['gold'];
    ob_start(); ?>
  <svg viewBox="0 0 128 96" aria-hidden="true" width="<?php echo (int)$size; ?>" height="<?php echo (int)$size; ?>">
      <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="<?php echo esc_attr($gg[0]); ?>"/><stop offset="55%" stop-color="<?php echo esc_attr($gg[1]); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($gg[2]); ?>"/>
      </linearGradient></defs>
      <!-- Corona más puntiaguda para mejor legibilidad a tamaño pequeño -->
      <path fill="url(#<?php echo esc_attr($gid); ?>)" d="M8,72 L120,72 L112,92 C80,98 48,98 16,92 Z M12,72 L28,40 L48,60 L64,28 L80,60 L100,40 L116,72 Z"/>
      <circle cx="28" cy="46" r="4.5" fill="url(#<?php echo esc_attr($gid); ?>)" />
      <circle cx="48" cy="58" r="4.5" fill="url(#<?php echo esc_attr($gid); ?>)" />
      <circle cx="64" cy="26" r="5.5" fill="url(#<?php echo esc_attr($gid); ?>)" />
      <circle cx="80" cy="58" r="4.5" fill="url(#<?php echo esc_attr($gid); ?>)" />
      <circle cx="100" cy="46" r="4.5" fill="url(#<?php echo esc_attr($gid); ?>)" />
    </svg>
    <?php return trim(ob_get_clean());
  }
}

// SVG de estrella para Campeonatos de España (línea superior)
if (!function_exists('rf_header_svg_star')) {
  function rf_header_svg_star($tone = 'gold', $size = 20){
    $gid = 'rf-hstar-'.sanitize_html_class($tone).'-'.substr(md5(uniqid('', true)), 0, 6);
    $pal = [
      'gold'   => ['#fff7c2','#f6b73c','#d19900'],
      'silver' => ['#f9fbff','#cfd6e1','#a1aab8'],
      'bronze' => ['#ffe2c5','#d18a4b','#a65a24'],
    ];
    $gg = isset($pal[$tone]) ? $pal[$tone] : $pal['gold'];
    ob_start(); ?>
    <svg viewBox="0 0 24 24" aria-hidden="true" width="<?php echo (int)$size; ?>" height="<?php echo (int)$size; ?>">
      <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="<?php echo esc_attr($gg[0]); ?>"/><stop offset="55%" stop-color="<?php echo esc_attr($gg[1]); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($gg[2]); ?>"/>
      </linearGradient></defs>
      <path fill="url(#<?php echo esc_attr($gid); ?>)" d="M12 2.3l3.4 6.7 7.4.7-5.5 4.7 1.8 7.1L12 18 4.9 21.5l1.8-7.1L1.2 9.7l7.4-.7z"></path>
    </svg>
    <?php return trim(ob_get_clean());
  }
}

// SVG de candado para "pendientes"
if (!function_exists('rf_header_svg_lock')) {
  function rf_header_svg_lock($tone = 'locked', $size = 18){
    $gid = 'rf-hlock-'.sanitize_html_class($tone).'-'.substr(md5(uniqid('', true)), 0, 6);
    $pal = [
      'locked' => ['#f9fbff','#c8ced8','#8c95a3'],
      'gold'   => ['#fff7c2','#f6b73c','#d19900'],
      'silver' => ['#ffffff','#cfd6e1','#9aa3b1'],
    ];
    $gg = isset($pal[$tone]) ? $pal[$tone] : $pal['locked'];
    ob_start(); ?>
    <svg viewBox="0 0 24 24" aria-hidden="true" width="<?php echo (int)$size; ?>" height="<?php echo (int)$size; ?>">
      <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="<?php echo esc_attr($gg[0]); ?>"/><stop offset="55%" stop-color="<?php echo esc_attr($gg[1]); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($gg[2]); ?>"/>
      </linearGradient></defs>
      <!-- Cuerpo del candado -->
      <rect x="5" y="10" width="14" height="10" rx="2" ry="2" fill="url(#<?php echo esc_attr($gid); ?>)" />
      <!-- Arco del candado -->
      <path d="M8 10 V7.5a4 4 0 0 1 8 0V10" fill="none" stroke="url(#<?php echo esc_attr($gid); ?>)" stroke-width="2.2" stroke-linecap="round"/>
      <!-- Cerrojo -->
      <circle cx="12" cy="15" r="1.6" fill="#6b7380" />
      <rect x="11.4" y="15.8" width="1.2" height="2.6" rx="0.6" fill="#6b7380"/>
    </svg>
    <?php return trim(ob_get_clean());
  }
}

/* ========= Normalizaciones y fallbacks ========= */

// Processor y datos base (compat: basic_data | player_data)
$processor = (isset($processor) && is_object($processor)) ? $processor : null;

// Si la plantilla ya recibió $player_details desde el shortcode, no lo sobrescribas.
if (!isset($player_details) || !is_object($player_details)) {
  $player_details = null;
  if ($processor) {
    if (isset($processor->basic_data) && is_object($processor->basic_data)) {
      $player_details = $processor->basic_data;
    } elseif (isset($processor->player_data) && is_object($processor->player_data)) {
      $player_details = $processor->player_data;
    }
  }
  if (!$player_details) { $player_details = (object)[]; }
}

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
  'hitos'   => true,   // activar Hitos por defecto
  'history' => true,
  'torneos' => true,
  'glicko'  => false, // por defecto off para evitar mensaje si no hay API
];

// Categorías y labels (si el shortcode las definió)
$categoria_dobles         = isset($categoria_dobles) ? (string)$categoria_dobles : '';
$categoria_individual     = isset($categoria_individual) ? (string)$categoria_individual : '';
$categoria_dobles_display = isset($categoria_dobles_display) ? (string)$categoria_dobles_display : '';

// Clase de cabecera por categoría
$header_cat_class = $categoria_dobles !== '' ? sanitize_html_class($categoria_dobles) : 'nc';

// URL “Volver”: canónica hacia /futbolin-ranking
if (empty($ranking_page_url)) {
  $opts = get_option('mi_plugin_futbolin_options', []);
  if (!empty($opts['ranking_page_id'])) {
    $ranking_permalink = get_permalink((int)$opts['ranking_page_id']);
    $ranking_page_url  = esc_url(add_query_arg(['view' => 'ranking'], $ranking_permalink));
  } elseif (function_exists('get_page_by_path')) {
    $pg = get_page_by_path('futbolin-ranking');
    if ($pg) {
      $ranking_page_url = esc_url(add_query_arg(['view'=>'ranking'], get_permalink($pg->ID)));
    } else {
      $ranking_page_url = home_url('/futbolin-ranking/');
    }
  } elseif (function_exists('_futb_url_ranking')) {
    $ranking_page_url = _futb_url_ranking([]);
  } else {
    $ranking_page_url = home_url('/futbolin-ranking/');
  }
}

// Blindaje adicional: si por cualquier motivo la URL no apunta a /futbolin-ranking/, forzarla
try {
  $rp = (string)$ranking_page_url;
  if ($rp === '' || strpos($rp, '/futbolin-ranking') === false) {
    $ranking_page_url = home_url('/futbolin-ranking/');
  }
} catch (\Throwable $e) { $ranking_page_url = home_url('/futbolin-ranking/'); }

// Player ID robusto para AJAX/data-atributos
$__player_id = 0;
if (isset($player_id) && is_numeric($player_id)) { $__player_id = (int)$player_id; }
if ($__player_id <= 0 && isset($jugador_id) && is_numeric($jugador_id)) { $__player_id = (int)$jugador_id; }
if ($__player_id <= 0 && isset($processor) && is_object($processor)) {
  if (isset($processor->basic_data) && is_object($processor->basic_data)) {
    $__player_id = rf_extract_basic_player_id($processor->basic_data);
  }
  if ($__player_id <= 0 && isset($processor->player_data) && is_object($processor->player_data)) {
    $__player_id = rf_extract_basic_player_id($processor->player_data);
  }
}
if ($__player_id <= 0 && isset($_GET['jugador_id']) && is_numeric($_GET['jugador_id'])) { $__player_id = (int)$_GET['jugador_id']; }
if (!isset($player_id) || !is_numeric($player_id)) { $player_id = $__player_id; }

/* ========= Tab activa (primera visible) ========= */
$__tab_map = [
  'glicko'  => 'tab-glicko-rankings',
  'summary' => 'tab-general',
  'stats'   => 'tab-estadisticas',
  'hitos'   => 'tab-hitos',
  'history' => 'tab-historial',
  'torneos' => 'tab-torneos',
];
$__active_tab = '';
foreach ($__tab_map as $k => $id) {
  if (!empty($visual[$k])) { $__active_tab = $id; break; }
}
if ($__active_tab === '') { $__active_tab = 'tab-general'; } // fallback
// Si estamos depurando Hitos (?rf_debug_hitos=1), forzar pestaña Hitos como activa
if (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') {
  $__active_tab = 'tab-hitos';
}
?>
<div class="futbolin-full-bleed-wrapper theme-light" data-rf-profile-root="1">
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

    <!-- Cabecera propia del perfil (NO la del wrapper) -->
    <header class="futbolin-main-header header-<?php echo esc_attr($header_cat_class); ?>">
      <div class="header-branding">
        <div class="header-side left">
          <?php if ($categoria_dobles === 'gm' || $categoria_individual === 'gm') : ?>
            <div class="gm-badge-wrapper"><span class="gm-badge">GM</span></div>
          <?php endif; ?>
          <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/fefm.png' ); ?>" alt="Logo FEFM" class="header-logo"/>
        </div>
        <div class="header-text">
          <h1><?php echo esc_html($nombre_jugador); ?></h1>
          <?php
          // Bloque de badges en dos líneas: 1) Estrellas (Campeonatos de España), 2) Coronas (Nº1 por temporada)
          // Hitos para el banner: usar Processor si existe, si no, usar $hitos_header precalculado (SSR rápido)
          $hitos = [];
          if (isset($processor) && is_object($processor) && isset($processor->hitos) && is_array($processor->hitos)) {
            $hitos = $processor->hitos;
          } elseif (isset($hitos_header) && is_array($hitos_header)) {
            $hitos = $hitos_header;
          }
          $champ_d = isset($hitos['campeon_esp_dobles_anios']) ? (array)$hitos['campeon_esp_dobles_anios'] : [];
          $champ_i = isset($hitos['campeon_esp_individual_anios']) ? (array)$hitos['campeon_esp_individual_anios'] : [];
          $no1_d   = isset($hitos['numero1_temporada_open_dobles_anios']) ? (array)$hitos['numero1_temporada_open_dobles_anios'] : [];
          $no1_i   = isset($hitos['numero1_temporada_open_individual_anios']) ? (array)$hitos['numero1_temporada_open_individual_anios'] : [];
          // Modo depuración de badges (activar con ?rf_debug_badges=1)
          $rf_debug_badges = (isset($_GET['rf_debug_badges']) && $_GET['rf_debug_badges'] == '1');
          // Estrellas (Campeonatos de España): mantener ocurrencias por modalidad; normalizar 2025→2024
          $normYear = function($yy){
            if (is_numeric($yy)) { $y = (int)$yy; }
            else if (preg_match('/(19|20)\d{2}/', (string)$yy, $m)) { $y = (int)$m[0]; }
            else { $y = null; }
            if ($y === 2025) $y = 2024;
            return $y;
          };
          $champ_occ = [];
          $seen = [];
          foreach ($champ_d as $yy) { $y = $normYear($yy); if ($y) { $k = $y.'|D'; if (!isset($seen[$k])) { $champ_occ[] = ['year'=>$y,'cat'=>'Dobles']; $seen[$k]=1; } } }
          foreach ($champ_i as $yy) { $y = $normYear($yy); if ($y) { $k = $y.'|I'; if (!isset($seen[$k])) { $champ_occ[] = ['year'=>$y,'cat'=>'Individual']; $seen[$k]=1; } } }
          usort($champ_occ, function($a,$b){ return ($a['year'] <=> $b['year']) ?: strcmp($a['cat'],$b['cat']); });
          $count_champs = count($champ_occ);
          // Coronas: una por cada logro (dobles e individual), manteniendo ocurrencias incluso si coinciden en el mismo año
          $no1_list = [];
          $normalize_year = function($yy){
            if (is_numeric($yy)) return (int)$yy;
            if (preg_match('/(19|20)\d{2}/', (string)$yy, $m)) return (int)$m[0];
            return null;
          };
          foreach ($no1_d as $yy) { $y = $normalize_year($yy); if ($y) { $no1_list[] = ['year'=>$y,'cat'=>'Dobles']; } }
          foreach ($no1_i as $yy) { $y = $normalize_year($yy); if ($y) { $no1_list[] = ['year'=>$y,'cat'=>'Individual']; } }
          // Ordenar por año ascendente para consistencia visual
          usort($no1_list, function($a,$b){ return ($a['year'] <=> $b['year']) ?: strcmp($a['cat'],$b['cat']); });
          $count_no1 = count($no1_list);
          // Fallback: intenta servicio central si no tenemos Nº1
          if ($count_no1 === 0 && isset($player_id) && $player_id && class_exists('Futbolin_Rankgen_Service')) {
            try {
              $podium = \Futbolin_Rankgen_Service::get_player_podium_years((string)$player_id);
              if (is_array($podium)) {
                $no1_d = isset($podium['dobles']['no1']) ? (array)$podium['dobles']['no1'] : $no1_d;
                $no1_i = isset($podium['individual']['no1']) ? (array)$podium['individual']['no1'] : $no1_i;
                $no1_list = [];
                foreach ($no1_d as $yy) { $y = $normalize_year($yy); if ($y) { $no1_list[] = ['year'=>$y,'cat'=>'Dobles']; } }
                foreach ($no1_i as $yy) { $y = $normalize_year($yy); if ($y) { $no1_list[] = ['year'=>$y,'cat'=>'Individual']; } }
                usort($no1_list, function($a,$b){ return ($a['year'] <=> $b['year']) ?: strcmp($a['cat'],$b['cat']); });
                $count_no1 = count($no1_list);
              }
            } catch (\Throwable $e) { /* silencioso */ }
          }
          ?>
          <h2>Perfil del Jugador</h2>
          <?php if ($count_champs > 0 || $count_no1 > 0): ?>
            <div class="player-header-badges" role="group" aria-label="Logros del jugador" style="margin-top:8px;">
              <?php if ($count_champs > 0): ?>
                <div class="badges-line line-stars" aria-label="Campeonatos de España" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:2px 0;">
                  <span class="badge-label" style="font-size:12px; color:#666;">Campeón de España (<?php echo (int)$count_champs; ?>):</span>
                  <?php foreach ($champ_occ as $occ): $yy = intval($occ['year']); $cat = (string)$occ['cat']; $ttl = 'Campeón de España ('.$cat.') '.$yy; ?>
                    <span class="star" title="<?php echo esc_attr($ttl); ?>" aria-label="<?php echo esc_attr($ttl); ?>"><?php echo rf_header_svg_star('gold', 19); ?><?php if ($rf_debug_badges) { echo '<span class="rf-badge-debug" style="margin-left:4px;font-size:11px;color:#555">'.esc_html($cat.' '.$yy).'</span>'; } ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if ($count_champs > 0 && $count_no1 > 0): ?>
                <div class="badges-sep" aria-hidden="true" style="width:100%; height:0; border-top:1px dashed #c9c9c9; margin:2px 0 4px;"></div>
              <?php endif; ?>
              <?php if ($count_no1 > 0): ?>
                <div class="badges-line line-crowns" aria-label="Nº1 del Ranking por Temporada" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:2px 0;">
                  <span class="badge-label" style="font-size:12px; color:#666;">Años como Nº1 (<?php echo (int)$count_no1; ?>):</span>
                  <?php foreach ($no1_list as $occ): $yy = intval($occ['year']); $cat = (string)$occ['cat']; $ttl = 'Nº 1 temporada ' . $yy; ?>
                    <span class="crown" title="<?php echo esc_attr($ttl); ?>" aria-label="<?php echo esc_attr($ttl); ?>"><?php echo rf_header_svg_crown('gold', 18); ?><?php if ($rf_debug_badges) { echo '<span class="rf-badge-debug" style="margin-left:4px;font-size:11px;color:#555">'.esc_html($cat.' '.$yy).'</span>'; } ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="header-side right">
          <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/es.webp' ); ?>" alt="Bandera de España" class="header-flag" height="48"/>
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
        <?php if (!empty($visual['hitos'])): ?>
          <a href="#tab-hitos" class="<?php echo ($__active_tab==='tab-hitos'?'active':''); ?>">Hitos</a>
        <?php endif; ?>
        <?php if (!empty($visual['history'])): ?>
          <a href="#tab-historial" class="<?php echo ($__active_tab==='tab-historial'?'active':''); ?>">Partidos</a>
        <?php endif; ?>
        <?php if (!empty($visual['torneos'])): ?>
          <a href="#tab-torneos" class="<?php echo ($__active_tab==='tab-torneos'?'active':''); ?>">Torneos</a>
        <?php endif; ?>
      </nav>

  <div class="futbolin-tabs-content" data-rf-player-tabs data-player-id="<?php echo intval($__player_id); ?>">
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
          <div id="tab-general" class="futbolin-tab-content <?php echo ($__active_tab==='tab-general'?'active':''); ?>" data-rf-lazy="summary" aria-busy="true" aria-live="polite">
            <div class="rf-skeleton rf-skel-general"><div class="rf-skel-line"></div><div class="rf-skel-line"></div><div class="rf-skel-line short"></div><p class="rf-skel-msg">Cargando…</p></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($visual['stats'])): ?>
          <div id="tab-estadisticas" class="futbolin-tab-content <?php echo ($__active_tab==='tab-estadisticas'?'active':''); ?>" data-rf-lazy="stats" aria-busy="true" aria-live="polite">
            <div class="rf-skeleton rf-skel-stats"><div class="rf-skel-line"></div><div class="rf-skel-line"></div><div class="rf-skel-line"></div><p class="rf-skel-msg">Cargando…</p></div>
          </div>

        <?php endif; ?>

        <?php if (!empty($visual['history'])): ?>
          <div id="tab-historial" class="futbolin-tab-content <?php echo ($__active_tab==='tab-historial'?'active':''); ?>" data-rf-lazy="history" aria-busy="true" aria-live="polite">
            <div class="rf-skeleton rf-skel-history"><div class="rf-skel-line"></div><div class="rf-skel-line"></div><p class="rf-skel-msg">Cargando…</p></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($visual['hitos'])): ?>
          <div id="tab-hitos" class="futbolin-tab-content <?php echo ($__active_tab==='tab-hitos'?'active':''); ?>" data-rf-lazy="hitos" aria-busy="true" aria-live="polite">
            <div class="rf-skeleton rf-skel-hitos"><div class="rf-skel-line"></div><div class="rf-skel-line"></div><div class="rf-skel-line short"></div><p class="rf-skel-msg">Cargando…</p></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($visual['torneos'])): ?>
          <div id="tab-torneos" class="futbolin-tab-content <?php echo ($__active_tab==='tab-torneos'?'active':''); ?>" data-rf-lazy="torneos" aria-busy="true" aria-live="polite">
            <div class="rf-skeleton rf-skel-torneos"><div class="rf-skel-line"></div><div class="rf-skel-line"></div><p class="rf-skel-msg">Cargando…</p></div>
          </div>
        <?php endif; ?>

        

        <?php
        if (empty($visual['glicko']) && empty($visual['summary']) && empty($visual['stats']) && empty($visual['history']) && empty($visual['hitos']) && empty($visual['torneos'])) {
          echo '<div class="futbolin-card" style="margin-top:12px;"><p>No hay secciones activas para este perfil.</p></div>';
        }
        ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // CSS mínimo de skeleton como fallback (por si no se cargan bundles o tarda el JS)
  try {
    var styleText = '.rf-skeleton{position:relative;overflow:hidden;background:#f6f7f8;border:1px solid #e5e7eb;border-radius:12px;padding:16px;min-height:96px}'
      + '.rf-skel-line{height:14px;background:linear-gradient(90deg,#eee 25%,#f5f5f5 37%,#eee 63%);background-size:400% 100%;animation:rf-shimmer 1.2s ease-in-out infinite;border-radius:8px;margin-bottom:10px}'
      + '.rf-skel-line.short{width:45%}'
      + '.rf-skel-msg{margin:8px 0 0 0;color:#666;font-size:13px;display:flex;align-items:center;gap:8px}'
      + '.rf-skel-msg:before{content:"";display:inline-block;width:14px;height:14px;border:2px solid #999;border-top-color:transparent;border-radius:50%;animation:rf-spin .9s linear infinite}'
      + '@keyframes rf-shimmer{0%{background-position:100% 0}100%{background-position:0 0}}'
      + '@keyframes rf-spin{to{transform:rotate(360deg)}}';
    // Inyectar en el documento principal
    if (!document.getElementById('rf-skeleton-inline-css')) {
      var st = document.createElement('style');
      st.id = 'rf-skeleton-inline-css';
      st.textContent = styleText;
      (document.head||document.documentElement).appendChild(st);
    }
    // Inyectar dentro de cada ShadowRoot del web component (si existe)
    var hosts = document.querySelectorAll('ranking-futbolin-app');
    hosts.forEach(function(h){
      try {
        if (!h || !h.shadowRoot) return;
        var root = h.shadowRoot;
        if (!root.querySelector('style[data-rf-skeleton="1"]')){
          var st2 = document.createElement('style');
          st2.setAttribute('data-rf-skeleton','1');
          st2.textContent = styleText;
          root.appendChild(st2);
        }
      } catch(_){ }
    });
  } catch(e){}
  function activateTabByHash(){
    var h = window.location.hash ? window.location.hash.substring(1) : '';
    if(!h) return;
    var tabs = document.querySelectorAll('.futbolin-tab-content');
    tabs.forEach(function(el){ el.classList.remove('active'); });
    var links = document.querySelectorAll('.futbolin-tabs-nav a');
    links.forEach(function(a){ a.classList.remove('active'); });
    var target = document.getElementById(h);
    if(target){ target.classList.add('active'); }
    links.forEach(function(a){ if(a.getAttribute('href') === '#'+h){ a.classList.add('active'); } });
  }
  window.addEventListener('hashchange', activateTabByHash);
  document.addEventListener('DOMContentLoaded', activateTabByHash);
})();
</script>

