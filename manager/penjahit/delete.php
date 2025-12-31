<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID penjahit tidak valid";
    header("Location: list.php");
    exit();
}

$id_penjahit = intval($_GET['id']);

// Cek apakah penjahit ada
$penjahit = query("SELECT * FROM penjahit WHERE id_penjahit = $id_penjahit");
if (empty($penjahit)) {
    $_SESSION['error'] = "Data penjahit tidak ditemukan";
    header("Location: list.php");
    exit();
}

// Cek relasi dengan tabel lain
$cek_pengiriman = query("SELECT 1 FROM pengiriman_penjahit WHERE id_penjahit = $id_penjahit LIMIT 1");


// Hapus data
$sql = "DELETE FROM penjahit WHERE id_penjahit = $id_penjahit";
if ($conn->query($sql)) {
    $_SESSION['success'] = "Data penjahit berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus data penjahit: " . $conn->error;
}

header("Location: list.php");
exit();
