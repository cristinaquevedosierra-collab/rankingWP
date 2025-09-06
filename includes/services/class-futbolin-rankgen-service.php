<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Rankgen_Service {

    const JOB_PREFIX = 'futb_rankgen_job_';
    const CACHE_PREFIX = 'futb_rankgen_cache_';
    const SETS_OPTION = 'futb_rankgen_sets';

    public static function start_job($slug, $set = array()) {
        $slug = sanitize_title($slug);
        if (!$slug) return new WP_Error('bad_slug','Slug vacío');

        if (empty($set)) {
            $sets = get_option(self::SETS_OPTION, array());
            if (isset($sets[$slug]) && is_array($sets[$slug])) {
                $set = $sets[$slug];
            }
        }

        $set = is_array($set) ? $set : array();
        $set['scope'] = isset($set['scope']) ? strtoupper(sanitize_text_field($set['scope'])) : 'ESP';
        $set['modalidades'] = isset($set['modalidades']) ? (array)$set['modalidades'] : array('1','2');
        $set['top_n'] = isset($set['top_n']) ? max(1, intval($set['top_n'])) : 25;
        $set['min_partidos'] = isset($set['min_partidos']) ? max(0, intval($set['min_partidos'])) : 0;
        $set['min_competiciones'] = isset($set['min_competiciones']) ? max(0, intval($set['min_competiciones'])) : 0;
        $set['include_liguilla'] = empty($set['include_liguilla']) ? '' : '1';
        $set['include_cruces']   = empty($set['include_cruces']) ? '' : '1';
        $set['sort_field'] = isset($set['sort_field']) ? sanitize_key($set['sort_field']) : 'win_rate_partidos';
        $set['sort_dir']   = (isset($set['sort_dir']) && strtolower($set['sort_dir']) === 'asc') ? 'asc' : 'desc';
        $set['temporadaId'] = isset($set['temporadaId']) ? sanitize_text_field($set['temporadaId']) : '';
        $set['competicionIds'] = isset($set['competicionIds']) ? array_map('intval', (array)$set['competicionIds']) : array();
        $set['torneoIds'] = isset($set['torneoIds']) ? array_map('intval', (array)$set['torneoIds']) : array();

        $candidates = self::discover_candidates($set);
        if (is_wp_error($candidates)) return $candidates;
        $total = count($candidates);

        $job = array(
            'slug'      => $slug,
            'set'       => $set,
            'players'   => $candidates,
            'index'     => 0,
            'results'   => array(),
            'started'   => current_time('mysql'),
            'finished'  => '',
            'errors'    => array(),
            'total'     => $total,
            'batch'     => 5,
        );
        set_transient(self::JOB_PREFIX.$slug, $job, HOUR_IN_SECONDS);
        return array('total'=>$total);
    }

    public static function step_job($slug) {
        $slug = sanitize_title($slug);
        $job = get_transient(self::JOB_PREFIX.$slug);
        if (!$job || empty($job['players'])) {
            return new WP_Error('no_job','Job no encontrado o expirado.');
        }

        $batch = isset($job['batch']) ? max(1,intval($job['batch'])) : 5;
        $set   = $job['set'];
        $done  = 0;

        while ($done < $batch && $job['index'] < $job['total']) {
            $playerId = intval($job['players'][$job['index']]);
            $row = self::build_player_row($playerId, $set);
            if (is_wp_error($row)) {
                $job['errors'][] = $row->get_error_message();
            } else {
                $job['results'][] = $row;
            }
            $job['index']++;
            $done++;
        }

        set_transient(self::JOB_PREFIX.$slug, $job, HOUR_IN_SECONDS);

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
            $job['results'] = array_slice($job['results'], 0, $set['top_n']);

            $cols_allowed = array('posicion_estatica','nombre','partidas_jugadas','partidas_ganadas','win_rate_partidos','competiciones_jugadas','competiciones_ganadas','win_rate_competiciones');
            $cols = (isset($set['columns']) && is_array($set['columns'])) ? array_values(array_intersect($set['columns'], $cols_allowed)) : $cols_allowed;
            if (!$cols) $cols = $cols_allowed;

            $payload = array(
                'columns' => $cols,
                'rows'    => $job['results'],
                'source'  => 'api_computed',
                'ts'      => current_time('mysql'),
            );
            update_option(self::CACHE_PREFIX.$slug, $payload);
            $job['finished'] = current_time('mysql');
            set_transient(self::JOB_PREFIX.$slug, $job, 10 * MINUTE_IN_SECONDS);
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

        $ids = array();
        foreach ($modalidades as $mod) {
            $mod = (string)$mod;
            $paths = array();
            if ($scope === 'ESP' || $scope === 'ALL') {
                $paths[] = "/api/Ranking/GetRankingPorModalidadESPGlicko2/{$mod}";
            }
            if ($scope === 'EXT' || $scope === 'ALL') {
                $paths[] = "/api/Ranking/GetRankingPorModalidadEXTGlicko2/{$mod}";
            }
            foreach ($paths as $p) {
                $json = self::http_get_json($p);
                if (is_wp_error($json) || !is_array($json)) continue;
                $c = 0;
                foreach ($json as $it) {
                    if (isset($it['jugadorId'])) $ids[] = intval($it['jugadorId']);
                    elseif (isset($it['id']))   $ids[] = intval($it['id']);
                    $c++; if ($c >= $topN*2) break;
                }
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) return new WP_Error('no_candidates','No se encontraron jugadores candidatos desde el ranking.');
        return $ids;
    }

    private static function build_player_row($playerId, $set) {
        $datos = self::http_get_json("/api/Jugador/{$playerId}/GetDatosJugador");
        $nombre = '';
        if (is_array($datos)) {
            if (isset($datos['nombreJugador'])) $nombre = (string)$datos['nombreJugador'];
            elseif (isset($datos['nombre']))    $nombre = (string)$datos['nombre'];
        }

        $comp = self::http_get_json("/api/Jugador/{$playerId}/GetJugadorPosicionPorTorneos");
        $competiciones_jugadas = 0;
        $competiciones_ganadas = 0;
        if (is_array($comp)) {
            foreach ($comp as $c) {
                if (!empty($set['temporadaId']) && isset($c['temporadaId']) && strval($c['temporadaId']) !== strval($set['temporadaId'])) {
                    continue;
                }
                if (!empty($set['competicionIds']) && isset($c['competicionId']) && !in_array(intval($c['competicionId']), $set['competicionIds'], true)) {
                    continue;
                }
                if (!empty($set['torneoIds']) && isset($c['torneoId']) && !in_array(intval($c['torneoId']), $set['torneoIds'], true)) {
                    continue;
                }
                if (!empty($set['modalidades']) && isset($c['modalidadId']) && !in_array(strval($c['modalidadId']), array_map('strval',$set['modalidades']), true)) {
                    continue;
                }
                $competiciones_jugadas++;
                $pos = 0;
                if (isset($c['posicion'])) $pos = intval($c['posicion']);
                elseif (isset($c['puesto'])) $pos = intval($c['puesto']);
                if ($pos === 1) $competiciones_ganadas++;
            }
        }

        $partidas_jugadas = 0;
        $partidas_ganadas = 0;
        $include_liguilla = !empty($set['include_liguilla']);
        $include_cruces   = !empty($set['include_cruces']);
        $page = 1;
        $pageSize = 200;
        $hard_cap = 3000;
        $fetched = 0;
        while (true) {
            $json = self::http_get_json("/api/Jugador/{$playerId}/GetJugadorPartidosPag?page={$page}&pageSize={$pageSize}");
            if (!is_array($json) || empty($json['items'])) break;
            foreach ($json['items'] as $m) {
                if (!empty($set['temporadaId']) && isset($m['temporadaId']) && strval($m['temporadaId']) !== strval($set['temporadaId'])) {
                    continue;
                }
                $fase = isset($m['fase']) ? (string)$m['fase'] : '';
                if ($fase === 'Liguilla' && !$include_liguilla) continue;
                if ($fase !== 'Liguilla' && !$include_cruces) continue;
                if (!empty($set['torneoIds']) && isset($m['torneoId']) && !in_array(intval($m['torneoId']), $set['torneoIds'], true)) {
                    continue;
                }
                if (!empty($set['competicionIds']) && isset($m['competicionId']) && !in_array(intval($m['competicionId']), $set['competicionIds'], true)) {
                    continue;
                }

                $partidas_jugadas++;
                $loc = isset($m['equipoLocal']) ? (string)$m['equipoLocal'] : '';
                $vis = isset($m['equipoVisitante']) ? (string)$m['equipoVisitante'] : '';
                $ganLoc = isset($m['ganadorLocal']) ? (bool)$m['ganadorLocal'] : null;

                $isLocal = null;
                if ($nombre) {
                    if ($loc && stripos($loc, $nombre) !== false) $isLocal = true;
                    elseif ($vis && stripos($vis, $nombre) !== false) $isLocal = false;
                }

                $won = false;
                if ($isLocal === true && $ganLoc === true) $won = true;
                elseif ($isLocal === false && $ganLoc === false) $won = true;
                elseif ($ganLoc !== null && $isLocal === null) {
                    $won = (bool)$ganLoc;
                }
                if ($won) $partidas_ganadas++;
            }
            $fetched += count($json['items']);
            if (empty($json['hasNextPage']) || !$json['hasNextPage']) break;
            if ($fetched >= $hard_cap) break;
            $page++;
        }

        $win_rate_partidos = $partidas_jugadas > 0 ? round(($partidas_ganadas / $partidas_jugadas) * 100, 2) : 0.0;
        $win_rate_competiciones = $competiciones_jugadas > 0 ? round(($competiciones_ganadas / $competiciones_jugadas) * 100, 2) : 0.0;

        return array(
            'id'                     => $playerId,
            'nombre'                 => $nombre,
            'partidas_jugadas'       => $partidas_jugadas,
            'partidas_ganadas'       => $partidas_ganadas,
            'win_rate_partidos'      => $win_rate_partidos,
            'competiciones_jugadas'  => $competiciones_jugadas,
            'competiciones_ganadas'  => $competiciones_ganadas,
            'win_rate_competiciones' => $win_rate_competiciones,
        );
    }

    private static function http_get_json($path) {
        $base = self::get_base_url();
        if (!$base) return new WP_Error('no_base','No hay base_url configurado');
        $url = rtrim($base, '/') . $path;

        $timeout = 30;
        $opts = get_option('mi_plugin_futbolin_options', array());
        if (is_array($opts) && !empty($opts['http_timeout'])) {
            $timeout = max(5, min(120, intval($opts['http_timeout'])));
        }

        $headers = array('Accept'=>'application/json');
        $token = get_transient('futbolin_api_token');
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $res = wp_remote_get($url, array('timeout'=>$timeout, 'headers'=>$headers));
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) return new WP_Error('http_'.$code, 'HTTP '.$code.' en '.$path);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json', 'JSON inválido en '.$path);
        }
        return $json;
    }

    private static function get_base_url() {
        $cfg = get_option('ranking_api_config', array());
        if (is_array($cfg) && !empty($cfg['base_url'])) {
            return rtrim($cfg['base_url'], '/');
        }
        $opts = get_option('mi_plugin_futbolin_options', array());
        if (is_array($opts) && !empty($opts['api_base_url'])) {
            return rtrim($opts['api_base_url'], '/');
        }
        return '';
    }
}
