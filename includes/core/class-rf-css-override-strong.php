<?php
/**
 * RF CSS Override Strong (integrated from former MU plugin rf-css-override-strong.php)
 * Objetivo: bloquear encolado/salida de CSS legacy y cargar bundles purgados + limpieza agresiva.
 */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('RF_CSS_Override_Strong')) {
    class RF_CSS_Override_Strong {
        protected static $booted = false;
        public static function boot() {
            if (self::$booted) return; self::$booted = true;
            if (!defined('RF_CSS_OVERRIDE_STRONG')) define('RF_CSS_OVERRIDE_STRONG', true);
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_purged'], 9);
            add_action('wp_enqueue_scripts', [__CLASS__, 'dequeue_legacy_handles'], 20);
            add_action('wp_print_styles',   [__CLASS__, 'dequeue_legacy_handles'], PHP_INT_MAX);
            add_filter('style_loader_src',  [__CLASS__, 'filter_style_src'], PHP_INT_MAX, 2);
            add_filter('style_loader_tag',  [__CLASS__, 'filter_style_tag'], PHP_INT_MAX, 4);
            add_action('template_redirect', [__CLASS__, 'buffer_output'], PHP_INT_MAX);
            add_action('wp_head',           [__CLASS__, 'dom_kill_script'], 0);
        }
        protected static function cfg() {
            $plugin_rel = '/wp-content/plugins/ranking-futbolin/';
            return [
                'plugin_rel'    => $plugin_rel,
                'legacy_dir'    => $plugin_rel . 'assets/css/',
                'handle_prefix' => 'futbolin-style-',
                'except_exact'  => [
                    $plugin_rel . 'assets/css/rf-live.css',
                    $plugin_rel . 'assets/css/90-compat-override.css',
                ],
            ];
        }
        protected static function rel_path($src) {
            if (!$src) return '';
            if (strpos($src, '//') === 0) $src = (is_ssl() ? 'https:' : 'http:') . $src;
            if (strpos($src, '/') === 0 && strpos($src, '/wp-content/') === 0) {
                $path = parse_url(home_url($src), PHP_URL_PATH);
            } else { $path = parse_url($src, PHP_URL_PATH); }
            return $path ?: '';
        }
        protected static function is_blocked($src) {
            $cfg = self::cfg();
            $rel = self::rel_path($src);
            if (in_array($rel, $cfg['except_exact'], true)) return false;
            if (strpos($rel, $cfg['legacy_dir']) === 0) return true;
            if (strpos((string)$src, $cfg['legacy_dir']) !== false || strpos((string)$src, 'wp-content/plugins/ranking-futbolin/assets/css/') !== false) return true;
            return false;
        }
        public static function enqueue_purged() {
            $ver = defined('FUTBOLIN_API_VERSION') ? FUTBOLIN_API_VERSION : '0.0.0';
            $base = plugins_url('dist/assets/css-purged/', dirname(__FILE__,2) . '/ranking-futbolin.php');
            $enqueue = function($file, $handle) use ($base, $ver) {
                wp_enqueue_style($handle, $base . $file, array(), $ver, 'all');
            };
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $qs  = $_GET;
            $enqueue('core.css', 'rf-core');
            $enqueue('components.css', 'rf-components');
            if (strpos($uri, '/perfil-jugador') !== false) { $enqueue('perfil.css', 'rf-perfil'); }
            if (strpos($uri, '/futbolin-ranking') !== false && empty($qs['view'])) { $enqueue('ranking.css', 'rf-ranking'); }
            $view = isset($qs['view']) ? $qs['view'] : '';
            switch ($view) {
                case 'tournaments':
                case 'tournament-stats':
                    $enqueue('tournaments.css', 'rf-tournaments'); break;
                case 'finals_reports':
                    $enqueue('tournaments.css', 'rf-tournaments');
                    $enqueue('ranking.css', 'rf-ranking'); break;
                case 'global-stats':
                    $enqueue('stats.css', 'rf-stats'); break;
                case 'info':
                    $enqueue('info.css', 'rf-info'); break;
            }
        }
        public static function dequeue_legacy_handles() {
            $cfg = self::cfg(); global $wp_styles;
            if (empty($wp_styles) || empty($wp_styles->registered)) return;
            foreach ($wp_styles->registered as $handle => $obj) {
                if (strpos($handle, $cfg['handle_prefix']) === 0 || self::is_blocked($obj->src)) {
                    wp_dequeue_style($handle); wp_deregister_style($handle);
                }
            }
        }
        public static function filter_style_src($src, $handle){ return self::is_blocked($src) ? false : $src; }
        public static function filter_style_tag($html, $handle, $href, $media){ return self::is_blocked($href) ? '' : $html; }
        public static function buffer_output(){
            $cfg = self::cfg();
            $blocked = preg_quote($cfg['legacy_dir'], '/');
            $allowed = array_map(function($p){ return preg_quote($p, '/'); }, $cfg['except_exact']);
            $allowed_re = $allowed ? '(?!' . implode('|', $allowed) . ')' : '';
            ob_start(function($html) use ($blocked, $allowed_re){
                $re = '/<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\']' . $allowed_re . '([^"\']*' . $blocked . '[^"\']*)["\'][^>]*>\s*/i';
                $html = preg_replace($re, '', $html);
                $re2 = '/<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\']' . $allowed_re . '([^"\']*wp-content\/plugins\/ranking-futbolin\/assets\/css\/[^"\']*)["\'][^>]*>\s*/i';
                return preg_replace($re2, '', $html);
            });
        }
        public static function dom_kill_script(){
            ?>
<script>/* RF CSS Override Strong (integrado) */(function(){function i(h){return /\/wp-content\/plugins\/ranking-futbolin\/assets\/css\//.test(h||"");}function k(l){try{l.parentNode&&l.parentNode.removeChild(l);}catch(e){} if(l&&l.href)console.info('RF CSS override strong: killed',l.href);}document.querySelectorAll('link[rel="stylesheet"]').forEach(function(l){if(l.href&&i(l.href)&&!/\/(rf-live\.css|90-compat-override\.css)$/.test(l.href))k(l);});var o=new MutationObserver(function(m){m.forEach(function(x){(x.addedNodes||[]).forEach(function(n){if(n.tagName==='LINK'&&(n.rel||'').indexOf('stylesheet')>-1&&n.href&&i(n.href))k(n);});});});o.observe(document.documentElement,{childList:true,subtree:true});})();</script>
<?php
        }
    }
}
