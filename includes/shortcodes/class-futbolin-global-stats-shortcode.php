<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Global_Stats_Shortcode {
    public function render($atts, $view) {
        $atts = shortcode_atts([ 'wrap' => '1' ], $atts, 'futbolin_global_stats');
        $wrap = ($atts['wrap'] === '1');

        $show_back_btn    = true;
        $hide_sidebar     = true;
        $template_to_load = 'global-stats-display.php';

        ob_start();
        $wrapper_path = FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        if ($wrap && file_exists($wrapper_path)) {
            include $wrapper_path;
        } else {
            include FUTBOLIN_API_PATH . 'includes/template-parts/' . $template_to_load;
        }
        return ob_get_clean();
    }
}
