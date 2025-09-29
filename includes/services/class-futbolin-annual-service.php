<?php
if (!defined('ABSPATH')) exit;

/**
 * Servicio: Annual (Ranking anual)
 * - Responsabilidad: resolver temporada vigente con datos y modalidades con datos.
 * - Endpoint de datos: GET /api/Ranking/GetRankingPorModalidadPorTemporadaPorPosicionESP/{ModalidadId}/{TemporadaId}
 * - Estricto: sin discovery alternativo, cabeceras emuladas si procede (delegado al API Client).
 */
class Futbolin_Annual_Service {

    private $api;

    public function __construct($apiClient = null) {
        $this->api = $apiClient ?: (class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null);
    }

    /**
     * Detecta la última temporada con datos válida para un conjunto de modalidades candidatas.
     * - Estrategia: probar desde clamp superior hacia abajo hasta encontrar datos (>0 items) en alguna modalidad.
     * - Respeta clamp global FUTBOLIN_MAX_SEASON_ORDINAL si existe; fallback 14.
     * - Limita modalidades por $candidateModalities (ids).
     */
    public function detect_last_season_with_data(array $candidateModalities = [2,1], int $maxSeasonFallback = 14): int {
        if (!$this->api) return $maxSeasonFallback;
        $maxOrd = defined('FUTBOLIN_MAX_SEASON_ORDINAL') ? (int)constant('FUTBOLIN_MAX_SEASON_ORDINAL') : $maxSeasonFallback;
        $minOrd = 1;
        // Cache por host para evitar pruebas repetidas
        $ck = $this->cache_key('rf:annual:last_season');
    $cached = (function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('get_transient') ? get_transient($ck) : false;
    if ($cached !== false && is_numeric($cached)) {
            return (int)$cached;
        }

        $found = $maxSeasonFallback;
        for ($t = $maxOrd; $t >= $minOrd; $t--) {
            foreach ($candidateModalities as $mod) {
                $resp = $this->api->get_ranking_por_modalidad_temporada_por_posicion_esp($mod, $t);
                if ($this->count_items($resp) > 0) {
                    $found = $t;
                    break 2;
                }
            }
        }
    if ((function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('set_transient')) { set_transient($ck, $found, 6 * HOUR_IN_SECONDS); }
        return $found;
    }

    /**
     * Devuelve las modalidades del array dado que efectivamente tienen datos para la temporada.
     * Retorna en el orden del array de entrada.
     */
    public function filter_modalities_with_data(int $temporadaId, array $candidateModalities = [2,1]): array {
        if (!$this->api || $temporadaId <= 0) return [];
        $ck = $this->cache_key('rf:annual:mods:' . $temporadaId);
    $cached = (function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('get_transient') ? get_transient($ck) : false;
    if ($cached !== false && is_array($cached)) return $cached;

        $out = [];
        foreach ($candidateModalities as $mod) {
            $resp = $this->api->get_ranking_por_modalidad_temporada_por_posicion_esp($mod, $temporadaId);
            if ($this->count_items($resp) > 0) {
                $out[] = (int)$mod;
            }
        }
    if ((function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('set_transient')) { set_transient($ck, $out, 6 * HOUR_IN_SECONDS); }
        return $out;
    }

    /**
     * Carga y devuelve el contenedor de ranking anual para una modalidad concreta y temporada dada.
     * Usa estrictamente el endpoint por posición.
     */
    public function get_annual_ranking_for(int $modalidadId, int $temporadaId) {
        if (!$this->api || $modalidadId <= 0 || $temporadaId <= 0) return [];
        return $this->api->get_ranking_por_modalidad_temporada_por_posicion_esp($modalidadId, $temporadaId);
    }

    /** Cuenta items de un contenedor/array/objeto de forma tolerante. */
    private function count_items($resp): int {
        if (!$resp || is_wp_error($resp)) return 0;
        if (is_array($resp)) {
            $is_assoc = array_keys($resp) !== range(0, count($resp) - 1);
            if (!$is_assoc) return count($resp);
            if (isset($resp['items']) && is_array($resp['items'])) return count($resp['items']);
            if (isset($resp['data']['items']) && is_array($resp['data']['items'])) return count($resp['data']['items']);
            if (isset($resp['result']['items']) && is_array($resp['result']['items'])) return count($resp['result']['items']);
            if (isset($resp['ranking']['items']) && is_array($resp['ranking']['items'])) return count($resp['ranking']['items']);
            return 0;
        }
        if (is_object($resp)) {
            if (isset($resp->items) && is_array($resp->items)) return count($resp->items);
            if (isset($resp->data) && is_object($resp->data) && isset($resp->data->items) && is_array($resp->data->items)) return count($resp->data->items);
            if (isset($resp->result) && is_object($resp->result) && isset($resp->result->items) && is_array($resp->result->items)) return count($resp->result->items);
            if (isset($resp->ranking) && is_object($resp->ranking) && isset($resp->ranking->items) && is_array($resp->ranking->items)) return count($resp->ranking->items);
        }
        return 0;
    }

    /** Construye una clave de cache estable diferenciada por host de la API base. */
    private function cache_key(string $suffix): string {
        try {
            $base = $this->api && method_exists($this->api, 'get_base_api_url') ? (string)$this->api->get_base_api_url() : '';
            $host = '';
            if ($base !== '') {
                $p = parse_url($base);
                $host = is_array($p) && !empty($p['host']) ? strtolower($p['host']) : '';
            }
            return ($host ? ($host . ':') : '') . $suffix;
        } catch (\Throwable $e) {
            return $suffix;
        }
    }
}
