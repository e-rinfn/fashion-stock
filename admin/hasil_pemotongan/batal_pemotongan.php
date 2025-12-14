<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

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

// ✅ FUNGSI BARU: untuk mengurangi hutang upah pemotong dengan validasi
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

// ✅ FUNGSI BARU: untuk mengembalikan stok bahan baku
// ✅ FUNGSI BARU: untuk mengembalikan stok bahan baku
function kembalikanStokBahanBaku($id_hasil_potong_fix)
{
    global $conn;

    try {
        // 1. Ambil semua detail bahan yang digunakan dalam produksi ini
        $sql_detail = "SELECT dh.*, b.nama_bahan, b.satuan
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

            if (($jumlah_digunakan > 0 || $total_meter > 0) && $id_bahan > 0) {
                // 2. Update stok bahan baku - TAMBAHKAN kembali jumlah yang digunakan
                // Perbaikan: field yang benar di tabel bahan_baku
                $sql_update_stok = "UPDATE bahan_baku 
                                   SET jumlah_stok = jumlah_stok + ?,
                                       jumlah_meter = jumlah_meter + ?,
                                       updated_at = NOW()
                                   WHERE id_bahan = ?";

                $stmt_update = $conn->prepare($sql_update_stok);
                // Perbaikan parameter binding: jumlah_digunakan (decimal), total_meter (decimal), id_bahan (integer)
                $stmt_update->bind_param("ddi", $jumlah_digunakan, $total_meter, $id_bahan);

                if (!$stmt_update->execute()) {
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
            }
        }

        return [
            'total' => $total_bahan_dikembalikan,
            'detail' => $detail_bahan_dikembalikan
        ];
    } catch (Exception $e) {
        throw new Exception("Gagal mengembalikan stok bahan baku: " . $e->getMessage());
    }
}

// Cek apakah ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID produksi tidak valid";
    header("Location: list.php");
    exit();
}

$id_hasil_potong_fix = intval($_GET['id']);

// Ambil data produksi sebelum dibatalkan
$produksi_data = query("SELECT 
    hp.*,
    p.nama_produk,
    p.tipe_produk,
    pem.nama_pemotong,
    pem.id_pemotong,
    pen.nama_penjahit,
    hp.tanggal_hasil_potong,
    hp.total_hasil,
    hp.seri,
    hp.total_hasil_jahit
FROM hasil_potong_fix hp
JOIN produk p ON hp.id_produk = p.id_produk 
JOIN pemotong pem ON hp.id_pemotong = pem.id_pemotong 
LEFT JOIN penjahit pen ON hp.id_penjahit = pen.id_penjahit 
WHERE hp.id_hasil_potong_fix = $id_hasil_potong_fix")[0];

if (!$produksi_data) {
    $_SESSION['error'] = "Data produksi tidak ditemukan";
    header("Location: list.php");
    exit();
}

$id_produk = $produksi_data['id_produk'];
$id_pemotong = $produksi_data['id_pemotong'];
$total_hasil_potong = $produksi_data['total_hasil'];
$total_hasil_jahit = $produksi_data['total_hasil_jahit'];
$seri = $produksi_data['seri'];
$tipe_produk = $produksi_data['tipe_produk'];
$tanggal_hasil_potong = $produksi_data['tanggal_hasil_potong'];
$status_potong = $produksi_data['status_potong'];

// Validasi: tidak bisa batal jika sudah ada hasil jahit
if (!empty($total_hasil_jahit) && $total_hasil_jahit > 0) {
    $_SESSION['error'] = "Tidak dapat membatalkan produksi karena sudah ada hasil jahit (" . $total_hasil_jahit . " Pcs). Batalkan hasil jahit terlebih dahulu.";
    header("Location: list.php");
    exit();
}

// Hitung upah pemotong yang akan dihapus
$tarif_pemotong = getTarifUpah('pemotongan', $tanggal_hasil_potong);
$upah_dihapus = $total_hasil_potong * $tarif_pemotong;

$conn->autocommit(FALSE);
try {
    // 1. Kembalikan stok bahan baku ke tabel bahan_baku
    $stok_dikembalikan = kembalikanStokBahanBaku($id_hasil_potong_fix);

    // 2. Hapus data detail hasil potong
    $sql_delete_detail = "DELETE FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
    if (!$conn->query($sql_delete_detail)) {
        throw new Exception("Gagal menghapus detail hasil potong: " . $conn->error);
    }

    // 3. Hapus data utama hasil potong
    $sql_delete_utama = "DELETE FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
    if (!$conn->query($sql_delete_utama)) {
        throw new Exception("Gagal menghapus data hasil potong: " . $conn->error);
    }

    // 4. Hapus/Update hutang upah pemotong (hanya jika ada upah)
    if ($upah_dihapus > 0 && $id_pemotong > 0) {
        if (!kurangiHutangUpahPemotong($id_pemotong, $upah_dihapus)) {
            throw new Exception("Gagal mengurangi hutang upah pemotong");
        }
    }

    $conn->commit();
    $conn->autocommit(TRUE);

    // Buat pesan detail bahan yang dikembalikan
    $pesan_bahan = "";
    if ($stok_dikembalikan['total'] > 0 && !empty($stok_dikembalikan['detail'])) {
        $pesan_bahan = " Bahan baku yang dikembalikan: ";
        $detail_items = [];
        foreach ($stok_dikembalikan['detail'] as $bahan) {
            $detail_items[] = $bahan['nama_bahan'] . " (" . $bahan['jumlah'] . " " . $bahan['satuan'] . ")";
        }
        $pesan_bahan .= implode(", ", $detail_items) . ".";
    }

    $_SESSION['success'] = "✅ Produksi seri $seri berhasil dibatalkan. " .
        ($upah_dihapus > 0 ? " Upah pemotong dikurangi: " . formatRupiah($upah_dihapus) . "." : "") .
        $pesan_bahan;
} catch (Exception $e) {
    $conn->rollback();
    $conn->autocommit(TRUE);
    $_SESSION['error'] = "❌ Gagal membatalkan produksi: " . $e->getMessage();
}

header("Location: list.php");
exit();
