<?php
/**
 * Vista genérica de módulo deshabilitado
 * Ruta: includes/template-parts/module-disabled-display.php
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

// Texto (permite override desde quien incluye)
$title = isset($disabled_title) ? (string)$disabled_title : 'Sección deshabilitada';
$msg   = isset($disabled_msg)   ? (string)$disabled_msg   : 'Esta sección está deshabilitada temporalmente desde el panel de administración.';

// ¿Mostramos botón "Volver"? Por defecto no (el wrapper ya pinta uno),
// pero si este partial se usa suelto, puedes pasar $show_back = true.
$show_back = isset($show_back) ? (bool)$show_back : false;

// Back URL: prioridad a override explícito; si no, intentamos ranking-page o helper.
$back_url = '';
if (!empty($back_url_override)) {
  $back_url = esc_url($back_url_override);
} else {
  $opts = get_option('mi_plugin_futbolin_options', []);
  if (!empty($opts['ranking_page_id'])) {
    $ranking_permalink = get_permalink((int)$opts['ranking_page_id']);
    $back_url = esc_url(add_query_arg(['view'=>'ranking'], $ranking_permalink));
  } elseif (function_exists('_futb_url_ranking')) {
    $back_url = _futb_url_ranking([]);
  }
}
?>
<div class="futbolin-card futbolin-disabled-card"
     role="region"
     aria-labelledby="futb-disabled-title"
     style="text-align:center;padding:28px;border:2px dashed #cbd5e1;background:#f8fafc;border-radius:12px;">
  <div aria-hidden="true" style="font-size:42px;line-height:1;margin-bottom:8px;">🚧</div>
  <h3 id="futb-disabled-title" style="margin:.2rem 0;"><?php echo esc_html($title); ?></h3>
  <p style="margin:0;color:#444;"><?php echo esc_html($msg); ?></p>

  <?php if ($show_back && $back_url): ?>
    <p style="margin-top:12px;">
      <a class="futbolin-back-button" href="<?php echo esc_url($back_url); ?>">← Volver a principal</a>
    </p>
  <?php endif; ?>

  <!-- Nota: normalmente el botón "Volver" lo pinta el wrapper (ranking-wrapper.php).
       Este fallback solo aparece si $show_back = true o se usa el partial de forma aislada. -->
</div>