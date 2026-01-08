<?php
// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error'] = "Silakan login terlebih dahulu";
    header("Location: {$base_url}auth/login.php");
    exit();
}

// Check parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID petugas finishing tidak valid";
    header("Location: list.php");
    exit();
}

$id_petugas_finishing = intval($_GET['id']);

try {
    // Check if petugas finishing exists
    $sql_check = "SELECT * FROM petugas_finishing WHERE id_petugas_finishing = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_petugas_finishing);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        $_SESSION['error'] = "Petugas finishing tidak ditemukan";
        header("Location: list.php");
        exit();
    }
    $stmt_check->close();

    // Check relations with hasil_kirim_finishing
    $sql_check_relation = "SELECT 1 FROM hasil_kirim_finishing WHERE id_petugas_finishing = ? LIMIT 1";
    $stmt_relation = $conn->prepare($sql_check_relation);
    $stmt_relation->bind_param("i", $id_petugas_finishing);
    $stmt_relation->execute();
    $result_relation = $stmt_relation->get_result();

    if ($result_relation->num_rows > 0) {
        $_SESSION['error'] = "Petugas finishing tidak dapat dihapus karena masih memiliki data hasil kirim finishing";
        header("Location: list.php");
        exit();
    }
    $stmt_relation->close();

    // Delete petugas finishing
    $sql_delete = "DELETE FROM petugas_finishing WHERE id_petugas_finishing = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id_petugas_finishing);

    if ($stmt_delete->execute()) {
        $_SESSION['success'] = "Petugas finishing berhasil dihapus";
    } else {
        throw new Exception("Gagal menghapus petugas finishing: " . $stmt_delete->error);
    }

    $stmt_delete->close();
} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
}

header("Location: list.php");
exit();
