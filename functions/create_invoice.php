<?php
require_once '../config/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/invoices/create.php');
    exit;
}

require_once '../config/database.php';

$transaction_id = $_POST['transaction_id'];
$metode_bayar = $_POST['metode_bayar'];
$dibayar = (float) $_POST['dibayar'];
$jatuh_tempo = $_POST['jatuh_tempo'] ?? null;

// Validasi data
$stmt = $pdo->prepare("SELECT total FROM transactions WHERE id = ?");
$stmt->execute([$transaction_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    $_SESSION['error'] = "Transaksi tidak ditemukan";
    header('Location: ../admin/invoices/create.php');
    exit;
}

if ($dibayar > $transaction['total']) {
    $_SESSION['error'] = "Jumlah dibayar tidak boleh melebihi total tagihan";
    header("Location: ../admin/invoices/view.php?transaction_id=$transaction_id");
    exit;
}

// Tentukan status pembayaran
if ($metode_bayar === 'kredit') {
    $status_bayar = $dibayar > 0 ? 'cicilan' : 'belum_lunas';
} else {
    $status_bayar = $dibayar >= $transaction['total'] ? 'lunas' : 'belum_lunas';
}

// Generate invoice number
$invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Buat invoice
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO invoices (
            transaction_id, 
            no_invoice, 
            tanggal_invoice, 
            total, 
            dibayar, 
            sisa, 
            status_bayar, 
            jatuh_tempo, 
            metode_bayar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $transaction_id,
        $invoice_number,
        date('Y-m-d'),
        $transaction['total'],
        $dibayar,
        $transaction['total'] - $dibayar,
        $status_bayar,
        $jatuh_tempo,
        $metode_bayar
    ]);

    $invoice_id = $pdo->lastInsertId();

    // Jika kredit dan ada sisa, buat angsuran
    if ($metode_bayar === 'kredit' && ($transaction['total'] - $dibayar) > 0) {
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
            $transaction['total'] - $dibayar,
            $jatuh_tempo,
            'belum_lunas'
        ]);
    }

    $pdo->commit();

    $_SESSION['success'] = "Invoice berhasil dibuat";
    header("Location: ../admin/invoices/view.php?id=$invoice_id");
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Gagal membuat invoice: " . $e->getMessage();
    header("Location: ../admin/invoices/view.php?transaction_id=$transaction_id");
}
