<?php
if (!defined('ABSPATH')) exit;
// Nota: No se definen stubs aquí para no interferir con funciones pluggable de WordPress.
if (!defined('FUTBOLIN_API_PATH')) { define('FUTBOLIN_API_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR); }
class Futbolin_Rankgen_Ajax {
    public static function init() {
        add_action('wp_ajax_futb_rankgen_build_start', [__CLASS__,'build_start']);
        add_action('wp_ajax_futb_rankgen_build_step',  [__CLASS__,'build_step']);
        add_action('wp_ajax_futb_rankgen_catalog',     [__CLASS__,'catalog']);
        add_action('wp_ajax_futb_rankgen_cache_info',  [__CLASS__,'cache_info']);
    }
    private static function base_url(){
        $cfg = get_option('ranking_api_config', array());
        $base = '';
        if (is_array($cfg) && !empty($cfg['base_url'])) $base = trim((string)$cfg['base_url']);
        if ($base === '') {
            $opts = get_option('mi_plugin_futbolin_options', array());
            if (is_array($opts) && !empty($opts['api_base_url'])) $base = trim((string)$opts['api_base_url']);
        }
        if ($base === '') return '';
        $base = rtrim($base, "/\\");
        // Asegura sufijo /api una sola vez
        if (stripos($base, '/api') === false) { $base .= '/api'; }
        $base = rtrim($base,'/');
        return $base;
    }
    private static function ensure_token(){
        $tok = get_transient('futbolin_api_token');
        if (!empty($tok)) return $tok;
        // Intentar login con ranking_api_config
        $cfg = get_option('ranking_api_config', array());
        $base = self::base_url(); if (!$base) return '';
        $user = is_array($cfg)&&!empty($cfg['username']) ? (string)$cfg['username'] : '';
        $pass = is_array($cfg)&&isset($cfg['password']) ? (string)$cfg['password'] : '';
        if ($user === '' || $pass === '') return '';
        $login_url = rtrim($base,'/') . '/Seguridad/login';
        $resp = wp_remote_post($login_url, array(
            'timeout'=> 15,
            'headers'=> array('Content-Type'=>'application/json','Accept'=>'application/json'),
            'sslverify'=> false,
            'body'   => wp_json_encode(array('usuario'=>$user,'password'=>$pass)),
        ));
        if (is_wp_error($resp)) return '';
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return '';
        $body = wp_remote_retrieve_body($resp);
        $token = '';
        $j = json_decode($body);
        if (is_object($j)){
            foreach (array('token','accessToken','access_token','bearer','jwt') as $k){ if (isset($j->$k) && is_string($j->$k) && $j->$k!==''){ $token = $j->$k; break; } }
        }
        if ($token === ''){
            $ja = json_decode($body, true);
            if (is_array($ja)){
                $paths = array(array('data','token'), array('result','token'));
                foreach ($paths as $p){ $tmp=$ja; foreach ($p as $seg){ if (is_array($tmp) && array_key_exists($seg,$tmp)) { $tmp=$tmp[$seg]; } else { $tmp=null; break; } } if (is_string($tmp)&&$tmp!==''){ $token=$tmp; break; } }
            }
        }
        if ($token !== '') { set_transient('futbolin_api_token', $token, 50 * MINUTE_IN_SECONDS); }
        return $token;
    }
    private static function unwrap_items($json){
        if (empty($json)) return array();
        if (is_array($json)) {
            $is_list = array_keys($json) === range(0, count($json)-1);
            if ($is_list) return $json;
            if (isset($json['items']) && is_array($json['items'])) return $json['items'];
            if (isset($json['data']['items']) && is_array($json['data']['items'])) return $json['data']['items'];
            if (isset($json['result']['items']) && is_array($json['result']['items'])) return $json['result']['items'];
            if (isset($json['rows']) && is_array($json['rows'])) return $json['rows'];
            if (isset($json['data']) && is_array($json['data']) && array_keys($json['data']) === range(0, count($json['data'])-1)) return $json['data'];
            if (isset($json['result']) && is_array($json['result']) && array_keys($json['result']) === range(0, count($json['result'])-1)) return $json['result'];
        }
        return array();
    }
    private static function get_json($path, $timeout=30){
        $base = self::base_url();
        if (!$base) return new WP_Error('no_base','API base_url no configurado');
        // Evitar doble /api: si $base ya termina en /api y $path empieza por /api/, recortar el prefijo
        $path_norm = is_string($path) ? $path : '';
        if ($path_norm === '') return new WP_Error('bad_path','Ruta vacía');
    if (strpos($path_norm, '/api/') === 0) { $path_norm = substr($path_norm, 4); }
        // Asegurar que $path_norm comience por '/'
        if ($path_norm[0] !== '/') { $path_norm = '/'.$path_norm; }
        $url = rtrim($base,'/') . $path_norm;
        $headers = array('Accept'=>'application/json');
        $token = get_transient('futbolin_api_token');
        if (empty($token)) { $token = self::ensure_token(); }
        if (!empty($token)) $headers['Authorization'] = 'Bearer '.$token;
        $res = wp_remote_get($url, array('timeout'=>$timeout,'headers'=>$headers,'sslverify'=>false));
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return new WP_Error('http_'.$code, 'HTTP '.$code.' '.$url);
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
        // Cargar set desde nuevo storage con fallback
        $sets = get_option('futb_rankgen_sets', array());
        if (!isset($sets[$slug])) { $sets = get_option('futb_rankgen_drafts', array()); }
        $set = isset($sets[$slug]) ? $sets[$slug] : array();
        $ret = Futbolin_Rankgen_Service::start_job($slug, $set);
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
            // 1) Intento principal: endpoint de Temporadas directo
            $out = array();
            $json = self::get_json('/Temporada/GetTemporadas');
            if (is_wp_error($json)) {
                if (function_exists('rf_log')) { rf_log('rankgen catalog seasons error', ['url'=>'/Temporada/GetTemporadas', 'err'=>$json->get_error_message()]); }
            } else {
                $items = self::unwrap_items($json);
                if (empty($items) && is_array($json)) { $items = $json; }
                foreach ((array)$items as $it){
                    // Acepta items escalares (año como 2024) o estructuras variadas
                    if (is_scalar($it)) { $id = (string)$it; $out[] = array('id'=>$id,'text'=>$id); continue; }
                    $id = null; $label = '';
                    if (is_array($it)) {
                        $id = $it['id'] ?? ($it['temporadaId'] ?? ($it['idTemporada'] ?? null));
                        // Caso anidado: temporada => { id, nombre, anio }
                        if ($id === null && isset($it['temporada']) && (is_array($it['temporada']) || is_object($it['temporada']))) {
                            $tmp = (array)$it['temporada'];
                            $id = $tmp['id'] ?? ($tmp['temporadaId'] ?? null);
                            $label = $tmp['nombre'] ?? ($tmp['temporada'] ?? ($tmp['anio'] ?? ''));
                        }
                        if ($label === '') { $label = $it['temporada'] ?? ($it['nombreTemporada'] ?? ($it['anio'] ?? ($it['anioTemporada'] ?? ($it['texto'] ?? '')))); }
                    } elseif (is_object($it)) {
                        $id = $it->id ?? ($it->temporadaId ?? ($it->idTemporada ?? null));
                        if ($id === null && isset($it->temporada) && (is_array($it->temporada) || is_object($it->temporada))) {
                            $tmp = (array)$it->temporada;
                            $id = $tmp['id'] ?? ($tmp['temporadaId'] ?? null);
                            $label = $tmp['nombre'] ?? ($tmp['temporada'] ?? ($tmp['anio'] ?? ''));
                        }
                        if ($label === '') { $label = $it->temporada ?? ($it->nombreTemporada ?? ($it->anio ?? ($it->anioTemporada ?? ($it->texto ?? '')))); }
                    }
                    if ($id === null) continue; $id = (string)$id; if ($label === '' || $label === null) { $label = $id; }
                    $out[] = array('id'=>$id, 'text'=>(string)$label);
                }
            }
            // 1b) Fallback alternativo: endpoint de temporadas paginado si existe
            if (empty($out)) {
                $page=1; $pageSize=100; $seen=array();
                while (true) {
                    $j = self::get_json("/Temporada/GetTemporadasPag?page={$page}&pageSize={$pageSize}");
                    if (is_wp_error($j)) { if (function_exists('rf_log')) { rf_log('rankgen catalog seasons pag error', ['page'=>$page,'err'=>$j->get_error_message()]); } break; }
                    $items = self::unwrap_items($j); if (empty($items) && is_array($j)) { $items = $j; }
                    if (empty($items)) break;
                    foreach ($items as $it){
                        if (is_scalar($it)) { $id=(string)$it; if(!isset($seen[$id])){ $seen[$id]=1; $out[]=['id'=>$id,'text'=>$id]; } continue; }
                        $id=null; $label='';
                        if (is_array($it)) { $id=$it['id']??($it['temporadaId']??($it['idTemporada']??null)); $label=$it['temporada']??($it['nombreTemporada']??($it['anio']??($it['anioTemporada']??''))); }
                        else { $id=$it->id??($it->temporadaId??($it->idTemporada??null)); $label=$it->temporada??($it->nombreTemporada??($it->anio??($it->anioTemporada??''))); }
                        if ($id===null) continue; $id=(string)$id; if(!isset($seen[$id])){ $seen[$id]=1; $out[]=['id'=>$id,'text'=>$label?:$id]; }
                    }
                    $page++;
                    if ($page>10) break; // límite de seguridad
                }
            }
            // 2) Fallback alternativo (solicitado): Preferir IDs numéricos de temporada usando Ranking/…ESP/{Modalidad}/{TemporadaId}
            // Intentamos descubrir temporadas 1..20 (ajustable) por modalidades 1/2. Si hay hits, usamos esos IDs numéricos.
            if (empty($out)) {
                $modalidades = array('1','2');
                $seen = array(); $found = array();
                for ($sid=1; $sid<=20; $sid++) {
                    foreach ($modalidades as $mod){
                        $p = "/Ranking/GetRankingPorModalidadPorTemporadaPorPosicionESP/{$mod}/{$sid}";
                        $j = self::get_json($p, 15);
                        if (is_wp_error($j)) {
                            // 404 esperado cuando no existe; log a debug y continuamos
                            if (function_exists('rf_log')) { rf_log('rankgen seasons rank-fallback numeric', ['path'=>$p, 'err'=>$j->get_error_message()], 'debug'); }
                            continue;
                        }
                        // Validar que hay contenido real (no vacío) en la respuesta (lista de items, filas, etc.)
                        $items = self::unwrap_items($j);
                        $hasContent = false;
                        if (!empty($items)) { $hasContent = true; }
                        elseif (is_array($j)) {
                            // También aceptamos si aparece algún campo reconocible con datos (p.ej. 'posiciones', 'ranking', etc.)
                            foreach (['posiciones','ranking','rows','data','result'] as $k) {
                                if (!empty($j[$k])) { $hasContent = true; break; }
                            }
                        }
                        if ($hasContent && !isset($seen[$sid])) { $seen[$sid]=1; $found[] = array('id'=>(string)$sid, 'text'=>(string)$sid); }
                    }
                }
                if (!empty($found)) {
                    // Ordenar por ID numérico ascendente para consistencia visual
                    usort($found, function($a,$b){ return intval($a['id']) <=> intval($b['id']); });
                    $out = $found;
                }
            }
            // 3) Fallback: años (si la instalación usa años como TemporadaId) mediante Ranking intentando rango de años
            if (empty($out)) {
                $years = array();
                $nowY = intval(date('Y'));
                for ($y=$nowY+1; $y >= $nowY-12; $y--) { $years[] = (string)$y; }
                $modalidades = array('1','2');
                $seenY = array(); $foundY = array();
                foreach ($years as $yy){
                    foreach ($modalidades as $mod){
                        $p = "/Ranking/GetRankingPorModalidadPorTemporadaPorPosicionESP/{$mod}/{$yy}";
                        $j = self::get_json($p, 15);
                        if (is_wp_error($j)) { if (function_exists('rf_log')) { rf_log('rankgen seasons rank-fallback year', ['path'=>$p, 'err'=>$j->get_error_message()], 'debug'); } continue; }
                        if (!isset($seenY[$yy])){ $seenY[$yy]=1; $foundY[] = array('id'=>$yy, 'text'=>$yy); }
                    }
                }
                if (!empty($foundY)) { usort($foundY, function($a,$b){ return strcmp($a['id'],$b['id']); }); $out = $foundY; }
            }
            // 4) Fallback: deducir temporadas únicas de los torneos paginados
            if (empty($out)) {
                $page = 1; $pageSize = 100; $maxPages = 10; $seen = array();
                while ($page <= $maxPages){
                    $json2 = self::get_json("/Torneo/GetTorneosPag?page={$page}&pageSize={$pageSize}");
                    if (is_wp_error($json2)) { if (function_exists('rf_log')) { rf_log('rankgen catalog seasons fallback error', ['page'=>$page, 'err'=>$json2->get_error_message()]); } break; }
                    $items2 = self::unwrap_items($json2);
                    if (empty($items2)) break;
                    foreach ($items2 as $it){
                        $tid = null; $tlabel='';
                        if (is_array($it)) {
                            $tid = $it['temporadaId'] ?? ($it['idTemporada'] ?? null);
                            // objeto anidado
                            if ($tid === null && isset($it['temporada']) && (is_array($it['temporada'])||is_object($it['temporada']))) {
                                $tmp = (array)$it['temporada'];
                                $tid = $tmp['id'] ?? ($tmp['temporadaId'] ?? null);
                                $tlabel = $tmp['nombre'] ?? ($tmp['temporada'] ?? ($tmp['anio'] ?? ''));
                            }
                            if ($tid === null) {
                                $tname = $it['temporada'] ?? ($it['anio'] ?? ($it['anioTemporada'] ?? ''));
                                if (is_string($tname) && $tname !== '') { $tid = $tname; }
                            }
                            if ($tlabel==='') { $tlabel = $it['temporada'] ?? ($it['anio'] ?? ($it['anioTemporada'] ?? '')); }
                            if ($tid === null && ($it['fecha'] ?? $it['fechaInicio'] ?? '')) {
                                $f = (string)($it['fecha'] ?? $it['fechaInicio']); $year = substr($f,0,4); if (ctype_digit($year)) { $tid = $year; $tlabel = $tlabel ?: $year; }
                            }
                        } else {
                            $o = $it; $tid = $o->temporadaId ?? ($o->idTemporada ?? null);
                            if ($tid === null && isset($o->temporada) && (is_array($o->temporada)||is_object($o->temporada))) {
                                $tmp = (array)$o->temporada; $tid = $tmp['id'] ?? ($tmp['temporadaId'] ?? null); $tlabel = $tmp['nombre'] ?? ($tmp['temporada'] ?? ($tmp['anio'] ?? ''));
                            }
                            if ($tid === null) {
                                $tname = $o->temporada ?? ($o->anio ?? ($o->anioTemporada ?? ''));
                                if (is_string($tname) && $tname !== '') { $tid = $tname; }
                            }
                            if ($tlabel==='') { $tlabel = $o->temporada ?? ($o->anio ?? ($o->anioTemporada ?? '')); }
                            if ($tid === null && ($o->fecha ?? $o->fechaInicio ?? '')) {
                                $f = (string)($o->fecha ?? $o->fechaInicio); $year = substr($f,0,4); if (ctype_digit($year)) { $tid = $year; $tlabel = $tlabel ?: $year; }
                            }
                        }
                        if ($tid === null) continue; $id = (string)$tid; if (isset($seen[$id])) continue; $seen[$id]=1;
                        $label = $tlabel ?: $id;
                        $out[] = array('id'=>$id, 'text'=>$label);
                    }
                    $hasNext = false;
                    if (is_array($json2)) {
                        $hasNext = !empty($json2['hasNextPage']) || (!empty($json2['data']['hasNextPage'])) || (!empty($json2['result']['hasNextPage']));
                    }
                    if (!$hasNext) break;
                    $page++;
                }
            }
            // 5) Fallback final: usar mapa de temporadas del Normalizer si existe
            if (empty($out) && class_exists('Futbolin_Normalizer')) {
                try {
                    $map = \Futbolin_Normalizer::temporada_year_map();
                    if (is_array($map) && !empty($map)) {
                        foreach ($map as $id => $year) {
                            $out[] = array('id' => (string)$id, 'text' => (string)$id);
                        }
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
            if (function_exists('rf_log')) { rf_log('rankgen catalog seasons final', ['count'=>count($out)]); }
            wp_send_json_success(array('items'=>$out));
        } elseif ($kind === 'tournaments'){
            $temporadaId = isset($_GET['temporadaId']) ? sanitize_text_field($_GET['temporadaId']) : '';
            $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $pageSize = isset($_GET['pageSize']) ? max(1, intval($_GET['pageSize'])) : 100;
            $apiPath = "/Torneo/GetTorneosPag?page={$page}&pageSize={$pageSize}";
            if ($temporadaId !== '') { $apiPath .= '&temporadaId=' . rawurlencode($temporadaId); }
            if ($q !== '') $apiPath .= '&q='.rawurlencode($q);
            $json = self::get_json($apiPath);
            $out = array(); $hasMore = false;
            if (is_wp_error($json)) { if (function_exists('rf_log')) { rf_log('rankgen catalog tournaments error', ['url'=>$apiPath, 'err'=>$json->get_error_message()]); } }
            else {
                $items = self::unwrap_items($json);
                foreach ($items as $it){
                    if ($temporadaId !== '' && isset($it['temporadaId']) && strval($it['temporadaId']) !== $temporadaId) continue;
                    $tid = isset($it['torneoId']) ? intval($it['torneoId']) : (isset($it['id']) ? intval($it['id']) : 0);
                    if (!$tid) continue;
                    $name = isset($it['nombreTorneo']) ? $it['nombreTorneo'] : ('Torneo '.$tid);
                    $fecha = isset($it['fecha']) ? substr($it['fecha'],0,10) : '';
                    if ($q !== ''){
                        $needle = mb_strtolower($q);
                        $hay = mb_strtolower($name.' '.$fecha);
                        if (strpos($hay, $needle) === false) continue;
                    }
                    $out[] = array('id'=>$tid, 'text'=>trim($fecha.' '.$name) );
                }
                if (is_array($json)) {
                    $hasMore = !empty($json['hasNextPage']) || (!empty($json['data']['hasNextPage'])) || (!empty($json['result']['hasNextPage']));
                }
            }
            // Fallback no paginado si no hay resultados
            if (empty($out)) {
                $altPath = '/Torneo/GetTorneos';
                if ($temporadaId !== '') { $altPath .= '?temporadaId=' . rawurlencode($temporadaId); }
                $jsonAlt = self::get_json($altPath);
                if (!is_wp_error($jsonAlt)) {
                    $items2 = self::unwrap_items($jsonAlt); if (empty($items2) && is_array($jsonAlt)) { $items2 = $jsonAlt; }
                    foreach ($items2 as $it){
                        $tid = isset($it['torneoId']) ? intval($it['torneoId']) : (isset($it['id']) ? intval($it['id']) : 0);
                        if (!$tid) continue;
                        if ($temporadaId !== '' && isset($it['temporadaId']) && strval($it['temporadaId']) !== $temporadaId) continue;
                        $name = isset($it['nombreTorneo']) ? $it['nombreTorneo'] : ('Torneo '.$tid);
                        $fecha = isset($it['fecha']) ? substr($it['fecha'],0,10) : '';
                        if ($q !== ''){
                            $needle = mb_strtolower($q);
                            $hay = mb_strtolower($name.' '.$fecha);
                            if (strpos($hay, $needle) === false) continue;
                        }
                        $out[] = array('id'=>$tid, 'text'=>trim($fecha.' '.$name) );
                    }
                    $hasMore = false;
                } else { if (function_exists('rf_log')) { rf_log('rankgen catalog tournaments alt error', ['url'=>$altPath, 'err'=>$jsonAlt->get_error_message()]); } }
            }
            wp_send_json_success(array('items'=>$out, 'hasMore'=>$hasMore));
        } elseif ($kind === 'competitions'){
            $torneoIds = isset($_GET['torneoIds']) ? (array) $_GET['torneoIds'] : array();
            $torneoIds = array_filter(array_map('intval', $torneoIds));
            $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $pageSize = isset($_GET['pageSize']) ? max(1, intval($_GET['pageSize'])) : 100;
            $temporadaId = isset($_GET['temporadaId']) ? sanitize_text_field($_GET['temporadaId']) : '';
            $tiposRaw = isset($_GET['tipos']) ? (array) $_GET['tipos'] : array();
            $tiposNeedles = array_map('mb_strtolower', array_filter(array_map('sanitize_text_field', $tiposRaw)));
            $seen = array(); $all = array();
            foreach ($torneoIds as $tid){
                $json = self::get_json("/Torneo/GetTorneoConPosiciones/{$tid}");
                if (is_wp_error($json) || empty($json)) {
                    if (function_exists('rf_log')) { rf_log('rankgen catalog competitions error', ['tid'=>$tid, 'err'=> is_wp_error($json)?$json->get_error_message():'empty']); }
                } else {
                    // Intentar varias rutas de extracción
                    $variants = array();
                    if (isset($json['competiciones']) && is_array($json['competiciones'])) { $variants[] = $json['competiciones']; }
                    if (isset($json['competencias']) && is_array($json['competencias'])) { $variants[] = $json['competencias']; }
                    if (isset($json['data']['competiciones']) && is_array($json['data']['competiciones'])) { $variants[] = $json['data']['competiciones']; }
                    if (empty($variants) && is_array($json)) { $variants[] = $json; }
                    foreach ($variants as $arr){
                        foreach ((array)$arr as $c){
                            $cid = 0; $name='';
                            if (is_array($c)) { $cid = isset($c['competicionId']) ? intval($c['competicionId']) : (isset($c['id']) ? intval($c['id']) : 0); $name = isset($c['nombreCompeticion']) ? $c['nombreCompeticion'] : (isset($c['nombre']) ? $c['nombre'] : ''); }
                            elseif (is_object($c)) { $cid = isset($c->competicionId) ? intval($c->competicionId) : (isset($c->id) ? intval($c->id) : 0); $name = isset($c->nombreCompeticion) ? $c->nombreCompeticion : (isset($c->nombre) ? $c->nombre : ''); }
                            if (!$cid) continue; if (isset($seen[$cid])) continue; $seen[$cid]=1;
                            if ($q !== '' && $name !== ''){ $needle = mb_strtolower($q); if (strpos(mb_strtolower($name), $needle) === false) continue; }
                            // Filtros por temporada y tipos
                            if ($temporadaId !== '') {
                                $tId = '';
                                if (is_array($c)) { $tId = isset($c['temporadaId']) ? (string)$c['temporadaId'] : (isset($c['temporada']) ? (string)$c['temporada'] : ''); }
                                else { $tId = isset($c->temporadaId) ? (string)$c->temporadaId : (isset($c->temporada) ? (string)$c->temporada : ''); }
                                if ($tId !== '' && (string)$tId !== (string)$temporadaId) { continue; }
                            }
                            if (!empty($tiposNeedles)) {
                                $nraw = is_array($c) ? ((isset($c['nombreCompeticion']) ? (string)$c['nombreCompeticion'] : (isset($c['nombre'])?(string)$c['nombre']:''))) : ((isset($c->nombreCompeticion) ? (string)$c->nombreCompeticion : (isset($c->nombre)?(string)$c->nombre:'')));
                                $nn = mb_strtolower(str_ireplace(['Amater','misto'], ['Amateur','mixto'], $nraw));
                                $ok=false; foreach ($tiposNeedles as $needle){ if ($needle!=='' && strpos($nn,$needle)!==false){ $ok=true; break; } }
                                if(!$ok) continue;
                            }
                            $all[] = array('id'=>$cid, 'text'=>$name ?: ('Competición '.$cid));
                        }
                    }
                }
                // Fallback: endpoint directo de competiciones por torneo (varias variantes conocidas)
                if (empty($all)) {
                    $paths = array(
                        "/Competicion/GetCompeticionesPorTorneo/{$tid}",
                        "/Competicion/GetCompeticionesPorTorneo?torneoId={$tid}"
                    );
                    foreach ($paths as $p) {
                        $j2 = self::get_json($p);
                        if (is_wp_error($j2) || empty($j2)) { continue; }
                        $arr = self::unwrap_items($j2); if (empty($arr) && is_array($j2)) { $arr = $j2; }
                        foreach ($arr as $c){
                            $cid = 0; $name='';
                            if (is_array($c)) { $cid = isset($c['competicionId']) ? intval($c['competicionId']) : (isset($c['id']) ? intval($c['id']) : 0); $name = isset($c['nombreCompeticion']) ? $c['nombreCompeticion'] : (isset($c['nombre']) ? $c['nombre'] : ''); }
                            elseif (is_object($c)) { $cid = isset($c->competicionId) ? intval($c->competicionId) : (isset($c->id) ? intval($c->id) : 0); $name = isset($c->nombreCompeticion) ? $c->nombreCompeticion : (isset($c->nombre) ? $c->nombre : ''); }
                            if (!$cid) continue; if (isset($seen[$cid])) continue; $seen[$cid]=1;
                            if ($q !== '' && $name !== ''){ $needle = mb_strtolower($q); if (strpos(mb_strtolower($name), $needle) === false) continue; }
                            if ($temporadaId !== '') {
                                $tId = is_array($c) ? (isset($c['temporadaId']) ? (string)$c['temporadaId'] : (isset($c['temporada']) ? (string)$c['temporada'] : '')) : (isset($c->temporadaId) ? (string)$c->temporadaId : (isset($c->temporada) ? (string)$c->temporada : ''));
                                if ($tId !== '' && (string)$tId !== (string)$temporadaId) { continue; }
                            }
                            if (!empty($tiposNeedles)) {
                                $nraw = is_array($c) ? ((isset($c['nombreCompeticion']) ? (string)$c['nombreCompeticion'] : (isset($c['nombre'])?(string)$c['nombre']:''))) : ((isset($c->nombreCompeticion) ? (string)$c->nombreCompeticion : (isset($c->nombre)?(string)$c->nombre:'')));
                                $nn = mb_strtolower(str_ireplace(['Amater','misto'], ['Amateur','mixto'], $nraw));
                                $ok=false; foreach ($tiposNeedles as $needle){ if ($needle!=='' && strpos($nn,$needle)!==false){ $ok=true; break; } }
                                if(!$ok) continue;
                            }
                            $all[] = array('id'=>$cid, 'text'=>$name ?: ('Competición '.$cid));
                        }
                        if (!empty($all)) break;
                    }
                }
            }
            $total = count($all);
            $offset = ($page-1)*$pageSize; if ($offset<0) $offset=0;
            $slice = array_slice($all, $offset, $pageSize);
            $hasMore = ($offset + count($slice)) < $total;
            wp_send_json_success(array('items'=>$slice, 'hasMore'=>$hasMore, 'total'=>$total));
        } elseif ($kind === 'types'){
            // Catálogo de "tipos" por nombre textual (needles) para set[tipos_comp]
            $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $fromTids = isset($_GET['torneoIds']) ? array_filter(array_map('intval', (array)$_GET['torneoIds'])) : array();
            $derived = array();
            // Si hay torneos, derivar tipos desde nombres de competiciones reales
            if (!empty($fromTids)) {
                $seenNames = array();
                foreach ($fromTids as $tid) {
                    $j = self::get_json("/Torneo/GetTorneoConPosiciones/{$tid}");
                    if (is_wp_error($j) || empty($j)) continue;
                    $arrs = array();
                    if (isset($j['competiciones']) && is_array($j['competiciones'])) $arrs[] = $j['competiciones'];
                    if (isset($j['competencias']) && is_array($j['competencias'])) $arrs[] = $j['competencias'];
                    foreach ($arrs as $a){
                        foreach ($a as $c){
                            $name = '';
                            if (is_array($c)) { $name = $c['nombreCompeticion'] ?? ($c['nombre'] ?? ''); }
                            elseif (is_object($c)) { $name = $c->nombreCompeticion ?? ($c->nombre ?? ''); }
                            if (!$name) continue;
                            $norm = mb_strtolower(str_ireplace('Amater','Amateur', $name));
                            if (!isset($seenNames[$norm])) { $seenNames[$norm]=1; $derived[$norm] = trim($name); }
                        }
                    }
                }
            }
            $all = array(
                'open dobles' => 'Open Dobles',
                'open individual' => 'Open Individual',
                'espana dobles' => 'España Dobles',
                'espana individual' => 'España Individual',
                'amateur dobles' => 'Amateur Dobles',
                'amateur individual' => 'Amateur Individual',
                'rookie dobles' => 'Rookie Dobles',
                'rookie individual' => 'Rookie Individual',
                'mixto' => 'Mixto',
                'mujeres dobles' => 'Mujeres Dobles',
                'mujeres individual' => 'Mujeres Individual',
                'senior dobles' => 'Senior Dobles',
                'senior individual' => 'Senior Individual',
                'junior dobles' => 'Junior Dobles',
                'junior individual' => 'Junior Individual',
                'proam dobles' => 'Pro-Am Dobles',
                'proam individual' => 'Pro-Am Individual',
                'master dobles' => 'Master Dobles',
                'master individual' => 'Master Individual',
            );
            // Si existe BUENO_master.json con enums.tipoCompeticion, incluir nombres crudos como sugerencias simples
            try {
                $plugin_dir = dirname(dirname(dirname(__FILE__))); // plugin root
                $master_path = $plugin_dir . '/BUENO_master.json';
                if (file_exists($master_path)) {
                    $mj = json_decode(file_get_contents($master_path), true);
                    if (is_array($mj) && isset($mj['enums']['tipoCompeticion']) && is_array($mj['enums']['tipoCompeticion'])) {
                        foreach ($mj['enums']['tipoCompeticion'] as $tc) {
                            $name = is_array($tc) ? ($tc['name'] ?? '') : (is_object($tc) ? ($tc->name ?? '') : '');
                            if (!is_string($name) || $name==='') continue;
                            $norm = mb_strtolower(str_ireplace('Amater', 'Amateur', $name));
                            if (!isset($all[$norm])) { $all[$norm] = trim($name); }
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
            // Fusionar sugerencias derivadas primero (datos reales), luego fallback estático
            $items = array();
            $catalog = !empty($derived) ? $derived + $all : $all;
            foreach ($catalog as $id => $label) {
                if ($q !== '') {
                    $needle = mb_strtolower($q);
                    if (strpos(mb_strtolower($label), $needle) === false && strpos($id, $needle) === false) { continue; }
                }
                $items[] = array('id' => $id, 'text' => $label);
            }
            // Orden alfabético legible
            usort($items, function($a,$b){ return strcasecmp($a['text'],$b['text']); });
            wp_send_json_success(array('items'=>$items));
        } else {
            wp_send_json_error('kind inválido',400);
        }
    }

    public static function cache_info(){
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden',403);
        check_ajax_referer('futb_rankgen_nonce','nonce');
        $slug = isset($_GET['slug']) ? sanitize_title(wp_unslash($_GET['slug'])) : '';
        if ($slug === '') wp_send_json_error('slug requerido',400);
        $cache = get_option('futb_rankgen_cache', array());
        $entry = isset($cache[$slug]) ? $cache[$slug] : null;
        $exists = is_array($entry);
        $rows = $exists && isset($entry['rows']) && is_array($entry['rows']) ? count($entry['rows']) : 0;
        $cols = $exists && isset($entry['columns']) && is_array($entry['columns']) ? count($entry['columns']) : 0;
        $ts = get_option('futb_rankgen_cache_ts_'.$slug, '');
        // Claves de almacenamiento en la BBDD
        $storage = array(
            'option_cache' => 'futb_rankgen_cache',
            'option_cache_ts' => 'futb_rankgen_cache_ts_'.$slug,
        );
        // URL pública sugerida
        $public = add_query_arg(array('view'=>'rankgen','slug'=>$slug), home_url('/futbolin-ranking/'));
        wp_send_json_success(array(
            'exists'=>$exists,
            'rows'=>$rows,
            'columns'=>$cols,
            'timestamp'=>$ts,
            'storage'=>$storage,
            'publicUrl'=>$public,
        ));
    }
}
Futbolin_Rankgen_Ajax::init();
