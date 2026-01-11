<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Load TCPDF library
require_once '../../vendor/autoload.php';

// Validasi parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID pengiriman finishing tidak valid");
}

$id_hasil_kirim_finishing = intval($_GET['id']);

// Ambil data utama pengiriman finishing
$sql_main = "SELECT 
    hk.*,
    p.nama_produk,
    p.id_produk as id_produk_utama,
    pet.nama_petugas,
    pet.id_petugas_finishing,
    hk.tanggal_kirim_finishing,
    hk.status_finishing,
    hk.total_kirim,
    hk.tanggal_hasil_finishing,
    hk.nama_penjahit,
    hk.keterangan
FROM hasil_kirim_finishing hk
LEFT JOIN produk p ON hk.id_produk = p.id_produk 
LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
WHERE hk.id_hasil_kirim_finishing = ?";

$stmt = $conn->prepare($sql_main);
$stmt->bind_param("i", $id_hasil_kirim_finishing);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Data pengiriman finishing tidak ditemukan");
}

$main_data = $result->fetch_assoc();

// Ambil data hasil finishing koko
$sql_finishing_data = "SELECT 
    dhfk.*,
    k.nama_koko,
    p.nama_produk as nama_produk_koko,
    dh.jumlah as jumlah_dikirim
FROM detail_hasil_finishing_koko dhfk
JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
JOIN koko k ON dhfk.id_koko = k.id_koko
LEFT JOIN produk p ON k.id_produk = p.id_produk
WHERE dh.id_hasil_kirim_finishing = ?
ORDER BY k.nama_koko";

$stmt_finishing = $conn->prepare($sql_finishing_data);
$stmt_finishing->bind_param("i", $id_hasil_kirim_finishing);
$stmt_finishing->execute();
$finishing_result = $stmt_finishing->get_result();
$finishing_data = [];
$total_selesai_finishing = 0;
$total_rusak_finishing = 0;
$total_upah_finishing = 0;

while ($row = $finishing_result->fetch_assoc()) {
    $finishing_data[] = $row;
    $total_selesai_finishing += $row['jumlah_selesai'];
    $total_rusak_finishing += $row['jumlah_rusak'];
    $total_upah_finishing += $row['total_upah'];
}

// Cek apakah ada data finishing
if (empty($finishing_data)) {
    die("Belum ada hasil finishing untuk data ini");
}

// Ambil data ATK finishing
$sql_atk = "SELECT * FROM atk_finishing_koko 
           WHERE id_hasil_kirim_finishing = ? 
           ORDER BY created_at DESC";
$stmt_atk = $conn->prepare($sql_atk);
$stmt_atk->bind_param("i", $id_hasil_kirim_finishing);
$stmt_atk->execute();
$atk_result = $stmt_atk->get_result();
$atk_data = [];

while ($row = $atk_result->fetch_assoc()) {
    $atk_data[] = $row;
}
$has_atk = (count($atk_data) > 0);

// Create new PDF document (Landscape orientation untuk A4)
$pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Produksi');
$pdf->SetAuthor('Sistem Produksi');
$pdf->SetTitle('Laporan Hasil Finishing Koko');

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

// === LAYOUT DUA KOLOM ===
$left_width = 90; // Lebar kolom kiri
$right_width = 177; // Lebar kolom kanan
$start_y = 35;

// === KOLOM KIRI: INFORMASI DAN RINGKASAN ===
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY(10, $start_y);
$pdf->Cell($left_width, 6, 'INFORMASI PENGIRIMAN', 0, 1);

// Informasi utama
$info_html = '
<style>
    .info-table { width: 100%; font-size: 8pt; }
    .info-table td { padding: 3px; vertical-align: top; }
    .info-label { font-weight: bold; width: 45%; }
    .info-value { width: 55%; }
</style>

<table class="info-table">
    <tr>
        <td class="info-label">Produk Utama:</td>
        <td class="info-value">' . htmlspecialchars($main_data['nama_produk']) . '</td>
    </tr>
    <tr>
        <td class="info-label">Tanggal Kirim:</td>
        <td class="info-value">' . dateIndo($main_data['tanggal_kirim_finishing']) . '</td>
    </tr>
    <tr>
        <td class="info-label">Total Kirim:</td>
        <td class="info-value">' . number_format($main_data['total_kirim']) . ' pcs</td>
    </tr>
    <tr>
        <td class="info-label">Finishing:</td>
        <td class="info-value">' . htmlspecialchars($main_data['nama_petugas']) . '</td>
    </tr>
    <tr>
        <td class="info-label">Nama Penjahit:</td>
        <td class="info-value">';

// Format nama penjahit
if (!empty($main_data['nama_penjahit'])) {
    $penjahit_list = array_map('trim', explode(',', $main_data['nama_penjahit']));
    $info_html .= htmlspecialchars(implode(', ', $penjahit_list));
} else {
    $info_html .= '-';
}

$info_html .= '</td>
    </tr>
    <tr>
        <td class="info-label">Tanggal Selesai:</td>
        <td class="info-value">' . dateIndo($finishing_data[0]['tanggal_finishing']) . '</td>
    </tr>
    <tr>
        <td class="info-label">Keterangan:</td>
        <td class="info-value">' . (!empty($main_data['keterangan']) ? htmlspecialchars($main_data['keterangan']) : '-') . '</td>
    </tr>
</table>';

$pdf->SetXY(10, $pdf->GetY());
$pdf->writeHTMLCell($left_width, 0, '', '', $info_html, 0, 1, 0, true, '', true);
$pdf->Ln(3);

// RINGKASAN HASIL
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell($left_width, 6, 'RINGKASAN HASIL', 0, 1);

$summary_html = '
<style>
    .summary-table { width: 100%; font-size: 8pt; margin: 3px 0; }
    .summary-table td { padding: 4px; border: 1px solid #ddd; }
    .summary-label { font-weight: bold; width: 60%; }
    .summary-value { width: 40%; text-align: right; font-weight: bold; }
</style>

<table class="summary-table">
    <tr>
        <td class="summary-label" style="background-color: #f8f9fa;">Total Selesai:</td>
        <td class="summary-value" style="color: #28a745;">' . number_format($total_selesai_finishing) . ' pcs</td>
    </tr>
    <tr>
        <td class="summary-label" style="background-color: #f8f9fa;">Total Kembali:</td>
        <td class="summary-value" style="color: #dc3545;">' . number_format($total_rusak_finishing) . ' pcs</td>
    </tr>
    <tr>
        <td class="summary-label" style="background-color: #f8f9fa;">Total Upah Finishing:</td>
        <td class="summary-value" style="color: #007bff;">' . formatRupiah($total_upah_finishing) . '</td>
    </tr>
</table>';

$pdf->SetX(10);
$pdf->writeHTMLCell($left_width, 0, '', '', $summary_html, 0, 1, 0, true, '', true);
$pdf->Ln(3);

// INFORMASI STOK
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell($left_width, 6, 'INFORMASI STOK', 0, 1);

$produk_ditambahkan = [];
$koko_rusak_list = [];

foreach ($finishing_data as $finish) {
    // Produk yang ditambahkan dari hasil selesai
    if ($finish['jumlah_selesai'] > 0 && !empty($finish['nama_produk_koko'])) {
        if (!isset($produk_ditambahkan[$finish['nama_produk_koko']])) {
            $produk_ditambahkan[$finish['nama_produk_koko']] = 0;
        }
        $produk_ditambahkan[$finish['nama_produk_koko']] += $finish['jumlah_selesai'];
    }

    // Koko yang dikembalikan (rusak)
    if ($finish['jumlah_rusak'] > 0) {
        $koko_rusak_list[] = $finish['nama_koko'] . ' (' . number_format($finish['jumlah_rusak']) . ' pcs)';
    }
}

$stok_html = '
<style>
    .stok-info { font-size: 8pt; }
    .stok-item { margin-bottom: 5px; }
</style>
<div class="stok-info">';

if (!empty($produk_ditambahkan)) {
    $stok_html .= '<div style="margin-bottom: 5px;"><strong>Hasil selesai:</strong></div><div style="padding-left: 10px;">';
    $counter = 0;
    foreach ($produk_ditambahkan as $nama_produk => $jumlah) {
        $stok_html .= '• ' . htmlspecialchars($nama_produk) . ': <strong>' . number_format($jumlah) . ' pcs</strong><br>';
        $counter++;
        if ($counter >= 2) break; // Batasi 2 item untuk layout compact
    }
    $stok_html .= '</div>';
}

if (!empty($koko_rusak_list)) {
    $stok_html .= '<div style="margin-top: 5px;"><strong>Koko kembali:</strong></div><div style="padding-left: 10px;">';
    $counter = 0;
    foreach ($koko_rusak_list as $koko) {
        $stok_html .= '• ' . htmlspecialchars($koko) . '<br>';
        $counter++;
        if ($counter >= 2) break; // Batasi 2 item
    }
    $stok_html .= '</div>';
}

$stok_html .= '</div>';

$pdf->SetX(10);
$pdf->writeHTMLCell($left_width, 0, '', '', $stok_html, 0, 1, 0, true, '', true);

// Garis vertikal pemisah
$pdf->SetLineWidth(0.1);
$pdf->Line(103, 35, 103, 130);
$pdf->SetY(130);

// === KOLOM KANAN: TABEL DETAIL ===
$right_start_x = 105;
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY($right_start_x, $start_y);
$pdf->Cell($right_width, 6, 'DETAIL HASIL FINISHING KOKO', 0, 1);

$detail_html = '
<style>
    .detail-table { width: 100%; font-size: 7pt; border-collapse: collapse; }
    .detail-table th { background-color: #e9ecef; font-weight: bold; padding: 4px; text-align: center; border: 1px solid #dee2e6; }
    .detail-table td { padding: 3px; border: 1px solid #dee2e6; vertical-align: middle; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .badge { padding: 1px 3px; border-radius: 2px; font-size: 7pt; display: inline-block; }
    .badge-selesai { background-color: #28a745; color: white; }
    .badge-rusak { background-color: #dc3545; color: white; }
    .total-row td { background-color: #f8f9fa; font-weight: bold; }
</style>

<table class="detail-table">
    <thead>
        <tr>
            <th width="20%">Nama Koko</th>
            <th width="12%">Dikirim</th>
            <th width="12%">Selesai</th>
            <th width="12%">Kembali</th>
            <th width="12%">Upah/Unit</th>
            <th width="14%">Total Upah Finishing</th>
            <th width="18%">Produk Hasil</th>
        </tr>
    </thead>
    <tbody>';

$total_dikirim_finishing = 0;
$total_selesai_finishing_detail = 0;
$total_rusak_finishing_detail = 0;
$total_upah_finishing_detail = 0;

foreach ($finishing_data as $finish) {
    $total_dikirim_finishing += $finish['jumlah_dikirim'];
    $total_selesai_finishing_detail += $finish['jumlah_selesai'];
    $total_rusak_finishing_detail += $finish['jumlah_rusak'];
    $total_upah_finishing_detail += $finish['total_upah'];

    $detail_html .= '
    <tr>
        <td width="20%">' . htmlspecialchars($finish['nama_koko']) . '</td>
        <td class="text-center" width="12%">' . number_format($finish['jumlah_dikirim']) . '</td>
        <td class="text-center" width="12%"><span class="badge badge-selesai">' . number_format($finish['jumlah_selesai']) . '</span></td>
        <td class="text-center" width="12%"><span class="badge badge-rusak">' . number_format($finish['jumlah_rusak']) . '</span></td>
        <td class="text-right" width="12%">' . formatRupiah($finish['upah_per_unit']) . '</td>
        <td class="text-right" style="font-weight: bold;" width="14%">' . formatRupiah($finish['total_upah']) . '</td>
        <td width="18%">' . (!empty($finish['nama_produk_koko']) ? htmlspecialchars($finish['nama_produk_koko']) : '-') . '</td>
    </tr>';
}

$detail_html .= '
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td class="text-center"><strong>TOTAL</strong></td>
            <td class="text-center"><strong>' . number_format($total_dikirim_finishing) . '</strong></td>
            <td class="text-center"><strong>' . number_format($total_selesai_finishing_detail) . '</strong></td>
            <td class="text-center"><strong>' . number_format($total_rusak_finishing_detail) . '</strong></td>
            <td class="text-center"><strong>-</strong></td>
            <td class="text-right"><strong>' . formatRupiah($total_upah_finishing_detail) . '</strong></td>
            <td class="text-center"><strong>' . count($produk_ditambahkan) . ' jenis</strong></td>
        </tr>
    </tfoot>
</table>';

$pdf->SetXY($right_start_x, $pdf->GetY());
$pdf->writeHTMLCell($right_width, 0, '', '', $detail_html, 0, 1, 0, true, '', true);
$pdf->Ln(3);

// === ATK FINISHING YANG DIGUNAKAN ===
if ($has_atk) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY($right_start_x, $pdf->GetY() + 3);
    $pdf->Cell($right_width, 6, 'ATK FINISHING YANG DIGUNAKAN', 0, 1);

    $atk_html = '
    <style>
        .atk-table { width: 100%; font-size: 7pt; border-collapse: collapse; }
        .atk-table th { background-color: #e9ecef; font-weight: bold; padding: 4px; text-align: center; border: 1px solid #dee2e6; }
        .atk-table td { padding: 3px; border: 1px solid #dee2e6; }
        .text-center { text-align: center; }
    </style>
    
    <table class="atk-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="60%">Nama ATK</th>
                <th width="15%">Jumlah</th>
                <th width="20%">Satuan</th>
            </tr>
        </thead>
        <tbody>';

    $no = 1;
    foreach ($atk_data as $atk) {
        $atk_html .= '
        <tr>
            <td class="text-center" width="5%">' . $no++ . '</td>
            <td width="60%"> ' . htmlspecialchars($atk['nama_atk']) . '</td>
            <td class="text-center" width="15%">' . number_format($atk['jumlah']) . '</td>
            <td class="text-center" width="20%">' . ucfirst($atk['satuan']) . '</td>
        </tr>';
    }

    $atk_html .= '
        </tbody>
    </table>';

    $pdf->SetXY($right_start_x, $pdf->GetY());
    $pdf->writeHTMLCell($right_width, 0, '', '', $atk_html, 0, 1, 0, true, '', true);
    $pdf->Ln(3);
}

// === INFORMASI UPAH DAN HUTANG ===
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY($right_start_x, $pdf->GetY() + 5);
$pdf->Cell($right_width, 6, 'INFORMASI UPAH', 0, 1);

$upah_html = '

<div class="upah-info">
    <div><strong>Finishing:</strong> ' . htmlspecialchars($main_data['nama_petugas']) . '</div>
    <div><strong>Total Upah Finishing:</strong> <span style="color: #28a745; font-weight: bold;">' . formatRupiah($total_upah_finishing) . '</span></div>
</div>';

$pdf->SetXY($right_start_x, $pdf->GetY());
$pdf->writeHTMLCell($right_width, 0, '', '', $upah_html, 0, 1, 0, true, '', true);


// Close and output PDF document
$filename = 'Hasil_Finishing_Koko_' . $id_hasil_kirim_finishing . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');
