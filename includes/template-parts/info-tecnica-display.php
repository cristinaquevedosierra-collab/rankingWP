<?php
/**
 * Archivo: includes/template-parts/info-tecnica-display.php
 * Sección "Información Técnica y Créditos".
 */
if (!defined('ABSPATH')) exit;
include_once FUTBOLIN_API_PATH . 'includes/template-parts/_helpers.php';
?>
<div class="futbolin-about-page">

  <div class="futbolin-card">
    <h3>Arquitectura Técnica del Ranking</h3>
    <p>
      El sistema de ranking de la FEFM se sustenta sobre dos componentes principales, desarrollados a medida para garantizar la máxima fiabilidad, rendimiento y escalabilidad del proyecto:
    </p>
    <ul style="list-style-type: disc; padding-left: 20px; margin-top: 15px;">
      <li style="margin-bottom: 10px;">
        <strong>API de Datos (Backend):</strong> Constituye el núcleo central del sistema. Esta API es responsable de centralizar, procesar y servir todo el histórico de datos y cálculos del algoritmo Glicko-2 tras cada competición.
      </li>
      <li>
        <strong>Plugin de Integración (Frontend):</strong> El sitio web que estás visitando utiliza un plugin para WordPress desarrollado a medida. Su función es la de consumir los datos servidos por la API y traducirlos en una interfaz interactiva, accesible y visualmente atractiva para toda la comunidad de jugadores.
      </li>
    </ul>
  </div>

  <div class="futbolin-card">
    <h3>Equipo de Desarrollo y Agradecimientos</h3>
    <p>
      Un proyecto de esta envergadura es el resultado de incontables horas de dedicación y experiencia. La Federación desea expresar su más profundo reconocimiento a los artífices de esta plataforma:
    </p>
    <p>
      <strong>Jorge Gómez Llano — Arquitecto de Datos y Creador de la API:</strong>
      La recopilación, depuración y tratamiento de más de una década de resultados históricos, así como el diseño y desarrollo de la robusta API que alimenta este ranking, ha sido una labor monumental llevada a cabo por Jorge Gómez Llano. Su incansable trabajo es el cimiento sobre el que se construye toda esta plataforma.
    </p>
    <p>
      <strong>Héctor Núñez Sáez — Desarrollador del Plugin WordPress:</strong>
      La integración, visualización y experiencia de usuario en esta plataforma web ha sido desarrollada por Héctor Núñez Sáez. Creando este plugin a medida, ha conseguido transformar los datos en bruto de la API en una herramienta viva, interactiva y accesible para todos los jugadores de la federación.
    </p>
    <p>
      <strong>David Gañán Terrón — Dirección y Validación del Proyecto:</strong>
      El rigor, la fiabilidad y la cohesión de esta plataforma son el resultado directo de la labor de David Gañán. Como responsable de la gestión integral y validación del proyecto, su rol ha sido indispensable en cada etapa. Ha supervisado un exhaustivo proceso de pruebas funcionales, ha validado la correcta interpretación e integridad de los datos históricos y su aprobación final ha sido la garantía para que el producto cumpla con los más altos estándares de calidad exigidos por la federación.
    </p>
  </div>

  <div class="futbolin-card">
    <h3>Contacto y Colaboraciones</h3>
    <p>
      Si admiras el trabajo realizado en este ranking y estás interesado en implementar un sistema similar para tu propia liga, federación o club, o si simplemente deseas obtener más información sobre el proyecto, puedes ponerte en contacto a través del siguiente correo electrónico:
    </p>
    <p id="email-contacto" style="font-weight:bold; font-size:1.1em;"></p>
    <noscript>
      <p><em>Activa JavaScript para ver el correo. Contacto: hector [at] fefm [dot] es</em></p>
    </noscript>
  </div>

  <script>
    // Construye el enlace de email sin usar innerHTML (menos superficie para inyección)
    document.addEventListener('DOMContentLoaded', function() {
      var user = 'hector';
      var domain = 'fefm.es';
      var emailContainer = document.getElementById('email-contacto');
      if (!emailContainer) return;

      var a = document.createElement('a');
      var addr = user + '@' + domain;
      a.setAttribute('href', 'mailto:' + addr);
      a.appendChild(document.createTextNode(addr));

      // Limpia por si acaso y agrega el anchor
      while (emailContainer.firstChild) emailContainer.removeChild(emailContainer.firstChild);
      emailContainer.appendChild(a);
    });
  </script>

</div>