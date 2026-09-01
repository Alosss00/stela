<?php
require_once __DIR__ . '/../bootstrap/app.php';
$db = new Database();

// Search ALL tables for '01/TT/MSM/09/2026'
$tables = $db->query("SHOW TABLES");
while ($tableRow = $tables->fetch_array()) {
    $table = $tableRow[0];
    $columns = $db->query("SHOW COLUMNS FROM $table");
    $where = [];
    while ($colRow = $columns->fetch_assoc()) {
        if (strpos($colRow['Type'], 'char') !== false || strpos($colRow['Type'], 'text') !== false) {
            $where[] = "{$colRow['Field']} LIKE '%01/TT/MSM/09/2026%'";
        }
    }
    if (!empty($where)) {
        $sql = "SELECT * FROM $table WHERE " . implode(' OR ', $where);
        $res = $db->query($sql);
        if ($res && $res->num_rows > 0) {
            echo "Found in table: $table\n";
            while ($r = $res->fetch_assoc()) {
                print_r($r);
            }
        }
    }
}
echo "Search complete.\n";
