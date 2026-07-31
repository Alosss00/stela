<?php
$rootDir = dirname(__DIR__);
$viewsDir = $rootDir . '/resources/views';

function fixLayouts($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            fixLayouts($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = file_get_contents($path);
            $modified = false;

            if (strpos($content, "dirname(__DIR__, 2) . '/layouts/") !== false) {
                $content = str_replace("dirname(__DIR__, 2) . '/layouts/", "dirname(__DIR__) . '/layouts/", $content);
                $modified = true;
            }

            if ($modified) {
                file_put_contents($path, $content);
                echo "Fixed layout path in: {$path}\n";
            }
        }
    }
}

fixLayouts($viewsDir);
echo "Layout paths fix finished!\n";
