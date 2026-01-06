<?php

// ==========================================
// PENGATURAN SESSION TIMEOUT (TERPUSAT)
// ==========================================
// Set session lifetime menjadi 30 hari (dalam detik)
$session_lifetime = 60 * 60 * 24 * 30; // 30 hari

// Hanya set jika session belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', $session_lifetime);
    ini_set('session.cookie_lifetime', $session_lifetime);

    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'secure' => true,    // Set true jika menggunakan HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Perpanjang session cookie setiap ada aktivitas
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    setcookie(session_name(), session_id(), time() + $session_lifetime, '/');
}

require_once __DIR__ . '/functions.php';

$base_url = $_ENV['BASE_URL'];
