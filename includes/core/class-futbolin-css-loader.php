<?php
/**
 * CSS Loader interno (soft): solo override si existen bundles en dist/assets/css-purged/.
 * Ubicación: includes/core/class-futbolin-css-loader.php
 */
if (!defined('ABSPATH')) { exit; }

// Stubs/guards para análisis fuera de WordPress (no afectan runtime en WP)
if (!function_exists('add_action')) { function add_action($hook, $cb, $prio = 10, $args = 1) {} }
if (!function_exists('is_admin')) { function is_admin(){ return false; } }
if (!function_exists('plugins_url')) { function plugins_url($path = '', $plugin = ''){ return $path; } }
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/i','', (string)$v); } }
if (!function_exists('wp_dequeue_style')) { function wp_dequeue_style($handle) {} }
if (!function_exists('wp_deregister_style')) { function wp_deregister_style($handle) {} }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {} }

add_action('wp_enqueue_scripts', 'rf_css_loader_internal_soft', 9999);
add_action('wp_print_styles',    'rf_css_loader_internal_soft', 9999);

function rf_css_loader_internal_soft() {
    if (is_admin()) return;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // Si Shadow DOM está activo (opción u override), no toques estilos del documento
    if (function_exists('rf_shadow_enabled') ? rf_shadow_enabled() : (function_exists('get_option') && get_option('rf_shadow_mode', 0))) {
        return;
    }
    $is_rf = (strpos($uri, '/futbolin-ranking') !== false) || (strpos($uri, '/perfil-jugador') !== false) || (strpos($uri, '/h2h') !== false);
    if (!$is_rf) return;

    // Activación controlada: por defecto habilitada si RF_ENABLE_PURGED=true, o si query ?rf_css_variant=purged

    // Activación controlada del override purged:
    // - Solo si ?rf_css_variant=purged
    // - O si está definida la constante RF_ENABLE_PURGED=true
    $enable_purged = (isset($_GET['rf_css_variant']) && $_GET['rf_css_variant'] === 'purged');
    if (!$enable_purged && defined('RF_ENABLE_PURGED')) {
        $enable_purged = (bool) constant('RF_ENABLE_PURGED');
    }
    if (!$enable_purged) {
        // No forzar override: dejar CSS estándar de assets/css
        return;
    }

    $plugin_main = dirname(__DIR__, 2) . '/ranking-futbolin.php'; // desde includes/core/
    $purged_rel  = 'dist/assets/css-purged/';
    $purged_dir  = dirname(__DIR__, 2) . '/' . $purged_rel;
    $purged_url  = plugins_url($purged_rel, $plugin_main);

    // SOFT-GUARD: sólo si tenemos al menos core.css y components.css
    $has_core = file_exists($purged_dir . 'core.css');
    $has_components = file_exists($purged_dir . 'components.css');
    if (!($has_core && $has_components)) {
        // No hay bundles purgados -> no tocamos nada (carga legacy).
        return;
    }

    // A partir de aquí, sí hacemos override fuerte
    if (!defined('RF_CSS_OVERRIDE_STRONG')) define('RF_CSS_OVERRIDE_STRONG', true);

    // 1) Dequeue + deregister legacy (excepto rf-live.css, 27-skeleton.css y 90-compat-override.css)
    global $wp_styles;
    if ($wp_styles && is_array($wp_styles->queue)) {
        foreach ($wp_styles->queue as $handle) {
            if (empty($wp_styles->registered[$handle])) continue;
            $src = $wp_styles->registered[$handle]->src ?? '';
            if (!$src) continue;
            if (strpos($src, '/wp-content/plugins/ranking-futbolin/') === false) continue;
            $is_legacy = (strpos($src, '/assets/css/') !== false);
            // Excepciones: rf-live.css, 27-skeleton.css y 90-compat-override.css (permitir query string)
            $is_exception = preg_match('#/(rf-live\\.css|27-skeleton\\.css|90-compat-override\\.css)(?:\\?|$)#', $src) === 1;
            if ($is_legacy && !$is_exception) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }

    // Helper para encolar si existe físicamente
    $enqueue_if_exists = function($handle, $file, $deps = array()) use ($purged_dir, $purged_url) {
        $path = $purged_dir . $file;
        if (file_exists($path)) {
            wp_enqueue_style($handle, $purged_url . $file, $deps, '0.7.3');
        }
    };

    $view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';

    // Base común
    $enqueue_if_exists('rf-core',       'core.css');
    $enqueue_if_exists('rf-components', 'components.css', array('rf-core'));

    if (strpos($uri, '/perfil-jugador') !== false) {
        $enqueue_if_exists('rf-perfil', 'perfil.css', array('rf-components'));
    } elseif (in_array($view, array('tournaments','tournament-stats','finals_reports'), true)) {
        $enqueue_if_exists('rf-tournaments', 'tournaments.css', array('rf-components'));
    } elseif ($view === 'global-stats' || $view === 'hall-of-fame' || $view === 'info') {
        $enqueue_if_exists('rf-stats', 'stats.css', array('rf-components'));
    } else {
        $enqueue_if_exists('rf-ranking', 'ranking.css', array('rf-components'));
    }
}

// Inspector opcional: ?rf_debug_css=1 → vuelca en error_log los estilos encolados y su origen
if (!function_exists('rf_css_debug_inspector')) {
    function rf_css_debug_inspector() {
        $enabled = isset($_GET['rf_debug_css']) && $_GET['rf_debug_css'] == '1';
        if (!$enabled) return;
        // Evitar ruido en admin
        if (function_exists('is_admin') && is_admin()) return;
        // Contexto útil para depurar
        $uri  = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $view = isset($_GET['view']) ? (string)$_GET['view'] : '';
        $flag = defined('RF_CSS_OVERRIDE_STRONG') ? '1' : '0';
        error_log('RF_CSS_DEBUG context override_strong=' . $flag . ' uri=' . $uri . ' view=' . $view);
        global $wp_styles;
        if (!$wp_styles || !is_array($wp_styles->queue)) { error_log('RF_CSS_DEBUG: no styles queued'); return; }
        $counts = array('purged' => 0, 'min' => 0, 'assets' => 0, 'flat' => 0, 'public' => 0, 'other' => 0);
        foreach ($wp_styles->queue as $handle) {
            if (empty($wp_styles->registered[$handle])) continue;
            $src = (string)($wp_styles->registered[$handle]->src ?? '');
            if ($src === '') continue;
            $origin = 'other';
            if (strpos($src, '/dist/assets/css-purged/') !== false)      { $origin = 'purged'; }
            elseif (strpos($src, '/dist/assets/css-min/') !== false)     { $origin = 'min'; }
            elseif (strpos($src, '/assets/css/') !== false)              { $origin = 'assets'; }
            elseif (strpos($src, '/flat/') !== false)                    { $origin = 'flat'; }
            elseif (strpos($src, '/public/css/') !== false || strpos($src, '/public/') !== false) { $origin = 'public'; }
            $counts[$origin] = isset($counts[$origin]) ? ($counts[$origin] + 1) : 1;
            error_log('RF_CSS_DEBUG handle=' . $handle . ' origin=' . $origin . ' src=' . $src);
        }
        $summary = array();
        foreach ($counts as $k => $v) { $summary[] = $k . ':' . $v; }
        error_log('RF_CSS_DEBUG summary ' . implode(', ', $summary));
    }
    // Muy tarde para capturar todo lo encolado
    add_action('wp_print_styles', 'rf_css_debug_inspector', 100000);
}

// Guard universal: nunca permitir estilos desde /public/css/ (fuente histórica no usada)
if (!function_exists('rf_neuter_public_css')) {
    function rf_neuter_public_css() {
        // Evitar ruido en admin
        if (function_exists('is_admin') && is_admin()) return;
        global $wp_styles;
        if (!$wp_styles || !is_array($wp_styles->queue)) return;
        foreach ($wp_styles->queue as $handle) {
            if (empty($wp_styles->registered[$handle])) continue;
            $src = (string)($wp_styles->registered[$handle]->src ?? '');
            if ($src === '') continue;
            if (strpos($src, '/public/css/') !== false) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
    // Muy tarde para ganarle a cualquier enqueue accidental
    add_action('wp_print_styles', 'rf_neuter_public_css', 100000);
}
