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

        // Carga perezosa de pestañas del perfil de jugador (HTML)
        add_action('wp_ajax_futbolin_load_player_tab',        [$this, 'load_player_tab_callback']);
        add_action('wp_ajax_nopriv_futbolin_load_player_tab',  [$this, 'load_player_tab_callback']);
    }

    /** Indica si el modo mantenimiento está activo para AJAX públicos */
    private function is_maintenance_on(): bool {
        // Admin siempre puede pasar para probar, salvo si fuerza 'ver como usuario'
        $view_as_user = isset($_REQUEST['rf_view_as']) && $_REQUEST['rf_view_as'] === 'user';
        if (function_exists('current_user_can') && current_user_can('manage_options') && !$view_as_user) {
            return false;
        }
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

        // No bloquear en vivo si falta/expira el nonce (petición pública): validar pero no matar
        $nonce_ok = (bool) check_ajax_referer('futbolin_nonce', 'security', false);

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if (mb_strlen($term) < 3) {
            wp_send_json_error(['message' => esc_html__('Término de búsqueda demasiado corto.', 'ranking-futbolin')]);
        }

        $players = $this->api_client ? $this->api_client->buscar_jugadores($term) : [];
        if (is_wp_error($players) || empty($players)) {
            wp_send_json_error(['message' => esc_html__('No se encontraron jugadores.', 'ranking-futbolin')]);
        }

        // Éxito: devolvemos siempre el listado; el cliente ya interpreta el JSON
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

    /** Carga perezosa de una pestaña del perfil de jugador, devolviendo HTML renderizado */
    public function load_player_tab_callback() {
        $t0 = microtime(true);
        // Buffer exterior para capturar cualquier salida espuria de otros plugins/temas y no romper JSON
        $outer_level = ob_get_level();
        ob_start();
        if ($this->is_maintenance_on()) {
            // Este endpoint devuelve HTML en success (html_friendly)
            // Limpiar buffer exterior antes de responder
            while (ob_get_level() > $outer_level) { ob_end_clean(); }
            $this->maintenance_response(true);
        }

        check_ajax_referer('futbolin_nonce', 'security');

        $player_id = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
        $tab_key   = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : '';

        if ($player_id <= 0 || $tab_key === '') {
            while (ob_get_level() > $outer_level) { ob_end_clean(); }
            wp_send_json_error(['message' => esc_html__('Parámetros inválidos.', 'ranking-futbolin')]);
        }

        if (!$this->api_client || !class_exists('Futbolin_Player_Processor')) {
            while (ob_get_level() > $outer_level) { ob_end_clean(); }
            wp_send_json_error(['message' => esc_html__('Servicio no disponible.', 'ranking-futbolin')]);
        }

        // Flags de control de caché (permitidos desde cliente para depuración controlada)
        $rf_tab_cache_bypass = isset($_POST['rf_tab_cache_bypass']) && ($_POST['rf_tab_cache_bypass'] === '1' || $_POST['rf_tab_cache_bypass'] === 1 || $_POST['rf_tab_cache_bypass'] === true);
        $rf_tab_cache_reset  = isset($_POST['rf_tab_cache_reset'])  && ($_POST['rf_tab_cache_reset']  === '1' || $_POST['rf_tab_cache_reset']  === 1 || $_POST['rf_tab_cache_reset']  === true);

        // Sirve desde caché si existe (HTML fragment cache por jugador/pestaña)
        if (!class_exists('Futbolin_Player_Cache_Service')) {
            $svc = dirname(__DIR__) . '/services/class-futbolin-player-cache-service.php';
            if (file_exists($svc)) { require_once $svc; }
        }
        if (class_exists('Futbolin_Player_Cache_Service')) {
            // Si se solicita reset explícito, borra y prosigue a render
            if ($rf_tab_cache_reset) {
                \Futbolin_Player_Cache_Service::delete_cache($player_id, $tab_key);
            }
            $cached = $rf_tab_cache_bypass ? '' : \Futbolin_Player_Cache_Service::get_cached($player_id, $tab_key);
            if (!$rf_tab_cache_bypass && is_string($cached) && trim($cached) !== '') {
                // Opcional: métrica de debug
                $rf_lazy_debug_flag = false;
                if (isset($_GET['rf_lazy_debug']) && $_GET['rf_lazy_debug'] == '1') { $rf_lazy_debug_flag = true; }
                if (isset($_POST['rf_lazy_debug']) && ($_POST['rf_lazy_debug'] === '1' || $_POST['rf_lazy_debug'] === 1)) { $rf_lazy_debug_flag = true; }
                if ($rf_lazy_debug_flag) {
                    $elapsed = (int)round((microtime(true) - $t0) * 1000);
                    $cached .= '<!-- rf_player_tab cache=HIT ver=' . esc_html(\Futbolin_Player_Cache_Service::dataset_ver()) . ' ms=' . $elapsed . ' -->';
                }
                while (ob_get_level() > $outer_level) { ob_end_clean(); }
                wp_send_json_success(['html' => $cached, 'cache' => 'hit', 'ms' => (int)round((microtime(true) - $t0) * 1000)]);
            }
        }

        // Carga de datos por pestaña (evitar trabajo innecesario)
        $player1_data = $this->api_client->get_datos_jugador($player_id);
        if (!$player1_data) {
            while (ob_get_level() > $outer_level) { ob_end_clean(); }
            wp_send_json_error(['message' => esc_html__('Jugador no encontrado.', 'ranking-futbolin')]);
        }
        $needs_partidos   = in_array($tab_key, ['summary','stats','history'], true);
        $needs_posiciones = in_array($tab_key, ['summary','stats','torneos'], true);

        $partidos1_items   = $needs_partidos   ? $this->api_client->get_partidos_jugador($player_id)   : [];
        $posiciones1_items = $needs_posiciones ? $this->api_client->get_posiciones_jugador($player_id) : [];

        $processor = null;
        if ($tab_key !== 'hitos') {
            // La pestaña Hitos usa su propio flujo/endpoint; evitamos construir el Processor para reducir latencia
            try {
                $processor = new \Futbolin_Player_Processor($player1_data, $partidos1_items, $posiciones1_items);
            } catch (\Throwable $e) {
                while (ob_get_level() > $outer_level) { ob_end_clean(); }
                wp_send_json_error(['message' => esc_html__('No se pudo preparar el procesador del jugador.', 'ranking-futbolin')]);
            }
        }

        // Categorías (para chips/textos en algunas plantillas)
        $categoria_dobles = 'rookie';
        $categoria_individual = 'rookie';
        $categoria_dobles_display = 'Rookie';
        if (method_exists($this->api_client, 'get_jugador_puntuacion_categoria')) {
            $puntuacion_categorias = $this->api_client->get_jugador_puntuacion_categoria($player_id);
            if (!empty($puntuacion_categorias) && is_array($puntuacion_categorias)) {
                foreach ($puntuacion_categorias as $cat_data) {
                    if (!isset($cat_data->categoria) || !isset($cat_data->modalidad)) continue;
                    $category_clean_name = strtolower(str_replace(' ', '', $cat_data->categoria));
                    if ($category_clean_name === 'elitegm') { $category_clean_name = 'gm'; }
                    if ($cat_data->modalidad === 'Dobles') {
                        $categoria_dobles = $category_clean_name;
                    } elseif ($cat_data->modalidad === 'Individual') {
                        $categoria_individual = $category_clean_name;
                    }
                }
                $categoria_dobles_display = ($categoria_dobles === 'gm') ? 'Elite GM' : ucfirst($categoria_dobles);
            }
        }

        // URL de ranking para enlaces de vuelta
        $plugin_options = get_option('mi_plugin_futbolin_options', []);
        $ranking_page_url = isset($plugin_options['ranking_page_id']) ? get_permalink($plugin_options['ranking_page_id']) : home_url('/');

        // Variables que consumen las plantillas
        $api_client = $this->api_client; // disponible dentro de include
        $jugador_id = $player_id;        // algunas plantillas esperan $jugador_id
        $player_positions = is_array($posiciones1_items) ? $posiciones1_items : (array)$posiciones1_items;

        // Mapeo de pestañas a plantillas
        $tpl_map = [
            'glicko'    => FUTBOLIN_API_PATH . 'includes/template-parts/player-glicko-rankings-tab.php',
            'summary'   => FUTBOLIN_API_PATH . 'includes/template-parts/player-summary.php',
            'stats'     => FUTBOLIN_API_PATH . 'includes/template-parts/player-stats.php',
            'hitos'     => FUTBOLIN_API_PATH . 'includes/template-parts/player-hitos-tab.php',
            'history'   => FUTBOLIN_API_PATH . 'includes/template-parts/player-history.php',
            'torneos'   => FUTBOLIN_API_PATH . 'includes/template-parts/player-torneos-tab.php',
        ];

        if (!isset($tpl_map[$tab_key]) || !file_exists($tpl_map[$tab_key])) {
            while (ob_get_level() > $outer_level) { ob_end_clean(); }
            wp_send_json_error(['message' => esc_html__('Pestaña desconocida o no disponible.', 'ranking-futbolin')]);
        }

        // Render del fragmento
        ob_start();
        try {
            $processor = $processor; // puede ser null en Hitos
            $player_id = $player_id; // para plantillas que lean $player_id
            $categoria_dobles = $categoria_dobles;
            $categoria_individual = $categoria_individual;
            $categoria_dobles_display = $categoria_dobles_display;
            $ranking_page_url = $ranking_page_url;
            // No marcar render ligero para Hitos; mantener flujo completo para asegurar datos correctos
            // Contexto mínimo estándar
            if (!isset($jugador_id)) { $jugador_id = $player_id; }
            if (!isset($player_positions)) { $player_positions = array(); }
            include $tpl_map[$tab_key];
            $html = ob_get_clean();
        } catch (\Throwable $e) {
            // Log compacto del fallo real para diagnóstico (sin trazar completa)
            try {
                error_log('[FUTBOLIN_AJAX] render_tab_error tab=' . $tab_key . ' player_id=' . (int)$player_id . ' msg=' . $e->getMessage());
                if (function_exists('rf_log')) { rf_log('AJAX render_tab_error', ['tab'=>$tab_key,'player_id'=>(int)$player_id,'msg'=>$e->getMessage()], 'error'); }
            } catch (\Throwable $ie) {}
            ob_end_clean();
            while (ob_get_level() > $outer_level) { ob_end_clean(); }
            wp_send_json_error(['message' => esc_html__('Error al renderizar la pestaña, recarga la página para intentarlo de nuevo.', 'ranking-futbolin')]);
        }

        // Si por cualquier motivo no hay contenido, envía error para que el cliente no deje el panel en blanco
        if (!is_string($html) || trim($html) === '') {
            while (ob_get_level() > $outer_level) { ob_end_clean(); }
            wp_send_json_error(['message' => esc_html__('Contenido no disponible en este momento.', 'ranking-futbolin')]);
        }

        // Guardar en caché para futuras solicitudes (si procede por tamaño/tipo de pestaña); respetar bypass (no guarda)
        if (!$rf_tab_cache_bypass && class_exists('Futbolin_Player_Cache_Service') && \Futbolin_Player_Cache_Service::should_cache_tab($tab_key)) {
            \Futbolin_Player_Cache_Service::set_cache($player_id, $tab_key, $html);
        }

        // Marca de debug opcional
        $rf_lazy_debug_flag2 = false;
        if (isset($_GET['rf_lazy_debug']) && $_GET['rf_lazy_debug'] == '1') { $rf_lazy_debug_flag2 = true; }
        if (isset($_POST['rf_lazy_debug']) && ($_POST['rf_lazy_debug'] === '1' || $_POST['rf_lazy_debug'] === 1)) { $rf_lazy_debug_flag2 = true; }
        if ($rf_lazy_debug_flag2) {
            $elapsed = (int)round((microtime(true) - $t0) * 1000);
            $html .= '<!-- rf_player_tab cache=MISS ver=' . esc_html(class_exists('Futbolin_Player_Cache_Service') ? \Futbolin_Player_Cache_Service::dataset_ver() : 'na') . ' ms=' . $elapsed . ' -->';
        }
        while (ob_get_level() > $outer_level) { ob_end_clean(); }
        wp_send_json_success(['html' => $html, 'cache' => 'miss', 'ms' => (int)round((microtime(true) - $t0) * 1000)]);
    }
}

new Futbolin_Ajax();
