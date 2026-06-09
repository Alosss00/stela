<?php
// Check current password configuration
$password = 'msox tvqb gypt konl';
echo "Current password: '$password'\n";
echo "Length: " . strlen($password) . " characters\n";
echo "Has spaces: " . (strpos($password, ' ') !== false ? 'YES ❌' : 'NO ✓') . "\n\n";

// Remove spaces
$clean_password = str_replace(' ', '', $password);
echo "Cleaned password: '$clean_password'\n";
echo "Length: " . strlen($clean_password) . " characters\n";
echo "Expected: 16 characters\n\n";

if (strlen($clean_password) == 16) {
    echo "✓ Password format correct!\n";
    echo "\nUpdate includes/notifications.php line 21:\n";
    echo "private \$smtp_password = '$clean_password'; // tanpa spasi\n";
} else {
    echo "❌ Password length incorrect! Should be 16 characters.\n";
    echo "Please generate a new App Password from Gmail.\n";
}

