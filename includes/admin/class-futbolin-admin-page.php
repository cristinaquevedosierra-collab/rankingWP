<?php
/**
 * Archivo: class-futbolin-admin-page.php
 * Ruta: includes/admin/class-futbolin-admin-page.php
 *
 * Descripción: Clase que gestiona la página de administración del plugin.
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Admin_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_init', [$this, 'page_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Carga CSS/JS solo en las pantallas del plugin.
     */
    public function enqueue_admin_assets($hook) {
        // Robust detection: allow both hook suffix and the ?page= parameter.
        $page_slugs = [
            'toplevel_page_futbolin-api-settings',
            'futbolin-api_page_futbolin-finals-reports',
        ];

        $should_enqueue = in_array($hook, $page_slugs, true);

        // Fallback: ?page param contains our plugin slug
        if (! $should_enqueue && isset($_GET['page'])) {
            $page = sanitize_text_field($_GET['page']);
            if (strpos($page, 'futbolin') !== false) {
                $should_enqueue = true;
            }
        }

        // Fallback 2: current screen id contains our slug
        if (! $should_enqueue && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && isset($screen->id) && strpos($screen->id, 'futbolin') !== false) {
                $should_enqueue = true;
            }
        }

        if (! $should_enqueue) return;

        // Scripts
        wp_enqueue_script(
            'futbolin-admin-main-js',
            FUTBOLIN_API_URL . 'assets/js/main.js',
            ['jquery'],
            defined('FUTBOLIN_API_VERSION') ? FUTBOLIN_API_VERSION : false,
            true
        );
        wp_localize_script('futbolin-admin-main-js', 'futbolin_ajax_obj', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'admin_nonce' => wp_create_nonce('futbolin_admin_nonce'),
        ]);

        // Styles
        wp_enqueue_style(
            'futbolin-admin-styles',
            FUTBOLIN_API_URL . 'assets/css/21-admin-styles.css',
            [],
            defined('FUTBOLIN_API_VERSION') ? FUTBOLIN_API_VERSION : false
        );
        // Dashicons are generally loaded in admin, but declare dependency for safety
        wp_enqueue_style('dashicons');
}

    /**
     * Añade la página principal del plugin (un único menú).
     */
    public function add_plugin_page() {
        add_menu_page(
            __('Ajustes Futbolín API', 'futbolin'),
            __('Futbolín API', 'futbolin'),
            'manage_options',
            'futbolin-api-settings',
            [$this, 'create_admin_page'],
            'dashicons-games'
        );
        // No añadimos submenús: “Avanzado” va como pestaña interna.
    }

    // --- PÁGINA PRINCIPAL ---
    public function create_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'inicio';
        ?>
        <div class="wrap futbolin-admin">
            <h1 class="futbolin-admin-header">Futbolín API</h1>
            <div class="futbolin-admin-layout">
                <div class="futbolin-sidebar">
                    <nav class="futbolin-tabs-nav">
                        <a href="?page=futbolin-api-settings&tab=inicio" class="<?php echo ($active_tab === 'inicio' ? 'active' : ''); ?>">
                            <span class="dashicons dashicons-admin-home"></span> <?php esc_html_e('Inicio','futbolin'); ?>
                        </a>
                        <a href="?page=futbolin-api-settings&tab=configuracion" class="<?php echo ($active_tab === 'configuracion' ? 'active' : ''); ?>">
                            <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Configuración','futbolin'); ?>
                        </a>
                        <a href="?page=futbolin-api-settings&tab=rutas" class="<?php echo ($active_tab === 'rutas' ? 'active' : ''); ?>">
                            <span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Páginas','futbolin'); ?>
                        </a>
                        <a href="?page=futbolin-api-settings&tab=calculos" class="<?php echo ($active_tab === 'calculos' ? 'active' : ''); ?>">
                            <span class="dashicons dashicons-media-text"></span> <?php esc_html_e('Acciones de Datos','futbolin'); ?>
                        </a>
                        <a href="?page=futbolin-api-settings&tab=rankgen" class="<?php echo ($active_tab==='rankgen'?'active':''); ?>">
                            <span class="dashicons dashicons-filter"></span> <?php echo esc_html__('Generador de rankings','futbolin'); ?>
                        </a>
                        <a href="?page=futbolin-api-settings&tab=avanzado" class="<?php echo ($active_tab === 'avanzado' ? 'active' : ''); ?>">
                            <span class="dashicons dashicons-hammer"></span> <?php esc_html_e('Avanzado','futbolin'); ?>
                        </a>
                    </nav>
                </div>

                <div class="futbolin-content-area">
    <?php settings_errors(); ?>
    <div class="futbolin-tab-content active" id="tab-<?php echo esc_attr($active_tab); ?>">
        <?php
        if ($active_tab === 'inicio') {

            $this->render_inicio_page();

        } elseif ($active_tab === 'configuracion') {

            ?>
            <form method="post" action="options.php">
                <?php settings_fields('mi_plugin_futbolin_option_group'); ?>
                <input type="hidden" name="mi_plugin_futbolin_options[__context]" value="configuracion">
                <?php
                do_settings_sections('futbolin-api-settings-configuracion');
                submit_button();
                ?>
            </form>
            <?php

        } elseif ($active_tab === 'rutas') {

            ?>
            <form method="post" action="options.php">
                <?php settings_fields('mi_plugin_futbolin_option_group'); ?>
                <input type="hidden" name="mi_plugin_futbolin_options[__context]" value="rutas">
                <?php
                do_settings_sections('futbolin-api-settings-rutas');
                submit_button();
                ?>
            </form>
            <?php

        } elseif ($active_tab === 'calculos') {

            // No se guardan opciones aquí
            do_settings_sections('futbolin-api-settings-calculos');

        } elseif ($active_tab === 'rankgen') {

            // Generador de rankings: partial con su propio <form> (admin-post), NUNCA dentro de options.php
            include_once FUTBOLIN_API_PATH . 'includes/admin/partials/rankgen-tab.php';

        } elseif ($active_tab === 'avanzado') {

            // === AVANZADO (timeout y reintentos) ===
            $opts    = get_option('mi_plugin_futbolin_options', []);
            $timeout = isset($opts['http_timeout']) ? (int)$opts['http_timeout'] : 30;
            if ($timeout < 5 || $timeout > 120) { $timeout = 30; }
            $retries = isset($opts['http_retries']) ? (int)$opts['http_retries'] : 3;
            if ($retries < 0 || $retries > 5) { $retries = 3; }
            ?>

            <form method="post" action="options.php">
                <?php settings_fields('mi_plugin_futbolin_option_group'); ?>
                <input type="hidden" name="mi_plugin_futbolin_options[__context]" value="avanzado">

                <div class="futbolin-card">
                    <h2><?php esc_html_e('Ajustes HTTP (API)','futbolin'); ?></h2>
                    <p><?php esc_html_e('Controla los timeouts y reintentos de llamadas a la API del ranking. No afecta a peticiones ajenas al plugin.','futbolin'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Timeout (segundos)','futbolin'); ?></th>
                                <td>
                                    <input type="number" min="5" max="120" step="1" name="mi_plugin_futbolin_options[http_timeout]" value="<?php echo esc_attr($timeout); ?>" style="width:110px;">
                                    <span class="description"> <?php esc_html_e('Rango recomendado: 5–120 s (por defecto 30).','futbolin'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Reintentos','futbolin'); ?></th>
                                <td>
                                    <input type="number" min="0" max="5" step="1" name="mi_plugin_futbolin_options[http_retries]" value="<?php echo esc_attr($retries); ?>" style="width:110px;">
                                    <span class="description"> <?php esc_html_e('Intentos extra tras un fallo transitorio (0–5, por defecto 3).','futbolin'); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="description">
                        <?php esc_html_e('Dominios incluidos: illozapatillo.zapto.org, 127.0.0.1, localhost o cualquier URL que contenga /api/.','futbolin'); ?>
                    </p>

                    <?php submit_button(); ?>
                </div>
            </form>
            <?php
        }
        ?>
    </div>
</div>

            </div>
        </div>
        <?php
    }

    // --- REGISTRO DE AJUSTES Y SECCIONES ---
    public function page_init() {
        register_setting('mi_plugin_futbolin_option_group', 'mi_plugin_futbolin_options', [$this, 'sanitize_options']);
        register_setting('mi_plugin_futbolin_option_group', 'futbolin_group_open_ids');
        register_setting('mi_plugin_futbolin_option_group', 'futbolin_group_rookie_ids');
        register_setting('mi_plugin_futbolin_option_group', 'futbolin_group_resto_ids');

        // 1) Opciones Generales
        add_settings_section('configuracion_section_id', 'Opciones Generales', [$this, 'print_configuracion_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('default_modalidad',   'Modalidad por Defecto',  [$this, 'default_modalidad_callback'],  'futbolin-api-settings-configuracion', 'configuracion_section_id');
        add_settings_field('ranking_modalities',  'Modalidades de Ranking', [$this, 'ranking_modalities_callback'], 'futbolin-api-settings-configuracion', 'configuracion_section_id');

        // 2) Visualización de Ranking
        add_settings_section('visualizacion_ranking_section_id', 'Visualización de Ranking', [$this, 'print_visual_ranking_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('show_champions',    'Mostrar Campeones de España',    [$this, 'show_champions_callback'],    'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_tournaments',  'Mostrar Campeonatos Disputados', [$this, 'show_tournaments_callback'],  'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_hall_of_fame', 'Mostrar Hall of Fame',           [$this, 'show_hall_of_fame_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_finals_reports','Mostrar Informes',              [$this, 'show_finals_reports_callback'],'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_global_stats', 'Mostrar Estadísticas globales', [$this, 'show_global_stats_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');


        // 3) Visualización de Jugador
        add_settings_section('visualizacion_player_section_id', 'Visualización de Jugador', [$this, 'print_visual_player_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('enable_player_profile', 'Habilitar Perfil de Jugador (maestro)', [$this, 'enable_player_profile_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_summary',   'Resumen del Jugador',                   [$this, 'show_player_summary_callback'],   'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_stats',     'Estadísticas del Jugador',              [$this, 'show_player_stats_callback'],     'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_history',   'Historial de Partidos',                 [$this, 'show_player_history_callback'],   'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_h2h',       'Head to Head (H2H)',                    [$this, 'show_player_h2h_callback'],       'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_glicko',    'Ranking Glicko (pestaña)',              [$this, 'show_player_glicko_callback'],    'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');

        // 4) Modo Mantenimiento
        add_settings_section('maintenance_section_id', 'Modo Mantenimiento', [$this, 'print_maintenance_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('maintenance_mode', '🛠️ Modo mantenimiento (bloquea el plugin en el front)', [$this, 'maintenance_mode_callback'], 'futbolin-api-settings-configuracion', 'maintenance_section_id');

        // Rutas
        add_settings_section('rutas_section_id', 'Páginas del Plugin', null, 'futbolin-api-settings-rutas');
        add_settings_field('player_profile_page_id', 'Página de Perfil de Jugador', [$this, 'player_profile_page_callback'], 'futbolin-api-settings-rutas', 'rutas_section_id');
        add_settings_field('ranking_page_id',        'Página del Ranking',          [$this, 'ranking_page_callback'],        'futbolin-api-settings-rutas', 'rutas_section_id');

        // Cálculos
        add_settings_section(
            'comp_types_section_id',
            'Tipos de competición (por ID)',
            function(){ echo '<p>Escanea la API para detectar todos los <code>competitionTypeId</code>, asígnalos a grupos y luego genera los informes directamente por ID.</p>'; },
            'futbolin-api-settings-calculos'
        );
        add_settings_field('scan_comp_types_button', '1) Detectar tipos',  [$this,'scan_comp_types_button_callback'], 'futbolin-api-settings-calculos', 'comp_types_section_id');
        add_settings_field('comp_types_mapping',     '2) Asignar a grupos',[$this,'comp_types_mapping_callback'],     'futbolin-api-settings-calculos', 'comp_types_section_id');
        add_settings_field('build_reports_by_types', '3) Generar informes',[$this,'build_reports_by_types_callback'], 'futbolin-api-settings-calculos', 'comp_types_section_id');

        add_settings_section('calculos_section_id', 'Acciones de Datos', null, 'futbolin-api-settings-calculos');
        add_settings_field('sync_tournaments_local',   'Sincronizar torneos (local)', [$this, 'sync_tournaments_button_callback'],       'futbolin-api-settings-calculos', 'calculos_section_id');
        add_settings_field('clear_tournaments_cache',  'Vaciar caché de torneos',     [$this, 'clear_tournaments_cache_button_callback'],'futbolin-api-settings-calculos', 'calculos_section_id');
        add_settings_field('clear_players_cache',      'Vaciar caché de jugadores',   [$this, 'clear_players_cache_button_callback'],   'futbolin-api-settings-calculos', 'calculos_section_id');
        add_settings_field('run_champions_button',     'Campeones de España',         [$this, 'run_champions_button_callback'],         'futbolin-api-settings-calculos', 'calculos_section_id');
        add_settings_field('calculate_seasons_button', 'Cálculo de Temporadas',       [$this, 'calculate_seasons_button_callback'],     'futbolin-api-settings-calculos', 'calculos_section_id');
        add_settings_field('run_stats_button',         'Estadísticas Globales',       [$this, 'run_stats_button_callback'],             'futbolin-api-settings-calculos', 'calculos_section_id');
        add_settings_field('calculate_hall_of_fame',   'Cálculo del Hall of Fame',    [$this, 'calculate_hall_of_fame_callback'],       'futbolin-api-settings-calculos', 'calculos_section_id');
        add_settings_field('run_finals_button',        'Cálculo de Finales',          [$this, 'run_finals_button_callback'],            'futbolin-api-settings-calculos', 'calculos_section_id');
    }

    // --- SANITIZACIÓN DE OPCIONES ---
    public function sanitize_options($input) {
        $old = get_option('mi_plugin_futbolin_options', []);
        if (!is_array($old)) $old = [];

        if (!is_array($input)) return $old;

        $ctx = isset($input['__context']) ? sanitize_key($input['__context']) : '';
        unset($input['__context']);

        $out = $old;

        if ($ctx === 'configuracion') {
            $checkboxes = [
                'show_champions',
                'show_tournaments',
                'show_hall_of_fame',
                'show_finals_reports',
                'enable_player_profile',
                'show_player_summary',
                'show_player_stats',
                'show_player_history',
                'show_player_h2h',
                'show_player_glicko',
                'show_global_stats',
                'maintenance_mode',
            ];
            foreach ($checkboxes as $k) {
                $out[$k] = (isset($input[$k]) && $input[$k] === 'on') ? 'on' : 'off';
            }

            if (isset($input['default_modalidad'])) {
                $out['default_modalidad'] = intval($input['default_modalidad']);
            }

            if (isset($input['ranking_modalities'])) {
                $out['ranking_modalities'] = array_map('intval', (array)$input['ranking_modalities']);
            } else {
                $out['ranking_modalities'] = [];
            }

        } elseif ($ctx === 'rutas') {
            if (isset($input['player_profile_page_id'])) {
                $out['player_profile_page_id'] = intval($input['player_profile_page_id']);
            }
            if (isset($input['ranking_page_id'])) {
                $out['ranking_page_id'] = intval($input['ranking_page_id']);
            }

        } elseif ($ctx === 'avanzado') {
            if (isset($input['http_timeout'])) {
                $t = (int)$input['http_timeout'];
                if ($t < 5 || $t > 120) { $t = 30; }
                $out['http_timeout'] = $t;
            }
            if (isset($input['http_retries'])) {
                $r = (int)$input['http_retries'];
                if ($r < 0 || $r > 5) { $r = 3; }
                $out['http_retries'] = $r;
            }

        } else {
            // Contexto desconocido: no tocar nada
            return $old;
        }

        return $out;
    }

    // --- DESCRIPCIONES DE SECCIONES (CONFIGURACIÓN) ---
    public function print_configuracion_info() {
        echo '<p>Estos valores se usan como predeterminados cuando no se especifican en el shortcode.</p>';
    }
    public function print_visual_ranking_info() {
        echo '<p>Activa o desactiva los módulos visibles en la navegación del ranking.</p>';
    }
    public function print_visual_player_info() {
        echo '<p>Activa o desactiva secciones del <strong>perfil de jugador</strong>. Si deshabilitas el maestro, el perfil no mostrará módulos.</p>';
    }
    public function print_maintenance_info() {
        echo '<p>Al activar el modo mantenimiento, el front muestra solo la cabecera y un mensaje; se bloquea la navegación del plugin.</p>';
    }

    // --- CHECKBOX GENÉRICO ---
    private function render_checkbox_callback($option_name, $label_text) {
        $options = get_option('mi_plugin_futbolin_options', []);
        $checked = (isset($options[$option_name]) && $options[$option_name] === 'on') ? 'checked' : '';
        echo '<label><input type="checkbox" name="mi_plugin_futbolin_options[' . esc_attr($option_name) . ']" ' . $checked . ' value="on"> ' . wp_kses_post($label_text) . '</label>';
    }

    // --- RANKING (VISUALIZACIÓN) ---
    public function show_champions_callback()      { $this->render_checkbox_callback('show_champions',      'Activar la visualización de Campeones de España.'); }
    public function show_tournaments_callback()    { $this->render_checkbox_callback('show_tournaments',    'Activar la visualización de Campeonatos Disputados.'); }
    public function show_hall_of_fame_callback()   { $this->render_checkbox_callback('show_hall_of_fame',   'Activar la visualización del Hall of Fame.'); }
    public function show_finals_reports_callback() { $this->render_checkbox_callback('show_finals_reports', 'Activar la visualización de los Informes de Finales.'); }

    // --- JUGADOR (VISUALIZACIÓN) ---
    public function enable_player_profile_callback() {
        $options = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($options['enable_player_profile']) && $options['enable_player_profile'] === 'on';
        $checked = $enabled ? 'checked' : '';
        ?>
        <style>
            .futbolin-profile-wrap { max-width: 820px; }
            .futbolin-profile-card {
                border: 2px solid <?php echo $enabled ? '#198754' : '#999'; ?>;
                background: <?php echo $enabled ? '#e6f4ea' : '#f6f7f7'; ?>;
                padding: 14px 16px; border-radius: 12px;
                display: flex; align-items: center; gap: 16px; box-sizing: border-box;
            }
            .futbolin-profile-icon {
                font-size: 26px; line-height: 1; width: 36px; height: 36px;
                display: flex; align-items: center; justify-content: center;
                border-radius: 50%;
                background: <?php echo $enabled ? '#d1f0d8' : '#ddd'; ?>;
                flex: 0 0 36px;
            }
            .futbolin-profile-body { flex: 1 1 auto; min-width: 0; }
            .futbolin-profile-status { font-weight: 700; margin: 0 0 4px; color: <?php echo $enabled ? '#14532d' : '#555'; ?>; }
            .futbolin-profile-desc { margin: 0; opacity: .9; }
            .futbolin-profile-actions { flex: 0 0 auto; display: flex; align-items: center; gap: 12px; }
            .futbolin-switch { position: relative; display: inline-block; width: 70px; height: 36px; }
            .futbolin-switch input { display: none; }
            .futbolin-slider {
                position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
                background-color: #ccc; transition: .3s; border-radius: 36px;
                box-shadow: inset 0 0 0 2px rgba(0,0,0,.06);
            }
            .futbolin-slider:before {
                position: absolute; content: ""; height: 28px; width: 28px; left: 4px; bottom: 4px;
                background-color: #fff; transition: .3s; border-radius: 50%;
                box-shadow: 0 1px 3px rgba(0,0,0,.3);
            }
            .futbolin-switch input:checked + .futbolin-slider { background-color: #198754; }
            .futbolin-switch input:checked + .futbolin-slider:before { transform: translateX(34px); }
            .profile-badge {
                font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 999px;
                background: <?php echo $enabled ? '#c6f6d5' : '#eee'; ?>;
                color: <?php echo $enabled ? '#14532d' : '#555'; ?>;
                border: 1px solid <?php echo $enabled ? '#95e3a4' : '#ccc'; ?>;
                text-transform: uppercase; letter-spacing: .3px;
            }
            @media (max-width: 782px) {
                .futbolin-profile-card { flex-direction: column; align-items: stretch; }
                .futbolin-profile-actions { justify-content: flex-end; }
            }
        </style>
        <div class="futbolin-profile-wrap">
            <div class="futbolin-profile-card">
                <div class="futbolin-profile-icon">👤</div>
                <div class="futbolin-profile-body">
                    <p class="futbolin-profile-status">
                        Estado actual: <?php echo $enabled ? 'PERFIL DE JUGADOR HABILITADO' : 'PERFIL DE JUGADOR DESHABILITADO'; ?>
                    </p>
                    <p class="futbolin-profile-desc">
                        Este interruptor controla el <strong>perfil de jugador</strong> en el front.<br>
                        Si lo deshabilitas:
                        <br>• No se mostrará el buscador de jugadores en la barra lateral.
                        <br>• Cualquier intento de abrir un perfil mostrará un aviso de “sección deshabilitada temporalmente”.
                    </p>
                </div>
                <div class="futbolin-profile-actions">
                    <span class="profile-badge"><?php echo $enabled ? 'Activo' : 'Inactivo'; ?></span>
                    <label class="futbolin-switch" title="Activar/Desactivar perfil de jugador">
                        <input id="futbolin-player-profile-toggle" type="checkbox"
                               name="mi_plugin_futbolin_options[enable_player_profile]"
                               value="on" <?php echo $checked; ?> />
                        <span class="futbolin-slider"></span>
                    </label>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const toggle = document.getElementById('futbolin-player-profile-toggle');
            if (!toggle) return;
            let last = toggle.checked;
            toggle.addEventListener('change', function(e){
                const goingOn  = e.target.checked && !last;
                const goingOff = !e.target.checked && last;
                let msg = '';
                if (goingOn) {
                    msg = '¿HABILITAR el Perfil de Jugador en el front?\\n\\n' +
                          '• Se mostrará el buscador de jugadores en la barra lateral.\\n' +
                          '• Los perfiles y pestañas activas serán accesibles.';
                } else if (goingOff) {
                    msg = '¿DESHABILITAR el Perfil de Jugador en el front?\\n\\n' +
                          '• Se ocultará el buscador de jugadores en la barra lateral.\\n' +
                          '• Cargar un perfil mostrará un aviso de “sección deshabilitada temporalmente”.';
                }
                if (msg && !confirm(msg)) {
                    e.target.checked = !e.target.checked;
                    return;
                }
                last = e.target.checked;
            });
        })();
        </script>
        <?php
    }
    public function show_player_summary_callback()  { $this->render_checkbox_callback('show_player_summary',  'Mostrar <em>Resumen del Jugador</em>.'); }
    public function show_player_stats_callback()    { $this->render_checkbox_callback('show_player_stats',    'Mostrar <em>Estadísticas del Jugador</em>.'); }
    public function show_player_history_callback()  { $this->render_checkbox_callback('show_player_history',  'Mostrar <em>Historial de Partidos</em>.'); }
    public function show_player_h2h_callback()      { $this->render_checkbox_callback('show_player_h2h',      'Mostrar pestaña <em>Head to Head (H2H)</em>.'); }
    public function show_player_glicko_callback()   { $this->render_checkbox_callback('show_player_glicko',   'Mostrar pestaña <em>Ranking Glicko</em>.'); }

    // --- CAMPOS DE RANKING: MODALIDADES (checkbox list) ---
    public function ranking_modalities_callback() {
        $options = get_option('mi_plugin_futbolin_options', []);
        $active_modalities = isset($options['ranking_modalities']) ? (array) $options['ranking_modalities'] : [];
        $modalities = [
            '2'=>'Dobles', '1'=>'Individual','7'=>'Mujeres Dobles','8'=>'Mujeres Individual',
            '10'=>'Mixto','3'=>'Senior Dobles','4'=>'Senior Individual','5'=>'Junior Dobles','6'=>'Junior Individual'
        ];
        echo '<div class="checkbox-list">';
        foreach ($modalities as $id => $name) {
            $checked = in_array($id, $active_modalities, true) ? 'checked' : '';
            echo '<label class="checkbox-item"><input type="checkbox" name="mi_plugin_futbolin_options[ranking_modalities][]" value="' . esc_attr($id) . '" ' . $checked . '>' . esc_html($name) . '</label>';
        }
        echo '</div>';
    }

    // --- CAMPO: MODALIDAD POR DEFECTO (select) ---
    public function default_modalidad_callback() {
        $api_client = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        $options = get_option('mi_plugin_futbolin_options', []);
        $default_modalidad = isset($options['default_modalidad']) ? (int)$options['default_modalidad'] : 2;

        $modalidades = $api_client && method_exists($api_client, 'get_modalidades')
            ? $api_client->get_modalidades()
            : [
                (object)['modalidadId' => 2, 'descripcion' => 'Dobles'],
                (object)['modalidadId' => 1, 'descripcion' => 'Individual']
            ];

        if (empty($modalidades)) {
            echo '<p>No se pudieron cargar las modalidades desde la API.</p>';
            return;
        }

        echo '<select name="mi_plugin_futbolin_options[default_modalidad]">';
        foreach ($modalidades as $modalidad) {
            $id   = isset($modalidad->modalidadId) ? (int)$modalidad->modalidadId : null;
            $desc = isset($modalidad->descripcion) ? (string)$modalidad->descripcion : ('Modalidad '.$id);
            if ($id === null) continue;
            echo '<option value="' . esc_attr($id) . '" ' . selected($default_modalidad, $id, false) . '>' . esc_html($desc) . '</option>';
        }
        echo '</select>';
    }

    // --- MODO MANTENIMIENTO (UI) ---
    public function maintenance_mode_callback() {
        $options = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($options['maintenance_mode']) && $options['maintenance_mode'] === 'on';
        $checked = $enabled ? 'checked' : '';
        ?>
        <style>
            .form-table td .futbolin-maint-wrap { max-width: 820px; }
            .futbolin-maint-card {
                border: 2px solid <?php echo $enabled ? '#b30000' : '#2271b1'; ?>;
                background: <?php echo $enabled ? '#ffe9e9' : '#eef6ff'; ?>;
                padding: 14px 16px; border-radius: 12px;
                display: flex; align-items: center; gap: 16px; box-sizing: border-box;
            }
            .futbolin-maint-icon {
                font-size: 28px; line-height: 1; width: 36px; height: 36px;
                display: flex; align-items: center; justify-content: center;
                border-radius: 50%;
                background: <?php echo $enabled ? '#ffcccc' : '#d6ebff'; ?>;
                flex: 0 0 36px;
            }
            .futbolin-maint-body { flex: 1 1 auto; min-width: 0; }
            .futbolin-maint-status { font-weight: 700; margin: 0 0 4px; color: <?php echo $enabled ? '#8a0000' : '#0a4b78'; ?>; }
            .futbolin-maint-desc { margin: 0; opacity: .9; }
            .futbolin-maint-actions { flex: 0 0 auto; display: flex; align-items: center; gap: 12px; }
            .futbolin-switch { position: relative; display: inline-block; width: 70px; height: 36px; }
            .futbolin-switch input { display: none; }
            .futbolin-slider {
                position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
                background-color: #ccc; transition: .3s; border-radius: 36px;
                box-shadow: inset 0 0 0 2px rgba(0,0,0,.06);
            }
            .futbolin-slider:before {
                position: absolute; content: ""; height: 28px; width: 28px; left: 4px; bottom: 4px;
                background-color: #fff; transition: .3s; border-radius: 50%;
                box-shadow: 0 1px 3px rgba(0,0,0,.3);
            }
            .futbolin-switch input:checked + .futbolin-slider { background-color: #d63638; }
            .futbolin-switch input:checked + .futbolin-slider:before { transform: translateX(34px); }
            .maint-badge {
                font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 999px;
                background: <?php echo $enabled ? '#ffdbdb' : '#dff0ff'; ?>;
                color: <?php echo $enabled ? '#7b0000' : '#0a4b78'; ?>;
                border: 1px solid <?php echo $enabled ? '#f5bcbc' : '#b9ddff'; ?>;
                text-transform: uppercase; letter-spacing: .3px;
            }
            @media (max-width: 782px) {
                .futbolin-maint-card { flex-direction: column; align-items: stretch; }
                .futbolin-maint-actions { justify-content: flex-end; }
            }
        </style>
        <div class="futbolin-maint-wrap">
            <div class="futbolin-maint-card">
                <div class="futbolin-maint-icon">🛠️</div>
                <div class="futbolin-maint-body">
                    <p class="futbolin-maint-status">
                        Estado actual: <?php echo $enabled ? 'MODO MANTENIMIENTO ACTIVADO' : 'MODO MANTENIMIENTO DESACTIVADO'; ?>
                    </p>
                    <p class="futbolin-maint-desc">
                        Al activar este modo, en el <strong>front</strong> solo se mostrará la cabecera del plugin y el mensaje:
                        <em>“Estamos en mantenimiento, volvemos lo antes posible.”</em>
                        Se bloqueará la navegación del plugin y sus enlaces (shortcodes/routers).
                    </p>
                </div>
                <div class="futbolin-maint-actions">
                    <span class="maint-badge"><?php echo $enabled ? 'Activo' : 'Inactivo'; ?></span>
                    <label class="futbolin-switch" title="Activar/Desactivar modo mantenimiento">
                        <input id="futbolin-maintenance-toggle" type="checkbox"
                               name="mi_plugin_futbolin_options[maintenance_mode]"
                               value="on" <?php echo $checked; ?> />
                        <span class="futbolin-slider"></span>
                    </label>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const toggle = document.getElementById('futbolin-maintenance-toggle');
            if (!toggle) return;
            let last = toggle.checked;
            toggle.addEventListener('change', function(e){
                const goingOn  = e.target.checked && !last;
                const goingOff = !e.target.checked && last;
                let msg = '';
                if (goingOn) {
                    msg = '¿Seguro que deseas ACTIVAR el Modo mantenimiento?\\n\\n' +
                          '• Ocultará todos los datos del plugin en el front.\\n' +
                          '• Solo se verá la cabecera y el mensaje de mantenimiento.\\n' +
                          '• Se bloqueará la navegación de las vistas del plugin.';
                } else if (goingOff) {
                    msg = '¿Desactivar el Modo mantenimiento y volver a mostrar los datos del plugin?';
                }
                if (msg && !confirm(msg)) {
                    e.target.checked = !e.target.checked;
                    return;
                }
                last = e.target.checked;
            });
        })();
        </script>
        <?php
    }

    // --- PÁGINA INICIO ---
    private function render_inicio_page() {
        ?>
        <div class="futbolin-card">
          <div class="logo-container">
            <img src="https://fefm.es/wp-content/uploads/2025/05/2.png" alt="Logo FEFM" class="fefm-logo">
            <h2 class="welcome-title">Bienvenido al Panel de Administración de Futbolín API</h2>
          </div>
          <p>Este plugin actúa como un puente vital, conectando la API de la Federación Española de Futbolín con tu sitio web de WordPress. Su propósito es transformar datos complejos en una experiencia interactiva y accesible, mostrando rankings de jugadores, perfiles detallados y estadísticas de torneos de forma dinámica y atractiva.</p>
          <h3>Características Principales</h3>
          <ul>
            <li><strong>Ranking Dinámico:</strong> Muestra el ranking de jugadores actualizado por modalidad, con opciones de filtrado y paginación.</li>
            <li><strong>Perfiles de Jugador:</strong> Crea páginas de perfil individuales para cada jugador, con estadísticas, historial de partidas y logros.</li>
            <li><strong>Búsqueda Avanzada:</strong> Permite a los usuarios buscar jugadores y comparar estadísticas en duelos 'Head-to-Head'.</li>
            <li><strong>Optimización de Rendimiento:</strong> Almacena datos estáticos como campeones y estadísticas globales para reducir las llamadas a la API y acelerar la carga de la web.</li>
          </ul>
        </div>
        <div class="futbolin-card developer-info-card">
          <div class="developer-info-header">
            <h3>Sobre el Desarrollador</h3>
          </div>
          <p>La integración, visualización y experiencia de usuario de este ranking ha sido concebida y desarrollada por <strong>Héctor Núñez Sáez</strong>. A través de este plugin a medida, se ha logrado transformar los datos en bruto de la API en una herramienta viva, interactiva y accesible para todos los jugadores de la federación.</p>
          <div class="contact-info">
            <p class="contact-text">Si admiras el trabajo realizado y estás interesado en implementar un sistema similar para tu liga, federación o club, o si deseas obtener más información sobre el proyecto, puedes ponerte en contacto conmigo a través del siguiente correo electrónico:</p>
            <p class="contact-email">hector@fefm.es</p>
          </div>
        </div>
        <?php
    }

    // --- SUBPÁGINA: INFORMES DE FINALES (placeholder deshabilitado) ---
    public function finals_reports_page() {
        echo '<div class="wrap"><h1 class="futbolin-admin-header">Datos de Finales</h1>';
        echo '<p>Se ha desactivado la sección de Informes de Finales. Los campeones y estadísticas relevantes se leen en tiempo real desde la API.</p>';
        echo '</div>';
    }

    private function get_report_title($key) {
        $titles = [
            'open_individual_finals'     => 'Informe de Finales Ganadas Open Individual',
            'open_doubles_player_finals' => 'Informe de Finales Ganadas Open Dobles por Jugador',
            'open_doubles_pair_finals'   => 'Informe de Finales Ganadas Open Dobles por Pareja',
            'championships_open'         => 'Informe de Campeonatos Open Ganados',
            'championships_rookie'       => 'Informe de Campeonatos Rookie/Amater Ganados',
            'championships_resto'        => 'Informe de Campeonatos Resto Ganados',
        ];
        return $titles[$key] ?? 'Informe de Finales';
    }

    private function render_report_table($data, $report_key) {
        echo '<table class="wp-list-table widefat striped">';
        if (strpos((string)($report_key ?? ''), 'finals') !== false) {
            echo '<thead><tr><th>Jugador/Pareja</th><th>Finales Jugadas</th><th>Finales Ganadas</th><th>Finales Perdidas</th><th>% Victoria</th></tr></thead><tbody>';
            foreach ($data as $player_name => $stats) {
                echo '<tr>';
                echo '<td>' . esc_html($player_name) . '</td>';
                echo '<td>' . esc_html($stats['total'] ?? 0) . '</td>';
                echo '<td>' . esc_html($stats['wins'] ?? 0) . '</td>';
                echo '<td>' . esc_html($stats['losses'] ?? 0) . '</td>';
                echo '<td>' . esc_html(number_format((float)($stats['win_rate'] ?? 0), 2)) . '%</td>';
                echo '</tr>';
            }
            echo '</tbody>';
        } else {
            $first = reset($data);
            $is_stats = is_array($first) && array_key_exists('torneos_jugados', $first);
            if ($is_stats) {
                echo '<thead><tr><th>Jugador</th><th>Torn.</th><th>Camp.</th><th>F</th><th>G</th><th>P</th><th>Rest</th><th>%F</th><th>%C</th><th>Ganados</th></tr></thead><tbody>';
                foreach ($data as $player_name => $st) {
                    echo '<tr>';
                    echo '<td>' . esc_html($player_name) . '</td>';
                    echo '<td>' . esc_html($st['torneos_jugados'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['campeonatos_jugados'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['finales_jugadas'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['finales_ganadas'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['finales_perdidas'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['resto_posiciones'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['porcentaje_finales_ganadas'] ?? 0) . '%</td>';
                    echo '<td>' . esc_html($st['porcentaje_campeonatos_ganados'] ?? 0) . '%</td>';
                    echo '<td>' . esc_html($st['campeonatos_ganados'] ?? 0) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
            } else {
                echo '<thead><tr><th>Jugador</th><th>Nº Campeonatos</th><th>Campeonatos</th></tr></thead><tbody>';
                foreach ($data as $player_name => $championships) {
                    echo '<tr>';
                    echo '<td>' . esc_html($player_name) . '</td>';
                    echo '<td>' . esc_html(is_array($championships) ? count($championships) : 0) . '</td>';
                    echo '<td>' . esc_html(is_array($championships) ? implode(', ', $championships) : '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
            }
        }
        echo '</table>';
    }

    // --- PÁGINAS DEL PLUGIN (Rutas) ---
    public function player_profile_page_callback() {
        $options = get_option('mi_plugin_futbolin_options');
        $page_id = isset($options['player_profile_page_id']) ? $options['player_profile_page_id'] : 0;
        wp_dropdown_pages([
            'selected' => $page_id,
            'name'  => 'mi_plugin_futbolin_options[player_profile_page_id]',
            'show_option_none' => '— Seleccionar página —'
        ]);
        echo '<p class="description">Selecciona la página que contiene el shortcode [futbolin_jugador].</p>';
    }

    public function ranking_page_callback() {
        $options = get_option('mi_plugin_futbolin_options');
        $page_id = isset($options['ranking_page_id']) ? $options['ranking_page_id'] : 0;
        wp_dropdown_pages([
            'selected' => $page_id,
            'name'  => 'mi_plugin_futbolin_options[ranking_page_id]',
            'show_option_none' => '— Seleccionar página —'
        ]);
        echo '<p class="description">Selecciona la página que contiene el shortcode [futbolin_ranking].</p>';
    }

    // --- ACCIONES / BOTONES DE CÁLCULO ---
    public function scan_comp_types_button_callback() {
        $catalog = get_option('futbolin_competition_types_catalog', []);
        $count = is_array($catalog) ? count($catalog) : 0;
        echo '<button type="button" id="futbolin-scan-comp-types" class="button button-secondary">Escanear tipos de competición</button> ';
        echo '<span id="futbolin-scan-status" style="margin-left:8px;color:#555;">Detectados: <strong>'.intval($count).'</strong></span>';
        echo '<p class="description">Guarda un catálogo local de IDs + etiquetas.</p>';
    }

    public function comp_types_mapping_callback() {
        $catalog = get_option('futbolin_competition_types_catalog', []);
        if (empty($catalog) || !is_array($catalog)) {
            echo '<p>No hay tipos detectados aún. Pulsa “Escanear tipos de competición”.</p>';
            return;
        }

        $open   = get_option('futbolin_group_open_ids',   []);
        $rookie = get_option('futbolin_group_rookie_ids', []);
        $resto  = get_option('futbolin_group_resto_ids',  []);

        echo '<div class="futbolin-grid-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">';

        $render_col = function($title, $name, $selected) use ($catalog) {
            echo '<div class="futbolin-box" style="border:1px solid #ddd;padding:10px;border-radius:6px;">';
            echo '<h4 style="margin-top:0;">'.$title.'</h4>';
            echo '<div style="max-height:260px;overflow:auto;">';
            foreach ($catalog as $id => $info) {
                $label = esc_html($info['label'] ?? ('Tipo '.$id));
                $cnt   = intval($info['count'] ?? 0);
                $chk   = in_array((int)$id, (array)$selected, true) ? 'checked' : '';
                echo '<label style="display:flex;align-items:center;gap:6px;margin:.25rem 0;">';
                echo '<input type="checkbox" name="'.$name.'[]" value="'.esc_attr($id).'" '.$chk.' />';
                echo '<span>#'.esc_html($id).' — '.$label.' <em style="opacity:.6;">('.$cnt.')</em></span>';
                echo '</label>';
            }
            echo '</div>';
            echo '</div>';
        };

        $render_col('OPEN (incluye España Open)', 'futbolin_group_open_ids',   $open);
        $render_col('ROOKIE / AMATEUR',          'futbolin_group_rookie_ids', $rookie);
        $render_col('RESTO (Mixto, DYP, ProAm…)', 'futbolin_group_resto_ids',  $resto);

        echo '</div>';
        echo '<p class="description">Marca cada <code>competitionTypeId</code> en el grupo que corresponda y pulsa “Guardar cambios”.</p>';
    }

    public function build_reports_by_types_callback() {
        echo '<button type="button" id="futbolin-build-reports-by-types" class="button button-primary">Generar informes por IDs</button> ';
        echo '<span id="futbolin-build-by-types-status" style="margin-left:8px;color:#555;"></span>';
        echo '<p class="description">Usa exclusivamente los IDs configurados para recomponer los 6 informes (incluye España Open en OPEN si marcas sus IDs ahí).</p>';
    }

    public function run_champions_button_callback() {
        echo '<div class="stats-control-group">';
        echo '<h4>Campeones de España</h4>';
        echo '<p class="description">Esta lista ya no se genera ni se guarda: ahora se obtiene directamente del endpoint oficial <code>/Jugador/GetCampeonesEspania</code>.</p>';
        echo '</div>';
    }

    public function run_stats_button_callback() {
        $status = get_option('futbolin_calculation_status', 'complete');
        $total_matches_count = get_option('futbolin_total_matches_count', 0);
        $last_checked_id_message = "Total de partidos contados: **{$total_matches_count}**.";
        $is_in_progress = ($status === 'in_progress');

        echo '<div class="stats-control-group"><h4>Recálculo de partidas</h4><div class="button-group"><button type="button" id="futbolin-run-full-calc" class="button button-danger" ' . ($is_in_progress ? 'disabled' : '') . '>Calcular Total de Partidas</button><button type="button" id="cancel-futbolin-run-full-calc" class="button button-secondary" style="display:' . ($is_in_progress ? 'inline-block' : 'none') . ';">Cancelar</button></div><p class="description">**ADVERTENCIA:** Este proceso es muy lento. Procesará el historial de todos los jugadores desde el ID 1 y puede tardar varios minutos. No cierres la ventana hasta que termine.</p><p class="stats-message">' . $last_checked_id_message . '</p><div id="futbolin-run-full-calc-progress-container" class="futbolin-progress-bar-wrapper" style="display:' . ($is_in_progress ? 'block' : 'none') . ';"><div id="futbolin-run-full-calc-progress-bar" style="width:0%;"></div><span id="futbolin-run-full-calc-progress-status"></span></div></div>';
    }

    public function calculate_seasons_button_callback() {
        $total_seasons_data = get_option('futbolin_total_seasons_count', []);
        $seasons_count = is_array($total_seasons_data) ? count($total_seasons_data) : 0;
        $stats_message = "Temporadas únicas: <strong>{$seasons_count}</strong>.";
        echo '<div class="stats-control-group"><h4>Cálculo de Temporadas</h4><button type="button" id="futbolin-calculate-seasons" class="button button-primary">Calcular Temporadas Únicas</button><p class="description">Este proceso rápido actualiza el número de temporadas únicas. ' . $stats_message . '</p></div>';
    }

    public function calculate_hall_of_fame_callback() {
        $status_active = get_option('futbolin_active_players_calculation_status', 'complete');
        $status_win_rate = get_option('futbolin_win_rate_calculation_status', 'complete');
        $total_players_active = get_option('futbolin_active_players_total_count', 0);
        $total_players_win_rate = get_option('futbolin_win_rate_total_count', 0);
        $message = "Jugadores activos: **{$total_players_active}**. Jugadores con % de victorias: **{$total_players_win_rate}**.";
        $is_in_progress = ($status_active === 'in_progress' || $status_win_rate === 'in_progress');

        echo '<div class="stats-control-group"><h4>Cálculo del Hall of Fame</h4><div class="button-group"><button type="button" id="futbolin-run-hall-of-fame-calc" class="button button-primary" ' . ($is_in_progress ? 'disabled' : '') . '>Recalcular Hall of Fame</button><button type="button" id="cancel-futbolin-run-hall-of-fame-calc" class="button button-secondary" style="display:' . ($is_in_progress ? 'inline-block' : 'none') . ';">Cancelar</button></div><p class="description">Calcula y guarda los rankings de Jugadores Más Activos y de Porcentaje de Victorias en un solo proceso. Este proceso es lento.</p><p class="stats-message">' . $message . '</p><div id="futbolin-run-hall-of-fame-calc-progress-container" class="futbolin-progress-bar-wrapper" style="display:' . ($is_in_progress ? 'block' : 'none') . ';"><div id="futbolin-run-hall-of-fame-calc-progress-bar" style="width:0%;"></div><span id="futbolin-run-hall-of-fame-calc-progress-status"></span></div></div>';
    }

    public function run_finals_button_callback() {
        echo '<div class="stats-control-group">';
        echo '<h4>Informes de Finales</h4>';
        echo '<p class="description">Deshabilitado. A partir de ahora los Campeones de España se muestran en vivo desde la API (<code>/Jugador/GetCampeonesEspania</code>) y no requieren cálculos previos.</p>';
        echo '</div>';
    }

    public function sync_tournaments_button_callback() {
        echo '<div class="stats-control-group">';
        echo '<button type="button" id="futbolin-sync-tournaments" class="button button-primary">Sincronizar torneos (local)</button>';
        echo '<p class="description">Descarga todos los torneos de la API y los guarda en WP para uso local.</p>';
        echo '</div>';
    }
    public function clear_tournaments_cache_button_callback() {
        echo '<div class="stats-control-group">';
        echo '<button type="button" id="futbolin-clear-tournaments-cache" class="button">Vaciar caché de torneos</button>';
        echo '</div>';
    }
    public function clear_players_cache_button_callback() {
        echo '<div class="stats-control-group">';
        echo '<button type="button" id="futbolin-clear-players-cache" class="button">Vaciar caché de jugadores</button>';
        echo '</div>';
        echo '<pre id="futbolin-data-actions-log" style="display:none;max-height:240px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:6px;"></pre>';
    }


public function show_global_stats_callback() {
    $this->render_checkbox_callback('show_global_stats', 'Activar la visualización de Estadísticas globales.');
}
}
// cierre de clase

?>

