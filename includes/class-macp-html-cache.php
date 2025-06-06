<?php
require_once MACP_PLUGIN_DIR . 'includes/class-macp-debug.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-filesystem.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-url-helper.php';
require_once MACP_PLUGIN_DIR . 'includes/class-macp-cache-helper.php';
require_once MACP_PLUGIN_DIR . 'includes/metrics/class-macp-metrics-recorder.php';
require_once MACP_PLUGIN_DIR . 'includes/html/processors/class-macp-html-processor.php';
require_once MACP_PLUGIN_DIR . 'includes/minify/class-macp-minify-html.php';

class MACP_HTML_Cache {
    private $cache_dir;
    private $excluded_urls;
    private $css_optimizer;
    private $redis;
    private $metrics_recorder;
    private $html_processor;
    private $cache_lifetime = 604800; // 7 days to match Varnish

    public function __construct($redis = null) {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/macp/';
        $this->excluded_urls = $this->get_excluded_urls();
        $this->redis = $redis;
        $this->metrics_recorder = new MACP_Metrics_Recorder();
        $this->html_processor = new MACP_HTML_Processor();
        
        if (get_option('macp_remove_unused_css', 0)) {
            $this->css_optimizer = new MACP_CSS_Optimizer();
        }
        
        $this->ensure_cache_directory();
    }

    public function should_cache_page() {
        // Never cache admin pages or logged-in users
        if (is_admin() || is_user_logged_in()) {
            return false;
        }

        if (!MACP_Cache_Helper::is_cacheable_request()) {
            return false;
        }

        // Check excluded URLs
        $current_url = $_SERVER['REQUEST_URI'];
        foreach ($this->excluded_urls as $excluded_url) {
            if (strpos($current_url, $excluded_url) !== false) {
                MACP_Debug::log("Not caching: Excluded URL pattern found - {$excluded_url}");
                return false;
            }
        }

        // Don't cache search results for SEO
        if (is_search()) {
            return false;
        }

        // Don't cache paginated archives beyond page 1
        if (is_archive() && get_query_var('paged') > 1) {
            return false;
        }

        return true;
    }

    public function start_buffer() {
        if ($this->should_cache_page()) {
            ob_start([$this, 'cache_output']);
        }
    }

    public function cache_output($buffer) {
        if (strlen($buffer) < 255) {
            $this->metrics_recorder->record_miss('html');
            return $buffer;
        }

        // Add canonical URLs for SEO
        if (is_singular() && !has_action('wp_head', 'rel_canonical')) {
            $canonical_url = get_permalink();
            $canonical_tag = sprintf('<link rel="canonical" href="%s" />', esc_url($canonical_url));
            $buffer = preg_replace('/<\/head>/', $canonical_tag . "\n</head>", $buffer);
        }

        // Process HTML before caching
        $buffer = $this->html_processor->process($buffer);

        // Get cache paths
        $cache_key = MACP_Cache_Helper::get_cache_key();
        $cache_paths = [
            'html' => MACP_Cache_Helper::get_cache_path($cache_key),
            'gzip' => MACP_Cache_Helper::get_cache_path($cache_key, true)
        ];

        // Save uncompressed version
        if (!MACP_Filesystem::write_file($cache_paths['html'], $buffer)) {
            $this->metrics_recorder->record_miss('html');
            return $buffer;
        }

        $this->metrics_recorder->record_hit('html');
        
        // Save gzipped version if enabled
        if (get_option('macp_enable_gzip', 1)) {
            $gzipped = gzencode($buffer, 9);
            if ($gzipped) {
                MACP_Filesystem::write_file($cache_paths['gzip'], $gzipped);
            }
        }

        // Store in Redis for faster access
        if ($this->redis) {
            $this->redis->set('html_' . $cache_key, $buffer, $this->cache_lifetime);
        }

        // Add cache control headers
        header('X-MACP-Cache: HIT');
        header('X-MACP-Cache-TTL: ' . $this->cache_lifetime);
        header('Cache-Control: public, max-age=' . $this->cache_lifetime);
        header('Vary: Accept-Encoding');

        return $buffer;
    }

    public function get_cached_content($key) {
        // Try Redis first (fastest)
        if ($this->redis) {
            $content = $this->redis->get('html_' . $key);
            if ($content) {
                $this->metrics_recorder->record_hit('html');
                return $content;
            }
        }

        // Try file cache
        $cache_file = MACP_Cache_Helper::get_cache_path($key);
        if (file_exists($cache_file)) {
            $this->metrics_recorder->record_hit('html');
            $content = file_get_contents($cache_file);
            
            // Repopulate Redis
            if ($content && $this->redis) {
                $this->redis->set('html_' . $key, $content, $this->cache_lifetime);
            }
            
            return $content;
        }

        $this->metrics_recorder->record_miss('html');
        return false;
    }

    private function get_excluded_urls() {
        return array_merge([
            'wp-login.php',
            'wp-admin',
            'wp-cron.php',
            'wp-content',
            'wp-includes',
            'xmlrpc.php',
            'wp-api',
            '/cart/',
            '/checkout/',
            '/my-account/',
            'add-to-cart',
            'logout',
            'lost-password',
            'register'
        ], get_option('macp_excluded_urls', []));
    }

    private function ensure_cache_directory() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            file_put_contents($this->cache_dir . 'index.php', '<?php // Silence is golden');
        }
    }

    public function clear_cache($post_id = null) {
        if ($post_id) {
            MACP_Cache_Helper::clear_page_cache($post_id);
            
            // Clear related URLs (archives, home, etc.)
            $this->clear_related_caches($post_id);
        } else {
            array_map('unlink', glob($this->cache_dir . '*.{html,gz}', GLOB_BRACE));
            
            // Clear all Redis HTML cache
            if ($this->redis) {
                $this->redis->delete_pattern('html_*');
            }
        }
    }

    private function clear_related_caches($post_id) {
        // Clear home page cache
        $this->clear_url_cache(home_url('/'));

        // Clear archive pages if post type has archives
        $post_type = get_post_type($post_id);
        if ($post_type) {
            $archive_url = get_post_type_archive_link($post_type);
            if ($archive_url) {
                $this->clear_url_cache($archive_url);
            }
        }

        // Clear category and tag archives
        $terms = wp_get_post_terms($post_id, get_object_taxonomies($post_type));
        foreach ($terms as $term) {
            $term_link = get_term_link($term);
            if (!is_wp_error($term_link)) {
                $this->clear_url_cache($term_link);
            }
        }
    }

    private function clear_url_cache($url) {
        $cache_key = MACP_Cache_Helper::get_cache_key($url);
        $cache_file = MACP_Cache_Helper::get_cache_path($cache_key);
        $gzip_file = MACP_Cache_Helper::get_cache_path($cache_key, true);

        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
        if (file_exists($gzip_file)) {
            unlink($gzip_file);
        }

        if ($this->redis) {
            $this->redis->delete('html_' . $cache_key);
        }
    }
}