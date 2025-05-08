<?php
require_once 'config/auth.php';

if (is_logged_in()) {
    switch (get_user_role()) {
        case 'admin':
            header('Location: admin/products/');
            break;
        case 'owner':
            header('Location: owner/dashboard.php');
            break;
        default:
            header('Location: auth/login.php');
    }
} else {
    header('Location: auth/login.php');
}
exit;
