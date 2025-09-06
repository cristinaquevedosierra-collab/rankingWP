<?php
if (!defined('ABSPATH')) exit;
/**
 * Ajustes HTTP para llamadas a la API del plugin:
 *  - Timeout configurable (opción: http_timeout)
 *  - Reintentos con backoff (opción: http_retries)
 *  - Solo aplica a URLs del plugin (lista blanca)
 */
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
        if (strpos($url, '/api/') !== false) return true;
        if ($host === 'illozapatillo.zapto.org') return true;
        if ($host === '127.0.0.1' || $host === 'localhost') return true;
        return false;
    }

    private static function get_opts() {
        $opts = get_option('mi_plugin_futbolin_options', []);
        $timeout = isset($opts['http_timeout']) ? (int)$opts['http_timeout'] : 30;
        if ($timeout < 5 || $timeout > 120) $timeout = 30;
        $retries = isset($opts['http_retries']) ? (int)$opts['http_retries'] : 3;
        if ($retries < 0 || $retries > 5) $retries = 3;
        return ['timeout'=>$timeout, 'retries'=>$retries];
    }

    public static function filter_http_args($args, $url) {
        if (!self::is_plugin_api_url($url)) return $args;
        $o = self::get_opts();
        $args['timeout'] = $o['timeout'];
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
