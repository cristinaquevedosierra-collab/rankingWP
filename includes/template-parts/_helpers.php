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
        // Por defecto, construimos hacia la vista 'player' preservando params y usando 'jugador_id' que es lo que espera el shortcode
        return _futb_build_url_view('player', ['jugador_id' => $id]);
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
