<?php
/**
 * Archivo: includes/processors/class-futbolin-finals-processor.php
 * Procesa torneos y genera informes de finales/campeonatos.
 */
if (!defined('ABSPATH')) exit;

require_once dirname(__DIR__) . '/core/class-futbolin-normalizer.php';

class Futbolin_Finals_Processor {

    private $api_client;

    // Si la API trae IDs de tipo de competición, puedes alimentarlos vía options:
    private array $OPEN_API_TYPE_IDS;
    private array $RESTO_API_TYPE_IDS;
    
    public function __construct($api_client) {
        $this->api_client = $api_client;
        $this->OPEN_API_TYPE_IDS  = get_option('futbolin_open_api_type_ids',  []); // ej [1101,1102]
        $this->RESTO_API_TYPE_IDS = get_option('futbolin_resto_api_type_ids', []); // ej [1401,1402]
    }

    /** Separa y normaliza nombres de un equipo en jugadores. */
    private function split_players(string $team): array {
        $normalized = Futbolin_Normalizer::normalize_players($team);
        $parts = preg_split('/\s+–\s+/u', $normalized) ?: [$normalized];
        if (count($parts) === 1) {
            // fallback por si algo no pasó por normalize
            $parts = preg_split('/\s*(?:—|–|-|\/|&|\s+y\s+)\s*/u', $normalized) ?: [$normalized];
        }
        $players = array_values(array_filter(array_map('trim', $parts), fn($p)=>$p!==''));
        return $players;
    }

    /** Nombre de equipo canónico (jugadores normalizados y ordenados). */
    private function canonical_team_name(array $players): string {
        $p = array_map('trim', $players);
        sort($p, SORT_NATURAL | SORT_FLAG_CASE);
        return implode(' – ', $p);
    }

    /** Clave única para deduplicar una final/posición. */
    private function final_row_key($result, string $team_canonical): string {
        $tid = (string)($result->torneoId ?? '');
        $cid = (string)($result->competicionId ?? '');
        $pos = (string)($result->posicion ?? '');
        if ($cid === '') {
            $rawc = mb_strtolower(trim((string)($result->nombreCompeticion ?? '')), 'UTF-8');
            $cid = 'name:'.preg_replace('/\s+/u',' ',$rawc);
        }
        return $tid.'|'.$cid.'|'.$team_canonical.'|'.$pos;
    }

    /** Intenta leer un id/tipo de competición nativo de la API si existe. */
    private function api_comp_type_id($result) {
        foreach (['competitionTypeId','tipoCompeticionId','competicionTipoId','tipoId','tipo_id'] as $prop) {
            if (isset($result->$prop)) return (int)$result->$prop;
        }
        return null;
    }

    /** Es OPEN (incluye "España Open"). */
    private function is_open_competition($result): bool {
        $raw = mb_strtolower(trim((string)($result->nombreCompeticion ?? '')), 'UTF-8');
        $has_open   = (bool)preg_match('/\b(open|abierto)\b/u', (string)$raw);
        $has_esp    = (bool)preg_match('/\b(espa(?:n|ñ)a)\b/u', (string)$raw);
        $is_es_open = ($has_esp && $has_open);

        $api_type = $this->api_comp_type_id($result);
        if ($api_type !== null && in_array($api_type, $this->OPEN_API_TYPE_IDS, true)) {
            return true;
        }
        if ($is_es_open) return true;

        $axes    = Futbolin_Normalizer::map_competicion((string)($result->nombreCompeticion ?? ''));
        $circuit = $axes['axes']['circuit'] ?? 'otro'; // open|pro|espana|otro
        return ($circuit === 'open' || $has_open);
    }

    /** Es RESTO según tus IDs + exclusiones (no Open, no España Open, no Rookie/Amateur). */
    private function is_resto_competition($result): bool {
        $raw = mb_strtolower(trim((string)($result->nombreCompeticion ?? '')), 'UTF-8');
        $has_open   = (bool)preg_match('/\b(open|abierto)\b/u', (string)$raw);
        $has_esp    = (bool)preg_match('/\b(espa(?:n|ñ)a)\b/u', (string)$raw);
        $is_es_open = ($has_esp && $has_open);
        $is_rookie  = (bool)preg_match('/\b(rookie|amateur|amater)\b/u', (string)$raw);
        if ($is_es_open || $is_rookie || ($has_open && !$has_esp)) return false;

        $api_type = $this->api_comp_type_id($result);
        if ($api_type !== null && !empty($this->RESTO_API_TYPE_IDS)) {
            return in_array($api_type, $this->RESTO_API_TYPE_IDS, true);
        }

        // Fallback por normalizador (IDs que pediste)
        static $ALLOW_IDS = [401,402,501,601,602,701,802,801,999];
        $dkey = Futbolin_Normalizer::detailed_type_key((string)($result->nombreCompeticion ?? ''));
        $tid  = Futbolin_Normalizer::detailed_type_id($dkey);
        return in_array($tid, $ALLOW_IDS, true);
    }
    
    public function process_tournament_positions($positions, $reports) {
        $reports = $this->initialize_reports_structure($reports);
        
        foreach ($positions as $result) {
            $players = $this->split_players((string)$result->equipoJugadores);
            $player_count = count($players);
            $team_name = $this->canonical_team_name($players);

            $is_winner = ((int)$result->posicion === 1);
            $is_final  = ((int)$result->posicion === 1 || (int)$result->posicion === 2);
            $tmpParts = explode('(', (string)$result->nombreTorneo);
            $tournament_name = trim($tmpParts[0] ?? (string)$result->nombreTorneo);

            // Deduplicación de la misma final (mismo torneo/competición/equipo/posición)
            $key = $this->final_row_key($result, $team_name);
            if (isset($reports['_seen_finals'][$key])) continue;
            $reports['_seen_finals'][$key] = true;

            // Categoría general para OPEN/ROOKIE y compatibilidad
            $category_general = 'resto_general';
            if ($this->is_open_competition($result)) {
                $category_general = 'open';
            } else {
                $raw = mb_strtolower(trim((string)$result->nombreCompeticion), 'UTF-8');
                $is_rookie_am = (bool)preg_match('/\b(rookie|amateur|amater)\b/u', (string)$raw);
                if ($is_rookie_am) $category_general = 'rookie_amater';
            }

            // RESTO filtrado exacto (solo para ese informe)
            $is_resto = $this->is_resto_competition($result);

            // ===== Totales globales (compatibilidad) =====
            foreach ($players as $player_name) {
                if (!isset($reports['championship_stats'][$player_name])) {
                    $reports['championship_stats'][$player_name] = [
                        'torneos_jugados' => [], 'campeonatos_jugados' => [], 'finales' => 0,
                        'ganadas' => 0, 'perdidas' => 0, 'resto' => 0,
                    ];
                }
                $reports['championship_stats'][$player_name]['torneos_jugados'][]     = $result->torneoId;
                $reports['championship_stats'][$player_name]['campeonatos_jugados'][] = $result->competicionId;

                if ($is_final) {
                    $reports['championship_stats'][$player_name]['finales']++;
                    if ($is_winner) $reports['championship_stats'][$player_name]['ganadas']++;
                    else            $reports['championship_stats'][$player_name]['perdidas']++;
                } elseif ((int)$result->posicion > 2) {
                    $reports['championship_stats'][$player_name]['resto']++;
                }
            }

            // ===== Totales por categoría general =====
            foreach ($players as $player_name) {
                if (!isset($reports['championship_stats_by_cat'][$category_general][$player_name])) {
                    $reports['championship_stats_by_cat'][$category_general][$player_name] = [
                        'torneos_jugados' => [], 'campeonatos_jugados' => [], 'finales' => 0,
                        'ganadas' => 0, 'perdidas' => 0, 'resto' => 0,
                    ];
                }
                $reports['championship_stats_by_cat'][$category_general][$player_name]['torneos_jugados'][]     = $result->torneoId;
                $reports['championship_stats_by_cat'][$category_general][$player_name]['campeonatos_jugados'][] = $result->competicionId;

                if ($is_final) {
                    $reports['championship_stats_by_cat'][$category_general][$player_name]['finales']++;
                    if ($is_winner) $reports['championship_stats_by_cat'][$category_general][$player_name]['ganadas']++;
                    else            $reports['championship_stats_by_cat'][$category_general][$player_name]['perdidas']++;
                } elseif ((int)$result->posicion > 2) {
                    $reports['championship_stats_by_cat'][$category_general][$player_name]['resto']++;
                }

                if ($is_winner) {
                    $reports['championship_wins'][$category_general][$player_name][] =
                        "{$tournament_name} (" . ucwords(trim($result->nombreCompeticion)) . ")";
                }
            }

            // ===== Totales por RESTO (filtrado) =====
            if ($is_resto) {
                foreach ($players as $player_name) {
                    if (!isset($reports['championship_stats_by_cat']['resto'][$player_name])) {
                        $reports['championship_stats_by_cat']['resto'][$player_name] = [
                            'torneos_jugados' => [], 'campeonatos_jugados' => [], 'finales' => 0,
                            'ganadas' => 0, 'perdidas' => 0, 'resto' => 0,
                        ];
                    }
                    $reports['championship_stats_by_cat']['resto'][$player_name]['torneos_jugados'][]     = $result->torneoId;
                    $reports['championship_stats_by_cat']['resto'][$player_name]['campeonatos_jugados'][] = $result->competicionId;

                    if ($is_final) {
                        $reports['championship_stats_by_cat']['resto'][$player_name]['finales']++;
                        if ($is_winner) $reports['championship_stats_by_cat']['resto'][$player_name]['ganadas']++;
                        else            $reports['championship_stats_by_cat']['resto'][$player_name]['perdidas']++;
                    } elseif ((int)$result->posicion > 2) {
                        $reports['championship_stats_by_cat']['resto'][$player_name]['resto']++;
                    }

                    if ($is_winner) {
                        $reports['championship_wins']['resto'][$player_name][] =
                            "{$tournament_name} (" . ucwords(trim($result->nombreCompeticion)) . ")";
                    }
                }
            }

            // ===== Finales (solo OPEN) =====
            if ($is_final && $category_general === 'open') {
                $is_individual = ($player_count === 1) || preg_match('/\b(individual|single|singles)\b/iu', (string)$result->nombreCompeticion);
                $is_doubles    = ($player_count > 1)  || preg_match('/\b(dobles|double|doubles)\b/iu', (string)$result->nombreCompeticion);

                if ($is_individual && $player_count > 0) { $player_name = $players[0];
                    if ($is_winner) $reports['open_individual_finals'][$player_name]['wins']   = ($reports['open_individual_finals'][$player_name]['wins']   ?? 0) + 1;
                    else            $reports['open_individual_finals'][$player_name]['losses'] = ($reports['open_individual_finals'][$player_name]['losses'] ?? 0) + 1;
                }
                if ($is_doubles && $player_count > 0) { foreach ($players as $player_name) {
                        if ($is_winner) $reports['open_doubles_player_finals'][$player_name]['wins']   = ($reports['open_doubles_player_finals'][$player_name]['wins']   ?? 0) + 1;
                        else            $reports['open_doubles_player_finals'][$player_name]['losses'] = ($reports['open_doubles_player_finals'][$player_name]['losses'] ?? 0) + 1;
                    }
                    $pair_name = $team_name; // pareja canónica
                    if ($is_winner) $reports['open_doubles_pair_finals'][$pair_name]['wins']   = ($reports['open_doubles_pair_finals'][$pair_name]['wins']   ?? 0) + 1;
                    else            $reports['open_doubles_pair_finals'][$pair_name]['losses'] = ($reports['open_doubles_pair_finals'][$pair_name]['losses'] ?? 0) + 1;
                }
            }
        }
        return $reports;
    }

    public function finalize_reports_data($reports) {
        // ==== FINALES (wins/losses) ====
        foreach (['open_individual_finals','open_doubles_player_finals','open_doubles_pair_finals'] as $report_key) {
            $report_data = $reports[$report_key] ?? [];
            $final = [];
            foreach ($report_data as $entity => $stats) {
                $w = (int)($stats['wins'] ?? 0);
                $l = (int)($stats['losses'] ?? 0);
                $t = $w + $l;
                $final[$entity] = [
                    'total' => $t,
                    'wins' => $w,
                    'losses' => $l,
                    'win_rate' => $t > 0 ? ($w/$t)*100 : 0,
                ];
            }
            uasort($final, function($a,$b){
                $c = $b['wins'] <=> $a['wins'];
                return $c !== 0 ? $c : ($b['total'] <=> $a['total']);
            });
            $reports[$report_key] = $final;
        }

        // ==== CAMPEONATOS por categoría ====
        $reports['championships_open']   = $this->build_championship_report_from_cat(
            $reports['championship_stats_by_cat']['open'] ?? [],
            $reports['championship_wins']['open'] ?? []
        );
        $reports['championships_rookie'] = $this->build_championship_report_from_cat(
            $reports['championship_stats_by_cat']['rookie_amater'] ?? [],
            $reports['championship_wins']['rookie_amater'] ?? []
        );
        $reports['championships_resto']  = $this->build_championship_report_from_cat(
            $reports['championship_stats_by_cat']['resto'] ?? [],
            $reports['championship_wins']['resto'] ?? []
        );

        return $reports;
    }

    private function build_championship_report_from_cat(array $src, array $winsL): array {
        $out = [];
        foreach ($src as $player => $st) {
            $fg = (int)($st['ganadas'] ?? 0);
            $fp = (int)($st['perdidas'] ?? 0);
            $tf = $fg + $fp;
            $pf = $tf > 0 ? ($fg/$tf)*100 : 0;

            $cg_list = $winsL[$player] ?? [];
            $cg = (int)count($cg_list);

            $tc = $tf + (int)($st['resto'] ?? 0);
            $pc = $tc > 0 ? ($cg/$tc)*100 : 0;

            $out[$player] = [
                'torneos_jugados' => count(array_unique($st['torneos_jugados'] ?? [])),
                'campeonatos_jugados' => count(array_unique($st['campeonatos_jugados'] ?? [])),
                'finales_jugadas' => $tf,
                'finales_ganadas' => $fg,
                'finales_perdidas' => $fp,
                'resto_posiciones' => (int)($st['resto'] ?? 0),
                'porcentaje_finales_ganadas' => number_format($pf, 2),
                'porcentaje_campeonatos_ganados' => number_format($pc, 2),
                'campeonatos_ganados' => $cg,
                'campeonatos_ganados_list' => $cg_list,
            ];
        }

        uasort($out, function($a,$b){
            $c = ($b['campeonatos_ganados'] ?? 0) <=> ($a['campeonatos_ganados'] ?? 0);
            if ($c !== 0) return $c;
            $c2 = ($b['finales_ganadas'] ?? 0) <=> ($a['finales_ganadas'] ?? 0);
            if ($c2 !== 0) return $c2;
            return ((float)$b['porcentaje_finales_ganadas'] ?? 0) <=> ((float)$a['porcentaje_finales_ganadas'] ?? 0);
        });

        return $out;
    }

    private function initialize_reports_structure($reports) {
        $keys = [
            'open_individual_finals','open_doubles_player_finals','open_doubles_pair_finals',
            'championship_stats','championship_wins','championship_stats_by_cat','_seen_finals'
        ];
        foreach ($keys as $k) { if (!isset($reports[$k])) $reports[$k] = []; }

        foreach (['open','rookie_amater','resto','resto_general'] as $cat) {
            if (!isset($reports['championship_stats_by_cat'][$cat])) {
                $reports['championship_stats_by_cat'][$cat] = [];
            }
            if (!isset($reports['championship_wins'][$cat])) {
                $reports['championship_wins'][$cat] = [];
            }
        }
        return $reports;
    }
}
