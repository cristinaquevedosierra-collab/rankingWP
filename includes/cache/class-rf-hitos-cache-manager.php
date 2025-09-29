<?php
/**
 * RF_Hitos_Cache_Manager
 * Cache persistente en disco (subcarpeta de uploads) para datasets críticos
 * del plugin (temporadas, modalidades, campeones, rankings por modalidad+temporada, etc.).
 *
 * Objetivos:
 *  - Botón único de "Generar / Actualizar Cache" que recorre tareas de forma incremental vía AJAX.
 *  - Botón de "Purgar" que elimina todos los archivos y manifiesto.
 *  - Capaz de servir datos aunque la API remota falle (si ya existe cache previa).
 *  - Diseño extensible: se pueden añadir nuevas tareas dinámicamente (rankings) tras obtener catálogos base.
 */
if (!defined('ABSPATH')) { exit; }

if (!class_exists('RF_Hitos_Cache_Manager')) {
    class RF_Hitos_Cache_Manager {
        const OPTION_MANIFEST = 'rf_hitos_cache_manifest_v1';
        const NONCE_ACTION    = 'rf_hitos_cache_nonce';
        const CACHE_FOLDER    = 'ranking-futbolin-cache';
        const OPTION_ENABLED  = 'rf_hitos_cache_enabled';
        const CONFIG_FILE     = 'includes/cache/rf-hitos-cache-endpoints.php';

        protected static $config_cache = null;

        /** Carga configuración de endpoints */
        protected static function config(): array {
            if (self::$config_cache !== null) { return self::$config_cache; }
            $file = trailingslashit(FUTBOLIN_API_PATH) . self::CONFIG_FILE;
            $cfg = [];
            if (file_exists($file)) {
                $cfg = include $file; // phpcs:ignore
                if (!is_array($cfg)) { $cfg = []; }
            }
            // Defaults mínimos si falta archivo
            $defaults = [
                'base' => [
                    'modalidades'=> '/api/Modalidad/GetModalidades',
                    'torneos'    => '/api/Torneo/GetTorneos',
                    'campeones'  => '/api/Jugador/GetCampeonesEspania',
                ],
                'ranking_templates' => [
                    'modalidad_temporada' => '/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{modalidad}/{temporada}',
                    'modalidad' => '/api/Ranking/GetRankingPorModalidadESPGlicko2/{modalidad}'
                ],
                'limits' => [
                    'max_temporadas' => 50,
                    'max_modalidades'=> 20,
                    'max_rankings'   => 400,
                    'players_per_ranking' => 50,
                    'max_player_profiles' => 2000,
                ],
                'player_endpoints' => [
                    'datos' => '/api/Jugador/{playerId}/GetDatosJugador',
                ]
            ];
            // Merge superficial
            $cfg = array_replace_recursive($defaults, $cfg);
            self::$config_cache = $cfg;
            return $cfg;
        }

        /** Directorio base de cache (wp-uploads/...) */
        public static function get_cache_dir(): string {
            $upload = wp_get_upload_dir();
            $base = trailingslashit($upload['basedir']) . self::CACHE_FOLDER;
            if (!is_dir($base)) { wp_mkdir_p($base); }
            return $base . '/';
        }
        /** URL pública (solo para inspección/debug) */
        public static function get_cache_url(): string {
            $upload = wp_get_upload_dir();
            return trailingslashit($upload['baseurl']) . self::CACHE_FOLDER . '/';
        }

        /** Obtiene manifiesto normalizado */
        public static function manifest(): array {
            $m = get_option(self::OPTION_MANIFEST, []);
            if (!is_array($m)) { $m = []; }
            $m += [
                'version'   => 1,
                'created'   => $m['created'] ?? time(),
                'updated'   => time(),
                'tasks'     => $m['tasks'] ?? [],
                'completed' => $m['completed'] ?? [],
                'errors'    => $m['errors'] ?? [],
                'status'    => $m['status'] ?? 'idle'
            ];
            return $m;
        }
        public static function save_manifest(array $m): void {
            $m['updated'] = time();
            update_option(self::OPTION_MANIFEST, $m, false);
        }
        public static function purge(): void {
            $dir = self::get_cache_dir();
            if (is_dir($dir)) {
                foreach (glob($dir . '*') as $f) {
                    if (is_file($f)) { @unlink($f); }
                }
            }
            delete_option(self::OPTION_MANIFEST);
        }
        public static function is_cache_ready(): bool {
            if (!self::is_enabled()) return false;
            $m = self::manifest();
            return $m['status'] === 'done' && count($m['tasks']) > 0 && count($m['tasks']) === count($m['completed']);
        }
        public static function is_enabled(): bool {
            $v = get_option(self::OPTION_ENABLED, 1);
            return (int)$v === 1;
        }
        public static function set_enabled($on): void {
            update_option(self::OPTION_ENABLED, $on ? 1 : 0, false);
        }
        public static function cache_path(string $key): string {
            $safe = preg_replace('/[^a-zA-Z0-9_-]+/','_', $key);
            return self::get_cache_dir() . $safe . '.json';
        }
        public static function cache_write(string $key, $data): void {
            $file = self::cache_path($key);
            @file_put_contents($file, wp_json_encode($data));
        }
        public static function cache_read(string $key) {
            $file = self::cache_path($key);
            if (!file_exists($file)) { return null; }
            $raw = @file_get_contents($file);
            $d = json_decode($raw, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $d : null;
        }
        public static function get_or(string $key, callable $producer) {
            $hit = self::cache_read($key);
            if ($hit !== null) return $hit;
            $data = $producer();
            if ($data !== null) self::cache_write($key, $data);
            return $data;
        }
        /** Construye lista base de tareas (se amplía dinámicamente luego) */
        public static function build_base_tasks(): array {
            $cfg = self::config();
            $tasks = [];
            foreach ($cfg['base'] as $key=>$path) {
                if (!$path) { continue; }
                $tasks[] = ['k'=>$key,'t'=>$key];
            }
            // Tarea artificial para forzar expansión aunque el flujo no la haya disparado
            $tasks[] = ['k'=>'expand','t'=>'expand'];
            return $tasks;
        }
        public static function init_warm(): void {
            // Reinicia siempre desde cero aunque ya exista manifest
            $m = [
                'version'        => 1,
                'created'        => time(),
                'updated'        => time(),
                'tasks'          => self::build_base_tasks(),
                'completed'      => [],
                'errors'         => [],
                'status'         => 'running',
                'tasks_indexed'  => [],
                'profiles_indexed' => []
            ];
            self::save_manifest($m);
        }
        /** Ejecuta un paso: procesa primera tarea pendiente */
    public static function step(): array {
            if (!class_exists('Futbolin_API_Client')) {
                throw new \RuntimeException('Futbolin_API_Client no disponible');
            }
            $api = new \Futbolin_API_Client();
            $m = self::manifest();
            if ($m['status'] !== 'running') { return $m; }
            if (!self::is_enabled()) { $m['status'] = 'disabled'; self::save_manifest($m); return $m; }

            // Lista de pendientes
            $pending = array_values(array_filter($m['tasks'], function($t) use ($m){ return !in_array($t['k'], $m['completed'], true); }));
            if (empty($pending)) {
                // Si ya no hay pendientes pero aún no hemos expandido rankings (porque quizá base incompleta), marcamos done.
                $m['status'] = 'done';
                self::save_manifest($m);
                return $m;
            }
            $task = $pending[0];
            $last_key = $task['k'];
            $last_type = $task['t'];
            try {
                $cfg = self::config();
                $limits = $cfg['limits'];
                switch ($task['t']) {
                    case 'modalidades':
                        $endpoint = $cfg['base']['modalidades'] ?? '/api/Modalidad/GetModalidades';
                        $data = $api->request_raw($endpoint, true);
                        if ($data instanceof \WP_Error) { $data = ['__error'=>true,'code'=>$data->get_error_code(),'message'=>$data->get_error_message()]; }
                        self::cache_write('modalidades', $data);
                    break;
                    case 'campeones':
                        $endpoint = $cfg['base']['campeones'] ?? '/api/Jugador/GetCampeonesEspania';
                        $data = $api->request_raw($endpoint, true);
                        if ($data instanceof \WP_Error) { $data = ['__error'=>true,'code'=>$data->get_error_code(),'message'=>$data->get_error_message()]; }
                        self::cache_write('campeones', $data);
                    break;
                    case 'torneos':
                        if (!empty($cfg['base']['torneos'])) {
                            try { $data = $api->request_raw($cfg['base']['torneos'], true); if ($data instanceof \WP_Error) { $data = ['__error'=>true,'code'=>$data->get_error_code(),'message'=>$data->get_error_message()]; } self::cache_write('torneos', $data); } catch (\Throwable $e) {}
                        }
                    break;
                    case 'ranking_global':
                        if (!empty($cfg['base']['ranking_global'])) {
                            try { $data = $api->request_raw($cfg['base']['ranking_global'], false); self::cache_write('ranking_global', $data); } catch (\Throwable $e) {}
                        }
                    break;
                    case 'expand':
                        // No hace fetch; solo fuerza expansión dinámicamente replicando la lógica
                        // Se ejecutará al final de las tareas base si por algún motivo no se disparó antes.
                    break;
                    case 'ranking':
                        $mod = intval($task['mod'] ?? 0); $temp = $task['temp'] ?? '';
                        $tpls = $cfg['ranking_templates'] ?? [];
                        if ($mod && $temp !== '' && isset($task['with_temp'])) {
                            $tpl = $tpls['modalidad_temporada'] ?? '/api/Ranking/GetRankingPorModalidadPorTemporadaESPGlicko2/{modalidad}/{temporada}';
                            $path = str_replace(['{modalidad}','{temporada}'], [$mod, rawurlencode($temp)], $tpl);
                            $data = $api->request_raw($path, true); // forzamos auth
                            if ($data instanceof \WP_Error) {
                                $data = ['__error'=>true,'code'=>$data->get_error_code(),'message'=>$data->get_error_message()];
                            }
                            self::cache_write('ranking_' . $mod . '_' . $temp, $data);
                        } elseif ($mod && $temp === '' && isset($task['only_mod'])) {
                            $tpl = $tpls['modalidad'] ?? '/api/Ranking/GetRankingPorModalidadESPGlicko2/{modalidad}';
                            $path = str_replace('{modalidad}', $mod, $tpl);
                            $data = $api->request_raw($path, true); // forzamos auth
                            if ($data instanceof \WP_Error) {
                                $data = ['__error'=>true,'code'=>$data->get_error_code(),'message'=>$data->get_error_message()];
                            }
                            self::cache_write('ranking_' . $mod, $data);
                        } else { $data = null; }
                        // A partir del ranking, si hay items (jugadores) añadimos tareas player_profile
                        if (is_array($data)) {
                            $items = $data;
                            if (isset($data['items']) && is_array($data['items'])) { $items = $data['items']; }
                            $added_profiles = 0;
                            foreach ($items as $row) {
                                $jid = 0;
                                if (is_array($row)) { $jid = intval($row['jugadorId'] ?? ($row['JugadorId'] ?? 0)); }
                                elseif (is_object($row)) { $jid = intval($row->jugadorId ?? ($row->JugadorId ?? 0)); }
                                if ($jid > 0) {
                                    $kprof = 'player_' . $jid;
                                    if (!isset($m['profiles_indexed'][$kprof]) && !in_array($kprof, $m['completed'], true)) {
                                        if (!isset($limits['max_player_profiles']) || count($m['profiles_indexed']) < (int)$limits['max_player_profiles']) {
                                            $m['tasks'][] = ['k'=>$kprof,'t'=>'player_profile','player'=>$jid];
                                            $m['profiles_indexed'][$kprof] = 1; $added_profiles++;
                                            if ($added_profiles >= (int)$limits['players_per_ranking']) break;
                                        }
                                    }
                                }
                            }
                        }
                    break;
                    case 'player_profile':
                        $pid = intval($task['player'] ?? 0);
                        if ($pid > 0) {
                            $profile = ['id'=>$pid];
                            $pEndpoints = $cfg['player_endpoints'];
                            foreach ($pEndpoints as $k=>$tpl) {
                                if (!$tpl) continue;
                                $ep = str_replace('{playerId}', (string)$pid, $tpl);
                                try { $profile[$k] = $api->request_raw($ep, true); }
                                catch (\Throwable $e) { $profile[$k.'_error'] = $e->getMessage(); }
                            }
                            self::cache_write('player_' . $pid, $profile);
                        }
                    break;
                }
                // Expansión dinámica: tras completar catálogos base, anexar rankings
                if (in_array($task['t'], ['torneos','modalidades','expand'], true)) {
                    // Derivar temporadas de torneos (temporadaId) y modalidades de estructura arbitraria
                    $extractIds = function($data, $fieldNames){
                        $out=[]; $stack=[ $data ];
                        while ($stack) { $cur = array_pop($stack); if ($cur === null) continue;
                            if (is_array($cur)) {
                                // si es lista numerica
                                $isList = array_keys($cur) === range(0, count($cur)-1);
                                if ($isList) { foreach ($cur as $v) { $stack[] = $v; } }
                                else { // asociativo
                                    foreach ($fieldNames as $fn) { if (isset($cur[$fn]) && is_scalar($cur[$fn])) { $out[] = intval($cur[$fn]); } }
                                    foreach ($cur as $v) { if (is_array($v) || is_object($v)) { $stack[] = $v; } }
                                }
                            } elseif (is_object($cur)) {
                                foreach ($fieldNames as $fn) { if (isset($cur->$fn) && !is_array($cur->$fn) && !is_object($cur->$fn)) { $out[] = intval($cur->$fn); } }
                                foreach ($cur as $k=>$v) { if (is_array($v) || is_object($v)) { $stack[] = $v; } }
                            }
                        }
                        return array_values(array_unique(array_filter($out))); };

                    $temps = $extractIds(self::cache_read('torneos'), ['temporadaId']);
                    $modsRaw = self::cache_read('modalidades');
                    $mods = $extractIds($modsRaw, ['modalidadId','id']);
                    if ($mods) {
                        $limits = $cfg['limits'];
                        $temps = array_slice($temps, 0, (int)$limits['max_temporadas']);
                        $mods  = array_slice($mods, 0, (int)$limits['max_modalidades']);
                        $m['debug_mods_found'] = count($mods);
                        $m['debug_temps_found'] = count($temps);
                        $enqueued = 0;
                        $stop=false;
                        foreach ($mods as $mod) {
                            // Ranking por modalidad (sin temporada)
                            $kmod = 'ranking_' . $mod;
                            if (!isset($m['tasks_indexed'][$kmod]) && !in_array($kmod, $m['completed'], true)) {
                                $m['tasks'][] = ['k'=>$kmod,'t'=>'ranking','mod'=>$mod,'temp'=>'','only_mod'=>1];
                                $m['tasks_indexed'][$kmod] = 1; $enqueued++;
                                if ($enqueued >= (int)$limits['max_rankings']) { $stop=true; break; }
                            }
                            foreach ($temps as $t) {
                                $k = 'ranking_' . $mod . '_' . $t;
                                if (!array_key_exists('tasks_indexed', $m)) { $m['tasks_indexed'] = []; }
                                if (!in_array($k, $m['completed'], true) && !isset($m['tasks_indexed'][$k])) {
                                    $m['tasks'][] = ['k'=>$k,'t'=>'ranking','mod'=>$mod,'temp'=>$t,'with_temp'=>1];
                                    $m['tasks_indexed'][$k] = 1;
                                    $enqueued++;
                                    if ($enqueued >= (int)$limits['max_rankings']) { $stop=true; break; }
                                }
                            }
                            if ($stop) { break; }
                        }
                        $m['debug_rankings_enqueued'] = ($m['debug_rankings_enqueued'] ?? 0) + $enqueued;
                    }
                }
                $m['completed'][] = $task['k'];
                if (count($m['completed']) === count($m['tasks'])) { $m['status'] = 'done'; }
                $m['last_completed'] = ['k'=>$last_key,'t'=>$last_type];
            } catch (\Throwable $e) {
                $m['errors'][] = ['k'=>$task['k'], 'msg'=>$e->getMessage()];
                $m['completed'][] = $task['k'];
                $m['last_completed'] = ['k'=>$last_key,'t'=>$last_type,'error'=>true];
            }
            self::save_manifest($m);
            return $m;
        }
        public static function status(): array {
            $m = self::manifest();
            $total = max(1, count($m['tasks']));
            $progress = round(count($m['completed']) * 100 / $total, 1);
            // Métricas de perfiles cacheados
            $players_cached = 0;
            foreach ($m['completed'] as $k) { if (strpos($k, 'player_') === 0) { $players_cached++; } }
            // Métricas de rankings (base vs temporada)
            $rankings_base_completed = 0; // ranking_{mod}
            $rankings_temp_completed = 0; // ranking_{mod}_{temp}
            foreach ($m['completed'] as $k) {
                if (strpos($k, 'ranking_') === 0) {
                    // contar guiones bajos
                    $underscores = substr_count($k, '_');
                    if ($underscores === 1) { $rankings_base_completed++; }
                    elseif ($underscores >= 2) { $rankings_temp_completed++; }
                }
            }
            $rankings_enqueued = $m['debug_rankings_enqueued'] ?? 0;
            $mods_found = $m['debug_mods_found'] ?? 0;
            $temps_found = $m['debug_temps_found'] ?? 0;
            // Token fingerprint (no expone el token real)
            $token_fp = '';
            $login_meta = null;
            if (class_exists('Futbolin_API_Client')) {
                try { $api = new \Futbolin_API_Client(); $token_fp = method_exists($api,'get_token_fingerprint') ? $api->get_token_fingerprint() : ''; } catch (\Throwable $e) { $token_fp=''; }
            }
            // Intentar recuperar metadatos de último login
            try { $lm = get_transient('futbolin_api_token_meta'); if ($lm) { $login_meta = $lm; } } catch (\Throwable $e) {}
            // Conteos directos de ficheros base cacheados
            $modalidades_count = 0; $torneos_count = 0; $campeones_count = 0;
            $modalidades = self::cache_read('modalidades');
            if (is_array($modalidades) && !isset($modalidades['__error'])) { $modalidades_count = count($modalidades); }
            $torneos = self::cache_read('torneos');
            if (is_array($torneos) && !isset($torneos['__error'])) { $torneos_count = count($torneos); }
            $campeones = self::cache_read('campeones');
            if (is_array($campeones) && !isset($campeones['__error'])) { $campeones_count = count($campeones); }
            // Detectar jugadores únicos a partir de rankings cacheados (rápido: solo nombres de archivos ya completados)
            $players_detected = [];
            foreach ($m['completed'] as $k) {
                if (strpos($k, 'ranking_') === 0) {
                    // Cargar ranking y extraer jugadorId
                    $rk = self::cache_read($k);
                    if (is_array($rk)) {
                        $list = $rk;
                        if (isset($rk['items']) && is_array($rk['items'])) { $list = $rk['items']; }
                        foreach ($list as $row) {
                            if (is_array($row)) {
                                $jid = intval($row['jugadorId'] ?? ($row['JugadorId'] ?? 0));
                            } elseif (is_object($row)) {
                                $jid = intval($row->jugadorId ?? ($row->JugadorId ?? 0));
                            } else { $jid = 0; }
                            if ($jid > 0) { $players_detected[$jid] = true; }
                        }
                    }
                }
            }
            $players_detected_count = count($players_detected);
            $coverage_pct = ($players_detected_count > 0) ? round(($players_cached / max(1,$players_detected_count))*100,1) : 0;
            // Throttle summary (cada 60s máximo)
            $now = time();
            $summary_path = self::cache_path('summary');
            $need_summary = true;
            if (file_exists($summary_path)) { $age = $now - filemtime($summary_path); if ($age < 60) { $need_summary = false; } }
            if ($need_summary) {
                try {
                    $summary = [
                        'modalidades'=>$modalidades_count,
                        'torneos'=>$torneos_count,
                        'campeones'=>$campeones_count,
                        'rankings_base_completed'=>$rankings_base_completed,
                        'rankings_temp_completed'=>$rankings_temp_completed,
                        'players_cached'=>$players_cached,
                        'players_detected'=>$players_detected_count,
                        'coverage_pct'=>$coverage_pct,
                        'generated'=>$now
                    ];
                    self::cache_write('summary', $summary);
                } catch (\Throwable $e) {}
            }
            // Índice de jugadores (throttle 30 min) solo si cache completa o muchos rankings procesados
            $players_index_meta = [];
            $index_path = self::cache_path('players_index');
            $need_index = false; $index_age = null;
            if (file_exists($index_path)) { $index_age = $now - filemtime($index_path); if ($index_age > 1800) { $need_index = true; } }
            else { $need_index = true; }
            $rankings_processed = 0; foreach ($m['completed'] as $k) { if (strpos($k,'ranking_')===0) { $rankings_processed++; } }
            if ($need_index && $rankings_processed > 5) {
                try {
                    $prev_index = self::cache_read('players_index');
                    $prev_positions = [];
                    if (is_array($prev_index) && isset($prev_index['players']) && is_array($prev_index['players'])) {
                        foreach ($prev_index['players'] as $pid=>$pdata) { if (isset($pdata['pos'])) { $prev_positions[$pid] = (int)$pdata['pos']; } }
                    }
                    $players_index = [ 'generated'=>$now, 'players'=>[], 'movements'=>[] ];
                    // Usar ranking de modalidad Individual (1) como base de posición global si existe; fallback a primer ranking base
                    $base_ranking_keys = [];
                    foreach ($m['completed'] as $k) { if (preg_match('/^ranking_\d+$/',$k)) { $base_ranking_keys[]=$k; } }
                    $primary_key = null;
                    foreach ($base_ranking_keys as $rk) { if ($rk === 'ranking_1') { $primary_key = $rk; break; } }
                    if (!$primary_key && $base_ranking_keys) { $primary_key = $base_ranking_keys[0]; }
                    if ($primary_key) {
                        $primary_data = self::cache_read($primary_key);
                        if (is_array($primary_data)) {
                            $list = isset($primary_data['items']) && is_array($primary_data['items']) ? $primary_data['items'] : $primary_data;
                            foreach ($list as $idx=>$row) {
                                $jid = 0; $puntos = 0; $categoria = null; $nombre = null; $pos = $idx+1;
                                if (is_array($row)) {
                                    $jid = intval($row['jugadorId'] ?? ($row['JugadorId'] ?? 0));
                                    $puntos = floatval($row['puntos'] ?? ($row['Puntos'] ?? 0));
                                    $nombre = $row['nombreJugador'] ?? ($row['NombreJugador'] ?? ($row['nombre'] ?? null));
                                    $categoria = $row['categoria'] ?? ($row['Categoria'] ?? null);
                                } elseif (is_object($row)) {
                                    $jid = intval($row->jugadorId ?? ($row->JugadorId ?? 0));
                                    $puntos = floatval($row->puntos ?? ($row->Puntos ?? 0));
                                    $nombre = $row->nombreJugador ?? ($row->NombreJugador ?? ($row->nombre ?? null));
                                    $categoria = $row->categoria ?? ($row->Categoria ?? null);
                                }
                                if ($jid > 0) {
                                    $delta = null;
                                    if (isset($prev_positions[$jid])) { $delta = $prev_positions[$jid] - $pos; }
                                    $players_index['players'][$jid] = [
                                        'pos'=>$pos,
                                        'points'=>(int)round($puntos),
                                        'name'=>$nombre,
                                        'cat'=> (is_array($categoria) && isset($categoria['descripcion'])) ? $categoria['descripcion'] : (is_object($categoria) && isset($categoria->descripcion) ? $categoria->descripcion : (is_string($categoria)?$categoria:null)),
                                        'delta'=>$delta
                                    ];
                                    if ($delta !== null && $delta !== 0) {
                                        $players_index['movements'][] = [ 'player_id'=>$jid, 'delta'=>$delta, 'new_pos'=>$pos ];
                                    }
                                }
                            }
                        }
                    }
                    // Ordenar movements por mejora (delta positiva mayor primero)
                    if (!empty($players_index['movements'])) {
                        usort($players_index['movements'], function($a,$b){ return $b['delta'] <=> $a['delta']; });
                        $players_index['movements'] = array_slice($players_index['movements'],0,50);
                    }
                    self::cache_write('players_index', $players_index);
                    $players_index_meta = [
                        'players_index_generated'=>$now,
                        'players_index_players'=>count($players_index['players']),
                        'top_movements_count'=>count($players_index['movements'])
                    ];
                } catch (\Throwable $e) {}
            } else {
                if (file_exists($index_path)) {
                    $pi = self::cache_read('players_index');
                    if (is_array($pi) && isset($pi['players'])) {
                        $players_index_meta = [
                            'players_index_generated'=> filemtime($index_path),
                            'players_index_players'=> count($pi['players']),
                            'top_movements_count'=> isset($pi['movements']) && is_array($pi['movements']) ? count($pi['movements']) : 0
                        ];
                    }
                }
            }
            return [
                'status'   => $m['status'],
                'total'    => $total,
                'done'     => count($m['completed']),
                'progress' => $progress,
                'errors'   => $m['errors'],
                'ready'    => self::is_cache_ready(),
                'enabled'  => self::is_enabled(),
                'players_cached' => $players_cached,
                'rankings_base_completed' => $rankings_base_completed,
                'rankings_temp_completed' => $rankings_temp_completed,
                'rankings_enqueued' => $rankings_enqueued,
                'mods_found' => $mods_found,
                'temps_found' => $temps_found,
                'tasks_pending' => $total - count($m['completed']),
                'last_completed' => $m['last_completed'] ?? null,
                'token_fp' => $token_fp,
                'login_meta' => $login_meta,
                'modalidades_count' => $modalidades_count,
                'torneos_count' => $torneos_count,
                'campeones_count' => $campeones_count
                ,'players_detected' => $players_detected_count
                ,'coverage_pct' => $coverage_pct
                ,'players_index_meta' => $players_index_meta
            ];
        }
    }
}
