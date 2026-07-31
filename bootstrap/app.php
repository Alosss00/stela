<?php
/**
 * Application Bootstrap / Initialization File
 * 
 * Central bootstrapper that loads configuration, database models,
 * helpers, services, and session settings.
 */

// Prevent direct script execution if accessed via URL
if (basename($_SERVER['PHP_SELF'] ?? '') === 'app.php') {
    die('Direct access not permitted');
}

// 1. Load Configurations
require_once dirname(__DIR__) . '/config/app.php';

// 2. Load Helpers
require_once dirname(__DIR__) . '/app/Helpers/url_helper.php';
require_once dirname(__DIR__) . '/app/Helpers/i18n_helper.php';

// 3. Load Models
require_once dirname(__DIR__) . '/app/Models/Database.php';

// 4. Load Services
require_once dirname(__DIR__) . '/app/Services/NotificationService.php';
if (file_exists(dirname(__DIR__) . '/app/Services/ElasticsearchService.php')) {
    require_once dirname(__DIR__) . '/app/Services/ElasticsearchService.php';
}

// Note: auth_helper.php is included on pages that require authentication checks.
