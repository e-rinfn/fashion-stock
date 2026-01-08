<?php
session_start();
require_once '../config/functions.php';
require_once '../config/lock_functions.php';

header('Content-Type: application/json');

$data_type = $_POST['data_type'] ?? '';
$data_id = $_POST['data_id'] ?? 0;
$action = $_POST['action'] ?? 'check_only';

$response = [
    'locked' => false,
    'locked_by' => '',
    'is_owner' => false,
    'success' => false
];

if (empty($data_type) || empty($data_id)) {
    echo json_encode($response);
    exit();
}

// Cek lock status
$lock_info = isDataLocked($data_type, $data_id);

if ($lock_info['locked']) {
    $response['locked'] = true;
    $response['locked_by'] = $lock_info['locked_by'];

    // Cek apakah user ini yang mengunci
    $current_user_id = $_SESSION['user_id'] ?? 0;
    if ($current_user_id && isset($lock_info['user_id'])) {
        $response['is_owner'] = ($current_user_id == $lock_info['user_id']);
    }
} else {
    // Jika tidak terkunci dan action adalah lock, maka set lock
    if ($action == 'check_and_lock') {
        if (lockData($data_type, $data_id)) {
            $response['success'] = true;
        }
    } else {
        $response['success'] = true;
    }
}

echo json_encode($response);
