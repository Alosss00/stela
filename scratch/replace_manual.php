<?php
$files = [
    'resources/superadmin/reports.php',
    'resources/user/reports.php',
    'resources/dept/reports.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }

    $content = file_get_contents($file);
    $start_idx = strpos($content, 'function toggleSection(sectionId) {');
    
    if ($start_idx === false) {
        echo "toggleSection not found in $file\n";
        continue;
    }

    $brace_count = 0;
    $end_idx = -1;
    $in_string = false;
    $string_char = '';

    for ($i = $start_idx; $i < strlen($content); $i++) {
        $char = $content[$i];

        if (in_array($char, ["'", '"', '`'])) {
            if ($in_string && $char === $string_char) {
                if ($i > 0 && $content[$i - 1] !== '\\') {
                    $in_string = false;
                }
            } elseif (!$in_string) {
                $in_string = true;
                $string_char = $char;
            }
        }

        if (!$in_string) {
            if ($char === '{') {
                $brace_count++;
            } elseif ($char === '}') {
                $brace_count--;
                if ($brace_count === 0) {
                    $end_idx = $i;
                    break;
                }
            }
        }
    }

    if ($end_idx !== -1) {
        $script_start = strrpos(substr($content, 0, $start_idx), '<script>');

        $new_content = substr($content, 0, $start_idx) . substr($content, $end_idx + 1);

        if ($script_start !== false) {
            // Replace <script> containing toggleSection with the external inclusion
            $new_content = substr_replace($new_content, '<script src="../../assets/js/report-toggles.js"></script>' . "\n<script>", $script_start, 8);
        }

        file_put_contents($file, $new_content);
        echo "Successfully updated $file\n";
    } else {
        echo "Could not find closing brace in $file\n";
    }
}
