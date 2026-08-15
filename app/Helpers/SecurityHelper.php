<?php

/**
 * HTML Escaping Function to prevent XSS
 *
 * This function should be used to wrap all user-generated content
 * before echoing it into the HTML view.
 * 
 * @param string|null $value The string to escape
 * @return string The escaped string
 */
if (!function_exists('e')) {
    function e($value) {
        if (is_null($value)) {
            return '';
        }
        
        // Convert to string and escape special characters to HTML entities
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
