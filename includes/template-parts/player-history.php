<?php
if (!isset($player) && isset($jugador)) { $player = $jugador; }
$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
?>
<?php
/**
 * Archivo: includes/template-parts/player-history.php
 * Descripción: Historial detallado de partidos de un jugador, con accesos seguros.
 */
if (!defined('ABSPATH')) exit;
if (!function_exists('__')) {
    if (file_exists(ABSPATH . 'wp-includes/l10n.php')) {
        require_once ABSPATH . 'wp-includes/l10n.php';
    } elseif (file_exists(ABSPATH . 'wp-includes/functions.php')) {
        require_once ABSPATH . 'wp-includes/functions.php';
    }
    if (!function_exists('__')) {
        function __($text, $domain = 'default') { return $text; }
    }
}
if (!function_exists('wp_enqueue_script')) {
    require_once ABSPATH . 'wp-includes/functions.wp-scripts.php';
    // En entornos fuera de WP, define un stub para evitar avisos del editor
    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) { return false; }
    }
}
if (!function_exists('plugins_url')) {
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
    }
    // plugins_url está definido en wp-includes/link-template.php
    $wp_link_template = ABSPATH . 'wp-includes/link-template.php';
    if (file_exists($wp_link_template)) { require_once $wp_link_template; }
}
// Como salvaguarda para editores/linters fuera de WP, define un stub si aún no existe
if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $plugin = '') { return is_string($path) ? $path : ''; }
}
if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
if (!function_exists('_futb_filter_history_rows')) { function _futb_filter_history_rows($rows){ if(!is_array($rows)) return []; return array_values(array_filter($rows, function($r){ return _futb_should_count_for_stats($r); })); } }

// Normaliza $processor por seguridad
if (!isset($processor) || !is_object($processor)) {
    $processor = (object)[];
}

// Helpers
$dash = '—';
$h = function($v) use ($dash) {
    if ($v === null || $v === '') return $dash;
    $out = (string)$v;
    $out = str_replace('()', '', $out); // Elimina cualquier aparición de '()'
    return esc_html($out);
};
$hn = function($v) use ($dash) {
    if ($v === null || $v === '' || !is_numeric($v)) return $dash;
    $out = (string)$v;
    $out = str_replace('()', '', $out); // Elimina cualquier aparición de '()'
    return esc_html($out);
};
$bool = function($v) { return (bool)$v; };

// Fuentes de datos seguras
$grouped = (isset($processor->grouped_matches) && is_array($processor->grouped_matches)) ? $processor->grouped_matches : [];

// Orden: temporadas desc (si las claves son comparables), torneos por nombre asc
if (!empty($grouped)) {
    // si las keys de temporada son años/strings comparables, krsort pondrá las más recientes primero
    $tmp_grouped = $grouped;
    if (@krsort($tmp_grouped)) {
        $grouped = $tmp_grouped;
    }
    foreach ($grouped as $seasonKey => $tournamentsSet) {
        if (is_array($tournamentsSet)) {
            $tmp_t = $tournamentsSet;
            @ksort($tmp_t, SORT_NATURAL | SORT_FLAG_CASE);
            $grouped[$seasonKey] = $tmp_t;
        }
    }
}
?>

<div class="futbolin-card">
    <h3 class="history-main-title">Historial de Partidos Detallado</h3>
<?php
    // === Bloque de totales para el historial ===
    $total_jugadas = 0;
    $total_ganadas = 0;
    $total_perdidas = 0;
    $player_name_for_stats = '';
    if (isset($player) && is_object($player)) {
        if (!empty($player->nombreJugador)) { $player_name_for_stats = (string)$player->nombreJugador; }
        elseif (!empty($player->nombre))    { $player_name_for_stats = (string)$player->nombre; }
    }

    if (!empty($grouped) && is_array($grouped)) {
        foreach ($grouped as $season_loop => $tournaments_loop) {
            if (empty($tournaments_loop) || !is_array($tournaments_loop)) continue;
            foreach ($tournaments_loop as $torneo_loop => $competitions_loop) {
                if (empty($competitions_loop) || !is_array($competitions_loop)) continue;
                foreach ($competitions_loop as $competicion_loop => $partidos_loop) {
                    if (empty($partidos_loop) || !is_array($partidos_loop)) continue;
                    $partidos_loop = _futb_filter_history_rows($partidos_loop);
                    foreach ($partidos_loop as $row_loop) {
                        $won = _futb_won_match($row_loop, $player_id, $player_name_for_stats);
                        if ($won === null) continue;
                        $total_jugadas++;
                        if ($won) $total_ganadas++; else $total_perdidas++;
                    }
                }
            }
        }
    }
?>
<div class="history-summary-search">
            <div id="history-global-label" class="history-global-label" role="heading" aria-level="2" style="display:block;margin-bottom:6px;font-weight:700;width:100%;flex-basis:100%;">RESULTADOS GLOBALES</div>
  <input type="hidden" id="history-player-name" value="<?php echo esc_attr($player_name_for_stats); ?>">
  <input type="hidden" id="history-player-id" value="<?php echo (int)$player_id; ?>">
    <div class="history-summary-cards" role="status" aria-live="polite">
        <div class="hs-item hs-total">
            <span><?php echo esc_html(__('Jugadas', 'ranking-futbolin')); ?></span>
            <strong id="hs-count-total"><?php echo (int)$total_jugadas; ?></strong>
        </div>
        <div class="hs-item hs-won">
            <span><?php echo esc_html(__('Ganadas', 'ranking-futbolin')); ?></span>
            <strong id="hs-count-won"><?php echo (int)$total_ganadas; ?></strong>
        </div>
        <div class="hs-item hs-lost">
            <span><?php echo esc_html(__('Perdidas', 'ranking-futbolin')); ?></span>
            <strong id="hs-count-lost"><?php echo (int)$total_perdidas; ?></strong>
        </div>
        <div class="hs-item hs-rate">
            <span><?php echo esc_html(__('% Victorias', 'ranking-futbolin')); ?></span>
            <strong id="hs-count-rate">
                <?php
                    $rate = ($total_jugadas > 0) ? round(($total_ganadas * 100.0 / $total_jugadas), 1) : 0;
                    echo esc_html($rate) . '%';
                ?>
            </strong>
        </div>
    </div>
  <div class="history-search-box">
    <label for="history-search" class="screen-reader-text"><?php echo esc_html(__('Buscar en historial', 'ranking-futbolin')); ?></label>
    <input type="search" id="history-search" class="history-search-input" placeholder="<?php echo esc_attr(__('Filtrar por torneo, fase, rival…', 'ranking-futbolin')); ?>" value="<?php echo esc_attr($q); ?>" />
  </div>
</div>



    <?php if (empty($grouped)) : ?>
        <p>No hay partidos registrados para este jugador.</p>
    <?php else : ?>
        <?php foreach ($grouped as $season => $tournaments) : ?>
            <?php if (empty($tournaments) || !is_array($tournaments)) continue; ?>
            <div class="history-season-block">
                                <h4 class="season-title">
                                    <?php
                                        $season_str = (string)$season;
                                        // Si la variable ya contiene 'Temporada', no anteponerla
                                        if (stripos($season_str, 'temporada') === 0) {
                                            echo $h($season_str);
                                        } else {
                                            echo 'Temporada ' . $h($season_str);
                                        }
                                    ?>
                                </h4>

                <?php foreach ($tournaments as $torneo_nombre => $competitions) : ?>
                    <?php
                    // Verifica si hay al menos un partido "real" (no NOEQUIPO) en el torneo
                    $has_real_matches_in_tournament = false;
                    if (is_array($competitions)) {
                        foreach ($competitions as $partidos_list) {
                            if (!is_array($partidos_list)) continue;
                            foreach ($partidos_list as $partido_check) {
                                $loc  = isset($partido_check->equipoLocal)    ? trim((string)$partido_check->equipoLocal)    : '';
                                $vis  = isset($partido_check->equipoVisitante) ? trim((string)$partido_check->equipoVisitante) : '';
                                if ($loc !== 'NOEQUIPO' && $vis !== 'NOEQUIPO' && !_futb_is_penalty_row($partido_check) && _futb_has_real_result($partido_check)) {
                                    $has_real_matches_in_tournament = true;
                                    break 2;
                                }
                            }
                        }
                    }
                    ?>
                    <div class="history-tournament-block">
                        <?php if ($has_real_matches_in_tournament) : ?>
                            <div class="tournament-header">
                                <h5><?php echo $h($torneo_nombre); ?></h5>
                            </div>
                        <?php endif; ?>

                        <?php
                        $competition_title_shown = [];
                        if (!empty($competitions) && is_array($competitions)) :
                            foreach ($competitions as $competicion_nombre => $partidos) : $partidos = _futb_filter_history_rows($partidos);
                                if (empty($partidos) || !is_array($partidos)) continue;
                        ?>
                            <div class="history-matches-list">
                                <?php foreach ($partidos as $partido) : ?>
                                    <?php
                                    // Valores protegidos
                                    $equipoLocal     = isset($partido->equipoLocal)    ? trim((string)$partido->equipoLocal)    : '';
                                    $equipoVisitante = isset($partido->equipoVisitante)? trim((string)$partido->equipoVisitante): '';
                                    $puntosLocal     = isset($partido->puntosLocal)    ? $partido->puntosLocal    : null;
                                    $puntosVisitante = isset($partido->puntosVisitante)? $partido->puntosVisitante: null;
                                    $puntosGanados   = isset($partido->puntosGanados)  ? (float)$partido->puntosGanados : 0.0;
                                    $puntuacionFinal = isset($partido->puntuacionFinal)? (float)$partido->puntuacionFinal : 0.0;
                                    $ganadorLocal    = isset($partido->ganadorLocal)   ? $bool($partido->ganadorLocal) : null;
                                    $modalidad       = isset($partido->modalidad)      ? (string)$partido->modalidad : '';

                                    // Inactividad
                                    $is_inactivity = ($equipoLocal === 'NOEQUIPO' || $equipoVisitante === 'NOEQUIPO');

                                    if ($is_inactivity) :
                                        $cambio_str = (string)round($puntosGanados, 1);
                                        ?>
                                        <div class="ranking-row history-inactivity-row">
                                            <span class="inactivity-icon">⏱️</span>
                                            <span class="inactivity-text">Retracción de puntos por inactividad (<?php echo $h($modalidad); ?>)</span>
                                            <span class="elo-change puntos-negativos"><?php echo esc_html($cambio_str); ?></span>
                                        </div>
                                    <?php
                                    else :
                                        if (!isset($competition_title_shown[$competicion_nombre])) {
                                            echo '<p class="history-competition-title">' . $h($competicion_nombre) . '</p>';
                                            $competition_title_shown[$competicion_nombre] = true;
                                        }

                                        $won_for_player = _futb_won_match($partido, $player_id, isset($player->nombreJugador) ? (string)$player->nombreJugador : '');
        if ($won_for_player === null) {
            $status_text  = '—';
            $status_class = 'status-indef';
        } else {
            $status_text  = $won_for_player ? 'Victoria' : 'Derrota';
            $status_class = $won_for_player ? 'status-victoria' : 'status-derrota';
        }
                                        $puntos_iniciales = $puntuacionFinal - $puntosGanados;
                                        $cambio_str    = ($puntosGanados >= 0 ? '+' : '') . (string)round($puntosGanados, 1);
                                        $cambio_class  = ($puntosGanados >= 0) ? 'puntos-positivos' : 'puntos-negativos';

                                        // Ganador / perdedor display (con fallback por si falta info)
                                        $ganador_display  = $dash;
                                        $perdedor_display = $dash;
                                        if ($ganadorLocal === true) {
                                            $ganador_display  = $h($equipoLocal) . ' (' . $hn($puntosLocal) . ')';
                                            $perdedor_display = $h($equipoVisitante) . ' (' . $hn($puntosVisitante) . ')';
                                        } elseif ($ganadorLocal === false) {
                                            $ganador_display  = $h($equipoVisitante) . ' (' . $hn($puntosVisitante) . ')';
                                            $perdedor_display = $h($equipoLocal) . ' (' . $hn($puntosLocal) . ')';
                                        }
                                        ?>
                                        <?php
                                        // Construir nombres por equipo (si el DTO trae jugadores, usar sus nombres; fallback: nombre del equipo)
                                        $__names_local = [];
                                        if (isset($partido->equipoLocalDTO) && isset($partido->equipoLocalDTO->jugadores) && is_array($partido->equipoLocalDTO->jugadores)) {
                                            foreach ($partido->equipoLocalDTO->jugadores as $__j) {
                                                if (!is_object($__j) && !is_array($__j)) continue;
                                                $__o = is_object($__j) ? $__j : (object)$__j;
                                                foreach (['nombreJugador','NombreJugador','nombre','Nombre','apodo','Apodo'] as $__nk) {
                                                    if (isset($__o->$__nk) && $__o->$__nk) { $__names_local[] = (string)$__o->$__nk; break; }
                                                }
                                            }
                                        }
                                        $__names_visit = [];
                                        if (isset($partido->equipoVisitanteDTO) && isset($partido->equipoVisitanteDTO->jugadores) && is_array($partido->equipoVisitanteDTO->jugadores)) {
                                            foreach ($partido->equipoVisitanteDTO->jugadores as $__j) {
                                                if (!is_object($__j) && !is_array($__j)) continue;
                                                $__o = is_object($__j) ? $__j : (object)$__j;
                                                foreach (['nombreJugador','NombreJugador','nombre','Nombre','apodo','Apodo'] as $__nk) {
                                                    if (isset($__o->$__nk) && $__o->$__nk) { $__names_visit[] = (string)$__o->$__nk; break; }
                                                }
                                            }
                                        }
                                        if (empty($__names_local)) { $__names_local[] = (string)$equipoLocal; }
                                        if (empty($__names_visit)) { $__names_visit[] = (string)$equipoVisitante; }
                                        $__names_winner = ($ganadorLocal === true) ? $__names_local : (($ganadorLocal === false) ? $__names_visit : []);
                                        $__names_loser  = ($ganadorLocal === true) ? $__names_visit : (($ganadorLocal === false) ? $__names_local : []);
                                        $data_names_winner = esc_attr(implode(' ', array_filter($__names_winner)));
                                        $data_names_loser  = esc_attr(implode(' ', array_filter($__names_loser)));
                                        ?>
                                        <div class="ranking-row history-match-row" data-resultado="<?php echo esc_attr($status_text); ?>"
             data-valid="<?php echo _futb_should_count_for_stats($partido) ? '1' : '0'; ?>"
             data-win="<?php echo ($won_for_player === true ? '1' : ($won_for_player === false ? '0' : '')); ?>"
             data-player-side="<?php echo ($won_for_player === true ? 'W' : ($won_for_player === false ? 'L' : '')); ?>"
             data-names-winner="<?php echo $data_names_winner; ?>"
             data-names-loser="<?php echo $data_names_loser; ?>"
             data-players="<?php $__players = []; if (isset($partido->equipoLocalDTO) && isset($partido->equipoLocalDTO->jugadores) && is_array($partido->equipoLocalDTO->jugadores)) { foreach ($partido->equipoLocalDTO->jugadores as $__j) { if (isset($__j->jugadorId)) { $__players[] = (int)$__j->jugadorId; } } } if (isset($partido->equipoVisitanteDTO) && isset($partido->equipoVisitanteDTO->jugadores) && is_array($partido->equipoVisitanteDTO->jugadores)) { foreach ($partido->equipoVisitanteDTO->jugadores as $__j) { if (isset($__j->jugadorId)) { $__players[] = (int)$__j->jugadorId; } } } if (empty($__players) && isset($player_id)) { $__players[] = (int)$player_id; } $__players = array_values(array_unique(array_filter($__players, fn($x)=>$x!==null))); $__data_players = esc_attr(implode(',', $__players)); ?><?php echo $__data_players; ?>">
    
                                            <div class="ranking-position history-match-phase <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></div>
                                            <div class="history-match-details">
                                                <div class="history-match-winner"><?php echo str_replace(['—', '()'], '', $ganador_display); ?></div>
                                                <div class="history-match-vs">vs</div>
                                                <div class="history-match-loser"><?php echo str_replace(['—', '()'], '', $perdedor_display); ?></div>
                                            </div>
                                            <div class="history-match-elo">
                                                <span class="elo-value elo-initial" title="Puntuación Inicial"><?php echo $hn(round($puntos_iniciales)); ?></span>
                                                <span class="elo-arrow">→</span>
                                                <span class="elo-change <?php echo esc_attr($cambio_class); ?>" title="Cambio de Puntos"><?php echo esc_html($cambio_str); ?></span>
                                                <span class="elo-arrow">→</span>
                                                <span class="elo-value elo-final" title="Puntuación Final"><?php echo $hn(round($puntuacionFinal)); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
// Encola script externo para el filtro de historial (evita escaping de && en inline <script>)
// Nota: evitamos dirname(__DIR__, 2) por compatibilidad con analizadores/entornos; construimos la ruta del plugin principal de forma explícita
if (function_exists('wp_enqueue_script') && function_exists('plugins_url')) {
    $plugin_main_file = defined('FUTBOLIN_API_PATH')
        ? FUTBOLIN_API_PATH . 'ranking-futbolin.php'
        : (dirname(dirname(__DIR__)) . '/ranking-futbolin.php');
    $script_src = plugins_url('assets/js/player-history-inline.js', $plugin_main_file);
    // Cache-busting del JS de historial: usar filemtime si es posible
    $script_ver = null;
    $inline_js_path = defined('FUTBOLIN_API_PATH')
        ? FUTBOLIN_API_PATH . 'assets/js/player-history-inline.js'
        : (dirname(dirname(__DIR__)) . '/assets/js/player-history-inline.js');
    if (file_exists($inline_js_path)) {
        $script_ver = @filemtime($inline_js_path);
    }
    wp_enqueue_script(
        'futbolin-player-history',
        $script_src,
        array(),
        ($script_ver ?: (defined('FUTBOLIN_API_VERSION') ? FUTBOLIN_API_VERSION : null)),
        true
    );
}
?>
