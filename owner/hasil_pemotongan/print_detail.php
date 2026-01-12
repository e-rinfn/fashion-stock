<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Load TCPDF library
require_once '../../vendor/autoload.php';

// Validasi parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID produksi tidak valid");
}

$id_hasil_potong_fix = intval($_GET['id']);

// Query data produksi
$produksi = query("SELECT h.*, p.nama_produk, pem.nama_pemotong, 
                          bor.nama_bordir, pen.nama_penjahit 
                   FROM hasil_potong_fix h
                   JOIN produk p ON h.id_produk = p.id_produk 
                   JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
                   LEFT JOIN bordir bor ON h.id_bordir = bor.id_bordir
                   LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
                   WHERE h.id_hasil_potong_fix = $id_hasil_potong_fix")[0] ?? null;

if (!$produksi) {
    die("Data produksi tidak ditemukan");
}

// Ambil detail bahan yang digunakan
$detail = query("SELECT d.*, b.nama_bahan, b.harga_per_satuan, 
                        COALESCE(d.meter_per_roll, 0) as meter_per_roll,
                        COALESCE(d.total_meter, 0) as total_meter
                 FROM detail_hasil_potong_fix d
                 JOIN bahan_baku b ON d.id_bahan = b.id_bahan
                 WHERE d.id_hasil_potong_fix = $id_hasil_potong_fix");

// Ambil data ATK finishing
$atk_finishing = query("SELECT * FROM atk_finishing 
                       WHERE id_hasil_potong_fix = $id_hasil_potong_fix
                       ORDER BY created_at DESC");

$has_atk_finishing = !empty($atk_finishing);

// Fungsi untuk mendapatkan tarif upah
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

// Hitung upah
$tarif_pemotong = getTarifUpah('pemotongan', $produksi['tanggal_hasil_potong']);
$upah_pemotong = $produksi['total_upah'];

$tarif_bordir = getTarifUpah('bordir', $produksi['tanggal_hasil_bordir'] ?? date('Y-m-d'));
$upah_bordir = !empty($produksi['total_hasil_bordir']) ?
    $produksi['total_hasil_bordir'] * $produksi['tarif_upah_bordir'] : 0;

$tarif_penjahit = getTarifUpah('penjahitan', $produksi['tanggal_hasil_jahit'] ?? date('Y-m-d'));
$upah_penjahit = !empty($produksi['total_hasil_jahit']) ?
    $produksi['total_hasil_jahit'] * $produksi['tarif_upah'] : 0;

$total_upah = $upah_pemotong + $upah_bordir + $upah_penjahit;

// Hitung total bahan dan ATK
$total_roll_used = 0;
$total_meter_used = 0;

foreach ($detail as $d) {
    $total_roll_used += $d['jumlah'];
    $total_meter_used += ($d['total_meter'] ?? 0);
}

$total_atk_items = 0;
foreach ($atk_finishing as $atk) {
    $total_atk_items += $atk['jumlah'];
}

// Tentukan warna badge berdasarkan status
switch ($produksi['status_potong']) {
    case 'selesai':
        $status_color = '#28a745';
        $status_text = 'SELESAI';
        break;
    case 'penjahitan':
        $status_color = '#17a2b8';
        $status_text = 'PENJAHITAN';
        break;
    case 'bordir':
        $status_color = '#1890ff';
        $status_text = 'BORDIR';
        break;
    case 'diproses':
        $status_color = '#ffc107';
        $status_text = 'DIPROSES';
        break;
    default:
        $status_color = '#6c757d';
        $status_text = 'TIDAK DIKETAHUI';
}

// Create new PDF document (Landscape orientation)
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Produksi');
$pdf->SetAuthor('Sistem Produksi');
$pdf->SetTitle('Laporan Detail Produksi');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins lebih kecil untuk compact
$pdf->SetMargins(8, 8, 8);
$pdf->SetAutoPageBreak(TRUE, 8);

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
$pdf->Cell(0, 8, 'LAPORAN DETAIL PRODUKSI', 0, 1, 'C');
$pdf->Ln(4);

// === LAYOUT 2 KOLOM: KIRI INFORMASI, KANAN TIMELINE ===
$col_left_x = 8;
$col_left_width = 165; // Lebar kolom kiri untuk informasi
$col_right_x = 190;    // Posisi awal kolom kanan untuk timeline
$col_right_width = 99; // Lebar kolom kanan untuk timeline
$current_y = 50;

// === KOLOM KIRI: INFORMASI PRODUK DAN DATA ===
// Informasi Produk
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($col_left_x, $current_y);
$pdf->Cell($col_left_width, 5, 'INFORMASI PRODUK', 0, 1);

$info_html = '
<style>
    .info-table { width: 62%; font-size: 7pt; border-collapse: collapse; }
    .info-table td { padding: 3px; border: 0.5px solid #ddd; }
    .info-label { font-weight: bold; width: 35%; background-color: #f5f5f5; }
</style>

<table class="info-table">
    <tr>
        <td class="info-label">Produk</td>
        <td>' . htmlspecialchars($produksi['nama_produk']) . '</td>
    </tr>
    <tr>
        <td class="info-label">Seri</td>
        <td>' . htmlspecialchars($produksi['seri']) . '</td>
    </tr>
    <tr>
        <td class="info-label">Status</td>
        <td style="font-weight: bold; color: ' . $status_color . ';">' . htmlspecialchars(strtoupper($status_text)) . '</td>
    </tr>
</table>';

$pdf->SetXY($col_left_x, $pdf->GetY());
$pdf->writeHTMLCell($col_left_width, 0, '', '', $info_html, 0, 1, 0, true, '', true);
$pdf->Ln(2);

// Ringkasan Upah
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($col_left_x, $pdf->GetY() + 2);
$pdf->Cell($col_left_width, 5, 'RINGKASAN UPAH', 0, 1);

$summary_html = '
<style>
    .summary-table { width: 50%; font-size: 7pt; border-collapse: collapse; }
    .summary-table td { padding: 3px; border: 0.5px solid #ddd; }
    .summary-label { font-weight: bold; width: 55%; background-color: #f5f5f5; }
    .summary-value { text-align: right; }
</style>

<table class="summary-table">
    <tr>
        <td class="summary-label">Pemotongan</td>
        <td class="summary-value">' . formatRupiah($upah_pemotong) . '</td>
    </tr>';

if (!empty($upah_bordir)) {
    $summary_html .= '
    <tr>
        <td class="summary-label">Bordir</td>
        <td class="summary-value">' . formatRupiah($upah_bordir) . '</td>
    </tr>';
}

if (!empty($upah_penjahit)) {
    $summary_html .= '
    <tr>
        <td class="summary-label">Penjahitan</td>
        <td class="summary-value">' . formatRupiah($upah_penjahit) . '</td>
    </tr>';
}

$summary_html .= '
    <tr style="background-color: #e8f5e8; font-weight: bold;">
        <td class="summary-label">TOTAL UPAH</td>
        <td class="summary-value" style="color: #28a745;">' . formatRupiah($total_upah) . '</td>
    </tr>
</table>';

$pdf->SetXY($col_left_x, $pdf->GetY());
$pdf->writeHTMLCell($col_left_width, 0, '', '', $summary_html, 0, 1, 0, true, '', true);
$pdf->Ln(3);

// Tahap Produksi (Tabel)
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($col_left_x, $pdf->GetY() + 2);
$pdf->Cell($col_left_width, 5, 'TAHAP PRODUKSI', 0, 1);

$produksi_html = '
<style>
    .produksi-table { width: 100%; font-size: 7pt; border-collapse: collapse; }
    .produksi-table th { background-color: #e9ecef; font-weight: bold; padding: 3px; border: 0.5px solid #ddd; text-align: center; }
    .produksi-table td { padding: 2px; border: 0.5px solid #ddd; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .phase-row { background-color: #f8f9fa; }
</style>

<table class="produksi-table">
    <thead>
        <tr>
            <th width="20%">Tahap</th>
            <th width="20%">Tanggal</th>
            <th width="30%">Pekerja</th>
            <th width="15%">Hasil</th>
            <th width="15%">Upah</th>
        </tr>
    </thead>
    <tbody>
        <tr class="phase-row">
            <td width="20%" class=""><strong>Pemotongan</strong></td>
            <td width="20%" class="text-center">' . dateIndo($produksi['tanggal_hasil_potong']) . '</td>
            <td width="30%">' . htmlspecialchars($produksi['nama_pemotong']) . '</td>
            <td width="15%" class="text-center">' . $produksi['total_hasil'] . ' pcs</td>
            <td width="15%" class="text-right">' . formatRupiah($upah_pemotong) . '</td>
        </tr>';

if (!empty($produksi['nama_bordir']) || !empty($produksi['total_hasil_bordir'])) {
    $produksi_html .= '
        <tr class="phase-row">
            <td><strong>Bordir</strong></td>
            <td class="text-center">' . (!empty($produksi['tanggal_hasil_bordir']) ? dateIndo($produksi['tanggal_hasil_bordir']) : '-') . '</td>
            <td>' . (!empty($produksi['nama_bordir']) ? htmlspecialchars($produksi['nama_bordir']) : '-') . '</td>
            <td class="text-center">' . (!empty($produksi['total_hasil_bordir']) ? $produksi['total_hasil_bordir'] . ' pcs' : '-') . '</td>
            <td class="text-right">' . (!empty($upah_bordir) ? formatRupiah($upah_bordir) : '-') . '</td>
        </tr>';
}

if (!empty($produksi['nama_penjahit']) || !empty($produksi['total_hasil_jahit'])) {
    $produksi_html .= '
        <tr class="phase-row">
            <td><strong>Penjahitan</strong></td>
            <td class="text-center">' . (!empty($produksi['tanggal_hasil_jahit']) ? dateIndo($produksi['tanggal_hasil_jahit']) : '-') . '</td>
            <td>' . (!empty($produksi['nama_penjahit']) ? htmlspecialchars($produksi['nama_penjahit']) : '-') . '</td>
            <td class="text-center">' . (!empty($produksi['total_hasil_jahit']) ? $produksi['total_hasil_jahit'] . ' pcs' : '-') . '</td>
            <td class="text-right">' . (!empty($upah_penjahit) ? formatRupiah($upah_penjahit) : '-') . '</td>
        </tr>';
}

$total_hasil = $produksi['status_potong'] == 'selesai' ?
    ($produksi['total_hasil_jahit'] ?? $produksi['total_hasil']) : ($produksi['total_hasil_bordir'] ?? $produksi['total_hasil']);

$produksi_html .= '
        <tr style="background-color: #d4edda; font-weight: bold;">
            <td colspan="3" class="text-center">TOTAL</td>
            <td class="text-center">' . $total_hasil . ' pcs</td>
            <td class="text-right">' . formatRupiah($total_upah) . '</td>
        </tr>
    </tbody>
</table>';

$pdf->SetXY($col_left_x, $pdf->GetY());
$pdf->writeHTMLCell($col_left_width, 0, '', '', $produksi_html, 0, 1, 0, true, '', true);
$pdf->Ln(3);

// Bahan Baku
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($col_left_x, $pdf->GetY() + 2);
$pdf->Cell($col_left_width, 5, 'BAHAN BAKU', 0, 1);

if (!empty($detail)) {
    $bahan_html = '
    <style>
        .bahan-table { width: 100%; font-size: 6.5pt; border-collapse: collapse; }
        .bahan-table th { background-color: #e9ecef; font-weight: bold; padding: 2px; border: 0.5px solid #ddd; text-align: center; }
        .bahan-table td { padding: 2px; border: 0.5px solid #ddd; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row { background-color: #f8f9fa; font-weight: bold; }
    </style>
    
    <table class="bahan-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="50%">Bahan</th>
                <th width="15%" class="text-center">Roll</th>
                <th width="15%" class="text-center">Meter/Roll</th>
                <th width="15%" class="text-right">Total M</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($detail as $i => $d) {
        $meter_per_roll = isset($d['meter_per_roll']) ? $d['meter_per_roll'] : 0;
        $total_meter = isset($d['total_meter']) ? $d['total_meter'] : ($d['jumlah'] * $meter_per_roll);

        $bahan_html .= '
        <tr>
            <td width="5%" class="text-center">' . ($i + 1) . '</td>
            <td width="50%" >' . htmlspecialchars($d['nama_bahan']) . '</td>
            <td width="15%" class="text-center">' . $d['jumlah'] . '</td>
            <td width="15%" class="text-center">' . ($meter_per_roll > 0 ? number_format($meter_per_roll, 0) : '-') . '</td>
            <td width="15%" class="text-right">' . ($total_meter > 0 ? number_format($total_meter, 0) : '-') . '</td>
        </tr>';
    }

    $bahan_html .= '
        </tbody>
        <tfoot class="total-row">
            <tr style="background-color: #d4edda;">
                <td colspan="2" class="text-center"><strong>TOTAL</strong></td>
                <td width="15%" class="text-center"><strong>' . $total_roll_used . '</strong></td>
                <td width="15%" class="text-center"><strong>-</strong></td>
                <td width="15%" class="text-right"><strong>' . number_format($total_meter_used, 0) . ' m</strong></td>
            </tr>
        </tfoot>
    </table>';

    $pdf->SetXY($col_left_x, $pdf->GetY());
    $pdf->writeHTMLCell($col_left_width, 0, '', '', $bahan_html, 0, 1, 0, true, '', true);
}

// ATK Finishing (jika ada)
if ($has_atk_finishing && in_array($produksi['status_potong'], ['penjahitan', 'selesai'])) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetXY($col_left_x, $pdf->GetY() + 2);
    $pdf->Cell($col_left_width, 5, 'ATK FINISHING', 0, 1);

    $atk_html = '
    <style>
        .atk-table { width: 100%; font-size: 6.5pt; border-collapse: collapse; }
        .atk-table th { background-color: #e9ecef; font-weight: bold; padding: 2px; border: 0.5px solid #ddd; text-align: center; }
        .atk-table td { padding: 2px; border: 0.5px solid #ddd; }
        .text-center { text-align: center; }
    </style>
    
    <table class="atk-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="65%">ATK</th>
                <th width="15%" class="text-center">Jml</th>
                <th width="15%" class="text-center">Satuan</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($atk_finishing as $i => $atk) {
        $atk_html .= '
        <tr>
            <td width="5%" class="text-center">' . ($i + 1) . '</td>
            <td width="65%">' . htmlspecialchars($atk['nama_atk']) . '</td>
            <td width="15%" class="text-center">' . $atk['jumlah'] . '</td>
            <td width="15%" class="text-center">' . ucfirst($atk['satuan']) . '</td>
        </tr>';
    }

    $atk_html .= '
        </tbody>
    </table>';

    $pdf->SetXY($col_left_x, $pdf->GetY());
    $pdf->writeHTMLCell($col_left_width, 0, '', '', $atk_html, 0, 1, 0, true, '', true);
}

// === KOLOM KANAN: TIMELINE HORIZONTAL ===
// Timeline Header
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetXY($col_right_x, $current_y);
$pdf->Cell($col_right_width, 5, 'TIMELINE PRODUKSI', 0, 1);

// Timeline dalam bentuk tabel horizontal dengan alignment center
$timeline_html = '
<style>
    .timeline-table { width: 100%; font-size: 7pt; border-collapse: collapse; margin-top: 3px; }
    .timeline-table th { background-color: #e9ecef; font-weight: bold; padding: 4px; border: 0.5px solid #ddd; text-align: center; vertical-align: middle; }
    .timeline-table td { padding: 3px; border: 0.5px solid #ddd; text-align: center; vertical-align: middle; }
    .step-num { 
        width: 20px; 
        height: 20px; 
        line-height: 20px;
        text-align: center; 
        font-weight: bold; 
        color: white; 
        border-radius: 50%; 
        display: inline-block;
        margin: 0 auto 2px auto;
    }
    .step-1 { background-color: #28a745; }
    .step-2 { background-color: #1890ff; }
    .step-3 { background-color: #ffc107; }
    .step-4 { background-color: #6c757d; }
    .step-title { 
        font-weight: bold; 
        margin-bottom: 2px;
        font-size: 7.5pt;
    }
    .step-detail { 
        font-size: 6.5pt; 
        color: #555; 
        line-height: 1.2;
        margin: 0;
    }
    .step-status { 
        font-size: 6pt; 
        padding: 2px 4px; 
        border-radius: 3px; 
        display: inline-block; 
        font-weight: bold;
        margin-top: 2px;
    }
    .center-content { text-align: center; }
</style>

<table class="timeline-table">
    <thead>
        <tr>
            <th width="25%">Tahap</th>
            <th width="25%">Tanggal</th>
            <th width="25%">Keterangan</th>
            <th width="25%">Status</th>
        </tr>
    </thead>
    <tbody>
        <!-- Step 1: Pemotongan -->
        <tr>
            <td class="center-content">
                <div class="step-num step-1">1</div>
                <div class="step-title">PEMOTONGAN</div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    ' . dateIndo($produksi['tanggal_hasil_potong']) . '
                </div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    <strong>' . htmlspecialchars($produksi['nama_pemotong']) . '</strong><br>
                    ' . number_format($produksi['total_hasil']) . ' pcs
                </div>
            </td>
            <td class="center-content">
                <span class="step-status" style="background-color: #28a745; color: white;">SELESAI</span>
            </td>
        </tr>';

// Step 2: Bordir
$step2_color = '#6c757d';
$step2_status = 'MENUNGGU';
$step2_text = '-';

if (!empty($produksi['nama_bordir']) || !empty($produksi['total_hasil_bordir'])) {
    if ($produksi['status_potong'] == 'bordir') {
        $step2_color = '#1890ff';
        $step2_status = 'PROSES';
        $step2_text = htmlspecialchars($produksi['nama_bordir']);
    } elseif (in_array($produksi['status_potong'], ['penjahitan', 'selesai'])) {
        $step2_color = '#28a745';
        $step2_status = 'SELESAI';
        $step2_text = htmlspecialchars($produksi['nama_bordir']) . '<br>' . number_format($produksi['total_hasil_bordir'] ?? 0) . ' pcs';
    } else {
        $step2_text = htmlspecialchars($produksi['nama_bordir'] ?? '-');
    }
}

$timeline_html .= '
        <!-- Step 2: Bordir -->
        <tr>
            <td class="center-content">
                <div class="step-num step-2">2</div>
                <div class="step-title">BORDIR</div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    ' . (!empty($produksi['tanggal_hasil_bordir']) ? dateIndo($produksi['tanggal_hasil_bordir']) : '-') . '
                </div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    ' . $step2_text . '
                </div>
            </td>
            <td class="center-content">
                <span class="step-status" style="background-color: ' . $step2_color . '; color: white;">' . $step2_status . '</span>
            </td>
        </tr>';

// Step 3: Penjahitan
$step3_color = '#6c757d';
$step3_status = 'MENUNGGU';
$step3_text = '-';

if (!empty($produksi['nama_penjahit']) || !empty($produksi['total_hasil_jahit'])) {
    if ($produksi['status_potong'] == 'penjahitan') {
        $step3_color = '#ffc107';
        $step3_status = 'PROSES';
        $step3_text = htmlspecialchars($produksi['nama_penjahit']);
    } elseif ($produksi['status_potong'] == 'selesai') {
        $step3_color = '#28a745';
        $step3_status = 'SELESAI';
        $step3_text = htmlspecialchars($produksi['nama_penjahit']) . '<br>' . number_format($produksi['total_hasil_jahit'] ?? 0) . ' pcs';
    } else {
        $step3_text = htmlspecialchars($produksi['nama_penjahit'] ?? '-');
    }
}

$timeline_html .= '
        <!-- Step 3: Penjahitan -->
        <tr>
            <td class="center-content">
                <div class="step-num step-3">3</div>
                <div class="step-title">PENJAHITAN</div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    ' . (!empty($produksi['tanggal_hasil_jahit']) ? dateIndo($produksi['tanggal_hasil_jahit']) : '-') . '
                </div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    ' . $step3_text . '
                </div>
            </td>
            <td class="center-content">
                <span class="step-status" style="background-color: ' . $step3_color . '; color: white;">' . $step3_status . '</span>
            </td>
        </tr>';

$timeline_html .= '
        <!-- Step 4: Selesai -->
        <tr>
            <td class="center-content">
                <div class="step-num step-4">4</div>
                <div class="step-title">SELESAI</div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    ' . ($produksi['status_potong'] == 'selesai' && !empty($produksi['tanggal_hasil_jahit']) ? dateIndo($produksi['tanggal_hasil_jahit']) : '-') . '
                </div>
            </td>
            <td class="center-content">
                <div class="step-detail">
                    ' . ($produksi['status_potong'] == 'selesai' ? number_format($total_hasil) . ' pcs' : '-') . '
                </div>
            </td>
            <td class="center-content">
                <span class="step-status" style="background-color: ' . $status_color . '; color: white;">' . $status_text . '</span>
            </td>
        </tr>
    </tbody>
</table>';


$pdf->SetXY($col_right_x, $pdf->GetY());
$pdf->writeHTMLCell($col_right_width, 0, '', '', $timeline_html, 0, 1, 0, true, '', true);


// Close and output PDF document
$filename = 'Produksi_' . htmlspecialchars($produksi['seri']) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');
