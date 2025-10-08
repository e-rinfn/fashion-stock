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
    $cicilan = query("SELECT * FROM cicilan_pembelian WHERE id_cicilan_pembelian = $id_cicilan")[0] ?? null;

    if (!$cicilan) {
        throw new Exception("Data cicilan tidak ditemukan");
    }

    $id_pembelian = $cicilan['id_pembelian'];
    $jumlah_cicilan = $cicilan['jumlah_cicilan'];
    $bukti_file = $cicilan['bukti_pembayaran'];

    // Hapus cicilan
    $conn->query("DELETE FROM cicilan_pembelian WHERE id_cicilan_pembelian = $id_cicilan");

    // Hapus file bukti jika ada
    if ($bukti_file && file_exists("bukti/" . $bukti_file)) {
        unlink("bukti/" . $bukti_file);
    }

    // Update status pembelian
    $total_dibayar = query("SELECT COALESCE(SUM(jumlah_cicilan), 0) as total FROM cicilan_pembelian WHERE id_pembelian = $id_pembelian")[0]['total'];
    $pembelian = query("SELECT total_harga FROM pembelian WHERE id_pembelian = $id_pembelian")[0];

    if ($total_dibayar >= $pembelian['total_harga']) {
        $conn->query("UPDATE pembelian SET status_pembayaran = 'lunas' WHERE id_pembelian = $id_pembelian");
    } else if ($total_dibayar > 0) {
        $conn->query("UPDATE pembelian SET status_pembayaran = 'cicilan' WHERE id_pembelian = $id_pembelian");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dibatalkan']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
