<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// ✅ FUNGSI BARU: untuk mengurangi hutang upah petugas finishing
function kurangiHutangUpahFinishing($id_petugas_finishing, $jumlah_kurang)
{
    global $conn;

    try {
        // 1. Cek apakah ada hutang
        $sql_check = "SELECT id_hutang, total_upah, sisa_hutang, total_dibayar 
                     FROM hutang_upah 
                     WHERE id_karyawan = ? AND jenis_karyawan = 'finishing'
                     LIMIT 1";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("i", $id_petugas_finishing);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Jika tidak ada hutang, tidak perlu melakukan apa-apa
            return true;
        }

        $hutang = $result->fetch_assoc();

        // 2. Validasi: tidak boleh mengurangi lebih dari sisa hutang
        if ($jumlah_kurang > $hutang['sisa_hutang']) {
            throw new Exception("Tidak dapat mengurangi hutang karena jumlah yang akan dikurangi (" .
                formatRupiah($jumlah_kurang) . ") lebih besar dari sisa hutang (" .
                formatRupiah($hutang['sisa_hutang']) . "). Total yang sudah dibayar: " .
                formatRupiah($hutang['total_dibayar']));
        }

        // 3. Hitung nilai baru
        $total_upah_baru = $hutang['total_upah'] - $jumlah_kurang;
        $sisa_hutang_baru = $hutang['sisa_hutang'] - $jumlah_kurang;

        // Pastikan tidak minus
        $total_upah_baru = max(0, $total_upah_baru);
        $sisa_hutang_baru = max(0, $sisa_hutang_baru);

        // 4. Update atau hapus
        if ($total_upah_baru <= 0) {
            // Hapus record hutang jika total upah menjadi 0
            $sql_delete = "DELETE FROM hutang_upah WHERE id_hutang = ?";
            $stmt = $conn->prepare($sql_delete);
            $stmt->bind_param("i", $hutang['id_hutang']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus record hutang: " . $conn->error);
            }
            return true;
        } else {
            // Update hutang yang sudah ada
            $sql_update = "UPDATE hutang_upah 
                          SET total_upah = ?, 
                              sisa_hutang = ?,
                              updated_at = NOW()
                          WHERE id_hutang = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal update hutang: " . $conn->error);
            }
            return true;
        }
    } catch (Exception $e) {
        throw new Exception("Gagal mengurangi hutang upah petugas finishing: " . $e->getMessage());
    }
}

// ✅ FUNGSI BARU: untuk mengembalikan stok bahan baku (koko)
function kembalikanStokKoko($id_hasil_kirim_finishing)
{
    global $conn;

    try {
        // 1. Ambil semua detail bahan (koko) yang digunakan dalam finishing ini
        $sql_detail = "SELECT dh.*, k.nama_koko
                      FROM detail_hasil_kirim_finishing dh
                      JOIN koko k ON dh.id_koko = k.id_koko
                      WHERE dh.id_hasil_kirim_finishing = ?";

        $stmt = $conn->prepare($sql_detail);
        $stmt->bind_param("i", $id_hasil_kirim_finishing);
        $stmt->execute();
        $result = $stmt->get_result();

        $total_koko_dikembalikan = 0;
        $detail_koko_dikembalikan = [];

        while ($detail = $result->fetch_assoc()) {
            $id_koko = $detail['id_koko'];
            $jumlah_digunakan = $detail['jumlah'] ?? 0;
            $nama_koko = $detail['nama_koko'];

            if ($jumlah_digunakan > 0 && $id_koko > 0) {
                // 2. Update stok koko - TAMBAHKAN kembali jumlah yang digunakan
                $sql_update_stok = "UPDATE koko 
                                   SET stok = stok + ?,
                                       updated_at = NOW()
                                   WHERE id_koko = ?";

                $stmt_update = $conn->prepare($sql_update_stok);
                $stmt_update->bind_param("ii", $jumlah_digunakan, $id_koko);

                if (!$stmt_update->execute()) {
                    throw new Exception("Gagal mengembalikan stok koko ID $id_koko: " . $conn->error);
                }

                $total_koko_dikembalikan += $jumlah_digunakan;
                $detail_koko_dikembalikan[] = [
                    'id_koko' => $id_koko,
                    'nama_koko' => $nama_koko,
                    'jumlah' => $jumlah_digunakan
                ];
            }
        }

        return [
            'total' => $total_koko_dikembalikan,
            'detail' => $detail_koko_dikembalikan
        ];
    } catch (Exception $e) {
        throw new Exception("Gagal mengembalikan stok koko: " . $e->getMessage());
    }
}

// ✅ FUNGSI: untuk mendapatkan tarif upah terkini
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

    return 0; // Default jika tidak ada tarif
}

// ✅ FUNGSI BARU: untuk mengembalikan stok semua bahan yang digunakan
function kembalikanStokBahan($id_hasil_kirim_finishing)
{
    global $conn;

    try {
        // 1. Ambil semua detail bahan yang digunakan dalam finishing ini
        $sql_detail = "SELECT dh.*, k.nama_koko, p.id_produk as id_produk_koko, p.nama_produk, p.stok as stok_produk
                      FROM detail_hasil_kirim_finishing dh
                      JOIN koko k ON dh.id_koko = k.id_koko
                      JOIN produk p ON k.id_produk = p.id_produk
                      WHERE dh.id_hasil_kirim_finishing = ?";

        $stmt = $conn->prepare($sql_detail);
        $stmt->bind_param("i", $id_hasil_kirim_finishing);
        $stmt->execute();
        $result = $stmt->get_result();

        $total_bahan_dikembalikan = 0;
        $detail_bahan_dikembalikan = [];

        while ($detail = $result->fetch_assoc()) {
            $id_koko = $detail['id_koko'];
            $id_produk_koko = $detail['id_produk_koko'];
            $jumlah_digunakan = $detail['jumlah'] ?? 0;
            $nama_koko = $detail['nama_koko'];
            $nama_produk = $detail['nama_produk'];

            if ($jumlah_digunakan > 0 && $id_koko > 0) {
                // 2. Update stok koko - TAMBAHKAN kembali jumlah yang digunakan
                $sql_update_stok = "UPDATE koko 
                                   SET stok = stok + ?,
                                       updated_at = NOW()
                                   WHERE id_koko = ?";

                $stmt_update = $conn->prepare($sql_update_stok);
                $stmt_update->bind_param("ii", $jumlah_digunakan, $id_koko);

                if (!$stmt_update->execute()) {
                    throw new Exception("Gagal mengembalikan stok koko ID $id_koko: " . $conn->error);
                }

                // 3. Update stok produk terkait (jika koko terkait dengan produk)
                if ($id_produk_koko > 0) {
                    $sql_update_produk = "UPDATE produk 
                                         SET stok = stok + ?,
                                             updated_at = NOW()
                                         WHERE id_produk = ?";

                    $stmt_produk = $conn->prepare($sql_update_produk);
                    $stmt_produk->bind_param("ii", $jumlah_digunakan, $id_produk_koko);

                    if (!$stmt_produk->execute()) {
                        throw new Exception("Gagal mengembalikan stok produk ID $id_produk_koko: " . $conn->error);
                    }
                }

                $total_bahan_dikembalikan += $jumlah_digunakan;
                $detail_bahan_dikembalikan[] = [
                    'id_koko' => $id_koko,
                    'id_produk_koko' => $id_produk_koko,
                    'nama_koko' => $nama_koko,
                    'nama_produk' => $nama_produk,
                    'jumlah' => $jumlah_digunakan
                ];
            }
        }

        return [
            'total' => $total_bahan_dikembalikan,
            'detail' => $detail_bahan_dikembalikan
        ];
    } catch (Exception $e) {
        throw new Exception("Gagal mengembalikan stok bahan: " . $e->getMessage());
    }
}

// ✅ PROSES PEMBATALAN JIKA ADA PARAMETER
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id_hasil_kirim_finishing = intval($_GET['id']);

    if ($action == 'batal_finishing') {
        // Ambil data finishing sebelum dibatalkan
        $finishing_data = query("SELECT 
            hk.*,
            p.nama_produk,
            p.stok as stok_produk,
            pet.nama_petugas,
            pet.id_petugas_finishing,
            pen.nama_penjahit,
            hk.tanggal_hasil_finishing,
            hk.total_hasil_finishing,
            hk.status_finishing
        FROM hasil_kirim_finishing hk
        JOIN produk p ON hk.id_produk = p.id_produk 
        JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
        WHERE hk.id_hasil_kirim_finishing = $id_hasil_kirim_finishing")[0];

        if (!$finishing_data) {
            $_SESSION['error'] = "Data finishing tidak ditemukan";
            header("Location: finishing.php");
            exit();
        }

        $id_produk = $finishing_data['id_produk'];
        $id_petugas_finishing = $finishing_data['id_petugas_finishing'];
        $total_hasil_finishing = $finishing_data['total_hasil_finishing'] ?? 0;
        $tanggal_hasil_finishing = $finishing_data['tanggal_hasil_finishing'];
        $status_finishing = $finishing_data['status_finishing'];
        $nama_produk = $finishing_data['nama_produk'];
        $nama_penjahit = $finishing_data['nama_penjahit'] ?? '-';

        // Validasi: hanya bisa batal jika status adalah 'selesai'
        if ($status_finishing != 'selesai') {
            $_SESSION['error'] = "Tidak dapat membatalkan finishing karena status bukan 'selesai'. Status saat ini: " . $status_finishing;
            header("Location: finishing.php");
            exit();
        }

        // Hitung upah petugas finishing yang akan dihapus
        $tarif_finishing = getTarifUpah('finishing', $tanggal_hasil_finishing);
        $upah_dihapus = $total_hasil_finishing * $tarif_finishing;

        $conn->autocommit(FALSE);
        try {
            // 1. Kurangi stok produk hasil (jika sudah ditambahkan sebelumnya)
            if ($total_hasil_finishing > 0) {
                $sql_kurangi_produk = "UPDATE produk SET stok = stok - ? WHERE id_produk = ?";
                $stmt = $conn->prepare($sql_kurangi_produk);
                $stmt->bind_param("ii", $total_hasil_finishing, $id_produk);
                if (!$stmt->execute()) {
                    throw new Exception("Gagal mengurangi stok produk: " . $conn->error);
                }
            }

            // 2. Kembalikan stok semua bahan baku (koko) DAN produk terkait
            $stok_dikembalikan = kembalikanStokBahan($id_hasil_kirim_finishing);

            // 3. Hapus data detail hasil kirim finishing
            $sql_delete_detail = "DELETE FROM detail_hasil_kirim_finishing WHERE id_hasil_kirim_finishing = ?";
            $stmt = $conn->prepare($sql_delete_detail);
            $stmt->bind_param("i", $id_hasil_kirim_finishing);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus detail hasil finishing: " . $conn->error);
            }

            // 4. Hapus data utama hasil kirim finishing
            $sql_delete_utama = "DELETE FROM hasil_kirim_finishing WHERE id_hasil_kirim_finishing = ?";
            $stmt = $conn->prepare($sql_delete_utama);
            $stmt->bind_param("i", $id_hasil_kirim_finishing);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus data hasil finishing: " . $conn->error);
            }

            // 5. Hapus/Update hutang upah petugas finishing (hanya jika ada upah)
            if ($upah_dihapus > 0 && $id_petugas_finishing > 0) {
                if (!kurangiHutangUpahFinishing($id_petugas_finishing, $upah_dihapus)) {
                    throw new Exception("Gagal mengurangi hutang upah petugas finishing");
                }
            }

            $conn->commit();
            $conn->autocommit(TRUE);

            // Buat pesan detail koko yang dikembalikan
            $pesan_koko = "";
            if ($stok_dikembalikan['total'] > 0 && !empty($stok_dikembalikan['detail'])) {
                $pesan_koko = " Stok koko yang dikembalikan: ";
                $detail_items = [];
                foreach ($stok_dikembalikan['detail'] as $koko) {
                    $detail_items[] = $koko['nama_koko'] . " (" . $koko['jumlah'] . " pcs)";
                }
                $pesan_koko .= implode(", ", $detail_items) . ".";
            }

            // Buat pesan detail bahan yang dikembalikan
            $pesan_bahan = "";
            if ($stok_dikembalikan['total'] > 0 && !empty($stok_dikembalikan['detail'])) {
                $pesan_bahan = " Stok bahan yang dikembalikan: ";
                $detail_items = [];
                foreach ($stok_dikembalikan['detail'] as $bahan) {
                    $detail_items[] = $bahan['nama_koko'] . " (" . $bahan['nama_produk'] . ") " . $bahan['jumlah'] . " pcs";
                }
                $pesan_bahan .= implode(", ", $detail_items) . ".";
            }

            $_SESSION['success'] = "✅ Finishing berhasil dibatalkan. " .
                ($total_hasil_finishing > 0 ? " Stok produk <strong>$nama_produk</strong> dikurangi <strong>$total_hasil_finishing pcs</strong>." : "") .
                ($upah_dihapus > 0 ? " Upah petugas finishing dikurangi: <strong>" . formatRupiah($upah_dihapus) . "</strong>." : "") .
                $pesan_koko;
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(TRUE);
            $_SESSION['error'] = "❌ Gagal membatalkan finishing: " . $e->getMessage();
        }

        header("Location: finishing.php");
        exit();
    }

    if ($action == 'batal_kirim') {
        // Ambil data kirim finishing sebelum dibatalkan
        $finishing_data = query("SELECT 
        hk.*,
        p.nama_produk,
        pet.nama_petugas,
        pet.id_petugas_finishing,
        hk.status_finishing,
        hk.total_hasil_finishing,
        -- Cek apakah sudah ada detail hasil finishing koko
        (SELECT COUNT(*) FROM detail_hasil_finishing_koko dhfk
         JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
         WHERE dh.id_hasil_kirim_finishing = hk.id_hasil_kirim_finishing) as jumlah_hasil_koko
    FROM hasil_kirim_finishing hk
    LEFT JOIN produk p ON hk.id_produk = p.id_produk 
    LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
    WHERE hk.id_hasil_kirim_finishing = $id_hasil_kirim_finishing")[0];

        if (!$finishing_data) {
            $_SESSION['error'] = "Data kirim finishing tidak ditemukan";
            header("Location: finishing.php");
            exit();
        }

        $status_finishing = $finishing_data['status_finishing'];
        $nama_produk = $finishing_data['nama_produk'] ?? '-';
        $total_hasil_finishing = $finishing_data['total_hasil_finishing'] ?? 0;
        $jumlah_hasil_koko = $finishing_data['jumlah_hasil_koko'] ?? 0;

        // Validasi: tidak bisa batal kirim jika:
        // 1. Status sudah selesai
        // 2. Sudah ada hasil finishing (total_hasil_finishing > 0)
        // 3. Sudah ada hasil finishing koko (jumlah_hasil_koko > 0)
        if ($status_finishing == 'selesai' || $total_hasil_finishing > 0 || $jumlah_hasil_koko > 0) {
            $error_message = "Tidak dapat membatalkan kirim karena: ";
            $reasons = [];

            if ($status_finishing == 'selesai') {
                $reasons[] = "status sudah selesai";
            }
            if ($total_hasil_finishing > 0) {
                $reasons[] = "sudah ada hasil finishing ($total_hasil_finishing pcs)";
            }
            if ($jumlah_hasil_koko > 0) {
                $reasons[] = "sudah ada hasil finishing koko";
            }

            $error_message .= implode(", ", $reasons) . ".";
            $_SESSION['error'] = $error_message;
            header("Location: finishing.php");
            exit();
        }

        $conn->autocommit(FALSE);
        try {
            // 1. Kembalikan stok bahan baku (koko)
            $stok_dikembalikan = kembalikanStokKoko($id_hasil_kirim_finishing);

            // 2. Hapus data detail hasil kirim finishing
            $sql_delete_detail = "DELETE FROM detail_hasil_kirim_finishing WHERE id_hasil_kirim_finishing = ?";
            $stmt = $conn->prepare($sql_delete_detail);
            $stmt->bind_param("i", $id_hasil_kirim_finishing);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus detail hasil finishing: " . $conn->error);
            }

            // 3. Hapus data utama hasil kirim finishing
            $sql_delete_utama = "DELETE FROM hasil_kirim_finishing WHERE id_hasil_kirim_finishing = ?";
            $stmt = $conn->prepare($sql_delete_utama);
            $stmt->bind_param("i", $id_hasil_kirim_finishing);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus data hasil finishing: " . $conn->error);
            }

            $conn->commit();
            $conn->autocommit(TRUE);

            // Buat pesan detail koko yang dikembalikan
            $pesan_koko = "";
            if ($stok_dikembalikan['total'] > 0 && !empty($stok_dikembalikan['detail'])) {
                $pesan_koko = " Stok koko yang dikembalikan: ";
                $detail_items = [];
                foreach ($stok_dikembalikan['detail'] as $koko) {
                    $detail_items[] = $koko['nama_koko'] . " (" . $koko['jumlah'] . " pcs)";
                }
                $pesan_koko .= implode(", ", $detail_items) . ".";
            }

            $_SESSION['success'] = "✅ Kirim finishing berhasil dibatalkan." . $pesan_koko;
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(TRUE);
            $_SESSION['error'] = "❌ Gagal membatalkan kirim finishing: " . $e->getMessage();
        }

        header("Location: finishing.php");
        exit();
    }
}

// Ambil semua produk untuk dropdown
$produk = query("SELECT * FROM produk ORDER BY nama_produk");
$petugas_finishing = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

// Cek filter yang diterapkan
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$id_petugas_finishing = isset($_GET['id_petugas_finishing']) ? (int)$_GET['id_petugas_finishing'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Query untuk mengambil data kirim finishing
// $sql = "SELECT hk.*, p.nama_produk, pet.nama_petugas,
//                (SELECT SUM(jumlah) FROM detail_hasil_kirim_finishing 
//                 WHERE id_hasil_kirim_finishing = hk.id_hasil_kirim_finishing) as total_bahan
//         FROM hasil_kirim_finishing hk 
//         LEFT JOIN produk p ON hk.id_produk = p.id_produk 
//         LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
//         WHERE 1=1";

// $sql = "SELECT 
//             hk.*, 
//             p.nama_produk, 
//             pet.nama_petugas,
//             GROUP_CONCAT(DISTINCT k.nama_koko ORDER BY k.nama_koko SEPARATOR ', ') as jenis_bahan,
//             COUNT(DISTINCT dh.id_koko) as jumlah_jenis_bahan,
//             SUM(dh.jumlah) as total_bahan
//         FROM hasil_kirim_finishing hk 
//         LEFT JOIN produk p ON hk.id_produk = p.id_produk 
//         LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
//         LEFT JOIN detail_hasil_kirim_finishing dh ON hk.id_hasil_kirim_finishing = dh.id_hasil_kirim_finishing
//         LEFT JOIN koko k ON dh.id_koko = k.id_koko
//         WHERE 1=1
//         GROUP BY hk.id_hasil_kirim_finishing";

// // Filter produk
// if ($id_produk > 0) {
//     $sql .= " AND hk.id_produk = $id_produk";
// }

// // Filter status
// if ($status != 'all') {
//     $sql .= " AND hk.status_finishing = '$status'";
// }

// // Filter periode
// if (!empty($start_date)) {
//     $sql .= " AND hk.tanggal_kirim_finishing >= '$start_date'";
// }

// if (!empty($end_date)) {
//     $end_date .= ' 23:59:59';
//     $sql .= " AND hk.tanggal_kirim_finishing <= '$end_date'";
// }

// $sql .= " ORDER BY hk.tanggal_kirim_finishing DESC";


// Query untuk mengambil data kirim finishing
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

// GROUP BY dengan kolom utama yang diperlukan untuk unique record
$sql .= " GROUP BY hk.id_hasil_kirim_finishing, hk.tanggal_kirim_finishing, hk.id_produk, hk.total_kirim, hk.status_finishing, p.nama_produk, pet.nama_petugas";

$sql .= " ORDER BY hk.tanggal_kirim_finishing DESC";


$data_finishing = query($sql);

// Format tanggal untuk tampilan
function formatDateIndo($date)
{
    if (empty($date)) return '-';
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $timestamp = strtotime($date);
    $hari_ini = $hari[date('w', $timestamp)];
    $tanggal = date('j', $timestamp);
    $bulan_ini = $bulan[date('n', $timestamp) - 1];
    $tahun = date('Y', $timestamp);

    return "$hari_ini, $tanggal $bulan_ini $tahun";
}

// Format waktu
function formatTime($datetime)
{
    if (empty($datetime)) return '-';
    return date('H:i', strtotime($datetime));
}


// HITUNG TOTAL UNTUK INFORMASI RINGKASAN
// ========================================

// 1. Hitung total semua data (tanpa filter)
$sql_total_all = "SELECT 
    COUNT(*) as total_kirim,
    SUM(hk.total_kirim) as total_kirim_pcs,
    SUM(hk.total_hasil_finishing) as total_hasil_finishing,
    SUM(CASE WHEN hk.status_finishing = 'selesai' THEN 1 ELSE 0 END) as jumlah_selesai
FROM hasil_kirim_finishing hk";

$result_total_all = $conn->query($sql_total_all);
$total_all = $result_total_all->fetch_assoc();

$total_kirim_all = $total_all['total_kirim'] ?? 0;
$total_kirim_pcs_all = $total_all['total_kirim_pcs'] ?? 0;
$total_hasil_finishing_all = $total_all['total_hasil_finishing'] ?? 0;
$jumlah_selesai_all = $total_all['jumlah_selesai'] ?? 0;

// 2. Hitung total dengan filter (jika ada filter)
$is_filtered = ($id_petugas_finishing > 0 || $status != 'all' || !empty($start_date) || !empty($end_date));

$sql_filtered = "SELECT 
    COUNT(*) as total_kirim,
    SUM(hk.total_kirim) as total_kirim_pcs,
    SUM(hk.total_hasil_finishing) as total_hasil_finishing,
    SUM(CASE WHEN hk.status_finishing = 'selesai' THEN 1 ELSE 0 END) as jumlah_selesai
FROM hasil_kirim_finishing hk
WHERE 1=1";

if ($id_petugas_finishing > 0) {
    $sql_filtered .= " AND hk.id_petugas_finishing = $id_petugas_finishing";
}

if ($status != 'all') {
    $sql_filtered .= " AND hk.status_finishing = '$status'";
}

if (!empty($start_date)) {
    $sql_filtered .= " AND hk.tanggal_kirim_finishing >= '$start_date'";
}

if (!empty($end_date)) {
    $end_date_temp = $end_date . ' 23:59:59';
    $sql_filtered .= " AND hk.tanggal_kirim_finishing <= '$end_date_temp'";
}

$result_filtered = $conn->query($sql_filtered);
$total_filtered = $result_filtered->fetch_assoc();

$total_kirim_filtered = $total_filtered['total_kirim'] ?? 0;
$total_kirim_pcs_filtered = $total_filtered['total_kirim_pcs'] ?? 0;
$total_hasil_finishing_filtered = $total_filtered['total_hasil_finishing'] ?? 0;
$jumlah_selesai_filtered = $total_filtered['jumlah_selesai'] ?? 0;

// Hitung persentase
$persentase_selesai_all = ($total_kirim_all > 0) ? ($jumlah_selesai_all / $total_kirim_all) * 100 : 0;
$persentase_selesai_filtered = ($total_kirim_filtered > 0) ? ($jumlah_selesai_filtered / $total_kirim_filtered) * 100 : 0;

// Hitung total upah (estimasi berdasarkan tarif standar)
$tarif_standar = getTarifUpah('finishing');
$total_upah_all = $total_hasil_finishing_all * $tarif_standar;
$total_upah_filtered = $total_hasil_finishing_filtered * $tarif_standar;

?>

<style>
    .swal2-container {
        z-index: 99999 !important;
    }

    .badge-produksi {
        background-color: #0d6efd;
    }

    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .btn-group-actions {
        display: flex;
        gap: 5px;
        flex-wrap: nowrap;
    }

    .btn-group-actions .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .upah-column {
        background-color: #e8f5e8 !important;
        font-weight: bold;
    }

    .table th {
        font-size: 0.8rem;
    }

    .table td {
        font-size: 0.8rem;
    }

    .tarif-info {
        font-size: 0.7rem;
        color: #6c757d;
    }

    .status-badge {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.075);
    }
</style>

<style>
    .tarif-info {
        font-size: 0.7rem;
        color: #6c757d;
    }

    .total-info {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 15px;
        margin-top: 20px;
    }

    .total-info h5 {
        color: #0d6efd;
        margin-bottom: 15px;
        border-bottom: 2px solid #0d6efd;
        padding-bottom: 5px;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        padding: 5px 0;
        border-bottom: 1px dashed #dee2e6;
    }

    .total-label {
        font-weight: 600;
        color: #495057;
    }

    .total-value {
        font-weight: 700;
        color: #198754;
    }

    .total-filtered {
        background-color: #e7f1ff;
        padding: 10px;
        border-radius: 3px;
        margin-top: 10px;
    }
</style>

<!-- [Body] Start -->

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <!-- Sidebar Start -->
    <?php include_once '../includes/sidebar.php'; ?>
    <!-- Sidebar End -->

    <!-- [ Header Topbar ] start -->
    <header class="pc-header">
        <div class="header-wrapper">
            <!-- [Mobile Media Block] start -->
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
                    <!-- ======= Menu collapse Icon ===== -->
                    <li class="pc-h-item pc-sidebar-collapse">
                        <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                    <li class="pc-h-item pc-sidebar-popup">
                        <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                            <i class="ti ti-menu-2"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- [Mobile Media Block end] -->
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Master Data Finishing Koko</h2>
                    <div hidden>
                        <a href="hasil_finishing.php" class="btn btn-success">
                            <i class="ti ti-circle-plus"></i> Hasil Finishing
                        </a>
                    </div>

                    <div>
                        <a href="kirim_koko.php" class="btn btn-warning">
                            <i class="ti ti-circle-plus"></i> Kirim Koko
                        </a>
                    </div>
                </div>

                <!-- BAGIAN FILTER DAN RINGKASAN -->
                <div class="row mb-4">
                    <!-- FILTER FORM -->
                    <div class="col-md-8">
                        <form method="GET" class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Filter Petugas Finishing</label>
                                <select name="id_petugas_finishing" class="form-select">
                                    <option value="0">Semua Petugas</option>
                                    <?php foreach ($petugas_finishing as $p): ?>
                                        <option value="<?= $p['id_petugas_finishing'] ?>" <?= ($id_petugas_finishing == $p['id_petugas_finishing']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nama_petugas']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Filter Status</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?= ($status == 'all') ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="pengiriman" <?= ($status == 'pengiriman') ? 'selected' : '' ?>>Pengiriman</option>
                                    <option value="diproses" <?= ($status == 'diproses') ? 'selected' : '' ?>>Diproses</option>
                                    <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Mulai</label>
                                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                                <small class="text-muted">Bulan/Tanggal/Tahun</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Akhir</label>
                                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                                <small class="text-muted">Bulan/Tanggal/Tahun</small>
                            </div>


                            <div class="col-md-4 d-flex align-items-end">
                                <div class="d-flex">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="ti ti-filter"></i> Filter
                                    </button>
                                    <?php if ($is_filtered): ?>
                                        <a href="finishing.php" class="btn btn-secondary">
                                            <i class="ti ti-rotate"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <!-- ============================================
                        KARTU INFORMASI FILTER YANG DIGUNAKAN
                        ============================================ -->
                        <?php if ($is_filtered): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card border-primary">
                                        <div class="card-header bg-primary text-white py-2">
                                            <h6 class="mb-0 text-white">
                                                <i class="ti ti-filter-check"></i> Filter Aktif
                                            </h6>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="row g-2">
                                                <?php
                                                // Fungsi untuk menampilkan nilai filter dengan label yang sesuai
                                                function getFilterLabel($key, $value)
                                                {
                                                    global $petugas_finishing;

                                                    switch ($key) {
                                                        case 'id_petugas_finishing':
                                                            if ($value == 0) return null;
                                                            foreach ($petugas_finishing as $pf) {
                                                                if ($pf['id_petugas_finishing'] == $value) {
                                                                    return '<span class="badge bg-primary">Petugas: ' . htmlspecialchars($pf['nama_petugas']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'status':
                                                            if ($value == 'all') return null;
                                                            $status_labels = [
                                                                'pengiriman' => 'Pengiriman',
                                                                'diproses' => 'Diproses',
                                                                'selesai' => 'Selesai'
                                                            ];
                                                            $status_colors = [
                                                                'pengiriman' => 'secondary',
                                                                'diproses' => 'warning',
                                                                'selesai' => 'success'
                                                            ];
                                                            return '<span class="badge bg-' . ($status_colors[$value] ?? 'secondary') . '">Status: ' . $status_labels[$value] . '</span>';

                                                        case 'start_date':
                                                            if (empty($value)) return null;
                                                            return '<span class="badge bg-secondary">Mulai: ' . dateIndo($value) . '</span>';

                                                        case 'end_date':
                                                            if (empty($value)) return null;
                                                            return '<span class="badge bg-secondary">Akhir: ' . dateIndo($value) . '</span>';
                                                    }
                                                    return null;
                                                }
                                                ?>

                                                <?php
                                                // Array filter yang akan ditampilkan
                                                $filters_to_display = [
                                                    'id_petugas_finishing' => $id_petugas_finishing,
                                                    'status' => $status,
                                                    'start_date' => $start_date,
                                                    'end_date' => $end_date
                                                ];

                                                $active_filters = [];

                                                // Loop melalui semua filter
                                                foreach ($filters_to_display as $key => $value) {
                                                    $label = getFilterLabel($key, $value);
                                                    if ($label) {
                                                        $active_filters[] = $label;
                                                    }
                                                }
                                                ?>

                                                <?php if (!empty($active_filters)): ?>
                                                    <div class="col-12">
                                                        <p class="mb-2 small text-muted">
                                                            <i class="ti ti-info-circle"></i> Menampilkan data dengan filter:
                                                        </p>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <?php foreach ($active_filters as $filter): ?>
                                                                <?= $filter ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Informasi Jumlah Data yang Difilter -->
                                                    <div class="col-12 mt-2">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <p class="mb-0 small">
                                                                <i class="ti ti-database"></i>
                                                                <strong><?= count($data_finishing) ?> data</strong> ditemukan dengan filter ini
                                                                (dari total <?= number_format($total_kirim_all) ?> pengiriman)
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="col-12">
                                                        <p class="mb-0 text-muted">
                                                            <i class="ti ti-info-circle"></i> Tidak ada filter yang aktif
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ============================================
                    BAGIAN TOTAL INFORMASI FINISHING
                    ============================================ -->
                    <div class="col-md-4">
                        <div class="total-info">
                            <h5><i class="ti ti-chart-bar"></i> Ringkasan Finishing</h5>

                            <!-- Total Semua Data (Tanpa Filter) -->
                            <div class="total-row">
                                <span class="total-label">Total Pengiriman:</span>
                                <span class="total-value">
                                    <?= number_format($total_kirim_all) ?> Data
                                </span>
                            </div>

                            <div class="total-row">
                                <span class="total-label">Total Kirim (Pcs):</span>
                                <span class="total-value">
                                    <?= number_format($total_kirim_pcs_all) ?> Pcs
                                </span>
                            </div>

                            <div class="total-row">
                                <span class="total-label">Total Hasil Finishing:</span>
                                <span class="total-value">
                                    <?= number_format($total_hasil_finishing_all) ?> Pcs
                                </span>
                            </div>

                            <!-- Progress Bar Persentase Selesai -->
                            <div hidden class="progress-container">
                                <div class="total-row">
                                    <span class="total-label">Selesai:</span>
                                    <span class="total-value">
                                        <?= number_format($persentase_selesai_all, 1) ?>%
                                        <span class="badge badge-percentage 
                                            <?= $persentase_selesai_all >= 80 ? 'bg-high-success' : ($persentase_selesai_all >= 50 ? 'bg-medium-warning' : 'bg-low-danger') ?>">
                                            <?= $jumlah_selesai_all ?>/<?= $total_kirim_all ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?= min($persentase_selesai_all, 100) ?>%"></div>
                                </div>
                            </div>

                            <!-- Total Dengan Filter (Jika Ada Filter) -->
                            <?php if ($is_filtered): ?>
                                <div class="total-filtered">
                                    <h6><i class="ti ti-filter"></i> Hasil Setelah Filter:</h6>
                                    <div class="total-row">
                                        <span class="total-label">Pengiriman:</span>
                                        <span class="total-value">
                                            <?= number_format($total_kirim_filtered) ?> Data
                                        </span>
                                    </div>

                                    <div class="total-row">
                                        <span class="total-label">Kirim (Pcs):</span>
                                        <span class="total-value">
                                            <?= number_format($total_kirim_pcs_filtered) ?> Pcs
                                        </span>
                                    </div>

                                    <div class="total-row">
                                        <span class="total-label">Hasil Finishing:</span>
                                        <span class="total-value">
                                            <?= number_format($total_hasil_finishing_filtered) ?> Pcs
                                        </span>
                                    </div>

                                    <?php if ($total_kirim_filtered > 0): ?>
                                        <div hidden class="total-row">
                                            <span class="total-label">Persentase Selesai:</span>
                                            <span class="total-value" style="color: 
                                                <?= $persentase_selesai_filtered >= 80 ? '#28a745' : ($persentase_selesai_filtered >= 50 ? '#ffc107' : '#dc3545') ?>;">
                                                <?= number_format($persentase_selesai_filtered, 1) ?>%
                                                <span class="badge badge-percentage 
                                                    <?= $persentase_selesai_filtered >= 80 ? 'bg-high-success' : ($persentase_selesai_filtered >= 50 ? 'bg-medium-warning' : 'bg-low-danger') ?>">
                                                    <?= $jumlah_selesai_filtered ?>/<?= $total_kirim_filtered ?>
                                                </span>
                                            </span>
                                        </div>
                                    <?php endif; ?>


                                </div>
                            <?php else: ?>
                                <!-- Jika tidak ada filter, tampilkan estimasi upah semua data -->
                                <?php if ($total_upah_all > 0): ?>
                                    <div hidden class="total-row mt-2">
                                        <span class="total-label">Total Estimasi Upah:</span>
                                        <span class="total-value text-warning">
                                            <?= formatRupiah($total_upah_all) ?>
                                            <small class="tarif-info">(@<?= formatRupiah($tarif_standar) ?>/pcs)</small>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card p-3">
                    <!-- Tampilkan pesan error atau success -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th class="bg-warning text-white align-middle">Penjahit</th>
                                    <th class="bg-warning text-white align-middle">Petugas Finishing</th>
                                    <th class="bg-warning text-white align-middle">Tgl Kirim</th>
                                    <th class="bg-warning text-white align-middle">Total Kirim</th>
                                    <th class="bg-warning text-white align-middle">Jenis Bahan</th>
                                    <th class="bg-warning text-white align-middle">Jml Jenis</th>
                                    <th class="align-middle">Status</th>
                                    <th class="bg-info text-white align-middle">Tgl Selesai Finishing</th>
                                    <th class="bg-info text-white align-middle">Hasil Finishing (Pcs)</th>
                                    <th class="align-middle">Aksi</th>
                                </tr>
                            </thead>

                            <!-- Dalam bagian tabel body -->
                            <tbody>
                                <?php if (empty($data_finishing)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center">Tidak ada data kirim finishing</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($data_finishing as $data): ?>
                                        <?php
                                        $status_badge = [
                                            'pengiriman' => 'secondary',
                                            'diproses' => 'warning',
                                            'selesai' => 'success'
                                        ];
                                        $status_color = $status_badge[$data['status_finishing']] ?? 'secondary';

                                        // Cek apakah sudah ada hasil finishing (total_hasil_finishing > 0)
                                        $has_results = ($data['total_hasil_finishing'] > 0);

                                        // Tombol hapus/batal hanya bisa ditekan jika:
                                        // 1. Status bukan 'selesai' 
                                        // 2. Belum ada hasil finishing sama sekali
                                        $can_delete = ($data['status_finishing'] != 'selesai' && !$has_results);
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($data['nama_penjahit']) ?></td>

                                            <td><?= htmlspecialchars($data['nama_petugas']) ?></td>
                                            <td><?= formatDateIndo($data['tanggal_kirim_finishing']) ?></td>
                                            <td class="text-center"><?= $data['total_kirim'] ?></td>
                                            <td>
                                                <?php if (!empty($data['jenis_bahan'])): ?>
                                                    <small><?= htmlspecialchars($data['jenis_bahan']) ?></small>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= $data['jumlah_jenis_bahan'] ?? 0 ?>
                                            </td>

                                            <td class="text-center">
                                                <span class="badge bg-<?= $status_color ?> status-badge">
                                                    <?= ucfirst($data['status_finishing']) ?>
                                                </span>
                                            </td>
                                            <td class="text-start">
                                                <?php if (!empty($data['tanggal_hasil_finishing'])): ?>
                                                    <?= formatDateIndo($data['tanggal_hasil_finishing']) ?><br>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= $data['total_hasil_finishing'] > 0 ? $data['total_hasil_finishing'] . ' Pcs' : '-' ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group gap-1 text-center">
                                                    <!-- Tombol Detail -->
                                                    <!-- <a href="detail.php?id=<?= $data['id_hasil_kirim_finishing'] ?>"
                                                        class="btn btn-sm btn-primary" title="Detail">
                                                        <i class="ti ti-eye"></i>
                                                    </a> -->

                                                    <a href="hasil_finishing_koko.php?id=<?= $data['id_hasil_kirim_finishing'] ?>"
                                                        class="btn btn-sm btn-warning" title="Finishing Koko">
                                                        <i class="ti ti-settings"></i>
                                                    </a>

                                                    <!-- TOMBOL BARU: Finishing Koko -->
                                                    <!-- <?php if ($data['status_finishing'] == 'pengiriman' || $data['status_finishing'] == 'diproses'): ?>
                                                        <a href="hasil_finishing_koko.php?id=<?= $data['id_hasil_kirim_finishing'] ?>"
                                                            class="btn btn-sm btn-warning" title="Finishing Koko">
                                                            <i class="ti ti-settings"></i>
                                                        </a>
                                                    <?php endif; ?> -->

                                                    <!-- Tombol Edit (hanya untuk pengiriman/diproses) -->
                                                    <?php if ($data['status_finishing'] == 'pengiriman' || $data['status_finishing'] == 'diproses'): ?>

                                                        <!-- Tombol Batal Kirim Finishing - Hanya jika belum ada hasil -->
                                                        <?php if ($can_delete): ?>
                                                            <button class="btn btn-sm btn-danger btn-batal-kirim"
                                                                data-id="<?= $data['id_hasil_kirim_finishing'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['nama_produk']) ?>"
                                                                data-status="<?= $data['status_finishing'] ?>"
                                                                title="Batalkan Kirim Finishing">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled
                                                                title="Tidak dapat dibatalkan - Sudah ada hasil finishing">
                                                                <i class="ti ti-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <!-- Tombol Batal Hasil Finishing (untuk selesai) -->
                                                    <!-- <?php if ($data['status_finishing'] == 'selesai'): ?>
                                                        <button class="btn btn-sm btn-danger btn-batal-finishing"
                                                            data-id="<?= $data['id_hasil_kirim_finishing'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['nama_produk']) ?>"
                                                            data-hasil="<?= $data['total_hasil_finishing'] ?>"
                                                            title="Batalkan Hasil Finishing">
                                                            <i class="ti ti-x"></i>
                                                        </button>
                                                    <?php endif; ?> -->
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include '../includes/footer.php'; ?>
</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // ✅ Konfirmasi Batal Kirim Finishing (untuk status pengiriman/diproses)
    $(document).on('click', '.btn-batal-kirim:not(:disabled)', function() {
        const id = $(this).data('id');
        const produk = $(this).data('produk');
        const status = $(this).data('status');

        Swal.fire({
            title: 'Batalkan Kirim Finishing?',
            html: `<div class="text-left">
              <p>Apakah Anda yakin ingin membatalkan kirim finishing untuk:</p>
              <ul>
                <li><strong>Produk:</strong> ${produk}</li>
                <li><strong>Status:</strong> ${status}</li>
              </ul>
              <p class="text-danger mt-3"><strong>Konsekuensi:</strong></p>
              <ul class="text-danger">
                <li>Stok bahan baku (koko) akan dikembalikan</li>
                <li>Data akan dihapus permanen dari sistem</li>
                <li><strong>Aksi ini tidak dapat dibatalkan!</strong></li>
              </ul>
            </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Batal',
            width: '600px'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'finishing.php?action=batal_kirim&id=' + id;
            }
        });
    });

    $(document).ready(function() {
        // ✅ Konfirmasi Batal Hasil Finishing (untuk status selesai)
        $(document).on('click', '.btn-batal-finishing', function() {
            const id = $(this).data('id');
            const produk = $(this).data('produk');
            const hasil = $(this).data('hasil');

            Swal.fire({
                title: 'Batalkan Hasil Finishing?',
                html: `<div class="text-left">
                      <p>Apakah Anda yakin ingin membatalkan hasil finishing untuk:</p>
                      <ul>
                        <li><strong>Produk:</strong> ${produk}</li>
                        <li><strong>Hasil Finishing:</strong> ${hasil} Pcs</li>
                      </ul>
                      <p class="text-danger mt-3"><strong>Konsekuensi:</strong></p>
                      <ul class="text-danger">
                        <li>Stok produk <strong>${produk}</strong> akan dikurangi <strong>${hasil} pcs</strong></li>
                        <li>Stok bahan baku (koko) akan dikembalikan</li>
                        <li>Hutang upah petugas finishing akan dikurangi</li>
                        <li>Data akan dihapus dari sistem</li>
                      </ul>
                    </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Batal',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'finishing.php?action=batal_finishing&id=' + id;
                }
            });
        });

        // ✅ Konfirmasi Batal Kirim Finishing (untuk status pengiriman/diproses)
        $(document).on('click', '.btn-batal-kirim', function() {
            const id = $(this).data('id');
            const produk = $(this).data('produk');
            const status = $(this).data('status');

            Swal.fire({
                title: 'Batalkan Kirim Finishing?',
                html: `<div class="text-left">
                      <p>Apakah Anda yakin ingin membatalkan kirim finishing untuk:</p>
                      <ul>
                        <li><strong>Produk:</strong> ${produk}</li>
                        <li><strong>Status:</strong> ${status}</li>
                      </ul>
                      <p class="text-danger mt-3"><strong>Konsekuensi:</strong></p>
                      <ul class="text-danger">
                        <li>Stok bahan baku (koko) akan dikembalikan</li>
                        <li>Data akan dihapus permanen dari sistem</li>
                        <li><strong>Aksi ini tidak dapat dibatalkan!</strong></li>
                      </ul>
                    </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Batal',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'finishing.php?action=batal_kirim&id=' + id;
                }
            });
        });

        // Set default date range (30 hari terakhir)
        function setDefaultDateRange() {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - 30);

            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };

            if (!$('input[name="start_date"]').val()) {
                $('input[name="start_date"]').val(formatDate(startDate));
            }
            if (!$('input[name="end_date"]').val()) {
                $('input[name="end_date"]').val(formatDate(endDate));
            }
        }

        setDefaultDateRange();
    });
</script>

</html>