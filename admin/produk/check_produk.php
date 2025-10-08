<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

$id_produk = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Cek relasi dengan tabel penjualan
$penjualan = query("SELECT dp.id_detail_penjualan 
                   FROM detail_penjualan dp
                   JOIN penjualan p ON dp.id_penjualan = p.id_penjualan
                   WHERE dp.id_produk = $id_produk
                   LIMIT 1");

// Cek relasi dengan tabel hasil_penjahitan (jika ada)
$produksi = query("SELECT id_hasil_jahit FROM hasil_penjahitan WHERE id_produk = $id_produk LIMIT 1");

if ($penjualan || $produksi) {
    $message = '<div style="text-align:center">';
    // $message .= '<strong>Produk tidak dapat dihapus karena terhubung dengan:</strong><br>';

    // if ($penjualan) {
    //     $message .= '- Data penjualan<br>';
    // }
    // if ($produksi) {
    //     $message .= '- Data produksi<br>';
    // }

    $message .= 'Produk tidak dapat dihapus karena masih digunakan dalam data penjualan atau produksi';
    $message .= '</div>';

    echo json_encode([
        'can_delete' => false,
        'message' => $message
    ]);
} else {
    echo json_encode([
        'can_delete' => true,
        'message' => ''
    ]);
}
