<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Rankgen_Route {
    public static function init() {
        add_action('template_redirect', array(__CLASS__, 'hook_content'));
    }
    public static function hook_content() {
        if (isset($_GET['view']) && $_GET['view'] === 'rankgen') {
            add_filter('the_content', array(__CLASS__, 'filter_content'), 9);
        }
    }
    public static function filter_content($content) {
        $slug = isset($_GET['slug']) ? sanitize_title($_GET['slug']) : '';
        if (!class_exists('Futbolin_Rankgen_Shortcode')) {
            $sc = FUTBOLIN_API_PATH . 'includes/shortcodes/class-futbolin-rankgen-shortcode.php';
            if (file_exists($sc)) require_once $sc;
        }
        if (class_exists('Futbolin_Rankgen_Shortcode')) {
            return Futbolin_Rankgen_Shortcode::render(array('slug'=>$slug));
        }
        return $content;
    }
}
Futbolin_Rankgen_Route::init();
