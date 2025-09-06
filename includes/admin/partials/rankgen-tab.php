<?php
if (!defined('ABSPATH')) exit;

$drafts = get_option('futb_rankgen_drafts', array());
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
$selected_cols = (array)$def('columns', array('pos','nombre','partidas','ganadas','win_rate_partidos','comp_jugadas','comp_ganadas','win_rate_comp','torneos'));
$tipos_comp    = (array)$def('tipos_comp', array());
$torneos_sel   = (array)$def('torneos', array());

$admin_post = esc_url( admin_url('admin-post.php') );
$rankgen_url = esc_url( admin_url('admin.php?page=futbolin-api-settings&tab=rankgen') );

?>
<div class="futbolin-card">
    <h2><?php echo esc_html__('Generador de rankings','futbolin'); ?></h2>

    <form method="get" action="">
        <input type="hidden" name="page" value="futbolin-api-settings"/>
        <input type="hidden" name="tab" value="rankgen"/>
        <label><?php echo esc_html__('Seleccionar ranking','futbolin'); ?>:
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
        <input type="hidden" name="action" value="futb_rankgen_save"/>

        <table class="form-table" role="presentation"><tbody>
            <tr>
                <th><?php echo esc_html__('Estado','futbolin'); ?></th>
                <td><label><input type="checkbox" name="set[is_enabled]" value="1" <?php echo $enabled?'checked':''; ?>/> <?php echo esc_html__('Activado','futbolin'); ?></label></td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Nombre del ranking','futbolin'); ?></th>
                <td><input type="text" name="set[name]" value="<?php echo esc_attr($rg_name); ?>" class="regular-text"/></td>
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
                <th><?php echo esc_html__('Temporada','futbolin'); ?></th>
                <td>
                    <input type="text" name="set[temporadaId]" value="<?php echo esc_attr($temporadaId); ?>" class="regular-text" placeholder="Ej: 2024 o ID exacto"/>
                    <br/><small><?php echo esc_html__('Próximo: selector dinámico por API.','futbolin'); ?></small>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Tipos de competición','futbolin'); ?></th>
                <td>
                    <?php $_tipos_val = esc_attr(implode(', ', $tipos_comp)); ?>
                    <input type="text" name="set[tipos_comp_raw]" value="<?php echo $_tipos_val; ?>" class="regular-text" placeholder="Ej: Open Dobles, Amateur Dobles"/>
                    <br/><small><?php echo esc_html__('Próximo: multiselección con catálogo vía API','futbolin'); ?></small>
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Torneos (IDs)','futbolin'); ?></th>
                <td>
                    <?php $_t_val = esc_textarea(implode(',', $torneos_sel)); ?>
                    <textarea name="set[torneos_raw]" rows="3" class="large-text" placeholder="Ej: 101,102,103"><?php echo $_t_val; ?></textarea>
                    <br/><small><?php echo esc_html__('Próximo: buscador multiselect de torneos','futbolin'); ?></small>
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
                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Columnas a mostrar','futbolin'); ?></th>
                <td>
                    <?php $all_cols = array(
                        'pos' => 'Posición',
                        'nombre' => 'Jugador',
                        'partidas' => 'Partidas',
                        'ganadas' => 'Ganadas',
                        'win_rate_partidos' => '% Ganados (partidos)',
                        'comp_jugadas' => 'Comp. jugadas',
                        'comp_ganadas' => 'Comp. ganadas',
                        'win_rate_comp' => '% Comp.',
                        'torneos' => 'Torneos',
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
        </tbody></table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo esc_html__('Guardar configuración','futbolin'); ?></button>
            <?php if ($rg_slug) : ?>
                <button type="submit" class="button" formaction="<?php echo $admin_post; ?>" name="action" value="futb_rankgen_build"><?php echo esc_html__('Generar y guardar caché','futbolin'); ?></button>
                <button type="submit" class="button" formaction="<?php echo $admin_post; ?>" name="action" value="futb_rankgen_toggle"><?php echo $enabled?esc_html__('Desactivar','futbolin'):esc_html__('Activar','futbolin'); ?></button>
                <button type="submit" class="button button-link-delete futb-danger" formaction="<?php echo $admin_post; ?>" name="action" value="futb_rankgen_delete" onclick="return confirm('<?php echo esc_js(__('¿Seguro que quieres borrar este ranking y su caché?','futbolin')); ?>');"><?php echo esc_html__('Borrar','futbolin'); ?></button>
            <?php endif; ?>
        </p>

        <?php if ($rg_slug) : ?>
            <p><code>[futb_rankgen slug="<?php echo esc_attr($rg_slug); ?>"]</code>
            &nbsp; <?php echo esc_html__('o utiliza','futbolin'); ?> <code>?view=rankgen&slug=<?php echo esc_html($rg_slug); ?></code></p>
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
        <p class="description"><?php esc_html_e('Carga desde /api/Torneo/GetTorneosPag','futbolin'); ?></p>
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
  const seasonSel = document.getElementById('rg-season');
  const tournSel  = document.getElementById('rg-tournaments');
  const compSel   = document.getElementById('rg-competitions');

  function fetchJSON(params){
    return fetch(ajaxurl + '?' + new URLSearchParams(params).toString(), {credentials:'same-origin'})
      .then(r=>r.json()).then(j=>{ if(!j || !j.success) throw new Error((j&&j.data)||'Error'); return j.data; });
  }
  function fillSelect(sel, items, keep=false){
    if(!keep) sel.innerHTML='';
    items.forEach(it=>{
      const opt=document.createElement('option'); opt.value=it.id; opt.textContent=it.text; sel.appendChild(opt);
    });
  }
  function getSelectedValues(sel){
    return Array.from(sel.selectedOptions).map(o=>o.value);
  }

  // Load seasons on ready
  document.addEventListener('DOMContentLoaded', function(){
    fetchJSON({action:'futb_rankgen_catalog',kind:'seasons',nonce:nonce}).then(d=>{
      fillSelect(seasonSel, d.items || [], true);
    }).catch(console.error);
  });

  // Load tournaments on season change
  seasonSel && seasonSel.addEventListener('change', function(){
    tournSel.innerHTML=''; compSel.innerHTML='';
    const temporadaId = this.value || '';
    const params = {action:'futb_rankgen_catalog',kind:'tournaments',nonce:nonce};
    if(temporadaId) params['temporadaId']=temporadaId;
    fetchJSON(params).then(d=>{
      fillSelect(tournSel, d.items || []);
    }).catch(console.error);
  });

  // Load competitions when tournaments change
  tournSel && tournSel.addEventListener('change', function(){
    compSel.innerHTML='';
    const torneoIds = getSelectedValues(tournSel);
    if(!torneoIds.length) return;
    const params = new URLSearchParams({action:'futb_rankgen_catalog',kind:'competitions',nonce:nonce});
    torneoIds.forEach(id=>params.append('torneoIds[]', id));
    fetch(ajaxurl+'?'+params.toString(), {credentials:'same-origin'})
      .then(r=>r.json()).then(j=>{
        if(!j || !j.success) throw new Error((j&&j.data)||'Error'); 
        fillSelect(compSel, (j.data && j.data.items) || []);
      }).catch(console.error);
  });

  // On save (submit), copy selections into hidden inputs aligned with server schema
  const form = document.querySelector('#tab-rankgen form') || document.querySelector('form[action="options.php"]');
  if(form){
    form.addEventListener('submit', function(){
      // create hidden fields for torneoIds[] and competicionIds[] and temporadaId
      const ensure = (name, value) => { const input = document.createElement('input'); input.type='hidden'; input.name=name; input.value=value; form.appendChild(input); };
      const temporadaId = seasonSel ? seasonSel.value : '';
      if(temporadaId) ensure('futb_rankgen_current[temporadaId]', temporadaId);
      Array.from(tournSel.selectedOptions).forEach(o=>ensure('futb_rankgen_current[torneoIds][]', o.value));
      Array.from(compSel.selectedOptions).forEach(o=>ensure('futb_rankgen_current[competicionIds][]', o.value));
    });
  }
})(); 
</script>

<div class="futbolin-card" style="margin-top:12px;">
  <input type="hidden" name="futb_rankgen_current_slug" value="<?php echo esc_attr(isset($_GET['slug']) ? sanitize_title($_GET['slug']) : ''); ?>">
  <button type="button" class="button button-primary" id="futb-rankgen-build"><?php esc_html_e('Generar y guardar caché','futbolin'); ?></button>
</div>
<div class="futbolin-card" id="futb-rankgen-progress-wrap" style="margin-top:16px;">
  <h3><?php esc_html_e('Progreso de generación','futbolin'); ?></h3>
  <div id="futb-rankgen-progress" style="position:relative;height:22px;background:#f1f1f1;border-radius:6px;overflow:hidden;">
    <div class="bar" style="height:100%;width:0;background:#2271b1;transition:width .25s;"></div>
    <span class="label" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-weight:600;">0%</span>
  </div>
  <p id="futb-rankgen-msg" class="description" style="margin-top:8px;"></p>
</div>
<script type="text/javascript">
(function(){
  const btn = document.getElementById('futb-rankgen-build');
  const wrap = document.getElementById('futb-rankgen-progress-wrap');
  if(!btn || !wrap) return;
  const bar  = wrap.querySelector('.bar');
  const lab  = wrap.querySelector('.label');
  const msg  = document.getElementById('futb-rankgen-msg');
  const slugInput = document.querySelector('[name="futb_rankgen_current_slug"]') || document.querySelector('[name="futb_rankgen_slug"]');

  function setPct(p){ if(bar&&lab){ bar.style.width=p+'%'; lab.textContent=p+'%'; } }
  function setState(disabled){ btn.disabled = !!disabled; btn.textContent = disabled ? '<?php echo esc_js(__('Generando…','futbolin')); ?>' : '<?php echo esc_js(__('Generar y guardar caché','futbolin')); ?>'; }

  btn.addEventListener('click', function(ev){
    ev.preventDefault();
    const slug = slugInput ? slugInput.value : '';
    if(!slug){ alert('Slug vacío'); return; }
    setState(true); setPct(0); if(msg) msg.textContent='';
    const nonce = '<?php echo wp_create_nonce('futb_rankgen_nonce'); ?>';

    fetch(ajaxurl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, credentials:'same-origin',
      body:new URLSearchParams({action:'futb_rankgen_build_start', nonce:nonce, slug:slug})
    }).then(r=>r.json()).then(data=>{
      if(!data || !data.success){ throw new Error((data&&data.data)||'Error al iniciar'); }
      tick(slug);
    }).catch(e=>{ if(msg) msg.textContent='Error: '+e.message; setState(false); });

    function tick(sl){
      fetch(ajaxurl, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, credentials:'same-origin',
        body:new URLSearchParams({action:'futb_rankgen_build_step', nonce:'<?php echo wp_create_nonce('futb_rankgen_nonce'); ?>', slug:sl})
      }).then(r=>r.json()).then(data=>{
        if(!data || !data.success){ throw new Error((data&&data.data)||'Error de paso'); }
        const d = data.data;
        if(typeof d.percent !== 'undefined') setPct(d.percent);
        if(d.errors && d.errors.length){ msg.textContent = d.errors[d.errors.length-1]; }
        if(d.finished){ setState(false); msg.textContent = (msg.textContent? msg.textContent + ' · ' : '') + '<?php echo esc_js(__('Completado','futbolin')); ?>'; return; }
        setTimeout(()=>tick(sl), 700);
      }).catch(e=>{ if(msg) msg.textContent='Error: '+e.message; setState(false); });
    }
  });
})();
</script>
