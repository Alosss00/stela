<?php
$file = 'resources/auth/change_password.php';
$content = file_get_contents($file);
$search = '} elseif (strlen($new_password) < 6) {
            $error = stela_t(''new-password-min-6-chars'');';
$replace = '} elseif (strlen($new_password) < 8 || !preg_match(''/[A-Z]/'', $new_password) || !preg_match(''/[0-9]/'', $new_password)) {
            $error = function_exists(''stela_t'') ? stela_t(''password-policy-error'') : ''Password must be at least 8 characters long and contain at least one uppercase letter and one number.'';';
$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Replaced in auth/change_password.php\n";
