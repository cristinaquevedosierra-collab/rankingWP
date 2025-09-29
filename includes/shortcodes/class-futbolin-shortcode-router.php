<?php
/**
 * Archivo: includes/shortcodes/class-futbolin-shortcode-router.php
 * Descripción: Router principal del shortcode [ranking_futbolin]
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Shortcode_Router {

    public function __construct() {
        add_shortcode('futbolin_ranking', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts) {

        // Carga defensiva de handlers de shortcodes (evita depender del autoloader si falla)
        $__shortcode_files = [
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-ranking-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-annual-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-global-stats-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-champions-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-tournaments-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-halloffame-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-finals-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-info-shortcode.php',
            FUTBOLIN_API_PATH . 'includes/shortcodes/player/class-futbolin-player-shortcode.php',
        ];
        foreach ($__shortcode_files as $__file) {
            if (file_exists($__file)) { require_once $__file; }
        }

        // --- Comprobación global de Modo Mantenimiento ---
        $opts = get_option('mi_plugin_futbolin_options', []);
    $maintenance_on = isset($opts['maintenance_mode']) && $opts['maintenance_mode'] === 'on';
    $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
    $view_as_user = isset($_GET['rf_view_as']) && $_GET['rf_view_as'] === 'user';

        if ($maintenance_on && (!$is_admin || $view_as_user)) {
            return $this->render_maintenance_screen();
        }

        // --- Router normal ---
        $current_view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'ranking';

        // Guardas de visibilidad desde admin
    $can_champions     = (($opts['show_champions']      ?? '') === 'on') || $is_admin;
    $can_tournaments   = (($opts['show_tournaments']    ?? '') === 'on') || $is_admin;
    $can_hof           = (($opts['show_hall_of_fame']   ?? '') === 'on') || $is_admin;
    $can_finals        = (($opts['show_finals_reports'] ?? '') === 'on') || $is_admin;
    $can_player_master = (($opts['enable_player_profile'] ?? '') === 'on') || $is_admin;

        // Mensaje estándar de módulo deshabilitado (bonito)
        $disabled_html = $this->render_disabled_screen(
            'Sección deshabilitada',
            'Esta sección está deshabilitada temporalmente. La recuperaremos lo antes posible.',
            $current_view
        );

        // Comprobaciones previas según toggles
        $active_modalities = isset($opts['ranking_modalities']) && is_array($opts['ranking_modalities']) ? array_map('intval', $opts['ranking_modalities']) : [];
        $annual_doubles_on    = (!isset($opts['enable_annual_doubles']) || $opts['enable_annual_doubles'] === 'on');
        $annual_individual_on = (!isset($opts['enable_annual_individual']) || $opts['enable_annual_individual'] === 'on');

        // Si no hay ninguna modalidad activa, bloquear la vista ranking
        if ($current_view === 'ranking' && empty($active_modalities) && !$is_admin) {
            return $this->render_disabled_screen('Ranking deshabilitado', 'No hay modalidades activas en la configuración. Activa alguna modalidad para mostrar el ranking.', $current_view);
        }

        // Si la vista es annual pero ambos toggles están off, bloquear
        if ($current_view === 'annual' && (!$annual_doubles_on && !$annual_individual_on) && !$is_admin) {
            return $this->render_disabled_screen('Ranking anual deshabilitado', 'El ranking anual (Dobles e Individual) está deshabilitado desde el panel de administración.', $current_view);
        }

    // Marcador SSR (inicio)
    if (function_exists('rf_log')) { rf_log('SSR START', ['view' => $current_view], 'debug'); }

    switch ($current_view) {
            case 'global-stats':
                $handler = new Futbolin_Global_Stats_Shortcode();
                break;

            case 'ranking':
                $handler = new Futbolin_Ranking_Shortcode();
                break;

            case 'annual':
                $handler = new Futbolin_Annual_Shortcode();
                break;

            case 'champions':
                if (!$can_champions) return $disabled_html;
                $handler = new Futbolin_Champions_Shortcode();
                break;

            case 'tournaments':
            case 'tournament-stats':
                if (!$can_tournaments) return $disabled_html;
                $handler = new Futbolin_Tournaments_Shortcode();
                break;

            case 'hall-of-fame':
                if (!$can_hof) return $disabled_html;
                $handler = new Futbolin_HallOfFame_Shortcode();
                break;

            case 'finals_reports':
                if (!$can_finals) return $disabled_html;
                $handler = new Futbolin_Finals_Shortcode();
                break;

            case 'info':
                $handler = new Futbolin_Info_Shortcode();
                break;

            case 'player': // vista directa de jugador (si la usas por view)
                if (!$can_player_master) return $disabled_html;
                $handler = new Futbolin_Player_Shortcode();
                break;

            default:
                return '<div class="futbolin-card"><p>Vista no encontrada.</p></div>';
        }

        if (isset($handler) && method_exists($handler, 'render')) {
            $out = $handler->render($atts, $current_view);
            if (function_exists('rf_log')) { rf_log('SSR OK', ['view' => $current_view, 'bytes' => is_string($out) ? strlen($out) : 0], 'debug'); }
            return $out;
        }
        return $this->render_disabled_screen('Error al cargar la vista', 'No se pudo inicializar el manejador de esta vista.', $current_view);
    }

    /**
     * Pantalla de Mantenimiento (oculta sidebar, muestra cabecera y aviso)
     */
    private function render_maintenance_screen() {
        ob_start();

        // Variables que espera el wrapper
        $show_back_btn    = false;                       // sin botón extra
        $hide_sidebar     = true;                        // ocultar sidebar
        $current_view     = 'maintenance';
        $template_to_load = 'maintenance-display.php';

        $wrapper_path = FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        if (file_exists($wrapper_path)) {
            include $wrapper_path;
        } else {
            // Fallback muy simple
            echo '<div style="max-width:980px;margin:24px auto;padding:16px;">';
            echo '<header style="display:flex;align-items:center;gap:12px;margin-bottom:16px;border-bottom:1px solid #eee;padding-bottom:12px;">';
            echo '<img src="'.esc_url( FUTBOLIN_API_URL . 'assets/img/fefm.png' ).'" alt="FEFM" style="height:40px;width:auto;">';
            echo '<h2 style="margin:0;">Ranking ELO Futbolín</h2>';
            echo '<img src="'.esc_url( FUTBOLIN_API_URL . 'assets/img/es.webp' ).'" alt="Bandera de España" style="margin-left:auto;height:48px;width:auto;"/>';
            echo '</header>';
            echo '<div class="futbolin-card" style="text-align:center;padding:28px;border:2px dashed #d63638;background:#fff5f5;border-radius:10px;">';
            echo '<div style="font-size:42px;line-height:1;margin-bottom:8px;">🛠️</div>';
            echo '<h3 style="margin:0 0 6px 0;color:#b30000;">Estamos en mantenimiento</h3>';
            echo '<p style="margin:0;color:#444;">Volvemos lo antes posible.</p>';
            echo '</div>';
            echo '</div>';
        }

        return ob_get_clean();
    }

    /**
     * Pantalla bonita para módulos deshabilitados o vistas no disponibles.
     * Mantiene el wrapper y el sidebar para no “encerrar” al usuario.
     */
    private function render_disabled_screen(string $title, string $message, string $current_view = 'ranking') {
        ob_start();

        // Variables que espera el wrapper (sidebar visible)
        $show_back_btn    = false;                             // sin botón "volver" extra
        $hide_sidebar     = false;                             // queremos el sidebar
        $template_to_load = 'module-disabled-display.php';     // <-- tu plantilla real
        $disabled_title   = $title;
        $disabled_msg     = $message;

        // Datos que suele requerir el sidebar
        $opciones = get_option('mi_plugin_futbolin_options', []);
        $modalidades = [];
        if (class_exists('Futbolin_API_Client')) {
            $api = new Futbolin_API_Client();
            if (method_exists($api, 'get_modalidades')) {
                $mods = $api->get_modalidades();
                if (is_array($mods)) $modalidades = $mods;
            }
        }
        $modalidades_activas = $opciones['ranking_modalities'] ?? [];

        // Para coherencia con wrapper
        $current_view = $current_view ?: 'ranking';

        $wrapper_path = FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        if (file_exists($wrapper_path)) {
            include $wrapper_path;
        } else {
            // Fallback simple si faltara el wrapper
            echo '<div class="futbolin-card"><h3>'.esc_html($title).'</h3><p>'.esc_html($message).'</p></div>';
        }

        return ob_get_clean();
    }
}