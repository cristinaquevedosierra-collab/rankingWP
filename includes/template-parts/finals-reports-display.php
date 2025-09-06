<?php
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
$page = isset($page) ? (int)$page : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
?>
<?php
/**
 * Archivo: includes/template-parts/finals-reports-display.php
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

$report_key = isset($_GET['report']) ? sanitize_key($_GET['report']) : 'open_individual_finals';

// Carga de datos (desde variable inyectada o desde options)
if (isset($finals_reports) && is_array($finals_reports)) {
  $report_data = $finals_reports[$report_key] ?? null;
}
if (empty($report_data)) {
  $report_data = get_option('futbolin_report_' . $report_key);
}

// Títulos visibles en el selector
$report_titles = [
  'open_individual_finals'     => 'Informe de Finales Ganadas Open Individual',
  'open_doubles_player_finals' => 'Informe de Finales Ganadas Open Dobles por Jugador',
  'open_doubles_pair_finals'   => 'Informe de Finales Ganadas Open Dobles por Pareja',
  'championships_open'         => 'Informe de Campeonatos Open Ganados',
  'championships_rookie'       => 'Informe de Campeonatos Rookie/Amater Ganados',
  'championships_resto'        => 'Informe de Campeonatos Resto Ganados',
];

// Estructura de columnas (orden visual)
$report_fields = [
  'championships_open'   => ['nombre' => 'Jugador', 'torneos_jugados' => 'Torn.', 'campeonatos_jugados' => 'Camp.', 'finales_jugadas' => 'F', 'finales_ganadas' => 'G', 'finales_perdidas' => 'P', 'resto_posiciones' => 'Rest', 'porcentaje_finales_ganadas' => '%F', 'porcentaje_campeonatos_ganados' => '%C'],
  'championships_rookie' => ['nombre' => 'Jugador', 'torneos_jugados' => 'Torn.', 'campeonatos_jugados' => 'Camp.', 'finales_jugadas' => 'F', 'finales_ganadas' => 'G', 'finales_perdidas' => 'P', 'resto_posiciones' => 'Rest', 'porcentaje_finales_ganadas' => '%F', 'porcentaje_campeonatos_ganados' => '%C'],
  'championships_resto'  => ['nombre' => 'Jugador', 'torneos_jugados' => 'Torn.', 'campeonatos_jugados' => 'Camp.', 'finales_jugadas' => 'F', 'finales_ganadas' => 'G', 'finales_perdidas' => 'P', 'resto_posiciones' => 'Rest', 'porcentaje_finales_ganadas' => '%F', 'porcentaje_campeonatos_ganados' => '%C'],
  'open_individual_finals'     => ['nombre' => 'Jugador/Pareja', 'total' => 'Finales', 'wins' => 'Ganadas', 'losses' => 'Perdidas', 'win_rate' => '% Victorias'],
  'open_doubles_player_finals' => ['nombre' => 'Jugador/Pareja', 'total' => 'Finales', 'wins' => 'Ganadas', 'losses' => 'Perdidas', 'win_rate' => '% Victorias'],
  'open_doubles_pair_finals'   => ['nombre' => 'Jugador/Pareja', 'total' => 'Finales', 'wins' => 'Ganadas', 'losses' => 'Perdidas', 'win_rate' => '% Victorias'],
];

// Flags de vista
$fields = $report_fields[$report_key] ?? [];
$is_championships_report = (strpos($report_key, 'championships') !== false);
$is_resto_top30 = ($report_key === 'championships_resto');

// Top-30 solo para RESTO por campeonatos_ganados; desempates por G y %F
if ($is_resto_top30 && is_array($report_data) && !empty($report_data)) {
  uasort($report_data, function($a,$b){
    $A = (int)($a['campeonatos_ganados'] ?? 0);
    $B = (int)($b['campeonatos_ganados'] ?? 0);
    if ($B !== $A) return $B <=> $A;
    $Ag = (int)($a['finales_ganadas'] ?? 0);
    $Bg = (int)($b['finales_ganadas'] ?? 0);
    if ($Bg !== $Ag) return $Bg <=> $Ag;
    $Ap = (float)($a['porcentaje_finales_ganadas'] ?? 0);
    $Bp = (float)($b['porcentaje_finales_ganadas'] ?? 0);
    return $Bp <=> $Ap;
  });
  $report_data = array_slice($report_data, 0, 30, true);
}

// Leyenda fija
$legend_text = $is_championships_report
  ? 'Leyenda columnas: Jugador · Torn. · Camp. · F · G · P · Rest · %F · %C.'
  : 'Leyenda columnas: Jugador/Pareja · Finales · Ganadas · Perdidas · % Victorias.';

// Nota Top-30 (solo RESTO)
$top_note = $is_resto_top30
  ? ' Mostrando únicamente el Top-30 por campeonatos ganados (desempates: G y %F).'
  : '';

// Nota de categorías incluidas (RESTO) basada en IDs configurados
$resto_note = '';
if ($is_resto_top30) {
  $catalog    = get_option('futbolin_competition_types_catalog', []);
  $resto_ids  = get_option('futbolin_group_resto_ids', []);
  $resto_names = [];
  foreach ((array)$resto_ids as $rid) {
    $rid = (int)$rid;
    if (is_array($catalog) && isset($catalog[$rid]['label'])) {
      $resto_names[] = $catalog[$rid]['label'] . ' (#' . $rid . ')';
    } else {
      $resto_names[] = 'ID ' . $rid;
    }
  }
  $resto_names = array_values(array_unique($resto_names));
  if (!empty($resto_names)) {
    $resto_note = ' Categorías incluidas: ' . implode(', ', array_map('esc_html', $resto_names)) . '.';
  }
}

/** ========= Preparación de enlaces por ID ========= */

// URL perfil si no vino del shortcode
$profile_page_url = isset($profile_page_url) ? (string)$profile_page_url : '';
if ($profile_page_url === '') {
  $opts = get_option('mi_plugin_futbolin_options', []);
  if (!empty($opts['player_profile_page_id'])) {
    $profile_page_url = get_permalink((int)$opts['player_profile_page_id']);
  }
}

// Resolver Nombre → ID (con caché simple por carga)
$name_to_id = [];
$api = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;

// Normalizar nombres para comparar
$__norm_name = function(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  if (function_exists('iconv')) {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
  }
  $s = strtolower($s);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
};

// Busca el ID para un nombre (mejor esfuerzo)
$__resolve_id = function(string $name) use (&$name_to_id, $api, $__norm_name): int {
  $key = $__norm_name($name);
  if ($key === '') return 0;
  if (isset($name_to_id[$key])) return (int)$name_to_id[$key];

  $found = 0;
  if ($api && method_exists($api, 'buscar_jugadores')) {
    $hits = $api->buscar_jugadores($name);
    if (is_array($hits)) {
      // Match exacto normalizado
      foreach ($hits as $h) {
        $hn = isset($h->nombreJugador) ? (string)$h->nombreJugador : '';
        if ($__norm_name($hn) === $key) {
          $found = isset($h->jugadorId) ? (int)$h->jugadorId : 0;
          if ($found) break;
        }
      }
      // Fallback: primer resultado con id
      if (!$found) {
        foreach ($hits as $h) {
          if (!empty($h->jugadorId)) { $found = (int)$h->jugadorId; break; }
        }
      }
    }
  }
  $name_to_id[$key] = (int)$found;
  return (int)$found;
};

// Renderiza un nombre de entidad (jugador o pareja "A / B") con enlaces por ID si es posible
$__render_entity = function(string $entity_name) use ($profile_page_url, $__resolve_id) : string {
  // Parejas separadas por "/"
  if (strpos($entity_name, '/') !== false) {
    $parts = array_map('trim', explode('/', $entity_name));
    $out = [];
    foreach ($parts as $p) {
      $pid = $__resolve_id($p);
      if ($pid && function_exists('_futb_link_player')) {
        $out[] = _futb_link_player(['id' => $pid, 'nombre' => $p], $profile_page_url);
      } else {
        $out[] = esc_html($p);
      }
    }
    return implode(' / ', $out);
  }

  // Un solo jugador
  $pid = $__resolve_id($entity_name);
  if ($pid && function_exists('_futb_link_player')) {
    return _futb_link_player(['id' => $pid, 'nombre' => $entity_name], $profile_page_url);
  }
  return esc_html($entity_name);
};
?>
<div class="futbolin-card futbolin-hall-of-fame-wrapper">
  <h2 class="futbolin-main-title"><?php echo esc_html($report_titles[$report_key] ?? 'Informe de Finales'); ?></h2>

  <p class="disclaimer">
    <em>
      Estos datos son estáticos y deben ser actualizados manualmente desde el panel de administración.
      <?php echo esc_html($top_note); ?>
      <?php echo wp_kses_post($resto_note); ?>
      <?php echo esc_html($legend_text); ?>
    </em>
  </p>

  <div class="futbolin-sidebar-block futbolin-finals-reports-menu">
    <h3>Informes</h3>
    <ul class="futbolin-sidebar-nav">
      <?php
      $base_url = remove_query_arg('report');
      foreach ($report_titles as $key => $title) {
        $is_active = ($report_key === $key);
        $link_url = add_query_arg(['view' => 'finals_reports', 'report' => $key], $base_url);
        ?>
        <li class="<?php echo $is_active ? 'active-submenu-item' : ''; ?>">
          <a href="<?php echo esc_url($link_url); ?>"><?php echo esc_html($title); ?></a>
        </li>
        <?php
      }
      ?>
    </ul>
  </div>

  <div class="finals-reports-table-container">
    <?php if (empty($report_data) || !is_array($report_data)) : ?>
      <p>No hay datos disponibles para este informe. Por favor, asegúrate de haber ejecutado el proceso de cálculo de finales desde el panel de administración.</p>
    <?php else : ?>
      <?php $table_class = ''; $row_class = ''; ?>
      <div class="finals-reports-table-header <?php echo esc_attr($table_class); ?>">
        <?php foreach ($fields as $field => $label) : ?>
          <div class="header-stat header-<?php echo esc_attr($field); ?>">
            <span><?php echo esc_html($label); ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="ranking-table-content">
        <?php $i = 0;
        foreach ($report_data as $entity_name => $stats) :
          $row_class = ($i % 2 === 0) ? 'even' : 'odd'; ?>
          <div class="ranking-row <?php echo esc_attr($row_class); ?>">
            <div class="finals-player-name-cell">
              <span><?php echo $__render_entity((string)$entity_name); // ya escapado dentro ?></span>
            </div>

            <?php if ($is_championships_report) : ?>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['torneos_jugados'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['campeonatos_jugados'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['finales_jugadas'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['finales_ganadas'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['finales_perdidas'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['resto_posiciones'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['porcentaje_finales_ganadas'] ?? 0); ?>%</span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['porcentaje_campeonatos_ganados'] ?? 0); ?>%</span></div>
            <?php else : ?>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['total'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['wins'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html($stats['losses'] ?? 0); ?></span></div>
              <div class="finals-stat-cell"><span class="stat-value"><?php echo esc_html(number_format((float)($stats['win_rate'] ?? 0), 2)); ?>%</span></div>
            <?php endif; ?>
          </div>
          <?php $i++; endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>