<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $data_type = $conn->real_escape_string($input['data_type']);
    $data_id = intval($input['data_id']);

    $success = lockData($data_type, $data_id);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Lock berhasil' : 'Data sudah terkunci'
    ]);
}
