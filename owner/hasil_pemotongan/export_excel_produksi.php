<?php
require_once '../../config/functions.php';

// Set header untuk file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"laporan_produksi_" . date('Y-m-d') . ".xls\"");
header("Cache-Control: max-age=0");

// Set filter dari GET
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$id_pemotong = isset($_GET['id_pemotong']) ? (int)$_GET['id_pemotong'] : 0;
$id_penjahit = isset($_GET['id_penjahit']) ? $_GET['id_penjahit'] : 0;
$id_bordir = isset($_GET['id_bordir']) ? $_GET['id_bordir'] : 0;

// Query data dengan filter yang sama seperti di list.php
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

if ($id_produk > 0) {
    $sql .= " AND h.id_produk = $id_produk";
}
if ($id_pemotong > 0) {
    $sql .= " AND h.id_pemotong = $id_pemotong";
}
if ($id_penjahit == '-1') {
    $sql .= " AND (h.id_penjahit IS NULL OR h.id_penjahit = 0)";
} elseif ($id_penjahit > 0) {
    $sql .= " AND h.id_penjahit = $id_penjahit";
}
if ($id_bordir == '-1') {
    $sql .= " AND (h.id_bordir IS NULL OR h.id_bordir = 0)";
} elseif ($id_bordir > 0) {
    $sql .= " AND h.id_bordir = $id_bordir";
}
if ($status != 'all') {
    $sql .= " AND h.status_potong = '$status'";
}
if (!empty($start_date)) {
    $sql .= " AND h.tanggal_hasil_potong >= '$start_date'";
}
if (!empty($end_date)) {
    $sql .= " AND h.tanggal_hasil_potong <= '$end_date'";
}

$sql .= " ORDER BY CAST(h.seri AS UNSIGNED) DESC, h.tanggal_hasil_potong DESC";
$produksi = query($sql);

// Hitung total
$total_hasil = 0;
$total_bordir = 0;
$total_jahit = 0;
$total_sisa = 0;
$total_upah_pemotong = 0;
$total_upah_bordir = 0;
$total_upah_penjahit = 0;

foreach ($produksi as $p) {
    $total_hasil += $p['total_hasil'] ?? 0;
    $total_bordir += $p['total_hasil_bordir'] ?? 0;
    $total_jahit += $p['total_hasil_jahit'] ?? 0;
    $sisa = ($p['total_hasil'] ?? 0) - ($p['total_hasil_jahit'] ?? 0);
    $total_sisa += $sisa;

    // Hitung upah pemotong (jika ada total upah dari hasil_potong_fix)
    $upah_pemotong = $p['total_upah'] ?? 0;
    $total_upah_pemotong += $upah_pemotong;

    // Hitung upah bordir
    $upah_bordir = 0;
    if (!empty($p['total_hasil_bordir']) && !empty($p['tarif_upah_bordir'])) {
        $upah_bordir = $p['total_hasil_bordir'] * $p['tarif_upah_bordir'];
    }
    $total_upah_bordir += $upah_bordir;

    // Hitung upah penjahit
    $upah_penjahit = 0;
    if (!empty($p['total_hasil_jahit']) && !empty($p['tarif_upah'])) {
        $upah_penjahit = $p['total_hasil_jahit'] * $p['tarif_upah'];
    }
    $total_upah_penjahit += $upah_penjahit;
}

// Fungsi untuk konversi status
function getStatusLabel($status)
{
    $labels = [
        'selesai' => 'Selesai',
        'diproses' => 'Potong',
        'bordir' => 'Bordir',
        'penjahitan' => 'Penjahitan'
    ];
    return $labels[$status] ?? $status;
}

// Fungsi untuk format angka
function formatExcelNumber($number)
{
    return number_format($number, 0, '.', '');
}

// Fungsi untuk format rupiah di Excel
function formatExcelRupiah($number)
{
    return number_format($number, 0, '.', ',');
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Laporan Produksi</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-size: 11px;
        }

        .header {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .total {
            background-color: #e6f7ff;
            font-weight: bold;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        /* Style untuk kolom angka */
        .number {
            mso-number-format: "0";
            text-align: right;
        }

        /* Style untuk kolom uang */
        .currency {
            mso-number-format: "#,##0";
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="title">
        LAPORAN DATA PRODUKSI<br>
        Periode: <?= !empty($start_date) ? dateIndo($start_date) : 'Semua' ?>
        s/d <?= !empty($end_date) ? dateIndo($end_date) : 'Semua' ?>
    </div>

    <table>
        <thead>
            <tr class="header">
                <th>No</th>
                <th>Status</th>
                <th>Seri</th>
                <th>Tanggal Potong</th>
                <th>Produk</th>
                <th>Tipe</th>
                <th>Pemotong</th>
                <th>Hasil Potong</th>
                <th>Upah Pemotong</th>
                <th>Bordir</th>
                <th>Tanggal Bordir</th>
                <th>Hasil Bordir</th>
                <th>Upah Bordir</th>
                <th>Penjahit</th>
                <th>Tanggal Jahit</th>
                <th>Hasil Jahit</th>
                <th>Upah Penjahit</th>
                <th>Sisa</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($produksi)): ?>
                <tr>
                    <td colspan="18" style="text-align: center;">Tidak ada data</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; ?>
                <?php foreach ($produksi as $p): ?>
                    <?php
                    $sisa = ($p['total_hasil'] ?? 0) - ($p['total_hasil_jahit'] ?? 0);

                    // Hitung upah
                    $upah_pemotong = $p['total_upah'] ?? 0;

                    $upah_bordir = 0;
                    if (!empty($p['total_hasil_bordir']) && !empty($p['tarif_upah_bordir'])) {
                        $upah_bordir = $p['total_hasil_bordir'] * $p['tarif_upah_bordir'];
                    }

                    $upah_penjahit = 0;
                    if (!empty($p['total_hasil_jahit']) && !empty($p['tarif_upah'])) {
                        $upah_penjahit = $p['total_hasil_jahit'] * $p['tarif_upah'];
                    }
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= getStatusLabel($p['status_potong']) ?></td>
                        <td><?= htmlspecialchars($p['seri']) ?></td>
                        <td><?= dateIndo($p['tanggal_hasil_potong']) ?></td>
                        <td><?= htmlspecialchars($p['nama_produk']) ?></td>
                        <td><?= strtoupper($p['tipe_produk']) ?></td>
                        <td><?= htmlspecialchars($p['nama_pemotong']) ?></td>

                        <!-- Kolom angka tanpa teks "Pcs" -->
                        <td class="number"><?= $p['total_hasil'] ?></td>

                        <!-- Kolom upah pemotong -->
                        <td class="currency"><?= $upah_pemotong > 0 ? formatExcelRupiah($upah_pemotong) : '' ?></td>

                        <td><?= !empty($p['nama_bordir']) ? htmlspecialchars($p['nama_bordir']) : '-' ?></td>
                        <td><?= !empty($p['tanggal_hasil_bordir']) ? dateIndo($p['tanggal_hasil_bordir']) : '-' ?></td>

                        <!-- Kolom angka tanpa teks "Pcs" -->
                        <td class="number"><?= !empty($p['total_hasil_bordir']) ? $p['total_hasil_bordir'] : '' ?></td>

                        <!-- Kolom upah bordir -->
                        <td class="currency"><?= $upah_bordir > 0 ? formatExcelRupiah($upah_bordir) : '' ?></td>

                        <td><?= !empty($p['nama_penjahit']) ? htmlspecialchars($p['nama_penjahit']) : '-' ?></td>
                        <td><?= !empty($p['tanggal_hasil_jahit']) ? dateIndo($p['tanggal_hasil_jahit']) : '-' ?></td>

                        <!-- Kolom angka tanpa teks "Pcs" -->
                        <td class="number"><?= !empty($p['total_hasil_jahit']) ? $p['total_hasil_jahit'] : '' ?></td>

                        <!-- Kolom upah penjahit -->
                        <td class="currency"><?= $upah_penjahit > 0 ? formatExcelRupiah($upah_penjahit) : '' ?></td>

                        <!-- Kolom angka tanpa teks "Pcs" -->
                        <td class="number"><?= $sisa > 0 ? $sisa : '' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="7" style="text-align: right;"><strong>TOTAL:</strong></td>

                <!-- Total hasil potong -->
                <td class="number"><strong><?= $total_hasil ?></strong></td>

                <!-- Total upah pemotong -->
                <td class="currency"><strong><?= formatExcelRupiah($total_upah_pemotong) ?></strong></td>

                <td colspan="2"></td>

                <!-- Total hasil bordir -->
                <td class="number"><strong><?= $total_bordir ?></strong></td>

                <!-- Total upah bordir -->
                <td class="currency"><strong><?= formatExcelRupiah($total_upah_bordir) ?></strong></td>

                <td colspan="2"></td>

                <!-- Total hasil jahit -->
                <td class="number"><strong><?= $total_jahit ?></strong></td>

                <!-- Total upah penjahit -->
                <td class="currency"><strong><?= formatExcelRupiah($total_upah_penjahit) ?></strong></td>

                <!-- Total sisa -->
                <td class="number"><strong><?= $total_sisa ?></strong></td>
            </tr>

            <!-- Baris Grand Total Upah -->
            <tr class="total" style="background-color: #ffecb3;">
                <td colspan="16" style="text-align: right;">
                    <strong>GRAND TOTAL UPAH:</strong>
                </td>
                <td class="currency" colspan="2">
                    <strong><?= formatExcelRupiah($total_upah_pemotong + $total_upah_bordir + $total_upah_penjahit) ?></strong>
                </td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 30px;">
        <table style="border: none; width: 100%;">
            <tr>
                <td style="border: none; padding: 20px 0; width: 60%; text-align: left; vertical-align: top;">
                    <strong>Rincian:</strong><br>
                    1. Total Hasil Potong: <?= $total_hasil ?> Pcs<br>
                    2. Total Hasil Bordir: <?= $total_bordir ?> Pcs<br>
                    3. Total Hasil Jahit: <?= $total_jahit ?> Pcs<br>
                    4. Total Sisa: <?= $total_sisa ?> Pcs<br>
                    5. Total Upah Pemotong: Rp <?= formatRupiah($total_upah_pemotong) ?><br>
                    6. Total Upah Bordir: Rp <?= formatRupiah($total_upah_bordir) ?><br>
                    7. Total Upah Penjahit: Rp <?= formatRupiah($total_upah_penjahit) ?><br>
                    8. <strong>Grand Total Upah: Rp <?= formatRupiah($total_upah_pemotong + $total_upah_bordir + $total_upah_penjahit) ?></strong>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 20px; font-size: 11px; text-align: right;">
        Dicetak pada: <?= date('d/m/Y H:i:s') ?>
    </div>
</body>

</html>