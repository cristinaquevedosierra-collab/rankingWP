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

// Helpers compartidos (exclusiones, detección de resultados reales)
if (!function_exists('_futb_has_real_result')) {
    include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
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
    /**
     * Resumen de modalidades profesionales (PHP 8.2-safe).
     * Evita propiedades dinámicas deprecated.
     */
    public $professional_stats = [];


    public function __construct($player_data, $partidos_items, $posiciones_items) {
        if (!$player_data) { return; }
        $this->basic_data = $player_data;
        $this->matches = is_array($partidos_items) ? $partidos_items : [];
        $this->honours = is_array($posiciones_items) ? $posiciones_items : [];

        $this->calculate_summary_stats();
        $this->calculate_professional_summary_stats();
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
    $jugador_id = isset($this->basic_data->jugadorId) ? intval($this->basic_data->jugadorId) : null;

    $is_local = $this->is_player_in_equipo(
        $jugador_id,
        $name_parts,
        $match->equipoLocalDTO ?? null,
        $match->equipoLocal ?? ''
    );
    $is_visit = $this->is_player_in_equipo(
        $jugador_id,
        $name_parts,
        $match->equipoVisitanteDTO ?? null,
        $match->equipoVisitante ?? ''
    );

    if (!$is_local && !$is_visit) return false;

    $gl = isset($match->ganadorLocal) ? (bool)$match->ganadorLocal : (isset($match->ganoLocal) ? (bool)$match->ganoLocal : null);
    if ($gl === null) return false;

    return ($is_local && $gl === true) || ($is_visit && $gl === false);
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

    /**
     * Calcula el bloque "Resultados en modalidades profesionales".
     * Reglas:
     * - Partidos: solo tipoCompeticionId ∈ {24,25,13,12,11,1}, excluyendo fase=Liguilla y NOEQUIPO.
     * - Victorias: según lado ganador (ganadorLocal) y pertenencia del jugador al equipo.
     * - Competiciones jugadas/ganadas: MISMO conjunto de tipoCompeticionId, PERO contando participaciones
     *   aunque exista fase de liguilla (no se excluye para este cálculo).
     */
    private function calculate_professional_summary_stats() {
        $allowed_types = [24,25,13,12,11,1];
        $name_parts = [];
        if (isset($this->basic_data->nombreJugador)) {
            $name_parts = explode(' ', (string)$this->basic_data->nombreJugador);
        }

        // --- Partidos (excluye liguilla y NOEQUIPO) ---
        $pro_matches = array_filter($this->matches ?? [], function($m) use ($allowed_types) {
            $type_ok = in_array(intval($m->tipoCompeticionId ?? 0), $allowed_types, true);
            if (!$type_ok) return false;
            $fase = isset($m->fase) ? (string)$m->fase : '';
            $is_liguilla = (function_exists('_futb_is_liguilla_fase') ? _futb_is_liguilla_fase($fase, $m) : (stripos($fase, 'liguilla') !== false));
            if ($is_liguilla) return false;
            $no_team = ((isset($m->equipoLocal) && $m->equipoLocal === 'NOEQUIPO ()')
                      && (isset($m->equipoVisitante) && $m->equipoVisitante === 'NOEQUIPO ()'));
            return !$no_team;
        });

        $wins = 0;
        $total_matches = count($pro_matches);
        if ($total_matches > 0 && !empty($name_parts)) {
            foreach ($pro_matches as $match) {
                $won = $this->did_player_win_in_match($name_parts, $match);
                if ($won) { $wins++; }
            }
        }
        $win_rate = ($total_matches > 0) ? round(($wins / $total_matches) * 100, 2) : 0.0;

        // --- Competiciones (incluye liguilla si la hubiera) ---
        // Derivamos el conjunto de competiciones desde los partidos (sin excluir liguilla).
        $played_keys = [];
        foreach (($this->matches ?? []) as $m) {
            $type_ok = in_array(intval($m->tipoCompeticionId ?? 0), $allowed_types, true);
            if (!$type_ok) continue;
            // Normaliza una clave de competición; preferimos competicionId si existe, si no torneoId+tipoCompeticionId
            $comp_id = isset($m->competicionId) ? (string)$m->competicionId : null;
            $torneo_id = isset($m->torneoId) ? (string)$m->torneoId : null;
            $tipo_id = isset($m->tipoCompeticionId) ? (string)$m->tipoCompeticionId : null;
            $key = $comp_id !== null ? "C:$comp_id" : ("T:$torneo_id-TC:$tipo_id");
            $played_keys[$key] = true;
        }
        $total_competitions = count($played_keys);

        // Títulos ganados dentro de esos tipos: contamos honours con posicion=1
        $titles = 0;
        if (!empty($this->honours) && $total_competitions > 0) {
            foreach ($this->honours as $p) {
                
            $pos = isset($p->posicion) ? intval($p->posicion) : 0;
                if ($pos !== 1) continue;
                // Intentamos casar la competición del honour con la clave usada arriba
                $comp_id = isset($p->competicionId) ? (string)$p->competicionId : null;
                $torneo_id = isset($p->torneoId) ? (string)$p->torneoId : null;
                $tipo_id = isset($p->tipoCompeticionId) ? (string)$p->tipoCompeticionId : null;
                // si no trae tipoCompeticionId, intentamos inferirlo con matches (lookup por torneo+nombreCompeticion)
                $keys = [];
                if ($comp_id !== null) { $keys[] = "C:$comp_id"; }
                if ($torneo_id !== null && $tipo_id !== null) { $keys[] = "T:$torneo_id-TC:$tipo_id"; }
                // fallback pobre: si no hay tipo_id en honours, intentamos todos los posibles para ese torneo
                if ($torneo_id !== null && $tipo_id === null) {
                    foreach ($allowed_types as $aid) {
                        $keys[] = "T:$torneo_id-TC:$aid";
                    }
                }
                $matched = false;
                foreach ($keys as $k) {
                    if (isset($played_keys[$k])) { $matched = true; break; }
                }
                if ($matched) { $titles++; }
            }
        }
        $comp_rate = ($total_competitions > 0) ? round(($titles / $total_competitions) * 100, 2) : 0.0;

        $this->professional_stats = [
            'total_matches'        => $total_matches,
            'wins'                 => $wins,
            'win_rate'             => $win_rate,
            'total_competitions'   => $total_competitions,
            'titles'               => $titles,
            'competition_win_rate' => $comp_rate,
        ];
    }
    



private function calculate_summary_stats() {
    $wins = 0; $total_real_matches = 0; $tournaments_played = [];
    $jugador_id = isset($this->basic_data->jugadorId) ? intval($this->basic_data->jugadorId) : null;
    $name_parts = isset($this->basic_data->nombreJugador) ? preg_split('/\s+/', (string)$this->basic_data->nombreJugador) : [];

    if (!empty($this->matches) && is_array($this->matches)) {
        foreach ($this->matches as $match) {
            // Usar el predicado alineado con player-stats.php
            if (function_exists('_futb_should_count_for_stats') && !_futb_should_count_for_stats($match)) { continue; }

            $gl = isset($match->ganadorLocal) ? (bool)$match->ganadorLocal : (isset($match->ganoLocal) ? (bool)$match->ganoLocal : null);
            if ($gl === null) continue;

            // Determinar lado del jugador
            $is_local = $this->is_player_in_equipo($jugador_id, $name_parts, $match->equipoLocalDTO ?? null, $match->equipoLocal ?? '');
            $is_visit = $this->is_player_in_equipo($jugador_id, $name_parts, $match->equipoVisitanteDTO ?? null, $match->equipoVisitante ?? '');
            if (!$is_local && !$is_visit) continue;

            $total_real_matches++;
            if (($is_local && $gl === true) || ($is_visit && $gl === false)) { $wins++; }

            if (isset($match->torneoId)) { $tournaments_played[(string)$match->torneoId] = true; }
        }
    }

    $global_total_matches = $total_real_matches;
    $global_wins = $wins;
    $global_win_rate = ($global_total_matches > 0) ? round(($global_wins / $global_total_matches) * 100, 2) : 0.0;

    // Global competitions/titles (usa honores completos, sin filtro de tipos)
    $global_total_competitions = 0;
    $global_titles = 0;
    if (is_array($this->honours)) {
        $seen_competitions = [];
        foreach ($this->honours as $h) {
            // contamos competiciones jugadas únicas por torneoId+competicionId si están disponibles
            $cid = null;
            if (isset($h->torneoId) || isset($h->competicionId)) {
                $cid = (string)($h->torneoId ?? '') . '#' . (string)($h->competicionId ?? '');
            } elseif (isset($h->nombreCompeticion)) {
                $cid = 'name:' . mb_strtolower((string)$h->nombreCompeticion, 'UTF-8');
            }
            if ($cid !== null) { $seen_competitions[$cid] = true; }
            if (isset($h->posicion) && intval($h->posicion) === 1) { $global_titles++; }
        }
        $global_total_competitions = count($seen_competitions);
    }
    $global_comp_wr = ($global_total_competitions > 0) ? round(($global_titles / $global_total_competitions) * 100, 2) : 0.0;

    $this->summary_stats = [
        // Claves esperadas por la plantilla de “Resultados Globales”
        'total_matches'         => $global_total_matches,
        'wins'                  => $global_wins,
        'win_rate'              => $global_win_rate,
        'total_competitions'    => $global_total_competitions,
        'titles'                => $global_titles,
        'competition_win_rate'  => $global_comp_wr,
        // Compatibilidad hacia atrás
        'matches'               => $global_total_matches,
        'tournaments_played'    => count($tournaments_played),
    ];
}


public function is_player_in_equipo($jugador_id, $name_parts, $equipoDTO, $equipoStr) {
    if (is_object($equipoDTO) && isset($equipoDTO->jugadores) && is_array($equipoDTO->jugadores)) {
        foreach ($equipoDTO->jugadores as $j) {
            $jid = isset($j->jugadorId) ? intval($j->jugadorId) : null;
            if ($jid !== null && $jugador_id !== null && $jid === $jugador_id) {
                return true;
            }
        }
    }
    $team_string = (string)($equipoStr ?? '');
    if (!empty($name_parts) && is_array($name_parts)) {
        foreach ($name_parts as $part) {
            $part = trim((string)$part);
            if ($part === '' || strlen($part) <= 1) continue;
            if (stripos($team_string, $part) === false) {
                return false;
            }
        }
        return true;
    }
    return false;
}


private function calculate_hitos(): void {
// Inicializa estructura de hitos; se rellenará si hay honores disponibles
$this->hitos = ['campeon_esp_dobles_anios' => [], 'campeon_esp_individual_anios' => []];

// Cachear hitos por jugador (persistente 365 días) para acelerar pestaña Hitos
try {
    $pid = isset($this->basic_data->jugadorId) ? (string)$this->basic_data->jugadorId : '';
    if ($pid !== '' && function_exists('get_transient') && function_exists('set_transient')) {
        // Atar caché a versión de dataset para invalidaciones limpias
        $ver = function_exists('get_option') ? (string)(get_option('rf_dataset_ver') ?: '1') : '1';
        $ck_h = 'rf:hitos:player:v' . $ver . ':' . $pid;
        $cv = get_transient($ck_h);
        if (is_array($cv) && isset($cv['campeon_esp_dobles_anios']) && isset($cv['campeon_esp_individual_anios'])) {
            $this->hitos = $cv;
            return; // usar cache y salir
        }
    }
} catch (\Throwable $e) { /* ignore */ }

// Hitos de podio nacional por temporada (Open)
if (isset($this->basic_data) && is_object($this->basic_data) && isset($this->basic_data->jugadorId) && class_exists('Futbolin_Rankgen_Service')) {
    $podium = \Futbolin_Rankgen_Service::get_player_podium_years($this->basic_data->jugadorId);
    $this->hitos['numero1_temporada_open_dobles_anios']     = isset($podium['dobles']['no1']) ? $podium['dobles']['no1'] : [];
    $this->hitos['numero1_temporada_open_individual_anios'] = isset($podium['individual']['no1']) ? $podium['individual']['no1'] : [];
    $this->hitos['numero2_temporada_open_dobles_anios']     = isset($podium['dobles']['no2']) ? $podium['dobles']['no2'] : [];
    $this->hitos['numero2_temporada_open_individual_anios'] = isset($podium['individual']['no2']) ? $podium['individual']['no2'] : [];
    $this->hitos['numero3_temporada_open_dobles_anios']     = isset($podium['dobles']['no3']) ? $podium['dobles']['no3'] : [];
    $this->hitos['numero3_temporada_open_individual_anios'] = isset($podium['individual']['no3']) ? $podium['individual']['no3'] : [];
}




// 1) Fuente de verdad: endpoint oficial de campeones
$campeones_ok = false;
if (class_exists('Futbolin_API_Client')) {
    try {
        $api = new Futbolin_API_Client();
        if (method_exists($api, 'get_campeones_index')) {
            $idx = $api->get_campeones_index();
            $jid = (int)($this->basic_data->jugadorId ?? 0);
            if (is_array($idx) && $jid && isset($idx[$jid])) {
                $this->hitos['campeon_esp_dobles_anios'] = isset($idx[$jid]['dobles']) ? (array)$idx[$jid]['dobles'] : [];
                $this->hitos['campeon_esp_individual_anios'] = isset($idx[$jid]['individual']) ? (array)$idx[$jid]['individual'] : [];
                $campeones_ok = true;
            }
        } else if (method_exists($api, 'get_campeones_espania')) {
            // Fallback: mantenemos camino anterior si no existe índice
            $champ = $api->get_campeones_espania();
            if (is_array($champ)) {
                foreach ($champ as $row) {
                    if ((int)($row->jugadorId ?? 0) === (int)($this->basic_data->jugadorId ?? 0)) {
                        $grabYears = function($arr){
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
                                    $tid = (int)$ii->temporadaId;
                                    $map = Futbolin_Normalizer::temporada_year_map();
                                    if (isset($map[(string)$tid]) && is_numeric($map[(string)$tid])) { $year = (int)$map[(string)$tid]; }
                                }
                                if ($year) { $out[] = $year; }
                            }
                            $out = array_values(array_unique(array_filter($out, function($v){ return is_numeric($v) && (int)$v >= 2011; })));
                            sort($out);
                            return array_map('strval', $out);
                        };
                        $years_d = $grabYears(($row->torneosDobles ?? []));
                        $years_i = $grabYears(($row->torneosIndividual ?? []));
                        $this->hitos['campeon_esp_dobles_anios'] = $years_d;
                        $this->hitos['campeon_esp_individual_anios'] = $years_i;
                        $campeones_ok = true;
                        break;
                    }
                }
            }
        }
    } catch (\Throwable $e) { /* fallback abajo */ }
}
if ($campeones_ok) {
    // Asegurar orden
    sort($this->hitos['campeon_esp_dobles_anios'], SORT_NATURAL);
    sort($this->hitos['campeon_esp_individual_anios'], SORT_NATURAL);
    // Guardar cache larga
    try {
        if (isset($pid) && $pid !== '' && function_exists('set_transient')) {
            $ver = function_exists('get_option') ? (string)(get_option('rf_dataset_ver') ?: '1') : '1';
            set_transient('rf:hitos:player:v' . $ver . ':' . $pid, $this->hitos, 365 * DAY_IN_SECONDS);
        }
    } catch (\Throwable $e) {}
    return;
}

// 2) Fallback MUY ESTRICTO (si no hay endpoint o no devuelve al jugador):
//    - Solo cuenta posicion=1
//    - tipoCompeticionId ∈ {24 (ES IND), 25 (ES DOB)}  **OJO** la API puede compartir IDs con sub-brackets
//    - nombreCompeticion DEBE contener "campeonato" y "espa"
//    - EXCLUIR SI contiene cualquiera de: open, amateur, amater, rookie, proam, master, dyp
if (!empty($this->honours) && is_array($this->honours)) {
    $ban = ['open','amateur','amater','rookie','proam','master','dyp'];
    $tmp_ind = []; $tmp_dob = [];
    foreach ($this->honours as $result) {
        if (!isset($result->posicion) || intval($result->posicion) !== 1) { continue; }

        $tipoId   = isset($result->tipoCompeticionId) ? intval($result->tipoCompeticionId) : 0;
        $modalId  = isset($result->modalidadId) ? intval($result->modalidadId) : 0; // 1=Individual, 2=Dobles
        $nombre   = mb_strtolower(trim((string)($result->nombreCompeticion ?? $result->competicion ?? '')), 'UTF-8');
        $torneo   = mb_strtolower(trim((string)($result->nombreTorneo ?? '')), 'UTF-8');

        // nombre debe parecer "Campeonato de España ..."
        $looks_es = (strpos($nombre, 'campeonato') !== false) && (strpos($nombre, 'espa') !== false);
        if (!$looks_es) { continue; }

        // excluir si tiene palabras prohibidas
        $banned = false;
        foreach ($ban as $w) { if (strpos($nombre, $w) !== false) { $banned = true; break; } }
        if ($banned) { continue; }

        // requerimos además el tipo 24/25 para reforzar
        $is_es_ind = ($tipoId === 24);
        $is_es_dob = ($tipoId === 25);
        if (!($is_es_ind || $is_es_dob)) { continue; }

        // temporada/año
        $season = '';
        if (isset($result->temporada) && preg_match('/(20\\d{2})/', (string)$result->temporada, $m2)) { $season = $m2[1]; }
        elseif (isset($result->temporadaId)) { $season = (string)$result->temporadaId; }

        if ($is_es_ind || ($modalId === 1 && (strpos($nombre, 'individual') !== false || strpos($nombre, 'indiv') !== false))) {
            $tmp_ind[] = $season;
        } elseif ($is_es_dob || ($modalId === 2 && strpos($nombre, 'dobl') !== false)) {
            $tmp_dob[] = $season;
        }
    }
    $this->hitos['campeon_esp_dobles_anios'] = Futbolin_Normalizer::normalize_years_from_temporada($tmp_dob);
        $this->hitos['campeon_esp_individual_anios'] = Futbolin_Normalizer::normalize_years_from_temporada($tmp_ind);
    // Filtro conservador: ignorar años anteriores a 2011
    $this->hitos['campeon_esp_dobles_anios'] = array_values(array_filter($this->hitos['campeon_esp_dobles_anios'], function($y){ return intval($y) >= 2011; }));
    $this->hitos['campeon_esp_individual_anios'] = array_values(array_filter($this->hitos['campeon_esp_individual_anios'], function($y){ return intval($y) >= 2011; }));
    // Guardar cache larga también en fallback
    try {
        if (isset($pid) && $pid !== '' && function_exists('set_transient')) {
            $ver = function_exists('get_option') ? (string)(get_option('rf_dataset_ver') ?: '1') : '1';
            set_transient('rf:hitos:player:v' . $ver . ':' . $pid, $this->hitos, 365 * DAY_IN_SECONDS);
        }
    } catch (\Throwable $e) {}
}
}


    /** Añade “rate” (porcentaje) a cada bloque del array de agregados. */
    private function finalize_rates(array $agg): array {
        foreach ($agg as $label => &$r) {
            $j = isset($r['jugados']) ? max(0, (int)$r['jugados']) : 0;
            $g = isset($r['ganados']) ? max(0, (int)$r['ganados']) : 0;
            $r['rate'] = ($j > 0) ? round(($g / $j) * 100, 2) : 0.0;
        }
        return $agg;
    }

}
