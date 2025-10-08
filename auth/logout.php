<?php
require_once '../config/database.php';
include_once '../config/config.php';

// Mulai session terlebih dahulu
session_start();

// Hapus semua data session
$_SESSION = [];

// Hapus session cookie jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Akhiri session
session_destroy();

// Redirect ke halaman login
header("Location: {$base_url}/auth/login.php");
exit();
