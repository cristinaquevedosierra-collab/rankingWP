<?php
/**
 * Plantilla segura "campeones-espana-display.php"
 * NOTA: ya no se usa el wrapper ni esta plantilla desde el shortcode,
 * pero la dejamos "segura" por si alguien la incluye manualmente.
 * - No consulta get_option('futbolin_campeones_de_espana').
 * - No hace búsquedas adicionales a la API.
 * - Espera $champions_rows (array de ['id','nombre','dobles','individual','total']).
 */
if (!defined('ABSPATH')) exit;

// CSS helpers (enlaces de perfil)
@include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
@include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

$rows = is_array($champions_rows ?? null) ? $champions_rows : [];
$sum_totales = 0; foreach ($rows as $r) { $sum_totales += (int)($r['total'] ?? 0); }

// URL de perfil (si existe)
$options = get_option('mi_plugin_futbolin_options', []);
$profile_page_url = !empty($options['player_profile_page_id'])
    ? get_permalink((int)$options['player_profile_page_id'])
    : '';

?>
<div class="futbolin-card">
  <h2 class="futbolin-main-title">Campeones de España</h2>
  <p class="sub-count"><?php echo esc_html(number_format_i18n($sum_totales)); ?> títulos totales (dobles + individual)</p>

  <?php if (empty($rows)): ?>
    <p>No hay datos de campeones para mostrar ahora mismo.</p>
  <?php else: ?>
    <div class="futbolin-champions-list">
      <?php $position = 0; foreach ($rows as $r): if (($r['total'] ?? 0) <= 0) continue; $position++;
        $pos_class = 'ranking-position' . ($position <= 3 ? ' pos-' . $position : '');
        $dob_txt = !empty($r['dobles']) ? implode(', ', array_map('esc_html', $r['dobles'])) : '';
        $ind_txt = !empty($r['individual']) ? implode(', ', array_map('esc_html', $r['individual'])) : '';
        $name_html = esc_html($r['nombre']);
        if (function_exists('_futb_link_player') && $profile_page_url && (int)$r['id'] > 0) {
            $name_html = _futb_link_player(['id'=>$r['id'], 'nombre'=>$r['nombre']], $profile_page_url);
        }
      ?>
      <div class="ranking-row champion-row">
        <div class="<?php echo esc_attr($pos_class); ?>"><?php echo (int)$position; ?></div>
        <div class="ranking-player-details"><span class="champion-name"><?php echo $name_html; ?></span></div>
        <div class="champion-titles-count">
          <div class="points-pill">
            <span class="points-value"><?php echo esc_html(number_format_i18n((int)$r['total'])); ?></span>
            <span class="points-label">Títulos</span>
          </div>
        </div>
        <div class="champion-titles-breakdown">
          <?php if ($dob_txt !== ''): ?><span class="breakdown-doubles"><strong>Dobles:</strong> <?php echo $dob_txt; ?></span><?php endif; ?>
          <?php if ($ind_txt !== ''): ?><span class="breakdown-individual" style="margin-left:12px;"><strong>Individual:</strong> <?php echo $ind_txt; ?></span><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
