<?php
if (!defined('ABSPATH')) exit;

class Futbolin_Logger {
    public static function is_enabled(): bool {
        $opts = get_option('mi_plugin_futbolin_options', []);
        $on = isset($opts['rf_logging_enabled']) ? (int)!!$opts['rf_logging_enabled'] : 0;
        return $on === 1;
    }

    // Umbrales internos por nivel (plugin)
    public static function level_threshold(): int { return 10; /* con archivos por nivel, registramos todo y filtramos por tier */ }

    public static function max_bytes(): int {
        // Rediseño: tamaño fijo 19MB por archivo
        return 19 * 1024 * 1024;
    }

    public static function get_log_dir(): string {
        $up = wp_upload_dir();
        $dir = trailingslashit($up['basedir']) . 'ranking-futbolin/logs';
        if (!is_dir($dir)) { wp_mkdir_p($dir); }
        return $dir;
    }

    public static function get_log_file(): string { return self::get_log_file_for_tier('high'); }

    public static function get_log_file_for_tier(string $tier): string {
        $tier = strtolower($tier);
        if (!in_array($tier, ['low','medium','high'], true)) { $tier = 'high'; }
        $fname = 'rf-' . $tier . '.log';
        return trailingslashit(self::get_log_dir()) . $fname;
    }

    public static function get_log_dir_url(): string {
        $up = wp_upload_dir();
        return trailingslashit($up['baseurl']) . 'ranking-futbolin/logs';
    }

    /** Devuelve ruta a wp-content/debug.log si está activo WP_DEBUG_LOG */
    public static function get_wp_debug_log_file(): ?string {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_string(WP_DEBUG_LOG)) {
                return WP_DEBUG_LOG;
            }
            if (defined('WP_CONTENT_DIR')) {
                return trailingslashit(WP_CONTENT_DIR) . 'debug.log';
            }
        }
        return null;
    }

    private static function lvl_code(string $level): int {
        switch (strtolower($level)) {
            case 'debug': return 10;
            case 'info': return 20;
            case 'warning': return 30;
            case 'error': return 40;
        }
        return 20;
    }

    public static function log(string $level, string $message, array $context = []): void {
        if (!self::is_enabled()) return;
        // Con archivos por nivel: siempre escribimos en HIGH; en MEDIUM si level>=info; en LOW si level>=error
        $ts = date('Y-m-d H:i:s');
        $req = isset($_SERVER['REQUEST_METHOD']) ? ($_SERVER['REQUEST_METHOD'].' '.($_SERVER['REQUEST_URI'] ?? '')) : 'CLI';
        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $uname = ($user && isset($user->user_login)) ? $user->user_login : '-';
        $line = sprintf('[%s] %-7s [%s] %s %s', $ts, strtoupper($level), $uname, $message, $context ? json_encode($context, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : '');
        $lvl = self::lvl_code($level);
        // high
        self::append_with_rotation(self::get_log_file_for_tier('high'), $line);
        // medium: info/warn/error
        if ($lvl >= 20) { self::append_with_rotation(self::get_log_file_for_tier('medium'), $line); }
        // low: errors
        if ($lvl >= 40) { self::append_with_rotation(self::get_log_file_for_tier('low'), $line); }
    }

    private static function append_with_rotation(string $file, string $line): void {
        // rotate if needed
        if (file_exists($file) && filesize($file) > self::max_bytes()) {
            self::rotate_file($file);
        }
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function rotate_file(string $file): void {
        $ts = date('Ymd-His');
        $rot = $file . '-' . $ts . '.log';
        // Move current to rotated name
        @rename($file, $rot);
        // Compress rotated file to .gz
        $gz = $rot . '.gz';
        if (function_exists('gzopen')) {
            $in = @fopen($rot, 'rb');
            if ($in) {
                $gzf = @gzopen($gz, 'wb9');
                if ($gzf) {
                    while (!feof($in)) { $buf = fread($in, 8192); if ($buf !== false) { @gzwrite($gzf, $buf); } }
                    @gzclose($gzf);
                }
                @fclose($in);
            }
            // Remove uncompressed rotated file
            @unlink($rot);
        }
        // Optional prune: keep last 20 rotated files to avoid unbounded growth
        self::prune_old_rotations(dirname($file), basename($file));
    }

    private static function prune_old_rotations(string $dir, string $base): void {
        $pattern = $base . '-*.log.gz';
        $files = glob(trailingslashit($dir) . $pattern);
        if (!is_array($files)) return;
        // Sort by mtime desc
        usort($files, function($a,$b){ return @filemtime($b) <=> @filemtime($a); });
        $keep = 20;
        for ($i=$keep; $i<count($files); $i++) { @unlink($files[$i]); }
    }

    public static function clear(string $type = 'plugin'): bool {
        $file = null;
        if ($type === 'wp') { $file = self::get_wp_debug_log_file(); }
        elseif ($type === 'plugin' || $type === 'plugin-high') { $file = self::get_log_file_for_tier('high'); }
        elseif ($type === 'plugin-medium') { $file = self::get_log_file_for_tier('medium'); }
        elseif ($type === 'plugin-low') { $file = self::get_log_file_for_tier('low'); }
        if (!$file) return false;
        if (!file_exists($file)) return true;
        return (bool)@file_put_contents($file, '');
    }

    public static function tail(string $type = 'plugin', int $maxBytes = 65536): array {
        // Soporta fuentes: wp | plugin-low | plugin-medium | plugin-high | plugin-combined
        if ($type === 'plugin-combined') {
            $a = self::tail('plugin-high', $maxBytes);
            $bFile = self::get_wp_debug_log_file();
            $b = ['text'=>''];
            if ($bFile && file_exists($bFile)) {
                $b = self::tail('wp', $maxBytes);
            }
            $txt = trim(($a['text'] ?? ''));
            $txt2 = trim(($b['text'] ?? ''));
            $full = $txt2 ? ($txt . "\n--- WP debug.log ---\n" . $txt2) : $txt;
            return [
                'path' => 'combined',
                'size' => strlen($full),
                'mtime'=> time(),
                'text' => $full !== '' ? $full : '(sin datos)'
            ];
        }

        $file = null;
        if ($type === 'wp') { $file = self::get_wp_debug_log_file(); }
        elseif ($type === 'plugin' || $type === 'plugin-high') { $file = self::get_log_file_for_tier('high'); }
        elseif ($type === 'plugin-medium') { $file = self::get_log_file_for_tier('medium'); }
        elseif ($type === 'plugin-low') { $file = self::get_log_file_for_tier('low'); }
        if (!$file || !file_exists($file)) return ['path'=>$file, 'size'=>0, 'mtime'=>0, 'text'=>'(sin datos)'];
        $size = filesize($file);
        if ($size === 0) {
            return ['path'=>$file, 'size'=>0, 'mtime'=>@filemtime($file), 'text'=>'(sin datos)'];
        }
        $fp = @fopen($file, 'rb');
        if (!$fp) return ['path'=>$file, 'size'=>$size, 'mtime'=>@filemtime($file), 'text'=>'(no se pudo leer el archivo)'];
        $read = $size > $maxBytes ? $maxBytes : $size;
        if ($read <= 0) { // salvaguarda por si parámetros anómalos
            fclose($fp);
            return ['path'=>$file, 'size'=>$size, 'mtime'=>@filemtime($file), 'text'=>'(sin datos)'];
        }
        if ($size > $maxBytes) {
            $seek = -1 * $maxBytes;
            // fseek con offset negativo sólo válido en SEEK_END y si size>=maxBytes ya garantizado
            @fseek($fp, $seek, SEEK_END);
        }
        $data = '';
        // Leer en bloques para robustez
        $remaining = $read;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, $remaining > 8192 ? 8192 : $remaining);
            if ($chunk === false) { break; }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($fp);
        // Mostrar solo las últimas ~400 líneas para no saturar
        $lines = explode("\n", (string)$data);
        $lines = array_slice($lines, -400);
        return ['path'=>$file, 'size'=>$size, 'mtime'=>@filemtime($file), 'text'=>implode("\n", $lines)];
    }

    public static function clear_all(): bool {
        $ok = true;
        foreach (['low','medium','high'] as $t) {
            $f = self::get_log_file_for_tier($t);
            if (file_exists($f) && @file_put_contents($f, '') === false) { $ok = false; }
        }
        return $ok;
    }

    public static function list_current_log_files(): array {
        $out = [];
        foreach (['low','medium','high'] as $t) {
            $f = self::get_log_file_for_tier($t);
            $out[] = [
                'tier' => $t,
                'path' => $f,
                'url'  => trailingslashit(self::get_log_dir_url()) . basename($f),
                'size' => file_exists($f) ? filesize($f) : 0,
                'mtime'=> file_exists($f) ? @filemtime($f) : 0,
                'exists'=> file_exists($f),
            ];
        }
        return $out;
    }

    public static function prepare_zip_all(): ?array {
        $files = array_filter(self::list_current_log_files(), function($f){ return isset($f['exists']) && $f['exists'] && $f['size'] > 0; });
        $dir = self::get_log_dir();
        if (!is_dir($dir)) { @wp_mkdir_p($dir); }
        // Primero intentamos ZIP nativo
        if (class_exists('ZipArchive')) {
            $zipName = 'rf-logs-all-' . date('Ymd-His') . '.zip';
            $zipPath = trailingslashit($dir) . $zipName;
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($files as $f) { $zip->addFile($f['path'], basename($f['path'])); }
                $zip->close();
                return [
                    'zip_path' => $zipPath,
                    'zip_url'  => trailingslashit(self::get_log_dir_url()) . $zipName,
                    'files'    => array_values($files),
                ];
            }
        }
        // Fallback: TAR.GZ con PharData si está disponible
        if (class_exists('PharData')) {
            try {
                $tarName = 'rf-logs-all-' . date('Ymd-His') . '.tar';
                $tarPath = trailingslashit($dir) . $tarName;
                if (file_exists($tarPath)) { @unlink($tarPath); }
                $tar = new PharData($tarPath);
                foreach ($files as $f) { $tar->addFile($f['path'], basename($f['path'])); }
                $tar->compress(Phar::GZ);
                // Compressed file will be tar.gz
                $tgzPath = $tarPath . '.gz';
                // Remove the uncompressed tar
                if (file_exists($tarPath)) { @unlink($tarPath); }
                return [
                    'zip_path' => $tgzPath,
                    'zip_url'  => trailingslashit(self::get_log_dir_url()) . basename($tgzPath),
                    'files'    => array_values($files),
                ];
            } catch (Exception $e) {
                // continúa al siguiente fallback
            }
        }
        // Último fallback: crear un TXT combinado sencillo
        $comboName = 'rf-logs-all-' . date('Ymd-His') . '.log';
        $comboPath = trailingslashit($dir) . $comboName;
        $buff = '';
        foreach ($files as $f) {
            $buff .= "==== ".basename($f['path'])." ===="."\n";
            $buff .= @file_get_contents($f['path']) ?: '';
            $buff .= "\n\n";
        }
        @file_put_contents($comboPath, $buff);
        return [
            'zip_path' => $comboPath,
            'zip_url'  => trailingslashit(self::get_log_dir_url()) . $comboName,
            'files'    => array_values($files),
        ];
    }
}

if (!function_exists('rf_log')) {
    function rf_log(string $message, array $context = [], string $level = 'info'): void {
        if (class_exists('Futbolin_Logger')) {
            Futbolin_Logger::log($level, $message, $context);
        }
        // Extra: si WP_DEBUG está activo, duplicar a error_log con un prefijo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            @error_log('[RF] ' . $message . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : ''));
        }
    }
}

?>