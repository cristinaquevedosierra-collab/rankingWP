<?php
/**
 * Archivo: includes/shortcodes/class-futbolin-champions-shortcode.php
 * Vista "Campeones de España" — SOLO endpoint oficial /Jugador/GetCampeonesEspania.
 * Usa el wrapper común para mantener layout idéntico al resto de vistas.
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Champions_Shortcode {

    /** @var Futbolin_API_Client|null */
    private $api_client;

    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api_client = new Futbolin_API_Client();
        }
    }

    public function render($atts = [], $view = 'champions') {
        if (!$this->api_client || !method_exists($this->api_client, 'get_campeones_espania')) {
            return '<div class="futbolin-card"><p>Error: cliente de API no disponible.</p></div>';
        }

        // 1) Consumir endpoint oficial
        $resp = $this->api_client->get_campeones_espania();
        if (is_wp_error($resp)) {
            $msg = esc_html($resp->get_error_message());
            return '<div class="futbolin-card"><p>No se pudo obtener la lista de campeones: ' . $msg . '</p></div>';
        }
        if (!$resp || !is_array($resp)) {
            // seguimos mostrando wrapper para consistencia
            $champions_rows = [];
            $template_to_load = 'campeones-espana-display.php';
        $current_view = isset($view) ? $view : 'champions';
            ob_start();
            include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
            return ob_get_clean();
        }

        // 2) Normalizar estructura por jugador con arrays de AÑOS
        $extract_year = function($item) {
            $y = 0;
            if (isset($item->temporada) && is_string($item->temporada)) {
                if (preg_match('/(19|20)\d{2}/', $item->temporada, $m)) $y = (int)$m[0];
            }
            if (!$y && isset($item->nombreTorneo) && is_string($item->nombreTorneo)) {
                if (preg_match('/(19|20)\d{2}/', $item->nombreTorneo, $m)) $y = (int)$m[0];
            }
            return $y;
        };

        $champions_rows = [];
        foreach ($resp as $row) {
            $id   = isset($row->jugadorId) ? (int)$row->jugadorId : 0;
            $name = isset($row->nombreJugador) ? trim((string)$row->nombreJugador) : '';
            if ($id <= 0 || $name === '') continue;
            $dob = []; $ind = [];
            if (!empty($row->torneosDobles) && is_array($row->torneosDobles)) {
                foreach ($row->torneosDobles as $td) { $y = $extract_year($td); if ($y) $dob[] = (string)$y; }
            }
            if (!empty($row->torneosIndividual) && is_array($row->torneosIndividual)) {
                foreach ($row->torneosIndividual as $ti) { $y = $extract_year($ti); if ($y) $ind[] = (string)$y; }
            }
            $dob = array_values(array_unique($dob));
            $ind = array_values(array_unique($ind));
            sort($dob, SORT_NATURAL); sort($ind, SORT_NATURAL);
            $champions_rows[] = [
                'id' => $id,
                'nombre' => $name,
                'dobles' => $dob,
                'individual' => $ind,
                'total' => count($dob)+count($ind),
            ];
        }

        // ordenar por total desc
        usort($champions_rows, function($a,$b){ return ($b['total'] ?? 0) <=> ($a['total'] ?? 0); });

        // 3) Render con el wrapper común y la plantilla dedicada
        $template_to_load = 'campeones-espana-display.php';
        $current_view = isset($view) ? $view : 'champions';
        ob_start();
        include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        return ob_get_clean();
    }
}
