<?php
if (!defined('ABSPATH')) exit;

require_once dirname(__DIR__) . '/core/class-futbolin-normalizer.php';

class Futbolin_Reports_Engine {
    private $api;

    public function __construct($api_client) { $this->api = $api_client; }

    private function get_group_ids($opt_key): array {
        $ids = get_option($opt_key, []);
        return array_values(array_unique(array_map('intval', (array)$ids)));
    }

    private function groups(): array {
        return [
            'open'   => $this->get_group_ids('futbolin_group_open_ids'),
            'rookie' => $this->get_group_ids('futbolin_group_rookie_ids'),
            'resto'  => $this->get_group_ids('futbolin_group_resto_ids'),
        ];
    }

    private function type_id($r): ?int {
        foreach (['competitionTypeId','tipoCompeticionId','competicionTipoId','tipoId','tipo_id'] as $p) {
            if (isset($r->$p)) return (int)$r->$p;
        }
        return null;
    }

    private function normalize_team($s): array {
        $norm = Futbolin_Normalizer::normalize_players((string)$s); // "A – B"
        $parts = preg_split('/\s+–\s+/u', $norm) ?: [$norm];
        $parts = array_values(array_filter(array_map('trim', $parts)));
        return $parts;
    }
    private function canonical_pair(array $players): string {
        $p = $players; sort($p, SORT_NATURAL | SORT_FLAG_CASE);
        return implode(' – ', $p);
    }
    private function final_key($row, string $pair): string {
        $tid = (string)($row->torneoId ?? '');
        $cid = (string)($row->competicionId ?? '');
        $pos = (string)($row->posicion ?? '');
        if ($cid === '') $cid = 'name:'.mb_strtolower(trim((string)($row->nombreCompeticion ?? '')), 'UTF-8');
        return $tid.'|'.$cid.'|'.$pair.'|'.$pos;
    }

    public function build_all_reports(): array {
        $G = $this->groups();
        $seen = [];
        $R = [
            'open_individual_finals'     => [],
            'open_doubles_player_finals' => [],
            'open_doubles_pair_finals'   => [],
            'championships_open'         => [],
            'championships_rookie'       => [],
            'championships_resto'        => [],
        ];

        $tournaments = $this->api->get_torneos();
        foreach ((array)$tournaments as $t) {
            $tid = is_object($t) ? ($t->torneoId ?? null) : null;
            if (!$tid) continue;

            $rows = $this->api->get_tournament_with_positions($tid);
            if (!is_array($rows)) continue;

            foreach ($rows as $row) {
                if (!is_object($row)) continue;
                $type = $this->type_id($row);
                if ($type === null) continue;

                $players = $this->normalize_team($row->equipoJugadores ?? '');
                if (empty($players)) continue;

                $pair = $this->canonical_pair($players);
                $k = $this->final_key($row, $pair);
                if (isset($seen[$k])) continue; // dedupe
                $seen[$k] = true;

                $is_final = ((int)$row->posicion === 1 || (int)$row->posicion === 2);
                $is_win   = ((int)$row->posicion === 1);

                $in_open   = in_array($type, $G['open'], true);
                $in_rookie = in_array($type, $G['rookie'], true);
                $in_resto  = in_array($type, $G['resto'], true);

                // FINALES OPEN
                if ($in_open && $is_final) {
                    if (count($players) === 1) {
                        $p = $players[0];
                        $R['open_individual_finals'][$p]['wins']   = ($R['open_individual_finals'][$p]['wins']   ?? 0) + ($is_win ? 1 : 0);
                        $R['open_individual_finals'][$p]['losses'] = ($R['open_individual_finals'][$p]['losses'] ?? 0) + ($is_win ? 0 : 1);
                    } else {
                        foreach ($players as $p) {
                            $R['open_doubles_player_finals'][$p]['wins']   = ($R['open_doubles_player_finals'][$p]['wins']   ?? 0) + ($is_win ? 1 : 0);
                            $R['open_doubles_player_finals'][$p]['losses'] = ($R['open_doubles_player_finals'][$p]['losses'] ?? 0) + ($is_win ? 0 : 1);
                        }
                        $R['open_doubles_pair_finals'][$pair]['wins']   = ($R['open_doubles_pair_finals'][$pair]['wins']   ?? 0) + ($is_win ? 1 : 0);
                        $R['open_doubles_pair_finals'][$pair]['losses'] = ($R['open_doubles_pair_finals'][$pair]['losses'] ?? 0) + ($is_win ? 0 : 1);
                    }
                }

                // CAMPEONATOS por jugador
                $buckets = [];
                if ($in_open)   $buckets[] = 'championships_open';
                if ($in_rookie) $buckets[] = 'championships_rookie';
                if ($in_resto)  $buckets[] = 'championships_resto';

                foreach ($buckets as $bk) {
                    foreach ($players as $p) {
                        if (!isset($R[$bk][$p])) {
                            $R[$bk][$p] = [
                                'torneos_jugados' => [], 'campeonatos_jugados' => [],
                                'finales_ganadas' => 0, 'finales_perdidas' => 0, 'resto_posiciones' => 0,
                            ];
                        }
                        $R[$bk][$p]['torneos_jugados'][]     = $row->torneoId ?? null;
                        $R[$bk][$p]['campeonatos_jugados'][] = $row->competicionId ?? null;

                        if ($is_final) {
                            if ($is_win) $R[$bk][$p]['finales_ganadas']++;
                            else          $R[$bk][$p]['finales_perdidas']++;
                        } else {
                            if ((int)$row->posicion > 2) $R[$bk][$p]['resto_posiciones']++;
                        }
                    }
                }
            }
        }

        // Cierre de métricas y orden
        foreach (['open_individual_finals','open_doubles_player_finals','open_doubles_pair_finals'] as $k) {
            $tmp=[];
            foreach ($R[$k] as $name=>$st){
                $w=(int)($st['wins']??0); $l=(int)($st['losses']??0); $t=$w+$l;
                $tmp[$name]=['total'=>$t,'wins'=>$w,'losses'=>$l,'win_rate'=>$t>0?($w/$t)*100:0];
            }
            uasort($tmp, fn($a,$b)=>($b['wins']<=>$a['wins']) ?: ($b['total']<=>$a['total']));
            $R[$k]=$tmp;
        }
        foreach (['championships_open','championships_rookie','championships_resto'] as $k) {
            $tmp=[];
            foreach ($R[$k] as $p=>$st){
                $fg=(int)$st['finales_ganadas']; $fp=(int)$st['finales_perdidas']; $tf=$fg+$fp;
                $pf=$tf>0?($fg/$tf)*100:0;
                $tc=$tf+(int)$st['resto_posiciones']; $cg=$fg; // 1º = campeonato ganado
                $pc=$tc>0?($cg/$tc)*100:0;
                $tmp[$p]=[
                    'torneos_jugados'=>count(array_unique(array_filter($st['torneos_jugados']))),
                    'campeonatos_jugados'=>count(array_unique(array_filter($st['campeonatos_jugados']))),
                    'finales_jugadas'=>$tf,'finales_ganadas'=>$fg,'finales_perdidas'=>$fp,'resto_posiciones'=>(int)$st['resto_posiciones'],
                    'porcentaje_finales_ganadas'=>number_format($pf,2),'porcentaje_campeonatos_ganados'=>number_format($pc,2),
                    'campeonatos_ganados'=>$cg,
                ];
            }
            uasort($tmp, function($a,$b){
                return ($b['campeonatos_ganados']<=>$a['campeonatos_ganados'])
                    ?: ($b['finales_ganadas']<=>$a['finales_ganadas'])
                    ?: ((float)$b['porcentaje_finales_ganadas']<=> (float)$a['porcentaje_finales_ganadas']);
            });
            $R[$k]=$tmp;
        }
        return $R;
    }
}
