<?php
/**
 * Archivo: includes/shortcodes/player/class-futbolin-player-shortcode.php
 * Descripción: Gestiona el shortcode [futbolin_jugador].
 */
if (!defined('ABSPATH')) exit;

// Stubs seguros para que el editor no marque como "función indefinida" fuera del runtime de WordPress
// En WordPress real, estas funciones ya existen y estos stubs no se definen.
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { return false; }
}
if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback) { return false; }
}
if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '') { return array_merge((array)$pairs, (array)$atts); }
}
if (!function_exists('remove_query_arg')) {
    function remove_query_arg($keys, $uri = '') { return $uri; }
}

class Futbolin_Player_Shortcode {

    private $api_client;

    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api_client = new Futbolin_API_Client();
        }

        // If WordPress shortcode API is already available, register immediately.
        if (function_exists('add_shortcode')) {
            add_shortcode('futbolin_jugador', [$this, 'render_player_profile']);
        }
        // If add_shortcode is not yet available but add_action is (normal WP bootstrap),
        // defer registration to 'init' so we don't call an undefined function during static analysis.
        elseif (function_exists('add_action')) {
            add_action('init', [$this, 'register_shortcode']);
        }
        // Otherwise (e.g. static analysis / non-WP context) skip registration silently.
    }

    /**
     * Register shortcode when WP has fully initialized.
     */
    private function register_shortcode() {
        if (function_exists('add_shortcode')) {
            add_shortcode('futbolin_jugador', [$this, 'render_player_profile']);
        }
    }

    /**
     * Normaliza flags "on/off"/true/false/"1"/"0" a booleano.
     */
    private function normalize_flag($val, $default = false) : bool {
        if (is_bool($val)) return $val;
        if ($val === null) return (bool)$default;
        $val = is_string($val) ? strtolower(trim($val)) : $val;
        $truthy = ['on','1','true','yes','y','si','sí'];
        $falsy  = ['off','0','false','no','n'];
        if (is_string($val)) {
            if (in_array($val, $truthy, true)) return true;
            if (in_array($val, $falsy,  true)) return false;
        }
        return (bool)$val;
    }

    /**
     * Shortcode: [futbolin_jugador id="123" enable="on" summary="off" stats="on" history="on" h2h="on" glicko="off"]
     */
    public function render_player_profile($atts) {
        // --- Atributos con override opcional ---
        $atts = shortcode_atts([
            'id'      => 0,
            // overrides (opcionales)
            'enable'  => null,
            'summary' => null,
            'stats'   => null,
            'history' => null,
            'torneos' => null,
            'hitos'   => null,'glicko'  => null,
        ], $atts, 'futbolin_jugador');

        // ID por querystring como fallback
        $jugador_id = intval($atts['id']);
        if ($jugador_id <= 0) {
            $jugador_id = isset($_GET['jugador_id']) ? intval($_GET['jugador_id']) : 0;
        }
        if ($jugador_id <= 0) {
            return '<div class="futbolin-card"><p>ID de jugador no válido.</p></div>';
        }

        // --- Opciones del plugin ---
        $plugin_options = get_option('mi_plugin_futbolin_options', []);
        $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
        $view_as_user = isset($_GET['rf_view_as']) && $_GET['rf_view_as'] === 'user';

        // Prioridad: si está activo el modo mantenimiento y estamos "viendo como usuario" (o no somos admin),
        // devolver la pantalla de mantenimiento completa en lugar de la plantilla minimal de perfil deshabilitado.
        $maintenance_on = isset($plugin_options['maintenance_mode']) && $plugin_options['maintenance_mode'] === 'on';
        if ($maintenance_on && (!$is_admin || $view_as_user)) {
            // Reutilizar el wrapper como hace el router, para mantener cabecera y estilos coherentes
            ob_start();
            $show_back_btn    = false;
            $hide_sidebar     = true;
            $current_view     = 'maintenance';
            $template_to_load = 'maintenance-display.php';
            $wrapper_path = FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
            if (file_exists($wrapper_path)) {
                include $wrapper_path;
            } else {
                // Fallback a la marca estática si el wrapper no está disponible
                if (function_exists('futbolin_maintenance_static_markup')) {
                    echo futbolin_maintenance_static_markup();
                } else {
                    echo '<div class="futbolin-card" style="text-align:center;padding:28px;border:2px dashed #d63638;background:#fff5f5;border-radius:10px;"><div style="font-size:42px;line-height:1;margin-bottom:8px;">🛠️</div><h3 style="margin:0 0 6px 0;color:#b30000;">Estamos en mantenimiento</h3><p style="margin:0;color:#444;">Volvemos lo antes posible.</p></div>';
                }
            }
            return ob_get_clean();
        }

        // Flags desde admin con override por shortcode (si se especifica)
        $enable_player_profile = $this->normalize_flag(
            $atts['enable'],
            isset($plugin_options['enable_player_profile']) ? ($plugin_options['enable_player_profile'] === 'on') : true);
        $show_player_summary = $this->normalize_flag(
            $atts['summary'],
            isset($plugin_options['show_player_summary']) ? ($plugin_options['show_player_summary'] === 'on') : true);
        $show_player_stats = $this->normalize_flag(
            $atts['stats'],
            isset($plugin_options['show_player_stats']) ? ($plugin_options['show_player_stats'] === 'on') : true);
                $show_player_hitos = $this->normalize_flag(
            isset($atts['hitos']) ? $atts['hitos'] : null,
            isset($plugin_options['show_player_hitos']) ? ($plugin_options['show_player_hitos'] === 'on') : true);
$show_player_history = $this->normalize_flag(
            $atts['history'],
            isset($plugin_options['show_player_history']) ? ($plugin_options['show_player_history'] === 'on') : true);
        $show_player_torneos = $this->normalize_flag(
            isset($atts['torneos']) ? $atts['torneos'] : null,
            isset($plugin_options['show_player_torneos']) ? ($plugin_options['show_player_torneos'] === 'on') : false);
        $show_player_glicko = $this->normalize_flag(
            $atts['glicko'],
            isset($plugin_options['show_player_glicko']) && $plugin_options['show_player_glicko'] === 'on'
        );

        
        $show_player_h2h = $this->normalize_flag(
            isset($atts['h2h']) ? $atts['h2h'] : null,
            isset($plugin_options['show_player_h2h']) ? ($plugin_options['show_player_h2h'] === 'on') : false
        );
// ⛔ Si el admin desactiva el perfil completo → usuarios normales ven aviso; admin puede ver
    // Nota: $is_admin y $view_as_user ya calculados arriba
    if (!$enable_player_profile && (!$is_admin || $view_as_user)) {
            return $this->render_player_disabled_minimal();
        }

        // --- Carga de datos principal ---
        if (!$this->api_client || !method_exists($this->api_client, 'get_datos_jugador')) {
            return '<div class="futbolin-card"><p>No se pudo inicializar el cliente de la API.</p></div>';
        }

        $player1_data = $this->api_client->get_datos_jugador($jugador_id);
        if (!$player1_data) {
            return '<div class="futbolin-card"><p>No se pudo encontrar al jugador solicitado.</p></div>';
        }

    // SSR rápido: evitar llamadas pesadas; las pestañas cargarán datos por AJAX
    $partidos1_items   = [];
    $posiciones1_items = [];

        // SSR ligero: no construir Processor para no bloquear el render inicial.
        $processor = null;
        // Exponer datos básicos para el banner (nombre/id)
        $player_details = (object)[];
        if (is_object($player1_data)) {
            $player_details = $player1_data;
        } elseif (is_array($player1_data)) {
            $tmp = (object)$player1_data;
            $player_details = $tmp;
        }
        // Precalcular hitos mínimos para el banner (campeones + Nº1 si es posible)
        $hitos_header = [];
        if ($this->api_client && method_exists($this->api_client, 'get_campeones_index')) {
            try {
                $idx = $this->api_client->get_campeones_index();
                if (is_array($idx) && isset($idx[$jugador_id])) {
                    $hitos_header = [
                        'campeon_esp_dobles_anios'     => isset($idx[$jugador_id]['dobles']) ? (array)$idx[$jugador_id]['dobles'] : [],
                        'campeon_esp_individual_anios' => isset($idx[$jugador_id]['individual']) ? (array)$idx[$jugador_id]['individual'] : [],
                    ];
                }
            } catch (\Throwable $e) { /* silencioso */ }
        }
        // Intentar también Nº1 desde servicio central si existe
        if (class_exists('Futbolin_Rankgen_Service')) {
            try {
                $podium = \Futbolin_Rankgen_Service::get_player_podium_years((string)$jugador_id);
                if (is_array($podium)) {
                    $hitos_header['numero1_temporada_open_dobles_anios']     = isset($podium['dobles']['no1']) ? (array)$podium['dobles']['no1'] : [];
                    $hitos_header['numero1_temporada_open_individual_anios'] = isset($podium['individual']['no1']) ? (array)$podium['individual']['no1'] : [];
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        // Categorías (dobles/individual) para chips
        $puntuacion_categorias = [];
        if (method_exists($this->api_client, 'get_jugador_puntuacion_categoria')) {
            $puntuacion_categorias = $this->api_client->get_jugador_puntuacion_categoria($jugador_id);
        }
        $categoria_dobles = 'rookie';
        $categoria_individual = 'rookie';
        if (!empty($puntuacion_categorias) && is_array($puntuacion_categorias)) {
            foreach ($puntuacion_categorias as $cat_data) {
                if (!isset($cat_data->categoria) || !isset($cat_data->modalidad)) continue;
                $category_clean_name = strtolower(str_replace(' ', '', $cat_data->categoria));
                if ($category_clean_name === 'elitegm') { $category_clean_name = 'gm'; }
                if ($cat_data->modalidad === 'Dobles') {
                    $categoria_dobles = $category_clean_name;
                } elseif ($cat_data->modalidad === 'Individual') {
                    $categoria_individual = $category_clean_name;
                }
            }
        }
        $categoria_dobles_display = ($categoria_dobles === 'gm') ? 'Elite GM' : ucfirst($categoria_dobles);

        // Enlaces útiles
    $ranking_page_url = isset($plugin_options['ranking_page_id']) ? get_permalink($plugin_options['ranking_page_id']) : home_url('/');
    // Marca opcional de rendimiento en SSR
    // echo "<!-- rf_profile_ssr fast=1 -->";

        // Hacemos el api_client accesible a la plantilla (por si alguna sub-plantilla lo necesita)
        $api_client = $this->api_client;

        // --- H2H (opcional, solo si está activo) ---
        $h2h_processor = null;
        $search_results = [];
        $search_term = isset($_GET['search_h2h']) ? sanitize_text_field($_GET['search_h2h']) : '';
        $jugador2_id = isset($_GET['compare_id']) ? intval($_GET['compare_id']) : 0;

        if ($show_player_h2h) {
            if ($jugador2_id > 0) {
                $player2_data = $this->api_client->get_datos_jugador($jugador2_id);
                if ($player2_data) {
                    $partidos2_items = $this->api_client->get_partidos_jugador($jugador2_id);
                    $posiciones2_items = $this->api_client->get_posiciones_jugador($jugador2_id);
                    if (class_exists('Futbolin_H2H_Processor')) {
                        $h2h_processor = new Futbolin_H2H_Processor(
                            $player1_data, $partidos1_items, $posiciones1_items,
                            $player2_data, $partidos2_items, $posiciones2_items
                        );
                    }
                }
            } elseif (!empty($search_term) && method_exists($this->api_client, 'buscar_jugadores')) {
                $search_results = $this->api_client->buscar_jugadores($search_term) ?? [];
            }
        }

        // Flags de visualización para la vista (las consume player-profile-wrapper.php)
        $player_visual = [
            'summary' => $show_player_summary,
            'stats'   => $show_player_stats,
            'history' => $show_player_history,
            'hitos'   => $show_player_hitos,
            'torneos' => $show_player_torneos,
            'glicko'  => $show_player_glicko,
            'h2h'     => $show_player_h2h,
        ];

        // Render plantilla del perfil
        ob_start();
        include FUTBOLIN_API_PATH . 'includes/template-parts/player-profile-wrapper.php';
        return ob_get_clean();
    }

    /**
     * Render minimalista cuando el perfil está deshabilitado:
     * - SIN wrapper (no sidebar, no botón “volver” interno)
     * - Con cabecera superior y un card de aviso bonito
     */
    private function render_player_disabled_minimal() : string {
        // Construir URL de "volver a principal"
        $opts = get_option('mi_plugin_futbolin_options', []);
        if (!empty($opts['ranking_page_id'])) {
            $ranking_permalink = get_permalink((int)$opts['ranking_page_id']);
            $back_url = esc_url( add_query_arg(['view' => 'ranking'], $ranking_permalink) );
        } else {
            // Fallback: limpia la query actual y fuerza view=ranking
            $back_url = esc_url( add_query_arg(
                ['view' => 'ranking'],
                remove_query_arg([
                    'jugador_busqueda','page','page_size','order_by','order_dir',
                    'info_type','torneo_id','compare_id','search_h2h','jugador_id','modalidad','modalidad_id'
                ])
            ));
        }

        ob_start(); ?>
        <div class="futbolin-full-bleed-wrapper">
          <div class="futbolin-content-container">

            <!-- Cabecera (igual que el wrapper) -->
                        <header class="futbolin-main-header">
              <div class="header-branding">
                <div class="header-side left">
                                    <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/fefm.png' ); ?>" alt="Logo FEFM" class="header-logo" />
                </div>
                <div class="header-text">
                  <h1>Ranking ELO Futbolín</h1>
                  <h2>Una Pierna en España</h2>
                </div>
                <div class="header-side right">
                                    <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/es.webp' ); ?>" alt="Bandera de España" class="header-flag" height="48" />
                </div>
              </div>
            </header>

            <main class="futbolin-main-content">
              <p style="margin:0 0 12px 0;">
                <a class="futbolin-back-button" href="<?php echo $back_url; ?>">← Volver a principal</a>
              </p>

              <div class="futbolin-card" style="text-align:center;padding:28px;border:2px dashed #ffd166;background:#fffaf0;border-radius:10px;">
                <div style="font-size:42px;line-height:1;margin-bottom:8px;">🚧</div>
                <h3 style="margin:0 0 6px 0;color:#8a6d3b;">Perfil de Jugador deshabilitado</h3>
                <p style="margin:0;color:#444;">El perfil de jugador está actualmente en tareas de mantenimiento.</p>
              </div>
            </main>

          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
