<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';

// ============================================================================
// FUNGSI VALIDASI DAN UTILITAS
// ============================================================================

/**
 * Cek apakah data koko sudah ada untuk produk tertentu
 * @param int $id_produk ID produk yang akan dicek
 * @return bool True jika data koko sudah ada, false jika belum
 */
function isKokoExist($id_produk)
{
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM koko WHERE id_produk = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] > 0;
}

/**
 * Mendapatkan stok koko untuk produk tertentu
 * @param int $id_produk ID produk yang akan dicek stoknya
 * @return int Jumlah stok koko, 0 jika tidak ada
 */
function getStokKoko($id_produk)
{
    global $conn;
    $sql = "SELECT stok FROM koko WHERE id_produk = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['stok'];
    }
    return 0;
}

/**
 * Mencatat atau menambah hutang upah karyawan (tanpa periode)
 * @param int $id_karyawan ID karyawan
 * @param string $jenis_karyawan Jenis karyawan ('pemotong' atau 'penjahit')
 * @param string $tanggal_produksi Tanggal produksi
 * @param float $jumlah_upah Jumlah upah yang harus dibayar
 * @return bool True jika berhasil, false jika gagal
 */
function catatHutangUpah($id_karyawan, $jenis_karyawan, $tanggal_produksi, $jumlah_upah)
{
    global $conn;

    /**
     * 1. Cek catatan hutang tanpa periode
     * Hanya berdasarkan id_karyawan + jenis_karyawan.
     */
    $check = $conn->prepare("
        SELECT id_hutang, total_upah, sisa_hutang 
        FROM hutang_upah 
        WHERE id_karyawan = ? AND jenis_karyawan = ?
    ");
    $check->bind_param("is", $id_karyawan, $jenis_karyawan);
    $check->execute();
    $result = $check->get_result();

    /**
     * 2. Jika catatan hutang SUDAH ADA → update total / sisa hutang
     */
    if ($result->num_rows > 0) {

        $hutang = $result->fetch_assoc();

        // Tambah upah ke total & sisa
        $total_upah_baru = $hutang['total_upah'] + $jumlah_upah;
        $sisa_hutang_baru = $hutang['sisa_hutang'] + $jumlah_upah;

        $update = $conn->prepare("
            UPDATE hutang_upah 
            SET total_upah = ?, sisa_hutang = ?, updated_at = NOW()
            WHERE id_hutang = ?
        ");
        $update->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);

        return $update->execute();
    }

    /**
     * 3. Jika catatan hutang BELUM ADA → buat baru
     */
    else {

        $insert = $conn->prepare("
            INSERT INTO hutang_upah (id_karyawan, jenis_karyawan, total_upah, sisa_hutang, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $insert->bind_param("isdd", $id_karyawan, $jenis_karyawan, $jumlah_upah, $jumlah_upah);

        return $insert->execute();
    }
}

/**
 * Mendapatkan tarif upah terkini berdasarkan jenis tarif dan tanggal referensi
 * @param string $jenis_tarif Jenis tarif ('pemotongan' atau 'penjahitan')
 * @param string|null $tanggal_referensi Tanggal referensi untuk mencari tarif yang berlaku
 * @return float Tarif per unit
 */
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

    // Default value jika tidak ada tarif
    return 0.00;
}

/**
 * Update hutang upah TANPA PERIODE (tambah hutang)
 * @param int $id_karyawan ID karyawan
 * @param string $jenis_karyawan Jenis karyawan ('pemotong' atau 'penjahit')
 * @param float $jumlah_upah Jumlah upah yang ditambahkan
 * @return bool True jika berhasil, false jika gagal
 */
function updateHutangUpah($id_karyawan, $jenis_karyawan, $jumlah_upah)
{
    global $conn;

    $sql = "UPDATE hutang_upah 
           SET total_upah = total_upah + ?,
               sisa_hutang = sisa_hutang + ?,
               updated_at = NOW()
           WHERE id_karyawan = ? AND jenis_karyawan = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddis", $jumlah_upah, $jumlah_upah, $id_karyawan, $jenis_karyawan);
    return $stmt->execute();
}

/**
 * Mengurangi hutang upah penjahit dengan validasi
 * @param int $id_penjahit ID penjahit
 * @param float $jumlah_kurang Jumlah yang akan dikurangi
 * @return bool True jika berhasil, Exception jika gagal
 * @throws Exception Jika terjadi kesalahan
 */
function kurangiHutangUpahPenjahit($id_penjahit, $jumlah_kurang)
{
    global $conn;

    try {
        // 1. Cek apakah ada hutang
        $sql_check = "SELECT id_hutang, total_upah, sisa_hutang, total_dibayar 
                     FROM hutang_upah 
                     WHERE id_karyawan = ? AND jenis_karyawan = 'penjahit'
                     LIMIT 1";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("i", $id_penjahit);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Jika tidak ada hutang, tidak perlu melakukan apa-apa
            // (mungkin data sudah dihapus sebelumnya)
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
        throw new Exception("Gagal mengurangi hutang upah: " . $e->getMessage());
    }
}

/**
 * Mengurangi hutang upah pemotong dengan validasi
 * @param int $id_pemotong ID pemotong
 * @param float $jumlah_kurang Jumlah yang akan dikurangi
 * @return bool True jika berhasil, Exception jika gagal
 * @throws Exception Jika terjadi kesalahan
 */
function kurangiHutangUpahPemotong($id_pemotong, $jumlah_kurang)
{
    global $conn;

    try {
        // 1. Cek apakah ada hutang
        $sql_check = "SELECT id_hutang, total_upah, sisa_hutang, total_dibayar 
                     FROM hutang_upah 
                     WHERE id_karyawan = ? AND jenis_karyawan = 'pemotong'
                     LIMIT 1";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("i", $id_pemotong);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Jika tidak ada hutang, tidak perlu melakukan apa-apa
            // (mungkin data sudah dihapus sebelumnya)
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
        throw new Exception("Gagal mengurangi hutang upah pemotong: " . $e->getMessage());
    }
}

// ============================================================================
// AMBIL DATA DARI DATABASE UNTUK DROPDOWN DAN FILTER
// ============================================================================

// Ambil semua produk untuk dropdown
$produk = query("SELECT * FROM produk");
$pemotong = query("SELECT * FROM pemotong");
$penjahit = query("SELECT * FROM penjahit");
$bordir = query("SELECT * FROM bordir"); // Tambah data bordir

// ============================================================================
// SET FILTER DARI REQUEST GET
// ============================================================================

// Cek filter yang diterapkan
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Tambah filter pemotong, penjahit, dan bordir
$id_pemotong = isset($_GET['id_pemotong']) ? (int)$_GET['id_pemotong'] : 0;
$id_penjahit = isset($_GET['id_penjahit']) ? $_GET['id_penjahit'] : 0; // Bisa string untuk nilai -1
$id_bordir = isset($_GET['id_bordir']) ? $_GET['id_bordir'] : 0; // Tambah filter bordir

$start_date_default = date('Y-m-01');
$end_date_default   = date('Y-m-t');

// ============================================================================
// HITUNG TOTAL DATA UNTUK FOOTER TABEL
// ============================================================================

/**
 * 1. HITUNG TOTAL TANPA FILTER (SEMUA DATA)
 * Menghitung total hasil potong, hasil bordir, dan hasil jahit dari semua data tanpa filter
 */
$sql_total_all = "SELECT 
    SUM(h.total_hasil) as total_hasil_all,
    SUM(COALESCE(h.total_hasil_bordir, 0)) as total_hasil_bordir_all,
    SUM(COALESCE(h.total_hasil_jahit, 0)) as total_hasil_jahit_all
FROM hasil_potong_fix h 
WHERE 1=1";

$total_all = query($sql_total_all)[0];
$total_hasil_all = $total_all['total_hasil_all'] ?? 0;
$total_hasil_bordir_all = $total_all['total_hasil_bordir_all'] ?? 0;
$total_hasil_jahit_all = $total_all['total_hasil_jahit_all'] ?? 0;

/**
 * 2. HITUNG TOTAL DENGAN FILTER YANG DITERAPKAN
 * Menghitung total hasil potong, bordir, dan jahit dengan filter yang diterapkan user
 */
$sql_total_filtered = "SELECT 
    SUM(h.total_hasil) as total_hasil_filtered,
    SUM(COALESCE(h.total_hasil_bordir, 0)) as total_hasil_bordir_filtered,
    SUM(COALESCE(h.total_hasil_jahit, 0)) as total_hasil_jahit_filtered
FROM hasil_potong_fix h 
JOIN produk p ON h.id_produk = p.id_produk 
JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
LEFT JOIN bordir bor ON h.id_bordir = bor.id_bordir 
WHERE 1=1";

// Filter produk
if ($id_produk > 0) {
    $sql_total_filtered .= " AND h.id_produk = $id_produk";
}

// Filter pemotong
if ($id_pemotong > 0) {
    $sql_total_filtered .= " AND h.id_pemotong = $id_pemotong";
}

// Filter penjahit
if ($id_penjahit == '-1') {
    $sql_total_filtered .= " AND (h.id_penjahit IS NULL OR h.id_penjahit = 0)";
} elseif ($id_penjahit > 0) {
    $sql_total_filtered .= " AND h.id_penjahit = $id_penjahit";
}

// Filter bordir
if ($id_bordir == '-1') {
    $sql_total_filtered .= " AND (h.id_bordir IS NULL OR h.id_bordir = 0)";
} elseif ($id_bordir > 0) {
    $sql_total_filtered .= " AND h.id_bordir = $id_bordir";
}

// Filter status
if ($status != 'all') {
    $sql_total_filtered .= " AND h.status_potong = '$status'";
}

// Filter periode
if (!empty($start_date)) {
    $sql_total_filtered .= " AND h.tanggal_hasil_potong >= '$start_date'";
}

if (!empty($end_date)) {
    $sql_total_filtered .= " AND h.tanggal_hasil_potong <= '$end_date'";
}

$total_filtered = query($sql_total_filtered)[0];
$total_hasil_filtered = $total_filtered['total_hasil_filtered'] ?? 0;
$total_hasil_bordir_filtered = $total_filtered['total_hasil_bordir_filtered'] ?? 0;
$total_hasil_jahit_filtered = $total_filtered['total_hasil_jahit_filtered'] ?? 0;

/**
 * 3. QUERY UNTUK DATA TABEL DENGAN FILTER
 * Query utama untuk menampilkan data di tabel dengan filter yang diterapkan
 */
$sql = "SELECT h.*, p.nama_produk, p.tipe_produk, pem.nama_pemotong, 
               pen.nama_penjahit, bor.nama_bordir,
               COALESCE(h.tarif_upah, 0) as tarif_upah,
               (SELECT SUM(jumlah) FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = h.id_hasil_potong_fix) as total_hasil_potong
        FROM hasil_potong_fix h 
        JOIN produk p ON h.id_produk = p.id_produk 
        JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
        LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
        LEFT JOIN bordir bor ON h.id_bordir = bor.id_bordir 
        WHERE 1=1";

// Filter produk
if ($id_produk > 0) {
    $sql .= " AND h.id_produk = $id_produk";
}

// Filter pemotong
if ($id_pemotong > 0) {
    $sql .= " AND h.id_pemotong = $id_pemotong";
}

// Filter penjahit
if ($id_penjahit == '-1') {
    $sql .= " AND (h.id_penjahit IS NULL OR h.id_penjahit = 0)";
} elseif ($id_penjahit > 0) {
    $sql .= " AND h.id_penjahit = $id_penjahit";
}

// Filter bordir
if ($id_bordir == '-1') {
    $sql .= " AND (h.id_bordir IS NULL OR h.id_bordir = 0)";
} elseif ($id_bordir > 0) {
    $sql .= " AND h.id_bordir = $id_bordir";
}

// Filter status
if ($status != 'all') {
    $sql .= " AND h.status_potong = '$status'";
}

// Filter periode
if (!empty($start_date)) {
    $sql .= " AND h.tanggal_hasil_potong >= '$start_date'";
}

if (!empty($end_date)) {
    $sql .= " AND h.tanggal_hasil_potong <= '$end_date'";
}

$sql .= " ORDER BY CAST(h.seri AS UNSIGNED) DESC, h.tanggal_hasil_potong DESC";

$produksi = query($sql);

// ============================================================================
// PERSIAPAN DATA UNTUK DITAMPILKAN DI TABEL
// ============================================================================

/**
 * Gabungkan data produksi untuk tampilan dengan perhitungan upah
 * Menghitung upah pemotong, upah penjahit, dan total upah
 */
$all_data = [];
foreach ($produksi as $prod) {
    // Dapatkan tarif upah berdasarkan tanggal produksi
    $tarif_pemotong = getTarifUpah('pemotongan', $prod['tanggal_hasil_potong']);
    $tarif_bordir = !empty($prod['tanggal_hasil_bordir']) ?
        getTarifUpah('bordir', $prod['tanggal_hasil_bordir']) :
        getTarifUpah('bordir', $prod['tanggal_hasil_potong']);
    $tarif_penjahit = !empty($prod['tanggal_hasil_jahit']) ?
        getTarifUpah('penjahitan', $prod['tanggal_hasil_jahit']) :
        getTarifUpah('penjahitan', $prod['tanggal_hasil_potong']);

    // Hitung upah
    $upah_pemotong = $prod['total_hasil'] * $tarif_pemotong;
    $upah_bordir = !empty($prod['total_hasil_bordir']) ? $prod['total_hasil_bordir'] * $tarif_bordir : 0;
    $upah_penjahit = !empty($prod['total_hasil_jahit']) ? $prod['total_hasil_jahit'] * $tarif_penjahit : 0;
    $total_upah = $upah_pemotong + $upah_bordir + $upah_penjahit;

    $all_data[] = [
        'type' => 'produksi',
        'id' => $prod['id_hasil_potong_fix'],
        'tanggal' => $prod['tanggal_hasil_potong'],
        'produk' => $prod['nama_produk'],
        'tipe_produk' => $prod['tipe_produk'],
        'seri' => $prod['seri'],
        'seri_numeric' => intval(preg_replace('/[^0-9]/', '', $prod['seri'])), // Ekstrak angka saja
        'pemotong' => $prod['nama_pemotong'],
        'bordir' => $prod['nama_bordir'] ?? '-',
        'penjahit' => $prod['nama_penjahit'],
        'id_bordir' => $prod['id_bordir'] ?? null,
        'id_penjahit' => $prod['id_penjahit'],
        'status' => $prod['status_potong'],
        'total_hasil' => $prod['total_hasil'],
        'total_harga' => $prod['total_harga'],
        'tanggal_kirim_bordir' => $prod['tanggal_kirim_bordir'] ?? null,
        'tanggal_hasil_bordir' => $prod['tanggal_hasil_bordir'] ?? null,
        'total_hasil_bordir' => $prod['total_hasil_bordir'] ?? 0,
        'tanggal_kirim_jahit' => $prod['tanggal_kirim_jahit'] ?? null,
        'tanggal_hasil_jahit' => $prod['tanggal_hasil_jahit'] ?? null,
        'total_hasil_jahit' => $prod['total_hasil_jahit'] ?? 0,
        'upah_pemotong' => $upah_pemotong,
        'upah_bordir' => $upah_bordir,
        'upah_penjahit' => $upah_penjahit,
        'total_upah' => $total_upah,
        'rate_pemotong' => $tarif_pemotong,
        'rate_bordir' => $tarif_bordir,
        'rate_penjahit' => $tarif_penjahit
    ];
}

// Urutkan data berdasarkan seri (descending)
usort($all_data, function ($a, $b) {
    return (int)$b['seri'] <=> (int)$a['seri'];
});

// ============================================================================
// PROSES INPUT BORDIR DAN PENJAHITAN DARI FORM POST
// ============================================================================

/**
 * 1. SIMPAN TANGGAL KIRIM BORDIR (Modal Pertama untuk Bordir)
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['simpan_tanggal_kirim_bordir'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
        $id_bordir = isset($_POST['id_bordir']) && !empty($_POST['id_bordir']) ? intval($_POST['id_bordir']) : null;
        $tanggal_kirim_bordir = isset($_POST['tanggal_kirim_bordir']) && !empty($_POST['tanggal_kirim_bordir'])
            ? $conn->real_escape_string($_POST['tanggal_kirim_bordir'])
            : null;

        // Validasi
        $error_modal = null;

        if (empty($id_bordir)) {
            $error_modal = "Bordir harus dipilih";
        } elseif (empty($tanggal_kirim_bordir)) {
            $error_modal = "Tanggal kirim bordir harus diisi";
        }

        if (!$error_modal) {
            try {
                // Update tanggal kirim bordir
                $id_bordir_sql = $id_bordir ? $id_bordir : "NULL";
                $tanggal_kirim_sql = $tanggal_kirim_bordir ? "'$tanggal_kirim_bordir'" : "NULL";

                $sql_update = "UPDATE hasil_potong_fix 
                          SET id_bordir = $id_bordir_sql, 
                              tanggal_kirim_bordir = $tanggal_kirim_sql,
                              status_potong = 'bordir'
                          WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                if ($conn->query($sql_update)) {
                    $_SESSION['success'] = "Data tanggal kirim bordir berhasil disimpan. Status berubah menjadi 'Bordir'.";
                    header("Location: list.php");
                    exit();
                } else {
                    throw new Exception("Gagal menyimpan data tanggal kirim bordir: " . $conn->error);
                }
            } catch (Exception $e) {
                $error_modal = $e->getMessage();
            }
        }
    }

    /**
     * 2. SIMPAN HASIL BORDIR
     */
    if (isset($_POST['simpan_hasil_bordir'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
        $tanggal_hasil_bordir = isset($_POST['tanggal_hasil_bordir']) && !empty($_POST['tanggal_hasil_bordir'])
            ? $conn->real_escape_string($_POST['tanggal_hasil_bordir'])
            : null;
        $total_hasil_bordir = isset($_POST['total_hasil_bordir']) ? intval($_POST['total_hasil_bordir']) : 0;

        // Ambil input upah manual
        $upah_per_potongan = floatval($_POST['upah_per_potongan_bordir']);
        $total_upah = floatval($_POST['total_upah_bordir']);

        // Validasi input upah
        if ($total_upah <= 0) {
            $error_modal = "Total upah harus lebih dari 0!";
        }

        // Validasi perhitungan
        if ($upah_per_potongan > 0 && $total_hasil_bordir > 0) {
            $calculated_upah = $upah_per_potongan * $total_hasil_bordir;
            if (abs($calculated_upah - $total_upah) > 1) { // Toleransi 1 rupiah
                $error_modal = "Perhitungan upah tidak sesuai! Total seharusnya: Rp " . number_format($calculated_upah);
            }
        }

        // Ambil data produksi
        $produksi_data = query("SELECT 
        hp.id_produk, 
        hp.total_hasil, 
        hp.id_bordir, 
        hp.tanggal_kirim_bordir,
        hp.seri,
        p.tipe_produk,
        hp.tanggal_hasil_bordir as existing_tanggal,
        hp.total_hasil_bordir as existing_jumlah
    FROM hasil_potong_fix hp
    JOIN produk p ON hp.id_produk = p.id_produk
    WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0];

        $id_produk = $produksi_data['id_produk'];
        $total_hasil_potong = $produksi_data['total_hasil'];
        $id_bordir = $produksi_data['id_bordir'];
        $tanggal_kirim_bordir = $produksi_data['tanggal_kirim_bordir'];
        $seri = $produksi_data['seri'];
        $tipe_produk = $produksi_data['tipe_produk'];
        $existing_tanggal = $produksi_data['existing_tanggal'];
        $existing_jumlah = $produksi_data['existing_jumlah'];

        // Cek apakah data sudah ada (existing)
        $existing = !empty($existing_tanggal) && !empty($existing_jumlah);

        // Inisialisasi variabel change_log
        $change_log = '';
        if ($existing) {
            // Log perubahan
            $changes = [];
            if ($existing_tanggal != $tanggal_hasil_bordir) {
                $changes[] = "Tanggal: " . $existing_tanggal . " → " . $tanggal_hasil_bordir;
            }
            if ($existing_jumlah != $total_hasil_bordir) {
                $changes[] = "Jumlah: " . $existing_jumlah . " → " . $total_hasil_bordir . " Pcs";
            }

            if (!empty($changes)) {
                $change_log = "Update hasil bordir: " . implode(", ", $changes);
            }
        }

        // Validasi
        $error_modal = null;

        if (empty($tanggal_hasil_bordir)) {
            $error_modal = "Tanggal hasil bordir harus diisi";
        } elseif ($total_hasil_bordir <= 0) {
            $error_modal = "Total hasil bordir harus lebih dari 0";
        } elseif ($total_hasil_bordir > $total_hasil_potong) {
            $error_modal = "Total hasil bordir tidak boleh melebihi total hasil potong ($total_hasil_potong Pcs)";
        } elseif (empty($id_bordir) || empty($tanggal_kirim_bordir)) {
            $error_modal = "Data bordir atau tanggal kirim belum diinput. Silakan input tanggal kirim terlebih dahulu.";
        }

        if (!$error_modal) {
            $conn->autocommit(FALSE);
            try {
                // HITUNG UPAH BORDIR
                $upah_per_potongan_manual = floatval($_POST['upah_per_potongan_bordir']);
                $upah_bordir = $total_hasil_bordir * $upah_per_potongan_manual;

                // 1. Update data hasil bordir
                $sql_update = "UPDATE hasil_potong_fix 
                    SET tanggal_hasil_bordir = '$tanggal_hasil_bordir', 
                        tarif_upah_bordir = $upah_per_potongan_manual,
                        total_hasil_bordir = $total_hasil_bordir,
                        status_potong = 'penjahitan'";

                // Jika ada perubahan, tambahkan log
                if ($existing && !empty($change_log)) {
                    $sql_update .= ", keterangan = CONCAT(COALESCE(keterangan, ''), ' | $change_log')";
                }

                $sql_update .= " WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                if (!$conn->query($sql_update)) {
                    throw new Exception("Gagal update data hasil bordir: " . $conn->error);
                }

                // 2. Catat/Update hutang upah bordir TANPA PERIODE
                if ($existing) {
                    // Update hutang yang sudah ada
                    $upah_sebelumnya = $existing_jumlah * getTarifUpah('bordir', $existing_tanggal);
                    $selisih_upah = $upah_bordir - $upah_sebelumnya;

                    if ($selisih_upah != 0) {
                        updateHutangUpah($id_bordir, 'bordir', $selisih_upah);
                    }
                } else {
                    // Data baru
                    if (!catatHutangUpah($id_bordir, 'bordir', $tanggal_hasil_bordir, $upah_bordir)) {
                        throw new Exception("Gagal mencatat hutang upah bordir");
                    }
                }

                $conn->commit();
                $conn->autocommit(TRUE);

                $success_msg = $existing ?
                    "Data hasil bordir berhasil diupdate. Upah bordir: " . formatRupiah($upah_bordir) :
                    "Data hasil bordir berhasil disimpan. Upah bordir: " . formatRupiah($upah_bordir);

                $_SESSION['success'] = $success_msg;
                header("Location: list.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $conn->autocommit(TRUE);
                $error_modal = $e->getMessage();
            }
        }
    }

    /**
     * 3. SIMPAN TANGGAL KIRIM JAHIT (Modal Pertama untuk Penjahit)
     */
    if (isset($_POST['simpan_tanggal_kirim'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
        $id_penjahit = isset($_POST['id_penjahit']) && !empty($_POST['id_penjahit']) ? intval($_POST['id_penjahit']) : null;
        $tanggal_kirim_jahit = isset($_POST['tanggal_kirim_jahit']) && !empty($_POST['tanggal_kirim_jahit'])
            ? $conn->real_escape_string($_POST['tanggal_kirim_jahit'])
            : null;

        // Validasi
        $error_modal = null;

        if (empty($id_penjahit)) {
            $error_modal = "Penjahit harus dipilih";
        } elseif (empty($tanggal_kirim_jahit)) {
            $error_modal = "Tanggal kirim jahit harus diisi";
        }

        if (!$error_modal) {
            try {
                // Update hanya tanggal kirim dan penjahit
                $id_penjahit_sql = $id_penjahit ? $id_penjahit : "NULL";
                $tanggal_kirim_sql = $tanggal_kirim_jahit ? "'$tanggal_kirim_jahit'" : "NULL";

                $sql_update = "UPDATE hasil_potong_fix 
                          SET id_penjahit = $id_penjahit_sql, 
                              tanggal_kirim_jahit = $tanggal_kirim_sql,
                              status_potong = 'penjahitan'
                          WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                if ($conn->query($sql_update)) {
                    $_SESSION['success'] = "Data tanggal kirim jahit berhasil disimpan. Status berubah menjadi 'Penjahitan'.";
                    header("Location: list.php");
                    exit();
                } else {
                    throw new Exception("Gagal menyimpan data tanggal kirim: " . $conn->error);
                }
            } catch (Exception $e) {
                $error_modal = $e->getMessage();
            }
        }
    }

    /**
     * 4. SIMPAN HASIL JAHIT (Modal Kedua)
     */
    if (isset($_POST['simpan_hasil_jahit'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
        $tanggal_hasil_jahit = isset($_POST['tanggal_hasil_jahit']) && !empty($_POST['tanggal_hasil_jahit'])
            ? $conn->real_escape_string($_POST['tanggal_hasil_jahit'])
            : null;
        $total_hasil_jahit = isset($_POST['total_hasil_jahit']) ? intval($_POST['total_hasil_jahit']) : 0;

        // Ambil input upah manual
        $upah_per_potongan = floatval($_POST['upah_per_potongan_penjahit']);
        $total_upah = floatval($_POST['total_upah_penjahit']);

        // Validasi input upah
        if ($total_upah <= 0) {
            $error_modal = "Total upah harus lebih dari 0!";
        }

        // Validasi perhitungan
        if ($upah_per_potongan > 0 && $total_hasil_jahit > 0) {
            $calculated_upah = $upah_per_potongan * $total_hasil_jahit;
            if (abs($calculated_upah - $total_upah) > 1) { // Toleransi 1 rupiah
                $error_modal = "Perhitungan upah tidak sesuai! Total seharusnya: Rp " . number_format($calculated_upah);
            }
        }

        // Ambil data produksi
        $produksi_data = query("SELECT 
        hp.id_produk, 
        hp.total_hasil, 
        hp.id_penjahit, 
        hp.tanggal_kirim_jahit,
        hp.seri,
        p.tipe_produk,
        hp.tanggal_hasil_jahit as existing_tanggal,
        hp.total_hasil_jahit as existing_jumlah
    FROM hasil_potong_fix hp
    JOIN produk p ON hp.id_produk = p.id_produk
    WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0];

        $id_produk = $produksi_data['id_produk'];
        $total_hasil_potong = $produksi_data['total_hasil'];
        $id_penjahit = $produksi_data['id_penjahit'];
        $tanggal_kirim_jahit = $produksi_data['tanggal_kirim_jahit'];
        $seri = $produksi_data['seri'];
        $tipe_produk = $produksi_data['tipe_produk'];
        $existing_tanggal = $produksi_data['existing_tanggal'];
        $existing_jumlah = $produksi_data['existing_jumlah'];

        // Cek apakah data sudah ada (existing)
        $existing = !empty($existing_tanggal) && !empty($existing_jumlah);

        // Inisialisasi variabel change_log
        $change_log = '';
        if ($existing) {
            // Log perubahan
            $changes = [];
            if ($existing_tanggal != $tanggal_hasil_jahit) {
                $changes[] = "Tanggal: " . $existing_tanggal . " → " . $tanggal_hasil_jahit;
            }
            if ($existing_jumlah != $total_hasil_jahit) {
                $changes[] = "Jumlah: " . $existing_jumlah . " → " . $total_hasil_jahit . " Pcs";
            }

            if (!empty($changes)) {
                $change_log = "Update hasil jahit: " . implode(", ", $changes);
            }
        }

        // Validasi
        $error_modal = null;

        if (empty($tanggal_hasil_jahit)) {
            $error_modal = "Tanggal hasil jahit harus diisi";
        } elseif ($total_hasil_jahit <= 0) {
            $error_modal = "Total hasil jahit harus lebih dari 0";
        } elseif ($total_hasil_jahit > $total_hasil_potong) {
            $error_modal = "Total hasil jahit tidak boleh melebihi total hasil potong ($total_hasil_potong Pcs)";
        } elseif (empty($id_penjahit) || empty($tanggal_kirim_jahit)) {
            $error_modal = "Data penjahit atau tanggal kirim belum diinput. Silakan input tanggal kirim terlebih dahulu.";
        }

        if (!$error_modal) {
            $conn->autocommit(FALSE);
            try {
                // HITUNG UPAH PENJAHIT
                $upah_per_potongan_manual = floatval($_POST['upah_per_potongan_penjahit']);
                $upah_penjahit = $total_hasil_jahit * $upah_per_potongan_manual;

                // 1. Update data hasil jahit
                $sql_update = "UPDATE hasil_potong_fix 
                    SET tanggal_hasil_jahit = '$tanggal_hasil_jahit', 
                        tarif_upah = $upah_per_potongan_manual,
                        total_hasil_jahit = $total_hasil_jahit,
                        status_potong = 'selesai'";

                // Jika ada perubahan, tambahkan log
                if ($existing && !empty($change_log)) {
                    $sql_update .= ", keterangan = CONCAT(COALESCE(keterangan, ''), ' | $change_log')";
                }

                $sql_update .= " WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                if (!$conn->query($sql_update)) {
                    throw new Exception("Gagal update data hasil jahit: " . $conn->error);
                }

                // 2. LOGIKA BERBEDA BERDASARKAN TIPE PRODUK
                if ($tipe_produk == 'mukena') {
                    // MUKENA: Update stok
                    if ($existing) {
                        // Hitung selisih
                        $selisih = $total_hasil_jahit - $existing_jumlah;
                        if ($selisih != 0) {
                            $sql_update_stok = "UPDATE produk 
                               SET stok = stok + $selisih 
                               WHERE id_produk = $id_produk";

                            if (!$conn->query($sql_update_stok)) {
                                throw new Exception("Gagal update stok produk: " . $conn->error);
                            }
                            $pesan_stok = $selisih > 0 ?
                                "Stok produk bertambah +$selisih" :
                                "Stok produk berkurang $selisih";
                        } else {
                            $pesan_stok = "Stok produk tidak berubah";
                        }
                    } else {
                        // Data baru
                        $sql_update_stok = "UPDATE produk 
                           SET stok = stok + $total_hasil_jahit 
                           WHERE id_produk = $id_produk";

                        if (!$conn->query($sql_update_stok)) {
                            throw new Exception("Gagal update stok produk: " . $conn->error);
                        }
                        $pesan_stok = "Stok produk bertambah +$total_hasil_jahit";
                    }
                } else {
                    // PRODUK TIPE KOKO: masuk ke tabel koko (belum selesai produksi)

                    // Cek apakah data koko sudah ada untuk produk ini
                    $koko_exist = isKokoExist($id_produk);

                    if ($koko_exist) {
                        // UPDATE stok koko
                        $sql_update_koko = "UPDATE koko 
                       SET stok = stok + $total_hasil_jahit,
                           updated_at = NOW()
                       WHERE id_produk = $id_produk";

                        if (!$conn->query($sql_update_koko)) {
                            throw new Exception("Gagal update stok koko: " . $conn->error);
                        }

                        $pesan_stok = "Stok koko berhasil ditambah +" . $total_hasil_jahit;
                    } else {
                        // INSERT data koko baru
                        // Ambil informasi produk dari tabel produk
                        $sql_produk = "SELECT nama_produk, harga_jual FROM produk WHERE id_produk = $id_produk";
                        $result_produk = $conn->query($sql_produk);

                        if ($result_produk && $result_produk->num_rows > 0) {
                            $row_produk = $result_produk->fetch_assoc();
                            $nama_koko = $row_produk['nama_produk'];
                            $harga_jual = $row_produk['harga_jual'] ?: 0;
                        } else {
                            $nama_koko = "Produk Koko";
                            $harga_jual = 0;
                        }

                        // INSERT dengan semua kolom yang diperlukan
                        $sql_insert_koko = "INSERT INTO koko (id_produk, nama_koko, harga_jual, stok, created_at, updated_at)
                       VALUES ($id_produk, '$nama_koko', $harga_jual, $total_hasil_jahit, NOW(), NOW())";

                        if (!$conn->query($sql_insert_koko)) {
                            throw new Exception("Gagal menambah data koko baru: " . $conn->error);
                        }

                        $pesan_stok = "Data koko baru berhasil dibuat (stok +" . $total_hasil_jahit . ")";
                    }
                }

                // 3. Catat/Update hutang upah penjahit TANPA PERIODE
                if ($existing) {
                    // Update hutang yang sudah ada
                    $upah_sebelumnya = $existing_jumlah * getTarifUpah('penjahitan', $existing_tanggal);
                    $selisih_upah = $upah_penjahit - $upah_sebelumnya;

                    if ($selisih_upah != 0) {
                        updateHutangUpah($id_penjahit, 'penjahit', $selisih_upah);
                    }
                } else {
                    // Data baru
                    if (!catatHutangUpah($id_penjahit, 'penjahit', $tanggal_hasil_jahit, $upah_penjahit)) {
                        throw new Exception("Gagal mencatat hutang upah penjahit");
                    }
                }

                $conn->commit();
                $conn->autocommit(TRUE);

                $success_msg = $existing ?
                    "Data hasil jahit berhasil diupdate. $pesan_stok. Upah penjahit: " . formatRupiah($upah_penjahit) :
                    "Data hasil jahit berhasil disimpan. $pesan_stok. Upah penjahit: " . formatRupiah($upah_penjahit);

                $_SESSION['success'] = $success_msg;
                header("Location: list.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $conn->autocommit(TRUE);
                $error_modal = $e->getMessage();
            }
        }
    }

    /**
     * 5. BATAL HASIL JAHIT (Batal Penjahitan)
     */
    if (isset($_POST['batal_penjahitan'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);

        // Ambil data sebelum dibatalkan
        $produksi_data = query("SELECT 
        hp.id_produk, 
        hp.total_hasil_jahit, 
        hp.id_penjahit, 
        hp.tanggal_hasil_jahit, 
        hp.tanggal_kirim_jahit, 
        hp.total_hasil,
        hp.seri,
        p.tipe_produk
    FROM hasil_potong_fix hp
    JOIN produk p ON hp.id_produk = p.id_produk
    WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0];

        $id_produk = $produksi_data['id_produk'];
        $total_hasil_jahit = $produksi_data['total_hasil_jahit'];
        $id_penjahit = $produksi_data['id_penjahit'];
        $tanggal_hasil_jahit = $produksi_data['tanggal_hasil_jahit'];
        $tanggal_kirim_jahit = $produksi_data['tanggal_kirim_jahit'];
        $total_hasil_potong = $produksi_data['total_hasil'];
        $seri = $produksi_data['seri'];
        $tipe_produk = $produksi_data['tipe_produk'];

        // Validasi: pastikan ada total_hasil_jahit untuk dibatalkan
        if (empty($total_hasil_jahit) || $total_hasil_jahit <= 0) {
            $error_modal = "Tidak ada data hasil jahit yang bisa dibatalkan.";
        } else {
            // Hitung upah yang akan dihapus
            $upah_dihapus = 0;
            if ($total_hasil_jahit > 0 && $id_penjahit > 0 && !empty($tanggal_hasil_jahit)) {
                // Ambil tarif yang sebenarnya digunakan dari database
                $produksi_data = query("SELECT tarif_upah FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix")[0];

                // Gunakan tarif yang disimpan, jika tidak ada gunakan tarif standar
                $tarif_penjahit = !empty($produksi_data['tarif_upah'])
                    ? floatval($produksi_data['tarif_upah'])
                    : getTarifUpah('penjahitan', $tanggal_hasil_jahit);

                $upah_dihapus = $total_hasil_jahit * $tarif_penjahit;
            }

            $conn->autocommit(FALSE);
            try {
                // 1. Reset HANYA data hasil jahit
                $sql_batal = "UPDATE hasil_potong_fix 
             SET tanggal_hasil_jahit = NULL, 
                 total_hasil_jahit = NULL,
                 status_potong = 'penjahitan'
             WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                if (!$conn->query($sql_batal)) {
                    throw new Exception("Gagal membatalkan data hasil jahit, stok pada finishing koko kurang dari hasil jahit ini.");
                }

                // 2. LOGIKA BERBEDA BERDASARKAN TIPE PRODUK
                $pesan_stok = "";
                if ($tipe_produk == 'mukena' && $total_hasil_jahit > 0) {
                    // MUKENA: Kurangi stok dari tabel produk

                    // Cek stok produk saat ini
                    $produk_data = query("SELECT stok, nama_produk FROM produk WHERE id_produk = $id_produk")[0];
                    $stok_sekarang = $produk_data['stok'];
                    $nama_produk = htmlspecialchars($produk_data['nama_produk']);

                    if ($stok_sekarang >= $total_hasil_jahit) {
                        // Stok cukup, kurangi normal
                        $sql_kurangi_stok = "UPDATE produk 
                            SET stok = stok - $total_hasil_jahit 
                            WHERE id_produk = $id_produk";

                        if (!$conn->query($sql_kurangi_stok)) {
                            throw new Exception("Gagal mengurangi stok produk.");
                        }
                        $pesan_stok = "Stok produk dikurangi $total_hasil_jahit pcs";
                    } else {
                        // Stok tidak cukup, set ke 0
                        $selisih = $total_hasil_jahit - $stok_sekarang;

                        $sql_kurangi_stok = "UPDATE produk 
                            SET stok = 0 
                            WHERE id_produk = $id_produk";

                        if (!$conn->query($sql_kurangi_stok)) {
                            throw new Exception("Gagal mengurangi stok produk.");
                        }

                        // Simpan pesan warning di session
                        $warning_msg = "Stok produk '$nama_produk' kurang. ";
                        $warning_msg .= "Hanya berhasil mengurangi $stok_sekarang dari $total_hasil_jahit pcs. ";
                        $warning_msg .= "Selisih $selisih pcs tidak dapat dikurangi.";

                        $_SESSION['warning'] = $warning_msg;

                        $pesan_stok = "Stok produk direset ke 0 (stok tidak mencukupi).";
                    }
                } elseif ($tipe_produk == 'koko' && $total_hasil_jahit > 0) {
                    // KOKO: Kurangi stok dari tabel koko

                    // Cek stok koko saat ini
                    $koko_data = query("SELECT stok, nama_koko FROM koko WHERE id_produk = $id_produk LIMIT 1");

                    if (!empty($koko_data)) {
                        $stok_sekarang = $koko_data[0]['stok'];
                        $nama_koko = htmlspecialchars($koko_data[0]['nama_koko']);

                        if ($stok_sekarang >= $total_hasil_jahit) {
                            // Stok cukup, kurangi normal
                            $sql_kurangi_stok = "UPDATE koko 
                                SET stok = stok - $total_hasil_jahit,
                                    updated_at = NOW()
                                WHERE id_produk = $id_produk";

                            if (!$conn->query($sql_kurangi_stok)) {
                                throw new Exception("Gagal mengurangi stok koko.");
                            }
                            $pesan_stok = "Stok koko dikurangi $total_hasil_jahit roll";
                        } else {
                            // Stok tidak cukup, set ke 0
                            $selisih = $total_hasil_jahit - $stok_sekarang;

                            $sql_kurangi_stok = "UPDATE koko 
                                SET stok = 0,
                                    updated_at = NOW()
                                WHERE id_produk = $id_produk";

                            if (!$conn->query($sql_kurangi_stok)) {
                                throw new Exception("Gagal update stok koko.");
                            }

                            // Simpan pesan warning di session
                            $warning_msg = "Stok koko '$nama_koko' kurang. ";
                            $warning_msg .= "Hanya berhasil mengurangi $stok_sekarang dari $total_hasil_jahit roll. ";
                            $warning_msg .= "Selisih $selisih roll tidak dapat dikurangi.";

                            $_SESSION['warning'] = $warning_msg;

                            $pesan_stok = "Stok koko direset ke 0 (stok tidak mencukupi).";
                        }
                    } else {
                        // Data koko tidak ditemukan
                        $pesan_stok = "Data koko tidak ditemukan. Tidak ada stok yang dapat dikurangi.";
                    }
                }

                // 3. Hapus/Update hutang upah penjahit (hanya jika ada upah)
                if ($upah_dihapus > 0 && $id_penjahit > 0) {
                    if (!kurangiHutangUpahPenjahit($id_penjahit, $upah_dihapus)) {
                        throw new Exception("Gagal mengurangi hutang upah penjahit.");
                    }
                }

                $conn->commit();
                $conn->autocommit(TRUE);

                $pesan_success = "Data hasil jahit berhasil dibatalkan";
                if ($total_hasil_jahit > 0 && !empty($pesan_stok)) {
                    $pesan_success .= " dan " . strtolower($pesan_stok);
                }
                if ($upah_dihapus > 0) {
                    $pesan_success .= ". Upah penjahit dikurangi: " . formatRupiah($upah_dihapus);
                }
                $pesan_success .= ". Data penjahit dan tanggal kirim tetap tersimpan.";

                $_SESSION['success'] = $pesan_success;
                header("Location: list.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $conn->autocommit(TRUE);
                // Pesan error sederhana
                $error_modal = "Gagal membatalkan data hasil jahit, stok koko kurang dari hasil jahit ini.";
            }
        }
    }

    /**
     * 6. HAPUS PENJAHIT DAN TANGGAL KIRIM
     */
    if (isset($_POST['hapus_penjahit'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);

        // Ambil data sebelum dihapus
        $produksi_data = query("SELECT 
        hp.id_produk, 
        hp.total_hasil_jahit, 
        hp.id_penjahit, 
        hp.tanggal_hasil_jahit, 
        hp.tanggal_kirim_jahit, 
        hp.total_hasil,
        hp.seri,
        p.tipe_produk
    FROM hasil_potong_fix hp
    JOIN produk p ON hp.id_produk = p.id_produk
    WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0];

        $id_produk = $produksi_data['id_produk'];
        $total_hasil_jahit = $produksi_data['total_hasil_jahit'];
        $id_penjahit = $produksi_data['id_penjahit'];
        $tanggal_hasil_jahit = $produksi_data['tanggal_hasil_jahit'];
        $seri = $produksi_data['seri'];
        $tipe_produk = $produksi_data['tipe_produk'];

        // Hitung upah yang akan dihapus
        $upah_dihapus = 0;
        if ($total_hasil_jahit > 0 && $id_penjahit > 0 && !empty($tanggal_hasil_jahit)) {
            $tarif_penjahit = getTarifUpah('penjahitan', $tanggal_hasil_jahit);
            $upah_dihapus = $total_hasil_jahit * $tarif_penjahit;
        }

        $conn->autocommit(FALSE);
        try {
            // 1. Reset SEMUA data penjahitan
            $sql_hapus = "UPDATE hasil_potong_fix 
             SET id_penjahit = NULL, 
                 tanggal_kirim_jahit = NULL, 
                 tanggal_hasil_jahit = NULL, 
                 total_hasil_jahit = NULL,
                 status_potong = 'diproses'
             WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

            if (!$conn->query($sql_hapus)) {
                throw new Exception("Gagal menghapus data penjahit.");
            }

            // 2. LOGIKA BERBEDA BERDASARKAN TIPE PRODUK
            $pesan_stok = "";
            if ($tipe_produk == 'mukena' && $total_hasil_jahit > 0) {
                // MUKENA: Kurangi stok produk

                // Cek stok produk saat ini
                $produk_data = query("SELECT stok, nama_produk FROM produk WHERE id_produk = $id_produk")[0];
                $stok_sekarang = $produk_data['stok'];
                $nama_produk = htmlspecialchars($produk_data['nama_produk']);

                if ($stok_sekarang >= $total_hasil_jahit) {
                    // Stok cukup, kurangi normal
                    $sql_kurangi_stok = "UPDATE produk 
                            SET stok = stok - $total_hasil_jahit 
                            WHERE id_produk = $id_produk";

                    if (!$conn->query($sql_kurangi_stok)) {
                        throw new Exception("Gagal mengurangi stok produk.");
                    }
                    $pesan_stok = "stok produk dikurangi $total_hasil_jahit pcs";
                } else {
                    // Stok tidak cukup, set ke 0
                    $selisih = $total_hasil_jahit - $stok_sekarang;

                    $sql_kurangi_stok = "UPDATE produk 
                            SET stok = 0 
                            WHERE id_produk = $id_produk";

                    if (!$conn->query($sql_kurangi_stok)) {
                        throw new Exception("Gagal mengurangi stok produk.");
                    }

                    // Simpan pesan warning di session
                    $warning_msg = "Stok produk '$nama_produk' kurang saat penghapusan penjahit. ";
                    $warning_msg .= "Hanya berhasil mengurangi $stok_sekarang dari $total_hasil_jahit pcs.";

                    $_SESSION['warning'] = $warning_msg;

                    $pesan_stok = "stok produk direset ke 0 (stok tidak mencukupi)";
                }
            } elseif ($tipe_produk == 'koko' && $total_hasil_jahit > 0) {
                // KOKO: Kurangi stok koko

                // Cek apakah data koko ada
                $koko_data = query("SELECT stok, nama_koko FROM koko WHERE id_produk = $id_produk LIMIT 1");

                if (!empty($koko_data)) {
                    $stok_sekarang = $koko_data[0]['stok'];
                    $nama_koko = htmlspecialchars($koko_data[0]['nama_koko']);

                    if ($stok_sekarang >= $total_hasil_jahit) {
                        // Stok cukup, kurangi normal
                        $sql_kurangi_stok = "UPDATE koko 
                                SET stok = stok - $total_hasil_jahit,
                                    updated_at = NOW()
                                WHERE id_produk = $id_produk";

                        if (!$conn->query($sql_kurangi_stok)) {
                            throw new Exception("Gagal mengurangi stok koko.");
                        }
                        $pesan_stok = "stok koko dikurangi $total_hasil_jahit roll";

                        // Cek jika stok menjadi 0 atau kurang, hapus record
                        $stok_baru = $stok_sekarang - $total_hasil_jahit;
                        if ($stok_baru <= 0) {
                            $sql_hapus_koko = "DELETE FROM koko WHERE id_produk = $id_produk";
                            if ($conn->query($sql_hapus_koko)) {
                                $pesan_stok = "data koko dihapus karena stok habis";
                            }
                        }
                    } else {
                        // Stok tidak cukup, hapus record koko
                        $selisih = $total_hasil_jahit - $stok_sekarang;

                        $sql_hapus_koko = "DELETE FROM koko WHERE id_produk = $id_produk";
                        if (!$conn->query($sql_hapus_koko)) {
                            throw new Exception("Gagal menghapus data koko.");
                        }

                        // Simpan pesan warning di session
                        $warning_msg = "Stok koko '$nama_koko' kurang saat penghapusan penjahit. ";
                        $warning_msg .= "Hanya berhasil mengurangi $stok_sekarang dari $total_hasil_jahit roll. ";
                        $warning_msg .= "Data koko dihapus karena stok tidak mencukupi.";

                        $_SESSION['warning'] = $warning_msg;

                        $pesan_stok = "data koko dihapus (stok tidak mencukupi)";
                    }
                } else {
                    // Data koko tidak ditemukan
                    $pesan_stok = "data koko tidak ditemukan";
                }
            }

            // 3. Hapus/Update hutang upah penjahit (hanya jika ada upah)
            if ($upah_dihapus > 0 && $id_penjahit > 0) {
                if (!kurangiHutangUpahPenjahit($id_penjahit, $upah_dihapus)) {
                    throw new Exception("Gagal mengurangi hutang upah penjahit.");
                }
            }

            $conn->commit();
            $conn->autocommit(TRUE);

            // Pesan sukses berdasarkan kondisi
            $pesan_success = "Data penjahit berhasil dihapus";
            if ($total_hasil_jahit > 0 && !empty($pesan_stok)) {
                $pesan_success .= " dan " . $pesan_stok;
            }
            if ($upah_dihapus > 0) {
                $pesan_success .= ". Upah penjahit dikurangi: " . formatRupiah($upah_dihapus);
            }
            $pesan_success .= ". Status kembali ke 'Potong'.";

            $_SESSION['success'] = $pesan_success;
            header("Location: list.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(TRUE);
            // Pesan error sederhana
            $error_modal = "Gagal menghapus data penjahit. Silakan coba lagi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Produksi</title>
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
</head>

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
            <div class="me-auto pc-mob-drp">
                <ul class="list-unstyled">
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
        </div>
    </header>
    <!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <div class="row">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Master Data Produksi</h2>
                    <div class="btn-group">
                        <div>
                            <a href="new.php" class="btn btn-success">
                                <i class="ti ti-circle-plus"></i> Tambah Produksi
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Filter Form -->
                    <div class="col-md-8">



                        <form method="GET" class="mb-3">
                            <!-- Baris 1: Filter Dropdown -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">Filter Produk</label>
                                    <select name="id_produk" class="form-select">
                                        <option value="0">Semua Produk</option>
                                        <?php foreach ($produk as $p): ?>
                                            <option value="<?= $p['id_produk'] ?>" <?= ($id_produk == $p['id_produk']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['nama_produk']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Filter Pemotong</label>
                                    <select name="id_pemotong" class="form-select">
                                        <option value="0">Semua Pemotong</option>
                                        <?php foreach ($pemotong as $pm): ?>
                                            <option value="<?= $pm['id_pemotong'] ?>" <?= (isset($_GET['id_pemotong']) && $_GET['id_pemotong'] == $pm['id_pemotong']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pm['nama_pemotong']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Filter Bordir</label>
                                    <select name="id_bordir" class="form-select">
                                        <option value="0">Semua Bordir</option>
                                        <option value="-1" <?= (isset($_GET['id_bordir']) && $_GET['id_bordir'] == '-1') ? 'selected' : '' ?>>Belum Ada Bordir</option>
                                        <?php foreach ($bordir as $br): ?>
                                            <option value="<?= $br['id_bordir'] ?>" <?= (isset($_GET['id_bordir']) && $_GET['id_bordir'] == $br['id_bordir']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($br['nama_bordir']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Filter Penjahit</label>
                                    <select name="id_penjahit" class="form-select">
                                        <option value="0">Semua Penjahit</option>
                                        <option value="-1" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == '-1') ? 'selected' : '' ?>>Belum Ada Penjahit</option>
                                        <?php foreach ($penjahit as $pj): ?>
                                            <option value="<?= $pj['id_penjahit'] ?>" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == $pj['id_penjahit']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pj['nama_penjahit']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Filter Status</label>
                                    <select name="status" class="form-select">
                                        <option value="all" <?= ($status == 'all') ? 'selected' : '' ?>>Semua Status</option>
                                        <option value="diproses" <?= ($status == 'diproses') ? 'selected' : '' ?>>Potong</option>
                                        <option value="bordir" <?= ($status == 'bordir') ? 'selected' : '' ?>>Bordir</option>
                                        <option value="penjahitan" <?= ($status == 'penjahitan') ? 'selected' : '' ?>>Penjahitan</option>
                                        <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Baris 2: Tanggal dan Tombol -->
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Mulai</label>
                                    <input type="date" name="start_date" class="form-control"
                                        value="<?= htmlspecialchars($start_date ?: $start_date_default) ?>">
                                    <small class="text-muted">Bulan/Tanggal/Tahun</small>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Tanggal Akhir</label>
                                    <input type="date" name="end_date" class="form-control"
                                        value="<?= htmlspecialchars($end_date ?: $end_date_default) ?>">
                                    <small class="text-muted">Bulan/Tanggal/Tahun</small>
                                </div>

                                <div class="col-md-6">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-filter"></i> Filter
                                        </button>

                                        <?php
                                        // Cek apakah ada filter yang aktif
                                        $is_filtered = $id_produk > 0 || $id_pemotong > 0 || $id_penjahit != 0 ||
                                            $id_bordir != 0 || $status != 'all' || !empty($start_date) || !empty($end_date);
                                        ?>

                                        <?php if ($is_filtered): ?>
                                            <a href="list.php" class="btn btn-secondary">
                                                <i class="ti ti-rotate"></i> Reset
                                            </a>

                                        <?php endif; ?>

                                        <button type="button" class="btn btn-danger" id="btnPrintPDF">
                                            <i class="ti ti-file-text"></i> Print PDF
                                        </button>

                                    </div>
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
                                                    global $produk, $pemotong, $penjahit, $bordir;

                                                    switch ($key) {
                                                        case 'id_produk':
                                                            if ($value == 0) return null;
                                                            foreach ($produk as $p) {
                                                                if ($p['id_produk'] == $value) {
                                                                    return '<span class="badge bg-primary">Produk: ' . htmlspecialchars($p['nama_produk']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'id_pemotong':
                                                            if ($value == 0) return null;
                                                            foreach ($pemotong as $pm) {
                                                                if ($pm['id_pemotong'] == $value) {
                                                                    return '<span class="badge bg-warning text-dark">Pemotong: ' . htmlspecialchars($pm['nama_pemotong']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'id_bordir':
                                                            if ($value == 0) return null;
                                                            if ($value == '-1') {
                                                                return '<span class="badge bg-secondary">Bordir: Belum Ada</span>';
                                                            }
                                                            foreach ($bordir as $br) {
                                                                if ($br['id_bordir'] == $value) {
                                                                    return '<span class="badge bg-info text-dark">Bordir: ' . htmlspecialchars($br['nama_bordir']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'id_penjahit':
                                                            if ($value == 0) return null;
                                                            if ($value == '-1') {
                                                                return '<span class="badge bg-secondary">Penjahit: Belum Ada</span>';
                                                            }
                                                            foreach ($penjahit as $pj) {
                                                                if ($pj['id_penjahit'] == $value) {
                                                                    return '<span class="badge bg-info text-dark">Penjahit: ' . htmlspecialchars($pj['nama_penjahit']) . '</span>';
                                                                }
                                                            }
                                                            break;

                                                        case 'status':
                                                            if ($value == 'all') return null;
                                                            $status_labels = [
                                                                'diproses' => 'Potong',
                                                                'bordir' => 'Bordir',
                                                                'penjahitan' => 'Penjahitan',
                                                                'selesai' => 'Selesai'
                                                            ];
                                                            $status_colors = [
                                                                'diproses' => 'warning',
                                                                'bordir' => 'info',
                                                                'penjahitan' => 'primary',
                                                                'selesai' => 'success'
                                                            ];
                                                            return '<span class="badge bg-' . ($status_colors[$value] ?? 'secondary') .
                                                                '">Status: ' . $status_labels[$value] . '</span>';

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
                                                    'id_produk' => $id_produk,
                                                    'id_pemotong' => $id_pemotong,
                                                    'id_bordir' => $id_bordir,
                                                    'id_penjahit' => $id_penjahit,
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
                                                        <p class="mb-0 small">
                                                            <i class="ti ti-database"></i>
                                                            <strong><?= count($all_data) ?> data</strong> ditemukan dengan filter ini
                                                            (dari total <?= $total_hasil_all ?> Pcs hasil potong)
                                                        </p>
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
                    BAGIAN TOTAL INFORMASI
                    ============================================ -->
                    <div class="col-md-4 mb-4">
                        <div class="total-info">
                            <h5><i class="ti ti-chart-bar"></i> Ringkasan Produksi</h5>

                            <!-- Total Semua Data (Tanpa Filter) -->
                            <div class="total-row">
                                <span class="total-label">Total Semua Hasil Potong:</span>
                                <span class="total-value">
                                    <?= number_format($total_hasil_all) ?> Pcs
                                </span>
                            </div>

                            <div class="total-row">
                                <span class="total-label">Total Semua Hasil Bordir:</span>
                                <span class="total-value">
                                    <?= number_format($total_hasil_bordir_all) ?> Pcs
                                </span>
                            </div>

                            <div class="total-row">
                                <span class="total-label">Total Semua Hasil Jahit:</span>
                                <span class="total-value">
                                    <?= number_format($total_hasil_jahit_all) ?> Pcs
                                </span>
                            </div>

                            <!-- Total Dengan Filter (Jika Ada Filter) -->
                            <?php if ($is_filtered): ?>
                                <div class="total-filtered">
                                    <h6><i class="ti ti-filter"></i> Hasil Setelah Filter:</h6>
                                    <div class="total-row">
                                        <span class="total-label">Total Hasil Potong (Filter):</span>
                                        <span class="total-value text-primary">
                                            <?= number_format($total_hasil_filtered) ?> Pcs
                                        </span>
                                    </div>

                                    <div class="total-row">
                                        <span class="total-label">Total Hasil Bordir (Filter):</span>
                                        <span class="total-value text-primary">
                                            <?= number_format($total_hasil_bordir_filtered) ?> Pcs
                                        </span>
                                    </div>

                                    <div class="total-row">
                                        <span class="total-label">Total Hasil Jahit (Filter):</span>
                                        <span class="total-value text-primary">
                                            <?= number_format($total_hasil_jahit_filtered) ?> Pcs
                                        </span>
                                    </div>
                                </div>
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

                    <?php if (isset($error_modal)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error_modal) ?>
                            <button type="button"
                                class="btn-close"
                                data-bs-dismiss="alert"
                                aria-label="Close"></button>
                        </div>
                    <?php endif; ?>


                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th class="align-middle">Status</th>
                                    <th class="bg-warning text-white align-middle">Seri</th>
                                    <th class="bg-warning text-white align-middle">Pemotong</th>
                                    <th class="bg-warning text-white align-middle">Tgl Potong</th>
                                    <th class="bg-warning text-white align-middle">Produk</th>
                                    <th class="bg-warning text-white align-middle">Hasil Potong</th>
                                    <th class="bg-primary text-white align-middle">Tgl Kirim Bordir</th>
                                    <th class="bg-primary text-white align-middle">Bordir</th>
                                    <th class="bg-primary text-white align-middle">Tgl Bordir</th>
                                    <th class="bg-primary text-white align-middle">Hasil Bordir</th>
                                    <th class="bg-info text-white align-middle">Tgl Kirim Jahit</th>
                                    <th class="bg-info text-white align-middle">Penjahit</th>
                                    <th class="bg-info text-white align-middle">Tgl Jahit</th>
                                    <th class="bg-info text-white align-middle">Hasil Jahit</th>
                                    <th class="align-middle">Sisa</th>
                                    <th class="align-middle">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($all_data)): ?>
                                    <tr>
                                        <td colspan="16" class="text-center">Tidak ada data produksi</td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $no = 1;
                                    $total_hasil_potong = 0;
                                    $total_hasil_bordir = 0;
                                    $total_hasil_jahit = 0;
                                    $total_sisa = 0;
                                    ?>
                                    <?php foreach ($all_data as $data): ?>
                                        <?php
                                        // Hitung total untuk footer
                                        $total_hasil_potong += $data['total_hasil'] ?? 0;
                                        $total_hasil_bordir += $data['total_hasil_bordir'] ?? 0;
                                        $total_hasil_jahit += $data['total_hasil_jahit'] ?? 0;

                                        // Hitung sisa
                                        $totalHasil = $data['total_hasil'] ?? 0;
                                        $totalHasilBordir = $data['total_hasil_bordir'] ?? 0;
                                        $totalHasilJahit = $data['total_hasil_jahit'] ?? 0;
                                        $sisa = $totalHasil - ($totalHasilBordir + $totalHasilJahit);
                                        $total_sisa += $sisa;
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <?php
                                                $status = $data['status']; // ambil status

                                                // Tentukan warna badge
                                                switch ($status) {
                                                    case 'selesai':
                                                        $badge = 'success';
                                                        $label = 'Selesai';
                                                        break;
                                                    case 'diproses':
                                                        $badge = 'warning';
                                                        $label = 'Potong';
                                                        break;
                                                    case 'bordir':
                                                        $badge = 'primary';
                                                        $label = 'Bordir';
                                                        break;
                                                    case 'penjahitan':
                                                        $badge = 'info';
                                                        $label = 'Penjahitan';
                                                        break;
                                                    case '-':
                                                    default:
                                                        $badge = 'secondary';
                                                        $label = '-';
                                                        break;
                                                }
                                                ?>

                                                <span class="badge bg-<?= $badge ?> p-1 fw-normal">
                                                    <?= $label ?>
                                                </span>

                                            </td>
                                            <td class="text-center"><?= htmlspecialchars($data['seri']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($data['pemotong']) ?>
                                            </td>
                                            <td><?= dateIndo($data['tanggal']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($data['produk']) ?>
                                                <br>
                                                <small class="text-muted">
                                                    <span class="badge bg-<?= $data['tipe_produk'] == 'koko' ? 'info' : 'secondary' ?>">
                                                        <?= strtoupper($data['tipe_produk']) ?>
                                                    </span>
                                                </small>
                                            </td>
                                            <td class="text-center"><?= $data['total_hasil'] ?> Pcs</td>
                                            <td>
                                                <?= !empty($data['tanggal_kirim_bordir']) ? dateIndo($data['tanggal_kirim_bordir']) : '-' ?>
                                            </td>
                                            <td class="">
                                                <?php if (!empty($data['bordir'])): ?>
                                                    <?= htmlspecialchars($data['bordir']) ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= !empty($data['tanggal_hasil_bordir']) ? dateIndo($data['tanggal_hasil_bordir']) : '-' ?>
                                            </td>
                                            <td class="text-center">
                                                <?= !empty($data['total_hasil_bordir']) ? $data['total_hasil_bordir'] . ' Pcs' : '-' ?>
                                            </td>
                                            <td>
                                                <?= !empty($data['tanggal_kirim_jahit']) ? dateIndo($data['tanggal_kirim_jahit']) : '-' ?>
                                            </td>
                                            <td class="">
                                                <?php if (!empty($data['penjahit'])): ?>
                                                    <?= htmlspecialchars($data['penjahit']) ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= !empty($data['tanggal_hasil_jahit']) ? dateIndo($data['tanggal_hasil_jahit']) : '-' ?>
                                            </td>
                                            <td class="text-center">
                                                <?= !empty($data['total_hasil_jahit']) ? $data['total_hasil_jahit'] . ' Pcs' : '-' ?>
                                            </td>
                                            <td class="text-center">
                                                <?= $sisa > 0 ? $sisa . ' Pcs' : '-' ?>
                                            </td>

                                            <td class="text-center">
                                                <div class="btn-group gap-1 text-center">
                                                    <!-- Tombol Detail -->
                                                    <a href="detail.php?id=<?= $data['id'] ?>" class="btn btn-sm btn-primary" title="Detail">
                                                        <i class="ti ti-eye"></i>
                                                    </a>

                                                    <?php
                                                    // Logika tombol berdasarkan status
                                                    $has_tanggal_kirim_bordir = !empty($data['tanggal_kirim_bordir']);
                                                    $has_hasil_bordir = !empty($data['total_hasil_bordir']);
                                                    $has_tanggal_kirim_jahit = !empty($data['tanggal_kirim_jahit']);
                                                    $has_hasil_jahit = !empty($data['total_hasil_jahit']);
                                                    $is_diproses = $data['status'] == 'diproses';
                                                    $is_bordir = $data['status'] == 'bordir';
                                                    $is_penjahitan = $data['status'] == 'penjahitan';
                                                    $is_selesai = $data['status'] == 'selesai';
                                                    ?>

                                                    <?php if ($is_diproses): ?>
                                                        <!-- Status: Diproses -->
                                                        <button class="btn btn-sm btn-primary btn-input-tanggal-bordir"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-total-potong="<?= $data['total_hasil'] ?>"
                                                            data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                            title="Input Tanggal Kirim Bordir">
                                                            <i class="ti ti-calendar"></i>
                                                        </button>

                                                        <!-- Tombol Batal Produksi -->
                                                        <button class="btn btn-sm btn-danger btn-batal-produksi"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-pemotong="<?= htmlspecialchars($data['pemotong']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-total-potong="<?= $data['total_hasil'] ?>"
                                                            data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-upah-pemotong="<?= $data['upah_pemotong'] ?>"
                                                            title="Batalkan Produksi">
                                                            <i class="ti ti-trash"></i>
                                                        </button>

                                                    <?php elseif ($is_bordir): ?>
                                                        <!-- Status: Bordir -->

                                                        <?php if ($has_tanggal_kirim_bordir && !$has_hasil_bordir): ?>
                                                            <!-- Ada tanggal kirim bordir tapi belum ada hasil bordir -->
                                                            <button class="btn btn-sm btn-success btn-input-hasil-bordir"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-total-potong="<?= $data['total_hasil'] ?>"
                                                                data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                                data-bordir="<?= $data['id_bordir'] ?>"
                                                                data-nama-bordir="<?= htmlspecialchars($data['bordir']) ?>"
                                                                data-tanggal-kirim="<?= $data['tanggal_kirim_bordir'] ?>"
                                                                title="Input Hasil Bordir">
                                                                <i class="ti ti-check"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($has_tanggal_kirim_bordir): ?>
                                                            <!-- Tombol Edit Tanggal Kirim Bordir -->
                                                            <button class="btn btn-sm btn-info btn-edit-tanggal-bordir"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-total-potong="<?= $data['total_hasil'] ?>"
                                                                data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                                data-bordir="<?= $data['id_bordir'] ?>"
                                                                data-tanggal-kirim="<?= $data['tanggal_kirim_bordir'] ?>"
                                                                title="Edit Tanggal Kirim Bordir">
                                                                <i class="ti ti-edit"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($has_hasil_bordir): ?>
                                                            <!-- Tombol Batal Hasil Bordir -->
                                                            <button class="btn btn-sm btn-outline-warning btn-batal-hasil-bordir"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-bordir="<?= htmlspecialchars($data['bordir']) ?>"
                                                                data-hasil-bordir="<?= $data['total_hasil_bordir'] ?>"
                                                                title="Batal Hasil Bordir">
                                                                <i class="ti ti-eraser"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <!-- Tombol Hapus Bordir -->
                                                        <button class="btn btn-sm btn-outline-danger btn-hapus-bordir"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-bordir="<?= htmlspecialchars($data['bordir']) ?>"
                                                            title="Hapus Bordir">
                                                            <i class="ti ti-user-off"></i>
                                                        </button>

                                                    <?php elseif ($is_penjahitan || $is_selesai): ?>
                                                        <!-- Status: Penjahitan atau Selesai -->

                                                        <?php if ($has_tanggal_kirim_jahit && !$has_hasil_jahit): ?>
                                                            <!-- Ada tanggal kirim jahit tapi belum ada hasil jahit -->
                                                            <button class="btn btn-sm btn-success btn-input-hasil-penjahitan"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-total-potong="<?= $data['total_hasil'] ?>"
                                                                data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                                data-penjahit="<?= $data['id_penjahit'] ?>"
                                                                data-nama-penjahit="<?= htmlspecialchars($data['penjahit']) ?>"
                                                                data-tanggal-kirim="<?= $data['tanggal_kirim_jahit'] ?>"
                                                                title="Input Hasil Jahit">
                                                                <i class="ti ti-check"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($has_tanggal_kirim_jahit): ?>
                                                            <!-- Tombol Edit Tanggal Kirim Jahit -->
                                                            <button class="btn btn-sm btn-info btn-edit-tanggal-penjahitan"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-total-potong="<?= $data['total_hasil'] ?>"
                                                                data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                                data-penjahit="<?= $data['id_penjahit'] ?>"
                                                                data-tanggal-kirim="<?= $data['tanggal_kirim_jahit'] ?>"
                                                                title="Edit Tanggal Kirim Jahit">
                                                                <i class="ti ti-edit"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($has_hasil_jahit): ?>
                                                            <!-- Tombol Batal Hasil Jahit -->
                                                            <button class="btn btn-sm btn-outline-warning btn-batal-hasil-jahit"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-penjahit="<?= htmlspecialchars($data['penjahit']) ?>"
                                                                data-hasil-jahit="<?= $data['total_hasil_jahit'] ?>"
                                                                data-tipe-produk="<?= $data['tipe_produk'] ?>"
                                                                title="Batal Hasil Jahit">
                                                                <i class="ti ti-eraser"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <!-- Tombol Hapus Penjahit -->
                                                        <button class="btn btn-sm btn-outline-danger btn-hapus-penjahit"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-penjahit="<?= htmlspecialchars($data['penjahit']) ?>"
                                                            title="Hapus Penjahit">
                                                            <i class="ti ti-user-off"></i>
                                                        </button>

                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>

                            <!-- TABLE FOOTER UNTUK TOTAL -->
                            <?php if (!empty($all_data)): ?>
                                <tfoot class="table-light">
                                    <tr class="text-center fw-bold">
                                        <td colspan="5" class="text-end">TOTAL:</td>
                                        <td><?= $total_hasil_potong ?> Pcs</td>
                                        <td colspan="4"></td>
                                        <td><?= $total_hasil_bordir ?> Pcs</td>
                                        <td colspan="3"></td>
                                        <td><?= $total_hasil_jahit ?> Pcs</td>
                                        <td><?= $total_sisa ?> Pcs</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Input Tanggal Kirim Bordir -->
    <div class="modal fade" id="modalTanggalBordir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitleTanggalBordir">Input Tanggal Kirim Bordir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formTanggalBordir">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="modal_bordir_id_hasil_potong">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Produk</label>
                                <input type="text" class="form-control" id="modal_bordir_produk" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Seri</label>
                                <input type="text" class="form-control" id="modal_bordir_seri" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_bordir_total_potong" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bordir</label>
                            <select name="id_bordir" class="form-control" id="modal_bordir_bordir" required>
                                <option value="">-- Pilih Bordir --</option>
                                <?php foreach ($bordir as $b): ?>
                                    <option value="<?= $b['id_bordir'] ?>">
                                        <?= htmlspecialchars($b['nama_bordir']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pilih bordir yang akan mengerjakan</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Kirim Bordir <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_kirim_bordir" class="form-control"
                                id="modal_bordir_tanggal_kirim" required value="<?= date('Y-m-d') ?>">
                            <small class="text-muted">Tanggal ketika bahan dikirim ke bordir</small>
                        </div>

                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i> Data hasil bordir dapat diinput nanti setelah bordir selesai.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_tanggal_kirim_bordir" class="btn btn-primary">Simpan Tanggal Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Input Hasil Bordir -->
    <div class="modal fade" id="modalHasilBordir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitleHasilBordir">Input Hasil Bordir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHasilBordir">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="modal_hasil_bordir_id">
                        <input type="hidden" name="total_upah_bordir" id="total_upah_bordir_hidden">
                        <input type="hidden" name="upah_per_potongan_bordir" id="upah_per_potongan_bordir_hidden">

                        <!-- Alert jika sudah ada data -->
                        <div class="alert alert-info d-none" id="modalBordirExistAlert">
                            <i class="ti ti-info-circle"></i>
                            <strong>Perhatian:</strong> Data hasil bordir sudah ada sebelumnya.
                            <div id="modalBordirExistDetail"></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Produk</label>
                                <input type="text" class="form-control" id="modal_hasil_bordir_produk" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Seri</label>
                                <input type="text" class="form-control" id="modal_hasil_bordir_seri" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_hasil_bordir_total_potong" readonly>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Bordir</label>
                                <input type="text" class="form-control" id="modal_hasil_bordir_nama_bordir" readonly>
                            </div>

                            <div hidden class="col-md-6">
                                <label class="form-label">Tanggal Kirim</label>
                                <input type="text" class="form-control" id="modal_hasil_bordir_tanggal_kirim" readonly>
                            </div>
                        </div>

                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Total Hasil Bordir (Pcs) <span class="text-danger">*</span></label>
                                <input type="number" name="total_hasil_bordir" class="form-control"
                                    min="1" max="" id="modal_hasil_bordir_total" required>
                                <small class="text-muted">Maksimal: <span id="modal_hasil_bordir_max">0</span> Pcs</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Hasil Bordir <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_hasil_bordir" class="form-control"
                                    id="modal_hasil_bordir_tanggal" required>
                                <small class="text-muted">bulan/tanggal/tahun</small>
                            </div>
                        </div>

                        <!-- Input Upah Bordir -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Upah Bordir</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Upah per Potongan</label>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" name="upah_per_potongan_bordir_manual"
                                                class="form-control" id="upah_per_potongan_bordir_manual"
                                                min="0" step="100" value=""
                                                placeholder="Input manual upah">
                                        </div>
                                        <small class="text-muted">Pilih tarif dari dropdown:</small>
                                        <select class="form-control mt-1" id="tarif_bordir_dropdown">
                                            <option value="">-- Pilih Tarif Standar --</option>
                                            <?php
                                            // Query untuk mendapatkan tarif bordir
                                            $tarif_bordir = query("SELECT * FROM tarif_upah WHERE jenis_tarif = 'bordir' ORDER BY berlaku_sejak DESC");
                                            foreach ($tarif_bordir as $tarif):
                                            ?>
                                                <option value="<?= $tarif['tarif_per_unit'] ?>"
                                                    data-tanggal="<?= $tarif['berlaku_sejak'] ?>">
                                                    Rp <?= number_format($tarif['tarif_per_unit']) ?> sejak <?= dateIndo($tarif['berlaku_sejak']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Total Upah</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="text" class="form-control"
                                                id="total_upah_bordir_display" readonly>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <span id="detail_upah_bordir_perhitungan">
                                                    0 potongan × Rp 0
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning" id="modal_hasil_bordir_alert">
                            <i class="ti ti-alert-triangle"></i>
                            <span id="modal_hasil_bordir_alert_text">
                                Pastikan jumlah hasil bordir sesuai dengan kondisi fisik.
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Tutup
                        </button>
                        <button type="submit" name="simpan_hasil_bordir" class="btn btn-success" id="modalBordirSubmitBtn">
                            Simpan Hasil Bordir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Input Tanggal Kirim Jahit -->
    <div class="modal fade" id="modalTanggalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitleTanggal">Input Tanggal Kirim Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formTanggalPenjahitan">
                    <div class="modal-body">
                        <?php if (isset($error_modal)): ?>
                            <div class="alert alert-danger"><?= $error_modal ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="id_hasil_potong_fix" id="modal_tanggal_id_hasil_potong">
                        <input type="hidden" id="modal_tanggal_tanggal_potong">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Produk</label>
                                <input type="text" class="form-control" id="modal_tanggal_produk" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Seri</label>
                                <input type="text" class="form-control" id="modal_tanggal_seri" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_tanggal_total_potong" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Penjahit</label>
                            <select name="id_penjahit" class="form-control" id="modal_tanggal_penjahit" required>
                                <option value="">-- Pilih Penjahit --</option>
                                <?php foreach ($penjahit as $j): ?>
                                    <option value="<?= $j['id_penjahit'] ?>">
                                        <?= htmlspecialchars($j['nama_penjahit']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pilih penjahit yang akan mengerjakan</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Kirim Jahit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_kirim_jahit" class="form-control"
                                id="modal_tanggal_kirim_jahit" required value="<?= date('Y-m-d') ?>">
                            <small class="text-muted">Tanggal ketika bahan dikirim ke penjahit</small>
                        </div>

                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i> Data hasil jahit dapat diinput nanti setelah penjahitan selesai.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_tanggal_kirim" class="btn btn-primary">Simpan Tanggal Kirim</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Input Hasil Jahit -->
    <div class="modal fade" id="modalHasilPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitleHasil">Input Hasil Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHasilPenjahitan">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="modal_hasil_id_hasil_potong">
                        <input type="hidden" name="total_upah_penjahit" id="total_upah_penjahit_hidden">
                        <input type="hidden" name="upah_per_potongan_penjahit" id="upah_per_potongan_penjahit_hidden">

                        <!-- Alert jika sudah ada data -->
                        <div class="alert alert-info d-none" id="modalHasilExistAlert">
                            <i class="ti ti-info-circle"></i>
                            <strong>Perhatian:</strong> Data hasil jahit sudah ada sebelumnya.
                            <div id="modalHasilExistDetail"></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Produk</label>
                                <input type="text" class="form-control" id="modal_hasil_produk" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Seri</label>
                                <input type="text" class="form-control" id="modal_hasil_seri" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Potong</label>
                            <input type="text" class="form-control" id="modal_hasil_total_potong" readonly>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Penjahit</label>
                                <input type="text" class="form-control" id="modal_hasil_nama_penjahit" readonly>
                            </div>

                            <div hidden class="col-md-6">
                                <label class="form-label">Tanggal Kirim</label>
                                <input type="text" class="form-control" id="modal_hasil_tanggal_kirim_text" readonly>
                            </div>
                        </div>

                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Total Hasil Jahit (Pcs) <span class="text-danger">*</span></label>
                                <input type="number" name="total_hasil_jahit" class="form-control"
                                    min="1" max="" id="modal_hasil_total_jahit" required>
                                <small class="text-muted">Maksimal: <span id="modal_hasil_max_total">0</span> Pcs</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Hasil Jahit <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_hasil_jahit" class="form-control"
                                    id="modal_hasil_tanggal_jahit" required>
                                <small class="text-muted">bulan/tanggal/tahun</small>
                            </div>
                        </div>

                        <!-- Input Upah Penjahit -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Upah Penjahit</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Upah per Potongan</label>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" name="upah_per_potongan_manual"
                                                class="form-control" id="upah_per_potongan_manual"
                                                min="0" step="100" value=""
                                                placeholder="Input manual upah">
                                        </div>
                                        <small class="text-muted">Pilih tarif dari dropdown:</small>
                                        <select class="form-control mt-1" id="tarif_penjahit_dropdown">
                                            <option value="">-- Pilih Tarif Standar --</option>
                                            <?php
                                            // Query untuk mendapatkan tarif penjahitan
                                            $tarif_penjahit = query("SELECT * FROM tarif_upah WHERE jenis_tarif = 'penjahitan' ORDER BY berlaku_sejak DESC");
                                            foreach ($tarif_penjahit as $tarif):
                                            ?>
                                                <option value="<?= $tarif['tarif_per_unit'] ?>"
                                                    data-tanggal="<?= $tarif['berlaku_sejak'] ?>">
                                                    Rp <?= number_format($tarif['tarif_per_unit']) ?> sejak <?= dateIndo($tarif['berlaku_sejak']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Total Upah</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="text" class="form-control"
                                                id="total_upah_penjahit_display" readonly>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <span id="detail_upah_perhitungan">
                                                    0 potongan × Rp 0
                                                </span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning" id="modal_hasil_alert">
                            <i class="ti ti-alert-triangle"></i>
                            <span id="modal_hasil_alert_text">
                                Pastikan jumlah hasil jahit sesuai dengan kondisi fisik.
                            </span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Tutup
                        </button>
                        <button type="submit" name="simpan_hasil_jahit" class="btn btn-success" id="modalHasilSubmitBtn">
                            Simpan Hasil Jahit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Batal Hasil Jahit -->
    <div class="modal fade" id="modalBatalPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Batal Hasil Jahit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formBatalPenjahitan">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="batal_modal_id">
                        <input type="hidden" name="tipe_produk" id="batal_modal_tipe_produk">

                        <p>Apakah Anda yakin ingin membatalkan <strong>hasil jahit</strong> untuk:</p>
                        <p><strong>Produk:</strong> <span id="batal_modal_produk"></span></p>
                        <p><strong>Seri:</strong> <span id="batal_modal_seri"></span></p>
                        <p><strong>Tipe Produk:</strong> <span id="batal_modal_tipe_text" class="badge"></span></p>

                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i>
                            <strong>Catatan:</strong><br>
                            1. Hanya data hasil jahit yang akan dihapus<br>
                            2. Data penjahit dan tanggal kirim tetap tersimpan<br>
                            3. Status akan kembali ke "Penjahitan"<br>
                        </div>
                        <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="batal_penjahitan" class="btn btn-danger">Ya, Batalkan Hasil Jahit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus Penjahit -->
    <div class="modal fade" id="modalHapusPenjahit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus Data Penjahit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHapusPenjahit">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="hapus_penjahit_id">
                        <p>Apakah Anda yakin ingin menghapus <strong>data penjahit dan tanggal kirim</strong> untuk:</p>
                        <p><strong>Produk:</strong> <span id="hapus_penjahit_produk"></span></p>
                        <p><strong>Seri:</strong> <span id="hapus_penjahit_seri"></span></p>
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle"></i>
                            <strong>Peringatan:</strong><br>
                            1. Semua data penjahit dan tanggal kirim akan dihapus<br>
                            2. Status akan kembali ke "Bordir"<br>
                            3. Jika ada hasil jahit, akan dihapus juga
                        </div>
                        <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_penjahit" class="btn btn-danger">Ya, Hapus Penjahit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');

            // Inisialisasi semua modal
            const modalTanggalBordir = new bootstrap.Modal(document.getElementById('modalTanggalBordir'));
            const modalHasilBordir = new bootstrap.Modal(document.getElementById('modalHasilBordir'));
            const modalTanggalPenjahitan = new bootstrap.Modal(document.getElementById('modalTanggalPenjahitan'));
            const modalHasilPenjahitan = new bootstrap.Modal(document.getElementById('modalHasilPenjahitan'));
            const modalBatalPenjahitan = new bootstrap.Modal(document.getElementById('modalBatalPenjahitan'));
            const modalHapusPenjahit = new bootstrap.Modal(document.getElementById('modalHapusPenjahit'));

            // Fungsi format number dan rupiah
            function formatNumber(angka) {
                return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            function formatRupiah(angka) {
                return 'Rp ' + formatNumber(angka);
            }

            // Inisialisasi event untuk modal bordir
            function initModalBordirEvents() {
                const modal = document.getElementById('modalHasilBordir');
                if (!modal) return;

                const totalBordirInput = modal.querySelector('#modal_hasil_bordir_total');
                const upahManualInput = modal.querySelector('#upah_per_potongan_bordir_manual');
                const tarifDropdown = modal.querySelector('#tarif_bordir_dropdown');
                const tanggalBordirInput = modal.querySelector('#modal_hasil_bordir_tanggal');
                const totalUpahDisplay = modal.querySelector('#total_upah_bordir_display');
                const detailUpahSpan = modal.querySelector('#detail_upah_bordir_perhitungan');
                const totalUpahHidden = modal.querySelector('#total_upah_bordir_hidden');
                const upahPerPotonganHidden = modal.querySelector('#upah_per_potongan_bordir_hidden');

                function hitungTotalUpahBordir() {
                    const totalHasil = parseInt(totalBordirInput.value) || 0;
                    const upahPerPotongan = parseFloat(upahManualInput.value) || 0;
                    const totalUpah = totalHasil * upahPerPotongan;

                    totalUpahDisplay.value = formatRupiah(totalUpah);
                    totalUpahHidden.value = totalUpah;
                    upahPerPotonganHidden.value = upahPerPotongan;
                    detailUpahSpan.innerHTML = `${totalHasil} potongan × Rp ${formatNumber(upahPerPotongan)}`;
                }

                totalBordirInput.addEventListener('input', hitungTotalUpahBordir);
                upahManualInput.addEventListener('input', function() {
                    tarifDropdown.value = '';
                    hitungTotalUpahBordir();
                });

                tarifDropdown.addEventListener('change', function() {
                    const selectedTarif = parseFloat(this.value) || 0;
                    if (selectedTarif > 0) {
                        upahManualInput.value = selectedTarif;
                        hitungTotalUpahBordir();
                    }
                });

                tanggalBordirInput.addEventListener('change', function() {
                    const selectedDate = this.value;
                    if (selectedDate) {
                        const options = tarifDropdown.options;
                        let found = false;
                        for (let i = 0; i < options.length; i++) {
                            const option = options[i];
                            const tanggalBerlaku = option.dataset.tanggal;
                            if (tanggalBerlaku && selectedDate >= tanggalBerlaku) {
                                tarifDropdown.value = option.value;
                                upahManualInput.value = option.value;
                                hitungTotalUpahBordir();
                                found = true;
                                break;
                            }
                        }
                        if (!found && options.length > 1) {
                            tarifDropdown.value = options[1].value;
                            upahManualInput.value = options[1].value;
                            hitungTotalUpahBordir();
                        }
                    }
                });

                // Set default date
                if (!tanggalBordirInput.value) {
                    tanggalBordirInput.value = '<?= date("Y-m-d") ?>';
                    tanggalBordirInput.dispatchEvent(new Event('change'));
                }
            }

            // Inisialisasi event untuk modal jahit
            function initModalJahitEvents() {
                const modal = document.getElementById('modalHasilPenjahitan');
                if (!modal) return;

                const totalJahitInput = modal.querySelector('#modal_hasil_total_jahit');
                const upahManualInput = modal.querySelector('#upah_per_potongan_manual');
                const tarifDropdown = modal.querySelector('#tarif_penjahit_dropdown');
                const tanggalJahitInput = modal.querySelector('#modal_hasil_tanggal_jahit');
                const totalUpahDisplay = modal.querySelector('#total_upah_penjahit_display');
                const detailUpahSpan = modal.querySelector('#detail_upah_perhitungan');
                const totalUpahHidden = modal.querySelector('#total_upah_penjahit_hidden');
                const upahPerPotonganHidden = modal.querySelector('#upah_per_potongan_penjahit_hidden');

                function hitungTotalUpahJahit() {
                    const totalHasil = parseInt(totalJahitInput.value) || 0;
                    const upahPerPotongan = parseFloat(upahManualInput.value) || 0;
                    const totalUpah = totalHasil * upahPerPotongan;

                    totalUpahDisplay.value = formatRupiah(totalUpah);
                    totalUpahHidden.value = totalUpah;
                    upahPerPotonganHidden.value = upahPerPotongan;
                    detailUpahSpan.innerHTML = `${totalHasil} potongan × Rp ${formatNumber(upahPerPotongan)}`;
                }

                totalJahitInput.addEventListener('input', hitungTotalUpahJahit);
                upahManualInput.addEventListener('input', function() {
                    tarifDropdown.value = '';
                    hitungTotalUpahJahit();
                });

                tarifDropdown.addEventListener('change', function() {
                    const selectedTarif = parseFloat(this.value) || 0;
                    if (selectedTarif > 0) {
                        upahManualInput.value = selectedTarif;
                        hitungTotalUpahJahit();
                    }
                });

                tanggalJahitInput.addEventListener('change', function() {
                    const selectedDate = this.value;
                    if (selectedDate) {
                        const options = tarifDropdown.options;
                        let found = false;
                        for (let i = 0; i < options.length; i++) {
                            const option = options[i];
                            const tanggalBerlaku = option.dataset.tanggal;
                            if (tanggalBerlaku && selectedDate >= tanggalBerlaku) {
                                tarifDropdown.value = option.value;
                                upahManualInput.value = option.value;
                                hitungTotalUpahJahit();
                                found = true;
                                break;
                            }
                        }
                        if (!found && options.length > 1) {
                            tarifDropdown.value = options[1].value;
                            upahManualInput.value = options[1].value;
                            hitungTotalUpahJahit();
                        }
                    }
                });

                // Set default date
                if (!tanggalJahitInput.value) {
                    tanggalJahitInput.value = '<?= date("Y-m-d") ?>';
                    tanggalJahitInput.dispatchEvent(new Event('change'));
                }
            }

            // Event listener untuk tombol-tombol
            document.addEventListener('click', function(e) {
                // Tombol Input Tanggal Kirim Bordir
                if (e.target.closest('.btn-input-tanggal-bordir')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-input-tanggal-bordir');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');

                    document.getElementById('modal_bordir_id_hasil_potong').value = id;
                    document.getElementById('modal_bordir_produk').value = produk;
                    document.getElementById('modal_bordir_seri').value = seri;
                    document.getElementById('modal_bordir_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_bordir_tanggal_kirim').value = '<?= date('Y-m-d') ?>';
                    document.getElementById('modal_bordir_bordir').selectedIndex = 0;

                    modalTanggalBordir.show();
                }

                // Tombol Input Hasil Bordir
                if (e.target.closest('.btn-input-hasil-bordir')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-input-hasil-bordir');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const namaBordir = button.getAttribute('data-nama-bordir');
                    const tanggalKirim = button.getAttribute('data-tanggal-kirim');

                    document.getElementById('modal_hasil_bordir_id').value = id;
                    document.getElementById('modal_hasil_bordir_produk').value = produk;
                    document.getElementById('modal_hasil_bordir_seri').value = seri;
                    document.getElementById('modal_hasil_bordir_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_hasil_bordir_nama_bordir').value = namaBordir || '-';
                    document.getElementById('modal_hasil_bordir_total').value = totalPotong;
                    document.getElementById('modal_hasil_bordir_total').max = totalPotong;
                    document.getElementById('modal_hasil_bordir_max').textContent = totalPotong;
                    document.getElementById('modal_hasil_bordir_tanggal').value = '<?= date('Y-m-d') ?>';

                    // Reset input upah
                    document.getElementById('upah_per_potongan_bordir_manual').value = '';
                    document.getElementById('tarif_bordir_dropdown').selectedIndex = 0;
                    document.getElementById('total_upah_bordir_display').value = '';
                    document.getElementById('detail_upah_bordir_perhitungan').textContent = '0 potongan × Rp 0';

                    setTimeout(() => {
                        initModalBordirEvents();
                        const tanggalInput = document.getElementById('modal_hasil_bordir_tanggal');
                        if (tanggalInput) {
                            tanggalInput.dispatchEvent(new Event('change'));
                        }
                    }, 100);

                    modalHasilBordir.show();
                }

                // Tombol Input Tanggal Kirim Jahit
                if (e.target.closest('.btn-input-tanggal-penjahitan')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-input-tanggal-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');

                    document.getElementById('modal_tanggal_id_hasil_potong').value = id;
                    document.getElementById('modal_tanggal_produk').value = produk;
                    document.getElementById('modal_tanggal_seri').value = seri;
                    document.getElementById('modal_tanggal_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_tanggal_kirim_jahit').value = '<?= date('Y-m-d') ?>';
                    document.getElementById('modal_tanggal_penjahit').selectedIndex = 0;

                    modalTanggalPenjahitan.show();
                }

                // Tombol Input Hasil Jahit
                if (e.target.closest('.btn-input-hasil-penjahitan')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-input-hasil-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const namaPenjahit = button.getAttribute('data-nama-penjahit');
                    const tanggalKirim = button.getAttribute('data-tanggal-kirim');

                    document.getElementById('modal_hasil_id_hasil_potong').value = id;
                    document.getElementById('modal_hasil_produk').value = produk;
                    document.getElementById('modal_hasil_seri').value = seri;
                    document.getElementById('modal_hasil_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_hasil_nama_penjahit').value = namaPenjahit || '-';
                    document.getElementById('modal_hasil_total_jahit').value = totalPotong;
                    document.getElementById('modal_hasil_total_jahit').max = totalPotong;
                    document.getElementById('modal_hasil_max_total').textContent = totalPotong;
                    document.getElementById('modal_hasil_tanggal_jahit').value = '<?= date('Y-m-d') ?>';

                    // Reset input upah
                    document.getElementById('upah_per_potongan_manual').value = '';
                    document.getElementById('tarif_penjahit_dropdown').selectedIndex = 0;
                    document.getElementById('total_upah_penjahit_display').value = '';
                    document.getElementById('detail_upah_perhitungan').textContent = '0 potongan × Rp 0';

                    setTimeout(() => {
                        initModalJahitEvents();
                        const tanggalInput = document.getElementById('modal_hasil_tanggal_jahit');
                        if (tanggalInput) {
                            tanggalInput.dispatchEvent(new Event('change'));
                        }
                    }, 100);

                    modalHasilPenjahitan.show();
                }

                // Tombol Batal Produksi
                if (e.target.closest('.btn-batal-produksi')) {
                    e.preventDefault();
                    const button = e.target.closest('.btn-batal-produksi');
                    const id = button.getAttribute('data-id');
                    const pemotong = button.getAttribute('data-pemotong');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const produk = button.getAttribute('data-produk');

                    Swal.fire({
                        title: 'Yakin ingin membatalkan produksi ini?',
                        html: `<div class="text-start">
                                <p><strong>Produksi Seri ${seri}</strong> akan dibatalkan.</p>
                                <div class="alert alert-warning mt-2">
                                    <p><strong>Detail Produksi:</strong></p>
                                    <p>Produk: ${produk}</p>
                                    <p>Pemotong: ${pemotong}</p>
                                    <p>Hasil Potong: ${totalPotong} Pcs</p>
                                </div>
                                <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                            </div>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, batalkan!',
                        cancelButtonText: 'Batal',
                        width: '600px'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'batal_pemotongan.php?id=' + id;
                        }
                    });
                }
            });

            // Tombol Print PDF
            document.getElementById('btnPrintPDF')?.addEventListener('click', function() {
                const id_produk = document.querySelector('select[name="id_produk"]')?.value;
                const status = document.querySelector('select[name="status"]')?.value;
                const start_date = document.querySelector('input[name="start_date"]')?.value;
                const end_date = document.querySelector('input[name="end_date"]')?.value;

                let url = 'print_laporan_produksi.php?id_produk=' + (id_produk || 0) +
                    '&status=' + (status || 'all') +
                    '&start_date=' + (start_date || '') +
                    '&end_date=' + (end_date || '');

                window.open(url, '_blank');
            });

            // Set default date range
            function setDefaultDateRange() {
                const startInput = document.querySelector('input[name="start_date"]');
                const endInput = document.querySelector('input[name="end_date"]');

                if (startInput && !startInput.value) {
                    const startDate = new Date();
                    startDate.setDate(startDate.getDate() - 30);
                    startInput.value = startDate.toISOString().split('T')[0];
                }

                if (endInput && !endInput.value) {
                    const endDate = new Date();
                    endInput.value = endDate.toISOString().split('T')[0];
                }
            }

            setDefaultDateRange();
        });
    </script>
</body>

</html>