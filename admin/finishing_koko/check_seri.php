<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['seri'])) {
    $seri = $conn->real_escape_string($_GET['seri']);

    // Cek apakah seri sudah ada
    $result = $conn->query("SELECT seri FROM hasil_kirim_finishing WHERE seri = '$seri'");
    $exists = $result->num_rows > 0;

    // Ambil nomor seri terakhir
    $last_seri_result = $conn->query("SELECT seri FROM hasil_kirim_finishing ORDER BY id_hasil_kirim_finishing DESC LIMIT 1");
    $last_seri = $last_seri_result->num_rows > 0 ? $last_seri_result->fetch_assoc()['seri'] : 'Belum ada seri';

    echo json_encode([
        'exists' => $exists,
        'last_seri' => $last_seri
    ]);
} else {
    echo json_encode([
        'exists' => false,
        'last_seri' => 'Parameter tidak valid'
    ]);
}
