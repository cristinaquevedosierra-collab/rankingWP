<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('Futbolin_API')) {
// Stubs suaves para editores fuera de WP (no se ejecutan en WordPress real)
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) { return array('response' => array('code' => 0), 'body' => ''); }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) { return is_array($response) && isset($response['response']['code']) ? (int)$response['response']['code'] : 0; }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) { return is_array($response) && isset($response['body']) ? (string)$response['body'] : ''; }
}
class Futbolin_API {
    public static function get_base_url() {
        $opt = function_exists('get_option') ? get_option('futbolin_api_base_url') : null;
        if ($opt) return rtrim($opt, '/');
        
        // Fallback sin hardcode: leer meta.baseUrl desde BUENO_master.json
        $plugin_dir = dirname(dirname(__FILE__)); // .../includes/core -> includes
        $plugin_dir = dirname($plugin_dir); // -> plugin root
        $master_path = $plugin_dir . '/BUENO_master.json';
        if (file_exists($master_path)) {
            $json = json_decode(file_get_contents($master_path), true);
            if (is_array($json) && isset($json['meta']['baseUrl'])) {
                return rtrim($json['meta']['baseUrl'], '/');
            }
        }
        return '';

    }
    public static function get_json($url) {
        $headers = ['Accept' => 'application/json'];

        // If running inside WordPress, prefer the WP HTTP API
        if (function_exists('wp_remote_get')) {
            $args = array('timeout' => 20, 'headers' => $headers);
            $res = wp_remote_get($url, $args);
            if (function_exists('is_wp_error') && is_wp_error($res)) {
                return [];
            }
            // Obtener código HTTP de forma robusta
            $code = 0;
            if (function_exists('wp_remote_retrieve_response_code')) {
                $code = wp_remote_retrieve_response_code($res);
            } else {
                if (is_array($res) && isset($res['response']) && is_array($res['response']) && isset($res['response']['code'])) {
                    $code = (int)$res['response']['code'];
                }
            }
            if ($code < 200 || $code >= 300) {
                return [];
            }
            $body = '';
            if (function_exists('wp_remote_retrieve_body')) {
                $body = wp_remote_retrieve_body($res);
            } else {
                if (is_array($res) && isset($res['body'])) { $body = (string)$res['body']; }
            }
        } else {
            // Fallback for non-WordPress environments: use file_get_contents with stream context
            $opts = array(
                'http' => array(
                    'method'  => 'GET',
                    'timeout' => 20,
                    'header'  => "Accept: application/json\r\n",
                ),
                'ssl' => array(
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ),
            );
            $context = stream_context_create($opts);
            $body = @file_get_contents($url, false, $context);
            if ($body === false) {
                return [];
            }

            // Try to extract HTTP response code from response headers if available
            $code = 0;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $hdr) {
                    if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#i', $hdr, $m)) {
                        $code = intval($m[1]);
                        break;
                    }
                }
            }
            if ($code < 200 || $code >= 300) {
                return [];
            }
        }

        $data = json_decode($body);
        return (json_last_error() === JSON_ERROR_NONE) ? $data : [];
    }
}}
