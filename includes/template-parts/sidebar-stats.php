<?php
/**
 * Archivo: includes/template-parts/sidebar-stats.php
 * Descripción: Bloque de estadísticas generales en la sidebar.
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// ===== Datos de temporadas (array de temporadas guardado en options) =====
$seasons_data      = get_option('futbolin_total_seasons_count', []);
$temporadas_count  = is_array($seasons_data) ? count($seasons_data) : 0;

// ===== Total de torneos (precalculado en admin) =====
$total_tournaments = (int) get_option('futbolin_finals_total_tournaments_count', 0);

// ===== Top en victorias desde el Hall of Fame (transient) =====
$hof = get_transient('futbolin_hall_of_fame_data');

$top_name = '';
$top_wins = 0;
$top_id   = 0;

if (is_array($hof) && !empty($hof)) {
  // Ordena por partidas_ganadas desc de forma robusta (array u objeto)
  usort($hof, function($a, $b){
    $aw = is_array($a) ? (int)($a['partidas_ganadas'] ?? 0) : (int)($a->partidas_ganadas ?? 0);
    $bw = is_array($b) ? (int)($b['partidas_ganadas'] ?? 0) : (int)($b->partidas_ganadas ?? 0);
    return $bw <=> $aw;
  });

  $top = reset($hof);
  if ($top) {
    if (is_array($top)) {
      $top_name = (string)($top['nombre'] ?? '');
      $top_wins = (int)($top['partidas_ganadas'] ?? 0);
      $top_id   = (int)($top['jugador_id'] ?? $top['id'] ?? 0);
    } else {
      $top_name = (string)($top->nombre ?? '');
      $top_wins = (int)($top->partidas_ganadas ?? 0);
      $top_id   = (int)($top->jugador_id ?? $top->id ?? 0);
    }
  }
}

// ===== Enlace al perfil si tenemos URL-base y un id =====
$profile_page_url = isset($profile_page_url) ? (string)$profile_page_url : '';
$link_open  = '';
$link_close = '';
if ($profile_page_url && $top_id) {
  $profile_url = esc_url( add_query_arg('jugador_id', $top_id, $profile_page_url) );
  $link_open   = '<a href="'.$profile_url.'">';
  $link_close  = '</a>';
}
?>
<div class="futbolin-sidebar-block">
  <h3>Estadísticas Generales</h3>
  <ul class="stats-list general-stats">
    <li>
      <strong>Temporadas en el Ranking:</strong>
      <span>
        <?php
          if ($temporadas_count > 0) {
            echo esc_html( number_format_i18n($temporadas_count) );
          } else {
            echo '<span class="value-placeholder"><em>(Aún no disponible)</em></span>';
          }
        ?>
      </span>
    </li>
    <li>
      <strong>Total Torneos Registrados:</strong>
      <span>
        <?php
          if ($total_tournaments > 0) {
            echo esc_html( number_format_i18n($total_tournaments) );
          } else {
            echo '<span class="value-placeholder"><em>(Aún no disponible)</em></span>';
          }
        ?>
      </span>
    </li>
    <li>
      <strong>Jugador con Más Victorias:</strong>
      <span>
        <?php
          if ($top_name !== '') {
            echo $link_open . esc_html($top_name) . $link_close
               . ' (' . esc_html( number_format_i18n($top_wins) ) . ')';
          } else {
            echo '<span class="value-placeholder"><em>(Aún no disponible)</em></span>';
          }
        ?>
      </span>
    </li>
  </ul>
</div>