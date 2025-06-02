<?php
class MACP_Script_Handler {
    private $excluded_scripts = [];
    private $deferred_scripts = [];

    public function __construct() {
        $this->deferred_scripts = get_option('macp_deferred_scripts', []);
        $this->excluded_scripts = get_option('macp_defer_excluded_scripts', []);
        $this->init_hooks();
    }

    private function init_hooks() {
        if (!is_admin()) {
            add_filter('script_loader_tag', [$this, 'process_script_tag'], 10, 3);
            add_action('admin_init', [$this, 'register_settings']);
        }
    }

    public function register_settings() {
        register_setting('macp_settings', 'macp_deferred_scripts', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_script_list']
        ]);
        
        register_setting('macp_settings', 'macp_defer_excluded_scripts', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_script_list']
        ]);
    }

    public function sanitize_script_list($value) {
        if (!is_array($value)) {
            $value = explode("\n", $value);
        }
        
        return array_filter(array_map('trim', $value));
    }

    public function process_script_tag($tag, $handle, $src) {
        // Skip if defer is not enabled
        if (!get_option('macp_enable_js_defer', 0)) {
            return $tag;
        }

        // Skip if script is excluded
        foreach ($this->excluded_scripts as $excluded) {
            if (!empty($excluded) && strpos($src, $excluded) !== false) {
                return $tag;
            }
        }

        // Check if script should be deferred
        foreach ($this->deferred_scripts as $deferred) {
            if (!empty($deferred) && strpos($src, $deferred) !== false) {
                if (strpos($tag, 'defer="defer"') === false) {
                    $tag = str_replace(' src=', ' defer="defer" src=', $tag);
                }
                break;
            }
        }

        return $tag;
    }
}
