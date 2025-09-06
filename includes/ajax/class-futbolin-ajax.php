<?php
/**
 * Archivo: includes/ajax/class-futbolin-ajax.php
 * Descripción: Peticiones AJAX públicas (frontend) del plugin.
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Ajax {
    /** @var Futbolin_API_Client|null */
    private $api_client = null;

    public function __construct() {
        if (class_exists('Futbolin_API_Client')) {
            $this->api_client = new Futbolin_API_Client();
        }

        // Hooks AJAX
        add_action('wp_ajax_futbolin_search_players',        [$this, 'search_players_callback']);
        add_action('wp_ajax_nopriv_futbolin_search_players',  [$this, 'search_players_callback']);

        add_action('wp_ajax_futbolin_filter_hall_of_fame',        [$this, 'filter_hall_of_fame_callback']);
        add_action('wp_ajax_nopriv_futbolin_filter_hall_of_fame',  [$this, 'filter_hall_of_fame_callback']);
    }

    /** Indica si el modo mantenimiento está activo para AJAX públicos */
    private function is_maintenance_on(): bool {
        $flag = get_option('futbolin_public_maintenance', false);
        return (bool)$flag;
    }

    /**
     * Respuesta estándar en modo mantenimiento.
     * @param bool $html_friendly Si true, devuelve un HTML placeholder dentro de success.
     */
    private function maintenance_response(bool $html_friendly = false) {
        if ($html_friendly) {
            $html = '<div class="futbolin-card" style="text-align:center;padding:24px;border:2px dashed #d63638;background:#fff5f5;border-radius:10px;">'
                  . '<div style="font-size:42px;line-height:1;margin-bottom:8px;">🛠️</div>'
                  . '<h3 style="margin:0 0 6px 0;color:#b30000;">' . esc_html__('Estamos en mantenimiento', 'ranking-futbolin') . '</h3>'
                  . '<p style="margin:0;color:#444;">' . esc_html__('Volvemos lo antes posible.', 'ranking-futbolin') . '</p>'
                  . '</div>';
            wp_send_json_success(['html' => $html, 'maintenance' => true]);
        }

        wp_send_json_error([
            'code'    => 'maintenance',
            'message' => esc_html__('Modo mantenimiento activo. Vuelve a intentarlo más tarde.', 'ranking-futbolin'),
        ]);
    }

    /** Buscador de jugadores (live search) */
    public function search_players_callback() {
        if ($this->is_maintenance_on()) {
            // Para buscador, devolvemos error normal
            $this->maintenance_response(false);
        }

        check_ajax_referer('futbolin_nonce', 'security');

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if (mb_strlen($term) < 3) {
            wp_send_json_error(['message' => esc_html__('Término de búsqueda demasiado corto.', 'ranking-futbolin')]);
        }

        $players = $this->api_client ? $this->api_client->buscar_jugadores($term) : [];
        if (is_wp_error($players) || empty($players)) {
            wp_send_json_error(['message' => esc_html__('No se encontraron jugadores.', 'ranking-futbolin')]);
        }

        wp_send_json_success($players);
    }

    /** Filtrado/orden/paginación del Hall of Fame desde cache */
    public function filter_hall_of_fame_callback() {
        if ($this->is_maintenance_on()) {
            // Este endpoint devuelve HTML en success normalmente => friendly
            $this->maintenance_response(true);
        }

        check_ajax_referer('futbolin_nonce', 'security');

        $busqueda = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        $order_by = isset($_POST['orderBy']) ? sanitize_key($_POST['orderBy']) : 'win_rate_partidos';
        $order_dir = (isset($_POST['orderDir']) && strtolower(sanitize_text_field($_POST['orderDir'])) === 'asc') ? 'asc' : 'desc';
        $size = isset($_POST['pageSize']) ? sanitize_text_field(wp_unslash($_POST['pageSize'])) : '25'; // '25','50','100','all'
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;

        $raw = get_transient('futbolin_hall_of_fame_data');
        $all = [];
        if (is_array($raw)) {
            $all = (isset($raw['items']) && is_array($raw['items'])) ? $raw['items'] : $raw;
        } elseif (is_object($raw)) {
            $all = (isset($raw->items) && is_array($raw->items)) ? $raw->items : (array)$raw;
        }
        $all = is_array($all) ? $all : [];

        // Inclusión reforzada: >=100 victorias (la lógica ya lo aplica al calcular, doble check por robustez)
        $all = array_values(array_filter($all, function($r){
            return (int)($r['partidas_ganadas'] ?? 0) >= 100;
        }));

        // Búsqueda (case-insensitive, nombre)
        if ($busqueda !== '') {
            $needle = mb_strtolower($busqueda, 'UTF-8');
            $all = array_values(array_filter($all, function($r) use ($needle){
                $name = mb_strtolower((string)($r['nombre'] ?? ''), 'UTF-8');
                return ($needle === '') || (mb_strpos($name, $needle) !== false);
            }));
        }

        // Orden
        $valid_keys = ['win_rate_partidos','partidas_ganadas','partidas_jugadas','win_rate_competiciones','competiciones_ganadas'];
        $key = in_array($order_by, $valid_keys, true) ? $order_by : 'win_rate_partidos';

        usort($all, function($a,$b) use ($key, $order_dir){
            $av = (float)($a[$key] ?? 0);
            $bv = (float)($b[$key] ?? 0);
            if ($av === $bv) return 0;
            $cmp = ($av < $bv) ? -1 : 1;
            return ($order_dir === 'asc') ? $cmp : -$cmp;
        });

        // Paginación
        $page_size = (strtolower($size) === 'all') ? PHP_INT_MAX : max(1, intval($size));
        $total = count($all);
        $pages = ($page_size === PHP_INT_MAX) ? 1 : max(1, (int)ceil($total / $page_size));
        $page  = min(max(1, $page), $pages);
        $offset = ($page - 1) * (($page_size === PHP_INT_MAX) ? $total : $page_size);
        $items = array_slice($all, $offset, ($page_size === PHP_INT_MAX) ? $total : $page_size);

        // Render filas
        $html = '<div class="ranking-rows">';
        foreach ($items as $r) {
            $name = esc_html($r['nombre'] ?? '');
            $pj   = intval($r['partidas_jugadas'] ?? 0);
            $pg   = intval($r['partidas_ganadas'] ?? 0);
            $wrp  = number_format((float)($r['win_rate_partidos'] ?? 0), 2);
            $cj   = intval($r['competiciones_jugadas'] ?? 0);
            $cg   = intval($r['competiciones_ganadas'] ?? 0);
            $wrc  = number_format((float)($r['win_rate_competiciones'] ?? 0), 2);

            $html .= '<div class="ranking-row">'
                  .  '<div class="ranking-cell ranking-player-name-cell">' . $name . '</div>'
                  .  '<div class="ranking-cell">' . $pj . '</div>'
                  .  '<div class="ranking-cell">' . $pg . '</div>'
                  .  '<div class="ranking-cell">' . $wrp . '<span>%</span></div>'
                  .  '<div class="ranking-cell">' . $cj . '</div>'
                  .  '<div class="ranking-cell">' . $cg . '</div>'
                  .  '<div class="ranking-cell">' . $wrc . '<span>%</span></div>'
                  .  '</div>';
        }
        $html .= '</div>';

        wp_send_json_success([
            'html'      => $html,
            'total'     => $total,
            'page'      => $page,
            'totalPage' => $pages,
        ]);
    }
}

new Futbolin_Ajax();
