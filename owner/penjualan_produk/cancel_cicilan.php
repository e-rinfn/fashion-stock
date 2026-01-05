<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID cicilan tidak valid']);
    exit();
}

$id_cicilan = intval($_GET['id']);

// Mulai transaksi
$conn->begin_transaction();

try {
    // Ambil data cicilan yang akan dibatalkan
    $cicilan = query("SELECT * FROM cicilan WHERE id_cicilan = $id_cicilan")[0] ?? null;

    if (!$cicilan) {
        throw new Exception("Data cicilan tidak ditemukan");
    }

    $id_penjualan = $cicilan['id_penjualan'];
    $jumlah_cicilan = $cicilan['jumlah_cicilan'];
    $bukti_pembayaran = $cicilan['bukti_pembayaran'];

    // Hapus cicilan
    $conn->query("DELETE FROM cicilan WHERE id_cicilan = $id_cicilan");

    // Update status penjualan
    $total_dibayar = query("SELECT SUM(jumlah_cicilan) as total FROM cicilan WHERE id_penjualan = $id_penjualan")[0]['total'];
    $total_dibayar = $total_dibayar ?: 0;

    $penjualan = query("SELECT total_harga FROM penjualan WHERE id_penjualan = $id_penjualan")[0];

    if ($total_dibayar <= 0) {
        $status = 'belum lunas';
    } elseif ($total_dibayar < $penjualan['total_harga']) {
        $status = 'cicilan';
    } else {
        $status = 'lunas';
    }

    // $conn->query("UPDATE penjualan SET status_pembayaran = '$status' WHERE id_penjualan = $id_penjualan");

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
