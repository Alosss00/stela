<?php
$file = 'app/Services/NotificationService.php';
$content = file_get_contents($file);

// Find all query( calls that have "WHERE a.id = ?" followed by whitespace and ");
// Pattern: WHERE a.id = ?\n        ");  -> WHERE a.id = ?\n        ", [$appointment_id]);
$count = 0;
$pattern = '/(WHERE a\.id = \?)\s*\n(\s*)"\s*\)\s*;/';
$replacement = '$1\n$2", [$appointment_id]);';
$new_content = preg_replace_callback($pattern, function($m) use (&$count) {
    $count++;
    return $m[1] . "\n" . $m[2] . '", [$appointment_id]);';
}, $content);

if ($new_content !== $content) {
    file_put_contents($file, $new_content);
    echo "Fixed $count query calls\n";
} else {
    echo "No matches found with regex\n";
    // Try line by line
    $lines = explode("\n", $content);
    $fixed = 0;
    for ($i = 0; $i < count($lines); $i++) {
        if (trim($lines[$i]) === 'WHERE a.id = ?') {
            // Check next non-empty line
            for ($j = $i+1; $j < count($lines); $j++) {
                $t = trim($lines[$j]);
                if ($t !== '') {
                    if ($t === '");' || $t === '");') {
                        $lines[$j] = str_replace('");', '", [$appointment_id]);', $lines[$j]);
                        $fixed++;
                    }
                    break;
                }
            }
        }
    }
    if ($fixed > 0) {
        file_put_contents($file, implode("\n", $lines));
        echo "Fixed $fixed query calls via line-by-line\n";
    }
}
echo "Done\n";
?>
