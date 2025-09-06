<?php
/**
 * Archivo: includes/processors/class-futbolin-h2h-processor.php
 */
if (!defined('ABSPATH')) exit;

class Futbolin_H2H_Processor {

    public $p1_stats = [];
    public $p2_stats = [];
    public $h2h_stats = [];
    public $direct_matches = [];

    public function __construct($p1_data, $p1_matches, $p1_positions, $p2_data, $p2_matches, $p2_positions) {
        if (!$p1_data || !$p2_data) return;

        $this->process_direct_matches($p1_data, $p1_matches, $p2_data);
        $this->p1_stats = $this->calculate_career_stats($p1_data, $p1_matches, $p1_positions);
        $this->p2_stats = $this->calculate_career_stats($p2_data, $p2_matches, $p2_positions);
    }

    private function process_direct_matches($p1_data, $p1_matches, $p2_data) {
        $p1_id = isset($p1_data->jugadorId) ? (int)$p1_data->jugadorId : (isset($p1_data->id) ? (int)$p1_data->id : 0);
        $p2_id = isset($p2_data->jugadorId) ? (int)$p2_data->jugadorId : (isset($p2_data->id) ? (int)$p2_data->id : 0);

        $p2_name_parts = explode(' ', (string)($p2_data->nombreJugador ?? ''));
        $p1_wins = 0;
        $direct_matches_found = [];

        foreach ($p1_matches as $match) {
            // 1) Intento por IDs (preferido)
            [$local_ids, $visitante_ids] = $this->extract_team_ids($match); // arrays de ints (o vacíos)

            $p1_is_local = null; $p2_is_local = null;

            if (!empty($local_ids) || !empty($visitante_ids)) {
                $p1_is_local = ($p1_id && in_array($p1_id, $local_ids, true)) ? true : (($p1_id && in_array($p1_id, $visitante_ids, true)) ? false : null);
                $p2_is_local = ($p2_id && in_array($p2_id, $local_ids, true)) ? true : (($p2_id && in_array($p2_id, $visitante_ids, true)) ? false : null);
            }

            // 2) Fallback por nombre si IDs no estaban o alguno es null
            if ($p1_is_local === null || $p2_is_local === null) {
                $p1_is_local = $this->name_in_team(($p1_data->nombreJugador ?? ''), ($match->equipoLocal ?? '')) ? true :
                               ($this->name_in_team(($p1_data->nombreJugador ?? ''), ($match->equipoVisitante ?? '')) ? false : null);

                // Para p2 usamos “todas las partes” del nombre (como hacías)
                $p2_is_local = $this->all_parts_in_team($p2_name_parts, ($match->equipoLocal ?? '')) ? true :
                               ($this->all_parts_in_team($p2_name_parts, ($match->equipoVisitante ?? '')) ? false : null);
            }

            // Si no pudimos determinar ambos, seguimos al siguiente
            if (!is_bool($p1_is_local) || !is_bool($p2_is_local)) continue;

            // Son rivales (uno local y otro visitante)
            if ($p1_is_local === $p2_is_local) continue;

            // ¿Ganó p1?
            $p1_won = ($p1_is_local && !empty($match->ganadorLocal)) || (!$p1_is_local && empty($match->ganadorLocal));
            if ($p1_won) $p1_wins++;

            $direct_matches_found[] = $match;
        }

        // Ordena por temporada desc y luego torneo
        usort($direct_matches_found, function($a, $b) {
            $t1 = (string)($b->temporada ?? '');
            $t2 = (string)($a->temporada ?? '');
            $cmp = strcmp($t1, $t2);
            if ($cmp !== 0) return $cmp;
            return strcmp((string)($b->torneo ?? ''), (string)($a->torneo ?? ''));
        });

        $this->direct_matches = $direct_matches_found;
        $this->h2h_stats['p1_wins'] = $p1_wins;
        $this->h2h_stats['p2_wins'] = count($direct_matches_found) - $p1_wins;
    }

    /**
     * Extrae IDs por equipo intentando varios nombres de campos habituales.
     * Devuelve [local_ids[], visitante_ids[]]
     */
    private function extract_team_ids($match): array {
        $candidates = [
            // Arrays
            ['equipoLocalIds','equipoVisitanteIds'],
            ['jugadoresLocalIds','jugadoresVisitanteIds'],
            ['localIds','visitanteIds'],
            ['idsLocal','idsVisitante'],
            // Escalares (local1Id/local2Id/visitante1Id/visitante2Id)
            ['local1Id','local2Id','visitante1Id','visitante2Id'],
        ];

        foreach ($candidates as $pair) {
            // Caso arrays de ids
            if (count($pair) === 2 && isset($match->{$pair[0]}, $match->{$pair[1]})) {
                $l = $match->{$pair[0]};
                $v = $match->{$pair[1]};
                $local  = is_array($l) ? array_map('intval', $l) : [];
                $visit  = is_array($v) ? array_map('intval', $v) : [];
                if ($local || $visit) return [$local, $visit];
            }
            // Caso 4 campos escalares
            if (count($pair) === 4 &&
                isset($match->{$pair[0]}, $match->{$pair[1]}, $match->{$pair[2]}, $match->{$pair[3]})) {
                $local = [];
                $visit = [];
                foreach ([$pair[0], $pair[1]] as $k) { if (is_numeric($match->{$k})) $local[] = (int)$match->{$k}; }
                foreach ([$pair[2], $pair[3]] as $k) { if (is_numeric($match->{$k})) $visit[] = (int)$match->{$k}; }
                if ($local || $visit) return [$local, $visit];
            }
        }
        return [[], []];
    }

    private function name_in_team(string $full_name, string $team_str): bool {
        $full_name = trim($full_name);
        if ($full_name === '') return false;
        return (stripos((string)($team_str ?? ''), $full_name) !== false);
    }

    private function all_parts_in_team(array $name_parts, string $team_str): bool {
        $has_any = false;
        foreach ($name_parts as $part) {
            $part = trim((string)$part);
            if (strlen($part) <= 1) continue;
            $has_any = true;
            if (stripos((string)($team_str ?? ''), $part) === false) return false;
        }
        return $has_any;
    }

    private function calculate_career_stats($player_data, $matches, $positions) {
        $wins = 0;
        $name = (string)($player_data->nombreJugador ?? '');
        $pid  = isset($player_data->jugadorId) ? (int)$player_data->jugadorId : (isset($player_data->id) ? (int)$player_data->id : 0);

        foreach ($matches as $m) {
            [$local_ids, $visitante_ids] = $this->extract_team_ids($m);
            $is_local = null;

            if (!empty($local_ids) || !empty($visitante_ids)) {
                if ($pid && in_array($pid, $local_ids, true)) $is_local = true;
                elseif ($pid && in_array($pid, $visitante_ids, true)) $is_local = false;
            }
            if ($is_local === null && $name !== '') {
                $is_local = (stripos(($m->equipoLocal ?? ''), $name) !== false) ? true :
                            ((stripos(($m->equipoVisitante ?? ''), $name) !== false) ? false : null);
            }
            if (!is_bool($is_local)) continue;

            $won = ($is_local && !empty($m->ganadorLocal)) || (!$is_local && empty($m->ganadorLocal));
            if ($won) $wins++;
        }

        $total_matches = is_array($matches) ? count($matches) : 0;
        $losses = $total_matches - $wins;
        $titles = 0;
        $unique_tournaments = [];
        if (is_array($positions)) {
            foreach ($positions as $p) {
                if (isset($p->posicion) && (int)$p->posicion === 1) $titles++;
                if (isset($p->torneoId)) $unique_tournaments[$p->torneoId] = true;
            }
        }

        $years_active = (!empty($player_data->activoDesdeTorneoAnio) && is_numeric($player_data->activoDesdeTorneoAnio))
            ? (date('Y') - (int)$player_data->activoDesdeTorneoAnio)
            : '-';

        return [
            'name'                => (string)($player_data->nombreJugador ?? ''),
            'titles'              => $titles,
            'years_active'        => $years_active,
            'total_matches'       => $total_matches,
            'wins'                => $wins,
            'losses'              => $losses,
            'total_tournaments'   => count($unique_tournaments),
            'total_competitions'  => is_array($positions) ? count($positions) : 0,
        ];
    }
}
