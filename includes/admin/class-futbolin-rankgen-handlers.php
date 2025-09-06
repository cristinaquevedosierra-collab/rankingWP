<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Rankgen_Handlers {
    public static function init() {
        add_action('admin_post_futb_rankgen_save', array(__CLASS__,'save'));
        add_action('admin_post_futb_rankgen_build', array(__CLASS__,'build'));
        add_action('admin_post_futb_rankgen_toggle', array(__CLASS__,'toggle'));
        add_action('admin_post_futb_rankgen_delete', array(__CLASS__,'delete'));
    }
    private static function sanitize_set($raw) {
        $set = is_array($raw) ? $raw : array();
        $out = array();
        $out['name'] = isset($set['name']) ? sanitize_text_field($set['name']) : '';
        $out['slug'] = isset($set['slug']) ? sanitize_title($set['slug']) : '';
        $out['is_enabled'] = empty($set['is_enabled']) ? '' : '1';
        $out['scope'] = isset($set['scope']) ? sanitize_text_field($set['scope']) : 'ESP';
        $out['modalidades'] = isset($set['modalidades']) ? array_map('sanitize_text_field', (array)$set['modalidades']) : array('1','2');
        $out['temporadaId'] = isset($set['temporadaId']) ? sanitize_text_field($set['temporadaId']) : '';
        $out['include_liguilla'] = empty($set['include_liguilla']) ? '' : '1';
        $out['include_cruces'] = empty($set['include_cruces']) ? '' : '1';
        $out['min_partidos'] = isset($set['min_partidos']) ? max(0, intval($set['min_partidos'])) : 0;
        $out['min_competiciones'] = isset($set['min_competiciones']) ? max(0, intval($set['min_competiciones'])) : 0;
        $out['top_n'] = isset($set['top_n']) ? max(1, intval($set['top_n'])) : 25;
        $out['sort_field'] = isset($set['sort_field']) ? sanitize_text_field($set['sort_field']) : 'win_rate_partidos';
        $out['sort_dir'] = isset($set['sort_dir']) ? sanitize_text_field($set['sort_dir']) : 'desc';
        $out['columns'] = isset($set['columns']) ? array_map('sanitize_text_field', (array)$set['columns']) : array();
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
        $drafts = get_option('futb_rankgen_drafts', array());
        $drafts[$slug] = $set;
        update_option('futb_rankgen_drafts', $drafts);
        return $slug;
    }
    private static function check_caps_nonce() {
        if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
        check_admin_referer('futb_rankgen_save');
    }
    public static function save() {
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $slug = self::save_set(self::sanitize_set($raw));
        wp_safe_redirect(add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','slug'=>$slug,'futb_msg'=>'saved'), admin_url('admin.php')));
        exit;
    }
    public static function build() {
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $set = self::sanitize_set($raw);
        $slug = self::save_set($set);
        // Optional: call service if exists
        if (!class_exists('Futbolin_Global_Stats_Service')) {
            $svc = FUTBOLIN_API_PATH . 'includes/services/class-futbolin-global-stats-service.php';
            if (file_exists($svc)) { require_once $svc; }
        }
        if (class_exists('Futbolin_Global_Stats_Service')) {
            $payload = Futbolin_Global_Stats_Service::rebuild($set);
        } else {
            $payload = array('rows'=>array(), 'columns'=>isset($set['columns'])?$set['columns']:array(), 'note'=>'service_not_found');
        }
        $cache = get_option('futb_rankgen_cache', array());
        $cache[$slug] = $payload;
        update_option('futb_rankgen_cache', $cache);
        update_option('futb_rankgen_cache_ts_'.$slug, current_time('mysql'));
        wp_safe_redirect(add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','slug'=>$slug,'futb_msg'=>'built'), admin_url('admin.php')));
        exit;
    }
    public static function toggle() {
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $set = self::sanitize_set($raw);
        $set['is_enabled'] = ($set['is_enabled']==='1') ? '' : '1';
        $slug = self::save_set($set);
        wp_safe_redirect(add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','slug'=>$slug,'futb_msg'=>'toggled'), admin_url('admin.php')));
        exit;
    }
    public static function delete() {
        self::check_caps_nonce();
        $raw = isset($_POST['set']) ? (array)$_POST['set'] : array();
        $slug = isset($raw['slug']) ? sanitize_title($raw['slug']) : '';
        $drafts = get_option('futb_rankgen_drafts', array());
        if ($slug && isset($drafts[$slug])) {
            unset($drafts[$slug]);
            update_option('futb_rankgen_drafts', $drafts);
        }
        $cache = get_option('futb_rankgen_cache', array());
        if (isset($cache[$slug])) {
            unset($cache[$slug]);
            update_option('futb_rankgen_cache', $cache);
        }
        delete_option('futb_rankgen_cache_ts_'.$slug);
        wp_safe_redirect(add_query_arg(array('page'=>'futbolin-api-settings','tab'=>'rankgen','futb_msg'=>'deleted'), admin_url('admin.php')));
        exit;
    }
}
Futbolin_Rankgen_Handlers::init();
