<?php
/**
 * Archivo Resultante: class-futbolin-tournament-stats.php
 * Ruta: includes/modules/tournament/class-futbolin-tournament-stats.php
 * Fuente Original: class-futbolin-tournament-stats.php (antiguo)
 *
 * Descripción: Clase de ayuda (helper) que procesa y ordena los datos
 * de las posiciones de un torneo para mostrarlos en la plantilla.
 */
if (!defined('ABSPATH')) exit;

class Futbolin_Tournament_Stats {

    /**
     * Procesa los datos de un torneo y devuelve competiciones agrupadas y ordenadas.
     * @param array $tournament_data Datos brutos de la API.
     * @return array Competiciones agrupadas y ordenadas.
     */
    public static function prepare_competitions($tournament_data) {
        $competitions = [];

        if (!empty($tournament_data) && is_array($tournament_data)) {
            // Agrupamos los resultados por el nombre de la competición
            foreach ($tournament_data as $position) {
                $competitions[$position->nombreCompeticion][] = $position;
            }

            // Ordenamos los jugadores dentro de cada competición por su posición
            foreach ($competitions as &$results) { // Pasamos por referencia para modificar el array original
                usort($results, function($a, $b) {
                    return $a->posicion <=> $b->posicion;
                });
            }
            unset($results); // Rompemos la referencia
        }

        return $competitions;
    }
}