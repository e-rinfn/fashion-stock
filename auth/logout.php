<?php
require_once '../config/database.php';
include_once '../config/config.php';

// Mulai session terlebih dahulu
session_start();

// Hapus remember token dari database jika ada
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE id_user = $user_id";
    $conn->query($sql);
}

// Hapus remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

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
