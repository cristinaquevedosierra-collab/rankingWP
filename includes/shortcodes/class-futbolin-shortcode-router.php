<?php
/**
 * Archivo: includes/shortcodes/class-futbolin-shortcode-router.php
 * Descripción: Router principal del shortcode [ranking_futbolin]
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Shortcode_Router {

    public function __construct() {
        add_shortcode('ranking_futbolin', [$this, 'render_shortcode']);
    }

    public function render_shortcode($atts) {
        // --- Comprobación global de Modo Mantenimiento ---
        $opts = get_option('mi_plugin_futbolin_options', []);
        $maintenance_on = isset($opts['maintenance_mode']) && $opts['maintenance_mode'] === 'on';

        if ($maintenance_on) {
            return $this->render_maintenance_screen();
        }

        // --- Router normal ---
        $current_view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'ranking';

        // Guardas de visibilidad desde admin
        $can_champions     = (($opts['show_champions']      ?? '') === 'on');
        $can_tournaments   = (($opts['show_tournaments']    ?? '') === 'on');
        $can_hof           = (($opts['show_hall_of_fame']   ?? '') === 'on');
        $can_finals        = (($opts['show_finals_reports'] ?? '') === 'on');
        $can_player_master = (($opts['enable_player_profile'] ?? '') === 'on');

        // Mensaje estándar de módulo deshabilitado (bonito)
        $disabled_html = $this->render_disabled_screen(
            'Sección deshabilitada',
            'Esta sección está deshabilitada temporalmente. La recuperaremos lo antes posible.',
            $current_view
        );

        switch ($current_view) {
            case 'global-stats':
                $handler = new Futbolin_Global_Stats_Shortcode();
                break;

            case 'ranking':
                $handler = new Futbolin_Ranking_Shortcode();
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
            return $handler->render($atts, $current_view);
        }
        return '<div class="futbolin-card"><p>Error al cargar el manejador de la vista.</p></div>';
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
            echo '<img src="https://fefm.es/wp-content/uploads/2025/05/2.png" alt="FEFM" style="height:40px;width:auto;">';
            echo '<h2 style="margin:0;">Ranking de Futbolín</h2>';
            echo '<span style="margin-left:auto;font-size:24px;">🇪🇸</span>';
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
