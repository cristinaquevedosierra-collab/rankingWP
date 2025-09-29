<?php
if (!defined('ABSPATH')) exit;

/**
 * Servicio de caché HTML por jugador/pestaña.
 * Guarda fragmentos renderizados de las pestañas del perfil para acelerar la carga.
 */
class Futbolin_Player_Cache_Service {
    const KEY_PREFIX = 'rf:player:tab:'; // usa transients: _transient_{KEY}
    const MAX_BYTES_FALLBACK = 716800; // ~700KB por defecto si no hay filtro
    const CHUNK_SIZE_B64 = 900000;     // ~0.9MB por fragmento base64 (similar a otras caches grandes)

    public static function dataset_ver(): string {
        $v = get_option('rf_dataset_ver');
        return $v ? (string)$v : '1';
    }

    /** Modo persistente (sin expiración): guarda en wp_options con autoload=no en lugar de transients */
    private static function persistent_mode(): bool {
        // Permite activarlo por opción o filtro. Por defecto ON para acelerar perfiles.
        $opt = get_option('rf_player_cache_persistent', 1);
        $on  = is_numeric($opt) ? (intval($opt) === 1) : (bool)$opt;
        /** Permitir personalización externa */
        $on = apply_filters('rf_player_cache_persistent', $on);
        return (bool)$on;
    }

    /** Helpers para escribir opciones con autoload=no en primera escritura */
    private static function option_set_noautoload(string $key, $value): bool {
        // Si no existe, add_option con autoload=no; si existe, update_option sin cambiar autoload
        $exists = get_option($key, null);
        if ($exists === null) {
            return (bool) add_option($key, $value, '', 'no');
        }
        return (bool) update_option($key, $value, false);
    }

    public static function ttl_seconds(): int {
        // ~6 actualizaciones/año -> ~cada 60 días por defecto
        $default = defined('DAY_IN_SECONDS') ? (60 * DAY_IN_SECONDS) : (60 * 24 * 3600);
        // Permitir personalización vía filtro
        $ttl = apply_filters('rf_player_cache_ttl_seconds', $default);
        $ttl = is_numeric($ttl) ? intval($ttl) : $default;
        // mínimo de seguridad: 1 hora
        return max(3600, $ttl);
    }

    public static function cache_key(int $player_id, string $tab): string {
        $tab = sanitize_key($tab);
        $ver = self::dataset_ver();
        // Revisión por pestaña (permite invalidar caches de una plantilla concreta sin tocar dataset_ver)
        $rev = apply_filters('rf_player_cache_tab_rev', self::default_tab_rev($tab), $tab);
        $rev = is_string($rev) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $rev) : '';
        $rev = ($rev !== '') ? $rev : '1';
        return self::KEY_PREFIX . 'v' . $ver . ':r' . $rev . ':' . intval($player_id) . ':' . $tab;
    }

    /** Revisión por defecto por pestaña; incrementar para forzar un MISS y regenerar fragmentos tras cambios de plantilla */
    private static function default_tab_rev(string $tab): string {
        switch (sanitize_key($tab)) {
            case 'hitos':
                // Revisado el 2025-09-22 (d) barras más intensas, separadas y un pelo más anchas; sin pliegue
                return '20250922d';
            default:
                return '1';
        }
    }

    public static function index_key(int $player_id): string {
        $ver = self::dataset_ver();
        return self::KEY_PREFIX . 'v' . $ver . ':' . intval($player_id) . ':idx';
    }

    public static function get_cached(int $player_id, string $tab): string {
        // Si la caché global está deshabilitada, no devolver nada (fuerza render en caliente)
        if (function_exists('rf_cache_enabled') && !rf_cache_enabled()) { return ''; }
        // No intentar cachear/leer pestañas excluidas
        if (!self::should_cache_tab($tab)) { return ''; }
        $key = self::cache_key($player_id, $tab);
        $persistent = self::persistent_mode();
    // 1) Intento simple
    $val = $persistent ? get_option($key, '') : get_transient($key);
    if (is_string($val) && $val !== '') { return $val; }
    // 2) Intento fragmentado (idx + parts)
    $idx = $persistent ? get_option($key . ':idx', false) : get_transient($key . ':idx');
        if ($idx !== false && is_array($idx) && isset($idx['chunks'])) {
            $chunks = (int)$idx['chunks'];
            if ($chunks > 0 && $chunks <= 2000) {
                $blob = '';
                for ($i = 0; $i < $chunks; $i++) {
                    $part = $persistent ? get_option($key . ':' . $i, false) : get_transient($key . ':' . $i);
                    if ($part === false) { return ''; }
                    $blob .= (string)$part;
                }
                $data = base64_decode($blob);
                if ($data === false) { return ''; }
                $un = function_exists('gzuncompress') ? @gzuncompress($data) : $data;
                if ($un === false) { return ''; }
                return (string)$un;
            }
        }
        // 3) Fallback de compatibilidad: si modo persistente y no hallado en options, mirar transients antiguos
        if ($persistent) {
            $valT = get_transient($key);
            if (is_string($valT) && $valT !== '') {
                // Migrar a options y limpiar transient
                self::option_set_noautoload($key, $valT);
                delete_transient($key);
                delete_transient($key . ':idx'); // por si existiera un idx viejo
                return $valT;
            }
            $idxT = get_transient($key . ':idx');
            if ($idxT !== false && is_array($idxT) && isset($idxT['chunks'])) {
                $chunks = (int)$idxT['chunks'];
                if ($chunks > 0 && $chunks <= 2000) {
                    $blob = '';
                    for ($i = 0; $i < $chunks; $i++) {
                        $part = get_transient($key . ':' . $i);
                        if ($part === false) { return ''; }
                        $blob .= (string)$part;
                    }
                    $data = base64_decode($blob);
                    if ($data !== false) {
                        $un = function_exists('gzuncompress') ? @gzuncompress($data) : $data;
                        if ($un !== false) {
                            // Migrar a options en formato single si cabe; si es muy grande, reescribir como fragmentado options
                            $html = (string)$un;
                            // Intentar set_cache con HTML ya reconstruido para que cree correctamente el backend persistente
                            self::set_cache($player_id, $tab, $html);
                            // Limpiar transients antiguos
                            delete_transient($key . ':idx');
                            for ($i = 0; $i < $chunks; $i++) { delete_transient($key . ':' . $i); }
                            delete_transient($key);
                            return $html;
                        }
                    }
                }
            }
        }
        return '';
    }

    public static function set_cache(int $player_id, string $tab, string $html): bool {
        if (!is_string($html) || trim($html) === '') return false;
        // Si la caché global está deshabilitada, simular éxito sin escribir
        if (function_exists('rf_cache_enabled') && !rf_cache_enabled()) return true;
        // Evitar cachear pestañas pesadas o excluidas
        if (!self::should_cache_tab($tab)) return false;

        $ttl = self::ttl_seconds();
        $persistent = self::persistent_mode();
        $key = self::cache_key($player_id, $tab);

        // Límite soft para guardar en una sola entrada; por encima usamos fragmentación
        $max_bytes = apply_filters('rf_player_cache_max_bytes', self::MAX_BYTES_FALLBACK);
        $max_bytes = is_numeric($max_bytes) ? intval($max_bytes) : self::MAX_BYTES_FALLBACK;

        // Total máximo permitido (para evitar crecimientos descontrolados). Por defecto 8MB.
        $max_total = apply_filters('rf_player_cache_max_total_bytes', 8 * 1024 * 1024);
        $max_total = is_numeric($max_total) ? intval($max_total) : (8 * 1024 * 1024);
        if (strlen($html) > $max_total) { return false; }

        // Suprimir errores de DB en el tramo de escritura
        global $wpdb;
        $prev = null;
        if (isset($wpdb) && method_exists($wpdb, 'suppress_errors')) {
            $prev = $wpdb->suppress_errors();
            $wpdb->suppress_errors(true);
        }

        $ok = false;
        if (strlen($html) <= max(65536, $max_bytes)) {
            // Escribir en una sola entrada
            if ($persistent) {
                $ok = self::option_set_noautoload($key, $html);
                // Borrar restos fragmentados previos si existen
                delete_option($key . ':idx');
            } else {
                $ok = (bool) set_transient($key, $html, $ttl);
                // Borrar restos fragmentados previos si existen
                delete_transient($key . ':idx');
            }
            // No es necesario limpiar parts si no sabemos el número previo; se irán expirando (transients) o quedarán borradas (options idx).
        } else {
            // Fragmentar: (gz)compress + base64 + shards de ~0.9MB
            $ser = $html; // ya es string
            $gz  = function_exists('gzcompress') ? gzcompress($ser, 3) : $ser;
            $b64 = base64_encode($gz);
            // Cap por seguridad
            if (strlen($b64) > ($max_total * 2)) { // base64 expande ~33%
                if (isset($wpdb) && method_exists($wpdb, 'suppress_errors')) { $wpdb->suppress_errors($prev); }
                return false;
            }
            $len = strlen($b64);
            $chunks = (int) ceil($len / self::CHUNK_SIZE_B64);
            if ($chunks > 2000) { if (isset($wpdb) && method_exists($wpdb, 'suppress_errors')) { $wpdb->suppress_errors($prev); } return false; }
            for ($i = 0; $i < $chunks; $i++) {
                $slice = substr($b64, $i * self::CHUNK_SIZE_B64, self::CHUNK_SIZE_B64);
                if ($persistent) { self::option_set_noautoload($key . ':' . $i, $slice); }
                else { set_transient($key . ':' . $i, $slice, $ttl); }
            }
            if ($persistent) {
                self::option_set_noautoload($key . ':idx', ['chunks' => $chunks]);
                // Borra la clave simple si existía (options)
                delete_option($key);
            } else {
                set_transient($key . ':idx', ['chunks' => $chunks], $ttl);
                // Borra la clave simple si existía (transient)
                delete_transient($key);
            }
            $ok = true;
        }

        if (isset($wpdb) && method_exists($wpdb, 'suppress_errors')) {
            $wpdb->suppress_errors($prev);
        }

        // Marca de índice por jugador para estadísticas
        if ($ok) {
            $idx = self::index_key($player_id);
            if ($persistent) {
                if (get_option($idx, null) === null) { add_option($idx, '1', '', 'no'); }
            } else {
                if (!get_transient($idx)) { set_transient($idx, '1', $ttl); }
            }
        }
        return $ok;
    }

    /** Elimina la caché del fragmento (incluye variantes fragmentadas) para un jugador/pestaña */
    public static function delete_cache(int $player_id, string $tab): void {
        if (function_exists('rf_cache_enabled') && !rf_cache_enabled()) { return; }
        $tab = sanitize_key($tab);
        $key = self::cache_key($player_id, $tab);
        $persistent = self::persistent_mode();
        // Borrar clave simple en ambos backends para limpieza total
        delete_option($key);
        delete_transient($key);
        // Borrar índice y partes fragmentadas si existen
        $idx = $persistent ? get_option($key . ':idx', false) : get_transient($key . ':idx');
        if (is_array($idx) && isset($idx['chunks'])) {
            $chunks = intval($idx['chunks']);
            // Borra índices en ambos
            delete_option($key . ':idx');
            delete_transient($key . ':idx');
            if ($chunks > 0 && $chunks <= 2000) {
                for ($i = 0; $i < $chunks; $i++) {
                    delete_option($key . ':' . $i);
                    delete_transient($key . ':' . $i);
                }
            }
        } else {
            // Aun sin índice, intenta borrar un rango pequeño de partes por limpieza defensiva
            for ($i = 0; $i < 5; $i++) {
                delete_option($key . ':' . $i);
                delete_transient($key . ':' . $i);
            }
            delete_option($key . ':idx');
            delete_transient($key . ':idx');
        }
    }

    /** Precálculo de todas las pestañas para un jugador. Devuelve resumen. */
    public static function precache_player(int $player_id, array $tabs = []): array {
    if (!$player_id) return ['cached'=>0, 'tabs'=>[]];
    if (empty($tabs)) {
            // Por defecto: las 6 pestañas. Permitir override por filtro.
            $tabs = apply_filters('rf_player_precache_default_tabs', ['glicko','summary','stats','hitos','history','torneos']);
        }

        // Preparación de datos base (igual que en AJAX público)
        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        if (!class_exists('Futbolin_Player_Processor')) {
            require_once dirname(__DIR__) . '/processors/class-futbolin-player-processor.php';
        }

        $api = new Futbolin_API_Client();
        $player1_data = $api->get_datos_jugador($player_id);
        if (!$player1_data) return ['cached'=>0, 'tabs'=>[], 'error'=>'jugador_no_encontrado'];
        $partidos1_items   = $api->get_partidos_jugador($player_id);
        $posiciones1_items = $api->get_posiciones_jugador($player_id);

        try { $processor = new \Futbolin_Player_Processor($player1_data, $partidos1_items, $posiciones1_items); }
        catch (\Throwable $e) { return ['cached'=>0, 'tabs'=>[], 'error'=>'processor_failed']; }

        $categoria_dobles = 'rookie';
        $categoria_individual = 'rookie';
        $categoria_dobles_display = 'Rookie';
        if (method_exists($api, 'get_jugador_puntuacion_categoria')) {
            $puntuacion_categorias = $api->get_jugador_puntuacion_categoria($player_id);
            if (!empty($puntuacion_categorias) && is_array($puntuacion_categorias)) {
                foreach ($puntuacion_categorias as $cat_data) {
                    if (!isset($cat_data->categoria) || !isset($cat_data->modalidad)) continue;
                    $category_clean_name = strtolower(str_replace(' ', '', $cat_data->categoria));
                    if ($category_clean_name === 'elitegm') { $category_clean_name = 'gm'; }
                    if ($cat_data->modalidad === 'Dobles') { $categoria_dobles = $category_clean_name; }
                    elseif ($cat_data->modalidad === 'Individual') { $categoria_individual = $category_clean_name; }
                }
                $categoria_dobles_display = ($categoria_dobles === 'gm') ? 'Elite GM' : ucfirst($categoria_dobles);
            }
        }

        $plugin_options = get_option('mi_plugin_futbolin_options', []);
        $ranking_page_url = isset($plugin_options['ranking_page_id']) ? get_permalink($plugin_options['ranking_page_id']) : home_url('/');

        $tpl_map = [
            'glicko'    => FUTBOLIN_API_PATH . 'includes/template-parts/player-glicko-rankings-tab.php',
            'summary'   => FUTBOLIN_API_PATH . 'includes/template-parts/player-summary.php',
            'stats'     => FUTBOLIN_API_PATH . 'includes/template-parts/player-stats.php',
            'hitos'     => FUTBOLIN_API_PATH . 'includes/template-parts/player-hitos-tab.php',
            'history'   => FUTBOLIN_API_PATH . 'includes/template-parts/player-history.php',
            'torneos'   => FUTBOLIN_API_PATH . 'includes/template-parts/player-torneos-tab.php',
        ];

        $cached = 0; $out_tabs = []; $reasons = []; $sizes = [];
        foreach ($tabs as $tab) {
            if (!isset($tpl_map[$tab]) || !file_exists($tpl_map[$tab])) { $reasons[$tab] = 'template_missing'; continue; }
            if (!self::should_cache_tab($tab)) { $reasons[$tab] = 'not_allowed'; continue; }
            // Render aislado por pestaña
            ob_start();
            try {
                $api_client = $api;
                $jugador_id = $player_id;
                $player_positions = is_array($posiciones1_items) ? $posiciones1_items : (array)$posiciones1_items;
                // variables usadas en templates
                $processor = $processor;
                $player_id = $player_id; // sombra local
                $categoria_dobles = $categoria_dobles;
                $categoria_individual = $categoria_individual;
                $categoria_dobles_display = $categoria_dobles_display;
                $ranking_page_url = $ranking_page_url;
                include $tpl_map[$tab];
                $html = ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                $html = '';
            }
            // Medición de tamaños y plan de escritura
            $plan = 'skip'; $len_html = 0; $len_b64 = null; $chunks = null; $cause = '';
            if (is_string($html)) {
                $len_html = strlen($html);
                $max_bytes = apply_filters('rf_player_cache_max_bytes', self::MAX_BYTES_FALLBACK);
                $max_bytes = is_numeric($max_bytes) ? intval($max_bytes) : self::MAX_BYTES_FALLBACK;
                $max_total = apply_filters('rf_player_cache_max_total_bytes', 8 * 1024 * 1024);
                $max_total = is_numeric($max_total) ? intval($max_total) : (8 * 1024 * 1024);
                if ($len_html > $max_total) {
                    $plan = 'too_large_total';
                    $cause = 'too_large_total';
                } else {
                    $threshold = max(65536, $max_bytes);
                    if ($len_html <= $threshold) {
                        $plan = 'single';
                        $len_b64 = null; $chunks = 0;
                    } else {
                        $plan = 'chunked';
                        $gz  = function_exists('gzcompress') ? gzcompress($html, 3) : $html;
                        $b64 = base64_encode($gz);
                        $len_b64 = strlen($b64);
                        $chunks = (int) ceil($len_b64 / self::CHUNK_SIZE_B64);
                    }
                }
            }
            $sizes[$tab] = [
                'html'   => $len_html,
                'b64'    => $len_b64,
                'chunks' => $chunks,
                'plan'   => $plan,
            ];

            if (is_string($html) && trim($html) !== '') {
                if ($cause === 'too_large_total') {
                    $reasons[$tab] = 'write_failed:too_large_total';
                } else if (self::set_cache($jugador_id, $tab, $html)) {
                    $cached++; $out_tabs[] = $tab; $reasons[$tab] = 'cached';
                } else {
                    $reasons[$tab] = 'write_failed';
                }
            } else {
                $reasons[$tab] = 'empty_html';
            }
        }
        return ['cached'=>$cached, 'tabs'=>$out_tabs, 'reasons'=>$reasons, 'sizes'=>$sizes];
    }

    /** Determina si una pestaña debe entrar en la caché de fragmentos (ahora permite history con almacenamiento fragmentado) */
    public static function should_cache_tab(string $tab): bool {
        $tab = sanitize_key($tab);
        // Permitir el resto por defecto. Se puede excluir por filtro según necesidad.
        $allow = true;
        /** Permitir personalización */
        return (bool) apply_filters('rf_player_cache_allow_tab', $allow, $tab);
    }
}
