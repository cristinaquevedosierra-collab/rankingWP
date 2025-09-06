<?php
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
 * Version:           0.7.2 (Beta)
 * Author:            Héctor Núñez Sáez
 * Author URI:        https://fefm.es/
 */

/**
 * Definición de constantes del plugin para rutas y URLs.
 * Esto hace que el código sea más robusto y fácil de mantener.
 */
define('FUTBOLIN_API_VERSION', '0.7.2');
define('FUTBOLIN_API_PATH', plugin_dir_path(__FILE__));
define('FUTBOLIN_API_URL', plugin_dir_url(__FILE__));

// Carga unificada de assets (front+admin)
include_once FUTBOLIN_API_PATH . 'includes/core/class-futbolin-assets.php';

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
        if ($file->isFile() && $file->getFilename() === $file_name) {
            require_once $file->getPathname();
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
        ob_start(); ?>
        <div style="max-width:980px;margin:24px auto;padding:0 16px;">
          <header style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid #eee;padding-bottom:12px;">
            <img src="https://fefm.es/wp-content/uploads/2025/05/2.png" alt="FEFM" style="height:40px;width:auto;">
            <div style="display:flex;flex-direction:column;gap:2px;">
              <h1 style="margin:0;font-size:22px;line-height:1.2;">Ranking de Futbolín</h1>
              <h2 style="margin:0;color:#777;font-size:14px;font-weight:600;">Una Pierna en España</h2>
            </div>
            <span style="margin-left:auto;font-size:24px;">🇪🇸</span>
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
    if (in_array($tag, $targets, true) && futbolin_is_maintenance_on()) {
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

    if ( is_admin() ) {
        if ( class_exists('Futbolin_Admin_Page') )  new Futbolin_Admin_Page();
        if ( class_exists('Futbolin_Admin_Ajax') )  new Futbolin_Admin_Ajax();
        if ( class_exists('Futbolin_HOF_Admin') )   new Futbolin_HOF_Admin();
    }
}

add_action('plugins_loaded', 'futbolin_api_init');

/**
 * Carga de estilos y scripts (CSS/JS) para la parte pública.
 * La lógica es la misma, pero usando las constantes para mayor claridad.
 */
function futbolin_api_enqueue_public_assets() {
    global $post;

    $has_our_shortcode = is_a($post, 'WP_Post') && (
        has_shortcode($post->post_content, 'ranking_futbolin') ||
        has_shortcode($post->post_content, 'futbolin_jugador') ||
        has_shortcode($post->post_content, 'futbolin_campeones_espana')
    );

    if (!$has_our_shortcode) {
        return;
    }

   wp_enqueue_script(
       'lottie-player',
       'https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js',
       [],      // sin dependencias
       null,    // sin versión
       true     // en el footer
   );

    wp_enqueue_style('dashicons');

    $css_files = [
        '01-variables.css', '02-components.css', '03-layout.css', '04-header.css',
        '05-sidebar-forms.css', '06-sidebar-menu.css', '07-ranking-table.css',
        '08-player-profile.css', '09-h2h.css', '10-pagination.css', '11-ajax-search.css',
        '12-h2h-integration.css', '13-scrollbar.css', '14-tab-content.css', '15-futbolin-h2h.css', '16-final-fixes.css',
        '17-loader.css', '18-ranking-controls.css', '19-player-profile-dynamic.css',
        '20-ranking-category.css', '21-admin-styles.css', '22-ranking-styles.css','23-hall-of-fame-styles.css',
        '24-finals-reports.css', '25-futbolin-tournaments.css', '26-maintenance.css',
    ];

    foreach($css_files as $file) {
        $handle = 'futbolin-style-' . sanitize_title(str_replace('.', '-', $file));
        wp_enqueue_style($handle, FUTBOLIN_API_URL . 'assets/css/' . $file, ['dashicons'], FUTBOLIN_API_VERSION);
    }
    
    wp_enqueue_script('futbolin-main-js', FUTBOLIN_API_URL . 'assets/js/main.js', ['jquery'], FUTBOLIN_API_VERSION, true);
    wp_enqueue_script('futbolin-hall-of-fame-js', FUTBOLIN_API_URL . 'assets/js/hall-of-fame-pager.js', ['jquery'], FUTBOLIN_API_VERSION, true);
    // 👇 AÑADE ESTA LÍNEA: script del filtro del ranking
    wp_enqueue_script(
        'futbolin-ranking',
        FUTBOLIN_API_URL . 'assets/js/futbolin-ranking.js',
        [],               // sin dependencias
        '1.0.2',          // sube versión para romper caché si cambias el archivo
        true              // en el footer
    );

    $options = get_option('mi_plugin_futbolin_options');
    $profile_page_id = isset($options['player_profile_page_id']) ? intval($options['player_profile_page_id']) : 0;
    $profile_page_url = $profile_page_id ? get_permalink($profile_page_id) : '';

    wp_localize_script('futbolin-main-js', 'futbolin_ajax_obj', [
        'ajax_url'    => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('futbolin_nonce'),
        'profile_url' => $profile_page_url,
    ]);
}
add_action('wp_enqueue_scripts', 'futbolin_api_enqueue_public_assets');

/**
 * Funciones de Ayuda y Misceláneas.
 * Código original mantenido y limpiado (había un bloque duplicado).
 */
add_filter('http_request_args', function($args, $url) {
    if (strpos((string)($url ?? ''), 'illozapatillo.zapto.org') !== false) {
        $args['sslverify'] = false;
    }
    return $args;
}, 10, 2);

add_action('wp_footer', function() {
  if (is_admin()) { return; }
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
