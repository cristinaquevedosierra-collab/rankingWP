<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Info_Shortcode {
    private $api;

    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api = new Futbolin_API_Client();
        }
    }

    // Firma con $view por compatibilidad con tu router
    public function render($atts, $view) {
        $param_q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $param_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $atts = shortcode_atts([
            'wrap' => '1',
        ], $atts, 'futbolin_info');

        $wrap = ($atts['wrap'] === '1');

        // ===== Datos para sidebar / wrapper =====
        $opciones            = get_option('mi_plugin_futbolin_options', []);
        $modalidades         = ($this->api && method_exists($this->api, 'get_modalidades')) ? ($this->api->get_modalidades() ?: []) : [];
        $modalidades_activas = $opciones['ranking_modalities'] ?? [];

        // ===== Datos para los parciales de "Información" =====
        $info_data = $this->get_info_data();

        // ===== Variables para el wrapper =====
        $current_view     = 'info';
        $template_to_load = 'info-display.php'; // el orquestador que ya creamos

        ob_start();
        if ($wrap) {
            include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        } else {
            include FUTBOLIN_API_PATH . 'includes/template-parts/info-display.php';
        }
        return ob_get_clean();
    }

    private function get_info_data() : array {
        // Total de torneos (simple, consistente con general-stats-display)
        if (!$this->api || !method_exists($this->api, 'get_torneos')) {
            return ['total_torneos' => 0];
        }
        $todos = $this->api->get_torneos();
        return ['total_torneos' => is_array($todos) ? count($todos) : 0];
    }
}
