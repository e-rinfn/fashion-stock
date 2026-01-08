<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id_produksi']) || empty($_GET['id_produksi'])) {
    echo json_encode(['upah_dibayar' => false]);
    exit();
}

$id_hasil_potong_fix = intval($_GET['id_produksi']);

try {
    // Ambil data produksi
    $produksi_data = query("SELECT id_pemotong, tanggal_hasil_potong, total_hasil, seri 
                           FROM hasil_potong_fix 
                           WHERE id_hasil_potong_fix = $id_hasil_potong_fix")[0];

    if (!$produksi_data) {
        echo json_encode(['upah_dibayar' => false]);
        exit();
    }

    $id_pemotong = $produksi_data['id_pemotong'];
    $tanggal_potong = $produksi_data['tanggal_hasil_potong'];
    $total_hasil = $produksi_data['total_hasil'];
    $seri = $produksi_data['seri'];

    // Jika tidak ada pemotong yang terkait
    if (empty($id_pemotong)) {
        echo json_encode(['upah_dibayar' => false]);
        exit();
    }

    // Hitung upah pemotong untuk produksi ini
    $tarif_pemotong = getTarifUpah('pemotongan', $tanggal_potong);
    $upah_produksi_ini = $total_hasil * $tarif_pemotong;

    // Cek apakah ada pembayaran upah yang secara spesifik menyebutkan seri produksi ini
    $check_upah = $conn->prepare("
        SELECT COUNT(pu.id_pembayaran) as total_pembayaran_terkait
        FROM pembayaran_upah_2 pu
        JOIN hutang_upah hu ON pu.id_hutang = hu.id_hutang
        WHERE hu.id_karyawan = ? 
        AND hu.jenis_karyawan = 'pemotong' 
        AND hu.periode = DATE_FORMAT(?, '%Y-%m-01')
        AND pu.keterangan LIKE ?
    ");

    if (!$check_upah) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $search_seri = "%" . $seri . "%";
    $check_upah->bind_param("iss", $id_pemotong, $tanggal_potong, $search_seri);
    $check_upah->execute();
    $result = $check_upah->get_result();
    $pembayaran_terkait = $result->fetch_assoc()['total_pembayaran_terkait'] > 0;
    $check_upah->close();

    echo json_encode([
        'upah_dibayar' => $pembayaran_terkait,
        'seri' => $seri,
        'upah_produksi' => $upah_produksi_ini,
        'pemotong_id' => $id_pemotong
    ]);
} catch (Exception $e) {
    error_log("Error check_upah_pemotong_status: " . $e->getMessage());
    echo json_encode(['upah_dibayar' => false]);
}

// Fungsi getTarifUpah untuk menghitung tarif
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
