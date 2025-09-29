<?php
if (!defined('ABSPATH')) exit;

// Stubs/guards para análisis fuera de WP (no afectan runtime WP)
if (!function_exists('add_action')) { function add_action($h,$c,$p=10,$a=1){} }
if (!function_exists('wp_die')) { function wp_die($msg=''){ throw new Exception(is_string($msg)?$msg:''); } }
if (!function_exists('check_admin_referer')) { function check_admin_referer($a,$b='_wpnonce'){ return true; } }
if (!function_exists('admin_url')) { function admin_url($path=''){ return $path; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args,$url=''){ return $url; } }
if (!function_exists('wp_safe_redirect')) { function wp_safe_redirect($url){ /* no-op */ } }
if (!function_exists('current_time')) { function current_time($type='mysql'){ return date('Y-m-d H:i:s'); } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $d; } }
if (!function_exists('update_option')) { function update_option($k,$v){ return true; } }
if (!function_exists('delete_option')) { function delete_option($k){ return true; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return is_string($v)?trim($v):$v; } }
if (!function_exists('sanitize_title')) { function sanitize_title($v){ return strtolower(trim(preg_replace('/[^a-z0-9\-]+/i','-',(string)$v),'-')); } }
if (!function_exists('current_user_can')) { function current_user_can($cap){ return true; } }
if (!defined('FUTBOLIN_API_PATH')) { define('FUTBOLIN_API_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR); }

class Futbolin_Rankgen_Handlers {
    public static function init() {
        add_action('admin_post_futb_rankgen_save', array(__CLASS__,'save'));
        add_action('admin_post_futb_rankgen_build', array(__CLASS__,'build'));
        add_action('admin_post_futb_rankgen_toggle', array(__CLASS__,'toggle'));
        add_action('admin_post_futb_rankgen_delete', array(__CLASS__,'delete'));
        // Hooks nopriv por si alguna instalación pierde el contexto admin (no deberían dispararse en admin)
        add_action('admin_post_nopriv_futb_rankgen_save', array(__CLASS__,'save'));
        add_action('admin_post_nopriv_futb_rankgen_build', array(__CLASS__,'build'));
        add_action('admin_post_nopriv_futb_rankgen_toggle', array(__CLASS__,'toggle'));
        add_action('admin_post_nopriv_futb_rankgen_delete', array(__CLASS__,'delete'));
    }
    private static function sanitize_set($raw) {
        $set = is_array($raw) ? $raw : array();
        $out = array();
        $out['name'] = isset($set['name']) ? sanitize_text_field($set['name']) : '';
        $out['slug'] = isset($set['slug']) ? sanitize_title($set['slug']) : '';
        // Descripción: permitir HTML básico
        if (isset($set['description'])) {
            $allowed = array(
                'a' => array('href'=>true,'title'=>true,'target'=>true,'rel'=>true),
                'br' => array(),
                'em' => array(),
                'strong' => array(),
                'p' => array(),
                'ul' => array(),
                'ol' => array(),
                'li' => array(),
                'span' => array('class'=>true),
              );
            $out['description'] = function_exists('wp_kses') ? wp_kses($set['description'], $allowed) : strip_tags($set['description']);
        } else { $out['description'] = ''; }
        $out['is_enabled'] = empty($set['is_enabled']) ? '' : '1';
        $out['scope'] = isset($set['scope']) ? sanitize_text_field($set['scope']) : 'ESP';
        $out['modalidades'] = isset($set['modalidades']) ? array_map('sanitize_text_field', (array)$set['modalidades']) : array('1','2');
        $out['temporadaId'] = isset($set['temporadaId']) ? sanitize_text_field($set['temporadaId']) : '';
        $out['include_liguilla'] = empty($set['include_liguilla']) ? '' : '1';
        $out['include_cruces'] = empty($set['include_cruces']) ? '' : '1';
        $out['min_partidos'] = isset($set['min_partidos']) ? max(0, intval($set['min_partidos'])) : 0;
        $out['min_competiciones'] = isset($set['min_competiciones']) ? max(0, intval($set['min_competiciones'])) : 0;
    // Filtros avanzados para HOF/Globales
    $out['min_victorias'] = isset($set['min_victorias']) ? max(0, intval($set['min_victorias'])) : 0;
    $out['require_campeonato'] = empty($set['require_campeonato']) ? '' : '1';
        $out['top_n'] = isset($set['top_n']) ? max(1, intval($set['top_n'])) : 25;
        $out['sort_field'] = isset($set['sort_field']) ? sanitize_text_field($set['sort_field']) : 'win_rate_partidos';
        $out['sort_dir'] = isset($set['sort_dir']) ? sanitize_text_field($set['sort_dir']) : 'desc';
        $out['columns'] = isset($set['columns']) ? array_map('sanitize_text_field', (array)$set['columns']) : array();
        // Nuevos campos
        $out['torneos_all'] = empty($set['torneos_all']) ? '' : '1';
        $front_layout = isset($set['front_layout']) ? sanitize_text_field($set['front_layout']) : 'with';
        $out['front_hide_sidebar'] = ($front_layout === 'without') ? '1' : '';
        // Catálogos del UI (arrays directos)
        if (isset($set['torneoIds'])) {
            $out['torneoIds'] = array_map('intval', (array)$set['torneoIds']);
        }
        if (isset($set['competicionIds'])) {
            $out['competicionIds'] = array_map('intval', (array)$set['competicionIds']);
        }
        if (!empty($set['tipos_comp_raw'])) {
            $arr = array_map('trim', explode(',', $set['tipos_comp_raw']));
            $out['tipos_comp'] = array_filter($arr);
        } else { $out['tipos_comp'] = array(); }
        if (!empty($set['torneos_raw'])) {
            $arr = array_map('trim', explode(',', $set['torneos_raw']));
            $arr = array_filter($arr, function($v){ return $v!==''; });
            $out['torneos'] = array_values($arr);
        } else { $out['torneos'] = array(); }
        if (empty($out['slug'])) { $out['slug'] = sanitize_title($out['name']); }
        return $out;
    }
    private static function save_set($set) {
        $slug = $set['slug'];
        // Migración a nuevo storage: futb_rankgen_sets
        $sets = get_option('futb_rankgen_sets', array());
        if (!is_array($sets)) $sets = array();
        $sets[$slug] = $set;
        update_option('futb_rankgen_sets', $sets);
        // Mantener compatibilidad escribiendo también en drafts si existiera
        $drafts = get_option('futb_rankgen_drafts', null);
        if (is_array($drafts)) {
            $drafts[$slug] = $set;
            update_option('futb_rankgen_drafts', $drafts);
        }
        return $slug;
    }
    private static function check_caps_nonce() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('futb_rankgen_save');
    }
    public static function save() {
        if (function_exists('rf_log')) { rf_log('rankgen save handler enter', ['_POST'=>isset($_POST)?array_keys($_POST):[]], 'debug'); }
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $clean = self::sanitize_set($raw);
        $slug = self::save_set($clean);
        // Sincronizar estado con Configuración global
        try {
            if ($slug !== '') {
                $opts = get_option('mi_plugin_futbolin_options', array());
                if (!is_array($opts)) { $opts = array(); }
                $gkey = 'enable_rankgen__' . sanitize_key($slug);
                $opts[$gkey] = !empty($clean['is_enabled']) ? 1 : 0;
                update_option('mi_plugin_futbolin_options', $opts);
            }
        } catch (\Throwable $e) { /* ignore */ }
        $redir = add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','slug'=>$slug,'futb_msg'=>'saved'), admin_url('admin.php'));
        if (function_exists('rf_log')) { rf_log('rankgen save handler exit', ['slug'=>$slug, 'redirect'=>$redir], 'debug'); }
        wp_safe_redirect($redir);
        if (!headers_sent()) { @wp_redirect($redir); }
        exit;
    }
    public static function build() {
        if (function_exists('rf_log')) { rf_log('rankgen build handler enter', ['_POST'=>isset($_POST)?array_keys($_POST):[]], 'debug'); }
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $set = self::sanitize_set($raw);
        $slug = self::save_set($set);
        // Usar el servicio del generador (staged) si existe
        if (!class_exists('Futbolin_Rankgen_Service')) {
            $svc = FUTBOLIN_API_PATH . 'includes/services/class-futbolin-rankgen-service.php';
            if (file_exists($svc)) { require_once $svc; }
        }
        if (class_exists('Futbolin_Rankgen_Service')) {
            // Inicio rápido y pasos en bucle hasta finalizar para ejecución síncrona desde POST
            $start = Futbolin_Rankgen_Service::start_job($slug, $set);
            if (is_object($start) && method_exists($start,'get_error_message')) {
                // Fallo de inicio, guardamos marca mínima
                update_option('futb_rankgen_cache_ts_'.$slug, current_time('mysql'));
            } else {
                // Avanzar hasta terminar o límite de iteraciones
                $maxLoops = 500; $loops=0; $finished=false; $last=array();
                while (!$finished && $loops < $maxLoops) {
                    $last = Futbolin_Rankgen_Service::step_job($slug);
                    if (is_object($last) && method_exists($last,'get_error_message')) break;
                    $finished = !empty($last['finished']);
                    $loops++;
                }
                update_option('futb_rankgen_cache_ts_'.$slug, current_time('mysql'));
            }
        } else {
            // Fallback: payload vacío para no romper el front
            $cache = get_option('futb_rankgen_cache', array());
            $cache[$slug] = array('rows'=>array(), 'columns'=>isset($set['columns'])?$set['columns']:array(), 'note'=>'rankgen_service_missing');
            update_option('futb_rankgen_cache', $cache);
            update_option('futb_rankgen_cache_ts_'.$slug, current_time('mysql'));
        }
        $redir = add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','slug'=>$slug,'futb_msg'=>'built'), admin_url('admin.php'));
        if (function_exists('rf_log')) { rf_log('rankgen build handler exit', ['slug'=>$slug, 'redirect'=>$redir], 'debug'); }
        wp_safe_redirect($redir);
        if (!headers_sent()) { @wp_redirect($redir); }
        exit;
    }
    public static function toggle() {
        if (function_exists('rf_log')) { rf_log('rankgen toggle handler enter', ['_POST'=>isset($_POST)?array_keys($_POST):[]], 'debug'); }
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $set = self::sanitize_set($raw);
        $set['is_enabled'] = ($set['is_enabled']==='1') ? '' : '1';
        $slug = self::save_set($set);
        // Sincronizar opción global
        try {
            if ($slug !== '') {
                $opts = get_option('mi_plugin_futbolin_options', array());
                if (!is_array($opts)) { $opts = array(); }
                $gkey = 'enable_rankgen__' . sanitize_key($slug);
                $opts[$gkey] = !empty($set['is_enabled']) ? 1 : 0;
                update_option('mi_plugin_futbolin_options', $opts);
            }
        } catch (\Throwable $e) { /* ignore */ }
        $redir = add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','slug'=>$slug,'futb_msg'=>'toggled'), admin_url('admin.php'));
        if (function_exists('rf_log')) { rf_log('rankgen toggle handler exit', ['slug'=>$slug, 'redirect'=>$redir], 'debug'); }
        wp_safe_redirect($redir);
        if (!headers_sent()) { @wp_redirect($redir); }
        exit;
    }
    public static function delete() {
        if (function_exists('rf_log')) { rf_log('rankgen delete handler enter', ['_POST'=>isset($_POST)?array_keys($_POST):[], '_GET'=>isset($_GET)?array_keys($_GET):[]], 'debug'); }
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $slug = isset($raw['slug']) ? sanitize_title($raw['slug']) : '';
        if ($slug === '' && isset($_POST['slug'])) { $slug = sanitize_title($_POST['slug']); }
        if ($slug === '' && isset($_GET['slug']))  { $slug = sanitize_title($_GET['slug']); }
        if (function_exists('rf_log')) { rf_log('rankgen delete requested', ['slug'=>$slug], $slug? 'info':'warning'); }
        // Borrar de nuevo storage
        $sets = get_option('futb_rankgen_sets', array());
        if ($slug && isset($sets[$slug])) { unset($sets[$slug]); update_option('futb_rankgen_sets', $sets); }
        // Mantener compat
        $drafts = get_option('futb_rankgen_drafts', array());
        if ($slug && isset($drafts[$slug])) { unset($drafts[$slug]); update_option('futb_rankgen_drafts', $drafts); }
        $cache = get_option('futb_rankgen_cache', array());
        if (isset($cache[$slug])) {
            unset($cache[$slug]);
            update_option('futb_rankgen_cache', $cache);
        }
        delete_option('futb_rankgen_cache_ts_'.$slug);
        // También elimina el toggle dinámico en Configuración para que no aparezca marcado
        $opts = get_option('mi_plugin_futbolin_options', array());
        if (is_array($opts)) {
            $key = 'enable_rankgen__' . sanitize_key($slug);
            if (isset($opts[$key])) { unset($opts[$key]); update_option('mi_plugin_futbolin_options', $opts); }
        }
        if (function_exists('rf_log')) { rf_log('rankgen delete finished', ['slug'=>$slug, 'remaining_sets'=> is_array($sets)? count($sets):0], 'info'); }
        $redir = add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','futb_msg'=>'deleted'), admin_url('admin.php'));
        wp_safe_redirect($redir);
        if (!headers_sent()) { @wp_redirect($redir); }
        exit;
    }
}
Futbolin_Rankgen_Handlers::init();
