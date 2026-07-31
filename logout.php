<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/bootstrap/app.php';

session_destroy();
redirect(BASE_URL . '/index.php');
