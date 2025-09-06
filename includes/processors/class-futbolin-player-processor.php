<?php
/**
 * Archivo: class-futbolin-player-processor.php
 * Ruta: includes/processors/class-futbolin-player-processor.php
 *
 * Procesa los datos de un jugador para estadísticas y vistas.
 * Versión revisada: añade agregados de “Dobles (Partidos)”, “Open Dobles *” y “Pro Dobles”
 * usando el normalizador canónico (sin cambiar tu salida previa).
 */
if (!defined('ABSPATH')) exit;

// Asegura que el normalizador está cargado
if (!class_exists('Futbolin_Normalizer')) {
    require_once dirname(__DIR__) . '/core/class-futbolin-normalizer.php';
}

class Futbolin_Player_Processor {

    public $basic_data;

    // Salidas originales (se mantienen tal cual para no romper plantillas)
    public $summary_stats = [];
    public $match_stats_by_type = [];
    public $honours_stats_by_type = [];
    public $grouped_matches = [];
    public $inactivity_events = [];
    public $point_evolution = [];
    public $matches = [];
    public $honours = [];
    public $hitos = [];

    // NUEVO: agregados especiales para mostrar líneas tipo:
    // "Dobles (Partidos) 324 de 401", "Open Dobles * 277 de 341", "Pro Dobles 47 de 60"
    public $match_aggregates = [];

    public function __construct($player_data, $partidos_items, $posiciones_items) {
        if (!$player_data) { return; }
        $this->basic_data = $player_data;
        $this->matches = is_array($partidos_items) ? $partidos_items : [];
        $this->honours = is_array($posiciones_items) ? $posiciones_items : [];

        $this->calculate_summary_stats();
        $this->calculate_match_stats_by_type();
        $this->calculate_honours_stats_by_type();
        $this->group_matches_for_history();
        $this->calculate_point_evolution();
        $this->calculate_hitos();

        // NUEVO: agregados especiales basados en claves canónicas del normalizador
        $this->calculate_match_aggregates();
    }

    /* ==================== N U E V O S   M É T O D O S ==================== */

    /**
     * Agregados “Dobles (Partidos)”, “Open Dobles *”, “Pro Dobles”.
     * - “Dobles (Partidos)” = todas las partidas cuyo eje base = dobles (cualquier circuito/banda), EXCLUIDO DYP.
     * - “Open Dobles *” = suma de keys canónicas {open_dobles, espana_dobles} (incluye Amateur/Rookie/Master).
     * - “Pro Dobles”     = key canónica {pro_dobles}.
     *
     * NO toca tus arrays existentes. Esto vive en $this->match_aggregates.
     */
    /*** REEMPLAZA COMPLETO ESTE MÉTODO ***/
private function calculate_match_aggregates(): void {
    $label_all_doubles = 'Dobles (Partidos)';
    $label_open_star   = 'Open Dobles *';
    $label_pro_dobles  = 'Pro Dobles';
    $label_minor_star  = 'Competiciones de Menor Categoría (Partidos) *';

    $agg = [
        $label_all_doubles => ['jugados' => 0, 'ganados' => 0],
        $label_open_star   => ['jugados' => 0, 'ganados' => 0],
        $label_pro_dobles  => ['jugados' => 0, 'ganados' => 0],
        $label_minor_star  => ['jugados' => 0, 'ganados' => 0],
    ];

    if (empty($this->matches) || !isset($this->basic_data->nombreJugador)) {
        $this->match_aggregates = $this->finalize_rates($agg);
        return;
    }

    $name_parts = explode(' ', $this->basic_data->nombreJugador);

    foreach ($this->matches as $p) {
        if ($this->is_no_team($p)) continue;

        $raw_comp = isset($p->competicion) ? (string)$p->competicion : '';
        if ($raw_comp === '') continue;

        $won = $this->did_player_win_in_match($name_parts, $p);

        // Ejes canónicos del normalizador
        $mapped = Futbolin_Normalizer::map_competicion($raw_comp);
        $key    = $mapped['key']  ?? 'otros';
        $axes   = $mapped['axes'] ?? ['base' => 'otro', 'is_dyp' => false, 'band' => 'ninguna', 'circuit' => 'otro'];

        $base   = $axes['base'] ?? 'otro';
        $band   = $axes['band'] ?? 'ninguna';
        $is_dyp = !empty($axes['is_dyp']);

        $is_minor = $this->is_minor_category_name($raw_comp); // Amateur/Rookie/Master/Pro-Am

        // 1) “Dobles (Partidos)”: todo dobles excepto DYP (cualquier circuito/banda/categoría)
        if ($base === 'dobles' && !$is_dyp) {
            $agg[$label_all_doubles]['jugados']++;
            if ($won) $agg[$label_all_doubles]['ganados']++;
        }

        // 2) “Open Dobles *”: unión de Open Dobles + España Dobles (+ España Open Dobles),
        //    SOLO si NO hay banda (mujeres/senior/junior/mixto) y NO es menor (amateur/rookie/master/pro-am)
        if (($key === 'open_dobles' || $key === 'espana_dobles') && $band === 'ninguna' && !$is_minor) {
            $agg[$label_open_star]['jugados']++;
            if ($won) $agg[$label_open_star]['ganados']++;
        }

        // 3) “Pro Dobles”: key canónica pro_dobles (da igual banda/categoría; si prefieres excluir bandas, dilo y lo filtro)
        if ($key === 'pro_dobles') {
            $agg[$label_pro_dobles]['jugados']++;
            if ($won) $agg[$label_pro_dobles]['ganados']++;
        }

        // 4) “Competiciones de Menor Categoría (Partidos) *”:
        //    Amateur / Rookie / Master / Pro-Am (en dobles), de cualquier circuito (Open/España/…).
        if ($base === 'dobles' && !$is_dyp && $is_minor) {
            $agg[$label_minor_star]['jugados']++;
            if ($won) $agg[$label_minor_star]['ganados']++;
        }
    }

    $this->match_aggregates = $this->finalize_rates($agg);
}

/*** AÑADE ESTE HELPER DEBAJO ***/
private function is_minor_category_name(string $raw): bool {
    // Detecta categorías menores por nombre textual (sin depender de la API)
    // Cubre variantes y tildes comunes
    $raw = mb_strtolower($raw, 'UTF-8');

    // Pro-Am/pro am/proam
    if (preg_match('/\bpro\s*[- ]?\s*am\b/u', $raw)) return true;

    // Amateur (y variantes amater/amateur)
    if (preg_match('/\bamateu?r\b/u', $raw)) return true;
    if (preg_match('/\bamater\b/u', $raw)) return true;

    // Rookie (principiante mapeado a rookie en otras partes, pero por si acaso)
    if (preg_match('/\brookie\b/u', $raw)) return true;
    if (preg_match('/\bprincipiante\b/u', $raw)) return true;

    // Master (categoría, no confundir con “España Master Dobles” que también es menor a efectos de tu estadística)
    if (preg_match('/\bmaster\b/u', $raw)) return true;

    return false;
}

    /** Devuelve true si el partido es un registro “NOEQUIPO ()” en alguno de los lados. */
    private function is_no_team($match): bool {
        return (
            (isset($match->equipoLocal) && $match->equipoLocal === 'NOEQUIPO ()') ||
            (isset($match->equipoVisitante) && $match->equipoVisitante === 'NOEQUIPO ()')
        );
    }

    /** Determina si el jugador ganó un partido dado. */
    private function did_player_win_in_match(array $name_parts, $match): bool {
        $is_local = $this->is_player_in_team($name_parts, $match->equipoLocal ?? '');
        return (($is_local && !empty($match->ganadorLocal)) || (!$is_local && empty($match->ganadorLocal) === false && !$match->ganadorLocal));
    }

    /** Añade “rate” (porcentaje) a cada bloque. */
    private function finalize_rates(array $agg): array {
        foreach ($agg as $label => &$r) {
            $j = max(0, (int)$r['jugados']);
            $g = max(0, (int)$r['ganados']);
            $r['rate'] = ($j > 0) ? round(($g / $j) * 100, 1) : 0.0;
        }
        return $agg;
    }

    /* ==================== C Ó D I G O   E X I S T E N T E ==================== */

    private function calculate_hitos() {
        $campeonatos_esp_map = [
            '2024' => ['id' => 133, 'rule' => 'espana_open'],
            '2023' => ['id' => 124, 'rule' => 'espana'],
            '2022' => ['id' => 118, 'rule' => 'open'],
            '2021' => ['id' => 115, 'rule' => 'open'],
            '2019' => ['id' => 114, 'rule' => 'open'],
            '2018' => ['id' => 112, 'rule' => 'espana'],
            '2017' => ['id' => 110, 'rule' => 'open'],
            '2016' => ['id' => 101, 'rule' => 'open'],
            '2015' => ['id' => 95,  'rule' => 'open'],
            '2014' => ['id' => 89,  'rule' => 'open'],
            '2013' => ['id' => 21,  'rule' => 'open'],
            '2012' => ['id' => 16,  'rule' => 'open'],
            '2010' => ['id' => 1,   'rule' => 'open'],
        ];

        $dobles_years = [];
        $individual_years = [];

        if (empty($this->honours)) {
            $this->hitos = ['campeon_esp_dobles_anios' => [], 'campeon_esp_individual_anios' => []];
            return;
        }

        foreach ($this->honours as $result) {
            if (!isset($result->posicion) || $result->posicion != 1) continue;

            $championship_year = null;
            $rule = null;

            foreach ($campeonatos_esp_map as $year => $data) {
                if ($result->torneoId == $data['id']) {
                    $championship_year = $year;
                    $rule = $data['rule'];
                    break;
                }
            }

            if (!$championship_year) continue;

            $comp_nombre = mb_strtolower(trim($result->nombreCompeticion), 'UTF-8');

            switch ($rule) {
                case 'espana':
                    if (strpos($comp_nombre, 'españa dobles') !== false) $dobles_years[] = $championship_year;
                    if (strpos($comp_nombre, 'españa individual') !== false) $individual_years[] = $championship_year;
                    break;
                case 'espana_open':
                    if (strpos($comp_nombre, 'españa open dobles') !== false) $dobles_years[] = $championship_year;
                    if (strpos($comp_nombre, 'españa open individual') !== false) $individual_years[] = $championship_year;
                    break;
                case 'open':
                    $is_open_doubles = strpos($comp_nombre, 'open dobles') !== false || strpos($comp_nombre, 'open doubles') !== false;
                    $is_open_singles = strpos($comp_nombre, 'open individual') !== false || strpos($comp_nombre, 'open singles') !== false;
                    if ($is_open_doubles) $dobles_years[] = $championship_year;
                    if ($is_open_singles) $individual_years[] = $championship_year;
                    break;
            }
        }

        sort($dobles_years);
        sort($individual_years);

        $this->hitos = [
            'campeon_esp_dobles_anios' => array_unique($dobles_years),
            'campeon_esp_individual_anios' => array_unique($individual_years),
        ];
    }

    private function calculate_summary_stats() {
        $wins = 0;
        $real_matches = array_filter($this->matches, function($match) {
            return !((isset($match->equipoLocal) && $match->equipoLocal === 'NOEQUIPO ()') || (isset($match->equipoVisitante) && $match->equipoVisitante === 'NOEQUIPO ()'));
        });
        $total_real_matches = count($real_matches);

        if ($total_real_matches > 0 && isset($this->basic_data->nombreJugador)) {
            $name_parts = explode(' ', $this->basic_data->nombreJugador);
            foreach ($real_matches as $match) {
                $is_local = $this->is_player_in_team($name_parts, $match->equipoLocal);
                if (($is_local && $match->ganadorLocal) || (!$is_local && !$match->ganadorLocal)) {
                    $wins++;
                }
            }
        }
        $titles = count(array_filter($this->honours, fn($p) => isset($p->posicion) && $p->posicion == 1));
        $total_competitions = count($this->honours);
        $this->summary_stats = [
            'total_matches' => $total_real_matches,
            'wins' => $wins,
            'losses' => $total_real_matches - $wins,
            'win_rate' => $total_real_matches > 0 ? round(($wins / $total_real_matches) * 100) . '%' : '0%',
            'total_competitions' => $total_competitions,
            'titles' => $titles,
            'podiums' => count(array_filter($this->honours, fn($p) => isset($p->posicion) && in_array($p->posicion, [1, 2, 3]))),
            'unique_tournaments' => $total_real_matches > 0 ? count(array_unique(array_column($real_matches, 'torneo'))) : 0,
            'competition_win_rate' => $total_competitions > 0 ? round(($titles / $total_competitions) * 100) . '%' : '0%',
        ];
    }

    private function calculate_match_stats_by_type() {
        $stats_norm = [];
        if (!empty($this->matches) && isset($this->basic_data->nombreJugador)) {
            $name_parts = explode(' ', $this->basic_data->nombreJugador);
            foreach ($this->matches as $p) {
                if ($this->is_no_team($p)) continue;

                // Normalización de etiqueta textual antigua (se mantiene para no romper plantillas)
                $clean_name = $this->normalize_competition_name($p->competicion ?? '');

                if (!isset($stats_norm[$clean_name])) { $stats_norm[$clean_name] = ['jugados' => 0, 'ganados' => 0]; }
                $stats_norm[$clean_name]['jugados']++;

                $is_local = $this->is_player_in_team($name_parts, $p->equipoLocal);
                if (($is_local && $p->ganadorLocal) || (!$is_local && !$p->ganadorLocal)) {
                    $stats_norm[$clean_name]['ganados']++;
                }
            }
        }
        $this->match_stats_by_type = $this->group_stats_by_type($stats_norm);
    }

    private function calculate_honours_stats_by_type() {
        $stats_norm = [];
        if (!empty($this->honours)) {
            foreach ($this->honours as $p) {
                $clean_name = $this->normalize_competition_name($p->nombreCompeticion ?? '');
                if (!isset($stats_norm[$clean_name])) { $stats_norm[$clean_name] = ['jugados' => 0, 'ganados' => 0]; }
                $stats_norm[$clean_name]['jugados']++;
                if (isset($p->posicion) && $p->posicion == 1) {
                    $stats_norm[$clean_name]['ganados']++;
                }
            }
        }
        $this->honours_stats_by_type = $this->group_stats_by_type($stats_norm);
    }

    private function group_stats_by_type($stats_norm) {
        $grouped_stats = [];
        $all_types = ['Dobles', 'Individual', 'Mixto', 'Competiciones de Menor Categoría'];
        foreach ($all_types as $type) {
            $grouped_stats[$type] = ['details' => [], 'total' => ['jugados' => 0, 'ganados' => 0, 'rate' => 0]];
        }
        foreach ($stats_norm as $comp_name => $stats) {
            $type = $this->get_competition_type_for_stats($comp_name);
            if (!array_key_exists($type, $grouped_stats)) {
                $type = 'Competiciones de Menor Categoría';
            }
            $stats['rate'] = ($stats['jugados'] > 0) ? round(($stats['ganados'] / $stats['jugados']) * 100, 1) : 0;
            $grouped_stats[$type]['details'][$comp_name] = $stats;
        }
        foreach ($grouped_stats as $type => &$data) {
            if (!empty($data['details'])) {
                $total = array_reduce($data['details'], function($c, $i){ $c['jugados'] += $i['jugados']; $c['ganados'] += $i['ganados']; return $c; }, ['jugados'=>0,'ganados'=>0]);
                $total['rate'] = ($total['jugados'] > 0) ? round(($total['ganados'] / $total['jugados']) * 100, 1) : 0;
                $data['total'] = $total;
                ksort($data['details']);
            }
        }
        return $grouped_stats;
    }

    private function group_matches_for_history() {
        $grouped = [];
        $inactivity = [];

        if (!empty($this->matches)) {
            foreach ($this->matches as $partido) {
                $season = (isset($partido->temporada) && !empty($partido->temporada))
                    ? $partido->temporada
                    : 'Sin Temporada';

                if ($this->is_no_team($partido)) {
                    $inactivity[$season][] = $partido;
                    continue;
                }

                $grouped[$season][$partido->torneo][$partido->competicion][] = $partido;
            }
        }

        krsort($grouped);
        krsort($inactivity);

        foreach ($grouped as &$seasons) {
            foreach ($seasons as &$tournaments) {
                uksort($tournaments, function($a, $b) {
                    $priority_a = $this->get_competition_sort_priority($a);
                    $priority_b = $this->get_competition_sort_priority($b);
                    if ($priority_a === $priority_b) return 0;
                    return ($priority_a < $priority_b) ? -1 : 1;
                });
            }
        }

        $this->grouped_matches = $grouped;
        $this->inactivity_events = $inactivity;
    }

    private function get_competition_sort_priority($name) {
        $name_lower = mb_strtolower($name);
        $priority = 99;
        if (strpos($name_lower, 'open') !== false) {
            if (strpos($name_lower, 'españa') !== false) { $priority = 2; }
            else { $priority = 1; }
        } elseif (strpos($name_lower, 'pro') !== false) { $priority = 3; }
        elseif (strpos($name_lower, 'mixto') !== false) { $priority = 4; }
        elseif (strpos($name_lower, 'senior') !== false) { $priority = 5; }
        elseif (strpos($name_lower, 'junior') !== false) { $priority = 6; }
        elseif (strpos($name_lower, 'master') !== false) { $priority = 7; }
        elseif (strpos($name_lower, 'amateur') !== false) { $priority = 8; }
        elseif (strpos($name_lower, 'rookie') !== false) { $priority = 9; }
        elseif (strpos($name_lower, 'dyp') !== false) { $priority = 10; }
        if (stripos((string)($name_lower ?? ''), 'dobles') !== false) { $priority += 0.1; }
        elseif (stripos((string)($name_lower ?? ''), 'individual') !== false) { $priority += 0.2; }
        return $priority;
    }

    private function calculate_point_evolution() {
        $grouped = [];
        if (!empty($this->matches)) {
            foreach($this->matches as $partido) {
                if ($this->is_no_team($partido)) continue;
                $season = (isset($partido->temporada) && !empty($partido->temporada))  ? $partido->temporada : 'Sin Temporada';
                $grouped[$season][$this->normalize_competition_name($partido->competicion ?? '')][] = $partido;
            }
        }
        $processed = [];
        krsort($grouped);
        foreach ($grouped as $season => $competitions) {
            ksort($competitions);
            foreach ($competitions as $comp_name => $partidos) {
                if(empty($partidos)) continue;
                $puntos_iniciales = 0; $puntos_finales = 0;
                if(isset($partidos[0]->puntuacionFinal) && isset($partidos[0]->puntosGanados)) {
                    $puntos_iniciales = $partidos[0]->puntuacionFinal - $partidos[0]->puntosGanados;
                    $puntos_finales = $puntos_iniciales;
                }
                for ($i = count($partidos) - 1; $i >= 0; $i--) {
                    if (isset($partidos[$i]->puntuacionFinal) && $partidos[$i]->puntuacionFinal > 0) {
                        $puntos_finales = $partidos[$i]->puntuacionFinal; break;
                    }
                }
                $processed[$season][$comp_name] = ['inicial' => $puntos_iniciales, 'final' => $puntos_finales];
            }
        }
        $this->point_evolution = $processed;
    }

    public function is_player_in_team($name_parts, $team_string) {
        if (empty($name_parts)) return false;
        foreach ($name_parts as $part) {
            if (strlen(trim($part)) > 1 && stripos((string)($team_string ?? ''), trim($part)) === false) {
                return false;
            }
        }
        return true;
    }

    private function get_competition_type_for_stats($normalized_name) {
        if (stripos((string)($normalized_name ?? ''), 'individual') !== false) { return 'Individual'; }
        if (stripos((string)($normalized_name ?? ''), 'mixto') !== false) { return 'Mixto'; }
        if (stripos((string)($normalized_name ?? ''), 'dobles') !== false) {
            if (stripos((string)($normalized_name ?? ''), 'españa') !== false || stripos((string)($normalized_name ?? ''), 'open') !== false || stripos((string)($normalized_name ?? ''), 'pro') !== false) {
                return 'Dobles';
            }
            return 'Competiciones de Menor Categoría';
        }
        $minor_keywords = ['senior', 'junior', 'amateur', 'rookie', 'dyp', 'master'];
        foreach ($minor_keywords as $keyword) {
            if (stripos((string)($normalized_name ?? ''), $keyword) !== false) {
                return 'Competiciones de Menor Categoría';
            }
        }
        return 'Competiciones de Menor Categoría';
    }

    private function normalize_competition_name($raw_name) {
        $clean_name = mb_strtolower(trim((string)$raw_name), 'UTF-8');
        $clean_name = str_ireplace(
            ['doubles', 'singles', 'principiante', 'amater'],
            ['dobles',  'individual', 'rookie',      'amateur'],
            $clean_name
        );
        $clean_name = str_replace('-', ' ', $clean_name);
        $clean_name = preg_replace('/\s+/', ' ', $clean_name);

        if (strpos($clean_name, 'españa') !== false) {
            $clean_name = str_replace('españa ', '', $clean_name);
        }
        if (strpos($clean_name, 'pro') !== false && strpos($clean_name, 'dobles') !== false) { return 'Pro Dobles'; }
        if (strpos($clean_name, 'pro') !== false && strpos($clean_name, 'individual') !== false) { return 'Pro Individual'; }
        if (strpos($clean_name, 'open') !== false && strpos($clean_name, 'dobles') !== false) { return 'Open Dobles'; }
        if (strpos($clean_name, 'open') !== false && strpos($clean_name, 'individual') !== false) { return 'Open Individual'; }
        return ucwords($clean_name);
    }
}
