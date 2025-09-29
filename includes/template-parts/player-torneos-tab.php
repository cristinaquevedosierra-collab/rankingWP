<?php

/**
 * Pestana "Torneos" — sin export CSV, sin JS/CSS inline.
 * Fuente: SOLO posiciones finales por torneo (ALL). No partidos. Sin paginacion.
 */

// NO redefinas esc_html(): WordPress ya la trae.
// Si repites helpers en varios templates, protegelos:
if (!function_exists('_tv')) {
  function _tv($a,$path,$d=null){
    $t=$a; foreach((array)$path as $k){
      if (is_array($t) && array_key_exists($k,$t)) { $t=$t[$k]; continue; }
      if (is_object($t) && isset($t->$k)) { $t=$t->$k; continue; }
      return $d;
    } return $t;
  }
}
if (!function_exists('_tnorm')) {
  function _tnorm($s){ $s=trim((string)$s); if($s==='')return $s; return str_ireplace('Amater','Amateur',$s); }
}
if (!function_exists('_tdate')) {
  function _tdate($v){
    if ($v===null || $v==='') return '—';
    if (is_numeric($v)) { $n=(int)$v; if($n>1e12)$n=(int)($n/1000); return $n>0?date('d/m/Y',$n):'—'; }
    if (preg_match('#/Date\((\d+)\)/#',$v,$m)) return date('d/m/Y', ((int)$m[1])/1000);
    $ts=strtotime($v); return ($ts && $ts>0)?date('d/m/Y',$ts):'—';
  }
}

/** Dataset desde wrapper/shortcode **/
$jugador_id = isset($context['jugador_id']) ? (int)$context['jugador_id'] : (isset($jugador_id) ? (int)$jugador_id : 0);
$posiciones = array();
if (isset($context['player_positions']) && is_array($context['player_positions'])) { $posiciones=$context['player_positions']; }
elseif (isset($player_positions) && is_array($player_positions)) { $posiciones=$player_positions; }

// Unwrap wrappers (items | data.items | result.items | stdClass numerado)
if (is_object($posiciones)) {
  if (isset($posiciones->items) && is_array($posiciones->items)) { $posiciones = $posiciones->items; }
  elseif (isset($posiciones->data) && is_object($posiciones->data) && isset($posiciones->data->items) && is_array($posiciones->data->items)) { $posiciones = $posiciones->data->items; }
  elseif (isset($posiciones->result) && is_object($posiciones->result) && isset($posiciones->result->items) && is_array($posiciones->result->items)) { $posiciones = $posiciones->result->items; }
  else { $arr = (array)$posiciones; if (isset($arr[0]) || isset($arr[1])) { $posiciones = array_values($arr); } else { $posiciones = array(); } }
}
if (!is_array($posiciones)) $posiciones = array();



$torneos_search_term = isset($_GET['torneos_filter']) ? sanitize_text_field(wp_unslash($_GET['torneos_filter'])) : '';

if (empty($posiciones)) {
  echo '<div class="futbolin-card"><h3 class="history-main-title">Historial de torneos detallados</h3><p class="note">Sin datos de posiciones finales para este jugador.</p></div>';
  return;
}

/** Normalizar filas **/
$rows = array();
foreach ($posiciones as $it) {
  $torneoNombre = _tv($it,['torneoNombre']); if(!$torneoNombre)$torneoNombre=_tv($it,['torneo','nombre']); if(!$torneoNombre)$torneoNombre=_tv($it,['nombreTorneo']);
  $torneoId     = _tv($it,['torneoId']); if(!$torneoId)$torneoId=_tv($it,['torneo','id']);
  $fecha        = _tv($it,['fecha']); if(!$fecha)$fecha=_tv($it,['torneoFecha']); if(!$fecha)$fecha=_tv($it,['fechaTorneo']); if(!$fecha)$fecha=_tv($it,['fechaInicio']); if(!$fecha)$fecha=_tv($it,['torneo','fecha']); if(!$fecha)$fecha=_tv($it,['startDate']); if(!$fecha)$fecha=_tv($it,['fechaCelebracion']);
  $tipo         = _tv($it,['tipoCompeticion']); if(!$tipo)$tipo=_tv($it,['tipo']); if(!$tipo)$tipo=_tv($it,['tipoCompeticionNombre']);
  $mod          = _tv($it,['modalidad']); if(!$mod)$mod=_tv($it,['modalidadNombre']); if(!$mod)$mod=_tv($it,['categoria','modalidad']);
  $pos          = _tv($it,['posicionFinal']); if($pos===null)$pos=_tv($it,['posicion']); if($pos===null)$pos=_tv($it,['puesto']);

  // companero
  $companero = '—';
  $js = _tv($it,['equipoDTO','jugadores'],array());
  if (is_array($js) && count($js)>0) {
    $n=[]; foreach($js as $j){ $jid=_tv($j,['id'],0); $jn=_tv($j,['nombre'],null); if($jugador_id && $jid==$jugador_id) continue; if($jn) $n[]=$jn; }
    if (count($n)>0) $companero = implode(' / ',$n);
  }
  if ($companero==='—') { foreach(['companero','pareja','dupla','partner','equipo'] as $k){ $v=_tv($it,[$k]); if($v){ $companero=str_replace(' - ',' / ',$v); break; } } }

  $rows[] = array(
    'torneoId'  => $torneoId,
    'torneo'    => $torneoNombre ?: ('Torneo #'.$torneoId),
    'fecha'     => $fecha,
    'tipo'      => _tnorm($tipo) ?: '—',
    'modalidad' => _tnorm($mod) ?: '—',
    'posicion'  => ($pos!==null ? $pos : '—'),
    'companero' => $companero,
  );
}

/** Agregado por (tipo, modalidad, posicion) **/
$agg = array();
foreach ($rows as $r) {
  $key = strtolower($r['tipo']).'|'.strtolower($r['modalidad']).'|'.strtolower((string)$r['posicion']);
  if (!isset($agg[$key])) {
    $agg[$key] = array(
      'tipo'      => $r['tipo'],
      'modalidad' => $r['modalidad'],
      'pos'       => $r['posicion'],
      'total'     => 0,
    );
  }
  $agg[$key]['total']++;
}

/** Orden especifico solicitado **/
$priority = array(
  'open dobles','open individual',
  'espana dobles','espana individual',
  'pro dobles','mixto',
  'mujeres dobles','mujeres individual',
  'junior dobles','junior individual',
  'senior dobles','senior individual',
  'master dobles','amateur dobles','rookie dobles'
);
$index = array(); $i=0; foreach(array_unique($priority) as $k){ $index[$k]=$i++; }

/** Canonicaliza tipo+modalidad a una etiqueta del orden anterior */
$canon = function($tipo,$mod) {
  $rep = array('a'=>'a','e'=>'e','i'=>'i','o'=>'o','u'=>'u','n'=>'n');
  $t = strtr(strtolower((string)$tipo), $rep);
  $m = strtr(strtolower((string)$mod), $rep);
  if (strpos($t,'campeonato')!==false && (strpos($t,'espana')!==false || strpos($t,'espan')!==false)) $t = 'espana';
  elseif (strpos($t,'open')!==false) $t = 'open';
  elseif (strpos($t,'pro')!==false) $t = 'pro';
  elseif (strpos($t,'mixto')!==false) $t = 'mixto';
  elseif (strpos($t,'mujer')!==false || strpos($t,'women')!==false) $t = 'mujeres';
  elseif (strpos($t,'junior')!==false || strpos($t,'sub')!==false) $t = 'junior';
  elseif (strpos($t,'senior')!==false) $t = 'senior';
  elseif (strpos($t,'master')!==false) $t = 'master';
  elseif (strpos($t,'amateur')!==false || strpos($t,'amater')!==false) $t = 'amateur';
  elseif (strpos($t,'rookie')!==false || strpos($t,'novato')!==false) $t = 'rookie';
  if (strpos($m,'dobl')!==false || strpos($m,'doubles')!==false) $m = 'dobles';
  elseif (strpos($m,'indiv')!==false || strpos($m,'single')!==false) $m = 'individual';
  return trim($t.' '.$m);
};

$cmp = function($a,$b) use ($index, $canon) {
  $ka = $canon($a['tipo'],$a['modalidad']);
  $kb = $canon($b['tipo'],$b['modalidad']);
  $pa = array_key_exists($ka,$index) ? $index[$ka] : 999;
  $pb = array_key_exists($kb,$index) ? $index[$kb] : 999;
  $posa = is_numeric($a['pos']) ? (int)$a['pos'] : 999;
  $posb = is_numeric($b['pos']) ? (int)$b['pos'] : 999;
  if ($pa !== $pb) return ($pa < $pb) ? -1 : 1;
  if ($ka !== $kb) return strcmp($ka,$kb);
  return $posa <=> $posb;
};
uasort($agg, $cmp);

$distribution_groups = array();
foreach ($agg as $row) {
  $label = ($row['tipo'] !== '') ? $row['tipo'] : 'Otros';
  if (!isset($distribution_groups[$label])) {
    $distribution_groups[$label] = array();
  }
  $distribution_groups[$label][] = $row;
}

/** Preparar participaciones agrupadas por torneo **/
usort($rows, function($a,$b){
  $ta = strtotime($a['fecha'] ?? '') ?: 0;
  $tb = strtotime($b['fecha'] ?? '') ?: 0;
  if ($ta === $tb) {
    return strcasecmp((string)$a['torneo'], (string)$b['torneo']);
  }
  return $tb <=> $ta;
});

$participation_groups = array();
$participation_order = array();
foreach ($rows as $entry) {
  $group_key = $entry['torneoId'] ? 'id-'.$entry['torneoId'] : md5(strtolower($entry['torneo']).'|'.($entry['fecha'] ?? ''));
  if (!isset($participation_groups[$group_key])) {
    $participation_groups[$group_key] = array(
      'torneo' => $entry['torneo'],
      'fecha'  => $entry['fecha'],
      'items'  => array(),
    );
    $participation_order[] = $group_key;
  }
  $participation_groups[$group_key]['items'][] = $entry;
}


$summary_total_competitions = count($rows);
$summary_titles = 0;
foreach ($rows as $row_data) {
  $pos_val = isset($row_data['posicion']) ? $row_data['posicion'] : null;
  if (is_numeric($pos_val) && (int)$pos_val === 1) { $summary_titles++; }
}
$summary_losses = max(0, $summary_total_competitions - $summary_titles);
$summary_win_rate = $summary_total_competitions > 0 ? round(($summary_titles * 100) / max(1, $summary_total_competitions), 1) : 0;
$summary_unique_tournaments = count($participation_order);
?>

<div class="futbolin-card torneos-card torneos-summary-card">
  <h3 class="history-main-title">Historial de torneos detallados</h3>
  <div class="torneos-summary-wrapper">
    <p class="torneos-summary-context">Resumen general de los campeonatos disputados por el jugador. Usa el buscador para localizar una modalidad o posicion concreta dentro de la tabla de distribucion.</p>
    <div class="torneos-summary-cards" role="status" aria-live="polite">
      <div class="ts-item ts-total">
        <span>Jugados</span>
        <strong><?php echo esc_html(function_exists('number_format_i18n') ? number_format_i18n($summary_total_competitions) : number_format($summary_total_competitions)); ?></strong>
      </div>
      <div class="ts-item ts-won">
        <span>Ganados</span>
        <strong><?php echo esc_html(function_exists('number_format_i18n') ? number_format_i18n($summary_titles) : number_format($summary_titles)); ?></strong>
      </div>
      <div class="ts-item ts-lost">
        <span>Perdidos</span>
        <strong><?php echo esc_html(function_exists('number_format_i18n') ? number_format_i18n($summary_losses) : number_format($summary_losses)); ?></strong>
      </div>
      <div class="ts-item ts-rate">
        <span>% Victorias</span>
        <strong><?php echo esc_html(function_exists('number_format_i18n') ? number_format_i18n($summary_win_rate, 1) : number_format($summary_win_rate, 1)); ?>%</strong>
      </div>
    </div>
  </div>
  <div class="torneos-filter-box torneos-filter-box--distribution">
    <p class="torneos-filter-context">Filtra la tabla "Distribucion de posiciones" por tipo de campeonato, modalidad o numero de puesto.</p>
    <label for="torneos-distribution-search" class="screen-reader-text">Buscar en distribucion de posiciones</label>
    <input type="search" id="torneos-distribution-search" class="torneos-filter-input" placeholder="Ej. Open, Dobles, 1" autocomplete="off">
  </div>
  <p id="torneos-distribution-empty" class="torneos-search-empty" style="display:none;">No se encontraron coincidencias en la distribucion de posiciones.</p>
</div>
<div class="futbolin-card torneos-card torneos-distribution-card">
  <div class="futbolin-card-header"><h3>Distribucion de posiciones</h3></div>
  <div class="torneos-distribution-groups">
    <?php foreach ($distribution_groups as $tipo_label => $items): ?>
      <?php $dist_search_value = Futbolin_Normalizer::mb_lower(trim((string)$tipo_label)); ?>
      <section class="torneos-distribution-group" data-search="<?php echo esc_attr($dist_search_value); ?>">
        <header class="torneos-group-header">
          <h4 class="torneos-group-title"><?php echo esc_html($tipo_label); ?></h4>
        </header>
        <div class="torneos-table-wrapper">
          <table class="torneos-table torneos-distribution-table">
            <thead>
              <tr>
                <th>Tipo Campeonato</th>
                <th>Modalidad</th>
                <th>Posicion</th>
                <th>Total Puestos</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $row): ?>
                <tr data-search-row="<?php echo esc_attr(Futbolin_Normalizer::mb_lower(trim((string)$row['tipo'] . ' ' . (string)$row['modalidad'] . ' ' . (string)$row['pos']))); ?>">
                  <td><?php echo esc_html($row['tipo']); ?></td>
                  <td><?php echo esc_html($row['modalidad']); ?></td>
                  <td><?php echo esc_html($row['pos']); ?></td>
                  <td><?php echo intval($row['total']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<div class="torneos-filter-box torneos-filter-box--participation">
  <p class="torneos-filter-context">Filtra las participaciones por nombre de torneo para localizar un campeonato concreto.</p>
  <label for="torneos-participation-search" class="screen-reader-text">Buscar en participaciones</label>
  <input type="search" id="torneos-participation-search" class="torneos-filter-input" placeholder="Ej. Campeonato de Espana, Open" value="<?php echo esc_attr($torneos_search_term); ?>" autocomplete="off">
</div>
<p id="torneos-participation-empty" class="torneos-search-empty" style="display:none;">No se encontraron torneos que coincidan con tu busqueda.</p>
<div class="futbolin-card torneos-card torneos-participation-card">
  <div class="futbolin-card-header"><h3>Participaciones</h3></div>
  <div class="torneos-participation-groups">
    <?php foreach ($participation_order as $key):
      $group = $participation_groups[$key];
      $fecha_legible = _tdate($group['fecha']);
      $group_tokens = array();
      $group_tokens[] = Futbolin_Normalizer::mb_lower((string)$group['torneo']);
      foreach ($group['items'] as $_item_for_search) {
        $group_tokens[] = Futbolin_Normalizer::mb_lower((string)($_item_for_search['tipo'] ?? '')); 
        $group_tokens[] = Futbolin_Normalizer::mb_lower((string)($_item_for_search['modalidad'] ?? '')); 
      }
      $group_search_value = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($group_tokens))));
    ?>
      <section class="torneos-participation-group" data-torneo="<?php echo esc_attr(Futbolin_Normalizer::mb_lower((string)$group['torneo'])); ?>" data-search="<?php echo esc_attr($group_search_value); ?>">
        <header class="torneos-group-header">
          <h4 class="torneos-group-title"><?php echo esc_html($group['torneo']); ?></h4>
          <?php if ($fecha_legible !== '—'): ?>
            <span class="torneos-group-meta"><?php echo esc_html($fecha_legible); ?></span>
          <?php endif; ?>
        </header>
        <div class="torneos-table-wrapper">
          <table class="torneos-table torneos-participation-table">
            <thead>
              <tr>
                <th>Tipo Campeonato</th>
                <th>Modalidad</th>
                <th>Companero</th>
                <th>Pos.</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($group['items'] as $item): ?>
                <?php
                  $pos_value = (is_numeric($item['posicion']) ? (int)$item['posicion'] : null);
                  $pos_class = '';
                  if ($pos_value === 1) { $pos_class = 'pos-gold'; }
                  elseif ($pos_value === 2) { $pos_class = 'pos-silver'; }
                  elseif ($pos_value === 3) { $pos_class = 'pos-bronze'; }
                ?>
                <tr data-search-row="<?php echo esc_attr(Futbolin_Normalizer::mb_lower(trim((string)$item['tipo'] . ' ' . (string)$item['modalidad']))); ?>">
                  <td><?php echo esc_html($item['tipo']); ?></td>
                  <td><?php echo esc_html($item['modalidad']); ?></td>
                  <td><?php echo esc_html($item['companero']); ?></td>
                  <td class="<?php echo esc_attr($pos_class); ?>"><?php echo esc_html($item['posicion']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</div><script>
(function(){
  function normalize(str){
    return (str || '').toLowerCase();
  }

  function tokenize(str){
    var normalized = normalize(str);
    if (!normalized) { return []; }
    return normalized.trim().split(/\s+/).filter(Boolean);
  }

  var distributionInput = document.getElementById('torneos-distribution-search');
  var distributionSections = Array.prototype.slice.call(document.querySelectorAll('#tab-torneos .torneos-distribution-group'));
  var distributionEmpty = document.getElementById('torneos-distribution-empty');

  function filterDistribution(){
    if (!distributionInput) { return; }
    var termTokens = tokenize(distributionInput.value);
    var visibleRows = 0;
    distributionSections.forEach(function(section){
      var groupText = normalize(section.getAttribute('data-search')).trim();
      var groupTokens = groupText ? groupText.split(/\s+/).filter(Boolean) : [];
      var rows = Array.prototype.slice.call(section.querySelectorAll('tbody tr'));
      var sectionHasMatch = false;
      rows.forEach(function(row){
        var rowText = normalize(row.getAttribute('data-search-row')).trim();
        var rowTokens = rowText ? rowText.split(/\s+/).filter(Boolean) : [];
        var matches = !termTokens.length || termTokens.every(function(token){
          if (!token) { return true; }
          if (/^\d+$/.test(token)) {
            return rowTokens.indexOf(token) !== -1 || groupTokens.indexOf(token) !== -1;
          }
          return rowText.indexOf(token) !== -1 || groupText.indexOf(token) !== -1;
        });
        row.style.display = matches ? '' : 'none';
        if (matches){
          sectionHasMatch = true;
          visibleRows++;
        }
      });
      section.style.display = sectionHasMatch ? '' : 'none';
    });
    if (distributionEmpty){
      distributionEmpty.style.display = termTokens.length && visibleRows === 0 ? '' : 'none';
    }
  }

  if (distributionInput){
    filterDistribution();
    distributionInput.addEventListener('input', filterDistribution);
    distributionInput.addEventListener('change', filterDistribution);
  }

  var participationInput = document.getElementById('torneos-participation-search');
  var participationSections = Array.prototype.slice.call(document.querySelectorAll('#tab-torneos .torneos-participation-group'));
  var participationEmpty = document.getElementById('torneos-participation-empty');

  function filterParticipation(){
    if (!participationInput) { return; }
    var term = normalize(participationInput.value.trim());
    var visibleRows = 0;
    participationSections.forEach(function(section){
      var groupText = normalize(section.getAttribute('data-search'));
      var rows = Array.prototype.slice.call(section.querySelectorAll('tbody tr'));
      var sectionHasMatch = false;
      rows.forEach(function(row){
        var rowText = normalize(row.getAttribute('data-search-row')) + ' ' + normalize(row.textContent);
        var matches = !term || rowText.indexOf(term) !== -1 || groupText.indexOf(term) !== -1;
        row.style.display = matches ? '' : 'none';
        if (matches){
          sectionHasMatch = true;
          visibleRows++;
        }
      });
      section.style.display = sectionHasMatch ? '' : 'none';
    });
    if (participationEmpty){
      participationEmpty.style.display = term && visibleRows === 0 ? '' : 'none';
    }
  }

  if (participationInput){
    filterParticipation();
    participationInput.addEventListener('input', filterParticipation);
    participationInput.addEventListener('change', filterParticipation);
  }
})();
</script>



