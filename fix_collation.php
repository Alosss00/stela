<?php
$file = 'database/dumps/mining_appointment 9-6-2026 terbaru.sql';
$content = file_get_contents($file);
$content = str_replace('utf8mb4_uca1400_ai_ci', 'utf8mb4_general_ci', $content);
file_put_contents($file, $content);
echo "Collation fixed!";
