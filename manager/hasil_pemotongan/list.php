<?php

// Aktifkan error reporting
error_reporting(error_level: E_ALL);
ini_set('display_errors', 1);

require_once '../includes/header.php';
require_once '../../config/functions.php';

// ============================================================================
// FUNGSI VALIDASI DAN UTILITAS
// ============================================================================

/**
 * Cek apakah data koko sudah ada untuk produk tertentu
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
 * Mencatat atau menambah hutang upah karyawan
 */
function catatHutangUpah($id_karyawan, $jenis_karyawan, $tanggal_produksi, $jumlah_upah)
{
    global $conn;

    $check = $conn->prepare("
        SELECT id_hutang, total_upah, sisa_hutang 
        FROM hutang_upah 
        WHERE id_karyawan = ? AND jenis_karyawan = ?
    ");
    $check->bind_param("is", $id_karyawan, $jenis_karyawan);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $hutang = $result->fetch_assoc();
        $total_upah_baru = $hutang['total_upah'] + $jumlah_upah;
        $sisa_hutang_baru = $hutang['sisa_hutang'] + $jumlah_upah;

        $update = $conn->prepare("
            UPDATE hutang_upah 
            SET total_upah = ?, sisa_hutang = ?, updated_at = NOW()
            WHERE id_hutang = ?
        ");
        $update->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
        return $update->execute();
    } else {
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
 * Mengurangi hutang upah penjahit dengan jumlah yang ditentukan
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
        throw new Exception("Gagal mengurangi hutang upah penjahit: " . $e->getMessage());
    }
}

/**
 * Mengurangi hutang upah pemotong dengan jumlah yang ditentukan
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
            $sql_delete = "DELETE FROM hutang_upah WHERE id_hutang = ?";
            $stmt = $conn->prepare($sql_delete);
            $stmt->bind_param("i", $hutang['id_hutang']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus record hutang: " . $conn->error);
            }
            return true;
        } else {
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

/**
 * Mengurangi hutang upah bordir dengan jumlah yang ditentukan
 * @param int $id_bordir ID bordir
 * @param float $jumlah_kurang Jumlah yang akan dikurangi
 * @return bool True jika berhasil, Exception jika gagal
 * @throws Exception Jika terjadi kesalahan
 */
function kurangiHutangUpahBordir($id_bordir, $jumlah_kurang)
{
    global $conn;

    try {
        // 1. Cek apakah ada hutang
        $sql_check = "SELECT id_hutang, total_upah, sisa_hutang, total_dibayar 
                     FROM hutang_upah 
                     WHERE id_karyawan = ? AND jenis_karyawan = 'bordir'
                     LIMIT 1";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("i", $id_bordir);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return true;
        }

        $hutang = $result->fetch_assoc();

        // 2. Validasi: tidak boleh mengurangi lebih dari sisa hutang
        if ($jumlah_kurang > $hutang['sisa_hutang']) {
            throw new Exception("Tidak dapat mengurangi hutang bordir karena jumlah yang akan dikurangi (" .
                formatRupiah($jumlah_kurang) . ") lebih besar dari sisa hutang (" .
                formatRupiah($hutang['sisa_hutang']) . ")");
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
            if (!$stmt->execute()) {
                throw new Exception("Gagal menghapus record hutang bordir: " . $conn->error);
            }
            return true;
        } else {
            $sql_update = "UPDATE hutang_upah 
                          SET total_upah = ?, 
                              sisa_hutang = ?,
                              updated_at = NOW()
                          WHERE id_hutang = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
            if (!$stmt->execute()) {
                throw new Exception("Gagal update hutang bordir: " . $conn->error);
            }
            return true;
        }
    } catch (Exception $e) {
        throw new Exception("Gagal mengurangi hutang upah bordir: " . $e->getMessage());
    }
}

function kembalikanStokBahanBaku($id_hasil_potong_fix)
{
    global $conn;

    try {
        // Log untuk debugging
        error_log("Memulai pengembalian stok untuk id_hasil_potong_fix: " . $id_hasil_potong_fix);

        // 1. Ambil semua detail bahan yang digunakan dalam produksi ini
        $sql_detail = "SELECT dh.*, b.nama_bahan, b.satuan, b.jumlah_stok, b.jumlah_meter
                      FROM detail_hasil_potong_fix dh
                      JOIN bahan_baku b ON dh.id_bahan = b.id_bahan
                      WHERE dh.id_hasil_potong_fix = ?";

        $stmt = $conn->prepare($sql_detail);
        $stmt->bind_param("i", $id_hasil_potong_fix);
        $stmt->execute();
        $result = $stmt->get_result();

        $total_bahan_dikembalikan = 0;
        $detail_bahan_dikembalikan = [];

        while ($detail = $result->fetch_assoc()) {
            $id_bahan = $detail['id_bahan'];
            $jumlah_digunakan = $detail['jumlah'] ?? 0;
            $total_meter = $detail['total_meter'] ?? 0;
            $nama_bahan = $detail['nama_bahan'];
            $satuan = $detail['satuan'];

            error_log("Mengembalikan bahan: $nama_bahan (ID: $id_bahan)");
            error_log("  - Jumlah digunakan: $jumlah_digunakan");
            error_log("  - Total meter: $total_meter");

            if (($jumlah_digunakan > 0 || $total_meter > 0) && $id_bahan > 0) {
                // 2. Update stok bahan baku - TAMBAHKAN kembali jumlah yang digunakan
                $sql_update_stok = "UPDATE bahan_baku 
                                   SET jumlah_stok = jumlah_stok + ?,
                                       jumlah_meter = jumlah_meter + ?,
                                       updated_at = NOW()
                                   WHERE id_bahan = ?";

                $stmt_update = $conn->prepare($sql_update_stok);
                $stmt_update->bind_param("ddi", $jumlah_digunakan, $total_meter, $id_bahan);

                if (!$stmt_update->execute()) {
                    error_log("Gagal update stok untuk bahan ID $id_bahan: " . $conn->error);
                    throw new Exception("Gagal mengembalikan stok bahan baku ID $id_bahan: " . $conn->error);
                }

                $total_bahan_dikembalikan += ($jumlah_digunakan + $total_meter);
                $detail_bahan_dikembalikan[] = [
                    'id_bahan' => $id_bahan,
                    'nama_bahan' => $nama_bahan,
                    'jumlah_stok' => $jumlah_digunakan,
                    'jumlah_meter' => $total_meter,
                    'satuan' => $satuan
                ];

                error_log("  - Berhasil dikembalikan");
            }
        }

        error_log("Total bahan dikembalikan: " . $total_bahan_dikembalikan);

        return [
            'total' => $total_bahan_dikembalikan,
            'detail' => $detail_bahan_dikembalikan
        ];
    } catch (Exception $e) {
        error_log("Error dalam kembalikanStokBahanBaku: " . $e->getMessage());
        throw new Exception("Gagal mengembalikan stok bahan baku: " . $e->getMessage());
    }
}


// ============================================================================
// AMBIL DATA DARI DATABASE UNTUK DROPDOWN DAN FILTER
// ============================================================================

// Ambil semua produk untuk dropdown
$produk = query("SELECT * FROM produk");
$pemotong = query("SELECT * FROM pemotong");
$penjahit = query("SELECT * FROM penjahit");
$bordir = query("SELECT * FROM bordir");

// ============================================================================
// SET FILTER DARI REQUEST GET
// ============================================================================

// Cek filter yang diterapkan
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$id_pemotong = isset($_GET['id_pemotong']) ? (int)$_GET['id_pemotong'] : 0;
$id_penjahit = isset($_GET['id_penjahit']) ? $_GET['id_penjahit'] : 0;
$id_bordir = isset($_GET['id_bordir']) ? $_GET['id_bordir'] : 0;

$start_date_default = date('Y-m-01');
$end_date_default   = date('Y-m-t');

// ============================================================================
// HITUNG TOTAL DATA
// ============================================================================

/**
 * 1. HITUNG TOTAL TANPA FILTER
 */
$sql_total_all = "SELECT 
    SUM(h.total_hasil) as total_hasil_all,
    SUM(COALESCE(h.total_hasil_bordir, 0)) as total_hasil_bordir_all,
    SUM(COALESCE(h.total_hasil_jahit, 0)) as total_hasil_jahit_all
FROM hasil_potong_fix h 
WHERE 1=1";

$total_all = query($sql_total_all)[0] ?? [];
$total_hasil_all = $total_all['total_hasil_all'] ?? 0;
$total_hasil_bordir_all = $total_all['total_hasil_bordir_all'] ?? 0;
$total_hasil_jahit_all = $total_all['total_hasil_jahit_all'] ?? 0;

/**
 * 2. HITUNG TOTAL DENGAN FILTER
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

$total_filtered = query($sql_total_filtered)[0] ?? [];
$total_hasil_filtered = $total_filtered['total_hasil_filtered'] ?? 0;
$total_hasil_bordir_filtered = $total_filtered['total_hasil_bordir_filtered'] ?? 0;
$total_hasil_jahit_filtered = $total_filtered['total_hasil_jahit_filtered'] ?? 0;

/**
 * 3. QUERY UNTUK DATA TABEL DENGAN FILTER
 */
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

$all_data = [];
foreach ($produksi as $prod) {
    // Dapatkan tarif upah
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

    $all_data[] = [
        'type' => 'produksi',
        'id' => $prod['id_hasil_potong_fix'],
        'tanggal' => $prod['tanggal_hasil_potong'],
        'produk' => $prod['nama_produk'],
        'tipe_produk' => $prod['tipe_produk'],
        'seri' => $prod['seri'],
        'pemotong' => $prod['nama_pemotong'],
        'bordir' => $prod['nama_bordir'] ?? '-',
        'penjahit' => $prod['nama_penjahit'],
        'id_bordir' => $prod['id_bordir'] ?? null,
        'id_penjahit' => $prod['id_penjahit'],
        'status' => $prod['status_potong'],
        'total_hasil' => $prod['total_hasil'],
        'tanggal_kirim_bordir' => $prod['tanggal_kirim_bordir'] ?? null,
        'tanggal_hasil_bordir' => $prod['tanggal_hasil_bordir'] ?? null,
        'total_hasil_bordir' => $prod['total_hasil_bordir'] ?? 0,
        'tanggal_kirim_jahit' => $prod['tanggal_kirim_jahit'] ?? null,
        'tanggal_hasil_jahit' => $prod['tanggal_hasil_jahit'] ?? null,
        'total_hasil_jahit' => $prod['total_hasil_jahit'] ?? 0
    ];
}

// Urutkan data berdasarkan seri (descending)
usort($all_data, function ($a, $b) {
    return (int)$b['seri'] <=> (int)$a['seri'];
});

// ============================================================================
// PROSES INPUT BORDIR DAN PENJAHITAN DARI FORM POST
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    /**
     * 1. SIMPAN TANGGAL KIRIM BORDIR
     */
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

        // Validasi
        $error_modal = null;

        if (empty($tanggal_hasil_bordir)) {
            $error_modal = "Tanggal hasil bordir harus diisi";
        } elseif ($total_hasil_bordir <= 0) {
            $error_modal = "Total hasil bordir harus lebih dari 0";
        } elseif ($total_upah <= 0) {
            $error_modal = "Total upah harus lebih dari 0!";
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
                        status_potong = 'bordir'
                    WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                if (!$conn->query($sql_update)) {
                    throw new Exception("Gagal update data hasil bordir: " . $conn->error);
                }

                // 2. Catat hutang upah bordir
                // Ambil id bordir dari database
                $result = query("SELECT id_bordir FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix");
                if (!empty($result) && !empty($result[0]['id_bordir'])) {
                    $id_bordir = $result[0]['id_bordir'];
                    if (!catatHutangUpah($id_bordir, 'bordir', $tanggal_hasil_bordir, $upah_bordir)) {
                        throw new Exception("Gagal mencatat hutang upah bordir");
                    }
                }

                $conn->commit();
                $conn->autocommit(TRUE);

                $_SESSION['success'] = "Data hasil bordir berhasil disimpan. Upah bordir: " . formatRupiah($upah_bordir);
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
     * 3. SIMPAN TANGGAL KIRIM JAHIT
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
     * 4. SIMPAN HASIL JAHIT
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

        // Ambil data ATK finishing
        $atk_nama = $_POST['atk_nama'] ?? [];
        $atk_jumlah = $_POST['atk_jumlah'] ?? [];
        $atk_satuan = $_POST['atk_satuan'] ?? [];

        // Validasi
        $error_modal = null;

        if (empty($tanggal_hasil_jahit)) {
            $error_modal = "Tanggal hasil jahit harus diisi";
        } elseif ($total_hasil_jahit <= 0) {
            $error_modal = "Total hasil jahit harus lebih dari 0";
        } elseif ($total_upah <= 0) {
            $error_modal = "Total upah harus lebih dari 0!";
        }

        // AMBIL DATA TIPE PRODUK SEBELUM VALIDASI
        $produksi_data = query("SELECT 
        hp.id_produk, 
        p.tipe_produk
    FROM hasil_potong_fix hp
    JOIN produk p ON hp.id_produk = p.id_produk
    WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0] ?? [];

        $tipe_produk = $produksi_data['tipe_produk'] ?? '';

        // Validasi ATK hanya untuk mukena
        if ($tipe_produk == 'mukena') {
            if (empty($atk_nama[0]) || empty($atk_nama[0])) { // Validasi minimal 1 ATK hanya untuk mukena
                $error_modal = "Minimal satu ATK finishing harus diisi untuk produk mukena!";
            }
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
                status_potong = 'selesai'
            WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                if (!$conn->query($sql_update)) {
                    throw new Exception("Gagal update data hasil jahit: " . $conn->error);
                }

                // 2. Catat hutang upah penjahit
                // Ambil id penjahit dari database
                $result = query("SELECT id_penjahit FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix");
                if (!empty($result) && !empty($result[0]['id_penjahit'])) {
                    $id_penjahit = $result[0]['id_penjahit'];
                    if (!catatHutangUpah($id_penjahit, 'penjahit', $tanggal_hasil_jahit, $upah_penjahit)) {
                        throw new Exception("Gagal mencatat hutang upah penjahit");
                    }
                }

                // 3. SIMPAN ATK FINISHING (HANYA UNTUK MUKENA)
                if ($tipe_produk == 'mukena') {
                    $atk_data = [];
                    foreach ($atk_nama as $index => $nama) {
                        if (!empty($nama) && isset($atk_jumlah[$index]) && isset($atk_satuan[$index])) {
                            $atk_data[] = [
                                'nama' => $conn->real_escape_string($nama),
                                'jumlah' => intval($atk_jumlah[$index]),
                                'satuan' => $conn->real_escape_string($atk_satuan[$index])
                            ];
                        }
                    }

                    // Simpan ATK finishing ke database
                    foreach ($atk_data as $atk) {
                        $sql_atk = "INSERT INTO atk_finishing (id_hasil_potong_fix, nama_atk, jumlah, satuan, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
                        $stmt_atk = $conn->prepare($sql_atk);
                        $stmt_atk->bind_param("isis", $id_hasil_potong_fix, $atk['nama'], $atk['jumlah'], $atk['satuan']);
                        if (!$stmt_atk->execute()) {
                            throw new Exception("Gagal menyimpan ATK finishing: " . $stmt_atk->error);
                        }
                    }
                }

                // 4. Update stok berdasarkan tipe produk
                if (!empty($produksi_data)) {
                    $id_produk = $produksi_data['id_produk'];

                    if ($tipe_produk == 'mukena') {
                        // Update stok produk
                        $sql_update_stok = "UPDATE produk 
                    SET stok = stok + $total_hasil_jahit 
                    WHERE id_produk = $id_produk";

                        if (!$conn->query($sql_update_stok)) {
                            throw new Exception("Gagal update stok produk: " . $conn->error);
                        }
                    } else {
                        // Untuk koko, update stok koko
                        $koko_exist = isKokoExist($id_produk);

                        if ($koko_exist) {
                            $sql_update_koko = "UPDATE koko 
                        SET stok = stok + $total_hasil_jahit,
                            updated_at = NOW()
                        WHERE id_produk = $id_produk";

                            if (!$conn->query($sql_update_koko)) {
                                throw new Exception("Gagal update stok koko: " . $conn->error);
                            }
                        } else {
                            // Insert data koko baru
                            $produk_data = query("SELECT nama_produk, harga_jual FROM produk WHERE id_produk = $id_produk")[0] ?? [];
                            $nama_koko = $produk_data['nama_produk'] ?? "Produk Koko";
                            $harga_jual = $produk_data['harga_jual'] ?? 0;

                            $sql_insert_koko = "INSERT INTO koko (id_produk, nama_koko, harga_jual, stok, created_at, updated_at)
                        VALUES ($id_produk, '$nama_koko', $harga_jual, $total_hasil_jahit, NOW(), NOW())";

                            if (!$conn->query($sql_insert_koko)) {
                                throw new Exception("Gagal menambah data koko baru: " . $conn->error);
                            }
                        }
                    }
                }

                $conn->commit();
                $conn->autocommit(TRUE);

                $_SESSION['success'] = "Data hasil jahit berhasil disimpan. Upah penjahit: " . formatRupiah($upah_penjahit);
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
     * 5. PROSES PEMBATALAN TAHAP
     */
    if (isset($_POST['batal_tahap'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
        $tahap = $_POST['tahap'];

        $conn->autocommit(FALSE);
        try {
            // Ambil data produksi
            $produksi_data = query("SELECT 
            hp.*,
            p.tipe_produk,
            p.stok as stok_produk,
            pem.nama_pemotong,
            bor.nama_bordir,
            pen.nama_penjahit
        FROM hasil_potong_fix hp
        JOIN produk p ON hp.id_produk = p.id_produk
        JOIN pemotong pem ON hp.id_pemotong = pem.id_pemotong
        LEFT JOIN bordir bor ON hp.id_bordir = bor.id_bordir
        LEFT JOIN penjahit pen ON hp.id_penjahit = pen.id_penjahit
        WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0] ?? [];

            if (empty($produksi_data)) {
                throw new Exception("Data produksi tidak ditemukan");
            }

            $tipe_produk = $produksi_data['tipe_produk'];
            $id_produk = $produksi_data['id_produk'];
            $total_hasil_jahit = $produksi_data['total_hasil_jahit'] ?? 0;
            $total_hasil_bordir = $produksi_data['total_hasil_bordir'] ?? 0;
            $id_penjahit = $produksi_data['id_penjahit'];
            $id_bordir = $produksi_data['id_bordir'];
            $id_pemotong = $produksi_data['id_pemotong'];
            $seri = $produksi_data['seri'];

            switch ($tahap) {
                case 'hasil_jahit':
                    // Batalkan hasil jahit
                    $sql_update = "UPDATE hasil_potong_fix 
            SET tanggal_hasil_jahit = NULL,
                total_hasil_jahit = NULL,
                status_potong = 'penjahitan'
            WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                    if (!$conn->query($sql_update)) {
                        throw new Exception("Gagal membatalkan hasil jahit");
                    }

                    // Kurangi stok berdasarkan tipe produk
                    if ($total_hasil_jahit > 0) {
                        if ($tipe_produk == 'mukena') {
                            // Kurangi stok produk
                            $sql_stok = "UPDATE produk 
                    SET stok = stok - $total_hasil_jahit 
                    WHERE id_produk = $id_produk";
                        } else {
                            // Kurangi stok koko
                            $sql_stok = "UPDATE koko 
                    SET stok = stok - $total_hasil_jahit,
                        updated_at = NOW()
                    WHERE id_produk = $id_produk";
                        }

                        if (!$conn->query($sql_stok)) {
                            throw new Exception("Gagal mengurangi stok");
                        }
                    }

                    // Kurangi hutang upah penjahit
                    if ($id_penjahit > 0 && $total_hasil_jahit > 0) {
                        $tarif_upah_manual = $produksi_data['tarif_upah'] ?? 0;
                        $upah_dikurangi = $total_hasil_jahit * $tarif_upah_manual;

                        if (!kurangiHutangUpahPenjahit($id_penjahit, $upah_dikurangi)) {
                            throw new Exception("Gagal mengurangi hutang upah penjahit");
                        }
                    }

                    // HAPUS ATK FINISHING (jika ada)
                    if ($tipe_produk == 'mukena') {
                        $sql_delete_atk = "DELETE FROM atk_finishing WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
                        if (!$conn->query($sql_delete_atk)) {
                            throw new Exception("Gagal menghapus ATK finishing: " . $conn->error);
                        }
                    }

                    $success_msg = "Hasil jahit berhasil dibatalkan. Status kembali ke 'Penjahitan'";
                    break;

                case 'tanggal_kirim_jahit':
                    // Batalkan tanggal kirim jahit dan penjahit
                    $sql_update = "UPDATE hasil_potong_fix 
            SET id_penjahit = NULL,
                tanggal_kirim_jahit = NULL,
                status_potong = 'bordir'
            WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                    if (!$conn->query($sql_update)) {
                        throw new Exception("Gagal membatalkan tanggal kirim jahit");
                    }

                    $success_msg = "Tanggal kirim jahit dan data penjahit berhasil dibatalkan. Status kembali ke 'Bordir'";
                    break;

                case 'hasil_bordir':
                    // Batalkan hasil bordir
                    $sql_update = "UPDATE hasil_potong_fix 
            SET tanggal_hasil_bordir = NULL,
                total_hasil_bordir = NULL,
                status_potong = 'bordir'
            WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                    if (!$conn->query($sql_update)) {
                        throw new Exception("Gagal membatalkan hasil bordir");
                    }

                    // Kurangi hutang upah bordir
                    if ($id_bordir > 0 && $total_hasil_bordir > 0) {
                        $tarif_upah_bordir_manual = $produksi_data['tarif_upah_bordir'] ?? 0;
                        $upah_dikurangi = $total_hasil_bordir * $tarif_upah_bordir_manual;

                        if (!kurangiHutangUpahBordir($id_bordir, $upah_dikurangi)) {
                            throw new Exception("Gagal mengurangi hutang upah bordir");
                        }
                    }

                    $success_msg = "Hasil bordir berhasil dibatalkan. Status kembali ke 'Bordir'";
                    break;

                case 'tanggal_kirim_bordir':
                    // Batalkan tanggal kirim bordir dan bordir
                    $sql_update = "UPDATE hasil_potong_fix 
            SET id_bordir = NULL,
                tanggal_kirim_bordir = NULL,
                status_potong = 'diproses'
            WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                    if (!$conn->query($sql_update)) {
                        throw new Exception("Gagal membatalkan tanggal kirim bordir");
                    }

                    $success_msg = "Tanggal kirim bordir dan data bordir berhasil dibatalkan. Status kembali ke 'Potong'";
                    break;

                case 'pemotongan':
                    // PERBAIKAN: KEMBALIKAN STOK BAHAN BAKU SEBELUM MENGHAPUS DATA
                    try {
                        // 1. Kembalikan stok bahan baku terlebih dahulu
                        $hasil_kembali = kembalikanStokBahanBaku($id_hasil_potong_fix);

                        // 2. Kurangi hutang upah pemotong
                        if ($id_pemotong > 0) {
                            // Ambil data upah pemotong dari database
                            $sql_upah = "SELECT total_upah FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
                            $result_upah = $conn->query($sql_upah);
                            if ($result_upah->num_rows > 0) {
                                $row_upah = $result_upah->fetch_assoc();
                                $total_upah = $row_upah['total_upah'] ?? 0;

                                if ($total_upah > 0) {
                                    if (!kurangiHutangUpahPemotong($id_pemotong, $total_upah)) {
                                        throw new Exception("Gagal mengurangi hutang upah pemotong");
                                    }
                                }
                            }
                        }

                        // 3. Hapus detail hasil potong (detail bahan baku)
                        $sql_delete_detail = "DELETE FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
                        if (!$conn->query($sql_delete_detail)) {
                            throw new Exception("Gagal menghapus detail bahan baku: " . $conn->error);
                        }

                        // 4. Hapus data dari hasil_potong_fix
                        $sql_delete = "DELETE FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
                        if (!$conn->query($sql_delete)) {
                            throw new Exception("Gagal menghapus data produksi");
                        }

                        // 5. Tambahkan pesan sukses dengan informasi stok yang dikembalikan
                        $detail_bahan = "";
                        if (isset($hasil_kembali['detail']) && count($hasil_kembali['detail']) > 0) {
                            $detail_bahan = "Bahan baku yang dikembalikan: ";
                            foreach ($hasil_kembali['detail'] as $bahan) {
                                $detail_bahan .= "{$bahan['nama_bahan']} ({$bahan['jumlah_stok']} {$bahan['satuan']}), ";
                            }
                            $detail_bahan = rtrim($detail_bahan, ', ');
                        }

                        $success_msg = "Produksi berhasil dibatalkan. Data telah dihapus." . $detail_bahan;
                    } catch (Exception $e) {
                        throw new Exception("Gagal membatalkan pemotongan: " . $e->getMessage());
                    }
                    break;

                default:
                    throw new Exception("Tahap pembatalan tidak valid");
            }

            $conn->commit();
            $conn->autocommit(TRUE);

            $_SESSION['success'] = $success_msg;
            header("Location: list.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $conn->autocommit(TRUE);
            $error_modal = "Gagal membatalkan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Produksi</title>
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

        .table th {
            font-size: 0.8rem;
        }

        .table td {
            font-size: 0.8rem;
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
                    <h2>Data Produksi</h2>
                    <div class="btn-group">
                        <div>
                            <a href="new.php" class="btn btn-warning">
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
                                <div class="col-md-3">
                                    <label class="form-label">Produk</label>
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
                                    <label class="form-label">Pemotong</label>
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
                                    <label class="form-label">Bordir</label>
                                    <select name="id_bordir" class="form-select">
                                        <option value="0">Semua Bordir</option>
                                        <?php foreach ($bordir as $br): ?>
                                            <option value="<?= $br['id_bordir'] ?>" <?= (isset($_GET['id_bordir']) && $_GET['id_bordir'] == $br['id_bordir']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($br['nama_bordir']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Penjahit</label>
                                    <select name="id_penjahit" class="form-select">
                                        <option value="0">Semua Penjahit</option>
                                        <?php foreach ($penjahit as $pj): ?>
                                            <option value="<?= $pj['id_penjahit'] ?>" <?= (isset($_GET['id_penjahit']) && $_GET['id_penjahit'] == $pj['id_penjahit']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pj['nama_penjahit']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
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
                                    <div class="d-flex gap-2 mb-4">
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
                                                                'bordir' => 'primary',
                                                                'penjahitan' => 'info',
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
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                                        $sisa = $totalHasil - $totalHasilJahit;
                                        $total_sisa += $sisa;
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <?php
                                                $status = $data['status'];
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
                                                    default:
                                                        $badge = 'secondary';
                                                        $label = '-';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?= $badge ?> p-1 fw-normal"><?= $label ?></span>
                                            </td>
                                            <td class="text-center"><?= htmlspecialchars($data['seri']) ?></td>
                                            <td><?= htmlspecialchars($data['pemotong']) ?></td>
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
                                            <td>
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
                                            <td>
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
                                                    $has_tanggal_kirim_bordir = !empty($data['tanggal_kirim_bordir']);
                                                    $has_hasil_bordir = !empty($data['total_hasil_bordir']);
                                                    $has_tanggal_kirim_jahit = !empty($data['tanggal_kirim_jahit']);
                                                    $has_hasil_jahit = !empty($data['total_hasil_jahit']);
                                                    $is_diproses = $data['status'] == 'diproses';
                                                    $is_bordir = $data['status'] == 'bordir';
                                                    $is_penjahitan = $data['status'] == 'penjahitan';
                                                    $is_selesai = $data['status'] == 'selesai';

                                                    // Tentukan tahap pembatalan
                                                    $tahap_pembatalan = 'tidak_ada';
                                                    if ($is_selesai) {
                                                        $tahap_pembatalan = 'hasil_jahit'; // Hapus hasil jahit dulu
                                                    } elseif ($has_hasil_jahit) {
                                                        $tahap_pembatalan = 'hasil_jahit';
                                                    } elseif ($is_penjahitan && $has_tanggal_kirim_jahit) {
                                                        $tahap_pembatalan = 'tanggal_kirim_jahit'; // Hapus tanggal kirim jahit
                                                    } elseif ($has_hasil_bordir) {
                                                        $tahap_pembatalan = 'hasil_bordir'; // Hapus hasil bordir dulu
                                                    } elseif ($is_bordir && $has_tanggal_kirim_bordir) {
                                                        $tahap_pembatalan = 'tanggal_kirim_bordir'; // Hapus tanggal kirim bordir
                                                    } elseif ($is_diproses) {
                                                        $tahap_pembatalan = 'pemotongan'; // Hapus pemotongan (batal produksi)
                                                    }
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

                                                    <?php elseif ($is_bordir): ?>
                                                        <!-- Status: Bordir -->

                                                        <?php if ($has_tanggal_kirim_bordir && !$has_hasil_bordir): ?>
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

                                                        <!-- Status: Penjahitan -->
                                                        <?php if ($has_hasil_bordir && !$has_tanggal_kirim_jahit): ?>
                                                            <button class="btn btn-sm btn-info btn-input-tanggal-penjahitan"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-total-potong="<?= $data['total_hasil'] ?>"
                                                                data-total-bordir="<?= $data['total_hasil_bordir'] ?? 0 ?>"
                                                                data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                                title="Input Tanggal Kirim Jahit">
                                                                <i class="ti ti-calendar"></i>
                                                            </button>
                                                        <?php endif; ?>


                                                    <?php elseif ($is_penjahitan): ?>



                                                        <!-- Dalam loop data produksi, temukan bagian tombol ini: -->

                                                        <?php if ($has_tanggal_kirim_jahit && !$has_hasil_jahit): ?>
                                                            <button class="btn btn-sm btn-success btn-input-hasil-penjahitan"
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-tipe-produk="<?= $data['tipe_produk'] ?>"
                                                                data-total-potong="<?= $data['total_hasil'] ?>"
                                                                data-total-bordir="<?= $data['total_hasil_bordir'] ?? 0 ?>"
                                                                data-penjahit="<?= $data['id_penjahit'] ?>"
                                                                data-nama-penjahit="<?= htmlspecialchars($data['penjahit']) ?>"
                                                                data-tanggal-kirim="<?= $data['tanggal_kirim_jahit'] ?>"
                                                                title="Input Hasil Jahit">
                                                                <i class="ti ti-check"></i>
                                                            </button>

                                                        <?php endif; ?>

                                                    <?php endif; ?>

                                                    <!-- TOMBOL PEMBATALAN BERDASARKAN TAHAP -->
                                                    <?php if ($tahap_pembatalan != 'tidak_ada'): ?>
                                                        <button class="btn btn-sm btn-outline-danger btn-batal-produksi-tahap"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-total-potong="<?= $data['total_hasil'] ?>"
                                                            data-total-bordir="<?= $data['total_hasil_bordir'] ?>"
                                                            data-total-jahit="<?= $data['total_hasil_jahit'] ?>"
                                                            data-tahap="<?= $tahap_pembatalan ?>"
                                                            data-status="<?= $data['status'] ?>"
                                                            data-pemotong="<?= htmlspecialchars($data['pemotong']) ?>"
                                                            data-bordir="<?= htmlspecialchars($data['bordir']) ?>"
                                                            data-penjahit="<?= htmlspecialchars($data['penjahit']) ?>"
                                                            title="Batalkan Tahap <?= ucfirst(str_replace('_', ' ', $tahap_pembatalan)) ?>">
                                                            <i class="ti ti-x"></i>
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
                                        <td colspan="3"></td>
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
                    <h5 class="modal-title">Input Tanggal Kirim Bordir</h5>
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
                                    <option value="<?= $b['id_bordir'] ?>"><?= htmlspecialchars($b['nama_bordir']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Kirim Bordir <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_kirim_bordir" class="form-control"
                                id="modal_bordir_tanggal_kirim" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_tanggal_kirim_bordir" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Input Hasil Bordir -->
    <div class="modal fade" id="modalHasilBordir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Input Hasil Bordir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHasilBordir">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="modal_hasil_bordir_id">
                        <input type="hidden" name="total_upah_bordir" id="total_upah_bordir_hidden">
                        <input type="hidden" name="upah_per_potongan_bordir" id="upah_per_potongan_bordir_hidden">

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

                            <div class="col-md-6">
                                <label class="form-label">Total Hasil Bordir (Pcs) <span class="text-danger">*</span></label>
                                <input type="number" name="total_hasil_bordir" class="form-control"
                                    min="1" max="" id="modal_hasil_bordir_total" required>
                                <small class="text-muted">Maksimal: <span id="modal_hasil_bordir_max">0</span> Pcs</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Hasil Bordir <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_hasil_bordir" class="form-control"
                                id="modal_hasil_bordir_tanggal" required value="<?= date('Y-m-d') ?>">
                        </div>

                        <!-- Di dalam modal, bagian input upah bordir -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Upah Bordir</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Upah per Potongan <span class="text-danger">*</span></label>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" name="upah_per_potongan_bordir_manual"
                                                class="form-control" id="upah_per_potongan_bordir_manual"
                                                min="0" step="100" value="" required>
                                        </div>

                                        <!-- PERBAIKAN: Tambah instruksi -->
                                        <small class="text-muted d-block mb-2">
                                            Pilih tarif standar atau input manual
                                        </small>

                                        <label class="form-label">Pilih Tarif Standar (Opsional)</label>
                                        <select class="form-control select2-custom" id="tarif_bordir_dropdown">
                                            <option value="">-- Pilih Tarif Standar --</option>
                                            <?php
                                            $tarif_bordir = query("SELECT * FROM tarif_upah WHERE jenis_tarif = 'bordir' ORDER BY berlaku_sejak DESC");
                                            foreach ($tarif_bordir as $tarif):
                                            ?>
                                                <option value="<?= $tarif['tarif_per_unit'] ?>"
                                                    data-tanggal="<?= $tarif['berlaku_sejak'] ?>">
                                                    Rp <?= number_format($tarif['tarif_per_unit']) ?> sejak <?= dateIndo($tarif['berlaku_sejak']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted d-block mt-1">
                                            Tarif akan otomatis terisi ke input di atas
                                        </small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Total Upah</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="text" class="form-control bg-light"
                                                id="total_upah_bordir_display" readonly style="font-weight: bold;">
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Formula: Total Hasil × Upah per Potongan
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_hasil_bordir" class="btn btn-success">Simpan</button>
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
                    <h5 class="modal-title">Input Tanggal Kirim Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formTanggalPenjahitan">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="modal_tanggal_id_hasil_potong">

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
                                    <option value="<?= $j['id_penjahit'] ?>"><?= htmlspecialchars($j['nama_penjahit']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Kirim Jahit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_kirim_jahit" class="form-control"
                                id="modal_tanggal_kirim_jahit" required value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_tanggal_kirim" class="btn btn-primary">Simpan</button>
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
                    <h5 class="modal-title">Input Hasil Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHasilPenjahitan">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="modal_hasil_id_hasil_potong">
                        <input type="hidden" name="total_upah_penjahit" id="total_upah_penjahit_hidden">
                        <input type="hidden" name="upah_per_potongan_penjahit" id="upah_per_potongan_penjahit_hidden">

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

                            <div class="col-md-6">
                                <label class="form-label">Total Hasil Jahit (Pcs) <span class="text-danger">*</span></label>
                                <input type="number" name="total_hasil_jahit" class="form-control"
                                    min="1" max="" id="modal_hasil_total_jahit" required>
                                <small class="text-muted">Maksimal: <span id="modal_hasil_max_total">0</span> Pcs</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Hasil Jahit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_hasil_jahit" class="form-control"
                                id="modal_hasil_tanggal_jahit" required value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <!-- Input ATK Finishing (Hanya untuk produk mukena) -->
                                <div class="card mb-3" id="atk-card">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">ATK Finishing yang Digunakan</h6>
                                            <button type="button" class="btn btn-sm btn-primary" id="btnTambahAtk">
                                                <i class="ti ti-plus"></i> Tambah ATK
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body" id="atk-container-wrapper">
                                        <!-- Container untuk ATK items -->
                                        <div id="atk-container">
                                            <div class="atk-item row mb-3">
                                                <div class="col-md-5">
                                                    <label class="form-label">Nama ATK Finishing <span class="text-danger">*</span></label>
                                                    <input type="text" name="atk_nama[]" class="form-control atk-nama"
                                                        placeholder="Contoh: Renda, Kancing, Tali, Label, dll." required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <input type="number" name="atk_jumlah[]" class="form-control atk-jumlah"
                                                            min="1" value="1" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Satuan</label>
                                                    <select name="atk_satuan[]" class="form-control atk-satuan">
                                                        <option value="meter">Meter</option>
                                                        <option value="buah" selected>Buah</option>
                                                        <option value="set">Set</option>
                                                        <option value="roll">Roll</option>
                                                        <option value="lbr">Lembar</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-1 d-flex align-items-end">
                                                    <button type="button" class="btn btn-sm btn-danger btn-hapus-atk" style="display: none;">
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <i class="ti ti-info-circle"></i> ATK Finishing adalah bahan pendukung seperti renda, kancing, tali, label, dll.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <!-- Input Upah Penjahit -->
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Upah Penjahit</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-7">
                                                <label class="form-label">Upah per Potongan</label>
                                                <div class="input-group mb-2">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" name="upah_per_potongan_manual"
                                                        class="form-control" id="upah_per_potongan_manual"
                                                        min="0" step="100" value="" required>
                                                </div>
                                                <select class="form-control mt-1" id="tarif_penjahit_dropdown">
                                                    <option value="">-- Pilih Tarif Standar --</option>
                                                    <?php
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

                                            <div class="col-md-5">
                                                <label class="form-label">Total Upah</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="text" class="form-control"
                                                        id="total_upah_penjahit_display" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" name="simpan_hasil_jahit" class="btn btn-success">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal Konfirmasi Pembatalan Tahap -->
    <div class="modal fade" id="modalBatalTahap" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBatalTitle">Konfirmasi Pembatalan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formBatalTahap">
                    <div class="modal-body">
                        <input type="hidden" name="id_hasil_potong_fix" id="batal_id">
                        <input type="hidden" name="tahap" id="batal_tahap">

                        <p id="batal_detail"></p>

                        <div class="alert alert-warning" id="batal_keterangan">
                            <!-- Keterangan akan diisi oleh JavaScript -->
                        </div>

                        <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger" name="batal_tahap">Ya, Lanjutkan</button>
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
            console.log('DOM Content Loaded - Modal Batal Tahap Ready');

            // Inisialisasi modal
            const modalTanggalBordir = new bootstrap.Modal(document.getElementById('modalTanggalBordir'));
            const modalHasilBordir = new bootstrap.Modal(document.getElementById('modalHasilBordir'));
            const modalTanggalPenjahitan = new bootstrap.Modal(document.getElementById('modalTanggalPenjahitan'));
            const modalHasilPenjahitan = new bootstrap.Modal(document.getElementById('modalHasilPenjahitan'));
            const modalBatalTahap = new bootstrap.Modal(document.getElementById('modalBatalTahap'));

            // Fungsi format rupiah
            function formatRupiah(angka) {
                const number = parseFloat(angka) || 0;
                return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            // Fungsi untuk memformat angka ke input number
            function formatAngkaInput(angka) {
                return parseFloat(angka) || 0;
            }

            // PERBAIKAN: Fungsi untuk menghitung total upah bordir
            function hitungTotalUpahBordir() {
                const upahPerPotongan = parseFloat(document.getElementById('upah_per_potongan_bordir_manual')?.value) || 0;
                const totalHasil = parseFloat(document.getElementById('modal_hasil_bordir_total')?.value) || 0;
                const totalUpah = upahPerPotongan * totalHasil;

                if (document.getElementById('total_upah_bordir_display')) {
                    document.getElementById('total_upah_bordir_display').value = formatRupiah(totalUpah);
                }
                if (document.getElementById('total_upah_bordir_hidden')) {
                    document.getElementById('total_upah_bordir_hidden').value = totalUpah;
                }
                if (document.getElementById('upah_per_potongan_bordir_hidden')) {
                    document.getElementById('upah_per_potongan_bordir_hidden').value = upahPerPotongan;
                }
            }

            // PERBAIKAN: Fungsi untuk menghitung total upah penjahit
            function hitungTotalUpahPenjahit() {
                const upahPerPotongan = parseFloat(document.getElementById('upah_per_potongan_manual')?.value) || 0;
                const totalHasil = parseFloat(document.getElementById('modal_hasil_total_jahit')?.value) || 0;
                const totalUpah = upahPerPotongan * totalHasil;

                if (document.getElementById('total_upah_penjahit_display')) {
                    document.getElementById('total_upah_penjahit_display').value = formatRupiah(totalUpah);
                }
                if (document.getElementById('total_upah_penjahit_hidden')) {
                    document.getElementById('total_upah_penjahit_hidden').value = totalUpah;
                }
                if (document.getElementById('upah_per_potongan_penjahit_hidden')) {
                    document.getElementById('upah_per_potongan_penjahit_hidden').value = upahPerPotongan;
                }
            }

            // PERBAIKAN: Event listener untuk dropdown tarif bordir
            document.getElementById('tarif_bordir_dropdown')?.addEventListener('change', function() {
                const selectedValue = this.value;
                const upahInput = document.getElementById('upah_per_potongan_bordir_manual');

                if (selectedValue && upahInput) {
                    // Isi input manual dengan nilai dari dropdown
                    upahInput.value = formatAngkaInput(selectedValue);

                    // Hitung ulang total upah
                    hitungTotalUpahBordir();

                    // PERBAIKAN: Tampilkan pesan sukses
                    showTemporaryMessage('Tarif bordir berhasil dipilih', 'success');
                } else if (upahInput) {
                    // Jika pilihan "-- Pilih Tarif Standar --" dipilih, kosongkan input
                    upahInput.value = '';
                    if (document.getElementById('total_upah_bordir_display')) {
                        document.getElementById('total_upah_bordir_display').value = '';
                    }
                    if (document.getElementById('total_upah_bordir_hidden')) {
                        document.getElementById('total_upah_bordir_hidden').value = '';
                    }
                    if (document.getElementById('upah_per_potongan_bordir_hidden')) {
                        document.getElementById('upah_per_potongan_bordir_hidden').value = '';
                    }
                }
            });

            // PERBAIKAN: Event listener untuk dropdown tarif penjahit
            document.getElementById('tarif_penjahit_dropdown')?.addEventListener('change', function() {
                const selectedValue = this.value;
                const upahInput = document.getElementById('upah_per_potongan_manual');

                if (selectedValue && upahInput) {
                    // Isi input manual dengan nilai dari dropdown
                    upahInput.value = formatAngkaInput(selectedValue);

                    // Hitung ulang total upah
                    hitungTotalUpahPenjahit();

                    // PERBAIKAN: Tampilkan pesan sukses
                    showTemporaryMessage('Tarif penjahit berhasil dipilih', 'success');
                } else if (upahInput) {
                    // Jika pilihan "-- Pilih Tarif Standar --" dipilih, kosongkan input
                    upahInput.value = '';
                    if (document.getElementById('total_upah_penjahit_display')) {
                        document.getElementById('total_upah_penjahit_display').value = '';
                    }
                    if (document.getElementById('total_upah_penjahit_hidden')) {
                        document.getElementById('total_upah_penjahit_hidden').value = '';
                    }
                    if (document.getElementById('upah_per_potongan_penjahit_hidden')) {
                        document.getElementById('upah_per_potongan_penjahit_hidden').value = '';
                    }
                }
            });

            // Event listener untuk tombol-tombol MODAL LAIN
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

                    document.getElementById('modal_hasil_bordir_id').value = id;
                    document.getElementById('modal_hasil_bordir_produk').value = produk;
                    document.getElementById('modal_hasil_bordir_seri').value = seri;
                    document.getElementById('modal_hasil_bordir_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_hasil_bordir_nama_bordir').value = namaBordir || '-';
                    document.getElementById('modal_hasil_bordir_total').value = totalPotong;
                    document.getElementById('modal_hasil_bordir_total').max = totalPotong;
                    document.getElementById('modal_hasil_bordir_max').textContent = totalPotong;
                    document.getElementById('modal_hasil_bordir_tanggal').value = '<?= date('Y-m-d') ?>';

                    // Reset input upah bordir
                    document.getElementById('upah_per_potongan_bordir_manual').value = '';
                    document.getElementById('tarif_bordir_dropdown').selectedIndex = 0;
                    document.getElementById('total_upah_bordir_display').value = '';
                    document.getElementById('total_upah_bordir_hidden').value = '';
                    document.getElementById('upah_per_potongan_bordir_hidden').value = '';

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
                    const totalBordir = button.getAttribute('data-total-bordir') || 0;

                    document.getElementById('modal_tanggal_id_hasil_potong').value = id;
                    document.getElementById('modal_tanggal_produk').value = produk;
                    document.getElementById('modal_tanggal_seri').value = seri;

                    // Tampilkan informasi lengkap
                    let infoText = '';
                    if (totalBordir > 0) {
                        infoText = `${totalBordir} Pcs (Hasil Bordir)`;
                        infoText += ` dari ${totalPotong} Pcs hasil potong`;
                    } else {
                        infoText = `${totalPotong} Pcs (Tanpa proses bordir)`;
                    }

                    document.getElementById('modal_tanggal_total_potong').value = infoText;
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
                    const totalBordir = parseInt(button.getAttribute('data-total-bordir')) || 0;
                    const penjahit = button.getAttribute('data-penjahit');
                    const namaPenjahit = button.getAttribute('data-nama-penjahit');

                    // AMBIL TIPE PRODUK dari tombol
                    const tipeProduk = button.getAttribute('data-tipe-produk');

                    document.getElementById('modal_hasil_id_hasil_potong').value = id;
                    document.getElementById('modal_hasil_produk').value = produk;
                    document.getElementById('modal_hasil_seri').value = seri;

                    // Tampilkan total hasil potong
                    document.getElementById('modal_hasil_total_potong').value = totalPotong + ' Pcs';

                    // Jika ada hasil bordir, tampilkan juga
                    if (totalBordir > 0) {
                        document.getElementById('modal_hasil_total_potong').value =
                            totalPotong + ' Pcs (Potong) | ' + totalBordir + ' Pcs (Bordir)';
                    }

                    document.getElementById('modal_hasil_nama_penjahit').value = namaPenjahit || '-';

                    // Set maksimal berdasarkan hasil bordir jika ada, jika tidak gunakan hasil potong
                    const maxJahit = totalBordir > 0 ? totalBordir : totalPotong;

                    document.getElementById('modal_hasil_total_jahit').value = maxJahit;
                    document.getElementById('modal_hasil_total_jahit').max = maxJahit;

                    // Tampilkan informasi sumber maksimal
                    let sourceInfo = totalBordir > 0 ? 'berdasarkan hasil bordir' : 'berdasarkan hasil potong (tanpa bordir)';
                    document.getElementById('modal_hasil_max_total').textContent = maxJahit + ' Pcs ' + sourceInfo;

                    document.getElementById('modal_hasil_tanggal_jahit').value = '<?= date('Y-m-d') ?>';

                    // Reset input upah penjahit
                    document.getElementById('upah_per_potongan_manual').value = '';
                    document.getElementById('tarif_penjahit_dropdown').selectedIndex = 0;
                    document.getElementById('total_upah_penjahit_display').value = '';
                    document.getElementById('total_upah_penjahit_hidden').value = '';
                    document.getElementById('upah_per_potongan_penjahit_hidden').value = '';

                    // Toggle ATK section berdasarkan tipe produk
                    const atkCard = document.getElementById('atk-card');
                    if (tipeProduk === 'mukena') {
                        atkCard.style.display = 'block';
                        // Set required untuk input ATK
                        const atkInputs = document.querySelectorAll('#atk-container input');
                        atkInputs.forEach(input => input.required = true);
                    } else {
                        atkCard.style.display = 'none';
                        // Hapus required untuk input ATK
                        const atkInputs = document.querySelectorAll('#atk-container input');
                        atkInputs.forEach(input => input.required = false);
                    }

                    // Reset ATK container ke 1 item
                    const atkContainer = document.getElementById('atk-container');
                    const atkItems = document.querySelectorAll('.atk-item');

                    // Hapus semua item kecuali yang pertama
                    for (let i = atkItems.length - 1; i > 0; i--) {
                        atkItems[i].remove();
                    }

                    // Reset input pertama
                    const firstAtkItem = document.querySelector('.atk-item:first-child');
                    if (firstAtkItem) {
                        const inputs = firstAtkItem.querySelectorAll('input');
                        inputs.forEach(input => {
                            if (input.type === 'text') {
                                input.value = '';
                            } else if (input.type === 'number') {
                                input.value = '1';
                            }
                        });

                        const select = firstAtkItem.querySelector('select');
                        if (select) {
                            select.value = 'buah';
                        }

                        // Sembunyikan tombol hapus
                        const deleteBtn = firstAtkItem.querySelector('.btn-hapus-atk');
                        if (deleteBtn) {
                            deleteBtn.style.display = 'none';
                        }
                    }

                    modalHasilPenjahitan.show();
                }
            });

            // ========== PERBAIKAN UTAMA: EVENT LISTENER UNTUK TOMBOL BATAL TAHAP ==========
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-batal-produksi-tahap')) {
                    console.log('Tombol batal diklik!');

                    // HAPUS e.preventDefault() - ini penyebab masalah!
                    const button = e.target.closest('.btn-batal-produksi-tahap');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const totalBordir = button.getAttribute('data-total-bordir') || 0;
                    const totalJahit = button.getAttribute('data-total-jahit') || 0;
                    const tahap = button.getAttribute('data-tahap');
                    const status = button.getAttribute('data-status');
                    const pemotong = button.getAttribute('data-pemotong');
                    const bordir = button.getAttribute('data-bordir');
                    const penjahit = button.getAttribute('data-penjahit');

                    console.log('Data yang diterima:', {
                        id,
                        produk,
                        seri,
                        tahap,
                        totalJahit,
                        totalBordir
                    });

                    // Set data ke modal
                    document.getElementById('batal_id').value = id;
                    document.getElementById('batal_tahap').value = tahap;

                    // Buat detail berdasarkan tahap
                    let detail = '';
                    let keterangan = '';

                    switch (tahap) {
                        case 'hasil_jahit':
                            detail = `Apakah Anda yakin ingin membatalkan <strong>hasil jahit</strong> untuk:`;
                            detail += `<br><strong>Produk:</strong> ${produk}`;
                            detail += `<br><strong>Seri:</strong> ${seri}`;
                            detail += `<br><strong>Penjahit:</strong> ${penjahit || '-'}`;
                            detail += `<br><strong>Hasil Jahit:</strong> ${totalJahit} Pcs`;

                            keterangan = `<i class="ti ti-info-circle"></i> 
            <strong>Keterangan:</strong><br>
            1. Hasil jahit akan dihapus<br>
            2. Status akan kembali ke "Penjahitan"<br>
            3. Stok akan disesuaikan<br>
            4. Hutang upah penjahit akan dikurangi`;
                            break;

                        case 'tanggal_kirim_jahit':
                            detail = `Apakah Anda yakin ingin membatalkan <strong>tanggal kirim jahit</strong> untuk:`;
                            detail += `<br><strong>Produk:</strong> ${produk}`;
                            detail += `<br><strong>Seri:</strong> ${seri}`;
                            detail += `<br><strong>Penjahit:</strong> ${penjahit || '-'}`;

                            keterangan = `<i class="ti ti-info-circle"></i> 
            <strong>Keterangan:</strong><br>
            1. Tanggal kirim jahit akan dihapus<br>
            2. Data penjahit akan dihapus<br>
            3. Status akan kembali ke "Bordir"`;
                            break;

                        case 'hasil_bordir':
                            detail = `Apakah Anda yakin ingin membatalkan <strong>hasil bordir</strong> untuk:`;
                            detail += `<br><strong>Produk:</strong> ${produk}`;
                            detail += `<br><strong>Seri:</strong> ${seri}`;
                            detail += `<br><strong>Bordir:</strong> ${bordir || '-'}`;
                            detail += `<br><strong>Hasil Bordir:</strong> ${totalBordir} Pcs`;

                            keterangan = `<i class="ti ti-info-circle"></i> 
            <strong>Keterangan:</strong><br>
            1. Hasil bordir akan dihapus<br>
            2. Status akan kembali ke "Bordir"<br>
            3. Hutang upah bordir akan dikurangi`;
                            break;

                        case 'tanggal_kirim_bordir':
                            detail = `Apakah Anda yakin ingin membatalkan <strong>tanggal kirim bordir</strong> untuk:`;
                            detail += `<br><strong>Produk:</strong> ${produk}`;
                            detail += `<br><strong>Seri:</strong> ${seri}`;
                            detail += `<br><strong>Bordir:</strong> ${bordir || '-'}`;

                            keterangan = `<i class="ti ti-info-circle"></i> 
            <strong>Keterangan:</strong><br>
            1. Tanggal kirim bordir akan dihapus<br>
            2. Data bordir akan dihapus<br>
            3. Status akan kembali ke "Potong"`;
                            break;

                        case 'pemotongan':
                            detail = `Apakah Anda yakin ingin <strong>membatalkan produksi</strong> untuk:`;
                            detail += `<br><strong>Produk:</strong> ${produk}`;
                            detail += `<br><strong>Seri:</strong> ${seri}`;
                            detail += `<br><strong>Pemotong:</strong> ${pemotong || '-'}`;
                            detail += `<br><strong>Hasil Potong:</strong> ${totalPotong} Pcs`;

                            keterangan = `<i class="ti ti-info-circle"></i> 
        <strong>Keterangan:</strong><br>
        1. Semua data produksi akan dihapus<br>
        2. Stok bahan baku akan dikembalikan ke gudang<br>
        3. Hutang upah pemotong akan dikurangi<br>
        4. Data akan hilang permanen`;
                            break;
                    }

                    document.getElementById('batal_detail').innerHTML = detail;
                    document.getElementById('batal_keterangan').innerHTML = keterangan;

                    // Tampilkan modal
                    console.log('Menampilkan modal batal tahap...');
                    modalBatalTahap.show();
                }
            });

            // PERBAIKAN: Event listener untuk input upah bordir manual
            document.getElementById('upah_per_potongan_bordir_manual')?.addEventListener('input', function() {
                // Jika user input manual, reset dropdown
                if (this.value) {
                    document.getElementById('tarif_bordir_dropdown').selectedIndex = 0;
                }
                hitungTotalUpahBordir();
            });

            // PERBAIKAN: Event listener untuk total hasil bordir
            document.getElementById('modal_hasil_bordir_total')?.addEventListener('input', function() {
                hitungTotalUpahBordir();
            });

            // PERBAIKAN: Event listener untuk input upah penjahit manual
            document.getElementById('upah_per_potongan_manual')?.addEventListener('input', function() {
                // Jika user input manual, reset dropdown
                if (this.value) {
                    document.getElementById('tarif_penjahit_dropdown').selectedIndex = 0;
                }
                hitungTotalUpahPenjahit();
            });

            // PERBAIKAN: Event listener untuk total hasil jahit
            document.getElementById('modal_hasil_total_jahit')?.addEventListener('input', function() {
                hitungTotalUpahPenjahit();
            });

            // PERBAIKAN: Fungsi untuk menampilkan pesan sementara
            function showTemporaryMessage(message, type = 'success') {
                // Hapus pesan sebelumnya jika ada
                const existingAlert = document.querySelector('.temp-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }

                // Buat elemen alert baru
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} temp-alert alert-dismissible fade show position-fixed`;
                alertDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;

                alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

                document.body.appendChild(alertDiv);

                // Otomatis hilangkan setelah 3 detik
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 3000);
            }

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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const atkContainer = document.getElementById('atk-container');
            const atkCard = document.getElementById('atk-card');
            const btnTambahAtk = document.getElementById('btnTambahAtk');
            let atkCounter = 1;

            // Fungsi untuk mengatur tampilan ATK berdasarkan tipe produk
            function toggleAtkSection(tipeProduk) {
                if (tipeProduk === 'mukena') {
                    atkCard.style.display = 'block';
                    // Set required untuk input ATK pertama
                    const firstAtkItem = document.querySelector('.atk-item:first-child');
                    if (firstAtkItem) {
                        const atkNamaInput = firstAtkItem.querySelector('.atk-nama');
                        const atkJumlahInput = firstAtkItem.querySelector('.atk-jumlah');
                        if (atkNamaInput) atkNamaInput.required = true;
                        if (atkJumlahInput) atkJumlahInput.required = true;
                    }
                } else {
                    atkCard.style.display = 'none';
                    // Hapus required untuk semua input ATK
                    const allAtkNamaInputs = document.querySelectorAll('.atk-nama');
                    const allAtkJumlahInputs = document.querySelectorAll('.atk-jumlah');
                    allAtkNamaInputs.forEach(input => input.required = false);
                    allAtkJumlahInputs.forEach(input => input.required = false);
                }
            }

            // Fungsi untuk menambahkan item ATK baru
            function tambahAtkItem() {
                atkCounter++;

                const atkItem = document.createElement('div');
                atkItem.className = 'atk-item row mb-3';
                atkItem.innerHTML = `
            <div class="col-md-5">
                <label class="form-label">Nama ATK Finishing <span class="text-danger">*</span></label>
                <input type="text" name="atk_nama[]" class="form-control atk-nama"
                    placeholder="Contoh: Renda, Kancing, Tali, Label, dll." required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" name="atk_jumlah[]" class="form-control atk-jumlah"
                        min="1" value="1" required>
                    <span class="input-group-text">Pcs</span>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Satuan</label>
                <select name="atk_satuan[]" class="form-control atk-satuan">
                    <option value="meter">Meter</option>
                    <option value="buah" selected>Buah</option>
                    <option value="set">Set</option>
                    <option value="roll">Roll</option>
                    <option value="lbr">Lembar</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-danger btn-hapus-atk">
                    <i class="ti ti-trash"></i>
                </button>
            </div>
        `;

                atkContainer.appendChild(atkItem);

                // Tampilkan tombol hapus pada item pertama jika sudah ada lebih dari 1 item
                if (atkCounter > 1) {
                    const firstDeleteBtn = document.querySelector('.atk-item:first-child .btn-hapus-atk');
                    if (firstDeleteBtn) {
                        firstDeleteBtn.style.display = 'block';
                    }
                }
            }

            // Event listener untuk tombol tambah ATK
            btnTambahAtk.addEventListener('click', tambahAtkItem);

            // Event delegation untuk tombol hapus ATK
            atkContainer.addEventListener('click', function(e) {
                if (e.target.closest('.btn-hapus-atk')) {
                    const atkItem = e.target.closest('.atk-item');
                    const atkItems = document.querySelectorAll('.atk-item');

                    // Hanya hapus jika ada lebih dari 1 item
                    if (atkItems.length > 1) {
                        atkItem.remove();
                        atkCounter--;

                        // Sembunyikan tombol hapus pada item pertama jika hanya tersisa 1 item
                        if (atkCounter === 1) {
                            const firstDeleteBtn = document.querySelector('.atk-item:first-child .btn-hapus-atk');
                            if (firstDeleteBtn) {
                                firstDeleteBtn.style.display = 'none';
                            }
                        }
                    }
                }
            });

            // Reset ATK container saat modal ditutup
            document.getElementById('modalHasilPenjahitan')?.addEventListener('hidden.bs.modal', function() {
                // Reset ATK container ke 1 item
                const firstAtkItem = document.querySelector('.atk-item:first-child');

                // Kosongkan container
                if (atkContainer) {
                    atkContainer.innerHTML = '';

                    // Tambahkan kembali item pertama dengan nilai reset
                    if (firstAtkItem) {
                        // Clone item pertama
                        const clonedItem = firstAtkItem.cloneNode(true);

                        // Reset nilai
                        const inputs = clonedItem.querySelectorAll('input');
                        inputs.forEach(input => {
                            if (input.type === 'text') {
                                input.value = '';
                            } else if (input.type === 'number') {
                                input.value = '1';
                            }
                        });

                        // Reset select
                        const select = clonedItem.querySelector('select');
                        if (select) {
                            select.value = 'buah';
                        }

                        // Sembunyikan tombol hapus
                        const deleteBtn = clonedItem.querySelector('.btn-hapus-atk');
                        if (deleteBtn) {
                            deleteBtn.style.display = 'none';
                        }

                        atkContainer.appendChild(clonedItem);
                    }

                    atkCounter = 1;
                    // Sembunyikan ATK card secara default saat modal ditutup
                    if (atkCard) {
                        atkCard.style.display = 'none';
                    }
                }
            });

            // Sembunyikan ATK card secara default saat halaman dimuat
            if (atkCard) {
                atkCard.style.display = 'none';
            }
        });
    </script>

    <script>
        // Force remove all backdrops on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Hapus semua backdrop yang mungkin tersisa
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => {
                backdrop.remove();
            });

            // Reset body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';

            // Prevent Bootstrap from creating multiple instances
            if (typeof bootstrap !== 'undefined') {
                // Store original Modal
                const OriginalModal = bootstrap.Modal;

                // Override to prevent multiple instances
                bootstrap.Modal = function(element, config) {
                    const existingModal = bootstrap.Modal.getInstance(element);
                    if (existingModal) {
                        existingModal.dispose();
                    }
                    return new OriginalModal(element, config);
                };
                bootstrap.Modal.prototype = OriginalModal.prototype;
            }
        });
    </script>
</body>

</html>