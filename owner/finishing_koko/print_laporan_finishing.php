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
$id_petugas_finishing = isset($_GET['id_petugas_finishing']) ? (int)$_GET['id_petugas_finishing'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Query untuk mengambil data kirim finishing (sama seperti di finishing.php)
$sql = "SELECT 
            hk.*, 
            p.nama_produk, 
            pet.nama_petugas,
            GROUP_CONCAT(DISTINCT k.nama_koko ORDER BY k.nama_koko SEPARATOR ', ') as jenis_bahan,
            COUNT(DISTINCT dh.id_koko) as jumlah_jenis_bahan,
            SUM(dh.jumlah) as total_bahan
        FROM hasil_kirim_finishing hk 
        LEFT JOIN produk p ON hk.id_produk = p.id_produk 
        LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
        LEFT JOIN detail_hasil_kirim_finishing dh ON hk.id_hasil_kirim_finishing = dh.id_hasil_kirim_finishing
        LEFT JOIN koko k ON dh.id_koko = k.id_koko
        WHERE 1=1";

// Filter petugas finishing
if ($id_petugas_finishing > 0) {
    $sql .= " AND hk.id_petugas_finishing = $id_petugas_finishing";
}

// Filter status
if ($status != 'all') {
    $sql .= " AND hk.status_finishing = '$status'";
}

// Filter periode
if (!empty($start_date)) {
    $sql .= " AND hk.tanggal_kirim_finishing >= '$start_date'";
}

if (!empty($end_date)) {
    $end_date .= ' 23:59:59';
    $sql .= " AND hk.tanggal_kirim_finishing <= '$end_date'";
}

// GROUP BY dengan kolom utama
$sql .= " GROUP BY hk.id_hasil_kirim_finishing, hk.tanggal_kirim_finishing, hk.id_produk, 
          hk.total_kirim, hk.status_finishing, p.nama_produk, pet.nama_petugas";

$sql .= " ORDER BY hk.tanggal_kirim_finishing DESC";

$finishing_data = query($sql);

// Proses data untuk PDF
$all_data = [];
foreach ($finishing_data as $data) {
    // Dapatkan tarif upah finishing
    $tanggal_referensi = !empty($data['tanggal_hasil_finishing']) ?
        $data['tanggal_hasil_finishing'] : $data['tanggal_kirim_finishing'];

    $tarif_finishing = getTarifUpah('finishing', $tanggal_referensi);

    // Hitung upah finishing
    $upah_finishing = ($data['total_hasil_finishing'] ?? 0) * $tarif_finishing;

    // Format data untuk PDF
    $all_data[] = [
        'tanggal_kirim' => $data['tanggal_kirim_finishing'],
        'produk' => $data['nama_produk'] ?? '-',
        'petugas' => $data['nama_petugas'] ?? '-',
        'status' => $data['status_finishing'],
        'total_kirim' => $data['total_kirim'] ?? 0,
        'jenis_bahan' => $data['jenis_bahan'] ?? '-',
        'jumlah_jenis_bahan' => $data['jumlah_jenis_bahan'] ?? 0,
        'total_bahan' => $data['total_bahan'] ?? 0,
        'tanggal_hasil' => $data['tanggal_hasil_finishing'] ?? null,
        'total_hasil_finishing' => $data['total_hasil_finishing'] ?? 0,
        'tarif_finishing' => $tarif_finishing,
        'upah_finishing' => $upah_finishing
    ];
}

// Create new PDF document (Landscape orientation untuk A4)
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Produksi');
$pdf->SetAuthor('Sistem Produksi');
$pdf->SetTitle('Laporan Finishing');

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
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 10, 10, 15);
}

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
$pdf->Cell(0, 8, 'LAPORAN DATA FINISHING KOKO', 0, 1, 'C');
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

// Info petugas finishing
if ($id_petugas_finishing > 0) {
    $petugas_info = query("SELECT nama_petugas FROM petugas_finishing WHERE id_petugas_finishing = $id_petugas_finishing")[0] ?? [];
    if (!empty($petugas_info)) {
        $filter_info .= " | Petugas: " . $petugas_info['nama_petugas'];
    }
}

// Info status
if ($status != 'all') {
    $status_labels = [
        'pengiriman' => 'Pengiriman',
        'diproses' => 'Diproses',
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

// Buat HTML table yang compact untuk data finishing
$html = '
<style>
    table { 
        border-collapse: collapse; 
        width: 100%; 
        font-size: 7pt; 
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
    .small { font-size: 6pt; color: #555; }
    .total-row { 
        background-color: #e8f5e8; 
        font-weight: bold; 
    }
    .bg-pengiriman { background-color: #e2e3e5; }
    .bg-selesai { background-color: #d4edda; }
</style>

<table>
<thead>
    <tr>
        <th width="4%">No</th>
        <th width="10%">Tanggal Kirim</th>
        <th width="18%">Petugas Finishing</th>
        <th width="8%">Total Kirim (Pcs)</th>
        <th width="10%">Jenis Bahan</th>
        <th width="6%">Jml Jenis</th>
        <th width="8%">Total Bahan</th>
        <th width="10%">Tanggal Hasil</th>
        <th width="10%">Hasil Finishing (Pcs)</th>
        <th width="16%">Status</th>
    </tr>
</thead>
<tbody>';

// Isi Tabel
$no = 1;
$total_kirim_all = 0;
$total_jenis_all = 0;
$total_bahan_all = 0;
$total_hasil_all = 0;
$total_upah_all = 0;

foreach ($all_data as $data) {
    // Format data untuk keamanan
    $petugas = htmlspecialchars($data['petugas'] ?? '-');
    $produk = htmlspecialchars($data['produk'] ?? '-');
    $jenis_bahan = htmlspecialchars($data['jenis_bahan'] ?? '-');

    // Format tanggal
    $tgl_kirim = !empty($data['tanggal_kirim']) ? dateIndo($data['tanggal_kirim']) : '-';
    $tgl_hasil = !empty($data['tanggal_hasil']) ? dateIndo($data['tanggal_hasil']) : '-';

    // Format jumlah
    $total_kirim = $data['total_kirim'] ? number_format($data['total_kirim']) . ' Pcs' : '0 Pcs';
    $jumlah_jenis = $data['jumlah_jenis_bahan'] ? number_format($data['jumlah_jenis_bahan']) : '0';
    $total_bahan = $data['total_bahan'] ? number_format($data['total_bahan']) . ' Pcs' : '0 Pcs';
    $total_hasil = $data['total_hasil_finishing'] ? number_format($data['total_hasil_finishing']) . ' Pcs' : '-';

    // Format tarif dan upah
    $tarif_finishing = $data['tarif_finishing'] > 0 ? formatRupiah($data['tarif_finishing']) : '-';
    $upah_finishing = $data['upah_finishing'] > 0 ? formatRupiah($data['upah_finishing']) : '-';

    // Tentukan warna background berdasarkan status
    $status_class = '';
    switch ($data['status']) {
        case 'pengiriman':
            $status_class = 'bg-pengiriman';
            break;
        case 'diproses':
            $status_class = 'bg-diproses';
            break;
        case 'selesai':
            $status_class = 'bg-selesai';
            break;
    }

    // Status label
    $status_label = ucfirst($data['status']);

    // Tambah baris
    $html .= '
    <tr class="' . $status_class . '">
        <td class="text-center" width="4%">' . $no++ . '</td>
        <td class="text-left" width="10%">' . $tgl_kirim . '</td>
        <td class="text-left" width="18%">' . $petugas . '</td>
       
        <td class="text-center" width="8%">' . $total_kirim . '</td>
        <td class="text-left" width="10%"><small>' . $jenis_bahan . '</small></td>
        <td class="text-center" width="6%">' . $jumlah_jenis . '</td>
        <td class="text-center" width="8%">' . $total_bahan . '</td>
        <td class="text-left" width="10%">' . $tgl_hasil . '</td>
        <td class="text-center" width="10%">' . $total_hasil . '</td>
       
        <td class="text-center" width="16%">' . $status_label . '</td>
    </tr>';

    // Hitung total
    $total_kirim_all += $data['total_kirim'] ?? 0;
    $total_jenis_all += $data['jumlah_jenis_bahan'] ?? 0;
    $total_bahan_all += $data['total_bahan'] ?? 0;
    $total_hasil_all += $data['total_hasil_finishing'] ?? 0;
    $total_upah_all += $data['upah_finishing'] ?? 0;
}

// Baris Total
$html .= '
<tr class="total-row">
    <td colspan="4" class="text-center"><b>TOTAL KESELURUHAN</b></td>
    <td class="text-center"><b>' . number_format($total_kirim_all) . ' Pcs</b></td>
    <td class="text-center">-</td>
    <td class="text-center"><b>' . number_format($total_jenis_all) . '</b></td>
    <td class="text-center"><b>' . number_format($total_bahan_all) . ' Pcs</b></td>
    <td class="text-center"><b>' . number_format($total_hasil_all) . ' Pcs</b></td>
    <td class="text-center">-</td>
</tr>
</tbody>
</table>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Summary section
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'RINGKASAN FINISHING', 0, 1, 'L');
$pdf->Ln(3);

// Hitung persentase selesai
$total_data = count($all_data);
$jumlah_selesai = 0;
foreach ($all_data as $data) {
    if ($data['status'] == 'selesai') {
        $jumlah_selesai++;
    }
}
$persentase_selesai = $total_data > 0 ? ($jumlah_selesai / $total_data) * 100 : 0;

// Hitung rata-rata tarif finishing
$rata_rata_tarif = count($all_data) > 0 ? ($total_upah_all / max(1, $total_hasil_all)) : 0;

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
        <td width="50%">Total Data Pengiriman:</td>
        <td width="50%" class="summary-value">' . number_format($total_data) . ' Data</td>
    </tr>
    <tr>
        <td>Total Kirim Finishing:</td>
        <td class="summary-value">' . number_format($total_kirim_all) . ' Pcs</td>
    </tr>
    <tr>
        <td>Total Bahan Digunakan:</td>
        <td class="summary-value">' . number_format($total_bahan_all) . ' Pcs (' . number_format($total_jenis_all) . ' Jenis)</td>
    </tr>
    <tr>
        <td>Total Hasil Finishing:</td>
        <td class="summary-value">' . number_format($total_hasil_all) . ' Pcs</td>
    </tr>
    <tr>
        <td>Data Selesai:</td>
        <td class="summary-value">' . number_format($jumlah_selesai) . ' Data (' . number_format($persentase_selesai, 1) . '%)</td>
    </tr>
    <tr>
        <td colspan="2"><hr></td>
    </tr>
    <tr>
        <td>Total Upah Finishing:</td>
        <td class="summary-value">' . formatRupiah($total_upah_all) . '</td>
    </tr>
    <tr>
        <td>Rata-rata Tarif:</td>
        <td class="summary-value">' . formatRupiah($rata_rata_tarif) . '/Pcs</td>
    </tr>
</table>';

$pdf->writeHTML($summary_html, true, false, true, false, '');

// Footer dengan informasi jumlah data
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 5, 'Jumlah data yang ditampilkan: ' . count($all_data) . ' pengiriman finishing', 0, 1, 'L');

// Legenda status
$pdf->SetFont('helvetica', '', 8);
$pdf->Ln(5);
$pdf->Cell(0, 5, 'Status:', 0, 1, 'L');
$pdf->Cell(5, 4, '', 0, 0, 'L');
$pdf->SetFillColor(226, 227, 229);
$pdf->Cell(5, 4, '', 1, 0, 'C', true);
$pdf->Cell(2, 4, '', 0, 0, 'L');
$pdf->Cell(25, 4, 'Pengiriman', 0, 0, 'L');
$pdf->Cell(5, 4, '', 0, 0, 'L');
$pdf->SetFillColor(212, 237, 218);
$pdf->Cell(5, 4, '', 1, 0, 'C', true);
$pdf->Cell(2, 4, '', 0, 0, 'L');
$pdf->Cell(25, 4, 'Selesai', 0, 1, 'L');

// Informasi statistik per status
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'Distribusi Status:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 8);

// Hitung distribusi status
$status_distribution = [
    'pengiriman' => 0,
    'diproses' => 0,
    'selesai' => 0
];

foreach ($all_data as $data) {
    if (isset($status_distribution[$data['status']])) {
        $status_distribution[$data['status']]++;
    }
}

$pdf->Cell(10, 4, '', 0, 0, 'L');
$pdf->Cell(50, 4, 'Pengiriman: ' . $status_distribution['pengiriman'] . ' data', 0, 0, 'L');
$pdf->Cell(50, 4, 'Selesai: ' . $status_distribution['selesai'] . ' data', 0, 1, 'L');

// Close and output PDF document
$pdf->Output('laporan_finishing_' . date('Y-m-d_His') . '.pdf', 'I');
