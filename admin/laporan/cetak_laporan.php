<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';
require_once '../../vendor/autoload.php';

// Cek session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil parameter filter
$search = isset($_REQUEST['search']) ? mysqli_real_escape_string($conn, $_REQUEST['search']) : '';
$filter_bulan = isset($_REQUEST['bulan']) ? $_REQUEST['bulan'] : '';
$filter_tahun = isset($_REQUEST['tahun']) ? intval($_REQUEST['tahun']) : date('Y');
$filter_tipe = isset($_REQUEST['tipe']) ? $_REQUEST['tipe'] : '';

// Daftar bulan untuk display
$bulan_list = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember'
];

// Query untuk detail per kategori dengan filter yang sama
$sql_detail = "SELECT 
    kk.kelompok_kategori,
    kk.nama_kategori,
    kk.tipe_kategori,
    COUNT(kt.id_transaksi) as jumlah_transaksi,
    COALESCE(SUM(kt.jumlah), 0) as total
FROM kas_kategori kk
LEFT JOIN kas_transaksi kt ON kk.id_kategori = kt.id_kategori";

// Build WHERE conditions
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(kk.kelompok_kategori LIKE '%$search%' OR kk.nama_kategori LIKE '%$search%')";
}

if (!empty($filter_tipe)) {
    $where_conditions[] = "kk.tipe_kategori = '$filter_tipe'";
}

if (!empty($filter_bulan)) {
    $where_conditions[] = "MONTH(kt.tanggal) = '$filter_bulan'";
}

if (!empty($filter_tahun)) {
    $where_conditions[] = "YEAR(kt.tanggal) = '$filter_tahun'";
}

if (!empty($where_conditions)) {
    $sql_detail .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_detail .= " GROUP BY kk.kelompok_kategori, kk.nama_kategori, kk.tipe_kategori 
                 ORDER BY kk.kelompok_kategori, kk.tipe_kategori DESC, total DESC";

$result_detail = $conn->query($sql_detail);
if (!$result_detail) {
    die("Query error: " . $conn->error);
}

$detail_data = [];
$total_all_detail = 0;
$total_all_transaksi = 0;
$total_all_kategori = 0;

while ($row = $result_detail->fetch_assoc()) {
    $kelompok = $row['kelompok_kategori'];
    if (!isset($detail_data[$kelompok])) {
        $detail_data[$kelompok] = [];
    }
    $detail_data[$kelompok][] = $row;

    // Hitung total
    $total_all_detail += $row['total'];
    $total_all_transaksi += $row['jumlah_transaksi'];
    $total_all_kategori++;
}

// Inisialisasi TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Font
$pdf->SetFont('helvetica', '', 10);

// Header
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'LAPORAN KAS', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Sistem Manajemen Kas', 0, 1, 'C');
$pdf->Ln(5);

// Informasi Filter
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'INFORMASI FILTER', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);

$filter_info = "Periode: ";
if (!empty($filter_bulan) && !empty($filter_tahun)) {
    $filter_info .= $bulan_list[$filter_bulan] . " " . $filter_tahun;
} elseif (!empty($filter_tahun)) {
    $filter_info .= "Tahun " . $filter_tahun;
} else {
    $filter_info .= "Semua Waktu";
}

if (!empty($search)) {
    $filter_info .= " | Pencarian: " . $search;
}

if (!empty($filter_tipe)) {
    $filter_info .= " | Tipe: " . $filter_tipe;
}

$pdf->Cell(0, 5, $filter_info, 0, 1);
$pdf->Ln(3);


// Jika tidak ada data
if (empty($detail_data)) {
    $pdf->SetFont('helvetica', 'I', 11);
    $pdf->Cell(0, 10, 'Tidak ada data ditemukan untuk filter yang dipilih.', 0, 1, 'C');
    $pdf->Output('laporan_kas_kosong.pdf', 'I');
    exit();
}

// Tabel Header
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'DETAIL PER KELOMPOK KATEGORI', 0, 1, 'L');
$pdf->Ln(2);

// Loop melalui setiap kelompok
$counter = 1;
foreach ($detail_data as $kelompok => $kategories) {
    // Header kelompok
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(0, 8, $counter . ". Kelompok: " . $kelompok, 0, 1, 'L', 1);
    $counter++;

    // Tabel detail kategori
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(90, 7, 'Nama Kategori', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Tipe', 1, 0, 'C');
    $pdf->Cell(30, 7, 'Jml. Transaksi', 1, 0, 'C');
    $pdf->Cell(40, 7, 'Total', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 9);
    $sub_total = 0;
    $sub_transaksi = 0;

    foreach ($kategories as $kategori) {
        $sub_total += $kategori['total'];
        $sub_transaksi += $kategori['jumlah_transaksi'];

        // Tipe dengan warna
        $tipe_text = $kategori['tipe_kategori'];
        $tipe_color = ($kategori['tipe_kategori'] == 'MASUK') ? 0 : 1; // 0=hitam, 1=merah

        $pdf->Cell(90, 7, $kategori['nama_kategori'], 1);
        $pdf->SetTextColor($tipe_color * 255, 0, 0);
        $pdf->Cell(30, 7, $tipe_text, 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(30, 7, $kategori['jumlah_transaksi'], 1, 0, 'C');

        // Format total dengan warna
        if ($kategori['tipe_kategori'] == 'MASUK') {
            $pdf->SetTextColor(0, 128, 0); // Hijau untuk masuk
        } else {
            $pdf->SetTextColor(255, 0, 0); // Merah untuk keluar
        }
        $pdf->Cell(40, 7, formatRupiah($kategori['total']), 1, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
    }

    // Sub total per kelompok
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(120, 7, 'Sub Total ' . $kelompok, 1, 0, 'L', 1);
    $pdf->Cell(30, 7, $sub_transaksi, 1, 0, 'C', 1);
    $pdf->Cell(40, 7, formatRupiah($sub_total), 1, 1, 'R', 1);

    $pdf->Ln(3);
}

// Total Keseluruhan
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(120, 8, 'TOTAL KESELURUHAN', 1, 0, 'L', 1);
$pdf->Cell(30, 8, $total_all_transaksi, 1, 0, 'C', 1);
$pdf->Cell(40, 8, formatRupiah($total_all_detail), 1, 1, 'R', 1);
$pdf->Ln(5);



// Output PDF ke browser
$pdf->Output('laporan_kas_' . date('Ymd_His') . '.pdf', 'I');
exit();
