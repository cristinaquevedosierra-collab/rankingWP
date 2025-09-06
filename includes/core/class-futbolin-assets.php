<?php
if (!defined('ABSPATH')) exit;
class Futbolin_Assets {
    public function __construct(){ add_action('wp_enqueue_scripts',[$this,'enqueue_front']); add_action('admin_enqueue_scripts',[$this,'enqueue_admin']); }
    public function enqueue_front(){
        $base_url = plugins_url('assets/', dirname(__DIR__, 2) . '/ranking-futbolin.php');
        $css_files = ['01-variables.css','02-components.css','03-layout.css','04-header.css','05-sidebar-forms.css','06-sidebar-menu.css','07-ranking-table.css','08-player-profile.css','09-h2h.css','10-pagination.css','11-ajax-search.css','12-h2h-integration.css','13-scrollbar.css','14-tab-content.css','16-final-fixes.css','17-loader.css','18-ranking-controls.css','19-player-profile-dynamic.css','20-ranking-category.css','21-admin-styles.css','22-ranking-styles.css','23-hall-of-fame-styles.css','24-finals-reports.css','25-futbolin-tournaments.css'];
        foreach($css_files as $css){ wp_enqueue_style('futbolin-'.basename($css,'.css'), $base_url.'css/'.$css, [], defined('FUTBOLIN_API_VERSION')?FUTBOLIN_API_VERSION:null); }
        $js_files = ['main.js','hall-of-fame-search.js','hall-of-fame-pager.js','futbolin-ranking.js','finals-sort.js'];
        foreach($js_files as $js){ wp_enqueue_script('futbolin-'.basename($js,'.js'), $base_url.'js/'.$js, ['jquery'], defined('FUTBOLIN_API_VERSION')?FUTBOLIN_API_VERSION:null, true); }
    }
    public function enqueue_admin(){
        $base_url = plugins_url('assets/css/', dirname(__DIR__, 2) . '/ranking-futbolin.php');
        wp_enqueue_style('futbolin-admin', $base_url.'21-admin-styles.css', [], defined('FUTBOLIN_API_VERSION')?FUTBOLIN_API_VERSION:null);
    }
}
new Futbolin_Assets();
