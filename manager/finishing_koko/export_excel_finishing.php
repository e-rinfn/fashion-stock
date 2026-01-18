<?php
require_once '../../config/functions.php';

// Set header untuk file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"laporan_finishing_koko_" . date('Y-m-d') . ".xls\"");
header("Cache-Control: max-age=0");

// Set filter dari GET
$id_petugas_finishing = isset($_GET['id_petugas_finishing']) ? (int)$_GET['id_petugas_finishing'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Query data dengan filter yang sama seperti di finishing.php
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

$sql .= " GROUP BY hk.id_hasil_kirim_finishing, hk.tanggal_kirim_finishing, hk.id_produk, hk.total_kirim, hk.status_finishing, p.nama_produk, pet.nama_petugas";
$sql .= " ORDER BY hk.tanggal_kirim_finishing DESC";

$data_finishing = query($sql);

// Hitung total untuk footer
$total_kirim = 0;
$total_jenis_bahan = 0;
$total_hasil_finishing = 0;
$total_upah = 0;

// Fungsi untuk mendapatkan tarif upah finishing
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

    return 0;
}

// Fungsi untuk format status
function getStatusLabel($status)
{
    $labels = [
        'pengiriman' => 'Pengiriman',
        'diproses' => 'Diproses',
        'selesai' => 'Selesai'
    ];
    return $labels[$status] ?? $status;
}

// Fungsi untuk format rupiah Excel
function formatRupiahExcel($number)
{
    return number_format($number, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Laporan Finishing Koko</title>
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
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
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

        /* Style untuk status */
        .status-selesai {
            color: #28a745;
        }

        .status-diproses {
            color: #ffc107;
        }

        .status-pengiriman {
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="title">
        LAPORAN FINISHING KOKO<br>
        Periode: <?= !empty($start_date) ? dateIndo($start_date) : 'Semua' ?>
        s/d <?= !empty($end_date) ? dateIndo(substr($end_date, 0, 10)) : 'Semua' ?>
    </div>

    <table>
        <thead>
            <tr class="header">
                <th>No</th>
                <th>Status</th>
                <th>Tanggal Kirim</th>
                <th>Petugas Finishing</th>
                <th>Total Kirim (Pcs)</th>
                <th>Jenis Bahan</th>
                <th>Jumlah Jenis</th>
                <th>Tanggal Selesai</th>
                <th>Hasil Finishing (Pcs)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($data_finishing)): ?>
                <tr>
                    <td colspan="12" style="text-align: center;">Tidak ada data finishing</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; ?>
                <?php foreach ($data_finishing as $data): ?>
                    <?php
                    // Ambil tarif upah finishing seperti pada hasil_finishing_koko.php
                    $tanggal_referensi = !empty($data['tanggal_hasil_finishing']) ? $data['tanggal_hasil_finishing'] : $data['tanggal_kirim_finishing'];
                    $tarif_finishing = getTarifUpah('finishing', $tanggal_referensi);
                    $total_upah_item = $data['total_hasil_finishing'] * $tarif_finishing;

                    // Hitung total untuk footer
                    $total_kirim += $data['total_kirim'] ?? 0;
                    $total_jenis_bahan += $data['jumlah_jenis_bahan'] ?? 0;
                    $total_hasil_finishing += $data['total_hasil_finishing'] ?? 0;
                    $total_upah += $total_upah_item;

                    // Tentukan class untuk status
                    $status_class = "status-" . $data['status_finishing'];
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="<?= $status_class ?>">
                            <?= getStatusLabel($data['status_finishing']) ?>
                        </td>
                        <td><?= dateIndo($data['tanggal_kirim_finishing']) ?></td>
                        <td><?= htmlspecialchars($data['nama_petugas']) ?></td>
                        <td class="number"><?= $data['total_kirim'] ?></td>
                        <td style="text-align: left;">
                            <?php if (!empty($data['jenis_bahan'])): ?>
                                <?= htmlspecialchars($data['jenis_bahan']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="number"><?= $data['jumlah_jenis_bahan'] ?? 0 ?></td>
                        <td>
                            <?php if (!empty($data['tanggal_hasil_finishing'])): ?>
                                <?= dateIndo($data['tanggal_hasil_finishing']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="number"><?= $data['total_hasil_finishing'] > 0 ? $data['total_hasil_finishing'] : '' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <?php if (!empty($data_finishing)): ?>
                <!-- Total Baris -->
                <tr class="total">
                    <td colspan="4" style="text-align: right;"><strong>TOTAL:</strong></td>
                    <td class="number"><strong><?= $total_kirim ?></strong></td>
                    <td></td>
                    <td class="number"><strong><?= $total_jenis_bahan ?></strong></td>
                    <td></td>
                    <td class="number"><strong><?= $total_hasil_finishing ?></strong></td>
                </tr>

                <!-- Rincian -->
                <tr>
                    <td colspan="12" style="text-align: left; border: none; padding-top: 20px;">
                        <strong>RINCIAN:</strong><br>
                        1. Total Kirim: <?= $total_kirim ?> Pcs<br>
                        2. Total Jenis Bahan: <?= $total_jenis_bahan ?> Jenis<br>
                        3. Total Hasil Finishing: <?= $total_hasil_finishing ?> Pcs<br>
                    </td>
                </tr>
            <?php endif; ?>
        </tfoot>
    </table>

    <div style="margin-top: 20px; font-size: 10px; text-align: right;">
        Dicetak pada: <?= date('d/m/Y H:i:s') ?> |
        Total Data: <?= count($data_finishing) ?> |
        Status Filter: <?= getStatusLabel($status) ?>
    </div>
</body>

</html>