<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/product_functions.php';

/**
 * Fungsi untuk membuat transaksi baru
 */
function createTransaction($type, $userId, $supplierId = null, $customerName = null, $customerContact = null, $notes = null)
{
    global $pdo;

    // Generate transaction code
    $prefix = $type == 'masuk' ? 'TRX-IN-' : 'TRX-OUT-';
    $transactionCode = $prefix . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    $sql = "INSERT INTO transactions (kode_transaksi, tipe, tanggal, user_id, supplier_id, customer_nama, customer_kontak, keterangan)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$transactionCode, $type, $userId, $supplierId, $customerName, $customerContact, $notes]);

    return $pdo->lastInsertId();
}

/**
 * Fungsi untuk menambahkan detail transaksi
 */
function addTransactionDetail($transactionId, $productId, $quantity, $unitPrice)
{
    global $pdo;

    $subtotal = $quantity * $unitPrice;

    $sql = "INSERT INTO transaction_details (transaction_id, product_id, quantity, harga_satuan, subtotal)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$transactionId, $productId, $quantity, $unitPrice, $subtotal]);

    // Update total transaksi
    if ($result) {
        updateTransactionTotal($transactionId);
    }

    return $result;
}

/**
 * Fungsi untuk mengupdate total transaksi
 */
function updateTransactionTotal($transactionId)
{
    global $pdo;

    $sql = "UPDATE transactions t
            SET total = (
                SELECT COALESCE(SUM(subtotal), 0)
                FROM transaction_details
                WHERE transaction_id = ?
            )
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$transactionId, $transactionId]);
}

/**
 * Fungsi untuk mendapatkan detail transaksi
 */
function getTransactionDetails($transactionId)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT td.*, p.nama as product_name, p.kode_barang as product_code
                          FROM transaction_details td
                          JOIN products p ON td.product_id = p.id
                          WHERE td.transaction_id = ?");
    $stmt->execute([$transactionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan semua transaksi masuk (pembelian)
 */
function getAllPurchaseTransactions($startDate = null, $endDate = null)
{
    global $pdo;

    $sql = "SELECT t.*, u.full_name as user_name, s.nama as supplier_name
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN suppliers s ON t.supplier_id = s.id
            WHERE t.tipe = 'masuk'";

    $params = [];

    if ($startDate && $endDate) {
        $sql .= " AND t.tanggal BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }

    $sql .= " ORDER BY t.tanggal DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan semua transaksi keluar (penjualan)
 */
function getAllSalesTransactions($startDate = null, $endDate = null)
{
    global $pdo;

    $sql = "SELECT t.*, u.full_name as user_name, i.no_invoice, i.status_bayar
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            LEFT JOIN invoices i ON t.id = i.transaction_id
            WHERE t.tipe = 'keluar'";

    $params = [];

    if ($startDate && $endDate) {
        $sql .= " AND t.tanggal BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }

    $sql .= " ORDER BY t.tanggal DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
