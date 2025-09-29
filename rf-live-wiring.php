<?php
/**
 * RF Live Wiring Loader (v7)
 */
if (!defined('ABSPATH')) { exit; }

add_action('wp_enqueue_scripts', function(){
    if (is_admin()) return;
    // SIEMPRE activo en frontend para que el buscador funcione sin flags
    $rf_live_enabled = true;
    $base = plugin_dir_path(__FILE__);
    // CSS
    $css_rel = 'assets/css/rf-live.css';
    $css_abs = $base . $css_rel;
    $css_url = plugins_url($css_rel, __FILE__);
    // Versionar y encolar después de nuestros estilos base (si están) para poder sobreescribir detalles del dropdown
    $css_ver = file_exists($css_abs) ? filemtime($css_abs) : false;
        if ($rf_live_enabled) {
            wp_enqueue_style('rf-live', $css_url, array('dashicons'), $css_ver);
        }
    // JS
    $js_rel = 'assets/js/rf-live-wiring.js';
    $js_abs = $base . $js_rel;
    $js_url = plugins_url($js_rel, __FILE__);
    // Versionar por mtime para evitar cachés
    $js_ver = file_exists($js_abs) ? filemtime($js_abs) : false;
    if ($rf_live_enabled) {
      wp_enqueue_script('rf-live-wiring', $js_url, array(), $js_ver, true);
      wp_localize_script('rf-live-wiring', 'futbolin_ajax_obj', array(
          'ajax_url' => admin_url('admin-ajax.php'),
          'nonce'    => wp_create_nonce('futbolin_nonce'),
      ));
    }
}, 20);
