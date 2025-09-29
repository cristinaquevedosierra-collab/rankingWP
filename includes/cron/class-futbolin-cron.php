<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Cron {
    const EVENT_CLEANUP = 'futbolin_cleanup_old_transients_cron';
    const EVENT_PRECACHE = 'futbolin_precache_top_cron';
    const EVENT_PRECACHE_ALL = 'futbolin_precache_all_cron';

    public static function init() {
        // Registro de eventos
        add_action(self::EVENT_CLEANUP, [__CLASS__, 'cleanup_old_transients_runner']);
        add_action(self::EVENT_PRECACHE, [__CLASS__, 'precache_top_runner']);
    add_action(self::EVENT_PRECACHE_ALL, [__CLASS__, 'precache_all_runner']);
        // Al hacer bust del dataset, programa una precarga automática
        add_action('rf_dataset_cache_busted', [__CLASS__, 'schedule_precache_after_bump'], 10, 1);
        // Al cargar, asegurar programación de limpieza si está habilitada
        $opts = get_option('mi_plugin_futbolin_options', []);
        $cleanup_enabled = isset($opts['rf_cron_cleanup_enabled']) ? (int)$opts['rf_cron_cleanup_enabled'] : 1;
        if ($cleanup_enabled) { self::schedule_cleanup_daily(); }
        // Si la precarga ALL está habilitada y no hay evento, prográmalo para arrancar pronto
        $all_enabled = isset($opts['rf_cron_precache_all_enabled']) ? (int)$opts['rf_cron_precache_all_enabled'] : 0;
        if ($all_enabled && !wp_next_scheduled(self::EVENT_PRECACHE_ALL)) {
            wp_schedule_single_event(time() + 60, self::EVENT_PRECACHE_ALL);
        }
    }

    public static function schedule_cleanup_daily() {
        if (!wp_next_scheduled(self::EVENT_CLEANUP)) {
            wp_schedule_event(time() + 120, 'daily', self::EVENT_CLEANUP);
        }
    }

    public static function unschedule_cleanup() {
        $ts = wp_next_scheduled(self::EVENT_CLEANUP);
        if ($ts) { wp_unschedule_event($ts, self::EVENT_CLEANUP); }
    }

    public static function schedule_precache_after_bump($new_ver) {
        $opts = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($opts['rf_cron_precache_enabled']) ? (int)$opts['rf_cron_precache_enabled'] : 1;
        if (!$enabled) return;
        // Evita programar si el cron ya está en cola
        if (!wp_next_scheduled(self::EVENT_PRECACHE)) {
            wp_schedule_single_event(time() + 60, self::EVENT_PRECACHE, ['ver' => (string)$new_ver, 'offset' => 0]);
        }
    }

    public static function cleanup_old_transients_runner() {
        $opts = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($opts['rf_cron_cleanup_enabled']) ? (int)$opts['rf_cron_cleanup_enabled'] : 1;
        if (!$enabled) return;
        global $wpdb;
        // Solo aplica si el modo persistente está activo (así no borramos backend en uso)
        $persist = true;
        if (class_exists('Futbolin_Player_Cache_Service')) {
            try {
                $ref = new ReflectionClass('Futbolin_Player_Cache_Service');
                if ($ref->hasMethod('persistent_mode')) {
                    $m = $ref->getMethod('persistent_mode');
                    $m->setAccessible(true);
                    $persist = (bool)$m->invoke(null);
                }
            } catch (Throwable $e) { $persist = true; }
        }
        if (!$persist) return;
        // Borrar únicamente transients relacionados con pestañas de perfil de jugadores
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rf:player:tab:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rf:player:tab:%'");
    }

    public static function precache_top_runner($ver = null, $offset = 0) {
        // Protección de tiempo
        @set_time_limit(180);
        $start = microtime(true);
        // Lee opciones y aplica máximos por defecto (pedido del usuario)
        $opts = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($opts['rf_cron_precache_enabled']) ? (int)$opts['rf_cron_precache_enabled'] : 1;
        if (!$enabled) return;
        $batch_n = isset($opts['rf_precache_top_n']) ? (int)$opts['rf_precache_top_n'] : 100; // máximo por defecto
        $time_budget = isset($opts['rf_precache_time_budget']) ? (int)$opts['rf_precache_time_budget'] : 180; // máximo por defecto
        $batch_n = max(1, min(500, $batch_n)); // sanidad
        $max_seconds = max(30, min(600, $time_budget));
        $tabs = apply_filters('rf_player_precache_default_tabs', ['glicko','summary','stats','hitos','history','torneos']);

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        if (!class_exists('Futbolin_Player_Cache_Service')) {
            require_once dirname(__DIR__) . '/services/class-futbolin-player-cache-service.php';
        }
        $api = new \Futbolin_API_Client();
        // Construir IDs combinando ambas modalidades
        $ids = [];
        foreach (['1','2'] as $mod) {
            try {
                $rows = $api->get_ranking_por_modalidad_esp_g2_all((int)$mod);
                $arr = (array) json_decode(json_encode($rows), true);
                foreach ($arr as $it) {
                    if (isset($it['jugadorId'])) { $ids[] = (int)$it['jugadorId']; }
                    elseif (isset($it['id']))     { $ids[] = (int)$it['id']; }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($offset > 0) { $ids = array_slice($ids, $offset); }
        $ids = array_slice($ids, 0, $batch_n);

        $done = 0;
        foreach ($ids as $pid) {
            \Futbolin_Player_Cache_Service::precache_player((int)$pid, $tabs);
            $done++;
            if ((microtime(true) - $start) > $max_seconds) { break; }
        }

        // Si quedan más jugadores, reprogramar con nuevo offset
        if ($done === $batch_n) {
            $next_offset = (int)$offset + $batch_n;
            wp_schedule_single_event(time() + 60, self::EVENT_PRECACHE, ['ver' => (string)$ver, 'offset' => $next_offset]);
        }
    }

    /* ===== Precarga escalonada de TODOS los jugadores (background) ===== */
    private static function ids_store_key_meta(): string { return 'rf:precache_all_ids:idx'; }
    private static function ids_store_key_part($i): string { return 'rf:precache_all_ids:' . intval($i); }
    private static function ids_store_clear(): void {
        $idx = get_option(self::ids_store_key_meta(), false);
        if (is_array($idx) && isset($idx['chunks'])) {
            $chunks = (int)$idx['chunks'];
            delete_option(self::ids_store_key_meta());
            for ($i = 0; $i < $chunks; $i++) { delete_option(self::ids_store_key_part($i)); }
        }
    }
    private static function ids_store_save(array $ids): void {
        // Serializa, comprime y guarda por partes (como en cache de pestañas)
        $json = wp_json_encode(array_values(array_unique(array_map('intval', $ids))));
        if (!is_string($json)) { return; }
        $gz  = function_exists('gzcompress') ? gzcompress($json, 3) : $json;
        $b64 = base64_encode($gz);
        $chunkSize = 900000; // ~0.9MB
        $len = strlen($b64);
        $chunks = (int) ceil($len / $chunkSize);
        for ($i = 0; $i < $chunks; $i++) {
            $slice = substr($b64, $i * $chunkSize, $chunkSize);
            add_option(self::ids_store_key_part($i), $slice, '', 'no');
        }
        add_option(self::ids_store_key_meta(), ['chunks' => $chunks], '', 'no');
    }
    private static function ids_store_load(): array {
        $idx = get_option(self::ids_store_key_meta(), false);
        if (!is_array($idx) || !isset($idx['chunks'])) { return []; }
        $chunks = (int)$idx['chunks'];
        if ($chunks <= 0 || $chunks > 2000) { return []; }
        $blob = '';
        for ($i = 0; $i < $chunks; $i++) {
            $part = get_option(self::ids_store_key_part($i), '');
            if (!is_string($part) || $part === '') { return []; }
            $blob .= $part;
        }
        $data = base64_decode($blob);
        if ($data === false) { return []; }
        $un = function_exists('gzuncompress') ? @gzuncompress($data) : $data;
        if ($un === false) { return []; }
        $arr = json_decode($un, true);
        return is_array($arr) ? array_values(array_unique(array_map('intval', $arr))) : [];
    }

    private static function build_all_player_ids(): array {
        // Combina IDs de ambas modalidades del ranking nacional (cobertura amplia)
        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        $api = new \Futbolin_API_Client();
        $ids = [];
        foreach (['1','2'] as $mod) {
            try {
                $rows = $api->get_ranking_por_modalidad_esp_g2_all((int)$mod);
                $arr = (array) json_decode(json_encode($rows), true);
                foreach ($arr as $it) {
                    if (isset($it['jugadorId'])) { $ids[] = (int)$it['jugadorId']; }
                    elseif (isset($it['id']))     { $ids[] = (int)$it['id']; }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        return $ids;
    }

    /** Inicializa la lista de IDs para la precarga ALL si aún no existe (devuelve true si la creó) */
    public static function init_all_ids_if_empty(): bool {
        $existing = self::ids_store_load();
        if (!empty($existing)) { return false; }
        $ids = self::build_all_player_ids();
        if (empty($ids)) { return false; }
        self::ids_store_clear();
        self::ids_store_save($ids);
        update_option('rf_precache_all_cursor', 0, false);
        update_option('rf_precache_all_total', count($ids), false);
        update_option('rf_precache_all_started_at', time(), false);
        update_option('rf_precache_all_tab_counts', [
            'glicko'=>0,'summary'=>0,'stats'=>0,'hitos'=>0,'history'=>0,'torneos'=>0
        ], false);
        return true;
    }

    public static function schedule_precache_all_now(): void {
        if (!wp_next_scheduled(self::EVENT_PRECACHE_ALL)) {
            wp_schedule_single_event(time() + 60, self::EVENT_PRECACHE_ALL);
        }
    }

    public static function unschedule_precache_all(): void {
        $ts = wp_next_scheduled(self::EVENT_PRECACHE_ALL);
        if ($ts) { wp_unschedule_event($ts, self::EVENT_PRECACHE_ALL); }
    }

    public static function precache_all_runner(): void {
        // Config y toggles
        $opts = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($opts['rf_cron_precache_all_enabled']) ? (int)$opts['rf_cron_precache_all_enabled'] : 0;
        if (!$enabled) { return; }
        @set_time_limit(0);
        $time_budget = isset($opts['rf_precache_time_budget']) ? (int)$opts['rf_precache_time_budget'] : 180;
        $max_seconds = max(30, min(600, $time_budget));
        $start = microtime(true);

        // Cargar o construir lista de IDs
        $ids = self::ids_store_load();
        if (empty($ids)) {
            $ids = self::build_all_player_ids();
            self::ids_store_clear();
            self::ids_store_save($ids);
            // Reset estado
            update_option('rf_precache_all_cursor', 0, false);
            update_option('rf_precache_all_total', count($ids), false);
            update_option('rf_precache_all_started_at', time(), false);
            // Reset contadores por pestaña
            update_option('rf_precache_all_tab_counts', [
                'glicko'=>0,'summary'=>0,'stats'=>0,'hitos'=>0,'history'=>0,'torneos'=>0
            ], false);
        }
        $total = (int) get_option('rf_precache_all_total', count($ids));
        if ($total <= 0) { $total = count($ids); update_option('rf_precache_all_total', $total, false); }
        $cursor = (int) get_option('rf_precache_all_cursor', 0);
        $tabs = apply_filters('rf_player_precache_default_tabs', ['glicko','summary','stats','hitos','history','torneos']);

        $done_this_run = 0;
        $tab_counts = get_option('rf_precache_all_tab_counts', []);
        if (!is_array($tab_counts)) { $tab_counts = []; }
        for ($i = $cursor; $i < $total; $i++) {
            $pid = (int)$ids[$i];
            $res = \Futbolin_Player_Cache_Service::precache_player($pid, $tabs);
            if (is_array($res) && !empty($res['reasons'])) {
                foreach ($res['reasons'] as $t => $reason) {
                    if ($reason === 'cached') {
                        if (!isset($tab_counts[$t])) { $tab_counts[$t] = 0; }
                        $tab_counts[$t]++;
                    }
                }
            }
            $done_this_run++;
            $cursor = $i + 1;
            if ((microtime(true) - $start) > $max_seconds) { break; }
        }
        update_option('rf_precache_all_cursor', $cursor, false);
        update_option('rf_precache_all_last_run', time(), false);
        update_option('rf_precache_all_tab_counts', $tab_counts, false);

        if ($cursor < $total) {
            // Reprograma siguiente tramo
            wp_schedule_single_event(time() + 60, self::EVENT_PRECACHE_ALL);
        } else {
            update_option('rf_precache_all_finished_at', time(), false);
            // Programar repetición semanal para reconstruir lista (nuevos jugadores)
            // Limpiar almacén para forzar reconstrucción en la próxima ejecución
            self::ids_store_clear();
            delete_option('rf_precache_all_cursor');
            delete_option('rf_precache_all_total');
            wp_schedule_single_event(time() + 7*DAY_IN_SECONDS, self::EVENT_PRECACHE_ALL);
        }
    }
}

// Inicializa hooks
add_action('plugins_loaded', ['Futbolin_Cron', 'init']);
// Programa limpieza diaria al activar el plugin
register_activation_hook(__FILE__, ['Futbolin_Cron', 'schedule_cleanup_daily']);
// Desprograma al desactivar
register_deactivation_hook(__FILE__, ['Futbolin_Cron', 'unschedule_cleanup']);
