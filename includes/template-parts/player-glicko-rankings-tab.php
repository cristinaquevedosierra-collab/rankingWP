<?php
if (!isset($player) && isset($jugador)) { $player = $jugador; }
$player_id = isset($player_id) ? (int)$player_id : (isset($_GET['player_id']) ? (int)$_GET['player_id'] : 0);
$q = isset($q) ? (string)$q : (isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '');
?>
<?php
/**
 * Archivo: includes/template-parts/player-glicko-rankings-tab.php
 * Descripción: Ranking del jugador por modalidad (Glicko), con enlaces al ranking completo.
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

// Comprobaciones y defaults seguros
$api_client_ok = (isset($api_client) && is_object($api_client)
    && method_exists($api_client, 'get_modalidades')
    && method_exists($api_client, 'get_ranking'));

if (!isset($processor->basic_data) || !is_object($processor->basic_data)) {
    echo '<div class="futbolin-card"><p>No hay datos del jugador.</p></div>';
    return;
}

$player_id = isset($processor->basic_data->jugadorId) ? (int)$processor->basic_data->jugadorId : 0;
if ($player_id <= 0) {
    echo '<div class="futbolin-card"><p>ID de jugador no válido.</p></div>';
    return;
}

// URL a la página de ranking para enlazar a la modalidad
$ranking_page_url = isset($ranking_page_url) ? $ranking_page_url : home_url('/');

// Orden preferente
$display_order = [
    'Dobles', 'Individual', 'Mixto',
    'Senior Dobles', 'Senior Individual',
    'Junior Dobles', 'Junior Individual',
    'Mujeres Dobles', 'Mujeres Individual'
];

$modalidades_list = [];
if ($api_client_ok) {
    $modalidades_list = $api_client->get_modalidades();
}

// Reordenamos según preferencia y añadimos las que falten al final
$ordered_modalidades = [];
if (!empty($modalidades_list) && is_array($modalidades_list)) {
    // Índice por nombre
    $by_name = [];
    foreach ($modalidades_list as $m) {
        if (!isset($m->descripcion)) continue;
        $by_name[$m->descripcion] = $m;
    }
    // Primero las del orden preferido
    foreach ($display_order as $name) {
        if (isset($by_name[$name])) {
            $ordered_modalidades[] = $by_name[$name];
            unset($by_name[$name]);
        }
    }
    // Luego el resto (por si la API trae nuevas)
    foreach ($by_name as $leftover) {
        $ordered_modalidades[] = $leftover;
    }
}

$ranking_mostrado = false;
?>
<div class="futbolin-card">
    <h3>Ranking por Modalidad</h3>

    <?php if (!$api_client_ok): ?>
        <p>No se pudo inicializar el cliente de la API o faltan métodos requeridos.</p>
    <?php elseif (empty($ordered_modalidades)): ?>
        <p>No se pudieron obtener las modalidades desde la API.</p>
    <?php else: ?>
        <ul class="stats-list">
            <?php foreach ($ordered_modalidades as $modalidad) :
                $mod_id   = isset($modalidad->modalidadId) ? (int)$modalidad->modalidadId : 0;
                $mod_name = isset($modalidad->descripcion) ? (string)$modalidad->descripcion : 'Modalidad';

                if ($mod_id <= 0) continue;

                // Traemos un ranking suficientemente grande para localizar al jugador
                $ranking_data = $api_client->get_ranking($mod_id, 1, 9999);

                $player_ranking = null;
                if ($ranking_data && isset($ranking_data->ranking->items) && is_array($ranking_data->ranking->items)) {
                    foreach ($ranking_data->ranking->items as $idx => $jug) {
                        if ((int)($jug->jugadorId ?? 0) === $player_id) {
                            $jug->posicion = $idx + 1;
                            $player_ranking = $jug;
                            break;
                        }
                    }
                }

                if ($player_ranking && !empty($player_ranking->posicion)) :
                    $ranking_mostrado = true;
                    $posicion = (int)$player_ranking->posicion;
                    $puntos   = isset($player_ranking->puntos) ? round((float)$player_ranking->puntos) : 0;
                    $categoria = isset($player_ranking->categoria) ? (string)$player_ranking->categoria : null;

                    // Link a la clasificación completa de esa modalidad
                    $mod_url = add_query_arg(['view' => 'ranking', 'modalidad' => $mod_id], $ranking_page_url);
            ?>
                <li class="ranking-row">
                    <div class="ranking-position pos-<?php echo esc_attr($posicion); ?>">
                        <?php echo esc_html($posicion); ?>
                    </div>
                    <div class="ranking-player-details">
                        <h4 style="margin:0; font-size: 1.2em; color: var(--futbolin-text-headings);">
                            <a href="<?php echo esc_url($mod_url); ?>" title="Ver clasificación completa de <?php echo esc_attr($mod_name); ?>">
                                <?php echo esc_html($mod_name); ?>
                            </a>
                        </h4>
                        <?php if (in_array($mod_name, ['Dobles','Individual'], true) && $categoria): ?>
                            <span style="font-size: 0.9em; color: var(--futbolin-text-muted);">
                                (Categoría: <?php echo esc_html($categoria); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="ranking-points">
                        <div class="points-pill">
                            <span class="points-value"><?php echo esc_html($puntos); ?></span>
                            <span class="points-label">puntos</span>
                        </div>
                    </div>
                </li>
            <?php endif; endforeach; ?>
        </ul>

        <?php if ($ranking_mostrado): ?>
            <h5>Pincha en una modalidad si quieres ver su clasificación completa</h5>
        <?php else: ?>
            <p style="text-align: center; font-style: italic; color: var(--futbolin-text-muted);">
                Actualmente no tienes ranking en ninguna modalidad activa.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>