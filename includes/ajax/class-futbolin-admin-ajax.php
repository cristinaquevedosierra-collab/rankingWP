
<?php
/**
 * Archivo Resultante: class-futbolin-admin-ajax.php (Fusionado y Corregido)
 * Ruta: includes/ajax/class-futbolin-admin-ajax.php
 *
 * Gestiona las llamadas AJAX exclusivas del panel de administración:
 * - Cálculo de Temporadas
 * - Cálculo de Finales (por lotes, persistiendo en opciones públicas)
 * - Cálculo del Hall of Fame (por lotes)
 */
if (!defined('ABSPATH')) exit;

// Nota: No definir stubs de funciones de WP en este archivo. Este código se ejecuta dentro de WordPress
// (admin), y cualquier stub podría interferir con funciones pluggable o del core.

class Futbolin_Admin_Ajax {

    // Generar Campeones (AJAX)
    // Registramos la acción en el constructor via init hook


/* --------- Opciones grandes (chunk + compresión) para evitar max_allowed_packet --------- */
private function blob_option_key($name, $suffix = '') {
    return $suffix ? "{$name}__{$suffix}" : $name;
}
private function blob_delete_option($name) {
    $chunks = get_option($this->blob_option_key($name, 'chunks'), 0);
    if ($chunks && is_numeric($chunks)) {
        for ($i=1; $i<=intval($chunks); $i++) {
            delete_option($this->blob_option_key($name, "part_{$i}"));
        }
    }
    delete_option($this->blob_option_key($name, 'chunks'));
    delete_option($name); // por si existía la opción "plana"
}
private function blob_set_option($name, $value) {
    $this->blob_delete_option($name);
    $serialized = maybe_serialize($value);
    // gzip ↓ suele comprimir muy bien estructuras repetitivas
    $compressed = function_exists('gzcompress') ? gzcompress($serialized, 6) : $serialized;
    $chunkSize = 950 * 1024; // ~950KB por parte (WP y MySQL quedan holgados)
    $len = strlen($compressed);
    $chunks = max(1, (int)ceil($len / $chunkSize));
    for ($i=0; $i<$chunks; $i++) {
        $slice = substr($compressed, $i*$chunkSize, $chunkSize);
        update_option($this->blob_option_key($name, "part_".($i+1)), $slice, 'no');
    }
    update_option($this->blob_option_key($name, 'chunks'), $chunks, 'no');
    return true;
}
private function blob_get_option($name, $default = []) {
    $chunks = get_option($this->blob_option_key($name, 'chunks'), 0);
    if (!$chunks) {
        $legacy = get_option($name, null);
        return is_null($legacy) ? $default : $legacy;
    }
    $buf = '';
    for ($i=1; $i<=$chunks; $i++) {
        $part = get_option($this->blob_option_key($name, "part_{$i}"), '');
        $buf .= (string)$part;
    }
    if ($buf === '') return $default;
    $decompressed = (function_exists('gzuncompress') ? @gzuncompress($buf) : $buf);
    $value = maybe_unserialize($decompressed);
    return is_array($value) ? $value : $default;
}
public function __construct() {
        add_action('wp_ajax_futbolin_generate_champions', [$this, 'ajax_generate_champions']);
        // --- Temporadas ---
        add_action('wp_ajax_futbolin_calculate_seasons', [$this, 'calculate_seasons']);

        // --- Finales (nombres ya existentes en tu proyecto) ---
        add_action('wp_ajax_futbolin_start_finals_calculation',        [$this, 'start_finals_calculation']);
        add_action('wp_ajax_futbolin_run_finals_calculation_step',     [$this, 'run_finals_calculation_step']);
        add_action('wp_ajax_futbolin_cancel_finals_calc',              [$this, 'cancel_finals_calculation']);

        // --- Herramientas por competitionTypeId (NUEVO) ---
        add_action('wp_ajax_futbolin_scan_comp_types',        [$this, 'scan_comp_types']);
        add_action('wp_ajax_futbolin_build_reports_by_types', [$this, 'build_reports_by_types']);

        // --- Hall of Fame (nombres largos ya existentes) ---
        add_action('wp_ajax_futbolin_start_hall_of_fame_calculation',    [$this, 'start_hall_of_fame_calculation']);
        add_action('wp_ajax_futbolin_run_hall_of_fame_calculation_step', [$this, 'run_hall_of_fame_calculation_step']);
        add_action('wp_ajax_futbolin_cancel_hall_of_fame_calculation',   [$this, 'cancel_hall_of_fame_calculation']);

        // --- Hall of Fame (ALIAS para nombres cortos que aparecen en tu main.js) ---
        add_action('wp_ajax_futbolin_start_hof_calculation',           [$this, 'start_hall_of_fame_calculation']);
        add_action('wp_ajax_futbolin_run_hof_calculation_step',        [$this, 'run_hall_of_fame_calculation_step']);
        add_action('wp_ajax_futbolin_cancel_hof_calc',                 [$this, 'cancel_hall_of_fame_calculation']);

        // Utilidad existente para exportar modalidades detectadas
        add_action('wp_ajax_futbolin_export_modalidades', [$this, 'export_modalidades_csv']);
    
        // === NUEVOS: Acciones de Datos (local)
        add_action('wp_ajax_futbolin_sync_tournaments', [$this, 'ajax_sync_tournaments']);
        add_action('wp_ajax_futbolin_clear_tournaments_cache', [$this, 'ajax_clear_tournaments_cache']);
        add_action('wp_ajax_futbolin_clear_players_cache', [$this, 'ajax_clear_players_cache']);
        add_action('wp_ajax_futbolin_cache_stats',        [$this, 'ajax_cache_stats']);
            // Limpieza proactiva de transients antiguos de pestañas de perfil (modo persistente)
            add_action('wp_ajax_futbolin_cleanup_old_transients', [$this, 'ajax_cleanup_old_transients']);
            add_action('wp_ajax_futbolin_clear_all_caches',   [$this, 'ajax_clear_all_caches']);
    // Control manual de versión de dataset (reset/ajuste del contador visible)
    add_action('wp_ajax_futbolin_set_dataset_version',[$this, 'ajax_set_dataset_version']);
    // Precálculo de perfiles
    add_action('wp_ajax_futbolin_precache_player',    [$this, 'ajax_precache_player']);
    add_action('wp_ajax_futbolin_precache_top',       [$this, 'ajax_precache_top']);
    // Nuevo: obtener IDs del Top para precarga progresiva desde el admin
    add_action('wp_ajax_futbolin_precache_top_ids',   [$this, 'ajax_precache_top_ids']);
    // Guardar ajustes de cron
    add_action('wp_ajax_futbolin_save_cron_settings', [$this, 'ajax_save_cron_settings']);
    // Precarga ALL (background)
    add_action('wp_ajax_futbolin_precache_all_toggle',  [$this, 'ajax_precache_all_toggle']);
    add_action('wp_ajax_futbolin_precache_all_start',   [$this, 'ajax_precache_all_start']);
    add_action('wp_ajax_futbolin_precache_all_stop',    [$this, 'ajax_precache_all_stop']);
    add_action('wp_ajax_futbolin_precache_all_status',  [$this, 'ajax_precache_all_status']);
        // Nuevo: obtener ajustes actuales de cron para resincronizar la UI tras recarga
        add_action('wp_ajax_futbolin_get_cron_settings',    [$this, 'ajax_get_cron_settings']);
        }

    /* ------------------------------ Utilidades ------------------------------ */

    /**
     * Verifica nonce admitiendo ambos nombres de parámetro: 'security' y 'admin_nonce'.
     */
    private function verify_nonce_or_die() {
        if (isset($_POST['admin_nonce'])) {
            check_ajax_referer('futbolin_admin_nonce', 'admin_nonce');
            return;
        }
        // Por compatibilidad con tu JS actual:
        check_ajax_referer('futbolin_admin_nonce', 'security');
    }

    /**
     * Extrae un array de IDs de torneos de forma robusta (torneoId | id | Id).
     */
    private function extract_tournament_ids($all_tournaments) {
        $ids = array_values(array_filter(array_map(function($t){
            if (is_array($t)) {
                return $t['torneoId'] ?? $t['id'] ?? $t['Id'] ?? null;
            } elseif (is_object($t)) {
                return $t->torneoId ?? $t->id ?? $t->Id ?? null;
            }
            return null;
        }, (array)$all_tournaments)));
        return $ids;
    }

    /* ---------------------------- Calcular temporadas --------------------------- */

    public function calculate_seasons() {
        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No tienes permisos.']);
        }

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        $api_client = new Futbolin_API_Client();
        $all_tournaments = $api_client->get_torneos();

        if (is_wp_error($all_tournaments) || empty($all_tournaments)) {
            wp_send_json_error(['message' => 'No se pudieron obtener los torneos de la API.']);
        }

        // Acepta objeto o array:
        $all_tournaments = (array)$all_tournaments;
        $seasons = [];
        foreach ($all_tournaments as $t) {
            $temporada = null;
            if (is_object($t))   { $temporada = $t->temporada ?? null; }
            elseif (is_array($t)){ $temporada = $t['temporada'] ?? null; }
            if ($temporada !== null) { $seasons[] = $temporada; }
        }

        $unique_seasons = array_values(array_unique($seasons));
        sort($unique_seasons);

        update_option('futbolin_total_seasons_count', $unique_seasons);

        wp_send_json_success([
            'message' => "Cálculo completado. Se encontraron " . count($unique_seasons) . " temporadas únicas.",
            'total_seasons' => count($unique_seasons)
        ]);
    }

    /* --------------------------- Cálculo de Finales ---------------------------- */

    public function start_finals_calculation() {
        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'No tienes permisos.']); }

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        $api_client = new Futbolin_API_Client();
        $all_tournaments = $api_client->get_torneos();

        if (is_wp_error($all_tournaments) || empty($all_tournaments)) {
            wp_send_json_error(['message' => 'Error: No se pudieron obtener los torneos de la API.']);
        }

        $tournament_ids = $this->extract_tournament_ids($all_tournaments);

        update_option('futbolin_finals_total_tournaments_count', count($tournament_ids), 'no');
        update_option('futbolin_finals_tournament_ids_to_process', $tournament_ids, 'no');
        $this->blob_set_option('futbolin_finals_reports_data', []);
        update_option('futbolin_finals_calc_status', 'in_progress', 'no');
        update_option('futbolin_finals_processed_tournaments_count', 0, 'no');

        wp_send_json_success([
            'message'  => 'Proceso iniciado. Obtenidos ' . count($tournament_ids) . ' torneos.',
            'finished' => false,
            'progress' => 0
        ]);
    }

    public function run_finals_calculation_step() {
        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'No tienes permisos.']); }

        @set_time_limit(300);

        $tournament_ids   = get_option('futbolin_finals_tournament_ids_to_process', []);
        $processed_count  = (int) get_option('futbolin_finals_processed_tournaments_count', 0);
        $reports_data     = $this->blob_get_option('futbolin_finals_reports_data', []);
        $total_tournaments= (int) get_option('futbolin_finals_total_tournaments_count', 0);

        // Si no quedan IDs -> finalizar: consolidar y guardar opciones públicas
        if (empty($tournament_ids)) {

            // Construcción final de informes por IDs (nuevo motor)
            if (!class_exists('Futbolin_API_Client')) {
                require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
            }
            if (!class_exists('Futbolin_Reports_Engine')) {
                require_once dirname(__DIR__) . '/processors/class-futbolin-reports-engine.php';
            }

            $engine  = new Futbolin_Reports_Engine(new Futbolin_API_Client());
            $reports = $engine->build_all_reports();

            // Guardar EXACTAMENTE con las claves que la vista pública lee:
            update_option('futbolin_report_open_individual_finals',      $reports['open_individual_finals'] ?? [], 'no');
            update_option('futbolin_report_open_doubles_player_finals',  $reports['open_doubles_player_finals'] ?? [], 'no');
            update_option('futbolin_report_open_doubles_pair_finals',    $reports['open_doubles_pair_finals'] ?? [], 'no');
            update_option('futbolin_report_championships_open',          $reports['championships_open'] ?? [], 'no');
            update_option('futbolin_report_championships_rookie',        $reports['championships_rookie'] ?? [], 'no');
            update_option('futbolin_report_championships_resto',         $reports['championships_resto'] ?? [], 'no');

            update_option('futbolin_finals_calc_status', 'complete');
            update_option('futbolin_finals_last_run', date('d/m/Y H:i'), 'no');

            // Limpiar temporales
            delete_option('futbolin_finals_tournament_ids_to_process');
            delete_option('futbolin_finals_processed_tournaments_count');
            $this->blob_delete_option('futbolin_finals_reports_data');
            delete_option('futbolin_finals_total_tournaments_count');

            wp_send_json_success([
                'finished' => true,
                'message'  => '¡Proceso de cálculo de finales completado!',
                'progress' => 100
            ]);
            return;
        }

        // Procesar en lotes
        $batch_size = 10;
        $ids_to_process_now = array_slice($tournament_ids, 0, $batch_size);

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        if (!class_exists('Futbolin_Finals_Processor')) {
            // El processor procesa posiciones por torneo y acumula en $reports_data (compatible con el motor nuevo)
            require_once dirname(__DIR__) . '/processors/class-futbolin-finals-processor.php';
        }

        $api_client = new Futbolin_API_Client();
        $processor  = new Futbolin_Finals_Processor($api_client);

        foreach ($ids_to_process_now as $torneo_id) {
            $positions = $api_client->get_tournament_with_positions($torneo_id);

            // Aceptar objeto/array; dejamos que el processor lo entienda:
            if (!empty($positions)) {
                $reports_data = $processor->process_tournament_positions($positions, $reports_data);
            }
        }

        $new_processed_count = $processed_count + count($ids_to_process_now);
        $progress = ($total_tournaments > 0) ? round(($new_processed_count / $total_tournaments) * 100) : 100;

        $remaining_ids = array_slice($tournament_ids, count($ids_to_process_now));
        update_option('futbolin_finals_tournament_ids_to_process', $remaining_ids, 'no');
        $this->blob_set_option('futbolin_finals_reports_data', $reports_data);
        update_option('futbolin_finals_processed_tournaments_count', $new_processed_count, 'no');

        wp_send_json_success([
            'finished' => false,
            'message'  => "Procesados {$new_processed_count} de {$total_tournaments} torneos.",
            'progress' => $progress
        ]);
    }

    public function cancel_finals_calculation() {
        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'No tienes permisos.']); }

        delete_option('futbolin_finals_calc_status');
        delete_option('futbolin_finals_tournament_ids_to_process');
        delete_option('futbolin_finals_processed_tournaments_count');
        $this->blob_delete_option('futbolin_finals_reports_data');
        delete_option('futbolin_finals_total_tournaments_count');

        wp_send_json_success(['message' => 'Proceso de cálculo de finales cancelado y reiniciado.']);
    }

    /** Escanea todos los competitionTypeId y guarda catálogo en fut_competition_types_catalog */
    public function scan_comp_types() {
        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'No tienes permisos.']); }

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        $api = new Futbolin_API_Client();

        $seen = []; // type_id => ['label'=>'', 'count'=>N]

        // preferible paginado si lo tienes; fallback a get_torneos()
        $page = 1;
        $page_size = 100;
        $guard = 200;
        $tournaments = [];

        while ($guard-- > 0) {
            $list = $api->get_tournaments_paginated($page, $page_size);
            if (!is_wp_error($list) && is_object($list) && isset($list->torneos, $list->torneos->items) && is_array($list->torneos->items) && count($list->torneos->items)) {
                $tournaments = array_merge($tournaments, $list->torneos->items);
                if (count($list->torneos->items) < $page_size) break;
                $page++;
            } else {
                if ($page === 1) {
                    $fallback = $api->get_torneos();
                    if (is_array($fallback)) $tournaments = $fallback;
                }
                break;
            }
        }

        foreach ((array)$tournaments as $t) {
            $tid = is_object($t) ? ($t->torneoId ?? null) : null;
            if (!$tid) continue;
            $rows = $api->get_tournament_with_positions($tid);
            if (!is_array($rows)) continue;

            foreach ($rows as $r) {
                if (!is_object($r)) continue;
                $type_id = null;
                foreach (['competitionTypeId','tipoCompeticionId','competicionTipoId','tipoId','tipo_id'] as $p) {
                    if (isset($r->$p)) { $type_id = (int)$r->$p; break; }
                }
                if ($type_id === null) continue;

                $label = null;
                foreach (['competitionTypeName','tipoCompeticionNombre','nombreCompeticionTipo','nombreCompeticion'] as $lp) {
                    if (!empty($r->$lp)) { $label = (string)$r->$lp; break; }
                }
                if ($label === null) $label = 'Tipo '.$type_id;

                if (!isset($seen[$type_id])) $seen[$type_id] = ['label'=>$label, 'count'=>0];
                $seen[$type_id]['count']++;
            }
        }

        update_option('futbolin_competition_types_catalog', $seen, 'no');
        wp_send_json_success(['message'=>'Tipos detectados: '.count($seen), 'types'=>$seen]);
    }

    /** Construye y guarda los informes usando SOLO IDs configurados */
    public function build_reports_by_types() {
        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'No tienes permisos.']); }

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        if (!class_exists('Futbolin_Reports_Engine')) {
            require_once dirname(__DIR__) . '/processors/class-futbolin-reports-engine.php';
        }

        $engine  = new Futbolin_Reports_Engine(new Futbolin_API_Client());
        $reports = $engine->build_all_reports();

        update_option('futbolin_report_open_individual_finals',      $reports['open_individual_finals'] ?? [], 'no');
        update_option('futbolin_report_open_doubles_player_finals',  $reports['open_doubles_player_finals'] ?? [], 'no');
        update_option('futbolin_report_open_doubles_pair_finals',    $reports['open_doubles_pair_finals'] ?? [], 'no');
        update_option('futbolin_report_championships_open',          $reports['championships_open'] ?? [], 'no');
        update_option('futbolin_report_championships_rookie',        $reports['championships_rookie'] ?? [], 'no');
        update_option('futbolin_report_championships_resto',         $reports['championships_resto'] ?? [], 'no');

        update_option('futbolin_finals_last_run', date('d/m/Y H:i'), 'no');
        wp_send_json_success(['message'=>'Informes reconstruidos por IDs.']);
    }

    /* --------------------------- Hall of Fame (HOF) --------------------------- */

    public function start_hall_of_fame_calculation() {
        // 0904: Hall of Fame deshabilitado temporalmente
        $this->verify_nonce_or_die();
        wp_send_json_success(['disabled' => true, 'message' => __('Hall of Fame deshabilitado temporalmente.', 'ranking-futbolin')]);
        return;

        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'No tienes permisos.']); }

        set_transient('futbolin_hall_of_fame_data', [], 7 * DAY_IN_SECONDS);
        update_option('futbolin_hall_of_fame_rebuilding', 'yes');
        update_option('futbolin_hall_of_fame_calculation_status', 'in_progress');
        update_option('futbolin_last_processed_player_id', 0);

        wp_send_json_success(['message' => 'Cálculo del Hall of Fame iniciado.']);
    }

    public function run_hall_of_fame_calculation_step() {
        // 0904: Hall of Fame deshabilitado temporalmente
        $this->verify_nonce_or_die();
        wp_send_json_success(['disabled' => true, 'message' => __('Hall of Fame deshabilitado temporalmente.', 'ranking-futbolin')]);
        return;

        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'No tienes permisos.']); }

        @set_time_limit(0);

        $hall_of_fame_data = get_transient('futbolin_hall_of_fame_data') ?: [];
        $last_processed_id = (int) get_option('futbolin_last_processed_player_id', 0);
        $next_player_id    = $last_processed_id + 1;
        $max_players_to_process = 1500;
        $batch_size = 20;

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        if (!class_exists('Futbolin_Stats_Processor')) {
            require_once dirname(__DIR__) . '/processors/class-futbolin-stats-processor.php';
        }

        $api_client = new Futbolin_API_Client();
        $processor  = new Futbolin_Stats_Processor($api_client);

        $end_id = min($next_player_id + $batch_size, $max_players_to_process + 1);

        // Seguridad: si no hay nada que procesar, conserva último ID
        $last_id_processed_this_loop = $next_player_id - 1;

        for ($id = $next_player_id; $id < $end_id; $id++) {
            $player_stats = $processor->get_player_hall_of_fame_stats($id);
            if ($player_stats) {
                $hall_of_fame_data[] = $player_stats;
            }
            $last_id_processed_this_loop = $id;
        }

        update_option('futbolin_last_processed_player_id', $last_id_processed_this_loop);

        $final_data = $processor->sort_hall_of_fame_data($hall_of_fame_data);
        set_transient('futbolin_hall_of_fame_data', $final_data, 7 * DAY_IN_SECONDS);

        $total_players = count($hall_of_fame_data);
        $progress = min(100, round(($last_id_processed_this_loop / $max_players_to_process) * 100));

        if ($last_id_processed_this_loop >= $max_players_to_process) {
            update_option('futbolin_hall_of_fame_calculation_status', 'complete');
            update_option('futbolin_hall_of_fame_total_count', $total_players);
            delete_option('futbolin_hall_of_fame_rebuilding');

            wp_send_json_success(['finished' => true, 'message' => 'Cálculo del Hall of Fame finalizado.', 'progress' => 100]);
            return;
        }

        wp_send_json_success([
            'finished' => false,
            'message'  => 'Procesados jugadores hasta el ID ' . ($last_id_processed_this_loop) . '.',
            'progress' => $progress,
            'next_step'=> $last_id_processed_this_loop + 1
        ]);
    }

    public function cancel_hall_of_fame_calculation() {
        // 0904: Hall of Fame deshabilitado temporalmente
        $this->verify_nonce_or_die();
        wp_send_json_success(['disabled' => true, 'message' => __('Hall of Fame deshabilitado temporalmente.', 'ranking-futbolin')]);
        return;

        $this->verify_nonce_or_die();
        if (!current_user_can('manage_options')) { wp_send_json_error(['message' => 'No tienes permisos.']); }

        delete_option('futbolin_hall_of_fame_calculation_status');
        delete_option('futbolin_last_processed_player_id');
        delete_option('futbolin_hall_of_fame_rebuilding');

        wp_send_json_success(['message' => 'Proceso de cálculo del Hall of Fame cancelado.']);
    }

    /* FUNCION PARA EXTRAER EL NOMBRE DE LAS COMPETICIONES DENTRO DE LOS CAMPEONATOS NO BORRAR*/
    public function export_modalidades_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('No permitido', 403);
        }
        nocache_headers();

        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        if (!class_exists('Futbolin_Normalizer')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-normalizer.php';
        }

        $api = new Futbolin_API_Client();

        // Cabecera ampliada con IDs/types
        $rows = [];
        $rows[] = [
            'torneo_id',
            'torneo_nombre',
            'competicion_id',
            'nombre_competicion',
            'key_detectada',
            'prioridad',
            'filas',
            'tipo_id',               // coarse
            'tipo_key_detallado',    // ej. espana_amateur_dobles
            'tipo_id_detallado'      // ej. 311
        ];

        $page = 1;
        $page_size = 100;
        $safety_pages = 200;

        while ($safety_pages-- > 0) {
            $list  = $api->get_tournaments_paginated($page, $page_size);
            $items = [];

            if (!is_wp_error($list) && is_object($list) && isset($list->torneos, $list->torneos->items) && is_array($list->torneos->items)) {
                $items = $list->torneos->items;
            } elseif ($page === 1) {
                $fallback = $api->get_torneos();
                if (!is_wp_error($fallback) && is_array($fallback)) {
                    $items = $fallback;
                }
            }

            if (empty($items)) break;

            foreach ($items as $t) {
                $tid   = isset($t->torneoId) ? (int)$t->torneoId : 0;
                $tname = isset($t->nombreTorneo) ? (string)$t->nombreTorneo : '';
                if ($tid <= 0) continue;

                $det = $api->get_tournament_with_positions($tid);
                if (is_wp_error($det) || empty($det) || !is_array($det)) continue;

                // Deduplicación por competición dentro del torneo
                $seen = [];
                $counts = [];
                $dataByKey = [];

                foreach ($det as $row) {
                    if (!is_object($row)) continue;

                    $compId = isset($row->competicionId) ? (string)$row->competicionId : '';
                    $raw    = isset($row->nombreCompeticion) ? (string)$row->nombreCompeticion : '';
                    if ($raw === '') continue;

                    $keyDedupe = $tid . '::' . ($compId !== '' ? ('id:' . $compId) : ('raw:' . $raw));

                    if (!isset($counts[$keyDedupe])) {
                        $counts[$keyDedupe] = 0;
                        $dataByKey[$keyDedupe] = [
                            'compId' => $compId,
                            'raw'    => $raw,
                        ];
                    }
                    $counts[$keyDedupe]++;
                    $seen[$keyDedupe] = true;
                }

                foreach ($seen as $k => $_) {
                    $compId = $dataByKey[$k]['compId'];
                    $raw    = $dataByKey[$k]['raw'];

                    $mapped = Futbolin_Normalizer::map_competicion($raw);
                    $key    = $mapped['key']  ?? 'otros';
                    $prio   = $mapped['prio'] ?? 90;

                    // NUEVO: coarse & detailed
                    $tipo_id = Futbolin_Normalizer::type_id_from_key($key);
                    $dkey    = Futbolin_Normalizer::detailed_type_key($raw);
                    $dtipoid = Futbolin_Normalizer::detailed_type_id($dkey);

                    $rows[] = [
                        $tid,
                        $tname,
                        ($compId !== '' ? $compId : ''),
                        $raw,
                        $key,
                        $prio,
                        (int)$counts[$k],
                        $tipo_id,
                        $dkey,
                        $dtipoid,
                    ];
                }
            }

            if (count($items) < $page_size) break;
            $page++;
        }

        // Descarga CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=modalidades_detectadas.csv');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 para Excel
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        foreach ($rows as $r) {
            $clean = array_map(function($v){
                $v = (string)$v;
                $v = str_replace(["\r","\n"], [' ',' '], $v);
                return $v;
            }, $r);
            fputcsv($out, $clean, ';');
        }
        fclose($out);
        exit;
    }

    /* ===== Acciones de Datos: Torneos/Caché (NUEVO) ===== */
    public function ajax_sync_tournaments() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        if (!class_exists('Futbolin_API_Client')) wp_send_json_error(['message'=>'Cliente API no disponible'], 500);

        $api = new Futbolin_API_Client();
    $list = $api->get_torneos();
        if (is_wp_error($list)) wp_send_json_error(['message'=>$list->get_error_message()]);
        if (!is_array($list)) wp_send_json_error(['message'=>'Respuesta inesperada de API']);

        $clean = [];
        foreach ($list as $row) {
            $o = is_object($row) ? $row : (object)$row;
            $tid   = isset($o->torneoId) ? intval($o->torneoId) : (isset($o->id) ? intval($o->id) : 0);
            $name  = isset($o->nombreTorneo) ? (string)$o->nombreTorneo : (isset($o->nombre) ? (string)$o->nombre : '');
            $fecha = isset($o->fecha) ? (string)$o->fecha : (isset($o->torneoFecha) ? (string)$o->torneoFecha : '');
            $lugar = isset($o->lugar) ? (string)$o->lugar : '';
            $temp  = isset($o->temporada) ? (string)$o->temporada : '';
            if ($tid > 0 && $name !== '') {
                $clean[] = (object)[
                    'torneoId'      => $tid,
                    'nombreTorneo'  => $name,
                    'fecha'         => $fecha,
                    'lugar'         => $lugar,
                    'temporada'     => $temp,
                ];
            }
        }
        update_option('futbolin_cached_tournaments', $clean, 'no');
        update_option('futbolin_cached_tournaments_last_sync', current_time('mysql'), 'no');
        wp_send_json_success(['count'=>count($clean), 'last_sync'=>get_option('futbolin_cached_tournaments_last_sync')]);
    }

    public function ajax_clear_tournaments_cache() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        delete_option('futbolin_cached_tournaments');
        delete_option('futbolin_cached_tournaments_last_sync');
        wp_send_json_success(['message'=>'OK']);
    }

    public function ajax_clear_players_cache() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        global $wpdb;
        // Limpia transients negativos y posibles caches locales de perfil
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_futb_neg_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_futb_neg_%'");
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('futbolin_player_cache_') . '%%'));
        // Nuevos: ranking por temporada cacheado y podios por jugador
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rf:rank:esp2:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rf:rank:esp2:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rf:podium:player:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rf:podium:player:%'");
        wp_send_json_success(['message'=>'OK']);
    }
    
    private function rf_current_dataset_version() {
        $v = get_option('rf_dataset_ver');
        return $v ? (string)$v : '1';
    }
    private function rf_count_cached_players_current_ver() {
        global $wpdb;
        $ver = $this->rf_current_dataset_version();
        // Contamos índices ':idx' que representan 1 jugador por cache
        $like = $wpdb->esc_like('_transient_rf:p:partidos:') . '%:v' . $ver . ':idx';
        // options.option_name es varchar; usamos LIKE directamente
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $like);
        $count = (int)$wpdb->get_var($sql);
        // Si hay object cache, puede haber 0 en options; devolvemos count>=0 igualmente
        return max(0, $count);
    }
    private function rf_count_cached_player_fragments_current_ver() {
        global $wpdb;
        $ver = $this->rf_current_dataset_version();
        // Índices de fragmentos por jugador (marcamos 1 por jugador con al menos una pestaña cacheada)
        $like = $wpdb->esc_like('_transient_rf:player:tab:v' . $ver . ':') . '%:idx';
        $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $like);
        $count = (int)$wpdb->get_var($sql);
        return max(0, $count);
    }
    private function rf_delete_all_partidos_caches_all_versions() {
        global $wpdb;
        // Borrar TODOS los shards e índices de partidos (todas las versiones)
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rf:p:partidos:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rf:p:partidos:%'");
    }
    private function rf_delete_tournament_caches() {
        global $wpdb;
        // Heurística: limpia claves conocidas usadas para torneos locales
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_futbolin_tournaments_%'");
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('futbolin_tournaments_cache_') . '%%'));
    }

    public function ajax_cache_stats() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $players = $this->rf_count_cached_players_current_ver();
        $players_frag = $this->rf_count_cached_player_fragments_current_ver();
        $ver = $this->rf_current_dataset_version();
        $enabled = function_exists('rf_cache_enabled') ? (int)rf_cache_enabled() : 1;
        wp_send_json_success([
            'players_cached'       => $players,
            'players_frag_cached'  => $players_frag,
            'dataset_ver'          => $ver,
            'cache_enabled'        => $enabled,
        ]);
    }

    public function ajax_clear_all_caches() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        // 1) Borrar caches de jugadores (todas versiones)
        $this->rf_delete_all_partidos_caches_all_versions();
        // 2) Borrar caches de torneos
        $this->rf_delete_tournament_caches();
        // 3) Borrar negativos y locales ya existentes
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_futb_neg_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_futb_neg_%'");
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('futbolin_player_cache_') . '%%'));
        // 3b) Borrar caches de rankings de temporada y podios por jugador
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rf:rank:esp2:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rf:rank:esp2:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rf:podium:player:%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rf:podium:player:%'");
        // 4) Bump version para invalidar caches en object cache (si existiera)
        if (!function_exists('rf_dataset_bump_cache_version')) {
            function rf_dataset_bump_cache_version() {
                $v = get_option('rf_dataset_ver');
                $nv = $v ? (string)(intval($v) + 1) : '1';
                update_option('rf_dataset_ver', $nv, false);
                do_action('rf_dataset_cache_busted', $nv);
                return $nv;
            }
        }
        $new_ver = rf_dataset_bump_cache_version();
        // 5) Estado final (debería ser 0 en options para la versión nueva)
        $players = $this->rf_count_cached_players_current_ver();
        wp_send_json_success(['message'=>'Caches purgadas', 'players_cached'=>$players, 'dataset_ver'=>$new_ver]);
    }

        public function ajax_cleanup_old_transients() {
            if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
            $this->verify_nonce_or_die();
            if (!class_exists('Futbolin_Cron')) {
                $file = dirname(__DIR__) . '/cron/class-futbolin-cron.php';
                if (file_exists($file)) { require_once $file; }
            }
            if (class_exists('Futbolin_Cron')) {
                \Futbolin_Cron::cleanup_old_transients_runner();
                wp_send_json_success(['message'=>'Limpieza de transients antiguos realizada']);
            } else {
                wp_send_json_error(['message'=>'Servicio de limpieza no disponible']);
            }
        }
    /** Ajusta manualmente la versión del dataset (p.ej. resetear a 1) */
    public function ajax_set_dataset_version() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $v = isset($_POST['version']) ? intval($_POST['version']) : 0;
        if ($v < 1) $v = 1;
        update_option('rf_dataset_ver', (string)$v, false);
        // Recalcular contador para la versión fijada
        $players = $this->rf_count_cached_players_current_ver();
        wp_send_json_success(['message'=>'Versión actualizada', 'dataset_ver'=>(string)$v, 'players_cached'=>$players]);
    }

    public function ajax_save_cron_settings() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $pre = isset($_POST['precache_enabled']) ? (int)$_POST['precache_enabled'] : 1;
        $cle = isset($_POST['cleanup_enabled']) ? (int)$_POST['cleanup_enabled'] : 1;
        $top = isset($_POST['top_n']) ? max(1, min(500, (int)$_POST['top_n'])) : 100;
        $tim = isset($_POST['time_budget']) ? max(30, min(600, (int)$_POST['time_budget'])) : 180;
        $opts = get_option('mi_plugin_futbolin_options', []);
        if (!is_array($opts)) { $opts = []; }
        $opts['rf_cron_precache_enabled'] = $pre ? 1 : 0;
        $opts['rf_cron_cleanup_enabled']  = $cle ? 1 : 0;
        $opts['rf_precache_top_n']        = (int)$top;
        $opts['rf_precache_time_budget']  = (int)$tim;
        update_option('mi_plugin_futbolin_options', $opts, false);
        // Reprogramar limpieza si corresponde
        if (class_exists('Futbolin_Cron')) {
            if ($opts['rf_cron_cleanup_enabled']) { \Futbolin_Cron::schedule_cleanup_daily(); }
            else { \Futbolin_Cron::unschedule_cleanup(); }
        }
        wp_send_json_success([
            'message'=>'Ajustes de cron guardados',
            'precache_enabled'=>$opts['rf_cron_precache_enabled'],
            'cleanup_enabled'=>$opts['rf_cron_cleanup_enabled'],
            'top_n'=>$opts['rf_precache_top_n'],
            'time_budget'=>$opts['rf_precache_time_budget']
        ]);
    }

    /** Precarga de caché para un jugador (todas las pestañas) */
    public function ajax_precache_player() {
        // Buffer exterior para capturar cualquier salida ajena que rompa el JSON
        $outer_level = ob_get_level();
        ob_start();
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $pid = isset($_POST['player_id']) ? intval($_POST['player_id']) : 0;
        if ($pid <= 0) { while (ob_get_level() > $outer_level) { ob_end_clean(); } wp_send_json_error(['message'=>'player_id inválido']); }
        if (!class_exists('Futbolin_Player_Cache_Service')) {
            require_once dirname(__DIR__) . '/services/class-futbolin-player-cache-service.php';
        }
        $res = \Futbolin_Player_Cache_Service::precache_player($pid);
        while (ob_get_level() > $outer_level) { ob_end_clean(); }
        wp_send_json_success(['message'=>'Precálculo completado', 'result'=>$res]);
    }

    /** Precarga de caché para un conjunto de jugadores (por ahora: top N por ranking global) */
    public function ajax_precache_top() {
        // Buffer exterior para capturar notices/HTML y no romper JSON
        $outer_level = ob_get_level();
        ob_start();
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        @set_time_limit(600);
        $n = isset($_POST['top_n']) ? max(1, intval($_POST['top_n'])) : 50;
        // Estrategia simple: buscar por ambas modalidades ESP Glicko2 y combinar IDs
        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        if (!class_exists('Futbolin_Player_Cache_Service')) {
            require_once dirname(__DIR__) . '/services/class-futbolin-player-cache-service.php';
        }
        $api = new \Futbolin_API_Client();
        $ids = [];
        foreach (['1','2'] as $mod) {
            try {
                $rows = $api->get_ranking_por_modalidad_esp_g2_all((int)$mod);
                $arr = (array) json_decode(json_encode($rows), true);
                $c = 0;
                foreach ($arr as $it) {
                    if (isset($it['jugadorId'])) { $ids[] = (int)$it['jugadorId']; }
                    elseif (isset($it['id']))     { $ids[] = (int)$it['id']; }
                    $c++; if ($c >= $n) break;
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $done = 0; $results = [];
        foreach ($ids as $pid) {
            // Precargar todas las pestañas cacheables, incluido 'history' (almacenamiento fragmentado)
            $res = \Futbolin_Player_Cache_Service::precache_player((int)$pid, ['glicko','summary','stats','hitos','history','torneos']);
            $done++; $results[] = ['player_id'=>$pid, 'cached'=>$res['cached'] ?? 0];
            if ($done >= $n) break;
        }
        while (ob_get_level() > $outer_level) { ob_end_clean(); }
        wp_send_json_success(['message'=>'Precálculo TOP completado', 'processed'=>$done, 'results'=>$results]);
    }

    /** Solo devuelve la lista de IDs (Top N combinado modalidades 1 y 2) para precarga progresiva */
    public function ajax_precache_top_ids() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $n      = isset($_POST['top_n']) ? max(1, intval($_POST['top_n'])) : 50;
        $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
        $modIn  = isset($_POST['modalidad']) ? (string)$_POST['modalidad'] : 'both'; // '1' dobles, '2' individual, 'both'
        if (!class_exists('Futbolin_API_Client')) {
            require_once dirname(__DIR__) . '/core/class-futbolin-api-client.php';
        }
        $api = new \Futbolin_API_Client();
        $ids = [];
        $mods = [];
        if ($modIn === '1' || $modIn === '2') { $mods = [$modIn]; } else { $mods = ['1','2']; }
        foreach ($mods as $mod) {
            try {
                $rows = $api->get_ranking_por_modalidad_esp_g2_all((int)$mod);
                $arr = (array) json_decode(json_encode($rows), true);
                $c = 0;
                foreach ($arr as $it) {
                    if (isset($it['jugadorId'])) { $ids[] = (int)$it['jugadorId']; }
                    elseif (isset($it['id']))     { $ids[] = (int)$it['id']; }
                    $c++; if ($c >= ($offset + $n)) break; // recorta cuanto se lee
                }
            } catch (\Throwable $e) { /* ignore */ }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        // aplicar offset y límite tras deduplicar
        if ($offset > 0 || count($ids) > $n) {
            $ids = array_slice($ids, $offset, $n);
        }
        wp_send_json_success(['ids' => $ids, 'count' => count($ids), 'modalidad'=>$modIn, 'offset'=>$offset, 'limit'=>$n]);
    }

    /* ====== Precarga ALL background (cron) ====== */
    public function ajax_precache_all_toggle() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
        $opts = get_option('mi_plugin_futbolin_options', []);
        if (!is_array($opts)) $opts = [];
        $opts['rf_cron_precache_all_enabled'] = $enabled ? 1 : 0;
        update_option('mi_plugin_futbolin_options', $opts, false);
        if (!class_exists('Futbolin_Cron')) { require_once dirname(__DIR__) . '/cron/class-futbolin-cron.php'; }
        if (class_exists('Futbolin_Cron')) {
            if ($enabled) {
                // Si no hay lista de IDs, construirla para que el estado no aparezca 0/0
                if (method_exists('Futbolin_Cron','init_all_ids_if_empty')) { \Futbolin_Cron::init_all_ids_if_empty(); }
                \Futbolin_Cron::schedule_precache_all_now();
            }
            else { \Futbolin_Cron::unschedule_precache_all(); }
        }
        // Devolver estado actual tras el cambio
        $cursor = (int) get_option('rf_precache_all_cursor', 0);
        $total  = (int) get_option('rf_precache_all_total', 0);
        $scheduled = (bool) wp_next_scheduled('futbolin_precache_all_cron');
        wp_send_json_success(['message'=>'Estado actualizado','enabled'=>$enabled?1:0,'cursor'=>$cursor,'total'=>$total,'scheduled'=>$scheduled]);
    }

    public function ajax_precache_all_start() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        if (!class_exists('Futbolin_Cron')) { require_once dirname(__DIR__) . '/cron/class-futbolin-cron.php'; }
        if (class_exists('Futbolin_Cron')) {
            if (method_exists('Futbolin_Cron','init_all_ids_if_empty')) { \Futbolin_Cron::init_all_ids_if_empty(); }
            \Futbolin_Cron::schedule_precache_all_now();
        }
        $cursor = (int) get_option('rf_precache_all_cursor', 0);
        $total  = (int) get_option('rf_precache_all_total', 0);
        $scheduled = (bool) wp_next_scheduled('futbolin_precache_all_cron');
        wp_send_json_success(['message'=>'Programado siguiente lote en background','cursor'=>$cursor,'total'=>$total,'scheduled'=>$scheduled]);
    }

    public function ajax_precache_all_stop() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        if (!class_exists('Futbolin_Cron')) { require_once dirname(__DIR__) . '/cron/class-futbolin-cron.php'; }
        if (class_exists('Futbolin_Cron')) { \Futbolin_Cron::unschedule_precache_all(); }
        wp_send_json_success(['message'=>'Proceso detenido']);
    }

    public function ajax_precache_all_status() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $opts = get_option('mi_plugin_futbolin_options', []);
        $enabled = is_array($opts) && !empty($opts['rf_cron_precache_all_enabled']);
        // Si está habilitado pero total es 0, intenta inicializar para evitar estado 0/0 permanente
        if ($enabled) {
            if (!class_exists('Futbolin_Cron')) { require_once dirname(__DIR__) . '/cron/class-futbolin-cron.php'; }
            if (class_exists('Futbolin_Cron') && method_exists('Futbolin_Cron','init_all_ids_if_empty')) {
                \Futbolin_Cron::init_all_ids_if_empty();
            }
        }
        $cursor = (int) get_option('rf_precache_all_cursor', 0);
        $total  = (int) get_option('rf_precache_all_total', 0);
        $fin    = (int) get_option('rf_precache_all_finished_at', 0);
        $scheduled = (bool) wp_next_scheduled('futbolin_precache_all_cron');
        $tabs = get_option('rf_precache_all_tab_counts', []);
        $pct    = $total>0 ? round($cursor * 100.0 / $total, 1) : 0;
        $state  = ($total>0 && $cursor>= $total) ? 'finished' : ($scheduled ? 'scheduled' : 'idle');
        wp_send_json_success(['cursor'=>$cursor, 'total'=>$total, 'progress'=>$pct, 'state'=>$state, 'finished_at'=>$fin, 'tab_counts'=>$tabs, 'enabled'=>$enabled?1:0, 'scheduled'=>$scheduled?1:0]);
    }

    /** Devuelve los ajustes persistidos de cron para sincronizar checkboxes en la UI */
    public function ajax_get_cron_settings() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Permiso denegado'], 403);
        $this->verify_nonce_or_die();
        $opts = get_option('mi_plugin_futbolin_options', []);
        if (!is_array($opts)) $opts = [];
        $data = [
            'precache_enabled' => !empty($opts['rf_cron_precache_enabled']) ? 1 : 0,
            'cleanup_enabled'  => !empty($opts['rf_cron_cleanup_enabled']) ? 1 : 0,
            'all_enabled'      => !empty($opts['rf_cron_precache_all_enabled']) ? 1 : 0,
            'top_n'            => isset($opts['rf_precache_top_n']) ? (int)$opts['rf_precache_top_n'] : 100,
            'time_budget'      => isset($opts['rf_precache_time_budget']) ? (int)$opts['rf_precache_time_budget'] : 180,
        ];
        wp_send_json_success($data);
    }

}
