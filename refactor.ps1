<?php
require_once __DIR__ . '/bootstrap/app.php';

$files = Get-ChildItem -Path "resources" -Recurse -Filter "*.php";
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw;
    if ($content -match "move_uploaded_file") {
        $newContent = $content -replace "move_uploaded_file\(, "safe_move_uploaded_file(";
        Set-Content -Path $file.FullName -Value $newContent;
        Write-Host "Updated $($file.FullName)";
    }
}
