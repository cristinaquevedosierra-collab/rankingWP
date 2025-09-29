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
$__epp = $opciones['enable_player_profile'] ?? '';
$enable_player_profile = ($__epp === 'on' || $__epp === '1' || $__epp === 1 || $__epp === true || $__epp === 'true');
$__is_admin_user = function_exists('current_user_can') && current_user_can('manage_options');

/** ====== Modalidades activas (para el menú de ranking) ====== */
$modalidades_activas = isset($opciones['ranking_modalities']) && is_array($opciones['ranking_modalities'])
    ? array_map('intval', $opciones['ranking_modalities'])
    : [];

/** ====== Slugs unificados (mantener en sync con el router) ====== */
$SLUGS = [
  'annual' => 'annual',
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

<?php if (!empty($enable_player_profile) || $__is_admin_user) : ?>
<div class="futbolin-sidebar-block">
  <h3>Perfiles de jugador</h3>
  <form action="" method="get" class="futbolin-search-form">
    <div class="search-wrapper">
      <small class="rf-search-hint rf-hint-top">* usa nombre o apellido</small>
      <input
        type="text"
        name="jugador_busqueda"
        class="futbolin-live-search"
        placeholder="Buscar Jugador"
        value="<?php echo esc_attr($busqueda); ?>"
        autocomplete="off">
      <div class="search-results-dropdown"></div>
    </div>
    
  </form>
</div>
<?php else: ?>
<div class="futbolin-sidebar-block">
  <h3>Perfiles de jugador</h3>
  <p class="futbolin-inline-notice">El perfil de jugadores está en mantenimiento, por eso no puedes ver el cuadro de búsqueda; volveremos lo antes posible. ¡Gracias por tu paciencia!</p>
</div>
<?php endif; ?>

<div class="futbolin-sidebar-block">
  <h3>Navegación</h3>
  <ul class="futbolin-sidebar-nav">

    <!-- ===== Ranking ELO (por modalidad) ===== -->
    <?php
      $is_ranking_active = ($current_view === $SLUGS['ranking'] || $current_view === '' || $current_view === null);
    ?>
    <li class="has-submenu open">
      <span class="sidebar-title <?php echo $is_ranking_active ? 'active' : ''; ?>">Ranking ELO</span>
   <ul class="submenu">
     <?php
    // Flag para detectar si se imprime alguna opción en ELO
    $elo_printed = false;
    // Si no hay modalidades activas configuradas, no mostrar opciones (solo encabezado)
  if (!empty($modalidades_activas)) {
          // Orden preferido por nombre visible
          $orden_deseado = ['Dobles','Individual','Mujeres Dobles','Mujeres Individual','Mixto','Senior Dobles','Senior Individual','Junior Dobles','Junior Individual'];
          // Normalizador de nombres (sin tildes, lower, quita espacios extra)
          if (!function_exists('rf_normaliza_nombre_modalidad')) {
            function rf_normaliza_nombre_modalidad($txt) {
              $t = strtolower((string)$txt);
              // quitar tildes básicas
              $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
              // espacios múltiples
              $t = preg_replace('/\s+/', ' ', $t);
              $t = trim($t);
              return $t;
            }
          }

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

          // Fallback mínimo si no hay modalidades: Dobles(2) e Individual(1)
          if (empty($modalidades_api)) {
            $d = (object)['modalidadId'=>2,'descripcion'=>'Dobles'];
            $i = (object)['modalidadId'=>1,'descripcion'=>'Individual'];
            $modalidades_api = ['Dobles'=>$d, 'Individual'=>$i];
          }

          // Nuevo enfoque robusto:
          // 1) Mapear ID -> objeto desde API real
          $map_por_id = [];
          foreach ($modalidades as $mapi) { if (is_object($mapi) && isset($mapi->modalidadId)) { $map_por_id[(int)$mapi->modalidadId] = $mapi; } }

          // 2) IDs activos saneados
          $ids_activos = array_values(array_unique(array_filter(array_map('intval', $modalidades_activas), fn($v)=>$v>0)));

          // 3) Tabla de nombres normalizados para matching flexible
          $norm_nombre_a_id = [];
          foreach ($map_por_id as $iid=>$obj) {
            $desc = isset($obj->descripcion)?(string)$obj->descripcion:'';
            if ($desc!=='') { $norm_nombre_a_id[ rf_normaliza_nombre_modalidad($desc) ] = $iid; }
          }

          // 4) Traducir orden_deseado (normalizado) a IDs si existen y están activos
          $ids_prefer = [];
          foreach ($orden_deseado as $nraw) {
            $norm = rf_normaliza_nombre_modalidad($nraw);
            if (isset($norm_nombre_a_id[$norm])) {
              $candidate_id = (int)$norm_nombre_a_id[$norm];
              if (in_array($candidate_id, $ids_activos, true) && !in_array($candidate_id, $ids_prefer, true)) {
                $ids_prefer[] = $candidate_id;
              }
            }
          }

          // 5) Resto de IDs activos que no entraron en preferencia
          $ids_rest = [];
          foreach ($ids_activos as $aid) { if (!in_array($aid, $ids_prefer, true)) { $ids_rest[] = $aid; } }

          // 6) Lista final (permitir filtro externo)
          $ids_final = array_merge($ids_prefer, $ids_rest);
          if (function_exists('apply_filters')) {
            $ids_final = apply_filters('futbolin_ranking_modalities_order', $ids_final, $ids_activos, $map_por_id);
            if (!is_array($ids_final)) { $ids_final = array_merge($ids_prefer, $ids_rest); }
          }

          // 6) Render
          if (!isset($is_ranking_active)) {
            $is_ranking_active = ($current_view === ($SLUGS['ranking'] ?? 'ranking') || $current_view === '' || $current_view === null);
          }
          $es_admin_rf = function_exists('current_user_can') && current_user_can('manage_options');
          foreach ($ids_final as $mod_id) {
            if (!isset($map_por_id[$mod_id])) continue;
            $mod_obj = $map_por_id[$mod_id];
            $is_active_mod = ($is_ranking_active && $curr_mod_id === $mod_id);
            $link_url      = _futb_build_url_view($SLUGS['ranking'], ['modalidad' => $mod_id]);
            $nombre_vis    = isset($mod_obj->descripcion) ? (string)$mod_obj->descripcion : ('Modalidad '.$mod_id);
            if ($es_admin_rf) {
              $nombre_vis .= ' <span class="rf-mod-id-badge" style="opacity:.6;font-size:11px;">#'.$mod_id.'</span>';
            }
            echo '<li class="'.($is_active_mod ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">'.wp_kses_post($nombre_vis).'</a></li>';
            $elo_printed = true;
          }
        } // end if modalidades_activas

        // Placeholder solo visible para administradores si no hay elementos
        if (!$elo_printed && function_exists('current_user_can') && current_user_can('manage_options')) {
          echo '<li class="submenu-empty-hint">'.esc_html__('Sin modalidades disponibles','futbolin').'</li>';
        }
        ?>

      </ul>
    </li>

    <!-- ===== Ranking anual ===== -->
    <?php
      $is_annual_active = ($current_view === ($SLUGS['annual'] ?? 'annual'));
    ?>
    <?php
      // Modalidades visibles en Ranking anual (limitado a Dobles e Individual)
      $opts_side = isset($opciones) && is_array($opciones) ? $opciones : get_option('mi_plugin_futbolin_options', []);
      $annual_modalidades = [];
      $annual_doubles_on    = (!isset($opts_side['enable_annual_doubles']) || $opts_side['enable_annual_doubles'] === 'on');
      $annual_individual_on = (!isset($opts_side['enable_annual_individual']) || $opts_side['enable_annual_individual'] === 'on');
      if ($annual_doubles_on)    { $annual_modalidades[2] = 'Dobles'; }
      if ($annual_individual_on) { $annual_modalidades[1] = 'Individual'; }
    ?>
    <li class="has-submenu open">
      <span class="sidebar-title <?php echo $is_annual_active ? 'active' : ''; ?>">Ranking anual</span>
      <ul class="submenu">
        <?php $annual_printed = false; foreach ($annual_modalidades as $mid=>$label):
            $is_active_mod = ($is_annual_active && isset($curr_mod_id) && (int)$curr_mod_id === (int)$mid);
            $link_url = _futb_build_url_view($SLUGS['annual'], ['modalidad'=>(int)$mid]);
        ?>
            <li class="<?php echo $is_active_mod ? 'active-submenu-item' : ''; ?>"><a href="<?php echo esc_url($link_url); ?>"><?php echo esc_html($label); ?></a></li>
        <?php $annual_printed = true; endforeach; ?>
        <?php if (!$annual_printed && function_exists('current_user_can') && current_user_can('manage_options')): ?>
          <li class="submenu-empty-hint"><?php echo esc_html__('Sin modalidades disponibles','futbolin'); ?></li>
        <?php endif; ?>
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
if ($show_global_stats) {
  $is_active = ($current_view === $SLUGS['global_stats']);
  $link_url  = _futb_build_url_view($SLUGS['global_stats'], []);
  echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">Estadísticas globales</a></li>';
    // Sub-secciones de Estadísticas globales (anclas) según toggles
    $__fefm_on  = (!isset($opciones['enable_fefm_no1_club']) || $opciones['enable_fefm_no1_club'] === 'on');
    $__c500_on  = (!isset($opciones['enable_club_500_played']) || $opciones['enable_club_500_played'] === 'on');
    $__c100_on  = (!isset($opciones['enable_club_100_winners']) || $opciones['enable_club_100_winners'] === 'on');
    $__rivs_on  = (!isset($opciones['enable_top_rivalries']) || $opciones['enable_top_rivalries'] === 'on');

    $__base_global_url = _futb_build_url_view($SLUGS['global_stats'], []);

    if ($__fefm_on) {
      echo '<li class=""><a href="'.esc_url($__base_global_url).'#fefm-no1-club">'.esc_html__('FEFM Nº1 CLUB','futbolin').'</a></li>';
    }
    if ($__c500_on) {
      echo '<li class=""><a href="'.esc_url($__base_global_url).'#club-500-played">'.esc_html__('Club 500 – Played','futbolin').'</a></li>';
    }
    if ($__c100_on) {
      echo '<li class=""><a href="'.esc_url($__base_global_url).'#club-100-winners">'.esc_html__('Club 100 – Winners','futbolin').'</a></li>';
    }
    if ($__rivs_on) {
      echo '<li class=""><a href="'.esc_url($__base_global_url).'#top-rivalries">'.esc_html__('Top rivalidades','futbolin').'</a></li>';
    }

}

if ($show_finals_reports) {
            $is_active = ($current_view === $SLUGS['finals']);
            $link_url  = _futb_build_url_view($SLUGS['finals'], []);
            echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">Informes</a></li>';
          }

          // === Listados del Generador (Rankgen) ===
          $rankgen_sets = get_option('futb_rankgen_sets', []);
          if (is_array($rankgen_sets) && !empty($rankgen_sets)){
            foreach ($rankgen_sets as $__slug => $__cfg){
              $key = 'enable_rankgen__' . sanitize_key($__slug);
              $enabled_in_menu = (($opciones[$key] ?? '') === 'on');
              $is_list_enabled = !empty($__cfg['is_enabled']);
              if (!$enabled_in_menu || !$is_list_enabled) continue;
              $name = isset($__cfg['name']) && $__cfg['name']!=='' ? $__cfg['name'] : $__slug;
              $link_url = _futb_build_url_view('rankgen', ['slug'=>$__slug]);
              $is_active = ($current_view === 'rankgen' && isset($_GET['slug']) && sanitize_title($_GET['slug']) === $__slug);
              echo '<li class="'.($is_active ? 'active-submenu-item' : '').'"><a href="'.esc_url($link_url).'">'.esc_html($name).'</a></li>';
            }
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
