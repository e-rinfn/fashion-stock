<?php
require_once 'database.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function redirectIfNotLoggedIn()
{
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit();
    }
}

function checkRole($requiredRole)
{
    if ($_SESSION['role'] != $requiredRole) {
        header("Location: ../index.php");
        exit();
    }
}

function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// config/functions.php
function loadEnv($path = __DIR__ . '/../.env')
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split into key and value
        list($name, $value) = array_map('trim', explode('=', $line, 2));

        // Remove quotes if present
        $value = trim($value, "\"'");

        // Set environment variable
        $_ENV[$name] = $value;
    }
}

// Fungsi untuk mencatat upah ke hutang
function catatHutangUpah($id_karyawan, $jenis_karyawan, $tanggal_produksi, $jumlah_upah)
{
    global $conn;

    // Tentukan periode (bulan-tahun dari tanggal produksi)
    $periode = date('Y-m-01', strtotime($tanggal_produksi));

    // Cek apakah sudah ada hutang untuk periode ini
    $check = $conn->prepare("SELECT id_hutang, total_upah, sisa_hutang FROM hutang_upah 
                            WHERE id_karyawan = ? AND jenis_karyawan = ? AND periode = ?");
    $check->bind_param("iss", $id_karyawan, $jenis_karyawan, $periode);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Update hutang yang sudah ada
        $hutang = $result->fetch_assoc();
        $total_upah_baru = $hutang['total_upah'] + $jumlah_upah;
        $sisa_hutang_baru = $hutang['sisa_hutang'] + $jumlah_upah;

        $update = $conn->prepare("UPDATE hutang_upah SET total_upah = ?, sisa_hutang = ? 
                                WHERE id_hutang = ?");
        $update->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
        return $update->execute();
    } else {
        // Buat hutang baru
        $insert = $conn->prepare("INSERT INTO hutang_upah (id_karyawan, jenis_karyawan, periode, total_upah, sisa_hutang) 
                                VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("issdd", $id_karyawan, $jenis_karyawan, $periode, $jumlah_upah, $jumlah_upah);
        return $insert->execute();
    }
}

// Fungsi untuk melakukan pembayaran
function bayarHutangUpah($id_hutang, $tanggal_bayar, $jumlah_bayar, $metode_bayar, $keterangan = '')
{
    global $conn;

    $conn->autocommit(FALSE);
    try {
        // 1. Insert pembayaran
        $insert = $conn->prepare("INSERT INTO pembayaran_upah_2 (id_hutang, tanggal_bayar, jumlah_bayar, metode_bayar, keterangan) 
                                VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("isdss", $id_hutang, $tanggal_bayar, $jumlah_bayar, $metode_bayar, $keterangan);

        if (!$insert->execute()) {
            throw new Exception("Gagal menyimpan pembayaran: " . $insert->error);
        }

        // 2. Update hutang
        $update = $conn->prepare("UPDATE hutang_upah 
                                SET total_dibayar = total_dibayar + ?, 
                                    sisa_hutang = sisa_hutang - ?,
                                    status = CASE WHEN (sisa_hutang - ?) <= 0 THEN 'lunas' ELSE 'belum_lunas' END
                                WHERE id_hutang = ?");
        $update->bind_param("dddi", $jumlah_bayar, $jumlah_bayar, $jumlah_bayar, $id_hutang);

        if (!$update->execute()) {
            throw new Exception("Gagal update hutang: " . $update->error);
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    } finally {
        $conn->autocommit(TRUE);
    }
}

// Fungsi untuk mendapatkan detail hutang
function getDetailHutang($id_hutang)
{
    global $conn;

    $sql = "SELECT h.*, 
                   CASE 
                       WHEN h.jenis_karyawan = 'pemotong' THEN p.nama_pemotong 
                       ELSE j.nama_penjahit 
                   END as nama_karyawan,
                   COUNT(pb.id_pembayaran) as jumlah_pembayaran
            FROM hutang_upah h
            LEFT JOIN pemotong p ON h.jenis_karyawan = 'pemotong' AND h.id_karyawan = p.id_pemotong
            LEFT JOIN penjahit j ON h.jenis_karyawan = 'penjahit' AND h.id_karyawan = j.id_penjahit
            LEFT JOIN pembayaran_upah_2 pb ON h.id_hutang = pb.id_hutang
            WHERE h.id_hutang = ?
            GROUP BY h.id_hutang";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_hutang);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Fungsi untuk membatalkan pembayaran
function batalPembayaranUpah($id_pembayaran)
{
    global $conn;

    $conn->autocommit(FALSE);
    try {
        // 1. Ambil data pembayaran
        $sql_pembayaran = "SELECT * FROM pembayaran_upah_2 WHERE id_pembayaran = ?";
        $stmt_pembayaran = $conn->prepare($sql_pembayaran);
        $stmt_pembayaran->bind_param("i", $id_pembayaran);
        $stmt_pembayaran->execute();
        $pembayaran = $stmt_pembayaran->get_result()->fetch_assoc();

        if (!$pembayaran) {
            throw new Exception("Data pembayaran tidak ditemukan");
        }

        $id_hutang = $pembayaran['id_hutang'];
        $jumlah_bayar = $pembayaran['jumlah_bayar'];

        // 2. Hapus pembayaran
        $sql_hapus = "DELETE FROM pembayaran_upah_2 WHERE id_pembayaran = ?";
        $stmt_hapus = $conn->prepare($sql_hapus);
        $stmt_hapus->bind_param("i", $id_pembayaran);

        if (!$stmt_hapus->execute()) {
            throw new Exception("Gagal menghapus pembayaran");
        }

        // 3. Update hutang
        $sql_update_hutang = "UPDATE hutang_upah 
                             SET total_dibayar = total_dibayar - ?, 
                                 sisa_hutang = sisa_hutang + ?,
                                 status = CASE WHEN (sisa_hutang + ?) > 0 THEN 'belum_lunas' ELSE 'lunas' END
                             WHERE id_hutang = ?";
        $stmt_update = $conn->prepare($sql_update_hutang);
        $stmt_update->bind_param("dddi", $jumlah_bayar, $jumlah_bayar, $jumlah_bayar, $id_hutang);

        if (!$stmt_update->execute()) {
            throw new Exception("Gagal update data hutang");
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error batal pembayaran: " . $e->getMessage());
        return false;
    } finally {
        $conn->autocommit(TRUE);
    }
}

loadEnv();
