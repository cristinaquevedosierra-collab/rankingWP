<?php
if (!defined('ABSPATH')) exit;
if (!class_exists('Futbolin_API')) {
class Futbolin_API {
    public static function get_base_url() {
        $opt = get_option('futbolin_api_base_url');
        if ($opt) return rtrim($opt, '/');
        return 'https://illozapatillo.zapto.org';
    }
    public static function get_json($url) {
        $args = ['timeout'=>20, 'headers'=>['Accept'=>'application/json']];
        $res = wp_remote_get($url, $args);
        if (is_wp_error($res)) return [];
        $code = wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 300) return [];
        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body);
        return (json_last_error()===JSON_ERROR_NONE) ? $data : [];
    }
}}
