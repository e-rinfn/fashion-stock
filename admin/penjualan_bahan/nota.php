<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
require_once '../../vendor/autoload.php';

$id_penjualan_bahan = isset($_GET['id']) ? intval($_GET['id']) : 0;

$penjualan_bahan = query("SELECT p.*, r.nama_reseller, r.alamat as alamat_reseller 
                    FROM penjualan_bahan p
                    JOIN reseller r ON p.id_reseller = r.id_reseller
                    WHERE p.id_penjualan_bahan = $id_penjualan_bahan")[0] ?? null;

if (!$penjualan_bahan) {
    die('Data penjualan bahan tidak ditemukan.');
}

$detail = query("SELECT d.*, pr.nama_bahan, pr.satuan 
                FROM detail_penjualan_bahan d
                JOIN bahan_baku pr ON d.id_bahan = pr.id_bahan
                WHERE d.id_penjualan_bahan = $id_penjualan_bahan");

$total_cicilan = 0;
if ($penjualan_bahan['status_pembayaran'] == 'cicilan') {
    $cicilan = query("SELECT SUM(jumlah_cicilan_penjualan_bahan) as total FROM cicilan_penjualan_bahan WHERE id_penjualan_bahan = $id_penjualan_bahan AND status = 'lunas'")[0];
    $total_cicilan = $cicilan['total'] ?? 0;
}

// Inisialisasi TCPDF
$pdf = new TCPDF('P', 'mm', 'A5', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(8, 8, 8);
$pdf->AddPage();

// Font
$pdf->SetFont('helvetica', '', 10);

// Logo (kiri)
$logoPath = __DIR__ . '/Logo-Ipenk.png';
$pdf->Image($logoPath, 10, 10, 22); // x=10 (kiri), y=10, width=22

// Posisi teks header (kanan logo)
$pdf->SetXY(10, 15);

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 6, 'IPENK LEGEND', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Jl. Contoh No. 123, Kota Tasikmalaya', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890', 0, 1, 'C');

// Spasi ke bawah
$pdf->Ln(10);

// Garis pemisah
$y = $pdf->GetY();
$pdf->Line(0, $y, 200, $y);
$pdf->Ln(2);


// Judul
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'NOTA PENJUALAN BAHAN BAKU', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Ln(5);

// Informasi Penjualan bahan dan Reseller
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(35, 5, 'No. Transaksi', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, '#' . $penjualan_bahan['id_penjualan_bahan'], 0, 1);

$pdf->Cell(35, 5, 'Tanggal', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, dateIndo($penjualan_bahan['tanggal_penjualan_bahan']), 0, 1);

$pdf->Cell(35, 5, 'Status', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, strtoupper($penjualan_bahan['status_pembayaran']), 0, 1);

$pdf->Cell(35, 5, 'Reseller', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->MultiCell(60, 5, $penjualan_bahan['nama_reseller'], 0, 'L');

$pdf->Cell(35, 5, 'Alamat', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->MultiCell(60, 5, $penjualan_bahan['alamat_reseller'], 0, 'L');

if ($penjualan_bahan['status_pembayaran'] == 'cicilan' && $total_cicilan > 0) {
    $pdf->Cell(35, 5, 'Telah Dibayar', 0, 0);
    $pdf->Cell(3, 5, ':', 0, 0);
    $pdf->Cell(60, 5, formatRupiah($total_cicilan), 0, 1);

    $sisa_bayar = $penjualan_bahan['total_harga'] - $total_cicilan;
    $pdf->Cell(35, 5, 'Sisa Bayar', 0, 0);
    $pdf->Cell(3, 5, ':', 0, 0);
    $pdf->Cell(60, 5, formatRupiah($sisa_bayar), 0, 1);
}
$pdf->Ln(5);

// Tabel Bahan
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(245, 245, 245);

$pdf->Cell(8, 7, 'No', 1, 0, 'C', true);
$pdf->Cell(42, 7, 'Bahan Baku', 1, 0, 'L', true);
$pdf->Cell(15, 7, 'Roll/Yard', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'Meter', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Harga / Meter', 1, 0, 'R', true);
$pdf->Cell(25, 7, 'Subtotal', 1, 1, 'R', true);

$pdf->SetFont('helvetica', '', 9);

foreach ($detail as $i => $d) {

    $pdf->Cell(8, 7, $i + 1, 1, 0, 'C');

    // Potong nama bahan jika terlalu panjang
    $nama_bahan = mb_strlen($d['nama_bahan']) > 30
        ? mb_substr($d['nama_bahan'], 0, 30) . '...'
        : $d['nama_bahan'];

    $pdf->Cell(42, 7, $nama_bahan, 1, 0, 'L');
    $pdf->Cell(15, 7, $d['jumlah'], 1, 0, 'C');
    $pdf->Cell(15, 7, $d['meter'] . ' m', 1, 0, 'C');
    $pdf->Cell(25, 7, formatRupiah($d['harga_satuan']), 1, 0, 'R');
    $pdf->Cell(25, 7, formatRupiah($d['subtotal']), 1, 1, 'R');
}

// Total
$pdf->SetFont('helvetica', 'B', 9);

// Geser ke kanan agar sejajar subtotal
$pdf->Cell(105, 7, 'TOTAL', 1, 0, 'R');
$pdf->Cell(25, 7, formatRupiah($penjualan_bahan['total_harga']), 1, 1, 'R');

$pdf->Ln(4);

// Output PDF ke browser
$pdf->Output('nota_penjualan_bahan_' . $id_penjualan_bahan . '.pdf', 'I');
exit();
