<?php
/**
 * Archivo: class-futbolin-admin-page.php
 * Ruta: includes/admin/class-futbolin-admin-page.php
 *
 * Descripción: Clase que gestiona la página de administración del plugin.
 */
if (!defined('ABSPATH')) exit;

// Importante: NO definir stubs de funciones de WordPress en runtime (este archivo corre dentro de WP).
// Cualquier stub aquí puede provocar "Cannot redeclare" cuando WP cargue su propia implementación
// (ej. get_current_screen en admin). Si necesitas stubs fuera de WP, colócalos en un archivo que
// solo se incluya en entorno de desarrollo, nunca en producción.

class Futbolin_Admin_Page {

    /**
     * Flag interno para imprimir el bloque JS de gestión de páginas solo una vez.
     * Evita propiedades dinámicas (deprecated en PHP 8.2).
     * @var bool
     */
    private $printed_pages_manager_js = false;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_plugin_page']);
        add_action('admin_init', [$this, 'page_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        // Aviso cuando falta configuración de API y endpoint AJAX para probar conexión
        add_action('admin_notices', [$this, 'maybe_show_connection_notice']);
        add_action('wp_ajax_futbolin_test_connection', [$this, 'ajax_test_connection']);
        // Log: endpoints AJAX (solo admin)
        add_action('wp_ajax_futbolin_tail_log', [$this, 'ajax_tail_log']);
        add_action('wp_ajax_futbolin_clear_log', [$this, 'ajax_clear_log']);
    add_action('wp_ajax_futbolin_clear_all_logs', [$this, 'ajax_clear_all_logs']);
    add_action('wp_ajax_futbolin_prepare_logs_zip', [$this, 'ajax_prepare_logs_zip']);
        // Páginas: endpoints para crear/gestionar páginas del plugin
        add_action('wp_ajax_futbolin_create_plugin_page', [$this, 'ajax_create_plugin_page']);
        add_action('wp_ajax_futbolin_get_permalink', [$this, 'ajax_get_permalink']);
        add_action('wp_ajax_futbolin_insert_shortcode', [$this, 'ajax_insert_shortcode']);
    add_action('wp_ajax_futbolin_delete_page', [$this, 'ajax_delete_page']);
    }

    /**
     * Carga CSS/JS solo en las pantallas del plugin.
     */
    public function enqueue_admin_assets($hook) {
        $target_hooks = ['toplevel_page_futbolin-api-settings'];
        $is_screen = in_array($hook, $target_hooks, true) || (isset($_GET['page']) && $_GET['page']==='futbolin-api-settings'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!$is_screen) { return; }
        // (El bloque fallback de cache se inserta únicamente en el embed específico; aquí solo van estilos si hace falta)
        // Eliminado bloque JS/cadenas sueltas corruptas.
            /* Espaciado adicional entre grupos del formulario */
        echo '<style>
        .futbolin-admin-layout{display:flex;align-items:stretch;gap:24px}
        .futbolin-sidebar{width:240px;flex:0 0 240px}
        .futbolin-content-area{flex:1 1 auto;min-width:0}
        .futbolin-tabs-nav{display:flex;flex-direction:column;gap:4px;margin:0;padding:0}
        .futbolin-tabs-nav a{display:flex;align-items:center;gap:8px;padding:10px 14px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;color:#334155;text-decoration:none;font-weight:500;transition:.15s}
        .futbolin-tabs-nav a .dashicons{line-height:1;font-size:18px;width:18px;height:18px}
        .futbolin-tabs-nav a:hover{background:#e2e8f0;color:#0f172a}
        .futbolin-tabs-nav a.active{background:#0f172a;color:#fff;border-color:#0f172a;font-weight:600}
        @media (max-width:1050px){.futbolin-admin-layout{flex-direction:column}.futbolin-sidebar{width:auto;display:block}.futbolin-tabs-nav{flex-direction:row;flex-wrap:wrap}}
        .futbolin-content-area .form-table{margin-bottom:26px}
        .futbolin-content-area h2{margin-top:30px}
        .futbolin-content-area h2:first-of-type{margin-top:0}
        .futbolin-content-area h2{background:#f8fafc;border:1px solid #e2e8f0;border-bottom:none;border-radius:10px 10px 0 0;padding:12px 18px;margin-bottom:0;color:#0f172a}
        .futbolin-content-area h2 + p{margin:0;padding:12px 18px;background:#fff;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;color:#334155}
        .futbolin-content-area h2 + p + table.form-table{background:#fff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;padding:10px 18px}
        .futbolin-content-area h2 + table.form-table{background:#fff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;padding:10px 18px}
        .futbolin-content-area table.form-table th{width:280px;padding:12px 18px;vertical-align:top}
        .futbolin-content-area table.form-table td{padding:12px 18px}
        .futbolin-card{margin-bottom:24px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#fff}
        .futbolin-card > h2{margin:0;border:0;background:#f8fafc;padding:12px 18px}
        .futbolin-card > p{padding:12px 18px}
        .futbolin-card > ul, .futbolin-card > ol, .futbolin-card > h3, .futbolin-card > h4{padding:12px 18px}
        .futbolin-card form p{padding:12px 18px}
        .developer-info-card .developer-info-header{padding:12px 18px}
        .developer-info-card .developer-info-header h3{margin:0}
        .developer-info-card .contact-info{padding:12px 18px}
        .developer-info-card .contact-info p{margin:0 0 8px 0}
        .futbolin-card .stats-control-group{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:14px 16px;border-top:1px solid #e2e8f0}
        .futbolin-card .stats-control-group > h4{width:100%;margin:0 0 6px 0;color:#0f172a}
        .futbolin-card .stats-control-group:first-of-type{border-top:1px solid #e2e8f0}
        .futbolin-card .stats-control-group .button{margin-right:4px}
        .futbolin-card .stats-control-group .description{color:#475569}
        .futbolin-card .stats-control-group form{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
        .futbolin-card .form-field{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
        .futbolin-card .form-field + .form-field{margin-top:8px}
        .futbolin-card .form-field label{font-weight:600;color:#0f172a}
        #futbolin-data-actions-log{margin:12px 16px 0 16px}
        .futbolin-card input[type=number], .futbolin-card select{height:32px}
        #futbolin-cache-stats{margin-left:8px;color:#334155!important}
        @media (max-width:782px){.futbolin-card .stats-control-group{flex-direction:column;align-items:flex-start}.futbolin-card .form-field{flex-direction:column;align-items:flex-start;gap:6px}}
        @media (max-width:1024px){.futbolin-admin-layout{display:block}}
        </style>';
        // Objeto JS global para AJAX (log y otros)
        $nonce = wp_create_nonce('futbolin_admin_nonce');
        $ajax  = admin_url('admin-ajax.php');
        echo '<script>window.futbolin_ajax_obj = window.futbolin_ajax_obj || {'
            .'ajax_url:'.json_encode(esc_url_raw($ajax)).','
            .'admin_nonce:'.json_encode($nonce).','
            .'default_profile_preview_id:'.json_encode(intval(get_option('futb_default_profile_preview_id',0)))
            .'};</script>';
        // (Solo estilos; el contenido HTML principal se genera en render_admin_page())
    }

    public function add_plugin_page(){
        add_menu_page(
            __('Futbolín API','futbolin'),
            __('Futbolín API','futbolin'),
            'manage_options',
            'futbolin-api-settings',
            [$this,'render_admin_page'],
            'dashicons-tickets',
            56
        );
    }

    public function render_admin_page(){
        if(!current_user_can('manage_options')){ return; }
        $opts = get_option('mi_plugin_futbolin_options', []);
        $admin_max_width = isset($opts['admin_max_width']) ? (int)$opts['admin_max_width'] : 1920;
        if ($admin_max_width < 960) { $admin_max_width = 960; }
        if ($admin_max_width > 3840) { $admin_max_width = 3840; }
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'inicio'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<div class="wrap">';
        echo '<h1 class="futbolin-admin-header">Futbolín API</h1>';
        echo '<div class="futbolin-admin-surface" style="max-width: '.esc_attr($admin_max_width).'px; margin:0 auto;">';
        echo '<div class="futbolin-admin-layout">';
        echo '<div class="futbolin-sidebar">';
        $tabs = [
            'inicio'=>['icon'=>'admin-home','label'=>__('Inicio','futbolin')],
            'documentacion'=>['icon'=>'book-alt','label'=>__('Documentación','futbolin')],
            'conexion'=>['icon'=>'shield','label'=>__('Conexión','futbolin')],
            'configuracion'=>['icon'=>'admin-settings','label'=>__('Configuración','futbolin')],
            'rutas'=>['icon'=>'admin-links','label'=>__('Páginas','futbolin')],
            'calculos'=>['icon'=>'media-text','label'=>__('Acciones de Datos','futbolin')],
            'oficiales'=>['icon'=>'forms','label'=>__('Control de listados oficiales','futbolin')],
            'rankgen'=>['icon'=>'filter','label'=>__('Generador de listados','futbolin')],
            'avanzado'=>['icon'=>'hammer','label'=>__('Avanzado','futbolin')],
            'log'=>['icon'=>'list-view','label'=>__('Log','futbolin')],
        ];
        echo '<nav class="futbolin-tabs-nav">';
        foreach($tabs as $k=>$tab){
            $cls = $k===$active_tab ? 'active' : '';
            $aria = $k===$active_tab ? ' aria-current="page"' : '';
            echo '<a href="?page=futbolin-api-settings&tab='.esc_attr($k).'" class="'.$cls.'"'.$aria.'><span class="dashicons dashicons-'.esc_attr($tab['icon']).'"></span> '.esc_html($tab['label']).'</a>';
        }
        echo '</nav></div><div class="futbolin-content-area">';
        settings_errors();
        echo '<div class="futbolin-tab-content active" id="tab-'.esc_attr($active_tab).'">';
        if ($active_tab === 'inicio') {
            $this->render_inicio_page();
        } elseif ($active_tab === 'documentacion') {
            $this->render_documentacion_page();
        } elseif ($active_tab === 'conexion') {

            $api_cfg = function_exists('futbolin_get_api_config') ? futbolin_get_api_config() : ['base_url'=>'','username'=>'','password'=>'','sources'=>['baseurl_source'=>'none','user_source'=>'none','pass_source'=>'none']];
            $saved = get_option('ranking_api_config', []);
            $base_url = isset($saved['base_url']) ? $saved['base_url'] : (is_array($api_cfg) ? ($api_cfg['base_url'] ?? '') : '');
            $username = isset($saved['username']) ? $saved['username'] : (is_array($api_cfg) ? ($api_cfg['username'] ?? '') : '');
            $password = isset($saved['password']) ? $saved['password'] : (is_array($api_cfg) ? ($api_cfg['password'] ?? '') : '');
            $last_ok = get_option('ranking_api_last_ok', []);
            $ok_info = '';
            if (is_array($last_ok) && !empty($last_ok['time'])) {
                $age = time() - (int)$last_ok['time'];
                $mins = max(1, (int) floor($age/60));
                $ok_info = sprintf(__('Última conexión OK hace %d min (host %s).','futbolin'), $mins, isset($last_ok['host'])?$last_ok['host']:'?');
            }
            $sources = is_array($api_cfg) && isset($api_cfg['sources']) && is_array($api_cfg['sources']) ? $api_cfg['sources'] : ['baseurl_source'=>'none','user_source'=>'none','pass_source'=>'none'];
            // Si hay valores guardados en esta pestaña, damos prioridad visual a su origen
            if (is_array($saved)) {
                if (!empty($saved['base_url'])) { $sources['baseurl_source'] = 'option_new'; }
                if (!empty($saved['username'])) { $sources['user_source']    = 'option_new'; }
                if (!empty($saved['password'])) { $sources['pass_source']    = 'option_new'; }
            }
            $src_labels = [
                'option_new'   => __('Ajustes (esta pestaña)','futbolin'),
                'option_legacy'=> __('Ajustes legacy','futbolin'),
                'filter'       => __('Filtro (futbolin_api_config)','futbolin'),
                'constant'     => __('Constante PHP','futbolin'),
                'master_json'  => __('Fallback del plugin (BUENO_master.json)','futbolin'),
                'none'         => __('No definido','futbolin'),
            ];
            ?>
            <form method="post" action="options.php">
                <?php settings_fields('ranking_api_settings'); ?>
                <div class="futbolin-card">
                    <h2><?php esc_html_e('Credenciales y endpoint de la API','futbolin'); ?></h2>
                    <p><?php esc_html_e('Configura la URL base de la API y las credenciales. Puedes probar la conexión antes de guardar.','futbolin'); ?></p>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Base URL de la API','futbolin'); ?></th>
                                <td>
                                    <input type="text" name="ranking_api_config[base_url]" id="rf-base-url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" placeholder="https://tu-dominio.tld/api">
                                    <p class="description"><?php esc_html_e('Ejemplo: https://tu-dominio.tld/api (sin barra final adicional).','futbolin'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Usuario','futbolin'); ?></th>
                                <td>
                                    <input type="text" name="ranking_api_config[username]" id="rf-username" value="<?php echo esc_attr($username); ?>" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Contraseña','futbolin'); ?></th>
                                <td>
                                    <input type="password" name="ranking_api_config[password]" id="rf-password" value="<?php echo esc_attr($password); ?>" class="regular-text">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php
                        $label_base = $src_labels[$sources['baseurl_source']] ?? $sources['baseurl_source'];
                        $label_user = $src_labels[$sources['user_source']] ?? $sources['user_source'];
                        $label_pass = $src_labels[$sources['pass_source']] ?? $sources['pass_source'];
                    ?>
                    <p id="rf-origins" class="description" style="margin-top:-6px" title="<?php echo esc_attr__('Guarda para que el plugin use estos valores. Hasta entonces constarán como “Formulario (sin guardar)”.','futbolin'); ?>">
                        <?php echo esc_html__('Origen actual de cada valor →','futbolin'); ?>
                        <strong>base_url:</strong> <span id="rf-src-base"><?php echo esc_html($label_base); ?></span>
                        · <strong>usuario:</strong> <span id="rf-src-user"><?php echo esc_html($label_user); ?></span>
                        · <strong>contraseña:</strong> <span id="rf-src-pass"><?php echo esc_html($label_pass); ?></span>
                    </p>
                    <?php submit_button(__('Guardar credenciales','futbolin')); ?>
                </div>

                <div class="futbolin-card">
                    <h2><?php esc_html_e('Probar conexión','futbolin'); ?></h2>
                    <p><?php esc_html_e('Esta prueba hace un login contra /Seguridad/login con los valores introducidos arriba, sin afectar a los guardados.','futbolin'); ?></p>
                    <p>
                        <button type="button" class="button button-secondary" id="rf-test-conn-btn"><?php esc_html_e('Probar ahora','futbolin'); ?></button>
                        <span id="rf-test-conn-status" style="margin-left:10px;color:#334155;"></span>
                    </p>
                    <?php if ($ok_info) { echo '<p class="description">'.esc_html($ok_info).'</p>'; } ?>
                </div>

                <div class="futbolin-card">
                    <h2><?php esc_html_e('¿Qué valor se usa si hay varios?','futbolin'); ?></h2>
                    <p><?php esc_html_e('El plugin toma los datos de conexión en este orden (el primero que esté disponible gana):','futbolin'); ?></p>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">1</th>
                                <td><?php esc_html_e('Ajustes de esta pestaña (ranking_api_config).','futbolin'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">2</th>
                                <td><?php esc_html_e('Ajustes legacy (mi_plugin_futbolin_options).','futbolin'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">3</th>
                                <td><?php esc_html_e('Filtro de desarrollador: futbolin_api_config (puede completar huecos).','futbolin'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">4</th>
                                <td><?php esc_html_e('Constantes PHP (FUTBOLIN_API_BASE_URL, FUTBOLIN_API_USER, FUTBOLIN_API_PASS).','futbolin'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">5</th>
                                <td><?php esc_html_e('Fallback del plugin: BUENO_master.json (solo base_url).','futbolin'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="description">
                        <?php esc_html_e('Importante: las constantes solo se usan si dejas vacío el campo correspondiente arriba. Es decir, los valores guardados aquí tienen prioridad.','futbolin'); ?>
                    </p>
                </div>

                <div class="futbolin-card">
                    <h2><?php esc_html_e('Opcional: definir constantes en wp-config.php','futbolin'); ?></h2>
                    <p><?php esc_html_e('Si prefieres configurar la conexión a nivel de servidor, puedes añadir estas líneas en wp-config.php (antes de “That’s all, stop editing!”). Sustituye los valores por los tuyos.','futbolin'); ?></p>
                    <?php
                        $base_demo = $base_url ? $base_url : 'https://tu-dominio.tld/api';
                        $user_demo = $username ? $username : 'tu_usuario';
                        $snippet = "define('FUTBOLIN_API_BASE_URL', '" . $base_demo . "');\n" .
                                   "define('FUTBOLIN_API_USER', '" . $user_demo . "');\n" .
                                   "define('FUTBOLIN_API_PASS', 'REEMPLAZA_CON_TU_PASSWORD');\n";
                    ?>
                    <textarea readonly rows="5" style="width:100%;font-family:Menlo,Consolas,monospace;" onclick="this.select();"><?php echo esc_textarea($snippet); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('Nota: según la precedencia actual, estas constantes actuarán como respaldo si alguno de los campos de arriba está vacío.','futbolin'); ?>
                    </p>
                </div>
            </form>
            <script>
            (function(){
                var $btn = document.getElementById('rf-test-conn-btn');
                var $st  = document.getElementById('rf-test-conn-status');
                if(!$btn) return;
                function v(id){var el=document.getElementById(id);return el?el.value:'';}
                function msg(text, ok){ if(!$st) return; $st.style.color = ok ? '#0f5132' : '#842029'; $st.textContent = text; }
                $btn.addEventListener('click', function(){
                    $btn.disabled=true; msg('<?php echo esc_js(__('Conectando...','futbolin')); ?>', true);
                    var data = {
                        action: 'futbolin_test_connection',
                        admin_nonce: (window.futbolin_ajax_obj&&futbolin_ajax_obj.admin_nonce)||'',
                        base_url: v('rf-base-url'), username: v('rf-username'), password: v('rf-password')
                    };
                    fetch((window.futbolin_ajax_obj&&futbolin_ajax_obj.ajax_url)||ajaxurl,{
                        method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                        body:new URLSearchParams(data).toString()
                    }).then(function(r){return r.json();}).then(function(r){
                        $btn.disabled=false;
                        if(r && r.success){
                            msg('<?php echo esc_js(__('OK: conexión verificada.','futbolin')); ?>', true);
                            var n=document.getElementById('futbolin-conn-notice'); if(n) n.remove();
                        } else {
                            var e=(r&&r.data&&r.data.message)?r.data.message:'<?php echo esc_js(__('No fue posible conectar.','futbolin')); ?>';
                            msg(e,false);
                        }
                    }).catch(function(e){ $btn.disabled=false; msg('Error: '+e,false); });
                });
                                // Actualiza el origen mostrado cuando el usuario teclea (antes de guardar)
                                var srcInit = {
                                    base: <?php echo json_encode($label_base); ?>,
                                    user: <?php echo json_encode($label_user); ?>,
                                    pass: <?php echo json_encode($label_pass); ?>
                                };
                                var savedVals = {
                                    base: <?php echo json_encode(isset($saved['base_url']) ? (string)$saved['base_url'] : ''); ?>,
                                    user: <?php echo json_encode(isset($saved['username']) ? (string)$saved['username'] : ''); ?>,
                                    pass: <?php echo json_encode(isset($saved['password']) ? (string)$saved['password'] : ''); ?>
                                };
                                var $srcBase = document.getElementById('rf-src-base');
                                var $srcUser = document.getElementById('rf-src-user');
                                var $srcPass = document.getElementById('rf-src-pass');
                                function updateOrigin(){
                                    var curBase = v('rf-base-url');
                                    var curUser = v('rf-username');
                                    var curPass = v('rf-password');
                                    // Si hay texto y difiere de lo guardado, indicamos "Formulario (sin guardar)"
                                    if ($srcBase) { $srcBase.textContent = (curBase && curBase !== savedVals.base) ? '<?php echo esc_js(__('Formulario (sin guardar)','futbolin')); ?>' : srcInit.base; }
                                    if ($srcUser) { $srcUser.textContent = (curUser && curUser !== savedVals.user) ? '<?php echo esc_js(__('Formulario (sin guardar)','futbolin')); ?>' : srcInit.user; }
                                    if ($srcPass) { $srcPass.textContent = (curPass && curPass !== savedVals.pass) ? '<?php echo esc_js(__('Formulario (sin guardar)','futbolin')); ?>' : srcInit.pass; }
                                }
                                ['rf-base-url','rf-username','rf-password'].forEach(function(id){
                                    var el = document.getElementById(id);
                                    if (!el) return;
                                    el.addEventListener('input', updateOrigin);
                                    el.addEventListener('change', updateOrigin);
                                });
                                updateOrigin();
            })();
            </script>
            <?php

        } elseif ($active_tab === 'configuracion') {

            ?>
            <form method="post" action="options.php">
                <?php settings_fields('mi_plugin_futbolin_option_group'); ?>
                <input type="hidden" name="mi_plugin_futbolin_options[__context]" value="configuracion">
                <?php
                do_settings_sections('futbolin-api-settings-configuracion');
                submit_button();
                ?>
            </form>
            <?php

        } elseif ($active_tab === 'rutas') {

            ?>
            <form method="post" action="options.php">
                <?php settings_fields('mi_plugin_futbolin_option_group'); ?>
                <input type="hidden" name="mi_plugin_futbolin_options[__context]" value="rutas">
                <?php
                do_settings_sections('futbolin-api-settings-rutas');
                submit_button();
                ?>
            </form>
            <script>
            (function(){
                function q(id){ return document.getElementById(id); }
                function ajax(action, data){
                    data = data||{}; data.action=action; data.admin_nonce=(window.futbolin_ajax_obj&&futbolin_ajax_obj.admin_nonce)||'';
                    return fetch((window.futbolin_ajax_obj&&futbolin_ajax_obj.ajax_url)||ajaxurl,{
                        method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:new URLSearchParams(data).toString()
                    }).then(function(r){ return r.json(); });
                }
                function saveLastSelected(kind, id){ try{ localStorage.setItem('rf_last_page_'+kind, String(id||'')); }catch(e){} }
                function loadLastSelected(kind){ try{ return localStorage.getItem('rf_last_page_'+kind)||''; }catch(e){ return ''; } }
                function getSelected(kind){
                    var name = (kind==='player') ? 'mi_plugin_futbolin_options[player_profile_page_id]' : 'mi_plugin_futbolin_options[ranking_page_id]';
                    var sel = document.querySelector('select[name="'+name+'"]');
                    return sel && sel.value ? { id: sel.value, title: sel.options[sel.selectedIndex].text, el: sel } : null;
                }
                // Restaurar última selección si no hay valor actual
                ['player','ranking'].forEach(function(kind){
                    var name = (kind==='player') ? 'mi_plugin_futbolin_options[player_profile_page_id]' : 'mi_plugin_futbolin_options[ranking_page_id]';
                    var sel = document.querySelector('select[name="'+name+'"]');
                    if (!sel) return;
                    if (!sel.value) {
                        var last = loadLastSelected(kind);
                        if (last) {
                            // Si existe opción con ese value, seleccionarla; si no, crearla como placeholder hasta que se guarde
                            var opt = Array.prototype.find.call(sel.options, function(o){return String(o.value)===String(last);});
                            if (opt) { sel.value = String(last); }
                        }
                    }
                    // Guardar en cada cambio
                    sel.addEventListener('change', function(){ if(sel && sel.value){ saveLastSelected(kind, sel.value); } });
                });
                function bindCreate(kind){
                    var pre = kind==='player' ? 'rf-player' : 'rf-ranking';
                    var $btn = q(pre+'-create'); if(!$btn) return;
                    var $title = q(pre+'-title'); var $open = q(pre+'-open'); var $msg = q(pre+'-msg');
                    var visName = pre.replace('rf-','')+'-vis'; var $pass = q(pre+'-pass');
                    function showPass(){ var v = (document.querySelector('input[name="'+visName+'"]:checked')||{}).value; if($pass){ $pass.style.display = (v==='password')?'inline-block':'none'; } }
                    document.querySelectorAll('input[name="'+visName+'"]').forEach(function(r){ r.addEventListener('change', showPass); }); showPass();
                    $btn.addEventListener('click', function(){
                        var title = ($title&&$title.value||'').trim(); if(!title){ if($msg){ $msg.textContent='Pon un título válido.'; } return; }
                        var vis = (document.querySelector('input[name="'+visName+'"]:checked')||{}).value || 'publish';
                        var pass = ($pass&&$pass.value)||'';
                        var shortcode = (kind==='player') ? '[futbolin_jugador]' : '[futbolin_ranking]';
                        $btn.disabled=true; if($msg){ $msg.textContent='Creando…'; }
                        ajax('futbolin_create_plugin_page', { title:title, visibility:vis, password:pass, shortcode:shortcode, kind:kind })
                        .then(function(r){
                            if(r && r.success && r.data){
                                var id = r.data.id; var link = r.data.link; if($msg){ $msg.textContent='Página creada (#'+id+').'; }
                                if($open){ $open.disabled=false; $open.onclick=function(){ window.open(link,'_blank'); } }
                                // Seleccionar en el dropdown correspondiente
                                var selectName = (kind==='player') ? 'mi_plugin_futbolin_options[player_profile_page_id]' : 'mi_plugin_futbolin_options[ranking_page_id]';
                                var sel = document.querySelector('select[name="'+selectName+'"]'); if(sel){ var opt = document.createElement('option'); opt.value=String(id); opt.textContent=title; opt.selected=true; sel.appendChild(opt); saveLastSelected(kind, id); }
                            } else { if($msg){ $msg.textContent = (r&&r.data&&r.data.message)||'No se pudo crear la página.'; } }
                        }).catch(function(e){ if($msg){ $msg.textContent='Error: '+e; } })
                        .finally(function(){ $btn.disabled=false; });
                    });
                }
                function bindInsert(kind){
                    var pre = kind==='player' ? 'rf-player' : 'rf-ranking';
                    var $btn = q(pre+'-insert'); if(!$btn) return;
                    $btn.addEventListener('click', function(){
                        var selObj = getSelected(kind);
                        if(!selObj){ alert('Selecciona primero una página.'); return; }
                        var shortcode = (kind==='player') ? '[futbolin_jugador]' : '[futbolin_ranking]';
                        $btn.disabled=true;
                        ajax('futbolin_insert_shortcode', { page_id: selObj.id, shortcode: shortcode })
                        .then(function(r){
                            var msg = (r && r.data && r.data.message) ? r.data.message : (r && r.success ? 'Shortcode insertado tras el contenido existente.' : 'No se pudo insertar.');
                            alert(msg);
                        })
                        .catch(function(e){ alert('Error: '+e); })
                        .finally(function(){ $btn.disabled=false; });
                    });
                }
                function bindOpenFromSelect(kind){
                    var id = (kind==='player') ? 'rf-player-open-from-select' : 'rf-ranking-open-from-select';
                    var $btn = q(id); if(!$btn) return;
                    $btn.addEventListener('click', function(){
                        var sel = getSelected(kind); if(!sel){ alert('Selecciona primero una página.'); return; }
                        ajax('futbolin_get_permalink', { page_id: sel.id }).then(function(r){
                            if(r&&r.success&&r.data&&r.data.link){
                                var url = r.data.link;
                                if(kind==='player' && url.indexOf('?')===-1 && url.indexOf('#')===-1){
                                    var defId = 4;
                                    if (window.futbolin_ajax_obj && typeof futbolin_ajax_obj.default_profile_preview_id !== 'undefined') {
                                        var parsed = parseInt(String(futbolin_ajax_obj.default_profile_preview_id||'0'), 10);
                                        if (!isNaN(parsed) && parsed >= 0) defId = parsed;
                                    }
                                    url += '?jugador_id=' + String(defId);
                                }
                                window.open(url,'_blank');
                                // persistir última selección al abrir
                                saveLastSelected(kind, sel.id);
                            } else { alert('No se pudo abrir.'); }
                        });
                    });
                }
                function bindDelete(kind){
                    var id = (kind==='player') ? 'rf-player-delete' : 'rf-ranking-delete';
                    var $btn = q(id); if(!$btn) return;
                    $btn.addEventListener('click', function(){
                        var sel = getSelected(kind); if(!sel){ alert('Selecciona primero una página.'); return; }
                        if(!confirm('¿Seguro que quieres enviar la página "'+sel.title+'" a la papelera? Podrás restaurarla desde la Papelera.')) return;
                        $btn.disabled=true;
                        ajax('futbolin_delete_page', { page_id: sel.id }).then(function(r){
                            if(r && r.success){
                                // Eliminar opción del select
                                var el = sel.el; if(el){ el.remove(el.selectedIndex); el.value=''; }
                                alert('Página enviada a la papelera.');
                            } else { alert((r&&r.data&&r.data.message)||'No se pudo borrar.'); }
                        }).catch(function(e){ alert('Error: '+e); }).finally(function(){ $btn.disabled=false; });
                    });
                }
                bindCreate('player');
                bindCreate('ranking');
                bindInsert('player');
                bindInsert('ranking');
                bindOpenFromSelect('player');
                bindOpenFromSelect('ranking');
                bindDelete('player');
                bindDelete('ranking');
            })();
            </script>
            <?php

                } elseif ($active_tab === 'calculos') {

            // Render vertical, sin submenú: tres bloques apilados
            echo '<div class="futbolin-card futbolin-card-cache">';
            echo '<h2>' . esc_html__('Caché','futbolin') . '</h2>';
            if (is_callable([$this,'clear_caches_button_callback'])) { $this->clear_caches_button_callback(); }
            // Toggle de caché global del plugin
            echo '<div class="stats-control-group">';
            echo '<form method="post" action="options.php">';
            settings_fields('mi_plugin_futbolin_option_group');
            $enabled = get_option('rf_cache_enabled', 1);
            echo '<label><input type="checkbox" name="rf_cache_enabled" value="1" ' . checked(1, (int)$enabled, false) . ' /> ' . esc_html__('Habilitar caché del plugin','futbolin') . '</label> ';
            echo '<span class="description">' . esc_html__('Si se desactiva, el plugin no leerá ni escribirá transients propios (los datos se computan en caliente).','futbolin') . '</span> ';
            submit_button(__('Guardar'), 'secondary', 'submit', false);
            echo '</form>';
            echo '</div>';
            echo '</div>';

            // Bloque "Estadísticas globales" eliminado a petición del usuario

            // Tarjeta Hall of Fame movida a pestaña 'oficiales'

        } elseif ($active_tab === 'oficiales') {

            echo '<div class="futbolin-card">';
            echo '<h2>' . esc_html__('Control de listados oficiales','futbolin') . '</h2>';
            echo '<p class="description">' . esc_html__('Este bloque no funcional ha sido movido aquí tal cual para diagnóstico y ajustes futuros.','futbolin') . '</p>';
            echo '<hr style="margin:16px 0">';
            echo '<h3 style="margin:0 0 8px 0;">' . esc_html__('Hall of Fame','futbolin') . '</h3>';
            if (is_callable([$this,'calculate_hall_of_fame_callback'])) { $this->calculate_hall_of_fame_callback(); }
            // TODO: mover aquí el bloque específico solicitado por el usuario (contenido original no funcional)
            echo '</div>';

        } elseif ($active_tab === 'rankgen') {

            // Generador de rankings: partial con su propio <form> (admin-post), NUNCA dentro de options.php
            include_once FUTBOLIN_API_PATH . 'includes/admin/partials/rankgen-tab.php';

    } elseif ($active_tab === 'avanzado') {

        // === AVANZADO (timeout y reintentos) ===
            $opts    = get_option('mi_plugin_futbolin_options', []);
            $timeout = isset($opts['http_timeout']) ? (int)$opts['http_timeout'] : 30;
            if ($timeout < 5 || $timeout > 120) { $timeout = 30; }
            $retries = isset($opts['http_retries']) ? (int)$opts['http_retries'] : 3;
            if ($retries < 0 || $retries > 5) { $retries = 3; }
            $admin_max_width = isset($opts['admin_max_width']) ? (int)$opts['admin_max_width'] : 1920;
            if ($admin_max_width < 960) { $admin_max_width = 960; }
            if ($admin_max_width > 3840) { $admin_max_width = 3840; }
            ?>

            <div class="futb-advanced-vertical">
            <form method="post" action="options.php" class="futb-adv-item">
                <?php settings_fields('mi_plugin_futbolin_option_group'); ?>
                <input type="hidden" name="mi_plugin_futbolin_options[__context]" value="avanzado">

                <div class="futbolin-card">
                    <h2><?php esc_html_e('Ajustes HTTP (API)','futbolin'); ?></h2>
                    <p><?php esc_html_e('Controla los timeouts y reintentos de llamadas a la API del ranking. No afecta a peticiones ajenas al plugin.','futbolin'); ?></p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Timeout (segundos)','futbolin'); ?></th>
                                <td>
                                    <input type="number" min="5" max="120" step="1" name="mi_plugin_futbolin_options[http_timeout]" value="<?php echo esc_attr($timeout); ?>" style="width:110px;">
                                    <span class="description"> <?php esc_html_e('Rango recomendado: 5–120 s (por defecto 30).','futbolin'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Ancho máximo del panel (px)','futbolin'); ?></th>
                                <td>
                                    <input type="number" min="960" max="3840" step="10" name="mi_plugin_futbolin_options[admin_max_width]" value="<?php echo esc_attr($admin_max_width); ?>" style="width:120px;" id="rf-admin-maxw-input">
                                    <select id="rf-admin-maxw-preset" style="margin-left:8px;">
                                        <?php $presets = [1280,1440,1600,1920]; foreach($presets as $p){ $sel = ((int)$admin_max_width===$p)?'selected':''; echo '<option value="'.esc_attr($p).'" '.$sel.'>'.esc_html($p.' px').'</option>'; } ?>
                                        <option value="custom" <?php echo in_array((int)$admin_max_width, [1280,1440,1600,1920], true)?'':'selected'; ?>><?php esc_html_e('Personalizado','futbolin'); ?></option>
                                    </select>
                                    <span class="description" style="display:block;margin-top:6px;"> <?php esc_html_e('El desplegable aplica presets rápidos; el número permite cualquier valor entre 960 y 3840. Se previsualiza al cambiar y se guarda al pulsar “Guardar”.','futbolin'); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Reintentos','futbolin'); ?></th>
                                <td>
                                    <input type="number" min="0" max="5" step="1" name="mi_plugin_futbolin_options[http_retries]" value="<?php echo esc_attr($retries); ?>" style="width:110px;">
                                    <span class="description"> <?php esc_html_e('Intentos extra tras un fallo transitorio (0–5, por defecto 3).','futbolin'); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="description">
                        <?php esc_html_e('Dominio permitido: ranking.fefm.net (Swagger API oficial).','futbolin'); ?>
                    </p>

                    <?php submit_button(); ?>
                </div>
            </form>
            <div class="futbolin-card futb-adv-item">
                <h2><?php esc_html_e('Integración front‑end','futbolin'); ?></h2>
                <p><?php esc_html_e('Control de la encapsulación de estilos para el ranking en el front‑end.','futbolin'); ?></p>
                <form method="post" action="options.php">
                    <?php settings_fields('rf_settings_group'); ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Modo aislado (Shadow DOM)','futbolin'); ?></th>
                                <td>
                                    <?php $val = get_option('rf_shadow_mode', 0); ?>
                                    <label>
                                        <input type="checkbox" name="rf_shadow_mode" value="1" <?php checked(1, $val); ?>>
                                        <?php esc_html_e('Activar','futbolin'); ?>
                                    </label>
                                    <p class="description">
                                        <?php esc_html_e('Aísla los estilos del ranking para que no los afecte el tema o el maquetador.','futbolin'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>

            </div>
            <div class="futbolin-card futb-adv-item">
                <h2><?php esc_html_e('Herramienta de Chequeo','futbolin'); ?></h2>
                <p><?php esc_html_e('Ejecuta una batería de validaciones contra la API (login, normalizaciones, wrappers/paginación, ALL≥PAG, búsqueda mínima con fallbacks).','futbolin'); ?></p>
                <form method="get" action="">
                    <input type="hidden" name="page" value="futbolin-api-settings">
                    <input type="hidden" name="tab" value="avanzado">
                    <input type="hidden" name="futbolin_diag" value="1">
                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Ejecutar','futbolin'); ?></button>
                    </p>
                </form>
            </div>
            </div><!-- /.futb-advanced-vertical -->
            <style>
            /* Forzado layout vertical en pestaña Avanzado */
            .futb-advanced-vertical{display:block;margin:0;padding:0;}
            .futb-advanced-vertical > .futb-adv-item{display:block;margin:0 0 32px 0;}
            .futb-advanced-vertical .futbolin-card{display:block;}
            .futb-advanced-vertical .futbolin-card form{margin-top:12px;}
            </style>
            <script>
            (function(){
                var num = document.getElementById('rf-admin-maxw-input');
                var sel = document.getElementById('rf-admin-maxw-preset');
                var surface = document.querySelector('.futbolin-admin-surface');
                function clamp(v){ v = parseInt(v||0,10); if(isNaN(v)) v=1920; if(v<960) v=960; if(v>3840) v=3840; return v; }
                function apply(v){ if(surface){ surface.style.maxWidth = clamp(v) + 'px'; } }
                if (num){ num.addEventListener('input', function(){ var v=clamp(num.value); // sync preset if exact
                    if (sel){ var found=false; for (var i=0;i<sel.options.length;i++){ if (sel.options[i].value!=="custom" && parseInt(sel.options[i].value,10)===v){ sel.selectedIndex=i; found=true; break; } }
                        if(!found){ sel.value='custom'; }
                    }
                    apply(v);
                }); }
                if (sel){ sel.addEventListener('change', function(){ var v = sel.value==='custom' ? num.value : sel.value; v = clamp(v); num.value = v; apply(v); }); }
                // Initial ensure
                if (num){ apply(num.value); }
            })();
            </script>

            <?php
            if (isset($_GET['futbolin_diag']) && $_GET['futbolin_diag'] == '1') {
                if (!class_exists('Futbolin_Diagnostic')) {
                    // Autoloader localizará la clase si existe en includes/admin/class-futbolin-diagnostic.php
                }
                if (class_exists('Futbolin_Diagnostic') && is_callable(['Futbolin_Diagnostic','render_page'])) {
                    // Pintamos el diagnóstico completo debajo (no alteramos el frontend)
                    Futbolin_Diagnostic::render_page();
                } else {
                    echo '<div class="notice notice-error"><p>No se pudo cargar la herramienta de chequeo.</p></div>';
                }
            }
            ?>

            <?php
        }
        elseif ($active_tab === 'log') {
            $opts = get_option('mi_plugin_futbolin_options', []);
            // Mostrar habilitado por defecto si no existe (por claridad de UI)
            $log_enabled = isset($opts['rf_logging_enabled']) ? (int)!!$opts['rf_logging_enabled'] : 1;
            $wp_log = (class_exists('Futbolin_Logger') ? Futbolin_Logger::get_wp_debug_log_file() : null);
            $files = (class_exists('Futbolin_Logger') ? Futbolin_Logger::list_current_log_files() : []);
            ?>
            <div class="futbolin-card">
                <h2><?php esc_html_e('Ajustes de logging','futbolin'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('mi_plugin_futbolin_option_group'); ?>
                    <input type="hidden" name="mi_plugin_futbolin_options[__context]" value="logging">
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e('Habilitar log del plugin','futbolin'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="mi_plugin_futbolin_options[rf_logging_enabled]" value="1" <?php checked(1,$log_enabled); ?>> <?php esc_html_e('Activar','futbolin'); ?></label>
                                    <p class="description"><?php esc_html_e('Se generan tres archivos:','futbolin'); ?>
                                        <br>• <strong>Bajo</strong>: solo errores críticos del plugin.
                                        <br>• <strong>Medio</strong>: todos los mensajes del plugin (info/aviso/error).
                                        <br>• <strong>Alto</strong>: todo lo anterior más depuración detallada.
                                        <br><?php esc_html_e('Cada archivo rota automáticamente al llegar a 19MB (se comprime con fecha/hora).','futbolin'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>

            <div class="futbolin-card">
                <h2><?php esc_html_e('Visor de log','futbolin'); ?></h2>
                <div class="stats-control-group">
                    <button type="button" class="button" id="rf-log-refresh"><?php esc_html_e('Refrescar','futbolin'); ?></button>
                    <button type="button" class="button" id="rf-log-clear"><?php esc_html_e('Vaciar','futbolin'); ?></button>
                    <a class="button" id="rf-log-download" href="#" download="rf.log"><?php esc_html_e('Descargar','futbolin'); ?></a>
                    <button type="button" class="button" id="rf-log-clear-all" style="margin-left:8px;" title="Borra Bajo/Medio/Alto (recomendado descargar antes)"><?php esc_html_e('Borrar TODOS','futbolin'); ?></button>
                    <button type="button" class="button" id="rf-log-download-all" title="Descarga un ZIP con los tres logs si está disponible">
                        <?php esc_html_e('Descargar TODOS','futbolin'); ?>
                    </button>
                    <label style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                        <span><?php esc_html_e('Fuente','futbolin'); ?>:</span>
                        <select id="rf-log-source">
                            <optgroup label="Plugin">
                                <option value="plugin-high"><?php esc_html_e('Plugin – Alto','futbolin'); ?></option>
                                <option value="plugin-medium"><?php esc_html_e('Plugin – Medio','futbolin'); ?></option>
                                <option value="plugin-low"><?php esc_html_e('Plugin – Bajo','futbolin'); ?></option>
                            </optgroup>
                            <option value="plugin-combined" selected="selected"><?php esc_html_e('Combinado: Plugin + WP','futbolin'); ?></option>
                            <option value="wp" <?php echo empty($wp_log) ? 'disabled' : ''; ?>><?php esc_html_e('WP debug.log','futbolin'); ?></option>
                        </select>
                        <input type="text" id="rf-log-search" placeholder="<?php esc_attr_e('Buscar… (texto o código: 200, 401, 404, 500)','futbolin'); ?>" style="width:220px;" />
                        <div class="rf-quick-codes" style="display:flex;gap:4px;">
                            <?php foreach([200,401,403,404,500] as $c){ echo '<button type="button" class="button" data-code="'.esc_attr($c).'">'.$c.'</button>'; } ?>
                        </div>
                        <label style="display:flex;align-items:center;gap:6px;">
                            <input type="checkbox" id="rf-log-autorefresh" checked /> <?php esc_html_e('Autorefresco','futbolin'); ?>
                        </label>
                        <input type="number" id="rf-log-autorefresh-interval" min="2" max="60" step="1" value="5" style="width:64px;" title="segundos" />s
                    </label>
                </div>
                <pre id="rf-log-view" style="display:block;max-height:460px;overflow:auto;background:#0b1021;color:#e6edf3;padding:12px;border-radius:8px;white-space:pre-wrap;word-break:break-word;"></pre>
                <p style="margin-top:6px;color:#666;font-size:12px;">
                    <?php esc_html_e('Nota: las fuentes "Plugin – Alto/Medio/Bajo" muestran SOLO eventos del plugin. "WP debug.log" muestra todo WordPress. "Combinado" concatena ambos para facilitar la comparación.', 'futbolin'); ?>
                </p>
                <div class="description">
                    <?php
                    if (is_array($files)) {
                        echo '<p style="margin:6px 0 2px 0;"><strong>Ubicación de archivos actuales:</strong></p><ul style="margin:0;">';
                        foreach ($files as $f) {
                            echo '<li>'.esc_html(strtoupper($f['tier'])).': <code>'.esc_html($f['path']).'</code> ('.esc_html(number_format_i18n((int)$f['size']/1024, 0)).' KB)</li>';
                        }
                        echo '</ul>';
                    }
                    if ($wp_log) { echo '<p style="margin:6px 0 0 0;">WP debug.log: <code>'.esc_html($wp_log).'</code></p>'; }
                    ?>
                </div>
            </div>

            <script>
            (function(){
                var $view = document.getElementById('rf-log-view');
                var $src  = document.getElementById('rf-log-source');
                var $dl   = document.getElementById('rf-log-download');
                var $lvl  = null; // eliminado selector de nivel
                var $auto = document.getElementById('rf-log-autorefresh');
                var $intv = document.getElementById('rf-log-autorefresh-interval');
                var $dlAll= document.getElementById('rf-log-download-all');
                var $clrAll= document.getElementById('rf-log-clear-all');
                var $search = document.getElementById('rf-log-search');
                var timer = null;
                var ready = false;
                var currentBlobUrl = null;
                var lastText = '';
                if($view){ $view.textContent = 'Cargando…'; }
                function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
                function colorize(line){
                    try{
                        var raw = String(line||'');
                        var esc = escapeHtml(raw);
                        if(/\b(5\d\d)\b/.test(raw)) return '<span style="display:block;background:#3a0f0f;color:#ffdada;padding:1px 4px;border-radius:2px;">'+esc.replace(/\b(5\d\d)\b/g,'<span style="color:#ff6b6b;font-weight:600;">$1<\/span>')+'</span>';
                        if(/\b(4\d\d)\b/.test(raw)) return '<span style="display:block;background:#3a2a00;color:#ffe8b5;padding:1px 4px;border-radius:2px;">'+esc.replace(/\b(4\d\d)\b/g,'<span style="color:#f7b955;font-weight:600;">$1<\/span>')+'</span>';
                        if(/\b(2\d\d)\b/.test(raw)) return '<span style="display:block;background:#0f2d0f;color:#c9f3c9;padding:1px 4px;border-radius:2px;">'+esc.replace(/\b(2\d\d)\b/g,'<span style="color:#7ad67a;font-weight:600;">$1<\/span>')+'</span>';
                        return esc; // sin código, solo escapado
                    }catch(e){ return escapeHtml(line); }
                }
                function applySearch(text){
                    var q = ($search&&$search.value||'').trim();
                    if(!q) return text;
                    try{
                        var lines = (text||'').split(/\r?\n/);
                        var rx = /\d+/i.test(q) ? new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'i') : new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'i');
                        return lines.filter(function(l){ return rx.test(l); }).join('\n');
                    }catch(e){ return text; }
                }
                function isNonceError(msg){ return /nonce/i.test(String(msg||'')); }
                function fetchLog(silent) {
                    var data = { action:'futbolin_tail_log', admin_nonce:(window.futbolin_ajax_obj&&futbolin_ajax_obj.admin_nonce)||'', type:$src.value };
                    return fetch((window.futbolin_ajax_obj&&futbolin_ajax_obj.ajax_url)||ajaxurl,{ method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:new URLSearchParams(data).toString() })
                    .then(function(r){return r.json();}).then(function(r){
                        if (r && r.success && r.data) {
                            var txt = r.data.text || '';
                            lastText = txt;
                            // Pintado con color por rango de código HTTP si el contenido lo incluye
                            var render = applySearch(lastText);
                            try{
                                var colored = render.split(/\r?\n/).map(colorize).join('\n');
                                $view.innerHTML = colored; // ya viene escapado por línea
                            }catch(e){ $view.textContent = render; }
                            // preparar descarga
                            if (currentBlobUrl) { try { URL.revokeObjectURL(currentBlobUrl); } catch(_){} }
                            var blob = new Blob([txt], {type:'text/plain'});
                            currentBlobUrl = URL.createObjectURL(blob);
                            var fname = 'rf-log-' + ($src&&$src.value?$src.value:'src') + '-' + new Date().toISOString().replace(/[:T]/g,'-').slice(0,19) + '.log';
                            $dl.href = currentBlobUrl;
                            try { $dl.setAttribute('download', fname); } catch(_) {}
                            ready = true;
                            return { success:true };
                        } else {
                            var msg = (r&&r.data&&r.data.message)||'No se pudo leer el log.';
                            if(!silent || !isNonceError(msg)) { $view.textContent = msg; }
                            ready = false; $dl.removeAttribute('download'); $dl.href = '#';
                            return { success:false, nonceError:isNonceError(msg) };
                        }
                    }).catch(function(e){ if(!silent){ $view.textContent = 'Error: '+e; } ready=false; $dl.removeAttribute('download'); $dl.href='#'; return { success:false, nonceError:false }; });
                }
                document.getElementById('rf-log-refresh').addEventListener('click', fetchLog);
                $dl.addEventListener('click', function(ev){
                    if (!ready) {
                        ev.preventDefault();
                        fetchLog(true).then(function(res){ if(res&&res.success){ setTimeout(function(){ $dl.click(); }, 0); } });
                    }
                });
                document.getElementById('rf-log-clear').addEventListener('click', function(){
                    var data = { action:'futbolin_clear_log', admin_nonce:(window.futbolin_ajax_obj&&futbolin_ajax_obj.admin_nonce)||'', type:$src.value };
                    fetch((window.futbolin_ajax_obj&&futbolin_ajax_obj.ajax_url)||ajaxurl,{ method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:new URLSearchParams(data).toString() })
                    .then(function(r){return r.json();}).then(function(r){ fetchLog(); }).catch(function(e){ fetchLog(); });
                });
                $src.addEventListener('change', function(){
                    var v = $src.value;
                    var disableClear = (v === 'plugin-combined');
                    var btn = document.getElementById('rf-log-clear');
                    if(btn){ btn.disabled = disableClear; btn.title = disableClear ? 'No aplicable en modo combinado' : ''; }
                    ready = false; if($dl){ $dl.removeAttribute('download'); $dl.href = '#'; }
                    fetchLog();
                });
                // Carga inicial
                fetchLog(true).then(function(){
                    // activar autorefresco si está marcado
                    if($auto){ $auto.checked = true; }
                    if($intv){ var s = parseInt($intv.value||'5',10); if(!s||s<2) s=5; if(s>60) s=60; timer=setInterval(fetchLog, s*1000); }
                });
                if($search){ $search.addEventListener('input', function(){ $view.textContent = applySearch(lastText); }); }
                var $chips = document.querySelectorAll('.rf-quick-codes .button');
                if($chips&&$chips.length){
                    $chips.forEach(function(btn){ btn.addEventListener('click', function(){ if($search){ $search.value = String(btn.getAttribute('data-code')||''); $view.textContent = applySearch(lastText); } }); });
                }
                if($clrAll){ $clrAll.addEventListener('click', function(){
                    if (!confirm('¿Seguro que deseas BORRAR todos los logs (Bajo/Medio/Alto)?\n\nSugerencia: usa “Descargar TODOS” antes si quieres conservarlos.')) return;
                    var data = { action:'futbolin_clear_all_logs', admin_nonce:(window.futbolin_ajax_obj&&futbolin_ajax_obj.admin_nonce)||'' };
                    fetch((window.futbolin_ajax_obj&&futbolin_ajax_obj.ajax_url)||ajaxurl,{ method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:new URLSearchParams(data).toString() })
                    .then(function(r){return r.json();}).then(function(r){ fetchLog(); }).catch(function(e){ fetchLog(); });
                }); }
                if($dlAll){ $dlAll.addEventListener('click', function(){
                    var data = { action:'futbolin_prepare_logs_zip', admin_nonce:(window.futbolin_ajax_obj&&futbolin_ajax_obj.admin_nonce)||'' };
                    fetch((window.futbolin_ajax_obj&&futbolin_ajax_obj.ajax_url)||ajaxurl,{ method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:new URLSearchParams(data).toString() })
                    .then(function(r){return r.json();}).then(function(r){
                        try{
                            if (r && r.success && r.data && r.data.zip_url) {
                                // Navegar en misma pestaña para permitir descarga directa por el navegador
                                window.location.href = r.data.zip_url;
                                return;
                            }
                            if (r && r.success && r.data && r.data.files) {
                                var files = (r.data.files||[]).filter(function(f){ return f && f.url; });
                                if (files.length === 0) { alert('No hay archivos de log disponibles para descargar.'); return; }
                                // Abre cada URL en pestaña nueva como fallback
                                files.forEach(function(f){ try{ window.open(f.url, '_blank'); }catch(_){} });
                                return;
                            }
                            alert('No se pudo preparar la descarga.');
                        }catch(e){ alert('Error preparando descarga: '+e); }
                    }).catch(function(e){ alert('Error creando ZIP: '+e); });
                }); }
                function restartTimer(){
                    if(timer){ clearInterval(timer); timer=null; }
                    if($auto && $auto.checked){
                        var s = parseInt(($intv&&$intv.value)||'5', 10); if(!s || s<2) s=5; if(s>60) s=60;
                        timer = setInterval(fetchLog, s*1000);
                    }
                }
                if($auto){ $auto.addEventListener('change', function(){ restartTimer(); if($auto.checked){ fetchLog(); } }); }
                if($intv){ $intv.addEventListener('change', function(){ restartTimer(); }); }
                // Primer refresco con reintentos silenciosos para evitar mostrar "bad nonce" fugaz
                (function initialLoad(){
                    var attempts = 5; var delay = 500;
                    function attempt(){
                        fetchLog(true).then(function(res){
                            if(!res || !res.success){
                                if(res && res.nonceError && attempts>1){ attempts--; setTimeout(attempt, delay); }
                                else if(!res || !res.nonceError){ /* mostraría error ya si no es nonce y no es silent */ }
                            }
                        });
                    }
                    attempt();
                })();
            })();
            </script>
            <?php
        }
        // Cierre de tab-content, content-area, layout y surface
        echo '</div></div></div></div>';
    }

    // --- REGISTRO DE AJUSTES Y SECCIONES ---
    public function page_init() {
        register_setting('mi_plugin_futbolin_option_group', 'mi_plugin_futbolin_options', [$this, 'sanitize_options']);
        register_setting('mi_plugin_futbolin_option_group', 'futbolin_group_open_ids');
        register_setting('mi_plugin_futbolin_option_group', 'futbolin_group_rookie_ids');
        register_setting('mi_plugin_futbolin_option_group', 'futbolin_group_resto_ids');
        // Grupo de ajustes para la conexión (base_url, username, password)
        register_setting('ranking_api_settings', 'ranking_api_config', [$this, 'sanitize_api_config']);

        // 1) Opciones Generales
        add_settings_section('configuracion_section_id', 'Menu ranking elo', [$this, 'print_configuracion_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('default_modalidad',   'Modalidad por Defecto',  [$this, 'default_modalidad_callback'],  'futbolin-api-settings-configuracion', 'configuracion_section_id');
        add_settings_field('ranking_modalities',  'Modalidades de Ranking', [$this, 'ranking_modalities_callback'], 'futbolin-api-settings-configuracion', 'configuracion_section_id');
    // ID por defecto para previsualización de perfil
    add_settings_field('default_profile_preview_id', __('ID por defecto para previsualización de perfil','futbolin'), [$this, 'default_profile_preview_id_callback'], 'futbolin-api-settings-configuracion', 'configuracion_section_id');

        // Nueva sección: Menu ranking anual (debajo de Modalidades de Ranking)
        add_settings_section('menu_ranking_anual_section_id', 'Menu ranking anual', function(){
            echo '<p>Activa o desactiva qué modalidades aparecen en el menú de Ranking anual.</p>';
        }, 'futbolin-api-settings-configuracion');
        add_settings_field('default_modalidad_anual', __('Modalidad por Defecto (anual)','futbolin'), [$this, 'default_modalidad_anual_callback'], 'futbolin-api-settings-configuracion', 'menu_ranking_anual_section_id');
        add_settings_field('enable_annual_doubles', __('Ranking anual – Dobles','futbolin'), function(){ $this->render_checkbox_callback_default_on('enable_annual_doubles', __('Habilitar ranking anual de Dobles','futbolin')); }, 'futbolin-api-settings-configuracion', 'menu_ranking_anual_section_id');
        add_settings_field('enable_annual_individual', __('Ranking anual – Individual','futbolin'), function(){ $this->render_checkbox_callback_default_on('enable_annual_individual', __('Habilitar ranking anual de Individual','futbolin')); }, 'futbolin-api-settings-configuracion', 'menu_ranking_anual_section_id');

        // 2) Visualización de Ranking
    add_settings_section('visualizacion_ranking_section_id', 'Menu Estadisticas', [$this, 'print_visual_ranking_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('show_champions',    'Mostrar Campeones de España',    [$this, 'show_champions_callback'],    'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_tournaments',  'Mostrar Campeonatos Disputados', [$this, 'show_tournaments_callback'],  'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_hall_of_fame', 'Mostrar Hall of Fame',           [$this, 'show_hall_of_fame_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_finals_reports','Mostrar Informes',              [$this, 'show_finals_reports_callback'],'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('show_global_stats', 'Mostrar Estadísticas globales', [$this, 'show_global_stats_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('enable_fefm_no1_club', __('FEFM Nº1 CLUB','futbolin'), [$this, 'enable_fefm_no1_club_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('enable_club_500_played', __('Club 500 – Played','futbolin'), [$this, 'enable_club_500_played_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('enable_club_100_winners', __('Club 100 – Winners','futbolin'), [$this, 'enable_club_100_winners_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');
        add_settings_field('enable_top_rivalries', __('Top rivalidades','futbolin'), [$this, 'enable_top_rivalries_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_ranking_section_id');

        // 2.1) Listados creados con el Generador (toggles dinámicos)
        add_settings_section('visualizacion_rankgen_section_id', __('Listados del Generador','futbolin'), function(){
            echo '<p>'.esc_html__('Activa o desactiva los listados creados con el Generador para que aparezcan en el menú de Estadísticas.','futbolin').'</p>';
            $sets = get_option('futb_rankgen_sets', []);
            if (!is_array($sets) || empty($sets)) {
                echo '<p><em>'.esc_html__('No hay listados creados todavía.','futbolin').'</em></p>';
                return;
            }
            $opts = get_option('mi_plugin_futbolin_options', []);
            echo '<table class="form-table"><tbody>';
            foreach ($sets as $slug => $cfg) {
                $name = isset($cfg['name']) && $cfg['name'] !== '' ? $cfg['name'] : $slug;
                $key  = 'enable_rankgen__' . sanitize_key($slug);
                $checked = (isset($opts[$key]) && $opts[$key] === 'on') ? 'checked' : '';
                echo '<tr><th scope="row">'.esc_html($name).'</th><td>';
                echo '<label><input type="checkbox" name="mi_plugin_futbolin_options['.esc_attr($key).']" value="on" '.$checked.'> ' . esc_html__('Activar en menú de Estadísticas','futbolin') . '</label>';
                echo '<br/><small><code>[futb_rankgen slug="'.esc_html($slug).'"]</code></small>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }, 'futbolin-api-settings-configuracion');



        // 3) Visualización de Jugador
        add_settings_section('visualizacion_player_section_id', 'Visualización de Jugador', [$this, 'print_visual_player_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('enable_player_profile', __('Habilitar perfil de jugador','futbolin'), [$this, 'enable_player_profile_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_glicko', __('Clasificación (Glicko)','futbolin'), [$this, 'show_player_glicko_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_summary', __('General (resumen)','futbolin'), [$this, 'show_player_summary_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_stats', __('Estadísticas','futbolin'), [$this, 'show_player_stats_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_hitos', __('Hitos','futbolin'), [$this, 'show_player_hitos_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_history', __('Partidos','futbolin'), [$this, 'show_player_history_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');
        add_settings_field('show_player_torneos', __('Torneos','futbolin'), [$this, 'show_player_torneos_callback'], 'futbolin-api-settings-configuracion', 'visualizacion_player_section_id');

        // 4) Modo Mantenimiento
        add_settings_section('maintenance_section_id', 'Modo Mantenimiento', [$this, 'print_maintenance_info'], 'futbolin-api-settings-configuracion');
        add_settings_field('maintenance_mode', '🛠️ Modo mantenimiento (bloquea el plugin en el front)', [$this, 'maintenance_mode_callback'], 'futbolin-api-settings-configuracion', 'maintenance_section_id');

        // Rutas
        add_settings_section('rutas_section_id', 'Páginas del Plugin', null, 'futbolin-api-settings-rutas');
        add_settings_field('player_profile_page_id', 'Página de Perfil de Jugador', [$this, 'player_profile_page_callback'], 'futbolin-api-settings-rutas', 'rutas_section_id');
        add_settings_field('ranking_page_id',        'Página del Ranking',          [$this, 'ranking_page_callback'],        'futbolin-api-settings-rutas', 'rutas_section_id');

        // Cálculos
        // Se elimina la sección y campos de "Tipos de competición (por ID)"

        add_settings_section('calculos_section_id', 'Acciones de Datos', null, 'futbolin-api-settings-calculos');
    // Eliminado: Sincronizar torneos (local)
    add_settings_field('clear_all_caches', 'Caché (global)', [$this, 'clear_caches_button_callback'], 'futbolin-api-settings-calculos', 'calculos_section_id');
    // Eliminado: Campeones de España (se obtiene en vivo)
    // Eliminado: Cálculo de Temporadas
    // Eliminado: Estadísticas Globales (bloque retirado)
    add_settings_field('calculate_hall_of_fame',   'Cálculo del Hall of Fame',    [$this, 'calculate_hall_of_fame_callback'],       'futbolin-api-settings-calculos', 'calculos_section_id');
    // Eliminado: Cálculo de Finales (deshabilitado)
    }

    /** Callback campo: Página de Perfil de Jugador (evita fatal si faltaba el método) */
    public function player_profile_page_callback() {
        $opts   = get_option('mi_plugin_futbolin_options', []);
        $current= isset($opts['player_profile_page_id']) ? intval($opts['player_profile_page_id']) : 0;
        $pages  = get_pages(['sort_column'=>'post_title']);
        echo '<div class="futb-rutas-block">';
        echo '<select id="futb-player-profile-select" name="mi_plugin_futbolin_options[player_profile_page_id]" style="min-width:260px">';
        echo '<option value="0">'.esc_html__('— Seleccionar —','futbolin').'</option>';
        foreach($pages as $p){ $sel=selected($current,$p->ID,false); echo '<option value="'.esc_attr($p->ID).'" '.$sel.'>'.esc_html($p->post_title).'</option>'; }
        echo '</select> ';
        echo '<button type="button" class="button" id="futb-open-player-page" disabled>'.esc_html__('Abrir seleccionada','futbolin').'</button> ';
        echo '<button type="button" class="button" id="futb-insert-player-shortcode" disabled>'.esc_html__('Insertar shortcode en seleccionada','futbolin').'</button> ';
        echo '<button type="button" class="button button-secondary" id="futb-delete-player-page" disabled>'.esc_html__('Borrar seleccionada','futbolin').'</button>';
        echo '<p class="description" style="margin-top:6px">'.esc_html__('Selecciona una página existente con el shortcode [futbolin_jugador] o crea una nueva aquí abajo.','futbolin').'</p>';
        // Crear nueva
        echo '<fieldset style="margin:12px 0 0;padding:12px 14px;border:1px solid #ccd0d4;border-radius:6px;background:#f8f9fa">';
        echo '<legend style="font-weight:600">'.esc_html__('Crear nueva página para perfil de jugador','futbolin').'</legend>';
        echo '<label style="display:block;margin:4px 0">'.esc_html__('Título de la página','futbolin').'<br><input type="text" id="futb-new-player-title" style="width:100%;max-width:420px" placeholder="Perfil de Jugador" /></label>';
        echo '<div style="margin:6px 0">'.esc_html__('Visibilidad:','futbolin').'<br>'
            .'<label style="margin-right:12px"><input type="radio" name="futb-player-vis" value="publish" checked> '.esc_html__('Publicada','futbolin').'</label>'
            .'<label style="margin-right:12px"><input type="radio" name="futb-player-vis" value="private"> '.esc_html__('Privada','futbolin').'</label>'
            .'<label><input type="radio" name="futb-player-vis" value="password"> '.esc_html__('Protegida con contraseña','futbolin').'</label> '
            .'<input type="password" id="futb-player-pass" style="display:none;margin-left:8px" placeholder="'.esc_attr__('Contraseña','futbolin').'" />'
            .'</div>';
        echo '<button type="button" class="button button-primary" id="futb-create-player-page">'.esc_html__('Crear página','futbolin').'</button> ';
        echo '<button type="button" class="button" id="futb-open-player-page-secondary" disabled>'.esc_html__('Abrir','futbolin').'</button>';
        echo '<div id="futb-player-page-msg" style="margin-top:8px;font-size:12px"></div>';
        echo '</fieldset>';
        echo '</div>';
    }

    /** Callback campo: Página del Ranking (faltaba y causaba fatal) */
    public function ranking_page_callback() {
        $opts   = get_option('mi_plugin_futbolin_options', []);
        $current= isset($opts['ranking_page_id']) ? intval($opts['ranking_page_id']) : 0;
        $pages  = get_pages(['sort_column'=>'post_title']);
        echo '<div class="futb-rutas-block">';
        echo '<select id="futb-ranking-select" name="mi_plugin_futbolin_options[ranking_page_id]" style="min-width:260px">';
        echo '<option value="0">'.esc_html__('— Seleccionar —','futbolin').'</option>';
        foreach($pages as $p){ $sel=selected($current,$p->ID,false); echo '<option value="'.esc_attr($p->ID).'" '.$sel.'>'.esc_html($p->post_title).'</option>'; }
        echo '</select> ';
        echo '<button type="button" class="button" id="futb-open-ranking-page" disabled>'.esc_html__('Abrir seleccionada','futbolin').'</button> ';
        echo '<button type="button" class="button" id="futb-insert-ranking-shortcode" disabled>'.esc_html__('Insertar shortcode en seleccionada','futbolin').'</button> ';
        echo '<button type="button" class="button button-secondary" id="futb-delete-ranking-page" disabled>'.esc_html__('Borrar seleccionada','futbolin').'</button>';
        echo '<p class="description" style="margin-top:6px">'.esc_html__('Selecciona una página existente con el shortcode [futbolin_ranking] o crea una nueva aquí abajo.','futbolin').'</p>';
        echo '<fieldset style="margin:12px 0 0;padding:12px 14px;border:1px solid #ccd0d4;border-radius:6px;background:#f8f9fa">';
        echo '<legend style="font-weight:600">'.esc_html__('Crear nueva página de ranking','futbolin').'</legend>';
        echo '<label style="display:block;margin:4px 0">'.esc_html__('Título de la página','futbolin').'<br><input type="text" id="futb-new-ranking-title" style="width:100%;max-width:420px" placeholder="Ranking" /></label>';
        echo '<div style="margin:6px 0">'.esc_html__('Visibilidad:','futbolin').'<br>'
            .'<label style="margin-right:12px"><input type="radio" name="futb-ranking-vis" value="publish" checked> '.esc_html__('Publicada','futbolin').'</label>'
            .'<label style="margin-right:12px"><input type="radio" name="futb-ranking-vis" value="private"> '.esc_html__('Privada','futbolin').'</label>'
            .'<label><input type="radio" name="futb-ranking-vis" value="password"> '.esc_html__('Protegida con contraseña','futbolin').'</label> '
            .'<input type="password" id="futb-ranking-pass" style="display:none;margin-left:8px" placeholder="'.esc_attr__('Contraseña','futbolin').'" />'
            .'</div>';
        echo '<button type="button" class="button button-primary" id="futb-create-ranking-page">'.esc_html__('Crear página','futbolin').'</button> ';
        echo '<button type="button" class="button" id="futb-open-ranking-page-secondary" disabled>'.esc_html__('Abrir','futbolin').'</button>';
        echo '<div id="futb-ranking-page-msg" style="margin-top:8px;font-size:12px"></div>';
        echo '</fieldset>';
        echo '</div>';
                // Script unificado para ambos bloques (solo se imprime una vez) - versión segura con HEREDOC
                if (!$this->printed_pages_manager_js) {
                        $this->printed_pages_manager_js = true;
                        $ajax  = esc_js(admin_url('admin-ajax.php'));
                        $nonce = esc_js(wp_create_nonce('futbolin_admin_nonce'));
                        $script = <<<EOT
<script>
(function(){
    const ajaxUrl = '{$ajax}';
    const nonce   = '{$nonce}';

    function qs(s){return document.querySelector(s);} 
    function getCheckedVal(group){const el=document.querySelector("input[name='"+group+"']:checked");return el?el.value:'';}

    function bindBlock(cfg){
        const sel = document.getElementById(cfg.select);
        if(!sel) return;
        sel.addEventListener('change',()=>{
            const has = !!parseInt(sel.value||'0',10);
            [cfg.btnOpen,cfg.btnInsert,cfg.btnDelete,cfg.btnOpen2].forEach(id=>{const b=document.getElementById(id);if(b)b.disabled=!has;});
        });
        sel.dispatchEvent(new Event('change'));
        document.querySelectorAll('input[name='+cfg.visName+']').forEach(r=>{
            r.addEventListener('change',()=>{
                const pass=document.getElementById(cfg.passId); if(!pass)return;
                pass.style.display=(r.value==='password'&&r.checked)?'inline-block':'none';
            });
        });
        const createBtn=document.getElementById(cfg.btnCreate);
        if(createBtn){
            createBtn.addEventListener('click',()=>{
                const titleEl=document.getElementById(cfg.titleId);
                if(!titleEl||!titleEl.value.trim()){alert('Título requerido');return;}
                const vis = getCheckedVal(cfg.visName);
                const passInput=document.getElementById(cfg.passId);
                const pass=(vis==='password'&&passInput)?passInput.value:'';
                const msgEl=document.getElementById(cfg.msgId);
                msgEl.innerHTML='Creando...';
                const fd=new FormData();
                fd.append('action','futbolin_create_plugin_page');
                fd.append('admin_nonce',nonce);
                fd.append('title',titleEl.value.trim());
                fd.append('visibility',vis);
                fd.append('password',pass);
                fd.append('shortcode',cfg.shortcode);
                fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(r=>r.json()).then(r=>{
                    if(!r.success){
                        msgEl.innerHTML='<span style="color:#c00">'+(r.data&&r.data.message||'Error')+'</span>';
                        return;
                    }
                        msgEl.innerHTML='<span style="color:#065f46">Página creada ID '+r.data.id+'</span>';
                        const opt=document.createElement('option');
                        opt.value=r.data.id; opt.textContent=titleEl.value.trim();
                        sel.appendChild(opt); sel.value=r.data.id; sel.dispatchEvent(new Event('change'));
                }).catch(()=>{msgEl.innerHTML='<span style="color:#c00">Error de red</span>';});
            });
        }
    }

    const actions=[
        {select:'futb-player-profile-select',btnOpen:'futb-open-player-page',btnInsert:'futb-insert-player-shortcode',btnDelete:'futb-delete-player-page',btnOpen2:'futb-open-player-page-secondary',btnCreate:'futb-create-player-page',titleId:'futb-new-player-title',visName:'futb-player-vis',passId:'futb-player-pass',msgId:'futb-player-page-msg',shortcode:'[futbolin_jugador]'},
        {select:'futb-ranking-select',btnOpen:'futb-open-ranking-page',btnInsert:'futb-insert-ranking-shortcode',btnDelete:'futb-delete-ranking-page',btnOpen2:'futb-open-ranking-page-secondary',btnCreate:'futb-create-ranking-page',titleId:'futb-new-ranking-title',visName:'futb-ranking-vis',passId:'futb-ranking-pass',msgId:'futb-ranking-page-msg',shortcode:'[futbolin_ranking]'}
    ];
    actions.forEach(bindBlock);

    function post(action, data){
        const fd=new FormData();
        fd.append('action',action);
        fd.append('admin_nonce',nonce);
        Object.keys(data||{}).forEach(k=>fd.append(k,data[k]));
        return fetch(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd}).then(r=>r.json());
    }

    function attachOpen(btnId, selectId){
        const b=qs('#'+btnId); const s=document.getElementById(selectId); if(!b||!s)return;
        b.addEventListener('click',()=>{const id=parseInt(s.value||'0',10); if(!id)return; post('futbolin_get_permalink',{page_id:id}).then(r=>{ if(r.success&&r.data&&r.data.link){ window.open(r.data.link,'_blank'); } });});
    }
    function attachInsert(btnId, selectId, shortcode){
        const b=qs('#'+btnId); const s=document.getElementById(selectId); if(!b||!s)return;
        b.addEventListener('click',()=>{const id=parseInt(s.value||'0',10); if(!id)return; if(!confirm('Insertar shortcode en la página seleccionada?'))return; post('futbolin_insert_shortcode',{page_id:id,shortcode:shortcode}).then(r=>{alert(r.success?(r.data&&r.data.message)||'Insertado':'Error insertando');});});
    }
    function attachDelete(btnId, selectId, msgId){
        const b=qs('#'+btnId); const s=document.getElementById(selectId); const msg=document.getElementById(msgId); if(!b||!s)return;
        b.addEventListener('click',()=>{const id=parseInt(s.value||'0',10); if(!id)return; if(!confirm('¿Enviar a la papelera la página seleccionada?'))return; post('futbolin_delete_page',{page_id:id}).then(r=>{ if(r.success){ msg.innerHTML='<span style="color:#aa0000">Página enviada a la papelera.</span>'; const opt=s.querySelector('option[value="'+id+'"]'); if(opt){ opt.remove(); s.value='0'; s.dispatchEvent(new Event('change')); } } else { alert('Error borrando'); } });});
    }

    attachOpen('futb-open-player-page','futb-player-profile-select');
    attachOpen('futb-open-player-page-secondary','futb-player-profile-select');
    attachOpen('futb-open-ranking-page','futb-ranking-select');
    attachOpen('futb-open-ranking-page-secondary','futb-ranking-select');
    attachInsert('futb-insert-player-shortcode','futb-player-profile-select','[futbolin_jugador]');
    attachInsert('futb-insert-ranking-shortcode','futb-ranking-select','[futbolin_ranking]');
    attachDelete('futb-delete-player-page','futb-player-profile-select','futb-player-page-msg');
    attachDelete('futb-delete-ranking-page','futb-ranking-select','futb-ranking-page-msg');
})();
</script>
EOT;
                        echo $script; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado arriba
                }
    }

    // --- SANITIZACIÓN DE OPCIONES ---
    public function sanitize_options($input) {
        $old = get_option('mi_plugin_futbolin_options', []);
        if (!is_array($old)) $old = [];

        if (!is_array($input)) return $old;

        $ctx = isset($input['__context']) ? sanitize_key($input['__context']) : '';
        unset($input['__context']);


        if ($ctx === 'configuracion') {
            // Merge no destructivo: partir de $old y tocar solo claves de esta pestaña
            $out = $old;
            $checkboxes = [
                'show_champions',
                'show_tournaments',
                'show_hall_of_fame',
                'show_finals_reports',
                'enable_player_profile',
                'show_player_summary',
                'show_player_stats',
                'show_player_history',
                'show_player_glicko',
                'show_global_stats',
                'maintenance_mode',
                'show_player_hitos',
                'show_player_torneos',
            ];
            // Merge nuevos toggles de Estadísticas
            $checkboxes = array_merge($checkboxes, ['enable_fefm_no1_club','enable_club_500_played','enable_club_100_winners','enable_top_rivalries']);
            // Añadir toggles de Ranking anual (por modalidad)
            $checkboxes = array_merge($checkboxes, ['enable_annual_doubles','enable_annual_individual']);

            // Añadir toggles dinámicos de Rankgen
            $sets_dyn = get_option('futb_rankgen_sets', []);
            if (is_array($sets_dyn)){
                foreach ($sets_dyn as $slug => $_cfg){
                    $checkboxes[] = 'enable_rankgen__' . sanitize_key($slug);
                }
            }

            foreach ($checkboxes as $k) {
                $out[$k] = (isset($input[$k]) && $input[$k] === 'on') ? 'on' : 'off';
            }

            if (isset($input['default_modalidad'])) {
                $out['default_modalidad'] = intval($input['default_modalidad']);
            }
            if (isset($input['default_modalidad_anual'])) {
                $out['default_modalidad_anual'] = intval($input['default_modalidad_anual']);
            }

            // ID por defecto para previsualización de perfil
            if (isset($input['default_profile_preview_id'])) {
                $out['default_profile_preview_id'] = max(0, intval($input['default_profile_preview_id']));
            }

            if (isset($input['ranking_modalities'])) {
                $out['ranking_modalities'] = array_map('intval', (array)$input['ranking_modalities']);
            } else {
                $out['ranking_modalities'] = [];
            }

        } elseif ($ctx === 'rutas') {
            // Merge no destructivo para no resetear otros ajustes
            $out = $old;
            if (isset($input['player_profile_page_id'])) {
                $out['player_profile_page_id'] = intval($input['player_profile_page_id']);
            }
            if (isset($input['ranking_page_id'])) {
                $out['ranking_page_id'] = intval($input['ranking_page_id']);
            }

    } elseif ($ctx === 'avanzado') {
            // Merge no destructivo para no perder campos de otras pestañas
            $out = $old;
            if (isset($input['http_timeout'])) {
                $t = (int)$input['http_timeout'];
                if ($t < 5 || $t > 120) { $t = 30; }
                $out['http_timeout'] = $t;
            }
            if (isset($input['admin_max_width'])) {
                $w = (int)$input['admin_max_width'];
                if ($w < 960) { $w = 960; }
                if ($w > 3840) { $w = 3840; }
                $out['admin_max_width'] = $w;
                if (function_exists('add_settings_error')) {
                    add_settings_error('mi_plugin_futbolin_option_group', 'rf_admin_maxw_saved', sprintf(__('Ancho del panel actualizado a %d px','futbolin'), (int)$w), 'updated');
                }
            }
            if (isset($input['http_retries'])) {
                $r = (int)$input['http_retries'];
                if ($r < 0 || $r > 5) { $r = 3; }
                $out['http_retries'] = $r;
            }

        } elseif ($ctx === 'logging') {
            // Merge no destructivo: parte de $old y solo escribe claves de logging
            $out = $old;
            // Habilitar/Deshabilitar logging (siempre activo por defecto)
            $out['rf_logging_enabled'] = isset($input['rf_logging_enabled']) ? 1 : 0;
            // Rediseño: sin selector de nivel ni tamaño (fijamos 19MB por archivo con rotación comprimida)

        } else {
            // Fallback: permitir toggles puntuales enviados sin __context (ej. rf_cache_enabled)
            if (isset($input['rf_cache_enabled'])) {
                // Guardamos en opción independiente para lectura directa por el helper
                update_option('rf_cache_enabled', (int)!!$input['rf_cache_enabled'], false);
                // No modificamos el array principal en este caso
                return $old;
            }
            // Contexto desconocido: no tocar nada
            return $old;
        }

        return $out;
    }

    // --- DESCRIPCIONES DE SECCIONES (CONFIGURACIÓN) ---
    public function print_configuracion_info() {
        echo '<p>Elige la modalidad por defecto y qué modalidades aparecen en el menú del ranking ELO. Estos valores se usan como predeterminados si no se especifican en el shortcode.</p>';
    }
    public function print_visual_ranking_info() {
        echo '<p>Activa o desactiva los módulos del menú de Estadísticas.</p>';
    }
    public function print_visual_player_info() {
        echo '<p>Activa o desactiva secciones del <strong>perfil de jugador</strong>. Si deshabilitas el maestro, el perfil no mostrará módulos.</p>';
    }
    public function print_maintenance_info() {
        echo '<p>Al activar el modo mantenimiento, el front muestra solo la cabecera y un mensaje; se bloquea la navegación del plugin.</p>';
    }

    // --- SANITIZACIÓN ranking_api_config ---
    public function sanitize_api_config($input) {
        $out = [];
        if (!is_array($input)) return $out;
        if (isset($input['base_url'])) {
            $bu = trim((string)$input['base_url']);
            if ($bu !== '') {
                if (strpos($bu, 'http://') !== 0 && strpos($bu, 'https://') !== 0) { $bu = 'https://' . $bu; }
                $bu = rtrim($bu, "/\\");
            }
            $out['base_url'] = esc_url_raw($bu);
        }
        if (isset($input['username'])) { $out['username'] = sanitize_text_field((string)$input['username']); }
        if (isset($input['password'])) { $out['password'] = wp_kses_post((string)$input['password']); }
        return $out;
    }

    /**
     * Helper genérico para pintar un checkbox de una opción booleana estándar.
     * Marca el checkbox si el valor almacenado es 'on'. No fuerza valor por defecto.
     * @param string $option_key Clave dentro del array mi_plugin_futbolin_options
     * @param string $label Etiqueta (permitido HTML básico ya saneado por caller si lo usa)
     */
    private function render_checkbox_callback($option_key, $label){
        $options = get_option('mi_plugin_futbolin_options', []);
        $checked = isset($options[$option_key]) && $options[$option_key] === 'on';
        echo '<label style="display:inline-flex;align-items:center;gap:6px;">'
            .'<input type="checkbox" name="mi_plugin_futbolin_options['.esc_attr($option_key).']" value="on" '.checked($checked, true, false).' />'
            .'<span>'.wp_kses_post($label).'</span>'
            .'</label>';
    }

    /**
     * Variante donde el estado por defecto (si la opción aún NO existe) es considerado "on".
     * Se usa para elementos que queremos aparezcan activos tras la instalación sin requerir guardado.
     * Si el usuario guarda la página y desmarca, se persistirá 'off'.
     * @param string $option_key
     * @param string $label
     */
    private function render_checkbox_callback_default_on($option_key, $label){
        $options = get_option('mi_plugin_futbolin_options', []);
        if (!array_key_exists($option_key, (array)$options)) {
            // Valor implícito por defecto = on (visual); no guardamos hasta submit para permitir override.
            $checked = true;
        } else {
            $checked = ($options[$option_key] === 'on');
        }
        echo '<label style="display:inline-flex;align-items:center;gap:6px;">'
            .'<input type="checkbox" name="mi_plugin_futbolin_options['.esc_attr($option_key).']" value="on" '.checked($checked, true, false).' />'
            .'<span>'.wp_kses_post($label).'</span>'
            .'</label>';
    }

    /** UI: Toggle principal de perfil de jugador */
    public function enable_player_profile_callback(){
        $options = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($options['enable_player_profile']) && $options['enable_player_profile']==='on';
        $checked = $enabled ? 'checked' : '';
        echo '<div class="futbolin-profile-wrap" style="max-width:720px">'
            .'<div class="futbolin-profile-card" style="border:2px solid '.($enabled?'#198754':'#999').';background:'.($enabled?'#e6f4ea':'#f6f7f7').';padding:14px 16px;border-radius:12px;display:flex;align-items:center;gap:16px;box-sizing:border-box">'
                .'<div class="futbolin-profile-icon" style="font-size:26px;line-height:1;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:'.($enabled?'#d1f0d8':'#ddd').';flex:0 0 36px">👤</div>'
                .'<div class="futbolin-profile-body" style="flex:1 1 auto;min-width:0">'
                    .'<p class="futbolin-profile-status" style="font-weight:700;margin:0 0 4px;color:'.($enabled?'#14532d':'#555').';">Estado actual: '.($enabled?'PERFIL DE JUGADOR HABILITADO':'PERFIL DE JUGADOR DESHABILITADO').'</p>'
                    .'<p class="futbolin-profile-desc" style="margin:0;opacity:.9">Este interruptor controla el <strong>perfil de jugador</strong> en el front.<br>Si lo deshabilitas:<br>• No se mostrará el buscador de jugadores en la barra lateral.<br>• Cualquier intento de abrir un perfil mostrará un aviso de “sección deshabilitada temporalmente”.</p>'
                .'</div>'
                .'<div class="futbolin-profile-actions" style="flex:0 0 auto;display:flex;align-items:center;gap:12px">'
                    .'<span class="profile-badge" style="font-size:11px;font-weight:700;padding:4px 8px;border-radius:999px;background:'.($enabled?'#c6f6d5':'#eee').';color:'.($enabled?'#14532d':'#555').';border:1px solid '.($enabled?'#95e3a4':'#ccc').';text-transform:uppercase;letter-spacing:.3px">'.($enabled?'Activo':'Inactivo').'</span>'
                    .'<label class="futbolin-switch" style="position:relative;display:inline-block;width:74px;height:36px" title="Activar/Desactivar perfil de jugador">'
                        .'<input id="futbolin-player-profile-toggle" type="checkbox" name="mi_plugin_futbolin_options[enable_player_profile]" value="on" '.$checked.' style="opacity:0;width:0;height:0" />'
                        .'<span class="futbolin-slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.3s;border-radius:36px;box-shadow:inset 0 0 0 2px rgba(0,0,0,.06)"></span>'
                    .'</label>'
                .'</div>'
            .'</div>'
        .'</div>';
        echo '<script>(function(){var t=document.getElementById("futbolin-player-profile-toggle");if(!t)return;var s=t.nextElementSibling;if(!s)return;function upd(){if(t.checked){s.style.background="#198754";s.innerHTML="";}else{s.style.background="#ccc";s.innerHTML="";}}t.addEventListener("change",upd);upd();})();</script>';
    }
    
    public function show_player_summary_callback()  { $this->render_checkbox_callback('show_player_summary',  __('Mostrar pestaña "General (resumen)"','futbolin')); }
    public function show_player_stats_callback()    { $this->render_checkbox_callback('show_player_stats',    __('Mostrar pestaña "Estadísticas"','futbolin')); }
    public function show_player_history_callback()  { $this->render_checkbox_callback('show_player_history',  __('Mostrar pestaña "Historial"','futbolin')); }
public function show_player_glicko_callback()   { $this->render_checkbox_callback('show_player_glicko',   __('Mostrar pestaña "Clasificación (Glicko)"','futbolin')); }

    // --- CAMPOS DE RANKING: MODALIDADES (checkbox list) ---
    public function ranking_modalities_callback() {
        $options = get_option('mi_plugin_futbolin_options', []);
        $active_modalities = isset($options['ranking_modalities']) ? (array) $options['ranking_modalities'] : [];
        $modalities = [
            '2'=>'Dobles', '1'=>'Individual','7'=>'Mujeres Dobles','8'=>'Mujeres Individual',
            '10'=>'Mixto','3'=>'Senior Dobles','4'=>'Senior Individual','5'=>'Junior Dobles','6'=>'Junior Individual'
        ];
        echo '<div class="checkbox-list">';
        foreach ($modalities as $id => $name) {
            $checked = in_array($id, $active_modalities, true) ? 'checked' : '';
            echo '<label class="checkbox-item"><input type="checkbox" name="mi_plugin_futbolin_options[ranking_modalities][]" value="' . esc_attr($id) . '" ' . $checked . '>' . esc_html($name) . '</label>';
        }
        echo '</div>';
    }

    // --- CAMPO: ID por defecto para previsualización de perfil ---
    public function default_profile_preview_id_callback(){
        $options = get_option('mi_plugin_futbolin_options', []);
        $val = isset($options['default_profile_preview_id']) ? (int)$options['default_profile_preview_id'] : 0;
        echo '<input type="number" min="0" style="width:120px" name="mi_plugin_futbolin_options[default_profile_preview_id]" value="'.esc_attr($val).'" />';
        echo '<p class="description">ID de jugador usado como ejemplo al previsualizar diseño de perfil si no se especifica uno.</p>';
    }

    // --- CAMPO: MODALIDAD POR DEFECTO (select) ---
    public function default_modalidad_callback() {
        $api_client = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        $options = get_option('mi_plugin_futbolin_options', []);
        $default_modalidad = isset($options['default_modalidad']) ? (int)$options['default_modalidad'] : 2;

        $modalidades = $api_client && method_exists($api_client, 'get_modalidades')
            ? $api_client->get_modalidades()
            : [
                (object)['modalidadId' => 2, 'descripcion' => 'Dobles'],
                (object)['modalidadId' => 1, 'descripcion' => 'Individual']
            ];

        if (empty($modalidades)) {
            echo '<p>No se pudieron cargar las modalidades desde la API.</p>';
            return;
        }

        echo '<select name="mi_plugin_futbolin_options[default_modalidad]">';
        foreach ($modalidades as $modalidad) {
            $id   = isset($modalidad->modalidadId) ? (int)$modalidad->modalidadId : null;
            $desc = isset($modalidad->descripcion) ? (string)$modalidad->descripcion : ('Modalidad '.$id);
            if ($id === null) continue;
            echo '<option value="' . esc_attr($id) . '" ' . selected($default_modalidad, $id, false) . '>' . esc_html($desc) . '</option>';
        }
        echo '</select>';
    }

    // --- CAMPO: MODALIDAD POR DEFECTO (ANUAL) ---
    public function default_modalidad_anual_callback() {
        $api_client = class_exists('Futbolin_API_Client') ? new Futbolin_API_Client() : null;
        $options = get_option('mi_plugin_futbolin_options', []);
        $default_modalidad_anual = isset($options['default_modalidad_anual']) ? (int)$options['default_modalidad_anual'] : 2;

        // Para anual, hoy solo tienen sentido Dobles (2) e Individual (1)
        $modalidades = [
            (object)['modalidadId' => 2, 'descripcion' => 'Dobles'],
            (object)['modalidadId' => 1, 'descripcion' => 'Individual']
        ];

        echo '<select name="mi_plugin_futbolin_options[default_modalidad_anual]">';
        foreach ($modalidades as $modalidad) {
            $id   = isset($modalidad->modalidadId) ? (int)$modalidad->modalidadId : null;
            $desc = isset($modalidad->descripcion) ? (string)$modalidad->descripcion : ('Modalidad '.$id);
            if ($id === null) continue;
            echo '<option value="' . esc_attr($id) . '" ' . selected($default_modalidad_anual, $id, false) . '>' . esc_html($desc) . '</option>';
        }
        echo '</select>';
    }

    // --- MODO MANTENIMIENTO (UI) ---
    public function maintenance_mode_callback() {
        $options = get_option('mi_plugin_futbolin_options', []);
        $enabled = isset($options['maintenance_mode']) && $options['maintenance_mode'] === 'on';
        $checked = $enabled ? 'checked' : '';
        ?>
        <style>
            .form-table td .futbolin-maint-wrap { max-width: 820px; }
            .futbolin-maint-card {
                border: 2px solid <?php echo $enabled ? '#b30000' : '#2271b1'; ?>;
                background: <?php echo $enabled ? '#ffe9e9' : '#eef6ff'; ?>;
                padding: 14px 16px; border-radius: 12px;
                display: flex; align-items: center; gap: 16px; box-sizing: border-box;
            }
            .futbolin-maint-icon {
                font-size: 28px; line-height: 1; width: 36px; height: 36px;
                display: flex; align-items: center; justify-content: center;
                border-radius: 50%;
                background: <?php echo $enabled ? '#ffcccc' : '#d6ebff'; ?>;
                flex: 0 0 36px;
            }
            .futbolin-maint-body { flex: 1 1 auto; min-width: 0; }
            .futbolin-maint-status { font-weight: 700; margin: 0 0 4px; color: <?php echo $enabled ? '#8a0000' : '#0a4b78'; ?>; }
            .futbolin-maint-desc { margin: 0; opacity: .9; }
            .futbolin-maint-actions { flex: 0 0 auto; display: flex; align-items: center; gap: 12px; }
            .futbolin-switch { position: relative; display: inline-block; width: 70px; height: 36px; }
            .futbolin-switch input { display: none; }
            .futbolin-slider {
                position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
                background-color: #ccc; transition: .3s; border-radius: 36px;
                box-shadow: inset 0 0 0 2px rgba(0,0,0,.06);
            }
            .futbolin-slider:before {
                position: absolute; content: ""; height: 28px; width: 28px; left: 4px; bottom: 4px;
                background-color: #fff; transition: .3s; border-radius: 50%;
                box-shadow: 0 1px 3px rgba(0,0,0,.3);
            }
            .futbolin-switch input:checked + .futbolin-slider { background-color: #d63638; }
            .futbolin-switch input:checked + .futbolin-slider:before { transform: translateX(34px); }
            .maint-badge {
                font-size: 11px; font-weight: 700; padding: 4px 8px; border-radius: 999px;
                background: <?php echo $enabled ? '#ffdbdb' : '#dff0ff'; ?>;
                color: <?php echo $enabled ? '#7b0000' : '#0a4b78'; ?>;
                border: 1px solid <?php echo $enabled ? '#f5bcbc' : '#b9ddff'; ?>;
                text-transform: uppercase; letter-spacing: .3px;
            }
            @media (max-width: 782px) {
                .futbolin-maint-card { flex-direction: column; align-items: stretch; }
                .futbolin-maint-actions { justify-content: flex-end; }
            }
        </style>
        <div class="futbolin-maint-wrap">
            <div class="futbolin-maint-card">
                <div class="futbolin-maint-icon">🛠️</div>
                <div class="futbolin-maint-body">
                    <p class="futbolin-maint-status">
                        Estado actual: <?php echo $enabled ? 'MODO MANTENIMIENTO ACTIVADO' : 'MODO MANTENIMIENTO DESACTIVADO'; ?>
                    </p>
                    <p class="futbolin-maint-desc">
                        Al activar este modo, en el <strong>front</strong> solo se mostrará la cabecera del plugin y el mensaje:
                        <em>“Estamos en mantenimiento, volvemos lo antes posible.”</em>
                        Se bloqueará la navegación del plugin y sus enlaces (shortcodes/routers).
                    </p>
                </div>
                <div class="futbolin-maint-actions">
                    <span class="maint-badge"><?php echo $enabled ? 'Activo' : 'Inactivo'; ?></span>
                    <label class="futbolin-switch" title="Activar/Desactivar modo mantenimiento">
                        <input id="futbolin-maintenance-toggle" type="checkbox"
                               name="mi_plugin_futbolin_options[maintenance_mode]"
                               value="on" <?php echo $checked; ?> />
                        <span class="futbolin-slider"></span>
                    </label>
                </div>
            </div>
        </div>
        <script>
        (function(){
            const toggle = document.getElementById('futbolin-maintenance-toggle');
            if (!toggle) return;
            let last = toggle.checked;
            toggle.addEventListener('change', function(e){
                const goingOn  = e.target.checked && !last;
                const goingOff = !e.target.checked && last;
                let msg = '';
                if (goingOn) {
                    msg = '¿Seguro que deseas ACTIVAR el Modo mantenimiento?\\n\\n' +
                          '• Ocultará todos los datos del plugin en el front.\\n' +
                          '• Solo se verá la cabecera y el mensaje de mantenimiento.\\n' +
                          '• Se bloqueará la navegación de las vistas del plugin.';
                } else if (goingOff) {
                    msg = '¿Desactivar el Modo mantenimiento y volver a mostrar los datos del plugin?';
                }
                if (msg && !confirm(msg)) {
                    e.target.checked = !e.target.checked;
                    return;
                }
                last = e.target.checked;
            });
        })();
        </script>
        <?php
    }

    // --- PÁGINA INICIO ---
    private function render_inicio_page() {
        ?>
        <div class="futbolin-card">
          <div class="logo-container">
            <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/fefm.png' ); ?>" alt="Logo FEFM" class="fefm-logo">
            <h2 class="welcome-title">Bienvenido al Panel de Administración de Futbolín API</h2>
          </div>
          <p>Este plugin actúa como un puente vital, conectando la API de la Federación Española de Futbolín con tu sitio web de WordPress. Su propósito es transformar datos complejos en una experiencia interactiva y accesible, mostrando rankings de jugadores, perfiles detallados y estadísticas de torneos de forma dinámica y atractiva.</p>
          <h3>Características Principales</h3>
          <ul>
            <li><strong>Ranking Dinámico:</strong> Muestra el ranking de jugadores actualizado por modalidad, con opciones de filtrado y paginación.</li>
            <li><strong>Perfiles de Jugador:</strong> Crea páginas de perfil individuales para cada jugador, con estadísticas, historial de partidas y logros.</li>
            <li><strong>Búsqueda Avanzada:</strong> Permite a los usuarios buscar jugadores y comparar estadísticas en duelos 'Head-to-Head'.</li>
            <li><strong>Optimización de Rendimiento:</strong> Almacena datos estáticos como campeones y estadísticas globales para reducir las llamadas a la API y acelerar la carga de la web.</li>
          </ul>
        </div>
        <div class="futbolin-card developer-info-card">
          <div class="developer-info-header">
            <h3>Sobre el Desarrollador</h3>
          </div>
          <p>La integración, visualización y experiencia de usuario de este ranking ha sido concebida y desarrollada por <strong>Héctor Núñez Sáez</strong>. A través de este plugin a medida, se ha logrado transformar los datos en bruto de la API en una herramienta viva, interactiva y accesible para todos los jugadores de la federación.</p>
          <div class="contact-info">
            <p class="contact-text">Si admiras el trabajo realizado y estás interesado en implementar un sistema similar para tu liga, federación o club, o si deseas obtener más información sobre el proyecto, puedes ponerte en contacto conmigo a través del siguiente correo electrónico:</p>
            <p class="contact-email">hector@fefm.es</p>
          </div>
        </div>
        <?php
    }

        // --- PÁGINA DOCUMENTACIÓN ---
        private function render_documentacion_page() {
                ?>
                <div class="futbolin-card">
                    <div class="logo-container">
                        <img src="<?php echo esc_url( FUTBOLIN_API_URL . 'assets/img/fefm.png' ); ?>" alt="Logo FEFM" class="fefm-logo">
                        <h2 class="welcome-title">Documentación del Plugin</h2>
                    </div>
                    <p>Guía rápida para poner en marcha el plugin, buenas prácticas de uso (especialmente caché y rendimiento) y cómo interpretar los logs.</p>
                </div>

                <div class="futbolin-card">
                    <h2>1) Puesta en marcha tras instalar</h2>
                    <p>Pasos recomendados nada más activar el plugin para dejarlo funcionando.</p>
                      <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row">a. Conexión con la API</th>
                            <td>
                                • Ve a la pestaña <strong>Conexión</strong> e introduce <em>Base URL</em>, <em>Usuario</em> y <em>Contraseña</em>.<br/>
                                • Usa el botón <strong>Probar conexión</strong> para validar sin guardar.<br/>
                                • Después pulsa <strong>Guardar credenciales</strong> para que el plugin las use.
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">b. Crear las páginas del front</th>
                            <td>
                                • En la pestaña <strong>Páginas</strong> crea la página de <em>Perfil de Jugador</em> y la de <em>Ranking</em> con sus shortcodes.<br/>
                                • También puedes seleccionar páginas existentes y <em>Insertar shortcode en seleccionada</em>.
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">c. Vista previa de un jugador</th>
                            <td>
                                • Configura en <strong>Configuración</strong> el <em>ID por defecto de previsualización</em> si lo necesitas.<br/>
                                • En <strong>Páginas</strong> usa <em>Abrir seleccionada</em> y el plugin añadirá <code>?jugador_id=...</code> automáticamente.
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">d. Opciones recomendadas</th>
                            <td>
                                • Por defecto el plugin activa <strong>caché</strong> y <strong>logging</strong> para un mejor rendimiento y diagnóstico.<br/>
                                • Puedes revisar ambos en las pestañas <strong>Acciones de Datos</strong> y <strong>Log</strong>.
                            </td>
                        </tr>
                                    <tr>
                                        <th scope="row">e. Si hay problemas de visualización</th>
                                        <td>
                                            • Activa el <strong>Modo aislado (Shadow DOM)</strong> en la pestaña <em>Avanzado</em> para encapsular estilos si el tema o maquetador interfiere.<br/>
                                            • Si ya estaba activo, revisa la pestaña <em>Log</em> por si hay avisos de CSS o conflictos de scripts.
                                        </td>
                                    </tr>
                    </tbody></table>
                </div>

                <div class="futbolin-card">
                    <h2>2) Buenas prácticas y rendimiento</h2>
                    <p>Recomendaciones para obtener la mejor experiencia en producción.</p>
                    <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row">Caché del plugin</th>
                            <td>
                                • Mantén la <strong>caché habilitada</strong> en producción. El contador de la pestaña <em>Acciones de Datos</em> mostrará un aviso cuando esté desactivada.<br/>
                                • Usa la <em>versión de dataset</em> para invalidar cachés tras importar cambios mayores de datos.<br/>
                                • La <em>precarga</em> permite calentar perfiles concretos o los <em>TOP IDs</em> para mejorar la primera visita.
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">PurgeCSS y estilos</th>
                            <td>
                                • El plugin incluye <strong>PurgeCSS con safelist</strong> y un <em>kill‑switch</em> por si necesitas desactivarlo.<br/>
                                • El front usa <em>Shadow DOM</em> para aislar estilos del tema y garantizar consistencia.
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">SSR + hidratación por pestañas</th>
                            <td>
                                • La UI se renderiza <strong>SSR primero</strong> y se <em>hidrata</em> bajo demanda por pestaña para reducir JS inicial.<br/>
                                • Evita sobrecargar con plugins de terceros que inyecten JS en exceso en estas páginas.
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Buenas prácticas generales</th>
                            <td>
                                • Evita borrar y recrear páginas con shortcodes: usa el botón <em>Insertar shortcode</em> si necesitas reinsertarlo.<br/>
                                • En constructores, el título de página se <em>oculta automáticamente</em> para evitar duplicados visuales.
                            </td>
                        </tr>
                    </tbody></table>
                </div>

                <div class="futbolin-card">
                    <h2>3) Logs y diagnóstico</h2>
                    <p>Cómo leer el visor de logs del plugin y consejos de troubleshooting.</p>
                    <table class="form-table" role="presentation"><tbody>
                        <tr>
                            <th scope="row">Qué muestra el Log</th>
                            <td>
                                • Eventos clave del plugin: precarga, cache hits/misses, errores de API, avisos de configuración.<br/>
                                • Filtros por <em>nivel</em> (info/warn/error), <em>auto‑refresh</em>, descargar y limpiar archivo.
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Trucos rápidos</th>
                            <td>
                                • Si algo no se actualiza, sube la <strong>versión de dataset</strong> y limpia caché desde <em>Acciones de Datos</em>.<br/>
                                • Activa el <strong>auto‑refresh</strong> del visor mientras reproduces el problema en otra pestaña/ventana.<br/>
                                • Revisa que <strong>logging</strong> esté activo (por defecto lo habilitamos incluso en instalaciones existentes si no hay preferencia guardada).
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Dónde se guardan</th>
                            <td>
                                • Carpeta <code>wp-content/uploads/ranking-futbolin/logs/</code> con rotación automática.<br/>
                                • Se puede reflejar en <code>error_log</code> si el servidor lo requiere para soporte.
                            </td>
                        </tr>
                    </tbody></table>
                </div>
                <div class="futbolin-card">
                    <h2>Guía detallada de pestañas</h2>
                    <p style="margin-bottom:14px;">Explora toda la documentación integrada sin salir del panel. Usa el índice lateral para navegar. También puedes abrir la versión Markdown original o alternar su vista en bruto.</p>
                                        <?php
                      // Raíz real del plugin (subimos desde /includes/admin/ hasta /<plugin>/)
                      $plugin_root = dirname( dirname( __DIR__ ) );
                      $html_path   = $plugin_root . '/docs/admin-tabs-guia.html';
                      $plugin_slug = basename( $plugin_root );
                      $md_url      = plugins_url( $plugin_slug . '/docs/admin-tabs-guia.md' );

                      $exists = file_exists( $html_path );
                      if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                          error_log( '[ranking-futbolin][docs] Ruta guia calculada: ' . $html_path . ' exists=' . ( $exists ? '1' : '0' ) );
                      }
                      if ( $exists ) {
                          $html = @file_get_contents( $html_path );
                          if ( $html !== false ) {
                              // Insertamos data attribute con URL MD para que el JS lo lea en vez de construirla manualmente
                              $html = preg_replace(
                                  '/<section class=\"rf-admin-docs\"/i',
                                  '<section class="rf-admin-docs" data-md-url="' . esc_attr( $md_url ) . '"',
                                  $html,
                                  1
                              );
                              echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                          } else {
                              echo '<p>La guía HTML existe pero no pudo leerse (permisos). Ruta: <code>' . esc_html( $html_path ) . '</code></p>';
                          }
                      } else {
                          echo '<div class="notice notice-error" style="padding:12px 16px;">'
                               . '<p><strong>No se encontró la guía HTML.</strong></p>'
                               . '<p>Ruta buscada: <code>' . esc_html( $html_path ) . '</code></p>'
                               . '<p>Comprueba que el archivo esté en <code>/docs/</code> del plugin activo (<code>' . esc_html( $plugin_slug ) . '</code>).</p>'
                               . '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $md_url ) . '">Abrir Markdown</a></p>'
                               . '</div>';
                      }
                    ?>
                </div>
                <?php
        }

    // --- SUBPÁGINA: INFORMES DE FINALES (placeholder deshabilitado) ---
    public function finals_reports_page() {
        echo '<div class="wrap"><h1 class="futbolin-admin-header">Datos de Finales</h1>';
        echo '<p>Se ha desactivado la sección de Informes de Finales. Los campeones y estadísticas relevantes se leen en tiempo real desde la API.</p>';
        echo '</div>';
    }

    private function get_report_title($key) {
        $titles = [
            'open_individual_finals'     => 'Informe de Finales Ganadas Open Individual',
            'open_doubles_player_finals' => 'Informe de Finales Ganadas Open Dobles por Jugador',
            'open_doubles_pair_finals'   => 'Informe de Finales Ganadas Open Dobles por Pareja',
            'championships_open'         => 'Informe de Campeonatos Open Ganados',
            'championships_rookie'       => 'Informe de Campeonatos Rookie/Amater Ganados',
            'championships_resto'        => 'Informe de Campeonatos Resto Ganados',
        ];
        return $titles[$key] ?? 'Informe de Finales';
    }

    private function render_report_table($data, $report_key) {
        echo '<table class="wp-list-table widefat striped">';
        if (strpos((string)($report_key ?? ''), 'finals') !== false) {
            echo '<thead><tr><th>Jugador/Pareja</th><th>Finales Jugadas</th><th>Finales Ganadas</th><th>Finales Perdidas</th><th>% Victoria</th></tr></thead><tbody>';
            foreach ($data as $player_name => $stats) {
                echo '<tr>';
                echo '<td>' . esc_html($player_name) . '</td>';
                echo '<td>' . esc_html($stats['total'] ?? 0) . '</td>';
                echo '<td>' . esc_html($stats['wins'] ?? 0) . '</td>';
                echo '<td>' . esc_html($stats['losses'] ?? 0) . '</td>';
                echo '<td>' . esc_html(number_format((float)($stats['win_rate'] ?? 0), 2)) . '%</td>';
                echo '</tr>';
            }
            echo '</tbody>';
        } else {
            $first = reset($data);
            $is_stats = is_array($first) && array_key_exists('torneos_jugados', $first);
            if ($is_stats) {
                echo '<thead><tr><th>Jugador</th><th>Torn.</th><th>Camp.</th><th>F</th><th>G</th><th>P</th><th>Rest</th><th>%F</th><th>%C</th><th>Ganados</th></tr></thead><tbody>';
                foreach ($data as $player_name => $st) {
                    echo '<tr>';
                    echo '<td>' . esc_html($player_name) . '</td>';
                    echo '<td>' . esc_html($st['torneos_jugados'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['campeonatos_jugados'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['finales_jugadas'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['finales_ganadas'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['finales_perdidas'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['resto_posiciones'] ?? 0) . '</td>';
                    echo '<td>' . esc_html($st['porcentaje_finales_ganadas'] ?? 0) . '%</td>';
                    echo '<td>' . esc_html($st['porcentaje_campeonatos_ganados'] ?? 0) . '%</td>';
                    echo '<td>' . esc_html($st['campeonatos_ganados'] ?? 0) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
            } else {
                echo '<thead><tr><th>Jugador</th><th>Nº Campeonatos</th><th>Campeonatos</th></tr></thead><tbody>';
                foreach ($data as $player_name => $championships) {
                    echo '<tr>';
                    echo '<td>' . esc_html($player_name) . '</td>';
                    echo '<td>' . esc_html(is_array($championships) ? count($championships) : 0) . '</td>';
                    echo '<td>' . esc_html(is_array($championships) ? implode(', ', $championships) : '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
            }
        }
        echo '</table>';
    }

    // --- PÁGINAS DEL PLUGIN (Rutas) ---
    public function clear_caches_button_callback() {
        if ( ! class_exists('RF_Hitos_Cache_Manager') ) {
            echo '<p>No disponible (clase de caché no cargada).</p>';
            return;
        }
        $status = RF_Hitos_Cache_Manager::status();
        echo '<div id="rf-hitos-cache-embed" class="rf-hitos-cache-box" style="border:1px solid #ccd0d4;padding:16px;border-radius:6px;background:#fff;max-width:920px;">';
        echo '<h3 style="margin-top:0;">Cache de Datos – Ranking Futbolín</h3>';
        echo '<p style="margin:4px 0 12px;">Genera una caché persistente (temporadas, modalidades, campeones, rankings y perfiles) para que el sitio funcione aunque la API remota falle. Tres controles: activar, generar/actualizar y purgar.</p>';
        echo '<div class="rf-hitos-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">'
            .'<button id="rf-hitos-cache-start" class="button button-primary">Generar / Actualizar Cache</button>'
            .'<button id="rf-hitos-cache-purge" class="button">Purgar Cache</button>'
            .'<label style="margin-left:12px;font-weight:600;">'
                .'<input type="checkbox" id="rf-hitos-cache-enabled" value="1" '. ( RF_Hitos_Cache_Manager::is_enabled() ? 'checked' : '' ) .' /> Activar caché'
            .'</label>'
        .'</div>';
        echo '<div class="rf-hitos-progress" style="background:#e2e8f0;height:18px;border-radius:4px;overflow:hidden;margin:8px 0;">'
            .'<span id="rf-hitos-cache-progress" style="display:block;height:100%;background:#2563eb;width:0;transition:width .25s;"></span>'
        .'</div>';
    echo '<div id="rf-hitos-cache-meta" style="font-size:13px;color:#334155;"><em>Esperando acción...</em></div>';
        echo '<p id="rf-hitos-cache-players" style="font-size:12px;color:#334155;margin-top:4px;"></p>';
        // Panel de métricas detalladas (rellenado por JS principal)
        echo '<div id="rf-hitos-cache-stats" style="margin-top:12px;font-size:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:6px;align-items:stretch;"></div>';
                echo '<div id="rf-hitos-cache-log" style="font:12px/1.4 monospace;max-height:200px;overflow:auto;background:#111;color:#eee;padding:8px;border-radius:4px;"></div>';
                echo '<div id="rf-hitos-cache-legend" style="margin-top:10px;font-size:11px;line-height:1.5;color:#475569;background:#f8fafc;border:1px solid #e2e8f0;padding:8px;border-radius:4px;">
                        <strong>Leyenda métricas:</strong> 
                        <ul style="margin:6px 0 0 18px;padding:0;list-style:disc;">
                            <li><strong>Modalidades</strong>: modalidades detectadas usadas para rankings.</li>
                            <li><strong>Temporadas</strong>: temporadas únicas derivadas de torneos.</li>
                            <li><strong>Rankings Base</strong>: uno por cada modalidad (sin temporada).</li>
                            <li><strong>Rankings Temporada</strong>: combinaciones Modalidad × Temporada.</li>
                            <li><strong>Perfiles</strong>: jugadores cacheados / jugadores detectados en rankings.</li>
                            <li><strong>Cobertura</strong>: (Perfiles cacheados / Detectados) × 100.</li>
                            <li><strong>Índice</strong>: nº de jugadores en el índice global (entre paréntesis la edad del índice).</li>
                            <li><strong>Desglose</strong>: suma desestructurada de tipos de tareas realizadas.</li>
                        </ul>
                </div>';
        echo '<script>window.__RF_HITOS_STATUS = '. wp_json_encode( $status ) .';</script>';
                // Hook para que el JS principal (si se carga) adopte la misma UI de métricas.
            echo '<script>(function(){' 
                .'window.RF_HITOS_ENHANCE_MAIN=function(s){try{var $=window.jQuery;if(!$)return;' 
                .'var prog=(s.progress||0);var metaEl=$("#rf-hitos-cache-meta");if(!metaEl.length)return;' 
                .'function breakdown(st){try{var parts=[];if(st){var base=st.rankings_base_completed||0;var temp=st.rankings_temp_completed||0;var profiles=st.players_cached||0;var fixed=4;parts.push(fixed+" base");if(base)parts.push(base+" rank base");if(temp)parts.push(temp+" rank temp");if(profiles)parts.push(profiles+" perfiles");return parts.join(" + ");}}catch(e){}return "";}' 
                .'var bd=breakdown(s);var html="<strong>Estado:</strong> "+(s.status||"?")+" | "+(s.done||0)+"/"+(s.total||0)+" ("+prog+"%)";' 
                .'if(bd)html+="<br><span style=\"opacity:.7\">Desglose: "+bd+"</span>";metaEl.html(html);' 
                .'var sb=$("#rf-hitos-cache-stats");if(!sb.length)return;var cells=[];' 
                .'function cell(l,v,ttl){cells.push("<div style=\"background:#f1f5f9;border:1px solid #cbd5e1;padding:6px;border-radius:4px;\""+(ttl?" title=\""+ttl.replace(/\"/g,"&quot;")+"\"":"")+" ><div style=\"font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#475569;\">"+l+"</div><div style=\"font-weight:600;color:#0f172a;\">"+v+"</div></div>");}' 
                .'if(typeof s.mods_found!=="undefined")cell("Modalidades",s.mods_found,"Modalidades detectadas"); else if(typeof s.modalidades_count!=="undefined")cell("Modalidades",s.modalidades_count,"Elementos devueltos por API");' 
                .'if(typeof s.temps_found!=="undefined")cell("Temporadas",s.temps_found,"Temporadas detectadas");' 
                .'if(typeof s.rankings_base_completed!=="undefined")cell("Rankings Base",s.rankings_base_completed,"1 por modalidad");' 
                .'if(typeof s.rankings_temp_completed!=="undefined")cell("Rankings Temporada",s.rankings_temp_completed,"Modalidad × Temporada");' 
                .'if(typeof s.players_cached!=="undefined")cell("Perfiles",s.players_cached+(s.players_detected?(" / "+s.players_detected):""),"Perfiles cacheados / detectados");' 
                .'if(typeof s.coverage_pct!=="undefined")cell("Cobertura",s.coverage_pct+"%","(Perfiles cacheados / detectados) × 100");' 
                .'if(s.players_index_meta&&s.players_index_meta.players_index_players){var pim=s.players_index_meta;var ageSec=Math.max(0,Math.round(Date.now()/1000-(pim.players_index_generated||0)));var ageFmt=ageSec<90?ageSec+"s":Math.round(ageSec/60)+"m";cell("Índice",pim.players_index_players+" ("+ageFmt+")","Jugadores indexados (edad)");}' 
                .'sb.html(cells.join(""));}catch(e){}};' 
                .'})();</script>';
        // Fallback inline (por si el JS principal no se carga)
        $ajax_url  = esc_js( admin_url('admin-ajax.php') );
        $nonce_val = esc_js( wp_create_nonce( RF_Hitos_Cache_Manager::NONCE_ACTION ) );
    // Usamos nowdoc para evitar que PHP intente interpolar variables JS ($, $p, etc.)
    $fallback_js = <<<'FALLBACK'
<script>(function(){try{if(window.__RF_HITOS_FALLBACK_INIT)return;window.__RF_HITOS_FALLBACK_INIT=1;var $=window.jQuery;if(!$)return;if(window.RFHITOSCACHE&&window.__RF_HITOS_STATUS){return;}var box=$('#rf-hitos-cache-log');function log(m){var ts=new Date().toLocaleTimeString();box.prepend('<div>['+ts+'] '+m+'</div>');}var ajax='{$ajax_url}';var nonce='{$nonce_val}';
function breakdown(s){try{var parts=[];if(s){var base=s.rankings_base_completed||0;var temp=s.rankings_temp_completed||0;var profiles=s.players_cached||0;var fixed=4;parts.push(fixed+' base');if(base)parts.push(base+' rank base');if(temp)parts.push(temp+' rank temp');if(profiles)parts.push(profiles+' perfiles');return parts.join(' + ');} }catch(e){}return '';} 
function paint(s){s=s||{};var $p=$;var prog=(s.progress||0);$p('#rf-hitos-cache-progress').css('width',prog+'%');var meta='<strong>Estado:</strong> '+(s.status||'?')+' | '+(s.done||0)+'/'+(s.total||0)+' ('+prog+'%)';var bd=breakdown(s);if(bd)meta+='<br><span style="opacity:.7">Desglose: '+bd+'</span>';$p('#rf-hitos-cache-meta').html(meta);var sb=$p('#rf-hitos-cache-stats');if(sb.length){var cells=[];function cell(l,v,ttl){cells.push('<div style="background:#f1f5f9;border:1px solid #cbd5e1;padding:6px;border-radius:4px;"'+(ttl?' title="'+ttl.replace(/"/g,'&quot;')+'"':'')+'><div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#475569;">'+l+'</div><div style="font-weight:600;color:#0f172a;">'+v+'</div></div>');}
cell('Progreso',(s.done||0)+'/'+(s.total||0)+' ('+prog+'%)','Tareas completadas / total');
if(typeof s.mods_found!=='undefined')cell('Modalidades',s.mods_found,'Modalidades detectadas'); else if(typeof s.modalidades_count!=='undefined')cell('Modalidades',s.modalidades_count,'Elementos devueltos por API');
if(typeof s.temps_found!=='undefined')cell('Temporadas',s.temps_found,'Temporadas detectadas');
if(typeof s.torneos_count!=='undefined')cell('Torneos',s.torneos_count,'Torneos listados');
if(typeof s.campeones_count!=='undefined')cell('Campeones',s.campeones_count,'Registros de campeones');
if(typeof s.rankings_base_completed!=='undefined')cell('Rankings Base',s.rankings_base_completed,'1 por modalidad');
if(typeof s.rankings_temp_completed!=='undefined')cell('Rankings Temporada',s.rankings_temp_completed,'Modalidad × Temporada');
if(typeof s.players_cached!=='undefined')cell('Perfiles',s.players_cached+(s.players_detected?(' / '+s.players_detected):''),'Perfiles cacheados / detectados');
if(typeof s.coverage_pct!=='undefined')cell('Cobertura',s.coverage_pct+'%','(Perfiles cacheados / detectados) × 100');
if(s.players_index_meta&&s.players_index_meta.players_index_players){var pim=s.players_index_meta;var ageSec=Math.max(0,Math.round(Date.now()/1000-(pim.players_index_generated||0)));var ageFmt=ageSec<90?ageSec+'s':Math.round(ageSec/60)+'m';cell('Índice',pim.players_index_players+' ('+ageFmt+')','Jugadores indexados (edad)');}
sb.html(cells.join(''));}}
function step(){if(!window.__RF_HITOS_FB_RUNNING)return;$.post(ajax,{action:'rfhitos_cache_step',_n:nonce}).done(function(r){if(!r.success){log('Error step');window.__RF_HITOS_FB_RUNNING=false;return;}paint(r.data);if(r.data.status==='done'){log('Completado');window.__RF_HITOS_FB_RUNNING=false;}else setTimeout(step,350);}).fail(function(){log('Fallo conexión');setTimeout(step,3000);});}
$('#rf-hitos-cache-start').on('click',function(){if(window.__RF_HITOS_FB_RUNNING)return;window.__RF_HITOS_FB_RUNNING=true;log('Iniciando generación...');$('#rf-hitos-cache-meta').html('<strong>Estado:</strong> iniciando');$.post(ajax,{action:'rfhitos_cache_init',_n:nonce}).done(function(r){if(!r.success){log('Error init');window.__RF_HITOS_FB_RUNNING=false;return;}paint(r.data);step();});});
$('#rf-hitos-cache-purge').on('click',function(){if(!confirm('¿Purgar cache?'))return;window.__RF_HITOS_FB_RUNNING=false;$.post(ajax,{action:'rfhitos_cache_purge',_n:nonce}).done(function(r){log('Cache purgada');paint(r.data);});});
if(window.__RF_HITOS_STATUS){paint(window.__RF_HITOS_STATUS);}log('Fallback JS listo.');}catch(e){if(window.console)console.warn(e);}})();</script>
FALLBACK;
        echo $fallback_js;
        echo '</div>';
    }

    // --- ACCIONES / BOTONES DE CÁLCULO ---
    // Callbacks de sección "Tipos de competición" eliminados

    // Botón informativo de "Campeones de España" eliminado (se obtiene en vivo)

    public function run_stats_button_callback() {
        // Bloque temporalmente deshabilitado hasta disponer del endpoint oficial de "Total de partidas jugadas".
        echo '<div class="stats-control-group"><h4>Estadísticas Globales</h4><p class="description">El cálculo de <em>Total de partidas jugadas</em> se integrará cuando esté disponible el endpoint oficial. Por ahora, esta acción queda deshabilitada.</p></div>';
    }

    // Botón de "Cálculo de Temporadas" eliminado

    public function calculate_hall_of_fame_callback() {
        // Render de la subsección de Hall of Fame
        $status_active = get_option('futbolin_active_players_calculation_status', 'complete');
        $status_win_rate = get_option('futbolin_win_rate_calculation_status', 'complete');
        $total_players_active = get_option('futbolin_active_players_total_count', 0);
        $total_players_win_rate = get_option('futbolin_win_rate_total_count', 0);
        $message = "Jugadores activos: **{$total_players_active}**. Jugadores con % de victorias: **{$total_players_win_rate}**.";
        $is_in_progress = ($status_active === 'in_progress' || $status_win_rate === 'in_progress');

        echo '<div class="stats-control-group"><h4>Cálculo del Hall of Fame</h4><div class="button-group"><button type="button" id="futbolin-run-hall-of-fame-calc" class="button button-primary" ' . ($is_in_progress ? 'disabled' : '') . '>Recalcular Hall of Fame</button><button type="button" id="cancel-futbolin-run-hall-of-fame-calc" class="button button-secondary" style="display:' . ($is_in_progress ? 'inline-block' : 'none') . ';">Cancelar</button></div><p class="description">Calcula y guarda los rankings de Jugadores Más Activos y de Porcentaje de Victorias en un solo proceso. Este proceso es lento.</p><p class="stats-message">' . $message . '</p><div id="futbolin-run-hall-of-fame-calc-progress-container" class="futbolin-progress-bar-wrapper" style="display:' . ($is_in_progress ? 'block' : 'none') . ';"><div id="futbolin-run-hall-of-fame-calc-progress-bar" style="width:0%;"></div><span id="futbolin-run-hall-of-fame-calc-progress-status"></span></div></div>';
    }

    // Botón de "Cálculo de Finales" eliminado (resto de UI legacy purgada)

    // (UI legacy de dataset/version eliminada)

    // ====== AJAX: Gestión de páginas del plugin ======
    public function ajax_create_plugin_page() {
        if (! current_user_can('manage_options')) { wp_send_json_error(['message'=>'Permisos insuficientes'], 403); }
        check_ajax_referer('futbolin_admin_nonce', 'admin_nonce');
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $visibility = isset($_POST['visibility']) ? sanitize_key($_POST['visibility']) : 'publish';
        $password = isset($_POST['password']) ? (string)wp_unslash($_POST['password']) : '';
        $shortcode = isset($_POST['shortcode']) ? wp_kses_post(wp_unslash($_POST['shortcode'])) : '';
        if ($title === '' || $shortcode === '') { wp_send_json_error(['message'=>'Faltan título o shortcode']); }
        $postarr = [
            'post_title'   => $title,
            'post_content' => $shortcode,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ];
        if ($visibility === 'private') { $postarr['post_status'] = 'private'; }
        if ($visibility === 'password') { $postarr['post_status'] = 'publish'; $postarr['post_password'] = $password; }
        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) { wp_send_json_error(['message'=>$post_id->get_error_message()]); }
        $link = get_permalink($post_id);
        wp_send_json_success(['id'=>$post_id, 'link'=>$link]);
    }

    public function ajax_get_permalink() {
        if (! current_user_can('manage_options')) { wp_send_json_error(['message'=>'Permisos insuficientes'], 403); }
        check_ajax_referer('futbolin_admin_nonce', 'admin_nonce');
        $id = isset($_POST['page_id']) ? (int)$_POST['page_id'] : 0;
        if (! $id) { wp_send_json_error(['message'=>'page_id requerido']); }
        $link = get_permalink($id);
        if (! $link) { wp_send_json_error(['message'=>'No se encontró permalink']); }
        wp_send_json_success(['link'=>$link]);
    }

    public function ajax_insert_shortcode() {
        if (! current_user_can('manage_options')) { wp_send_json_error(['message'=>'Permisos insuficientes'], 403); }
        check_ajax_referer('futbolin_admin_nonce', 'admin_nonce');
        $id = isset($_POST['page_id']) ? (int)$_POST['page_id'] : 0;
        $shortcode = isset($_POST['shortcode']) ? (string)wp_unslash($_POST['shortcode']) : '';
        if (! $id || $shortcode==='') { wp_send_json_error(['message'=>'Parámetros inválidos']); }
        $post = get_post($id);
        if (! $post || $post->post_type !== 'page') { wp_send_json_error(['message'=>'Página no válida']); }
        $content = (string)$post->post_content;
        // Evitar duplicados si el shortcode ya está presente
        if (stripos($content, $shortcode) !== false) {
            wp_send_json_success(['updated'=>false, 'message'=>'La página ya contiene el shortcode.']);
        }
        // Insertar shortcode tras el contenido existente, dejando separación si procede
        $new = rtrim($content) . "\n\n" . $shortcode . "\n";
        $res = wp_update_post(['ID'=>$id, 'post_content'=>$new], true);
        if (is_wp_error($res)) { wp_send_json_error(['message'=>$res->get_error_message()]); }
        wp_send_json_success(['updated'=>true, 'message'=>'Shortcode insertado tras el contenido existente.']);
    }

    public function ajax_delete_page() {
        if (! current_user_can('manage_options')) { wp_send_json_error(['message'=>'Permisos insuficientes'], 403); }
        check_ajax_referer('futbolin_admin_nonce', 'admin_nonce');
        $id = isset($_POST['page_id']) ? (int)$_POST['page_id'] : 0;
        if (! $id) { wp_send_json_error(['message'=>'page_id requerido']); }
        $post = get_post($id);
        if (! $post || $post->post_type !== 'page') { wp_send_json_error(['message'=>'Página no válida']); }
        $res = wp_trash_post($id); // Enviar a papelera
        if (! $res) { wp_send_json_error(['message'=>'No se pudo enviar a la papelera']); }
        wp_send_json_success(['trashed'=>true]);
    }
    


public function show_global_stats_callback() {
    $this->render_checkbox_callback('show_global_stats', 'Activar la visualización de Estadísticas globales.');
}

    public function show_player_hitos_callback() { $this->render_checkbox_callback('show_player_hitos', __('Mostrar pestaña "Hitos"','futbolin')); }

    public function show_player_torneos_callback() { $this->render_checkbox_callback('show_player_torneos', __('Mostrar pestaña "Torneos"','futbolin')); }

    /* === Callbacks faltantes de visualización de Ranking (evitaban fatales) === */
    public function show_champions_callback()    { $this->render_checkbox_callback('show_champions',    __('Activar módulo Campeones de España','futbolin')); }
    public function show_tournaments_callback()  { $this->render_checkbox_callback('show_tournaments',  __('Activar módulo Campeonatos disputados','futbolin')); }
    public function show_hall_of_fame_callback() { $this->render_checkbox_callback('show_hall_of_fame', __('Activar Hall of Fame','futbolin')); }
    public function show_finals_reports_callback(){ $this->render_checkbox_callback('show_finals_reports', __('Activar Informes de Finales','futbolin')); }
    public function enable_fefm_no1_club_callback(){ $this->render_checkbox_callback('enable_fefm_no1_club', __('Mostrar bloque FEFM Nº1 CLUB','futbolin')); }
    public function enable_club_500_played_callback(){ $this->render_checkbox_callback('enable_club_500_played', __('Mostrar bloque Club 500 – Played','futbolin')); }
    public function enable_club_100_winners_callback(){ $this->render_checkbox_callback('enable_club_100_winners', __('Mostrar bloque Club 100 – Winners','futbolin')); }
    public function enable_top_rivalries_callback(){ $this->render_checkbox_callback('enable_top_rivalries', __('Mostrar listado Top rivalidades','futbolin')); }

    // === Compat: callbacks antiguos redirigen al botón unificado ===
    public function clear_tournaments_cache_button_callback() {
        if (method_exists($this, 'clear_caches_button_callback')) {
            return $this->clear_caches_button_callback();
        }
    echo '<div class="stats-control-group"><button type="button" id="futbolin-clear-all-caches" class="button button-primary">Vaciar caché (jugadores + torneos)</button> <button type="button" id="rf-btn-cleanup-transients" class="button">Limpieza de transients antiguos</button> <span id="futbolin-cache-stats" style="margin-left:12px;color:#555"></span></div><pre id="futbolin-data-actions-log" style="display:block;max-height:240px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:6px;"></pre>';
    }
    public function clear_players_cache_button_callback() {
        if (method_exists($this, 'clear_caches_button_callback')) {
            return $this->clear_caches_button_callback();
        }
        echo '<div class="stats-control-group"><button type="button" id="futbolin-clear-all-caches" class="button button-primary">Vaciar caché (jugadores + torneos)</button> <span id="futbolin-cache-stats" style="margin-left:12px;color:#555"></span></div><pre id="futbolin-data-actions-log" style="display:block;max-height:240px;overflow:auto;background:#111;color:#0f0;padding:10px;border-radius:6px;"></pre>';
    }

    // --- AVISO DE CONEXIÓN ---
    public function maybe_show_connection_notice() {
        // Solo en pantallas de admin y para usuarios con permisos
        if (!current_user_can('manage_options')) return;
        // Mostrar aviso únicamente en el área de administración
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        // Ocultar si estamos en nuestra propia página de ajustes (ya se ve la pestaña)
        if ($screen && isset($_GET['page']) && $_GET['page'] === 'futbolin-api-settings') {
            // Permitimos que se muestre si faltan credenciales pero no estorbará la UI
        }

        // ¿Hubo una prueba de conexión OK reciente? Si sí, no molestamos con el aviso global
        $ok = get_option('ranking_api_last_ok', []);
        $has_ok = is_array($ok) && !empty($ok['time']) && (time() - (int)$ok['time'] < 12 * HOUR_IN_SECONDS);
        if ($has_ok) return;

        // Comprobar config efectiva (solo si no hay OK reciente)
        $cfg = function_exists('futbolin_get_api_config') ? futbolin_get_api_config() : ['base_url'=>'','username'=>'','password'=>'','sources'=>['baseurl_source'=>'none','user_source'=>'none','pass_source'=>'none']];
        $base = is_array($cfg) ? trim((string)($cfg['base_url'] ?? '')) : '';
        $usr  = is_array($cfg) ? trim((string)($cfg['username'] ?? '')) : '';
        $pwd  = is_array($cfg) ? (string)($cfg['password'] ?? '') : '';
        $has_cfg = ($base !== '' && $usr !== '' && $pwd !== '');

        $url = admin_url('admin.php?page=futbolin-api-settings&tab=conexion');
        $msg = $has_cfg ? __('Configura la conexión y prueba para verificar credenciales.','futbolin') : __('Falta configurar la conexión a la API.','futbolin');
        echo '<div id="futbolin-conn-notice" class="notice notice-warning is-dismissible"><p><strong>Futbolín API:</strong> '.esc_html($msg).' <a href="'.esc_url($url).'">'.esc_html__('Ir a Conexión','futbolin').'</a></p></div>';
    }

    // --- AJAX: PROBAR CONEXIÓN ---
    public function ajax_test_connection() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'forbidden'], 403); }
        $nonce = isset($_POST['admin_nonce']) ? sanitize_text_field($_POST['admin_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'futbolin_admin_nonce')) { wp_send_json_error(['message'=>'bad nonce'], 400); }

        $base = isset($_POST['base_url']) ? trim((string)$_POST['base_url']) : '';
        $usr  = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $pwd  = isset($_POST['password']) ? (string)$_POST['password'] : '';
        if ($base === '' || $usr === '' || $pwd === '') { wp_send_json_error(['message'=>__('Faltan datos.','futbolin')], 400); }
        if (stripos($base, 'http://') !== 0 && stripos($base, 'https://') !== 0) { $base = 'https://' . $base; }
        $base = rtrim($base, "/\\");

        // Construir URL de login
        $login_url = $base . (stripos($base, '/api') === false ? '/api' : '') . '/Seguridad/login';
        $args = [
            'method'    => 'POST',
            'timeout'   => 20,
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => wp_json_encode(['usuario' => $usr, 'password' => $pwd]),
            'sslverify' => false,
        ];
        $resp = wp_remote_post($login_url, $args);
        if (is_wp_error($resp)) { wp_send_json_error(['message'=>__('Error de red','futbolin')], 502); }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($resp);
            $msg  = __('Credenciales o endpoint no válidos','futbolin');
            if ($body) { $msg .= ' — ' . substr(strip_tags($body), 0, 160); }
            wp_send_json_error(['message'=>$msg], 400);
        }
        $body_raw = wp_remote_retrieve_body($resp);
        $tok = '';
        $cand = ['token','accessToken','access_token','bearer','jwt'];
        $j = json_decode($body_raw);
        if (is_object($j)) {
            foreach ($cand as $k) { if (isset($j->$k) && is_string($j->$k) && $j->$k !== '') { $tok = $j->$k; break; } }
        }
        if ($tok === '') {
            $ja = json_decode($body_raw, true);
            if (is_array($ja)) {
                $paths = [['data','token'], ['result','token']];
                foreach ($paths as $p) {
                    $tmp = $ja; foreach ($p as $seg) { if (is_array($tmp) && array_key_exists($seg, $tmp)) { $tmp = $tmp[$seg]; } else { $tmp = null; break; } }
                    if (is_string($tmp) && $tmp !== '') { $tok = $tmp; break; }
                }
            }
        }
        if ($tok === '') { wp_send_json_error(['message'=>__('Respuesta sin token','futbolin')], 400); }

        // Registrar OK reciente (oculta la alerta) con fingerprint básico
        $host = parse_url($base, PHP_URL_HOST);
        update_option('ranking_api_last_ok', ['time'=>time(), 'host'=>$host], false);
        wp_send_json_success(['message'=>'ok']);
    }

    // --- AJAX: LOG VIEW ---
    public function ajax_tail_log() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'forbidden'], 403); }
        $nonce = isset($_POST['admin_nonce']) ? sanitize_text_field($_POST['admin_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'futbolin_admin_nonce')) { wp_send_json_error(['message'=>'bad nonce'], 400); }
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'plugin-high';
        if (!class_exists('Futbolin_Logger')) { wp_send_json_error(['message'=>'logger missing'], 500); }
        $valid = in_array($type, ['wp','plugin-low','plugin-medium','plugin-high','plugin-combined'], true) ? $type : 'plugin-high';
        try {
            $data = Futbolin_Logger::tail($valid);
            wp_send_json_success($data);
        } catch (Throwable $e) {
            wp_send_json_error(['message'=>'logger error','detail'=>$e->getMessage()], 500);
        }
    }

    public function ajax_clear_log() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'forbidden'], 403); }
        $nonce = isset($_POST['admin_nonce']) ? sanitize_text_field($_POST['admin_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'futbolin_admin_nonce')) { wp_send_json_error(['message'=>'bad nonce'], 400); }
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'plugin-high';
        if (!class_exists('Futbolin_Logger')) { wp_send_json_error(['message'=>'logger missing'], 500); }
        $valid = in_array($type, ['wp','plugin-low','plugin-medium','plugin-high'], true) ? $type : 'plugin-high';
        $ok = Futbolin_Logger::clear($valid);
        if ($ok) wp_send_json_success(['message'=>'cleared']);
        wp_send_json_error(['message'=>'failed']);
    }

    public function ajax_clear_all_logs() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'forbidden'], 403); }
        $nonce = isset($_POST['admin_nonce']) ? sanitize_text_field($_POST['admin_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'futbolin_admin_nonce')) { wp_send_json_error(['message'=>'bad nonce'], 400); }
        if (!class_exists('Futbolin_Logger')) { wp_send_json_error(['message'=>'logger missing'], 500); }
        $ok = Futbolin_Logger::clear_all();
        if ($ok) wp_send_json_success(['message'=>'cleared-all']);
        wp_send_json_error(['message'=>'failed']);
    }

    public function ajax_prepare_logs_zip() {
        if (!current_user_can('manage_options')) { wp_send_json_error(['message'=>'forbidden'], 403); }
        $nonce = isset($_POST['admin_nonce']) ? sanitize_text_field($_POST['admin_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'futbolin_admin_nonce')) { wp_send_json_error(['message'=>'bad nonce'], 400); }
        if (!class_exists('Futbolin_Logger')) { wp_send_json_error(['message'=>'logger missing'], 500); }
        $zip = Futbolin_Logger::prepare_zip_all();
        if ($zip) { wp_send_json_success($zip); }
        // Fallback: devolver lista de archivos actuales con URL
        $files = Futbolin_Logger::list_current_log_files();
        wp_send_json_success(['files'=>$files]);
    }
}
// cierre de clase

?>