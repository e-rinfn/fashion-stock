<?php
// check_penjahit.php
require_once '../../config/database.php';

$id_penjahit = isset($_GET['id_penjahit']) ? intval($_GET['id_penjahit']) : 0;
$tanggal = isset($_GET['tanggal']) ? $conn->real_escape_string($_GET['tanggal']) : '';

$response = ['exists' => false];

if ($id_penjahit > 0 && !empty($tanggal)) {
    $sql = "SELECT COUNT(*) as count FROM hasil_kirim_finishing 
            WHERE id_penjahit = $id_penjahit 
            AND tanggal_kirim_finishing = '$tanggal'";

    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $response['exists'] = $row['count'] > 0;
    }
}

header('Content-Type: application/json');
echo json_encode($response);
