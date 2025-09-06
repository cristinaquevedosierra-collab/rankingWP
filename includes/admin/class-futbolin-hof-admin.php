<?php
if ( ! defined('ABSPATH') ) exit;

class Futbolin_HOF_Admin {

    public function __construct() {
        add_action('wp_ajax_futbolin_start_hof_calculation',    [$this, 'start_hof_calculation']);
        add_action('wp_ajax_futbolin_run_hof_calculation_step', [$this, 'run_hof_calculation_step']);
        add_action('wp_ajax_futbolin_cancel_hof_calc',          [$this, 'cancel_hof_calc']);
    }

    /**
     * Inicia el cálculo del Hall of Fame y cachea el resultado (12h).
     */
    public function start_hof_calculation() {
        @set_time_limit(300);
        error_log('HALL OF FAME DEBUG: Proceso AJAX iniciado.');

        if (!current_user_can('manage_options')) {
            error_log('HALL OF FAME DEBUG: Permisos insuficientes.');
            wp_send_json_error(['message' => __('Permisos insuficientes.', 'ranking-futbolin')], 403);
        }

        // Verificación segura del nonce SIN matar el proceso
        $nonce_ok = check_ajax_referer('futbolin_admin_nonce', 'security', false);
        if (!$nonce_ok) {
            error_log('HALL OF FAME DEBUG: ¡Fallo en la verificación de seguridad!');
            wp_send_json_error(['message' => __('Error de seguridad', 'ranking-futbolin')], 400);
        }
        error_log('HALL OF FAME DEBUG: Nonce de seguridad verificado.');

        if (!class_exists('Futbolin_API_Client') || !class_exists('Futbolin_Stats_Processor')) {
            error_log('HALL OF FAME DEBUG: Componentes no disponibles (API/Processor).');
            wp_send_json_error(['message' => __('Componentes no disponibles.', 'ranking-futbolin')], 500);
        }

        error_log('HALL OF FAME DEBUG: Obteniendo datos de la API/Base de Datos...');

        try {
            $client = new Futbolin_API_Client();
            $proc   = new Futbolin_Stats_Processor($client);

            error_log('HALL OF FAME DEBUG: Iniciando bucle de procesamiento.');
            $items = $proc->build_hof_dataset(); // aplica filtro de modalidades + >=100 victorias

            $count = is_array($items) ? count($items) : 0;
            error_log('HALL OF FAME DEBUG: Cálculo finalizado. Se encontraron ' . $count . ' jugadores para el ranking.');

            // Guardar snapshot + estado
            set_transient('futbolin_hall_of_fame_data', ['generated_at' => current_time('mysql'), 'items' => $items], 12 * HOUR_IN_SECONDS);
            update_option('futbolin_hof_status', [
                'finished' => true,
                'progress' => 100,
                'message'  => __('Cálculo del Hall of Fame completado', 'ranking-futbolin'),
                'count'    => $count,
            ]);

            error_log('HALL OF FAME DEBUG: Enviando respuesta JSON de éxito al navegador.');
            wp_send_json_success([
                'finished' => true,
                'count'    => $count,
                'message'  => __('Cálculo completado', 'ranking-futbolin'),
            ]);
        } catch (\Throwable $e) {
            error_log('HALL OF FAME DEBUG: Excepción durante el cálculo -> ' . $e->getMessage());
            wp_send_json_error(['message' => __('Error interno durante el cálculo', 'ranking-futbolin')], 500);
        }
    }

    /**
     * Devuelve el estado del cálculo (simple, ya que es síncrono).
     */
    public function run_hof_calculation_step() {
        @set_time_limit(60);
        error_log('HALL OF FAME DEBUG: Consulta de estado iniciada.');

        if (!current_user_can('manage_options')) {
            error_log('HALL OF FAME DEBUG: Permisos insuficientes en status.');
            wp_send_json_error(['message' => __('Permisos insuficientes.', 'ranking-futbolin')], 403);
        }
        $nonce_ok = check_ajax_referer('futbolin_admin_nonce', 'security', false);
        if (!$nonce_ok) {
            error_log('HALL OF FAME DEBUG: ¡Fallo nonce en status!');
            wp_send_json_error(['message' => __('Error de seguridad', 'ranking-futbolin')], 400);
        }
        error_log('HALL OF FAME DEBUG: Nonce de seguridad verificado (status).');

        $status = get_option('futbolin_hof_status', []);
        $raw    = get_transient('futbolin_hall_of_fame_data');
        $count  = 0;
        if (is_array($raw) && isset($raw['items']) && is_array($raw['items'])) {
            $count = count($raw['items']);
        } elseif (is_array($raw)) {
            $count = count($raw);
        }

        error_log('HALL OF FAME DEBUG: Estado actual -> finished=' . (!empty($status['finished']) ? '1':'0') . ', count=' . $count);
        error_log('HALL OF FAME DEBUG: Enviando respuesta JSON de estado.');

        wp_send_json_success([
            'finished' => !empty($status['finished']),
            'progress' => $status['progress'] ?? 100,
            'message'  => $status['message'] ?? '',
            'count'    => $count,
        ]);
    }

    /**
     * Cancela el proceso (limpia estado y cache).
     */
    public function cancel_hof_calc() {
        @set_time_limit(60);
        error_log('HALL OF FAME DEBUG: Cancelación solicitada.');

        if (!current_user_can('manage_options')) {
            error_log('HALL OF FAME DEBUG: Permisos insuficientes en cancelación.');
            wp_send_json_error(['message' => __('Permisos insuficientes.', 'ranking-futbolin')], 403);
        }
        $nonce_ok = check_ajax_referer('futbolin_admin_nonce', 'security', false);
        if (!$nonce_ok) {
            error_log('HALL OF FAME DEBUG: ¡Fallo nonce en cancelación!');
            wp_send_json_error(['message' => __('Error de seguridad', 'ranking-futbolin')], 400);
        }

        delete_option('futbolin_hof_status');
        delete_transient('futbolin_hall_of_fame_data');

        error_log('HALL OF FAME DEBUG: Transients/estado borrados. Enviando respuesta JSON de éxito.');
        wp_send_json_success(['message' => __('Proceso cancelado y datos borrados', 'ranking-futbolin')]);
    }
}

new Futbolin_HOF_Admin();
