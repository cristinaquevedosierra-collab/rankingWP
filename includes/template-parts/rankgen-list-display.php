<?php
if (!defined('ABSPATH')) exit;
// Variables esperadas cuando se incluye desde el shortcode a través del wrapper:
// - $rankgen_title (string)
// - $rankgen_columns (array)
// - $rankgen_rows (array)

$columns = is_array($rankgen_columns) ? $rankgen_columns : array();
$rows    = is_array($rankgen_rows) ? $rankgen_rows : array();
// Parámetros con prefijo para evitar conflicto con WordPress: rgp (page), rgsz (page_size), rgq (query), rgob (order_by), rgod (order_dir)
$page = isset($_GET['rgp']) ? max(1, intval($_GET['rgp'])) : (isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1);
$page_size = isset($_GET['rgsz']) ? intval($_GET['rgsz']) : (isset($_GET['page_size']) ? intval($_GET['page_size']) : 0);
$q = isset($_GET['rgq']) ? (string) wp_unslash($_GET['rgq']) : (isset($_GET['q']) ? (string) wp_unslash($_GET['q']) : '');
$order_by = isset($_GET['rgob']) ? sanitize_key($_GET['rgob']) : (isset($_GET['order_by']) ? sanitize_key($_GET['order_by']) : '');
$order_dir_src = isset($_GET['rgod']) ? $_GET['rgod'] : (isset($_GET['order_dir']) ? $_GET['order_dir'] : '');
$order_dir = strtolower((string)$order_dir_src) === 'asc' ? 'asc' : 'desc';
// Columnas ordenables (evitar confusión con posicion_estatica)
$sortable_cols = array_values(array_intersect($columns, array(
  'nombre','partidas_jugadas','partidas_ganadas','win_rate_partidos','competiciones_jugadas','competiciones_ganadas','win_rate_competiciones'
)));
if (function_exists('get_option')) {
  $slug = isset($_GET['slug']) ? sanitize_title($_GET['slug']) : '';
  $sets = get_option('futb_rankgen_sets', array());
  if (!isset($sets[$slug])) { $sets = get_option('futb_rankgen_drafts', array()); }
  $user_specified_ps = isset($_GET['rgsz']) || isset($_GET['page_size']);
  if (!$user_specified_ps && isset($sets[$slug]['top_n'])) {
    $ps = intval($sets[$slug]['top_n']); if ($ps > 0) { $page_size = $ps; }
  }
}
// Permitir page_size=0 (Todos). Solo forzar defecto si es negativo.
if ($page_size < 0) { $page_size = 25; }
// Si no hay page_size en query ni top_n definido, usar 25 por defecto
if ($page_size === 0 && !isset($_GET['page_size'])) { $page_size = 25; }
// Filtrado SSR por texto libre (en todas las columnas visibles)
$filtered = $rows;
if (is_array($rows) && $q !== '') {
  $qnorm = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
  $filtered = array_values(array_filter($rows, function($row) use ($columns, $qnorm){
    $hay_parts = [];
    foreach ($columns as $c) {
      if (isset($row[$c])) $hay_parts[] = (string)$row[$c];
    }
    // Añadir alias alternativos de nombre si existen
    if (isset($row['nombreJugador'])) $hay_parts[] = (string)$row['nombreJugador'];
    if (isset($row['player']) && is_array($row['player']) && isset($row['player']['name'])) $hay_parts[] = (string)$row['player']['name'];
    $hay = implode(' ', $hay_parts);
    $hay = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
    return $qnorm === '' || strpos($hay, $qnorm) !== false;
  }));
}
$hasAnyRows = is_array($rows) ? count($rows) > 0 : false;
// Ordenar si corresponde
if (is_array($filtered) && $order_by && in_array($order_by, $sortable_cols, true)) {
  usort($filtered, function($a, $b) use ($order_by, $order_dir) {
    $va = isset($a[$order_by]) ? $a[$order_by] : null;
    $vb = isset($b[$order_by]) ? $b[$order_by] : null;
    // Tratar numéricos si ambos son numéricos
    $na = is_numeric(str_replace([',','%'], ['.',''], (string)$va));
    $nb = is_numeric(str_replace([',','%'], ['.',''], (string)$vb));
    if ($na && $nb) {
      $fa = (float) str_replace([',','%'], ['.',''], (string)$va);
      $fb = (float) str_replace([',','%'], ['.',''], (string)$vb);
      $cmp = $fa <=> $fb;
    } else {
      // Comparación de texto con sensibilidad española (ignora acentos)
      $sa = is_string($va) ? $va : (string)$va;
      $sb = is_string($vb) ? $vb : (string)$vb;
      if (class_exists('Collator')) {
        $coll = new Collator('es_ES');
        // SECONDARY: ignora acentos/mayúsculas. Si no, fallback simple.
        if (method_exists($coll, 'setStrength')) { $coll->setStrength(Collator::SECONDARY); }
        $cmp = $coll->compare($sa, $sb);
      } else {
        // Fallback: eliminar diacríticos básico
        $norm = function($s){
          $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE', (string)$s);
          $s = strtolower((string)$s);
          return (string)$s;
        };
        $cmp = strcmp($norm($sa), $norm($sb));
      }
    }
    return $order_dir === 'asc' ? $cmp : -$cmp;
  });
}
$total_rows = is_array($filtered) ? count($filtered) : 0;
if ($page_size === 0) {
  $total_pages = 1; $page = 1; $offset = 0; $slice = $filtered;
} else {
  $total_pages = $page_size > 0 ? max(1, (int)ceil($total_rows / $page_size)) : 1;
  $page = min($page, $total_pages);
  $offset = ($page - 1) * $page_size;
  $slice = array_slice($filtered, $offset, $page_size);
}

// Mapa de cabeceras amigables
$headers = array(
  'posicion_estatica'      => __('Posición','futbolin'),
  'nombre'                 => __('Jugador','futbolin'),
  'partidas_jugadas'       => __('Partidas','futbolin'),
  'partidas_ganadas'       => __('Ganadas','futbolin'),
  'win_rate_partidos'      => __('% Ganados (partidos)','futbolin'),
  'competiciones_jugadas'  => __('Comp. jugadas','futbolin'),
  'competiciones_ganadas'  => __('Comp. ganadas','futbolin'),
  'win_rate_competiciones' => __('% Comp.','futbolin'),
);
$numeric_cols = array('posicion_estatica','partidas_jugadas','partidas_ganadas','win_rate_partidos','competiciones_jugadas','competiciones_ganadas','win_rate_competiciones');
// Formateo de valores para salida humana (ES)
function rf_rankgen_format_value($col, $val){
  if ($val === null || $val === '') return '';
  // Porcentajes
  if ($col === 'win_rate_partidos' || $col === 'win_rate_competiciones') {
    $n = (float) str_replace([',','%'], ['.',''], (string)$val);
    // Si parece proporción (0..1), escalar a %
    if ($n > 0 && $n <= 1) $n = $n * 100.0;
    return number_format($n, 1, ',', '.') . '%';
  }
  // Enteros
  if (in_array($col, ['posicion_estatica','partidas_jugadas','partidas_ganadas','competiciones_jugadas','competiciones_ganadas'], true)) {
    $n = (int) (is_numeric($val) ? $val : preg_replace('/[^0-9-]/','',$val));
    return number_format($n, 0, ',', '.');
  }
  // Default: cadena tal cual
  return (string)$val;
}
?>
<?php $slug = isset($slug) && $slug ? $slug : (isset($_GET['slug']) ? sanitize_title($_GET['slug']) : ''); ?>
<?php $is_shadow = !empty($rf_shadow_mode); ?>
<div class="<?php echo $is_shadow ? 'futbolin-hall-of-fame-wrapper' : 'rf-rankgen-wrapper'; ?> rf-rankgen-list" id="rf-rankgen-<?php echo esc_attr($slug); ?>" data-slug="<?php echo esc_attr($slug); ?>" data-rankgen="1">
  <h2><?php echo esc_html($rankgen_title); ?></h2>
  <?php if (!empty($rankgen_description)): ?>
    <div class="ranking-explainer"><?php echo $rankgen_description; ?></div>
  <?php endif; ?>
  <?php if ($is_shadow): ?>
    <div class="ranking-top-bar">
      <form class="futbolin-search-form" method="get">
        <div class="search-wrapper">
          <?php foreach ($_GET as $k=>$v){ if (in_array($k,['q','rgq','page','rgp'],true)) continue; echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr(is_array($v)?reset($v):$v).'" />'; } ?>
          <input type="text" name="rgq" class="futbolin-live-filter" placeholder="<?php echo esc_attr__('Escribe para filtrar…','futbolin'); ?>" value="<?php echo esc_attr($q); ?>" autocomplete="off" />
        </div>
      </form>
      <div class="page-size-form">
        <?php
          $curr = ($page_size === 0) ? 0 : max(1, (int)$page_size);
          $sizes = [25=>'25', 50=>'50', 100=>'100', 0=>'Todos'];
          foreach ($sizes as $val => $label):
            $isActive = ((int)$curr === (int)$val);
            $url = esc_url( add_query_arg( ['rgsz'=>$val, 'rgp'=>1, 'page'=>false, 'page_size'=>false] ) );
        ?>
          <a class="button<?php echo $isActive ? ' active' : ''; ?>" href="<?php echo $url; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <form method="get" class="rf-filter-form" style="margin:8px 0; display:flex; gap:8px; align-items:center;">
      <?php foreach ($_GET as $k=>$v){ if (in_array($k,['q','rgq'],true)) continue; echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr(is_array($v)?reset($v):$v).'" />'; } ?>
      <label style="display:flex; gap:6px; align-items:center;">
        <span><?php echo esc_html__('Filtro','futbolin'); ?>:</span>
        <input type="text" name="rgq" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Buscar en la tabla…','futbolin'); ?>" />
      </label>
      <button type="submit" class="button"><?php echo esc_html__('Aplicar','futbolin'); ?></button>
      <?php if ($q !== ''): ?>
        <a href="<?php echo esc_url(remove_query_arg(array('q','rgq','page','rgp'))); ?>" class="button button-secondary" role="button"><?php echo esc_html__('Limpiar','futbolin'); ?></a>
      <?php endif; ?>
    </form>
  <?php endif; ?>
  <div class="<?php echo $is_shadow ? 'hall-of-fame-table-container' : 'rf-rankgen-table-container'; ?>" style="<?php echo $is_shadow ? 'overflow-x: visible;' : 'overflow-x:auto;'; ?>">
    <?php if (!$is_shadow): ?>
      <style>
        /* Estilos mínimos sólo fuera de Shadow; dentro del Shadow se aplican los habituales */
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-header,
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-row { display:grid; grid-template-columns: repeat(<?php echo (int)max(1,count($columns)); ?>, minmax(80px, 1fr)); gap:0; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-th, 
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-cell { padding:8px 10px; border-bottom:1px solid rgba(0,0,0,0.08); }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-header { font-weight:600; background:rgba(0,0,0,0.03); position:sticky; top:0; z-index:2; box-shadow:0 1px 0 rgba(0,0,0,0.08); }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-th span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-num { text-align: right; font-variant-numeric: tabular-nums; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-rows .rf-rg-row:nth-child(odd) { background: rgba(0,0,0,0.015); }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-rg-rows .rf-rg-row:hover { background: rgba(30,144,255,0.08); }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-sort-link { color: inherit; text-decoration: none; display:flex; align-items:center; gap:6px; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-sort-ind { font-size: 12px; opacity: 0.7; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-name { font-weight: 600; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-medal { margin-right:6px; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-medal-1 { filter: drop-shadow(0 0 2px rgba(255,215,0,0.6)); }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-medal-2 { filter: drop-shadow(0 0 2px rgba(192,192,192,0.6)); }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-medal-3 { filter: drop-shadow(0 0 2px rgba(205,127,50,0.6)); }
      </style>
    <?php else: ?>
      <style>
        /* Ajustes mínimos válidos también en Shadow para medallas y negrita del nombre */
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-name { font-weight: 600; }
        #rf-rankgen-<?php echo esc_attr($slug); ?> .rf-medal { margin-right:6px; }
        /* Quitar subrayado y color enlaces de cabecera dentro de Shadow */
        #rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-header a { text-decoration: none; color: inherit; }
      </style>
    <?php endif; ?>
    <?php
      // Construcción de enlaces de orden en cabecera
      $base_params = $_GET;
      unset($base_params['page'], $base_params['rgp']); // cambiar orden resetea página
      $mk_sort = function($col) use ($base_params, $order_by, $order_dir, $sortable_cols){
        if (!in_array($col, $sortable_cols, true)) return null;
        $next_dir = ($order_by === $col && $order_dir === 'asc') ? 'desc' : 'asc';
        $params = array_merge($base_params, ['rgob' => $col, 'rgod' => $next_dir, 'order_by'=>false, 'order_dir'=>false]);
        $url = esc_url( add_query_arg( $params, remove_query_arg([]) ) );
        $ind = '';
        if ($order_by === $col) { $ind = $order_dir === 'asc' ? '▲' : '▼'; }
        return ['url' => $url, 'ind' => $ind, 'dir' => $next_dir];
      };
    ?>
    <?php if ($total_pages > 1 && $is_shadow): ?>
      <?php
        $mk_hof_pager = function($page, $total_pages){
          $base_url = remove_query_arg(array('page','rgp'));
          echo '<div id="hof-pager">';
          // Prev
          if ($page > 1) {
            echo '<a class="button prev" href="'.esc_url(add_query_arg(array('rgp'=>$page-1,'page'=>false), $base_url)).'">Anterior</a>';
          } else {
            echo '<span class="button prev disabled">Anterior</span>';
          }
          // Next
          if ($page < $total_pages) {
            echo '<a class="button next" href="'.esc_url(add_query_arg(array('rgp'=>$page+1,'page'=>false), $base_url)).'">Siguiente</a>';
          } else {
            echo '<span class="button next disabled">Siguiente</span>';
          }
          echo '</div>';
          // Números
          echo '<div id="hof-pager-numbers">';
          $win = 3; $start = max(1, $page-$win); $end = min($total_pages, $page+$win);
          if ($start > 1) {
            echo '<a class="button" href="'.esc_url(add_query_arg(array('rgp'=>1,'page'=>false), $base_url)).'">1</a>';
            if ($start > 2) echo '<span class="button disabled">…</span>';
          }
          for ($p=$start; $p<=$end; $p++) {
            if ($p === $page) echo '<span class="button active">'.$p.'</span>';
            else echo '<a class="button" href="'.esc_url(add_query_arg(array('rgp'=>$p,'page'=>false), $base_url)).'">'.$p.'</a>';
          }
          if ($end < $total_pages) {
            if ($end < $total_pages-1) echo '<span class="button disabled">…</span>';
            echo '<a class="button" href="'.esc_url(add_query_arg(array('rgp'=>$total_pages,'page'=>false), $base_url)).'">'.$total_pages.'</a>';
          }
          echo '</div>';
        };
        $mk_hof_pager($page, $total_pages);
      ?>
    <?php elseif ($total_pages > 1 && !$is_shadow): ?>
      <div class="futbolin-paginacion" role="navigation" aria-label="Paginación (superior)">
        <?php if ($page > 1): ?>
          <a class="button prev" href="<?php echo esc_url(add_query_arg(['rgp'=>$page-1,'page'=>false])); ?>">← Anterior</a>
        <?php endif; ?>
        <span class="page-indicator">Página <?php echo (int)$page; ?> de <?php echo (int)$total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
          <a class="button next" href="<?php echo esc_url(add_query_arg(['rgp'=>$page+1,'page'=>false])); ?>">Siguiente →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="<?php echo $is_shadow ? 'ranking-header' : 'rf-rg-header'; ?>" id="rf-rankgen-head-<?php echo esc_attr($slug); ?>">
      <?php if ($is_shadow): ?>
        <div class="ranking-th"><?php echo esc_html($headers['posicion_estatica']); ?></div>
        <div class="ranking-th"><?php echo esc_html($headers['nombre']); ?></div>
        <?php foreach ($columns as $c): if ($c==='posicion_estatica' || $c==='nombre') continue; $s=$mk_sort($c); $is_num=in_array($c,$numeric_cols,true) && $c!=='nombre'; $active=($order_by===$c); ?>
          <div class="ranking-th <?php echo $is_num ? 'rf-num' : ''; ?>">
            <?php if ($s): ?>
              <a class="rf-sort-link" href="<?php echo $s['url']; ?>" data-col="<?php echo esc_attr($c); ?>" data-nextdir="<?php echo esc_attr($s['dir']); ?>">
                <span class="sortable-header<?php echo $active ? (' active ' . esc_attr($order_dir)) : ''; ?>" data-sort="<?php echo esc_attr($c); ?>">
                  <?php echo esc_html(isset($headers[$c]) ? $headers[$c] : $c); ?>
                  <span class="sort-arrow"><?php echo $active ? ($order_dir==='asc'?'▲':'▼') : ''; ?></span>
                </span>
              </a>
            <?php else: ?>
              <span><?php echo esc_html(isset($headers[$c]) ? $headers[$c] : $c); ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($columns as $c): $s = $mk_sort($c); $is_num = in_array($c, $numeric_cols, true) && $c !== 'nombre'; $active = ($order_by === $c); ?>
          <div class="rf-rg-th <?php echo $is_num ? 'rf-num' : ''; ?>">
            <?php if ($s): ?>
              <a class="rf-sort-link" href="<?php echo $s['url']; ?>" data-col="<?php echo esc_attr($c); ?>" data-nextdir="<?php echo esc_attr($s['dir']); ?>">
                <span class="sortable-header<?php echo $active ? (' active ' . esc_attr($order_dir)) : ''; ?>" data-sort="<?php echo esc_attr($c); ?>">
                  <?php echo esc_html(isset($headers[$c]) ? $headers[$c] : $c); ?>
                  <span class="sort-arrow"><?php echo $active ? ($order_dir==='asc'?'▲':'▼') : ''; ?></span>
                </span>
              </a>
            <?php else: ?>
              <span><?php echo esc_html(isset($headers[$c]) ? $headers[$c] : $c); ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php if ($is_shadow): ?><div class="ranking-table-content"><?php endif; ?>
  <div class="<?php echo $is_shadow ? 'ranking-rows' : 'rf-rg-rows'; ?>" id="rf-rankgen-rows-<?php echo esc_attr($slug); ?>" data-total-all="<?php echo (int)count($rows); ?>" data-total-filtered="<?php echo (int)$total_rows; ?>">
      <?php if (!$hasAnyRows) : ?>
        <div class="rf-rg-row"><div class="rf-rg-cell"><?php echo esc_html__('Sin datos (pendiente de generar caché).','futbolin'); ?></div></div>
      <?php elseif ($total_rows === 0) : ?>
        <div class="rf-rg-row"><div class="rf-rg-cell"><?php echo esc_html__('No se encontraron resultados.','futbolin'); ?></div></div>
      <?php else: foreach ($slice as $row): ?>
        <?php if ($is_shadow): ?>
          <?php
            // Estructura compatible con HOF (alineación por grid)
            $pos = isset($row['posicion_estatica']) ? (int)$row['posicion_estatica'] : 0;
            $pos_class = ($pos>=1 && $pos<=3) ? ' pos-'.$pos : '';
            $nombre_val = isset($row['nombre']) ? (string)$row['nombre'] : '';
            if ($nombre_val === '' && isset($row['nombreJugador'])) $nombre_val = (string)$row['nombreJugador'];
            if ($nombre_val === '' && isset($row['player']) && is_array($row['player']) && isset($row['player']['name'])) $nombre_val = (string)$row['player']['name'];
            $pid = isset($row['jugador_id']) ? $row['jugador_id'] : (isset($row['id']) ? $row['id'] : null);
            $options = function_exists('get_option') ? get_option('mi_plugin_futbolin_options', []) : [];
            $profile_page_id = isset($options['player_profile_page_id']) ? (int)$options['player_profile_page_id'] : 0;
            $profile_page_url = ($profile_page_id && function_exists('get_permalink')) ? get_permalink($profile_page_id) : '';
            $href = ($pid && $profile_page_url) ? esc_url(add_query_arg(['jugador_id'=>$pid], $profile_page_url)) : '';
          ?>
          <div class="ranking-row">
            <div class="ranking-cell pos">
              <span class="badge<?php echo $pos_class; ?>"><?php echo (int)$pos; ?></span>
            </div>
            <div class="ranking-cell ranking-player-name-cell">
              <?php if ($href): ?>
                <a href="<?php echo $href; ?>"><?php echo esc_html($nombre_val); ?></a>
              <?php else: ?>
                <?php echo esc_html($nombre_val); ?>
              <?php endif; ?>
            </div>
            <?php foreach ($columns as $c): if ($c==='posicion_estatica' || $c==='nombre') continue; ?>
              <?php 
                $is_num = in_array($c, $numeric_cols, true) && $c !== 'nombre';
                $val = isset($row[$c]) ? $row[$c] : ($is_num ? 0 : '');
                $display = rf_rankgen_format_value($c, $val);
              ?>
              <div class="ranking-cell<?php echo $is_num ? ' rf-num' : ''; ?>"><?php echo esc_html($display); ?></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="rf-rg-row">
            <?php foreach ($columns as $c): $is_num = in_array($c, $numeric_cols, true) && $c !== 'nombre'; ?>
              <?php $val = isset($row[$c]) ? $row[$c] : ($is_num ? 0 : ''); $display = rf_rankgen_format_value($c, $val); ?>
              <div class="rf-rg-cell <?php echo $is_num ? 'rf-num' : ''; ?>"><?php echo esc_html($display); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; endif; ?>
    </div>
    <?php if ($is_shadow): ?></div><?php endif; ?>
    <?php if ($total_pages > 1): ?>
      <?php if ($is_shadow): ?>
        <?php $mk_hof_pager($page, $total_pages); ?>
      <?php else: ?>
        <div class="futbolin-paginacion" id="rf-rankgen-pager-<?php echo esc_attr($slug); ?>" role="navigation" aria-label="Paginación listados">
          <form method="get" class="rf-pager-form" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
            <?php
            // Mantener resto de parámetros
            foreach ($_GET as $k=>$v){ if (in_array($k,['page','page_size','rgp','rgsz'],true)) continue; echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr(is_array($v)?reset($v):$v).'" />'; }
            ?>
            <label><?php echo esc_html__('Tamaño de página','futbolin'); ?>
              <select name="rgsz">
                <?php foreach (array(10,25,50,100) as $_ps): ?>
                  <option value="<?php echo (int)$_ps; ?>" <?php selected($page_size, $_ps); ?>><?php echo (int)$_ps; ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label><?php echo esc_html__('Ir a','futbolin'); ?>
              <input type="number" name="rgp" min="1" max="<?php echo (int)$total_pages; ?>" value="<?php echo (int)$page; ?>" style="width:80px;" /> / <?php echo (int)$total_pages; ?>
            </label>
            <button type="submit" class="button">OK</button>
          </form>
          <div class="pager">
            <?php
            $base_url = remove_query_arg(array('page','rgp'));
            $mk = function($p,$label,$disabled=false,$active=false) use($base_url){
              $url = esc_url(add_query_arg(array('rgp'=>$p,'page'=>false), $base_url));
              $cls = 'pager-link';
              if ($disabled) $cls .= ' disabled';
              if ($active) $cls .= ' active';
              if ($disabled) {
                echo '<span class="'.$cls.'">'.esc_html($label).'</span>';
              } else {
                echo '<a class="'.$cls.'" href="'.$url.'">'.esc_html($label).'</a>';
              }
            };
            $mk(max(1,$page-1), '«', $page<=1);
            // ventana simple
            $win = 3; $start = max(1, $page-$win); $end = min($total_pages, $page+$win);
            for ($p=$start;$p<=$end;$p++) { $mk($p, (string)$p, false, $p===$page); }
            $mk(min($total_pages,$page+1), '»', $page>=$total_pages);
            ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

 <?php if (!$is_shadow): ?>
 <script>
(function(){
  try{
    var cont = document.getElementById('rf-rankgen-<?php echo esc_js($slug); ?>');
    if(!cont) return;
    var rows = document.getElementById('rf-rankgen-rows-<?php echo esc_js($slug); ?>');
    var pager = document.getElementById('rf-rankgen-pager-<?php echo esc_js($slug); ?>');
    var head  = document.getElementById('rf-rankgen-head-<?php echo esc_js($slug); ?>');
    var filterForm = cont.querySelector('form.rf-filter-form');
  var filterInput = filterForm ? filterForm.querySelector('input[name="rgq"]') : null;

    function replaceFromDoc(doc){
      var newRows = doc.getElementById('rf-rankgen-rows-<?php echo esc_js($slug); ?>');
      var newPager = doc.getElementById('rf-rankgen-pager-<?php echo esc_js($slug); ?>');
      var newHead = doc.getElementById('rf-rankgen-head-<?php echo esc_js($slug); ?>');
      var newFilter = doc.querySelector('#rf-rankgen-<?php echo esc_js($slug); ?> form.rf-filter-form');
      if(newRows && rows){ rows.innerHTML = newRows.innerHTML; }
      if(pager){
        if(newPager){ pager.innerHTML = newPager.innerHTML; }
        else { pager.innerHTML = ''; }
      }
      if(newHead && head){ head.innerHTML = newHead.innerHTML; }
      if(newFilter && filterForm){ filterForm.innerHTML = newFilter.innerHTML; }
      // Focus al contenedor para accesibilidad
      cont.setAttribute('tabindex','-1'); cont.focus(); cont.removeAttribute('tabindex');
      // Reenganchar referencias tras reemplazo
      head  = document.getElementById('rf-rankgen-head-<?php echo esc_js($slug); ?>');
      filterForm = cont.querySelector('form.rf-filter-form');
      filterInput = filterForm ? filterForm.querySelector('input[name="q"]') : null;
      wireFilter();
    }

    function fetchAndSwap(url, push){
      cont.classList.add('rf-loading');
      fetch(url, {credentials:'same-origin'})
        .then(function(r){ return r.text(); })
        .then(function(html){
          var parser = new DOMParser();
          var doc = parser.parseFromString(html, 'text/html');
          var newCont = doc.getElementById('rf-rankgen-<?php echo esc_js($slug); ?>');
          if(!newCont){ window.location.href = url; return; }
          replaceFromDoc(doc);
          if(push){ window.history.pushState({rfRankgen:true}, '', url); }
        })
        .catch(function(){ window.location.href = url; })
        .finally(function(){ cont.classList.remove('rf-loading'); });
    }

    cont.addEventListener('click', function(ev){
      var a = ev.target.closest('a');
      if(!a) return;
      // Sólo interceptar enlaces internos con parámetros relevantes de rankgen
      if(a.target && a.target==='_blank') return;
      if(a.classList.contains('disabled') || a.classList.contains('active')) return;
      var href = a.getAttribute('href');
      if(!href || href.indexOf('#')===0) return;
      // Detectar si el enlace manipula paginación/orden/tamaño
      if(/[?&](rgp|rgsz|rgob|rgod|page|page_size|order_by|order_dir)=/.test(href)){
        ev.preventDefault();
        // Si es sort link (tiene rf-sort-link) forzar page=1
        if(a.classList.contains('rf-sort-link')){
          try { var u = new URL(href, window.location.origin); u.searchParams.set('rgp','1'); u.searchParams.delete('page'); href = u.toString(); } catch(e) {}
        }
        fetchAndSwap(href, true);
      }
    });

    // Interceptar envío del formulario de paginación para AJAX
    var form = cont.querySelector('form.rf-pager-form');
    if(form){
      form.addEventListener('submit', function(e){
        e.preventDefault();
        var params = new URLSearchParams(new FormData(form));
        var url = window.location.pathname + '?' + params.toString();
        fetchAndSwap(url, true);
      });
    }

    function wireFilter(){
      if(!filterForm) return;
      filterForm.addEventListener('submit', function(e){
        e.preventDefault();
        var params = new URLSearchParams(new FormData(filterForm));
        // Conservar slug/view si no están (seguridad)
        if(!params.has('slug')){
          var slugEl = cont.getAttribute('data-slug');
          if(slugEl) params.set('slug', slugEl);
        }
        if(!params.has('view')){ params.set('view','rankgen'); }
        params.set('rgp','1'); params.delete('page');
        var base = window.location.pathname;
        var url = base + '?' + params.toString();
        fetchAndSwap(url, true);
      });
      if(filterInput){
        var t;
        filterInput.addEventListener('input', function(){
          clearTimeout(t);
          t = setTimeout(function(){
            filterForm.requestSubmit ? filterForm.requestSubmit() : filterForm.submit();
          }, 300);
        });
      }
    }

    wireFilter();

    window.addEventListener('popstate', function(e){
      // Solo manejar si es historia nuestra o si el contenedor existe aún
      if(!document.getElementById('rf-rankgen-<?php echo esc_js($slug); ?>')) return;
      fetchAndSwap(window.location.href, false);
    });
  } catch(err) { /* no-op: degradación elegante mantiene SSR */ }
})();
</script>
 <?php endif; ?>

<?php if ($is_shadow): ?>
<style>
/* Botones unificados RankGen */
#rf-rankgen-<?php echo esc_attr($slug); ?> a.button,
#rf-rankgen-<?php echo esc_attr($slug); ?> span.button,
#hof-pager a.button,
#hof-pager-numbers a.button,
#hof-pager-numbers span.button {
  background:#f5f7fa;
  border:1px solid #d0d7e2;
  padding:4px 10px;
  border-radius:4px;
  font-size:13px;
  line-height:1.2;
  color:#1d2d44;
  text-decoration:none;
  display:inline-flex;
  gap:4px;
  align-items:center;
  font-weight:500;
  transition:background .15s,border-color .15s,box-shadow .15s;
}
#rf-rankgen-<?php echo esc_attr($slug); ?> a.button:hover:not(.disabled),
#hof-pager a.button:hover:not(.disabled),
#hof-pager-numbers a.button:hover:not(.disabled){
  background:#e6ecf3;
  border-color:#b8c2ce;
}
#rf-rankgen-<?php echo esc_attr($slug); ?> a.button.active,
#hof-pager-numbers span.button.active{
  background:#1769ff;
  color:#fff;
  border-color:#145fe6;
  box-shadow:0 0 0 1px rgba(23,105,255,0.15),0 2px 4px -1px rgba(0,0,0,0.12);
}
#rf-rankgen-<?php echo esc_attr($slug); ?> a.button.disabled,
#hof-pager a.button.disabled,
#hof-pager span.button.disabled{ opacity:.45; cursor:default; pointer-events:none; }
#hof-pager, #hof-pager-numbers { display:flex; flex-wrap:wrap; gap:6px; margin:14px 0 10px; }
#hof-pager-numbers { margin-top:0; }
/* Separación tabla - pager inferior */
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-table-content + #hof-pager { margin-top:20px; }
/* Barra top */
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-top-bar { display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin:8px 0 14px; }
#rf-rankgen-<?php echo esc_attr($slug); ?> .page-size-form { display:flex; gap:6px; }
#rf-rankgen-<?php echo esc_attr($slug); ?> .page-size-form a.button { min-width:54px; justify-content:center; }
#rf-rankgen-<?php echo esc_attr($slug); ?> input.futbolin-live-filter { padding:6px 10px; border:1px solid #ccd3dd; border-radius:4px; font-size:14px; }
#rf-rankgen-<?php echo esc_attr($slug); ?> input.futbolin-live-filter:focus { outline:2px solid #1769ff33; outline-offset:1px; }
/* Distancia extra bajo cabecera */
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-header { margin-bottom:4px; }
/* Filas */
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-row { border-bottom:1px solid #e8edf3; }
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-row:hover { background:#f8fbff; }
/* Badges posiciones */
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-cell.pos .badge { display:inline-block; min-width:32px; text-align:center; background:#eef2f7; border-radius:4px; padding:4px 6px; font-weight:600; }
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-cell.pos .badge { display:inline-flex; min-width:40px; justify-content:center; text-align:center; background:#eef2f7; border-radius:4px; padding:4px 6px; font-weight:600; white-space:nowrap; }
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-cell.pos .badge.pos-1 { background:linear-gradient(135deg,#ffd700,#f5c400); color:#4d3b00; }
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-cell.pos .badge.pos-2 { background:linear-gradient(135deg,#d9d9d9,#c0c0c0); color:#2f2f2f; }
#rf-rankgen-<?php echo esc_attr($slug); ?> .ranking-cell.pos .badge.pos-3 { background:linear-gradient(135deg,#cd7f32,#b06824); color:#fff; }
</style>
<script>
(function(){
  try {
    var root = document.getElementById('rf-rankgen-<?php echo esc_js($slug); ?>');
    if(!root) return;
    var liveInput = root.querySelector('input.futbolin-live-filter');
    if(!liveInput) return;
    var form = liveInput.closest('form');
    function submitFilter() {
      if(!form) return;
      var params = new URLSearchParams(new FormData(form));
      if(!params.has('view')) params.set('view','rankgen');
      params.set('rgp','1'); params.delete('page');
      var url = window.location.pathname + '?' + params.toString();
      window.location.href = url; // Shadow: recarga completa (SSR) para simplificar
    }
    var t;
    liveInput.addEventListener('input', function(){
      clearTimeout(t); t=setTimeout(submitFilter, 350);
    });
    form.addEventListener('submit', function(e){ e.preventDefault(); submitFilter(); });
  } catch(e) { /* noop */ }
})();
</script>
<?php endif; ?>
