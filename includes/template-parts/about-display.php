<?php
/**
 * Archivo: includes/template-parts/about-display.php
 * Sección "Acerca de" (tab de información).
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
?>
<div class="futbolin-about-page">

  <div class="futbolin-card">
    <h3>Objeto y Ámbito del Ranking</h3>
    <p>
      El presente ranking de la Federación Española de Futbolín (FEFM) tiene por objeto establecer una clasificación oficial de jugadores basada en el rendimiento deportivo demostrado en las competiciones de la modalidad “una pierna” que integran el calendario oficial a nivel nacional.
    </p>
  </div>

  <div class="futbolin-card">
    <h3>Sistema de Puntuación y Categorías</h3>
    <p>
      El sistema de puntuación se rige por el algoritmo <strong>Glicko-2</strong>, un método de calificación avanzado inspirado en el modelo alemán P4P. Dicho sistema evalúa el rendimiento relativo entre dos contendientes en cada partida, calculando una ganancia o pérdida de puntos en función del resultado y la diferencia de puntos previa entre ambos.
    </p>
    <p>
      Paralelamente al sistema de puntos, se establece una clasificación por niveles de destreza. El ascenso de categoría se produce de forma automática al finalizar cada temporada para aquellos jugadores que hayan alcanzado el umbral de puntos requerido:
    </p>

    <table class="wp-list-table widefat striped fixed" style="margin-top:20px;">
      <caption class="screen-reader-text">Categorías y condiciones</caption>
      <thead>
        <tr>
          <th scope="col">Categoría</th>
          <th scope="col" style="text-align:center;">Puntos de Acceso</th>
          <th scope="col" style="text-align:center;">Condición de Descenso</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>Rookie</strong></td>
          <td colspan="2" style="text-align:center;font-style:italic;">Categoría inicial. Sin ascenso ni descenso por puntos.</td>
        </tr>
        <tr>
          <td><strong>Amateur</strong></td>
          <td style="text-align:center;">1450 puntos</td>
          <td style="text-align:center;">—</td>
        </tr>
        <tr>
          <td><strong>Master</strong></td>
          <td style="text-align:center;">1700 puntos</td>
          <td style="text-align:center;">—</td>
        </tr>
        <tr>
          <td><strong>Élite</strong></td>
          <td style="text-align:center;">2150 puntos</td>
          <td style="text-align:center;">Bajar de 2000 puntos</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="futbolin-card">
    <h3>Título de Gran Maestro (GM)</h3>
    <p>
      Se instituye la distinción de <strong>Gran Maestro (GM)</strong> como el máximo reconocimiento a la trayectoria deportiva, la excelencia competitiva y la dedicación extraordinaria al futbolín. Este título honorífico, sustentado por más de 15 años de datos históricos, no afecta a la puntuación del ranking, pero representa el mayor logro que un jugador puede alcanzar a nivel nacional.
    </p>
    <p>
      La obtención de dicha distinción está supeditada al cumplimiento de los siguientes requisitos acumulativos en campeonatos puntuables para la FEFM:
    </p>

    <table class="wp-list-table widefat striped fixed" style="margin-top:20px;">
      <caption class="screen-reader-text">Requisitos para Gran Maestro</caption>
      <thead>
        <tr>
          <th scope="col">Requisitos para la obtención del título de Gran Maestro</th>
          <th scope="col" style="text-align:center;">Dobles</th>
          <th scope="col" style="text-align:center;">Individual</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Torneos disputados en categoría OPEN en campeonatos puntuables</td>
          <td style="text-align:center;">15</td>
          <td style="text-align:center;">15</td>
        </tr>
        <tr>
          <td>Podios (1º, 2º o 3º) en categoría OPEN en campeonatos puntuables</td>
          <td style="text-align:center;">3</td>
          <td style="text-align:center;">3</td>
        </tr>
        <tr>
          <td>Clasificación entre los 5 primeros en Campeonatos de España</td>
          <td style="text-align:center;">2</td>
          <td style="text-align:center;">2</td>
        </tr>
        <tr>
          <td>Clasificación entre los 5 primeros en categoría OPEN en campeonatos puntuables</td>
          <td style="text-align:center;">10</td>
          <td style="text-align:center;">10</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="futbolin-card">
    <h3>Normativa de Actividad</h3>
    <p>
      Con efecto <strong>desde la temporada 2024</strong> (iniciada el 01/01/2024), se establece como requisito para todos los jugadores con licencia la participación en, al menos, el <strong>50% de los torneos puntuables del calendario anual</strong>. El incumplimiento de esta normativa conllevará una retracción de 100 puntos en las modalidades de individual y dobles, que se hará efectiva en la actualización previa al Campeonato de España de final de temporada.
    </p>
  </div>

  <div class="futbolin-card">
    <h3>Fundamento Técnico del Algoritmo</h3>
    <p>
      El sistema Glicko-2 es un método de calificación de jugadores que perfecciona el sistema ELO, tradicionalmente usado por la FIDE en ajedrez. Su principal ventaja es la inclusión de una variable de “fiabilidad de la puntuación” (RD — <em>Ratings Deviation</em>), que permite que los puntos ganados o perdidos sean mayores o menores en función de la regularidad con la que compite un jugador y la fiabilidad de su ranking actual.
    </p>
    <p>
      Para una descripción técnica detallada del algoritmo, se recomienda consultar la documentación de la
      <a href="<?php echo esc_url('https://www.players4players.de/en/node/29'); ?>" target="_blank" rel="noopener noreferrer">P4P Alemana</a>, sistema en el que se basa fundamentalmente este ranking.
    </p>
  </div>

</div>