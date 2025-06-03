<?php
use MatthiasMullie\Minify\CSS;

class MACP_Minify_CSS {
    private static $instance = null;
    private $minifier;
    private $cache_dir;
    
    public function __construct() {
        $this->minifier = new CSS();
        $this->cache_dir = WP_CONTENT_DIR . '/cache/min/';
        $this->ensure_cache_directory();
    }
    
    private function ensure_cache_directory() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            file_put_contents($this->cache_dir . '.htaccess', 
                "Options -Indexes\n" .
                "<IfModule mod_headers.c>\n" .
                "    Header set Cache-Control 'max-age=31536000, public'\n" .
                "</IfModule>"
            );
        }
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function minify($css) {
        if (empty($css)) return $css;

        try {
            $this->minifier->add($css);
            $minified = $this->minifier->minify();
            
            // Additional optimizations
            $minified = $this->optimize_font_weights($minified);
            $minified = $this->optimize_zeros($minified);
            $minified = $this->optimize_colors($minified);
            
            return $minified;
        } catch (Exception $e) {
            error_log('MACP CSS Minification Error: ' . $e->getMessage());
            return $css;
        }
    }

    private function optimize_font_weights($css) {
        return preg_replace('/font-weight:\s*normal;/i', 'font-weight:400;', $css);
    }

    private function optimize_zeros($css) {
        $patterns = [
            '/(?<!\\\\)0px/' => '0',
            '/(?<!\\\\)0em/' => '0',
            '/(?<!\\\\)0rem/' => '0',
            '/(?<!\\\\)0%/' => '0',
            '/:\s*0 0 0 0;/' => ':0;',
            '/:\s*0 0 0;/' => ':0;',
            '/:\s*0 0;/' => ':0;'
        ];
        return preg_replace(array_keys($patterns), array_values($patterns), $css);
    }

    private function optimize_colors($css) {
        $patterns = [
            '/(?<!\\\\)#([a-f0-9])\\1([a-f0-9])\\2([a-f0-9])\\3/i' => '#$1$2$3',
            '/(?<!\\\\)rgb\\(([0-9]+),\\s*([0-9]+),\\s*([0-9]+)\\)/' => function($match) {
                return sprintf('#%02x%02x%02x', $match[1], $match[2], $match[3]);
            }
        ];
        return preg_replace_callback_array($patterns, $css);
    }
}