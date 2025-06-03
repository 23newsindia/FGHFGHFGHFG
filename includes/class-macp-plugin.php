<?php
class MACP_Plugin {
    private static $instance = null;
    private $css_minifier;
    private $js_minifier;
    private $html_minifier;
    private $redis;
    private $html_cache;
    private $admin;
    private $js_optimizer;
    private $admin_bar;
    private $settings_manager;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        // Initialize settings manager first
        $this->settings_manager = new MACP_Settings_Manager();
        
        // Initialize Redis
        $this->redis = new MACP_Redis();
        
        // Initialize minifiers
        $this->css_minifier = MACP_Minify_CSS::get_instance();
        $this->js_minifier = MACP_Minify_JS::get_instance();
        $this->html_minifier = MACP_Minify_HTML::get_instance();
        
        // Initialize other components
        $this->html_cache = new MACP_HTML_Cache($this->redis);
        $this->js_optimizer = new MACP_JS_Optimizer();
        $this->admin = new MACP_Admin($this->redis);
        $this->admin_bar = new MACP_Admin_Bar();

        $this->init_hooks();
    }

    private function init_hooks() {
        // Initialize caching based on settings
        add_action('init', [$this, 'initialize_caching'], 0);
        
        // Add minification hooks if enabled
        if (get_option('macp_minify_css', 0)) {
            add_filter('style_loader_tag', [$this->css_minifier, 'process_tag'], 10, 4);
        }
        
        if (get_option('macp_minify_js', 0)) {
            add_filter('script_loader_tag', [$this->js_minifier, 'process_tag'], 10, 3);
        }
        
        if (get_option('macp_minify_html', 0)) {
            add_action('template_redirect', function() {
                ob_start([$this->html_minifier, 'minify']);
            }, 1);
        }
        
        // Handle cache clearing
        add_action('save_post', [$this->html_cache, 'clear_cache']);
        add_action('comment_post', [$this->html_cache, 'clear_cache']);
        add_action('wp_trash_post', [$this->html_cache, 'clear_cache']);
        add_action('switch_theme', [$this->html_cache, 'clear_cache']);
    }

    public function initialize_caching() {
        if (get_option('macp_enable_html_cache', 1)) {
            $this->html_cache->start_buffer();
        }
    }
}