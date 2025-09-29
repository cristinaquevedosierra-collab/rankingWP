<?php
if (!defined('ABSPATH')) exit;

/**
 * Servicio de generación de rankings y utilidades de podio.
 * Implementación limpia que sustituye un archivo previamente corrupto.
 */
class Futbolin_Rankgen_Service {

    const JOB_PREFIX  = 'futb_rankgen_job_';
    const CACHE_PREFIX = 'futb_rankgen_cache_';
    const SETS_OPTION = 'futb_rankgen_sets';
    private static $mem_transients = array();
    private static $mem_options = array();

    public static function start_job($slug, $set = array()) {
        $slug = self::wp_sanitize_title($slug);
        if (!$slug) return new WP_Error('bad_slug','Slug vacío');

        if (empty($set)) {
            // Intentar leer del nuevo option; fallback al antiguo si vacío
            $sets = self::wp_get_option(self::SETS_OPTION, array());
            if (!is_array($sets) || !count($sets)) {
                $sets = self::wp_get_option('futb_rankgen_drafts', array());
            }
            if (isset($sets[$slug]) && is_array($sets[$slug])) {
                $set = $sets[$slug];
            }
        }

    $set = is_array($set) ? $set : array();
    $set['scope']               = isset($set['scope']) ? strtoupper(self::wp_sanitize_text($set['scope'])) : 'ESP';
        $set['modalidades']         = isset($set['modalidades']) ? (array)$set['modalidades'] : array('1','2');
        $set['top_n']               = isset($set['top_n']) ? max(1, intval($set['top_n'])) : 25;
        $set['min_partidos']        = isset($set['min_partidos']) ? max(0, intval($set['min_partidos'])) : 0;
        $set['min_competiciones']   = isset($set['min_competiciones']) ? max(0, intval($set['min_competiciones'])) : 0;
        $set['include_liguilla']    = empty($set['include_liguilla']) ? '' : '1';
        $set['include_cruces']      = empty($set['include_cruces']) ? '' : '1';
    $set['sort_field']          = isset($set['sort_field']) ? self::wp_sanitize_key($set['sort_field']) : 'win_rate_partidos';
        $set['sort_dir']            = (isset($set['sort_dir']) && strtolower($set['sort_dir']) === 'asc') ? 'asc' : 'desc';
    $set['temporadaId']         = isset($set['temporadaId']) ? self::wp_sanitize_text($set['temporadaId']) : '';
    $set['competicionIds']      = isset($set['competicionIds']) ? array_map('intval', (array)$set['competicionIds']) : array();
    $set['torneoIds']           = isset($set['torneoIds']) ? array_map('intval', (array)$set['torneoIds']) : array();
    $set['torneos_all']         = !empty($set['torneos_all']) ? '1' : '';
    $set['tipos_comp']          = isset($set['tipos_comp']) ? (array)$set['tipos_comp'] : array();
    // Filtros avanzados (HOF/Globales): mínimos y condiciones
    $set['min_victorias']       = isset($set['min_victorias']) ? max(0, intval($set['min_victorias'])) : 0; // para HOF y similares
    $set['min_partidas']        = isset($set['min_partidas']) ? max(0, intval($set['min_partidas'])) : 0;   // para globales/club 500
    $set['require_campeonato']  = !empty($set['require_campeonato']) ? '1' : '';

    $candidates = self::discover_candidates($set);
    if (self::is_wp_error($candidates)) return $candidates;
        $total = count($candidates);

        $job = array(
            'slug'      => $slug,
            'set'       => $set,
            'players'   => $candidates,
            'index'     => 0,
            'results'   => array(),
            'started'   => self::wp_current_time_mysql(),
            'finished'  => '',
            'errors'    => array(),
            'total'     => $total,
            'batch'     => 5,
        );
    self::wp_set_transient(self::JOB_PREFIX.$slug, $job, self::sec_hour());
        return array('total'=>$total);
    }

    public static function step_job($slug) {
        $slug = self::wp_sanitize_title($slug);
        $job = self::wp_get_transient(self::JOB_PREFIX.$slug);
        if (!$job || empty($job['players'])) {
            return new WP_Error('no_job','Job no encontrado o expirado.');
        }

        $batch = isset($job['batch']) ? max(1,intval($job['batch'])) : 5;
        $set   = $job['set'];
        $done  = 0;

        while ($done < $batch && $job['index'] < $job['total']) {
            $playerId = intval($job['players'][$job['index']]);
            $row = self::build_player_row($playerId, $set);
            if (self::is_wp_error($row)) {
                $job['errors'][] = (is_object($row) && method_exists($row, 'get_error_message')) ? $row->get_error_message() : 'Error procesando jugador';
            } else {
                $job['results'][] = $row;
            }
            $job['index']++;
            $done++;
        }

    self::wp_set_transient(self::JOB_PREFIX.$slug, $job, self::sec_hour());

        $finished = ($job['index'] >= $job['total']);
        if ($finished) {
            $sf = $set['sort_field'];
            $sd = $set['sort_dir'];
            usort($job['results'], function($a,$b) use($sf,$sd){
                $va = isset($a[$sf]) ? $a[$sf] : 0;
                $vb = isset($b[$sf]) ? $b[$sf] : 0;
                if ($va == $vb) return 0;
                $res = ($va < $vb) ? -1 : 1;
                return ($sd === 'asc') ? $res : -$res;
            });
            foreach ($job['results'] as $i=>&$r) { $r['posicion_estatica'] = $i+1; }
            unset($r);
            // No recortar aquí; 'top_n' será el tamaño de página por defecto en el front.

            $cols_allowed = array('posicion_estatica','nombre','partidas_jugadas','partidas_ganadas','win_rate_partidos','competiciones_jugadas','competiciones_ganadas','win_rate_competiciones');
            $cols = (isset($set['columns']) && is_array($set['columns'])) ? array_values(array_intersect($set['columns'], $cols_allowed)) : $cols_allowed;
            if (!$cols) $cols = $cols_allowed;

            $payload = array(
                'columns' => $cols,
                'rows'    => $job['results'],
                'source'  => 'api_computed',
                'ts'      => self::wp_current_time_mysql(),
                'meta'    => array(
                    'total_rows' => count($job['results']),
                ),
            );
            // Guardar caché per-slug
            self::wp_update_option(self::CACHE_PREFIX.$slug, $payload);
            // Compatibilidad: también escribir en el option legado si existe en memoria
            $legacy = self::wp_get_option('futb_rankgen_cache', array());
            if (is_array($legacy)) {
                $legacy[$slug] = $payload;
                self::wp_update_option('futb_rankgen_cache', $legacy);
            }
            $job['finished'] = self::wp_current_time_mysql();
            self::wp_set_transient(self::JOB_PREFIX.$slug, $job, 10 * self::sec_min());
        }

        return array(
            'finished' => $finished,
            'index'    => $job['index'],
            'total'    => $job['total'],
            'errors'   => $job['errors'],
            'percent'  => $job['total'] ? round(($job['index']/$job['total'])*100, 1) : 100.0,
        );
    }

    private static function discover_candidates($set) {
        $modalidades = (array)$set['modalidades'];
        $scope = $set['scope'];
        $topN  = max(1, intval($set['top_n']));
    $temporadaId = isset($set['temporadaId']) ? (string)$set['temporadaId'] : '';
    $torneoIds   = isset($set['torneoIds']) ? array_map('intval', (array)$set['torneoIds']) : array();
    $competIds   = isset($set['competicionIds']) ? array_map('intval', (array)$set['competicionIds']) : array();
        $torneosAll  = !empty($set['torneos_all']);

        $ids = array();
        $api = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        foreach ($modalidades as $mod) {
            $mod = (string)$mod;
            // Preferir métodos del cliente (más robustos y con unwrapping interno)
            if ($scope === 'ESP' || $scope === 'ALL') {
                $resp = ($api && method_exists($api,'get_ranking_por_modalidad_esp_g2_all')) ? $api->get_ranking_por_modalidad_esp_g2_all($mod) : self::http_get_json("/Ranking/GetRankingPorModalidadESPGlicko2/{$mod}", $api);
                $arr = (array) json_decode(json_encode($resp), true);
                $items = self::unwrap_items($arr);
                if (empty($items) && isset($arr[0])) { $items = $arr; }
                $c = 0; foreach ($items as $it){ $jid = self::extract_player_id($it); if ($jid!=='') { $ids[] = (int)$jid; $c++; if ($c >= $topN*2) break; } }
            }
            if ($scope === 'EXT' || $scope === 'ALL') {
                // No hay helper específico EXT; usar http directo con unwrap tolerante si algún host lo expone
                $resp = self::http_get_json("/Ranking/GetRankingPorModalidadEXTGlicko2/{$mod}", $api);
                $arr = (array) json_decode(json_encode($resp), true);
                $items = self::unwrap_items($arr);
                if (empty($items) && isset($arr[0])) { $items = $arr; }
                $c = 0; foreach ($items as $it){ $jid = self::extract_player_id($it); if ($jid!=='') { $ids[] = (int)$jid; $c++; if ($c >= $topN*2) break; } }
            }
        }
        // Si hay restricciones por temporada y torneos concretos, podemos ampliar candidatos desde rankings por temporada
        if ($temporadaId !== '') {
            foreach ($modalidades as $mod) {
                $mod = (string)$mod;
                if ($scope === 'ESP' || $scope === 'ALL') {
                    if ($api && method_exists($api, 'get_ranking_por_modalidad_temporada_esp_g2_cached_official')) {
                        $rk = $api->get_ranking_por_modalidad_temporada_esp_g2_cached_official($mod, $temporadaId);
                    } elseif ($api && method_exists($api, 'get_ranking_por_modalidad_temporada_esp_g2')) {
                        $rk = $api->get_ranking_por_modalidad_temporada_esp_g2($mod, $temporadaId);
                    } else {
                        $rk = self::http_get_json("/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{$mod}/{$temporadaId}", $api);
                    }
                    $items = self::unwrap_items((array) json_decode(json_encode($rk), true));
                    $c=0; foreach ($items as $row){
                        $jid = self::extract_player_id($row); if ($jid!=='') $ids[] = (int)$jid; $c++; if ($c >= $topN*2) break;
                    }
                }
            }
        }
        // Si se seleccionaron torneos concretos (y no torneos_all), añadimos candidatos por posiciones en esos torneos
        if (!$torneosAll && !empty($torneoIds)) {
            foreach ($torneoIds as $tid) {
                $json = self::http_get_json("/Torneo/GetTorneoConPosiciones/{$tid}", $api);
                $items = (array) json_decode(json_encode($json), true);
                $comp = isset($items['competiciones']) && is_array($items['competiciones']) ? $items['competiciones'] : array();
                foreach ($comp as $c) {
                    $rows = isset($c['posiciones']) && is_array($c['posiciones']) ? $c['posiciones'] : array();
                    $cnt=0; foreach ($rows as $r){
                        $jid = self::extract_player_id($r); if ($jid!=='') $ids[] = (int)$jid; $cnt++; if ($cnt >= $topN) break;
                    }
                }
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) return new WP_Error('no_candidates','No se encontraron jugadores candidatos desde el ranking (verifica conexión, temporada y filtros).');
        return $ids;
    }

    private static function build_player_row($playerId, $set) {
        $api = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        // Preferir método del cliente si existe
        if ($api && method_exists($api, 'get_datos_jugador')) {
            try { $datos = $api->get_datos_jugador($playerId); } catch (\Throwable $e) { $datos = null; }
        } else {
            $datos = self::http_get_json("/Jugador/{$playerId}/GetDatosJugador", $api);
        }
        $darr = (array) json_decode(json_encode($datos), true);
        $nombre = isset($darr['nombreJugador']) ? (string)$darr['nombreJugador'] : (isset($darr['nombre']) ? (string)$darr['nombre'] : '');
        $temporadaId = isset($set['temporadaId']) ? (string)$set['temporadaId'] : '';
        $torneoIds   = isset($set['torneoIds']) ? array_map('intval', (array)$set['torneoIds']) : array();
        $torneosAll  = !empty($set['torneos_all']);
        // Posiciones del jugador por torneos/competiciones (preferir método del cliente si está)
    $comp = ($api && method_exists($api,'get_posiciones_jugador')) ? $api->get_posiciones_jugador($playerId) : self::http_get_json("/Jugador/{$playerId}/GetJugadorPosicionPorTorneos", $api);
        $comp_items = self::unwrap_items((array) json_decode(json_encode($comp), true));
        // Filtrar competiciones por temporada/torneo/competición/tipos y fases
    $tiposComp   = isset($set['tipos_comp']) ? (array)$set['tipos_comp'] : array();
    // lower-case robusto + normaliza typos (sin depender de mbstring)
    $toLower = function($s){ $s = (string)$s; return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); };
    $tiposComp = array_map(function($s) use ($toLower){
        $s = trim((string)$s);
        $s = str_ireplace('Amater','Amateur',$s); // typos comunes
        $s = str_ireplace('amater','amateur',$s);
        $s = str_ireplace('misto','mixto',$s);
        return $toLower($s);
    }, $tiposComp);
    // Si el set pide 'rookie', amplía con sinónimos habituales
    $hasRookie = false; foreach ($tiposComp as $_t) { if (strpos($_t, 'rookie') !== false) { $hasRookie = true; break; } }
    if ($hasRookie) {
        $rookieSyn = array('rookies','novato','novatos','novel','noveles','iniciacion','iniciación','principiante','principiantes','nivel 1','nivel1','beginner');
        foreach ($rookieSyn as $_syn) { $tiposComp[] = $toLower($_syn); }
        $tiposComp = array_values(array_unique($tiposComp));
    }
        $inclLig     = !empty($set['include_liguilla']);
        $inclCruces  = !empty($set['include_cruces']);
    // Definir $competIds explícitamente en este ámbito para la clausura
    $competIds   = isset($set['competicionIds']) ? array_map('intval', (array)$set['competicionIds']) : array();
        if (is_array($comp_items)) {
            $comp_items = array_filter($comp_items, function($row) use($temporadaId,$torneoIds,$competIds,$torneosAll,$tiposComp,$inclLig,$inclCruces){
                $r = (array) json_decode(json_encode($row), true);
                // Temporada
                if ($temporadaId !== '') {
                    $tId = $r['temporadaId'] ?? ($r['temporada'] ?? '');
                    if ((string)$tId !== (string)$temporadaId) return false;
                }
                // Torneos
                if (!$torneosAll && !empty($torneoIds)) {
                    $torId = isset($r['torneoId']) ? (int)$r['torneoId'] : (isset($r['idTorneo']) ? (int)$r['idTorneo'] : 0);
                    if ($torId && !in_array($torId, $torneoIds, true)) return false;
                }
                // CompeticionIds explícitos
                if (!empty($competIds)) {
                    $cid = isset($r['competicionId']) ? (int)$r['competicionId'] : (isset($r['idCompeticion']) ? (int)$r['idCompeticion'] : 0);
                    if ($cid && !in_array($cid, $competIds, true)) return false;
                }
                // Tipos de competiciones (por nombre textual de competición o campeonato)
                if (!empty($tiposComp)) {
                    $candidates = array(
                        $r['nombreCompeticion'] ?? null,
                        $r['competicionNombre'] ?? null,
                        $r['competicion'] ?? null,
                        $r['nombreCampeonato'] ?? null,
                        $r['campeonato'] ?? null,
                    );
                    $txt = '';
                    foreach ($candidates as $cand) { if (is_string($cand) && $cand !== '') { $txt .= ' ' . $cand; } }
                    $txt = str_ireplace('Amater','Amateur',$txt);
                    $txt = str_ireplace('misto','mixto',$txt);
                    $hay = function_exists('mb_strtolower') ? mb_strtolower($txt, 'UTF-8') : strtolower($txt);
                    $ok = false; foreach ($tiposComp as $needle){ $needle = (string)$needle; if ($needle !== '' && strpos($hay, $needle) !== false) { $ok = true; break; } }
                    if (!$ok) return false;
                }
                // Fases: si se especifica al menos una, filtramos
                $fase = isset($r['fase']) ? mb_strtolower((string)$r['fase']) : '';
                $isLiguilla = strpos($fase,'liguilla') !== false || strpos($fase,'grupo') !== false;
                $isCruces   = strpos($fase,'elimin') !== false || strpos($fase,'cruce') !== false || strpos($fase,'final') !== false;
                if (!$inclLig && $isLiguilla) return false;
                if (!$inclCruces && $isCruces) return false;
                return true;
            });
        }
        $competiciones_jugadas = is_array($comp_items) ? count($comp_items) : 0;
        $competiciones_ganadas = 0;
        if (is_array($comp_items)) {
            foreach ($comp_items as $row) {
                $pos = null;
                if (is_array($row) && isset($row['posicion'])) $pos = (int)$row['posicion'];
                elseif (is_object($row) && isset($row->posicion)) $pos = (int)$row->posicion;
                if ($pos === 1) $competiciones_ganadas++;
            }
        }
        $partidos = ($api && method_exists($api,'get_partidos_jugador')) ? $api->get_partidos_jugador($playerId) : array();
        $plist = (array) json_decode(json_encode($partidos), true);
        // Filtrar partidos por temporada/torneos
        if (is_array($plist)) {
            $plist = array_filter($plist, function($m) use($temporadaId,$torneoIds,$torneosAll,$tiposComp){
                $r = (array) json_decode(json_encode($m), true);
                if ($temporadaId !== '') {
                    $tId = $r['temporadaId'] ?? ($r['temporada'] ?? '');
                    if ((string)$tId !== (string)$temporadaId) return false;
                }
                if (!$torneosAll && !empty($torneoIds)) {
                    $torId = isset($r['torneoId']) ? (int)$r['torneoId'] : (isset($r['idTorneo']) ? (int)$r['idTorneo'] : 0);
                    if ($torId && !in_array($torId, $torneoIds, true)) return false;
                }
                if (!empty($tiposComp)) {
                    $candidates = array(
                        $r['nombreCompeticion'] ?? null,
                        $r['competicionNombre'] ?? null,
                        $r['competicion'] ?? null,
                        $r['nombreCampeonato'] ?? null,
                        $r['campeonato'] ?? null,
                        $r['categoria'] ?? null,
                    );
                    $txt = '';
                    foreach ($candidates as $cand) { if (is_string($cand) && $cand !== '') { $txt .= ' ' . $cand; } }
                    $txt = str_ireplace('Amater','Amateur',$txt);
                    $txt = str_ireplace('misto','mixto',$txt);
                    $hay = function_exists('mb_strtolower') ? mb_strtolower($txt, 'UTF-8') : strtolower($txt);
                    $ok = false; foreach ($tiposComp as $needle){ $needle = (string)$needle; if ($needle !== '' && strpos($hay, $needle) !== false) { $ok = true; break; } }
                    if (!$ok) return false;
                }
                return true;
            });
        }
        $partidas_jugadas = is_array($plist) ? count($plist) : 0;
        $partidas_ganadas = 0;
        if (is_array($plist)) {
            foreach ($plist as $m) {
                $gl = null;
                $gl = isset($m['ganadorLocal']) ? (bool)$m['ganadorLocal'] : (isset($m['ganoLocal']) ? (bool)$m['ganoLocal'] : null);
                $is_local = false;
                if (isset($m['local1Id']) && (string)$m['local1Id'] === (string)$playerId) $is_local = true;
                elseif (isset($m['local2Id']) && (string)$m['local2Id'] === (string)$playerId) $is_local = true;
                elseif (isset($m['visitante1Id']) && (string)$m['visitante1Id'] === (string)$playerId) $is_local = false;
                elseif (isset($m['visitante2Id']) && (string)$m['visitante2Id'] === (string)$playerId) $is_local = false;
                if ($gl !== null) {
                    $won = ($is_local && $gl === true) || (!$is_local && $gl === false);
                    if ($won) $partidas_ganadas++;
                }
            }
        }

        $row = array(
            'jugador_id'                => (int)$playerId,
            'nombre'                    => $nombre,
            'partidas_jugadas'          => (int)$partidas_jugadas,
            'partidas_ganadas'          => (int)$partidas_ganadas,
            'win_rate_partidos'         => ($partidas_jugadas > 0) ? round(($partidas_ganadas / $partidas_jugadas) * 100, 1) : 0,
            'competiciones_jugadas'     => (int)$competiciones_jugadas,
            'competiciones_ganadas'     => (int)$competiciones_ganadas,
            'win_rate_competiciones'    => ($competiciones_jugadas > 0) ? round(($competiciones_ganadas / $competiciones_jugadas) * 100, 1) : 0,
        );
        // Filtros finales (aplican condiciones del set)
        $minV = isset($set['min_victorias']) ? (int)$set['min_victorias'] : 0;
        $minP = isset($set['min_partidas']) ? (int)$set['min_partidas'] : 0;
        $reqC = !empty($set['require_campeonato']);
        if ($minV > 0 && $row['partidas_ganadas'] < $minV) {
            return new WP_Error('filter_skip','min_victorias');
        }
        if ($minP > 0 && $row['partidas_jugadas'] < $minP) {
            return new WP_Error('filter_skip','min_partidas');
        }
        if ($reqC && $row['competiciones_ganadas'] < 1) {
            return new WP_Error('filter_skip','require_campeonato');
        }
        return $row;
    }

    private static function http_get_json($path, $api_client = null) {
        $api = ($api_client && is_object($api_client)) ? $api_client : (class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null);
    if (!$api) return new WP_Error('api_client_missing','Cliente API no disponible');
        $base = $api->get_base_api_url();
    if (!$base) return new WP_Error('api_base_missing','Base API URL no configurada');
        $url = rtrim($base, '/') . $path;
        $resp = null;
        if (method_exists($api, 'do_request')) {
            try { $resp = (array) json_decode(json_encode($api->do_request($url, true)), true); } catch (Throwable $e) { $resp = null; }
        }
        if ($resp === null) return new WP_Error('api_call_failed','Fallo en llamada a la API');
        return $resp;
    }

    private static function unwrap_items($json) {
        if (empty($json)) return array();
        if (is_array($json)) {
            $is_list = array_keys($json) === range(0, count($json)-1);
            if ($is_list) return $json;
            if (isset($json['items']) && is_array($json['items'])) return $json['items'];
            if (isset($json['data']['items']) && is_array($json['data']['items'])) return $json['data']['items'];
            if (isset($json['result']['items']) && is_array($json['result']['items'])) return $json['result']['items'];
            if (isset($json['ranking']) && is_array($json['ranking'])) return $json['ranking'];
            if (isset($json['data']['ranking']) && is_array($json['data']['ranking'])) return $json['data']['ranking'];
            if (isset($json['result']['ranking']) && is_array($json['result']['ranking'])) return $json['result']['ranking'];
            if (isset($json['rows']) && is_array($json['rows'])) return $json['rows'];
            if (isset($json['data']) && is_array($json['data']) && array_keys($json['data']) === range(0, count($json['data'])-1)) return $json['data'];
            if (isset($json['result']) && is_array($json['result']) && array_keys($json['result']) === range(0, count($json['result'])-1)) return $json['result'];
        }
        return array();
    }

    public static function get_player_no1_years($playerId): array {
        static $cache_no1 = array();
        $key = (string)$playerId;
        if (isset($cache_no1[$key])) return $cache_no1[$key];

        $out = array('dobles' => array(), 'individual' => array());
        if (!$playerId && $playerId !== 0) return $out;

        $mods = self::get_open_modalidad_ids();
        $yearMap  = class_exists('Futbolin_Normalizer') ? \Futbolin_Normalizer::temporada_year_map() : array();
        $tempIds = self::get_temporada_ids();

        $api = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        // Memoización por petición: ranking por mod/temp -> items
        $rank_cache = array();
        foreach ($tempIds as $tempId) {
            $year = isset($yearMap[$tempId]) ? $yearMap[$tempId] : (string)$tempId;
            foreach (array('dobles','individual') as $k) {
                $modId = isset($mods[$k]) ? $mods[$k] : null;
                if (!$modId) continue;
                if (!$api || !method_exists($api,'get_ranking_por_modalidad_temporada_esp_g2')) continue;
                // El galardón de temporada se basa en el listado ESP Glicko2 por temporada (orden del array)
                $rk_key = $modId . ':' . $tempId;
                if (!isset($rank_cache[$rk_key])) {
                    // Usar variante cacheada si existe en el cliente
                    if (method_exists($api, 'get_ranking_por_modalidad_temporada_esp_g2_cached_official')) {
                        $rank_cache[$rk_key] = $api->get_ranking_por_modalidad_temporada_esp_g2_cached_official($modId, $tempId);
                    } else {
                        $rank_cache[$rk_key] = $api->get_ranking_por_modalidad_temporada_esp_g2($modId, $tempId);
                    }
                }
                $resp = $rank_cache[$rk_key];
                $items = self::unwrap_items((array) json_decode(json_encode($resp), true));
                if (!is_array($items) || !count($items)) continue;
                // La API ya filtra extranjeros; el orden del array es la verdad (no usar campo 'posicion')
                $top = $items[0];
                $jid = self::extract_player_id($top);
                if ((string)$jid === (string)$playerId) {
                    if (!in_array($year, $out[$k], true)) $out[$k][] = $year;
                }
            }
        }
        foreach ($out as $k => $arr) {
            $arr = array_values(array_unique($arr));
            sort($arr, SORT_NATURAL);
            $out[$k] = $arr;
        }
        $cache_no1[$key] = $out;
        return $out;
    }

    public static function get_player_podium_years($playerId): array {
        // Intentar recuperar de transient para evitar recomputar en cada carga de perfil
        $tck = function_exists('get_transient') ? 'rf:podium:player:' . (string)$playerId : '';
        if ($tck !== '') {
            $tval = get_transient($tck);
            if (is_array($tval) && isset($tval['dobles']) && isset($tval['individual'])) {
                return $tval;
            }
        }
        $out = array(
            'dobles'     => array('no1'=>array(), 'no2'=>array(), 'no3'=>array()),
            'individual' => array('no1'=>array(), 'no2'=>array(), 'no3'=>array()),
        );
        if (!$playerId && $playerId !== 0) return $out;
        $mods = self::get_open_modalidad_ids();
        $tempIds = self::get_temporada_ids();
        $api = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        // Memoización por petición: ranking por mod/temp -> items
        $rank_cache = array();
        foreach ($tempIds as $tempId) {
            $year = self::temporada_display_year($tempId);
            foreach (array('dobles','individual') as $k) {
                $modId = isset($mods[$k]) ? $mods[$k] : null;
                if (!$modId) continue;
                if (!$api || !method_exists($api,'get_ranking_por_modalidad_temporada_esp_g2')) continue;
                // Fuente principal: ESP Glicko2 (orden del array)
                $rk_key = $modId . ':' . $tempId;
                if (!isset($rank_cache[$rk_key])) {
                    if (method_exists($api, 'get_ranking_por_modalidad_temporada_esp_g2_cached_official')) {
                        $rank_cache[$rk_key] = $api->get_ranking_por_modalidad_temporada_esp_g2_cached_official($modId, $tempId);
                    } else {
                        $rank_cache[$rk_key] = $api->get_ranking_por_modalidad_temporada_esp_g2($modId, $tempId);
                    }
                }
                $resp = $rank_cache[$rk_key];
                $items = self::unwrap_items((array) json_decode(json_encode($resp), true));
                if (!is_array($items) || !count($items)) continue;
                $top3 = array_slice($items, 0, 3);
                foreach ($top3 as $idx => $row) {
                    $jid = self::extract_player_id($row);
                    if ((string)$jid === (string)$playerId) {
                        $pos = $idx + 1; // 1,2,3
                        $bucket = $pos === 1 ? 'no1' : ($pos === 2 ? 'no2' : 'no3');
                        if (!in_array($year, $out[$k][$bucket], true)) $out[$k][$bucket][] = $year;
                    }
                }
            }
        }
        foreach ($out as $k => $bucket) {
            foreach (array('no1','no2','no3') as $p) {
                $arr = array_values(array_unique($bucket[$p]));
                sort($arr, SORT_NATURAL);
                $out[$k][$p] = $arr;
            }
        }
        // Guardar en transient 12h
        if ($tck !== '' && function_exists('set_transient')) {
            set_transient($tck, $out, 12 * HOUR_IN_SECONDS);
        }
        return $out;
    }

    private static function get_temporada_ids(): array {
        $map = class_exists('Futbolin_Normalizer') ? \Futbolin_Normalizer::temporada_year_map() : array();
        if (!empty($map)) {
            $ids = array_keys($map);
            $ids = array_map(function($v){ return (string)$v; }, $ids);
            return $ids;
        }
        $items = self::unwrap_items(self::http_get_json("/Temporada/GetTemporadas"));
        $ids = array();
        if (is_array($items) && count($items)) {
            foreach ($items as $row) {
                $id = null;
                if (is_array($row)) { $id = $row['id'] ?? ($row['temporadaId'] ?? null); }
                elseif (is_object($row)) { $id = $row->id ?? ($row->temporadaId ?? null); }
                if ($id !== null) $ids[] = (string)$id;
            }
        }
        if (empty($ids)) { for ($i=1; $i<=14; $i++) { $ids[] = (string)$i; } }
        return array_values(array_unique($ids));
    }

    private static function temporada_display_year($tempId) {
        $map = class_exists('Futbolin_Normalizer') ? \Futbolin_Normalizer::temporada_year_map() : array();
        if (isset($map[(string)$tempId])) return $map[(string)$tempId];
        return (string)$tempId;
    }

    private static function normalize_accents($s) {
        $s = (string)$s;
        $s = mb_strtolower($s, 'UTF-8');
        $repl = array(
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
            'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ÿ'=>'y'
        );
        return strtr($s, $repl);
    }

    private static function get_open_modalidad_ids(): array {
        static $cache = null;
        if (is_array($cache)) return $cache;
        $mods_out = array('dobles' => null, 'individual' => null);
        $items = self::unwrap_items(self::http_get_json("/Modalidad/GetModalidades"));
        if (is_array($items)) {
            foreach ($items as $row) {
                $id = null; $name = '';
                if (is_array($row)) { $id = $row['id'] ?? ($row['modalidadId'] ?? null); $name = $row['name'] ?? ($row['nombre'] ?? ''); }
                elseif (is_object($row)) { $id = $row->id ?? ($row->modalidadId ?? null); $name = $row->name ?? ($row->nombre ?? ''); }
                $n = self::normalize_accents($name);
                if ($id === null) continue;
                if (strpos($n,'doble') !== false || strpos($n,'pareja') !== false) {
                    if ($mods_out['dobles'] === null) $mods_out['dobles'] = (string)$id;
                }
                if (strpos($n,'individual') !== false) {
                    if ($mods_out['individual'] === null) $mods_out['individual'] = (string)$id;
                }
            }
        }
        if ($mods_out['individual'] === null) $mods_out['individual'] = '1';
        if ($mods_out['dobles'] === null) $mods_out['dobles'] = '2';
        $cache = $mods_out;
        return $mods_out;
    }

    private static function extract_player_id($row) {
        if (is_array($row)) {
            foreach (array('jugadorId','playerId','idJugador','id') as $k) { if (isset($row[$k])) return (string)$row[$k]; }
            if (isset($row['jugador']) && is_array($row['jugador'])) {
                $jr = $row['jugador'];
                foreach (array('id','jugadorId','idJugador') as $k) { if (isset($jr[$k])) return (string)$jr[$k]; }
            }
        } elseif (is_object($row)) {
            foreach (array('jugadorId','playerId','idJugador','id') as $k) { if (isset($row->$k)) return (string)$row->$k; }
            if (isset($row->jugador) && is_object($row->jugador)) {
                $jr = $row->jugador;
                foreach (array('id','jugadorId','idJugador') as $k) { if (isset($jr->$k)) return (string)$jr->$k; }
            }
        }
        return '';
    }

    private static function extract_position($row) {
        if (is_array($row)) {
            foreach (array('posicion','position','rank','puesto','pos','posFinal','posicionFinal') as $k) {
                if (!isset($row[$k])) continue; $v = $row[$k];
                if (is_numeric($v)) return (int)$v;
                if (is_string($v) && preg_match('/(\d+)/', $v, $m)) return (int)$m[1];
            }
        } elseif (is_object($row)) {
            foreach (array('posicion','position','rank','puesto','pos','posFinal','posicionFinal') as $k) {
                if (!isset($row->$k)) continue; $v = $row->$k;
                if (is_numeric($v)) return (int)$v;
                if (is_string($v) && preg_match('/(\d+)/', $v, $m)) return (int)$m[1];
            }
        }
        return null;
    }

    // -------- Wrappers y polyfills seguros para entorno sin WP --------
    private static function wp_sanitize_title($s) {
    if (function_exists('sanitize_title')) return call_user_func('sanitize_title', $s);
        $s = (string)$s;
        $s = preg_replace('~[\pP\pS]+~u', ' ', $s);
        $s = strtolower(trim($s));
        $s = preg_replace('/\s+/', '-', $s);
        $s = preg_replace('/[^a-z0-9\-]/', '', $s);
        return trim($s, '-');
    }

    private static function wp_sanitize_key($s) {
    if (function_exists('sanitize_key')) return call_user_func('sanitize_key', $s);
        $s = strtolower((string)$s);
        return preg_replace('/[^a-z0-9_]/', '_', $s);
    }

    private static function wp_sanitize_text($s) {
        if (function_exists('sanitize_text_field')) return sanitize_text_field($s);
        $s = (string)$s;
        $s = strip_tags($s);
        $s = preg_replace("/[\r\n\t]+/", ' ', $s);
        return trim($s);
    }

    private static function wp_current_time_mysql() {
    if (function_exists('current_time')) return call_user_func('current_time', 'mysql');
        return gmdate('Y-m-d H:i:s');
    }

    private static function wp_set_transient($key, $value, $expiration) {
    if ((function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('set_transient')) return call_user_func('set_transient', $key, $value, $expiration);
        self::$mem_transients[$key] = array('value'=>$value,'expires'=>time() + (int)$expiration);
        return true;
    }

    private static function wp_get_transient($key) {
    if ((function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('get_transient')) return call_user_func('get_transient', $key);
        if (!isset(self::$mem_transients[$key])) return false;
        $t = self::$mem_transients[$key];
        if (time() > $t['expires']) { unset(self::$mem_transients[$key]); return false; }
        return $t['value'];
    }

    private static function wp_update_option($key, $value) {
    // Siempre mantener memoria local para continuidad del proceso, aunque la caché global esté deshabilitada
    if ((function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('update_option')) return call_user_func('update_option', $key, $value);
        self::$mem_options[$key] = $value; return true;
    }

    private static function wp_get_option($key, $default = false) {
    if ((function_exists('rf_cache_enabled') ? rf_cache_enabled() : true) && function_exists('get_option')) return get_option($key, $default);
    return isset(self::$mem_options[$key]) ? self::$mem_options[$key] : $default;
    }

    private static function sec_hour() { return defined('HOUR_IN_SECONDS') ? constant('HOUR_IN_SECONDS') : 3600; }
    private static function sec_min()  { return defined('MINUTE_IN_SECONDS') ? constant('MINUTE_IN_SECONDS') : 60; }

    private static function is_wp_error($v) {
        return (is_object($v) && (get_class($v) === 'WP_Error' || is_subclass_of($v, 'WP_Error')));
    }
}

// Polyfill mínimo para WP_Error en entornos sin WordPress
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public function __construct($code = 'error', $message = '', $data = null) {
            $this->errors = array($code => array($message));
        }
        public function get_error_message() {
            $first = reset($this->errors);
            return is_array($first) ? reset($first) : '';
        }
    }
}
