<?php

// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Load TCPDF library
require_once '../vendor/autoload.php';

// Get data type parameter
$type = isset($_GET['type']) ? $_GET['type'] : 'bahan';

// Query data based on type
switch ($type) {
    case 'bahan':
        $data = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");
        $title = "DATA BAHAN BAKU";
        break;
    case 'produk':
        $data = query("SELECT * FROM produk ORDER BY nama_produk");
        $title = "DATA PRODUK";
        break;
    case 'pemotong':
        $data = query("SELECT * FROM pemotong ORDER BY nama_pemotong");
        $title = "DATA PEMOTONG";
        break;
    case 'bordir':
        $data = query("SELECT * FROM bordir ORDER BY nama_bordir");
        $title = "DATA BORDIR";
        break;
    case 'penjahit':
        $data = query("SELECT * FROM penjahit ORDER BY nama_penjahit");
        $title = "DATA PENJAHIT";
        break;
    case 'finishing':
        $data = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");
        $title = "DATA PETUGAS FINISHING";
        break;
    case 'reseller':
        $data = query("SELECT * FROM reseller ORDER BY nama_reseller");
        $title = "DATA RESELLER";
        break;
    case 'supplier':
        $data = query("SELECT * FROM supplier ORDER BY nama_supplier");
        $title = "DATA SUPPLIER";
        break;
    case 'tarif':
        $data = query("SELECT * FROM tarif_upah ORDER BY jenis_tarif ASC, berlaku_sejak DESC");
        $title = "DATA TARIF UPAH";
        break;
    default:
        $data = [];
        $title = "DATA MASTER";
}

// Inisialisasi TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
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

$pdf->SetFont('times', 'B', 14);
$pdf->Cell(0, 6, 'IPENK LEGEND', 0, 1, 'C');

$pdf->SetFont('times', '', 9);
$pdf->Cell(0, 5, 'Jl. Raya Cigereung No. 45, Tasikmalaya - Jawa Barat', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890 | Email: admin@ipenklegend.com', 0, 1, 'C');

// Tanggal cetak
$pdf->SetFont('times', '', 9);
$pdf->Cell(0, 5, 'Tanggal Cetak: ' . dateIndo(date('Y-m-d')) . ' | ' . date('H:i') . ' WIB', 0, 1, 'C');

// Spasi ke bawah
$pdf->Ln(5);

// Garis pemisah
$y = $pdf->GetY();
$pdf->Line(0, $y, 300, $y);
$pdf->Ln(2);

// Judul
$pdf->SetFont('times', 'B', 14);
$pdf->Cell(0, 8, $title, 0, 1, 'C');
$pdf->Ln(5);

// Generate table based on type
$html = '<style>
    table { 
        border-collapse: collapse; 
        width: 100%; 
        font-size: 9pt;
    }
    th, td { 
        border: 0.5px solid #000; 
        padding: 4px;
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
</style>';

switch ($type) {
    case 'bahan':
        $html .= '<table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Nama Bahan</th>
                <th width="15%">Stok (Roll)</th>
                <th width="15%">Total (Meter)</th>
                <th width="15%">Harga/Meter</th>
                <th width="15%">Total Nilai</th>
            </tr>
        </thead>
        <tbody>';
        
        $no = 1;
        $total_stok = 0;
        $total_meter = 0;
        $total_nilai = 0;
        
        foreach ($data as $d) {
            $nilai = ($d['jumlah_meter'] ?? 0) * ($d['harga_per_satuan'] ?? 0);
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . htmlspecialchars($d['nama_bahan']) . '</td>
                <td class="text-center">' . number_format($d['jumlah_stok'] ?? 0) . '</td>
                <td class="text-right">' . number_format($d['jumlah_meter'] ?? 0) . '</td>
                <td class="text-right">' . formatRupiah($d['harga_per_satuan'] ?? 0) . '</td>
                <td class="text-right">' . formatRupiah($nilai) . '</td>
            </tr>';
            
            $total_stok += $d['jumlah_stok'] ?? 0;
            $total_meter += $d['jumlah_meter'] ?? 0;
            $total_nilai += $nilai;
        }
        
        $html .= '<tr style="background-color: #e8f5e8; font-weight: bold;">
            <td colspan="2" class="text-center">TOTAL</td>
            <td class="text-center">' . number_format($total_stok) . '</td>
            <td class="text-right">' . number_format($total_meter) . '</td>
            <td class="text-right">-</td>
            <td class="text-right">' . formatRupiah($total_nilai) . '</td>
        </tr>';
        break;
        
    case 'produk':
        $html .= '<table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Nama Produk</th>
                <th width="15%">Tipe</th>
                <th width="15%">Stok</th>
                <th width="15%">Harga/Pcs</th>
                <th width="15%">Total Nilai</th>
            </tr>
        </thead>
        <tbody>';
        
        $no = 1;
        $total_stok = 0;
        $total_nilai = 0;
        
        foreach ($data as $d) {
            $nilai = ($d['stok'] ?? 0) * ($d['harga_jual'] ?? 0);
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . htmlspecialchars($d['nama_produk']) . '</td>
                <td class="text-center">' . ucfirst($d['tipe_produk'] ?? '-') . '</td>
                <td class="text-center">' . number_format($d['stok'] ?? 0) . '</td>
                <td class="text-right">' . formatRupiah($d['harga_jual'] ?? 0) . '</td>
                <td class="text-right">' . formatRupiah($nilai) . '</td>
            </tr>';
            
            $total_stok += $d['stok'] ?? 0;
            $total_nilai += $nilai;
        }
        
        $html .= '<tr style="background-color: #e8f5e8; font-weight: bold;">
            <td colspan="3" class="text-center">TOTAL</td>
            <td class="text-center">' . number_format($total_stok) . '</td>
            <td class="text-right">-</td>
            <td class="text-right">' . formatRupiah($total_nilai) . '</td>
        </tr>';
        break;
        
    case 'pemotong':
    case 'bordir':
    case 'penjahit':
    case 'finishing':
        $html .= '<table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="35%">Nama</th>
                <th width="25%">Kontak</th>
                <th width="35%">Alamat</th>
            </tr>
        </thead>
        <tbody>';
        
        $no = 1;
        foreach ($data as $d) {
            $nama = '';
            if ($type == 'pemotong') $nama = $d['nama_pemotong'];
            elseif ($type == 'bordir') $nama = $d['nama_bordir'];
            elseif ($type == 'penjahit') $nama = $d['nama_penjahit'];
            elseif ($type == 'finishing') $nama = $d['nama_petugas'];
            
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . htmlspecialchars($nama) . '</td>
                <td>' . htmlspecialchars($d['kontak'] ?? '-') . '</td>
                <td>' . htmlspecialchars($d['alamat'] ?? '-') . '</td>
            </tr>';
        }
        break;
        
    case 'reseller':
        $html .= '<table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="30%">Nama Reseller</th>
                <th width="20%">Kontak</th>
                <th width="25%">Alamat</th>
                <th width="20%">Tanggal Bergabung</th>
            </tr>
        </thead>
        <tbody>';
        
        $no = 1;
        foreach ($data as $d) {
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . htmlspecialchars($d['nama_reseller']) . '</td>
                <td>' . htmlspecialchars($d['kontak'] ?? '-') . '</td>
                <td>' . htmlspecialchars($d['alamat'] ?? '-') . '</td>
                <td class="text-center">' . (isset($d['tanggal_bergabung']) ? dateIndo($d['tanggal_bergabung']) : '-') . '</td>
            </tr>';
        }
        break;
        
    case 'supplier':
        $html .= '<table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="30%">Nama Supplier</th>
                <th width="25%">Kontak</th>
                <th width="40%">Alamat</th>
            </tr>
        </thead>
        <tbody>';
        
        $no = 1;
        foreach ($data as $d) {
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . htmlspecialchars($d['nama_supplier']) . '</td>
                <td>' . htmlspecialchars($d['kontak'] ?? '-') . '</td>
                <td>' . htmlspecialchars($d['alamat'] ?? '-') . '</td>
            </tr>';
        }
        break;
        
    case 'tarif':
        $html .= '<table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Jenis Tarif</th>
                <th width="20%">Tarif/Unit</th>
                <th width="20%">Berlaku Sejak</th>
                <th width="30%">Keterangan</th>
            </tr>
        </thead>
        <tbody>';
        
        $no = 1;
        foreach ($data as $d) {
            $jenis_badge = match($d['jenis_tarif']) {
                'pemotongan' => 'Pemotongan',
                'bordir' => 'Bordir',
                'penjahitan' => 'Penjahitan',
                'finishing' => 'Finishing',
                default => ucfirst($d['jenis_tarif'])
            };
            
            $html .= '<tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . $jenis_badge . '</td>
                <td class="text-right">' . formatRupiah($d['tarif_per_unit'] ?? 0) . '</td>
                <td class="text-center">' . (isset($d['berlaku_sejak']) ? dateIndo($d['berlaku_sejak']) : '-') . '</td>
                <td>' . htmlspecialchars($d['keterangan'] ?? '-') . '</td>
            </tr>';
        }
        break;
}

$html .= '</tbody></table>';

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Footer
$pdf->Ln(10);
$pdf->SetFont('times', 'I', 8);
$pdf->Cell(0, 5, 'Jumlah data: ' . count($data) . ' record', 0, 1, 'L');

// Close and output PDF document
$filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d_His') . '.pdf';
$pdf->Output($filename, 'I');
