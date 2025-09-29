<?php
/**
 * Archivo Resultante: class-futbolin-api-client.php (Versión Completa)
 * Ruta: includes/core/class-futbolin-api-client.php
 * Fuente Original: class-futbolin-api-client.php (antiguo)
 *
 * Descripción: Cliente centralizado para todas las comunicaciones con la API de Futbolín.
 * Esta versión mantiene el contrato original y añade:
 * - Manejo robusto de 204/5xx
 * - Cache negativa por URL
 * - Log deduplicado
 */

if (!defined('ABSPATH')) exit;

// --- Stubs/guards para análisis de editor fuera de WordPress (no afectan runtime WP) ---
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = [];
        public function __construct($code = '', $message = '') {
            if ($code !== '' || $message !== '') {
                $this->errors[$code] = [$message];
            }
        }
        public function get_error_message() {
            foreach ($this->errors as $msgs) { if (!empty($msgs[0])) return $msgs[0]; }
            return '';
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return $thing instanceof WP_Error; }
}
if (!defined('MINUTE_IN_SECONDS')) { define('MINUTE_IN_SECONDS', 60); }
if (!defined('HOUR_IN_SECONDS'))   { define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS); }
if (!defined('DAY_IN_SECONDS'))    { define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS); }
// Opciones y transients
if (!function_exists('get_option')) { function get_option($k, $d = null) { return $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $autoload = false) { return true; } }
if (!function_exists('get_transient')) { function get_transient($k) { return false; } }
if (!function_exists('set_transient')) { function set_transient($k, $v, $ttl) { return true; } }
if (!function_exists('delete_transient')) { function delete_transient($k) { return true; } }
// HTTP API (stubs inertes para análisis)
if (!function_exists('wp_remote_get')) { function wp_remote_get($url, $args = []) { return new WP_Error('http_unavailable', 'HTTP API stub'); } }
if (!function_exists('wp_remote_post')) { function wp_remote_post($url, $args = []) { return new WP_Error('http_unavailable', 'HTTP API stub'); } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($resp) { return ''; } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($resp) { return 0; } }
// Object cache helpers
if (!function_exists('wp_using_ext_object_cache')) { function wp_using_ext_object_cache() { return false; } }
if (!function_exists('wp_cache_get')) { function wp_cache_get($key, $group = '') { return false; } }
if (!function_exists('wp_cache_set')) { function wp_cache_set($key, $value, $group = '', $ttl = 0) { return true; } }
if (!function_exists('wp_cache_delete')) { function wp_cache_delete($key, $group = '') { return true; } }
// maybe_* helpers
if (!function_exists('maybe_serialize')) { function maybe_serialize($value) { return serialize($value); } }
if (!function_exists('maybe_unserialize')) { function maybe_unserialize($value) { $u = @unserialize($value); return ($u === false && $value !== 'b:0;') ? $value : $u; } }

class Futbolin_API_Client {

    // Declaración de propiedades antes de métodos para contentar analizadores estáticos
    protected $base_api_url = '';

    public function __construct() {
    // Permite override del base_api_url desde opciones del plugin (si el admin lo configuró)
    $cfg = get_option('ranking_api_config', array());
    if (is_array($cfg) && !empty($cfg['base_url'])) {
        $this->base_api_url = rtrim($cfg['base_url'], '/');
    } else {
        $opts = get_option('mi_plugin_futbolin_options', array());
        if (is_array($opts) && !empty($opts['api_base_url'])) {
            $this->base_api_url = rtrim($opts['api_base_url'], '/');
        }
    }
    // Fallback sin hardcode: leer de BUENO_master.json (meta.baseUrl)
    if (empty($this->base_api_url)) {
        $plugin_dir = dirname(dirname(dirname(__FILE__))); // .../includes/core -> plugin root
        $master_path = $plugin_dir . '/BUENO_master.json';
        if (file_exists($master_path)) {
            $json = json_decode(file_get_contents($master_path), true);
            if (is_array($json) && isset($json['meta']['baseUrl']) && is_string($json['meta']['baseUrl'])) {
                $this->base_api_url = rtrim($json['meta']['baseUrl'], '/');
            }
        }
    }
    // Asegura que termina con '/api' (evita dobles //)
    if (!empty($this->base_api_url) && stripos($this->base_api_url, '/api') === false) {
        $this->base_api_url = rtrim($this->base_api_url, '/') . '/api';
    }
}

    public function get_base_api_url(){ return $this->base_api_url; }


    // --- MÉTODOS PÚBLICOS (Endpoints de la API) ---

    public function get_modalidades() {
        $api_url = $this->base_api_url . '/Modalidad/GetModalidades';
        return $this->do_request($api_url, true);
    }

    public function get_ranking($modalidad_id, $page = 1, $page_size = 25) {
        $api_page_size = ($page_size == -1) ? 9999 : $page_size;
        $api_url = $this->base_api_url . "/Ranking/GetRankingPorModalidadESPPagGlicko2/{$modalidad_id}?page={$page}&pageSize={$api_page_size}";
        return $this->do_request($api_url, true);
    }

    /**
     * Ranking ESP Glicko2 completo por modalidad (no paginado, sin temporada).
     * Endpoint: GET /api/Ranking/GetRankingPorModalidadESPGlicko2/{ModalidadId}
     */
    public function get_ranking_por_modalidad_esp_g2_all($modalidad_id) {
        if (!$modalidad_id) return [];
        // Variantes (por si algún host acepta QS también)
        $candidates = [];
        $candidates[] = $this->base_api_url . "/Ranking/GetRankingPorModalidadESPGlicko2/{$modalidad_id}";
        $candidates[] = $this->base_api_url . "/Ranking/GetRankingPorModalidadESPGlicko2?modalidadId={$modalidad_id}";
        $last = null;
        foreach ($candidates as $u) {
            $resp = $this->do_request($u, true);
            $last = $resp;
            if (is_wp_error($resp) || $resp === null) { continue; }
            $cnt = 0;
            if (is_array($resp)) {
                $is_assoc = array_keys($resp) !== range(0, count($resp) - 1);
                if (!$is_assoc) { $cnt = count($resp); }
                else {
                    if (isset($resp['items']) && is_array($resp['items'])) { $cnt = count($resp['items']); }
                    elseif (isset($resp['data']['items']) && is_array($resp['data']['items'])) { $cnt = count($resp['data']['items']); }
                    elseif (isset($resp['result']['items']) && is_array($resp['result']['items'])) { $cnt = count($resp['result']['items']); }
                    elseif (isset($resp['ranking']['items']) && is_array($resp['ranking']['items'])) { $cnt = count($resp['ranking']['items']); }
                }
            } elseif (is_object($resp)) {
                if (isset($resp->items) && is_array($resp->items)) { $cnt = count($resp->items); }
                elseif (isset($resp->data) && is_object($resp->data) && isset($resp->data->items) && is_array($resp->data->items)) { $cnt = count($resp->data->items); }
                elseif (isset($resp->result) && is_object($resp->result) && isset($resp->result->items) && is_array($resp->result->items)) { $cnt = count($resp->result->items); }
                elseif (isset($resp->ranking) && is_object($resp->ranking) && isset($resp->ranking->items) && is_array($resp->ranking->items)) { $cnt = count($resp->ranking->items); }
            }
            $rf_debug_on = isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1';
            if ($rf_debug_on) { try { error_log('RFHITOS_RANK_ESP_ALL url=' . $u . ' items=' . $cnt); } catch (\Throwable $e) {} }
            if ($cnt > 0) { return $resp; }
        }
        return $last ?: [];
    }

    
    /**
     * Ranking por modalidad y temporada (ESP, Glicko2).
     * Usa el orden del listado como verdad de posiciones (no confíes en "posicion").
     */
    public function get_ranking_por_modalidad_temporada_esp_g2($modalidad_id, $temporada_id) {
        if (!$modalidad_id || !$temporada_id) return [];
        // Intentar variantes conocidas: path y querystring
        $candidates = [];
        $candidates[] = $this->base_api_url . "/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{$modalidad_id}/{$temporada_id}";
        $candidates[] = $this->base_api_url . "/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2?modalidadId={$modalidad_id}&temporadaId={$temporada_id}";
        $candidates[] = $this->base_api_url . "/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2?temporadaId={$temporada_id}&modalidadId={$modalidad_id}";

        $last = null;
        foreach ($candidates as $u) {
            $resp = $this->do_request($u, true);
            $last = $resp;
            // Si error, probar siguiente
            if (is_wp_error($resp) || $resp === null) {
                try { error_log('RFHITOS_SEASON_ALT url=' . $u . ' status=error_or_null'); } catch (\Throwable $e) {}
                continue;
            }
            // Contar items de forma tolerante
            $cnt = 0;
            if (is_array($resp)) {
                $is_assoc = array_keys($resp) !== range(0, count($resp) - 1);
                if (!$is_assoc) { $cnt = count($resp); }
                else {
                    if (isset($resp['items']) && is_array($resp['items'])) { $cnt = count($resp['items']); }
                    elseif (isset($resp['data']['items']) && is_array($resp['data']['items'])) { $cnt = count($resp['data']['items']); }
                    elseif (isset($resp['result']['items']) && is_array($resp['result']['items'])) { $cnt = count($resp['result']['items']); }
                    elseif (isset($resp['ranking']['items']) && is_array($resp['ranking']['items'])) { $cnt = count($resp['ranking']['items']); }
                }
            } elseif (is_object($resp)) {
                if (isset($resp->items) && is_array($resp->items)) { $cnt = count($resp->items); }
                elseif (isset($resp->data) && is_object($resp->data) && isset($resp->data->items) && is_array($resp->data->items)) { $cnt = count($resp->data->items); }
                elseif (isset($resp->result) && is_object($resp->result) && isset($resp->result->items) && is_array($resp->result->items)) { $cnt = count($resp->result->items); }
                elseif (isset($resp->ranking) && is_object($resp->ranking) && isset($resp->ranking->items) && is_array($resp->ranking->items)) { $cnt = count($resp->ranking->items); }
            }
            try { error_log('RFHITOS_SEASON_ALT url=' . $u . ' items=' . $cnt); } catch (\Throwable $e) {}
            if ($cnt > 0) { return $resp; }
        }
        // Ninguna variante produjo items, devolvemos el último resultado (posible array vacío u objeto)
        return $last ?: [];
    }

    // Variante estricta: solo endpoint oficial (sin discovery), siempre con auth
    public function get_ranking_por_modalidad_temporada_esp_g2_official_only($modalidad_id, $temporada_id) {
        if (!$modalidad_id || !$temporada_id) return [];
        $api_url = $this->base_api_url . "/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{$modalidad_id}/{$temporada_id}";
        return $this->do_request($api_url, true);
    }

    /**
     * Igual que la variante estricta, pero con caché positiva (transient) por host+mod+temp+token.
     * - Cachea respuestas válidas (array/objeto) durante 6 horas.
     * - Si llega un array vacío pero 200, cachea corto (15 minutos) para evitar martilleo.
     * - Errores o null (204) quedan cubiertos por la caché negativa ya existente en do_request.
     */
    public function get_ranking_por_modalidad_temporada_esp_g2_cached_official($modalidad_id, $temporada_id) {
        if (!$modalidad_id || !$temporada_id) return [];
        $host = '';
        try {
            $pu = parse_url($this->base_api_url);
            $host = is_array($pu) && isset($pu['host']) ? strtolower($pu['host']) : '';
        } catch (\Throwable $e) { $host = ''; }
        $tfp = $this->get_token_fingerprint();
        $ck  = sprintf('rf:rank:esp2:host:%s:mod:%s:temp:%s:tfp:%s', $host ?: 'h', (string)$modalidad_id, (string)$temporada_id, $tfp ?: 't');
        $cached = function_exists('get_transient') ? get_transient($ck) : false;
        if ($cached !== false) {
            if (function_exists('rf_log')) { rf_log('CACHE HIT ranking temporada', ['key' => $ck, 'mod' => (string)$modalidad_id, 'temp' => (string)$temporada_id], 'debug'); }
            return $cached;
        }

        $api_url = $this->base_api_url . "/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{$modalidad_id}/{$temporada_id}";
        $resp = $this->do_request($api_url, true);
        if ($resp === null || is_wp_error($resp)) {
            if (function_exists('rf_log')) {
                $tag = ($resp === null) ? 'null' : (is_wp_error($resp) ? 'wp_error' : 'other');
                rf_log('CACHE MISS ranking temporada -> upstream not cacheable', ['key' => $ck, 'tag' => $tag], 'info');
            }
            return $resp;
        }
        // Cachea objetos/arrays con TTL largo (estático hasta purga manual)
        $ttl_long = 365 * DAY_IN_SECONDS;
        $to_cache = $resp;
        if (is_array($resp) || is_object($resp)) {
            if (function_exists('set_transient')) { set_transient($ck, $to_cache, $ttl_long); }
            if (function_exists('rf_log')) { rf_log('CACHE SET ranking temporada', ['key' => $ck, 'items' => (is_array($resp) ? count($resp) : 0)], 'debug'); }
            return $resp;
        }
        return $resp;
    }

    /** Campeones de España cacheado largo (hasta purga manual) */
    public function get_campeones_espania() {
        $host = '';
        try {
            $pu = parse_url($this->base_api_url);
            $host = is_array($pu) && isset($pu['host']) ? strtolower($pu['host']) : '';
        } catch (\Throwable $e) { $host = ''; }
        $tfp = $this->get_token_fingerprint();
        $ck  = sprintf('rf:champions:es:host:%s:tfp:%s', $host ?: 'h', $tfp ?: 't');
    $cached = function_exists('get_transient') ? get_transient($ck) : false;
    if ($cached !== false) { if (function_exists('rf_log')) { rf_log('CACHE HIT campeones_es', ['key' => $ck], 'debug'); } return $cached; }
        $api_url = $this->base_api_url . '/Jugador/GetCampeonesEspania';
        $resp = $this->do_request($api_url, true);
    if ($resp === null || is_wp_error($resp)) { if (function_exists('rf_log')) { rf_log('CACHE MISS campeones_es -> upstream not cacheable', ['key' => $ck], 'info'); } return $resp; }
        if (is_array($resp) || is_object($resp)) {
            if (function_exists('set_transient')) { set_transient($ck, $resp, 365 * DAY_IN_SECONDS); }
            if (function_exists('rf_log')) { rf_log('CACHE SET campeones_es', ['key' => $ck], 'debug'); }
        }
        return $resp;
    }

    /**
     * Índice de campeones por jugador (dobles/individual -> años), con cache larga.
     * Usa la misma heurística que Hitos: nombreTorneo -> temporada (texto) -> temporadaId (mapa), 2025→2024 y filtra <2011.
     */
    public function get_campeones_index() {
        static $memCache = null;
        if ($memCache !== null) return $memCache;
        $host = '';
        try { $pu = parse_url($this->base_api_url); $host = is_array($pu) && isset($pu['host']) ? strtolower($pu['host']) : ''; } catch (\Throwable $e) { $host = ''; }
        $tfp = $this->get_token_fingerprint();
        $ver = function_exists('get_option') ? (string)(get_option('rf_dataset_ver') ?: '1') : '1';
        $ck  = sprintf('rf:champions:index:host:%s:tfp:%s:v%s', $host ?: 'h', $tfp ?: 't', $ver);
        $cached = function_exists('get_transient') ? get_transient($ck) : false;
        if ($cached !== false && is_array($cached)) { $memCache = $cached; return $cached; }

        $rows = $this->get_campeones_espania();
        if (!$rows || is_wp_error($rows)) { return []; }
        $index = [];
        $mapYears = function($arr){
            $out = [];
            if (!is_array($arr)) return $out;
            foreach ($arr as $it) {
                if (!(is_object($it) || is_array($it))) continue;
                $ii = (object)$it;
                $year = null;
                if (isset($ii->nombreTorneo) && is_string($ii->nombreTorneo) && preg_match('/(19|20)\d{2}/', $ii->nombreTorneo, $m2)) { $year = (int)$m2[0]; }
                if (!$year && isset($ii->temporada) && is_string($ii->temporada) && preg_match('/(19|20)\d{2}/', $ii->temporada, $m)) { $year = (int)$m[0]; }
                if ($year === 2025) { $year = 2024; }
                if (!$year && isset($ii->temporadaId) && is_numeric($ii->temporadaId)) {
                    if (class_exists('Futbolin_Normalizer')) {
                        $tmap = \Futbolin_Normalizer::temporada_year_map();
                        $tid = (string)intval($ii->temporadaId);
                        if (isset($tmap[$tid]) && is_numeric($tmap[$tid])) { $year = (int)$tmap[$tid]; }
                    }
                }
                if ($year) { $out[] = $year; }
            }
            $out = array_values(array_unique(array_filter($out, function($v){ return is_numeric($v) && (int)$v >= 2011; })));
            sort($out);
            return array_map('strval', $out);
        };
        foreach ($rows as $row) {
            $o = (object)$row;
            $jid = intval($o->jugadorId ?? ($o->JugadorId ?? 0));
            if ($jid <= 0) continue;
            $index[$jid] = [
                'dobles' => $mapYears($o->torneosDobles ?? []),
                'individual' => $mapYears($o->torneosIndividual ?? []),
            ];
        }
        if (function_exists('set_transient')) { set_transient($ck, $index, 365 * DAY_IN_SECONDS); }
        $memCache = $index;
        return $index;
    }
    
    /**
     * Ranking por modalidad y temporada (ESP por posición).
     * Endpoints confirmados en hosts: GetRankingPorModalidadPorTemporadaPorPosicionESP/{ModalidadId}/{TemporadaId}
     */
    public function get_ranking_por_modalidad_temporada_por_posicion_esp($modalidad_id, $temporada_id) {
        if (!$modalidad_id || !$temporada_id) return [];
        $api_url = $this->base_api_url . "/Ranking/GetRankingPorModalidadPorTemporadaPorPosicionESP/{$modalidad_id}/{$temporada_id}";
        return $this->do_request($api_url, true);
    }
public function get_jugador_puntuacion_categoria($jugador_id) {
        if (!$jugador_id) return [];
        $api_url = $this->base_api_url . "/Jugador/{$jugador_id}/GetJugadorPuntuacionCategoria";
        return $this->do_request($api_url, true);
    }

    public function buscar_jugadores($search_term) {
        if (empty($search_term)) return [];
        // Normaliza espacios y recorta
        if (is_string($search_term)) {
            $search_term = preg_replace('/\s+/', ' ', trim($search_term));
        }
        if (strlen($search_term) < 2) return [];
        // IMPORTANTE: para segmentos de ruta usar rawurlencode (espacios = %20, no '+')
        $api_url = $this->base_api_url . '/Jugador/GetBuscarJugador/' . rawurlencode($search_term);
        $response = $this->do_request($api_url, true);
        if (!$response || is_wp_error($response)) {
            return [];
        }
        return $response;
    }

    public function get_datos_jugador($jugador_id) {
        if (!$jugador_id) return null;
        $api_url = $this->base_api_url . "/Jugador/{$jugador_id}/GetDatosJugador";
        return $this->do_request($api_url, true);
    }

        public function get_partidos_jugador($jugador_id) {
        if (!$jugador_id) return [];
        // Cache positiva (10 min) por jugador
        $ck = $this->rf_build_cache_key('rf:p:partidos:' . intval($jugador_id));
        $cached = $this->cache_get_large($ck);
        if ($cached !== false && is_array($cached)) { $this->maybe_revalidate_cached_partidos($jugador_id, $ck, $cached); return $cached; }
        // Master candidates (en orden)
        $candidates = [
            $this->base_api_url . "/Jugador/{$jugador_id}/GetJugadorPartidos",
            $this->base_api_url . "/Jugador/GetJugadorPartidos/{$jugador_id}",
        ];
        foreach ($candidates as $api_url) {
            $decoded = $this->do_request($api_url, true);
            if (!$decoded || is_wp_error($decoded)) { continue; }
            // Unwrap items según BUENO_master.unwind_order: items -> data.items -> result.items -> root
            $items = [];
            if (is_array($decoded)) {
                // Puede ser root array (lista de objetos)
                $is_assoc = array_keys($decoded) !== range(0, count($decoded) - 1);
                if (!$is_assoc) { $items = $decoded; }
                else {
                    if (isset($decoded['items']) && is_array($decoded['items'])) { $items = $decoded['items']; }
                    elseif (isset($decoded['data']['items']) && is_array($decoded['data']['items'])) { $items = $decoded['data']['items']; }
                    elseif (isset($decoded['result']['items']) && is_array($decoded['result']['items'])) { $items = $decoded['result']['items']; }
                }
            } elseif (is_object($decoded)) {
                if (isset($decoded->items) && is_array($decoded->items)) { $items = $decoded->items; }
                elseif (isset($decoded->data->items) && is_array($decoded->data->items)) { $items = $decoded->data->items; }
                elseif (isset($decoded->result->items) && is_array($decoded->result->items)) { $items = $decoded->result->items; }
            }
            if (!empty($items)) {
                // Normaliza a objetos (por consistencia con el resto del procesado)
                $items = array_map(function($x){ return is_object($x) ? $x : (object)$x; }, $items);
                $this->log_once("PARTIDOS ALL jugador={$jugador_id} via " . $api_url . " -> items=" . count($items), 120);
                $this->cache_set_large($ck, $items, 90 * DAY_IN_SECONDS);
                return $items;
            }
        }
        // Si no hubo éxito, registra intento con 0 y devuelve []
        $this->log_once("PARTIDOS ALL jugador={$jugador_id} -> items=0 (probado ambos candidatos)", 120);
        return [];
    }

    public function get_posiciones_jugador($jugador_id) {
        if (!$jugador_id) return [];
        $ck = 'rf:p:pos:' . intval($jugador_id);
        $cached = (function_exists('get_transient')) ? get_transient($ck) : false;
        if ($cached !== false) { return is_array($cached) ? $cached : $cached; }

        $api_url = $this->base_api_url . "/Jugador/{$jugador_id}/GetJugadorPosicionPorTorneos";
        $resp = $this->do_request($api_url, true);
        if ($resp && !is_wp_error($resp)) {
            if (function_exists('set_transient')) { set_transient($ck, $resp, 10 * MINUTE_IN_SECONDS); }
            return $resp;
        }
        // cache corta para errores/reintentos
        if (function_exists('set_transient')) { set_transient($ck, [], 3 * MINUTE_IN_SECONDS); }
        return [];
    }

    public function get_torneos() {
        $api_url = $this->base_api_url . "/Torneo/GetTorneos";
        return $this->do_request($api_url, true);
    }

    public function get_tournaments_paginated($page = 1, $page_size = 25) {
        $api_url = $this->base_api_url . "/Torneo/GetTorneosPag?page={$page}&pageSize={$page_size}";
        return $this->do_request($api_url, true);
    }

    public function get_tournament_with_positions($torneo_id) {
        if (!$torneo_id) return [];
        $api_url = $this->base_api_url . "/Torneo/GetTorneoConPosiciones/{$torneo_id}";
        return $this->do_request($api_url, true);
    }

    /**
     * Wrapper público genérico para peticiones arbitrarias (path relativo o URL completa).
     * $relative_or_full puede ser '/Temporada/GetTemporadas' o 'https://host/api/...' .
     * $auth indica si se fuerza cabecera Authorization.
     */
    public function request_raw($relative_or_full, $auth = false) {
        $url = $relative_or_full;
        if (is_string($relative_or_full) && strpos($relative_or_full, 'http') !== 0) {
            $base = rtrim($this->base_api_url, '/');
            if ($relative_or_full && $relative_or_full[0] !== '/') { $relative_or_full = '/' . $relative_or_full; }
            // Evitar duplicar /api cuando base termina en /api y el path también empieza por /api/
            if (substr($base, -4) === '/api' && stripos($relative_or_full, '/api/') === 0) {
                $relative_or_full = substr($relative_or_full, 4); // quita solo el primer /api
            }
            // También si el path es exactamente '/api' (raro) lo reducimos a '/'
            if (substr($base, -4) === '/api' && $relative_or_full === '/api') { $relative_or_full = '/'; }
            $url = $base . $relative_or_full;
        }
        return $this->do_request($url, (bool)$auth);
    }

    // --- LÓGICA INTERNA DE PETICIONES Y AUTENTICACIÓN ---

    protected function do_request($url, $auth_required = false) {
        // Pre-detección del endpoint de temporada y log mínimo de diagnóstico (antes de cache)
        $host_l = ''; $path_l = '';
        $is_season_ep0 = false; $season_err_key = '';
        try {
            $p0 = parse_url($url);
            $host_l = is_array($p0) && !empty($p0['host']) ? strtolower($p0['host']) : '';
            $path_l = is_array($p0) && !empty($p0['path']) ? strtolower($p0['path']) : '';
            $is_season_ep0 = (
                strpos($path_l, '/ranking/getrankingpormodalidadportemporadaespglicko2') !== false ||
                strpos($path_l, '/api/ranking/getrankingpormodalidadportemporadaespglicko2') !== false
            );
            if ($is_season_ep0 && $host_l !== '') { $season_err_key = 'futb_season_err:' . $host_l; }
            if ($host_l === 'ranking.fefm.net') {
                $rf_debug_on = isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1';
                if ($rf_debug_on) { error_log('RFHITOS_SEASON_DETECT host=' . $host_l . ' path=' . $path_l . ' match=' . ($is_season_ep0 ? '1' : '0')); }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Si recientemente el endpoint de temporada devolvió 5xx, atajar con backoff
        if ($is_season_ep0 && $season_err_key !== '' && get_transient($season_err_key)) {
            if (function_exists('rf_log')) { rf_log('BACKOFF temporada: 5xx throttled', ['url' => $url, 'host' => $host_l], 'warning'); }
            return new WP_Error('http_error', 'Endpoint de temporada en error temporal (5xx throttled)');
        }

        // Cache negativa por URL variante (incluye si va con/sin Authorization) para evitar colisiones
        $url_cache_key = $url . ($auth_required ? '::AUTH' : '::NOAUTH');
        // Versionar las claves de caché por fingerprint de token si la petición va autenticada
        if ($auth_required) {
            $tfp = $this->get_token_fingerprint();
            if ($tfp !== '') { $url_cache_key .= '::TFP:' . $tfp; }
        }
        // Permitir bust de cache de debug sin alterar la URL real (para evitar stripping de QS)
        if (isset($_GET['rf_debug_hitos_ncache_bust']) && $_GET['rf_debug_hitos_ncache_bust'] == '1') {
            $url_cache_key .= '::BUST:' . time();
        }
        $neg_key = $this->neg_cache_key($url_cache_key);
        // Permitir reset explícito de la entrada de cache negativa por debug
        if (isset($_GET['rf_debug_hitos_ncache_reset']) && $_GET['rf_debug_hitos_ncache_reset'] == '1') {
            delete_transient($neg_key);
        }
        $cached  = get_transient($neg_key);
        if ($cached !== false) {
            // Log opcional si el endpoint es de temporada (para saber si venimos de cache)
            try {
                $is_season_cached = (
                    strpos($path_l, '/ranking/getrankingpormodalidadportemporadaespglicko2') !== false ||
                    strpos($path_l, '/api/ranking/getrankingpormodalidadportemporadaespglicko2') !== false
                );
                // Siempre deja una pista mínima si es el endpoint de temporada
                if ($is_season_cached) {
                    $rf_debug_on = (
                        (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') ||
                        (isset($_GET['rf_debug_hitos_http']) && $_GET['rf_debug_hitos_http'] == '1')
                    );
                    $ctype = 'unknown';
                    if (is_array($cached) && isset($cached['__no_content'])) { $ctype = 'no_content'; }
                    elseif ($cached instanceof WP_Error) { $ctype = 'wp_error'; }
                    else { $ctype = is_object($cached) ? 'object' : (is_string($cached) ? 'string' : 'other'); }
                    if ($rf_debug_on) { error_log('RFHITOS_NEGCACHE_HIT type=' . $ctype . ' url=' . $url); }
                    if ($rf_debug_on) {
                        $tag = 'cached';
                        $snip = '';
                        if (is_string($cached)) { $snip = substr(preg_replace('/\s+/', ' ', $cached), 0, 120); }
                        elseif (is_array($cached) || is_object($cached)) { $snip = substr(json_encode($cached), 0, 120); }
                        error_log('RFHITOS_SEASON_HTTP (' . $tag . ') url=' . $url . ' repr=' . $snip);
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
            // marcador de 204 (sin contenido)
            if (is_array($cached) && isset($cached['__no_content']) && $cached['__no_content'] === true) {
                if (function_exists('rf_log')) { rf_log('NEG-CACHE HIT (204/no_content)', ['url' => $url], 'info'); }
                return null; // contrato: 204 => null
            }
            if ($cached instanceof WP_Error) {
                if (function_exists('rf_log')) { rf_log('NEG-CACHE HIT (WP_Error)', ['url' => $url], 'warning'); }
                return $cached; // contrato: error => WP_Error
            }
            if (function_exists('rf_log')) { rf_log('NEG-CACHE HIT (other)', ['url' => $url], 'debug'); }
            return $cached;
        }

        $args = [
            'method'    => 'GET',
            // Timeout conservador para evitar cuelgues prolongados si el filtro global no aplica
            'timeout'   => 12,
            'sslverify' => false,
            'headers'   => ['Accept'=>'application/json'],
        ];
        // Simplificado: sin modos especiales Swagger.

        // Regla específica para host/endpoint: emular Swagger automáticamente en
        // /Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2 (con o sin prefijo /api)
        // de illozapatillo.zapto.org
    $force_bearer_lower = false; // legacy flag (desactivado)
        try {
            $p = parse_url($url);
            $host_l = is_array($p) && !empty($p['host']) ? strtolower($p['host']) : '';
            $path_l = is_array($p) && !empty($p['path']) ? strtolower($p['path']) : '';
            $is_season_endpoint = (
                strpos($path_l, '/ranking/getrankingpormodalidadportemporadaespglicko2') !== false ||
                strpos($path_l, '/api/ranking/getrankingpormodalidadportemporadaespglicko2') !== false
            );
            // Log diagnóstico de detección (1 línea) para este host
            if ($host_l === 'ranking.fefm.net') {
                $rf_debug_on = isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1';
                if ($rf_debug_on) { error_log('RFHITOS_SEASON_DETECT host=' . $host_l . ' path=' . $path_l . ' match=' . ($is_season_endpoint ? '1' : '0')); }
            }
            // Sin sobrecarga especial para temporada: usamos headers básicos Accept: application/json
        } catch (\Throwable $e) { /* ignore */ }

        $auth_src = 'none';
        if ($auth_required) {
            // Debug: permitir override temporal del Bearer con ?rf_debug_bearer=...
            $debug_bearer = isset($_GET['rf_debug_bearer']) ? trim((string)$_GET['rf_debug_bearer']) : '';
            $bearer_lower = (isset($_GET['rf_debug_hitos_bearer_lower']) && $_GET['rf_debug_hitos_bearer_lower'] == '1');
            // bearer_lower legacy desactivado
            if ($debug_bearer !== '') {
                $args['headers']['Authorization'] = ($bearer_lower ? 'bearer ' : 'Bearer ') . $debug_bearer;
                $auth_src = 'rf_debug_bearer';
                // No persistimos este token ni lo registramos en logs (seguridad)
            } else {
                $token = $this->get_valid_token();
                if (!$token || is_wp_error($token)) {
                    // cache muy corta para no insistir mientras el token falla
                    $err_obj = ($token instanceof WP_Error) ? $token : new WP_Error('auth_error','Token inválido');
                    try {
                        // Log explícito de fallo de autenticación para diagnóstico del endpoint de temporada
                        if (isset($force_swagger_for_season) && $force_swagger_for_season) {
                            $emsg = method_exists($err_obj,'get_error_message') ? (string)$err_obj->get_error_message() : 'auth_error';
                            error_log('RFHITOS_AUTH_FAIL msg=' . $emsg . ' url=' . $url);
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                    set_transient($neg_key, $err_obj, 3 * MINUTE_IN_SECONDS);
                    return $err_obj;
                }
                $args['headers']['Authorization'] = ($bearer_lower ? 'bearer ' : 'Bearer ') . $token;
                $auth_src = 'token';
            }
            // Log del esquema usado si es el endpoint de temporada (no expone token) – solo en modo debug
            try {
                // Omitido log de scheme específico.
            } catch (\Throwable $e) { /* ignore */ }
        }

        // Si estamos en el endpoint de temporada conocido y vienen query params de debug (_rf_bust/_rf_auth), límpialos para replicar Swagger
        try {
            $pu2 = parse_url($url);
            $host2 = is_array($pu2) && !empty($pu2['host']) ? strtolower($pu2['host']) : '';
            $path2 = is_array($pu2) && !empty($pu2['path']) ? strtolower($pu2['path']) : '';
            $is_season_ep2 = (
                strpos($path2, '/ranking/getrankingpormodalidadportemporadaespglicko2') !== false ||
                strpos($path2, '/api/ranking/getrankingpormodalidadportemporadaespglicko2') !== false
            );
            if ($host2 === 'ranking.fefm.net' && $is_season_ep2) {
                if (isset($pu2['query']) && is_string($pu2['query']) && $pu2['query'] !== '') {
                    parse_str($pu2['query'], $qsarr);
                    if (isset($qsarr['_rf_bust']) || isset($qsarr['_rf_auth'])) {
                        unset($qsarr['_rf_bust'], $qsarr['_rf_auth']);
                        $new_qs = http_build_query($qsarr);
                        $rebuilt = $pu2['scheme'] . '://' . $pu2['host'] . (isset($pu2['port'])?(':' . $pu2['port']):'') . $pu2['path'];
                        if ($new_qs !== '') { $rebuilt .= '?' . $new_qs; }
                        if (isset($pu2['fragment'])) { $rebuilt .= '#' . $pu2['fragment']; }
                        $url = $rebuilt;
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Log de diagnóstico de cabeceras cuando emulamos Swagger para temporada – solo en modo debug
        try {
            // Eliminado log detallado de cabeceras emuladas.
        } catch (\Throwable $e) { /* ignore */ }

        // Bypass opcional de depuración: usar cURL directo para el endpoint de temporada de illozapatillo
        // Eliminado modo rawcurl.

    $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $err = $response->get_error_message();
            if (function_exists('rf_log')) { rf_log('HTTP ERROR (wp_remote_get)', ['url' => $url, 'auth' => $auth_required ? '1' : '0', 'error' => $err], 'error'); }
            $this->log_once('Error en la petición a la API. URL: ' . $url . ' Mensaje: ' . $err, 600);
            // Cache negativa corta para errores de red
            set_transient($neg_key, $response, 3 * MINUTE_IN_SECONDS);
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        // Debug/diagnóstico para Ranking: loguea código y pequeño extracto del body
        try {
            $is_ranking_endpoint = (stripos($url, '/ranking/') !== false);
            $debug_hitos_http = (
                (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') ||
                (isset($_GET['rf_debug_hitos_http']) && $_GET['rf_debug_hitos_http'] == '1')
            );
            if ($is_ranking_endpoint && $debug_hitos_http) {
                $snip = '';
                if (is_string($body) && $body !== '') {
                    $snip = preg_replace('/\s+/', ' ', $body);
                    if (strlen($snip) > 200) { $snip = substr($snip, 0, 200) . '…'; }
                }
                // No incluimos cabeceras sensibles; solo código y un snippet del cuerpo para diferenciar 200 vacíos vs 204/errores
                $auth_tag = $auth_required ? (' auth_src=' . $auth_src) : '';
                error_log('RFHITOS_HTTP code=' . $code . $auth_tag . ' url=' . $url . ' body_snip=' . $snip);
                // Logs específicos de temporada solo en modo debug explícito
                if ($debug_hitos_http) {
                    error_log('RFHITOS_SEASON_HTTP code=' . $code . ' url=' . $url . ' len=' . (is_string($body) ? strlen($body) : 0));
                    if (preg_match('#/ranking/getrankingpormodalidadportemporadaespglicko2/2/13#i', $path_l ?? strtolower(parse_url($url, PHP_URL_PATH) ?: ''))) {
                        error_log('RFHITOS_SEASON_HTTP_2_13 code=' . $code . ' body_snip=' . (is_string($body) ? substr(preg_replace('/\s+/', ' ', $body),0,200) . (strlen($body)>200?'…':'') : ''));
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore debug failures */ }

        // 204: sin contenido -> NO error; devolvemos null y no spameamos el log
        if ($code === 204 || $body === '' || $body === null) {
            if (function_exists('rf_log')) { rf_log('HTTP 204/EMPTY', ['url' => $url, 'code' => $code], 'info'); }
            // Cache negativa (10 min) para no reintentar en cada carga
            set_transient($neg_key, ['__no_content' => true], 10 * MINUTE_IN_SECONDS);
            return null;
        }

        // 2xx con body -> intentar json_decode
        if ($code >= 200 && $code < 300) {
            $decoded = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Fallback específico: si es el endpoint de temporada en illozapatillo y el resultado es array vacío, reintentar con cURL directo
                try {
                    $is_season_endpoint_ok = false;
                    $host_tmp = '';
                    $path_tmp = '';
                    $pu_tmp = @parse_url($url);
                    if (is_array($pu_tmp)) {
                        $host_tmp = isset($pu_tmp['host']) ? strtolower($pu_tmp['host']) : '';
                        $path_tmp = isset($pu_tmp['path']) ? strtolower($pu_tmp['path']) : '';
                        $is_season_endpoint_ok = (
                            strpos($path_tmp, '/ranking/getrankingpormodalidadportemporadaespglicko2') !== false ||
                            strpos($path_tmp, '/api/ranking/getrankingpormodalidadportemporadaespglicko2') !== false
                        );
                    }
                    $is_empty_array = is_array($decoded) && count($decoded) === 0;
                    $can_curl = function_exists('curl_init');
                    // Solo permitir el fallback cURL en modo debug explícito
                    $rf_debug_on_fb = (
                        (isset($_GET['rf_debug_hitos']) && $_GET['rf_debug_hitos'] == '1') ||
                        (isset($_GET['rf_debug_hitos_http']) && $_GET['rf_debug_hitos_http'] == '1')
                    );
                    if ($is_empty_array && $can_curl && $is_season_endpoint_ok && $host_tmp === 'illozapatillo.zapto.org' && $rf_debug_on_fb) {
                        // Construye cabeceras a partir de $args ya preparados (emulan Swagger)
                        $hdrs_out = [];
                        $ua_curl = isset($args['user-agent']) ? $args['user-agent'] : '';
                        if (!empty($args['headers']) && is_array($args['headers'])) {
                            foreach ($args['headers'] as $k => $v) { $hdrs_out[] = $k . ': ' . $v; }
                        }
                        $ch2 = curl_init($url);
                        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch2, CURLOPT_TIMEOUT, isset($args['timeout']) ? (int)$args['timeout'] : 30);
                        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                        if ($ua_curl !== '') { curl_setopt($ch2, CURLOPT_USERAGENT, $ua_curl); }
                        if (!empty($hdrs_out)) { curl_setopt($ch2, CURLOPT_HTTPHEADER, $hdrs_out); }
                        curl_setopt($ch2, CURLOPT_HEADER, false);
                        $body2 = curl_exec($ch2);
                        $code2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                        $cerr2 = curl_errno($ch2);
                        $cerrmsg2 = curl_error($ch2);
                        curl_close($ch2);
                        // Log del fallback solo en modo debug
                        try {
                            if ($rf_debug_on_fb) {
                                $snip2 = is_string($body2) ? substr(preg_replace('/\s+/', ' ', $body2), 0, 160) . ((strlen($body2) > 160) ? '…' : '') : '';
                                error_log('RFHITOS_SEASON_FALLBACK code=' . $code2 . ' url=' . $url . ' body_snip=' . $snip2 . ($cerr2 ? (' curl_err=' . $cerr2 . ' ' . $cerrmsg2) : ''));
                            }
                        } catch (\Throwable $e) {}
                        if ($code2 >= 200 && $code2 < 300 && is_string($body2) && $body2 !== '') {
                            $decoded2 = json_decode($body2);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                // Si el fallback trae datos no vacíos, úsalo
                                if (is_array($decoded2) && count($decoded2) > 0) {
                                    return $decoded2;
                                }
                                // También contemplar objetos con items aunque este endpoint suele devolver array root
                                if (is_object($decoded2)) {
                                    $items2 = [];
                                    if (isset($decoded2->items) && is_array($decoded2->items)) { $items2 = $decoded2->items; }
                                    elseif (isset($decoded2->data->items) && is_array($decoded2->data->items)) { $items2 = $decoded2->data->items; }
                                    elseif (isset($decoded2->result->items) && is_array($decoded2->result->items)) { $items2 = $decoded2->result->items; }
                                    elseif (isset($decoded2->ranking->items) && is_array($decoded2->ranking->items)) { $items2 = $decoded2->ranking->items; }
                                    if (!empty($items2)) { return $decoded2; }
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) { /* noop fallback errors */ }
                if (function_exists('rf_log')) { rf_log('HTTP OK', ['url' => $url, 'code' => $code, 'auth' => $auth_required ? '1' : '0'], 'debug'); }
                return $decoded; // contrato original: objeto/array según API
            }
            // JSON inválido
            $err = new WP_Error('json_error', 'Respuesta JSON inválida');
            if (function_exists('rf_log')) {
                $snip = is_string($body) ? substr(preg_replace('/\s+/', ' ', $body), 0, 160) : '';
                rf_log('JSON DECODE ERROR', ['url' => $url, 'code' => $code, 'body_snip' => $snip], 'warning');
            }
            $this->log_once('Error en la petición a la API. URL: ' . $url . ' Código: ' . $code . ' Body: ' . $body, 600);
            set_transient($neg_key, $err, 5 * MINUTE_IN_SECONDS);
            return $err;
        }

        // 5xx: backend roto (NRE, etc.) -> WP_Error. Para endpoint de temporada: backoff + log condensado
        if ($code >= 500) {
            if (function_exists('rf_log')) { rf_log('HTTP 5xx', ['url' => $url, 'code' => $code], 'error'); }
            $err_msg = $this->extract_message_from_body($body) ?: 'Error 5xx en API';
            if ($is_season_ep0) {
                // Marca backoff temporal por host para evitar martilleo
                if ($season_err_key !== '') { set_transient($season_err_key, 1, 2 * MINUTE_IN_SECONDS); }
                // Log único y corto por host+code (sin URL ni body)
                $this->log_once('RFHITOS_SEASON_5XX host=' . $host_l . ' code=' . $code, 180);
                $err = new WP_Error('http_error', $err_msg);
                set_transient($neg_key, $err, 5 * MINUTE_IN_SECONDS);
                return $err;
            } else {
                $line = 'Error en la petición a la API. URL: ' . $url . ' Código: ' . $code . ' Body: ' . $body;
                $this->log_once($line, 600);
                $err = new WP_Error('http_error', $err_msg);
                set_transient($neg_key, $err, 15 * MINUTE_IN_SECONDS);
                return $err;
            }
        }

        // Otros (3xx, 4xx): WP_Error, log deduplicado + cache negativa
        $line = 'Error en la petición a la API. URL: ' . $url . ' Código: ' . $code . ' Body: ' . $body;
        $this->log_once($line, 600);
        $err = new WP_Error('http_error', 'La API devolvió un error (código ' . $code . ').');
        set_transient($neg_key, $err, 10 * MINUTE_IN_SECONDS);
        return $err;
    }

    protected function get_valid_token() {
        // 1) Si existe constante en wp-config, SIEMPRE priorizar y sincronizar el transient si cambia
        if (defined('FUTBOLIN_API_TOKEN')) {
            $tok = (string) constant('FUTBOLIN_API_TOKEN');
            if ($tok !== '') {
                $current = get_transient('futbolin_api_token');
                if ($current !== $tok) { set_transient('futbolin_api_token', $tok, 50 * MINUTE_IN_SECONDS); }
                return $tok;
            }
        }
        // 2) Overrides por opciones de plugin (si existen), también sobreescriben el transient
        $cfg = get_option('ranking_api_config', array());
        if (is_array($cfg) && !empty($cfg['token_override'])) {
            $tok = (string) $cfg['token_override'];
            if ($tok !== '') { set_transient('futbolin_api_token', $tok, 50 * MINUTE_IN_SECONDS); return $tok; }
        }
        $opts = get_option('mi_plugin_futbolin_options', array());
        if (is_array($opts) && !empty($opts['api_token_override'])) {
            $tok = (string) $opts['api_token_override'];
            if ($tok !== '') { set_transient('futbolin_api_token', $tok, 50 * MINUTE_IN_SECONDS); return $tok; }
        }
        // 3) Si no hay overrides, usar el transient si existe; en caso contrario, login
        $token = get_transient('futbolin_api_token');
        if ($token) { return $token; }
        return $this->login();
    }

    /**
     * Devuelve un fingerprint del token actual sin exponerlo (para versionar caches).
     * Se basa en FUTBOLIN_API_TOKEN si está definido; si no, en el transient/override guardado.
     */
    public function get_token_fingerprint() {
        try {
            $tok = '';
            if (defined('FUTBOLIN_API_TOKEN')) {
                $tok = (string) constant('FUTBOLIN_API_TOKEN');
            } else {
                $tok = get_transient('futbolin_api_token');
                if (!$tok) {
                    $cfg = get_option('ranking_api_config', array());
                    if (is_array($cfg) && !empty($cfg['token_override'])) { $tok = (string)$cfg['token_override']; }
                    if (!$tok) {
                        $opts = get_option('mi_plugin_futbolin_options', array());
                        if (is_array($opts) && !empty($opts['api_token_override'])) { $tok = (string)$opts['api_token_override']; }
                    }
                }
            }
            if (!is_string($tok) || $tok === '') { return ''; }
            // Usamos los últimos 8 hex de sha1 del token
            return substr(sha1($tok), -8);
        } catch (\Throwable $e) { return ''; }
    }

    protected function login() {
        $base = rtrim($this->base_api_url,'/');
        // Candidatos de ruta de login (diferentes mayúsculas/minúsculas y ubicaciones)
        $login_candidates = [
            $base . '/Seguridad/login',
            $base . '/Seguridad/Login',
            $base . '/seguridad/login',
            $base . '/Auth/login',
            $base . '/Auth/Login',
            // fallback si base acaba en /api, intentar quitar /api
        ];
        if (substr($base,-4)==='/api') {
            $base_no_api = substr($base,0,-4);
            $login_candidates[] = rtrim($base_no_api,'/') . '/api/Seguridad/login';
            $login_candidates[] = rtrim($base_no_api,'/') . '/Seguridad/login';
        }
        // Recolectar credenciales
        if (defined('FUTBOLIN_API_USER') && defined('FUTBOLIN_API_PASS')) {
            $user = (string) constant('FUTBOLIN_API_USER');
            $pass = (string) constant('FUTBOLIN_API_PASS');
        } else {
            $user=''; $pass='';
            $cfg = get_option('ranking_api_config', array());
            if (is_array($cfg)) { $user = !empty($cfg['username']) ? $cfg['username'] : $user; $pass = !empty($cfg['password']) ? $cfg['password'] : $pass; }
            if (!$user || !$pass) {
                $opts = get_option('mi_plugin_futbolin_options', array());
                if (is_array($opts)) { $user = !empty($opts['api_username']) ? $opts['api_username'] : $user; $pass = !empty($opts['api_password']) ? $opts['api_password'] : $pass; }
            }
            if (!$user || !$pass) { error_log('Error crítico: Credenciales de API no configuradas.'); return new WP_Error('config_error','Credenciales de API no configuradas.'); }
        }
        $strategies = [
            ['type'=>'json','payload'=>['usuario'=>$user,'password'=>$pass]],
            ['type'=>'json','payload'=>['Usuario'=>$user,'Password'=>$pass]],
            ['type'=>'json','payload'=>['username'=>$user,'password'=>$pass]],
            ['type'=>'form','payload'=>['usuario'=>$user,'password'=>$pass]],
        ];
        $meta = ['attempts'=>[], 'success'=>false, 'token_key'=>null,'http_code'=>null,'strategy'=>null,'ts'=>time()];
        foreach ($login_candidates as $login_url) {
            foreach ($strategies as $s) {
                $args = [
                    'method'    => 'POST',
                    'timeout'   => 18,
                    'sslverify' => false,
                    'headers'   => [
                        'Accept'=>'application/json',
                        'X-Requested-With'=>'XMLHttpRequest',
                        'Origin'=> preg_replace('#/+$#','', preg_replace('#^(https?://[^/]+).*$#','$1',$login_url)),
                        'Referer'=> preg_replace('#/+$#','', preg_replace('#^(https?://[^/]+).*$#','$1',$login_url)) . '/swagger'
                    ]
                ];
                if ($s['type']==='json') {
                    $args['headers']['Content-Type']='application/json';
                    $args['body']= json_encode($s['payload']);
                } else {
                    $args['headers']['Content-Type']='application/x-www-form-urlencoded';
                    $args['body']= http_build_query($s['payload']);
                }
                $resp = wp_remote_post($login_url, $args);
                $code = is_wp_error($resp)?0:wp_remote_retrieve_response_code($resp);
                $meta['attempts'][] = ['login_url'=>$login_url,'strategy'=>$s['type'],'keys'=>implode(',', array_keys($s['payload'])),'code'=>$code];
                if (is_wp_error($resp) || $code !== 200) { continue; }
                $body_raw = wp_remote_retrieve_body($resp);
                $meta['http_code'] = $code;
            // Guardar copia para depuración si falla extracción
            if ($body_raw) {
                @file_put_contents(trailingslashit(wp_get_upload_dir()['basedir']).'ranking-futbolin-cache/login_raw.json', $body_raw);
            }
            $decoded = json_decode($body_raw, true);
            if (is_array($decoded)) {
                // Buscar tokens posibles recursivamente (profundidad limitada)
                $candidates_keys = ['token','accessToken','access_token','bearer','jwt','Token','AccessToken','Bearer'];
                $found = null;
                $stack = [$decoded]; $depth=0; $maxDepth=4;
                while ($stack && $found===null && $depth < 500) {
                    $cur = array_pop($stack); $depth++;
                    if (is_array($cur)) {
                        foreach ($candidates_keys as $k) {
                            if (isset($cur[$k]) && is_string($cur[$k]) && $cur[$k] !== '') { $found = ['val'=>$cur[$k],'key'=>$k]; break; }
                        }
                        if ($found) break;
                        foreach ($cur as $v) { if (is_array($v)) $stack[]=$v; }
                    }
                }
                if ($found) {
                    set_transient('futbolin_api_token', $found['val'], 55 * MINUTE_IN_SECONDS);
                    $meta['success']=true; $meta['token_key']=$found['key']; $meta['strategy']=$s['type'];
                    set_transient('futbolin_api_token_meta', $meta, 60 * MINUTE_IN_SECONDS);
                    if (function_exists('rf_log')) { rf_log('LOGIN OK', ['key'=>$found['key'],'strategy'=>$s['type']], 'info'); }
                    return $found['val'];
                }
            }
                // Si llegó aquí, seguir probando siguiente estrategia en otro login_url
            }
        }
        set_transient('futbolin_api_token_meta', $meta, 20 * MINUTE_IN_SECONDS);
        if (function_exists('rf_log')) { rf_log('LOGIN FAILED ALL', $meta, 'error'); }
        return new WP_Error('login_failed','No se pudo autenticar con la API (todas las estrategias fallaron).');
    }

    // --- Helpers privados ---

    /** Clave de cache negativa por URL exacta */
    private function neg_cache_key(string $url): string {
        return 'futb_neg_' . md5($url);
    }

    /** Log deduplicado por línea (evita spam en ráfagas). TTL configurable. */
    private function log_once(string $line, int $ttl_seconds = 600): void {
        $key = 'futb_log_' . md5($line);
        if (get_transient($key) !== false) return;
        set_transient($key, 1, $ttl_seconds);
        error_log($line);
    }

    /** Intenta extraer "message" del JSON de error (si existe) */
    private function extract_message_from_body($body): ?string {
        if (!is_string($body) || $body === '') return null;
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            if (!empty($json['message'])) return (string)$json['message'];
            if (!empty($json['error']))   return (string)$json['error'];
        }
        return null;
    }

    // get_campeones_espania() se redefine más abajo con caché positiva de larga duración
    

    /** ===== Cache helpers: dataset versioning + chunked storage to avoid max_allowed_packet ===== */
    private function rf_get_dataset_cache_version() {
        static $ver = null;
        if ($ver !== null) return $ver;
        $v = get_option('rf_dataset_ver');
        if (!$v) { $v = '1'; update_option('rf_dataset_ver', $v, false); }
        $ver = (string)$v;
        return $ver;
    }
    private function rf_build_cache_key($base_key) {
        return $base_key . ':v' . $this->rf_get_dataset_cache_version();
    }
    private function cache_get_large($key) {
        // Preferir object cache si existe
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache() && function_exists('wp_cache_get')) {
            $v = wp_cache_get($key, 'futbolin');
            if ($v !== false) return $v;
        }
        // Transients en shards
        $idx = get_transient($key . ':idx');
        if ($idx !== false && is_array($idx) && isset($idx['chunks'])) {
            $chunks = (int)$idx['chunks'];
            if ($chunks <= 0 || $chunks > 2000) return false;
            $blob = '';
            for ($i = 0; $i < $chunks; $i++) {
                $part = get_transient($key . ':' . $i);
                if ($part === false) return false;
                $blob .= (string)$part;
            }
            $data = base64_decode($blob);
            if ($data === false) return false;
            $un = function_exists('gzuncompress') ? @gzuncompress($data) : $data;
            if ($un === false) return false;
            if (function_exists('maybe_unserialize')) { $val = maybe_unserialize($un); } else { $val = @unserialize($un); }
            return $val;
        }
        return false;
    }
    private function cache_set_large($key, $value, $ttl) {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache() && function_exists('wp_cache_set')) {
            wp_cache_set($key, $value, 'futbolin', $ttl);
            return;
        }
        if (function_exists('maybe_serialize')) { $ser = maybe_serialize($value); } else { $ser = serialize($value); }
        $gz  = function_exists('gzcompress') ? gzcompress($ser, 3) : $ser;
        $b64 = base64_encode($gz);
        $max = 900000; // ~0.9MB por fragmento
        $len = strlen($b64);
        $chunks = (int)ceil($len / $max);
        for ($i = 0; $i < $chunks; $i++) {
            $slice = substr($b64, $i * $max, $max);
            set_transient($key . ':' . $i, $slice, $ttl);
        }
        set_transient($key . ':idx', ['chunks' => $chunks], $ttl);
    }


    private function cache_delete_large($key) {
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache() && function_exists('wp_cache_delete')) {
            wp_cache_delete($key, 'futbolin');
            return;
        }
        $idx = get_transient($key . ':idx');
        if ($idx && is_array($idx) && isset($idx['chunks'])) {
            $chunks = (int)$idx['chunks'];
            for ($i = 0; $i < $chunks; $i++) {
                delete_transient($key . ':' . $i);
            }
        }
        delete_transient($key . ':idx');
    }

    private function partidos_meta_key($jugador_id) {
        return $this->rf_build_cache_key('rf:p:partidos_meta:' . intval($jugador_id));
    }
    private function read_partidos_meta($jugador_id) {
        $k = $this->partidos_meta_key($jugador_id);
        $m = get_transient($k);
        return (is_array($m)) ? $m : ['last_check'=>0,'latest_sig'=>null];
    }
    private function write_partidos_meta($jugador_id, $meta, $ttl_days = 120) {
        set_transient($this->partidos_meta_key($jugador_id), $meta, $ttl_days * DAY_IN_SECONDS);
    }

    /** Devuelve una firma ligera del "último partido" vía endpoint paginado con pageSize=1 */
    private function probe_latest_partido_sig($jugador_id) {
        $candidates = [
            $this->base_api_url . "/Jugador/GetJugadorPartidosPag/{$jugador_id}?page=1&pageSize=1",
            $this->base_api_url . "/Jugador/{$jugador_id}/GetJugadorPartidosPag?page=1&pageSize=1",
        ];
        foreach ($candidates as $api_url) {
            $decoded = $this->do_request($api_url, true);
            if (!$decoded || is_wp_error($decoded)) { continue; }
            $items = [];
            if (is_array($decoded)) {
                $is_assoc = array_keys($decoded) !== range(0, count($decoded) - 1);
                if (!$is_assoc) { $items = $decoded; }
                else {
                    if (isset($decoded['items']) && is_array($decoded['items'])) { $items = $decoded['items']; }
                    elseif (isset($decoded['data']['items']) && is_array($decoded['data']['items'])) { $items = $decoded['data']['items']; }
                    elseif (isset($decoded['result']['items']) && is_array($decoded['result']['items'])) { $items = $decoded['result']['items']; }
                    elseif (isset($decoded['ranking']['items']) && is_array($decoded['ranking']['items'])) { $items = $decoded['ranking']['items']; }
                }
            }
            if (!empty($items)) {
                $it = is_array($items[0]) ? (object)$items[0] : (object)$items[0];
                $aliases_date = ['fecha','fechaPartido','playedAt','matchDate','fechaUTC','fechaLocal'];
                $aliases_id   = ['registroId','partidoId','id'];
                $d = null;
                foreach ($aliases_date as $a) { if (isset($it->$a)) { $d = (string)$it->$a; break; } }
                $pid = null;
                foreach ($aliases_id as $a) { if (isset($it->$a)) { $pid = (string)$it->$a; break; } }
                $torneo = isset($it->torneoId) ? (string)$it->torneoId : (isset($it->torneo) ? (string)$it->torneo : '');
                $fase = isset($it->fase) ? (string)$it->fase : '';
                // Firma estable con lo que tengamos
                return sha1(json_encode([$pid,$d,$torneo,$fase]));
            }
        }
        return null;
    }

    /** Revalida el cache si el "último partido" no coincide (chequeo oportunista cada 24h) */
    private function maybe_revalidate_cached_partidos($jugador_id, $ck, &$cached_items) {
        $meta = $this->read_partidos_meta($jugador_id);
        $now = time();
        if ($now - intval($meta['last_check']) < 24 * HOUR_IN_SECONDS) {
            return; // ya comprobado hace poco
        }
        // Firma del último partido en cache
        $last = null;
        if (is_array($cached_items) && !empty($cached_items)) {
            // intenta hallar el más reciente por fecha; si no, usa el último elemento
            $aliases_date = ['fecha','fechaPartido','playedAt','matchDate','fechaUTC','fechaLocal'];
            $latest_ts = 0; $latest = end($cached_items);
            foreach ($cached_items as $it) {
                $it = is_array($it) ? (object)$it : $it;
                foreach ($aliases_date as $a) {
                    if (isset($it->$a)) {
                        $ts = is_numeric($it->$a) ? intval($it->$a) : strtotime((string)$it->$a);
                        if ($ts && $ts > $latest_ts) { $latest_ts = $ts; $latest = $it; }
                    }
                }
            }
            $it = is_array($latest) ? (object)$latest : $latest;
            $pid = isset($it->registroId) ? (string)$it->registroId : (isset($it->partidoId) ? (string)$it->partidoId : (isset($it->id) ? (string)$it->id : ''));
            $d   = isset($it->fecha) ? (string)$it->fecha : (isset($it->fechaPartido) ? (string)$it->fechaPartido : (isset($it->matchDate) ? (string)$it->matchDate : ''));
            $torneo = isset($it->torneoId) ? (string)$it->torneoId : (isset($it->torneo) ? (string)$it->torneo : '');
            $fase = isset($it->fase) ? (string)$it->fase : '';
            $cache_sig = sha1(json_encode([$pid,$d,$torneo,$fase]));
        } else {
            $cache_sig = null;
        }
        $api_sig = $this->probe_latest_partido_sig($jugador_id);
        $meta['last_check'] = $now;
        if ($api_sig && $cache_sig && $api_sig !== $cache_sig) {
            // Mismatch -> invalidar y forzar refetch
            $this->cache_delete_large($ck);
            $meta['latest_sig'] = $api_sig;
        } elseif ($api_sig) {
            $meta['latest_sig'] = $api_sig;
        }
        $this->write_partidos_meta($jugador_id, $meta);
    }

    // Firma de la implementación (para diagnóstico en logs)
    public function rf_client_sig() {
        return 'Futbolin_API_Client[ranking-futbolin/includes] hdrs=1 bearerLower=1 negCacheVer=1';
    }

}
