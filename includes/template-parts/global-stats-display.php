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
</div>
