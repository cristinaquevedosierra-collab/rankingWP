<?php
/**
 * Archivo Resultante: class-futbolin-h2h-shortcode.php
 * Ruta: includes/shortcodes/class-futbolin-h2h-shortcode.php
 * Fuente Original: class-futbolin-h2h.php (antiguo)
 *
 * Descripción: Registra y renderiza el shortcode independiente [futbolin_h2h].
 */
if (!defined('ABSPATH')) exit;

class Futbolin_H2h_Shortcode {

    private $api_client;
    
    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api_client = new Futbolin_API_Client();
        }
        add_shortcode('futbolin_h2h', [$this, 'render_shortcode']);
    }

    public function render_shortcode() {
        // --- OBTENER PARÁMETROS DE ENTRADA ---
        $jugador1_id = isset($_GET['jugador1_id']) ? intval($_GET['jugador1_id']) : 0;
        $jugador2_id = isset($_GET['jugador2_id']) ? intval($_GET['jugador2_id']) : 0;
        $search1 = isset($_GET['search1']) ? sanitize_text_field($_GET['search1']) : '';
        $search2 = isset($_GET['search2']) ? sanitize_text_field($_GET['search2']) : '';

        // --- CONSULTAR DATOS BÁSICOS Y DE BÚSQUEDA A LA API ---
        $jugador1_data = $this->api_client->get_datos_jugador($jugador1_id);
        $jugador2_data = $this->api_client->get_datos_jugador($jugador2_id);
        $search1_results = !empty($search1) ? $this->api_client->buscar_jugadores($search1) : [];
        $search2_results = !empty($search2) ? $this->api_client->buscar_jugadores($search2) : [];

        ob_start();
        ?>
        <div class="h2h-page-wrapper">
            <div class="h2h-container">
                <?php 
                // --- MOSTRAR EL FORMULARIO DE BÚSQUEDA ---
                include FUTBOLIN_API_PATH . 'includes/template-parts/h2h-search-form.php'; 
                
                // --- SI TENEMOS DOS JUGADORES, PROCESAR Y MOSTRAR RESULTADOS ---
                if ($jugador1_data && $jugador2_data) {
                    $p1_matches = $this->api_client->get_partidos_jugador($jugador1_id);
                    $p1_positions = $this->api_client->get_posiciones_jugador($jugador1_id);
                    $p2_matches = $this->api_client->get_partidos_jugador($jugador2_id);
                    $p2_positions = $this->api_client->get_posiciones_jugador($jugador2_id);

                    $stats = new Futbolin_H2H_Processor($jugador1_data, $p1_matches, $p1_positions, $jugador2_data, $p2_matches, $p2_positions);
                    
                    include FUTBOLIN_API_PATH . 'includes/template-parts/h2h-results.php';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}