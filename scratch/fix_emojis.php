<?php
$file = 'app/Services/NotificationService.php';
$content = file_get_contents($file);

$map = [
    'Ã¢Å“â€¦ *NEW EMPLOYEE' => '🔔 *NEW EMPLOYEE',
    'Ã¢Å“â€¦ *KTT FINAL' => '🔔 *KTT FINAL',
    'Ã¢Å“â€¦ *EMPLOYEE VERIFICATION' => '✅ *EMPLOYEE VERIFICATION',
    'Ã¢Â Å’ *EMPLOYEE DATA REJECTED*' => '❌ *EMPLOYEE DATA REJECTED*',
    'Ã°Å¸Å½â€° *ASSIGN LETTER SUCCESSFULLY' => '🎉 *ASSIGN LETTER SUCCESSFULLY',
    'Ã¢Â Å’ *ASSIGN LETTER REJECTED' => '❌ *ASSIGN LETTER REJECTED',
    
    'Ã°Å¸â€œâ€¹' => '📋',
    'Ã¢â‚¬Â¢' => '•',
    'Ã¢Å¡Â Ã¯Â¸Â ' => '⚠️',
    'Ã°Å¸â€œÂ ' => '📍',
    'Ã°Å¸â€™Â¬' => '💬',
    'Ã¢â€žÂ¹Ã¯Â¸Â ' => 'ℹ️',
    'Ã¢Å“â€¦ The assign' => '✅ The assign'
];

foreach ($map as $from => $to) {
    $content = str_replace($from, $to, $content);
}

file_put_contents($file, $content);
echo "Done";
?>
