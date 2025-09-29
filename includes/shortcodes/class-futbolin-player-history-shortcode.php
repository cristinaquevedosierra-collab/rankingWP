<?php
/**
 * Shortcode: [futbolin_player_historial jugador_id="4"]
 *
 * Histórico global de partidos del jugador usando **ALL**.
 * Endpoints candidatos (se prueban en este orden):
 *   1) /api/Jugador/:jugadorId/GetJugadorPartidos
 *   2) /api/Jugador/GetJugadorPartidos/:jugadorId
 *
 * Cumple BUENO_master: preferir ALL, sin hardcodes, unwrap robusto.
 */
if (!defined('ABSPATH')) exit;

include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';

add_action('init', function () {
    add_shortcode('futbolin_player_historial', function ($atts = []) {
        $atts = shortcode_atts([
            'jugador_id' => null,
            'limit'      => null, // opcional, recorta la lista
        ], $atts, 'futbolin_player_historial');

        $jugador_id = $atts['jugador_id'];
        if (!$jugador_id) {
            $jugador_id = isset($_GET['jugador_id']) ? intval($_GET['jugador_id']) : 0;
        } else {
            $jugador_id = intval($jugador_id);
        }
        if ($jugador_id <= 0) {
            return '<div class="futbolin-card"><p>Falta <code>jugador_id</code>.</p></div>';
        }

        $base_url = trim(get_option('futbolin_api_base_url', ''));
        if (!$base_url) {
            return '<div class="futbolin-card"><p>No se ha configurado la URL de la API (<code>futbolin_api_base_url</code>).</p></div>';
        }
        $base_url = rtrim($base_url, '/');

        // Token Bearer desde opciones conocidas
        $token = trim(get_option('futbolin_api_token', ''));
        if (!$token) {
            $token = trim(get_option('futbolin_api_bearer', ''));
        }

        $headers = [
            'Accept' => 'application/json',
        ];
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $candidates = [
            $base_url . '/api/Jugador/' . $jugador_id . '/GetJugadorPartidos',
            $base_url . '/api/Jugador/GetJugadorPartidos/' . $jugador_id,
        ];

        $last_error_msg = null;
        $items = [];

        foreach ($candidates as $endpoint) {
            $response = wp_remote_get($endpoint, ['headers' => $headers, 'timeout' => 30]);
            if (is_wp_error($response)) {
                $last_error_msg = $response->get_error_message();
                continue;
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ($code < 200 || $code >= 300) {
                $last_error_msg = 'HTTP ' . intval($code) . ' (' . esc_html($endpoint) . ')';
                continue;
            }

            // Intentar decodificar como JSON
            $json = json_decode($body, true);
            if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
                $last_error_msg = 'JSON inválido (' . esc_html($endpoint) . ')';
                continue;
            }

            // Unwrap robusto (order del master): items -> data.items -> result.items -> root
            if (is_array($json)) {
                if (isset($json['items']) && is_array($json['items'])) {
                    $items = $json['items'];
                } elseif (isset($json['data']['items']) && is_array($json['data']['items'])) {
                    $items = $json['data']['items'];
                } elseif (isset($json['result']['items']) && is_array($json['result']['items'])) {
                    $items = $json['result']['items'];
                } elseif (!empty($json) && array_values($json) === $json && is_array(reset($json))) {
                    $items = $json; // root=array
                } else {
                    // Otras variantes conocidas en master (jugadorPartidos / partidos)
                    foreach (['jugadorPartidos', 'partidos', 'data', 'result'] as $k) {
                        if (isset($json[$k])) {
                            $v = $json[$k];
                            if (isset($v['items']) && is_array($v['items'])) {
                                $items = $v['items'];
                                break;
                            }
                            if (is_array($v) && !empty($v) && array_values($v) === $v && is_array(reset($v))) {
                                $items = $v;
                                break;
                            }
                        }
                    }
                }
            }

            if (!empty($items)) {
                break; // éxito con este endpoint
            }
        }

        if (empty($items)) {
            $msg = $last_error_msg ? ' ' . esc_html($last_error_msg) : '';
            return '<div class="futbolin-card"><p>No hay partidos registrados para este jugador.' . $msg . '</p></div>';
        }

        // Ordenación por fecha desc (si existe)
        usort($items, function($a, $b){
            $fa = isset($a['fecha']) ? strtotime($a['fecha']) : 0;
            $fb = isset($b['fecha']) ? strtotime($b['fecha']) : 0;
            return $fb <=> $fa;
        });

        
        // Alinear con estadísticas: filtrado de negocio
        if (is_array($items)) {
            $items = array_values(array_filter($items, function($r){ return _futb_should_count_for_stats($r); }));
        } else {
            $items = [];
        }
// Recorte opcional
        $limit = $atts['limit'] ? intval($atts['limit']) : 0;
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        // Render por template
        ob_start();
        $template = __DIR__ . '/../template-parts/player/history-table.php';
        if (file_exists($template)) {
            $rows = $items;
            $player_id = $jugador_id;
            include $template;
        } else {
            // Fallback simple
            echo '<div class="futbolin-ranking-wrapper">';
            echo '<table class="futbolin-table"><thead><tr>';
            echo '<th>Fecha</th><th>Torneo</th><th>Competición</th><th>Fase</th><th>Modalidad</th><th>Local</th><th>Visitante</th><th>Marcador</th><th>Resultado</th>';
            echo '</tr></thead><tbody>';
            foreach ($items as $row) {
                $fecha = isset($row['fecha']) ? esc_html(substr($row['fecha'], 0, 10)) : '—';
                $torneo = isset($row['torneo']) ? esc_html($row['torneo']) : '—';
                $competicion = isset($row['tipoCompeticion']) ? esc_html($row['tipoCompeticion']) : (isset($row['competicion']) ? esc_html($row['competicion']) : '—');
                $fase = isset($row['fase']) ? esc_html($row['fase']) : '—';
                $modalidad = isset($row['modalidad']) ? esc_html($row['modalidad']) : '—';
                $loc = isset($row['equipoLocal']) ? esc_html($row['equipoLocal']) : '—';
                $vis = isset($row['equipoVisitante']) ? esc_html($row['equipoVisitante']) : '—';
                $score = trim((isset($row['puntosLocal']) ? $row['puntosLocal'] : '') . ' - ' . (isset($row['puntosVisitante']) ? $row['puntosVisitante'] : ''));
                $score = esc_html($score);

                $is_local = false;
                if (isset($row['equipoLocalDTO']['jugadores']) && is_array($row['equipoLocalDTO']['jugadores'])) {
                    foreach ($row['equipoLocalDTO']['jugadores'] as $j) {
                        if (intval($j['jugadorId'] ?? 0) === $jugador_id) {
                            $is_local = true;
                            break;
                        }
                    }
                }
                $ganadorLocal = isset($row['ganadorLocal']) ? (bool)$row['ganadorLocal'] : null;
                $resultado = '—';
                if ($ganadorLocal !== null) {
                    $gano = ($is_local && $ganadorLocal) || (!$is_local && $ganadorLocal === false);
                    $resultado = $gano ? 'Ganado' : 'Perdido';
                }

                echo '<tr>';
                echo '<td>' . $fecha . '</td>';
                echo '<td>' . $torneo . '</td>';
                echo '<td>' . $competicion . '</td>';
                echo '<td>' . $fase . '</td>';
                echo '<td>' . $modalidad . '</td>';
                echo '<td>' . $loc . '</td>';
                echo '<td>' . $vis . '</td>';
                echo '<td>' . $score . '</td>';
                echo '<td>' . esc_html($resultado) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        return ob_get_clean();
    });
});
