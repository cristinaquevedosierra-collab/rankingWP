<?php
/**
 * Archivo Resultante: class-futbolin-api-client.php (Versión Completa)
 * Ruta: includes/core/class-futbolin-api-client.php
 * Fuente Original: class-futbolin-api-client.php (antiguo)
 *
 * Descripción: Cliente centralizado para todas las comunicaciones con la API de Futbolín.
 * Esta versión mantiene el contrato original y añade:
 * - Manejo robusto de 204/5xx
 * - Cache negativa por URL
 * - Log deduplicado
 */

if (!defined('ABSPATH')) exit;

class Futbolin_API_Client {

    protected $base_api_url = 'https://illozapatillo.zapto.org/api';

    // --- MÉTODOS PÚBLICOS (Endpoints de la API) ---

    public function get_modalidades() {
        $api_url = $this->base_api_url . '/Modalidad/GetModalidades';
        return $this->do_request($api_url, true);
    }

    public function get_ranking($modalidad_id, $page = 1, $page_size = 25) {
        $api_page_size = ($page_size == -1) ? 9999 : $page_size;
        $api_url = $this->base_api_url . "/Ranking/GetRankingPorModalidadESPPagGlicko2/{$modalidad_id}?page={$page}&pageSize={$api_page_size}";
        return $this->do_request($api_url, true);
    }

    public function get_jugador_puntuacion_categoria($jugador_id) {
        if (!$jugador_id) return [];
        $api_url = $this->base_api_url . "/Jugador/{$jugador_id}/GetJugadorPuntuacionCategoria";
        return $this->do_request($api_url, true);
    }

    public function buscar_jugadores($search_term) {
        if (empty($search_term)) return [];
        // Normaliza espacios y recorta
        if (is_string($search_term)) {
            $search_term = preg_replace('/\s+/', ' ', trim($search_term));
        }
        if (strlen($search_term) < 3) return [];
        // IMPORTANTE: para segmentos de ruta usar rawurlencode (espacios = %20, no '+')
        $api_url = $this->base_api_url . '/Jugador/GetBuscarJugador/' . rawurlencode($search_term);
        $response = $this->do_request($api_url, true);
        if (!$response || is_wp_error($response)) {
            return [];
        }
        return $response;
    }

    public function get_datos_jugador($jugador_id) {
        if (!$jugador_id) return null;
        $api_url = $this->base_api_url . "/Jugador/{$jugador_id}/GetDatosJugador";
        return $this->do_request($api_url, true);
    }

    public function get_partidos_jugador($jugador_id) {
        if (!$jugador_id) return [];
        $api_url = $this->base_api_url . "/Jugador/{$jugador_id}/GetJugadorPartidos";
        return $this->do_request($api_url, true);
    }

    public function get_posiciones_jugador($jugador_id) {
        if (!$jugador_id) return [];
        $api_url = $this->base_api_url . "/Jugador/{$jugador_id}/GetJugadorPosicionPorTorneos";
        return $this->do_request($api_url, true);
    }

    public function get_torneos() {
        $api_url = $this->base_api_url . "/Torneo/GetTorneos";
        return $this->do_request($api_url, true);
    }

    public function get_tournaments_paginated($page = 1, $page_size = 25) {
        $api_url = $this->base_api_url . "/Torneo/get_torneos?page={$page}&pageSize={$page_size}";
        return $this->do_request($api_url, true);
    }

    public function get_tournament_with_positions($torneo_id) {
        if (!$torneo_id) return [];
        $api_url = $this->base_api_url . "/Torneo/GetTorneoConPosiciones/{$torneo_id}";
        return $this->do_request($api_url, true);
    }

    // --- LÓGICA INTERNA DE PETICIONES Y AUTENTICACIÓN ---

    protected function do_request($url, $auth_required = false) {
        // Cache negativa por URL (evita martilleo ante 204/5xx/4xx/errores de red)
        $neg_key = $this->neg_cache_key($url);
        $cached  = get_transient($neg_key);
        if ($cached !== false) {
            // marcador de 204 (sin contenido)
            if (is_array($cached) && isset($cached['__no_content']) && $cached['__no_content'] === true) {
                return null; // contrato: 204 => null
            }
            if ($cached instanceof WP_Error) {
                return $cached; // contrato: error => WP_Error
            }
            return $cached;
        }

        $args = [
            'method'    => 'GET',
            'timeout'   => 30,
            'sslverify' => false,
            'headers'   => [],
        ];

        if ($auth_required) {
            $token = $this->get_valid_token();
            if (!$token || is_wp_error($token)) {
                // cache muy corta para no insistir mientras el token falla
                set_transient($neg_key, ($token instanceof WP_Error) ? $token : new WP_Error('auth_error','Token inválido'), 3 * MINUTE_IN_SECONDS);
                return $token;
            }
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $err = $response->get_error_message();
            $this->log_once('Error en la petición a la API. URL: ' . $url . ' Mensaje: ' . $err, 600);
            // Cache negativa corta para errores de red
            set_transient($neg_key, $response, 3 * MINUTE_IN_SECONDS);
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        // 204: sin contenido -> NO error; devolvemos null y no spameamos el log
        if ($code === 204 || $body === '' || $body === null) {
            // Cache negativa (10 min) para no reintentar en cada carga
            set_transient($neg_key, ['__no_content' => true], 10 * MINUTE_IN_SECONDS);
            return null;
        }

        // 2xx con body -> intentar json_decode
        if ($code >= 200 && $code < 300) {
            $decoded = json_decode($body);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded; // contrato original: objeto/array según API
            }
            // JSON inválido
            $err = new WP_Error('json_error', 'Respuesta JSON inválida');
            $this->log_once('Error en la petición a la API. URL: ' . $url . ' Código: ' . $code . ' Body: ' . $body, 600);
            set_transient($neg_key, $err, 5 * MINUTE_IN_SECONDS);
            return $err;
        }

        // 5xx: backend roto (NRE, etc.) -> WP_Error pero log deduplicado + cache negativa
        if ($code >= 500) {
            $err_msg = $this->extract_message_from_body($body) ?: 'Error 5xx en API';
            $line = 'Error en la petición a la API. URL: ' . $url . ' Código: ' . $code . ' Body: ' . $body;
            $this->log_once($line, 600);
            $err = new WP_Error('http_error', $err_msg);
            set_transient($neg_key, $err, 15 * MINUTE_IN_SECONDS);
            return $err;
        }

        // Otros (3xx, 4xx): WP_Error, log deduplicado + cache negativa
        $line = 'Error en la petición a la API. URL: ' . $url . ' Código: ' . $code . ' Body: ' . $body;
        $this->log_once($line, 600);
        $err = new WP_Error('http_error', 'La API devolvió un error (código ' . $code . ').');
        set_transient($neg_key, $err, 10 * MINUTE_IN_SECONDS);
        return $err;
    }

    protected function get_valid_token() {
        $token = get_transient('futbolin_api_token');
        if ($token) { return $token; }
        return $this->login();
    }

    protected function login() {
        $login_url = $this->base_api_url . '/seguridad/login';
        if (!defined('FUTBOLIN_API_USER') || !defined('FUTBOLIN_API_PASS')) {
            error_log('Error crítico: Credenciales FUTBOLIN_API_USER o FUTBOLIN_API_PASS no definidas en wp-config.php.');
            return new WP_Error('config_error', 'Credenciales de API no configuradas.');
        }
        $args = [
            'method'    => 'POST',
            'timeout'   => 45,
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => json_encode(['usuario' => FUTBOLIN_API_USER, 'password' => FUTBOLIN_API_PASS]),
            'sslverify' => false
        ];
        $response = wp_remote_post($login_url, $args);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Fallo en el login de la API: ' . print_r($response, true));
            return new WP_Error('login_failed', 'No se pudo autenticar con la API.');
        }
        $body = json_decode(wp_remote_retrieve_body($response));
        if (isset($body->token)) {
            set_transient('futbolin_api_token', $body->token, 55 * MINUTE_IN_SECONDS);
            return $body->token;
        }
        return false;
    }

    // --- Helpers privados ---

    /** Clave de cache negativa por URL exacta */
    private function neg_cache_key(string $url): string {
        return 'futb_neg_' . md5($url);
    }

    /** Log deduplicado por línea (evita spam en ráfagas). TTL configurable. */
    private function log_once(string $line, int $ttl_seconds = 600): void {
        $key = 'futb_log_' . md5($line);
        if (get_transient($key) !== false) return;
        set_transient($key, 1, $ttl_seconds);
        error_log($line);
    }

    /** Intenta extraer "message" del JSON de error (si existe) */
    private function extract_message_from_body($body): ?string {
        if (!is_string($body) || $body === '') return null;
        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            if (!empty($json['message'])) return (string)$json['message'];
            if (!empty($json['error']))   return (string)$json['error'];
        }
        return null;
    }

    /**
     * Campeones de España (endpoint oficial)
     * Devuelve un array de objetos con {jugadorId, nombreJugador, torneosIndividual[], torneosDobles[]}
     */
    public function get_campeones_espania() {
        $api_url = $this->base_api_url . '/Jugador/GetCampeonesEspania';
        return $this->do_request($api_url, true);
    }
    
}
