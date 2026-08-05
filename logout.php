<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/bootstrap/app.php';

session_start(); // Pastikan session aktif agar user_id bisa diambil

if (isset($_COOKIE['remember_me'])) {
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/app/Models/Database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
        $stmt = $conn->prepare("DELETE FROM user_tokens WHERE selector = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $selector, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Hapus cookie dari browser
    setcookie(
        'remember_me',
        '',
        time() - 3600,
        '/',
        '',
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        true
    );
}

session_unset();
session_destroy();
redirect(BASE_URL . '/index.php');
