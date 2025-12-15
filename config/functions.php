<?php
require_once 'database.php';

date_default_timezone_set('Asia/Jakarta');

// Bersihkan lock yang expired di awal setiap request
// cleanupExpiredLocks();

// Atau buat cron job untuk membersihkan lock yang expired
// 0 * * * * php /path/to/cleanup_locks.php

/***************************************
 * Fungsi: dateIndo
 * Deskripsi:
 *   Mengubah format tanggal dari format
 *   standar (YYYY-MM-DD) menjadi format
 *   tanggal Indonesia (DD NamaBulan YYYY).
 *
 * Parameter:
 *   - $tanggal : Tanggal dalam format apa pun
 *                yang dikenali oleh strtotime().
 *
 * Return:
 *   String tanggal dalam format Indonesia,
 *   contoh: "25 Desember 2025".
 ***************************************/
function dateIndo($tanggal)
{
    // Array nama bulan dalam bahasa Indonesia
    $bulanIndo = [
        1 => 'Januari',
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

    // Normalisasi tanggal ke format YYYY-MM-DD
    $tanggal = date('Y-m-d', strtotime($tanggal));

    // Memecah tanggal menjadi bagian tahun, bulan, dan hari
    $pecah = explode('-', $tanggal);

    // Mengembalikan format tanggal Indonesia
    return $pecah[2] . ' ' . $bulanIndo[(int)$pecah[1]] . ' ' . $pecah[0];
}



/******************************************************
 * Fungsi: isLoggedIn()
 * Deskripsi:
 *   Memeriksa apakah pengguna saat ini telah login.
 *   Pemeriksaan dilakukan dengan memastikan variabel
 *   sesi 'user_id' sudah diset ketika proses login
 *   berhasil.
 *
 * Return:
 *   - true  : Jika user_id ada dalam session.
 *   - false : Jika user_id tidak ditemukan.
 ******************************************************/
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}



/******************************************************
 * Fungsi: redirectIfNotLoggedIn()
 * Deskripsi:
 *   Digunakan untuk membatasi akses halaman yang
 *   membutuhkan autentikasi login. Jika pengguna belum
 *   login (isLoggedIn() mengembalikan false), maka
 *   pengguna akan langsung diarahkan ke halaman login.
 *
 * Catatan:
 *   - exit() digunakan untuk menghentikan eksekusi PHP
 *     setelah melakukan redirect.
 ******************************************************/
function redirectIfNotLoggedIn()
{
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit();
    }
}



/******************************************************
 * Fungsi: checkRole($requiredRole)
 * Deskripsi:
 *   Memvalidasi apakah pengguna memiliki role yang sesuai
 *   untuk mengakses halaman tertentu. Jika role pengguna
 *   tidak cocok dengan role yang diperlukan, maka pengguna
 *   akan diarahkan ke halaman utama.
 *
 * Parameter:
 *   - $requiredRole : Peran (role) yang wajib dimiliki
 *                     untuk mengakses halaman.
 ******************************************************/
function checkRole($requiredRole)
{
    if ($_SESSION['role'] != $requiredRole) {
        header("Location: ../index.php");
        exit();
    }
}



/******************************************************
 * Fungsi: formatRupiah($angka)
 * Deskripsi:
 *   Mengubah nilai numerik menjadi format rupiah dengan
 *   penulisan standar Indonesia (Rp xxx.xxx).
 *
 * Parameter:
 *   - $angka : Angka yang ingin diformat.
 *
 * Return:
 *   - string : Nilai rupiah dalam format "Rp 1.000.000".
 ******************************************************/
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}



/** 
 * ******************************************************************
 *  Fungsi: loadEnv
 *  Deskripsi:
 *      Fungsi ini digunakan untuk memuat dan membaca variabel-variabel
 *      lingkungan (environment variables) dari sebuah file `.env`.
 *      Setiap baris yang berisi pasangan KEY=VALUE akan diproses dan
 *      dimasukkan ke dalam array global $_ENV.
 *
 *  Parameter:
 *      - string $path
 *          Lokasi file `.env` yang akan dibaca.
 *          Secara default mengarah ke folder induk dari file ini.
 *
 *  Catatan:
 *      - Baris yang diawali dengan tanda `#` akan dianggap sebagai komentar
 *        dan dilewati.
 *      - Nilai VALUE yang menggunakan tanda kutip ("" atau '') akan
 *        dihilangkan kutipnya secara otomatis.
 *
 *  Return:
 *      Tidak mengembalikan nilai apa pun. Namun, variabel lingkungan akan
 *      tersimpan di dalam $_ENV.
 * ******************************************************************
 */
function loadEnv($path = __DIR__ . '/../.env')
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Lewati komentar
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Pisahkan KEY dan VALUE
        list($name, $value) = array_map('trim', explode('=', $line, 2));

        // Hilangkan tanda kutip jika ada
        $value = trim($value, "\"'");

        // Simpan ke variabel lingkungan
        $_ENV[$name] = $value;
    }
}


/** 
 * * * * * 
 * Fungsi: catatHutangUpah
 * Deskripsi:
 *   Mencatat atau memperbarui hutang upah karyawan berdasarkan tanggal produksi.
 *   Sistem menggunakan periode bulanan (selalu tanggal 1) agar semua transaksi
 *   dalam bulan yang sama masuk ke catatan hutang yang sama.
 *
 * Parameter:
 *   - $id_karyawan      : ID karyawan (integer)
 *   - $jenis_karyawan   : Jenis karyawan (string) — contoh: "penjahit" atau "pemotong"
 *   - $tanggal_produksi : Tanggal produksi (string: YYYY-MM-DD)
 *   - $jumlah_upah      : Nominal upah yang harus ditambahkan (float/double)
 *
 * Return:
 *   - true jika proses insert/update berhasil
 *   - false jika gagal
 * * * * * 
 */
// function catatHutangUpah($id_karyawan, $jenis_karyawan, $tanggal_produksi, $jumlah_upah)
// {
//     global $conn;

//     /**
//      * ------------------------------------------------------------
//      * 1. Cek catatan hutang tanpa periode
//      * ------------------------------------------------------------
//      * Hanya berdasarkan id_karyawan + jenis_karyawan.
//      */
//     $check = $conn->prepare("
//         SELECT id_hutang, total_upah, sisa_hutang 
//         FROM hutang_upah 
//         WHERE id_karyawan = ? AND jenis_karyawan = ?
//     ");
//     $check->bind_param("is", $id_karyawan, $jenis_karyawan);
//     $check->execute();
//     $result = $check->get_result();

//     /**
//      * ------------------------------------------------------------
//      * 2. Jika catatan hutang SUDAH ADA → update total / sisa hutang
//      * ------------------------------------------------------------
//      */
//     if ($result->num_rows > 0) {

//         $hutang = $result->fetch_assoc();

//         // Tambah upah ke total & sisa
//         $total_upah_baru = $hutang['total_upah'] + $jumlah_upah;
//         $sisa_hutang_baru = $hutang['sisa_hutang'] + $jumlah_upah;

//         $update = $conn->prepare("
//             UPDATE hutang_upah 
//             SET total_upah = ?, sisa_hutang = ?, updated_at = NOW()
//             WHERE id_hutang = ?
//         ");
//         $update->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);

//         return $update->execute();
//     }

//     /**
//      * ------------------------------------------------------------
//      * 3. Jika catatan hutang BELUM ADA → buat baru
//      * ------------------------------------------------------------
//      */
//     else {

//         $insert = $conn->prepare("
//             INSERT INTO hutang_upah (id_karyawan, jenis_karyawan, total_upah, sisa_hutang, created_at, updated_at)
//             VALUES (?, ?, ?, ?, NOW(), NOW())
//         ");
//         $insert->bind_param("isdd", $id_karyawan, $jenis_karyawan, $jumlah_upah, $jumlah_upah);

//         return $insert->execute();
//     }
// }


/** 
 * *****************************************************
 * Fungsi: bayarHutangUpah
 * -----------------------------------------------------
 * Deskripsi:
 *   Fungsi ini digunakan untuk mencatat pembayaran hutang upah
 *   serta memperbarui data hutang utama secara otomatis.
 *
 * Parameter:
 *   - $id_hutang      : ID hutang yang akan dibayar
 *   - $tanggal_bayar  : Tanggal pembayaran dilakukan
 *   - $jumlah_bayar   : Jumlah nominal pembayaran
 *   - $metode_bayar   : Metode pembayaran (cash, transfer, dll)
 *   - $keterangan     : Catatan tambahan (opsional)
 *
 * Alur:
 *   1. Insert data pembayaran baru ke tabel pembayaran_upah_2
 *   2. Update total_dibayar & sisa_hutang pada tabel hutang_upah
 *   3. Ubah status menjadi "lunas" jika sisa hutang sudah 0
 *   4. Transaksi menggunakan autocommit FALSE (atomic)
 *
 * Return:
 *   - true  : berhasil
 *   - false : gagal (rollback otomatis)
 *
 * *****************************************************
 */
function bayarHutangUpah($id_hutang, $tanggal_bayar, $jumlah_bayar, $metode_bayar, $keterangan = '')
{
    global $conn;

    // Matikan autocommit agar transaksi berjalan atomik
    $conn->autocommit(FALSE);

    try {
        /**
         * -----------------------------------------------------------
         * 1. Insert pembayaran hutang
         * -----------------------------------------------------------
         * Menyimpan data pembayaran ke tabel pembayaran_upah_2.
         */
        $insert = $conn->prepare("
            INSERT INTO pembayaran_upah_2 (id_hutang, tanggal_bayar, jumlah_bayar, metode_bayar, keterangan) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert->bind_param("isdss", $id_hutang, $tanggal_bayar, $jumlah_bayar, $metode_bayar, $keterangan);

        if (!$insert->execute()) {
            throw new Exception("Gagal menyimpan pembayaran: " . $insert->error);
        }


        /**
         * -----------------------------------------------------------
         * 2. Update data hutang
         * -----------------------------------------------------------
         * - total_dibayar ditambah jumlah pembayaran
         * - sisa_hutang dikurangi jumlah pembayaran
         * - status otomatis berubah menjadi 'lunas' jika sisa <= 0
         */
        $update = $conn->prepare("
            UPDATE hutang_upah 
            SET total_dibayar = total_dibayar + ?, 
                sisa_hutang   = sisa_hutang - ?,
                status        = CASE 
                                    WHEN (sisa_hutang - ?) <= 0 
                                    THEN 'lunas' 
                                    ELSE 'belum_lunas' 
                                END
            WHERE id_hutang = ?
        ");
        $update->bind_param("dddi", $jumlah_bayar, $jumlah_bayar, $jumlah_bayar, $id_hutang);

        if (!$update->execute()) {
            throw new Exception("Gagal update hutang: " . $update->error);
        }


        // Semua proses berhasil → commit transaksi
        $conn->commit();
        return true;
    } catch (Exception $e) {

        // Jika terjadi error → rollback data
        $conn->rollback();
        return false;
    } finally {

        // Nyalakan kembali autocommit
        $conn->autocommit(TRUE);
    }
}



/** 
 * **********************************************
 * Fungsi: getDetailHutang
 * Deskripsi:
 *   Mengambil detail data hutang upah berdasarkan ID hutang,
 *   termasuk nama karyawan (pemotong atau penjahit) dan jumlah 
 *   pembayaran yang sudah dilakukan.
 *   
 * Parameter:
 *   - $id_hutang (int): ID hutang yang ingin diambil datanya.
 *
 * Return:
 *   - Array associative berisi data hutang, nama karyawan,
 *     serta total jumlah pembayaran. Mengembalikan null jika data tidak ditemukan.
 * **********************************************
 */
function getDetailHutang($id_hutang)
{
    global $conn;

    // Query untuk mengambil detail hutang upah
    // - Mengambil semua kolom dari tabel hutang_upah (alias h)
    // - CASE digunakan untuk menentukan nama karyawan:
    //       Jika jenis_karyawan = 'pemotong' → ambil dari tabel pemotong
    //       Jika jenis_karyawan = 'penjahit' → ambil dari tabel penjahit
    // - LEFT JOIN digunakan agar data tetap muncul meskipun tidak ada relasi pembayaran
    // - COUNT menghitung jumlah pembayaran yang sudah dilakukan terhadap hutang tersebut
    $sql = "SELECT h.*, 
                   CASE 
                       WHEN h.jenis_karyawan = 'pemotong' THEN p.nama_pemotong 
                       ELSE j.nama_penjahit 
                   END as nama_karyawan,
                   COUNT(pb.id_pembayaran) as jumlah_pembayaran
            FROM hutang_upah h
            LEFT JOIN pemotong p 
                   ON h.jenis_karyawan = 'pemotong' 
                  AND h.id_karyawan = p.id_pemotong
            LEFT JOIN penjahit j 
                   ON h.jenis_karyawan = 'penjahit' 
                  AND h.id_karyawan = j.id_penjahit
            LEFT JOIN pembayaran_upah_2 pb 
                   ON h.id_hutang = pb.id_hutang
            WHERE h.id_hutang = ?
            GROUP BY h.id_hutang";

    // Menyiapkan statement SQL
    $stmt = $conn->prepare($sql);

    // Mengikat parameter ID hutang
    $stmt->bind_param("i", $id_hutang);

    // Eksekusi query
    $stmt->execute();

    // Ambil hasil dalam bentuk associative array
    $result = $stmt->get_result();

    return $result->fetch_assoc(); // Mengembalikan satu baris data hutang
}



/*******************************************************
 * Fungsi : batalPembayaranUpah
 * Deskripsi :
 *   Membatalkan transaksi pembayaran upah yang sudah tercatat.
 *   Proses yang dilakukan:
 *     1. Mengambil data pembayaran berdasarkan ID.
 *     2. Menghapus data pembayaran dari tabel pembayaran_upah_2.
 *     3. Mengembalikan perubahan pada tabel hutang_upah
 *        (mengurangi total_dibayar dan menambah sisa_hutang).
 *     4. Mengatur ulang status hutang menjadi "belum_lunas"
 *        jika setelah pembatalan masih ada sisa hutang.
 *
 * Catatan:
 *   - Menggunakan transaksi (BEGIN, COMMIT, ROLLBACK) untuk
 *     memastikan integritas data.
 *   - Jika terjadi error di salah satu langkah,
 *     maka semua perubahan dibatalkan (rollback).
 *
 * Parameter:
 *   @param int $id_pembayaran → ID pembayaran yang ingin dibatalkan
 *
 * Return:
 *   - true  → jika pembatalan berhasil
 *   - false → jika terjadi error
 *******************************************************/
function batalPembayaranUpah($id_pembayaran)
{
    global $conn;

    // Matikan autocommit agar proses menggunakan transaksi
    $conn->autocommit(FALSE);

    try {
        /** 
         * 1. Ambil data pembayaran berdasarkan id_pembayaran
         *    Digunakan untuk mengetahui jumlah bayar dan id_hutang terkait
         */
        $sql_pembayaran = "SELECT * FROM pembayaran_upah_2 WHERE id_pembayaran = ?";
        $stmt_pembayaran = $conn->prepare($sql_pembayaran);
        $stmt_pembayaran->bind_param("i", $id_pembayaran);
        $stmt_pembayaran->execute();
        $pembayaran = $stmt_pembayaran->get_result()->fetch_assoc();

        // Jika data tidak ditemukan → hentikan proses
        if (!$pembayaran) {
            throw new Exception("Data pembayaran tidak ditemukan");
        }

        $id_hutang = $pembayaran['id_hutang'];
        $jumlah_bayar = $pembayaran['jumlah_bayar'];

        /** 
         * 2. Hapus data pembayaran dari tabel pembayaran_upah_2 
         */
        $sql_hapus = "DELETE FROM pembayaran_upah_2 WHERE id_pembayaran = ?";
        $stmt_hapus = $conn->prepare($sql_hapus);
        $stmt_hapus->bind_param("i", $id_pembayaran);

        if (!$stmt_hapus->execute()) {
            throw new Exception("Gagal menghapus pembayaran");
        }

        /**
         * 3. Update kembali data hutang:
         *     - total_dibayar dikurangi jumlah_bayar
         *     - sisa_hutang ditambah jumlah_bayar
         *     - status diatur kembali: jika masih ada sisa → belum_lunas
         */
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

        // Semua proses berhasil → commit
        $conn->commit();
        return true;
    } catch (Exception $e) {

        // Terjadi error → rollback semua perubahan
        $conn->rollback();
        error_log("Error batal pembayaran: " . $e->getMessage());
        return false;
    } finally {
        // Aktifkan kembali autocommit
        $conn->autocommit(TRUE);
    }
}


// FUNGSI PRODUKSI DENGAN JENIS BERBEDA

// Fungsi untuk mendapatkan tipe produk
// function getTipeProduk($id_produk)
// {
//     global $conn;

//     $sql = "SELECT tipe_produk FROM produk WHERE id_produk = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("i", $id_produk);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     if ($result->num_rows > 0) {
//         $row = $result->fetch_assoc();
//         return $row['tipe_produk'];
//     }

//     return 'mukena'; // default
// }



// Fungsi untuk mengunci data
function lockData($data_type, $data_id, $user_id = null)
{
    global $conn;

    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? 0;
    }

    $session_id = session_id();
    $lock_time = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes')); // Lock selama 5 menit

    // Cek apakah sudah ada lock yang aktif
    $sql_check = "SELECT * FROM input_locks 
                  WHERE data_type = ? 
                  AND data_id = ? 
                  AND status = 'active' 
                  AND expires_at > NOW()";

    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("si", $data_type, $data_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Ada lock aktif
        $lock = $result->fetch_assoc();

        // Cek apakah lock milik user yang sama (di session yang sama)
        if ($lock['session_id'] === $session_id && $lock['user_id'] == $user_id) {
            // Update waktu lock
            $sql_update = "UPDATE input_locks 
                          SET expires_at = ? 
                          WHERE id_lock = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("si", $expires_at, $lock['id_lock']);
            $stmt->execute();
            return true;
        }

        // Lock dimiliki oleh user lain atau session lain
        return false;
    }

    // Buat lock baru
    $sql_insert = "INSERT INTO input_locks 
                  (session_id, user_id, data_type, data_id, expires_at) 
                  VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("siss", $session_id, $user_id, $data_type, $data_id, $expires_at);
    return $stmt->execute();
}

// Fungsi untuk melepas lock
function releaseLock($data_type, $data_id)
{
    global $conn;

    $session_id = session_id();
    $user_id = $_SESSION['user_id'] ?? 0;

    $sql = "UPDATE input_locks 
            SET status = 'released' 
            WHERE data_type = ? 
            AND data_id = ? 
            AND session_id = ? 
            AND user_id = ? 
            AND status = 'active'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisi", $data_type, $data_id, $session_id, $user_id);
    return $stmt->execute();
}

// Fungsi untuk membersihkan lock yang expired
function cleanupExpiredLocks()
{
    global $conn;

    $sql = "UPDATE input_locks 
            SET status = 'released' 
            WHERE expires_at <= NOW() 
            AND status = 'active'";

    return $conn->query($sql);
}

// Fungsi untuk cek apakah data terkunci
function isDataLocked($data_type, $data_id)
{
    global $conn;

    $session_id = session_id();
    $user_id = $_SESSION['user_id'] ?? 0;

    cleanupExpiredLocks();

    $sql = "SELECT * FROM input_locks 
            WHERE data_type = ? 
            AND data_id = ? 
            AND status = 'active' 
            AND expires_at > NOW()";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $data_type, $data_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $lock = $result->fetch_assoc();

        // Jika lock milik user yang sama (session yang sama), return false
        if ($lock['session_id'] === $session_id && $lock['user_id'] == $user_id) {
            return false;
        }

        // Lock dimiliki oleh user/session lain
        return [
            'locked' => true,
            'user_id' => $lock['user_id'],
            'lock_time' => $lock['lock_time'],
            'expires_at' => $lock['expires_at']
        ];
    }

    return false;
}


// Fungsi untuk mengecek apakah hasil jahit sudah ada
function isHasilJahitExist($id_hasil_potong_fix)
{
    global $conn;

    $sql = "SELECT COUNT(*) as total FROM hasil_potong_fix 
            WHERE id_hasil_potong_fix = ? 
            AND tanggal_hasil_jahit IS NOT NULL 
            AND total_hasil_jahit IS NOT NULL";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_hasil_potong_fix);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    return $data['total'] > 0;
}

// Fungsi untuk mendapatkan data hasil jahit existing
function getHasilJahitExisting($id_hasil_potong_fix)
{
    global $conn;

    $sql = "SELECT tanggal_hasil_jahit, total_hasil_jahit 
            FROM hasil_potong_fix 
            WHERE id_hasil_potong_fix = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_hasil_potong_fix);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_assoc();
}


// Fungsi untuk update hutang upah dengan validasi
function updateHutangUpahPenjahit($id_penjahit, $jumlah_kurang)
{
    global $conn;

    try {
        // 1. Cek apakah ada hutang
        $sql_check = "SELECT id_hutang, total_upah, sisa_hutang 
                     FROM hutang_upah 
                     WHERE id_karyawan = ? AND jenis_karyawan = 'penjahit'";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("i", $id_penjahit);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Tidak ditemukan hutang untuk penjahit ini");
        }

        $hutang = $result->fetch_assoc();

        // 2. Validasi: tidak boleh mengurangi lebih dari sisa hutang
        if ($jumlah_kurang > $hutang['sisa_hutang']) {
            throw new Exception("Jumlah yang akan dikurangi (Rp " . number_format($jumlah_kurang, 0, ',', '.') .
                ") lebih besar dari sisa hutang (Rp " . number_format($hutang['sisa_hutang'], 0, ',', '.') . ")");
        }

        // 3. Hitung nilai baru
        $total_upah_baru = $hutang['total_upah'] - $jumlah_kurang;
        $sisa_hutang_baru = $hutang['sisa_hutang'] - $jumlah_kurang;

        // Pastikan tidak minus
        $total_upah_baru = max(0, $total_upah_baru);
        $sisa_hutang_baru = max(0, $sisa_hutang_baru);

        // 4. Update atau hapus
        if ($total_upah_baru <= 0) {
            $sql_delete = "DELETE FROM hutang_upah WHERE id_hutang = ?";
            $stmt = $conn->prepare($sql_delete);
            $stmt->bind_param("i", $hutang['id_hutang']);
            $stmt->execute();
            return true;
        } else {
            $sql_update = "UPDATE hutang_upah 
                          SET total_upah = ?, 
                              sisa_hutang = ?,
                              updated_at = NOW()
                          WHERE id_hutang = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
            $stmt->execute();
            return true;
        }
    } catch (Exception $e) {
        throw new Exception("Gagal update hutang: " . $e->getMessage());
    }
}



// Muat variabel lingkungan dari file .env
loadEnv();
