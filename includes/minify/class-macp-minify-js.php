<?php
use MatthiasMullie\Minify\JS;

class MACP_Minify_JS {
    private static $instance = null;
    private $minifier;
    private $cache_dir;

    public function __construct() {
        $this->minifier = new JS();
        $this->cache_dir = WP_CONTENT_DIR . '/cache/min/';
        $this->ensure_cache_directory();
    }

    private function ensure_cache_directory() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function minify($js) {
        if (empty($js)) return $js;

        try {
            // Preserve important comments
            $js = $this->preserve_important_comments($js);
            
            $this->minifier->add($js);
            $minified = $this->minifier->minify();
            
            // Restore preserved comments
            $minified = $this->restore_important_comments($minified);
            
            return $minified;
        } catch (Exception $e) {
            error_log('MACP JS Minification Error: ' . $e->getMessage());
            return $js;
        }
    }

    private function preserve_important_comments($js) {
        return preg_replace_callback('/\/\*![\s\S]*?\*\//', function($matches) {
            return '/*' . base64_encode($matches[0]) . '*/';
        }, $js);
    }

    private function restore_important_comments($js) {
        return preg_replace_callback('/\/\*([A-Za-z0-9+\/=]+)\*\//', function($matches) {
            return base64_decode($matches[1]);
        }, $js);
    }
}