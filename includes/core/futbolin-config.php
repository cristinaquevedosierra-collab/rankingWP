<?php
if (!defined('ABSPATH')) exit;

// Editor/CLI guards: stubs mínimos cuando WordPress no está cargado
if (!function_exists('get_option')) {
    function get_option($option, $default = false) { return $default; }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}

/**
 * Retorna configuración de API y su procedencia.
 * Cadena de resolución (común a cliente y diagnóstico):
 *   1) option: ranking_api_config (base_url, username, password)
 *   2) filter: futbolin_api_config (puede completar los vacíos)
 *   3) constants legacy: FUTBOLIN_API_USER / FUTBOLIN_API_PASS / FUTBOLIN_FALLBACK_BASE_URL
 *   4) fallback final: meta.baseUrl de BUENO_master.json dentro del plugin (solo para base_url)
 */
function futbolin_get_api_config() {
    $src = ['baseurl_source'=>null, 'user_source'=>null, 'pass_source'=>null];
    $base_url = ''; $username=''; $password='';

    // 1) opción nueva
    $opt = get_option('ranking_api_config', []);
    if (is_array($opt)) {
        if (!empty($opt['base_url'])) { $base_url = $opt['base_url']; $src['baseurl_source'] = 'option_new'; }
        if (!empty($opt['username']))  { $username  = $opt['username'];  $src['user_source']   = 'option_new'; }
        if (!empty($opt['password']))  { $password  = $opt['password'];  $src['pass_source']   = 'option_new'; }
    }

    // 2) opción legacy
    if (empty($base_url)) {
        $legacy = get_option('mi_plugin_futbolin_options', []);
        if (is_array($legacy) && !empty($legacy['api_base_url'])) {
            $base_url = $legacy['api_base_url']; $src['baseurl_source'] = 'option_legacy';
        }
    }
    if (empty($username) || empty($password)) {
        $legacy = isset($legacy) ? $legacy : get_option('mi_plugin_futbolin_options', []);
        if (is_array($legacy)) {
            if (empty($username) && !empty($legacy['api_user'])) $username = $legacy['api_user'];
            if (empty($password) && !empty($legacy['api_pass'])) $password = $legacy['api_pass'];
            if (!empty($legacy['api_user']) || !empty($legacy['api_pass'])) {
                if (empty($src['user_source']) && !empty($legacy['api_user'])) $src['user_source'] = 'option_legacy';
                if (empty($src['pass_source']) && !empty($legacy['api_pass'])) $src['pass_source'] = 'option_legacy';
            }
        }
    }

    // 3) filtro para base_url y/o credenciales
    $flt = apply_filters('futbolin_api_config', []);
    if (is_array($flt)) {
        if (empty($base_url) && !empty($flt['base_url'])) { $base_url = $flt['base_url']; $src['baseurl_source'] = 'filter'; }
        if (empty($username) && !empty($flt['username']))  { $username  = $flt['username'];  $src['user_source']   = 'filter'; }
        if (empty($password) && !empty($flt['password']))  { $password  = $flt['password'];  $src['pass_source']   = 'filter'; }
    }

    // 4) constantes
    if (empty($base_url) && defined('FUTBOLIN_API_BASE_URL')) { $base_url = constant('FUTBOLIN_API_BASE_URL'); $src['baseurl_source'] = 'constant'; }
    if (empty($username) && defined('FUTBOLIN_API_USER'))     { $username = constant('FUTBOLIN_API_USER');     $src['user_source']    = 'constant'; }
    if (empty($password) && defined('FUTBOLIN_API_PASS'))     { $password = constant('FUTBOLIN_API_PASS');     $src['pass_source']    = 'constant'; }

    // 5) fallback: BUENO_master.json (baseUrl)
    if (empty($base_url)) {
        $cands = array(
            dirname(__FILE__, 3) . '/BUENO_master.json',
            dirname(__FILE__, 2) . '/BUENO_master.json',
            dirname(__FILE__)    . '/../BUENO_master.json',
        );
        foreach ($cands as $p) {
            if (is_readable($p)) {
                $json = json_decode(@file_get_contents($p), true);
                if (is_array($json) && !empty($json['meta']['baseUrl']) && is_string($json['meta']['baseUrl'])) {
                    $base_url = $json['meta']['baseUrl']; $src['baseurl_source'] = 'master_json'; break;
                }
            }
        }
    }

    // 6) normalizar fuentes nulas
    foreach (['baseurl_source','user_source','pass_source'] as $k) {
        if (empty($src[$k])) $src[$k] = 'none';
    }

    return ['base_url'=>$base_url, 'username'=>$username, 'password'=>$password, 'sources'=>$src];
}