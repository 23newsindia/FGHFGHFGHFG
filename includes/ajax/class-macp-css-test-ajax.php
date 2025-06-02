<?php
class MACP_CSS_Test_Ajax {
    public function __construct() {
        add_action('wp_ajax_macp_test_unused_css', [$this, 'handle_test_request']);
    }

    public function handle_test_request() {
        try {
            if (!check_ajax_referer('macp_admin_nonce', 'nonce', false)) {
                throw new Exception('Invalid security token');
            }

            if (!current_user_can('manage_options')) {
                throw new Exception('Unauthorized access');
            }

            wp_send_json_success([
                'message' => 'CSS optimization features have been disabled'
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }
}
