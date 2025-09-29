<?php
if (!defined('ABSPATH')) exit;

// Stubs/guards para análisis fuera de WP (no afectan runtime WordPress)
if (!function_exists('add_action')) { function add_action($hook, $cb, $prio = 10, $args = 1) {} }
if (!function_exists('add_filter')) { function add_filter($hook, $cb, $prio = 10, $args = 1) {} }
if (!function_exists('sanitize_title')) { function sanitize_title($title) { return strtolower(trim(preg_replace('/[^a-z0-9\-]+/i','-',(string)$title), '-')); } }
if (!defined('FUTBOLIN_API_PATH')) { define('FUTBOLIN_API_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR); }

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
