<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Ambil data penjualan
    $penjualan = query("SELECT * FROM penjualan WHERE id_penjualan = $id");
    if (!$penjualan || count($penjualan) == 0) {
        $_SESSION['error'] = "Data penjualan tidak ditemukan.";
        header("Location: list.php");
        exit;
    }
    $penjualan = $penjualan[0];

    // (Opsional) Cegah pembatalan jika status pembayaran sudah lunas
    if ($penjualan['status_pembayaran'] === '') { // Saya menghapus '=== "lunas"' karena supaya yang lunas juga dapat dihapus
        $_SESSION['error'] = "Penjualan yang sudah lunas tidak dapat dibatalkan.";
        header("Location: list.php");
        exit;
    }

    // Ambil detail penjualan
    $details = query("SELECT * FROM detail_penjualan WHERE id_penjualan = $id");

    // Mulai transaksi
    $conn->begin_transaction();
    try {
        foreach ($details as $d) {
            $id_produk = $d['id_produk'];
            $jumlah = $d['jumlah'];

            // Kembalikan stok produk
            $sql_update = "UPDATE produk SET stok = stok + $jumlah WHERE id_produk = $id_produk";
            if (!$conn->query($sql_update)) {
                throw new Exception("Gagal mengembalikan stok produk ID $id_produk");
            }
        }

        // Hapus data cicilan terkait penjualan ini
        $conn->query("DELETE FROM cicilan WHERE id_penjualan = $id");

        // Hapus detail penjualan
        $conn->query("DELETE FROM detail_penjualan WHERE id_penjualan = $id");

        // Hapus data penjualan
        $conn->query("DELETE FROM penjualan WHERE id_penjualan = $id");

        $conn->commit();
        $_SESSION['success'] = "Penjualan berhasil dibatalkan.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal membatalkan penjualan: " . $e->getMessage();
    }
}

header("Location: list.php");
exit;
