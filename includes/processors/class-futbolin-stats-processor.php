<?php
/**
 * Archivo: includes/processors/class-futbolin-stats-processor.php
 * Descripción: Calcula el Hall of Fame aplicando las reglas finales (Fase 1 y Fase 2).
 */
if (!defined('ABSPATH')) exit;

include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

class Futbolin_Stats_Processor {

    /** @var Futbolin_API_Client */
    private $api_client;

    public function __construct(Futbolin_API_Client $api_client) {
        $this->api_client = $api_client;
    }

    private function get_api_base(): string {
        if (class_exists('Futbolin_API') && method_exists('Futbolin_API', 'get_base_url')) {
            return rtrim(Futbolin_API::get_base_url(), '/');
        }
        $base = get_option('futb_api_base');
        return is_string($base) ? rtrim($base, '/') : '';
    }
    private function _norm(string $s): string {
        $s = strtolower(trim($s));
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $s ? preg_replace('/\s+/', ' ', $s) : '';
    }

    public function get_player_hall_of_fame_stats($player_id) {
        $player_id = (int)$player_id;
        $hof_debug = (isset($_GET['rf_debug_hof']) && $_GET['rf_debug_hof'] == '1');

        $wins_singles = 0;
        $wins_doubles = 0;
        $partidos_jugados = 0;
        $unique_tournaments = [];
        $unique_competitions_in_tournaments = [];
        $competiciones_ganadas = 0;
        $years_active = 0;

        $player_data = $this->api_client->get_datos_jugador($player_id);
        if (is_wp_error($player_data) || !$player_data || empty($player_data->nombreJugador)) {
            // Mantener como ERROR visible
            error_log('HALL OF FAME ERROR: Datos jugador vacíos para ID=' . $player_id);
            return null;
        }

        $player_history = $this->api_client->get_posiciones_jugador($player_id);
        if (is_array($player_history)) {
            $years = [];
            foreach ($player_history as $it) {
                if (!is_object($it)) continue;
                if (!empty($it->torneoId)) $unique_tournaments[(int)$it->torneoId] = true;
                if (!empty($it->competicionId)) $unique_competitions_in_tournaments[(int)$it->competicionId] = true;
                if (isset($it->posicion) && (int)$it->posicion === 1) $competiciones_ganadas++;
                if (!empty($it->fecha)) {
                    $y = (int)substr((string)$it->fecha, 0, 4);
                    if ($y > 0) $years[$y] = true;
                }
            }
            $years_active = count($years);
        }

        $player_matches = $this->api_client->get_partidos_jugador($player_id);
        if (!is_array($player_matches)) $player_matches = [];

        if ($hof_debug && $player_id === 4) {
            $api_base = $this->get_api_base();
            $url_guess = $api_base . '/api/Jugador/' . $player_id . '/GetJugadorPartidos';
            error_log('DEBUG API P4: URL de la petición = ' . $url_guess);
            error_log('DEBUG API P4: Respuesta recibida = ' . json_encode($player_matches));
        }

        $allowed_competitions = [
            'pro dobles', 'open dobles', 'open individual',
            'espana dobles', 'espana individual', 'misto', 'mixto',
        ];

        $valid_matches = [];
        foreach ($player_matches as $m) {
            if (!is_object($m)) continue;

            $competicion_raw = '';
            if (isset($m->competicion) && is_string($m->competicion)) {
                $competicion_raw = $m->competicion;
            } elseif (isset($m->nombreCompeticion) && is_string($m->nombreCompeticion)) {
                $competicion_raw = $m->nombreCompeticion;
            } else {
                continue;
            }
            $competicion_norm = $this->_norm((string)$competicion_raw);

            $fase_raw = isset($m->fase) && is_string($m->fase) ? $m->fase : '';
            $fase_norm = $this->_norm($fase_raw);
            if ($fase_norm === 'liguilla') continue;

            if (!in_array($competicion_norm, $allowed_competitions, true)) continue;

            $valid_matches[] = $m;
        }

        $partidos_validos = count($valid_matches);
        $victorias_validas = 0;
        $has_championship_win = false;

        foreach ($valid_matches as $m) {
            $won = (property_exists($m, 'ganadorLocal') && (bool)$m->ganadorLocal === true);

            if ($won) {
                $victorias_validas++;

                $torneo_raw = '';
                if (isset($m->torneo) && is_string($m->torneo)) {
                    $torneo_raw = $m->torneo;
                } elseif (isset($m->nombreTorneo) && is_string($m->nombreTorneo)) {
                    $torneo_raw = $m->nombreTorneo;
                }
                $torneo_norm = $this->_norm($torneo_raw);
                if ($torneo_norm !== '' && strpos($torneo_norm, 'campeonato') !== false) {
                    $has_championship_win = true;
                }
            }

            $is_doubles = false;
            if (isset($m->modalidadId)) {
                $is_doubles = ((int)$m->modalidadId === 2);
            } else {
                $txt = '';
                foreach (['modalidad','nombreCompeticion','competicion','categoria'] as $prop) {
                    if (isset($m->$prop) && is_string($m->$prop)) { $txt .= ' ' . $this->_norm($m->$prop); }
                }
                if ($txt !== '' && strpos($txt, 'doble') !== false) $is_doubles = true;
            }
            if ($is_doubles && $won) { $wins_doubles++; }
            elseif ($won) { $wins_singles++; }
        }

        $partidos_jugados = $partidos_validos;
        $partidas_ganadas = $victorias_validas;

        if ($hof_debug && $player_id === 4) {
            $msg = 'HOF P4 FASE2: validos=' . $partidos_validos
                . ' | victorias_validas=' . $victorias_validas
                . ' | has_championship_win=' . ($has_championship_win ? '1' : '0')
                . ' | criterio_inclusion=' . (($partidos_validos >= 100 && $has_championship_win) ? 'OK' : 'NO');
            error_log($msg);
        }

        return [
            'id'                        => $player_id,
            'nombre'                    => (string)$player_data->nombreJugador,
            'años_activo'               => (int)$years_active,
            'campeonatos_jugados'       => count($unique_tournaments),
            'competiciones_jugadas'     => count($unique_competitions_in_tournaments),
            'partidas_jugadas'          => (int)$partidos_jugados,
            'partidas_ganadas'          => (int)$partidas_ganadas,
            'win_rate_partidos'         => $partidos_jugados > 0 ? round(($partidas_ganadas / $partidos_jugados) * 100, 2) : 0.0,
            'competiciones_ganadas'     => (int)$competiciones_ganadas,
            'win_rate_competiciones'    => count($unique_competitions_in_tournaments) > 0 ? round(($competiciones_ganadas / count($unique_competitions_in_tournaments)) * 100, 2) : 0.0,
            'has_championship_win'      => (bool)$has_championship_win,
        ];
    }

    public function sort_hall_of_fame_data(array $hall_of_fame_data): array {
        usort($hall_of_fame_data, function ($a, $b) {
            $av = (float)($a['win_rate_partidos'] ?? 0);
            $bv = (float)($b['win_rate_partidos'] ?? 0);
            if ($av === $bv) {
                $ag = (int)($a['partidas_ganadas'] ?? 0);
                $bg = (int)($b['partidas_ganadas'] ?? 0);
                if ($ag === $bg) return 0;
                return ($ag < $bg) ? 1 : -1;
            }
            return ($av < $bv) ? 1 : -1;
        });
        return $hall_of_fame_data;
    }

    public function build_hof_dataset(): array {
    $hof_debug = (isset($_GET['rf_debug_hof']) && $_GET['rf_debug_hof'] == '1');
    if ($hof_debug) { error_log('HALL OF FAME DEBUG: build_hof_dataset() iniciado.'); }

    if ($hof_debug) { error_log('HALL OF FAME DEBUG: Derivando player_ids desde rankings...'); }
        $player_ids = $this->derive_player_ids_from_rankings();
        $player_ids = array_values(array_unique(array_map('intval', $player_ids)));
    if ($hof_debug) { error_log('HALL OF FAME DEBUG: player_ids derivados: ' . count($player_ids)); }

        $rows = [];
        $i = 0;
        foreach ($player_ids as $pid) {
            $i++;
            if ($hof_debug && $i % 25 === 0) { error_log('HALL OF FAME DEBUG: Procesando jugador #' . $i . ' (ID=' . (int)$pid . ')'); }
            $stats = $this->get_player_hall_of_fame_stats((int)$pid);
            if (is_array($stats)) {
                $rows[] = $stats;
            }
        }

        $rows = array_values(array_filter($rows, function($r) {
            $pj  = (int)($r['partidas_jugadas'] ?? 0);
            $hcw = (bool)($r['has_championship_win'] ?? false);
            return ($pj >= 100) && $hcw;
        }));

    if ($hof_debug) { error_log('HALL OF FAME DEBUG: Cálculo finalizado. Se encontraron ' . count($rows) . ' jugadores para el ranking.'); }

        $rows = $this->sort_hall_of_fame_data($rows);
        $pos = 1;
        foreach ($rows as &$r) {
            $r['posicion_estatica'] = $pos++;
        }
        unset($r);

        return $rows;
    }

    private function derive_player_ids_from_rankings(): array {
        $ids = [];

        $mods = $this->api_client->get_modalidades();
        if (!is_array($mods) || empty($mods)) return $ids;

        foreach ($mods as $m) {
            if (!is_object($m) || !isset($m->modalidadId)) continue;
            $mid = (int)$m->modalidadId;
            if ($mid !== 1 && $mid !== 2) continue;

            $resp = $this->api_client->get_ranking($mid, 1, 10000);
            if (is_wp_error($resp) || !$resp) continue;

            $container = (is_object($resp) && isset($resp->ranking)) ? $resp->ranking : $resp;
            $items = (is_object($container) && isset($container->items) && is_array($container->items)) ? $container->items : (is_array($container) ? $container : []);

            foreach ($items as $it) {
                if (!is_object($it)) continue;
                if (isset($it->jugadorId) && (int)$it->jugadorId > 0) {
                    $ids[] = (int)$it->jugadorId;
                }
            }
        }

        return $ids;
    }
}
