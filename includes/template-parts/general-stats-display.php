<?php
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
$page = isset($page) ? (int)$page : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
?>
<?php
/**
 * Archivo: includes/template-parts/general-stats-display.php
 * Descripción: Estadísticas globales del plugin (limpio, sin globals).
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Normaliza $info_data
$info_data = (isset($info_data) && is_array($info_data)) ? $info_data : [];

// Datos desde opciones (calculados en admin)
$total_partidos    = (int) get_option('futbolin_total_matches_count', 0);
$temporadas_array  = get_option('futbolin_total_seasons_count', []);
$temporadas_array  = is_array($temporadas_array) ? $temporadas_array : [];
$total_temporadas  = count($temporadas_array);

// Si tenemos temporadas, intentamos mostrar rango años
$temporadas_nums = array_values(array_filter(array_map('intval', $temporadas_array)));
$season_range = '';
if (!empty($temporadas_nums)) {
  $minY = min($temporadas_nums);
  $maxY = max($temporadas_nums);
  if ($minY && $maxY) {
    $season_range = ' (' . esc_html($minY . '–' . $maxY) . ')';
  }
}

// Total torneos puede venir del controlador
$total_torneos = isset($info_data['total_torneos']) ? (int)$info_data['total_torneos'] : 0;
?>
<div class="futbolin-card">
  <h2 class="futbolin-main-title">Datos Globales</h2>
  <p>Estadísticas acumuladas de todos los torneos y partidas registradas en la base de datos de la FEFM.</p>
  
  <div class="futbolin-stats-list">
    <div class="ranking-row stats-row">
      <div class="stats-label-group">
        <span class="stats-icon">🏆</span>
        <strong class="stats-label">Total de Torneos Registrados</strong>
      </div>
      <div class="stats-value-group">
        <div class="points-pill">
          <span class="points-value"><?php echo esc_html(number_format_i18n($total_torneos)); ?></span>
        </div>
      </div>
    </div>

    <div class="ranking-row stats-row">
      <div class="stats-label-group">
        <span class="stats-icon">⚔️</span>
        <strong class="stats-label">Total de Partidos Contabilizados</strong>
      </div>
      <div class="stats-value-group">
        <?php if ($total_partidos > 0): ?>
          <div class="points-pill">
            <span class="points-value"><?php echo esc_html(number_format_i18n($total_partidos)); ?></span>
          </div>
        <?php else: ?>
          <span class="value-placeholder"><em>(Aún no disponible)</em></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="ranking-row stats-row">
      <div class="stats-label-group">
        <span class="stats-icon">🗓️</span>
        <strong class="stats-label">Temporadas con Datos</strong>
      </div>
      <div class="stats-value-group">
        <?php if ($total_temporadas > 0): ?>
          <div class="points-pill">
            <span class="points-value">
              <?php echo esc_html(number_format_i18n($total_temporadas)); ?>
            </span>
          </div>
          <?php if ($season_range): ?>
            <span class="stats-subtle-range"><?php echo $season_range; // ya escapado arriba ?></span>
          <?php endif; ?>
        <?php else: ?>
          <span class="value-placeholder"><em>(Aún no disponible)</em></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>