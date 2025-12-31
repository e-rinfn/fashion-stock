<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit();
}

$id_hasil_potong_fix = intval($_GET['id']);

// Ambil data produksi
$produksi_data = query("SELECT 
    hp.*,
    pem.nama_pemotong,
    pem.id_pemotong
FROM hasil_potong_fix hp
JOIN pemotong pem ON hp.id_pemotong = pem.id_pemotong
WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0];

if (!$produksi_data) {
    echo json_encode(['success' => false, 'message' => 'Data produksi tidak ditemukan']);
    exit();
}

$id_pemotong = $produksi_data['id_pemotong'];
$nama_pemotong = $produksi_data['nama_pemotong'];
$seri = $produksi_data['seri'];

// Cek apakah upah sudah dibayar
$sql = "SELECT 
            hu.id_hutang,
            hu.total_upah,
            hu.sisa_hutang,
            hu.total_dibayar
        FROM hutang_upah hu
        WHERE hu.id_karyawan = ? 
        AND hu.jenis_karyawan = 'pemotong'
        HAVING total_dibayar > 0 
        OR sisa_hutang < total_upah";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_pemotong);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $hutang = $result->fetch_assoc();

    // Ambil riwayat pembayaran
    $sql_pembayaran = "SELECT 
                        pu.tanggal_pembayaran,
                        pu.jumlah_pembayaran,
                        pu.keterangan,
                        DATE_FORMAT(pu.tanggal_pembayaran, '%d/%m/%Y') as tanggal
                    FROM pembayaran_upah_2 pu
                    WHERE pu.id_hutang = ?
                    ORDER BY pu.tanggal_pembayaran DESC";

    $stmt2 = $conn->prepare($sql_pembayaran);
    $stmt2->bind_param("i", $hutang['id_hutang']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    $riwayat_pembayaran = [];
    while ($row = $result2->fetch_assoc()) {
        $riwayat_pembayaran[] = $row;
    }

    echo json_encode([
        'success' => true,
        'upah_dibayar' => true,
        'nama_pemotong' => $nama_pemotong,
        'seri' => $seri,
        'total_upah' => $hutang['total_upah'],
        'total_dibayar' => $hutang['total_dibayar'],
        'sisa_hutang' => $hutang['sisa_hutang'],
        'riwayat_pembayaran' => $riwayat_pembayaran
    ]);
} else {
    echo json_encode([
        'success' => true,
        'upah_dibayar' => false,
        'nama_pemotong' => $nama_pemotong,
        'seri' => $seri
    ]);
}
