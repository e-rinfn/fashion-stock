<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $id_hasil_potong_fix = intval($input['id_hasil_potong_fix']);

    // Cek apakah data sudah ada
    $exists = isHasilJahitExist($id_hasil_potong_fix);

    $response = [
        'exists' => $exists,
        'id_hasil_potong_fix' => $id_hasil_potong_fix
    ];

    if ($exists) {
        // Ambil data existing
        $existing_data = getHasilJahitExisting($id_hasil_potong_fix);
        $response['tanggal_hasil_jahit'] = $existing_data['tanggal_hasil_jahit'];
        $response['total_hasil_jahit'] = $existing_data['total_hasil_jahit'];
    }

    echo json_encode($response);
}
