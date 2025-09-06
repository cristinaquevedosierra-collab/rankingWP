<?php
if (!isset($player) && isset($jugador)) { $player = $jugador; }
$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
?>
<?php
/**
 * Archivo: includes/template-parts/player-stats.php
 * Descripción: Estadísticas de partidos y palmarés por tipo, con accesos seguros.
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Helpers
$dash = '—';
$hn = function($v) use ($dash) {
    if ($v === null || $v === '' || !is_numeric($v)) return $dash;
    return esc_html((string)$v);
};
$hperc = function($v, $dec = 1) use ($dash) {
    if ($v === null || $v === '' || !is_numeric($v)) return $dash;
    // Usa number_format_i18n si existe (WordPress), si no, fallback
    if (function_exists('number_format_i18n')) {
        $num = number_format_i18n((float)$v, $dec);
    } else {
        $num = number_format((float)$v, $dec, ',', '.');
    }
    return esc_html($num) . '%';
};

// Normaliza $processor por seguridad
if (!isset($processor) || !is_object($processor)) {
    $processor = (object)[];
}

// Datos de entrada con defaults seguros
$match_stats    = isset($processor->match_stats_by_type)    && is_array($processor->match_stats_by_type)    ? $processor->match_stats_by_type    : [];
$honours_stats  = isset($processor->honours_stats_by_type)  && is_array($processor->honours_stats_by_type)  ? $processor->honours_stats_by_type  : [];

// Orden de visualización
$display_order = ['Dobles', 'Individual', 'Mixto', 'Competiciones de Menor Categoría'];
?>

<div class="futbolin-card">

    <h3>Estadísticas de Partidos</h3>
    <ul class="stats-list">
    <?php foreach ($display_order as $type) :
        if (empty($match_stats[$type]) || !is_array($match_stats[$type])) continue;

        $total_stats = $match_stats[$type]['total']   ?? [];
        $details     = $match_stats[$type]['details'] ?? [];

        $jugados  = $total_stats['jugados']  ?? null;
        $ganados  = $total_stats['ganados']  ?? null;
        $rate     = $total_stats['rate']     ?? null;
    ?>
        <li class="<?php echo esc_attr(strtolower(str_replace(' ', '-', $type))); ?>-summary-item">
            <div class="summary-line">
                <strong class="summary-title">
                    <?php echo esc_html($type); ?> (Partidos)
                    <?php if ($type === 'Competiciones de Menor Categoría') : ?>
                        <span class="stats-footnote" title="Agrupa Rookie, Amateur, Master, DYP, etc.">*</span>
                    <?php endif; ?>
                </strong>
                <span>
                    <?php echo $hn($ganados); ?> victorias de <?php echo $hn($jugados); ?> partidos
                    <span class="porcentaje-victorias">(<?php echo $hperc($rate, 1); ?>)</span>
                </span>
            </div>

            <?php if ($type === 'Dobles' && !empty($details) && is_array($details)) : ?>
                <ul class="futbolin-indented-list">
                    <?php foreach ($details as $competicion => $st) :
                        // st puede ser incompleto; protegemos accesos
                        $c_jugados = is_array($st) && isset($st['jugados']) ? $st['jugados'] : null;
                        $c_ganados = is_array($st) && isset($st['ganados']) ? $st['ganados'] : null;
                        $c_rate    = is_array($st) && isset($st['rate'])    ? $st['rate']    : null;
                    ?>
                    <li>
                        <div class="list-item-content">
                            <strong>
                                <?php echo esc_html($competicion); ?>
                                <?php if ($competicion === 'Open Dobles') : ?>
                                    <span class="stats-footnote" title="Incluye 'Open Dobles' y 'España Dobles'.">*</span>
                                <?php endif; ?>
                            </strong>
                            <span>
                                <?php echo $hn($c_ganados); ?> de <?php echo $hn($c_jugados); ?>
                                <span class="porcentaje-victorias">(<?php echo $hperc($c_rate, 1); ?>)</span>
                            </span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>

    <hr style="border-top: 1px solid var(--futbolin-border-color); margin: 25px 0;">

    <h3>Estadísticas de Competición (Palmarés)</h3>
    <ul class="stats-list">
    <?php foreach ($display_order as $type) :
        if (empty($honours_stats[$type]) || !is_array($honours_stats[$type])) continue;

        $total_stats = $honours_stats[$type]['total'] ?? [];
        $jugados  = $total_stats['jugados']  ?? null;
        $ganados  = $total_stats['ganados']  ?? null;
        $rate     = $total_stats['rate']     ?? null;
    ?>
        <li>
            <strong><?php echo esc_html($type); ?> (Competiciones):</strong>
            <span>
                <?php echo $hn($ganados); ?> títulos de <?php echo $hn($jugados); ?> jugadas
                <span class="porcentaje-victorias">(<?php echo $hperc($rate, 1); ?>)</span>
            </span>
        </li>
    <?php endforeach; ?>
    </ul>

</div>