/* finals-sort.js
 * - Ordenación por columnas (toggle asc/desc)
 * - Filtro por texto (jugador/pareja)
 */
jQuery(function($){

  // === ORDENACIÓN ===
  function compareVals(a, b, type, dir) {
    if (type === 'num') {
      const na = parseFloat(a); const nb = parseFloat(b);
      if (isNaN(na) && isNaN(nb)) return 0;
      if (isNaN(na)) return (dir === 'asc') ? -1 : 1;
      if (isNaN(nb)) return (dir === 'asc') ? 1 : -1;
      return (dir === 'asc') ? (na - nb) : (nb - na);
    } else {
      // alpha
      const sa = (a || '').toString();
      const sb = (b || '').toString();
      return (dir === 'asc') ? sa.localeCompare(sb) : sb.localeCompare(sa);
    }
  }

  function getCellSortVal($row, colIndex, type) {
    const $cells = $row.children();
    const $cell  = $cells.eq(colIndex);
    const attr   = $cell.attr('data-sort-val');
    if (typeof attr !== 'undefined') return attr;
    return $cell.text().trim().toLowerCase();
  }

  function doSort($container, colIndex, type, dir, $header) {
    const $rowsWrap = $container.find('.ranking-table-content').first();
    const $rows = $rowsWrap.children('.ranking-row');

    const arr = $rows.get().map(el => {
      const $r = $(el);
      const val = getCellSortVal($r, colIndex, type);
      return { el, val };
    });

    arr.sort((a,b) => compareVals(a.val, b.val, type, dir));

    // Pintar
    arr.forEach(obj => $rowsWrap.append(obj.el));
    // Quita pares/impares y recalcula
    $rowsWrap.children('.ranking-row').removeClass('even odd').each(function(i){
      $(this).addClass(i % 2 === 0 ? 'even' : 'odd');
    });

    // Flechas y activos
    const $headers = $container.find('.ranking-header .sortable-header');
    $headers.removeClass('active asc desc');
    $header.addClass('active').addClass(dir);
    $headers.find('.sort-arrow').text('');
    $header.find('.sort-arrow').text(dir === 'asc' ? '▲' : '▼');
  }

  $('body').on('click', '.finals-table-container .ranking-header .sortable-header', function(e){
    e.preventDefault();
    const $h = $(this);
    const $cont = $h.closest('.finals-table-container');

    const colIndex = parseInt($h.data('col'), 10) || 0;
    const type = $h.data('type') === 'alpha' ? 'alpha' : 'num';
    let dir = $h.hasClass('active') ? ($h.hasClass('asc') ? 'desc' : 'asc') : ($h.data('default') || 'desc');

    doSort($cont, colIndex, type, dir, $h);
  });

  // Inicializa orden por defecto (marcado en data-default/active)
  $('.finals-table-container').each(function(){
    const $cont = $(this);
    const $default = $cont.find('.ranking-header .sortable-header.active').first();
    if ($default.length) {
      const colIndex = parseInt($default.data('col'), 10) || 0;
      const type = $default.data('type') === 'alpha' ? 'alpha' : 'num';
      const dir = $default.data('default') || 'desc';
      // pinta flecha sin forzar resort si ya está pintado
      doSort($cont, colIndex, type, dir, $default);
    }
  });

  // === FILTRO ===
  function normalize(s){ return (s||'').toString().toLowerCase(); }

  $('body').on('input', '#finals-filter', function(){
    const q = normalize($(this).val());
    const $container = $(this).closest('.futbolin-finals-wrapper').find('.finals-table-container').first();
    const $rows = $container.find('.ranking-table-content .ranking-row');

    if (!q) {
      $rows.show();
    } else {
      $rows.each(function(){
        const $r = $(this);
        const nameCell = $r.children().first(); // primera celda = nombre
        const val = normalize(nameCell.text());
        $r.toggle(val.indexOf(q) !== -1);
      });
    }

    // Reaplicar zebra
    const $visible = $rows.filter(':visible');
    $rows.removeClass('even odd');
    $visible.each(function(i){
      $(this).addClass(i % 2 === 0 ? 'even' : 'odd');
    });
  });

});
