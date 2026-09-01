<?php
$file = 'app/Services/NotificationService.php';
$content = file_get_contents($file);

$patterns = [
    '/"[^"]*\*KTT FINAL APPROVAL NOTIFICATION\*/' => '"🔔 *KTT FINAL APPROVAL NOTIFICATION*',
    '/"[^"]*\*EMPLOYEE VERIFICATION SUCCESSFUL\*/' => '"✅ *EMPLOYEE VERIFICATION SUCCESSFUL*',
    '/"[^"]*\*EMPLOYEE DATA REJECTED\*/' => '"❌ *EMPLOYEE DATA REJECTED*',
    '/"[^"]*\*ASSIGN LETTER SUCCESSFULLY APPROVED\*/' => '"🎉 *ASSIGN LETTER SUCCESSFULLY APPROVED*',
    '/"[^"]*\*ASSIGN LETTER REJECTED - DATA CORRECTION REQUIRED\*/' => '"❌ *ASSIGN LETTER REJECTED - DATA CORRECTION REQUIRED*',
    '/"[^"]*\*Letter Details:\*/' => '"📋 *Letter Details:*',
    '/"[^"]*\*Employee Details:\*/' => '"📋 *Employee Details:*',
    '/"[^"]*Rejection Reason:\*/' => '"💬 *Rejection Reason:*',
    '/"[^"]*The assign letter is now fully approved by both KTTs/' => '"ℹ️ The assign letter is now fully approved by both KTTs',
    '/"[^"]*The assign letter is now active and fully approved/' => '"✅ The assign letter is now active and fully approved',
    '/"[^"]*Please login to /' => '"⚠️ Please login to '
];

foreach ($patterns as $pattern => $replacement) {
    $content = preg_replace($pattern, $replacement, $content);
}

// For bullets and location pins, we need a different approach since they don't have distinct text right after them
// Wait, bullet points are followed by specific texts like ` Letter No.:`, ` Employee:`, ` Position:`, ` Company:`, ` KTT MSM:`, ` KTT TTN:`, ` Rejected by:`, ` ID BADGE:`, ` Name:`
$bullets = [
    ' Letter No.:', ' Employee:', ' Position:', ' Company:', ' KTT MSM:', ' KTT TTN:', ' Rejected by:', ' ID BADGE:', ' Name:'
];
foreach ($bullets as $b) {
    $content = preg_replace('/"[^"]*' . preg_quote($b) . '/', '"•' . $b, $content);
}

// For location pin
$content = preg_replace('/"[^"]*\{\$base_url\}\//', '"📍 {$base_url}/', $content);

file_put_contents($file, $content);
echo "Done";
?>
