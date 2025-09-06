<?php
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

require_once FUTBOLIN_API_PATH . 'includes/core/class-futbolin-normalizer.php';
include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/**
 * Variables esperadas (las provee el controlador):
 * - $tournament_data: array|iterable de objetos de posiciones del torneo
 * - $ranking_error: string|null con mensaje de error
 */

$back_url = _futb_url_tournaments([]);

// Normaliza $tournament_data a array de objetos
$rows = [];
if (!empty($tournament_data)) {
  if (is_array($tournament_data) || $tournament_data instanceof Traversable) {
    foreach ($tournament_data as $r) {
      if (is_object($r)) $rows[] = $r;
    }
  }
}

// Determina título del torneo (seguro)
$torneo_titulo = 'Campeonato';
if (!empty($rows) && isset($rows[0]->nombreTorneo) && is_scalar($rows[0]->nombreTorneo)) {
  $torneo_titulo = (string)$rows[0]->nombreTorneo;
}

// Prepara competiciones (si la clase existe)
$competitions = [];
if (class_exists('Futbolin_Tournament_Stats')) {
  $competitions = Futbolin_Tournament_Stats::prepare_competitions($rows);
} else {
  // Fallback muy simple: todo en un bloque si no existe la clase
  $competitions = !empty($rows) ? ['Resultados' => $rows] : [];
}
?>
<div class="futbolin-card">
  <a href="<?php echo esc_url($back_url); ?>" class="futbolin-back-button">← Volver a la lista de campeonatos</a>

  <?php if (!empty($ranking_error)): ?>
    <div class="futbolin-inline-notice"><?php echo esc_html($ranking_error); ?></div>

  <?php elseif (empty($competitions)): ?>
    <p>No se encontraron datos de competición para este torneo.</p>

  <?php else: ?>
    <h2 class="futbolin-main-title h2">Resultados del Torneo: <?php echo esc_html($torneo_titulo); ?></h2>

    <?php foreach ($competitions as $competicion_name => $results): ?>
      <div class="tournament-competicion-block">
        <h4 class="competicion-header"><?php echo esc_html($competicion_name); ?></h4>
        <div class="ranking-table">
          <?php foreach ((array)$results as $result):
            $pos = isset($result->posicion) ? (int)$result->posicion : 0;
            $pos_html = $pos > 0 ? (string)$pos : '—';
            $pos_class = 'ranking-position';
            if ($pos >= 1 && $pos <= 3) {
              $pos_class .= ' pos-' . $pos;
              $pos_html = '<span class="badge pos-' . $pos . '">' . $pos . '</span>';
            }
            $team = isset($result->equipoJugadores) ? (string)$result->equipoJugadores : '—';
          ?>
            <div class="ranking-row tournament-row">
              <div class="tournament-position-cell">
                <div class="<?php echo esc_attr($pos_class); ?>"><?php echo $pos_html; // intencionalmente sin esc_html por badge ?></div>
              </div>
              <div class="tournament-team-details">
                <?php echo esc_html($team); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>
</div>