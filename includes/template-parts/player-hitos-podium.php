<?php
/**
 * Include: player-hitos-podium.php
 * Añade tres tarjetas: Nº1 / Nº2 / Nº3 del Ranking por Temporada
 * - Mantiene layout .trophy-wall → .trophy → .trophy-year
 * - Coronas SVG (doble en Dobles), píldoras “Temporada N”
 * - Accesible: role="list"/"listitem" y aria-label con (Dobles|Individual)
 */

$hitosData = [];
if (isset($this) && is_object($this) && isset($this->hitos) && is_array($this->hitos)) {
  $hitosData = $this->hitos;
} elseif (isset($hitos) && is_array($hitos)) {
  $hitosData = $hitos;
}

// ===== Helpers =====
function _rfpl_safe_id($s){ return preg_replace('~[^a-zA-Z0-9_-]+~','', (string)$s); }




function _rfpl_crown_icon($tone='gold', $double=false, $id='cr'){
  $g = $tone === 'silver'
    ? ['#f9fbff','#dbe3ee','#b6c2cf']
    : ($tone === 'bronze' ? ['#fff4ea','#f0c9a2','#c19064'] : ['#fff9d6','#ffd372','#b8860b']);
  $stroke = $tone === 'silver' ? '#4b5563' : ($tone === 'bronze' ? '#7c4f2b' : '#7c5a00');

  $svg  = "<svg class=\"crown-ico\" viewBox=\"0 0 128 96\" aria-hidden=\"true\">";
  $svg .= "<defs>";
  $svg .= "<linearGradient id=\"cr-{$tone}-{$id}\" x1=\"0%\" y1=\"0%\" x2=\"100%\" y2=\"100%\">";
  $svg .= "<stop offset=\"0%\" stop-color=\"{$g[0]}\"></stop>";
  $svg .= "<stop offset=\"55%\" stop-color=\"{$g[1]}\"></stop>";
  $svg .= "<stop offset=\"100%\" stop-color=\"{$g[2]}\"></stop>";
  $svg .= "</linearGradient>";
  $svg .= "<linearGradient id=\"cr-hi-{$id}\" x1=\"0%\" y1=\"0%\" x2=\"0%\" y2=\"100%\">";
  $svg .= "<stop offset=\"0%\" stop-color=\"#ffffff\" stop-opacity=\"0.85\"></stop>";
  $svg .= "<stop offset=\"80%\" stop-color=\"#ffffff\" stop-opacity=\"0\"></stop>";
  $svg .= "</linearGradient>";
  $svg .= "</defs>";

  // Base band
  $svg .= "<g stroke=\"{$stroke}\" stroke-width=\"2.2\" stroke-linejoin=\"round\" vector-effect=\"non-scaling-stroke\">";
  $svg .= "<path fill=\"url(#cr-{$tone}-{$id})\" d=\"M12 64 L116 64 L112 84 Q64 92 16 84 Z\" />";

  // Crown body + rounded tips
  $svg .= "<path fill=\"url(#cr-{$tone}-{$id})\" d=\"M12 64 C24 46, 36 40, 42 44 C44 34, 52 26, 64 22 C76 26, 84 34, 86 44 C92 40, 104 46, 116 64 Z\" />";
  $svg .= "<circle cx=\"24\" cy=\"48\" r=\"5\" fill=\"url(#cr-{$tone}-{$id})\" stroke=\"{$stroke}\" />";
  $svg .= "<circle cx=\"44\" cy=\"38\" r=\"6\" fill=\"url(#cr-{$tone}-{$id})\" stroke=\"{$stroke}\" />";
  $svg .= "<circle cx=\"64\" cy=\"30\" r=\"7\" fill=\"url(#cr-{$tone}-{$id})\" stroke=\"{$stroke}\" />";
  $svg .= "<circle cx=\"84\" cy=\"38\" r=\"6\" fill=\"url(#cr-{$tone}-{$id})\" stroke=\"{$stroke}\" />";
  $svg .= "<circle cx=\"104\" cy=\"48\" r=\"5\" fill=\"url(#cr-{$tone}-{$id})\" stroke=\"{$stroke}\" />";

  // Highlight and jewels
  $svg .= "<path d=\"M20 60 Q64 48 108 60\" fill=\"none\" stroke=\"url(#cr-hi-{$id})\" stroke-width=\"6\" stroke-linecap=\"round\" />";
  $svg .= "<circle cx=\"44\" cy=\"74\" r=\"3\" fill=\"#ffffff\" opacity=\"0.85\" />";
  $svg .= "<circle cx=\"64\" cy=\"76\" r=\"3\" fill=\"#ffffff\" opacity=\"0.85\" />";
  $svg .= "<circle cx=\"84\" cy=\"74\" r=\"3\" fill=\"#ffffff\" opacity=\"0.85\" />";
  $svg .= "</g></svg>";

  echo $svg;
}

function _rfpl_trophy_wall_crown($labels, $tone='gold', $double=false){
  $cls = 'trophy-wall' . ($double ? ' trophy-wall--dobles' : ' trophy-wall--individual');
  echo '<div class="'.$cls.'" role="list">';
  if (empty($labels)) {
    echo '<p>(El jugador no tiene hitos de esta característica)</p>';
  } else {
    foreach ($labels as $lab) {
      $txt = is_scalar($lab) ? (string)$lab : '';
      $id  = _rfpl_safe_id($tone.'-'.$txt.'-'.($double?'d':'i'));
      echo '<div class="trophy" role="listitem" aria-label="Temporada '.$txt.($double?' (Dobles)':' (Individual)').'">';
      _rfpl_crown_icon($tone, $double, $id);
      echo '<span class="trophy-year">Temporada '.esc_html($txt).'</span>';
      echo '</div>';
    }
  }
  echo '</div>';
}

// ===== Tarjetas =====
?>
<div class="futbolin-card player-milestones hitos-gold" style="margin-top:16px;">
  <h3>Nº1 del Ranking por Temporada</h3>
  <div class="milestones-grid">
    <section class="milestone-block">
      <header class="milestone-header"><h4 class="milestone-title">Nº1 del Ranking (Dobles)</h4></header>
      <?php _rfpl_trophy_wall_crown($hitosData['numero1_temporada_open_dobles_anios'] ?? [], 'gold', false); ?>
    </section>
    <section class="milestone-block">
      <header class="milestone-header"><h4 class="milestone-title">Nº1 del Ranking (Individual)</h4></header>
      <?php _rfpl_trophy_wall_crown($hitosData['numero1_temporada_open_individual_anios'] ?? [], 'gold', false); ?>
    </section>
  </div>
</div>

<div class="futbolin-card player-milestones hitos-silver" style="margin-top:16px;">
  <h3>Nº2 del Ranking por Temporada</h3>
  <div class="milestones-grid">
    <section class="milestone-block">
      <header class="milestone-header"><h4 class="milestone-title">Nº2 del Ranking (Dobles)</h4></header>
      <?php _rfpl_trophy_wall_crown($hitosData['numero2_temporada_open_dobles_anios'] ?? [], 'silver', false); ?>
    </section>
    <section class="milestone-block">
      <header class="milestone-header"><h4 class="milestone-title">Nº2 del Ranking (Individual)</h4></header>
      <?php _rfpl_trophy_wall_crown($hitosData['numero2_temporada_open_individual_anios'] ?? [], 'silver', false); ?>
    </section>
  </div>
</div>

<div class="futbolin-card player-milestones hitos-bronze" style="margin-top:16px;">
  <h3>Nº3 del Ranking por Temporada</h3>
  <div class="milestones-grid">
    <section class="milestone-block">
      <header class="milestone-header"><h4 class="milestone-title">Nº3 del Ranking (Dobles)</h4></header>
      <?php _rfpl_trophy_wall_crown($hitosData['numero3_temporada_open_dobles_anios'] ?? [], 'bronze', false); ?>
    </section>
    <section class="milestone-block">
      <header class="milestone-header"><h4 class="milestone-title">Nº3 del Ranking (Individual)</h4></header>
      <?php _rfpl_trophy_wall_crown($hitosData['numero3_temporada_open_individual_anios'] ?? [], 'bronze', false); ?>
    </section>
  </div>
</div>
