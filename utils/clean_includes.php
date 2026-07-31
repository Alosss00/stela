<?php
$rootDir = dirname(__DIR__);
$viewsDir = $rootDir . '/resources/views';

function cleanViews($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            cleanViews($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($path);
            $modified = false;

            // Replace header / footer includes
            if (preg_match("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/header\.php['\"];?#", $content)) {
                $content = preg_replace("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/header\.php['\"];?#", "require_once dirname(__DIR__, 2) . '/layouts/header.php';", $content);
                $modified = true;
            }
            if (preg_match("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/footer\.php['\"];?#", $content)) {
                $content = preg_replace("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/footer\.php['\"];?#", "require_once dirname(__DIR__, 2) . '/layouts/footer.php';", $content);
                $modified = true;
            }

            // Replace auth includes
            if (preg_match("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/auth\.php['\"];?#", $content)) {
                $content = preg_replace("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/auth\.php['\"];?#", "require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';", $content);
                $modified = true;
            }

            // Replace config / db / notifications / ElasticsearchService includes
            if (preg_match("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/config\.php['\"];?#", $content)) {
                $content = preg_replace("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/config\.php['\"];?#", "require_once dirname(__DIR__, 3) . '/bootstrap/app.php';", $content);
                $modified = true;
            }
            if (preg_match("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/(db|notifications|ElasticsearchService)\.php['\"];?#", $content)) {
                $content = preg_replace("#(require_once|include_once|require|include)\s+['\"](\.\./)+includes/(db|notifications|ElasticsearchService)\.php['\"];?#", "// Included via bootstrap/app.php", $content);
                $modified = true;
            }

            if ($modified) {
                file_put_contents($path, $content);
                echo "Cleaned includes in: {$path}\n";
            }
        }
    }
}

cleanViews($viewsDir);
echo "View includes cleaning finished!\n";
