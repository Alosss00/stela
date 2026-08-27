<?php

/**
 * CSRF Protection Helper
 * Provides functions to generate and verify CSRF tokens.
 */

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token from the session.
     * Generates a new one if it doesn't exist.
     *
     * @return string
     */
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate an HTML hidden input field containing the CSRF token.
     *
     * @return string
     */
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Verify that the CSRF token in the request matches the one in the session.
     * Should be called at the top of any script that handles POST requests.
     *
     * @param string|null $token The token to verify (usually $_POST['csrf_token'])
     * @return bool True if valid, False otherwise
     */
    function verify_csrf_token($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }
        
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
