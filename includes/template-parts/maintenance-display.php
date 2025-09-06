<?php
/**
 * Plantilla de contenido para Modo Mantenimiento
 * Muestra un aviso claro y NO imprime ningún dato del plugin.
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

/* Encola el CSS del modo mantenimiento */
if (!wp_style_is('futbolin-maintenance', 'enqueued')) {
  $css_url = plugins_url('assets/css/26-maintenance.css', dirname(__DIR__, 2) . '/ranking-futbolin.php');
  // (centralizado) wp_enqueue_style('futbolin-maintenance', $css_url, [], '1.0');
}
?>
<section class="futbolin-maintenance-block">
  <div class="futbolin-card futbolin-maint-card-front">
    <div class="maint-emoji">🛠️</div>
    <h2>Estamos en mantenimiento</h2>
    <p>Volvemos lo antes posible.</p>
  </div>
</section>