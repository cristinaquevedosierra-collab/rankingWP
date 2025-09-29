<?php
if (!defined('ABSPATH')) exit;
// Asegurar carga del servicio Annual si no hay autoloader
if (!class_exists('Futbolin_Annual_Service')) {
    $svc_path = FUTBOLIN_API_PATH . 'includes/services/class-futbolin-annual-service.php';
    if (file_exists($svc_path)) { require_once $svc_path; }
}

/**
 * Shortcode handler para la vista "annual" (Ranking anual).
 * Usa el mismo formato visual que ranking-display.php, pero puede cargar
 * datos distintos (endpoint anual) cuando esté disponible.
 */
class Futbolin_Annual_Shortcode {

    private $api;
    private $annualService;

    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api = new Futbolin_API_Client();
        }
        if (class_exists('Futbolin_Annual_Service')) {
            $this->annualService = new Futbolin_Annual_Service($this->api);
        }
    }

    public function render($atts, $view) {
        $atts = shortcode_atts([
            'wrap'     => '1',
            'per_page' => 25,
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
        if (empty($profile_page_url)) {
            $maybe = get_page_by_path('perfil-jugador');
            if ($maybe) { $profile_page_url = get_permalink($maybe->ID); }
        }
        if (empty($profile_page_url)) {
            $maybe = get_page_by_path('futbolin-jugador');
            if ($maybe) { $profile_page_url = get_permalink($maybe->ID); }
        }

        // Modalidad: misma lógica que ranking con respeto a toggles del ranking anual
        $modalidad_id = 0;
        if (isset($_GET['modalidad_id'])) {
            $modalidad_id = (int)$_GET['modalidad_id'];
        } elseif (isset($_GET['modalidad'])) {
            $modalidad_id = (int)$_GET['modalidad'];
        } elseif (!empty($opciones['default_modalidad_anual'])) {
            $modalidad_id = (int)$opciones['default_modalidad_anual'];
        } elseif (!empty($opciones['default_modalidad'])) {
            // Fallback al selector general si no se configuró el anual específico
            $modalidad_id = (int)$opciones['default_modalidad'];
        }

    $ranking_error   = '';
    $ranking_full    = null; // contenedor con items para ranking-display
    $modalidades     = [];
    $modalidades_activas = (array)($opciones['ranking_modalities'] ?? []);

        if ($this->api) {
            // Toggles de ranking anual (por modalidad)
            $annual_doubles_on    = (!isset($opciones['enable_annual_doubles']) || $opciones['enable_annual_doubles'] === 'on');
            $annual_individual_on = (!isset($opciones['enable_annual_individual']) || $opciones['enable_annual_individual'] === 'on');

            // Modalidades (para menú y etiquetas); mapeamos por id->descripcion
            $modalidades = $this->api->get_modalidades() ?: [];
            $mod_labels = [];
            if (!empty($modalidades) && is_array($modalidades)) {
                foreach ($modalidades as $m) {
                    if (is_object($m) && isset($m->modalidadId)) {
                        $mod_labels[(int)$m->modalidadId] = (string)($m->descripcion ?? ('Modalidad '.$m->modalidadId));
                    }
                }
            }

            // Si no vino modalidad, elegimos por preferencia (priorizando las permitidas por los toggles: Dobles/Individual)
            if ($modalidad_id <= 0 && !empty($modalidades)) {
                $prefer = [];
                if ($annual_doubles_on) { $prefer[] = 2; }
                if ($annual_individual_on) { $prefer[] = 1; }
                // Resto por si en el futuro activamos más, pero hoy sólo D/I
                $found  = 0;
                foreach ($prefer as $pid) {
                    foreach ($modalidades as $m) {
                        if (is_object($m) && isset($m->modalidadId) && (int)$m->modalidadId === $pid) { $found = $pid; break 2; }
                    }
                }
                if ($found > 0) { $modalidad_id = $found; }
                else {
                    // Si no hay permitidas, devolvemos pantalla deshabilitada
                    ob_start();
                    $current_view = 'annual';
                    $template_to_load = 'module-disabled-display.php';
                    $disabled_title = 'Ranking anual deshabilitado';
                    $disabled_msg   = 'No hay modalidades de ranking anual habilitadas.';
                    include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
                    return ob_get_clean();
                }
            }

            // Si se solicitó una modalidad no permitida por los toggles, intentar fallback
            if ($modalidad_id === 2 && !$annual_doubles_on) {
                if ($annual_individual_on) { $modalidad_id = 1; }
                else {
                    ob_start();
                    $current_view = 'annual';
                    $template_to_load = 'module-disabled-display.php';
                    $disabled_title = 'Ranking anual (Dobles) deshabilitado';
                    $disabled_msg   = 'Esta modalidad del ranking anual está deshabilitada desde el panel de administración.';
                    include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
                    return ob_get_clean();
                }
            }
            if ($modalidad_id === 1 && !$annual_individual_on) {
                if ($annual_doubles_on) { $modalidad_id = 2; }
                else {
                    ob_start();
                    $current_view = 'annual';
                    $template_to_load = 'module-disabled-display.php';
                    $disabled_title = 'Ranking anual (Individual) deshabilitado';
                    $disabled_msg   = 'Esta modalidad del ranking anual está deshabilitada desde el panel de administración.';
                    include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
                    return ob_get_clean();
                }
            }

            if ($this->annualService) {
                // 1) Detectar última temporada con datos, usando todas las modalidades conocidas
                $candidate_ids = [];
                foreach ($modalidades as $m) { if (is_object($m) && isset($m->modalidadId)) $candidate_ids[] = (int)$m->modalidadId; }
                if (empty($candidate_ids)) { $candidate_ids = [2,1,3,4,5,6,7,8,9]; }
                $last_season = $this->annualService->detect_last_season_with_data($candidate_ids, 14);
                // 2) Obtener SIEMPRE el ranking anual de la modalidad solicitada (no forzar fallback a Dobles)
                $resp = $this->annualService->get_annual_ranking_for((int)$modalidad_id, $last_season);
                if (is_wp_error($resp) || empty($resp)) {
                    $ranking_error = 'Problemas en la conexión a la BBDD o la API lo resolveremos lo antes posible, ¡Gracias por tu paciencia! anual.';
                } else {
                    $container = $resp;
                    $items = [];
                    if (is_array($resp)) {
                        $is_assoc = array_keys($resp) !== range(0, count($resp) - 1);
                        if (!$is_assoc) { $items = $resp; }
                        else {
                            if (isset($resp['items']) && is_array($resp['items'])) { $items = $resp['items']; }
                            elseif (isset($resp['data']['items']) && is_array($resp['data']['items'])) { $items = $resp['data']['items']; }
                            elseif (isset($resp['result']['items']) && is_array($resp['result']['items'])) { $items = $resp['result']['items']; }
                            elseif (isset($resp['ranking']['items']) && is_array($resp['ranking']['items'])) { $items = $resp['ranking']['items']; }
                        }
                    } elseif (is_object($resp)) {
                        if (isset($resp->items) && is_array($resp->items)) { $items = $resp->items; }
                        elseif (isset($resp->data) && is_object($resp->data) && isset($resp->data->items) && is_array($resp->data->items)) { $items = $resp->data->items; }
                        elseif (isset($resp->result) && is_object($resp->result) && isset($resp->result->items) && is_array($resp->result->items)) { $items = $resp->result->items; }
                        elseif (isset($resp->ranking) && is_object($resp->ranking) && isset($resp->ranking->items) && is_array($resp->ranking->items)) { $items = $resp->ranking->items; }
                    }
                    // Deduplicar
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
                    if (empty($uniq)) {
                        $ranking_error = 'No hay resultados para esta modalidad.';
                    } else {
                        $ranking_full = (object)[
                            'items'      => $uniq,
                            'totalCount' => count($uniq),
                            'modalidad'  => ($mod_labels[$modalidad_id] ?? null),
                        ];
                    }
                }
            } else {
                $ranking_error = 'Servicio de ranking anual no disponible.';
            }
        } else {
            $ranking_error = 'Cliente de API no disponible.';
        }

        // Variables para wrapper/template
        $current_view        = 'annual';
        // Usar ranking-display (misma UX que ELO) para la modalidad seleccionada
        $template_to_load = 'ranking-display.php';
        $page_size           = $pageSize;
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
