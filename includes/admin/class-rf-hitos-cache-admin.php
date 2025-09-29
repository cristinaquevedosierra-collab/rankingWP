<?php
/**
 * Página admin de Cache (Ranking Futbolín)
 * Submenú sencillo con dos botones: Generar/Actualizar y Purgar.
 * Progreso mostrado vía AJAX incremental.
 */
if (!defined('ABSPATH')) { exit; }

if (!class_exists('RF_Hitos_Cache_Admin')) {
    class RF_Hitos_Cache_Admin {
        public static function boot() {
            // Página antigua desactivada: no registrar más el submenú en Ajustes.
            // add_action('admin_menu', [__CLASS__, 'register_menu']); // <- desactivado
            add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
            // AJAX endpoints
            add_action('wp_ajax_rfhitos_cache_init', [__CLASS__, 'ajax_init']);
            add_action('wp_ajax_rfhitos_cache_step', [__CLASS__, 'ajax_step']);
            add_action('wp_ajax_rfhitos_cache_status', [__CLASS__, 'ajax_status']);
            add_action('wp_ajax_rfhitos_cache_purge', [__CLASS__, 'ajax_purge']);
            add_action('wp_ajax_rfhitos_cache_toggle', [__CLASS__, 'ajax_toggle']);
            // Redirección suave si alguien fuerza la URL ?page=rf-hitos-cache
            add_action('admin_init', function(){
                if (!is_admin()) return;
                if (isset($_GET['page']) && $_GET['page']==='rf-hitos-cache') {
                    $target = admin_url('admin.php?page=futbolin-api-settings&tab=calculos');
                    wp_safe_redirect($target);
                    exit;
                }
            });
        }
        // register_menu eliminado (legacy)
        public static function register_menu() { /* legacy – mantenido vacío intencionadamente */ }
        public static function enqueue($hook) {
            // Ahora siempre cargamos el script en admin (es ligero) para evitar casos donde el hook no coincida
            // y la UI embebida quede muda. (Se podría refinar si se desea más adelante.)
            wp_register_script(
                'rf-hitos-cache-admin',
                plugins_url('assets/js/rf-hitos-cache-admin.js', dirname(__FILE__,2) . '/ranking-futbolin.php'),
                ['jquery'],
                '1.0.0',
                true
            );
            wp_localize_script('rf-hitos-cache-admin', 'RFHITOSCACHE', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(RF_Hitos_Cache_Manager::NONCE_ACTION)
            ]);
            wp_enqueue_script('rf-hitos-cache-admin');
            wp_enqueue_style('rf-hitos-cache-admin-inline', false);
            add_action('admin_head', function(){
                echo '<style>.rf-hitos-progress{background:#e2e8f0;height:18px;border-radius:4px;overflow:hidden;margin:8px 0;}'
                    .'.rf-hitos-progress span{display:block;height:100%;background:#2563eb;width:0;transition:width .25s;}'
                    .'#rf-hitos-cache-log{font:12px/1.4 monospace;max-height:200px;overflow:auto;background:#111;color:#eee;padding:8px;border-radius:4px;}'
                    .'.rf-hitos-actions button{margin-right:8px;}</style>';
            });
        }
        public static function render_page() { /* legacy no-op */ }
        // === AJAX ===
        protected static function check_nonce() {
            check_ajax_referer(RF_Hitos_Cache_Manager::NONCE_ACTION, '_n');
        }
        public static function ajax_init() { self::check_nonce(); RF_Hitos_Cache_Manager::init_warm(); wp_send_json_success(RF_Hitos_Cache_Manager::status()); }
        public static function ajax_step() { self::check_nonce(); try { RF_Hitos_Cache_Manager::step(); wp_send_json_success(RF_Hitos_Cache_Manager::status()); } catch (\Throwable $e) { wp_send_json_error(['msg'=>$e->getMessage()]); } }
        public static function ajax_status() { self::check_nonce(); wp_send_json_success(RF_Hitos_Cache_Manager::status()); }
        public static function ajax_purge() { self::check_nonce(); RF_Hitos_Cache_Manager::purge(); wp_send_json_success(RF_Hitos_Cache_Manager::status()); }
        // Toggle enable
        public static function ajax_toggle() { self::check_nonce(); $en = isset($_POST['on']) && intval($_POST['on'])===1; RF_Hitos_Cache_Manager::set_enabled($en); wp_send_json_success(RF_Hitos_Cache_Manager::status()); }
    }
    RF_Hitos_Cache_Admin::boot();
}
