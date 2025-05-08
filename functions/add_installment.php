<?php
require_once '../config/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/invoices/installments.php');
    exit;
}

require_once '../config/database.php';

$invoice_id = $_POST['invoice_id'];
$amount = (float) $_POST['amount'];
$due_date = $_POST['due_date'];

// Validasi data
$stmt = $pdo->prepare("SELECT sisa FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    $_SESSION['error'] = "Invoice tidak ditemukan";
    header('Location: ../admin/invoices/installments.php');
    exit;
}

if ($amount <= 0 || $amount > $invoice['sisa']) {
    $_SESSION['error'] = "Jumlah angsuran tidak valid";
    header('Location: ../admin/invoices/installments.php');
    exit;
}

// Tambah angsuran
try {
    $stmt = $pdo->prepare("
        INSERT INTO installments (
            invoice_id, 
            jumlah, 
            tanggal_jatuh_tempo, 
            status
        ) VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_id,
        $amount,
        $due_date,
        'belum_lunas'
    ]);

    $_SESSION['success'] = "Angsuran berhasil ditambahkan";
    header("Location: ../admin/invoices/view.php?id=$invoice_id");
} catch (PDOException $e) {
    $_SESSION['error'] = "Gagal menambahkan angsuran: " . $e->getMessage();
    header("Location: ../admin/invoices/view.php?id=$invoice_id");
}
