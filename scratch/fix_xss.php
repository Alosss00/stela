<?php
// Fix XSS vulnerabilities globally in resources/
$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources'));
$count = 0;
foreach ($dir as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    $new_content = $content;
    
    $new_content = preg_replace_callback('/<\?=\s*\$(\w+(?:\[\'.*?\'\])?(?:->\w+)?)\s*\?>/', function($m) {
        $var = $m[1];
        $safe_vars = ['total_employees', 'active_count', 'resigned_count', 'inactive_count', 'i', 'verified_count', 'rejected_count', 'pending_verification', 'csrf_token', 'page_title', 'color', 'icon', 'table_key'];
        if (in_array($var, $safe_vars)) {
            return $m[0];
        }
        if (strpos($var, 'row[') !== false || strpos($var, 'message') !== false || strpos($var, 'error') !== false || strpos($var, 'csrf') !== false || strpos($var, 'es_total') !== false || strpos($var, 'draft_count') !== false || strpos($var, 'table_label') !== false) {
            return $m[0];
        }
        return '<?=' . sprintf(' htmlspecialchars($%s ?? "", ENT_QUOTES, "UTF-8") ', $var) . '?>';
    }, $new_content);
    
    if ($new_content !== $content) {
        file_put_contents($file->getPathname(), $new_content);
        $count++;
        echo "Fixed XSS in " . basename($file->getPathname()) . "\n";
    }
}
echo "Total files fixed for XSS: $count\n";
?>
