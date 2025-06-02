<?php defined('ABSPATH') || exit; ?>

<div class="macp-card">
    <h2>JavaScript Optimization</h2>
    
    <!-- Defer JavaScript Section -->
    <div class="macp-optimization-section">
        <div class="macp-option-group">
            <label class="macp-toggle">
                <input type="checkbox" name="macp_enable_js_defer" value="1" <?php checked(get_option('macp_enable_js_defer', 0), 1); ?>>
                <span class="macp-toggle-slider"></span>
                Load JavaScript deferred
            </label>
            
            <div class="macp-exclusion-section">
                <h3>Scripts to Defer</h3>
                <p class="description">Enter script URLs to be deferred (one per line). These scripts will load with the defer attribute.</p>
                <textarea name="macp_deferred_scripts" rows="5" class="large-text code"><?php 
                    $deferred_scripts = get_option('macp_deferred_scripts', []);
                    echo esc_textarea(implode("\n", $deferred_scripts)); 
                ?></textarea>
            </div>

            <div class="macp-exclusion-section">
                <h3>Exclude from Defer</h3>
                <p class="description">Enter script URLs to exclude from defer (one per line). WordPress admin scripts (/wp-admin/*) are automatically excluded.</p>
                <textarea name="macp_defer_excluded_scripts" rows="5" class="large-text code"><?php 
                    $excluded_scripts = get_option('macp_defer_excluded_scripts', []);
                    if (!in_array('/wp-admin/*', $excluded_scripts)) {
                        array_unshift($excluded_scripts, '/wp-admin/*');
                    }
                    echo esc_textarea(implode("\n", $excluded_scripts)); 
                ?></textarea>
            </div>
        </div>

        <!-- Delay JavaScript Section -->
        <div class="macp-option-group" style="margin-top: 20px;">
            <label class="macp-toggle">
                <input type="checkbox" name="macp_enable_js_delay" value="1" <?php checked(get_option('macp_enable_js_delay', 0), 1); ?>>
                <span class="macp-toggle-slider"></span>
                Delay JavaScript execution
            </label>
            
            <div class="macp-exclusion-section">
                <h3>Scripts to Delay</h3>
                <p class="description">Enter script URLs to be delayed (one per line). These scripts will load after user interaction.</p>
                <textarea name="macp_delay_scripts" rows="5" class="large-text code"><?php 
                    $delay_scripts = get_option('macp_delay_scripts', []);
                    echo esc_textarea(implode("\n", array_filter($delay_scripts))); 
                ?></textarea>
            </div>

            <div class="macp-exclusion-section">
                <h3>Exclude from Delay</h3>
                <p class="description">Enter script URLs to exclude from delay (one per line). WordPress admin scripts (/wp-admin/*) are automatically excluded.</p>
                <textarea name="macp_delay_excluded_scripts" rows="5" class="large-text code"><?php 
                    $delay_excluded = get_option('macp_delay_excluded_scripts', []);
                    // Remove duplicate /wp-admin/* entries
                    $delay_excluded = array_unique($delay_excluded);
                    // Ensure /wp-admin/* is always first
                    if (($key = array_search('/wp-admin/*', $delay_excluded)) !== false) {
                        unset($delay_excluded[$key]);
                    }
                    array_unshift($delay_excluded, '/wp-admin/*');
                    echo esc_textarea(implode("\n", $delay_excluded));
                ?></textarea>
            </div>
        </div>
    </div>

    <div class="notice notice-info inline" style="margin-top: 15px;">
        <p><strong>Tips:</strong></p>
        <ul>
            <li>Defer: Scripts will load in parallel but execute after HTML parsing</li>
            <li>Delay: Scripts will only load after user interaction (click, scroll, etc.)</li>
            <li>WordPress admin scripts (/wp-admin/*) are automatically excluded</li>
            <li>jQuery and core WordPress scripts are excluded by default</li>
            <li>Use complete URLs or unique parts of URLs</li>
            <li>Critical scripts should be excluded from both defer and delay</li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle textarea changes
    $('.macp-exclusion-section textarea').on('change', function() {
        const $textarea = $(this);
        const option = $textarea.attr('name');
        const value = $textarea.val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'macp_save_textarea',
                option: option,
                value: value,
                nonce: macpAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Refresh the textarea content to show properly formatted list
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert('Failed to save settings');
                }
            },
            error: function() {
                alert('Failed to save settings');
            }
        });
    });
});
</script>