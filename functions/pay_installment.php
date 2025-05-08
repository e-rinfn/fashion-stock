<?php
require_once '../config/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/invoices/installments.php');
    exit;
}

require_once '../config/database.php';

// Data dari form
$installment_id = $_POST['installment_id'];
$invoice_id = $_POST['invoice_id'];
$payment_date = $_POST['payment_date'];
$payment_method = $_POST['payment_method'];

// Handle file upload
$payment_proof_path = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../../assets/payment_proofs/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
    $filename = 'proof_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target_path)) {
        $payment_proof_path = $filename;
    }
}

try {
    $pdo->beginTransaction();

    // 1. Update status angsuran
    $update_installment = $pdo->prepare("
        UPDATE installments 
        SET tanggal_bayar = ?,
            metode_bayar = ?,
            bukti_bayar = ?,
            status = 'lunas'
        WHERE id = ?
    ");
    $update_installment->execute([
        $payment_date,
        $payment_method,
        $payment_proof_path,
        $installment_id
    ]);

    // 2. Dapatkan jumlah angsuran
    $get_installment = $pdo->prepare("SELECT jumlah FROM installments WHERE id = ?");
    $get_installment->execute([$installment_id]);
    $installment = $get_installment->fetch();

    if (!$installment) {
        throw new Exception("Data angsuran tidak ditemukan");
    }

    // 3. Update invoice (tambah jumlah dibayar)
    $update_invoice = $pdo->prepare("
        UPDATE invoices 
        SET dibayar = dibayar + ?,
            sisa = GREATEST(0, total - (dibayar + ?))
        WHERE id = ?
    ");
    $update_invoice->execute([
        $installment['jumlah'],
        $installment['jumlah'],
        $invoice_id
    ]);

    // 4. Cek apakah invoice sudah lunas
    $check_invoice = $pdo->prepare("
        SELECT id, total, dibayar 
        FROM invoices 
        WHERE id = ? AND dibayar >= total
    ");
    $check_invoice->execute([$invoice_id]);
    $paid_invoice = $check_invoice->fetch();

    if ($paid_invoice) {
        // 5. Update status invoice jadi lunas
        $pdo->prepare("UPDATE invoices SET status_bayar = 'lunas' WHERE id = ?")
            ->execute([$invoice_id]);

        // 6. Update semua angsuran yang belum dibayar
        $update_all_installments = $pdo->prepare("
            UPDATE installments
            SET status = 'lunas',
                tanggal_bayar = COALESCE(tanggal_bayar, ?),
                metode_bayar = COALESCE(metode_bayar, ?)
            WHERE invoice_id = ? AND status != 'lunas'
        ");
        $update_all_installments->execute([
            $payment_date,
            $payment_method,
            $invoice_id
        ]);
    }

    $pdo->commit();

    $_SESSION['success'] = "Pembayaran angsuran berhasil dicatat";
} catch (Exception $e) {
    $pdo->rollBack();

    // Hapus file bukti bayar jika upload gagal
    if ($payment_proof_path && file_exists($upload_dir . $payment_proof_path)) {
        unlink($upload_dir . $payment_proof_path);
    }

    $_SESSION['error'] = "Gagal mencatat pembayaran: " . $e->getMessage();
}

header("Location: ../admin/invoices/view.php?id=$invoice_id");
exit;
