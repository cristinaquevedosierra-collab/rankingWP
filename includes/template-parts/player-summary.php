<?php
if (!isset($player) && isset($jugador)) { $player = $jugador; }
$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
?>
<?php
/**
 * Archivo: includes/template-parts/player-summary.php
 * Descripción: Resumen del perfil del jugador, con accesos seguros a datos.
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Helpers seguros
$dash = '—';
$h = function($v) use ($dash) {
    if ($v === null || $v === '') return $dash;
    return esc_html((string)$v);
};
$hn = function($v) use ($dash) {
    if ($v === null || $v === '' || !is_numeric($v)) return $dash;
    // admite enteros o floats
    return esc_html((string)$v);
};
$hperc = function($v, $dec = 2) use ($dash) {
    if ($v === null || $v === '' || !is_numeric($v)) return $dash;
    $num = number_format((float)$v, $dec);
    return esc_html($num) . '%';
};
$hlist = function($arr) use ($dash) {
    if (empty($arr) || !is_array($arr)) return $dash;
    $clean = array_map(function($x){ return esc_html((string)$x); }, $arr);
    return implode(', ', $clean);
};

// Normaliza $processor por seguridad
if (!isset($processor) || !is_object($processor)) {
    $processor = (object)[];
}

// Variables de entrada esperadas (compat: basic_data | player_data)
if (isset($processor->basic_data) && is_object($processor->basic_data)) {
    $player_details = $processor->basic_data;
} elseif (isset($processor->player_data) && is_object($processor->player_data)) {
    $player_details = $processor->player_data;
} else {
    $player_details = (object)[];
}

$stats = isset($processor->summary_stats) && is_array($processor->summary_stats) ? $processor->summary_stats : [];

// Campos básicos del jugador
$genero                    = $player_details->genero                   ?? $dash;
$activoDesdeTorneo         = $player_details->activoDesdeTorneo        ?? $dash;
$activoDesdeTorneoAnio     = $player_details->activoDesdeTorneoAnio    ?? $dash;
$ultimoTorneo              = $player_details->ultimoTorneo             ?? $dash;
$ultimoTorneoAnio          = $player_details->ultimoTorneoAnio         ?? $dash;

// Stats agregadas (con defaults)
$total_matches             = $stats['total_matches']            ?? null;
$wins                      = $stats['wins']                     ?? null;
$win_rate                  = $stats['win_rate']                 ?? null; // % partidos
$total_competitions        = $stats['total_competitions']       ?? null;
$titles                    = $stats['titles']                   ?? null;
$competition_win_rate      = $stats['competition_win_rate']     ?? null; // % competiciones
$unique_tournaments        = $stats['unique_tournaments']       ?? null;

// Hitos
$hitos = isset($processor->hitos) && is_array($processor->hitos) ? $processor->hitos : [];
$hitos_dobles_anios     = $hitos['campeon_esp_dobles_anios']     ?? [];
$hitos_individual_anios = $hitos['campeon_esp_individual_anios'] ?? [];
$hay_hitos_personales   = !empty($hitos_dobles_anios) || !empty($hitos_individual_anios);
?>

<div class="futbolin-card">
    <h3>Detalles Personales</h3>
    <ul class="stats-list">
        <li>
            <strong class="label">Género:</strong>
            <span class="value"><?php echo $h($genero); ?></span>
        </li>
        <li>
            <strong class="label">Activo desde:</strong>
            <span class="value"><?php echo $h($activoDesdeTorneo); ?> (<?php echo $h($activoDesdeTorneoAnio); ?>)</span>
        </li>
        <li>
            <strong class="label">Último torneo:</strong>
            <span class="value"><?php echo $h($ultimoTorneo); ?> (<?php echo $h($ultimoTorneoAnio); ?>)</span>
        </li>
        <li>
            <strong class="label">Total Torneos Disputados:</strong>
            <span class="value"><?php echo $hn($unique_tournaments); ?></span>
        </li>
    </ul>

    <hr style="border-top: 1px solid var(--futbolin-border-color); margin: 25px 0;">

    <h3>Resultados Globales</h3>
    
    <div class="player-career-stats-grid">
        <div class="stat-box">
            <div class="stat-value"><?php echo $hn($total_matches); ?></div>
            <div class="stat-label">Partidos Jugados</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $hn($wins); ?></div>
            <div class="stat-label">Partidos Ganados</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $hperc($win_rate); ?></div>
            <div class="stat-label">% Victorias (Partidos)</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $hn($total_competitions); ?></div>
            <div class="stat-label">Competiciones Jugadas</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $hn($titles); ?></div>
            <div class="stat-label">Títulos Ganados</div>
        </div>
        <div class="stat-box">
            <div class="stat-value"><?php echo $hperc($competition_win_rate); ?></div>
            <div class="stat-label">% Victorias (Competiciones)</div>
        </div>
    </div>

    <div class="player-milestones" style="margin-top: 25px;">
        <hr style="border-top: 1px solid var(--futbolin-border-color); margin-bottom: 25px;">
        <h3>Hitos Personales</h3>

        <?php if ($hay_hitos_personales) : ?>
            <ul class="stats-list">
                <?php if (!empty($hitos_dobles_anios)) : ?>
                    <li>
                        <strong>Campeón de España (Dobles)</strong>
                        <span class="value"><?php echo $hlist($hitos_dobles_anios); ?></span>
                    </li>
                <?php endif; ?>
                <?php if (!empty($hitos_individual_anios)) : ?>
                    <li>
                        <strong>Campeón de España (Individual)</strong>
                        <span class="value"><?php echo $hlist($hitos_individual_anios); ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        <?php else : ?>
            <p>Este jugador no tiene hitos destacados.</p>
        <?php endif; ?>
    </div>

</div>