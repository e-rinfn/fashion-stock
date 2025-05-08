<?php
session_start();

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function require_login()
{
    if (!is_logged_in()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function require_role($role)
{
    require_login();
    if ($_SESSION['role'] !== $role) {
        header('HTTP/1.0 403 Forbidden');
        echo 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.';
        exit;
    }
}

function get_user_role()
{
    return $_SESSION['role'] ?? null;
}
