<?php
use voku\helper\HtmlMin;

class MACP_Minify_HTML {
    private static $instance = null;
    private $minifier;
    private $cache_dir;

    public function __construct() {
        $this->minifier = new HtmlMin();
        $this->configure_minifier();
        $this->cache_dir = WP_CONTENT_DIR . '/cache/min/';
        $this->ensure_cache_directory();
    }

    private function ensure_cache_directory() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    private function configure_minifier() {
        $this->minifier
            ->doOptimizeViaHtmlDomParser(true)
            ->doRemoveComments(true)
            ->doSumUpWhitespace(true)
            ->doRemoveWhitespaceAroundTags(true)
            ->doOptimizeAttributes(true)
            ->doRemoveHttpPrefixFromAttributes(true)
            ->doRemoveDefaultAttributes(true)
            ->doRemoveEmptyAttributes(true)
            ->doRemoveValueFromEmptyInput(true)
            ->doSortCssClassNames(true)
            ->doSortHtmlAttributes(true)
            ->doRemoveSpacesBetweenTags(true)
            ->doKeepBrokenHtml(true);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function minify($html) {
        if (empty($html)) return $html;

        try {
            // Preserve conditional comments and scripts
            $preserved = $this->preserve_content($html);
            
            // Minify HTML
            $minified = $this->minifier->minify($html);
            
            // Restore preserved content
            $minified = $this->restore_content($minified, $preserved);
            
            return $minified;
        } catch (Exception $e) {
            error_log('MACP HTML Minification Error: ' . $e->getMessage());
            return $html;
        }
    }

    private function preserve_content($html) {
        $preserved = [];
        
        // Preserve conditional comments
        $html = preg_replace_callback('/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is', 
            function($matches) use (&$preserved) {
                $key = '<!--PRESERVED' . count($preserved) . '-->';
                $preserved[$key] = $matches[0];
                return $key;
            }, 
            $html
        );

        // Preserve scripts
        $html = preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is',
            function($matches) use (&$preserved) {
                $key = '<!--PRESERVED' . count($preserved) . '-->';
                $preserved[$key] = $matches[0];
                return $key;
            },
            $html
        );

        return ['html' => $html, 'preserved' => $preserved];
    }

    private function restore_content($html, $data) {
        return strtr($html, $data['preserved']);
    }
}