<?php
if (!defined('ABSPATH')) exit;

// Cargar sets (nuevo) con fallback a drafts (antiguo)
$sets_new = get_option('futb_rankgen_sets', array());
$drafts_old = get_option('futb_rankgen_drafts', array());
// Merge no destructivo: prioridad a nuevo si existe, si no, usar antiguo
$drafts = is_array($sets_new) && count($sets_new) ? $sets_new : $drafts_old;
$active_slug = isset($_GET['slug']) ? sanitize_title($_GET['slug']) : '';
$set = array();
if ($active_slug && isset($drafts[$active_slug])) { $set = $drafts[$active_slug]; }

$def = function($k,$d) use ($set){ return isset($set[$k]) ? $set[$k] : $d; };

$rg_name       = sanitize_text_field($def('name', ''));
$rg_slug       = sanitize_title($def('slug', $rg_name));
$enabled       = !empty($def('is_enabled','1'));
$scope         = sanitize_text_field($def('scope','ESP'));
$modalidades   = (array)$def('modalidades', array('1','2'));
$temporadaId   = sanitize_text_field($def('temporadaId',''));
$fase_liguilla = !empty($def('include_liguilla','1'));
$fase_cruces   = !empty($def('include_cruces','1'));
$min_partidos  = (int)$def('min_partidos', 100);
$min_competic  = (int)$def('min_competiciones', 1);
$top_n         = (int)$def('top_n', 25);
$sort_field    = sanitize_text_field($def('sort_field', 'win_rate_partidos'));
$sort_dir      = sanitize_text_field($def('sort_dir', 'desc'));
$selected_cols = (array)$def('columns', array('posicion_estatica','nombre','partidas_jugadas','partidas_ganadas','win_rate_partidos','competiciones_jugadas','competiciones_ganadas','win_rate_competiciones'));
$tipos_comp    = (array)$def('tipos_comp', array());
$torneos_sel   = (array)$def('torneos', array());
// Nuevo: descripción textual para mostrar bajo el título en el front
$rg_description = (string)$def('description', '');
// Nuevos ids guardados (si existen)
$torneo_ids_saved = (array)$def('torneoIds', array());
$comp_ids_saved   = (array)$def('competicionIds', array());
// Nuevos ajustes
$torneos_all   = !empty($def('torneos_all',''));
$front_hide_sb = !empty($def('front_hide_sidebar',''));

$admin_post = esc_url( admin_url('admin-post.php') );
$rankgen_url = esc_url( admin_url('admin.php?page=futbolin-api-settings&tab=rankgen') );

// Sincronizar estado con Configuración global si existe la preferencia
try {
  if ($rg_slug) {
    $opts = get_option('mi_plugin_futbolin_options', array());
    if (is_array($opts)) {
      $gkey = 'enable_rankgen__' . sanitize_key($rg_slug);
      if (array_key_exists($gkey, $opts)) {
        $enabled = !empty($opts[$gkey]);
      }
    }
  }
} catch (\Throwable $e) { /* ignore */ }

?>
<div class="futbolin-card">
  <h2><?php echo esc_html__('Generador de listados de estadisticas','futbolin'); ?></h2>

    <form method="get" action="">
        <input type="hidden" name="page" value="futbolin-api-settings"/>
        <input type="hidden" name="tab" value="rankgen"/>
    <label><?php echo esc_html__('Seleccionar listado','futbolin'); ?>:
            <select name="slug" onchange="this.form.submit()">
                <option value=""><?php echo esc_html__('(nuevo)','futbolin'); ?></option>
                <?php foreach ($drafts as $_slug => $_data) :
                    $label = isset($_data['name']) && $_data['name']!=='' ? $_data['name'] : $_slug; ?>
                    <option value="<?php echo esc_attr($_slug); ?>" <?php selected($active_slug, $_slug); ?>>
                        <?php echo esc_html($label.' ('.$_slug.')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <hr/>

    <form method="post" action="<?php echo $admin_post; ?>">
  <?php wp_nonce_field('futb_rankgen_save'); ?>
  <input type="hidden" name="action" id="futb-rankgen-action" value="futb_rankgen_save" />
  <input type="hidden" name="set[slug]" value="<?php echo esc_attr($rg_slug); ?>" />

        <table class="form-table" role="presentation"><tbody>
            <tr>
                <th><?php echo esc_html__('Estado','futbolin'); ?></th>
                <td><label><input type="checkbox" name="set[is_enabled]" value="1" <?php echo $enabled?'checked':''; ?>/> <?php echo esc_html__('Activado','futbolin'); ?></label></td>
            </tr>
            <tr>
        <th><?php echo esc_html__('Nombre del listado','futbolin'); ?></th>
                <td><input type="text" name="set[name]" value="<?php echo esc_attr($rg_name); ?>" class="regular-text"/></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Descripción','futbolin'); ?></th>
                <td>
                  <textarea name="set[description]" rows="3" class="large-text" placeholder="<?php echo esc_attr__('Texto explicativo que aparecerá bajo el título (opcional). Se admite HTML básico).','futbolin'); ?>"><?php echo esc_textarea($rg_description); ?></textarea>
                  <p class="description"><?php echo esc_html__('Ejemplo: “Listado de jugadores noveles (Rookie) con métricas calculadas solo sobre esta modalidad.”','futbolin'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Slug (URL)','futbolin'); ?></th>
                <td><input type="text" name="set[slug]" value="<?php echo esc_attr($rg_slug); ?>" class="regular-text"/></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Ámbito','futbolin'); ?></th>
                <td>
                    <label><input type="radio" name="set[scope]" value="ESP" <?php checked($scope,'ESP'); ?>/> ESP</label>
                    <label><input type="radio" name="set[scope]" value="EXT" <?php checked($scope,'EXT'); ?>/> EXT</label>
                    <label><input type="radio" name="set[scope]" value="ALL" <?php checked($scope,'ALL'); ?>/> ALL</label>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Modalidades','futbolin'); ?></th>
                <td>
                    <label><input type="checkbox" name="set[modalidades][]" value="1" <?php echo in_array('1',$modalidades)?'checked':''; ?>/> <?php echo esc_html__('Individual (1)','futbolin'); ?></label>
                    <label><input type="checkbox" name="set[modalidades][]" value="2" <?php echo in_array('2',$modalidades)?'checked':''; ?>/> <?php echo esc_html__('Dobles (2)','futbolin'); ?></label>
                </td>
            </tr>
            
            <tr>
                <th><?php echo esc_html__('Fases a incluir','futbolin'); ?></th>
                <td>
                    <label><input type="checkbox" name="set[include_liguilla]" value="1" <?php echo $fase_liguilla?'checked':''; ?>/> <?php echo esc_html__('Liguilla','futbolin'); ?></label>
                    <label><input type="checkbox" name="set[include_cruces]" value="1" <?php echo $fase_cruces?'checked':''; ?>/> <?php echo esc_html__('Eliminación directa','futbolin'); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Mínimos','futbolin'); ?></th>
                <td>
                    <label><?php echo esc_html__('Partidos','futbolin'); ?>: <input type="number" min="0" name="set[min_partidos]" value="<?php echo esc_attr($min_partidos); ?>" style="width:90px"/></label>
                    <label><?php echo esc_html__('Competiciones','futbolin'); ?>: <input type="number" min="0" name="set[min_competiciones]" value="<?php echo esc_attr($min_competic); ?>" style="width:90px"/></label>
      <label><?php echo esc_html__('Victorias (HOF/Club)','futbolin'); ?>: <input type="number" min="0" name="set[min_victorias]" value="<?php echo esc_attr( (int)$def('min_victorias', 0) ); ?>" style="width:110px"/></label>
                </td>
            </tr>
      <tr>
        <th><?php echo esc_html__('Condiciones especiales','futbolin'); ?></th>
        <td>
          <label><input type="checkbox" name="set[require_campeonato]" value="1" <?php echo !empty($def('require_campeonato',''))?'checked':''; ?>/> <?php echo esc_html__('Exigir al menos 1 campeonato ganado (HOF)','futbolin'); ?></label>
          <p class="description"><?php echo esc_html__('Útil para Hall of Fame o listados de élite: combina con “Victorias” y filtros por fases (sin liguilla).','futbolin'); ?></p>
        </td>
      </tr>
            <tr>
                <th><?php echo esc_html__('Columnas a mostrar','futbolin'); ?></th>
                <td>
          <?php $all_cols = array(
            'posicion_estatica'      => 'Posición',
            'nombre'                 => 'Jugador',
            'partidas_jugadas'       => 'Partidas',
            'partidas_ganadas'       => 'Ganadas',
            'win_rate_partidos'      => '% Ganados (partidos)',
            'competiciones_jugadas'  => 'Comp. jugadas',
            'competiciones_ganadas'  => 'Comp. ganadas',
            'win_rate_competiciones' => '% Comp.',
          ); ?>
                    <?php foreach ($all_cols as $_k => $_label) : ?>
                        <label style="display:inline-block;margin:2px 10px 2px 0">
                            <input type="checkbox" name="set[columns][]" value="<?php echo esc_attr($_k); ?>" <?php echo in_array($_k, $selected_cols)?'checked':''; ?>/> <?php echo esc_html($_label); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Orden por defecto','futbolin'); ?></th>
                <td>
                    <select name="set[sort_field]">
                        <?php foreach ($all_cols as $_k => $_label) : ?>
                            <option value="<?php echo esc_attr($_k); ?>" <?php selected($sort_field, $_k); ?>><?php echo esc_html($_label.' ('.$_k.')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="set[sort_dir]">
                        <option value="asc"  <?php selected($sort_dir,'asc');  ?>><?php echo esc_html__('Asc','futbolin'); ?></option>
                        <option value="desc" <?php selected($sort_dir,'desc'); ?>><?php echo esc_html__('Desc','futbolin'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Tamaño de página (Top N)','futbolin'); ?></th>
                <td><input type="number" min="1" name="set[top_n]" value="<?php echo esc_attr($top_n); ?>"/></td>
            </tr>
      <tr>
        <th><?php echo esc_html__('Plantilla front','futbolin'); ?></th>
        <td>
          <fieldset>
            <legend class="screen-reader-text"><?php echo esc_html__('Plantilla front','futbolin'); ?></legend>
            <label style="margin-right:12px;">
              <input type="radio" name="set[front_layout]" value="with" <?php checked(!$front_hide_sb); ?>/> <?php echo esc_html__('Con wrapper y sidebar','futbolin'); ?>
            </label>
            <label>
              <input type="radio" name="set[front_layout]" value="without" <?php checked($front_hide_sb); ?>/> <?php echo esc_html__('Sin sidebar (full-width)','futbolin'); ?>
            </label>
            <p class="description" style="margin-top:6px;"><?php echo esc_html__('Controla si el listado se integra en el wrapper de ranking-futbolin con menú lateral, o se muestra a ancho completo.','futbolin'); ?></p>
          </fieldset>
        </td>
      </tr>
        </tbody></table>

        <p class="submit">
      <button type="submit" class="button button-primary" data-action="futb_rankgen_save"><?php echo esc_html__('Guardar configuración','futbolin'); ?></button>
      <?php if ($rg_slug) : ?>
  <button type="submit" class="button button-link-delete futb-danger" formaction="<?php echo $admin_post; ?>" name="action" value="futb_rankgen_delete" data-action="futb_rankgen_delete" onclick="return confirm('<?php echo esc_js(__('¿Seguro que quieres borrar este ranking y su caché?','futbolin')); ?>');"><?php echo esc_html__('Borrar','futbolin'); ?></button>
      <?php endif; ?>
        </p>

        <?php if ($rg_slug) : ?>
      <p><code>[futb_rankgen slug="<?php echo esc_attr($rg_slug); ?>"]</code>
      &nbsp; <?php echo esc_html__('o utiliza','futbolin'); ?> <code>?view=rankgen&slug=<?php echo esc_html($rg_slug); ?></code></p>
      <p class="description"><?php echo esc_html__('El shortcode y la ruta utilizan el wrapper del plugin y respetan la opción de sidebar elegida.','futbolin'); ?></p>
        <?php else : ?>
            <p><em><?php echo esc_html__('Introduce un nombre/slug y pulsa Guardar para ver la ayuda de uso.','futbolin'); ?></em></p>
        <?php endif; ?>
    </form>
</div>

<div class="futbolin-card">
  <h3><?php esc_html_e('Selección por API','futbolin'); ?></h3>
  <table class="form-table"><tbody>
    <tr>
      <th scope="row"><?php esc_html_e('Temporada','futbolin'); ?></th>
      <td>
        <select id="rg-season" name="__tmp_rg_season" style="min-width:220px;">
          <option value=""><?php esc_html_e('Todas','futbolin'); ?></option>
        </select>
  <p class="description"><?php esc_html_e('Carga desde /api/Temporada/GetTemporadas. Si no existe, se derivan de /api/Torneo/GetTorneosPag y, como última opción, de un mapa local.','futbolin'); ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e('Tipos de campeonato/competición','futbolin'); ?></th>
      <td>
        <?php $_tipos_val = esc_attr(implode(', ', $tipos_comp)); ?>
        <input type="hidden" name="set[tipos_comp_raw]" id="rg-tipos-comp-raw" value="<?php echo $_tipos_val; ?>"/>
        <input type="search" id="rg-types-search" placeholder="<?php echo esc_attr__('Buscar tipos…','futbolin'); ?>" style="margin-bottom:6px;display:block;max-width:420px;"/>
        <select id="rg-types" multiple size="6" style="min-width:420px;"></select>
        <div class="description" id="rg-types-status" style="margin-top:4px;"></div>
        <p class="description"><?php esc_html_e('Sugerencias basadas en datos reales de las competiciones de los torneos seleccionados; si no hay datos, se muestran tipos comunes.','futbolin'); ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e('Torneos','futbolin'); ?></th>
      <td>
        <select id="rg-tournaments" multiple size="6" style="min-width:420px;"></select>
        <p class="description"><?php esc_html_e('Selecciona uno o varios torneos','futbolin'); ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php esc_html_e('Competiciones','futbolin'); ?></th>
      <td>
        <select id="rg-competitions" multiple size="6" style="min-width:420px;"></select>
        <p class="description"><?php esc_html_e('Depende de los torneos seleccionados','futbolin'); ?></p>
      </td>
    </tr>
  </tbody></table>
</div>
<script type="text/javascript">
(function(){
  const nonce = '<?php echo wp_create_nonce('futb_rankgen_nonce'); ?>';
  const initialData = {
    temporadaId: <?php echo json_encode((string)$temporadaId); ?>,
    torneoIds: <?php echo json_encode(array_values(array_map('strval', $torneo_ids_saved ?: $torneos_sel))); ?>,
    competicionIds: <?php echo json_encode(array_values(array_map('strval', $comp_ids_saved))); ?>
  };
  const seasonSel = document.getElementById('rg-season');
  const tournSel  = document.getElementById('rg-tournaments');
  const compSel   = document.getElementById('rg-competitions');
  const tiposRaw  = document.getElementById('rg-tipos-comp-raw');
  const typesSel  = document.getElementById('rg-types');
  const typesSearch = document.getElementById('rg-types-search');
  const typesStatus = document.getElementById('rg-types-status');
  const seasonStatus = document.createElement('div');
  seasonStatus.className='description'; seasonStatus.style.marginTop='4px';
  // Resumen
  const summaryCard = document.createElement('div'); summaryCard.className='futbolin-card'; summaryCard.style.marginTop='12px';
  summaryCard.innerHTML = '<h3><?php echo esc_js(__('Resumen de configuración','futbolin')); ?></h3>'
    + '<div id="rg-summary"></div>'
    + '<div id="rg-summary-actions" style="margin-top:8px;">'
    + '  <button type="button" class="button" id="rg-copy-json"><?php echo esc_js(__('Copiar configuración (JSON)','futbolin')); ?></button>'
    + '  <button type="button" class="button" id="rg-download-json" style="margin-left:6px;">\u2B73 <?php echo esc_js(__('Descargar JSON','futbolin')); ?></button>'
    + '</div>';

  // Controles de búsqueda/paginación y UI auxiliar
  const tournSearch = document.createElement('input');
  tournSearch.type='search'; tournSearch.placeholder='<?php echo esc_js(__('Buscar torneos…','futbolin')); ?>';
  tournSearch.style.margin='6px 0';
  const tournMore = document.createElement('button');
  tournMore.type='button'; tournMore.className='button'; tournMore.textContent='<?php echo esc_js(__('Cargar más','futbolin')); ?>';
  const tournSpinner = document.createElement('span'); tournSpinner.className='spinner'; tournSpinner.style.marginLeft='6px';
  const tournSelectAll = document.createElement('button');
  tournSelectAll.type='button'; tournSelectAll.className='button';
  tournSelectAll.textContent='<?php echo esc_js(__('Seleccionar visibles','futbolin')); ?>';
  const tournSelectAllPages = document.createElement('button');
  tournSelectAllPages.type='button'; tournSelectAllPages.className='button';
  tournSelectAllPages.textContent='<?php echo esc_js(__('Seleccionar todas las páginas','futbolin')); ?>';
  const tournStatus = document.createElement('div');
  tournStatus.className='description'; tournStatus.style.marginTop='4px';

  const compSearch = document.createElement('input');
  compSearch.type='search'; compSearch.placeholder='<?php echo esc_js(__('Buscar competiciones…','futbolin')); ?>';
  compSearch.style.margin='6px 0';
  const compMore = document.createElement('button');
  compMore.type='button'; compMore.className='button'; compMore.textContent='<?php echo esc_js(__('Cargar más','futbolin')); ?>';
  const compSpinner = document.createElement('span'); compSpinner.className='spinner'; compSpinner.style.marginLeft='6px';
  const compSelectAll = document.createElement('button');
  compSelectAll.type='button'; compSelectAll.className='button';
  compSelectAll.textContent='<?php echo esc_js(__('Seleccionar visibles','futbolin')); ?>';
  const compSelectAllPages = document.createElement('button');
  compSelectAllPages.type='button'; compSelectAllPages.className='button';
  compSelectAllPages.textContent='<?php echo esc_js(__('Seleccionar todas las páginas','futbolin')); ?>';
  const compStatus = document.createElement('div');
  compStatus.className='description'; compStatus.style.marginTop='4px';

  let tournPage=1, tournHasMore=false, tournQuery='';
  let compPage=1, compHasMore=false, compQuery='';

  // Estado de selección persistente + etiquetas
  const selectedTourn = new Set();
  const selectedComp = new Set();
  const labelTourn = new Map();
  const labelComp = new Map();

  // Utils
  function fetchJSON(params){
    return fetch(ajaxurl + '?' + new URLSearchParams(params).toString(), {credentials:'same-origin'})
      .then(r=>r.json()).then(j=>{ if(!j || !j.success) throw new Error((j&&j.data)||'Error'); return j.data; });
  }
  function fillSelect(sel, items, keep=false){
    if(!keep) sel.innerHTML='';
    if (!items || !items.length){
      const opt=document.createElement('option');
      opt.disabled=true; opt.textContent='<?php echo esc_js(__('(sin resultados)','futbolin')); ?>';
      sel.appendChild(opt);
      return;
    }
    items.forEach(it=>{
      const opt=document.createElement('option'); opt.value=it.id; opt.textContent=it.text; sel.appendChild(opt);
    });
  }
  function syncTiposRawFromSelect(){
    if(!tiposRaw || !typesSel) return;
    const current = tiposRaw.value ? tiposRaw.value.split(',').map(s=>s.trim()).filter(Boolean) : [];
    const selected = Array.from(typesSel.selectedOptions).map(o=>o.textContent.trim()).filter(Boolean);
    // Unir únicos
    const merged = Array.from(new Set(current.concat(selected)));
    tiposRaw.value = merged.join(', ');
  }
  function getSelectedValues(sel){ return Array.from(sel.selectedOptions).map(o=>o.value); }
  function debounce(fn, wait){ let t; return function(){ const args=arguments; const ctx=this; clearTimeout(t); t=setTimeout(()=>fn.apply(ctx,args), wait); }; }
  function setLoading(spinnerEl, buttonEl, isLoading, hasMore){ if(spinnerEl){ spinnerEl.classList[isLoading?'add':'remove']('is-active'); } if(buttonEl){ buttonEl.disabled = !!isLoading || (hasMore===false); } }
  function ensureOption(sel, id, text){ if(!id) return; const exists = Array.from(sel.options).some(o=>o.value===id); if(!exists){ const opt=document.createElement('option'); opt.value=id; opt.textContent=text || (id+''); opt.selected=true; opt.dataset.manual='1'; sel.appendChild(opt); } }
  function updateSelectedFromSelect(sel, set, labels){ Array.from(sel.options).forEach(o=>{ if(o.selected){ set.add(o.value); if(o.textContent) labels.set(o.value, o.textContent); } else { if(set.has(o.value)) set.delete(o.value); } }); }
  function applySelectedHiddenOptions(sel, set, labels){ set.forEach(id=>{ const txt = labels.get(id) || (id+''); ensureOption(sel, id, txt); const opt = Array.from(sel.options).find(o=>o.value===id); if(opt) opt.selected = true; }); }
  function renderChips(container, set, labels, onRemove){ if(!container) return; container.innerHTML=''; if(set.size===0){ container.style.display='none'; return; } container.style.display='block'; set.forEach(id=>{ const chip=document.createElement('span'); chip.style.display='inline-flex'; chip.style.alignItems='center'; chip.style.margin='4px 6px 0 0'; chip.style.padding='2px 8px'; chip.style.background='#f0f6ff'; chip.style.border='1px solid #cfe0ff'; chip.style.borderRadius='12px'; chip.style.fontSize='12px'; const label=document.createElement('span'); label.textContent=labels.get(id)||id; const close=document.createElement('button'); close.type='button'; close.textContent='×'; close.setAttribute('aria-label','<?php echo esc_js(__('Quitar','futbolin')); ?>'); close.style.marginLeft='6px'; close.style.border='none'; close.style.background='transparent'; close.style.cursor='pointer'; close.addEventListener('click', ()=> onRemove(id)); chip.appendChild(label); chip.appendChild(close); container.appendChild(chip); }); }

  // Colocar controles en el DOM
  if (seasonSel && seasonSel.parentNode){
    seasonSel.parentNode.appendChild(seasonStatus);
  }
  if (tournSel && tournSel.parentNode){
    tournSel.parentNode.insertBefore(tournSearch, tournSel);
    tournSel.parentNode.appendChild(tournMore);
    tournSel.parentNode.appendChild(tournSelectAll);
    tournSel.parentNode.appendChild(tournSelectAllPages);
    tournSel.parentNode.appendChild(tournSpinner);
    tournSel.parentNode.appendChild(tournStatus);
    const chips=document.createElement('div'); chips.id='rg-tournaments-chips'; chips.style.marginTop='4px';
    tournSel.parentNode.appendChild(chips);
  }
  if (compSel && compSel.parentNode){
    compSel.parentNode.insertBefore(compSearch, compSel);
    compSel.parentNode.appendChild(compMore);
    compSel.parentNode.appendChild(compSelectAll);
    compSel.parentNode.appendChild(compSelectAllPages);
    compSel.parentNode.appendChild(compSpinner);
    compSel.parentNode.appendChild(compStatus);
    const chips=document.createElement('div'); chips.id='rg-competitions-chips'; chips.style.marginTop='4px';
    compSel.parentNode.appendChild(chips);
  }
  // Insertar resumen antes del bloque de sondeo inferior
  const bottomBuildCard = document.querySelector('#futb-rankgen-progress-wrap');
  if (bottomBuildCard && bottomBuildCard.parentNode){ bottomBuildCard.parentNode.insertBefore(summaryCard, bottomBuildCard); }
  const tournChips = document.getElementById('rg-tournaments-chips');
  const compChips  = document.getElementById('rg-competitions-chips');

  // Carga de temporadas + bootstrap inicial
  document.addEventListener('DOMContentLoaded', function(){
    seasonStatus.textContent = '<?php echo esc_js(__('Cargando temporadas…','futbolin')); ?>';
    fetchJSON({action:'futb_rankgen_catalog',kind:'seasons',nonce:nonce}).then(d=>{
      fillSelect(seasonSel, d.items||[], true);
      seasonStatus.textContent = (d.items && d.items.length) ? '' : '<?php echo esc_js(__('No se encontraron temporadas (revisa la conexión de API)','futbolin')); ?>';
      // Seleccionar temporada inicial si la hay
      if (initialData.temporadaId && seasonSel){
        const opt = Array.from(seasonSel.options).find(o=>o.value==initialData.temporadaId);
        if (opt) seasonSel.value = initialData.temporadaId;
      }
      // Precargar selección persistente desde el set guardado
      (initialData.torneoIds||[]).forEach(id=>{ selectedTourn.add(String(id)); });
      (initialData.competicionIds||[]).forEach(id=>{ selectedComp.add(String(id)); });
  // Cargar torneos (primera página) y luego competiciones según selección
  loadTournaments(true).then(()=> loadCompetitions(true)).then(()=>{ updateSummary(); });
      // Cargar catálogo de tipos sugeridos (basados en datos reales si hay torneos seleccionados)
      if (typesSel) {
        const loadTypes = (q='')=>{
          const usp = new URLSearchParams({action:'futb_rankgen_catalog',kind:'types',nonce:nonce});
          if (q) usp.set('q', q);
          // Pasar torneos seleccionados para derivar tipos desde datos reales
          Array.from(selectedTourn).forEach(id=> usp.append('torneoIds[]', id));
          return fetch(ajaxurl+'?'+usp.toString(), {credentials:'same-origin'})
            .then(r=>r.json()).then(j=>{ if(!j||!j.success) throw new Error((j&&j.data)||'Error'); return j.data; })
            .then(d=>{ const items = d.items||[]; fillSelect(typesSel, items, false); typesStatus.textContent = items.length ? '' : '<?php echo esc_js(__('No se encontraron tipos','futbolin')); ?>';
              // Preseleccionar según lo guardado en tiposRaw
              const saved = (tiposRaw && tiposRaw.value) ? tiposRaw.value.split(',').map(s=>s.trim().toLowerCase()).filter(Boolean) : [];
              if (saved.length && typesSel){ Array.from(typesSel.options).forEach(o=>{ if (saved.includes(o.textContent.trim().toLowerCase())) o.selected = true; }); }
              // Sincronizar oculto y refrescar resumen
              syncTiposRawFromSelect(); updateSummary();
            })
            .catch(err=>{ console.error(err); typesStatus.textContent = '<?php echo esc_js(__('Error al cargar tipos','futbolin')); ?>'; });
        };
        loadTypes('');
        if (typesSearch) { typesSearch.addEventListener('input', debounce(function(){ loadTypes(this.value||''); }, 250)); }
        typesSel.addEventListener('change', function(){ syncTiposRawFromSelect(); });
      }
    }).catch(err=>{ console.error(err); seasonStatus.textContent = '<?php echo esc_js(__('Error al cargar temporadas (configura Conexión y vuelve a intentar)','futbolin')); ?>'; });
  });

  function loadTournaments(reset){
    const temporadaId = seasonSel ? (seasonSel.value||'') : '';
    if(reset){ tournPage=1; tournSel.innerHTML=''; }
    const params = {action:'futb_rankgen_catalog',kind:'tournaments',nonce:nonce,page:String(tournPage),pageSize:'100'};
    if (tournQuery) params.q = tournQuery;
    if (temporadaId) params.temporadaId = temporadaId;
    setLoading(tournSpinner, tournMore, true, false);
    return fetchJSON(params).then(d=>{
      const items = d.items||[];
      fillSelect(tournSel, items, !reset);
      tournHasMore = !!d.hasMore;
      tournMore.disabled = !tournHasMore;
  tournStatus.textContent = items.length ? ('<?php echo esc_js(__('Mostrando','futbolin')); ?> '+ (tournSel.options.length) + (tournHasMore ? '+' : '') ) : '<?php echo esc_js(__('Sin resultados','futbolin')); ?>';
  applySelectedHiddenOptions(tournSel, selectedTourn, labelTourn);
  // Poblar etiquetas para seleccionados (si vienen de persistencia)
  Array.from(tournSel.selectedOptions||[]).forEach(o=>{ if(o && o.value){ labelTourn.set(o.value, o.textContent||o.value); } });
      renderChips(tournChips, selectedTourn, labelTourn, (id)=>{
        selectedTourn.delete(id); labelTourn.delete(id);
        const opt = Array.from(tournSel.options).find(o=>o.value===id);
        if(opt){ if(opt.dataset.manual==='1'){ opt.remove(); } else { opt.selected=false; } }
        renderChips(tournChips, selectedTourn, labelTourn, ()=>{});
        // al cambiar selección, recargar competiciones
        loadCompetitions(true);
        // y refrescar tipos sugeridos con base real
        if (typesSel) { typesSel.innerHTML=''; typesStatus.textContent=''; }
        if (typesSearch) { typesSearch.value=''; }
        // Pequeño delay para asegurar que selectedTourn se ha aplicado
        setTimeout(()=>{ if (typesSel) { const q=(typesSearch&&typesSearch.value)||''; const usp = new URLSearchParams({action:'futb_rankgen_catalog',kind:'types',nonce:nonce}); Array.from(selectedTourn).forEach(id=> usp.append('torneoIds[]', id)); if(q) usp.set('q', q); fetch(ajaxurl+'?'+usp.toString(), {credentials:'same-origin'}).then(r=>r.json()).then(j=>{ if(j&&j.success){ fillSelect(typesSel, (j.data&&j.data.items)||[], false); typesStatus.textContent=''; } }); } }, 150);
  });
  updateSummary();
    }).catch(console.error).finally(()=> setLoading(tournSpinner, tournMore, false, tournHasMore));
  }

  function loadCompetitions(reset){
    const torneoIds = Array.from(selectedTourn);
    const temporadaId = seasonSel ? (seasonSel.value||'') : '';
    if(!torneoIds.length){ compSel.innerHTML=''; compHasMore=false; compMore.disabled=true; renderChips(compChips, selectedComp, labelComp, ()=>{}); return Promise.resolve(); }
    if(reset){ compPage=1; compSel.innerHTML=''; }
  const usp = new URLSearchParams({action:'futb_rankgen_catalog',kind:'competitions',nonce:nonce,page:String(compPage),pageSize:'200'});
    if (compQuery) usp.set('q', compQuery);
    if (temporadaId) usp.set('temporadaId', temporadaId);
  // Pasar tipos seleccionados (desde el input oculto)
  if (tiposRaw && tiposRaw.value){ tiposRaw.value.split(',').map(s=>s.trim()).filter(Boolean).forEach(tp=> usp.append('tipos[]', tp.toLowerCase())); }
    torneoIds.forEach(id=>usp.append('torneoIds[]', id));
    setLoading(compSpinner, compMore, true, false);
    return fetch(ajaxurl+'?'+usp.toString(), {credentials:'same-origin'})
      .then(r=>r.json()).then(j=>{
        if(!j || !j.success) throw new Error((j&&j.data)||'Error');
        const data = j.data || {};
        const items = data.items||[];
        fillSelect(compSel, items, !reset);
        compHasMore = !!data.hasMore;
        compMore.disabled = !compHasMore;
  compStatus.textContent = items.length ? ('<?php echo esc_js(__('Mostrando','futbolin')); ?> '+ (compSel.options.length) + (compHasMore ? '+' : '') ) : '<?php echo esc_js(__('Sin resultados','futbolin')); ?>';
  applySelectedHiddenOptions(compSel, selectedComp, labelComp);
  // Poblar etiquetas para seleccionados (si vienen de persistencia)
  Array.from(compSel.selectedOptions||[]).forEach(o=>{ if(o && o.value){ labelComp.set(o.value, o.textContent||o.value); } });
        renderChips(compChips, selectedComp, labelComp, (id)=>{
          selectedComp.delete(id); labelComp.delete(id);
          const opt = Array.from(compSel.options).find(o=>o.value===id);
          if(opt){ if(opt.dataset.manual==='1'){ opt.remove(); } else { opt.selected=false; } }
          renderChips(compChips, selectedComp, labelComp, ()=>{});
        });
        updateSummary();
      }).catch(console.error).finally(()=> setLoading(compSpinner, compMore, false, compHasMore));
  }

  // Eventos
  if (seasonSel){
    seasonSel.addEventListener('change', function(){
      // reset estado
      selectedTourn.clear(); labelTourn.clear(); selectedComp.clear(); labelComp.clear();
      tournQuery=''; compQuery='';
      tournSearch.value=''; compSearch.value='';
      loadTournaments(true).then(()=> loadCompetitions(true));
    });
  }

  if (tournSel){
    tournSel.addEventListener('change', function(){
      updateSelectedFromSelect(tournSel, selectedTourn, labelTourn);
      renderChips(tournChips, selectedTourn, labelTourn, (id)=>{
        selectedTourn.delete(id); labelTourn.delete(id);
        const opt = Array.from(tournSel.options).find(o=>o.value===id);
        if(opt){ if(opt.dataset.manual==='1'){ opt.remove(); } else { opt.selected=false; } }
        renderChips(tournChips, selectedTourn, labelTourn, ()=>{});
        loadCompetitions(true);
        // refrescar tipos sugeridos
        if (typesSel) { const q=(typesSearch&&typesSearch.value)||''; const usp = new URLSearchParams({action:'futb_rankgen_catalog',kind:'types',nonce:nonce}); Array.from(selectedTourn).forEach(id=> usp.append('torneoIds[]', id)); if(q) usp.set('q', q); fetch(ajaxurl+'?'+usp.toString(), {credentials:'same-origin'}).then(r=>r.json()).then(j=>{ if(j&&j.success){ typesSel.innerHTML=''; fillSelect(typesSel, (j.data&&j.data.items)||[], false); typesStatus.textContent=''; } }); }
      });
      loadCompetitions(true);
      // refrescar tipos sugeridos
      if (typesSel) { const q=(typesSearch&&typesSearch.value)||''; const usp = new URLSearchParams({action:'futb_rankgen_catalog',kind:'types',nonce:nonce}); Array.from(selectedTourn).forEach(id=> usp.append('torneoIds[]', id)); if(q) usp.set('q', q); fetch(ajaxurl+'?'+usp.toString(), {credentials:'same-origin'}).then(r=>r.json()).then(j=>{ if(j&&j.success){ typesSel.innerHTML=''; fillSelect(typesSel, (j.data&&j.data.items)||[], false); typesStatus.textContent=''; } }); }
    });
  }

  if (tournSearch){
    tournSearch.addEventListener('input', debounce(function(){
      tournQuery = this.value || '';
      loadTournaments(true);
    }, 300));
  }
  if (tournMore){
    tournMore.addEventListener('click', function(){ if(!tournHasMore) return; tournPage++; loadTournaments(false); });
  }
  if (tournSelectAll){
    tournSelectAll.addEventListener('click', function(){
      Array.from(tournSel.options).forEach(o=>{ if(!o.disabled){ o.selected=true; selectedTourn.add(o.value); if(o.textContent) labelTourn.set(o.value,o.textContent); } });
      renderChips(tournChips, selectedTourn, labelTourn, ()=>{});
      loadCompetitions(true);
    });
  }
  if (tournSelectAllPages){
    tournSelectAllPages.addEventListener('click', function(){
      // Marca intención de seleccionar todos: usaremos un flag al guardar
      selectedTourn.clear(); labelTourn.clear();
      const allFlag = document.createElement('input'); allFlag.type='hidden'; allFlag.name='set[torneos_all]'; allFlag.value='1';
      // Añadir al formulario de guardado si existe
      const form = document.querySelector('.futbolin-card form[action$="admin-post.php"]'); if(form){ form.appendChild(allFlag); }
      renderChips(tournChips, selectedTourn, labelTourn, ()=>{});
      tournStatus.textContent = '<?php echo esc_js(__('(Se guardará como TODOS los torneos)','futbolin')); ?>';
      compStatus.textContent  = '<?php echo esc_js(__('(Las competiciones se determinan al generar)','futbolin')); ?>';
    });
  }

  if (compSearch){
    compSearch.addEventListener('input', debounce(function(){
      compQuery = this.value || '';
      loadCompetitions(true);
    }, 300));
  }
  if (compMore){
    compMore.addEventListener('click', function(){ if(!compHasMore) return; compPage++; loadCompetitions(false); });
  }
  if (compSelectAll){
    compSelectAll.addEventListener('click', function(){
      Array.from(compSel.options).forEach(o=>{ if(!o.disabled){ o.selected=true; selectedComp.add(o.value); if(o.textContent) labelComp.set(o.value,o.textContent); } });
      renderChips(compChips, selectedComp, labelComp, ()=>{});
    });
  }
  if (compSelectAllPages){
    compSelectAllPages.addEventListener('click', function(){
      // Flag para indicar selección total de competiciones de torneos escogidos
      const allFlag = document.createElement('input'); allFlag.type='hidden'; allFlag.name='set[competitions_all]'; allFlag.value='1';
      const form = document.querySelector('.futbolin-card form[action$="admin-post.php"]'); if(form){ form.appendChild(allFlag); }
      selectedComp.clear(); labelComp.clear(); renderChips(compChips, selectedComp, labelComp, ()=>{});
      compStatus.textContent = '<?php echo esc_js(__('(Se guardarán todas las competiciones de los torneos seleccionados)','futbolin')); ?>';
    });
  }

  // Resumen dinámico
  function updateSummary(){
    const el = document.getElementById('rg-summary'); if(!el) return;
    const scope = (document.querySelector('input[name="set[scope]"]:checked')||{}).value || 'ESP';
    const mods = Array.from(document.querySelectorAll('input[name="set[modalidades][]"]:checked')).map(i=>i.value).join(', ') || '-';
    const temporada = seasonSel ? (seasonSel.value || '<?php echo esc_js(__('Todas','futbolin')); ?>') : '<?php echo esc_js(__('Todas','futbolin')); ?>';
    const tipos = (tiposRaw && tiposRaw.value) ? tiposRaw.value : '';
    // Preferimos usar los conjuntos persistentes + etiquetas para incluir IDs en el resumen
    const tIds = Array.from(selectedTourn);
    const cIds = Array.from(selectedComp);
    // Si están vacíos, usamos la selección directa del select
    if (!tIds.length && tournSel) Array.from(tournSel.selectedOptions||[]).forEach(o=>{ if(o&&o.value){ tIds.push(o.value); labelTourn.set(o.value, o.textContent||o.value); } });
    if (!cIds.length && compSel) Array.from(compSel.selectedOptions||[]).forEach(o=>{ if(o&&o.value){ cIds.push(o.value); labelComp.set(o.value, o.textContent||o.value); } });
    const tPairs = tIds.map(id=>{ const name = labelTourn.get(id)||id; return name + ' ('+id+')'; });
    const cPairs = cIds.map(id=>{ const name = labelComp.get(id)||id; return name + ' ('+id+')'; });
    const maxNames = 4;
    const tList = tPairs.length ? (tPairs.slice(0,maxNames).join(' · ') + (tPairs.length>maxNames ? ' · +' + (tPairs.length-maxNames) : '')) : '-';
    const cList = cPairs.length ? (cPairs.slice(0,maxNames).join(' · ') + (cPairs.length>maxNames ? ' · +' + (cPairs.length-maxNames) : '')) : '-';
    const minsP = (document.querySelector('input[name="set[min_partidos]"]')||{}).value || '0';
    const minsC = (document.querySelector('input[name="set[min_competiciones]"]')||{}).value || '0';
    const hofV  = (document.querySelector('input[name="set[min_victorias]"]')||{}).value || '0';
    const reqCh = (document.querySelector('input[name="set[require_campeonato]"]')||{}).checked ? '<?php echo esc_js(__('Sí','futbolin')); ?>' : '<?php echo esc_js(__('No','futbolin')); ?>';
    const topN  = (document.querySelector('input[name="set[top_n]"]')||{}).value || '25';
    const sortF = (document.querySelector('select[name="set[sort_field]"]')||{}).value || '';
    const sortD = (document.querySelector('select[name="set[sort_dir]"]')||{}).value || '';
    const colBoxes = Array.from(document.querySelectorAll('input[name="set[columns][]"]:checked'));
    const colNames = colBoxes.map(i=>{ const lbl=i.parentElement && i.parentElement.textContent ? i.parentElement.textContent.trim() : i.value; return lbl; });
    const colList  = colNames.length ? colNames.join(' · ') : '-';
    el.innerHTML = ''
      + '<ul style="margin:0; padding-left:18px;">'
      + '<li><strong>Ámbito:</strong> '+scope+'</li>'
      + '<li><strong>Modalidades:</strong> '+mods+'</li>'
      + '<li><strong>Temporada:</strong> '+temporada+'</li>'
      + '<li><strong>Tipos:</strong> '+(tipos||'-')+'</li>'
      + '<li><strong>Torneos:</strong> '+tList+'</li>'
      + '<li><strong>Competiciones:</strong> '+cList+'</li>'
      + '<li><strong>Mínimos:</strong> Partidos '+minsP+', Competiciones '+minsC+'</li>'
      + '<li><strong>HOF:</strong> Victorias '+hofV+', Requiere campeonato: '+reqCh+'</li>'
      + '<li><strong>Top N:</strong> '+topN+' · <strong>Orden:</strong> '+sortF+' '+(sortD||'')+'</li>'
      + '<li><strong>Columnas:</strong> '+colList+'</li>'
      + '</ul>';
  }

  // Construcción de objeto de configuración para exportar
  function buildConfigObject(){
    const slugInput = document.querySelector('input[name="set[slug]"]');
    const slug = slugInput ? slugInput.value : '';
    const scope = (document.querySelector('input[name="set[scope]"]:checked')||{}).value || 'ESP';
    const modalidades = Array.from(document.querySelectorAll('input[name="set[modalidades][]"]:checked')).map(i=>i.value);
    const temporadaId = seasonSel ? (seasonSel.value||'') : '';
    const tipos = (tiposRaw && tiposRaw.value) ? tiposRaw.value.split(',').map(s=>s.trim()).filter(Boolean) : [];
    const tIds = Array.from(selectedTourn); if (!tIds.length && tournSel) Array.from(tournSel.selectedOptions||[]).forEach(o=> tIds.push(o.value));
    const cIds = Array.from(selectedComp); if (!cIds.length && compSel) Array.from(compSel.selectedOptions||[]).forEach(o=> cIds.push(o.value));
    const isEnabled = !!(document.querySelector('input[name="set[is_enabled]"]')||{}).checked;
    const includeL = !!(document.querySelector('input[name="set[include_liguilla]"]')||{}).checked;
    const includeC = !!(document.querySelector('input[name="set[include_cruces]"]')||{}).checked;
    const minP = parseInt((document.querySelector('input[name="set[min_partidos]"]')||{}).value||'0',10);
    const minC = parseInt((document.querySelector('input[name="set[min_competiciones]"]')||{}).value||'0',10);
    const minV = parseInt((document.querySelector('input[name="set[min_victorias]"]')||{}).value||'0',10);
    const reqCh = !!(document.querySelector('input[name="set[require_campeonato]"]')||{}).checked;
    const topN = parseInt((document.querySelector('input[name="set[top_n]"]')||{}).value||'25',10);
    const sortF = (document.querySelector('select[name="set[sort_field]"]')||{}).value||'';
    const sortD = (document.querySelector('select[name="set[sort_dir]"]')||{}).value||'';
    const cols = Array.from(document.querySelectorAll('input[name="set[columns][]"]:checked')).map(i=>i.value);
    const fl = (document.querySelector('input[name="set[front_layout]"]:checked')||{}).value || 'with';
    const publicUrl = (window.location.origin || '') + '/futbolin-ranking/?view=rankgen&slug=' + encodeURIComponent(slug);
    const obj = { slug, is_enabled:isEnabled, scope, modalidades, temporadaId, tipos_comp:tipos, torneoIds:tIds, competicionIds:cIds,
      include_liguilla:includeL, include_cruces:includeC, min_partidos:minP, min_competiciones:minC, min_victorias:minV, require_campeonato:reqCh,
      top_n:topN, sort_field:sortF, sort_dir:sortD, columns:cols, front_layout:fl, publicUrl };
    return obj;
  }
  function copyJson(){ const obj=buildConfigObject(); const txt=JSON.stringify(obj,null,2); if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(txt).catch(()=>{}); }
    else { const ta=document.createElement('textarea'); ta.value=txt; document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(ta); }
  }
  function downloadJson(){ const obj=buildConfigObject(); const txt=JSON.stringify(obj,null,2); const blob=new Blob([txt],{type:'application/json'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); const slug=(obj.slug||'rankgen'); a.download=slug+'.json'; document.body.appendChild(a); a.click(); setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 500); }
  document.addEventListener('click', function(e){ const t=e.target; if(!t) return; if(t.id==='rg-copy-json'){ e.preventDefault(); copyJson(); }
    if(t.id==='rg-download-json'){ e.preventDefault(); downloadJson(); }
  });
  ['change','input'].forEach(ev=>{
    document.addEventListener(ev, function(e){ if (!e) return; const t=e.target; if(!t) return; if(t.matches('input[name^="set["]')) updateSummary(); });
  });
  if (seasonSel) seasonSel.addEventListener('change', updateSummary);
  if (typesSel) typesSel.addEventListener('change', function(){ syncTiposRawFromSelect(); updateSummary(); });
  if (tournSel) tournSel.addEventListener('change', updateSummary);
  if (compSel) compSel.addEventListener('change', updateSummary);

  // Antes de guardar, volcar selección en inputs ocultos
  const form = document.querySelector('.futbolin-card form[action$="admin-post.php"]') || document.querySelector('form[action="options.php"]');
  if (form){
    // Asegura post a admin-post.php con action dinámico según botón pulsado
    const actionInput = document.getElementById('futb-rankgen-action');
    form.setAttribute('action', '<?php echo $admin_post; ?>');
    form.querySelectorAll('button[type="submit"][data-action]').forEach(btn=>{
      btn.addEventListener('click', function(){ if(actionInput){ actionInput.value = this.getAttribute('data-action')||'futb_rankgen_save'; } });
    });
    form.addEventListener('submit', function(){
      const ensureHidden = (name, value) => { const input=document.createElement('input'); input.type='hidden'; input.name=name; input.value=value; form.appendChild(input); };
      const temporadaId = seasonSel ? (seasonSel.value||'') : '';
      if (temporadaId) ensureHidden('set[temporadaId]', temporadaId);
      const tIds = Array.from(selectedTourn);
      const cIds = Array.from(selectedComp);
      if (!tIds.length){ Array.from(tournSel.selectedOptions||[]).forEach(o=> tIds.push(o.value)); }
      if (!cIds.length){ Array.from(compSel.selectedOptions||[]).forEach(o=> cIds.push(o.value)); }
      tIds.forEach(id=> ensureHidden('set[torneoIds][]', id));
      cIds.forEach(id=> ensureHidden('set[competicionIds][]', id));
      // Asegurar volcamos tipos_comp_raw actualizado si el usuario usó el multiselect
      if (tiposRaw) { ensureHidden('set[tipos_comp_raw]', tiposRaw.value || ''); }
    });
  }
})();
</script>

<div class="futbolin-card" style="margin-top:12px;">
  <input type="hidden" name="futb_rankgen_current_slug" value="<?php echo esc_attr(isset($_GET['slug']) ? sanitize_title($_GET['slug']) : ''); ?>">
  <button type="button" class="button button-primary" id="futb-rankgen-build"><?php esc_html_e('Sondear (recalcular caché)','futbolin'); ?></button>
</div>
<div class="futbolin-card" id="futb-rankgen-progress-wrap" style="margin-top:16px;">
  <h3><?php esc_html_e('Progreso de generación','futbolin'); ?></h3>
  <div id="futb-rankgen-progress" style="position:relative;height:22px;background:#f1f1f1;border-radius:6px;overflow:hidden;">
    <div class="bar" style="height:100%;width:0;background:#2271b1;transition:width .25s;"></div>
    <span class="label" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-weight:600;">0%</span>
  </div>
  <p id="futb-rankgen-msg" class="description" style="margin-top:8px;"></p>
  <div id="futb-rankgen-result-info" class="description" style="margin-top:8px;"></div>
</div>
<script type="text/javascript">
(function(){
  const btn = document.getElementById('futb-rankgen-build');
  const wrap = document.getElementById('futb-rankgen-progress-wrap');
  if(!btn || !wrap) return;
  const bar  = wrap.querySelector('.bar');
  const lab  = wrap.querySelector('.label');
  const msg  = document.getElementById('futb-rankgen-msg');
  const info = document.getElementById('futb-rankgen-result-info');
  const slugInput = document.querySelector('[name="futb_rankgen_current_slug"]') || document.querySelector('[name="futb_rankgen_slug"]');

  function setPct(p){ if(bar&&lab){ bar.style.width=p+'%'; lab.textContent=p+'%'; } }
  function setState(disabled){ btn.disabled = !!disabled; btn.textContent = disabled ? '<?php echo esc_js(__('Generando…','futbolin')); ?>' : '<?php echo esc_js(__('Sondear (recalcular caché)','futbolin')); ?>'; }
  function cacheInfo(sl){
    const usp = new URLSearchParams({action:'futb_rankgen_cache_info', nonce:'<?php echo wp_create_nonce('futb_rankgen_nonce'); ?>', slug:sl});
    return fetch(ajaxurl+'?'+usp.toString(), {credentials:'same-origin'}).then(r=>r.json()).then(j=>{ if(!j||!j.success) throw new Error((j&&j.data)||'Error'); return j.data; });
  }
  function renderResultInfo(d){ if(!info) return; if(!d) { info.textContent=''; return; }
    const rows = d.rows||0; const ts = d.timestamp||''; const url = d.publicUrl||''; const oc = d.storage&&d.storage.option_cache; const ots = d.storage&&d.storage.option_cache_ts;
    info.innerHTML = ''
      + (d.exists ? '<?php echo esc_js(__('Resultado:','futbolin')); ?> ' + rows + ' <?php echo esc_js(__('filas','futbolin')); ?>' + (ts?' · '+ts:'') : '<?php echo esc_js(__('Sin caché previa','futbolin')); ?>')
      + (url ? ' · <a class="button" href="'+url+'" target="_blank">'+ '<?php echo esc_js(__('Ver listado','futbolin')); ?>' +'</a>' : '')
      + (oc ? '<br/><?php echo esc_js(__('Claves en BBDD:','futbolin')); ?> <code>'+oc+'</code>, <code>'+ots+'</code>' : '');
  }

  btn.addEventListener('click', function(ev){
    ev.preventDefault();
    const slug = slugInput ? slugInput.value : '';
    if(!slug){ alert('Slug vacío'); return; }
    const t0 = Date.now();
    // Comprobar si existe caché y advertir
    cacheInfo(slug).then(d=>{
      renderResultInfo(d);
      if (d && d.exists) {
        const ok = confirm('<?php echo esc_js(__('Ya existe una caché para este listado y se sobrescribirá. ¿Continuar?','futbolin')); ?>\n'+(d.rows?('('+d.rows+' <?php echo esc_js(__('filas','futbolin')); ?>)'):'') );
        if (!ok) return Promise.reject(new Error('cancelled'));
      }
      setState(true); setPct(0); if(msg) msg.textContent='';
      return true;
    }).then(()=>{
    const nonce = '<?php echo wp_create_nonce('futb_rankgen_nonce'); ?>';

    fetch(ajaxurl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, credentials:'same-origin',
      body:new URLSearchParams({action:'futb_rankgen_build_start', nonce:nonce, slug:slug})
    }).then(r=>r.json()).then(data=>{
      if(!data || !data.success){ throw new Error((data&&data.data)||'Error al iniciar'); }
      tick(slug);
    }).catch(e=>{ if(msg) msg.textContent='Error: '+e.message; setState(false); });
    }).catch(e=>{ if(e && e.message!== 'cancelled'){ if(msg) msg.textContent='Error: '+e.message; } });

    function tick(sl){
      fetch(ajaxurl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, credentials:'same-origin',
        body:new URLSearchParams({action:'futb_rankgen_build_step', nonce:'<?php echo wp_create_nonce('futb_rankgen_nonce'); ?>', slug:sl})
      }).then(r=>r.json()).then(data=>{
        if(!data || !data.success){ throw new Error((data&&data.data)||'Error de paso'); }
        const d = data.data;
        if(typeof d.percent !== 'undefined') setPct(d.percent);
        if(d.errors && d.errors.length){ msg.textContent = d.errors[d.errors.length-1]; }
        if(d.finished){ setState(false); const ms = Date.now() - t0; const secs = Math.max(1, Math.round(ms/1000)); msg.textContent = (msg.textContent? msg.textContent + ' · ' : '') + '<?php echo esc_js(__('Completado','futbolin')); ?>' + ' (<?php echo esc_js(__('Duración','futbolin')); ?>: '+secs+'s)';
          // Consultar y mostrar info de resultado + enlace
          cacheInfo(sl).then(renderResultInfo).catch(()=>{});
          return;
        }
        setTimeout(()=>tick(sl), 700);
      }).catch(e=>{ if(msg) msg.textContent='Error: '+e.message; setState(false); });
    }
  });
})();
</script>
