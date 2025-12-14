<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (isset($_GET['id'])) {
    $id_finishing = intval($_GET['id']);

    $conn->begin_transaction();
    try {
        // Ambil data
        $finishing = query("SELECT * FROM finishing WHERE id_finishing = $id_finishing")[0];

        if (!$finishing) {
            throw new Exception("Data tidak ditemukan");
        }

        // Jika sudah selesai, kurangi stok
        if ($finishing['status'] == 'selesai' && $finishing['jumlah_selesai'] > 0) {
            $sql_stok = "UPDATE produk 
                        SET stok = stok - {$finishing['jumlah_selesai']} 
                        WHERE id_produk = {$finishing['id_produk']}";

            if (!$conn->query($sql_stok)) {
                throw new Exception("Gagal mengurangi stok");
            }
        }

        // Update status jadi batal
        $sql_update = "UPDATE finishing SET status = 'batal' WHERE id_finishing = $id_finishing";
        if (!$conn->query($sql_update)) {
            throw new Exception("Gagal membatalkan finishing");
        }

        $conn->commit();
        $_SESSION['success'] = "Finishing berhasil dibatalkan";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    header("Location: finishing.php");
    exit();
}
