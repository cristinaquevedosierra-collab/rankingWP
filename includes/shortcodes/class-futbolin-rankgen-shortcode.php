<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Rankgen_Shortcode {
    public static function init() { add_shortcode('futb_rankgen', array(__CLASS__, 'render')); }
    public static function render($atts) {
        $atts = shortcode_atts(array('slug'=>''), $atts, 'futb_rankgen');
        $slug = sanitize_title($atts['slug']);
        if (!$slug) return '<div class="futbolin-card"><p>Ranking no especificado.</p></div>';
        // Leer del nuevo storage con fallback al antiguo
        $sets = get_option('futb_rankgen_sets', array());
        if (!isset($sets[$slug])) { $sets = get_option('futb_rankgen_drafts', array()); }
        if (!isset($sets[$slug])) return '<div class="futbolin-card"><p>Listado no encontrado.</p></div>';
        $set = $sets[$slug];
        if (empty($set['is_enabled'])) return '<div class="futbolin-card"><p>Ranking desactivado.</p></div>';
        // Obtener payload de caché per-slug (nuevo) o del option legado (array asociativo)
        $payload = get_option('futb_rankgen_cache_'.$slug, null);
        // Fallback robusto al option legado si el per-slug no existe o no tiene filas
        if (!is_array($payload) || !isset($payload['rows']) || !is_array($payload['rows']) || !count($payload['rows'])) {
            $legacy = get_option('futb_rankgen_cache', array());
            $payload = isset($legacy[$slug]) && is_array($legacy[$slug]) ? $legacy[$slug] : array('rows'=>array(), 'columns'=>array());
        }
        $cols = isset($set['columns']) && is_array($set['columns']) ? $set['columns'] : array('posicion_estatica','nombre','partidas_jugadas','partidas_ganadas','win_rate_partidos');

    // Render dentro del wrapper principal, eligiendo plantilla y sidebar
    $hide_sidebar = !empty($set['front_hide_sidebar']);
    // Mostrar botón "Volver" en estas vistas
    $show_back_btn = true;
    // Flag de Shadow DOM opcional desde ajustes (para aislar estilos del tema)
    $rf_shadow_mode = (int) get_option('rf_shadow_mode', 0);
        $template_to_load = 'rankgen-list-display.php';
        // Variables disponibles dentro del wrapper y la plantilla
        $rankgen_title = isset($set['name']) && $set['name'] ? $set['name'] : 'Listado';
    $rankgen_columns = $cols;
        $rankgen_rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : array();
    $rankgen_description = isset($set['description']) ? (string)$set['description'] : '';

        ob_start();
        include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        return ob_get_clean();
    }
}
Futbolin_Rankgen_Shortcode::init();
