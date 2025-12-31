<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

$id_cicilan = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_cicilan <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

// Mulai transaksi
$conn->begin_transaction();

try {
    // Ambil data cicilan
    $cicilan = query("SELECT * FROM cicilan_pembelian_bahan WHERE id_cicilan_pembelian_bahan = $id_cicilan")[0] ?? null;

    if (!$cicilan) {
        throw new Exception("Data cicilan tidak ditemukan");
    }

    $id_pembelian_bahan = $cicilan['id_pembelian_bahan'];
    $jumlah_cicilan = $cicilan['jumlah_cicilan'];
    $bukti_file = $cicilan['bukti_pembayaran'];

    // Hapus cicilan
    $conn->query("DELETE FROM cicilan_pembelian_bahan WHERE id_cicilan_pembelian_bahan = $id_cicilan");

    // Hapus file bukti jika ada
    if ($bukti_file && file_exists("bukti/" . $bukti_file)) {
        unlink("bukti/" . $bukti_file);
    }

    // Update status pembelian
    $total_dibayar = query("SELECT COALESCE(SUM(jumlah_cicilan), 0) as total FROM cicilan_pembelian_bahan WHERE id_pembelian_bahan = $id_pembelian_bahan")[0]['total'];
    $pembelian = query("SELECT total_harga FROM pembelian_bahan WHERE id_pembelian_bahan = $id_pembelian_bahan")[0];

    if ($total_dibayar >= $pembelian['total_harga']) {
        $conn->query("UPDATE pembelian_bahan SET status_pembayaran = 'lunas' WHERE id_pembelian_bahan = $id_pembelian_bahan");
    } else if ($total_dibayar > 0) {
        $conn->query("UPDATE pembelian_bahan SET status_pembayaran = 'cicilan' WHERE id_pembelian_bahan = $id_pembelian_bahan");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dibatalkan']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
