<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/resources/views');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/', RecursiveRegexIterator::GET_MATCH);

$replacements = [
    // employees
    '/FROM employees WHERE/i' => 'FROM employees WHERE deleted_at IS NULL AND',
    '/FROM employees e WHERE/i' => 'FROM employees e WHERE e.deleted_at IS NULL AND',
    '/FROM employees e\s+WHERE/i' => 'FROM employees e WHERE e.deleted_at IS NULL AND',
    
    // appointments
    '/FROM appointments WHERE/i' => 'FROM appointments WHERE deleted_at IS NULL AND',
    '/FROM appointments a\s+JOIN employees e ON a\.employee_id = e\.id\s+WHERE/i' => 'FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND',
    
    // positions, etc
    '/FROM positions WHERE/i' => 'FROM positions WHERE deleted_at IS NULL AND',
    '/FROM competencies WHERE/i' => 'FROM competencies WHERE deleted_at IS NULL AND',
    '/FROM supervision_areas WHERE/i' => 'FROM supervision_areas WHERE deleted_at IS NULL AND',
];

$count = 0;
foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    $newContent = $content;
    
    foreach ($replacements as $pattern => $replacement) {
        // Only replace if it doesn't already have deleted_at IS NULL
        // We will do a simple replace first, then fix double injections
        $newContent = preg_replace($pattern, $replacement, $newContent);
    }
    
    // Fix double injections
    $newContent = str_replace('deleted_at IS NULL AND deleted_at IS NULL AND', 'deleted_at IS NULL AND', $newContent);
    $newContent = str_replace('e.deleted_at IS NULL AND e.deleted_at IS NULL AND', 'e.deleted_at IS NULL AND', $newContent);
    $newContent = str_replace('a.deleted_at IS NULL AND e.deleted_at IS NULL AND a.deleted_at IS NULL AND e.deleted_at IS NULL AND', 'a.deleted_at IS NULL AND e.deleted_at IS NULL AND', $newContent);
    
    if ($content !== $newContent) {
        file_put_contents($path, $newContent);
        echo "Updated: $path\n";
        $count++;
    }
}
echo "Total files updated: $count\n";
