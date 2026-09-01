<?php
$file = 'resources/dept/reports.php';
$content = file_get_contents($file);

// The pattern is: AND e.department = '" . $db->escapeString($department) . "'
// We want to replace it with: AND e.department = ?"
// Wait, no! We want to replace `" . $db->escapeString($department) . "` with `?` and KEEP the rest of the string as is.
// Since it's inside a string constructed with `"` and concatenated:
// " ... AND e.department = '" . $db->escapeString($department) . "'\n"
// It should become:
// " ... AND e.department = ?\n"

// Let's replace the whole literal string concatenation part
$content = str_replace('AND e.department = \'" . $db->escapeString($department) . "\'', 'AND e.department = ?"', $content);
// Wait, if it was: `" ... AND e.department = '" . $db->escapeString($department) . "' ... "`
// then `'" . $db->escapeString($department) . "'` was string concatenation.
// So replacing `'" . $db->escapeString($department) . "'` with `?"` is WRONG, because it's inside a string.
// No, the original PHP code was:
// $db->query("SELECT ... WHERE e.department = '" . $db->escapeString($department) . "' \n ORDER BY ...")
// So it is:
// `'" . $db->escapeString($department) . "'`
// It should become:
// `?'`
// Wait, no, parameter placeholders don't have quotes around them!
// So it should become:
// `?`
// But the original had single quotes! `e.department = '" . $... . "'`
// So it was `'` then `"` then `.` then `$db->...` then `.` then `"` then `'`
// We need to replace `'" . $db->escapeString($department) . "'` with `?`
// BUT wait, it's inside the PHP string parsing, so:
$old_part = '\'" . $db->escapeString($department) . "\'';
$new_part = '?"';
// Actually, it's easier:
$content = str_replace('\'" . $db->escapeString($department) . "\'', '?', $content);

// And we also have to append the array argument to $db->query()
// So we find `$db->query("... \n ORDER BY ... \n");`
// This is too hard to parse with regex safely. Let me just do it line by line.

$lines = explode("\n", file_get_contents($file));
$in_query = false;
$found_dept = false;
for ($i=0; $i<count($lines); $i++) {
    if (strpos($lines[$i], '$db->query(') !== false) {
        $in_query = true;
        $found_dept = false;
    }
    
    if ($in_query && strpos($lines[$i], '\'" . $db->escapeString($department) . "\'') !== false) {
        $lines[$i] = str_replace('\'" . $db->escapeString($department) . "\'', '?', $lines[$i]);
        $found_dept = true;
    }
    
    if ($in_query && $found_dept && strpos($lines[$i], '");') !== false) {
        $lines[$i] = str_replace('");', '", [$department]);', $lines[$i]);
        $in_query = false;
        $found_dept = false;
    }
}

file_put_contents($file, implode("\n", $lines));
echo "Done reports.php fix\n";
?>
