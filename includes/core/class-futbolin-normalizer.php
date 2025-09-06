<?php
if (!defined('ABSPATH')) exit;

/**
 * Clase: Futbolin_Normalizer
 * Propósito: normalizar textos de competiciones, derivar claves y IDs de tipo/modalidad,
 *            y homogeneizar arrays de jugadores.
 * NOTA: Implementación defensiva (null-safe) compatible con PHP 8.1+.
 */
class Futbolin_Normalizer {

    /** mb_lower seguro */
    public static function mb_lower($s) {
        return is_string($s) ? mb_strtolower($s, 'UTF-8') : '';
    }

    /** Quita acentos y normaliza a ASCII básico */
    protected static function slug_ascii($s) {
        if (!is_string($s)) return '';
        if (function_exists('iconv')) {
            $s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        }
        $s = preg_replace('/[^A-Za-z0-9]+/u', '_', $s);
        $s = preg_replace('/_+/', '_', $s);
        return trim(strtolower($s), '_');
    }

    /** Devuelve ['key','prio','name'] a partir del nombre de la competición */
    public static function map_competicion($name) {
        $label = is_string($name) ? $name : '';
        $k = self::slug_ascii($label);

        // Heurística mínima de prioridad por tipo
        $prio = 999;
        $k_l = self::mb_lower($k);
        if (strpos($k_l, 'open') !== false && (strpos($k_l, 'individual') !== false || strpos($k_l, 'singles') !== false)) $prio = 100;
        elseif (strpos($k_l, 'open') !== false && (strpos($k_l, 'dobles') !== false || strpos($k_l, 'doubles') !== false)) $prio = 110;
        elseif (strpos($k_l, 'individual') !== false || strpos($k_l, 'singles') !== false) $prio = 200;
        elseif (strpos($k_l, 'dobles') !== false || strpos($k_l, 'doubles') !== false) $prio = 210;
        elseif (strpos($k_l, 'mixto') !== false || strpos($k_l, 'mixed') !== false) $prio = 300;
        elseif (strpos($k_l, 'rookie') !== false || strpos($k_l, 'amateur') !== false) $prio = 400;
        elseif (strpos($k_l, 'junior') !== false) $prio = 500;
        elseif (strpos($k_l, 'femenino') !== false || strpos($k_l, 'women') !== false) $prio = 600;

        return ['key' => $k ?: 'comp', 'prio' => $prio, 'name' => $label];
    }

    /** Devuelve clave detallada a partir de fila o texto */
    public static function detailed_type_key($row_or_text) {
        // Permite pasar directamente el nombre
        if (is_string($row_or_text)) {
            $k = self::slug_ascii($row_or_text);
        } else {
            // Buscar campos típicos
            $name = '';
            if (is_array($row_or_text)) {
                $name = $row_or_text['nombreCompeticion'] ?? $row_or_text['competicion'] ?? $row_or_text['modalidad'] ?? $row_or_text['tipo'] ?? '';
            } elseif (is_object($row_or_text)) {
                $name = $row_or_text->nombreCompeticion ?? ($row_or_text->competicion ?? ($row_or_text->modalidad ?? ($row_or_text->tipo ?? '')));
            }
            $k = self::slug_ascii($name);
        }

        // Normaliza sinónimos a claves canónicas
        $k_l = $k;
        if ((strpos($k_l, 'open') !== false) and (strpos($k_l, 'individual') !== false or strpos($k_l, 'singles') !== false)) return 'open_individual';
        if ((strpos($k_l, 'open') !== false) and (strpos($k_l, 'dobles') !== false or strpos($k_l, 'doubles') !== false)) return 'open_dobles';
        if (strpos($k_l, 'individual') !== false or strpos($k_l, 'singles') !== false) return 'individual';
        if (strpos($k_l, 'dobles') !== false or strpos($k_l, 'doubles') !== false) return 'dobles';
        if (strpos($k_l, 'mixto') !== false or strpos($k_l, 'mixed') !== false) return 'mixto';
        if (strpos($k_l, 'rookie') !== false or strpos($k_l, 'amateur') !== false) return 'rookie';
        if (strpos($k_l, 'junior') !== false) return 'junior';
        if (strpos($k_l, 'femenino') !== false or strpos($k_l, 'women') !== false) return 'femenino';

        return $k_l ?: 'comp';
    }

    /** Mapea clave canónica -> ID canónico (alineado con tu lógica previa) */
    public static function type_id_from_key($key) {
        $k = is_string($key) ? strtolower(trim($key)) : '';
        switch ($k) {
            case 'open_individual': return 401;
            case 'open_dobles':     return 402;
            case 'individual':      return 501;
            case 'dobles':          return 601;
            case 'mixto':           return 602;
            case 'rookie':          return 701;
            case 'amateur':         return 701;
            case 'junior':          return 801;
            case 'femenino':        return 802;
            default:                return 999; // resto / otras
        }
    }

    /** Conviene como atajo */
    public static function detailed_type_id($row_or_text) {
        return self::type_id_from_key(self::detailed_type_key($row_or_text));
    }

    /** Normaliza array de jugadores a estructura estándar */
    public static function normalize_players($arr) {
        $arr = is_array($arr) ? $arr : [];
        return array_map(function($p){
            $id   = is_array($p) ? ($p['id'] ?? ($p['jugadorId'] ?? null)) : (is_object($p) ? ($p->id ?? ($p->jugadorId ?? null)) : null);
            $name = is_array($p) ? ($p['nombre'] ?? ($p['nombreJugador'] ?? '')) : (is_object($p) ? ($p->nombre ?? ($p->nombreJugador ?? '')) : '');
            return ['jugador_id' => $id, 'jugador' => (string)$name];
        }, $arr);
    }

    /** Etiqueta de presentación */
    public static function display_label($map) {
        if (is_array($map)) {
            return trim(($map['name'] ?? ($map['tipo'] ?? '')) . '');
        } elseif (is_string($map)) {
            return $map;
        }
        return '';
    }


    /** Convierte equipoJugadores (string|array) a string legible */
    public static function smart_team_to_string($equipoJugadores) {
        if (is_string($equipoJugadores)) return $equipoJugadores;
        if (is_array($equipoJugadores)) {
            $norm = self::normalize_players($equipoJugadores);
            $names = array_map(function($p){
                return is_array($p) ? ($p['jugador'] ?? '') : (is_object($p) ? ($p->jugador ?? '') : '');
            }, $norm);
            return implode(' / ', array_filter($names));
        }
        return '';
    }

}
