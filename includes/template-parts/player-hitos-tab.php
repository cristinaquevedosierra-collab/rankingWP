<?php
if (!defined('ABSPATH')) exit;
if (!defined('FUTBOLIN_API_PATH')) { define('FUTBOLIN_API_PATH', dirname(__DIR__, 2) . '/'); }

include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Polyfills mínimos para análisis/CLI (WordPress los define en runtime)
if (!function_exists('esc_html')) {
  function esc_html($text){ return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
  function esc_attr($text){ return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('_n')) {
  function _n($single, $plural, $number, $domain = null) { return ($number == 1) ? $single : $plural; }
}
if (!function_exists('plugins_url')) {
  function plugins_url($path = '', $plugin = '') { return ''; }
}
if (!function_exists('wp_dequeue_style')) { function wp_dequeue_style($h) {} }
if (!function_exists('wp_deregister_style')) { function wp_deregister_style($h) {} }
// Polyfill de helper de depuración local
if (!function_exists('rf_hitos_is_debug')) {
  function rf_hitos_is_debug(){ return (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1'); }
}
// Flag de debug seguro por defecto (evita warnings si se usa antes de inicializar más abajo)
$rf_debug_on = isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1';

// Loader CSS: externo con fallback inline 1:1 y flag RF_FORCE_INLINE_HITOS_CSS
// Evitar colisiones con estilos encolados del plugin (legacy/v2)
@wp_dequeue_style('futbolin-hitos-cards');
@wp_deregister_style('futbolin-hitos-cards');

$__rf_hitos_css_file = FUTBOLIN_API_PATH . 'assets/css/rf-hitos-inline.css';
$__rf_hitos_css_url  = function_exists('plugins_url') ? plugins_url('assets/css/rf-hitos-inline.css', FUTBOLIN_API_PATH . 'ranking-futbolin.php') : '';
// Modo por defecto: inline (idéntico al original). Para usar <link>, define RF_HITOS_CSS_MODE='link'.
$__rf_css_mode       = defined('RF_HITOS_CSS_MODE') ? (string)constant('RF_HITOS_CSS_MODE') : 'inline';
$__rf_force_inline   = defined('RF_FORCE_INLINE_HITOS_CSS') ? (bool)constant('RF_FORCE_INLINE_HITOS_CSS') : false;
if ($__rf_force_inline || $__rf_css_mode !== 'link' || empty($__rf_hitos_css_url)) {
  echo '<style>'.(@file_get_contents($__rf_hitos_css_file) ?: '').'</style>';
} else {
  $href = esc_attr($__rf_hitos_css_url);
  echo '<link rel="stylesheet" href="'.$href.'" />';
}
?>
<?php
// Extrae, con tolerancia, la lista de items/ranking desde diversas estructuras JSON comunes
if (!function_exists('rf_hitos_unwrap_items')) {
function rf_hitos_unwrap_items($x){
  if (empty($x)) return [];
  if (is_object($x)) $x = (array)$x;
  if (is_array($x)) {
    // Si ya es lista indexada
    if (array_keys($x) === range(0, count($x)-1)) return $x;
    // Normaliza claves de primer nivel a minúsculas
    $lower = [];
    foreach ($x as $k => $v) { $lower[strtolower((string)$k)] = is_object($v) ? (array)$v : $v; }
    // Casos comunes
    if (isset($lower['items']) && is_array($lower['items'])) return $lower['items'];
    $data = isset($lower['data']) ? $lower['data'] : null; if (is_object($data)) $data = (array)$data;
    $result = isset($lower['result']) ? $lower['result'] : null; if (is_object($result)) $result = (array)$result;
    if (is_array($data)) {
      if (isset($data['items']) && is_array($data['items'])) return $data['items'];
      if (isset($data['ranking']) && is_array($data['ranking'])) return $data['ranking'];
    }
    if (is_array($result)) {
      if (isset($result['items']) && is_array($result['items'])) return $result['items'];
      if (isset($result['ranking']) && is_array($result['ranking'])) return $result['ranking'];
    }
    if (isset($lower['ranking'])) {
      $rk = $lower['ranking']; if (is_object($rk)) $rk = (array)$rk;
      if (is_array($rk)) {
        if (isset($rk['items']) && is_array($rk['items'])) return $rk['items'];
        // A veces 'ranking' puede ser ya una lista
        if (array_keys($rk) === range(0, count($rk)-1)) return $rk;
      }
    }
    if (isset($lower['rows']) && is_array($lower['rows'])) return $lower['rows'];
    // Otros alias posibles
    $candidates = ['list','lista','datos','data','result'];
    foreach ($candidates as $ck){ if (isset($lower[$ck]) && is_array($lower[$ck]) && array_keys($lower[$ck])===range(0,count($lower[$ck])-1)) return $lower[$ck]; }
    // Nested genérico: si algún valor es lista indexada, úsalo
    foreach($x as $v){ if (is_object($v)) $v=(array)$v; if (is_array($v)){ $is_assoc2 = array_keys($v)!==range(0,count($v)-1); if(!$is_assoc2) return $v; } }
  }
  return [];
}
}
if (!function_exists('rf_hitos_norm_temp_label')) {
function rf_hitos_norm_temp_label($v){
  $map = $GLOBALS['rf_hitos_temporada_map'] ?? [];
  $id_map = $map['id_to_ord'] ?? [];
  $year_map = $map['year_to_ord'] ?? [];
  if (is_numeric($v)){
    $n = intval($v);
    if (isset($id_map[$n])) return $id_map[$n];
    if (isset($year_map[$n])) return $year_map[$n];
    if ($n>=1 && $n<=200) return $n;
    if ($n>=1900){
      $BASE = defined('FUTBOLIN_SEASON_YEAR_BASE') ? (int)constant('FUTBOLIN_SEASON_YEAR_BASE') : 2005;
      if ($n >= ($BASE + 1)) return $n - $BASE;
    }
  }
  if (is_string($v)){
    $t = trim($v);
    if (preg_match('/temporada\s+(\d{1,3})/i', $t, $m)) return intval($m[1]);
    if (preg_match('/(19|20)\d{2}/', $t, $m)){
      $y = intval($m[0]);
      if (isset($year_map[$y])) return $year_map[$y];
      $BASE = defined('FUTBOLIN_SEASON_YEAR_BASE') ? (int)constant('FUTBOLIN_SEASON_YEAR_BASE') : 2005;
      if ($y >= ($BASE + 1)) return $y - $BASE;
    }
  }
  return null;
}
}
if (!function_exists('rf_call_api')) {
function rf_call_api($api,$path){
  if(!$api||!$path) return [];
  $url = $path;
  if (is_string($path) && strpos($path, 'http') !== 0) {
    if (method_exists($api, 'get_base_api_url')) {
      $base = rtrim($api->get_base_api_url(), '/');
      $cleanPath = $path;
      if (strpos($cleanPath, '/api/') === 0 && substr($base, -4) === '/api') {
        $cleanPath = substr($cleanPath, 4);
        if ($cleanPath === '') { $cleanPath = '/'; }
      }
      if ($cleanPath && $cleanPath[0] !== '/') { $cleanPath = '/' . $cleanPath; }
      $url = $base . $cleanPath;
    }
  }
  // Detecta si debemos emular estrictamente Swagger para un host/endpoint específico (sin QS extras)
  $strict_swagger_auto = false;
  try {
    $pu = parse_url($url);
    $host_l = is_array($pu) && !empty($pu['host']) ? strtolower($pu['host']) : '';
    $path_l = is_array($pu) && !empty($pu['path']) ? strtolower($pu['path']) : '';
  if ($host_l === 'ranking.fefm.net' && strpos($path_l, '/api/ranking/getrankingpormodalidadportemporadaespglicko2') !== false) {
      $strict_swagger_auto = true; // Evitar _rf_bust/_rf_auth y replicar condiciones exactas
    }
  } catch (\Throwable $e) { /* ignore */ }
  // Si se activa bypass de cache por debug, añade parámetro de bust a la URL para evitar caches por URL en el cliente/API
  // EXCEPTO cuando está activo el modo estricto de Swagger, para replicar condiciones exactas de esa herramienta
  $strict_swagger = ((isset($_GET['rf_debug_hitos_strict_swagger']) && $_GET['rf_debug_hitos_strict_swagger'] == '1') || $strict_swagger_auto);
  $bypass = (isset($_GET['rf_debug_hitos_bypass_cache']) && $_GET['rf_debug_hitos_bypass_cache'] == '1');
  if ($bypass && !$strict_swagger) {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    $url .= $sep . '_rf_bust=' . rawurlencode((string)time());
  }
  // Helpers internos para preferencia de auth por host para endpoints de Ranking
  $get_host = function($apiInst){
    $host = 'api';
    if ($apiInst && method_exists($apiInst, 'get_base_api_url')) {
      $base = $apiInst->get_base_api_url();
      if (is_string($base)) { $p = parse_url($base); if (is_array($p) && !empty($p['host'])) { $host = strtolower($p['host']); } }
    }
    return $host;
  };
  // Memoria compartida en-request para la preferencia de auth por host
  static $auth_pref_mem = [];
  $auth_pref_key = function($apiInst) use ($get_host){ return 'rf:rank:per-host:auth-required:' . $get_host($apiInst); };
  $auth_pref_get = function($apiInst) use ($auth_pref_key, &$auth_pref_mem){
    $k = $auth_pref_key($apiInst);
    if (function_exists('get_transient')) { $v = call_user_func('get_transient', $k); if ($v === '1' || $v === '0') { $auth_pref_mem[$k] = $v; return $v; } }
    return isset($auth_pref_mem[$k]) ? $auth_pref_mem[$k] : '';
  };
  $auth_pref_set = function($apiInst,$val) use ($auth_pref_key, &$auth_pref_mem){
    $k = $auth_pref_key($apiInst); $vv = ($val ? '1' : '0'); $ttl = (defined('DAY_IN_SECONDS') ? (3 * constant('DAY_IN_SECONDS')) : 259200);
    if (function_exists('set_transient')) { call_user_func('set_transient', $k, $vv, $ttl); }
    $auth_pref_mem[$k] = $vv;
  };
  // Permite resetear la preferencia de auth por host vía debug
  $auth_pref_del = function($apiInst) use ($auth_pref_key, &$auth_pref_mem){
    $k = $auth_pref_key($apiInst);
    if (function_exists('delete_transient')) { call_user_func('delete_transient', $k); }
    if (isset($auth_pref_mem[$k])) { unset($auth_pref_mem[$k]); }
  };
  if (method_exists($api,'do_request')) {
    // Algunos hosts limitan datos cuando la petición va autenticada (scoping por cuenta). Para endpoints públicos forzamos sin auth.
    $auth_required = true;
    $lower_url = strtolower($url);
  // Detecta ranking de forma tolerante (con o sin prefijo '/api')
  $is_ranking = (strpos($lower_url, '/ranking/') !== false);
    // Detecta específicamente el endpoint oficial por temporada (ESP Glicko2)
    $is_rank_per_season_espg2 = (strpos($lower_url, '/api/ranking/getrankingpormodalidadportemporadaespglicko2') !== false);
    $is_temporada = (strpos($lower_url, '/api/temporada/') !== false);
    $is_modalidad = (strpos($lower_url, '/api/modalidad/') !== false);
    $is_campeones = (strpos($lower_url, '/api/jugador/getcampeonesespania') !== false);
  $debug_on = (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1');
  if ($debug_on && $is_ranking) { error_log('RFHITOS rank-detect host=' . $get_host($api) . ' is_ranking=1 url=' . $url); if (function_exists('rf_log')) { rf_log('RFHITOS rank-detect', ['host'=>$get_host($api),'url'=>$url], 'debug'); } }
    // Reset manual de preferencia de auth por host
    if ($is_ranking && isset($_GET['rf_debug_hitos_authpref_reset']) && $_GET['rf_debug_hitos_authpref_reset'] == '1') {
      $auth_pref_del($api);
  if ($debug_on) { error_log('RFHITOS ranking auth pref reset for host=' . $get_host($api)); if (function_exists('rf_log')) { rf_log('RFHITOS auth pref reset', ['host'=>$get_host($api)], 'debug'); } }
    }
    $force_auth = ($is_ranking && isset($_GET['rf_debug_hitos_force_auth']) && $_GET['rf_debug_hitos_force_auth'] == '1');
    if ($is_temporada || $is_modalidad || $is_campeones) { $auth_required = false; }
    if ($is_ranking) {
      $pref = $auth_pref_get($api);
  if ($debug_on) { error_log('RFHITOS ranking auth pref read host=' . $get_host($api) . ' pref=' . ($pref === '' ? '(none)' : $pref)); if (function_exists('rf_log')) { rf_log('RFHITOS auth pref read', ['host'=>$get_host($api),'pref'=>$pref===''?'(none)':$pref], 'debug'); } }
      if ($is_rank_per_season_espg2) {
        // Para el endpoint oficial por temporada: ignora la preferencia global y prueba SIEMPRE sin auth primero.
        $auth_required = false;
      } else {
        if ($pref === '1') { $auth_required = true; }
        elseif ($pref === '0') { $auth_required = false; }
        else { $auth_required = false; } // probar sin auth primero si no hay preferencia
      }
  if ($force_auth) { $auth_required = true; if ($debug_on) { error_log('RFHITOS ranking auth forced by rf_debug_hitos_force_auth=1'); if (function_exists('rf_log')) { rf_log('RFHITOS auth forced by flag', [], 'debug'); } } }
      // Regla específica por host (anulada): no forzar auth para illozapatillo; algunos endpoints devuelven vacío si va autenticado.
      $host_now = $get_host($api);
  if ($host_now === 'ranking.fefm.net') {
        // Mantener el flujo normal: probar SIN auth primero y, si viene vacío, el código ya reintenta con auth.
  if ($debug_on) { error_log('RFHITOS ranking auth NOT forced for host ' . $host_now . ' (try no-auth first)'); if (function_exists('rf_log')) { rf_log('RFHITOS auth not forced', ['host'=>$host_now], 'debug'); } }
      }
      // Si se va a usar auth desde el inicio para Ranking, añade flag para evitar colisiones con caches previas sin auth
      // EXCEPTO en modo estricto Swagger: no alteramos la URL para replicar Swagger (la cabecera Authorization se seguirá enviando)
      if ($auth_required && !$strict_swagger) {
        if (strpos($url, '_rf_auth=1') === false) {
          $sepA = (strpos($url, '?') === false) ? '?' : '&';
          $url .= $sepA . '_rf_auth=1';
        }
      }
    } else {
      // Aplicar la misma regla de host también a Temporada/Modalidad si es necesario (algunos hosts exigen auth para listar estos catálogos)
      $host_now = $get_host($api);
  if ($host_now === 'ranking.fefm.net' && ($is_temporada || $is_modalidad)) {
        $auth_required = true;
  if ($debug_on) { error_log('RFHITOS auth forced by host rule for ' . $host_now . ' type=' . ($is_temporada ? 'temporada' : 'modalidad')); if (function_exists('rf_log')) { rf_log('RFHITOS auth forced by host', ['host'=>$host_now,'type'=>$is_temporada?'temporada':'modalidad'], 'debug'); } }
        if (strpos($url, '_rf_auth=1') === false && !$strict_swagger) {
          $sepA = (strpos($url, '?') === false) ? '?' : '&';
          $url .= $sepA . '_rf_auth=1';
        }
      }
    }
  if ($debug_on) { error_log('RFHITOS url=' . $url . ' auth=' . ($auth_required ? '1' : '0')); if (function_exists('rf_log')) { rf_log('RFHITOS url', ['url'=>$url,'auth'=>$auth_required?'1':'0'], 'debug'); } }
    try {
      $res = $api->do_request($url, $auth_required);
    if ($is_ranking) {
        $items = rf_hitos_unwrap_items($res);
        $count = is_array($items) ? count($items) : 0;
  if ($debug_on) { error_log('RFHITOS rank initial count=' . $count . ' auth=' . ($auth_required ? '1' : '0')); if (function_exists('rf_log')) { rf_log('RFHITOS initial count', ['count'=>$count,'auth'=>$auth_required?'1':'0'], 'debug'); } }
        if ($count === 0) {
          // Si Ranking viene vacío y hemos probado sin auth (o sin preferencia), reintentar con auth
          if (!$auth_required) {
            if ($debug_on) { error_log('RFHITOS retry ranking with auth due to empty items'); if (function_exists('rf_log')) { rf_log('RFHITOS retry with auth (empty items)', [], 'info'); } }
            try {
              // Evita colisión con caché negativa por URL del primer intento
              // Si estamos en modo Swagger estricto, no alteramos la URL (replicar Swagger exactamente)
              $url2 = $url;
              if (!$strict_swagger) {
                $sep2 = (strpos($url2, '?') === false) ? '?' : '&';
                $url2 .= $sep2 . '_rf_auth=1';
              }
              if ($debug_on) { error_log('RFHITOS retry url=' . $url2 . ' auth=1'); if (function_exists('rf_log')) { rf_log('RFHITOS retry', ['url'=>$url2,'auth'=>'1'], 'info'); } }
              $res2 = $api->do_request($url2, true);
              $items2 = rf_hitos_unwrap_items($res2);
              $count2 = is_array($items2) ? count($items2) : 0;
              if ($debug_on) { error_log('RFHITOS retry result count=' . $count2); if (function_exists('rf_log')) { rf_log('RFHITOS retry result', ['count'=>$count2], 'info'); } }
              if ($count2 > 0) {
                // No actualizar la preferencia global cuando es el endpoint por temporada (evita sesgos para otros endpoints)
                if (!$is_rank_per_season_espg2) { $auth_pref_set($api, true); if ($debug_on) { error_log('RFHITOS ranking auth pref updated host=' . $get_host($api) . ' auth=1'); if (function_exists('rf_log')) { rf_log('RFHITOS auth pref updated', ['host'=>$get_host($api),'auth'=>'1'], 'info'); } } }
                return $res2;
              }
            } catch (\Throwable $ee) { /* ignore */ }
          } else {
            if ($debug_on) { error_log('RFHITOS no retry: empty but already using auth=1'); if (function_exists('rf_log')) { rf_log('RFHITOS no retry (already auth=1)', [], 'info'); } }
          }
        } else {
          // Si con el modo actual hay datos y preferencia no está fijada, memorizamos el modo usado
          $pref = $auth_pref_get($api);
          if (!$is_rank_per_season_espg2 && $pref === '') { $auth_pref_set($api, $auth_required); }
        }
      }
      return $res;
  } catch (\Throwable $e) {}
  }
  foreach(['get','request','fetch','http_get'] as $m){
  if (method_exists($api,$m)){ try{ return $api->{$m}($url);}catch(\Throwable $e){} }
  }
  return [];
}
}
// Helper de lectura cacheada para rankings por modalidad+temporada
if (!function_exists('rf_hitos_cached_ranking_fetch')) {
function rf_hitos_cached_ranking_fetch($api, $path) {
  $try_cache = class_exists('RF_Hitos_Cache_Manager') && RF_Hitos_Cache_Manager::is_cache_ready();
  $norm = strtolower($path);
  // Extraer modalidad y temporada si el path es del tipo .../Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{mod}/{temp}
  $mod = null; $temp = null;
  if (preg_match('#/ranking/getrankingpormodalidadportemporadaespglicko2/(\d+)/(\d+)#i', $norm, $m)) {
    $mod = intval($m[1]);
    $temp = $m[2];
  }
  if ($try_cache && $mod !== null && $temp !== null) {
    $key = 'ranking_' . $mod . '_' . $temp;
    $cached = RF_Hitos_Cache_Manager::cache_read($key);
    if ($cached !== null) { return $cached; }
  }
  $data = rf_call_api($api, $path);
  if ($try_cache && $mod !== null && $temp !== null && !is_wp_error($data) && $data !== null) {
    RF_Hitos_Cache_Manager::cache_write('ranking_' . $mod . '_' . $temp, $data);
  }
  return $data;
}
}
// ---- Descubrimiento dinámico del endpoint de ranking por temporada (algunos hosts exponen variantes) ----
function rf_hitos_rank_endpoint_store_key($api){
  $host = 'api';
  if ($api && method_exists($api, 'get_base_api_url')) {
    $base = $api->get_base_api_url();
    if (is_string($base)) { $p = parse_url($base); if (is_array($p) && !empty($p['host'])) { $host = strtolower($p['host']); } }
  }
  return 'rf:rank:per-season:endpoint:' . $host;
}
function rf_hitos_rank_endpoint_get($api){
  $k = rf_hitos_rank_endpoint_store_key($api);
  if (function_exists('get_transient')) { $v = call_user_func('get_transient', $k); if (is_string($v) && $v !== '') { return $v; } }
  static $mem = [];
  return isset($mem[$k]) ? $mem[$k] : '';
}
function rf_hitos_rank_endpoint_set($api, $pattern){
  $k = rf_hitos_rank_endpoint_store_key($api);
  $ttl = (defined('HOUR_IN_SECONDS') ? (6 * constant('HOUR_IN_SECONDS')) : 21600);
  if (function_exists('set_transient')) { call_user_func('set_transient', $k, (string)$pattern, $ttl); }
  static $mem = []; $mem[$k] = (string)$pattern;
}
function rf_hitos_rank_endpoint_reset($api){
  $k = rf_hitos_rank_endpoint_store_key($api);
  if (function_exists('delete_transient')) { call_user_func('delete_transient', $k); }
  static $mem = []; unset($mem[$k]);
}
// Preferencia de autenticación para endpoints de Ranking (por host)
function rf_hitos_rank_auth_pref_key($api){
  $host = 'api';
  if ($api && method_exists($api, 'get_base_api_url')) {
    $base = $api->get_base_api_url();
    if (is_string($base)) { $p = parse_url($base); if (is_array($p) && !empty($p['host'])) { $host = strtolower($p['host']); } }
  }
  return 'rf:rank:per-host:auth-required:' . $host;
}
function rf_hitos_rank_auth_pref_get($api){
  $k = rf_hitos_rank_auth_pref_key($api);
  if (function_exists('get_transient')) { $v = call_user_func('get_transient', $k); if ($v === '1' || $v === '0') { return $v; } }
  return '';
}
function rf_hitos_rank_auth_pref_set($api, $require_auth){
  $k = rf_hitos_rank_auth_pref_key($api); $vv = ($require_auth ? '1' : '0');
  $ttl = (defined('DAY_IN_SECONDS') ? (3 * constant('DAY_IN_SECONDS')) : 259200);
  if (function_exists('set_transient')) { call_user_func('set_transient', $k, $vv, $ttl); }
}
function rf_hitos_rank_auth_pref_reset($api){
  $k = rf_hitos_rank_auth_pref_key($api);
  if (function_exists('delete_transient')) { call_user_func('delete_transient', $k); }
}
// Construye la URL/path según patrón detectado
function rf_hitos_rank_build_path($pattern, $modId, $temp, $with_qs_bypass = false){
  $modId = intval($modId); $temp = strval($temp);
  switch ($pattern) {
    case 'esg_m_t':
    default:
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/' . $modId . '/' . rawurlencode($temp);
    case 'esg_t_m':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadESPGlicko2/' . rawurlencode($temp) . '/' . $modId;
    case 'esg_t_qs':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadESPGlicko2?temporadaId=' . rawurlencode($temp) . '&modalidadId=' . $modId;
    case 'esp_m_t':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaESP/' . $modId . '/' . rawurlencode($temp);
    case 'esp_t_m':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadESP/' . rawurlencode($temp) . '/' . $modId;
    case 'esp_t_qs':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadESP?temporadaId=' . rawurlencode($temp) . '&modalidadId=' . $modId;
    // ESP por posición (nuevo endpoint aportado por el host)
    case 'esp_pos_m_t':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaPorPosicionESP/' . $modId . '/' . rawurlencode($temp);
    case 'esp_pos_t_m':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadPorPosicionESP/' . rawurlencode($temp) . '/' . $modId;
    case 'esp_pos_qs':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaPorPosicionESP?modalidadId=' . $modId . '&temporadaId=' . rawurlencode($temp);
    case 'espg_pag_m_t':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaESPPagGlicko2/' . $modId . '/' . rawurlencode($temp) . '?page=1&pageSize=500';
    case 'espg_pag_t_qs':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadESPPagGlicko2?temporadaId=' . rawurlencode($temp) . '&modalidadId=' . $modId . '&page=1&pageSize=500';
    case 'esg_qs':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2?modalidadId=' . $modId . '&temporadaId=' . rawurlencode($temp);
    case 'esg_qs_inv':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2?temporadaId=' . rawurlencode($temp) . '&modalidadId=' . $modId;
    case 'espg_pag_qs':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaESPPagGlicko2?modalidadId=' . $modId . '&temporadaId=' . rawurlencode($temp) . '&page=1&pageSize=500';
    case 'espg_t_m':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadESPPagGlicko2/' . rawurlencode($temp) . '/' . $modId . '?page=1&pageSize=500';
    case 'extg_m_t':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaEXTGlicko2/' . $modId . '/' . rawurlencode($temp);
    case 'extg_qs':
      return '/api/Ranking/GetRankingPorModalidadPorTemporadaEXTGlicko2?modalidadId=' . $modId . '&temporadaId=' . rawurlencode($temp);
    case 'extg_t_m':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadEXTGlicko2/' . rawurlencode($temp) . '/' . $modId;
    case 'extg_t_qs':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidadEXTGlicko2?temporadaId=' . rawurlencode($temp) . '&modalidadId=' . $modId;
    case 'plain_m_t':
      return '/api/Ranking/GetRankingPorModalidadPorTemporada/' . $modId . '/' . rawurlencode($temp);
    case 'plain_qs':
      return '/api/Ranking/GetRankingPorModalidadPorTemporada?modalidadId=' . $modId . '&temporadaId=' . rawurlencode($temp);
    case 'plain_t_m':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidad/' . rawurlencode($temp) . '/' . $modId;
    case 'plain_t_qs':
      return '/api/Ranking/GetRankingPorTemporadaPorModalidad?temporadaId=' . rawurlencode($temp) . '&modalidadId=' . $modId;
  }
}
// Intenta descubrir un patrón válido probando varias variantes con una temporada de muestra
function rf_hitos_rank_endpoint_discover($api, $modId, $tempId, $tempYear = null){
  if (!$api) return '';
  $candidates = [
    // Fuente principal para galardón: ESP Glicko2 por temporada
    'esg_m_t', 'esg_qs', 'esg_qs_inv', 'esg_t_m', 'esg_t_qs',
    // Paginado por temporada (si existiera)
    'espg_pag_m_t', 'espg_pag_qs', 'espg_t_m', 'espg_pag_t_qs',
    // Variante ESP (no Glicko)
    'esp_m_t', 'esp_t_m', 'esp_t_qs',
    // Por posición (algunos hosts lo exponen; lo dejamos como último recurso)
    'esp_pos_m_t', 'esp_pos_t_m', 'esp_pos_qs',
    // Variante EXT Glicko
    'extg_m_t', 'extg_qs', 'extg_t_m', 'extg_t_qs',
    // Genéricos sin sufijo (legacy)
    'plain_m_t', 'plain_qs', 'plain_t_m', 'plain_t_qs'
  ];
  // Fast-mode: reduce drásticamente los patrones probados para acelerar
  if (isset($_GET['rf_debug_hitos_fast']) && $_GET['rf_debug_hitos_fast'] == '1') {
    // Incluir también query-string y alternas más comunes
    $candidates = [
      'esp_pos_m_t', 'esp_m_t', 'esg_m_t', 'espg_pag_m_t', 'plain_m_t',
      'esg_qs', 'esg_qs_inv', 'espg_pag_qs', 'esp_t_qs', 'plain_qs', 'esg_t_m', 'esp_t_m'
    ];
    $candidates = array_values(array_unique($candidates));
  if ((function_exists('rf_hitos_is_debug') && rf_hitos_is_debug()) || (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1')) { error_log('RFHITOS fast-mode: endpoint discovery patterns reduced to ' . implode(',', $candidates)); if (function_exists('rf_log')) { rf_log('RFHITOS fast-mode', ['patterns'=>$candidates], 'debug'); } }
  }
  foreach ($candidates as $pat){
    try {
      $path = rf_hitos_rank_build_path($pat, $modId, $tempId);
      $rows = rf_call_api($api, $path);
      $items = rf_hitos_unwrap_items($rows);
      $count = is_array($items) ? count($items) : 0;
  if ((function_exists('rf_hitos_is_debug') && rf_hitos_is_debug()) || (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1')) { error_log('RFHITOS probe pattern=' . $pat . ' temp=' . $tempId . ' mod=' . intval($modId) . ' count=' . $count); if (function_exists('rf_log')) { rf_log('RFHITOS probe', ['pattern'=>$pat,'temp'=>$tempId,'mod'=>(int)$modId,'count'=>$count], 'debug'); } }
      if ($count > 0) {
        // Enriquecer diagnóstico cuando hay acierto: claves del primer elemento
        if (!empty($items)) {
          $first = is_array($items[0]) ? (array)$items[0] : (array)$items[0];
          if ((function_exists('rf_hitos_is_debug') && rf_hitos_is_debug()) || (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1')) { error_log('RFHITOS probe hit pattern=' . $pat . ' firstKeys=' . implode(',', array_slice(array_keys($first), 0, 12))); }
        }
        return $pat;
      }
      if ($count === 0 && $tempYear) {
        $pathY = rf_hitos_rank_build_path($pat, $modId, $tempYear);
        $rowsY = rf_call_api($api, $pathY);
        $itemsY = rf_hitos_unwrap_items($rowsY);
        $countY = is_array($itemsY) ? count($itemsY) : 0;
  if ((function_exists('rf_hitos_is_debug') && rf_hitos_is_debug()) || (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1')) { error_log('RFHITOS probe pattern=' . $pat . ' year=' . $tempYear . ' mod=' . intval($modId) . ' count=' . $countY); }
        if ($countY > 0) {
          if (!empty($itemsY)) {
            $firstY = is_array($itemsY[0]) ? (array)$itemsY[0] : (array)$itemsY[0];
            if ((function_exists('rf_hitos_is_debug') && rf_hitos_is_debug()) || (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1')) { error_log('RFHITOS probe hit pattern=' . $pat . ' (year) firstKeys=' . implode(',', array_slice(array_keys($firstY), 0, 12))); if (function_exists('rf_log')) { rf_log('RFHITOS probe hit (year)', ['pattern'=>$pat], 'debug'); } }
          }
          return $pat;
        }
      }
  } catch (\Throwable $e) { /* ignore and try next */ }
  }
  return '';
}
// ---- Cache negativa (per-season ranking) para evitar martilleo cuando todo viene vacío ----
function rf_hitos_rank_empty_key($api, $modId, $tempKey){
  $host = 'api';
  if ($api && method_exists($api, 'get_base_api_url')) {
    $base = $api->get_base_api_url();
    if (is_string($base)) {
      $p = parse_url($base);
      if (is_array($p) && !empty($p['host'])) { $host = strtolower($p['host']); }
    }
  }
  // Versionar por fingerprint de token (si existe) para evitar falsos vacíos cuando cambia el bearer
  $tfp = '';
  if ($api && method_exists($api, 'get_token_fingerprint')) {
    try { $tfp = (string)$api->get_token_fingerprint(); } catch (\Throwable $e) { $tfp = ''; }
  }
  $tag = $tfp !== '' ? (':tfp:' . $tfp) : '';
  return 'rf:rank:per-season:empty:' . $host . ':' . intval($modId) . ':' . strval($tempKey) . $tag;
}
function rf_hitos_rank_empty_is_marked($api, $modId, $tempKey){
  $k = rf_hitos_rank_empty_key($api, $modId, $tempKey);
  if (function_exists('get_transient')) {
    $v = call_user_func('get_transient', $k);
    return ($v === '1');
  }
  static $mem = [];
  return isset($mem[$k]) && $mem[$k] === '1';
}
function rf_hitos_rank_empty_mark($api, $modId, $tempKey, $ttl = 900){ // 15 min
  $k = rf_hitos_rank_empty_key($api, $modId, $tempKey);
  if (function_exists('set_transient')) { call_user_func('set_transient', $k, '1', $ttl); return; }
  static $mem = [];
  $mem[$k] = '1';
}
function rf_hitos_rank_empty_delete($api, $modId, $tempKey){
  $k = rf_hitos_rank_empty_key($api, $modId, $tempKey);
  if (function_exists('delete_transient')) { call_user_func('delete_transient', $k); return; }
  static $mem = []; unset($mem[$k]);
}
function rf_hitos_rank_global_skip_key($api){
  $host = 'api';
  if ($api && method_exists($api, 'get_base_api_url')) {
    $base = $api->get_base_api_url();
    if (is_string($base)) { $p = parse_url($base); if (is_array($p) && !empty($p['host'])) { $host = strtolower($p['host']); } }
  }
  $tfp = '';
  if ($api && method_exists($api, 'get_token_fingerprint')) {
    try { $tfp = (string)$api->get_token_fingerprint(); } catch (\Throwable $e) { $tfp = ''; }
  }
  $tag = $tfp !== '' ? (':tfp:' . $tfp) : '';
  return 'rf:rank:per-season:global-empty:' . $host . $tag;
}
function rf_hitos_rank_global_skip_is_marked($api){
  $k = rf_hitos_rank_global_skip_key($api);
  if (function_exists('get_transient')) { $v = call_user_func('get_transient', $k); return ($v === '1'); }
  static $mem = []; return isset($mem[$k]) && $mem[$k] === '1';
}
function rf_hitos_rank_global_skip_mark($api, $ttl = 900){
  $k = rf_hitos_rank_global_skip_key($api);
  if (function_exists('set_transient')) { call_user_func('set_transient', $k, '1', $ttl); return; }
  static $mem = []; $mem[$k] = '1';
}
function rf_hitos_rank_global_skip_reset($api){
  $k = rf_hitos_rank_global_skip_key($api);
  if (function_exists('delete_transient')) { call_user_func('delete_transient', $k); return; }
  static $mem = []; unset($mem[$k]);
}
function rf_hitos_get_temporada_map($api){
  $out = ['id_to_ord'=>[], 'year_to_ord'=>[], 'id_to_year'=>[], 'ord_to_id'=>[]];
  if(!$api) return $out;
  // Cache key por host (y opcionalmente fingerprint) para evitar llamadas tempranas repetidas
  $host = '';
  try { if (method_exists($api,'get_base_api_url')) { $u = (string)$api->get_base_api_url(); $p = @parse_url($u); if (is_array($p) && !empty($p['host'])) { $host = strtolower($p['host']); } } } catch (\Throwable $e) {}
  $ck = 'rf_hitos_tmap_' . md5($host !== '' ? $host : 'default');
  // Mem cache por petición
  static $mem = [];
  if (isset($mem[$ck]) && is_array($mem[$ck])) { return $mem[$ck]; }
  // Transient cache persistente
  if (function_exists('get_transient')) {
    try { $cached = call_user_func('get_transient', $ck); } catch (\Throwable $e) { $cached = false; }
    if (is_array($cached) && isset($cached['id_to_ord'])) { $mem[$ck] = $cached; return $cached; }
  }
  // Presupuesto de tiempo: si ya se excedió, devolver un mapa mínimo sin bloquear
  if (isset($GLOBALS['rf_hitos_time_exceeded']) && is_callable($GLOBALS['rf_hitos_time_exceeded'])) {
    try { if ($GLOBALS['rf_hitos_time_exceeded']()) {
      // por defecto 14 temporadas como máximo
      $maxOrd = defined('FUTBOLIN_MAX_SEASON_ORDINAL') ? (int)constant('FUTBOLIN_MAX_SEASON_ORDINAL') : 14;
      for($i=1;$i<=max(1,$maxOrd);$i++){ $out['id_to_ord'][$i] = $i; $out['ord_to_id'][$i] = $i; }
      $mem[$ck] = $out; return $out;
    } } catch (\Throwable $e) {}
  }
  try { $rows = rf_call_api($api, '/api/Temporada/GetTemporadas'); } catch (\Throwable $e) { $rows = []; }
  $items = rf_hitos_unwrap_items($rows);
  if (!empty($items)){
    $candidates = [];
    foreach ($items as $row){
      $o = (object)$row;
      $id = intval($o->id ?? ($o->temporadaId ?? ($o->TemporadaId ?? 0)));
      $year = null;
      foreach (['anio','year','anioTemporada'] as $yk){ if(isset($o->{$yk}) && is_numeric($o->{$yk})) { $year = intval($o->{$yk}); break; } }
      $name = '';
      foreach(['name','nombre','descripcion','label'] as $nk){ if(isset($o->{$nk})){ $name = (string)$o->{$nk}; break; } }
      if (!$year && $name && preg_match('/(19|20)\d{2}/',$name,$m)) $year = intval($m[0]);
      $candidates[] = ['id'=>$id,'year'=>$year];
    }
    usort($candidates, function($a,$b){
      $ay = $a['year'] ?? null; $by = $b['year'] ?? null;
      if ($ay && $by && $ay !== $by) return $ay <=> $by;
      if ($ay && !$by) return -1;
      if (!$ay && $by) return 1;
      return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    });
    $ordinal = 1;
    foreach ($candidates as $cand){
      $id = $cand['id'] ?? 0; if(!$id) continue;
      if(isset($out['id_to_ord'][$id])) continue;
      $out['id_to_ord'][$id] = $ordinal;
      if(!empty($cand['year'])) {
        $out['year_to_ord'][$cand['year']] = $ordinal;
        $out['id_to_year'][$id] = $cand['year'];
      }
      $out['ord_to_id'][$ordinal] = $id;
      $ordinal++;
    }
  }
  if (empty($out['id_to_ord'])){
    for($i=1;$i<=20;$i++){ $out['id_to_ord'][$i] = $i; }
  }
  // Guardar en caché (TTL largo si hay datos; corto si vacío)
  $hasData = !empty($out['id_to_ord']);
  $ttl = $hasData ? (defined('FUTBOLIN_TEMPORADA_MAP_TTL') ? (int)constant('FUTBOLIN_TEMPORADA_MAP_TTL') : 14*24*3600) : 2*3600;
  $mem[$ck] = $out;
  if (function_exists('set_transient')) { try { call_user_func('set_transient', $ck, $out, $ttl); } catch (\Throwable $e) {} }
  return $out;
}

function rf_hitos_svg_star_big($tone='gold',$size=28){
  $gid='rf-starbig-'.$tone.'-'.substr(md5(uniqid('',true)),0,6);
  $g=['gold'=>['#fff7c2','#f6b73c','#d19900'],'silver'=>['#f9fbff','#cfd6e1','#a1aab8'],'bronze'=>['#ffe2c5','#d18a4b','#a65a24']];
  $gg=$g[$tone]??$g['gold']; ob_start(); ?>
  <svg viewBox="0 0 24 24" aria-hidden="true" width="<?php echo (int)$size; ?>" height="<?php echo (int)$size; ?>">
    <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="<?php echo esc_attr($gg[0]); ?>"/><stop offset="55%" stop-color="<?php echo esc_attr($gg[1]); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($gg[2]); ?>"/>
    </linearGradient></defs>
    <path fill="url(#<?php echo esc_attr($gid); ?>)" d="M12 2.3l3.4 6.7 7.4.7-5.5 4.7 1.8 7.1L12 18 4.9 21.5l1.8-7.1L1.2 9.7l7.4-.7z"></path>
  </svg>
  <?php return trim(ob_get_clean());
}
function rf_hitos_svg_crown($tone='gold',$size=22){
  $gid='rf-crownb-'.$tone.'-'.substr(md5(uniqid('',true)),0,6);
  $g=['gold'=>['#fff7c2','#f6b73c','#d19900'],'silver'=>['#ffffff','#cfd6e1','#9aa3b1'],'bronze'=>['#ffe6cf','#d7935b','#b46535']];
  $gg=$g[$tone]??$g['gold']; ob_start(); ?>
  <svg viewBox="0 0 128 96" aria-hidden="true" width="<?php echo (int)$size; ?>" height="<?php echo (int)$size; ?>">
    <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="<?php echo esc_attr($gg[0]); ?>"/><stop offset="55%" stop-color="<?php echo esc_attr($gg[1]); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($gg[2]); ?>"/>
    </linearGradient></defs>
    <path fill="url(#<?php echo esc_attr($gid); ?>)" d="M8 72 L120 72 L112 92 Q64 100 16 92 Z M8 72 C20 54, 32 48, 42 52 C44 42, 52 34, 64 30 C76 34, 84 42, 86 52 C96 48, 108 54, 120 72 Z" />
    <circle cx="28" cy="48" r="5" fill="url(#<?php echo esc_attr($gid); ?>)" />
    <circle cx="52" cy="36" r="6" fill="url(#<?php echo esc_attr($gid); ?>)" />
    <circle cx="64" cy="28" r="7" fill="url(#<?php echo esc_attr($gid); ?>)" />
    <circle cx="76" cy="36" r="6" fill="url(#<?php echo esc_attr($gid); ?>)" />
    <circle cx="100" cy="48" r="5" fill="url(#<?php echo esc_attr($gid); ?>)" />
  </svg>
  <?php return trim(ob_get_clean());
}

// Candado para estados no conseguidos (sticker de Hitos)
if (!function_exists('rf_hitos_svg_lock')) {
  function rf_hitos_svg_lock($tone='locked',$size=56){
    $gid='rf-hlock-'.$tone.'-'.substr(md5(uniqid('',true)),0,6);
    $g=['locked'=>['#f9fbff','#c8ced8','#8c95a3'],'gold'=>['#fff7c2','#f6b73c','#d19900'],'silver'=>['#f9fbff','#cfd6e1','#a1aab8'],'bronze'=>['#ffe2c5','#d18a4b','#a65a24']];
    $gg=$g[$tone]??$g['locked']; ob_start(); ?>
    <svg viewBox="0 0 24 24" aria-hidden="true" width="<?php echo (int)$size; ?>" height="<?php echo (int)$size; ?>">
      <defs><linearGradient id="<?php echo esc_attr($gid); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="<?php echo esc_attr($gg[0]); ?>"/><stop offset="100%" stop-color="<?php echo esc_attr($gg[1]); ?>"/>
      </linearGradient></defs>
      <rect x="5" y="10" width="14" height="10" rx="2.5" fill="url(#<?php echo esc_attr($gid); ?>)" stroke="<?php echo esc_attr($gg[2]); ?>" stroke-width="1"/>
      <path d="M8 10V8a4 4 0 1 1 8 0v2" stroke="<?php echo esc_attr($gg[2]); ?>" stroke-width="1.5" fill="none" stroke-linecap="round"/>
      <circle cx="12" cy="15" r="1.5" fill="<?php echo esc_attr($gg[2]); ?>"/>
    </svg>
    <?php return trim(ob_get_clean());
  }
}

/** Extrae jugadorId robustamente de un item de ranking */
function rf_hitos_extract_jid($row){
  if (!$row) return 0;
  $o = is_object($row) ? $row : (object)$row;
  $candidates = ['jugadorId','JugadorId','idJugador','IdJugador','playerId','PlayerId','id','Id'];
  foreach ($candidates as $k){ if (isset($o->{$k}) && is_numeric($o->{$k})) { return intval($o->{$k}); } }
  // anidado bajo objeto jugador
  foreach (['jugador','Jugador','player','Player'] as $jk){
    if (isset($o->{$jk})){
      $j = $o->{$jk};
      if (is_object($j)){
        foreach (['id','Id','jugadorId','JugadorId'] as $ik){ if (isset($j->{$ik}) && is_numeric($j->{$ik})) { return intval($j->{$ik}); } }
      } elseif (is_array($j)){
        foreach (['id','Id','jugadorId','JugadorId'] as $ik){ if (isset($j[$ik]) && is_numeric($j[$ik])) { return intval($j[$ik]); } }
      }
    }
  }
  return 0;
}

/** Resuelve IDs de modalidades abiertas (dobles/individual) desde la API */
function rf_hitos_get_open_modalidad_ids($api){
  $mods = ['dobles'=>2,'individual'=>1];
  if(!$api || !method_exists($api,'get_modalidades')) return $mods;
  try { $rows = $api->get_modalidades(); } catch (\Throwable $e) { $rows = []; }
  $items = rf_hitos_unwrap_items($rows);
  if (!is_array($items) || !count($items)) return $mods;
  $norm = function($s){ $s = strtolower((string)$s); $tr = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']; return strtr($s,$tr); };
  foreach ($items as $row){
    $o = (object)$row; $id = $o->id ?? ($o->modalidadId ?? null); if (!$id) continue; $name = $o->name ?? ($o->nombre ?? ''); $n = $norm($name);
    if ($n === '') continue;
    if ((strpos($n,'doble')!==false) || (strpos($n,'pareja')!==false)) { $mods['dobles'] = (int)$id; }
    if (strpos($n,'individual')!==false) { $mods['individual'] = (int)$id; }
  }
  if ((function_exists('rf_hitos_is_debug') && rf_hitos_is_debug()) || (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1')) { error_log('RFHITOS modalidad_ids individual=' . $mods['individual'] . ' dobles=' . $mods['dobles']); if (function_exists('rf_log')) { rf_log('RFHITOS modalidad_ids', ['individual'=>$mods['individual'],'dobles'=>$mods['dobles']], 'debug'); } }
  return $mods;
}

/** ==== Player + API ==== */
// Resuelve player_id respetando el existente (del wrapper) y usando fallbacks
$player_id = isset($player_id) ? intval($player_id) : 0;
if ($player_id <= 0 && isset($processor) && is_object($processor)) {
  if (isset($processor->jugadorId)) {
    $player_id = intval($processor->jugadorId);
  } elseif (isset($processor->basic_data->jugadorId)) {
    $player_id = intval($processor->basic_data->jugadorId);
  }
}
if ($player_id <= 0) {
  if (isset($_GET['jugador_id'])) { $player_id = intval($_GET['jugador_id']); }
  elseif (isset($_GET['player_id'])) { $player_id = intval($_GET['player_id']); }
}
if ($rf_debug_on) { error_log('RFHITOS resolved player_id=' . $player_id); if (function_exists('rf_log')) { rf_log('RFHITOS resolved player_id', ['player_id'=>$player_id], 'debug'); } }

// === Intento de cargar perfil cacheado (posiciones/partidos) ===
$rf_player_cache = null;
if ($player_id > 0 && class_exists('RF_Hitos_Cache_Manager') && RF_Hitos_Cache_Manager::is_cache_ready()) {
  $rf_player_cache = RF_Hitos_Cache_Manager::cache_read('player_' . $player_id);
  if ($rf_debug_on && $rf_player_cache) { error_log('RFHITOS perfil cacheado player_'.$player_id.' keys='.implode(',', array_keys($rf_player_cache))); }
}
// Disponible para otras secciones si quieren usarlo
$GLOBALS['rf_hitos_player_cache'] = $rf_player_cache;
$api = class_exists('Futbolin_API_Client') ? new \Futbolin_API_Client() : null;
if ($api && isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1') {
  try {
    $sig = method_exists($api,'rf_client_sig') ? $api->rf_client_sig() : 'no-sig';
    $base = method_exists($api,'get_base_api_url') ? $api->get_base_api_url() : '';
  if ($rf_debug_on) { error_log('RFHITOS client_sig=' . $sig . ' base=' . $base); if (function_exists('rf_log')) { rf_log('RFHITOS client_sig', ['sig'=>$sig,'base'=>$base], 'debug'); } }
  } catch (\Throwable $e) { /* ignore */ }
}

// Diagnóstico: fingerprint del token (no expone el token). Solo si rf_debug_hitos=1
if ($api && isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1' && method_exists($api, 'get_token_fingerprint')) {
  if ($rf_debug_on) { try { $tfp = (string)$api->get_token_fingerprint(); error_log('RFHITOS token_fingerprint=' . ($tfp !== '' ? $tfp : '(none)')); } catch (\Throwable $e) {} }
}

// Presupuesto de tiempo para evitar cuelgues: por defecto 10s por render (ajustable por GET o filtro)
$rf_start_ts = microtime(true);
$rf_budget_ms = 10000; // 10s
if (isset($_GET['rf_debug_hitos_budget']) && is_numeric($_GET['rf_debug_hitos_budget'])) {
  $rf_budget_ms = max(1000, intval($_GET['rf_debug_hitos_budget']));
}
if (function_exists('apply_filters')) {
  $rf_budget_ms = apply_filters('rf_hitos_time_budget_ms', $rf_budget_ms);
}
$rf_time_exceeded = function() use ($rf_start_ts, $rf_budget_ms) {
  $elapsed = (int) round((microtime(true) - $rf_start_ts) * 1000);
  return $elapsed >= $rf_budget_ms;
};
// Expone para funciones auxiliares
$GLOBALS['rf_hitos_time_exceeded'] = $rf_time_exceeded;

$rf_hitos_temporada_map = rf_hitos_get_temporada_map($api);
$GLOBALS['rf_hitos_temporada_map'] = $rf_hitos_temporada_map;
$rf_hitos_temporada_ids = array_keys($rf_hitos_temporada_map['id_to_ord']);
if ($rf_debug_on) { error_log('RFHITOS map ids=' . json_encode($rf_hitos_temporada_map['id_to_ord'])); }
usort($rf_hitos_temporada_ids, function($a,$b) use ($rf_hitos_temporada_map){
  $ordA = $rf_hitos_temporada_map['id_to_ord'][$a] ?? $a;
  $ordB = $rf_hitos_temporada_map['id_to_ord'][$b] ?? $b;
  if ($ordA === $ordB) return $a <=> $b;
  return $ordA <=> $ordB;
});
// Limitar por ordinal máximo para evitar temporadas inexistentes (p.ej., solo hay 14)
$__rf_max_season_ord = defined('FUTBOLIN_MAX_SEASON_ORDINAL') ? (int)constant('FUTBOLIN_MAX_SEASON_ORDINAL') : 14;
if ($__rf_max_season_ord > 0 && is_array($rf_hitos_temporada_ids) && !empty($rf_hitos_temporada_ids)) {
  $rf_hitos_temporada_ids = array_values(array_filter($rf_hitos_temporada_ids, function($id) use ($rf_hitos_temporada_map, $__rf_max_season_ord){
    $ord = (int)($rf_hitos_temporada_map['id_to_ord'][$id] ?? 0);
    return $ord > 0 && $ord <= $__rf_max_season_ord;
  }));
  if ($rf_debug_on) { error_log('RFHITOS clamp temporadas to ordinal <=' . $__rf_max_season_ord . ' -> ids=' . implode(',', $rf_hitos_temporada_ids)); }
}
// Modo rápido opcional: limita el número de temporadas analizadas a las más recientes
$__rf_fast_mode = (isset($_GET['rf_debug_hitos_fast']) && $_GET['rf_debug_hitos_fast'] == '1');
if ($__rf_fast_mode && !empty($rf_hitos_temporada_ids)) {
  // Tomar las últimas 6 temporadas en orden ascendente (más recientes al final)
  $take = 6;
  if (count($rf_hitos_temporada_ids) > $take) {
    $rf_hitos_temporada_ids = array_slice($rf_hitos_temporada_ids, -$take);
  if ($rf_debug_on) { error_log('RFHITOS fast-mode: temporadas limitadas a ' . implode(',', $rf_hitos_temporada_ids)); }
  }
}
if (empty($rf_hitos_temporada_ids)) { $rf_hitos_temporada_ids = range(1,14); }

/** ==== Campeones (torneosDobles / torneosIndividual) ==== */
$temps_campeon_d=[]; $temps_campeon_i=[];
if ($api && $player_id) {
  try {
    // Intento de lectura desde cache persistente si está lista
    $rows = null;
    $rf_cache_ready = class_exists('RF_Hitos_Cache_Manager') && RF_Hitos_Cache_Manager::is_cache_ready();
    if ($rf_cache_ready) {
      $rows = RF_Hitos_Cache_Manager::cache_read('campeones');
    }
    if ($rows === null) {
      if (method_exists($api, 'get_campeones_espania')) { $rows = $api->get_campeones_espania(); }
      else { $rows = rf_call_api($api, '/api/Jugador/GetCampeonesEspania'); }
      // Si la cache estaba lista pero no existía este archivo concreto (o estaba corrupto) lo escribimos
      if ($rf_cache_ready && $rows !== null && !is_wp_error($rows)) {
        RF_Hitos_Cache_Manager::cache_write('campeones', $rows);
      }
    }
    if (is_wp_error($rows)) { error_log('RFHITOS error get_campeones_espania: ' . $rows->get_error_message()); $rows = []; }
    $items = rf_hitos_unwrap_items($rows);
    $total_items = is_array($items) ? count($items) : 0;
  if ($rf_debug_on) { error_log('RFHITOS champions items=' . $total_items); }
    if ($total_items > 0) {
      $sample = (array)(is_array($items[0]) ? $items[0] : (array)$items[0]);
  if ($rf_debug_on) { error_log('RFHITOS first item keys=' . implode(',', array_slice(array_keys($sample),0,12))); }
    }
    $matched = false; $seen_ids = [];
    foreach ($items as $row) {
      $o = (object)$row;
      $jid = intval($o->jugadorId ?? ($o->JugadorId ?? 0));
      if ($jid) { $seen_ids[] = $jid; }
      if ($jid !== intval($player_id)) continue;
      $matched = true;
      $grab = function($arr){
        // Para campeonatos: usar SIEMPRE el año textual como verdad (de 'temporada' o 'nombreTorneo').
        $out=[]; if (!is_array($arr)) return $out;
        foreach($arr as $it){
          if (!(is_object($it)||is_array($it))) { continue; }
          $ii=(object)$it;
          $year = null;
          // Prioriza el año del nombre del torneo (suele ser correcto para este endpoint)
          if (isset($ii->nombreTorneo) && is_string($ii->nombreTorneo) && preg_match('/(19|20)\d{2}/', $ii->nombreTorneo, $m2)) { $year = (int)$m2[0]; }
          if (!$year && isset($ii->temporada) && is_string($ii->temporada) && preg_match('/(19|20)\d{2}/', $ii->temporada, $m)) { $year = (int)$m[0]; }
          // Corrección específica: este endpoint puede etiquetar 2024 como "Temporada 2025" → trunca 2025→2024
          if ($year === 2025) { $year = 2024; }
          // Fallback: intenta deducir del temporadaId solo si no se ha podido extraer año textual
          if (!$year && isset($ii->temporadaId) && is_numeric($ii->temporadaId)) {
            $tid = (int)$ii->temporadaId;
            $map = isset($GLOBALS['rf_hitos_temporada_map']) && is_array($GLOBALS['rf_hitos_temporada_map']) ? $GLOBALS['rf_hitos_temporada_map'] : [];
            if (isset($map['id_to_year'][$tid]) && is_numeric($map['id_to_year'][$tid])) { $year = (int)$map['id_to_year'][$tid]; }
          }
          if ($year) { $out[] = $year; }
        }
        $out = array_values(array_unique(array_filter($out, function($v){ return is_numeric($v) && (int)$v > 0; })));
        sort($out);
        return $out;
      };
      $temps_campeon_d = array_merge($temps_campeon_d, $grab($o->torneosDobles ?? []));
      $temps_campeon_i = array_merge($temps_campeon_i, $grab($o->torneosIndividual ?? []));
      break;
    }
    if (!$matched) {
      $sample_ids = implode(',', array_slice(array_values(array_unique($seen_ids)),0,10));
      if ($rf_debug_on) { error_log('RFHITOS no match for player_id=' . $player_id . ' seen_ids_sample=[' . $sample_ids . ']'); }
    }
  } catch (\Throwable $e) {}
}
$temps_campeon_d = array_values(array_unique(array_filter($temps_campeon_d))); sort($temps_campeon_d);
$temps_campeon_i = array_values(array_unique(array_filter($temps_campeon_i))); sort($temps_campeon_i);
if ($rf_debug_on) { error_log('RFHITOS champs counts player=' . $player_id . ' dobles=' . count($temps_campeon_d) . ' individual=' . count($temps_campeon_i)); }

/** ==== Nº1/2/3 (por temporada; orden de aparición idx 1..3) ==== */
$temps_no1_d=[]; $temps_no1_i=[]; $temps_no2_d=[]; $temps_no2_i=[]; $temps_no3_d=[]; $temps_no3_i=[];
$rf_debug_hits = ['hits'=>[], 'attempts'=>0];

// Declarar variables de override fuera del bloque principal para que estén disponibles en la plantilla
$rf_bypass_cache = (isset($_GET['rf_debug_hitos_bypass_cache']) && $_GET['rf_debug_hitos_bypass_cache'] == '1');
$rf_force_pattern = isset($_GET['rf_debug_hitos_force_pattern']) ? preg_replace('/[^a-z0-9_\-]/i','', (string)$_GET['rf_debug_hitos_force_pattern']) : '';
$rf_only_temp     = isset($_GET['rf_debug_hitos_only_temp']) ? preg_replace('/[^0-9]/','', (string)$_GET['rf_debug_hitos_only_temp']) : '';
$rf_only_mod      = isset($_GET['rf_debug_hitos_only_mod']) ? intval($_GET['rf_debug_hitos_only_mod']) : 0;

// Detectar si hay overrides específicos activos
$rf_has_overrides = ($rf_force_pattern || $rf_only_temp || $rf_only_mod || $rf_bypass_cache);

// Si hay rf_debug_hitos_only_temp, asegúrate de que esa temporada esté incluida aunque el fast-mode la haya recortado
if ($rf_only_temp !== '') {
  $__want_temp = (int)$rf_only_temp;
  if (!in_array($__want_temp, $rf_hitos_temporada_ids, true)) {
    $rf_hitos_temporada_ids[] = $__want_temp;
    // Opcional: registrar para diagnóstico
    if (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') {
      error_log('RFHITOS include-only_temp added back into list: ' . $__want_temp);
    }
  }
}

if ($api && $player_id) {
  // Regla de host: activar modo "auth-forced" y evitar caches negativas cuando el host lo requiere.
  $rf_host_now = 'api';
  if ($api && method_exists($api, 'get_base_api_url')) {
    $baseTmp = $api->get_base_api_url();
    if (is_string($baseTmp)) { $phtmp = parse_url($baseTmp); if (is_array($phtmp) && !empty($phtmp['host'])) { $rf_host_now = strtolower($phtmp['host']); } }
  }
  // No forzar auth globalmente para este host; dejar que el flujo estándar gestione no-auth primero y reintento con auth
  $rf_host_auth_forced = false;

  // Overrides de debug opcionales (sanitizados manualmente para evitar dependencias)
  // Reset manual de cache global y por combinación vía parámetro de debug
  if (isset($_GET['rf_debug_hitos_reset']) && $_GET['rf_debug_hitos_reset'] == '1') {
    rf_hitos_rank_global_skip_reset($api);
    // Borrar cache negativa (per-season) para todas las combinaciones conocidas
    $mods_for_reset = rf_hitos_get_open_modalidad_ids($api);
    foreach ($rf_hitos_temporada_ids as $tempId) {
      foreach ([(int)$mods_for_reset['individual'], (int)$mods_for_reset['dobles']] as $mid) {
        rf_hitos_rank_empty_delete($api, $mid, $tempId);
      }
    }
  if ($rf_debug_on) { error_log('RFHITOS global and per-season empty-cache reset via rf_debug_hitos_reset=1'); }
  }
  // Nota: se elimina la "sonda" de autenticación inicial para este flujo. El intento sin-auth → con-auth ya se realiza
  // de forma controlada por llamada dentro de rf_call_api para el endpoint oficial por temporada.
  // Check cache global, pero ignorarlo si tenemos overrides específicos o si rf_debug_hitos=1
  $rf_debug_on = (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1');
  $rf_should_skip = (!$rf_bypass_cache && !$rf_has_overrides && !$rf_debug_on && rf_hitos_rank_global_skip_is_marked($api));
  // Añade guard de backoff global por errores 5xx recientes del endpoint de temporada
  try {
    $rf_host_now2 = 'api';
    if ($api && method_exists($api, 'get_base_api_url')) { $b2 = $api->get_base_api_url(); $p2 = parse_url($b2); if (is_array($p2) && !empty($p2['host'])) { $rf_host_now2 = strtolower($p2['host']); } }
    $rf_season_err_key = 'futb_season_err:' . $rf_host_now2;
    if (!$rf_bypass_cache && !$rf_has_overrides && !$rf_debug_on && function_exists('get_transient') && get_transient($rf_season_err_key)) {
      $rf_should_skip = true;
      if ($rf_debug_on) { error_log('RFHITOS skip due to recent season 5xx backoff for host=' . $rf_host_now2); }
    }
  } catch (\Throwable $e) { /* ignore */ }
  if ($rf_host_auth_forced && $rf_should_skip) { $rf_should_skip = false; error_log('RFHITOS ignoring global-skip due to host auth-forced rule for ' . $rf_host_now); }
  if ($rf_should_skip) {
    // Sonda rápida: probar la temporada más reciente y varios patrones conocidos para intentar romper la marca
    try {
      $mods_probe = rf_hitos_get_open_modalidad_ids($api);
      // Respetar overrides si existen
      $probe_mod = isset($rf_only_mod) && $rf_only_mod ? (int)$rf_only_mod : (int)($mods_probe['individual'] ?? 1);
      if (isset($rf_only_temp) && $rf_only_temp !== '') { $probe_temp = (int)$rf_only_temp; }
      else {
        // Elegimos la última temporada (más probable que tenga datos)
        $probe_temp = 1;
        if (is_array($rf_hitos_temporada_ids) && count($rf_hitos_temporada_ids)) { $probe_temp = (int)end($rf_hitos_temporada_ids); }
      }
      // Patrones a probar: en swagger-only solo esg_m_t; si hay guardado y discovery activo, incluirlo
      $saved_pat = rf_hitos_rank_endpoint_get($api);
      $rf_discovery_enabled = (
        (isset($_GET['rf_debug_hitos_discover']) && $_GET['rf_debug_hitos_discover'] == '1') ||
        (defined('RF_HITOS_ENABLE_DISCOVERY') && constant('RF_HITOS_ENABLE_DISCOVERY') === true)
      );
      $probe_patterns = $rf_discovery_enabled ? array_values(array_unique(array_filter([$saved_pat, 'esg_m_t', 'esp_m_t', 'esp_pos_m_t']))) : ['esg_m_t'];
      foreach ($probe_patterns as $pp) {
        $probe_path = rf_hitos_rank_build_path($pp, $probe_mod, $probe_temp);
        $probe_rows = rf_call_api($api, $probe_path);
        $probe_items = rf_hitos_unwrap_items($probe_rows);
        $probe_count = is_array($probe_items) ? count($probe_items) : 0;
        if ($rf_debug_on) { error_log('RFHITOS global-skip probe pattern=' . $pp . ' mod=' . $probe_mod . ' temp=' . $probe_temp . ' count=' . $probe_count); }
        if ($probe_count > 0) {
          rf_hitos_rank_global_skip_reset($api);
          if (!$rf_bypass_cache) { rf_hitos_rank_endpoint_set($api, $pp); }
          $rf_should_skip = false;
          break;
        }
      }
    } catch (\Throwable $e) { /* no-op */ }
  }
  if ($rf_should_skip) {
  if ($rf_debug_on) { error_log('RFHITOS per-season ranking globally marked empty recently; skipping batch calls (no overrides)'); }
    // Saltar el bucle de llamadas por temporada; dejaremos los arrays de nº1/2/3 vacíos
    // para que la plantilla muestre el estado vacío. Continuar flujo hasta render.
  } else {
    if ($rf_has_overrides || $rf_debug_on) {
      error_log('RFHITOS proceeding (debug/overrides): pattern='.$rf_force_pattern.' temp='.$rf_only_temp.' mod='.$rf_only_mod.' bypass='.$rf_bypass_cache.' debug='.$rf_debug_on);
    }
    // Modo Discovery: OFF por defecto (Swagger-only). Se activa solo con rf_debug_hitos_discover=1 o RF_HITOS_ENABLE_DISCOVERY=true
    $rf_discovery_enabled = (
      (isset($_GET['rf_debug_hitos_discover']) && $_GET['rf_debug_hitos_discover'] == '1') ||
      (defined('RF_HITOS_ENABLE_DISCOVERY') && constant('RF_HITOS_ENABLE_DISCOVERY') === true)
    );
    // Aviso de modo activo (solo debug)
    if ($rf_debug_on) {
      $saved_pat = rf_hitos_rank_endpoint_get($api);
      $base_pat = $rf_discovery_enabled ? ($saved_pat ?: 'esg_m_t') : 'esg_m_t';
      error_log('RFHITOS mode=' . ($rf_discovery_enabled ? 'discovery-enabled' : 'swagger-only') . ' base_pattern=' . $base_pat);
    }
    $mod_ids = rf_hitos_get_open_modalidad_ids($api);
    $rf_fast = (isset($_GET['rf_debug_hitos_fast']) && $_GET['rf_debug_hitos_fast'] == '1');
    // Modo debug compacto: activo solo cuando rf_debug_hitos=1. En normal mantenemos el comportamiento completo.
    $rf_debug_on_local = (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1');
    $rf_debug_full = (isset($_GET['rf_debug_hitos_full']) && $_GET['rf_debug_hitos_full'] == '1');
    if ($rf_debug_on_local) {
      $rf_attempt_cap = $rf_fast ? 12 : ($rf_debug_full ? 9999 : 1);
    } else {
      // En producción: cubrir todas las temporadas (2 modalidades x nº de temporadas)
      // Mantén el modo rápido si está activado
      $rf_attempt_cap = $rf_fast ? 12 : (2 * max(1, count($rf_hitos_temporada_ids)));
    }
    $rf_attempts_done = 0;
    // En modo compacto (solo con debug), definimos la temporada objetivo como la última del listado
    $rf_target_temp = null;
    if ($rf_debug_on_local && !$rf_debug_full) {
      if (!empty($rf_hitos_temporada_ids)) { $rf_target_temp = (int)end($rf_hitos_temporada_ids); }
      if ($rf_only_temp !== '') { $rf_target_temp = (int)$rf_only_temp; }
    }
  // Iterar temporadas de la más reciente a la más antigua para priorizar hitos actuales
  foreach (array_reverse($rf_hitos_temporada_ids) as $tempId) {
      if ($rf_time_exceeded()) { if ($rf_debug_on) { error_log('RFHITOS time-budget exceeded before season loop for temp='.$tempId); } break; }
      if ($rf_debug_on_local && !$rf_debug_full && $rf_target_temp !== null && (int)$tempId !== (int)$rf_target_temp) { continue; }
      if ($rf_only_temp !== '' && (string)$tempId !== (string)$rf_only_temp) { continue; }
    foreach ([(int)$mod_ids['individual']=>'i',(int)$mod_ids['dobles']=>'d'] as $modId=>$suf) {
      if ($rf_time_exceeded()) { if ($rf_debug_on) { error_log('RFHITOS time-budget exceeded at mod loop for temp='.$tempId.' mod='.$modId); } break 2; }
      // En modo compacto (solo con debug), priorizamos dobles y saltamos individual si no es override
      if ($rf_debug_on_local && !$rf_debug_full && !$rf_only_mod && $suf === 'i') { continue; }
      if ($rf_only_mod && (int)$rf_only_mod !== (int)$modId) { continue; }
  if ($rf_attempts_done >= $rf_attempt_cap) { if ($rf_debug_on) { error_log('RFHITOS fast-mode: attempt cap reached ('.$rf_attempt_cap.')'); } break 2; }
      // Respetar overrides también en cache individual
      // Si el host va con auth forzada, ignorar caches negativas para reintentar con el nuevo modo
      if (!$rf_host_auth_forced && !$rf_bypass_cache && !$rf_has_overrides && rf_hitos_rank_empty_is_marked($api, $modId, $tempId)) { 
        if ($rf_debug_on) { error_log('RFHITOS skip cached empty mod='.$modId.' temp='.$tempId); } 
        continue; 
      }
      if ($rf_has_overrides && rf_hitos_rank_empty_is_marked($api, $modId, $tempId)) {
        if ($rf_debug_on) { error_log('RFHITOS ignoring cached empty mod='.$modId.' temp='.$tempId.' due to overrides'); }
      }
      // Reset del patrón del endpoint si se solicita
      if ((isset($_GET['rf_debug_hitos_endpoint_reset']) && $_GET['rf_debug_hitos_endpoint_reset'] == '1') || (isset($_GET['rf_debug_hitos_reset']) && $_GET['rf_debug_hitos_reset'] == '1')) {
        rf_hitos_rank_endpoint_reset($api);
      }
      // Si el discovery está desactivado (Swagger-only), ignora cualquier patrón guardado y usa el de Swagger (esg_m_t)
      $endpoint_pattern = !$rf_discovery_enabled ? 'esg_m_t' : rf_hitos_rank_endpoint_get($api);
      if ($rf_force_pattern !== '') { $endpoint_pattern = $rf_force_pattern; }
      $year = $GLOBALS['rf_hitos_temporada_map']['id_to_year'][$tempId] ?? null;
  $rf_debug_hits['attempts']++;
  $rf_attempts_done++;
  // Log compacto de intento
  $is_2_13 = ((int)$modId === 2 && (string)$tempId === '13');
  $log_line = 'RFHITOS attempting endpoint mod='.$modId.' temp='.$tempId.' pattern='.($rf_force_pattern ?: $endpoint_pattern ?: 'esg_m_t');
  if ($is_2_13) { $log_line .= ' (focus 2/13)'; }
  if ($rf_debug_on) { error_log($log_line); }
      try {
        $path = rf_hitos_rank_build_path($endpoint_pattern ?: 'esg_m_t', $modId, $tempId);
        // Guardar última URL usada para diagnóstico en panel de debug
        $GLOBALS['rf_hitos_last_path'] = isset($path) ? (string)$path : '';
        $rows = rf_call_api($api, $path);
      } catch (\Throwable $e) { 
        if ($rf_debug_on) { error_log('RFHITOS exception mod='.$modId.' temp='.$tempId.' err='.$e->getMessage()); }
        $rows = []; 
      }
      if ($rf_time_exceeded()) { if ($rf_debug_on) { error_log('RFHITOS time-budget exceeded after main call temp='.$tempId.' mod='.$modId); } break 2; }
      if (function_exists('is_wp_error') && is_wp_error($rows)) {
        if ($rf_debug_on) { error_log('RFHITOS ranking error mod=' . $modId . ' temp=' . $tempId . ' msg=' . $rows->get_error_message()); }
        continue;
      }
      if ($rows === null) { // 204 o vacío
        if ($rf_debug_on) { error_log('RFHITOS ranking null (204) mod=' . $modId . ' temp=' . $tempId . ' path=' . $path); }
        // Fallback: probar con año, si mapeado
        if ($year) {
          try { $rows = rf_call_api($api, rf_hitos_rank_build_path($endpoint_pattern ?: 'esg_m_t', $modId, $year)); } catch (\Throwable $e) { $rows = null; }
          if ($rows === null || (function_exists('is_wp_error') && is_wp_error($rows))) { continue; }
        } else {
          continue;
        }
      }
      $items = rf_hitos_unwrap_items($rows);
      $total_items = is_array($items) ? count($items) : 0;
      // Log de respuesta reducido
  if ($rf_debug_on) { error_log('RFHITOS response mod='.$modId.' temp='.$tempId.' total_items='.$total_items.' path='.$path); }
      if ($is_2_13) {
        // Marca específica para ver rápidamente el estado del 2/13
        $raw2 = '';
        try { $raw2 = @json_encode($rows); } catch (\Throwable $e) { $raw2 = ''; }
        if (is_string($raw2)) { $raw2 = preg_replace('/\s+/', ' ', $raw2); if (strlen($raw2) > 160) { $raw2 = substr($raw2, 0, 160) . '…'; } }
  if ($rf_debug_on) { error_log('RFHITOS response_2_13 snip=' . $raw2); }
      }
      if ($total_items === 0 && (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1')) {
        // Loguea claves raíz y algunas claves internas para diagnóstico de estructura
        $rootKeys = [];
        if (is_array($rows)) { $rootKeys = array_slice(array_keys($rows), 0, 8); }
        elseif (is_object($rows)) { $rootKeys = array_slice(array_keys((array)$rows), 0, 8); }
  if ($rf_debug_on) { error_log('RFHITOS response rootKeys='.implode(',', $rootKeys)); }
        // si existe "ranking", mostrar sus claves
        $r = null;
        if (is_array($rows) && isset($rows['ranking'])) $r = $rows['ranking'];
        if (is_object($rows) && isset($rows->ranking)) $r = $rows->ranking;
        if ($r) {
          if (is_object($r)) $r = (array)$r;
          if (is_array($r) && $rf_debug_on) { error_log('RFHITOS response rankingKeys='.implode(',', array_slice(array_keys($r),0,8))); }
        }
        // y un pequeño extracto del JSON bruto
        $raw = '';
        try { $raw = @json_encode($rows); } catch (\Throwable $ee) { $raw = ''; }
        if (is_string($raw)) { $raw = preg_replace('/\s+/', ' ', $raw); if (strlen($raw) > 220) { $raw = substr($raw, 0, 220) . '…'; } }
  if ($raw !== '' && $rf_debug_on) { error_log('RFHITOS response raw_snip=' . $raw); }
      }
      if ($total_items > 0) {
        // Éxito en alguna combinación -> no tiene sentido mantener la marca global de vacío
        rf_hitos_rank_global_skip_reset($api);
      }
      if ($total_items === 0) {
        // Fail-safe: para el endpoint oficial por temporada, fuerza un reintento con auth desde el cliente directamente
        // Evitar reintentos forzados duplicados: rf_call_api ya hace un segundo intento con auth si viene vacío.
        // Dejamos este bloque desactivado en producción para no multiplicar latencias.
        if ($rf_debug_on) {
          try {
            $is_official_per_season = (($endpoint_pattern ?: 'esg_m_t') === 'esg_m_t');
            $can_force = ($api && method_exists($api,'get_ranking_por_modalidad_temporada_esp_g2_official_only'));
            if ($is_official_per_season && $can_force) {
              if ($rf_debug_on) { error_log('RFHITOS season forced-auth retry (debug) url=' . $path); }
              $rowsF = $api->get_ranking_por_modalidad_temporada_esp_g2_official_only($modId, $tempId);
              $itemsF = rf_hitos_unwrap_items($rowsF);
              $countF = is_array($itemsF) ? count($itemsF) : 0;
              if ($countF > 0) { $rows = $rowsF; $items = $itemsF; $total_items = $countF; }
            }
          } catch (\Throwable $e) { /* ignore forced retry errors */ }
        }
        if ($rf_time_exceeded()) { if ($rf_debug_on) { error_log('RFHITOS time-budget exceeded after forced/auth checks temp='.$tempId.' mod='.$modId); } break 2; }
        // intentar con año si lo tenemos
        if ($year) {
          try { $rows = rf_call_api($api, rf_hitos_rank_build_path($endpoint_pattern ?: 'esg_m_t', $modId, $year)); } catch (\Throwable $e) { $rows = null; }
          $items = rf_hitos_unwrap_items($rows);
          $total_items = is_array($items) ? count($items) : 0;
        }
        if ($rf_time_exceeded()) { if ($rf_debug_on) { error_log('RFHITOS time-budget exceeded after year fallback temp='.$tempId.' mod='.$modId); } break 2; }
        // Segundo fallback: si tempId parece ya un ordinal, intentar ord_to_id->id
        $maybe_id = $GLOBALS['rf_hitos_temporada_map']['ord_to_id'][$tempId] ?? null;
        if ($maybe_id && $maybe_id !== $tempId) {
          try { $rows = rf_call_api($api, rf_hitos_rank_build_path($endpoint_pattern ?: 'esg_m_t', $modId, $maybe_id)); } catch (\Throwable $e) { $rows = null; }
          $items = rf_hitos_unwrap_items($rows);
          $total_items = is_array($items) ? count($items) : 0;
        }
        if ($rf_time_exceeded()) { if ($rf_debug_on) { error_log('RFHITOS time-budget exceeded after ord_to_id fallback temp='.$tempId.' mod='.$modId); } break 2; }
        // Descubrimiento de endpoint si seguimos a cero
        if ($total_items === 0) {
          if ($rf_discovery_enabled) {
            $disc = rf_hitos_rank_endpoint_discover($api, $modId, $tempId, $year);
            if ($disc !== '') {
              if ($rf_debug_on) { error_log('RFHITOS discovered per-season endpoint pattern=' . $disc); }
              if (!$rf_bypass_cache) { rf_hitos_rank_endpoint_set($api, $disc); }
              try { $rows = rf_call_api($api, rf_hitos_rank_build_path($disc, $modId, $tempId)); } catch (\Throwable $e) { $rows = null; }
              $items = rf_hitos_unwrap_items($rows);
              $total_items = is_array($items) ? count($items) : 0;
              if ($total_items === 0 && $year) {
                try { $rows = rf_call_api($api, rf_hitos_rank_build_path($disc, $modId, $year)); } catch (\Throwable $e) { $rows = null; }
                $items = rf_hitos_unwrap_items($rows);
                $total_items = is_array($items) ? count($items) : 0;
              }
            }
            if ($rf_time_exceeded()) { if ($rf_debug_on) { error_log('RFHITOS time-budget exceeded after discovery temp='.$tempId.' mod='.$modId); } break 2; }
          } // si discovery no está habilitado, no probamos patrones alternativos
        }
  if ($total_items === 0) { if ($rf_debug_on) { error_log('RFHITOS items=0 mod=' . $modId . ' temp=' . $tempId); } if(!$rf_bypass_cache && !$rf_host_auth_forced){ rf_hitos_rank_empty_mark($api, $modId, $tempId); } continue; }
      }
      // Log keys of first item and top-3 jugadorIds for diagnosis
      if ($total_items > 0) {
        $first = is_array($items[0]) ? (array)$items[0] : (array)$items[0];
  if ($rf_debug_on) { error_log('RFHITOS first keys mod=' . $modId . ' temp=' . $tempId . ' keys=' . implode(',', array_slice(array_keys($first),0,15))); }
        $top3 = [];
        $max = min(3, $total_items);
        for ($i=0; $i<$max; $i++){
          $top3[] = rf_hitos_extract_jid($items[$i]);
        }
  if ($rf_debug_on) { error_log('RFHITOS top3 jids mod=' . $modId . ' temp=' . $tempId . ' = [' . implode(',', $top3) . ']'); }
      }
  if ($rf_debug_on) { error_log('RFHITOS items mod=' . $modId . ' temp=' . $tempId . ' count=' . $total_items); }

      // Filtra a españoles si existe un campo de país/nacionalidad, si no, usa tal cual (el endpoint debería ser ESP)
      $spanish = [];
      foreach ($items as $r){
        $o = (object)$r;
        $pais = null;
        foreach (['pais','Pais','country','Country','nacionalidad','Nacionalidad'] as $pk){ if(isset($o->{$pk})) { $pais = (string)$o->{$pk}; break; } }
        $is_es = true; // por defecto true
        if ($pais !== null) {
          $pp = strtoupper(trim($pais));
          $is_es = in_array($pp, ['ES','ESPANA','ESPAÑA','SPAIN','ES-ES'], true);
        }
        // flags booleanas típicas
        foreach(['esEspanol','EsEspanol','esNacional','EsNacional'] as $fk){ if(isset($o->{$fk})) { $is_es = !!$o->{$fk}; break; } }
        if ($is_es) { $spanish[] = $o; }
      }
      if (count($spanish) !== $total_items) {
  if ($rf_debug_on) { error_log('RFHITOS filtered ES mod=' . $modId . ' temp=' . $tempId . ' esCount=' . count($spanish)); }
      }
      if (empty($spanish)) {
  if ($rf_debug_on) { error_log('RFHITOS ES filter empty -> using raw items mod=' . $modId . ' temp=' . $tempId); }
        $spanish = array_map(function($x){ return (object)$x; }, $items);
      }

      // Debug específico para jugador 6
      $player6_debug = [];
      foreach ($spanish as $idx => $o) {
        $jid = rf_hitos_extract_jid($o);
        if ($jid == $player_id) {
          $player6_debug[] = 'pos='.($idx+1).' jid='.$jid;
        }
      }
      if (!empty($player6_debug)) {
  if ($rf_debug_on) { error_log('RFHITOS player6 found mod=' . $modId . ' temp=' . $tempId . ' -> ' . implode(', ', $player6_debug)); }
      }

      // Determinar podio con regla por temporada:
  // - A partir de la temporada 11 (ordinal >= 11): ignorar 'posicion' y usar ESTRICTAMENTE el orden del array (tras filtrar ES)
  // - Temporadas < 11: preferir 'posicion' si viene; si no, caer al orden del array
      $season_ord = rf_hitos_norm_temp_label($tempId);
      if (is_null($season_ord)) $season_ord = intval($tempId);
  $prefer_array_order = ($season_ord >= 11);

      $found_pos = null; // posición oficial si viene en el dato
      $found_idx = null; // índice 1..N en el array filtrado (fallback)
      $idx = 0;
      foreach ($spanish as $o){
        $idx++;
        $jid = rf_hitos_extract_jid($o);
        if ($jid === intval($player_id)) {
          if (isset($o->posicion) && is_numeric($o->posicion)) { $found_pos = intval($o->posicion); }
          $found_idx = $idx; // por si no hay 'posicion'
          break;
        }
      }

      $use_pos = null;
      if ($prefer_array_order) {
        // Desde T11: usar orden del array únicamente
        if ($found_idx !== null && $found_idx >= 1 && $found_idx <= 3) { $use_pos = $found_idx; }
      } else {
        // Antes de T11: usar 'posicion' si 1..3; si no, caer a índice
        if ($found_pos !== null && $found_pos >= 1 && $found_pos <= 3) { $use_pos = $found_pos; }
        elseif ($found_idx !== null && $found_idx >= 1 && $found_idx <= 3) { $use_pos = $found_idx; }
      }

      if ($use_pos !== null) {
        if ($use_pos === 1) {
          ${"temps_no1_{$suf}"}[] = $season_ord; $rf_debug_hits['hits'][] = ['mod'=>$modId,'temp'=>$tempId,'pos'=>1,'ord'=>$season_ord];
        } elseif ($use_pos === 2) {
          ${"temps_no2_{$suf}"}[] = $season_ord; $rf_debug_hits['hits'][] = ['mod'=>$modId,'temp'=>$tempId,'pos'=>2,'ord'=>$season_ord];
        } elseif ($use_pos === 3) {
          ${"temps_no3_{$suf}"}[] = $season_ord; $rf_debug_hits['hits'][] = ['mod'=>$modId,'temp'=>$tempId,'pos'=>3,'ord'=>$season_ord];
        }
        $via = $prefer_array_order ? 'array (T>=11)' : ((isset($o->posicion) && $found_pos !== null && $use_pos === $found_pos) ? 'posicion' : 'array');
  if ($rf_debug_on) { error_log('RFHITOS hit player=' . $player_id . ' mod=' . $modId . ' temp=' . $tempId . ' pos=' . $use_pos . ' (via ' . $via . ')'); }
      } else {
        if (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') {
          if ($rf_debug_on) { error_log('RFHITOS no podium decision player=' . $player_id . ' mod=' . $modId . ' temp=' . $tempId . ' found_pos=' . var_export($found_pos, true) . ' found_idx=' . var_export($found_idx, true) . ' prefer_array_order=' . ($prefer_array_order ? '1' : '0')); }
        }
      }
    }
  }
  // normaliza
  foreach (['temps_no1_d','temps_no1_i','temps_no2_d','temps_no2_i','temps_no3_d','temps_no3_i'] as $k){
    $debug_payload = ['player'=>$player_id,'no1_d'=>$temps_no1_d,'no1_i'=>$temps_no1_i,'no2_d'=>$temps_no2_d,'no2_i'=>$temps_no2_i,'no3_d'=>$temps_no3_d,'no3_i'=>$temps_no3_i,'champ_d'=>$temps_campeon_d,'champ_i'=>$temps_campeon_i];
  if ($rf_debug_on) { error_log('RFHITOS totals ' . json_encode($debug_payload)); }
    $v = array_values(array_unique(array_filter($$k))); sort($v); $$k=$v;
  }
}

// Fallback: si no hay ningún nº1/2/3, intenta sin filtrar por ES y usando índice 0/1/2 del array
// Evitar fallback si seguimos en modo skip global y, por defecto, en modo swagger-only para no salirse del contrato
if (empty($temps_no1_d) && empty($temps_no1_i) && empty($temps_no2_d) && empty($temps_no2_i) && empty($temps_no3_d) && empty($temps_no3_i)) {
  $rf_skip_guard = isset($rf_should_skip) ? (bool)$rf_should_skip : false;
  // Detecta si estamos en modo swagger-only (descubrimiento desactivado por defecto)
  $rf_discovery_enabled = (
    (isset($_GET['rf_debug_hitos_discover']) && $_GET['rf_debug_hitos_discover'] == '1') ||
    (defined('RF_HITOS_ENABLE_DISCOVERY') && constant('RF_HITOS_ENABLE_DISCOVERY') === true)
  );
  $rf_swagger_only = !$rf_discovery_enabled;
  $rf_allow_raw_fallback = (isset($_GET['rf_debug_hitos_allow_raw_fallback']) && $_GET['rf_debug_hitos_allow_raw_fallback'] == '1');
  if ($rf_swagger_only && !$rf_allow_raw_fallback) {
    if (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') {
  if ($rf_debug_on) { error_log('RFHITOS raw-fallback skipped (swagger-only mode)'); }
    }
  } elseif (!$rf_skip_guard) {
  if ($api && $player_id) {
    $found_any = false;
    foreach ($rf_hitos_temporada_ids as $tempId) {
      // Respetar overrides de debug si existen
      if (isset($rf_only_temp) && $rf_only_temp !== '' && (string)$tempId !== (string)$rf_only_temp) { continue; }
      foreach ([1=>'i',2=>'d'] as $modId=>$suf) {
        if (isset($rf_only_mod) && $rf_only_mod && (int)$rf_only_mod !== (int)$modId) { continue; }
        $endpoint_pattern = rf_hitos_rank_endpoint_get($api) ?: 'esg_m_t';
        try { $rows = rf_call_api($api, rf_hitos_rank_build_path($endpoint_pattern, $modId, $tempId)); } catch (\Throwable $e) { $rows = null; }
        if ($rows === null || (function_exists('is_wp_error') && is_wp_error($rows))) {
          $year = $GLOBALS['rf_hitos_temporada_map']['id_to_year'][$tempId] ?? null;
          if ($year) { try { $rows = rf_call_api($api, rf_hitos_rank_build_path($endpoint_pattern, $modId, $year)); } catch (\Throwable $e) { $rows = null; } }
        }
        $items = rf_hitos_unwrap_items($rows);
        if (empty($items)) continue;
        $max = min(3, count($items));
        $season_ord = rf_hitos_norm_temp_label($tempId); if (is_null($season_ord)) $season_ord = intval($tempId);
        for ($i=0; $i<$max; $i++){
          $jid = rf_hitos_extract_jid($items[$i]);
          if ($jid === intval($player_id)) {
            if ($i===0) ${"temps_no1_{$suf}"}[] = $season_ord;
            elseif ($i===1) ${"temps_no2_{$suf}"}[] = $season_ord;
            elseif ($i===2) ${"temps_no3_{$suf}"}[] = $season_ord;
            $found_any = true;
            if ($rf_debug_on) { error_log('RFHITOS fallback raw-order hit player=' . $player_id . ' mod=' . $modId . ' temp=' . $tempId . ' pos=' . ($i+1)); }
            break;
          }
        }
      }
    }
    if ($found_any) {
      foreach (['temps_no1_d','temps_no1_i','temps_no2_d','temps_no2_i','temps_no3_d','temps_no3_i'] as $k){ $v = array_values(array_unique(array_filter($$k))); sort($v); $$k=$v; }
  if ($rf_debug_on) { error_log('RFHITOS fallback raw-order used'); }
      $rf_debug_hits['fallback'] = 'raw-order';
    }
  }
  if (empty($temps_no1_d) && empty($temps_no1_i) && empty($temps_no2_d) && empty($temps_no2_i) && empty($temps_no3_d) && empty($temps_no3_i)) {
    if(!$rf_bypass_cache){ rf_hitos_rank_global_skip_mark($api); }
  }
  } // end guard for skip global
} // end fallback wrapper
} // end if ($api && $player_id)

  // Armonización con el encabezado: completar Nº1/2/3 que falten usando las mismas fuentes que el header
  // (processor->hitos y/o servicio central). Esto SOLO afecta a la pestaña Hitos; el encabezado no se toca.
  (function() use (&$temps_no1_d,&$temps_no1_i,&$temps_no2_d,&$temps_no2_i,&$temps_no3_d,&$temps_no3_i,$processor,$player_id){
    $rf_debug_on = (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1');
    $filled = ['no1_d'=>false,'no1_i'=>false,'no2_d'=>false,'no2_i'=>false,'no3_d'=>false,'no3_i'=>false];
    $norm = function($arr){ $out=[]; foreach((array)$arr as $v){ $n = rf_hitos_norm_temp_label($v); if(!is_null($n)) $out[]=$n; } $out = array_values(array_unique(array_filter($out))); sort($out); return $out; };

    // 1) Intentar con los datos ya calculados en el processor (mismo origen que el encabezado)
    if (isset($processor) && is_object($processor) && isset($processor->hitos) && is_array($processor->hitos)) {
      $h = $processor->hitos;
      if (empty($temps_no1_d)) { $cand = $norm($h['numero1_temporada_open_dobles_anios'] ?? []); if (!empty($cand)) { $temps_no1_d = $cand; $filled['no1_d']=true; } }
      if (empty($temps_no1_i)) { $cand = $norm($h['numero1_temporada_open_individual_anios'] ?? []); if (!empty($cand)) { $temps_no1_i = $cand; $filled['no1_i']=true; } }
      if (empty($temps_no2_d)) { $cand = $norm($h['numero2_temporada_open_dobles_anios'] ?? []); if (!empty($cand)) { $temps_no2_d = $cand; $filled['no2_d']=true; } }
      if (empty($temps_no2_i)) { $cand = $norm($h['numero2_temporada_open_individual_anios'] ?? []); if (!empty($cand)) { $temps_no2_i = $cand; $filled['no2_i']=true; } }
      if (empty($temps_no3_d)) { $cand = $norm($h['numero3_temporada_open_dobles_anios'] ?? []); if (!empty($cand)) { $temps_no3_d = $cand; $filled['no3_d']=true; } }
      if (empty($temps_no3_i)) { $cand = $norm($h['numero3_temporada_open_individual_anios'] ?? []); if (!empty($cand)) { $temps_no3_i = $cand; $filled['no3_i']=true; } }
    }

    // 2) Completar lo que siga faltando con el servicio central (mismo utilizado por el encabezado)
    $need_any = (empty($temps_no1_d) || empty($temps_no1_i) || empty($temps_no2_d) || empty($temps_no2_i) || empty($temps_no3_d) || empty($temps_no3_i));
    if ($need_any && class_exists('Futbolin_Rankgen_Service') && isset($player_id) && $player_id) {
      try {
        $podium = \Futbolin_Rankgen_Service::get_player_podium_years((string)$player_id);
        if (is_array($podium)) {
          if (empty($temps_no1_d)) { $cand = $norm($podium['dobles']['no1'] ?? []); if (!empty($cand)) { $temps_no1_d = $cand; $filled['no1_d']=true; } }
          if (empty($temps_no1_i)) { $cand = $norm($podium['individual']['no1'] ?? []); if (!empty($cand)) { $temps_no1_i = $cand; $filled['no1_i']=true; } }
          if (empty($temps_no2_d)) { $cand = $norm($podium['dobles']['no2'] ?? []); if (!empty($cand)) { $temps_no2_d = $cand; $filled['no2_d']=true; } }
          if (empty($temps_no2_i)) { $cand = $norm($podium['individual']['no2'] ?? []); if (!empty($cand)) { $temps_no2_i = $cand; $filled['no2_i']=true; } }
          if (empty($temps_no3_d)) { $cand = $norm($podium['dobles']['no3'] ?? []); if (!empty($cand)) { $temps_no3_d = $cand; $filled['no3_d']=true; } }
          if (empty($temps_no3_i)) { $cand = $norm($podium['individual']['no3'] ?? []); if (!empty($cand)) { $temps_no3_i = $cand; $filled['no3_i']=true; } }
        }
      } catch (\Throwable $e) { /* silencioso */ }
    }

    if ($rf_debug_on) {
      $tags = [];
      foreach ($filled as $k=>$v) { if ($v) { $tags[] = $k; } }
      if (!empty($tags)) { error_log('RFHITOS header-harmonization applied (tab-only): filled ' . implode(',', $tags)); }
    }
  })();

  // Fallback local (desactivado por defecto): solo se activa si rf_debug_hitos_use_fallback=1 o RF_HITOS_ALLOW_LOCAL_FALLBACK=true
  $rf_hitos_used_local_fallback = false;
  $rf_hitos_missing_local_fallback = false;
  $rf_hitos_allow_local_fallback = (
    (isset($_GET['rf_debug_hitos_use_fallback']) && $_GET['rf_debug_hitos_use_fallback'] == '1') ||
    (defined('RF_HITOS_ALLOW_LOCAL_FALLBACK') && constant('RF_HITOS_ALLOW_LOCAL_FALLBACK') === true)
  );
  if (!$rf_hitos_allow_local_fallback) {
    if (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') {
  if ($rf_debug_on) { error_log('RFHITOS local fallback disabled (enable with rf_debug_hitos_use_fallback=1 or define RF_HITOS_ALLOW_LOCAL_FALLBACK=true)'); }
    }
  }
  if ($rf_hitos_allow_local_fallback && empty($temps_no1_d) && empty($temps_no1_i) && empty($temps_no2_d) && empty($temps_no2_i) && empty($temps_no3_d) && empty($temps_no3_i)) {
    // Ruta del archivo de fallback (no versionado por defecto; proveemos un .example)
    $fallback_path = defined('FUTBOLIN_API_PATH')
      ? rtrim(FUTBOLIN_API_PATH, '\\/') . '/DOCUMENTACION/podiums_per_season.fallback.json'
      : __DIR__ . '/../../DOCUMENTACION/podiums_per_season.fallback.json';
    if (file_exists($fallback_path) && is_readable($fallback_path)) {
      $json = file_get_contents($fallback_path);
      $data = json_decode($json, true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        // Estructura esperada:
        // {
        //   "i": { "<temporadaOrd>": [jugadorId1, jugadorId2, jugadorId3], ... },
        //   "d": { "<temporadaOrd>": [jugadorId1, jugadorId2, jugadorId3], ... }
        // }
        $pid = intval($player_id);
        foreach ([ 'i' => 'individual', 'd' => 'dobles' ] as $suf => $label) {
          $by_season = isset($data[$suf]) && is_array($data[$suf]) ? $data[$suf] : [];
          foreach ($by_season as $season_key => $podium) {
            if (!is_array($podium)) continue;
            $season_ord = rf_hitos_norm_temp_label($season_key);
            if (is_null($season_ord)) { $season_ord = intval($season_key); }
            $jid1 = isset($podium[0]) && is_numeric($podium[0]) ? intval($podium[0]) : 0;
            $jid2 = isset($podium[1]) && is_numeric($podium[1]) ? intval($podium[1]) : 0;
            $jid3 = isset($podium[2]) && is_numeric($podium[2]) ? intval($podium[2]) : 0;
            if ($pid && $pid === $jid1) { ${"temps_no1_{$suf}"}[] = $season_ord; }
            if ($pid && $pid === $jid2) { ${"temps_no2_{$suf}"}[] = $season_ord; }
            if ($pid && $pid === $jid3) { ${"temps_no3_{$suf}"}[] = $season_ord; }
          }
        }
        foreach (['temps_no1_d','temps_no1_i','temps_no2_d','temps_no2_i','temps_no3_d','temps_no3_i'] as $k){ $v = array_values(array_unique(array_filter($$k))); sort($v); $$k=$v; }
        if (!empty($temps_no1_d) || !empty($temps_no1_i) || !empty($temps_no2_d) || !empty($temps_no2_i) || !empty($temps_no3_d) || !empty($temps_no3_i)) {
          $rf_hitos_used_local_fallback = true;
          if ($rf_debug_on) { error_log('RFHITOS used local fallback podiums_per_season.fallback.json'); }
        }
      } else {
  if ($rf_debug_on) { error_log('RFHITOS local fallback JSON decode error: ' . json_last_error_msg()); }
      }
    } else {
  if ($rf_debug_on) { error_log('RFHITOS local fallback file not found/readable at ' . $fallback_path); }
      $rf_hitos_missing_local_fallback = true;
    }
  }

  // Pase final: normalizar y recortar por ordinal máximo SOLO para nº1/2/3
  // Para campeonatos usamos año textual y NO recortamos por ordinal (mostrar p.ej. 2021 aunque su id sea 16).
  $rf_max_ord_final = isset($__rf_max_season_ord) ? (int)$__rf_max_season_ord : 14;
  $rf_clamp_ords = function($arr) use ($rf_max_ord_final) {
    $out = [];
    foreach ((array)$arr as $v) {
      $n = rf_hitos_norm_temp_label($v);
      if (is_null($n)) { $n = is_numeric($v) ? intval($v) : 0; }
      if ($n > 0 && $n <= $rf_max_ord_final) { $out[] = $n; }
    }
    $out = array_values(array_unique(array_filter($out)));
    sort($out);
    return $out;
  };
  $temps_no1_d = $rf_clamp_ords($temps_no1_d);
  $temps_no1_i = $rf_clamp_ords($temps_no1_i);
  $temps_no2_d = $rf_clamp_ords($temps_no2_d);
  $temps_no2_i = $rf_clamp_ords($temps_no2_i);
  $temps_no3_d = $rf_clamp_ords($temps_no3_d);
  $temps_no3_i = $rf_clamp_ords($temps_no3_i);

/** ==== Cards ==== */
$cards = [
  [ 'group' => 'championships', 'tone' => 'gold',   'title' => 'Campeón de España (Dobles)',    'temps' => $temps_campeon_d, 'icon' => 'star', 'doubleIcon' => true ],
  [ 'group' => 'championships', 'tone' => 'gold',   'title' => 'Campeón de España (Individual)', 'temps' => $temps_campeon_i, 'icon' => 'star', 'doubleIcon' => false ],
  [ 'group' => 'number1',       'tone' => 'gold',   'title' => 'Nº1 del Ranking por Temporada (Dobles)',     'temps' => $temps_no1_d, 'icon' => 'star',  'doubleIcon' => true ],
  [ 'group' => 'number1',       'tone' => 'gold',   'title' => 'Nº1 del Ranking por Temporada (Individual)', 'temps' => $temps_no1_i, 'icon' => 'star',  'doubleIcon' => false ],
  [ 'group' => 'number23',      'tone' => 'silver', 'title' => 'Nº2 del Ranking por Temporada (Dobles)',     'temps' => $temps_no2_d, 'icon' => 'star',  'doubleIcon' => true ],
  [ 'group' => 'number23',      'tone' => 'silver', 'title' => 'Nº2 del Ranking por Temporada (Individual)', 'temps' => $temps_no2_i, 'icon' => 'star',  'doubleIcon' => false ],
  [ 'group' => 'number23',      'tone' => 'bronze', 'title' => 'Nº3 del Ranking por Temporada (Dobles)',     'temps' => $temps_no3_d, 'icon' => 'star',  'doubleIcon' => true ],
  [ 'group' => 'number23',      'tone' => 'bronze', 'title' => 'Nº3 del Ranking por Temporada (Individual)', 'temps' => $temps_no3_i, 'icon' => 'star',  'doubleIcon' => false ],
];

$group_titles = [
  'championships' => ['label' => 'Campeonatos de España', 'heading_id' => 'rf-hitos-title'],
  'number1'       => ['label' => 'Años como número 1',    'heading_id' => ''],
  'number23'      => ['label' => 'Años como número 2 o 3','heading_id' => ''],
];

$grouped_cards = [];
foreach ($cards as $card_item) {
  $key = $card_item['group'];
  if (!isset($grouped_cards[$key])) {
    $grouped_cards[$key] = [];
  }
  $grouped_cards[$key][] = $card_item;
}
?>
<div class="futbolin-card">
  <h3 id="rf-hitos-heading" class="history-main-title">Hitos</h3>
<section class="rf-hitos" aria-labelledby="rf-hitos-heading">
<?php
  // Aviso visual cuando la cache global está activa (ocúltalo al público; muéstralo solo en debug o admin)
  $rf_global_skip_active = $api ? rf_hitos_rank_global_skip_is_marked($api) : false;
  $rf_debug_on = (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos']=='1');
  if ($rf_global_skip_active) : ?>
  <div style="padding:10px;margin:0 0 12px;border:1px dashed #caa;background:#fffaf1;border-radius:8px;font-size:12px;color:#7a5b00;">
    <strong>Nota</strong>: El ranking por temporada no está disponible temporalmente. Mostramos los datos disponibles y reintentaremos más tarde.
    <?php if ($rf_debug_on): ?><div style="margin-top:6px;">Debug: cache global activa. Añade <code>&rf_debug_hitos_reset=1</code> para limpiar el flag y reintentar ahora.</div><?php endif; ?>
  </div>
<?php endif; ?>
<?php if (isset($rf_bypass_cache) && $rf_bypass_cache): ?>
  <div style="padding:10px;margin:0 0 12px;border:1px dashed #93c5fd;background:#eff6ff;border-radius:8px;font-size:12px;color:#1e3a8a;">
    Debug: bypass de cache activado. No se consultarán/almacenarán marcas de cache negativa en esta carga.
  </div>
<?php endif; ?>
<?php if (!empty($rf_hitos_used_local_fallback)): ?>
  <div style="padding:10px;margin:0 0 12px;border:1px dashed #16a34a;background:#ecfdf5;border-radius:8px;font-size:12px;color:#065f46;">
    Modo fallback: los años como nº1/2/3 se han cargado desde un archivo local. Actualiza <code>DOCUMENTACION/podiums_per_season.fallback.json</code> para modificarlo.
  </div>
<?php endif; ?>
<?php if (!$rf_hitos_allow_local_fallback && isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1'): ?>
  <div style="padding:10px;margin:0 0 12px;border:1px dashed #bbb;background:#fff;border-radius:8px;font-size:12px;color:#333;">
    Fallback local desactivado por defecto. Para activarlo temporalmente añade <code>&rf_debug_hitos_use_fallback=1</code> a la URL o define <code>RF_HITOS_ALLOW_LOCAL_FALLBACK</code> en wp-config.php.
  </div>
<?php endif; ?>
<?php if (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1'): ?>
  <div style="padding:10px;margin:0 0 12px;border:1px dashed #bbb;background:#f8fafc;border-radius:8px;font-size:12px;color:#333;">
    <strong>RFHITOS DEBUG</strong>
    <div>player_id=<?php echo (int)$player_id; ?> | temporadas=<?php echo count($rf_hitos_temporada_ids); ?> | attempts=<?php echo (int)$rf_debug_hits['attempts']; ?></div>
    <?php 
      $rf_discovery_enabled = (
        (isset($_GET['rf_debug_hitos_discover']) && $_GET['rf_debug_hitos_discover'] == '1') ||
        (defined('RF_HITOS_ENABLE_DISCOVERY') && constant('RF_HITOS_ENABLE_DISCOVERY') === true)
      );
      $mode = $rf_discovery_enabled ? 'discovery-enabled' : 'swagger-only';
      $lastPath = isset($GLOBALS['rf_hitos_last_path']) ? (string)$GLOBALS['rf_hitos_last_path'] : '';
    ?>
    <div>mode=<?php echo esc_html($mode); ?><?php if($lastPath!==''){ echo ' | last_path=' . esc_html($lastPath); } ?></div>
    <?php if (!empty($rf_force_pattern) || !empty($rf_only_temp) || !empty($rf_only_mod)): ?>
      <div>overrides:<?php if(!empty($rf_force_pattern)) echo ' pattern='.esc_html($rf_force_pattern); ?><?php if(!empty($rf_only_temp)) echo ' only_temp='.esc_html($rf_only_temp); ?><?php if(!empty($rf_only_mod)) echo ' only_mod='.esc_html($rf_only_mod); ?></div>
    <?php endif; ?>
    <div>no1_d=<?php echo count($temps_no1_d); ?>; no1_i=<?php echo count($temps_no1_i); ?>; no2_d=<?php echo count($temps_no2_d); ?>; no2_i=<?php echo count($temps_no2_i); ?>; no3_d=<?php echo count($temps_no3_d); ?>; no3_i=<?php echo count($temps_no3_i); ?></div>
    <div>hits sample: <?php echo esc_html(substr(json_encode(array_slice($rf_debug_hits['hits'],0,6)),0,300)); ?></div>
    <?php if (!empty($rf_hitos_missing_local_fallback)): ?>
      <div style="margin-top:6px;font-size:12px;">Sugerencia: puedes crear <code>DOCUMENTACION/podiums_per_season.fallback.json</code> a partir de <code>DOCUMENTACION/podiums_per_season.fallback.json.example</code> para forzar los podios localmente mientras la API de ranking requiera autenticación.</div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php foreach ($group_titles as $group_key => $meta):
    $cards_for_group = isset($grouped_cards[$group_key]) ? $grouped_cards[$group_key] : [];
    if (empty($cards_for_group)) { continue; }
    $heading_id = !empty($meta['heading_id']) ? (string)$meta['heading_id'] : '';
?>
  <div class="rf-hitos__section">
    <h2<?php if ($heading_id !== '') { echo ' id="' . esc_attr($heading_id) . '"'; } ?> class="rf-hitos__title" aria-hidden="true"><?php echo esc_html($meta['label']); ?></h2>
    <div class="rf-hitos__grid">
<?php foreach ($cards_for_group as $card): ?>
  <?php
    $tone = $card['tone'];
    $title = $card['title'];
    $temps = (array) $card['temps'];
  $icon = $card['icon'];
  // Forzar coronas en grupos number1 y number23 (años como nº1/2/3)
  if ($group_key === 'number1' || $group_key === 'number23') { $icon = 'crown'; }
    $double = !empty($card['doubleIcon']);
    $count = count($temps);
  ?>
  <?php $is_empty = empty($temps); ?>
  <article class="rf-card tone-<?php echo esc_attr($tone); ?> group-<?php echo esc_attr($group_key); ?><?php echo $is_empty ? ' is-empty' : ''; ?>" aria-label="<?php echo esc_attr($title); ?>">
    <div class="rf-card__frame">
      <h3 class="rf-card__title">
        <?php if ($count > 0): ?>
          <span class="rf-card__title-count">
            <span class="rf-card__title-count-number"><?php echo esc_html($count); ?></span>
              <span class="rf-card__title-count-symbol" aria-hidden="true">&times;</span>
            <span class="rf-sr-only">
  <?php
    if (function_exists('_n')) {
      echo esc_html(sprintf(_n('%d hito','%d hitos',$count,'futbolin'), $count));
    } else {
      echo esc_html($count . ' hito' . ($count === 1 ? '' : 's'));
    }
  ?>
</span>
          </span>
        <?php endif; ?>
        <span class="rf-card__title-text"><?php echo esc_html($title); ?></span>
      </h3>
      <?php if (!empty($temps)): ?>
        <ul class="rf-badges" role="list">
          <?php foreach ($temps as $t): ?>
            <?php
              $primary_icon = ($icon === 'crown') ? rf_hitos_svg_crown($tone, 26) : rf_hitos_svg_star_big($tone, 28);
              $secondary_icon = '';
              if ($double) {
                $secondary_icon = ($icon === 'crown') ? rf_hitos_svg_crown($tone, 26) : rf_hitos_svg_star_big($tone, 28);
              }
              // Para campeonatos (grupo 'championships') usamos año textual; para number1/23 mostramos 'Temporada N'
              $is_champ = ($group_key === 'championships');
              $label_text = $is_champ ? (is_numeric($t) ? (string)intval($t) : (string)$t) : ('Temporada ' . (string)rf_hitos_norm_temp_label($t));
              $badge_title = $is_champ ? ('Campeón de España ' . $label_text) : $title . ' ' . $label_text;
            ?>
            <li class="rf-badge<?php echo $double ? ' rf-badge--double' : ''; ?>" role="listitem" title="<?php echo esc_attr($badge_title); ?>" aria-label="<?php echo esc_attr($badge_title); ?>">
              <span class="rf-badge__icon" aria-hidden="true">
                <span class="rf-badge__icon-asset rf-badge__icon-asset--primary"><?php echo $primary_icon; ?></span>
                <?php if ($double && $secondary_icon): ?>
                  <span class="rf-badge__icon-asset rf-badge__icon-asset--secondary"><?php echo $secondary_icon; ?></span>
                <?php endif; ?>
              </span>
              <span class="rf-badge__text"><?php echo esc_html($label_text); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
          <div class="rf-sticker rf-sticker--locked" aria-hidden="true">
            <span class="rf-tape" aria-hidden="true"></span>
            <span class="rf-corner" aria-hidden="true"></span>
            <span class="rf-sticker__icon"><?php echo rf_hitos_svg_lock('locked', 56); ?></span>
            <span class="rf-sticker__text">Pendiente de conseguir</span>
          </div>
          <p class="rf-empty">El jugador no tiene hitos de esta categoría</p>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</section>
</div>
  

