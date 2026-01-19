<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
require_once '../../vendor/autoload.php';

$id_penjualan = isset($_GET['id']) ? intval($_GET['id']) : 0;

$penjualan = query("SELECT p.*, r.nama_reseller, r.alamat as alamat_reseller 
                    FROM penjualan p
                    JOIN reseller r ON p.id_reseller = r.id_reseller
                    WHERE p.id_penjualan = $id_penjualan")[0] ?? null;

if (!$penjualan) {
    die('Data penjualan tidak ditemukan.');
}

$detail = query("SELECT d.*, pr.nama_produk 
                FROM detail_penjualan d
                JOIN produk pr ON d.id_produk = pr.id_produk
                WHERE d.id_penjualan = $id_penjualan");

$total_cicilan = 0;
if ($penjualan['status_pembayaran'] == 'cicilan') {
    $cicilan = query("SELECT SUM(jumlah_cicilan) as total FROM cicilan WHERE id_penjualan = $id_penjualan AND status = 'lunas'")[0];
    $total_cicilan = $cicilan['total'] ?? 0;
}

// Inisialisasi TCPDF
$pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Font
$pdf->SetFont('times', '', 10);

// Logo (kiri)
$logoPath = __DIR__ . '/Logo-Ipenk.png';
$pdf->Image($logoPath, 10, 10, 22); // x=10 (kiri), y=10, width=22

// Posisi teks header (kanan logo)
$pdf->SetXY(10, 10);

$pdf->SetFont('times', 'B', 12);
$pdf->Cell(0, 6, 'IPENK LEGEND', 0, 1, 'C');

$pdf->SetFont('times', '', 9);
$pdf->Cell(0, 5, 'Jl. Raya Cigereung No. 45, Tasikmalaya - Jawa Barat', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890 | Email: admin@ipenklegend.com', 0, 1, 'C');

// Tanggal cetak
$pdf->SetFont('times', '', 9);
$pdf->Cell(0, 5, 'Tanggal Cetak: ' . dateIndo(date('Y-m-d')) . ' | ' . date('H:i') . ' WIB', 0, 1, 'C');

// Spasi ke bawah
$pdf->Ln(10);

// Garis pemisah
$y = $pdf->GetY();
$pdf->Line(0, $y, 200, $y);
$pdf->Ln(2);


// Judul
$pdf->SetFont('times', 'B', 11);
$pdf->Cell(0, 6, 'NOTA PENJUALAN PRODUK', 0, 1, 'C');
$pdf->SetFont('times', '', 9);
$pdf->Ln(5);

// Informasi Penjualan dan Reseller
$pdf->SetFont('times', '', 9);
$pdf->Cell(40, 5, 'No. Nota', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, '#' . $penjualan['id_penjualan'], 0, 1);

$pdf->Cell(40, 5, 'Tanggal Penjualan', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, dateIndo($penjualan['tanggal_penjualan']), 0, 1);

$pdf->Cell(40, 5, 'Status', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, $penjualan['status_pembayaran'], 0, 1);

$pdf->Cell(40, 5, 'Dibayar', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);

if ($total_cicilan > 0) {
    $pdf->Cell(60, 5, formatRupiah($total_cicilan) . ' dari ' . formatRupiah($penjualan['total_harga']), 0, 1);
} else {
    $pdf->Cell(60, 5, '- dari ' . formatRupiah($penjualan['total_harga']), 0, 1);
}

$pdf->Cell(40, 5, 'Reseller', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, $penjualan['nama_reseller'], 0, 1);

$pdf->Cell(40, 5, 'Alamat', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, $penjualan['alamat_reseller'], 0, 1);
$pdf->Ln(5);

// Tabel Produk
$pdf->SetFont('times', 'B', 10);
$pdf->Cell(10, 7, 'No', 1, 0, 'C');
$pdf->Cell(50, 7, 'Produk', 1);
$pdf->Cell(25, 7, 'Harga', 1, 0, 'R');
$pdf->Cell(15, 7, 'Pcs', 1, 0, 'C');
$pdf->Cell(30, 7, 'Subtotal', 1, 1, 'R');

$pdf->SetFont('times', '', 10);
foreach ($detail as $i => $d) {
    $pdf->Cell(10, 7, $i + 1, 1, 0, 'C');
    $pdf->Cell(50, 7, $d['nama_produk'], 1);
    $pdf->Cell(25, 7, formatRupiah($d['harga_satuan']), 1, 0, 'R');
    $pdf->Cell(15, 7, $d['jumlah'], 1, 0, 'C');
    $pdf->Cell(30, 7, formatRupiah($d['subtotal']), 1, 1, 'R');
}

$pdf->SetFont('times', 'B', 10);
$pdf->Cell(100, 7, 'TOTAL', 1, 0, 'R');
$pdf->Cell(30, 7, formatRupiah($penjualan['total_harga']), 1, 1, 'R');
$pdf->Ln(4);

// Footer
$pdf->SetFont('times', '', 9);
$pdf->Cell(0, 6, 'Terima kasih telah berbelanja!', 0, 1, 'C');

// Output PDF ke browser
$pdf->Output('nota_penjualan_' . $id_penjualan . '.pdf', 'I');
exit();
