<?php
if (!defined('ABSPATH')) exit;

/**
 * Servicio: Mejor clasificación histórica a final de temporada por modalidad (ELO y Anual)
 * - Itera temporadas [min..max] y calcula la mejor posición alcanzada por jugador
 * - Extrae puntos/rating en el momento de esa mejor posición
 * - Caché por jugador+modalidad+tipo (elo|annual), diferenciada por host y token fingerprint
 */
class Futbolin_Best_Service {
    private $api;

    public function __construct($apiClient = null) {
        $this->api = $apiClient ?: (class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null);
    }

    private function dataset_ver(): string {
        if (class_exists('Futbolin_Player_Cache_Service')) {
            return (string) \Futbolin_Player_Cache_Service::dataset_ver();
        }
        $v = get_option('rf_dataset_ver');
        return $v ? (string)$v : '1';
    }

    private function host_fingerprint(): array {
        $host = '';
        try {
            if ($this->api && method_exists($this->api, 'get_base_api_url')) {
                $pu = parse_url((string)$this->api->get_base_api_url());
                $host = is_array($pu) && isset($pu['host']) ? strtolower($pu['host']) : '';
            }
        } catch (\Throwable $e) { $host = ''; }
        $tfp = '';
        try { if ($this->api && method_exists($this->api, 'get_token_fingerprint')) { $tfp = (string)$this->api->get_token_fingerprint(); } } catch (\Throwable $e) {}
        return [$host ?: 'h', $tfp ?: 't'];
    }

    private function cache_key(string $type, int $playerId, int $modId): string {
        list($host, $tfp) = $this->host_fingerprint();
        $ver = $this->dataset_ver();
        // h2: namespace histórico que (1) excluye la temporada en vigor y (2) usa posición por índice de lista (ignora campo 'posicion')
        return sprintf('rf:best:h2:%s:host:%s:tfp:%s:v%s:p:%d:m:%d', $type, $host, $tfp, $ver, $playerId, $modId);
    }

    private function unwrap_items($data): array {
        if (!$data) return [];
        if (is_array($data)) {
            $is_assoc = array_keys($data) !== range(0, count($data) - 1);
            if (!$is_assoc) return $data;
            if (isset($data['items']) && is_array($data['items'])) return $data['items'];
            if (isset($data['data']['items']) && is_array($data['data']['items'])) return $data['data']['items'];
            if (isset($data['result']['items']) && is_array($data['result']['items'])) return $data['result']['items'];
            if (isset($data['ranking']['items']) && is_array($data['ranking']['items'])) return $data['ranking']['items'];
            foreach ($data as $v) { if (is_array($v) && array_keys($v) === range(0, count($v) - 1)) return $v; }
            return [];
        }
        if (is_object($data)) {
            if (isset($data->items) && is_array($data->items)) return $data->items;
            if (isset($data->data) && is_object($data->data) && isset($data->data->items) && is_array($data->data->items)) return $data->data->items;
            if (isset($data->result) && is_object($data->result) && isset($data->result->items) && is_array($data->result->items)) return $data->result->items;
            if (isset($data->ranking) && is_object($data->ranking) && isset($data->ranking->items) && is_array($data->ranking->items)) return $data->ranking->items;
        }
        return [];
    }

    private function extract_player_id($row): int {
        if (!$row) return 0;
        $o = is_object($row) ? $row : (object)$row;
        foreach (['jugadorId','JugadorId','idJugador','IdJugador','playerId','PlayerId','id','Id'] as $k) {
            if (isset($o->$k) && is_numeric($o->$k)) return (int)$o->$k;
        }
        if (isset($o->jugador) && (is_object($o->jugador) || is_array($o->jugador))) {
            $j = (object)$o->jugador; foreach (['jugadorId','JugadorId','id','Id'] as $ik) { if (isset($j->$ik) && is_numeric($j->$ik)) return (int)$j->$ik; }
        }
        return 0;
    }

    private function extract_points($row): int {
        $o = is_object($row) ? $row : (object)$row;
        foreach (['puntos','Puntos','elo','Elo','rating','Rating','glicko','Glicko','puntuacion','Puntuacion'] as $pk) {
            if (isset($o->$pk) && is_numeric($o->$pk)) return (int)round((float)$o->$pk);
        }
        return 0;
    }

    private function max_season(): int {
        return defined('FUTBOLIN_MAX_SEASON_ORDINAL') ? (int)constant('FUTBOLIN_MAX_SEASON_ORDINAL') : 14;
    }

    /** Mejor histórica ELO por modalidad */
    public function get_best_elo_for_modality(int $playerId, int $modId, int $minSeason = 1, ?int $maxSeason = null) {
        if (!$this->api || $playerId <= 0 || $modId <= 0) return null;
    // Excluir la temporada en vigor (la última) del histórico
    $max = $maxSeason ?: $this->max_season();
    if ($max > 1) { $max -= 1; }
    if ($max < $minSeason) { $max = $minSeason; }
        $ck = $this->cache_key('elo', $playerId, $modId);
        if (function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) {
            $cached = get_transient($ck);
            if ($cached !== false && is_array($cached)) return $cached;
        }

        $best = null; // ['pos'=>int, 'season'=>int, 'points'=>int]
        for ($t = $minSeason; $t <= $max; $t++) {
            // Endpoint ELO por temporada (con caché positiva propia)
            if (!method_exists($this->api, 'get_ranking_por_modalidad_temporada_esp_g2_cached_official')) { continue; }
            $resp = $this->api->get_ranking_por_modalidad_temporada_esp_g2_cached_official($modId, $t);
            $items = $this->unwrap_items($resp);
            if (empty($items)) continue;
            foreach ($items as $idx => $row) {
                $jid = $this->extract_player_id($row);
                if ($jid !== $playerId) continue;
                // Posición basada SIEMPRE en el índice de aparición en el listado (1-indexed), ignorando 'posicion' del dataset
                $pos = (int)$idx + 1;
                $pts = $this->extract_points($row);
                if (!$best || $pos < $best['pos']) {
                    $best = ['pos' => $pos, 'season' => $t, 'points' => $pts];
                }
                // Empate: mantenemos la primera vez conseguida (ya iteramos ascendente), así que no reemplazamos en igualdad
                break; // encontrado en esta temporada
            }
            if ($best && $best['pos'] === 1) { break; }
        }
    if ($best && (function_exists('rf_cache_enabled') ? rf_cache_enabled() : true)) { set_transient($ck, $best, 180 * DAY_IN_SECONDS); }
        return $best;
    }

    /** Mejor histórica ANUAL por modalidad */
    public function get_best_annual_for_modality(int $playerId, int $modId, int $minSeason = 1, ?int $maxSeason = null) {
        if (!$this->api || $playerId <= 0 || $modId <= 0) return null;
    // Excluir la temporada en vigor también en ANUAL
    $max = $maxSeason ?: $this->max_season();
    if ($max > 1) { $max -= 1; }
    if ($max < $minSeason) { $max = $minSeason; }
        $ck = $this->cache_key('annual', $playerId, $modId);
        if (function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) {
            $cached = get_transient($ck);
            if ($cached !== false && is_array($cached)) return $cached;
        }

        $best = null;
        for ($t = $minSeason; $t <= $max; $t++) {
            if (!method_exists($this->api, 'get_ranking_por_modalidad_temporada_por_posicion_esp')) { continue; }
            $resp = $this->api->get_ranking_por_modalidad_temporada_por_posicion_esp($modId, $t);
            $items = $this->unwrap_items($resp);
            if (empty($items)) continue;
            foreach ($items as $idx => $row) {
                $jid = $this->extract_player_id($row);
                if ($jid !== $playerId) continue;
                // Posición basada SIEMPRE en el índice de aparición en el listado (1-indexed), ignorando 'posicion' del dataset
                $pos = (int)$idx + 1;
                $pts = $this->extract_points($row);
                if (!$best || $pos < $best['pos']) {
                    $best = ['pos' => $pos, 'season' => $t, 'points' => $pts];
                }
                // Empate: mantener la primera vez conseguida (iteración ascendente)
                break;
            }
            if ($best && $best['pos'] === 1) { break; }
        }
    if ($best && (function_exists('rf_cache_enabled') ? rf_cache_enabled() : true)) { set_transient($ck, $best, 180 * DAY_IN_SECONDS); }
        return $best;
    }
}
