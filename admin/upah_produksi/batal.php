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

    // Default value jika tidak ada tarif
    return 700.00;
}

// Cek apakah ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID produksi tidak valid";
    header("Location: list.php");
    exit();
}

$id_hasil_potong_fix = intval($_GET['id']);

// Ambil data produksi yang akan dibatalkan
$produksi_data = query("SELECT * FROM hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix")[0];

if (!$produksi_data) {
    $_SESSION['error'] = "Data produksi tidak ditemukan";
    header("Location: list.php");
    exit();
}

// Mulai transaksi
$conn->begin_transaction();

try {
    // 1. Ambil detail bahan yang digunakan untuk produksi ini
    $detail_bahan = query("SELECT * FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = $id_hasil_potong_fix");

    // 2. Kembalikan stok bahan baku
    foreach ($detail_bahan as $detail) {
        $id_bahan = $detail['id_bahan'];
        $jumlah_digunakan = $detail['jumlah'];

        $sql_kembalikan_stok = "UPDATE bahan_baku 
                               SET jumlah_stok = jumlah_stok + ? 
                               WHERE id_bahan = ?";
        $stmt_stok = $conn->prepare($sql_kembalikan_stok);
        $stmt_stok->bind_param("ii", $jumlah_digunakan, $id_bahan);

        if (!$stmt_stok->execute()) {
            throw new Exception("Gagal mengembalikan stok bahan ID: $id_bahan");
        }
        $stmt_stok->close();
    }

    // 3. Jika sudah ada penjahitan, kurangi stok produk
    if (!empty($produksi_data['total_hasil_jahit']) && $produksi_data['total_hasil_jahit'] > 0) {
        $sql_kurangi_stok_produk = "UPDATE produk 
                                  SET stok = stok - ? 
                                  WHERE id_produk = ?";
        $stmt_produk = $conn->prepare($sql_kurangi_stok_produk);
        $stmt_produk->bind_param("ii", $produksi_data['total_hasil_jahit'], $produksi_data['id_produk']);

        if (!$stmt_produk->execute()) {
            throw new Exception("Gagal mengurangi stok produk");
        }
        $stmt_produk->close();

        // 4. Hapus hutang upah penjahit jika ada
        if (!empty($produksi_data['id_penjahit'])) {
            $periode = date('Y-m-01', strtotime($produksi_data['tanggal_hasil_jahit']));
            $tarif_penjahit = getTarifUpah('penjahitan', $produksi_data['tanggal_hasil_jahit']);
            $upah_penjahit = $produksi_data['total_hasil_jahit'] * $tarif_penjahit;

            // Cari hutang yang terkait
            $check_hutang = $conn->prepare("SELECT id_hutang, total_upah, sisa_hutang FROM hutang_upah 
                                          WHERE id_karyawan = ? AND jenis_karyawan = 'penjahit' AND periode = ?");
            $check_hutang->bind_param("is", $produksi_data['id_penjahit'], $periode);
            $check_hutang->execute();
            $result_hutang = $check_hutang->get_result();

            if ($result_hutang->num_rows > 0) {
                $hutang = $result_hutang->fetch_assoc();
                $total_upah_baru = $hutang['total_upah'] - $upah_penjahit;
                $sisa_hutang_baru = $hutang['sisa_hutang'] - $upah_penjahit;

                if ($total_upah_baru <= 0) {
                    // Hapus record hutang jika total upah menjadi 0
                    $delete_hutang = $conn->prepare("DELETE FROM hutang_upah WHERE id_hutang = ?");
                    $delete_hutang->bind_param("i", $hutang['id_hutang']);
                    if (!$delete_hutang->execute()) {
                        throw new Exception("Gagal menghapus hutang upah penjahit");
                    }
                } else {
                    // Update hutang yang sudah ada
                    $update_hutang = $conn->prepare("UPDATE hutang_upah SET total_upah = ?, sisa_hutang = ? 
                                                  WHERE id_hutang = ?");
                    $update_hutang->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
                    if (!$update_hutang->execute()) {
                        throw new Exception("Gagal update hutang upah penjahit");
                    }
                }
            }
        }
    }

    // 5. Hapus hutang upah pemotong
    $periode_pemotong = date('Y-m-01', strtotime($produksi_data['tanggal_hasil_potong']));
    $tarif_pemotong = getTarifUpah('pemotongan', $produksi_data['tanggal_hasil_potong']);
    $upah_pemotong = $produksi_data['total_hasil'] * $tarif_pemotong;

    // Cari hutang pemotong yang terkait
    $check_hutang_pemotong = $conn->prepare("SELECT id_hutang, total_upah, sisa_hutang FROM hutang_upah 
                                           WHERE id_karyawan = ? AND jenis_karyawan = 'pemotong' AND periode = ?");
    $check_hutang_pemotong->bind_param("is", $produksi_data['id_pemotong'], $periode_pemotong);
    $check_hutang_pemotong->execute();
    $result_hutang_pemotong = $check_hutang_pemotong->get_result();

    if ($result_hutang_pemotong->num_rows > 0) {
        $hutang_pemotong = $result_hutang_pemotong->fetch_assoc();
        $total_upah_baru_pemotong = $hutang_pemotong['total_upah'] - $upah_pemotong;
        $sisa_hutang_baru_pemotong = $hutang_pemotong['sisa_hutang'] - $upah_pemotong;

        if ($total_upah_baru_pemotong <= 0) {
            // Hapus record hutang jika total upah menjadi 0
            $delete_hutang_pemotong = $conn->prepare("DELETE FROM hutang_upah WHERE id_hutang = ?");
            $delete_hutang_pemotong->bind_param("i", $hutang_pemotong['id_hutang']);
            if (!$delete_hutang_pemotong->execute()) {
                throw new Exception("Gagal menghapus hutang upah pemotong");
            }
        } else {
            // Update hutang yang sudah ada
            $update_hutang_pemotong = $conn->prepare("UPDATE hutang_upah SET total_upah = ?, sisa_hutang = ? 
                                                   WHERE id_hutang = ?");
            $update_hutang_pemotong->bind_param("ddi", $total_upah_baru_pemotong, $sisa_hutang_baru_pemotong, $hutang_pemotong['id_hutang']);
            if (!$update_hutang_pemotong->execute()) {
                throw new Exception("Gagal update hutang upah pemotong");
            }
        }
    }

    // 6. Hapus detail hasil potong
    $sql_delete_detail = "DELETE FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = ?";
    $stmt_detail = $conn->prepare($sql_delete_detail);
    $stmt_detail->bind_param("i", $id_hasil_potong_fix);

    if (!$stmt_detail->execute()) {
        throw new Exception("Gagal menghapus detail produksi");
    }
    $stmt_detail->close();

    // 7. Hapus data utama produksi
    $sql_delete_produksi = "DELETE FROM hasil_potong_fix WHERE id_hasil_potong_fix = ?";
    $stmt_produksi = $conn->prepare($sql_delete_produksi);
    $stmt_produksi->bind_param("i", $id_hasil_potong_fix);

    if (!$stmt_produksi->execute()) {
        throw new Exception("Gagal menghapus data produksi");
    }
    $stmt_produksi->close();

    // Commit transaksi
    $conn->commit();

    // Log aktivitas
    error_log("Produksi dibatalkan - ID: $id_hasil_potong_fix, Produk: {$produksi_data['id_produk']}, Seri: {$produksi_data['seri']}");

    $_SESSION['success'] = "Produksi berhasil dibatalkan. Stok bahan telah dikembalikan." .
        ($produksi_data['total_hasil_jahit'] > 0 ? " Stok produk dikurangi {$produksi_data['total_hasil_jahit']} pcs." : "");
} catch (Exception $e) {
    // Rollback transaksi jika terjadi error
    $conn->rollback();

    // Log error
    error_log("Error batal produksi - ID: $id_hasil_potong_fix - " . $e->getMessage());

    $_SESSION['error'] = "Gagal membatalkan produksi: " . $e->getMessage();
}

// Redirect kembali ke halaman list
header("Location: list.php");
exit();
