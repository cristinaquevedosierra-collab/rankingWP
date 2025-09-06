<?php
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
$page = isset($page) ? (int)$page : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
?>
<?php
/**
 * Archivo: includes/template-parts/info-display.php
 * Descripción: Orquesta las sub-secciones de Información (Datos, Técnica, Acerca).
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/* ===== Tipo de sub-sección ===== */
$info_type = isset($_GET['info_type']) ? sanitize_key($_GET['info_type']) : 'datos';

/* ===== Resolución del parcial ===== */
switch ($info_type) {
  case 'tecnica':
    $partial = 'info-tecnica-display.php';
    break;
  case 'acerca':
    $partial = 'about-display.php';
    break;
  case 'datos':
  default:
    $partial = 'general-stats-display.php';
    $info_type = 'datos';
    break;
}

/* ===== Back URL a principal ===== */
$options = get_option('mi_plugin_futbolin_options', []);
$ranking_page_id = isset($options['ranking_page_id']) ? (int)$options['ranking_page_id'] : 0;
if ($ranking_page_id > 0) {
  $ranking_permalink = get_permalink($ranking_page_id);
  $back_url = esc_url(add_query_arg(['view'=>'ranking'], $ranking_permalink));
} else {
  $back_url = function_exists('_futb_url_ranking') ? _futb_url_ranking([]) : esc_url(add_query_arg(['view'=>'ranking']));
}

/* ===== URLs de tabs (preservan el resto de la query) ===== */
$tab_urls = [
  'datos'   => _futb_build_url_view('info', ['info_type' => 'datos']),
  'tecnica' => _futb_build_url_view('info', ['info_type' => 'tecnica']),
  'acerca'  => _futb_build_url_view('info', ['info_type' => 'acerca']),
];

/* ===== Pintado de navegación y contenido ===== */
?>
<div class="futbolin-card">
  <p style="margin:0 0 12px 0;">
    <a class="futbolin-back-button" href="<?php echo $back_url; ?>">← Volver a principal</a>
  </p>


  <div class="futbolin-tab-content active">
    <?php
      $path = FUTBOLIN_API_PATH . 'includes/template-parts/' . $partial;
      if (file_exists($path)) {
        include $path;
      } else {
        echo '<div class="futbolin-card"><p>No se encontró la sección solicitada.</p></div>';
      }
    ?>
  </div>
</div>