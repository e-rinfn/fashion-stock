<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Fungsi untuk membuat invoice baru
 */
function createInvoice($transactionId, $paymentMethod, $paidAmount = 0, $dueDate = null)
{
    global $pdo;

    // Get transaction total
    $stmt = $pdo->prepare("SELECT total FROM transactions WHERE id = ?");
    $stmt->execute([$transactionId]);
    $total = $stmt->fetchColumn();

    // Determine payment status
    if ($paymentMethod == 'kredit') {
        $status = 'cicilan';
    } elseif ($paidAmount >= $total) {
        $status = 'lunas';
    } else {
        $status = 'belum_lunas';
    }

    // Generate invoice number
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // Create invoice
    $sql = "INSERT INTO invoices (
                transaction_id, 
                no_invoice, 
                tanggal_invoice, 
                total, 
                dibayar, 
                sisa, 
                status_bayar, 
                jatuh_tempo, 
                metode_bayar
            ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $transactionId,
        $invoiceNumber,
        $total,
        $paidAmount,
        $total - $paidAmount,
        $status,
        $dueDate,
        $paymentMethod
    ]);

    $invoiceId = $pdo->lastInsertId();

    // If credit, create first installment
    if ($paymentMethod == 'kredit' && $paidAmount < $total) {
        createInstallment($invoiceId, $total - $paidAmount, $dueDate);
    }

    return $invoiceId;
}

/**
 * Fungsi untuk membuat angsuran
 */
function createInstallment($invoiceId, $amount, $dueDate, $status = 'belum_lunas')
{
    global $pdo;

    $sql = "INSERT INTO installments (
                invoice_id, 
                jumlah, 
                tanggal_jatuh_tempo, 
                status
            ) VALUES (?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$invoiceId, $amount, $dueDate, $status]);
}

/**
 * Fungsi untuk mendapatkan invoice berdasarkan ID
 */
function getInvoiceById($id)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT i.*, t.customer_nama, t.customer_kontak, t.tanggal as transaction_date
                          FROM invoices i
                          JOIN transactions t ON i.transaction_id = t.id
                          WHERE i.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan semua invoice
 */
function getAllInvoices($status = null, $startDate = null, $endDate = null)
{
    global $pdo;

    $sql = "SELECT i.*, t.customer_nama, t.customer_kontak, t.tanggal as transaction_date
            FROM invoices i
            JOIN transactions t ON i.transaction_id = t.id
            WHERE 1=1";

    $params = [];

    if ($status) {
        $sql .= " AND i.status_bayar = ?";
        $params[] = $status;
    }

    if ($startDate && $endDate) {
        $sql .= " AND i.tanggal_invoice BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }

    $sql .= " ORDER BY i.tanggal_invoice DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan angsuran berdasarkan invoice ID
 */
function getInstallmentsByInvoice($invoiceId)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM installments WHERE invoice_id = ? ORDER BY tanggal_jatuh_tempo ASC");
    $stmt->execute([$invoiceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mencatat pembayaran angsuran
 */
function recordInstallmentPayment($installmentId, $amount, $paymentDate, $paymentMethod, $proof = null)
{
    global $pdo;

    $sql = "UPDATE installments SET 
                jumlah = ?,
                tanggal_bayar = ?,
                status = 'lunas',
                metode_bayar = ?,
                bukti_bayar = ?
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$amount, $paymentDate, $paymentMethod, $proof, $installmentId]);

    // Update invoice payment status
    if ($result) {
        updateInvoicePaymentStatus($installmentId);
    }

    return $result;
}

/**
 * Fungsi untuk mengupdate status pembayaran invoice
 */
function updateInvoicePaymentStatus($installmentId)
{
    global $pdo;

    // Get invoice ID from installment
    $stmt = $pdo->prepare("SELECT invoice_id FROM installments WHERE id = ?");
    $stmt->execute([$installmentId]);
    $invoiceId = $stmt->fetchColumn();

    // Calculate total paid
    $stmt = $pdo->prepare("SELECT SUM(jumlah) FROM installments WHERE invoice_id = ? AND status = 'lunas'");
    $stmt->execute([$invoiceId]);
    $totalPaid = $stmt->fetchColumn();

    // Get invoice total
    $stmt = $pdo->prepare("SELECT total FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $total = $stmt->fetchColumn();

    // Update invoice
    $status = ($totalPaid >= $total) ? 'lunas' : 'cicilan';

    $stmt = $pdo->prepare("UPDATE invoices SET 
                            dibayar = ?,
                            sisa = ?,
                            status_bayar = ?
                          WHERE id = ?");
    return $stmt->execute([$totalPaid, $total - $totalPaid, $status, $invoiceId]);
}
