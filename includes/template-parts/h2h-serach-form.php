<?php
$h2h_a = isset($h2h_a) ? (string)$h2h_a : (isset($_GET['a']) ? sanitize_text_field(wp_unslash($_GET['a'])) : '');
$h2h_b = isset($h2h_b) ? (string)$h2h_b : (isset($_GET['b']) ? sanitize_text_field(wp_unslash($_GET['b'])) : '');
$page = isset($page) ? (int)$page : (isset($_GET['page']) ? (int)$_GET['page'] : 1);
?>
<?php
/**
 * Archivo Resultante: h2h-search-form.php
 * Ruta: includes/template-parts/h2h-search-form.php
 * Fuente Original: h2h-search-form.php (antiguo)
 *
 * Descripción: Muestra los dos formularios para la página H2H independiente.
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
?>
<div class="h2h-search-area">
    <div class="h2h-player-search">
        <h3>Jugador 1 <?php if ($jugador1_data) echo ': <span class="player-selected-name">' . esc_html($jugador1_data->nombreJugador) . '</span>'; ?></h3>
        <form method="get" action="">
            <?php if ($jugador2_id) echo '<input type="hidden" name="jugador2_id" value="' . esc_attr($jugador2_id) . '">'; ?>
            <input type="text" name="search1" value="<?php echo esc_attr($search1); ?>" placeholder="Buscar Jugador 1...">
            <button type="submit">Buscar</button>
        </form>
        <?php if (!empty($search1_results)) : ?>
            <ul>
                <?php foreach ($search1_results as $player) : ?>
                    <li><a href="<?php echo esc_url(add_query_arg(['jugador1_id' => $player->jugadorId, 'jugador2_id' => $jugador2_id], remove_query_arg('search1'))); ?>"><?php echo esc_html($player->nombreJugador); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="h2h-player-search">
        <h3>Jugador 2 <?php if ($jugador2_data) echo ': <span class="player-selected-name">' . esc_html($jugador2_data->nombreJugador) . '</span>'; ?></h3>
        <form method="get" action="">
            <?php if ($jugador1_id) echo '<input type="hidden" name="jugador1_id" value="' . esc_attr($jugador1_id) . '">'; ?>
            <input type="text" name="search2" value="<?php echo esc_attr($search2); ?>" placeholder="Buscar Jugador 2...">
            <button type="submit">Buscar</button>
        </form>
        <?php if (!empty($search2_results)) : ?>
            <ul>
                <?php foreach ($search2_results as $player) : ?>
                    <li><a href="<?php echo esc_url(add_query_arg(['jugador1_id' => $jugador1_id, 'jugador2_id' => $player->jugadorId], remove_query_arg('search2'))); ?>"><?php echo esc_html($player->nombreJugador); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>