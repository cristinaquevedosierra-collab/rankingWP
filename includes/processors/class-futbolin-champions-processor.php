<?php
/**
 * Archivo Resultante: class-futbolin-champions-processor.php
 * Ruta: includes/processors/class-futbolin-champions-processor.php
 *
 * Descripción: Procesa y genera la lista estática de Campeones de España
 * a partir de los datos de la API.
 */

if (!defined('ABSPATH')) exit;

class Futbolin_Champions_Processor {

    private $api_client;
    
    public function __construct( $api_client ) {
        $this->api_client = $api_client;
    }

    /**
     * Genera la lista de campeones a partir de los torneos.
     *
     * @return array|WP_Error La lista de campeones o un objeto de error.
     */
    public function generate_champions_list() {
        $all_tournaments = $this->api_client->get_torneos();

        if ( is_wp_error( $all_tournaments ) || empty( $all_tournaments ) ) {
            return new WP_Error( 'generation_error', 'No se pudieron obtener los datos de los torneos.' );
        }

        $campeonatos_esp_map = [
            '2024' => ['id' => 133, 'rule' => 'espana_open'], '2023' => ['id' => 124, 'rule' => 'espana'],
            '2022' => ['id' => 118, 'rule' => 'open'], '2021' => ['id' => 115, 'rule' => 'open'],
            '2019' => ['id' => 114, 'rule' => 'open'], '2018' => ['id' => 112, 'rule' => 'espana'],
            '2017' => ['id' => 110, 'rule' => 'open'], '2016' => ['id' => 101, 'rule' => 'open'],
            '2015' => ['id' => 95, 'rule' => 'open'], '2014' => ['id' => 89, 'rule' => 'open'],
            '2013' => ['id' => 21, 'rule' => 'open'], '2012' => ['id' => 16, 'rule' => 'open'],
            '2010' => ['id' => 1,  'rule' => 'open'],
        ];

        $campeones = [];

        foreach ($all_tournaments as $torneo) {
            $year = null;
            $rule = null;
            foreach ($campeonatos_esp_map as $y => $data) {
                if ($torneo->torneoId == $data['id']) {
                    $year = $y;
                    $rule = $data['rule'];
                    break;
                }
            }

            if ($year && $rule) {
                $positions = $this->api_client->get_tournament_with_positions($torneo->torneoId);
                if (!empty($positions)) {
                    foreach ($positions as $result) {
                        if (isset($result->posicion) && $result->posicion == 1) {
                            $comp_nombre = mb_strtolower(trim($result->nombreCompeticion), 'UTF-8');
                            $players = array_map('trim', explode('-', $result->equipoJugadores));
                            
                            $is_doubles = false;
                            $is_individual = false;

                            switch ($rule) {
                                case 'espana':
                                    if (strpos((string)($comp_nombre ?? ''), 'españa dobles') !== false) $is_doubles = true;
                                    if (strpos((string)($comp_nombre ?? ''), 'españa individual') !== false) $is_individual = true;
                                    break;
                                case 'espana_open':
                                    if (strpos((string)($comp_nombre ?? ''), 'españa open dobles') !== false) $is_doubles = true;
                                    if (strpos((string)($comp_nombre ?? ''), 'españa open individual') !== false) $is_individual = true;
                                    break;
                                case 'open':
                                    if (strpos((string)($comp_nombre ?? ''), 'open dobles') !== false || strpos((string)($comp_nombre ?? ''), 'open doubles') !== false) $is_doubles = true;
                                    if (strpos((string)($comp_nombre ?? ''), 'open individual') !== false || strpos((string)($comp_nombre ?? ''), 'open singles') !== false) $is_individual = true;
                                    break;
                            }

                            foreach ($players as $player_name) {
                                if (!isset($campeones[$player_name])) {
                                    $campeones[$player_name] = ['individual' => [], 'dobles' => []];
                                }
                                if ($is_doubles) $campeones[$player_name]['dobles'][] = $year;
                                if ($is_individual) $campeones[$player_name]['individual'][] = $year;
                            }
                        }
                    }
                }
            }
        }
        
        foreach ($campeones as &$hitos) {
            $hitos['dobles'] = array_unique($hitos['dobles']);
            sort($hitos['dobles']);
            $hitos['individual'] = array_unique($hitos['individual']);
            sort($hitos['individual']);
        }
        
        return $campeones;
    }
}