<?php
if (!isset($player) && isset($jugador)) { $player = $jugador; }
$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
?>
<?php
/**
 * Archivo: includes/template-parts/player-h2h-tab.php
 * Descripción: Búsqueda y comparativa Head-to-Head entre jugadores.
 */
if (!defined('ABSPATH')) exit;
// Guards/stubs para editor/CLI sin WordPress cargado
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($str){ return is_string($str)?$str:''; } }
if (!function_exists('wp_unslash')) { function wp_unslash($v){ return $v; } }
if (!function_exists('plugins_url')) { function plugins_url($path = '', $plugin = '') { return ''; } }
if (!function_exists('wp_style_is')) { function wp_style_is($handle, $status = 'registered'){ return false; } }
if (!function_exists('get_permalink')) { function get_permalink($id = null){ return ''; } }
if (!function_exists('remove_query_arg')) { function remove_query_arg($keys, $url = ''){ return $url; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url = ''){ return $url; } }
if (!function_exists('esc_url')) { function esc_url($url){ return $url; } }
if (!function_exists('esc_attr')) { function esc_attr($s){ return $s; } }
if (!function_exists('esc_html')) { function esc_html($s){ return $s; } }
if (!defined('FUTBOLIN_API_PATH')) { define('FUTBOLIN_API_PATH', dirname(__DIR__, 2) . '/'); }
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
if ( function_exists('wp_style_is') && ! wp_style_is('futbolin-h2h', 'enqueued') ) {
    $css_url = plugins_url('assets/css/15-futbolin-h2h.css', dirname(__DIR__, 2) . '/ranking-futbolin.php');
    // (centralizado) wp_enqueue_style('futbolin-h2h', $css_url, [], '1.0');
}

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

// Variables de entrada con defaults seguros (las define el shortcode si H2H está activo)
$search_term    = isset($search_term) ? (string)$search_term : '';
$search_results = isset($search_results) && is_array($search_results) ? $search_results : [];
$h2h_processor  = isset($h2h_processor) ? $h2h_processor : null;

// Preserva jugador_id en la query al enviar el formulario
$jugador_id_q = isset($_GET['jugador_id']) ? (int)$_GET['jugador_id'] : 0;

// URL base actual (limpia de params de búsqueda previos)
$current_url = '';
if (function_exists('get_permalink')) {
  $perma = (string)call_user_func('get_permalink');
  // Quitamos parámetros de búsqueda previos para que el form "refresque" limpio
  $current_url = remove_query_arg(['search_h2h', 'compare_id'], $perma);
}
?>
<div class="futbolin-card">
  <h3>Comparar Head-to-Head</h3>
  <p>Busca a otro jugador para ver una comparativa directa de enfrentamientos y estadísticas.</p>

  <div class="futbolin-card" style="margin-bottom: 30px;">
    <form action="<?php echo esc_url($current_url ?: ''); ?>" method="get" class="futbolin-search-form">
      <?php if ($jugador_id_q > 0): ?>
        <input type="hidden" name="jugador_id" value="<?php echo esc_attr($jugador_id_q); ?>">
      <?php endif; ?>
      <div class="search-wrapper">
        <input
          type="text"
          name="search_h2h"
          class="futbolin-live-search"
          placeholder="Escribe para buscar..."
          value="<?php echo esc_attr($search_term); ?>"
          autocomplete="off"
        >
        <div class="search-results-dropdown"></div>
      </div>
      <button type="submit">Buscar Jugador</button>
    </form>

    <?php if (!empty($search_results)) : ?>
      <ul class="futbolin-search-results-list">
        <?php foreach ($search_results as $player) : ?>
          <?php
          // Construye link limpio preservando jugador_id y anclando a la pestaña H2H
          $base_url_params = [];
          if ($jugador_id_q > 0) $base_url_params['jugador_id'] = $jugador_id_q;

          $base_url = $current_url ?: '';
          if ($base_url_params) {
            $base_url = add_query_arg($base_url_params, $base_url);
          }

          $cid = isset($player->jugadorId) ? (int)$player->jugadorId : 0;
          $compare_link = add_query_arg(['compare_id' => $cid], $base_url) . '#tab-h2h';

          $player_name = isset($player->nombreJugador) ? (string)$player->nombreJugador : 'Jugador';
          ?>
          <li>
            <a href="<?php echo esc_url($compare_link); ?>">
              <?php echo esc_html($player_name); ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php elseif ($search_term !== ''): ?>
      <p>No se encontraron jugadores con ese nombre.</p>
    <?php endif; ?>
  </div>

  <?php if ($h2h_processor) : ?>
    <?php
    // La plantilla h2h-results.php espera $stats
    $stats = $h2h_processor;
    $tpl = FUTBOLIN_API_PATH . 'includes/template-parts/h2h-results.php';
    if (file_exists($tpl)) {
      include $tpl;
    } else {
      echo '<p>La comparativa no está disponible en este momento.</p>';
    }
    ?>
  <?php else: ?>
    <p style="opacity:.9;">Consejo: escribe el nombre del rival en el buscador de arriba y selecciona el resultado para ver la comparativa.</p>
  <?php endif; ?>
</div>