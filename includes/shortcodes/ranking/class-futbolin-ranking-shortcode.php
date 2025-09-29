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
    if (is_wp_error($opciones) || !is_array($opciones)) { $opciones = []; }
        $profile_page_url = '';
        if (!empty($opciones['player_profile_page_id'])) {
            $profile_page_url = get_permalink((int)$opciones['player_profile_page_id']);
        }
        // Fallback 1: try page with slug 'perfil-jugador'
        if (empty($profile_page_url)) {
            $maybe = get_page_by_path('perfil-jugador');
            if ($maybe) {
                $profile_page_url = get_permalink($maybe->ID);
            }
        }
        // Fallback 2: try 'futbolin-jugador'
        if (empty($profile_page_url)) {
            $maybe = get_page_by_path('futbolin-jugador');
            if ($maybe) {
                $profile_page_url = get_permalink($maybe->ID);
            }
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
            $modalidades = $this->api->get_modalidades();
            if (is_wp_error($modalidades) || empty($modalidades)) { $modalidades = []; }

            // NOTA: Flujo de datos ranking:
            //   1) Busca primero cache (ranking_<modalidad>.json)
            //   2) Valida mínima estructura (al menos un objeto con jugadorId o nombreJugador)
            //   3) Si la cache falta o es inválida → llamada API
            //   4) Si la API devuelve datos válidos → se reescribe la cache
            //   5) Sólo se muestra el mensaje de error si ambos caminos no producen items

            // Si no vino modalidad, preferimos Dobles(2) o Individual(1); si no existen, usamos la primera
            if ($modalidad_id <= 0 && !empty($modalidades)) {
                $prefer = [2,1];
                $found  = 0;
                foreach ($prefer as $pid) {
                    foreach ($modalidades as $m) {
                        if (is_object($m) && isset($m->modalidadId) && (int)$m->modalidadId === $pid) { $found = $pid; break 2; }
                    }
                }
                if ($found > 0) { $modalidad_id = $found; }
                else {
                    $first = $modalidades[0];
                    if (is_object($first) && isset($first->modalidadId)) { $modalidad_id = (int)$first->modalidadId; }
                }
            }

            // Si hay lista de modalidades activas, valida que la elegida esté permitida
            if (!empty($modalidades_activas) && $modalidad_id > 0 && !in_array($modalidad_id, $modalidades_activas, true)) {
                $modalidad_id = (int)reset($modalidades_activas);
            }

            if ($modalidad_id > 0) {
                // 1) Intentar leer cache local ranking_<modalidad>.json
                $cached = [];
                $cache_used = false;
                try {
                    if (class_exists('RF_Hitos_Cache_Manager')) {
                        $cache_dir = method_exists('RF_Hitos_Cache_Manager','get_cache_dir') ? RF_Hitos_Cache_Manager::get_cache_dir() : '';
                        $cache_file = $cache_dir ? $cache_dir . 'ranking_' . $modalidad_id . '.json' : '';
                        if (file_exists($cache_file) && filesize($cache_file) > 10) {
                            $raw = file_get_contents($cache_file);
                            $json = json_decode($raw);
                            if ($json && is_array($json)) { $cached = $json; $cache_used = true; }
                            elseif ($json && is_object($json)) {
                                // Intentar localizar items en varias rutas
                                if (isset($json->items) && is_array($json->items)) { $cached = $json->items; $cache_used = true; }
                                elseif (isset($json->data->items) && is_array($json->data->items)) { $cached = $json->data->items; $cache_used = true; }
                                elseif (isset($json->result->items) && is_array($json->result->items)) { $cached = $json->result->items; $cache_used = true; }
                                elseif (isset($json->ranking->items) && is_array($json->ranking->items)) { $cached = $json->ranking->items; $cache_used = true; }
                            }
                        }
                    }
                } catch (\Throwable $e) { /* ignorar fallos de lectura */ }

                // Validación mínima de cache: al menos 1 objeto con jugadorId o nombreJugador
                $cache_ok = false;
                if (is_array($cached) && !empty($cached)) {
                    foreach ($cached as $c) { if (is_object($c) && (isset($c->jugadorId) || isset($c->nombreJugador))) { $cache_ok = true; break; } }
                }

                $data_items = [];
                $container  = null;
                if ($cache_ok) {
                    $data_items = $cached;
                } else {
                    // 2) Cache inválida o inexistente -> API
                    if (method_exists($this->api, 'get_ranking_por_modalidad_esp_g2_all')) {
                        $resp = $this->api->get_ranking_por_modalidad_esp_g2_all($modalidad_id);
                    } else {
                        if (method_exists($this->api, 'get_ranking_por_modalidad_temporada_esp_g2')) {
                            $resp = $this->api->get_ranking_por_modalidad_temporada_esp_g2($modalidad_id, 13);
                        } else {
                            $resp = $this->api->get_ranking($modalidad_id, 1, -1);
                        }
                    }

                    if (!is_wp_error($resp) && !empty($resp) && (is_array($resp) || is_object($resp))) {
                        $container = $resp;
                        if (is_array($resp)) {
                            $is_assoc = array_keys($resp) !== range(0, count($resp) - 1);
                            if (!$is_assoc) { $data_items = $resp; }
                            else {
                                if (isset($resp['items']) && is_array($resp['items'])) { $data_items = $resp['items']; }
                                elseif (isset($resp['data']['items']) && is_array($resp['data']['items'])) { $data_items = $resp['data']['items']; }
                                elseif (isset($resp['result']['items']) && is_array($resp['result']['items'])) { $data_items = $resp['result']['items']; }
                                elseif (isset($resp['ranking']['items']) && is_array($resp['ranking']['items'])) { $data_items = $resp['ranking']['items']; }
                            }
                        } elseif (is_object($resp)) {
                            if (isset($resp->items) && is_array($resp->items)) { $data_items = $resp->items; }
                            elseif (isset($resp->data->items) && is_array($resp->data->items)) { $data_items = $resp->data->items; }
                            elseif (isset($resp->result->items) && is_array($resp->result->items)) { $data_items = $resp->result->items; }
                            elseif (isset($resp->ranking->items) && is_array($resp->ranking->items)) { $data_items = $resp->ranking->items; }
                        }
                        // 3) Guardar en cache si no venía de cache y obtuvimos algo
                        if (!empty($data_items) && class_exists('RF_Hitos_Cache_Manager')) {
                            try {
                                $cache_dir2 = method_exists('RF_Hitos_Cache_Manager','get_cache_dir') ? RF_Hitos_Cache_Manager::get_cache_dir() : '';
                                if ($cache_dir2) {
                                    $target = $cache_dir2 . 'ranking_' . $modalidad_id . '.json';
                                    @file_put_contents($target, json_encode($resp, JSON_UNESCAPED_UNICODE));
                                }
                            } catch (\Throwable $e) {}
                        }
                    }
                }

                // 4) Normalizar, deduplicar y sólo entonces decidir si hay error
                if (!is_array($data_items)) { $data_items = []; }
                $seen = [];
                $uniq = [];
                foreach ($data_items as $it) {
                    if (!is_object($it)) continue;
                    $key = isset($it->jugadorId)
                        ? 'id:' . $it->jugadorId
                        : 'np:' . mb_strtolower(($it->nombreJugador ?? '').'|'.($it->puntos ?? $it->puntuacion ?? '0'), 'UTF-8');
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $uniq[] = $it;
                }

                if (count($uniq) === 0) {
                    $ranking_error = 'Problemas en la conexión a la BBDD o la API lo resolveremos lo antes posible, ¡Gracias por tu paciencia!';
                } else {
                    $ranking_full = (object)[
                        'items'      => $uniq,
                        'totalCount' => count($uniq),
                        'modalidad'  => ($container && is_object($container) && isset($container->modalidad)) ? $container->modalidad : null,
                        'source'     => $cache_ok ? 'cache' : 'api'
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
