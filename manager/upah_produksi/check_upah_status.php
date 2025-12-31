<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id_produksi']) || empty($_GET['id_produksi'])) {
    echo json_encode(['upah_dibayar' => false]);
    exit();
}

$id_hasil_potong_fix = intval($_GET['id_produksi']);

try {
    // Ambil data produksi
    $produksi_data = query("SELECT id_penjahit, tanggal_hasil_jahit FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix")[0];

    if (!$produksi_data || empty($produksi_data['id_penjahit']) || empty($produksi_data['tanggal_hasil_jahit'])) {
        echo json_encode(['upah_dibayar' => false]);
        exit();
    }

    $id_penjahit = $produksi_data['id_penjahit'];
    $periode = date('Y-m-01', strtotime($produksi_data['tanggal_hasil_jahit']));

    // Cek apakah ada pembayaran upah untuk penjahit di periode ini
    $check_upah = $conn->prepare("
        SELECT COUNT(*) as total_pembayaran 
        FROM pembayaran_upah_2 pu
        JOIN hutang_upah hu ON pu.id_hutang = hu.id_hutang
        WHERE hu.id_karyawan = ? 
        AND hu.jenis_karyawan = 'penjahit' 
        AND hu.periode = ?
        AND hu.total_dibayar > 0
    ");
    $check_upah->bind_param("is", $id_penjahit, $periode);
    $check_upah->execute();
    $result = $check_upah->get_result();
    $upah_dibayar = $result->fetch_assoc()['total_pembayaran'] > 0;
    $check_upah->close();

    echo json_encode(['upah_dibayar' => $upah_dibayar]);
} catch (Exception $e) {
    error_log("Error check_upah_status: " . $e->getMessage());
    echo json_encode(['upah_dibayar' => false]);
}
