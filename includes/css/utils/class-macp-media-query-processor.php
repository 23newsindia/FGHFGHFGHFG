<?php
/**
 * Handles media query processing
 */
class MACP_Media_Query_Processor {
    public function process($css) {
        // Preserve media queries while minifying their content
        return preg_replace_callback(
            '/@media[^{]+\{([^{}]*(?:{[^{}]*}[^{}]*)*)}/',
            function($matches) {
                $minified = preg_replace('/\s+/', ' ', $matches[1]);
                return '@media' . $matches[0] . '{' . trim($minified) . '}';
            },
            $css
        );
    }
}