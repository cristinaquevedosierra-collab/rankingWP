<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Rankgen_Shortcode {
    public static function init() { add_shortcode('futb_rankgen', array(__CLASS__, 'render')); }
    public static function render($atts) {
        $atts = shortcode_atts(array('slug'=>''), $atts, 'futb_rankgen');
        $slug = sanitize_title($atts['slug']);
        if (!$slug) return '<div class="futbolin-card"><p>Ranking no especificado.</p></div>';
        $drafts = get_option('futb_rankgen_drafts', array());
        if (!isset($drafts[$slug])) return '<div class="futbolin-card"><p>Ranking no encontrado.</p></div>';
        $set = $drafts[$slug];
        if (empty($set['is_enabled'])) return '<div class="futbolin-card"><p>Ranking desactivado.</p></div>';
        $cache = get_option('futb_rankgen_cache', array());
        $payload = isset($cache[$slug]) ? $cache[$slug] : array('rows'=>array(), 'columns'=>array());
        $cols = isset($set['columns']) && is_array($set['columns']) ? $set['columns'] : array('pos','nombre','partidas','ganadas','win_rate_partidos');
        ob_start(); ?>
        <div class="futbolin-hall-of-fame-wrapper">
            <h2><?php echo esc_html( isset($set['name']) && $set['name'] ? $set['name'] : 'Ranking'); ?></h2>
            <div class="hall-of-fame-table-container">
                <div class="ranking-header">
                    <?php foreach ($cols as $c): ?>
                        <div class="ranking-th"><span><?php echo esc_html($c); ?></span></div>
                    <?php endforeach; ?>
                </div>
                <div class="ranking-rows">
                    <?php $rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : array();
                    if (empty($rows)) {
                        echo '<div class="ranking-row"><div class="ranking-cell">'.esc_html__('Sin datos (pendiente de generar caché).','futbolin').'</div></div>';
                    } else {
                        foreach ($rows as $row) {
                            echo '<div class="ranking-row">';
                            foreach ($cols as $c) {
                                $val = isset($row[$c]) ? $row[$c] : '';
                                echo '<div class="ranking-cell">'.esc_html($val).'</div>';
                            }
                            echo '</div>';
                        }
                    } ?>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }
}
Futbolin_Rankgen_Shortcode::init();
