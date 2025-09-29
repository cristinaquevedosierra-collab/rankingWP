<?php
if (!defined('ABSPATH')) exit;
/**
 * Ajustes HTTP para llamadas a la API del plugin:
 *  - Timeout configurable (opción: http_timeout)
 *  - Reintentos con backoff (opción: http_retries)
 *  - Solo aplica a URLs del plugin (lista blanca)
 */
// Stubs/guards para análisis fuera de WordPress (no afectan runtime en WP)
if (!function_exists('add_filter')) { function add_filter($tag,$func,$priority=10,$accepted_args=1){} }
if (!function_exists('remove_filter')) { function remove_filter($tag,$func,$priority=10){} }
if (!function_exists('get_option')) { function get_option($k,$d=[]) { return $d; } }
if (!class_exists('WP_Error')) { class WP_Error { public function __construct($c='',$m=''){} public function get_error_code(){ return 'error'; } public function get_error_message(){ return ''; } } }
if (!function_exists('is_wp_error')) { function is_wp_error($x){ return $x instanceof WP_Error; } }
if (!function_exists('wp_remote_request')) { function wp_remote_request($url,$args=[]) { return new WP_Error('http_unavailable','HTTP API stub'); } }
if (!class_exists('Futbolin_HTTP')) {
class Futbolin_HTTP {
    public static function boot() {
        add_filter('http_request_args', [__CLASS__,'filter_http_args'], 10, 2);
        add_filter('pre_http_request',  [__CLASS__,'filter_pre_http_request'], 10, 3);
    }

    private static function is_plugin_api_url($url) {
        if (empty($url)) return false;
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) return false;
        $host = strtolower($host);
        // A partir de sep 2025: solo trabajamos con el dominio final oficial
        return ($host === 'ranking.fefm.net');
    }

    private static function get_opts() {
        $opts = get_option('mi_plugin_futbolin_options', []);
        // Valores por defecto más conservadores para evitar cuelgues largos
        $timeout = isset($opts['http_timeout']) ? (int)$opts['http_timeout'] : 12;
        if ($timeout < 5 || $timeout > 120) $timeout = 12;
        // Por defecto, 1 reintento (2 intentos en total)
        $retries = isset($opts['http_retries']) ? (int)$opts['http_retries'] : 1;
        if ($retries < 0 || $retries > 5) $retries = 1;
        return ['timeout'=>$timeout, 'retries'=>$retries];
    }

    public static function filter_http_args($args, $url) {
        if (!self::is_plugin_api_url($url)) return $args;
        $o = self::get_opts();
        $args['timeout'] = $o['timeout'];
        // Endpoints sensibles: ranking por temporada ESP Glicko2 -> baja timeout
        $p = @parse_url($url);
        $path_l = is_array($p) && !empty($p['path']) ? strtolower($p['path']) : '';
        if ($path_l && strpos($path_l, '/ranking/getrankingpormodalidadportemporadaespglicko2') !== false) {
            // Clampa a 10-12s máx para que dos intentos no excedan ~25s totales
            if (!isset($args['timeout']) || (int)$args['timeout'] > 12) {
                $args['timeout'] = 12;
            }
        }
        return $args;
    }

    public static function filter_pre_http_request($preempt, $args, $url) {
        if (!self::is_plugin_api_url($url)) return $preempt;

        // Evitar loops si otro plugin llama a wp_remote_request dentro de este filtro
        static $in_retry = false;
        if ($in_retry) return $preempt;

        $o = self::get_opts();
        $retries = (int)$o['retries'];
        $args['timeout'] = $o['timeout'];

        // Ajuste específico para endpoint de temporada: reducir reintentos/timeout
        try {
            $p = @parse_url($url);
            $path_l = is_array($p) && !empty($p['path']) ? strtolower($p['path']) : '';
            if ($path_l && strpos($path_l, '/ranking/getrankingpormodalidadportemporadaespglicko2') !== false) {
                // Máximo 1 reintento (2 intentos) y timeout más corto
                if ($retries > 1) { $retries = 1; }
                if (!isset($args['timeout']) || (int)$args['timeout'] > 12) { $args['timeout'] = 12; }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Haremos nosotros la petición con reintentos y devolveremos la respuesta.
        $in_retry = true;
        // Desactivamos temporalmente este propio filtro para evitar recursión.
        remove_filter('pre_http_request', [__CLASS__,'filter_pre_http_request'], 10);

        try {
            $attempts = max(1, $retries + 1);
            $last = null;
            for ($i=0; $i<$attempts; $i++) {
                $last = wp_remote_request($url, $args);
                if (!is_wp_error($last)) {
                    // Éxito
                    return $last;
                }
                // Error: solo reintentar si parece timeout/conexión
                $code = $last->get_error_code();
                $msg  = $last->get_error_message();
                $is_timeout = (stripos($msg, 'timed out') !== false) || (stripos($msg, 'timeout') !== false) || $code === 'http_request_failed';
                if ($i < $attempts - 1 && $is_timeout) {
                    // Backoff 200ms, 400ms, 600ms...
                    $delay_ms = 200 * ($i + 1);
                    usleep($delay_ms * 1000);
                    continue;
                } else {
                    // Devolver el último error
                    return $last;
                }
            }
            return $last;
        } finally {
            // Restaurar filtro y estado
            add_filter('pre_http_request', [__CLASS__,'filter_pre_http_request'], 10, 3);
            $in_retry = false;
        }
    }
}
}
