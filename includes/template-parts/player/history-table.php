<?php
if (!defined('ABSPATH')) exit;
/** @var array $rows */
/** @var int $player_id */
?>
<div class="futbolin-ranking-wrapper">
  <table class="futbolin-table">
    <thead>
      <tr>
        <th><?php echo esc_html__('Fecha', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Torneo', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Competición', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Fase', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Modalidad', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Local', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Visitante', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Marcador', 'ranking-futbolin'); ?></th>
        <th><?php echo esc_html__('Resultado', 'ranking-futbolin'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): 
        $fecha = isset($row['fecha']) ? esc_html(substr($row['fecha'], 0, 10)) : '—';
        $torneo = isset($row['torneo']) ? esc_html($row['torneo']) : '—';
        $competicion = isset($row['tipoCompeticion']) ? esc_html($row['tipoCompeticion']) : (isset($row['competicion']) ? esc_html($row['competicion']) : '—');
        $fase = isset($row['fase']) ? esc_html($row['fase']) : '—';
        $modalidad = isset($row['modalidad']) ? esc_html($row['modalidad']) : '—';
        $loc = isset($row['equipoLocal']) ? esc_html($row['equipoLocal']) : '—';
        $vis = isset($row['equipoVisitante']) ? esc_html($row['equipoVisitante']) : '—';
        $score = trim((isset($row['puntosLocal']) ? $row['puntosLocal'] : '') . ' - ' . (isset($row['puntosVisitante']) ? $row['puntosVisitante'] : ''));
        $score = esc_html($score);

        $is_local = false;
        if (isset($row['equipoLocalDTO']['jugadores']) && is_array($row['equipoLocalDTO']['jugadores'])) {
            foreach ($row['equipoLocalDTO']['jugadores'] as $j) {
                if (intval($j['jugadorId'] ?? 0) === $player_id) {
                    $is_local = true;
                    break;
                }
            }
        }
        $ganadorLocal = isset($row['ganadorLocal']) ? (bool)$row['ganadorLocal'] : null;
        $resultado = '—';
        if ($ganadorLocal !== null) {
            $gano = ($is_local && $ganadorLocal) || (!$is_local && $ganadorLocal === false);
            $resultado = $gano ? 'Ganado' : 'Perdido';
        }
      ?>
      <tr>
        <td><?php echo $fecha; ?></td>
        <td><?php echo $torneo; ?></td>
        <td><?php echo $competicion; ?></td>
        <td><?php echo $fase; ?></td>
        <td><?php echo $modalidad; ?></td>
        <td><?php echo $loc; ?></td>
        <td><?php echo $vis; ?></td>
        <td><?php echo $score; ?></td>
        <td><?php echo esc_html($resultado); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
