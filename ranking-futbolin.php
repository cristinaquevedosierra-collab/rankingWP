<?php
// Habilitar por defecto el CSS purgado (polished) para las vistas del plugin
if (!defined('RF_ENABLE_PURGED')) {
    define('RF_ENABLE_PURGED', true);
}
// Helper global: Shadow DOM habilitado (con override por query ?rf_shadow=1/0)
if (!function_exists('rf_shadow_enabled')) {
    function rf_shadow_enabled(): bool {
        $on = (bool) get_option('rf_shadow_mode', 0);
        if (isset($_GET['rf_shadow'])) {
            $v = strtolower((string)$_GET['rf_shadow']);
            $on = ($v === '1' || $v === 'on' || $v === 'true' || $v === 'yes');
        }
        return (bool)$on;
    }
}
/* =============================================================
 * INLINE: RF CSS Override Strong (ex MU plugin)
 * Bloquea CSS legacy y encola bundles purgados.
 * ============================================================= */
if (defined('ABSPATH') && !defined('RF_CSS_OVERRIDE_STRONG')) {
  define('RF_CSS_OVERRIDE_STRONG', true);
  if (!function_exists('rf_css_paths')) {
    function rf_css_paths() {
                // Ruta relativa dinámica del plugin (evita depender del nombre fijo de la carpeta)
                // Extraemos solo la parte path de la URL del plugin y la usamos para las comparaciones.
                $plugin_url = plugins_url('/', __FILE__); // termina en '/'
                $plugin_path_part = parse_url($plugin_url, PHP_URL_PATH); // e.g. '/wp-content/plugins/ranking-futbolin/'
                $plugin_rel = rtrim($plugin_path_part, '/') . '/';
        return array(
            'plugin_rel' => $plugin_rel,
            'legacy_dir' => $plugin_rel . 'assets/css/',
            'handle_prefix' => 'futbolin-style-',
            'except_exact' => array(
                $plugin_rel . 'assets/css/rf-live.css',
                $plugin_rel . 'assets/css/90-compat-override.css',
            ),
        );
    }
  }
  if (!function_exists('rf_rel_path')) {
    function rf_rel_path($src) {
        if (!$src) return '';
        if (strpos($src, '//') === 0) $src = (is_ssl() ? 'https:' : 'http:') . $src;
        if (strpos($src, '/') === 0 && strpos($src, '/wp-content/') === 0) {
            $path = parse_url(home_url($src), PHP_URL_PATH);
        } else {
            $path = parse_url($src, PHP_URL_PATH);
        }
        return $path ?: '';
    }
  }
  if (!function_exists('rf_is_blocked_css')) {
    function rf_is_blocked_css($src) {
        $cfg = rf_css_paths();
        $rel = rf_rel_path($src);
        if (in_array($rel, $cfg['except_exact'], true)) return false;
        if (strpos($rel, $cfg['legacy_dir']) === 0) return true;
        if (strpos((string)$src, $cfg['legacy_dir']) !== false || strpos((string)$src, 'wp-content/plugins/ranking-futbolin/assets/css/') !== false) return true;
        return false;
    }
  }
  // Encolar bundles purgados
  add_action('wp_enqueue_scripts', function() {
      $ver = defined('FUTBOLIN_API_VERSION') ? FUTBOLIN_API_VERSION : '0.7.3';
      $base = plugins_url('dist/assets/css-purged/', __FILE__);
      $enqueue = function($file, $handle) use ($base, $ver) {
          wp_enqueue_style($handle, $base . $file, array(), $ver, 'all');
      };
      $uri = $_SERVER['REQUEST_URI'] ?? '';
      $qs  = $_GET;
      $enqueue('core.css', 'rf-core');
      $enqueue('components.css', 'rf-components');
      if (strpos($uri, '/perfil-jugador') !== false) { $enqueue('perfil.css', 'rf-perfil'); }
      if (strpos($uri, '/futbolin-ranking') !== false && empty($qs['view'])) { $enqueue('ranking.css', 'rf-ranking'); }
      $view = isset($qs['view']) ? $qs['view'] : '';
      switch ($view) {
          case 'tournaments':
          case 'tournament-stats':
              $enqueue('tournaments.css', 'rf-tournaments'); break;
          case 'finals_reports':
              $enqueue('tournaments.css', 'rf-tournaments');
              $enqueue('ranking.css', 'rf-ranking'); break;
          case 'global-stats':
              $enqueue('stats.css', 'rf-stats'); break;
          case 'info':
              $enqueue('info.css', 'rf-info'); break;
      }
  }, 9);
  // Dequeue por handle/URL
  if (!function_exists('rf_dequeue_legacy_handles')) {
    function rf_dequeue_legacy_handles() {
        $cfg = rf_css_paths();
        global $wp_styles;
        if (empty($wp_styles) || empty($wp_styles->registered)) return;
        foreach ($wp_styles->registered as $handle => $obj) {
            if (strpos($handle, $cfg['handle_prefix']) === 0 || rf_is_blocked_css($obj->src)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
  }
  add_action('wp_enqueue_scripts', 'rf_dequeue_legacy_handles', 20);
  add_action('wp_print_styles',   'rf_dequeue_legacy_handles', PHP_INT_MAX);
  add_filter('style_loader_src', function($src, $handle){ if (rf_is_blocked_css($src)) return false; return $src; }, PHP_INT_MAX, 2);
  add_filter('style_loader_tag', function($html, $handle, $href, $media){ if (rf_is_blocked_css($href)) return ''; return $html; }, PHP_INT_MAX, 4);
  // Kill por salida HTML
  add_action('template_redirect', function(){
      $cfg = rf_css_paths();
      $blocked = preg_quote($cfg['legacy_dir'], '/');
      $allowed = array_map(function($p){ return preg_quote($p, '/'); }, $cfg['except_exact']);
      $allowed_re = $allowed ? '(?!' . implode('|', $allowed) . ')' : '';
      ob_start(function($html) use ($blocked, $allowed_re){
          $re = '/<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\']' . $allowed_re . '([^"\']*' . $blocked . '[^"\']*)["\'][^>]*>\s*/i';
          $html = preg_replace($re, '', $html);
          $re2 = '/<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\']' . $allowed_re . '([^"\']*wp-content\/plugins\/ranking-futbolin\/assets\/css\/[^"\']*)["\'][^>]*>\s*/i';
          return preg_replace($re2, '', $html);
      });
  }, PHP_INT_MAX);
  // Capa DOM
  add_action('wp_head', function(){ ?>
<script>(function(){function isLegacy(h){return /\/wp-content\/plugins\/ranking-futbolin\/assets\/css\//.test(h||"");}function purgeLink(l){try{l.parentNode&&l.parentNode.removeChild(l);}catch(e){} if(l&&l.href)console.info('RF CSS override v3: killed',l.href);}document.querySelectorAll('link[rel="stylesheet"]').forEach(function(l){if(l.href&&isLegacy(l.href)&&!/\/(rf-live\.css|90-compat-override\.css)$/.test(l.href))purgeLink(l);});var obs=new MutationObserver(function(m){m.forEach(function(x){(x.addedNodes||[]).forEach(function(n){if(n.tagName==='LINK'&&(n.rel||'').indexOf('stylesheet')>-1&&n.href&&isLegacy(n.href))purgeLink(n);});});});obs.observe(document.documentElement,{childList:true,subtree:true});})();</script>
<?php }, 0);
}

require_once __DIR__ . '/rf-live-wiring.php'; // RF loader v7

/**
 * Archivo Resultante: ranking-futbolin.php
 * Ruta: (Raíz del plugin)
 * Fuente Original: ranking-futbolin.php (antiguo)
 *
 * Descripción: Versión refactorizada del archivo principal del plugin.
 * Incluye un autoloader recursivo para cargar clases automáticamente
 * y mantiene la lógica original de inicialización y carga de assets.
 */

/**
 * Plugin Name:       Ranking Futbolín API
 * Plugin URI:        https://fefm.es/
 * Description:       Plugin diseñado a medida para la gestión integral del ranking de futbolín de una pierna en España
 * Version:           0.7.5 (Beta)
 * Author:            Héctor Núñez Sáez
 * Author URI:        https://fefm.es/
 */

/**
 * Definición de constantes del plugin para rutas y URLs.
 * Esto hace que el código sea más robusto y fácil de mantener.
 */
define('FUTBOLIN_API_VERSION', '0.7.5');
define('FUTBOLIN_API_PATH', plugin_dir_path(__FILE__));
define('FUTBOLIN_API_URL', plugin_dir_url(__FILE__));

// Carga unificada de assets (front+admin)
include_once FUTBOLIN_API_PATH . 'includes/core/class-futbolin-assets.php';
include_once FUTBOLIN_API_PATH . 'includes/core/class-futbolin-logger.php';
// Cron y tareas programadas (limpieza y precache)
include_once FUTBOLIN_API_PATH . 'includes/cron/class-futbolin-cron.php';
// Gestor de cache de hitos/rankings (nuevo)
if (file_exists(FUTBOLIN_API_PATH . 'includes/cache/class-rf-hitos-cache-manager.php')) {
    require_once FUTBOLIN_API_PATH . 'includes/cache/class-rf-hitos-cache-manager.php';
}
// Página admin para gestionar la cache (botón único + purge)
if (is_admin() && file_exists(FUTBOLIN_API_PATH . 'includes/admin/class-rf-hitos-cache-admin.php')) {
    require_once FUTBOLIN_API_PATH . 'includes/admin/class-rf-hitos-cache-admin.php';
}

/**
 * Helper global: bandera de caché del plugin.
 * Permite deshabilitar TODAS las lecturas/escrituras de transients propias del plugin
 * sin tocar el resto de cachés del sitio (tokens, etc.).
 */
if (!function_exists('rf_cache_enabled')) {
    function rf_cache_enabled(): bool {
        $opt = get_option('rf_cache_enabled', 1);
        $on = (is_numeric($opt) ? intval($opt) : (is_string($opt) ? (int)($opt === '1' || strtolower($opt) === 'on' || strtolower($opt) === 'true') : 1));
        /** Permitir override por filtro (p.ej. querystring temporal) */
        $on = apply_filters('rf_cache_enabled', (bool)$on);
        return (bool)$on;
    }
}

/**
 * Autoloader recursivo de clases. VERSIÓN CORREGIDA Y ROBUSTA.
 * Busca automáticamente cualquier clase que necesitemos dentro de la carpeta /includes/.
 */
spl_autoload_register(function ($class_name) {
    // Solo cargamos clases de nuestro plugin (prefijo 'Futbolin_')
    if (!is_string($class_name) || strpos($class_name, 'Futbolin_') !== 0) {
        return;
    }

    // --- LÓGICA DE CONVERSIÓN CORREGIDA ---
    // 1. Quita el prefijo 'Futbolin_' del nombre de la clase.
    $class_base = str_replace('Futbolin_', '', $class_name);

    // 2. Convierte los guiones bajos '_' a guiones '-' y todo a minúsculas.
    $file_base = strtolower(str_replace('_', '-', $class_base));

    // 3. Construye el nombre final del archivo.
    $file_name = 'class-futbolin-' . $file_base . '.php';

    // Buscamos el archivo de forma recursiva en la carpeta 'includes'
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(FUTBOLIN_API_PATH . 'includes/', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) { continue; }
        // Evitar cargar cualquier clase dentro de carpetas marcadas como _deprecated
        $pathname = $file->getPathname();
        if (strpos($pathname, DIRECTORY_SEPARATOR . '_deprecated' . DIRECTORY_SEPARATOR) !== false) { continue; }
        if ($file->getFilename() === $file_name) {
            require_once $pathname;
            return;
        }
    }
});

/* ============================================================
 * MANTENIMIENTO: helpers + corte (SOLO ranking_futbolin y futbolin_jugador)
 * ============================================================ */

/** Lee el flag desde tus opciones */
if (!function_exists('futbolin_is_maintenance_on')) {
    function futbolin_is_maintenance_on(): bool {
        $opts = get_option('mi_plugin_futbolin_options', []);
        return isset($opts['maintenance_mode']) && $opts['maintenance_mode'] === 'on';
    }
}

/** Marca HTML mínima con cabecera + aviso (sin depender del wrapper) */
if (!function_exists('futbolin_maintenance_static_markup')) {
    function futbolin_maintenance_static_markup(): string {
        ob_start(); require_once __DIR__ . '/includes/core/class-futbolin-css-loader.php';
?>
        <div style="max-width:980px;margin:24px auto;padding:0 16px;">
                    <header style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid #eee;padding-bottom:12px;">
                        <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/fefm.png' ); ?>" alt="FEFM" style="height:40px;width:auto;">
            <div style="display:flex;flex-direction:column;gap:2px;">
              <h1 style="margin:0;font-size:22px;line-height:1.2;">Ranking ELO Futbolín</h1>
              <h2 style="margin:0;color:#777;font-size:14px;font-weight:600;">Una Pierna en España</h2>
            </div>
            <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/es.webp' ); ?>" alt="Bandera de España" style="margin-left:auto;height:48px;width:auto;"/>
          </header>
          <div style="text-align:center;padding:28px;border:2px dashed #d63638;background:#fff5f5;border-radius:10px;">
            <div style="font-size:42px;line-height:1;margin-bottom:8px;">🛠️</div>
            <h3 style="margin:0 0 6px 0;color:#b30000;">Estamos en mantenimiento</h3>
            <p style="margin:0;color:#444;">Volvemos lo antes posible.</p>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Interceptamos la ejecución de shortcodes y devolvemos el aviso en mantenimiento.
 * No tocamos nada más del plugin: si el modo está OFF, se devuelve el render normal.
 */
add_filter('do_shortcode_tag', function($output, $tag, $attr, $m) {
    static $targets = ['ranking_futbolin', 'futbolin_jugador'];
    $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
    $view_as_user = isset($_GET['rf_view_as']) && $_GET['rf_view_as'] === 'user';
    if (in_array($tag, $targets, true) && futbolin_is_maintenance_on() && (!$is_admin || $view_as_user)) {
        return futbolin_maintenance_static_markup();
    }
    return $output;
}, 0, 4);

/* ============================================================
 * Inicialización de las clases del plugin (tu lógica original)
 * ============================================================ */

/**
 * Inicialización de las clases del plugin.
 * Esta lógica es idéntica a la de tu archivo antiguo.
 */
function futbolin_api_init() {
    // === CARGAS EXPLÍCITAS (por si el autoloader no localiza algo a tiempo) ===
    if ( ! class_exists('Futbolin_Shortcode_Router') ) {
        require_once FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-shortcode-router.php';
    }
    if ( ! class_exists('Futbolin_Player_Shortcode') ) {
        require_once FUTBOLIN_API_PATH . 'includes/shortcodes/player/class-futbolin-player-shortcode.php';
    }
    if ( ! class_exists('Futbolin_Champions_Shortcode') ) {
        require_once FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-champions-shortcode.php';
    }
    if ( ! class_exists('Futbolin_H2h_Shortcode') ) {
        require_once FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-h2h-shortcode.php';
    }
    if ( ! class_exists('Futbolin_Tournaments_Shortcode') ) {
        require_once FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-tournaments-shortcode.php';
    }
    if ( ! class_exists('Futbolin_Finals_Shortcode') ) {
        require_once FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-finals-shortcode.php';
    }
    if ( ! class_exists('Futbolin_Ajax') ) {
        require_once FUTBOLIN_API_PATH . 'includes/ajax/class-futbolin-ajax.php';
    }

    if ( is_admin() ) {
        if ( ! class_exists('Futbolin_Admin_Page') ) {
            require_once FUTBOLIN_API_PATH . 'includes/admin/class-futbolin-admin-page.php';
        }
        if ( ! class_exists('Futbolin_Admin_Ajax') ) {
            require_once FUTBOLIN_API_PATH . 'includes/ajax/class-futbolin-admin-ajax.php';
        }
        if ( ! class_exists('Futbolin_HOF_Admin') ) {
            require_once FUTBOLIN_API_PATH . 'includes/admin/class-futbolin-hof-admin.php';
        }
    }

    // === INSTANCIAS (protegidas) ===
    if ( class_exists('Futbolin_Shortcode_Router') ) {
        new Futbolin_Shortcode_Router();
    }
    if ( class_exists('Futbolin_Player_Shortcode') && ! shortcode_exists('futbolin_jugador') ) {
        new Futbolin_Player_Shortcode();
    }
    if ( class_exists('Futbolin_Champions_Shortcode') && ! shortcode_exists('futbolin_campeones_espana') ) {
        new Futbolin_Champions_Shortcode();
    }
    if ( class_exists('Futbolin_Tournaments_Shortcode') && ! shortcode_exists('futbolin_tournaments') ) {
        new Futbolin_Tournaments_Shortcode();
    }
    if ( class_exists('Futbolin_Finals_Shortcode') && ! shortcode_exists('futbolin_finals') ) {
        new Futbolin_Finals_Shortcode();
    }
    if ( class_exists('Futbolin_H2h_Shortcode') && ! shortcode_exists('futbolin_h2h') ) {
        new Futbolin_H2h_Shortcode();
    }
    if ( class_exists('Futbolin_Ajax') ) {
        new Futbolin_Ajax();
    }

    // Ruta pública para listados generados (Rankgen): habilita ?view=rankgen&slug=...
    // Cargamos explícitamente el archivo que registra el filtro the_content
    $rankgen_route = FUTBOLIN_API_PATH . 'includes/public/class-futbolin-rankgen-route.php';
    if ( file_exists($rankgen_route) ) {
        require_once $rankgen_route;
    }

    if ( is_admin() ) {
            // Instanciar la página de admin de forma segura (una sola vez)
            if (!function_exists('rf_bootstrap_admin_page_once')) {
                function rf_bootstrap_admin_page_once() {
                    static $rf_admin_bootstrapped = false;
                    if ($rf_admin_bootstrapped) return;
                    if (!class_exists('Futbolin_Admin_Page')) {
                        $path = FUTBOLIN_API_PATH . 'includes/admin/class-futbolin-admin-page.php';
                        if (file_exists($path)) {
                            require_once $path;
                        }
                    }
                    if (class_exists('Futbolin_Admin_Page')) {
                        new Futbolin_Admin_Page();
                        $rf_admin_bootstrapped = true;
                    }
                }
            }
            rf_bootstrap_admin_page_once();
        if ( class_exists('Futbolin_Admin_Ajax') )  new Futbolin_Admin_Ajax();
        if ( class_exists('Futbolin_HOF_Admin') )   new Futbolin_HOF_Admin();
    }
}

add_action('plugins_loaded', 'futbolin_api_init');

// Fallback defensivo: si por cualquier motivo aún no se ha instanciado la página de admin
// antes de construir el menú, garantizamos su bootstrap aquí (idempotente).
add_action('admin_menu', function(){
    if (function_exists('rf_bootstrap_admin_page_once')) {
        rf_bootstrap_admin_page_once();
    }
}, 1);

// Registrar la opción rf_cache_enabled con default 1
add_action('admin_init', function(){
    if (function_exists('register_setting')) {
        register_setting('mi_plugin_futbolin_option_group', 'rf_cache_enabled', [
            'type' => 'integer', 'default' => 1,
            'sanitize_callback' => function($v){
                // Si este campo no viene en POST (pestañas que no lo muestran), no tocar el valor guardado
                if (!isset($_POST) || !is_array($_POST) || !array_key_exists('rf_cache_enabled', $_POST)) {
                    return (int) get_option('rf_cache_enabled', 1);
                }
                return (int) !!$v;
            }
        ]);
    }
});

/**
 * Carga de estilos y scripts (CSS/JS) para la parte pública.
 * La lógica es la misma, pero usando las constantes para mayor claridad.
 */
function futbolin_api_enqueue_public_assets() {
    global $post;
    $shadow_on = function_exists('rf_shadow_enabled') ? rf_shadow_enabled() : (bool) get_option('rf_shadow_mode', 0);

    $has_our_shortcode = is_a($post, 'WP_Post') && (
        has_shortcode($post->post_content, 'ranking_futbolin') ||
        has_shortcode($post->post_content, 'futbolin_jugador') ||
        has_shortcode($post->post_content, 'futbolin_campeones_espana')
    );
   wp_enqueue_script(
       'lottie-player',
       'https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js',
       [],      // sin dependencias
       null,    // sin versión
       true     // en el footer
   );

    wp_enqueue_style('dashicons');
    if (!$shadow_on) {
        $css_files = [
            '01-variables.css', '02-components.css', '03-layout.css', '04-header.css',
            '05-sidebar-forms.css', '06-sidebar-menu.css', '07-ranking-table.css',
            '08-player-profile.css', '09-h2h.css', '10-pagination.css', '11-ajax-search.css',
            '12-h2h-integration.css', '13-scrollbar.css', '14-tab-content.css', '15-futbolin-h2h.css', '16-final-fixes.css',
            '17-loader.css', '18-ranking-controls.css', '19-player-profile-dynamic.css',
            '20-ranking-category.css', '21-admin-styles.css', '22-ranking-styles.css','23-hall-of-fame-styles.css',
            '24-finals-reports.css', '25-futbolin-tournaments.css', '26-maintenance.css',
            '27-skeleton.css',
        ];
        foreach($css_files as $file) {
            $handle = 'futbolin-style-' . sanitize_title(str_replace('.', '-', $file));
            if ( ! defined('RF_CSS_OVERRIDE_STRONG') ) {
                $path = __DIR__ . '/assets/css/' . $file;
                $v    = file_exists($path) ? filemtime($path) : (defined('FUTBOLIN_API_VERSION') ? FUTBOLIN_API_VERSION : null);
                wp_enqueue_style($handle, FUTBOLIN_API_URL . 'assets/css/' . $file, ['dashicons'], $v);
            }
        }
        // Asegurar 90-compat-override.css (muy tarde) para z-index/overflow y reglas universales
        wp_enqueue_style('rf-compat-override', plugins_url('assets/css/90-compat-override.css', __FILE__), array(), filemtime(__DIR__ . '/assets/css/90-compat-override.css'));
    }
    
    wp_enqueue_script('futbolin-main-js', FUTBOLIN_API_URL . 'assets/js/main.js', ['jquery'], FUTBOLIN_API_VERSION, true);
    // Evitar interferencia del pager HOF en rankgen: no encolar en view=rankgen
    $current_view = isset($_GET['view']) ? sanitize_key($_GET['view']) : '';
    if ($current_view !== 'rankgen') {
        wp_enqueue_script('futbolin-hall-of-fame-js', FUTBOLIN_API_URL . 'assets/js/hall-of-fame-pager.js', ['jquery'], FUTBOLIN_API_VERSION, true);
    }
    // Script del filtro del ranking
    wp_enqueue_script(
        'futbolin-ranking',
        FUTBOLIN_API_URL . 'assets/js/futbolin-ranking.js',
        [],               // sin dependencias
        '1.0.2',          // sube versión para romper caché si cambias el archivo
        true              // en el footer
    );

    // Back-to-top: usa solo el nuevo #rf-btt y elimina el legado
    wp_enqueue_script('rf-btt', plugins_url('assets/js/rf-btt.js', __FILE__), array(), filemtime(__DIR__ . '/assets/js/rf-btt.js'), true);

    $options = get_option('mi_plugin_futbolin_options');
    $profile_page_id = isset($options['player_profile_page_id']) ? intval($options['player_profile_page_id']) : 0;
    $ranking_page_id = isset($options['ranking_page_id']) ? intval($options['ranking_page_id']) : 0;
    $profile_page_url = $profile_page_id ? get_permalink($profile_page_id) : '';

    wp_localize_script('futbolin-main-js', 'futbolin_ajax_obj', [
        'ajax_url'    => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('futbolin_nonce'),
        'profile_url' => $profile_page_url,
    ]);

    // Script de carga perezosa de pestañas del perfil (después de main.js)
    wp_enqueue_script('futbolin-player-tabs-lazy', FUTBOLIN_API_URL . 'assets/js/rf-player-tabs-lazy.js', ['futbolin-main-js'], FUTBOLIN_API_VERSION, true);

    // Ocultar el título de página en las páginas del plugin (creadas/seleccionadas o con nuestros shortcodes)
    add_action('wp_head', function() use ($post, $has_our_shortcode, $profile_page_id, $ranking_page_id){
        if (!is_singular()) return;
        $is_plugin_page = false;
        if (is_a($post, 'WP_Post')) {
            $is_plugin_page = $has_our_shortcode;
            if (!$is_plugin_page) {
                $pid = (int)$post->ID;
                if ($pid && ($pid === (int)$profile_page_id || $pid === (int)$ranking_page_id)) {
                    $is_plugin_page = true;
                }
            }
        }
        if (!$is_plugin_page) return;
        echo '<style id="rf-hide-page-title">';
        echo '/* Ocultar título en páginas del plugin */\n';
        // Selectores comunes de temas y constructores (Astra, Gutenberg, Beaver Builder, Divi, etc.)
        echo '.entry-title, h1.entry-title, .page-title, .ast-title, .site-main h1, .elementor-page-title,'
           . ' .ast-page-builder-template .entry-title,'
           . ' header.entry-header .entry-title,'
           . ' .wp-block-post-title,'
           . ' .fl-post-title,'
           . ' .et_pb_title_container h1'
           . '{display:none !important;}\n';
        echo '</style>';
    }, 99);
}
add_action('wp_enqueue_scripts', 'futbolin_api_enqueue_public_assets');

/**
 * Funciones de Ayuda y Misceláneas.
 * Código original mantenido y limpiado (había un bloque duplicado).
 */
add_filter('http_request_args', function($args, $url) {
    $base = class_exists('Futbolin_API') && method_exists('Futbolin_API', 'get_base_url') ? Futbolin_API::get_base_url() : '';
    if ($base) {
        $host = parse_url($base, PHP_URL_HOST);
        if ($host && strpos((string)($url ?? ''), (string)$host) !== false) {
            $args['sslverify'] = false;
        }
    }
    return $args;
}, 10, 2);
add_action('wp_footer', function() {
  if (is_admin()) { return; }
  require_once __DIR__ . '/includes/core/class-futbolin-css-loader.php';
?>
  <div id="futbolin-loader-overlay" class="futbolin-loader-hidden force-motion">
    <div class="futbolin-loader-content">

      <!-- Lottie opcional (ajusta tamaño con .lottie-2x en tu CSS) -->
      <lottie-player
        class="lottie-2x"
        src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/animations/loader-animado.json' ); ?>"
        background="transparent"
        speed="1"
        loop
        autoplay
      ></lottie-player>

      <!-- Texto + puntos -->
      <div class="futbolin-loader-text">
        Obteniendo datos <span class="dots"><i></i><i></i><i></i></span>
      </div>

    </div>
  </div>
  <?php
});
// HTTP settings (timeout/retries)
require_once __DIR__ . '/includes/core/class-futbolin-http.php';
add_action('plugins_loaded', function(){ if (class_exists('Futbolin_HTTP')) { Futbolin_HTTP::boot(); } });

if (is_admin()) { require_once FUTBOLIN_API_PATH . 'includes/admin/class-futbolin-rankgen-handlers.php'; }

require_once FUTBOLIN_API_PATH . 'includes/public/class-futbolin-rankgen-route.php';

require_once FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-rankgen-shortcode.php';
if (is_admin()) { require_once FUTBOLIN_API_PATH . 'includes/admin/class-futbolin-rankgen-ajax.php'; }


// Encola H2H patch22 (cuadro H2H + rótulo DATOS GLOBALES + self-ignore)
if (!function_exists('rf_enqueue_h2h_patch22')) {
  function rf_enqueue_h2h_patch22(){
    if (is_admin()) return;
    // Sólo si existe la caja de historial en la página
    // Se inyecta en footer para no depender de jQuery ni orden de carga
    wp_enqueue_script('rf-futpatch22-h2h', plugins_url('assets/js/futpatch22-h2h.js', __FILE__), array(), '0.22.4', true);
  }
  add_action('wp_enqueue_scripts','rf_enqueue_h2h_patch22', 99);
}


// CSS de compatibilidad para Astra/constructores: fuerza nuestro layout por encima del tema.
if (!function_exists('rf_enqueue_compat_css')) {
  function rf_enqueue_compat_css(){
    if (is_admin()) return;
                if (function_exists('rf_shadow_enabled') ? rf_shadow_enabled() : get_option('rf_shadow_mode', 0)) return; // En Shadow, no cargamos compat global
    // Cargamos tras todo lo demás para ganar la cascada.
    wp_enqueue_style('rf-compat-override', plugins_url('assets/css/90-compat-override.css', __FILE__), array(), defined('FUTBOLIN_API_VERSION')?FUTBOLIN_API_VERSION:null);
  }
  add_action('wp_enqueue_scripts','rf_enqueue_compat_css', 999);
}



/**
 * === RF Shadow Mode (opcional) ===
 */
add_action('admin_init', function() {
    register_setting('rf_settings_group', 'rf_shadow_mode', ['type' => 'boolean', 'default' => 0]);
    add_settings_section('rf_main_sec', 'Opciones de Ranking Futbolín', function(){}, 'rf_settings_page');
    add_settings_field('rf_shadow_mode', 'Modo aislado (Shadow DOM)', function(){
        $val = get_option('rf_shadow_mode', 0);
        echo '<label><input type="checkbox" name="rf_shadow_mode" value="1" ' . checked(1, $val, false) . '> Activar</label>';
        echo '<p class="description">Aísla los estilos del ranking para que no los afecte el tema/maquetador.</p>';
    }, 'rf_settings_page', 'rf_main_sec');
});
add_action('admin_menu', function() {
    // Ajustes trasladados a "Futbolín API → Avanzado".
    // Se mantiene el hook vacío para compatibilidad sin añadir entrada en "Ajustes".
});

add_action('wp_enqueue_scripts', function() {
    // Modo Shadow: opción global con override por query (?rf_shadow=1/0)
    $shadow_on = function_exists('rf_shadow_enabled') ? rf_shadow_enabled() : (bool) get_option('rf_shadow_mode', 0);
    if (!$shadow_on) return;
    // Versionado robusto para evitar cachés: en debug usa time(), en prod usa filemtime()
    $js_path = __DIR__ . '/assets/js/rf-shadow.js';
    $ver = (defined('WP_DEBUG') && WP_DEBUG) ? time() : (@filemtime($js_path) ?: null);
    $base = plugin_dir_url(__FILE__);
    // Encolar en HEAD para envolver pronto el contenido y evitar que otros scripts del tema modifiquen el DOM antes del Shadow
    wp_enqueue_script('rf-shadow', $base . 'assets/js/rf-shadow.js', [], $ver, false);
    // Preferir CSS purged dentro del Shadow si existen bundles; fallback a assets/*
    $purged_dir = __DIR__ . '/dist/assets/css-purged/';
    $purged_url = plugins_url('dist/assets/css-purged/', __FILE__);
    $has_purged = (file_exists($purged_dir . 'core.css') && file_exists($purged_dir . 'components.css'));
    $css_urls = [];
    if ($has_purged) {
        // Append filemtime as cache-busting query to ensure fresh CSS inside Shadow
        $core_v = @filemtime($purged_dir . 'core.css') ?: false;
        $comp_v = @filemtime($purged_dir . 'components.css') ?: false;
        $css_urls[] = $purged_url . 'core.css' . ($core_v ? ('?v=' . $core_v) : '');
        $css_urls[] = $purged_url . 'components.css' . ($comp_v ? ('?v=' . $comp_v) : '');
        // Heurística de vista: perfil / h2h / torneos / stats / info / ranking
        $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $view = isset($_GET['view']) ? (string)$_GET['view'] : '';
        // Detecta Perfil en más casos: slug dedicado, jugador_id o player_id, o view explícita
        $is_profile = (
            (strpos($uri, '/perfil-jugador') !== false)
            || (isset($_GET['jugador_id']) && $_GET['jugador_id'] !== '')
            || (isset($_GET['player_id']) && $_GET['player_id'] !== '')
            || (isset($_GET['view']) && in_array($_GET['view'], ['perfil','profile','player'], true))
        );
        $is_h2h = (strpos($uri, '/h2h') !== false) || ($view === 'h2h');
        if ($is_profile && file_exists($purged_dir . 'perfil.css')) {
            $pf_v = @filemtime($purged_dir . 'perfil.css') ?: false;
            $css_urls[] = $purged_url . 'perfil.css' . ($pf_v ? ('?v=' . $pf_v) : '');
        } elseif ($is_h2h && file_exists($purged_dir . 'h2h.css')) {
            $h2h_v = @filemtime($purged_dir . 'h2h.css') ?: false;
            $css_urls[] = $purged_url . 'h2h.css' . ($h2h_v ? ('?v=' . $h2h_v) : '');
        } elseif (in_array($view, ['tournaments','tournament-stats','finals_reports'], true) && file_exists($purged_dir . 'tournaments.css')) {
            $tor_v = @filemtime($purged_dir . 'tournaments.css') ?: false;
            $css_urls[] = $purged_url . 'tournaments.css' . ($tor_v ? ('?v=' . $tor_v) : '');
        } elseif ($view === 'info' && file_exists($purged_dir . 'info.css')) {
            $info_v = @filemtime($purged_dir . 'info.css') ?: false;
            $css_urls[] = $purged_url . 'info.css' . ($info_v ? ('?v=' . $info_v) : '');
        } elseif (in_array($view, ['global-stats','hall-of-fame','rankgen'], true) && file_exists($purged_dir . 'stats.css')) {
            $st_v = @filemtime($purged_dir . 'stats.css') ?: false;
            $css_urls[] = $purged_url . 'stats.css' . ($st_v ? ('?v=' . $st_v) : '');
        } elseif (file_exists($purged_dir . 'ranking.css')) {
            $rk_v = @filemtime($purged_dir . 'ranking.css') ?: false;
            $css_urls[] = $purged_url . 'ranking.css' . ($rk_v ? ('?v=' . $rk_v) : '');
        }
        // Nota: No añadimos assets legacy (20/26/90) porque ya están integrados en los bundles purgados (ranking/core/info)
    } else {
        $css_urls = array_map(function($rel) use ($base){ return $base . ltrim($rel, '/'); }, [
            "assets/css/90-compat-override.css", "assets/css/01-variables.css", "assets/css/02-components.css", "assets/css/03-layout.css", "assets/css/04-header.css",
            "assets/css/05-sidebar-forms.css", "assets/css/06-sidebar-menu.css", "assets/css/07-ranking-table.css",
            "assets/css/08-player-profile.css", "assets/css/09-h2h.css", "assets/css/10-pagination.css", "assets/css/11-ajax-search.css",
            "assets/css/12-h2h-integration.css", "assets/css/13-scrollbar.css", "assets/css/14-tab-content.css", "assets/css/15-futbolin-h2h.css", "assets/css/16-final-fixes.css",
            "assets/css/17-loader.css", "assets/css/18-ranking-controls.css", "assets/css/19-player-profile-dynamic.css",
            "assets/css/20-ranking-category.css", "assets/css/22-ranking-styles.css", "assets/css/23-hall-of-fame-styles.css", "assets/css/24-finals-reports.css",
            "assets/css/25-futbolin-torneos.css", "assets/css/25-futbolin-tournaments.css", "assets/css/26-maintenance.css", "assets/css/27-skeleton.css"
        ]);
    }
    // Fallback extra: si por cualquier motivo cssUrls está vacío, añade un mínimo viable
    if (empty($css_urls)) {
        $css_urls = array_map(function($rel) use ($base){ return $base . ltrim($rel, '/'); }, [
            "assets/css/01-variables.css",
            "assets/css/02-components.css",
            "assets/css/08-player-profile.css",
            "assets/css/22-ranking-styles.css",
            "assets/css/27-skeleton.css"
        ]);
    }
    wp_localize_script('rf-shadow', 'rfShadowSettings', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'wrapperSelector' => '.futbolin-full-bleed-wrapper',
        'cssUrls' => $css_urls,
        // Debug opcional del CSS del Shadow: ?rf_debug_shadow_css=1
        'debugCss' => isset($_GET['rf_debug_shadow_css']) && $_GET['rf_debug_shadow_css'] == '1',
        // Tiempo de seguridad del overlay (ms) para autoocultarse si algo va mal; 15000 por defecto
        'safetyMs' => 15000,
    ]);
}, 5);



// === Cache bust para datasets estáticos (cambian pocas veces al año) ===
if (!function_exists('rf_dataset_bump_cache_version')) {
    function rf_dataset_bump_cache_version() {
        $v = get_option('rf_dataset_ver');
        $nv = $v ? (string)(intval($v) + 1) : '1';
        update_option('rf_dataset_ver', $nv, false);
        do_action('rf_dataset_cache_busted', $nv);
        return $nv;
    }
}
// Permitir a importadores notificar el fin de importación
add_action('rf/cache_bust', 'rf_dataset_bump_cache_version');

// WP-CLI: wp rf cache-bust (blindado para entornos sin WP-CLI)
if (defined('WP_CLI') && constant('WP_CLI') && class_exists('WP_CLI')) {
    call_user_func(['WP_CLI', 'add_command'], 'rf cache-bust', function() {
        $nv = rf_dataset_bump_cache_version();
        call_user_func(['WP_CLI', 'success'], 'RF dataset cache version bumped to v' . $nv);
    });
}


/** RF clean BackToTop enqueue (standalone) */
add_action('wp_enqueue_scripts', function(){
    if (is_admin()) return;
    wp_enqueue_script('rf-btt', plugins_url('assets/js/rf-btt.js', __FILE__), array(), '1.0.0', true);
}, 100);

// Kill-switch tardío: eliminar cualquier CSS purged que se haya colado por otras rutas
add_action('wp_print_styles', function(){
    // Si explícitamente NO queremos purged (RF_ENABLE_PURGED=false), limpiarlo tardíamente
    if (defined('RF_ENABLE_PURGED') && RF_ENABLE_PURGED === false) {
        global $wp_styles;
        if (!$wp_styles || !is_array($wp_styles->queue)) return;
        foreach ($wp_styles->queue as $handle) {
            if (empty($wp_styles->registered[$handle])) continue;
            $src = isset($wp_styles->registered[$handle]->src) ? (string)$wp_styles->registered[$handle]->src : '';
            if ($src && strpos($src, '/dist/assets/css-purged/') !== false) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}, 100000);


// === Debug: volcado de estilos encolados (solo admin, bajo demanda) ===
add_action('wp_footer', function(){
    if (!function_exists('current_user_can') || !current_user_can('manage_options')) return;
    if (!isset($_GET['rf_debug_styles']) || $_GET['rf_debug_styles'] != '1') return;
    global $wp_styles;
    if (!$wp_styles || !is_array($wp_styles->queue)) return;
    $flag = defined('RF_CSS_OVERRIDE_STRONG') ? '1' : '0';
    echo '<div style="margin:24px 0;padding:12px;border:2px dashed #999;border-radius:8px;background:#f9fafb;color:#111;font:13px/1.4 system-ui">';
    echo '<strong>RF DEBUG STYLES</strong> · override_strong=' . esc_html($flag) . '<br/>';
    echo '<ol style="margin:8px 0 0 18px;">';
    foreach ($wp_styles->queue as $handle) {
        if (empty($wp_styles->registered[$handle])) continue;
        $src = isset($wp_styles->registered[$handle]->src) ? (string)$wp_styles->registered[$handle]->src : '';
        $origin = 'other';
        if (strpos($src, '/dist/assets/css-purged/') !== false)      { $origin = 'purged'; }
        elseif (strpos($src, '/dist/assets/css-min/') !== false)     { $origin = 'min'; }
        elseif (strpos($src, '/assets/css/') !== false)              { $origin = 'assets'; }
        elseif (strpos($src, '/flat/') !== false)                    { $origin = 'flat'; }
        elseif (strpos($src, '/public/css/') !== false)              { $origin = 'public'; }
        echo '<li><code>' . esc_html($handle) . '</code> — <em>' . esc_html($origin) . '</em> — <span style="word-break:break-all">' . esc_html($src) . '</span></li>';
    }
    echo '</ol></div>';
});



// === RF CSS variant switcher (safe rewrite of CSS src) ======================
// Use ?rf_css_variant=min  or ?rf_css_variant=purged   (default: original assets)
add_action('wp_enqueue_scripts', function(){
    if (!isset($_GET['rf_css_variant'])) return;
    $variant = sanitize_text_field($_GET['rf_css_variant']);
    if (empty($variant)) { $variant = 'assets'; }
$dir = '/assets/css/';
    if ($variant === 'min') { $dir = '/dist/assets/css-min/'; }
    elseif ($variant === 'purged') { $dir = '/dist/assets/css-purged/'; }
    if (!$dir) return;
    global $wp_styles;
    if (!($wp_styles instanceof WP_Styles)) return;
    foreach ($wp_styles->registered as $handle => $st) {
        if (!isset($st->src) || !is_string($st->src)) continue;
        // No reescribir el skeleton: no existe en dist y perderíamos el estilo de cargando
        if (preg_match('#/27-skeleton\.css(?:\?|$)#', (string)$st->src)) {
            continue;
        }
        if (strpos($st->src, '/assets/css/') !== false) {
            $st->src = str_replace('/assets/css/', $dir, $st->src);
        } elseif (strpos($st->src, '/dist/assets/css/') !== false) {
            // normalize any existing dist path to our chosen variant
            $pos = strpos($st->src, '/dist/assets/css/');
            $st->src = substr($st->src, 0, $pos) . $dir . substr($st->src, $pos + strlen('/dist/assets/css/'));
        }
    }
}, 999);
// ============================================================================


// Hook de activación: inicializa opciones por defecto (todo activado) si es instalación nueva
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, function(){
        $existing = get_option('mi_plugin_futbolin_options', null);
        if (!is_array($existing)) {
            $defaults = [
                'show_champions'           => 'on',
                'show_tournaments'         => 'on',
                'show_hall_of_fame'        => 'on',
                'show_finals_reports'      => 'on',
                'show_global_stats'        => 'on',
                'enable_fefm_no1_club'     => 'on',
                'enable_club_500_played'   => 'on',
                'enable_club_100_winners'  => 'on',
                'enable_top_rivalries'     => 'on',
                'enable_player_profile'    => 'on',
                'show_player_summary'      => 'on',
                'show_player_stats'        => 'on',
                'show_player_history'      => 'on',
                'show_player_glicko'       => 'on',
                'show_player_hitos'        => 'on',
                'show_player_torneos'      => 'on',
                'maintenance_mode'         => 'off',
                'enable_annual_doubles'    => 'on',
                'enable_annual_individual' => 'on',
                'default_modalidad'        => 2,
                'default_modalidad_anual'  => 2,
                'default_profile_preview_id' => 4,
            ];
            add_option('mi_plugin_futbolin_options', $defaults, '', false);
        }
        if (get_option('rf_cache_enabled', null) === null) {
            add_option('rf_cache_enabled', 1, '', false);
        }
        // Logging activado por defecto si no existe
        $opts = get_option('mi_plugin_futbolin_options', []);
        if (!is_array($opts)) { $opts = []; }
        if (!array_key_exists('rf_logging_enabled', $opts)) {
            $opts['rf_logging_enabled'] = 1;
            update_option('mi_plugin_futbolin_options', $opts, false);
        }
        if (class_exists('Futbolin_Cron')) { Futbolin_Cron::schedule_cleanup_daily(); }
    });
    register_deactivation_hook(__FILE__, function(){ if (class_exists('Futbolin_Cron')) { Futbolin_Cron::unschedule_cleanup(); } });
}

// En entornos existentes: asegurar que rf_logging_enabled tenga default 1 si aún no existe.
add_action('admin_init', function(){
    $opts = get_option('mi_plugin_futbolin_options', []);
    if (!is_array($opts)) { $opts = []; }
    if (!array_key_exists('rf_logging_enabled', $opts)) {
        $opts['rf_logging_enabled'] = 1;
        update_option('mi_plugin_futbolin_options', $opts, false);
    }
    // Defaults de cron si faltan (para que los checkboxes no aparezcan vacíos tras instalación/migración)
    $changed = false;
    if (!array_key_exists('rf_cron_precache_enabled', $opts)) { $opts['rf_cron_precache_enabled'] = 1; $changed = true; }
    if (!array_key_exists('rf_cron_cleanup_enabled', $opts)) { $opts['rf_cron_cleanup_enabled'] = 1; $changed = true; }
    if (!array_key_exists('rf_precache_top_n', $opts)) { $opts['rf_precache_top_n'] = 100; $changed = true; }
    if (!array_key_exists('rf_precache_time_budget', $opts)) { $opts['rf_precache_time_budget'] = 180; $changed = true; }
    if ($changed) { update_option('mi_plugin_futbolin_options', $opts, false); }
});

