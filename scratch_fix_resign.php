<?php
$files = [
    'user' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\user\reports.php',
    'dept' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\dept\reports.php',
    'admin' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\admin\reports.php'
];

foreach ($files as $role => $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace 'resign' with 'resigned' in the query
    $content = str_replace("employee_status = 'resign'", "employee_status = 'resigned'", $content);
    
    // Replace 'resign' with 'resigned' in the badge conditions
    $content = str_replace("employee_status'] === 'resign'", "employee_status'] === 'resigned'", $content);

    file_put_contents($file, $content);
}
echo "Done";
