<?php
if (!defined('ABSPATH')) exit;

class Futbolin_HallOfFame_Shortcode {

    public function render($atts, $view) {
        $param_q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
        $param_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $atts = shortcode_atts([
            'wrap' => '1',
        ], $atts, 'futbolin_halloffame');

        $wrap = ($atts['wrap'] === '1');

        // ===== Opciones / data para wrapper (aunque ocultemos sidebar, el wrapper las admite) =====
        $opciones            = get_option('mi_plugin_futbolin_options', []);
        $api                 = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        $modalidades         = $api && method_exists($api, 'get_modalidades') ? ($api->get_modalidades() ?: []) : [];
        $modalidades_activas = $opciones['ranking_modalities'] ?? [];

        // ===== Parámetros de UI =====
        $busqueda = isset($_GET['jugador_busqueda']) ? sanitize_text_field($_GET['jugador_busqueda']) : '';

        // pageSize unificado (acepta pageSize, page_size, 0=>25; 'all' y -1 => todos)
        $pageSizeRaw = isset($_GET['pageSize']) ? $_GET['pageSize']
                     : (isset($_GET['page_size']) ? $_GET['page_size']
                     : (isset($_GET['tpage_size']) ? $_GET['tpage_size'] : 25));
        if ($pageSizeRaw === 'all') $pageSizeRaw = -1;
        $page_size = (int)$pageSizeRaw;
        if ($page_size === 0) $page_size = 25;

        // página (acepta fpage/page)
        $page = isset($_GET['fpage']) ? (int)$_GET['fpage'] : (int)($_GET['page'] ?? 1);
        $page = max(1, $page);

        // URL perfil jugador (para los enlaces de nombre)
        $profile_page_url = '';
        if (!empty($opciones['player_profile_page_id'])) {
            $pid = (int)$opciones['player_profile_page_id'];
            $profile_page_url = $pid ? get_permalink($pid) : '';
        }

        // ===== Dataset HOF (y SSR inicial) =====
        $rankingData = $this->get_hall_of_fame_data(
            $busqueda,
            'win_rate_partidos', // orden base para posición estática
            'desc',
            $page,
            $page_size
        );

        // ===== Señales para el wrapper =====
        $current_view     = 'hall-of-fame';
        $template_to_load = 'hall-of-fame-display.php';
        $hide_sidebar     = true;   // sin menú lateral
        $show_back_btn    = true;   // botón "Volver" (el wrapper lo pinta)

        // Variables que usa el template:
        // - $rankingData, $busqueda, $page_size, $profile_page_url (y el wrapper puede usar $modalidades, $modalidades_activas)
        ob_start();
        if ($wrap) {
            include FUTBOLIN_API_PATH . 'includes/template-parts/ranking-wrapper.php';
        } else {
            include FUTBOLIN_API_PATH . 'includes/template-parts/hall-of-fame-display.php';
        }
        return ob_get_clean();
    }

    private function get_hall_of_fame_data(
        $busqueda = '',
        $order_by = 'win_rate_partidos',
        $order_dir = 'desc',
        $page = 1,
        $page_size = 25
    ) {
        // 1) Cargar transient y normalizar a array
        $raw = get_transient('futbolin_hall_of_fame_data');
        $all = [];
        if (is_array($raw)) {
            $all = isset($raw['items']) && is_array($raw['items']) ? $raw['items'] : $raw;
        } elseif (is_object($raw)) {
            $all = isset($raw->items) && is_array($raw->items) ? $raw->items : (array)$raw;
        }
        if (empty($all)) {
            return (object)[
                'items'           => [],
                'allItems'        => [],
                'totalCount'      => 0,
                'pageIndex'       => 1,
                'totalPages'      => 1,
                'hasPreviousPage' => false,
                'hasNextPage'     => false,
            ];
        }

        // 2) Normaliza a array asociativo
        $norm = [];
        foreach ($all as $p) { $norm[] = is_object($p) ? (array)$p : $p; }

        // 3) Reglas HOF: >=100 victorias (partidas ganadas)
        $norm = array_filter($norm, function($r) {
            $ganadas = (int)($r['partidas_ganadas'] ?? $r['wins'] ?? 0);
            return ($ganadas >= 100);});

        // 4) Filtro texto (SSR) opcional para primera carga
        if ($busqueda !== '') {
            $needle = mb_strtolower($busqueda, 'UTF-8');
            $norm = array_filter($norm, function($r) use ($needle){
                $name = mb_strtolower((string)($r['nombre'] ?? ''), 'UTF-8');
                return $needle === '' || mb_strpos($name, $needle) !== false;
            });
        }

        // 5) Posición estática por % ganados (desc)
        usort($norm, function($a,$b){
            $av = (float)($a['win_rate_partidos'] ?? 0);
            $bv = (float)($b['win_rate_partidos'] ?? 0);
            if ($av === $bv) return 0;
            return ($av < $bv) ? 1 : -1;
        });
        $pos = 1;
        foreach ($norm as &$r) {
            if (!isset($r['posicion_estatica'])) $r['posicion_estatica'] = $pos;
            $pos++;
        }
        unset($r);

        // 6) Paginación inicial (el JS luego rehace orden/filtrado cliente)
        $total = count($norm);
        if ((int)$page_size === -1) {
            $pages = 1; $page = 1; $items = array_values($norm);
        } else {
            $page_size = max(1, (int)$page_size);
            $pages  = (int)ceil($total / $page_size);
            $page   = max(1, min((int)$page, max(1, $pages)));
            $offset = ($page - 1) * $page_size;
            $items  = array_slice(array_values($norm), $offset, $page_size);
        }

        return (object)[
            'items'           => array_values($items),
            'allItems'        => array_values($norm),  // <- para el JS (script JSON en la vista)
            'totalCount'      => $total,
            'pageIndex'       => $page,
            'totalPages'      => $pages,
            'hasPreviousPage' => $page > 1,
            'hasNextPage'     => $page < $pages,
        ];
    }
}
