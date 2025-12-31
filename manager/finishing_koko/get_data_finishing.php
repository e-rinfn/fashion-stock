<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Ambil data finishing utama
    $sql_finishing = "SELECT 
        hk.*,
        p.nama_produk,
        pet.nama_petugas
    FROM hasil_kirim_finishing hk
    LEFT JOIN produk p ON hk.id_produk = p.id_produk
    LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing
    WHERE hk.id_hasil_kirim_finishing = ?";

    $stmt = $conn->prepare($sql_finishing);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $finishing = $result->fetch_assoc();

        // Ambil detail bahan baku
        $sql_detail = "SELECT 
            dh.*,
            k.nama_koko,
            k.harga_jual
        FROM detail_hasil_kirim_finishing dh
        JOIN koko k ON dh.id_koko = k.id_koko
        WHERE dh.id_hasil_kirim_finishing = ?";

        $stmt_detail = $conn->prepare($sql_detail);
        $stmt_detail->bind_param("i", $id);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();

        $detail = [];
        while ($row = $result_detail->fetch_assoc()) {
            $detail[] = $row;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id_hasil_kirim_finishing' => $finishing['id_hasil_kirim_finishing'],
                'seri' => $finishing['seri'],
                'nama_petugas' => $finishing['nama_petugas'],
                'nama_produk' => $finishing['nama_produk'],
                'tanggal_kirim_finishing' => $finishing['tanggal_kirim_finishing'],
                'status_finishing' => $finishing['status_finishing'],
                'detail' => $detail
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak ditemukan'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID tidak diberikan'
    ]);
}
