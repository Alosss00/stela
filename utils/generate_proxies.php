<?php
$rootDir = dirname(__DIR__);

$modules = ['admin', 'dept', 'user', 'ktt', 'superadmin'];

foreach ($modules as $module) {
    $dir = $rootDir . '/pages/' . $module;
    if (!is_dir($dir)) continue;

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') continue;

        $targetFile = $dir . '/' . $file;
        $content = "<?php\nrequire_once dirname(__DIR__, 2) . '/bootstrap/app.php';\nrequire_once VIEW_PATH . '/{$module}/{$file}';\n";
        file_put_contents($targetFile, $content);
        echo "Created proxy for pages/{$module}/{$file}\n";
    }
}

// API Proxies
$apiDir = $rootDir . '/api';
if (is_dir($apiDir)) {
    $apiFiles = scandir($apiDir);
    foreach ($apiFiles as $file) {
        if ($file === '.' || $file === '..') continue;
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') continue;

        $targetFile = $apiDir . '/' . $file;
        $content = "<?php\nrequire_once dirname(__DIR__) . '/bootstrap/app.php';\nrequire_once VIEW_PATH . '/api/{$file}';\n";
        file_put_contents($targetFile, $content);
        echo "Created proxy for api/{$file}\n";
    }
}
