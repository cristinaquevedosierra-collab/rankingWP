<?php
if (!isset($player) && isset($jugador)) { $player = $jugador; }
$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
?>
<?php
/**
 * Archivo: includes/template-parts/player-stats.php
 * Restructurado: Estadísticas por **tipoCompeticionId** (sin uniones),
 *                y estadísticas de competición por **tipoCompeticionId**.
 *                Oculta cualquier tipo sin partidos para el jugador.
 *
 * Reglas aplicadas (BUENO_master):
 *  - Preferir endpoint ALL para partidos de jugador.
 *  - Normalización de textos (Amaterâ†’Amateur).
 *  - Incluir Liguilla (alineado con Resultados globales).

 *  - Sin hardcodes de baseUrl/token; uso de Futbolin_API_Client.
 */
$ALLOWED_TIPO_IDS = (isset($ALLOWED_TIPO_IDS) && is_array($ALLOWED_TIPO_IDS) && !empty($ALLOWED_TIPO_IDS))
    ? array_values(array_unique(array_map('intval', $ALLOWED_TIPO_IDS)))
    : []; // Open/Dobles/Individual/Mixto/Pro y Campeonatos España

if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
include_once FUTBOLIN_API_PATH . 'includes/core/class-futbolin-normalizer.php';

$dash = 'â€”';
$hn = function($v) use ($dash) {
    if ($v === null || $v === '') return $dash;
    if (is_numeric($v)) return esc_html((string)$v);
    return esc_html($v);
};
// Asegura que la función esté disponible (por si el plugin se ejecuta fuera del contexto WP)
if (!function_exists('number_format_i18n')) {
    if (
        defined('ABSPATH') && defined('WPINC') &&
        file_exists(constant('ABSPATH') . constant('WPINC') . '/functions.php')
    ) {
        require_once(constant('ABSPATH') . constant('WPINC') . '/functions.php');
    } else {
        // Fallback: define a minimal version if WP is not loaded
        function number_format_i18n($number, $decimals = 0) {
            return number_format((float)$number, $decimals, ',', '.');
        }
    }
}
$hperc = function($v, $dec = 1) use ($dash) {
    if ($v === null || $v === '' || !is_numeric($v)) return $dash;
    if (function_exists('number_format_i18n')) {
        $num = number_format_i18n((float)$v, $dec);
    } else {
        $num = number_format((float)$v, $dec, ',', '.');
    }
    return esc_html($num) . '%';
};

// Carga enums.tipoCompeticion desde BUENO_master.json
$plugin_root = dirname(dirname(__FILE__)); // .../includes/template-parts -> .../includes
$plugin_root = dirname($plugin_root);      // -> raíz del plugin
$master_path = $plugin_root . '/BUENO_master.json';
$tipo_map_by_id = [];
$tipo_map_by_name = [];
if (file_exists($master_path)) {
    $master = json_decode(file_get_contents($master_path), true);
    if (is_array($master) && isset($master['enums']['tipoCompeticion']) && is_array($master['enums']['tipoCompeticion'])) {
        foreach ($master['enums']['tipoCompeticion'] as $tc) {
            $id = isset($tc['id']) ? (int)$tc['id'] : null;
            $name = isset($tc['name']) ? (string)$tc['name'] : '';
            if ($id !== null && $name !== '') {
                // Normaliza Amaterâ†’Amateur
                $name_norm = str_ireplace('Amater', 'Amateur', $name);
                $tipo_map_by_id[$id] = $name_norm;
                $tipo_map_by_name[Futbolin_Normalizer::mb_lower($name_norm)] = $id;
                if (!empty($tc['aliases']) && is_array($tc['aliases'])) {
                    foreach ($tc['aliases'] as $al) {
                        $tipo_map_by_name[Futbolin_Normalizer::mb_lower(str_ireplace('Amater', 'Amateur', (string)$al))] = $id;
                    }
                }
            }
        }
    }
}

// Derivar tipos permitidos desde enums del master si no se definieron externamente
if (empty($ALLOWED_TIPO_IDS) && !empty($tipo_map_by_id)) {
    $ALLOWED_TIPO_IDS = [];
    foreach ($tipo_map_by_id as $id => $name) {
        $name_l = mb_strtolower((string)$name);
        // Excluir solo penalizaciones por no jugar del conjunto de tipos
        if (preg_match('/penalizacion\s+por\s+no\s+jugar/i', $name_l)) { continue; }
        $ALLOWED_TIPO_IDS[] = (int)$id;
    }
    $ALLOWED_TIPO_IDS = array_values(array_unique(array_map('intval', $ALLOWED_TIPO_IDS)));
}
// Cliente API
$api_client = isset($api_client) && is_object($api_client) ? $api_client : (class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null);

// === 1) Estadísticas de PARTIDOS por tipoCompeticionId ===
$matches = [];
if ($api_client && $player_id) {
    $matches = $api_client->get_partidos_jugador($player_id); // ALL
}
if (!is_array($matches)) $matches = [];

// Helpers para identificar tipoCompeticionId de un registro
$get_tipo_id = function($m) use ($tipo_map_by_id, $tipo_map_by_name) {
    $id_fields = ['tipoCompeticionId','idTipoCompeticion','tipo_competicion_id'];
    foreach ($id_fields as $f) {
        if (is_object($m) && isset($m->$f) && is_numeric($m->$f)) return (int)$m->$f;
        if (is_array($m)  && isset($m[$f]) && is_numeric($m[$f])) return (int)$m[$f];
    }
    $name_fields = ['tipoCompeticion','competicion','competicionNombre','tipo_competicion','nombreCompeticion'];
    foreach ($name_fields as $f) {
        $val = is_object($m) ? ($m->$f ?? null) : (is_array($m) ? ($m[$f] ?? null) : null);
        if (is_string($val) && $val !== '') {
            $norm = Futbolin_Normalizer::mb_lower(str_ireplace('Amater','Amateur',$val));
            if (isset($tipo_map_by_name[$norm])) return (int)$tipo_map_by_name[$norm];
        }
    }
    return null;
};

// Determina si es Liguilla para excluir
$is_liguilla = function($m) {
    $fs = [];
    foreach (['fase','faseNombre','ronda','etapa'] as $f) {
        $fs[] = is_object($m) ? ($m->$f ?? '') : (is_array($m) ? ($m[$f] ?? '') : '');
    }
    $txt = implode(' ', array_map('strval', array_filter($fs)));
    return (bool)preg_match('/\bliguilla\b/i', $txt);
};

// Determina si ganó el partido

// Partes del nombre del jugador (para heurísticas de lado local/visitante)
$name_parts = [];
if (isset($player) && is_object($player) && isset($player->nombre)) {
    $name_parts = array_filter(explode(' ', (string)$player->nombre), function($p){ return strlen(trim($p))>1; });
}

 $won_match = function($m, $player_id) use ($name_parts) {
     // Determinar lado del jugador usando DTO y fallback por nombre (igual que en processor)
     $in_team = function($equipoDTO, $equipoStr) use ($player_id, $name_parts) {
         // 1) DTO con jugadores
         if (is_object($equipoDTO) && isset($equipoDTO->jugadores) && is_array($equipoDTO->jugadores)) {
             foreach ($equipoDTO->jugadores as $j) {
                 $jid = isset($j->jugadorId) ? intval($j->jugadorId) : null;
                 if ($jid !== null && (int)$jid === (int)$player_id) return true;
             }
         }
         // 2) Fallback por nombre en string
         $team_string = is_string($equipoStr) ? $equipoStr : '';
         if (!empty($name_parts)) {
             $guess = true;
             foreach ($name_parts as $part) {
                 $part = trim($part);
                 if ($part === '' || strlen($part) <= 1) continue;
                 if (stripos($team_string, $part) === false) { $guess = false; break; }
             }
             if ($guess) return true;
         }
         return false;
     };
     $is_local = $in_team($m->equipoLocalDTO ?? null, $m->equipoLocal ?? '');
     $is_visit = $in_team($m->equipoVisitanteDTO ?? null, $m->equipoVisitante ?? '');
     // Ganador local booleano
     $gl = null;
     foreach (['ganadorLocal','ganoLocal'] as $f) {
         if (is_object($m) && isset($m->$f)) { $gl = (bool)$m->$f; break; }
         if (is_array($m) && array_key_exists($f, $m)) { $gl = (bool)$m[$f]; break; }
     }
     if ($gl === null) return null; // mismo comportamiento que processor
     if (($is_local && $gl === true) || ($is_visit && $gl === false)) return true;
     return false;
 };


// Acumular
$stats_partidos = []; // [tipoId] => ['name'=>..., 'jugados'=>N, 'ganados'=>M]
foreach ($matches as $m) {
    $tid = $get_tipo_id($m);
    if ($tid === null || !in_array((int)$tid, (array)$ALLOWED_TIPO_IDS, true)) continue;
    /* incluir liguilla */ /* if ($is_liguilla($m)) continue; */
    if (_futb_is_penalty_row($m)) continue; // excluir penalizaciones/no actividad
    // (PATCH6) Ya no se excluye DYP/ProAm
    if (!_futb_has_real_result($m)) continue; // excluir sin resultado real
    
    if (!isset($stats_partidos[$tid])) {
        $stats_partidos[$tid] = ['name' => ($tipo_map_by_id[$tid] ?? ('Tipo #' . $tid)), 'jugados'=>0, 'ganados'=>0];
    }
    $stats_partidos[$tid]['jugados']++;
    $won = $won_match($m, $player_id);
    if ($won === true) $stats_partidos[$tid]['ganados']++;
}
// Calcular porcentaje y limpiar vacíos
foreach ($stats_partidos as $tid => &$data) {
    $j = max(0, (int)$data['jugados']);
    $g = max(0, (int)$data['ganados']);
    $data['rate'] = $j > 0 ? ($g * 100.0 / $j) : null;
}

// Totales agregados (excluyendo penalizaciones y sin resultado)
$tot_jug = 0; $tot_gan = 0;
foreach ($stats_partidos as $row) { $tot_jug += (int)$row['jugados']; $tot_gan += (int)$row['ganados']; }
$tot_rate = $tot_jug > 0 ? ($tot_gan * 100.0 / $tot_jug) : null;
// Inserta pseudo-fila 'Totales' al inicio manteniendo orden estable


// Ordenar por nombre
uasort($stats_partidos, function($a,$b){ return strcasecmp($a['name'],$b['name']); });

// === 2) Estadísticas de COMPETICI“N (títulos) por tipoCompeticionId ===
$posiciones = [];
if ($api_client && $player_id) {
    $posiciones_raw = $api_client->get_posiciones_jugador($player_id);
    if (is_array($posiciones_raw)) $posiciones = $posiciones_raw;
}
$stats_comp = []; // [tipoId] => ['name'=>..., 'jugadas'=>N, 'titulos'=>M]
$torneos_vistos = []; // [tipoId] => set de torneoId
foreach ($posiciones as $p) {
    $tid = $get_tipo_id($p);
    if ($tid === null || !in_array((int)$tid, (array)$ALLOWED_TIPO_IDS, true)) continue;
    
    /* No filtrar por tipos con partidos: debe cuadrar con global */
    $torneoId = null;
    foreach (['torneoId','idTorneo'] as $f) { if (isset($p->$f) && is_numeric($p->$f)) { $torneoId = (int)$p->$f; break; } }

    if (!isset($stats_comp[$tid])) {
        $stats_comp[$tid] = ['name'=> ($tipo_map_by_id[$tid] ?? ('Tipo #' . $tid)), 'jugadas'=>0, 'titulos'=>0];
        $torneos_vistos[$tid] = [];
    }
    if ($torneoId !== null && !in_array($torneoId, $torneos_vistos[$tid], true)) {
        $stats_comp[$tid]['jugadas']++;
        $torneos_vistos[$tid][] = $torneoId;
    }
    $posicion = null;
    foreach (['posicion','puesto','ranking'] as $f) { if (isset($p->$f) && is_numeric($p->$f)) { $posicion = (int)$p->$f; break; } }
    $es_campeon = ($posicion === 1) || (!empty($p->campeon));
    if ($es_campeon) { $stats_comp[$tid]['titulos']++; }
}
foreach ($stats_comp as $tid => &$data) {
    $j = max(0, (int)$data['jugadas']);
    $t = max(0, (int)$data['titulos']);
    $data['rate'] = $j > 0 ? ($t * 100.0 / $j) : null;
}
unset($data);
uasort($stats_comp, function($a,$b){ return strcasecmp($a['name'],$b['name']); });
// Totales de competiciones
$tot_comp_jug = 0; $tot_comp_tit = 0;
foreach ($stats_comp as $row) { $tot_comp_jug += (int)$row['jugadas']; $tot_comp_tit += (int)$row['titulos']; }
$tot_comp_rate = $tot_comp_jug > 0 ? ($tot_comp_tit * 100.0 / $tot_comp_jug) : null;



// === Render ===
?>

<!-- BLOQUE 1: PARTIDOS TOTALES -->
<div class="futbolin-card">
    <h3 class="history-main-title">Estadísticas</h3>
    <h3>Estadísticas de Partidos totales</h3>
    <div class="player-career-stats-grid global-muted" style="margin:10px 0 18px 0;">
        <div class="stat-box">
            <div class="stat-value"><?php echo $hn($tot_jug); ?></div>
            <div class="stat-label">Jugadas</div>
        </div>
        <div class="stat-box stat-box-won">
            <div class="stat-value"><?php echo $hn($tot_gan); ?></div>
            <div class="stat-label">Ganadas</div>
        </div>
        <div class="stat-box stat-box-lost">
            <div class="stat-value"><?php echo $hn($tot_jug - $tot_gan); ?></div>
            <div class="stat-label">Perdidas</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $hperc($tot_rate); ?></div>
            <div class="stat-label">% Victorias</div>
        </div>
    </div>
</div>

<!-- BLOQUE 2: PARTIDOS POR TIPO -->
<div class="futbolin-card">
    <h3 style="margin-top: 0; margin-bottom: 12px;">Estadísticas de Partidos por Tipo de Competición</h3>
    <?php if (empty($stats_partidos)) : ?>
        <p>No hay partidos para las competiciones definidas.</p>
    <?php else : ?>
    <ul class="stats-list">
        <?php foreach ($stats_partidos as $tid => $row): ?>
            <?php 
                $name = isset($row['name']) ? (string)$row['name'] : ''; 
                if ($name === 'Totales' || $name === 'Totales (Partidos)' || $tid === '__TOTALS__') { continue; }
                $name = trim(str_replace('â€”', '', $name));
                $victorias = (int)$row['ganados'];
                $jugados = (int)$row['jugados'];
                $clase = '';
                if ($jugados > 0) {
                    if ($victorias === $jugados) {
                        $clase = 'partido-victoria';
                    } elseif ($victorias === 0) {
                        $clase = 'partido-derrota';
                    }
                }
            ?>
            <li class="<?php echo esc_attr($clase); ?>">
                <strong><?php echo esc_html($name); ?> (Partidos):</strong>
                <span>
                    <?php echo $hn($row['ganados']); ?> victorias de <?php echo $hn($row['jugados']); ?> partidos
                    <span class="porcentaje-victorias">(<?php echo $hperc($row['rate'], 1); ?>)</span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<!-- BLOQUE 3: COMPETICIONES TOTALES -->
<div class="futbolin-card">
    <h3>Estadísticas de Competiciones TOTALES</h3>
    <div class="player-career-stats-grid global-muted" style="margin:10px 0 18px 0;">
        <div class="stat-box">
            <div class="stat-value"><?php echo $hn($tot_comp_jug); ?></div>
            <div class="stat-label">Jugadas</div>
        </div>
        <div class="stat-box stat-box-won">
            <div class="stat-value"><?php echo $hn($tot_comp_tit); ?></div>
            <div class="stat-label">Ganadas</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $hperc($tot_comp_rate); ?></div>
            <div class="stat-label">% Victorias</div>
        </div>
    </div>
</div>

<!-- BLOQUE 4: COMPETICIONES POR TIPO -->
<div class="futbolin-card">
    <h3 style="margin-top: 0; margin-bottom: 12px;">Estadísticas de Competición por Tipo</h3>
    <?php if (empty($stats_comp)) : ?>
        <p>No hay competiciones jugadas en los tipos detectados.</p>
    <?php else : ?>
    <ul class="stats-list">
        <?php foreach ($stats_comp as $tid => $row): ?>
            <?php $name = isset($row['name']) ? (string)$row['name'] : ''; if ($name === 'Totales (Competiciones)' || $tid === '__TOTALS__') { continue; } ?>
            <li>
                <strong><?php echo esc_html($row['name']); ?> (Competiciones):</strong>
                <span>
                    <?php echo $hn($row['titulos']); ?> títulos de <?php echo $hn($row['jugadas']); ?> jugadas
                    <span class="porcentaje-victorias">(<?php echo $hperc($row['rate'], 1); ?>)</span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

