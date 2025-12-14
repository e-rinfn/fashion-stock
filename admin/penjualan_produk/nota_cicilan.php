<?php
// Tambahkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../vendor/autoload.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';


$id_cicilan_penjualan = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_cicilan_penjualan <= 0) {
    die("ID cicilan tidak valid");
}

// Perbaiki query sesuai struktur database
$cicilan_penjualan = query("
    SELECT 
        c.*, 
        p.tanggal_penjualan, 
        p.total_harga, 
        p.status_pembayaran,
        r.nama_reseller, 
        r.alamat as alamat_reseller, 
        r.kontak as kontak_reseller,
        (SELECT COALESCE(SUM(jumlah_cicilan), 0) FROM cicilan WHERE id_penjualan = c.id_penjualan) as total_dibayar
    FROM cicilan c
    JOIN penjualan p ON c.id_penjualan = p.id_penjualan
    JOIN reseller r ON p.id_reseller = r.id_reseller
    WHERE c.id_cicilan = $id_cicilan_penjualan
");

// Cek apakah data ditemukan
if (empty($cicilan_penjualan)) {
    die("Data cicilan tidak ditemukan");
}

$cicilan_penjualan = $cicilan_penjualan[0];

// Hitung sisa hutang
$sisa_hutang = $cicilan_penjualan['total_harga'] - $cicilan_penjualan['total_dibayar'];

// Inisialisasi TCPDF
$pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Font
$pdf->SetFont('helvetica', '', 10);

// Header
$pdf->Cell(0, 6, 'NAMA PERUSAHAAN', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Jl. Contoh No. 123, Kota Tasikmalaya', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890', 0, 1, 'C');
$pdf->Ln(5);

// Judul
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'NOTA PEMBAYARAN CICILAN', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'No Cicilan: #' . $id_cicilan_penjualan . ' | Tanggal: ' . dateIndo($cicilan_penjualan['tanggal_bayar']), 0, 1, 'C');
$pdf->Ln(5);

// Informasi Penjualan dan Reseller
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(40, 5, 'No. Penjualan', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, '# ' . $cicilan_penjualan['id_penjualan'], 0, 1);

$pdf->Cell(40, 5, 'Tanggal Penjualan', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, dateIndo($cicilan_penjualan['tanggal_penjualan']), 0, 1);

$pdf->Cell(40, 5, 'Reseller', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, $cicilan_penjualan['nama_reseller'], 0, 1);

$pdf->Cell(40, 5, 'Kontak', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, $cicilan_penjualan['kontak_reseller'] ?? '-', 0, 1);
$pdf->Ln(5);

// Pembayaran
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 6, 'Keterangan', 1, 0);
$pdf->Cell(40, 6, 'Jumlah', 1, 1, 'R');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 6, 'Pembayaran Cicilan (' . ucfirst($cicilan_penjualan['metode_pembayaran'] ?? '-') . ')', 1, 0);
$pdf->Cell(40, 6, formatRupiah($cicilan_penjualan['jumlah_cicilan']), 1, 1, 'R');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 6, 'Total Pembayaran', 1, 0);
$pdf->Cell(40, 6, formatRupiah($cicilan_penjualan['jumlah_cicilan']), 1, 1, 'R');
$pdf->Ln(5);

// Informasi Tambahan
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(60, 5, 'Total Harga Penjualan', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(40, 5, formatRupiah($cicilan_penjualan['total_harga']), 0, 1);

$pdf->Cell(60, 5, 'Total Dibayar', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(40, 5, formatRupiah($cicilan_penjualan['total_dibayar']), 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 5, 'Sisa Hutang', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(40, 5, formatRupiah($sisa_hutang), 0, 1);
$pdf->Ln(5);

// Keterangan
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 5, 'Keterangan: Pembayaran ini merupakan bagian dari penjualan produk #' . $cicilan_penjualan['id_penjualan'], 0, 'C');
$pdf->Ln(5);

// Output
$pdf->Output('nota_cicilan_' . $id_cicilan_penjualan . '.pdf', 'I');
