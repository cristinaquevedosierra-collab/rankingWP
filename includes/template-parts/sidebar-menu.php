<?php
/**
 * Archivo: includes/template-parts/sidebar-menu.php
 * Versión: compatible con flags de visualización y perfil de jugador (con helpers URL)
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
include_once FUTBOLIN_API_PATH . 'includes/template-parts/url-helpers.php';

/** ====== Carga de opciones con fallback ====== */
if (!isset($opciones) || !is_array($opciones)) {
    $opciones = get_option('mi_plugin_futbolin_options', []);
}

/** ====== Flags de visualización (ranking) ====== */
$show_tournaments     = (($opciones['show_tournaments']     ?? '') === 'on');
$show_hall_of_fame    = (($opciones['show_hall_of_fame']    ?? '') === 'on');
$show_global_stats    = (($opciones['show_global_stats'] ?? '') === 'on');
$show_finals_reports  = (($opciones['show_finals_reports']  ?? '') === 'on');
$show_champions       = (($opciones['show_champions']       ?? '') === 'on');


/** ====== Flag maestro de Perfil de Jugador ====== */
$enable_player_profile = (($opciones['enable_player_profile'] ?? '') === 'on');

/** ====== Modalidades activas (para el menú de ranking) ====== */
$modalidades_activas = isset($opciones['ranking_modalities']) && is_array($opciones['ranking_modalities'])
    ? array_map('intval', $opciones['ranking_modalities'])
    : [];

/** ====== Slugs unificados (mantener en sync con el router) ====== */
$SLUGS = [
  'ranking' => 'ranking',
  'tournaments' => 'tournaments',
  'tournament_stats' => 'tournament-stats',
  'halloffame' => 'hall-of-fame',
  'finals' => 'finals_reports',
  'champions' => 'champions',
  'info' => 'info',
  'global_stats' => 'global-stats',
];

/** ====== Estado de vista actual ====== */
$current_view = isset($current_view) ? $current_view : sanitize_key($_GET['view'] ?? 'ranking');

/** ====== Modalidad activa actual (detecta ambas claves) ====== */
$curr_mod_id = isset($modalidad_id) ? (int)$modalidad_id : (int)($_GET['modalidad_id'] ?? $_GET['modalidad'] ?? 0);

/** ====== Buscador: término actual (si existe) ====== */
$busqueda = isset($busqueda) ? (string)$busqueda : sanitize_text_field( isset($_GET['jugador_busqueda']) ? wp_unslash($_GET['jugador_busqueda']) : '' );
?>

<?php if (!empty($enable_player_profile)) : ?>
<div class="futbolin-sidebar-block">
  <h3>Jugadores</h3>
  <form action="" method="get" class="futbolin-search-form">
    <div class="search-wrapper">
      <input
        type="text"
        name="jugador_busqueda"
        class="futbolin-live-search"
        placeholder="Buscar Jugador"
        value="<?php echo esc_attr($busqueda); ?>"
        autocomplete="off">
      <div class="search-results-dropdown"></div>
    </div>
    <button type="submit">Buscar</button>
  </form>
</div>
<?php else: ?>
<div class="futbolin-sidebar-block">
  <h3>Jugadores</h3>
  <p class="futbolin-inline-notice">El perfil de jugadores está en mantenimiento, por eso no puedes ver el cuadro de búsqueda; volveremos lo antes posible. ¡Gracias por tu paciencia!</p>
</div>
<?php endif; ?>

<div class="futbolin-sidebar-block">
  <h3>Navegación</h3>
  <ul class="futbolin-sidebar-nav">

    <!-- ===== Ranking ===== -->
    <?php
      $is_ranking_active = ($current_view === $SLUGS['ranking'] || $current_view === '' || $current_view === null);
    ?>
    <li class="has-submenu open">
      <span class="sidebar-title <?php echo $is_ranking_active ? 'active' : ''; ?>">Ranking</span>
      <ul class="submenu">
           <?php
          // Orden preferido por nombre visible
          $orden_deseado = ['Dobles','Individual','Mujeres Dobles','Mujeres Individual','Mixto','Senior Dobles','Senior Individual','Junior Dobles','Junior Individual'];

          // -- F A L L B A C K --
          // Si $modalidades no está definido/cargado por el wrapper, lo traemos ahora de la API
          if (empty($modalidades) || !is_array($modalidades)) {
            if (class_exists('Futbolin_API_Client')) {
              try {
                $api_tmp = new Futbolin_API_Client();
                $modalidades = $api_tmp->get_modalidades();
                if (is_wp_error($modalidades) || !is_array($modalidades)) {
                  $modalidades = [];
                }
              } catch (Exception $e) {
                $modalidades = [];
              }
            } else {
              $modalidades = [];
            }
          }

          // Normaliza listado de modalidades de la API por descripción
          $modalidades_api = [];
          if (!empty($modalidades) && is_array($modalidades)) {
            foreach ($modalidades as $m) {
              $desc = isset($m->descripcion) ? (string)$m->descripcion : '';
              if ($desc !== '') $modalidades_api[$desc] = $m;
            }
          }

          foreach ($orden_deseado as $nombre_modalidad) {
            if (!isset($modalidades_api[$nombre_modalidad])) continue;

            $mod_obj = $modalidades_api[$nombre_modalidad];
            $mod_id  = isset($mod_obj->modalidadId) ? (int)$mod_obj->modalidadId : 0;
            if ($mod_id <= 0) continue;

            // Respetar modalidades activas de opciones
            if (!empty($modalidades_activas) && !in_array($mod_id, $modalidades_activas, true)) continue;

            // Asegura que $is_ranking_active exista (por si tu wrapper no lo define en esta vista)
            if (!isset($is_ranking_active)) {
              $is_ranking_active = ($current_view === ($SLUGS['ranking'] ?? 'ranking') || $current_view === '' || $current_view === null);
            }

            $is_active_mod = ($is_ranking_active && $curr_mod_id === $mod_id);
            $link_url      = _futb_build_url_view($SLUGS['ranking'], ['modalidad' => $mod_id]);

            echo '<li class="'.($is_active_mod ? 'active-submenu-item' : '').'"><a href="'.
                  esc_url($link_url).'">'.esc_html($mod_obj->descripcion).'</a></li>';
          }
        ?>

      </ul>
    </li>

    <!-- ===== Estadísticas ===== -->
    <?php
      $is_stats_active = in_array($current_view, [
        $SLUGS['tournaments'],
        $SLUGS['tournament_stats'],
        $SLUGS['halloffame'],
        $SLUGS['finals'],
        $SLUGS['champions'],
      ], true);
    ?>
    <li class="has-submenu open">
      <span class="sidebar-title <?php echo $is_stats_active ? 'active' : ''; ?>">Estadísticas</span>
      <ul class="submenu">
        <?php
          if ($show_champions) {
            $is_active = ($current_view === $SLUGS['champions']);
            $link_url  = _futb_build_url_view($SLUGS['champions'], []);
            echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">Campeones de España</a></li>';
          }

          if ($show_tournaments) {
            $is_active = in_array($current_view, [$SLUGS['tournaments'], $SLUGS['tournament_stats']], true);
            $link_url  = _futb_build_url_view($SLUGS['tournaments'], []);
            echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">Torneos</a></li>';
          }

          if ($show_hall_of_fame) {
            $is_active = ($current_view === $SLUGS['halloffame']);
            $link_url  = _futb_build_url_view($SLUGS['halloffame'], []);
            echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">Hall of Fame</a></li>';
          }

          // Estadísticas globales hasta tener toggle propio)
if ($show_hall_of_fame) {
  $is_active = ($current_view === $SLUGS['global_stats']);
  $link_url  = _futb_build_url_view($SLUGS['global_stats'], []);
  echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">Estadísticas globales</a></li>';
}

if ($show_finals_reports) {
            $is_active = ($current_view === $SLUGS['finals']);
            $link_url  = _futb_build_url_view($SLUGS['finals'], []);
            echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">Informes</a></li>';
          }
        ?>
      </ul>
    </li>

    <!-- ===== Información ===== -->
    <li class="has-submenu open">
      <span class="sidebar-title <?php echo ($current_view === $SLUGS['info']) ? 'active' : ''; ?>">Información</span>
      <ul class="submenu">
        <?php
          $info_tab = isset($_GET['info_type']) ? sanitize_key($_GET['info_type']) : 'datos';

          $tabs = [
            'datos'   => 'Datos Globales',
            'tecnica' => 'Información Técnica',
            'acerca'  => 'Acerca de',
          ];

          foreach ($tabs as $key => $label) {
            $is_active = ($current_view === $SLUGS['info'] && $info_tab === $key);
            $link_url  = _futb_build_url_view($SLUGS['info'], ['info_type' => $key]);
            echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.
                  esc_url($link_url).'">'.esc_html($label).'</a></li>';
          }
        ?>
      </ul>
    </li>

  </ul>
</div>
