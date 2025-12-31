<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID cicilan tidak valid']);
    exit();
}

$id_cicilan_penjualan_bahan = intval($_GET['id']);

// Mulai transaksi
$conn->begin_transaction();

try {
    // Ambil data cicilan yang akan dibatalkan
    $cicilan = query("SELECT * FROM cicilan_penjualan_bahan WHERE id_cicilan_penjualan_bahan = $id_cicilan_penjualan_bahan")[0] ?? null;

    if (!$cicilan) {
        throw new Exception("Data cicilan tidak ditemukan");
    }

    $id_penjualan_bahan = $cicilan['id_penjualan_bahan'];
    $jumlah_cicilan = $cicilan['jumlah_cicilan_penjualan_bahan'];
    $bukti_pembayaran = $cicilan['bukti_pembayaran'];

    // Hapus cicilan
    $conn->query("DELETE FROM cicilan_penjualan_bahan WHERE id_cicilan_penjualan_bahan = $id_cicilan_penjualan_bahan");

    // Update status penjualan
    $total_dibayar = query("SELECT SUM(jumlah_cicilan_penjualan_bahan) as total FROM cicilan_penjualan_bahan WHERE id_penjualan_bahan = $id_penjualan_bahan")[0]['total'];
    $total_dibayar = $total_dibayar ?: 0;

    $penjualan = query("SELECT total_harga FROM penjualan_bahan WHERE id_penjualan_bahan = $id_penjualan_bahan")[0];

    if ($total_dibayar <= 0) {
        $status = 'belum lunas';
    } elseif ($total_dibayar < $penjualan['total_harga']) {
        $status = 'cicilan';
    } else {
        $status = 'lunas';
    }

    // $conn->query("UPDATE penjualan_bahan SET status_pembayaran = '$status' WHERE id_penjualan_bahan = $id_penjualan_bahan");

    // Hapus file bukti pembayaran jika ada
    if ($bukti_pembayaran && file_exists("bukti/$bukti_pembayaran")) {
        unlink("bukti/$bukti_pembayaran");
    }

    // Commit transaksi
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
