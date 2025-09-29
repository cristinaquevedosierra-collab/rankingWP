<?php

if (!isset($player) && isset($jugador)) { $player = $jugador; }

$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);

$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');

?>
<?php
/**
 * Archivo: includes/template-parts/player-glicko-rankings-tab.php
 * Descripción: Ranking del jugador por modalidad (Glicko), con enlaces al ranking completo.
 */
if (!defined('ABSPATH')) exit;

include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Comprobaciones y defaults seguros
$api_client_ok = (isset($api_client) && is_object($api_client)
    && method_exists($api_client, 'get_modalidades')
    && method_exists($api_client, 'get_ranking'));

// Determinar player_id de forma tolerante (sin abortar si falta basic_data)
if (!function_exists('rf_extract_basic_player_id')) {
    function rf_extract_basic_player_id($src) {
        if (!$src) return 0;
        $o = is_object($src) ? $src : (object)$src;
        foreach (['jugadorId','JugadorId','playerId','PlayerId','id','Id'] as $k) {
            if (isset($o->$k) && is_numeric($o->$k)) return (int)$o->$k;
        }
        foreach (['jugador','Jugador','player','Player'] as $jk) {
            if (isset($o->$jk)) {
                $j = $o->$jk;
                if (is_object($j)) { foreach (['jugadorId','JugadorId','id','Id'] as $ik) { if (isset($j->$ik) && is_numeric($j->$ik)) return (int)$j->$ik; } }
                if (is_array($j))   { foreach (['jugadorId','JugadorId','id','Id'] as $ik) { if (isset($j[$ik]) && is_numeric($j[$ik])) return (int)$j[$ik]; } }
            }
        }
        return 0;
    }
}

// No sobreescribir si ya viene de contexto; completar con fallbacks robustos
if (!isset($player_id) || (int)$player_id <= 0) {
    $player_id = isset($player_id) ? (int)$player_id : 0;
    if ($player_id <= 0 && isset($processor) && is_object($processor) && isset($processor->basic_data) && is_object($processor->basic_data)) {
        $player_id = rf_extract_basic_player_id($processor->basic_data);
    }
    // Fallback de variable de plantilla común
    if ($player_id <= 0 && isset($jugador_id) && is_numeric($jugador_id)) { $player_id = (int)$jugador_id; }
    // Fallback desde GET, tolerando valores mal formados (p.ej., "6?rf_tab_cache_bypass=1")
    if ($player_id <= 0 && isset($_GET['jugador_id'])) {
        $raw = (string) $_GET['jugador_id'];
        if (is_numeric($raw)) { $player_id = (int) $raw; }
        elseif (preg_match('/^(\d{1,10})/', $raw, $m)) { $player_id = (int) $m[1]; }
    }
    if ($player_id <= 0 && isset($_GET['player_id'])) {
        $raw2 = (string) $_GET['player_id'];
        if (is_numeric($raw2)) { $player_id = (int) $raw2; }
        elseif (preg_match('/^(\d{1,10})/', $raw2, $m2)) { $player_id = (int) $m2[1]; }
    }
    if ($player_id <= 0 && isset($player)) { $player_id = rf_extract_basic_player_id($player); }
}
if ($player_id <= 0) {
    echo '<div class="futbolin-card"><p>No se pudo determinar el ID del jugador.</p></div>';
    return;
}

// URL a la página de ranking para enlazar a la modalidad
$ranking_page_url = isset($ranking_page_url) ? $ranking_page_url : home_url('/');

// Orden preferente
$display_order = [
    'Dobles', 'Individual', 'Mixto',
    'Senior Dobles', 'Senior Individual',
    'Junior Dobles', 'Junior Individual',
    'Mujeres Dobles', 'Mujeres Individual'
];

$modalidades_list = [];
if ($api_client_ok) {
    $modalidades_list = $api_client->get_modalidades();
}
// Definir helper temprano si aún no existe (se usa justo debajo)
if (!function_exists('rf_unwrap_items_generic')) {
    function rf_unwrap_items_generic($data) {
        if (!$data) return [];
        if (is_array($data)) {
            $is_assoc = array_keys($data) !== range(0, count($data) - 1);
            if (!$is_assoc) return $data;
            if (isset($data['ranking'])) {
                $r = $data['ranking'];
                if (is_array($r) && isset($r['items']) && is_array($r['items'])) return $r['items'];
                if (is_array($r) && array_keys($r) === range(0, count($r) - 1)) return $r;
            }
            if (isset($data['items']) && is_array($data['items'])) return $data['items'];
            if (isset($data['data']) && is_array($data['data']) && isset($data['data']['items']) && is_array($data['data']['items'])) return $data['data']['items'];
            if (isset($data['result']) && is_array($data['result']) && isset($data['result']['items']) && is_array($data['result']['items'])) return $data['result']['items'];
            foreach (['rows','list','lista','datos'] as $k) {
                if (isset($data[$k]) && is_array($data[$k]) && array_keys($data[$k]) === range(0, count($data[$k]) - 1)) return $data[$k];
            }
            foreach ($data as $v) { if (is_array($v) && array_keys($v) === range(0, count($v) - 1)) return $v; }
            return [];
        }
        if (is_object($data)) {
            if (isset($data->ranking)) {
                $r = $data->ranking;
                if (is_object($r) && isset($r->items) && is_array($r->items)) return $r->items;
                if (is_array($r) && isset($r['items']) && is_array($r['items'])) return $r['items'];
                if (is_array($r) && array_keys($r) === range(0, count($r) - 1)) return $r;
            }
            if (isset($data->items) && is_array($data->items)) return $data->items;
            if (isset($data->data) && is_object($data->data) && isset($data->data->items) && is_array($data->data->items)) return $data->data->items;
            if (isset($data->result) && is_object($data->result) && isset($data->result->items) && is_array($data->result->items)) return $data->result->items;
        }
        return [];
    }
}
// Desempaquetar si la API devuelve un objeto envuelto
if (empty($modalidades_list) || !is_array($modalidades_list)) {
    $modalidades_list = rf_unwrap_items_generic($modalidades_list);
}

// Reordenamos según preferencia y aÃ±adimos las que falten al final
$ordered_modalidades = [];
if (!empty($modalidades_list) && is_array($modalidades_list)) {
    // Ãndice por nombre
    $by_name = [];
    foreach ($modalidades_list as $m) {
        $m_obj = is_object($m) ? $m : (object)$m;
        if (!isset($m_obj->descripcion)) continue;
        $by_name[$m_obj->descripcion] = $m_obj;
    }
    // Primero las del orden preferido
    foreach ($display_order as $name) {
        if (isset($by_name[$name])) {
            $ordered_modalidades[] = $by_name[$name];
            unset($by_name[$name]);
        }
    }
    // Luego el resto (por si la API trae nuevas)
    foreach ($by_name as $leftover) {
        $ordered_modalidades[] = $leftover;
    }
}

$ranking_mostrado = false;
// Detectar temporada en vigor (preferir constante global; fallback a 14)
$rf_temporada_actual = defined('FUTBOLIN_MAX_SEASON_ORDINAL') ? (int)constant('FUTBOLIN_MAX_SEASON_ORDINAL') : 14;
// Temporada fija solicitada para ELO (endpoint exacto /.../GetRankingPorModalidadPorTemporadaESPGlicko2/{ModalidadId}/{temporada})
$rf_temporada_id_elo = $rf_temporada_actual;

// FAST PATH: usar índice cacheado si existe para Individual/Dobles
$players_index_path = RF_Hitos_Cache_Manager::cache_path('players_index');
$players_index_data = null; $fast_index_players = [];
if (file_exists($players_index_path)) {
    $raw = @file_get_contents($players_index_path);
    $json = json_decode($raw, true);
    if (is_array($json) && isset($json['players'])) { $players_index_data = $json; $fast_index_players = $json['players']; }
}
// Helper: leer cache ranking_<modalidad>.json para un jugador y devolver puntos/posicion si existe
if (!function_exists('rf_lookup_player_in_ranking_cache')) {
    function rf_lookup_player_in_ranking_cache($player_id, $modalidad_id) {
        if (!class_exists('RF_Hitos_Cache_Manager')) return null;
        $dir = method_exists('RF_Hitos_Cache_Manager','get_cache_dir') ? RF_Hitos_Cache_Manager::get_cache_dir() : '';
        if (!$dir) return null;
        $file = $dir . 'ranking_' . (int)$modalidad_id . '.json';
        if (!file_exists($file) || filesize($file) < 10) return null;
        try {
            $raw = file_get_contents($file);
            if (!$raw) return null;
            $json = json_decode($raw);
            $items = [];
            if (is_array($json)) { $items = $json; }
            elseif (is_object($json)) {
                foreach (['items','data','result','ranking'] as $k) {
                    if (isset($json->$k)) {
                        $candidate = $json->$k;
                        if (is_object($candidate) && isset($candidate->items) && is_array($candidate->items)) { $items = $candidate->items; break; }
                        if (is_array($candidate)) { $items = $candidate; break; }
                    }
                }
            }
            if (empty($items) || !is_array($items)) return null;
            foreach ($items as $idx=>$row) {
                if (!is_object($row)) continue;
                $jid = 0;
                foreach (['jugadorId','JugadorId','id','Id'] as $rk){ if (isset($row->$rk) && is_numeric($row->$rk)) { $jid = (int)$row->$rk; break; } }
                if ($jid === (int)$player_id) {
                    $pos = isset($row->posicion) ? (int)$row->posicion : ($idx+1);
                    $pts = 0; foreach(['puntos','Puntos','elo','Elo','rating','Rating','glicko','Glicko'] as $pk){ if(isset($row->$pk)){ $pts = (float)$row->$pk; break; } }
                    // Extraer categoria si viene
                    $categoria = null;
                    if (isset($row->categoria)) {
                        if (is_object($row->categoria) && isset($row->categoria->descripcion)) {
                            $categoria = (string)$row->categoria->descripcion;
                        } elseif (is_string($row->categoria)) {
                            $categoria = (string)$row->categoria;
                        }
                    } elseif (isset($row->Categoria) && is_string($row->Categoria)) {
                        $categoria = (string)$row->Categoria;
                    }
                    return (object)['posicion'=>$pos,'puntos'=>$pts,'categoria'=>$categoria,'__source'=>'cache'];
                }
            }
        } catch(\Throwable $e) { return null; }
        return null;
    }
}
// Helper para recuperar rápidamente un jugador del índice (solo posiciones globales/points/cat)
if (!function_exists('rf_fast_index_lookup')) {
    function rf_fast_index_lookup($pid, $idxPlayers){ return isset($idxPlayers[$pid]) ? $idxPlayers[$pid] : null; }
}

// Helpers para desempaquetar items y extraer jugadorId con tolerancia a distintas formas de JSON
if (!function_exists('rf_extract_jid_generic')) {
    function rf_extract_jid_generic($row) {
        if (!$row) return 0;
        $o = is_object($row) ? $row : (object)$row;
        foreach (['jugadorId','JugadorId','idJugador','IdJugador','playerId','PlayerId','id','Id'] as $k) {
            if (isset($o->$k) && is_numeric($o->$k)) return (int)$o->$k;
        }
        foreach (['jugador','Jugador','player','Player'] as $jk) {
            if (isset($o->$jk)) {
                $j = $o->$jk;
                if (is_object($j)) { foreach (['jugadorId','JugadorId','id','Id'] as $ik) { if (isset($j->$ik) && is_numeric($j->$ik)) return (int)$j->$ik; } }
                if (is_array($j))   { foreach (['jugadorId','JugadorId','id','Id'] as $ik) { if (isset($j[$ik]) && is_numeric($j[$ik])) return (int)$j[$ik]; } }
            }
        }
        return 0;
    }
}

?>

<div class="futbolin-card">

    <h3 class="history-main-title" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <span>Clasificación actual</span>
        <span class="season-badge" style="background: var(--futbolin-chip, #eceff4); color: var(--futbolin-text, #222); padding:2px 8px; border-radius:8px; font-size: .9em;">Temporada <?php echo (int)$rf_temporada_actual; ?></span>
    </h3>

    <h4 class="section-subtitle">Ranking ELO</h4>
    <div class="player-ranking-block elo-block">



    <?php if (!$api_client_ok): ?>

        <p>No se pudo inicializar el cliente de la API o faltan métodos requeridos.</p>

    <?php elseif (empty($ordered_modalidades)): ?>

        <p>No se pudieron obtener las modalidades desde la API.</p>

    <?php else: ?>

        <ul class="stats-list">

            <?php foreach ($ordered_modalidades as $modalidad) :

                $mod      = is_object($modalidad) ? $modalidad : (object)$modalidad;

                $mod_id   = isset($mod->modalidadId) ? (int)$mod->modalidadId : (isset($mod->id) ? (int)$mod->id : 0);

                $mod_name = isset($mod->descripcion) ? (string)$mod->descripcion : (isset($mod->Descripcion) ? (string)$mod->Descripcion : 'Modalidad');



                if ($mod_id <= 0) continue;



                $player_ranking = null;
                $use_fast = ($players_index_data && in_array($mod_name, ['Individual','Dobles'], true));
                if ($use_fast) {
                    // Intentar cache específica de la modalidad primero (garantiza puntos correctos por modalidad)
                    $cached_mod = rf_lookup_player_in_ranking_cache($player_id, $mod_id);
                    if ($cached_mod) {
                        $player_ranking = $cached_mod;
                    } else {
                        // Fallback al índice global (puede contener solo una referencia genérica)
                        $fast = rf_fast_index_lookup($player_id, $fast_index_players);
                        if ($fast) {
                            $player_ranking = (object) [
                                'posicion' => $fast['pos'],
                                'puntos'   => $fast['points'], // Puede ser de otra modalidad si el índice no discrimina
                                'categoria'=> $fast['cat'],
                                '__source' => 'fast_index'
                            ];
                        }
                    }
                }
                if (!$player_ranking) {
                    // Usamos el endpoint estricto de temporada (ESP Glicko2) o fallback paginado sólo si no conseguimos fast path
                    if (method_exists($api_client, 'get_ranking_por_modalidad_esp_g2_all')) {
                        $ranking_data = $api_client->get_ranking_por_modalidad_esp_g2_all($mod_id);
                    } else {
                        $ranking_data = $api_client->get_ranking($mod_id, 1, 9999);
                    }
                    $items = rf_unwrap_items_generic($ranking_data);
                    if (!empty($items) && is_array($items)) {
                        foreach ($items as $idx => $jug) {
                            $jid = rf_extract_jid_generic($jug);
                            if ($jid === $player_id) {
                                $jug_obj = is_object($jug) ? $jug : (object)$jug;
                                if (!isset($jug_obj->posicion)) { $jug_obj->posicion = $idx + 1; }
                                $player_ranking = $jug_obj;
                                break;
                            }
                        }
                    }
                }



                if ($player_ranking && !empty($player_ranking->posicion)) :

                    $ranking_mostrado = true;

                    $posicion = (int)$player_ranking->posicion;



                    // Normalizamos puntos consultando varias claves habituales

                    $puntos = 0;

                    foreach (['puntos','Puntos','elo','Elo','rating','Rating','glicko','Glicko'] as $pk) {

                        if (isset($player_ranking->$pk)) { $puntos = (float)$player_ranking->$pk; break; }

                    }

                    $puntos = round($puntos);



                    // Categoría puede venir como string o como objeto con descripcion

                    $categoria = null;

                    if (isset($player_ranking->categoria)) {

                        if (is_object($player_ranking->categoria) && isset($player_ranking->categoria->descripcion)) {

                            $categoria = (string)$player_ranking->categoria->descripcion;

                        } elseif (is_string($player_ranking->categoria)) {

                            $categoria = (string)$player_ranking->categoria;

                        }

                    } elseif (isset($player_ranking->Categoria) && is_string($player_ranking->Categoria)) {

                        $categoria = (string)$player_ranking->Categoria;

                    }



                    // Link a la clasificación completa de esa modalidad

                    $mod_url = add_query_arg(['view' => 'ranking', 'modalidad' => $mod_id], $ranking_page_url);

            ?>

                <li class="ranking-row <?php echo ($posicion>=1 && $posicion<=3)?'has-pos-'.(int)$posicion:''; ?>">

                    <div class="ranking-position pos-<?php echo esc_attr($posicion); ?>">

                        <?php echo esc_html($posicion); ?>

                    </div>

                    <div class="ranking-player-details">

                        <h4 style="margin:0; font-size: 1.2em; color: var(--futbolin-text-headings);">

                            <a href="<?php echo esc_url($mod_url); ?>" title="Ver clasificación completa de <?php echo esc_attr($mod_name); ?>">

                                <?php echo esc_html($mod_name); ?>

                            </a>

                        </h4>

                        <?php if (in_array($mod_name, ['Dobles','Individual'], true) && $categoria): ?>

                            <span style="font-size: 0.9em; color: var(--futbolin-text-muted);">

                                (Categoría: <?php echo esc_html($categoria); ?>)

                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="ranking-points">

                        <div class="points-pill">

                            <span class="points-value"><?php echo esc_html($puntos); ?></span>

                            <span class="points-label">puntos</span>

                        </div>

                    </div>

                </li>

            <?php endif; endforeach; ?>

        </ul>



        <?php if (!$ranking_mostrado): ?>
            <div class="ranking-empty" role="note" style="background: var(--futbolin-chip, #eceff4); color: var(--futbolin-text, #222); padding:12px 16px; border-radius:10px; margin-top:8px; display:flex; align-items:center; gap:10px;">
                <span aria-hidden="true" style="width:8px; height:8px; background:#94a3b8; border-radius:50%; display:inline-block;"></span>
                <span>El jugador no se encuentra actualmente rankeado por inactividad.</span>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    </div><!-- /.player-ranking-block.elo-block -->

    <?php /* El bloque anual se presenta con el mismo estilo que "Ranking ELO" y badges informativos */ ?>

        <?php
            // Bloque: Ranking anual del jugador (solo Dobles e Individual)
            // Carga de dependencias mínimas
            if (!class_exists('Futbolin_Annual_Service')) {
                    $svc_path = FUTBOLIN_API_PATH . 'includes/services/class-futbolin-annual-service.php';
                    if (file_exists($svc_path)) { require_once $svc_path; }
            }
            if (!class_exists('Futbolin_API_Client')) {
                    $api_path = FUTBOLIN_API_PATH . 'includes/core/class-futbolin-api-client.php';
                    if (file_exists($api_path)) { require_once $api_path; }
            }

        $annual_rows = [];
            $season_id = null;
            if (class_exists('Futbolin_Annual_Service') && class_exists('Futbolin_API_Client')) {
                    $annual_client = (isset($api_client) && is_object($api_client)) ? $api_client : new Futbolin_API_Client();
                    try {
                            $annualSvc = new Futbolin_Annual_Service($annual_client);
                // Respetar toggles de admin para anual
                $opts_prof = get_option('mi_plugin_futbolin_options', []);
                $annual_doubles_on    = (!isset($opts_prof['enable_annual_doubles']) || $opts_prof['enable_annual_doubles'] === 'on');
                $annual_individual_on = (!isset($opts_prof['enable_annual_individual']) || $opts_prof['enable_annual_individual'] === 'on');
                // Modalidades objetivo para anual según toggles
                $annual_modalidades = [];
                if ($annual_doubles_on)    { $annual_modalidades[2] = 'Dobles'; }
                if ($annual_individual_on) { $annual_modalidades[1] = 'Individual'; }
                if (empty($annual_modalidades)) { throw new Exception('Annual ranking disabled by admin'); }
                            $season_id = $annualSvc->detect_last_season_with_data(array_keys($annual_modalidades), 14);

                            foreach ($annual_modalidades as $mid => $mname) {
                                    $resp = $annualSvc->get_annual_ranking_for((int)$mid, $season_id);
                                    $items = rf_unwrap_items_generic($resp);
                                    if (empty($items) || !is_array($items)) continue;
                                    foreach ($items as $idx => $jug) {
                                            $jid = rf_extract_jid_generic($jug);
                                            if ($jid === $player_id) {
                                                    $jug_obj = is_object($jug) ? $jug : (object)$jug;
                                                    if (!isset($jug_obj->posicion) || !is_numeric($jug_obj->posicion)) { $jug_obj->posicion = $idx + 1; }
                                                    $p_raw = 0;
                                                    foreach (['puntos','Puntos','puntuacion','Puntuacion'] as $pk) { if (isset($jug_obj->$pk)) { $p_raw = $jug_obj->$pk; break; } }
                                                    $annual_rows[] = [
                                                            'mod_id'   => (int)$mid,
                                                            'mod_name' => (string)$mname,
                                                            'pos'      => (int)$jug_obj->posicion,
                                                            'puntos'   => (int)round(is_numeric($p_raw) ? (float)$p_raw : 0),
                                                    ];
                                                    break;
                                            }
                                    }
                            }
                    } catch (Exception $e) {
                            // silencio: si falla el anual, no rompemos la pestaña
                    }
            }

            // Título anual con el mismo estilo que "Ranking ELO"
        ?>
            <div class="player-ranking-block annual-block" style="margin-top: 56px;">
                <h4 class="section-subtitle" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span>Ranking anual</span>
                </h4>
                <?php if (!empty($annual_rows)) : ?>
                    <ul class="stats-list">
                        <?php foreach ($annual_rows as $row):
                            $mod_id = (int)$row['mod_id'];
                            $mod_name = (string)$row['mod_name'];
                            $posicion = (int)$row['pos'];
                            $puntos = (int)$row['puntos'];
                            $annual_url = add_query_arg(['view' => 'annual', 'modalidad' => $mod_id], $ranking_page_url);
                        ?>
                            <li class="ranking-row <?php echo ($posicion>=1 && $posicion<=3)?'has-pos-'.(int)$posicion:''; ?>">
                                <div class="ranking-position pos-<?php echo esc_attr($posicion); ?>"><?php echo esc_html($posicion); ?></div>
                                <div class="ranking-player-details">
                                    <h4 style="margin:0; font-size: 1.2em; color: var(--futbolin-text-headings);">
                                        <a href="<?php echo esc_url($annual_url); ?>" title="Ver clasificación anual de <?php echo esc_attr($mod_name); ?>">
                                            <?php echo esc_html($mod_name); ?>
                                        </a>
                                    </h4>
                                    <span style="font-size: 0.9em; color: var(--futbolin-text-muted);">(Anual)</span>
                                </div>
                                <div class="ranking-points">
                                    <div class="points-pill">
                                        <span class="points-value"><?php echo esc_html(number_format_i18n($puntos)); ?></span>
                                        <span class="points-label">puntos</span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="ranking-empty" role="note" style="background: var(--futbolin-chip, #eceff4); color: var(--futbolin-text, #222); padding:12px 16px; border-radius:10px; margin-top:8px; display:flex; align-items:center; gap:10px;">
                        <span aria-hidden="true" style="width:8px; height:8px; background:#94a3b8; border-radius:50%; display:inline-block;"></span>
                        <span>El jugador no se encuentra actualmente rankeado por inactividad.</span>
                    </div>
                <?php endif; ?>
            </div><!-- /.player-ranking-block.annual-block -->

        <?php if ($ranking_mostrado || !empty($annual_rows)): ?>
            <h5 style="margin-top: 28px;">Pincha en una modalidad si quieres ver su clasificación completa</h5>
        <?php endif; ?>

    <?php
    /**
     * Bloque: Mejor clasificación histórica a final de temporada
     * - Para cada modalidad, calcula la mejor posición histórica en ELO y en ANUAL (si anual está habilitado)
     * - Muestra posición, temporada y puntos de ese momento
     */
    if (!class_exists('Futbolin_Best_Service')) {
        $best_path = FUTBOLIN_API_PATH . 'includes/services/class-futbolin-best-service.php';
        if (file_exists($best_path)) { require_once $best_path; }
    }
    $best_rows = [];
    $best_elo_rows = [];
    $best_annual_rows = [];
    if (class_exists('Futbolin_Best_Service') && $api_client_ok && !empty($ordered_modalidades)) {
        try {
            $bestSvc = new Futbolin_Best_Service($api_client);
            // Toggles de anual (como antes)
            $opts_prof = get_option('mi_plugin_futbolin_options', []);
            $annual_doubles_on    = (!isset($opts_prof['enable_annual_doubles']) || $opts_prof['enable_annual_doubles'] === 'on');
            $annual_individual_on = (!isset($opts_prof['enable_annual_individual']) || $opts_prof['enable_annual_individual'] === 'on');
            $annual_enabled = ($annual_doubles_on || $annual_individual_on);

            foreach ($ordered_modalidades as $modalidad) {
                $mod = is_object($modalidad) ? $modalidad : (object)$modalidad;
                $mod_id = isset($mod->modalidadId) ? (int)$mod->modalidadId : (isset($mod->id) ? (int)$mod->id : 0);
                $mod_name = isset($mod->descripcion) ? (string)$mod->descripcion : (isset($mod->Descripcion) ? (string)$mod->Descripcion : 'Modalidad');
                if ($mod_id <= 0) continue;

                // Mejor histórica ELO
                $bestElo = $bestSvc->get_best_elo_for_modality((int)$player_id, (int)$mod_id, 1, defined('FUTBOLIN_MAX_SEASON_ORDINAL') ? (int)constant('FUTBOLIN_MAX_SEASON_ORDINAL') : 14);
                // Mejor histórica Anual (solo si está habilitado y modalidad es de anual)
                $bestAnnual = null;
                if ($annual_enabled) {
                    $isAnnualMod = in_array($mod_name, ['Dobles','Individual'], true);
                    if ($isAnnualMod) {
                        $bestAnnual = $bestSvc->get_best_annual_for_modality((int)$player_id, (int)$mod_id, 1, defined('FUTBOLIN_MAX_SEASON_ORDINAL') ? (int)constant('FUTBOLIN_MAX_SEASON_ORDINAL') : 14);
                    }
                }
                if ($bestElo) {
                    $best_elo_rows[] = [
                        'mod_id'   => $mod_id,
                        'mod_name' => $mod_name,
                        'pos'      => (int)$bestElo['pos'],
                        'season'   => (int)$bestElo['season'],
                    ];
                }
                if ($bestAnnual) {
                    $best_annual_rows[] = [
                        'mod_id'   => $mod_id,
                        'mod_name' => $mod_name,
                        'pos'      => (int)$bestAnnual['pos'],
                        'season'   => (int)$bestAnnual['season'],
                    ];
                }
            }
        } catch (Exception $e) {
            // No romper la pestaña si falla el cálculo
        }
    }
    ?>

    <?php if (!empty($best_elo_rows) || !empty($best_annual_rows)) : ?>
        </div>
        <div class="futbolin-card" style="margin-top: 24px;">
            <h3 class="history-main-title" style="margin-bottom: 6px;">Mejor clasificación histórica a final de temporada</h3>
            <p class="history-note" style="margin-top:0; color: var(--futbolin-text-muted, #6b7280); font-size:.9em;">No se incluye la temporada en vigor.</p>
    <?php endif; ?>

    <?php if (!empty($best_elo_rows)) : ?>
        <div class="player-ranking-block best-elo-block" style="margin-top: 56px;">
            <h4 class="section-subtitle" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">Ranking ELO histórico</h4>
            <ul class="stats-list">
                <?php foreach ($best_elo_rows as $row): ?>
                <li class="ranking-row <?php $p=(int)$row['pos']; echo ($p>=1 && $p<=3)?'has-pos-'.$p:''; ?>">
                    <div class="ranking-position pos-<?php echo esc_attr((int)$row['pos']); ?>"><?php echo (int)$row['pos']; ?></div>
                    <div class="ranking-player-details" style="flex:1;">
                        <h4 style="margin:0; font-size: 1.2em; color: var(--futbolin-text-headings);">
                            <?php echo esc_html($row['mod_name']); ?>
                        </h4>
                    </div>
                    <div class="ranking-points">
                        <div class="points-pill season-pill" style="display:flex; align-items:center; gap:6px; white-space:nowrap;">
                            <span class="points-label" style="font-weight:700;">Temporada</span>
                            <span class="points-value" style="font-weight:800;"><?php echo (int)$row['season']; ?></span>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div><!-- /.player-ranking-block.best-elo-block -->
    <?php endif; ?>

    <?php if (!empty($best_annual_rows)) : ?>
        <div class="player-ranking-block best-annual-block" style="margin-top: 28px;">
            <h4 class="section-subtitle" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">Ranking anual histórico</h4>
            <ul class="stats-list">
                <?php foreach ($best_annual_rows as $row): ?>
                <li class="ranking-row <?php $p=(int)$row['pos']; echo ($p>=1 && $p<=3)?'has-pos-'.$p:''; ?>">
                    <div class="ranking-position pos-<?php echo esc_attr((int)$row['pos']); ?>"><?php echo (int)$row['pos']; ?></div>
                    <div class="ranking-player-details" style="flex:1;">
                        <h4 style="margin:0; font-size: 1.2em; color: var(--futbolin-text-headings);">
                            <?php echo esc_html($row['mod_name']); ?>
                        </h4>
                    </div>
                    <div class="ranking-points">
                        <div class="points-pill season-pill" style="display:flex; align-items:center; gap:6px; white-space:nowrap;">
                            <span class="points-label" style="font-weight:700;">Temporada</span>
                            <span class="points-value" style="font-weight:800;"><?php echo (int)$row['season']; ?></span>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div><!-- /.player-ranking-block.best-annual-block -->
    <?php endif; ?>

</div>



