<?php
/**
 * Handles Font Awesome specific optimizations
 */
class MACP_Font_Awesome_Processor {
    public function process($css) {
        // Preserve Font Awesome specific rules
        if (strpos($css, '.fa-') !== false || strpos($css, 'FontAwesome') !== false) {
            return $css;
        }
        return $css;
    }
}