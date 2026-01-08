<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
    $action = $_POST['action'] ?? '';

    if ($action == 'cek_pembayaran_penjahit') {
        // Ambil data penjahit dari produksi
        $sql = "SELECT hp.id_penjahit, hp.total_hasil_jahit, hp.tanggal_hasil_jahit
                FROM hasil_potong_fix hp
                WHERE hp.id_hasil_potong_fix = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_hasil_potong_fix);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $id_penjahit = $data['id_penjahit'];

            if ($id_penjahit > 0) {
                // Ambil detail hutang
                $sql_hutang = "SELECT 
                                h.total_upah,
                                h.sisa_hutang,
                                h.total_dibayar,
                                GROUP_CONCAT(DATE_FORMAT(p.tanggal_pembayaran, '%d/%m/%Y') SEPARATOR ', ') as tanggal_pembayaran
                              FROM hutang_upah h
                              LEFT JOIN pembayaran_upah p ON h.id_hutang = p.id_hutang
                              WHERE h.id_karyawan = ? AND h.jenis_karyawan = 'penjahit'
                              GROUP BY h.id_hutang";

                $stmt = $conn->prepare($sql_hutang);
                $stmt->bind_param("i", $id_penjahit);
                $stmt->execute();
                $result_hutang = $stmt->get_result();

                if ($result_hutang->num_rows > 0) {
                    $hutang = $result_hutang->fetch_assoc();

                    echo json_encode([
                        'error' => false,
                        'upah_sudah_dibayar' => $hutang['total_dibayar'] > 0,
                        'total_upah' => formatRupiah($hutang['total_upah']),
                        'total_dibayar' => formatRupiah($hutang['total_dibayar']),
                        'sisa_hutang' => formatRupiah($hutang['sisa_hutang']),
                        'tanggal_pembayaran' => $hutang['tanggal_pembayaran'] ?? ''
                    ]);
                } else {
                    echo json_encode([
                        'error' => false,
                        'upah_sudah_dibayar' => false,
                        'total_upah' => '0',
                        'total_dibayar' => '0',
                        'sisa_hutang' => '0',
                        'tanggal_pembayaran' => ''
                    ]);
                }
            } else {
                echo json_encode([
                    'error' => false,
                    'upah_sudah_dibayar' => false,
                    'total_upah' => '0',
                    'total_dibayar' => '0',
                    'sisa_hutang' => '0',
                    'tanggal_pembayaran' => ''
                ]);
            }
        } else {
            echo json_encode([
                'error' => true,
                'message' => 'Data produksi tidak ditemukan'
            ]);
        }
    }
}
