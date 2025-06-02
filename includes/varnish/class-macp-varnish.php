<?php
class MACP_Varnish {
    private $varnish_servers = [];
    private $varnish_port = 6081;
    private $purge_method = 'PURGE';
    private $cache_lifetime = 604800; // 7 days
    private $excluded_params = ['__SID', 'noCache'];
    private $excluded_paths = [
        '^/my-account/',
        '/cart/',
        '/checkout/',
        'wp-login.php'
    ];

    public function __construct() {
        $this->init_varnish_config();
        $this->init_hooks();
    }

    private function init_varnish_config() {
        $this->varnish_servers = get_option('macp_varnish_servers', ['127.0.0.1']);
        $this->varnish_port = get_option('macp_varnish_port', 6081);
        $this->cache_lifetime = get_option('macp_varnish_cache_lifetime', 604800);
        $this->excluded_params = get_option('macp_varnish_excluded_params', ['__SID', 'noCache']);
        $this->excluded_paths = get_option('macp_varnish_excluded_paths', $this->excluded_paths);
        
        $this->purge_headers = [
            'X-Purge-Method' => 'regex',
            'X-MACP-Host' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
            'X-MACP-Cache-Tags' => get_option('macp_varnish_cache_tag_prefix', '96aa')
        ];
    }

    private function init_hooks() {
        // Add cache control headers
        add_action('template_redirect', [$this, 'set_cache_headers']);
        
        // Handle cache purging
        add_action('macp_clear_page_cache', [$this, 'purge_url']);
        add_action('macp_clear_all_cache', [$this, 'purge_all']);
        
        // Add canonical URLs to prevent duplicate content
        add_action('wp_head', [$this, 'add_canonical_url'], 1);
        
        // Handle WooCommerce-specific caching
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_after_cart_update', [$this, 'clear_cart_cache']);
            add_action('woocommerce_checkout_update_order_review', [$this, 'clear_checkout_cache']);
        }
    }

    public function set_cache_headers() {
        // Skip caching for excluded paths
        foreach ($this->excluded_paths as $path) {
            if (preg_match('#' . $path . '#', $_SERVER['REQUEST_URI'])) {
                header('X-MACP-Cache: BYPASS');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                return;
            }
        }

        // Skip caching if excluded parameters are present
        foreach ($this->excluded_params as $param) {
            if (isset($_GET[$param])) {
                header('X-MACP-Cache: BYPASS');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                return;
            }
        }

        if (!is_user_logged_in()) {
            header('X-MACP-Cache: ACTIVE');
            header('Cache-Control: public, max-age=' . $this->cache_lifetime);
            header('X-MACP-Cache-TTL: ' . $this->cache_lifetime);
            header('Vary: Accept-Encoding, Cookie');
        } else {
            header('X-MACP-Cache: BYPASS');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
    }

    public function add_canonical_url() {
        if (is_singular()) {
            $canonical = get_permalink();
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }
    }

    public function purge_url($url) {
        if (empty($url)) return false;

        foreach ($this->varnish_servers as $server) {
            $parsed_url = parse_url($url);
            $purge_url = $parsed_url['path'];
            
            if (isset($parsed_url['query'])) {
                $purge_url .= '?' . $parsed_url['query'];
            }

            $headers = $this->purge_headers;
            $headers['Host'] = $parsed_url['host'];

            $this->send_purge_request($server, $purge_url, $headers);
        }

        // Also clear Redis cache for this URL
        $redis = new MACP_Redis();
        $redis->delete_pattern('page:' . md5($url));

        return true;
    }

    public function purge_all() {
        foreach ($this->varnish_servers as $server) {
            $this->send_purge_request($server, '/.*', array_merge(
                $this->purge_headers,
                ['X-Purge-Method' => 'regex']
            ));
        }

        // Clear Redis cache
        $redis = new MACP_Redis();
        $redis->flush_all();
    }

    private function send_purge_request($server, $url, $headers) {
        $sock = fsockopen($server, $this->varnish_port, $errno, $errstr, 2);
        
        if (!$sock) {
            MACP_Debug::log("Failed to connect to Varnish: $errstr ($errno)");
            return false;
        }

        $request = "{$this->purge_method} $url HTTP/1.1\r\n";
        foreach ($headers as $key => $value) {
            $request .= "$key: $value\r\n";
        }
        $request .= "Connection: Close\r\n\r\n";

        fwrite($sock, $request);
        $response = fgets($sock);
        fclose($sock);

        return strpos($response, '200 OK') !== false;
    }

    public function clear_cart_cache() {
        $this->purge_url(wc_get_cart_url());
    }

    public function clear_checkout_cache() {
        $this->purge_url(wc_get_checkout_url());
    }
}