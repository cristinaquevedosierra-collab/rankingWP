<?php
if (!defined('ABSPATH')) exit;
$inc = rtrim(dirname(__DIR__), '/\\') . '/';
@include_once $inc . 'template-parts/_helpers.php';
$torneos = isset($data['torneos']) && is_array($data['torneos']) ? $data['torneos'] : [];
$page    = isset($data['page']) ? intval($data['page']) : 1;
$hasMore = !empty($data['has_more']);
$baseUrl = isset($data['base_url']) ? $data['base_url'] : '';

echo '<div class="futbolin-card">';
echo '<h3 class="comp-title">Torneos</h3>';
if (empty($torneos)) {
  echo '<p>No hay torneos disponibles.</p>';
} else {
  echo '<ul class="futbolin-list">';
  foreach ($torneos as $t) {
    $tid = isset($t->torneoId) ? intval($t->torneoId) : 0;
    $nm  = isset($t->nombreTorneo) ? (string)$t->nombreTorneo : 'Torneo';
    $fc  = isset($t->fechaCelebracion) ? (string)$t->fechaCelebracion : (isset($t->fecha)?(string)$t->fecha:'');
    $lnk = $baseUrl . (strpos($baseUrl, '?')===false ? '?' : '&') . 'torneo_id=' . $tid;
    echo '<li><a class="futbolin-link" href="' . esc_attr($lnk) . '">' . esc_html($nm) . '</a>';
    if ($fc) echo ' <span class="muted">(' . esc_html($fc) . ')</span>';
    echo '</li>';
  }
  echo '</ul>';
}
echo '</div>';
