<?php
/**
 * Defines CSS patterns for minification
 */
class MACP_CSS_Patterns {
    public static function get_patterns() {
        return [
            'comments' => [
                '/\/\*[^*]*\*+([^/*][^*]*\*+)*\//',  // Multi-line comments
                '/\/\/[^\n]*/'                        // Single-line comments
            ],
            'whitespace' => [
                '/\s+/'              => ' ',     // Multiple spaces to single
                '/\s*{\s*/'         => '{',     // Spaces around {
                '/\s*}\s*/'         => '}',     // Spaces around }
                '/\s*;\s*/'         => ';',     // Spaces around ;
                '/\s*,\s*/'         => ',',     // Spaces around ,
                '/\s*:\s*/'         => ':',     // Spaces around :
                '/;\}/'             => '}',     // Remove last semicolon
            ],
            'numbers' => [
                '/(\d+)\.0+(?=\D)/' => '$1',    // Remove trailing zeros
                '/0+\.(\d+)/'       => '.$1',   // Remove leading zeros
                '/(\s|:)0+(\.\d+)/' => '$1$2',  // Remove leading zeros with decimal
            ],
            'colors' => [
                '/#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3/i' => '#$1$2$3', // Shorten hex colors
            ]
        ];
    }
}