<?php
$directory = new RecursiveDirectoryIterator('C:\Users\USER\OneDrive\Documents\stela-2\resources');
$iterator = new RecursiveIteratorIterator($directory);

$formCount = 0;
$postCount = 0;
foreach ($iterator as $info) {
    if ($info->isFile() && $info->getExtension() === 'php') {
        $content = file_get_contents($info->getPathname());
        if (strpos($content, '<form') !== false && strpos($content, 'method="post"') !== false) {
            $formCount++;
        } elseif (strpos($content, '<form') !== false && strpos($content, 'method="POST"') !== false) {
            $formCount++;
        }
        
        if (strpos($content, 'REQUEST_METHOD') !== false && strpos($content, 'POST') !== false) {
            $postCount++;
        }
    }
}
echo "Files with POST forms: $formCount\n";
echo "Files with POST handling: $postCount\n";
