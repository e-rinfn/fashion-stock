<?php
require_once '../../vendor/autoload.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

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

$id_cicilan_penjualan_bahan = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data cicilan
$cicilan_penjualan_bahan = query("
    SELECT c.*, p.tanggal_penjualan_bahan, p.total_harga, 
           r.nama_reseller, r.alamat as alamat_reseller, r.kontak as kontak_reseller,
           (SELECT SUM(jumlah_cicilan_penjualan_bahan) FROM cicilan_penjualan_bahan WHERE id_penjualan_bahan = c.id_penjualan_bahan) as total_dibayar
    FROM cicilan_penjualan_bahan c
    JOIN penjualan_bahan p ON c.id_penjualan_bahan = p.id_penjualan_bahan
    JOIN reseller r ON p.id_reseller = r.id_reseller
    WHERE c.id_cicilan_penjualan_bahan = $id_cicilan_penjualan_bahan
")[0] ?? null;

if (!$cicilan_penjualan_bahan) {
    die("Data cicilan tidak ditemukan");
}

$sisa_hutang = $cicilan_penjualan_bahan['total_harga'] - $cicilan_penjualan_bahan['total_dibayar'];

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
$pdf->Cell(0, 5, 'No Cicilan: ' . '#' . $id_cicilan_penjualan_bahan . ' | Tanggal: ' . dateIndo($cicilan_penjualan_bahan['tanggal_bayar']), 0, 1, 'C');
$pdf->Ln(5);

// Informasi Penjualan dan Reseller
$pdf->SetFont('helvetica', '',  9);
$pdf->Cell(40, 5, 'No. Penjualan Bahan', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, '# ' . $cicilan_penjualan_bahan['id_penjualan_bahan'], 0, 1);

$pdf->Cell(40, 5, 'Tanggal Penjualan', 0, 0,);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, dateIndo(($cicilan_penjualan_bahan['tanggal_penjualan_bahan'])), 0, 1);

$pdf->Cell(40, 5, 'Reseller', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, $cicilan_penjualan_bahan['nama_reseller'], 0, 1);

$pdf->Cell(40, 5, 'Kontak', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(60, 5, $cicilan_penjualan_bahan['kontak_reseller'], 0, 1);
$pdf->Ln(5);

// Pembayaran
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 6, 'Keterangan', 1, 0);
$pdf->Cell(40, 6, 'Jumlah', 1, 1, 'R');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 6, 'Pembayaran Cicilan Penjualan Bahan (' . ucfirst($cicilan_penjualan_bahan['metode_pembayaran']) . ')', 1, 0);
$pdf->Cell(40, 6, formatRupiah($cicilan_penjualan_bahan['jumlah_cicilan_penjualan_bahan']), 1, 1, 'R');

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 6, 'Total Pembayaran', 1, 0);
$pdf->Cell(40, 6, formatRupiah($cicilan_penjualan_bahan['jumlah_cicilan_penjualan_bahan']), 1, 1, 'R');
$pdf->Ln(5);

// Informasi Tambahan
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(60, 5, 'Total Harga Penjualan Bahan', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(40, 5, formatRupiah($cicilan_penjualan_bahan['total_harga']), 0, 1);

$pdf->Cell(60, 5, 'Total Dibayar', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(40, 5, formatRupiah($cicilan_penjualan_bahan['total_dibayar']), 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(60, 5, 'Sisa Hutang', 0, 0);
$pdf->Cell(3, 5, ':', 0, 0);
$pdf->Cell(40, 5, formatRupiah($sisa_hutang), 0, 1);
$pdf->Ln(5);

// Keterangan
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 5, 'Keterangan: Pembayaran ini merupakan bagian dari penjualan bahan #' . $cicilan_penjualan_bahan['id_penjualan_bahan'], 0, 'C');
$pdf->Ln(10);

// Output
$pdf->Output('nota_cicilan_penjualan_bahan' . $id_cicilan_penjualan_bahan . '.pdf', 'I');
