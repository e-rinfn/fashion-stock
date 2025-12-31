<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Load TCPDF library
require_once '../../vendor/autoload.php';

// Fungsi untuk mendapatkan tarif upah terkini
function getTarifUpah($jenis_tarif, $tanggal_referensi = null)
{
    global $conn;

    if ($tanggal_referensi === null) {
        $tanggal_referensi = date('Y-m-d');
    }

    $sql = "SELECT tarif_per_unit 
            FROM tarif_upah 
            WHERE jenis_tarif = ? 
            AND berlaku_sejak <= ? 
            ORDER BY berlaku_sejak DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $jenis_tarif, $tanggal_referensi);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['tarif_per_unit'];
    }

    return 0.00;
}

// Ambil parameter filter
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$id_pemotong = isset($_GET['id_pemotong']) ? (int)$_GET['id_pemotong'] : 0;
$id_penjahit = isset($_GET['id_penjahit']) ? $_GET['id_penjahit'] : 0;
$id_bordir = isset($_GET['id_bordir']) ? $_GET['id_bordir'] : 0;

// Query data produksi dengan filter yang sama seperti di list.php
$sql = "SELECT h.*, p.nama_produk, p.tipe_produk, pem.nama_pemotong, 
               pen.nama_penjahit, bor.nama_bordir,
               COALESCE(h.tarif_upah, 0) as tarif_upah,
               COALESCE(h.tarif_upah_bordir, 0) as tarif_upah_bordir
        FROM hasil_potong_fix h 
        JOIN produk p ON h.id_produk = p.id_produk 
        JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
        LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
        LEFT JOIN bordir bor ON h.id_bordir = bor.id_bordir 
        WHERE 1=1";

// Filter produk
if ($id_produk > 0) {
    $sql .= " AND h.id_produk = $id_produk";
}

// Filter pemotong
if ($id_pemotong > 0) {
    $sql .= " AND h.id_pemotong = $id_pemotong";
}

// Filter penjahit
if ($id_penjahit == '-1') {
    $sql .= " AND (h.id_penjahit IS NULL OR h.id_penjahit = 0)";
} elseif ($id_penjahit > 0) {
    $sql .= " AND h.id_penjahit = $id_penjahit";
}

// Filter bordir
if ($id_bordir == '-1') {
    $sql .= " AND (h.id_bordir IS NULL OR h.id_bordir = 0)";
} elseif ($id_bordir > 0) {
    $sql .= " AND h.id_bordir = $id_bordir";
}

// Filter status
if ($status != 'all') {
    $sql .= " AND h.status_potong = '$status'";
}

// Filter periode
if (!empty($start_date)) {
    $sql .= " AND h.tanggal_hasil_potong >= '$start_date'";
}

if (!empty($end_date)) {
    $sql .= " AND h.tanggal_hasil_potong <= '$end_date'";
}

$sql .= " ORDER BY CAST(h.seri AS UNSIGNED) DESC, h.tanggal_hasil_potong DESC";

$produksi = query($sql);

// Proses data untuk PDF
$all_data = [];
foreach ($produksi as $prod) {
    // Dapatkan tarif upah
    $tarif_pemotong = getTarifUpah('pemotongan', $prod['tanggal_hasil_potong']);
    $tarif_bordir = !empty($prod['tanggal_hasil_bordir']) ?
        getTarifUpah('bordir', $prod['tanggal_hasil_bordir']) :
        getTarifUpah('bordir', $prod['tanggal_hasil_potong']);
    $tarif_penjahit = !empty($prod['tanggal_hasil_jahit']) ?
        getTarifUpah('penjahitan', $prod['tanggal_hasil_jahit']) :
        getTarifUpah('penjahitan', $prod['tanggal_hasil_potong']);

    // Hitung upah (gunakan tarif yang sudah ada di database atau tarif standar)
    // PERBAIKAN: Upah pemotong diambil langsung dari kolom total_upah di tabel hasil_potong_fix
    $upah_pemotong = !empty($prod['total_upah']) ? $prod['total_upah'] : 0;
    
    // Hitung rate pemotong implisit untuk display
    $tarif_upah_pemotong_aktual = ($prod['total_hasil'] > 0) ? ($upah_pemotong / $prod['total_hasil']) : $tarif_pemotong;

    $tarif_upah_bordir_aktual = !empty($prod['tarif_upah_bordir']) ? $prod['tarif_upah_bordir'] : $tarif_bordir;
    
    // PERBAIKAN: Upah penjahit = tarif_upah di tabel hasil_potong_fix * total_hasil_jahit
    $tarif_upah_penjahit_aktual = !empty($prod['tarif_upah']) ? $prod['tarif_upah'] : $tarif_penjahit;
    
    $upah_bordir = !empty($prod['total_hasil_bordir']) ? $prod['total_hasil_bordir'] * $tarif_upah_bordir_aktual : 0;
    $upah_penjahit = !empty($prod['total_hasil_jahit']) ? $prod['total_hasil_jahit'] * $tarif_upah_penjahit_aktual : 0;
    
    $total_upah_produksi = $upah_pemotong + $upah_bordir + $upah_penjahit;
    
    // Hitung sisa
    $sisa = $prod['total_hasil'] - ($prod['total_hasil_jahit'] ?? 0);

    $all_data[] = [
        'seri' => $prod['seri'],
        'tanggal_potong' => $prod['tanggal_hasil_potong'],
        'produk' => $prod['nama_produk'],
        'tipe_produk' => $prod['tipe_produk'],
        'pemotong' => $prod['nama_pemotong'],
        'bordir' => $prod['nama_bordir'] ?? '-',
        'penjahit' => $prod['nama_penjahit'] ?? '-',
        'status' => $prod['status_potong'],
        'total_hasil' => $prod['total_hasil'],
        'tanggal_kirim_bordir' => $prod['tanggal_kirim_bordir'] ?? null,
        'tanggal_hasil_bordir' => $prod['tanggal_hasil_bordir'] ?? null,
        'total_hasil_bordir' => $prod['total_hasil_bordir'] ?? 0,
        'tanggal_kirim_jahit' => $prod['tanggal_kirim_jahit'] ?? null,
        'tanggal_hasil_jahit' => $prod['tanggal_hasil_jahit'] ?? null,
        'total_hasil_jahit' => $prod['total_hasil_jahit'] ?? 0,
        'upah_pemotong' => $upah_pemotong,
        'upah_bordir' => $upah_bordir,
        'upah_penjahit' => $upah_penjahit,
        'total_upah' => $total_upah_produksi,
        'rate_pemotong' => $tarif_upah_pemotong_aktual,
        'rate_bordir' => $tarif_upah_bordir_aktual,
        'rate_penjahit' => $tarif_upah_penjahit_aktual,
        'sisa' => $sisa
    ];
}

// Create new PDF document (Landscape orientation untuk A4)
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Produksi');
$pdf->SetAuthor('Sistem Produksi');
$pdf->SetTitle('Laporan Produksi Lengkap');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins lebih kecil agar muat
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(TRUE, 10);

// Add a page
$pdf->AddPage();

// === HEADER PERUSAHAAN ===
// Logo (kiri)
$logoPath = __DIR__ . '/Logo-Ipenk.png';
$pdf->Image($logoPath, 10, 10, 15); // x=10 (kiri), y=10, width=22

$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell(0, 6, 'IPENK LEGEND', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Jl. Raya Cigereung No. 45, Tasikmalaya - Jawa Barat', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890 | Email: admin@ipenklegend.com', 0, 1, 'C');
$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C');
$pdf->Ln(5);

// Judul utama
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'LAPORAN PEMOTONGAN SAMPAI PENJAHITAN', 0, 1, 'C');
$pdf->Ln(3);

// Filter info
$pdf->SetFont('helvetica', '', 9);
$filter_info = "";

// Info periode
if (!empty($start_date) && !empty($end_date)) {
    $filter_info .= "Periode: " . dateIndo($start_date) . " - " . dateIndo($end_date);
} elseif (!empty($start_date)) {
    $filter_info .= "Dari: " . dateIndo($start_date);
} elseif (!empty($end_date)) {
    $filter_info .= "Sampai: " . dateIndo($end_date);
} else {
    $filter_info .= "Periode: Semua Data";
}

// Info produk
if ($id_produk > 0) {
    $produk_info = query("SELECT nama_produk FROM produk WHERE id_produk = $id_produk")[0] ?? [];
    if (!empty($produk_info)) {
        $filter_info .= " | Produk: " . $produk_info['nama_produk'];
    }
}

// Info pemotong
if ($id_pemotong > 0) {
    $pemotong_info = query("SELECT nama_pemotong FROM pemotong WHERE id_pemotong = $id_pemotong")[0] ?? [];
    if (!empty($pemotong_info)) {
        $filter_info .= " | Pemotong: " . $pemotong_info['nama_pemotong'];
    }
}

// Info status
if ($status != 'all') {
    $status_labels = [
        'diproses' => 'Potong',
        'bordir' => 'Bordir',
        'penjahitan' => 'Penjahitan',
        'selesai' => 'Selesai'
    ];
    $filter_info .= " | Status: " . ($status_labels[$status] ?? ucfirst($status));
}

$pdf->Cell(0, 5, $filter_info, 0, 1, 'L');
$pdf->Ln(5);

// Info tanggal cetak
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Dicetak pada: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
$pdf->Ln(3);

// Buat HTML table yang lebih compact untuk data lengkap
$html = '
<style>
    table { 
        border-collapse: collapse; 
        width: 100%; 
        font-size: 6pt; 
        line-height: 1.2;
    }
    th, td { 
        border: 0.4px solid #000; 
        padding: 2px; 
        vertical-align: middle; 
    }
    th { 
        background-color: #f2f2f2; 
        font-weight: bold; 
        text-align: center; 
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .small { font-size: 5pt; color: #555; }
    .total-row { 
        background-color: #e8f5e8; 
        font-weight: bold; 
    }
    .bg-potong { background-color: #fff3cd; }
    .bg-bordir { background-color: #d1ecf1; }
    .bg-penjahitan { background-color: #d1e7ff; }
    .bg-selesai { background-color: #d4edda; }
</style>

<table>
<thead>
    <tr>
        <th width="3%">No</th>
        <th width="4%">Seri</th>
        <th width="7%">Pemotong</th>
        <th width="5%">Tgl Potong</th>
        <th width="7%">Produk</th>
        <th width="4%">Hasil<br>Potong</th>
        <th width="5%">Tgl Kirim<br>Bordir</th>
        <th width="7%">Bordir</th>
        <th width="5%">Tgl<br>Bordir</th>
        <th width="4%">Hasil<br>Bordir</th>
        <th width="5%">Tgl Kirim<br>Jahit</th>
        <th width="7%">Penjahit</th>
        <th width="5%">Tgl<br>Jahit</th>
        <th width="4%">Hasil<br>Jahit</th>
        <th width="4%">Sisa</th>
        <th width="6%">Upah<br>Pemotong</th>
        <th width="6%">Upah<br>Bordir</th>
        <th width="6%">Upah<br>Penjahit</th>
        <th width="6%">Total<br>Upah</th>
    </tr>
</thead>
<tbody>';

// Isi Tabel
$no = 1;
$total_hasil_potong = 0;
$total_hasil_bordir = 0;
$total_hasil_jahit = 0;
$total_sisa = 0;
$total_upah_pemotong = 0;
$total_upah_bordir = 0;
$total_upah_penjahit = 0;
$total_upah_all = 0;

foreach ($all_data as $data) {
    // Format data untuk keamanan
    $seri = htmlspecialchars($data['seri'] ?? '-');
    $pemotong = htmlspecialchars($data['pemotong'] ?? '-');
    $produk = htmlspecialchars($data['produk'] ?? '-');
    $tipe_produk = htmlspecialchars($data['tipe_produk'] ?? '-');
    $bordir = htmlspecialchars($data['bordir'] ?? '-');
    $penjahit = htmlspecialchars($data['penjahit'] ?? '-');
    
    // Format tanggal
    $tgl_potong = !empty($data['tanggal_potong']) ? dateIndo($data['tanggal_potong']) : '-';
    $tgl_kirim_bordir = !empty($data['tanggal_kirim_bordir']) ? dateIndo($data['tanggal_kirim_bordir']) : '-';
    $tgl_bordir = !empty($data['tanggal_hasil_bordir']) ? dateIndo($data['tanggal_hasil_bordir']) : '-';
    $tgl_kirim_jahit = !empty($data['tanggal_kirim_jahit']) ? dateIndo($data['tanggal_kirim_jahit']) : '-';
    $tgl_jahit = !empty($data['tanggal_hasil_jahit']) ? dateIndo($data['tanggal_hasil_jahit']) : '-';
    
    // Format jumlah
    $hasil_potong = $data['total_hasil'] ? number_format($data['total_hasil']) . ' Pcs' : '-';
    $hasil_bordir = $data['total_hasil_bordir'] ? number_format($data['total_hasil_bordir']) . ' Pcs' : '-';
    $hasil_jahit = $data['total_hasil_jahit'] ? number_format($data['total_hasil_jahit']) . ' Pcs' : '-';
    $sisa = $data['sisa'] > 0 ? number_format($data['sisa']) . ' Pcs' : '-';
    
    // Format upah
    $upah_pemotong = $data['upah_pemotong'] > 0 ? formatRupiah($data['upah_pemotong']) : '-';
    $upah_bordir = $data['upah_bordir'] > 0 ? formatRupiah($data['upah_bordir']) : '-';
    $upah_penjahit = $data['upah_penjahit'] > 0 ? formatRupiah($data['upah_penjahit']) : '-';
    $total_upah = $data['total_upah'] > 0 ? formatRupiah($data['total_upah']) : '-';
    
    // Tentukan warna background berdasarkan status
    $status_class = '';
    switch ($data['status']) {
        case 'diproses': $status_class = 'bg-potong'; break;
        case 'bordir': $status_class = 'bg-bordir'; break;
        case 'penjahitan': $status_class = 'bg-penjahitan'; break;
        case 'selesai': $status_class = 'bg-selesai'; break;
    }
    
    // Tambah baris
    $html .= '
    <tr class="' . $status_class . '">
        <td class="text-center" width="3%">' . $no++ . '</td>
        <td class="text-center" width="4%">' . $seri . '</td>
        <td class="text-left" width="7%">' . $pemotong . '</td>
        <td class="text-center" width="5%">' . $tgl_potong . '</td>
        <td class="text-left" width="7%">' . $produk . '<br><span class="small">' . strtoupper($tipe_produk) . '</span></td>
        <td class="text-center" width="4%">' . $hasil_potong . '</td>
        <td class="text-center" width="5%">' . $tgl_kirim_bordir . '</td>
        <td class="text-left" width="7%">' . $bordir . '</td>
        <td class="text-center" width="5%">' . $tgl_bordir . '</td>
        <td class="text-center" width="4%">' . $hasil_bordir . '</td>
        <td class="text-center" width="5%">' . $tgl_kirim_jahit . '</td>
        <td class="text-left" width="7%">' . $penjahit . '</td>
        <td class="text-center" width="5%">' . $tgl_jahit . '</td>
        <td class="text-center" width="4%">' . $hasil_jahit . '</td>
        <td class="text-center" width="4%">' . $sisa . '</td>
        <td class="text-right" width="6%">' . $upah_pemotong . '</td>
        <td class="text-right" width="6%">' . $upah_bordir . '</td>
        <td class="text-right" width="6%">' . $upah_penjahit . '</td>
        <td class="text-right" width="6%"><b>' . $total_upah . '</b></td>
    </tr>';

    // Hitung total
    $total_hasil_potong += $data['total_hasil'] ?? 0;
    $total_hasil_bordir += $data['total_hasil_bordir'] ?? 0;
    $total_hasil_jahit += $data['total_hasil_jahit'] ?? 0;
    $total_sisa += $data['sisa'] ?? 0;
    $total_upah_pemotong += $data['upah_pemotong'] ?? 0;
    $total_upah_bordir += $data['upah_bordir'] ?? 0;
    $total_upah_penjahit += $data['upah_penjahit'] ?? 0;
    $total_upah_all += $data['total_upah'] ?? 0;
}

// Baris Total
$html .= '
<tr class="total-row">
    <td colspan="5" class="text-center"><b>TOTAL KESELURUHAN</b></td>
    <td class="text-center"><b>' . number_format($total_hasil_potong) . ' Pcs</b></td>
    <td class="text-center">-</td>
    <td class="text-center">-</td>
    <td class="text-center">-</td>
    <td class="text-center"><b>' . number_format($total_hasil_bordir) . ' Pcs</b></td>
    <td class="text-center">-</td>
    <td class="text-center">-</td>
    <td class="text-center">-</td>
    <td class="text-center"><b>' . number_format($total_hasil_jahit) . ' Pcs</b></td>
    <td class="text-center"><b>' . number_format($total_sisa) . ' Pcs</b></td>
    <td class="text-right"><b>' . formatRupiah($total_upah_pemotong) . '</b></td>
    <td class="text-right"><b>' . formatRupiah($total_upah_bordir) . '</b></td>
    <td class="text-right"><b>' . formatRupiah($total_upah_penjahit) . '</b></td>
    <td class="text-right"><b>' . formatRupiah($total_upah_all) . '</b></td>
</tr>
</tbody>
</table>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Summary section
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'RINGKASAN PRODUKSI', 0, 1, 'L');
$pdf->Ln(3);

// Create summary table
$summary_html = '
<style>
    .summary-table { width: 100%; font-size: 9pt; }
    .summary-table td { padding: 4px; }
    .summary-header { background-color: #f8f9fa; font-weight: bold; }
    .summary-value { text-align: right; font-weight: bold; }
</style>

<table class="summary-table">
    <tr>
        <td width="40%">Total Hasil Potong:</td>
        <td width="10%"></td>
        <td width="50%" class="summary-value">' . number_format($total_hasil_potong) . ' Pcs</td>
    </tr>
    <tr>
        <td>Total Hasil Bordir:</td>
        <td></td>
        <td class="summary-value">' . number_format($total_hasil_bordir) . ' Pcs</td>
    </tr>
    <tr>
        <td>Total Hasil Jahit:</td>
        <td></td>
        <td class="summary-value">' . number_format($total_hasil_jahit) . ' Pcs</td>
    </tr>
    <tr>
        <td>Total Sisa:</td>
        <td></td>
        <td class="summary-value">' . number_format($total_sisa) . ' Pcs</td>
    </tr>
    <tr>
        <td colspan="3"><hr></td>
    </tr>
    <tr>
        <td>Total Upah Pemotong:</td>
        <td></td>
        <td class="summary-value">' . formatRupiah($total_upah_pemotong) . '</td>
    </tr>
    <tr>
        <td>Total Upah Bordir:</td>
        <td></td>
        <td class="summary-value">' . formatRupiah($total_upah_bordir) . '</td>
    </tr>
    <tr>
        <td>Total Upah Penjahit:</td>
        <td></td>
        <td class="summary-value">' . formatRupiah($total_upah_penjahit) . '</td>
    </tr>
    <tr>
        <td colspan="3"><hr></td>
    </tr>
    <tr class="total-row">
        <td><strong>TOTAL KESELURUHAN UPAH:</strong></td>
        <td></td>
        <td class="summary-value" style="color: #198754; font-size: 10pt;"><strong>' . formatRupiah($total_upah_all) . '</strong></td>
    </tr>
</table>';

$pdf->writeHTML($summary_html, true, false, true, false, '');

// Footer dengan informasi jumlah data
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Jumlah data yang ditampilkan: ' . count($all_data) . ' produksi', 0, 1, 'L');

// Legenda status
$pdf->SetFont('helvetica', '', 8);
$pdf->Ln(5);
$pdf->Cell(0, 5, 'Status:', 0, 1, 'L');
$pdf->Cell(5, 4, '', 0, 0, 'L');
$pdf->SetFillColor(255, 243, 205);
$pdf->Cell(5, 4, '', 1, 0, 'C', true);
$pdf->Cell(2, 4, '', 0, 0, 'L');
$pdf->Cell(20, 4, 'Potong', 0, 0, 'L');
$pdf->Cell(5, 4, '', 0, 0, 'L');
$pdf->SetFillColor(209, 236, 241);
$pdf->Cell(5, 4, '', 1, 0, 'C', true);
$pdf->Cell(2, 4, '', 0, 0, 'L');
$pdf->Cell(20, 4, 'Bordir', 0, 0, 'L');
$pdf->Cell(5, 4, '', 0, 0, 'L');
$pdf->SetFillColor(209, 231, 255);
$pdf->Cell(5, 4, '', 1, 0, 'C', true);
$pdf->Cell(2, 4, '', 0, 0, 'L');
$pdf->Cell(20, 4, 'Penjahitan', 0, 0, 'L');
$pdf->Cell(5, 4, '', 0, 0, 'L');
$pdf->SetFillColor(212, 237, 218);
$pdf->Cell(5, 4, '', 1, 0, 'C', true);
$pdf->Cell(2, 4, '', 0, 0, 'L');
$pdf->Cell(20, 4, 'Selesai', 0, 1, 'L');

// Close and output PDF document
$pdf->Output('laporan_produksi_lengkap_' . date('Y-m-d_His') . '.pdf', 'I');