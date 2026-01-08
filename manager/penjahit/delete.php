<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID penjahit tidak valid";
    header("Location: {$base_url}/manager/master_data.php");

    exit();
}

$id_penjahit = intval($_GET['id']);

// Cek apakah penjahit ada
$penjahit = query("SELECT * FROM penjahit WHERE id_penjahit = $id_penjahit");
if (empty($penjahit)) {
    $_SESSION['error'] = "Data penjahit tidak ditemukan";
    header("Location: {$base_url}/manager/master_data.php");

    exit();
}

// Cek relasi dengan tabel lain
$cek_pengiriman = query("SELECT 1 FROM pengiriman_penjahit WHERE id_penjahit = $id_penjahit LIMIT 1");
$cek_hasil_potong = query("SELECT 1 FROM hasil_potong_fix WHERE id_penjahit = $id_penjahit LIMIT 1");
$cek_hutang_upah = query("SELECT 1 FROM hutang_upah WHERE jenis_karyawan = 'penjahit' AND id_karyawan = $id_penjahit LIMIT 1");

if ($cek_pengiriman || $cek_hasil_potong || $cek_hutang_upah) {
    $error_msg = "Penjahit tidak dapat dihapus karena masih digunakan dalam: ";
    $reasons = [];

    if ($cek_pengiriman) $reasons[] = "pengiriman penjahit";
    if ($cek_hasil_potong) $reasons[] = "hasil pemotongan";
    if ($cek_hutang_upah) $reasons[] = "hutang upah";

    $_SESSION['error'] = $error_msg . implode(", ", $reasons);
    header("Location: {$base_url}/manager/master_data.php");
    exit();
}

// Hapus data
$sql = "DELETE FROM penjahit WHERE id_penjahit = $id_penjahit";
if ($conn->query($sql)) {
    $_SESSION['success'] = "Data penjahit berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus data penjahit: " . $conn->error;
}

header("Location: {$base_url}/manager/master_data.php");

exit();
