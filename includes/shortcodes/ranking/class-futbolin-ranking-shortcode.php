<?php
/**
 * Archivo: class-futbolin-ranking-shortcode.php
 * Rol: Shortcode de RANKING (solo ranking). El resto de vistas tienen su propio shortcode.
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Ranking_Shortcode {

    private $api;

    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api = new Futbolin_API_Client();
        }
    }

    // Firma con $view por compatibilidad con el router
    public function render($atts, $view) {
        $atts = shortcode_atts([
            'wrap'     => '1',   // por si algún día quieres sin wrapper
            'per_page' => 25,    // tamaño por defecto para la UI del template
        ], $atts, 'futbolin_ranking');

        $wrap     = ($atts['wrap'] === '1');
        $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : (int)$atts['per_page'];
        $pageSize = ($pageSize === -1) ? -1 : max(1, $pageSize);

        // Opciones y URLs auxiliares
        $opciones = get_option('mi_plugin_futbolin_options', []);
        $profile_page_url = '';
        if (!empty($opciones['player_profile_page_id'])) {
            $profile_page_url = get_permalink((int)$opciones['player_profile_page_id']);
        }

        // Modalidad: 'modalidad_id' (nuevo) o 'modalidad' (compat)
        $modalidad_id = 0;
        if (isset($_GET['modalidad_id'])) {
            $modalidad_id = (int)$_GET['modalidad_id'];
        } elseif (isset($_GET['modalidad'])) {
            $modalidad_id = (int)$_GET['modalidad'];
        } elseif (!empty($opciones['default_modalidad'])) {
            $modalidad_id = (int)$opciones['default_modalidad'];
        }

        $ranking_error   = '';
        $ranking_full    = null;   // contenedor con todos los items (para paginar en el template)
        $modalidades     = [];
        $modalidades_activas = (array)($opciones['ranking_modalities'] ?? []);

        if ($this->api) {
            // Para el menú lateral
            $modalidades = $this->api->get_modalidades() ?: [];

            // Si no vino modalidad, intenta con la primera disponible
            if ($modalidad_id <= 0 && !empty($modalidades)) {
                $first = $modalidades[0];
                if (is_object($first) && isset($first->modalidadId)) {
                    $modalidad_id = (int)$first->modalidadId;
                }
            }

            // Si hay lista de modalidades activas, valida que la elegida esté permitida
            if (!empty($modalidades_activas) && $modalidad_id > 0 && !in_array($modalidad_id, $modalidades_activas, true)) {
                $modalidad_id = (int)reset($modalidades_activas);
            }

            if ($modalidad_id > 0) {
                // Llamada “grande” para traer todo el ranking (el template hace la paginación local)
                $ALL_SIZE = 10000; // ajusta si tu API tiene tope menor
                $resp = $this->api->get_ranking($modalidad_id, 1, $ALL_SIZE);

                if (is_wp_error($resp) || empty($resp)) {
                    $ranking_error = 'No se pudo cargar el ranking.';
                } else {
                    $container = (is_object($resp) && isset($resp->ranking)) ? $resp->ranking : $resp;
                    $items = (is_object($container) && isset($container->items) && is_array($container->items)) ? $container->items : [];

                    // Deduplicar por jugadorId (fallback nombre+puntos si faltara)
                    $seen = [];
                    $uniq = [];
                    foreach ($items as $it) {
                        if (!is_object($it)) continue;
                        $key = isset($it->jugadorId)
                            ? 'id:' . $it->jugadorId
                            : 'np:' . mb_strtolower(($it->nombreJugador ?? '').'|'.($it->puntos ?? $it->puntuacion ?? '0'), 'UTF-8');
                        if (isset($seen[$key])) continue;
                        $seen[$key] = true;
                        $uniq[] = $it;
                    }

                    $ranking_full = (object)[
                        'items'      => $uniq,
                        'totalCount' => count($uniq),
                        'modalidad'  => $container->modalidad ?? null,
                    ];
                }
            } else {
                $ranking_error = 'No hay modalidad seleccionada.';
            }
        } else {
            $ranking_error = 'Cliente de API no disponible.';
        }

        // ===== Variables para el wrapper + template =====
        $current_view        = 'ranking';                 // <- clave: fija la vista
        $template_to_load    = 'ranking-display.php';
        $page_size           = $pageSize;                 // el template usa esta variable
        // para el template:
        $profile_page_url    = $profile_page_url;
        $modalidades         = $modalidades;
        $modalidades_activas = $modalidades_activas;
        $ranking_error       = $ranking_error;
        $ranking_full        = $ranking_full;
        $modalidad_id        = $modalidad_id;

        ob_start();
        if ($wrap) {
            include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        } else {
            include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-display.php';
        }
        return ob_get_clean();
    }
}
