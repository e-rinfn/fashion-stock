<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['tanggal']) && isset($_GET['jenis'])) {
    $tanggal = $_GET['tanggal'];
    $jenis = $_GET['jenis'];

    $sql = "SELECT id_tarif, tarif_per_unit, DATE(berlaku_sejak) as berlaku_sejak 
            FROM tarif_upah 
            WHERE jenis_tarif = ? 
            AND DATE(berlaku_sejak) <= ? 
            ORDER BY berlaku_sejak DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $jenis, $tanggal);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $tarifs = [];

            while ($row = $result->fetch_assoc()) {
                $tarifs[] = $row;
            }

            echo json_encode($tarifs);
        } else {
            echo json_encode(['error' => 'Query failed: ' . $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    }
} else {
    echo json_encode(['error' => 'Missing parameters']);
}

// $conn->close(); // Jangan tutup koneksi karena sudah include di header
