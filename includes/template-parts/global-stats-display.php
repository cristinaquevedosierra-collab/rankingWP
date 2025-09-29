<?php if (!defined('ABSPATH')) exit; ?>
<div class="futbolin-hall-of-fame-wrapper futbolin-global-stats-wrapper">
  <h2><?php echo esc_html__('Datos globales','futbolin-api'); ?></h2>
  <p class="hall-disclaimer"><?php echo esc_html__('Plantilla estructural (sin datos).','futbolin-api'); ?></p>

  <div class="ranking-top-bar">
    <form class="futbolin-search-form" onsubmit="return false;">
      <div class="search-wrapper">
        <input type="text" class="futbolin-live-search" placeholder="<?php echo esc_attr__('Escribe para filtrar…','futbolin-api'); ?>" value="" autocomplete="off">
      </div>
    </form>
    <div class="page-size-form">
      <button type="button" data-size="25" class="active psize-btn">25</button>
      <button type="button" data-size="50" class="psize-btn">50</button>
      <button type="button" data-size="100" class="psize-btn">100</button>
      <button type="button" data-size="all" class="psize-btn"><?php echo esc_html__('Todos','futbolin-api'); ?></button>
    </div>
  </div>

  <div class="global-stats-table-container">
    <div class="ranking-header">
      <div class="ranking-th"><span class="sortable-header" data-sort="grupo" data-default="asc"><?php echo esc_html__('Grupo','futbolin-api'); ?></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="metrica" data-default="asc"><?php echo esc_html__('Métrica','futbolin-api'); ?></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="valor" data-default="desc"><?php echo esc_html__('Valor','futbolin-api'); ?></span></div>
      <div class="ranking-th"><span class="sortable-header" data-sort="notas" data-default="asc"><?php echo esc_html__('Notas','futbolin-api'); ?></span></div>
    </div>

    <div class="ranking-rows" id="global-stats-rows">
      <div class="ranking-row">
        <div class="ranking-cell"><?php echo esc_html__('General','futbolin-api'); ?></div>
        <div class="ranking-cell"><?php echo esc_html__('(Pendiente de datos)','futbolin-api'); ?></div>
        <div class="ranking-cell">—</div>
        <div class="ranking-cell"><?php echo esc_html__('Enchufaremos tus métricas aquí','futbolin-api'); ?></div>
      </div>
    </div>
  </div>

  <?php
    // === NUEVOS BLOQUES BAJO "Estadísticas globales" ===
    // Recupera opciones con fallback a "activado por defecto"
    $__opts = get_option('mi_plugin_futbolin_options', []);
    $__fefm_no1_enabled   = isset($__opts['enable_fefm_no1_club'])   ? ($__opts['enable_fefm_no1_club'] === 'on')   : true;
    $__club500_enabled    = isset($__opts['enable_club_500_played']) ? ($__opts['enable_club_500_played'] === 'on') : true;
    $__club100_enabled    = isset($__opts['enable_club_100_winners'])? ($__opts['enable_club_100_winners'] === 'on'): true;
    $__rivals_enabled     = isset($__opts['enable_top_rivalries'])   ? ($__opts['enable_top_rivalries'] === 'on')   : true;
  ?>

  <?php if ($__fefm_no1_enabled): ?>
    <div id="fefm-no1-club" data-stat-section="fefm_no1_club" class="futbolin-card futbolin-ranking-card">
      <h3 class="futbolin-section-title"><?php echo esc_html__('FEFM Nº1 CLUB','futbolin-api'); ?></h3>
      <div class="futbolin-empty-state">
        <p><?php echo esc_html__('Sin datos disponibles','futbolin-api'); ?></p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($__club500_enabled): ?>
    <div id="club-500-played" data-stat-section="club_500_played" class="futbolin-card futbolin-ranking-card">
      <h3 class="futbolin-section-title"><?php echo esc_html__('Club 500 – Played','futbolin-api'); ?></h3>
      <div class="futbolin-empty-state">
        <p><?php echo esc_html__('Sin datos disponibles','futbolin-api'); ?></p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($__club100_enabled): ?>
    <div id="club-100-winners" data-stat-section="club_100_winners" class="futbolin-card futbolin-ranking-card">
      <h3 class="futbolin-section-title"><?php echo esc_html__('Club 100 – Winners','futbolin-api'); ?></h3>
      <div class="futbolin-empty-state">
        <p><?php echo esc_html__('Sin datos disponibles','futbolin-api'); ?></p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($__rivals_enabled): ?>
    <h2 id="top-rivalries" class="futbolin-main-title" data-stat-section="top_rivalries"><?php echo esc_html__('Top rivalidades','futbolin-api'); ?></h2>
    <div class="futbolin-empty-state">
      <p><?php echo esc_html__('Sin datos disponibles','futbolin-api'); ?></p>
    </div>
  <?php endif; ?>
</div>
