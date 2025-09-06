<?php
$h2h_a = isset($h2h_a) ? (string)$h2h_a : (isset($_GET['a']) ? sanitize_text_field(wp_unslash($_GET['a'])) : '');
$h2h_b = isset($h2h_b) ? (string)$h2h_b : (isset($_GET['b']) ? sanitize_text_field(wp_unslash($_GET['b'])) : '');
$page = isset($page) ? (int)$page : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
?>
<?php
/**
 * Archivo: includes/template-parts/h2h-results.php
 * Descripción: Muestra la comparativa visual entre dos jugadores (Head-to-Head).
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// $stats debe llegar desde la plantilla que incluye este archivo.
$stats = isset($stats) ? $stats : null;

// Normaliza entradas para evitar notices
$p1   = (is_object($stats) && isset($stats->p1_stats) && is_array($stats->p1_stats)) ? $stats->p1_stats : [];
$p2   = (is_object($stats) && isset($stats->p2_stats) && is_array($stats->p2_stats)) ? $stats->p2_stats : [];
$h2h  = (is_object($stats) && isset($stats->h2h_stats) && is_array($stats->h2h_stats)) ? $stats->h2h_stats : [];

$p1_name = isset($p1['name']) ? (string)$p1['name'] : 'Jugador 1';
$p2_name = isset($p2['name']) ? (string)$p2['name'] : 'Jugador 2';

$p1_tourn = isset($p1['total_tournaments']) ? (int)$p1['total_tournaments'] : 0;
$p2_tourn = isset($p2['total_tournaments']) ? (int)$p2['total_tournaments'] : 0;

$p1_wins  = isset($p1['wins'])  ? (int)$p1['wins']  : 0;
$p1_loss  = isset($p1['losses'])? (int)$p1['losses']: 0;
$p2_wins  = isset($p2['wins'])  ? (int)$p2['wins']  : 0;
$p2_loss  = isset($p2['losses'])? (int)$p2['losses']: 0;

$p1_titles = isset($p1['titles']) ? (int)$p1['titles'] : 0;
$p2_titles = isset($p2['titles']) ? (int)$p2['titles'] : 0;

$h2h_p1 = isset($h2h['p1_wins']) ? (int)$h2h['p1_wins'] : 0;
$h2h_p2 = isset($h2h['p2_wins']) ? (int)$h2h['p2_wins'] : 0;

// Partidos directos (array de objetos)
$direct_matches = (is_object($stats) && !empty($stats->direct_matches) && is_array($stats->direct_matches))
  ? $stats->direct_matches
  : [];
?>
<div class="h2h-results-area dark-theme">
  <div class="h2h-main-scoreboard">
    <div class="player-card">
      <div class="player-avatar player-1"></div>
      <div class="player-name"><?php echo esc_html($p1_name); ?></div>
    </div>
    <div class="score-center">
      <div class="score-ring">
        <span class="score-number player-1-color"><?php echo esc_html(number_format_i18n($h2h_p1)); ?></span>
        <span class="vs-text">VS</span>
        <span class="score-number player-2-color"><?php echo esc_html(number_format_i18n($h2h_p2)); ?></span>
      </div>
      <div class="score-label">Victorias H2H</div>
    </div>
    <div class="player-card">
      <div class="player-avatar player-2"></div>
      <div class="player-name"><?php echo esc_html($p2_name); ?></div>
    </div>
  </div>

  <div class="h2h-stats-comparison">
    <div class="stat-row">
      <div class="stat-value"><?php echo esc_html(number_format_i18n($p1_tourn)); ?></div>
      <div class="stat-label">Torneos Jugados</div>
      <div class="stat-value"><?php echo esc_html(number_format_i18n($p2_tourn)); ?></div>
    </div>

    <div class="stat-row highlighted">
      <div class="stat-value"><?php echo esc_html($p1_wins . ' / ' . $p1_loss); ?></div>
      <div class="stat-label">Victorias / Derrotas (Carrera)</div>
      <div class="stat-value"><?php echo esc_html($p2_wins . ' / ' . $p2_loss); ?></div>
    </div>

    <div class="stat-row">
      <div class="stat-value"><?php echo esc_html(number_format_i18n($p1_titles)); ?></div>
      <div class="stat-label">Títulos en su carrera</div>
      <div class="stat-value"><?php echo esc_html(number_format_i18n($p2_titles)); ?></div>
    </div>
  </div>

  <?php if (!empty($direct_matches)) : ?>
    <div class="h2h-match-breakdown">
      <h3>Desglose de Enfrentamientos</h3>
      <table class="h2h-breakdown-table">
        <thead>
          <tr>
            <th>Año</th><th>Ganador</th><th>Torneo</th><th>Competición</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($direct_matches as $match):
          if (!is_object($match)) continue;

          $loc_name  = isset($match->equipoLocal)     ? (string)$match->equipoLocal     : '';
          $vis_name  = isset($match->equipoVisitante) ? (string)$match->equipoVisitante : '';
          $gan_loc   = isset($match->ganadorLocal)    ? (bool)$match->ganadorLocal      : null;

          // Determina si P1 está en local por comparación segura (case-insensitive)
          $p1_is_local = ($loc_name !== '' && stripos((string)($loc_name ?? ''), $p1_name) !== false);

          // Si no hay info de ganador, no marcamos ganador (clase neutra)
          if ($gan_loc === null) {
            $winner_team  = $ganador_class = '';
          } else {
            $p1_won       = ($p1_is_local && $gan_loc) || (!$p1_is_local && $gan_loc === false);
            $winner_team  = $p1_won ? $loc_name : $vis_name;
            $ganador_class = $p1_won ? 'winner-p1' : 'winner-p2';
          }

          $temporada   = isset($match->temporada)   ? (string)$match->temporada   : '';
          $torneo      = isset($match->torneo)      ? (string)$match->torneo      : '';
          $competicion = isset($match->competicion) ? (string)$match->competicion : '';
        ?>
          <tr class="<?php echo isset($ganador_class) ? esc_attr($ganador_class) : ''; ?>">
            <td><?php echo esc_html($temporada); ?></td>
            <td class="winner-cell"><?php echo esc_html($winner_team); ?></td>
            <td><?php echo esc_html($torneo); ?></td>
            <td><?php echo esc_html($competicion); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>