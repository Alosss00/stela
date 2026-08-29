<?php
$content = file_get_contents(__DIR__ . '/resources/admin/reports.php');
$content = str_replace('layouts/header.php', 'layouts/superadmin_header.php', $content);
$content = str_replace('layouts/footer.php', 'layouts/superadmin_footer.php', $content);
$content = str_replace(
    "requirePermission('reports.view');", 
    "requirePermission('reports.view');\n\nif (!isSuperadmin()) {\n    header('Location: ../admin/dashboard.php');\n    exit();\n}", 
    $content
);
file_put_contents(__DIR__ . '/resources/superadmin/reports.php', $content);
echo "Done";
