<?php
/**
 * Archivo: _helpers.php
 * Compat: admite filas como array o stdClass
 */
if (!defined('ABSPATH')) exit;

/* ======================================================
 * Utils: acceso seguro array|objeto
 * ====================================================== */
if (!function_exists('_futb_get')) {
    function _futb_get($row, $key, $default = null) {
        if (is_array($row) && array_key_exists($key, $row)) return $row[$key];
        if (is_object($row) && isset($row->$key)) return $row->$key;
        return $default;
    }
}

/* ======================================================
 * WordPress compatibility functions
 * ====================================================== */
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        if (empty($url)) {
            $url = $_SERVER['REQUEST_URI'];
        }
        
        $url_parts = parse_url($url);
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query_args);
        } else {
            $query_args = array();
        }
        
        $query_args = array_merge($query_args, (array)$args);
        $url = $url_parts['path'] . '?' . http_build_query($query_args);
        
        if (isset($url_parts['fragment'])) {
            $url .= '#' . $url_parts['fragment'];
        }
        
        return $url;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        $home = rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'], '/');
        return $home . '/' . ltrim($path, '/');
    }
}

if (!function_exists('get_page_by_path')) {
    function get_page_by_path($path) {
        // Fallback simple - retorna null si no está WordPress
        return null;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id) {
        // Fallback simple - usa home_url
        return home_url();
    }
}

/* ======================================================
 * SLUGIFY
 * ====================================================== */
if (!function_exists('_futb_slugify')) {
    function _futb_slugify($text) {
        if (!is_string($text) || $text==='') return '';
        $s = trim($text);
        $s = function_exists('iconv') ? iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s) : $s;
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/','-',$s);
        $s = preg_replace('/-+/','-',$s);
        return trim($s,'-');
    }
}

/* ======================================================
 * FLAGS Y LABELS
 * ====================================================== */
if (!function_exists('_futb_using_new_ids')) {
    function _futb_using_new_ids($row) {
        return _futb_get($row, 'tipoCampeonatoId') !== null || _futb_get($row, 'modalidadId') !== null;
    }
}

if (!function_exists('_futb_set_flag')) {
    function _futb_set_flag(&$arr, $key, $val=true) {
        if (is_array($arr)) $arr[$key] = $val;
    }
}

if (!function_exists('_futb_label_tipo_campeonato')) {
    function _futb_label_tipo_campeonato($id) {
        $map = [1=>'Élite GM',2=>'Élite Plata',3=>'Nacional',4=>'Promoción'];
        return $map[(int)$id] ?? '';
    }
}

if (!function_exists('_futb_label_modalidad')) {
    function _futb_label_modalidad($id) {
        $map = [1=>'Individual',2=>'Parejas',3=>'Equipos'];
        return $map[(int)$id] ?? '';
    }
}

if (!function_exists('_futb_css_from_ids')) {
    function _futb_css_from_ids($tipoId, $modalidadId) {
        $classes = [];
        if (!empty($tipoId))      $classes[] = 'tipo-'.(int)$tipoId;
        if (!empty($modalidadId)) $classes[] = 'mod-'.(int)$modalidadId;
        return implode(' ', $classes);
    }
}

if (!function_exists('_futb_extract_container')) {
    function _futb_extract_container($row, $key) {
        return _futb_get($row, $key, '');
    }
}

/* ======================================================
 * URL HELPERS
 * ====================================================== */
if (!function_exists('_futb_preserve_params')) {
    function _futb_preserve_params($extra=[]) {
        $current = $_GET ?? [];
        unset($current['view']);
        return array_merge($current, $extra);
    }
}

if (!function_exists('_futb_build_url_view')) {
    function _futb_build_url_view($view, $extra=[]) {
        $args = _futb_clean_args($extra);
        $args['view'] = $view;
        return esc_url(add_query_arg($args));
    }
}

if (!function_exists('_futb_url_tournaments')) {
    function _futb_url_tournaments($extra = []) {
        $args = _futb_clean_args($extra);
        $args['view'] = 'tournaments';
        return esc_url(add_query_arg($args));
    }
}

if (!function_exists('_futb_url_ranking')) {
    function _futb_url_ranking() { return _futb_build_url_view('ranking'); }
}

/* ======================================================
 * Jugadores (array|obj)
 * ====================================================== */
if (!function_exists('_futb_player_id_from_row')) {
    function _futb_player_id_from_row($row) {
        $candidates = ['jugador_id','jugadorId','id','idJugador','playerId'];
        foreach ($candidates as $k) {
            $val = _futb_get($row, $k);
            if ($val !== null && $val !== '') return $val;
        }
        return null;
    }
}
if (!function_exists('_futb_player_name_from_row')) {
    function _futb_player_name_from_row($row) {
        $candidates = ['jugador','nombreJugador','nombre','nombre_completo','alias','name'];
        foreach ($candidates as $k) {
            $val = _futb_get($row, $k);
            if (is_string($val) && $val !== '') return $val;
        }
        // último recurso: si hay id, mostrar "Jugador #ID" para no dejar vacío
        $id = _futb_player_id_from_row($row);
        return $id ? ('Jugador #' . $id) : '';
    }
}
if (!function_exists('_futb_url_player')) {
    function _futb_url_player($row) {
        $id = _futb_player_id_from_row($row);
        if (!$id) return '#';
        // Preferimos la página "perfil-jugador" si existe; si no, "futbolin-jugador"; como último recurso, construimos el slug directo
        $base = '';
        $maybe = get_page_by_path('perfil-jugador');
        if ($maybe) {
            $base = get_permalink($maybe->ID);
        } else {
            $maybe2 = get_page_by_path('futbolin-jugador');
            if ($maybe2) {
                $base = get_permalink($maybe2->ID);
            } else {
                $base = home_url('/perfil-jugador/');
            }
        }
        return add_query_arg(['jugador_id' => $id], $base);
    }
}
if (!function_exists('_futb_link_player')) {
    function _futb_link_player($row, $label_or_url=null) {
        $name = _futb_player_name_from_row($row);
        $id   = _futb_player_id_from_row($row);

        $is_url = is_string($label_or_url) && preg_match('#^https?://#i', $label_or_url);

        if ($is_url && $id) {
            // Si el segundo parámetro es una URL base del perfil, añadir 'jugador_id' (lo que consume el shortcode)
            $href  = esc_url( add_query_arg(['jugador_id'=>$id], $label_or_url) );
            $label = $name ?: ('Jugador #' . $id);
        } else {
            // Comportamiento estándar: construir URL con helpers
            $href  = _futb_url_player($row);
            $label = ($label_or_url !== null && !$is_url) ? $label_or_url : ($name ?: ($id ? 'Jugador #'.$id : 'Jugador'));
        }

        return '<a href="'.esc_url($href).'">'.esc_html($label).'</a>';
    }
}
/* ======================================================
 * Competiciones
 * ====================================================== */
if (!function_exists('_futb_competition_map')) {
    function _futb_competition_map($row) {
        $tipoId = _futb_get($row, 'tipoCampeonatoId');
        $modId  = _futb_get($row, 'modalidadId');
        return ['tipo'=>_futb_label_tipo_campeonato($tipoId), 'modalidad'=>_futb_label_modalidad($modId)];
    }
}

if (!function_exists('_futb_competition_display_label')) {
    function _futb_competition_display_label($row) {
        $map = _futb_competition_map($row);
        return trim(($map['tipo'] ?? '').' '.($map['modalidad'] ?? ''));
    }
}

if (!function_exists('_futb_normalize_players')) {
    function _futb_normalize_players($arr) {
        $arr = is_array($arr) ? $arr : [];
        return array_map(function($p){
            $id   = _futb_get($p, 'id');
            $name = _futb_get($p, 'nombre');
            return ['jugador_id'=>$id, 'jugador'=>$name];
        }, $arr);
    }
}


/** Marca parámetros a eliminar para evitar "arrastre" en add_query_arg */
if (!function_exists('_futb_clean_args')) {
    function _futb_clean_args($extra = []) {
        $args = [];
        foreach (['torneo_id','modalidad','info_type','tpage','fpage','page','ppage','ppage_size','pageSize','psize'] as $k) {
            $args[$k] = false; // WP elimina el parámetro si es false
        }
        return array_merge($args, $extra);
    }
}

/* ======================================================
 * Reglas de EXCLUSIÓN (persistentes)
 * ====================================================== */
if (!defined('FUTBOLIN_EXCLUDED_TIPO_COMP_IDS')) {
    // Nunca contabilizar estas competiciones (Penalización por no jugar)
    define('FUTBOLIN_EXCLUDED_TIPO_COMP_IDS', [20, 21]);
}

if (!function_exists('_futb_is_penalty_row')) {
    /**
     * Detecta si una fila/partido corresponde a penalizaciones o inactividad.
     * Criterios:
     *  - tipoCompeticionId ∈ FUTBOLIN_EXCLUDED_TIPO_COMP_IDS
     *  - nombre de tipoCompeticion contiene 'Penalizacion por no jugar'
     *  - equipoLocal/Visitante == 'NOEQUIPO'
     */
    
if (!function_exists('_futb_is_liguilla_fase')) {
    /**
     * Devuelve true si la fase indica 'Liguilla' (case-insensitive).
     * Admite recibir solo la cadena $fase o una fila completa $row.
     */
    function _futb_is_liguilla_fase($fase, $row = null) {
        $f = is_string($fase) ? $fase : '';
        if ($f === '' && $row) {
            $f = (string)_futb_get($row, 'fase', '');
        }
        return stripos($f, 'liguilla') !== false;
    }
}
function _futb_is_penalty_row($row) {
        $get = function($k) use ($row) {
            if (is_array($row)) return $row[$k] ?? null;
            if (is_object($row)) return $row->$k ?? null;
            return null;
        };
        $tipoId = null;
        foreach (['tipoCompeticionId','idTipoCompeticion','tipoId'] as $f) {
            $v = $get($f);
            if (is_numeric($v)) { $tipoId = (int)$v; break; }
        }
        if ($tipoId !== null && in_array($tipoId, (array)FUTBOLIN_EXCLUDED_TIPO_COMP_IDS, true)) return true;

        $tipoNom = '';
        foreach (['tipoCompeticion','tipoCompeticionNombre','tipoNombre','tipo'] as $f) {
            $v = $get($f);
            if (is_string($v) && $v !== '') { $tipoNom = $v; break; }
        }
        if ($tipoNom && preg_match('/penalizacion\s+por\s+no\s+jugar/i', $tipoNom)) return true;

        $equipoLocal     = $get('equipoLocal') ?? '';
        $equipoVisitante = $get('equipoVisitante') ?? '';
        if ($equipoLocal === 'NOEQUIPO' || $equipoVisitante === 'NOEQUIPO') return true;

        return false;
    }
}

if (!function_exists('_futb_has_real_result')) {
    /**
     * Determina si el partido tiene un resultado real (no nulo).
     * Considera varios esquemas posibles del API.
     */
    function _futb_has_real_result($row) {
        $get = function($k) use ($row) {
            if (is_array($row)) return $row[$k] ?? null;
            if (is_object($row)) return $row->$k ?? null;
            return null;
        };
        // ganadorLocal boolean explícito
        $gl = $get('ganadorLocal');
        if (is_bool($gl)) return true;

        // Marcadores numéricos locales/visitantes
        $pL = $get('puntosLocal'); $pV = $get('puntosVisitante');
        if (is_numeric($pL) && is_numeric($pV)) return true;

        $gL = $get('golesLocal');  $gV = $get('golesVisitante');
        if (is_numeric($gL) && is_numeric($gV)) return true;

        // Puntuación final o puntosGanados
        $pf = $get('puntuacionFinal'); $pg = $get('puntosGanados');
        if (is_numeric($pf) || is_numeric($pg)) return true;
        return false;
    }
}


/**
 * Determina si una fila pertenece a categorías que NO deben computar en ningún lado (p. ej., DYP o ProAm).
 * Detecta en: tipoCompeticion, competicion, categoria, nombreCompeticion...
 */
if (!function_exists('_futb_is_excluded_special_mode')) {
    function _futb_is_excluded_special_mode($row) {
        $get = function($k) use ($row) {
            if (is_array($row)) return $row[$k] ?? null;
            if (is_object($row)) return $row->$k ?? null;
            return null;
        };
        $fields = [
            'tipoCompeticion','tipoCompeticionNombre','tipoNombre','tipo',
            'competicion','competicionNombre','nombreCompeticion','categoria','categoriaNombre'
        ];
        $txt = '';
        foreach ($fields as $f) {
            $v = $get($f);
            if (is_string($v) && $v !== '') { $txt .= ' ' . $v; }
        }
        if ($txt === '') return false;
        $txt = mb_strtolower($txt);
        // DYP, "draw your partner", ProAm (pro-am, pro am, proam)
        if (preg_match('/\b(dyp|draw\s*your\s*partner)\b/i', $txt)) return true;
        if (preg_match('/\bpro\s*[-\s]?am\b/i', $txt)) return true;
        return false;
    }
}

if (!function_exists('_futb_should_count_for_stats')) {
    /**
     * Predicado de negocio alineado con player-stats.php:
     * - Incluye Liguilla
     * - Excluye penalizaciones/NOEQUIPO
     * - Excluye DYP/ProAm
     * - Requiere resultado real
     */
    function _futb_should_count_for_stats($row) {
        if (_futb_is_penalty_row($row)) return false;
        // (PATCH6) Ya no se excluye DYP/ProAm: se contabilizan en estadísticas del perfil.
        if (!_futb_has_real_result($row)) return false;
        return true;
    }
}


if (!function_exists('_futb_player_in_team')) {
    function _futb_player_in_team($equipoDTO, $equipoStr, $player_id, $player_name = '') {
        // 1) Por DTO con jugadores
        if (is_object($equipoDTO) && isset($equipoDTO->jugadores) && is_array($equipoDTO->jugadores)) {
            foreach ($equipoDTO->jugadores as $j) {
                $jid = isset($j->jugadorId) ? intval($j->jugadorId) : null;
                if ($jid !== null && (int)$jid === (int)$player_id) return true;
            }
        } elseif (is_array($equipoDTO) && isset($equipoDTO['jugadores']) && is_array($equipoDTO['jugadores'])) {
            foreach ($equipoDTO['jugadores'] as $j) {
                $jid = isset($j['jugadorId']) ? intval($j['jugadorId']) : null;
                if ($jid !== null && (int)$jid === (int)$player_id) return true;
            }
        }
        // 2) Fallback simple por nombre (si se proporciona)
        if (is_string($player_name) && $player_name !== '') {
            $parts = preg_split('/\s+/', $player_name);
            $team_string = is_string($equipoStr) ? $equipoStr : '';
            $ok = true;
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '' || strlen($p) <= 1) continue;
                if (stripos($team_string, $p) === false) { $ok = false; break; }
            }
            if ($ok && $team_string !== '') return true;
        }
        return false;
    }
}

if (!function_exists('_futb_get_ganador_local')) {
    function _futb_get_ganador_local($row) {
    // Acepta 'ganadorLocal' o 'ganoLocal' (variante)
    if (is_object($row)) {
        if (isset($row->ganadorLocal)) return (bool)$row->ganadorLocal;
        if (isset($row->ganoLocal))    return (bool)$row->ganoLocal;
    } elseif (is_array($row)) {
        if (array_key_exists('ganadorLocal', $row)) return (bool)$row['ganadorLocal'];
        if (array_key_exists('ganoLocal', $row))    return (bool)$row['ganoLocal'];
    }
    return null;
}

if (!function_exists('_futb_won_match')) {
    /**
     * Determina si el jugador ganó el partido.
     * Usa 'ganadorLocal' o 'ganoLocal' y detecta lado local/visitante por DTO y fallback de nombre.
     */
    function _futb_won_match($row, $player_id, $player_name = '') {
    $gl = _futb_get_ganador_local($row);
    if ($gl === null) return null;

    $equipoLocalDTO     = _futb_get($row, 'equipoLocalDTO');
    $equipoVisitanteDTO = _futb_get($row, 'equipoVisitanteDTO');
    $equipoLocalStr     = (string)_futb_get($row, 'equipoLocal', '');
    $equipoVisitStr     = (string)_futb_get($row, 'equipoVisitante', '');

    if (!function_exists('_futb_player_in_team')) return null;
    $is_local = _futb_player_in_team($equipoLocalDTO, $equipoLocalStr, $player_id, $player_name);
    $is_visit = _futb_player_in_team($equipoVisitanteDTO, $equipoVisitStr, $player_id, $player_name);

    if (!$is_local && !$is_visit) return null;

    return ($is_local && $gl === true) || ($is_visit && $gl === false);
}

}}
