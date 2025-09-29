<?php
if (!defined('ABSPATH')) exit;

// Stubs/guards para análisis fuera de WP (no afectan runtime WP)
if (!function_exists('add_action')) { function add_action($h,$c,$p=10,$a=1){} }
if (!function_exists('add_submenu_page')) { function add_submenu_page($p,$pt,$mt,$cap,$slug,$cb){ return ''; } }
if (!function_exists('admin_url')) { function admin_url($p=''){ return $p; } }
if (!function_exists('current_user_can')) { function current_user_can($cap){ return true; } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $d; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return is_string($v)?trim($v):$v; } }
if (!function_exists('sanitize_title')) { function sanitize_title($v){ return strtolower(trim(preg_replace('/[^a-z0-9\-]+/i','-',(string)$v),'-')); } }
if (!function_exists('esc_url')) { function esc_url($v){ return $v; } }
if (!function_exists('esc_html__')) { function esc_html__($s,$d=null){ return $s; } }
if (!function_exists('esc_html')) { function esc_html($s){ return $s; } }
if (!function_exists('esc_attr')) { function esc_attr($s){ return $s; } }
if (!function_exists('esc_js')) { function esc_js($s){ return $s; } }
if (!function_exists('esc_textarea')) { function esc_textarea($s){ return $s; } }
if (!function_exists('__')) { function __($s,$d=null){ return $s; } }
if (!function_exists('selected')) { function selected($a,$b){ echo $a==$b?'selected="selected"':''; } }
if (!function_exists('checked')) { function checked($a,$b){ echo $a==$b?'checked="checked"':''; } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field($a){ echo '<input type="hidden" name="_wpnonce" value="stub" />'; } }

class Futbolin_Rankgen_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_page'));
    }

    public static function register_page() {
        // Colgar bajo "Ajustes" para evitar tocar tu UI interna (sin tocar class-futbolin-admin-page.php)
        add_submenu_page(
            'options-general.php',
            __('Generador de rankings', 'futbolin'),
            __('Generador de rankings', 'futbolin'),
            'manage_options',
            'futbolin-rankgen',
            array(__CLASS__, 'render_page')
        );
    }

    private static function get_drafts() {
        $drafts = get_option('futb_rankgen_drafts', array());
        if (!is_array($drafts)) $drafts = array();
        return $drafts;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) return;
        $drafts = self::get_drafts();
        $active_slug = isset($_GET['slug']) ? sanitize_title($_GET['slug']) : '';
        $set = array();
        if ($active_slug && isset($drafts[$active_slug])) {
            $set = $drafts[$active_slug];
        }

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
        $self_url   = esc_url( admin_url('options-general.php?page=futbolin-rankgen') );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Generador de rankings', 'futbolin'); ?></h1>

            <form method="get" action="">
                <input type="hidden" name="page" value="futbolin-rankgen" />
                <label><?php echo esc_html__('Seleccionar ranking', 'futbolin'); ?>:
                    <select name="slug" onchange="this.form.submit()">
                        <option value=""><?php echo esc_html__('(nuevo)', 'futbolin'); ?></option>
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
                <input type="hidden" name="action" value="futb_rankgen_save" />

                <table class="form-table" role="presentation"><tbody>
                    <tr>
                        <th><?php echo esc_html__('Estado','futbolin'); ?></th>
                        <td><label><input type="checkbox" name="set[is_enabled]" value="1" <?php echo $enabled?'checked':''; ?> /> <?php echo esc_html__('Activado','futbolin'); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Nombre del ranking','futbolin'); ?></th>
                        <td><input type="text" name="set[name]" value="<?php echo esc_attr($rg_name); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Slug (URL)','futbolin'); ?></th>
                        <td><input type="text" name="set[slug]" value="<?php echo esc_attr($rg_slug); ?>" class="regular-text" /></td>
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
                            <input type="text" name="set[temporadaId]" value="<?php echo esc_attr($temporadaId); ?>" class="regular-text" placeholder="Ej: 2024 o ID exacto" />
                            <br/><small><?php echo esc_html__('Próximo: selector dinámico por API.','futbolin'); ?></small>
                        </td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Tipos de competición','futbolin'); ?></th>
                        <td>
                            <?php $_tipos_val = esc_attr(implode(', ', $tipos_comp)); ?>
                            <input type="text" name="set[tipos_comp_raw]" value="<?php echo $_tipos_val; ?>" class="regular-text" placeholder="Ej: Open Dobles, Amateur Dobles" />
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
                            <label><?php echo esc_html__('Partidos','futbolin'); ?>: <input type="number" min="0" name="set[min_partidos]" value="<?php echo esc_attr($min_partidos); ?>" style="width:90px" /></label>
                            <label><?php echo esc_html__('Competiciones','futbolin'); ?>: <input type="number" min="0" name="set[min_competiciones]" value="<?php echo esc_attr($min_competic); ?>" style="width:90px" /></label>
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
                        <td><input type="number" min="1" name="set[top_n]" value="<?php echo esc_attr($top_n); ?>" /></td>
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
        <?php
    }
}

Futbolin_Rankgen_Admin::init();
