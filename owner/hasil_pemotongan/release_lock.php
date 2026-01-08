<?php
session_start();
require_once '../config/functions.php';
require_once '../config/lock_functions.php';

header('Content-Type: application/json');

$data_type = $_POST['data_type'] ?? '';
$data_id = $_POST['data_id'] ?? 0;

$response = ['success' => false];

if (empty($data_type) || empty($data_id)) {
    echo json_encode($response);
    exit();
}

// Release lock
if (releaseLock($data_type, $data_id)) {
    $response['success'] = true;
}

echo json_encode($response);
