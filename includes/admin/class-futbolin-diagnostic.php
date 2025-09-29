<?php
if (!defined('ABSPATH')) exit;
// Stubs/guards para análisis fuera de WP (no afectan runtime WordPress)
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($file){ return dirname($file) . DIRECTORY_SEPARATOR; } }
if (!function_exists('add_management_page')) { function add_management_page($pt,$mt,$cap,$slug,$cb){ return ''; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($data,$opts=0){ return json_encode($data,$opts); } }
if (!function_exists('wp_remote_get')) { function wp_remote_get($url,$args=array()){ return new WP_Error('http_unavailable','HTTP API stub'); } }
if (!function_exists('wp_remote_post')) { function wp_remote_post($url,$args=array()){ return new WP_Error('http_unavailable','HTTP API stub'); } }
if (!class_exists('WP_Error')) { class WP_Error { public function __construct($c='',$m=''){} public function get_error_message(){ return ''; } } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($resp){ return 0; } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($resp){ return ''; } }
if (!function_exists('current_user_can')) { function current_user_can($cap){ return true; } }
if (!function_exists('esc_html')) { function esc_html($s){ return $s; } }
if (!function_exists('esc_html__')) { function esc_html__($s,$d=null){ return $s; } }
if (!function_exists('__')) { function __($s,$d=null){ return $s; } }
if (is_readable(plugin_dir_path(__FILE__) . '/../core/futbolin-config.php')) require_once plugin_dir_path(__FILE__) . '/../core/futbolin-config.php';
require_once plugin_dir_path(__FILE__) . '/../core/class-futbolin-normalizer.php';

class Futbolin_Diagnostic {

    public static function register_page() {
        add_management_page(
            'Futbolín › Diagnóstico',
            'Futbolín › Diagnóstico',
            'manage_options',
            'futbolin-diagnostic',
            [__CLASS__, 'render_page']
        );
    }

    private static function log($msg, $context = null) {
        $prefix = '[FUTBOLIN-DIAG] ';
        if (is_array($context) || is_object($context)) {
            $msg .= ' ' . wp_json_encode($context);
        }
        if (function_exists('error_log')) error_log($prefix . $msg);
    }

    private static function load_master() {
        $cands = [
            plugin_dir_path(__FILE__) . '../../BUENO_master.json',
            plugin_dir_path(__FILE__) . '../BUENO_master.json',
            plugin_dir_path(__FILE__) . '../../../BUENO_master.json',
        ];
        foreach ($cands as $p) {
            if (is_readable($p)) {
                $json = json_decode(@file_get_contents($p), true);
                if (is_array($json)) return $json;
            }
        }
        return null;
    }

    private static function http_json($method, $url, $args = []) {
        $defaults = [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
        ];
        $args = array_replace_recursive($defaults, $args);
        $resp = ($method === 'POST') ? wp_remote_post($url, $args) : wp_remote_get($url, $args);
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return [$code, $body, $resp];
    }

    private static function deep_normalize($data) {
        if (!class_exists('Futbolin_Normalizer')) return $data;
        if (is_callable(['Futbolin_Normalizer', 'deep_normalize_amater'])) {
            return call_user_func(['Futbolin_Normalizer', 'deep_normalize_amater'], $data);
        }
        return $data;
    }

    private static function extract_token($body) {
        if (!is_array($body)) return '';
        foreach (['token','accessToken','access_token','bearer','jwt'] as $k) {
            if (!empty($body[$k]) && is_string($body[$k])) return $body[$k];
        }
        foreach (['data','result'] as $nest) {
            if (!empty($body[$nest]) && is_array($body[$nest]) && !empty($body[$nest]['token']) && is_string($body[$nest]['token'])) {
                return $body[$nest]['token'];
            }
        }
        return '';
    }

    private static function unwrap_items($json) {
        if (!is_array($json)) return [[], false];
        if (!isset($json['items'])) {
            $isList = !empty($json) && array_keys($json) === range(0, count($json)-1);
            if ($isList) return [$json, false];
        }
        $wrappers = ['jugadorPartidos','partidos','data','resultados','torneos','ranking'];
        $node = $json;
        foreach ($wrappers as $w) {
            if (isset($json[$w]) && is_array($json[$w])) { $node = $json[$w]; break; }
        }
        $items = (isset($node['items']) && is_array($node['items'])) ? $node['items'] : [];
        $hasNext = !empty($node['hasNextPage']);
        return [$items, $hasNext];
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;
        $cfg = function_exists('futbolin_get_api_config') ? futbolin_get_api_config() : [
    'base_url' => (function(){ $o=get_option('ranking_api_config',[]); if(is_array($o)&&!empty($o['base_url'])) return rtrim($o['base_url'],'/'); $o2=get_option('mi_plugin_futbolin_options',[]); if(is_array($o2)&&!empty($o2['api_base_url'])) return rtrim($o2['api_base_url'],'/'); return ''; })(),
    'username' => (function(){ $o=get_option('ranking_api_config',[]); if(is_array($o)&&!empty($o['username'])) return $o['username']; $o2=get_option('mi_plugin_futbolin_options',[]); return !empty($o2['api_username']) ? $o2['api_username'] : ''; })(),
    'password' => (function(){ $o=get_option('ranking_api_config',[]); if(is_array($o)&&!empty($o['password'])) return $o['password']; $o2=get_option('mi_plugin_futbolin_options',[]); return !empty($o2['api_password']) ? $o2['api_password'] : ''; })(),
    'sources'  => ['baseurl_source'=>'options','user_source'=>'options']
];
        $base = rtrim($cfg['base_url'] ?? '', '/');
        $user = $cfg['username'] ?? '';
        $pass = $cfg['password'] ?? '';
        $sources = $cfg['sources'] ?? [];
        $master = self::load_master();

        $results = [];


        // Base URL vacía → marcar SKIPPED todos los tests y no invocar HTTP
        if (empty($base)) {
            $results['login']      = ['url'=>null, 'status'=>'SKIPPED', 'reason'=>'BASE_URL_EMPTY', 'token_found'=>false, 'keys_tried'=>['token','accessToken','access_token','bearer','jwt','data.token','result.token']];
            $results['modalidades']= ['status'=>'SKIPPED', 'reason'=>'BASE_URL_EMPTY', 'count'=>0, 'normalized_no_Amater'=>true];
            $results['ranking']    = ['status'=>'SKIPPED', 'reason'=>'BASE_URL_EMPTY', 'wrapper_ok'=>false, 'items_count'=>0, 'hasNextPage'=>null];
            $results['torneos']    = ['status'=>'SKIPPED', 'reason'=>'BASE_URL_EMPTY', 'wrapper_ok'=>false, 'items_count'=>0, 'hasNextPage'=>null];
            $results['partidos']   = ['status_all'=>'SKIPPED', 'reason'=>'BASE_URL_EMPTY', 'count_all'=>0, 'count_pag'=>0, 'invariante_all_ge_pag'=>true];
            $results['search']     = ['status'=>'SKIPPED', 'reason'=>'BASE_URL_EMPTY', 'found'=>false, 'len'=>0, 'path'=>null];
            self::log('Config', ['base_url'=>$base, 'baseurl_source'=>$sources['baseurl_source'] ?? null]);
            self::log('Login', $results['login']);
            echo '<div class="wrap"><h1>Futbolín › Diagnóstico</h1>';
            echo '<p>Base de pruebas: <code>(vacía)</code></p>';
            echo '<p>Origen credenciales: usuario <code>'.esc_html($user).'</code> ('.esc_html($sources['user_source'] ?? 'none').') · baseUrl ('.esc_html($sources['baseurl_source'] ?? 'none').')</p>';
            echo '<table class="widefat striped"><thead><tr><th>Prueba</th><th>Veredicto</th><th>Detalle</th></tr></thead><tbody>';
            self::row('Login', false, $results['login']);
            self::row('Modalidades (normalización)', false, $results['modalidades']);
            self::row('Ranking ESP Pag', false, $results['ranking']);
            self::row('Torneos Pag', false, $results['torneos']);
            self::row('Partidos jugador (ALL ≥ PAG)', false, $results['partidos']);
            self::row('Búsqueda (min 2 + fallbacks)', false, $results['search']);
            echo '</tbody></table>';
            echo '<div class="notice notice-warning"><p>Diagnóstico SKIPPED: <strong>BASE_URL_EMPTY</strong>. Configura la URL base en Ajustes del plugin o coloca <code>BUENO_master.json</code> con <code>meta.baseUrl</code> en el directorio del plugin.</p></div>';
            echo '</div>';
            return;
        }
        // 1) Login
        $login_url = $base . '/api/Seguridad/login';
        list($code, $body, $resp) = self::http_json('POST', $login_url, [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body'    => wp_json_encode(['usuario' => $user, 'password' => $pass]),
        ]);
        $token = self::extract_token($body);
        $results['login'] = ['url'=>$login_url, 'status'=>$code, 'token_found'=> (bool)$token, 'keys_tried'=>['token','accessToken','access_token','bearer','jwt','data.token','result.token']];
        self::log('Config', ['base_url'=>$base, 'user_source'=>$sources['user_source'] ?? null, 'baseurl_source'=>$sources['baseurl_source'] ?? null]);
        self::log('Login', $results['login']);

        // Common headers
        $H = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];

        // 2) Modalidades
        list($codeM, $mods, $rM) = self::http_json('GET', $base . '/api/Modalidad/GetModalidades', ['headers'=>$H]);
        $mods_norm = (method_exists('Futbolin_Normalizer','deep_normalize_amater')) ? self::deep_normalize($mods) : $mods;
        $contains_amater = false;
        if (is_array($mods_norm)) {
            $flat = wp_json_encode($mods_norm);
            $contains_amater = (strpos($flat, '"Amater"') !== false);
        }
        $results['modalidades'] = ['status'=>$codeM, 'count'=> is_array($mods_norm)? count($mods_norm): 0, 'normalized_no_Amater'=> !$contains_amater];
        self::log('Modalidades', $results['modalidades']);
        self::log('Normalizer', ['available'=>method_exists('Futbolin_Normalizer','deep_normalize_amater')]);

        // 3) Ranking ESP (paginado) modalidadId=1
        $rank_url = $base . '/api/Ranking/GetRankingPorModalidadESPPagGlicko2/1?page=1&pageSize=20';
        list($codeR, $rank, $rR) = self::http_json('GET', $rank_url, ['headers'=>$H]);
        $wrapR = (isset($rank['ranking']) && is_array($rank['ranking'])) ? $rank['ranking'] : $rank;
        $okR = (isset($wrapR['items']) && is_array($wrapR['items']) && array_key_exists('hasNextPage', $wrapR));
        $results['ranking'] = ['status'=>$codeR, 'wrapper_ok'=>$okR, 'items_count'=> isset($wrapR['items']) && is_array($wrapR['items']) ? count($wrapR['items']) : 0, 'hasNextPage'=> $wrapR['hasNextPage'] ?? null];
        self::log('Ranking', $results['ranking']);

        // 4) Torneos (paginado)
        $tor_url = $base . '/api/Torneo/GetTorneosPag?page=1&pageSize=20';
        list($codeT, $tor, $rT) = self::http_json('GET', $tor_url, ['headers'=>$H]);
        $wrapT = (isset($tor['torneos']) && is_array($tor['torneos'])) ? $tor['torneos'] : $tor;
        $okT = (isset($wrapT['items']) && is_array($wrapT['items']) && array_key_exists('hasNextPage', $wrapT));
        $results['torneos'] = ['status'=>$codeT, 'wrapper_ok'=>$okT, 'items_count'=> isset($wrapT['items']) && is_array($wrapT['items']) ? count($wrapT['items']) : 0, 'hasNextPage'=> $wrapT['hasNextPage'] ?? null];
        self::log('Torneos', $results['torneos']);

        // 5) Partidos jugador (ALL→PAG con rutas candidatas y probe si 404/404)
        $jid = 614;
        $routeAll = null; $routePag = null;

        // Intentar ALL con rutas candidatas
        $candsAll = [
            $base . "/api/Jugador/$jid/GetJugadorPartidos",
            $base . "/api/Jugador/GetJugadorPartidos/$jid",
        ];
        $all = null; $codeA = null;
        foreach ($candsAll as $u) {
            list($codeTmp, $bodyTmp, $rawTmp) = self::http_json('GET', $u, ['headers'=>$H]);
            if ($codeA === null) $codeA = $codeTmp;
            if ($codeTmp === 200 && is_array($bodyTmp) && count($bodyTmp) > 0) { $all = $bodyTmp; $routeAll = $u; $codeA = 200; break; }
        }
        $cntALL = is_array($all) ? count($all) : 0;

        // Si ALL vacío/falla → intentar PAG con rutas candidatas
        $pagItems = []; $codeP = null;
        if ($cntALL === 0) {
            $candsPag = [
                function($page) use ($base, $jid) { return $base . "/api/Jugador/$jid/GetJugadorPartidosPag?page=$page&pageSize=200"; },
                function($page) use ($base, $jid) { return $base . "/api/Jugador/GetJugadorPartidosPag/$jid?page=$page&pageSize=200"; },
            ];
            foreach ($candsPag as $builder) {
                $page = 1; $hasNext = true; $acc = []; $firstCode = null;
                while ($hasNext && $page <= 50) {
                    $u = $builder($page);
                    list($c, $pag, $r) = self::http_json('GET', $u, ['headers'=>$H]);
                    if ($firstCode === null) $firstCode = $c;
                    list($items, $hasNext) = self::unwrap_items($pag);
                    if (!empty($items)) { $acc = array_merge($acc, $items); }
                    $page++;
                }
                if (!empty($acc)) { $pagItems = $acc; $codeP = 200; $routePag = $builder(1); break; }
                if ($codeP === null) $codeP = $firstCode;
            }
        }
        $cntPAG = count($pagItems);
        if ($routeAll) self::log('PartidosRoute', ['ALL'=>$routeAll]);
        if ($routePag) self::log('PartidosRoute', ['PAG'=>$routePag]);

        $inv = ($codeA === 200) ? ($cntALL >= $cntPAG) : (($codeA===404 || $codeA===null) && ($codeP===404 || $codeP===null) ? 'SKIPPED_ALL_404' : true);
        $results['partidos'] = ['status_all'=>$codeA, 'status_pag'=>$codeP, 'count_all'=>$cntALL, 'count_pag'=>$cntPAG, 'invariante_all_ge_pag'=>$inv];
        self::log('Partidos', $results['partidos']);

        // Probe si ambos 404 para el seedId
        if (($codeA === 404 || $codeA === null) && ($codeP === 404 || $codeP === null)) {
            // búsqueda corta "jo"
            list($cs, $list, $rr) = self::http_json('GET', $base . "/api/Jugador/GetBuscarJugador/jo", ['headers'=>$H]);
            $probeId = null;
            if (is_array($list) && !empty($list)) {
                $first = $list[0];
                if (is_array($first)) {
                    $probeId = $first['jugadorId'] ?? ($first['id'] ?? null);
                }
            }
            if ($probeId) {
                // repetir ALL→PAG con probeId
                $cA2 = null; $cP2 = null; $cntA2=0; $cntP2=0;
                foreach ([$base . "/api/Jugador/$probeId/GetJugadorPartidos", $base . "/api/Jugador/GetJugadorPartidos/$probeId"] as $u2) {
                    list($cTmp, $bTmp, $rTmp) = self::http_json('GET', $u2, ['headers'=>$H]);
                    if ($cA2 === null) $cA2 = $cTmp;
                    if ($cTmp === 200 && is_array($bTmp) && count($bTmp)>0) { $cntA2 = count($bTmp); $cA2 = 200; $routeAll = $u2; break; }
                }
                if ($cntA2 === 0) {
                    foreach ([
                        function($page) use ($base, $probeId) { return $base . "/api/Jugador/$probeId/GetJugadorPartidosPag?page=$page&pageSize=200"; },
                        function($page) use ($base, $probeId) { return $base . "/api/Jugador/GetJugadorPartidosPag/$probeId?page=$page&pageSize=200"; },
                    ] as $b2) {
                        $p=1; $nxt=true; $acc2=[]; $fc=null;
                        while ($nxt && $p<=50) {
                            $u2 = $b2($p);
                            list($c2, $pp2, $rr2) = self::http_json('GET', $u2, ['headers'=>$H]);
                            if ($fc===null) $fc=$c2;
                            list($it2,$nxt)=self::unwrap_items($pp2);
                            if (!empty($it2)) $acc2 = array_merge($acc2,$it2);
                            $p++;
                        }
                        if (!empty($acc2)) { $cntP2 = count($acc2); $cP2 = 200; $routePag = $b2(1); break; }
                        if ($cP2===null) $cP2=$fc;
                    }
                }
                self::log('PartidosProbe', ['seedId'=>$jid, 'probeId'=>$probeId, 'status_all'=>$cA2, 'status_pag'=>$cP2, 'count_all'=>$cntA2, 'count_pag'=>$cntP2]);
                if ($cA2===200 || $cP2===200) {
                    $results['partidos'] = ['status_all'=>$cA2, 'status_pag'=>$cP2, 'count_all'=>$cntA2, 'count_pag'=>$cntP2, 'invariante_all_ge_pag'=>($cA2===200 ? ($cntA2 >= $cntP2) : true), 'used_probeId'=>true, 'probeId'=>$probeId];
                    self::log('Partidos', $results['partidos']);
                } else {
                    $results['partidos']['invariante_all_ge_pag'] = 'SKIPPED_NO_VALID_ROUTE_OR_NO_DATA';
                    self::log('Partidos', $results['partidos']);
                }
            } else {
                $results['partidos']['invariante_all_ge_pag'] = 'SKIPPED_NO_VALID_ROUTE_OR_NO_DATA';
                self::log('Partidos', $results['partidos']);
            }
        }
// 6) Búsqueda (min 2 chars + fallbacks)
        $q = 'jo';
        $paths = [
            "/api/Jugador/GetBuscarJugador/$q",
            "/api/Jugador/GetBuscarJugador",
            "/api/Jugador/BuscarJugador/$q",
            "/api/Jugador/BuscarJugador",
            "/api/Jugador/GetBuscarJugadorES/$q",
            "/api/Jugador/GetBuscarJugadorES"
        ];
        $found = false; $len = 0; $path_used = null; $statusS = null; $dataS = null;
        foreach ($paths as $p) {
            list($codeS, $data, $rS) = self::http_json('GET', $base . $p, ['headers'=>$H]);
            $statusS = $codeS;
            if ($codeS === 200 && is_array($data) && !empty($data)) { $found = true; $len = count($data); $path_used = $p; $dataS = $data; break; }
        }
        $results['search'] = ['status'=>$statusS, 'found'=>$found, 'len'=>$len, 'path'=>$path_used];
        self::log('Busqueda', $results['search']);

        // Render
        echo '<div class="wrap"><h1>Futbolín › Diagnóstico</h1>';
        echo '<p>Base de pruebas: <code>'.esc_html($base).'</code></p>';echo '<p>Origen credenciales: usuario <code>'.esc_html($user).'</code> ('.esc_html($sources['user_source'] ?? 'desconocido').') · baseUrl ('.esc_html($sources['baseurl_source'] ?? 'desconocido').')</p>';if ($code===401){ echo '<div class="notice notice-error"><p>401 en <code>/Seguridad/login</code> con usuario <code>'.esc_html($user).'</code> desde '.esc_html($sources['user_source'] ?? 'desconocido').'. Revisa usuario/contraseña en Ajustes del plugin.</p></div>'; }
        echo '<table class="widefat striped"><thead><tr><th>Prueba</th><th>Veredicto</th><th>Detalle</th></tr></thead><tbody>';
        self::row('Login', ($code===200 && !empty($token)), $results['login']);
        self::row('Modalidades (normalización)', ($codeM===200 && $results['modalidades']['normalized_no_Amater']), $results['modalidades']);
        self::row('Ranking ESP Pag', ($codeR===200 && $results['ranking']['wrapper_ok']), $results['ranking']);
        self::row('Torneos Pag', ($codeT===200 && $results['torneos']['wrapper_ok']), $results['torneos']);
        self::row('Partidos jugador (ALL ≥ PAG)', ($codeA===200 && $results['partidos']['invariante_all_ge_pag']), $results['partidos']);
        self::row('Búsqueda (min 2 + fallbacks)', ($results['search']['found']===true), $results['search']);
        echo '</tbody></table>';
        echo '<p>Se han escrito entradas en <code>wp-content/debug.log</code> con el prefijo <strong>[FUTBOLIN-DIAG]</strong>.</p>';
        echo '</div>';
    }

    private static function row($name, $ok, $details) {
        $badge = $ok ? '<span style="color: #0a0; font-weight:700;">OK</span>' : '<span style="color:#a00;font-weight:700;">KO</span>';
        echo '<tr><td>'.esc_html($name).'</td><td>'.$badge.'</td><td><pre style="white-space:pre-wrap">'.esc_html(wp_json_encode($details, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)).'</pre></td></tr>';
    }
}

// Integración del menú desactivada: se ejecuta desde la pestaña Avanzado del propio plugin.
