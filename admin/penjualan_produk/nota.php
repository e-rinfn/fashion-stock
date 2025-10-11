<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
require_once '../../vendor/autoload.php';

function dateIndo($tanggal)
{
    $bulanIndo = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $tanggal = date('Y-m-d', strtotime($tanggal));
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulanIndo[(int)$pecah[1]] . ' ' . $pecah[0];
}

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
$pdf->SetFont('helvetica', '', 10);

// Header
$pdf->Cell(0, 6, 'NAMA PERUSAHAAN', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Jl. Contoh No. 123, Kota Tasikmalaya', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890', 0, 1, 'C');
$pdf->Ln(5);

// Judul
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'NOTA PENJUALAN PRODUK', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Ln(5);

// Informasi Penjualan dan Reseller
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(40, 5, 'No. Nota', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, '# ' . $penjualan['id_penjualan'], 0, 1);

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
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(10, 7, 'No', 1, 0, 'C');
$pdf->Cell(50, 7, 'Produk', 1);
$pdf->Cell(25, 7, 'Harga', 1, 0, 'R');
$pdf->Cell(15, 7, 'Qty', 1, 0, 'C');
$pdf->Cell(30, 7, 'Subtotal', 1, 1, 'R');

$pdf->SetFont('helvetica', '', 10);
foreach ($detail as $i => $d) {
    $pdf->Cell(10, 7, $i + 1, 1, 0, 'C');
    $pdf->Cell(50, 7, $d['nama_produk'], 1);
    $pdf->Cell(25, 7, formatRupiah($d['harga_satuan']), 1, 0, 'R');
    $pdf->Cell(15, 7, $d['jumlah'], 1, 0, 'C');
    $pdf->Cell(30, 7, formatRupiah($d['subtotal']), 1, 1, 'R');
}

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(100, 7, 'Total', 1);
$pdf->Cell(30, 7, formatRupiah($penjualan['total_harga']), 1, 1, 'R');
$pdf->Ln(4);

// Footer
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, 'Terima kasih telah berbelanja!', 0, 1, 'C');

// Output PDF ke browser
$pdf->Output('nota_penjualan_' . $id_penjualan . '.pdf', 'I');
exit();
