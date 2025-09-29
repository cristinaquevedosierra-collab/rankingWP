<?php
if (!defined('ABSPATH')) exit;

// Guards/stubs para editor/CLI cuando WP no está cargado
if (!function_exists('shortcode_atts')) { function shortcode_atts($pairs, $atts, $shortcode = '') { return array_merge($pairs, (array)$atts); } }
if (!function_exists('get_option')) { function get_option($name, $default = false) { return $default; } }
if (!function_exists('get_permalink')) { function get_permalink($id) { return ''; } }
if (!function_exists('remove_query_arg')) { function remove_query_arg($keys, $url = '') { return $url; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url = '') { return $url; } }
if (!function_exists('wp_parse_args')) { function wp_parse_args($args, $defaults = []) { return array_merge($defaults, (array)$args); } }
if (!function_exists('is_wp_error')) { function is_wp_error($thing) { return false; } }
if (!defined('FUTBOLIN_API_PATH')) { define('FUTBOLIN_API_PATH', dirname(__DIR__, 2) . '/'); }

/**
 * Shortcode: [futbolin_tournaments]
 * Renderiza lista de torneos y detalle (posiciones) usando la API nueva (IDs).
 */
class Futbolin_Tournaments_Shortcode {

    private $api;

    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api = new Futbolin_API_Client();
        } else {
            require_once FUTBOLIN_API_PATH . 'includes/core/class-futbolin-api-client.php';
            $this->api = new Futbolin_API_Client();
        }
    }

    public function render($atts, $view) {
        $atts = shortcode_atts([ 'wrap' => '1' ], $atts, 'futbolin_tournaments');

        $wrap      = $atts['wrap'] === '1';
        $torneo_id = isset($_GET['torneo_id']) ? max(0, intval($_GET['torneo_id'])) : 0;
        $page      = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page  = 25;

        // Página de perfil (para futuros enlaces)
        $opciones = get_option('mi_plugin_futbolin_options', []);
        $profile_page_url = '';
        if (!empty($opciones['player_profile_page_id'])) {
            $pid = (int)$opciones['player_profile_page_id'];
            $profile_page_url = $pid ? get_permalink($pid) : '';
        }

        $build_url = function(array $params = []) {
            $base = remove_query_arg(['torneo_id', 'page']);
            $args = wp_parse_args($params, ['view' => 'tournaments']);
            return add_query_arg($args, $base);
        };

        if ($torneo_id === 0) {
            // LISTA
            $items = [];
            $total = 0;

            if ($this->api && method_exists($this->api, 'get_torneos')) {
                $all = $this->api->get_torneos();
                if (!is_wp_error($all) && is_array($all)) {
                    $items = $all;
                    $total = count($all);
                }
            }

            $data = [
                'mode'     => 'list',
                'list'     => is_array($items) ? $items : [],
                'total'    => $total,
                'page'     => 1,
                'per_page' => ($total > 0 ? $total : 25),
                'build_url'=> $build_url,
            ];
        } else {
            // DETALLE
            $entries = [];
            if ($this->api && is_callable([$this->api, 'get_torneo_con_posiciones'])) {
                $raw = call_user_func([$this->api, 'get_torneo_con_posiciones'], $torneo_id);
                if (!is_wp_error($raw) && !empty($raw) && is_array($raw)) {
                    $entries = $raw;
                }
            } elseif ($this->api && is_callable([$this->api, 'get_tournament_with_positions'])) {
                $raw = call_user_func([$this->api, 'get_tournament_with_positions'], $torneo_id);
                if (!is_wp_error($raw) && !empty($raw) && is_array($raw)) {
                    $entries = $raw;
                }
            }
            $data = [
                'mode'       => 'detail',
                'torneo_id'  => $torneo_id,
                'entries'    => $entries,
                'build_url'  => $build_url,
            ];
        }

        ob_start();

        if ($wrap) {
            // Variables para el wrapper maestro
            $current_view         = ($torneo_id === 0) ? 'tournaments' : 'tournament-stats';
            $modalidades          = $this->api && method_exists($this->api, 'get_modalidades') ? ($this->api->get_modalidades() ?: []) : [];
            $modalidades_activas  = $opciones['ranking_modalities'] ?? [];

            $template_to_load = 'tournaments-display.php';

            include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        } else {
            // Render directo de la plantilla
            $data_local = $data; unset($data);
            $data = $data_local;
            include FUTBOLIN_API_PATH . 'includes/template-parts/tournaments-display.php';
        }

        return ob_get_clean();
    }
}
