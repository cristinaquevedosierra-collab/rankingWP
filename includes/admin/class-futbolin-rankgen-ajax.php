<?php
if (!defined('ABSPATH')) exit;
class Futbolin_Rankgen_Ajax {
    public static function init() {
        add_action('wp_ajax_futb_rankgen_build_start', [__CLASS__,'build_start']);
        add_action('wp_ajax_futb_rankgen_build_step',  [__CLASS__,'build_step']);
        add_action('wp_ajax_futb_rankgen_catalog',     [__CLASS__,'catalog']);
    }
    private static function base_url(){
        $cfg = get_option('ranking_api_config', array());
        if (is_array($cfg) && !empty($cfg['base_url'])) return rtrim($cfg['base_url'],'/');
        $opts = get_option('mi_plugin_futbolin_options', array());
        if (is_array($opts) && !empty($opts['api_base_url'])) return rtrim($opts['api_base_url'],'/');
        return '';
    }
    private static function get_json($path, $timeout=30){
        $base = self::base_url();
        if (!$base) return new WP_Error('no_base','API base_url no configurado');
        $url = rtrim($base,'/').$path;
        $headers = array('Accept'=>'application/json');
        $token = get_transient('futbolin_api_token');
        if (!empty($token)) $headers['Authorization'] = 'Bearer '.$token;
        $res = wp_remote_get($url, array('timeout'=>$timeout,'headers'=>$headers));
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) return new WP_Error('http_'.$code, 'HTTP '.$code.' '+$url);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) return new WP_Error('json','JSON inválido en '.$url);
        return $json;
    }
    public static function build_start(){
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden',403);
        check_ajax_referer('futb_rankgen_nonce','nonce');
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        if (!$slug) wp_send_json_error('slug vacío',400);
        if (!class_exists('Futbolin_Rankgen_Service')) require_once FUTBOLIN_API_PATH.'includes/services/class-futbolin-rankgen-service.php';
        $ret = Futbolin_Rankgen_Service::start_job($slug);
        if (is_wp_error($ret)) wp_send_json_error($ret->get_error_message(),400);
        wp_send_json_success($ret);
    }
    public static function build_step(){
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden',403);
        check_ajax_referer('futb_rankgen_nonce','nonce');
        $slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
        if (!$slug) wp_send_json_error('slug vacío',400);
        if (!class_exists('Futbolin_Rankgen_Service')) require_once FUTBOLIN_API_PATH.'includes/services/class-futbolin-rankgen-service.php';
        $ret = Futbolin_Rankgen_Service::step_job($slug);
        if (is_wp_error($ret)) wp_send_json_error($ret->get_error_message(),400);
        wp_send_json_success($ret);
    }
    public static function catalog(){
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden',403);
        check_ajax_referer('futb_rankgen_nonce','nonce');
        $kind = isset($_GET['kind']) ? sanitize_key($_GET['kind']) : '';
        if (!$kind) wp_send_json_error('kind requerido',400);

        if ($kind === 'seasons'){
            $page = 1; $pageSize = 100; $maxPages = 10;
            $seen = array(); $out = array();
            while ($page <= $maxPages){
                $json = self::get_json("/api/Torneo/GetTorneosPag?page={$page}&pageSize={$pageSize}");
                if (is_wp_error($json) || empty($json['items'])) break;
                foreach ($json['items'] as $it){
                    if (!isset($it['temporadaId'])) continue;
                    $id = strval($it['temporadaId']);
                    if (isset($seen[$id])) continue;
                    $seen[$id]=1;
                    $label = isset($it['temporada']) ? $it['temporada'] : $id;
                    $out[] = array('id'=>$id, 'text'=>$label);
                }
                if (empty($json['hasNextPage'])) break;
                $page++;
            }
            wp_send_json_success(array('items'=>$out));
        } elseif ($kind === 'tournaments'){
            $temporadaId = isset($_GET['temporadaId']) ? sanitize_text_field($_GET['temporadaId']) : '';
            $page = 1; $pageSize = 100; $maxPages = 10;
            $out = array();
            while ($page <= $maxPages){
                $json = self::get_json("/api/Torneo/GetTorneosPag?page={$page}&pageSize={$pageSize}");
                if (is_wp_error($json) || empty($json['items'])) break;
                foreach ($json['items'] as $it){
                    if ($temporadaId !== '' && isset($it['temporadaId']) && strval($it['temporadaId']) !== $temporadaId) continue;
                    $tid = isset($it['torneoId']) ? intval($it['torneoId']) : 0;
                    if (!$tid) continue;
                    $name = isset($it['nombreTorneo']) ? $it['nombreTorneo'] : ('Torneo '.$tid);
                    $fecha = isset($it['fecha']) ? substr($it['fecha'],0,10) : '';
                    $out[] = array('id'=>$tid, 'text'=>trim($fecha+' '+$name) );
                }
                if (empty($json['hasNextPage'])) break;
                $page++;
            }
            wp_send_json_success(array('items'=>$out));
        } elseif ($kind === 'competitions'){
            $torneoIds = isset($_GET['torneoIds']) ? (array) $_GET['torneoIds'] : array();
            $torneoIds = array_filter(array_map('intval', $torneoIds));
            $seen = array(); $out = array();
            foreach ($torneoIds as $tid){
                $json = self::get_json("/api/Torneo/GetTorneoConPosiciones/{$tid}");
                if (is_wp_error($json) || empty($json)) continue;
                if (isset($json['competiciones']) && is_array($json['competiciones'])){
                    foreach ($json['competiciones'] as $c){
                        if (!isset($c['competicionId'])) continue;
                        $cid = intval($c['competicionId']);
                        if (isset($seen[$cid])) continue;
                        $seen[$cid]=1;
                        $name = isset($c['nombreCompeticion']) ? $c['nombreCompeticion'] : ('Competición '.$cid);
                        $out[] = array('id'=>$cid, 'text'=>$name);
                    }
                }
            }
            wp_send_json_success(array('items'=>$out));
        } else {
            wp_send_json_error('kind inválido',400);
        }
    }
}
Futbolin_Rankgen_Ajax::init();
