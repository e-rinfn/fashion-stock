<?php
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

    return 700.00;
}


// Ambil parameter filter
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Query data produksi
$sql = "SELECT h.*, p.nama_produk, pem.nama_pemotong, 
               pen.nama_penjahit,
               (SELECT SUM(jumlah) FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = h.id_hasil_potong_fix) as total_hasil_potong
        FROM hasil_potong_fix h 
        JOIN produk p ON h.id_produk = p.id_produk 
        JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
        LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
        WHERE 1=1";

if ($id_produk > 0) {
    $sql .= " AND h.id_produk = $id_produk";
}

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

$sql .= " ORDER BY h.tanggal_hasil_potong DESC";

$produksi = query($sql);

// Proses data untuk PDF
$all_data = [];
foreach ($produksi as $prod) {
    $tarif_pemotong = getTarifUpah('pemotongan', $prod['tanggal_hasil_potong']);
    $tarif_penjahit = !empty($prod['tanggal_hasil_jahit']) ?
        getTarifUpah('penjahitan', $prod['tanggal_hasil_jahit']) :
        getTarifUpah('penjahitan', $prod['tanggal_hasil_potong']);

    $upah_pemotong = $prod['total_hasil'] * $tarif_pemotong;
    $upah_penjahit = !empty($prod['total_hasil_jahit']) ? $prod['total_hasil_jahit'] * $tarif_penjahit : 0;
    $total_upah = $upah_pemotong + $upah_penjahit;

    $all_data[] = [
        'seri' => $prod['seri'],
        'tanggal' => $prod['tanggal_hasil_potong'],
        'produk' => $prod['nama_produk'],
        'pemotong' => $prod['nama_pemotong'],
        'penjahit' => $prod['nama_penjahit'],
        'status' => $prod['status_potong'],
        'total_hasil' => $prod['total_hasil'],
        'tanggal_kirim_jahit' => $prod['tanggal_kirim_jahit'],
        'tanggal_hasil_jahit' => $prod['tanggal_hasil_jahit'],
        'total_hasil_jahit' => $prod['total_hasil_jahit'],
        'upah_pemotong' => $upah_pemotong,
        'upah_penjahit' => $upah_penjahit,
        'total_upah' => $total_upah,
        'rate_pemotong' => $tarif_pemotong,
        'rate_penjahit' => $tarif_penjahit
    ];
}

// Create new PDF document (Landscape orientation untuk A4)
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Produksi');
$pdf->SetAuthor('Sistem Produksi');
$pdf->SetTitle('Laporan Produksi');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins lebih kecil agar muat
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(TRUE, 10);

// Add a page
$pdf->AddPage();

// === HEADER PERUSAHAAN ===
$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell(0, 6, 'IRVEENA FASHION', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Jl. Raya Cigereung No. 45, Tasikmalaya - Jawa Barat', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890 | Email: admin@irveena.com', 0, 1, 'C');
$pdf->Ln(2);
$pdf->Cell(0, 0, '', 'T', 1, 'C');
$pdf->Ln(5);

// Set font untuk judul
$pdf->SetFont('helvetica', 'B', 14);


// Title
$pdf->Cell(0, 8, 'LAPORAN PRODUKSI', 0, 1, 'C');
$pdf->Ln(3);

// Filter info
$pdf->SetFont('helvetica', '', 9);
$filter_info = "Periode: " . date('d F Y');
if (!empty($start_date) && !empty($end_date)) {
    $filter_info = "Periode: " . dateIndo($start_date) . " - " . dateIndo($end_date);
} elseif (!empty($start_date)) {
    $filter_info = "Dari: " . dateIndo($start_date);
} elseif (!empty($end_date)) {
    $filter_info = "Sampai: " . dateIndo($end_date);
}

if ($id_produk > 0) {
    $produk_info = query("SELECT nama_produk FROM produk WHERE id_produk = $id_produk")[0];
    $filter_info .= " | Produk: " . $produk_info['nama_produk'];
}
if ($status != 'all') {
    $filter_info .= " | Status: " . ucfirst($status);
}

$pdf->Cell(0, 0, $filter_info, 0, 1, 'L');
$pdf->Ln(5);

// Buat HTML table yang lebih compact
$html = '
<style>
    table { 
        border-collapse: collapse; 
        width: 100%; 
        font-size: 7pt; 
    }
    th, td { 
        border: 0.5px solid #000; 
        padding: 3px; 
        vertical-align: top; 
    }
    th { 
        background-color: #f8f9fa; 
        font-weight: bold; 
        text-align: center; 
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .small { font-size: 6pt; color: #555; }
    .total-row { 
        background-color: #e8f5e8; 
        font-weight: bold; 
    }
</style>

<table>
<thead>
    <tr class="text-center">
        <th width="4%">No</th>
        <th width="6%">Seri</th>
        <th width="11%">Pemotong</th>
        <th width="7%">Tgl Potong</th>
        <th width="11%">Produk</th>
        <th width="6%">Hasil<br>Potong</th>
        <th width="9%">Upah<br>Pemotong</th>
        <th width="6%">Tgl Kirim Jahit</th>
        <th width="11%">Penjahit</th>
        <th width="7%">Tgl Jahit</th>
        <th width="6%">Hasil<br>Jahit</th>
        <th width="9%">Upah<br>Penjahit</th>
        <th width="7%">Total<br>Upah</th>
    </tr>
</thead>
<tbody>';


// Isi Tabel
$no = 1;
$total_upah_pemotong = 0;
$total_upah_penjahit = 0;
$total_keseluruhan = 0;

foreach ($all_data as $data) {
    // Pastikan nilai aman
    $seri = htmlspecialchars($data['seri'] ?? '-');
    $pemotong = htmlspecialchars($data['pemotong'] ?? '-');
    $produk = htmlspecialchars($data['produk'] ?? '-');
    $penjahit = htmlspecialchars($data['penjahit'] ?? '-');
    $tanggal_kirim_jahit = !empty($data['tanggal_kirim_jahit']) ? dateIndo($data['tanggal_kirim_jahit']) : '-';

    $tgl_potong = !empty($data['tanggal']) ? dateIndo($data['tanggal']) : '-';
    $tgl_jahit = !empty($data['tanggal_hasil_jahit']) ? dateIndo($data['tanggal_hasil_jahit']) : '-';

    $hasil_potong = $data['total_hasil'] ? $data['total_hasil'] . ' Pcs' : '-';
    $hasil_jahit = $data['total_hasil_jahit'] ? $data['total_hasil_jahit'] . ' Pcs' : '-';

    $rate_pemotong = isset($data['rate_pemotong']) ? formatRupiah($data['rate_pemotong']) : '0';
    $rate_penjahit = isset($data['rate_penjahit']) ? formatRupiah($data['rate_penjahit']) : '0';

    $upah_pemotong = isset($data['upah_pemotong']) ? formatRupiah($data['upah_pemotong']) : '0';
    $upah_penjahit = isset($data['upah_penjahit']) ? formatRupiah($data['upah_penjahit']) : '0';
    $total_upah = isset($data['total_upah']) ? formatRupiah($data['total_upah']) : '0';

    // Tambah baris
    $html .= '
    <tr>
        <td class="text-center" width="4%">' . $no++ . '</td>
        <td class="text-center" width="6%">' . $seri . '</td>
        <td class="text-left" width="11%">' . $pemotong . '<br></td>
        <td class="text-center" width="7%">' . $tgl_potong . '</td>
        <td class="text-left" width="11%">' . $produk . '</td>
        <td class="text-center" width="6%">' . $hasil_potong . '</td>
        <td class="text-right" width="9%">' . $upah_pemotong . '</td>
        <td class="text-center" width="6%">' . $tanggal_kirim_jahit . '</td>
        <td class="text-left" width="11%">' . ($penjahit != '-' ? $penjahit . '<br>' : '-') . '</td>
        <td class="text-center" width="7%">' . $tgl_jahit . '</td>
        <td class="text-center" width="6%">' . $hasil_jahit . '</td>
        <td class="text-right" width="9%">' . ($hasil_jahit != '-' ? $upah_penjahit : '-') . '</td>
        <td class="text-right" width="7%"><b>' . $total_upah . '</b></td>
    </tr>';

    // Hitung total
    $total_upah_pemotong += $data['upah_pemotong'] ?? 0;
    $total_upah_penjahit += $data['upah_penjahit'] ?? 0;
    $total_keseluruhan += $data['total_upah'] ?? 0;
}

// Baris Total
$html .= '
<tr class="total-row">
    <td colspan="6" class="text-center"><b>TOTAL</b></td>
    <td class="text-right"><b>' . formatRupiah($total_upah_pemotong) . '</b></td>
    <td class="text-center">-</td>
    <td class="text-center">-</td>
    <td class="text-center">-</td>
    <td class="text-center">-</td>
    <td class="text-right"><b>' . formatRupiah($total_upah_penjahit) . '</b></td>
    <td class="text-right"><b>' . formatRupiah($total_keseluruhan) . '</b></td>
</tr>
</tbody>
</table>';



// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Summary dengan font lebih kecil
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'RINGKASAN:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(0, 4, 'Total Upah Pemotong: ' . formatRupiah($total_upah_pemotong), 0, 1, 'L');
$pdf->Cell(0, 4, 'Total Upah Penjahit: ' . formatRupiah($total_upah_penjahit), 0, 1, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'Total Keseluruhan: ' . formatRupiah($total_keseluruhan), 0, 1, 'L');


// Close and output PDF document
$pdf->Output('laporan_produksi_' . date('Y-m-d') . '.pdf', 'I');
