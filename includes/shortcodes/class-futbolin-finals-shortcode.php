<?php
/**
 * Archivo: includes/shortcodes/class-futbolin-finals-shortcode.php
 * Vista pública "Datos de Finales" a través del wrapper + plantilla.
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Finals_Shortcode {

    public function __construct() {}

    public function render($atts, $view) {
        // (Opcional) Encola CSS específico si no lo cargas globalmente.
        if (defined('FUTBOLIN_API_URL') && defined('FUTBOLIN_API_VERSION')) {
            wp_enqueue_style(
                'futbolin-finals-styles',
                FUTBOLIN_API_URL . 'assets/css/24-finals-reports.css',
                [],
                FUTBOLIN_API_VERSION
            );
            // Si más adelante añadimos ordenación/filtrado por JS en la plantilla:
            // wp_enqueue_script('futbolin-finals-sort', FUTBOLIN_API_URL.'assets/js/finals-sort.js', ['jquery'], FUTBOLIN_API_VERSION, true);
        }

        // ===== Variables para el wrapper/side =====
        $opciones     = get_option('mi_plugin_futbolin_options', []);
        $api          = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        $modalidades  = $api && method_exists($api, 'get_modalidades') ? ($api->get_modalidades() ?: []) : [];
        $modalidades_activas = $opciones['ranking_modalities'] ?? [];

        // URL de la página de perfil (si existe) para que la plantilla genere enlaces por ID
        $profile_page_url = !empty($opciones['player_profile_page_id'])
            ? get_permalink((int)$opciones['player_profile_page_id'])
            : '';

        // ===== Señales al wrapper =====
        $current_view     = 'finals_reports';
        $template_to_load = 'finals-reports-display.php';

        // Ocultamos el sidebar en esta vista y mostramos botón Volver
        $hide_sidebar   = true;
        $show_back_btn  = true;

        ob_start();
        include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        return ob_get_clean();
    }
}
