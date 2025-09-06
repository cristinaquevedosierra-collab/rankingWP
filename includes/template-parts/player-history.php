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
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Normaliza $processor por seguridad
if (!isset($processor) || !is_object($processor)) {
    $processor = (object)[];
}

// Helpers
$dash = '—';
$h = function($v) use ($dash) {
    if ($v === null || $v === '') return $dash;
    return esc_html((string)$v);
};
$hn = function($v) use ($dash) {
    if ($v === null || $v === '' || !is_numeric($v)) return $dash;
    return esc_html((string)$v);
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

    <?php if (empty($grouped)) : ?>
        <p>No hay partidos registrados para este jugador.</p>
    <?php else : ?>
        <?php foreach ($grouped as $season => $tournaments) : ?>
            <?php if (empty($tournaments) || !is_array($tournaments)) continue; ?>
            <div class="history-season-block">
                <h4 class="season-title">Temporada <?php echo $h($season); ?></h4>

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
                                if ($loc !== 'NOEQUIPO' && $vis !== 'NOEQUIPO') {
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
                            foreach ($competitions as $competicion_nombre => $partidos) :
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

                                        $status_text   = ($puntosGanados >= 0) ? 'Victoria' : 'Derrota';
                                        $status_class  = ($puntosGanados >= 0) ? 'status-victoria' : 'status-derrota';
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
                                        <div class="ranking-row history-match-row">
                                            <div class="ranking-position history-match-phase <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></div>
                                            <div class="history-match-details">
                                                <div class="history-match-winner"><?php echo $ganador_display; ?></div>
                                                <div class="history-match-vs">vs</div>
                                                <div class="history-match-loser"><?php echo $perdedor_display; ?></div>
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