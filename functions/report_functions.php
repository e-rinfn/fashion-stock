<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Fungsi untuk mendapatkan laporan stok
 */
function getStockReport($minStock = null)
{
    global $pdo;

    $sql = "SELECT p.id, p.kode_barang, p.nama, c.nama as kategori, 
                   p.harga_beli, p.harga_jual, p.stok,
                   (p.harga_beli * p.stok) as total_nilai_beli,
                   (p.harga_jual * p.stok) as total_nilai_jual,
                   s.nama as supplier
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id";

    $params = [];

    if ($minStock !== null) {
        $sql .= " WHERE p.stok <= ?";
        $params[] = $minStock;
    }

    $sql .= " ORDER BY p.stok ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan laporan penjualan per periode
 */
function getSalesReport($startDate, $endDate)
{
    global $pdo;

    $sql = "SELECT 
                DATE(t.tanggal) as tanggal,
                COUNT(DISTINCT t.id) as jumlah_transaksi,
                SUM(td.quantity) as total_barang_terjual,
                SUM(td.subtotal) as total_penjualan,
                AVG(td.subtotal) as rata_rata_transaksi
            FROM transactions t
            JOIN transaction_details td ON t.id = td.transaction_id
            WHERE t.tipe = 'keluar' AND t.tanggal BETWEEN ? AND ?
            GROUP BY DATE(t.tanggal)
            ORDER BY t.tanggal DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan laporan produk terlaris
 */
function getBestSellingProducts($startDate = null, $endDate = null, $limit = 10)
{
    global $pdo;

    $sql = "SELECT 
                p.id, p.kode_barang, p.nama, 
                SUM(td.quantity) as total_terjual,
                SUM(td.subtotal) as total_penjualan
            FROM products p
            JOIN transaction_details td ON p.id = td.product_id
            JOIN transactions t ON td.transaction_id = t.id
            WHERE t.tipe = 'keluar'";

    $params = [];

    if ($startDate && $endDate) {
        $sql .= " AND t.tanggal BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }

    $sql .= " GROUP BY p.id
              ORDER BY total_terjual DESC
              LIMIT ?";

    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan laporan keuangan (pemasukan vs pengeluaran)
 */
function getFinancialReport($startDate, $endDate)
{
    global $pdo;

    $sql = "SELECT 
                'Pemasukan' as jenis,
                SUM(t.total) as total
            FROM transactions t
            WHERE t.tipe = 'keluar' AND t.tanggal BETWEEN ? AND ?
            
            UNION ALL
            
            SELECT 
                'Pengeluaran' as jenis,
                SUM(t.total) as total
            FROM transactions t
            WHERE t.tipe = 'masuk' AND t.tanggal BETWEEN ? AND ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan laporan angsuran
 */
function getInstallmentReport($status = null)
{
    global $pdo;

    $sql = "SELECT 
                i.id as invoice_id,
                i.no_invoice,
                t.customer_nama,
                t.customer_kontak,
                i.total,
                i.dibayar,
                i.sisa,
                i.status_bayar,
                ins.id as installment_id,
                ins.jumlah as installment_amount,
                ins.tanggal_jatuh_tempo,
                ins.status as installment_status,
                DATEDIFF(CURRENT_DATE, ins.tanggal_jatuh_tempo) as days_overdue
            FROM invoices i
            JOIN transactions t ON i.transaction_id = t.id
            LEFT JOIN installments ins ON i.id = ins.invoice_id
            WHERE i.status_bayar IN ('cicilan', 'belum_lunas')";

    $params = [];

    if ($status) {
        $sql .= " AND ins.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY ins.tanggal_jatuh_tempo ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
