<?php
if (!defined('ABSPATH')) exit;

// Stubs/guards para análisis fuera de WP (no alteran runtime WP)
if (!function_exists('add_action')) { function add_action($h, $c, $p = 10, $a = 1) {} }
if (!function_exists('plugins_url')) { function plugins_url($path = '', $plugin = '') { return $path; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style($h, $src = '', $deps = [], $ver = false, $media = 'all') {} }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script($h, $src = '', $deps = [], $ver = false, $in_footer = false) {} }

class Futbolin_Assets {
    public function __construct(){ add_action('wp_enqueue_scripts',[$this,'enqueue_front'],999); add_action('admin_enqueue_scripts',[$this,'enqueue_admin']); }
    public function enqueue_front(){
        // Si el modo Shadow está activo (opción u override), no encolamos CSS global en el documento; el CSS va dentro del Shadow
            if ((function_exists('rf_shadow_enabled') ? rf_shadow_enabled() : (function_exists('get_option') && get_option('rf_shadow_mode', 0)))) {
                // Mantener sólo JS global necesario
                $base_url = plugins_url('assets/js/', dirname(__DIR__, 2) . '/ranking-futbolin.php');
                $base_path = dirname(__DIR__, 2) . '/assets/js/';
                $ver = defined('FUTBOLIN_API_VERSION') ? constant('FUTBOLIN_API_VERSION') : null;
                $js_files = ['main.js','hall-of-fame-search.js','hall-of-fame-pager.js','futbolin-ranking.js','finals-sort.js','history-filter.js','hitos-ui.js'];
                foreach($js_files as $js){
                    $path = $base_path . $js;
                    $v = file_exists($path) ? filemtime($path) : $ver;
                    wp_enqueue_script('futbolin-'.basename($js,'.js'), $base_url.$js, ['jquery'], $v, true);
                }
                return;
            }
        $base_url = plugins_url('assets/', dirname(__DIR__, 2) . '/ranking-futbolin.php');
        $ver = defined('FUTBOLIN_API_VERSION') ? constant('FUTBOLIN_API_VERSION') : null;
    $css_files = ['01-variables.css','02-components.css','03-layout.css','04-header.css','05-sidebar-forms.css','06-sidebar-menu.css','07-ranking-table.css','08-player-profile.css','09-h2h.css','10-pagination.css','11-ajax-search.css','12-h2h-integration.css','13-scrollbar.css','14-tab-content.css','16-final-fixes.css','17-loader.css','18-ranking-controls.css','19-player-profile-dynamic.css','20-ranking-category.css','21-admin-styles.css','22-ranking-styles.css','23-hall-of-fame-styles.css','24-finals-reports.css','25-futbolin-tournaments.css','26-maintenance.css','27-skeleton.css'];
            foreach($css_files as $css){
                $handle = 'futbolin-'.basename($css,'.css');
                $path = dirname(__DIR__, 2) . '/assets/css/' . $css;
                $v = file_exists($path) ? filemtime($path) : $ver;
                wp_enqueue_style($handle, $base_url.'css/'.$css, [], $v);
            }
    // Desactivado: el template de Hitos gestiona su CSS de forma aislada (inline por defecto con fallback a asset)
    // wp_enqueue_style('futbolin-hitos-cards', $base_url.'css/20-hitos-cards.css', [], $ver);
            $js_files = ['main.js','hall-of-fame-search.js','hall-of-fame-pager.js','futbolin-ranking.js','finals-sort.js', 'history-filter.js', 'hitos-ui.js'];
            foreach($js_files as $js){
                $path = dirname(__DIR__, 2) . '/assets/js/' . $js;
                $v = file_exists($path) ? filemtime($path) : $ver;
                wp_enqueue_script('futbolin-'.basename($js,'.js'), $base_url.'js/'.$js, ['jquery'], $v, true);
            }
    }
    public function enqueue_admin(){
        $base_url = plugins_url('assets/css/', dirname(__DIR__, 2) . '/ranking-futbolin.php');
        $ver = defined('FUTBOLIN_API_VERSION') ? constant('FUTBOLIN_API_VERSION') : null;
        wp_enqueue_style('futbolin-admin', $base_url.'21-admin-styles.css', [], $ver);
    }
}
new Futbolin_Assets();
