<?php
// Aktifkan error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Validasi parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID pengiriman finishing tidak valid";
    header("Location: finishing.php");
    exit();
}

$id_hasil_kirim_finishing = intval($_GET['id']);

// ✅ FUNGSI BARU: untuk membatalkan hasil finishing koko
function batalkanHasilFinishingKoko($id_hasil_kirim_finishing, $tanggal_finishing = null)
{
    global $conn;

    try {
        // 1. Ambil data hasil finishing koko yang akan dibatalkan
        if ($tanggal_finishing) {
            // Batalkan berdasarkan tanggal tertentu
            $sql_data = "SELECT 
                dhfk.*,
                dh.id_hasil_kirim_finishing,
                dh.id_koko,
                dh.jumlah as jumlah_dikirim,
                k.nama_koko,
                k.id_produk as id_produk_koko,
                p.nama_produk as nama_produk_koko,
                hk.id_produk as id_produk_utama,
                hk.total_hasil_finishing,
                hk.id_petugas_finishing
            FROM detail_hasil_finishing_koko dhfk
            JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
            JOIN koko k ON dhfk.id_koko = k.id_koko
            LEFT JOIN produk p ON k.id_produk = p.id_produk
            JOIN hasil_kirim_finishing hk ON dh.id_hasil_kirim_finishing = hk.id_hasil_kirim_finishing
            WHERE dh.id_hasil_kirim_finishing = ? 
            AND dhfk.tanggal_finishing = ?";

            $stmt = $conn->prepare($sql_data);
            $stmt->bind_param("is", $id_hasil_kirim_finishing, $tanggal_finishing);
        } else {
            // Batalkan semua hasil finishing untuk pengiriman ini
            $sql_data = "SELECT 
                dhfk.*,
                dh.id_hasil_kirim_finishing,
                dh.id_koko,
                dh.jumlah as jumlah_dikirim,
                k.nama_koko,
                k.id_produk as id_produk_koko,
                p.nama_produk as nama_produk_koko,
                hk.id_produk as id_produk_utama,
                hk.total_hasil_finishing,
                hk.id_petugas_finishing
            FROM detail_hasil_finishing_koko dhfk
            JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
            JOIN koko k ON dhfk.id_koko = k.id_koko
            LEFT JOIN produk p ON k.id_produk = p.id_produk
            JOIN hasil_kirim_finishing hk ON dh.id_hasil_kirim_finishing = hk.id_hasil_kirim_finishing
            WHERE dh.id_hasil_kirim_finishing = ?";

            $stmt = $conn->prepare($sql_data);
            $stmt->bind_param("i", $id_hasil_kirim_finishing);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Tidak ada data hasil finishing koko untuk dibatalkan");
        }

        $results_data = [];
        $total_selesai_dibatalkan = 0;
        $total_rusak_dibatalkan = 0;
        $total_upah_dibatalkan = 0;
        $stok_koko_dikembalikan = [];
        $produk_dikurangi = [];
        $id_petugas_finishing = null;

        while ($row = $result->fetch_assoc()) {
            $results_data[] = $row;

            $total_selesai_dibatalkan += $row['jumlah_selesai'];
            $total_rusak_dibatalkan += $row['jumlah_rusak'];
            $total_upah_dibatalkan += $row['total_upah'];

            // Kumpulkan data untuk pengembalian stok koko
            $stok_koko_dikembalikan[] = [
                'id_koko' => $row['id_koko'],
                'nama_koko' => $row['nama_koko'],
                'jumlah_selesai' => $row['jumlah_selesai'],
                'jumlah_rusak' => $row['jumlah_rusak']
            ];

            // Kumpulkan data untuk pengurangan stok produk
            if ($row['jumlah_selesai'] > 0 && $row['id_produk_koko'] > 0) {
                if (!isset($produk_dikurangi[$row['id_produk_koko']])) {
                    $produk_dikurangi[$row['id_produk_koko']] = [
                        'nama' => $row['nama_produk_koko'],
                        'jumlah' => 0
                    ];
                }
                $produk_dikurangi[$row['id_produk_koko']]['jumlah'] += $row['jumlah_selesai'];
            }

            $id_petugas_finishing = $row['id_petugas_finishing'];
        }

        $conn->autocommit(FALSE);

        // 2. Kembalikan stok koko (selesai + rusak)
        foreach ($stok_koko_dikembalikan as $koko) {
            $total_kembali = $koko['jumlah_rusak'];
            if ($total_kembali > 0) {
                $sql_update_koko = "UPDATE koko SET stok = stok - ? WHERE id_koko = ?";
                $stmt_koko = $conn->prepare($sql_update_koko);
                $stmt_koko->bind_param("ii", $total_kembali, $koko['id_koko']);

                if (!$stmt_koko->execute()) {
                    throw new Exception("Gagal mengembalikan stok koko " . $koko['nama_koko'] . ": " . $conn->error);
                }
            }
        }

        // 3. Kurangi stok produk yang sudah ditambahkan
        foreach ($produk_dikurangi as $id_produk => $data) {
            if ($data['jumlah'] > 0) {
                $sql_update_produk = "UPDATE produk SET stok = stok - ? WHERE id_produk = ?";
                $stmt_produk = $conn->prepare($sql_update_produk);
                $stmt_produk->bind_param("ii", $data['jumlah'], $id_produk);

                if (!$stmt_produk->execute()) {
                    throw new Exception("Gagal mengurangi stok produk " . $data['nama'] . ": " . $conn->error);
                }
            }
        }

        // 4. Kurangi stok produk utama jika ada
        if (!empty($results_data) && $results_data[0]['id_produk_utama'] > 0 && $total_selesai_dibatalkan > 0) {
            // Cek apakah produk utama sudah dikurangi dari produk koko
            $produk_utama_sudah_dikurangi = false;
            foreach ($produk_dikurangi as $id_produk => $data) {
                if ($id_produk == $results_data[0]['id_produk_utama']) {
                    $produk_utama_sudah_dikurangi = true;
                    break;
                }
            }

            // Jika produk utama belum dikurangi, kurangi sekarang
            if (!$produk_utama_sudah_dikurangi) {
                $sql_update_produk_utama = "UPDATE produk SET stok = stok - ? WHERE id_produk = ?";
                $stmt_produk_utama = $conn->prepare($sql_update_produk_utama);
                $stmt_produk_utama->bind_param("ii", $total_selesai_dibatalkan, $results_data[0]['id_produk_utama']);

                if (!$stmt_produk_utama->execute()) {
                    throw new Exception("Gagal mengurangi stok produk utama: " . $conn->error);
                }
            }
        }

        // 5. Hapus data hasil finishing koko
        if ($tanggal_finishing) {
            $sql_delete = "DELETE FROM detail_hasil_finishing_koko 
                          WHERE id_detail_hasil_kirim_finishing IN (
                              SELECT id_detail_hasil_kirim_finishing 
                              FROM detail_hasil_kirim_finishing 
                              WHERE id_hasil_kirim_finishing = ?
                          ) AND tanggal_finishing = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("is", $id_hasil_kirim_finishing, $tanggal_finishing);
        } else {
            $sql_delete = "DELETE FROM detail_hasil_finishing_koko 
                          WHERE id_detail_hasil_kirim_finishing IN (
                              SELECT id_detail_hasil_kirim_finishing 
                              FROM detail_hasil_kirim_finishing 
                              WHERE id_hasil_kirim_finishing = ?
                          )";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $id_hasil_kirim_finishing);
        }

        if (!$stmt_delete->execute()) {
            throw new Exception("Gagal menghapus data hasil finishing koko: " . $conn->error);
        }

        // 6. Update status dan total hasil di tabel utama
        // Hitung ulang total hasil yang masih ada
        $sql_total_sisa = "SELECT 
            COALESCE(SUM(dhfk.jumlah_selesai), 0) as total_selesai_sisa,
            COALESCE(SUM(dhfk.jumlah_rusak), 0) as total_rusak_sisa
            FROM detail_hasil_finishing_koko dhfk
            JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
            WHERE dh.id_hasil_kirim_finishing = ?";

        $stmt_total = $conn->prepare($sql_total_sisa);
        $stmt_total->bind_param("i", $id_hasil_kirim_finishing);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $totals_sisa = $result_total->fetch_assoc();

        $total_selesai_sisa = $totals_sisa['total_selesai_sisa'];

        // Update status berdasarkan apakah masih ada hasil finishing
        $status_baru = ($total_selesai_sisa > 0) ? 'diproses' : 'pengiriman';

        $sql_update_status = "UPDATE hasil_kirim_finishing 
                             SET status_finishing = ?,
                                 tanggal_hasil_finishing = CASE WHEN ? > 0 THEN NOW() ELSE NULL END,
                                 total_hasil_finishing = ?
                             WHERE id_hasil_kirim_finishing = ?";
        $stmt_status = $conn->prepare($sql_update_status);
        $stmt_status->bind_param("siii", $status_baru, $total_selesai_sisa, $total_selesai_sisa, $id_hasil_kirim_finishing);

        if (!$stmt_status->execute()) {
            throw new Exception("Gagal update status finishing: " . $conn->error);
        }

        // 7. Kurangi hutang upah petugas
        if ($total_upah_dibatalkan > 0 && $id_petugas_finishing > 0) {
            $sql_check_hutang = "SELECT id_hutang FROM hutang_upah 
                                WHERE id_karyawan = ? AND jenis_karyawan = 'finishing' 
                                LIMIT 1";
            $stmt_hutang = $conn->prepare($sql_check_hutang);
            $stmt_hutang->bind_param("i", $id_petugas_finishing);
            $stmt_hutang->execute();
            $result_hutang = $stmt_hutang->get_result();

            if ($result_hutang->num_rows > 0) {
                $hutang = $result_hutang->fetch_assoc();
                $sql_update_hutang = "UPDATE hutang_upah 
                                     SET total_upah = GREATEST(0, total_upah - ?),
                                         sisa_hutang = GREATEST(0, sisa_hutang - ?),
                                         updated_at = NOW()
                                     WHERE id_hutang = ?";
                $stmt_update_hutang = $conn->prepare($sql_update_hutang);
                $stmt_update_hutang->bind_param("ddi", $total_upah_dibatalkan, $total_upah_dibatalkan, $hutang['id_hutang']);

                if (!$stmt_update_hutang->execute()) {
                    throw new Exception("Gagal mengurangi hutang upah: " . $conn->error);
                }
            }
        }

        $conn->commit();
        $conn->autocommit(TRUE);

        // 8. Siapkan pesan sukses
        $pesan_koko = "";
        if (!empty($stok_koko_dikembalikan)) {
            $pesan_koko = "<br><strong>Stok koko yang dikembalikan:</strong><br>";
            foreach ($stok_koko_dikembalikan as $koko) {
                $total = $koko['jumlah_selesai'] + $koko['jumlah_rusak'];
                if ($total > 0) {
                    $pesan_koko .= "- " . $koko['nama_koko'] . ": " . $total . " pcs (selesai: " . $koko['jumlah_selesai'] . ", rusak: " . $koko['jumlah_rusak'] . ")<br>";
                }
            }
        }

        $pesan_produk = "";
        if (!empty($produk_dikurangi)) {
            $pesan_produk = "<br><strong>Stok produk yang dikurangi:</strong><br>";
            foreach ($produk_dikurangi as $id_produk => $data) {
                if ($data['jumlah'] > 0) {
                    $pesan_produk .= "- " . $data['nama'] . ": " . $data['jumlah'] . " pcs<br>";
                }
            }
        }

        return [
            'success' => true,
            'message' => "✅ Hasil finishing koko berhasil dibatalkan!<br>" .
                "<strong>Total Selesai Dibatalkan:</strong> " . $total_selesai_dibatalkan . " pcs<br>" .
                "<strong>Total Rusak Dibatalkan:</strong> " . $total_rusak_dibatalkan . " pcs<br>" .
                "<strong>Total Upah Dibatalkan:</strong> " . formatRupiah($total_upah_dibatalkan) . "<br>" .
                "<strong>Status Baru:</strong> " . $status_baru . "<br>" .
                $pesan_koko . $pesan_produk
        ];
    } catch (Exception $e) {
        if ($conn->autocommit === FALSE) {
            $conn->rollback();
            $conn->autocommit(TRUE);
        }
        throw new Exception("Gagal membatalkan hasil finishing koko: " . $e->getMessage());
    }
}

// Ambil data utama pengiriman finishing
$sql_main = "SELECT 
    hk.*,
    p.nama_produk,
    p.id_produk as id_produk_utama,
    pet.nama_petugas,
    pet.id_petugas_finishing,
    hk.tanggal_kirim_finishing,
    hk.seri,
    hk.status_finishing,
    hk.total_kirim
FROM hasil_kirim_finishing hk
LEFT JOIN produk p ON hk.id_produk = p.id_produk 
LEFT JOIN petugas_finishing pet ON hk.id_petugas_finishing = pet.id_petugas_finishing 
WHERE hk.id_hasil_kirim_finishing = ?";

$stmt = $conn->prepare($sql_main);
$stmt->bind_param("i", $id_hasil_kirim_finishing);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data pengiriman finishing tidak ditemukan";
    header("Location: finishing.php");
    exit();
}

$main_data = $result->fetch_assoc();

// ✅ PROSES PEMBATALAN HASIL FINISHING KOKO
if (isset($_GET['action']) && $_GET['action'] == 'batal_hasil_koko') {
    $tanggal_finishing = isset($_GET['tanggal']) ? $_GET['tanggal'] : null;

    try {
        $result = batalkanHasilFinishingKoko($id_hasil_kirim_finishing, $tanggal_finishing);
        $_SESSION['success'] = $result['message'];
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ " . $e->getMessage();
    }

    header("Location: hasil_finishing_koko.php?id=" . $id_hasil_kirim_finishing);
    exit();
}

// Ambil semua detail koko yang dikirim
$sql_details = "SELECT 
    dh.*,
    k.nama_koko,
    k.stok as stok_koko,
    k.id_produk as id_produk_koko,
    p.nama_produk as nama_produk_koko,
    p2.id_produk as id_produk_finishing,
    p2.nama_produk as nama_produk_finishing,
    -- Ambil petugas finishing dari pengiriman utama
    pf.nama_petugas as nama_petugas_finishing,
    hk.id_petugas_finishing as id_petugas_finishing_utama,
    -- Hitung total yang sudah diselesaikan sebelumnya
    COALESCE(SUM(dhfk.jumlah_selesai), 0) as sudah_selesai,
    COALESCE(SUM(dhfk.jumlah_rusak), 0) as sudah_rusak,
    COALESCE(dhfk.upah_per_unit, 0) as upah_sebelumnya
FROM detail_hasil_kirim_finishing dh
JOIN koko k ON dh.id_koko = k.id_koko
LEFT JOIN produk p ON k.id_produk = p.id_produk
LEFT JOIN produk p2 ON k.id_produk = p2.id_produk
JOIN hasil_kirim_finishing hk ON dh.id_hasil_kirim_finishing = hk.id_hasil_kirim_finishing
LEFT JOIN petugas_finishing pf ON hk.id_petugas_finishing = pf.id_petugas_finishing
LEFT JOIN detail_hasil_finishing_koko dhfk ON dh.id_detail_hasil_kirim_finishing = dhfk.id_detail_hasil_kirim_finishing
WHERE dh.id_hasil_kirim_finishing = ?
GROUP BY dh.id_detail_hasil_kirim_finishing
ORDER BY k.nama_koko";

$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $id_hasil_kirim_finishing);
$stmt_details->execute();
$details_result = $stmt_details->get_result();
$details_data = [];

while ($row = $details_result->fetch_assoc()) {
    $details_data[] = $row;
}

// Ambil daftar tanggal finishing yang sudah ada
$sql_tanggal_finishing = "SELECT DISTINCT dhfk.tanggal_finishing,
    SUM(dhfk.jumlah_selesai) as total_selesai,
    SUM(dhfk.jumlah_rusak) as total_rusak,
    SUM(dhfk.total_upah) as total_upah
FROM detail_hasil_finishing_koko dhfk
JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
WHERE dh.id_hasil_kirim_finishing = ?
GROUP BY dhfk.tanggal_finishing
ORDER BY dhfk.tanggal_finishing DESC";

$stmt_tanggal = $conn->prepare($sql_tanggal_finishing);
$stmt_tanggal->bind_param("i", $id_hasil_kirim_finishing);
$stmt_tanggal->execute();
$tanggal_result = $stmt_tanggal->get_result();
$tanggal_finishing_data = [];

while ($row = $tanggal_result->fetch_assoc()) {
    $tanggal_finishing_data[] = $row;
}

// Ambil tarif upah standar untuk finishing dari tabel tarif_upah
$sql_tarif = "SELECT tarif_per_unit FROM tarif_upah 
             WHERE jenis_tarif = 'finishing' 
             ORDER BY berlaku_sejak DESC LIMIT 1";
$tarif_result = $conn->query($sql_tarif);
$tarif_standar = ($tarif_result->num_rows > 0) ? $tarif_result->fetch_assoc()['tarif_per_unit'] : 0;

// AMBIL DAFTAR TARIF UPAH DARI TABEL tarif_upah UNTUK DROPDOWN
$sql_upah_dropdown = "SELECT tarif_per_unit, berlaku_sejak, keterangan
                     FROM tarif_upah 
                     WHERE jenis_tarif = 'finishing'
                     ORDER BY berlaku_sejak DESC, tarif_per_unit DESC";
$upah_dropdown_result = $conn->query($sql_upah_dropdown);
$upah_dropdown_options = [];

while ($row = $upah_dropdown_result->fetch_assoc()) {
    $upah_dropdown_options[] = [
        'tarif_per_unit' => $row['tarif_per_unit'] ?? 0,
        'berlaku_sejak' => $row['berlaku_sejak'] ?? date('Y-m-d'),
        'keterangan' => $row['keterangan'] ?? 'Tanpa Keterangan'
    ];
}

// Proses submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_finishing_koko'])) {
    $tanggal_finishing = $_POST['tanggal_finishing'];
    $id_petugas_finishing = $main_data['id_petugas_finishing']; // Petugas dari pengiriman utama
    $id_produk_utama = $main_data['id_produk_utama'];

    // Validasi tanggal
    if (empty($tanggal_finishing)) {
        $_SESSION['error'] = "Tanggal finishing harus diisi";
        header("Location: hasil_finishing_koko.php?id=" . $id_hasil_kirim_finishing);
        exit();
    }

    $conn->autocommit(FALSE);
    $total_selesai = 0;
    $total_rusak = 0;
    $total_upah = 0;
    $stok_dikembalikan = [];
    $produk_ditambahkan = [];
    $stok_rusak_dikembalikan = []; // Array untuk menyimpan stok rusak yang dikembalikan

    try {
        foreach ($details_data as $detail) {
            $id_detail = $detail['id_detail_hasil_kirim_finishing'];
            $id_koko = $detail['id_koko'];
            $nama_koko = $detail['nama_koko'];
            $id_produk_koko = $detail['id_produk_koko'];
            $nama_produk_koko = $detail['nama_produk_koko'];
            $jumlah_kirim = $detail['jumlah'];
            $sudah_selesai = $detail['sudah_selesai'];
            $sudah_rusak = $detail['sudah_rusak'];

            // Gunakan petugas finishing dari pengiriman utama (TIDAK BISA DIUBAH)
            $id_petugas_koko = $main_data['id_petugas_finishing']; // SELALU gunakan petugas dari pengiriman utama

            // Ambil upah per unit (dropdown atau input manual)
            $upah_per_unit = 0;
            $upah_input_type = $_POST['upah_input_type_' . $id_detail] ?? 'dropdown';

            if ($upah_input_type == 'manual') {
                $upah_per_unit = floatval($_POST['upah_manual_' . $id_detail] ?? 0);
            } else {
                $upah_per_unit = floatval($_POST['upah_dropdown_' . $id_detail] ?? $tarif_standar);
            }

            $jumlah_selesai = intval($_POST['jumlah_selesai_' . $id_detail] ?? 0);
            $jumlah_rusak = intval($_POST['jumlah_rusak_' . $id_detail] ?? 0);

            // Validasi: total input tidak boleh melebihi yang dikirim
            $total_input = $jumlah_selesai + $jumlah_rusak + $sudah_selesai + $sudah_rusak;
            if ($total_input > $jumlah_kirim) {
                throw new Exception("Total input untuk koko <strong>$nama_koko</strong> ($total_input) melebihi jumlah yang dikirim ($jumlah_kirim). Sudah selesai: $sudah_selesai, Sudah rusak: $sudah_rusak");
            }

            // Hitung sisa yang bisa dikembalikan ke stok koko
            $sisa_koko = $jumlah_kirim - ($sudah_selesai + $sudah_rusak + $jumlah_selesai + $jumlah_rusak);

            // Insert atau update data finishing koko
            if ($jumlah_selesai > 0 || $jumlah_rusak > 0) {
                $sql_check_existing = "SELECT id_detail_hasil_finishing_koko 
                          FROM detail_hasil_finishing_koko 
                          WHERE id_detail_hasil_kirim_finishing = ? 
                          AND tanggal_finishing = ?";
                $stmt_check = $conn->prepare($sql_check_existing);
                $stmt_check->bind_param("is", $id_detail, $tanggal_finishing);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($result_check->num_rows > 0) {
                    // Update existing
                    $existing = $result_check->fetch_assoc();

                    // Hitung upah tambahan
                    $additional_upah = $jumlah_selesai * $upah_per_unit;

                    // HAPUS updated_at dari query karena tidak ada di tabel
                    $sql_update = "UPDATE detail_hasil_finishing_koko 
                      SET jumlah_selesai = jumlah_selesai + ?,
                          jumlah_rusak = jumlah_rusak + ?,
                          upah_per_unit = ?,
                          total_upah = total_upah + ?
                      WHERE id_detail_hasil_finishing_koko = ?";

                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param(
                        "iiddi",  // i = integer, d = double/decimal
                        $jumlah_selesai,
                        $jumlah_rusak,
                        $upah_per_unit,
                        $additional_upah,
                        $existing['id_detail_hasil_finishing_koko']
                    );

                    if (!$stmt_update->execute()) {
                        throw new Exception("Gagal update data finishing koko: " . $stmt_update->error);
                    }
                } else {
                    // Insert new
                    $total_upah_item = $jumlah_selesai * $upah_per_unit;
                    $sql_insert = "INSERT INTO detail_hasil_finishing_koko 
                      (id_detail_hasil_kirim_finishing, id_koko, 
                       jumlah_selesai, jumlah_rusak, 
                       tanggal_finishing, upah_per_unit, total_upah, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param(
                        "iiiisdd",
                        $id_detail,
                        $id_koko,
                        $jumlah_selesai,
                        $jumlah_rusak,
                        $tanggal_finishing,
                        $upah_per_unit,
                        $total_upah_item
                    );

                    if (!$stmt_insert->execute()) {
                        throw new Exception("Gagal menyimpan data finishing koko: " . $stmt_insert->error);
                    }
                }

                $total_selesai += $jumlah_selesai;
                $total_rusak += $jumlah_rusak;
                $total_upah += ($jumlah_selesai * $upah_per_unit);

                // Tambahkan hasil finishing ke stok produk yang sesuai
                if ($jumlah_selesai > 0 && $id_produk_koko > 0) {
                    // Cek apakah produk sudah ada dalam array
                    if (!isset($produk_ditambahkan[$id_produk_koko])) {
                        $produk_ditambahkan[$id_produk_koko] = [
                            'nama' => $nama_produk_koko,
                            'jumlah' => 0
                        ];
                    }
                    $produk_ditambahkan[$id_produk_koko]['jumlah'] += $jumlah_selesai;
                }

                // Tambahkan upah ke hutang petugas finishing (petugas dari pengiriman utama)
                if ($jumlah_selesai > 0 && $id_petugas_koko > 0) {
                    $upah_koko = $jumlah_selesai * $upah_per_unit;

                    // Cek apakah sudah ada hutang untuk petugas ini
                    $sql_check_hutang = "SELECT id_hutang FROM hutang_upah 
                                        WHERE id_karyawan = ? AND jenis_karyawan = 'finishing' 
                                        LIMIT 1";
                    $stmt_hutang = $conn->prepare($sql_check_hutang);
                    $stmt_hutang->bind_param("i", $id_petugas_koko);
                    $stmt_hutang->execute();
                    $result_hutang = $stmt_hutang->get_result();

                    if ($result_hutang->num_rows > 0) {
                        // Update existing
                        $hutang = $result_hutang->fetch_assoc();
                        $sql_update_hutang = "UPDATE hutang_upah 
                                             SET total_upah = total_upah + ?,
                                                 sisa_hutang = sisa_hutang + ?,
                                                 updated_at = NOW()
                                             WHERE id_hutang = ?";
                        $stmt_update_hutang = $conn->prepare($sql_update_hutang);
                        $stmt_update_hutang->bind_param("ddi", $upah_koko, $upah_koko, $hutang['id_hutang']);

                        if (!$stmt_update_hutang->execute()) {
                            throw new Exception("Gagal update hutang upah petugas: " . $conn->error);
                        }
                    } else {
                        // Insert new
                        $sql_insert_hutang = "INSERT INTO hutang_upah 
                                             (id_karyawan, jenis_karyawan, total_upah, sisa_hutang, created_at)
                                             VALUES (?, 'finishing', ?, ?, NOW())";
                        $stmt_insert_hutang = $conn->prepare($sql_insert_hutang);
                        $stmt_insert_hutang->bind_param("idd", $id_petugas_koko, $upah_koko, $upah_koko);

                        if (!$stmt_insert_hutang->execute()) {
                            throw new Exception("Gagal menambahkan hutang upah petugas: " . $conn->error);
                        }
                    }
                }
            }

            // Kembalikan sisa koko ke stok (termasuk yang rusak)
            if ($sisa_koko > 0) {
                $sql_update_stok = "UPDATE koko SET stok = stok + ? WHERE id_koko = ?";
                $stmt_stok = $conn->prepare($sql_update_stok);
                $stmt_stok->bind_param("ii", $sisa_koko, $id_koko);

                if (!$stmt_stok->execute()) {
                    throw new Exception("Gagal mengembalikan stok koko $nama_koko: " . $conn->error);
                }

                $stok_dikembalikan[] = [
                    'nama' => $nama_koko,
                    'jumlah' => $sisa_koko
                ];
            }

            // Kembalikan stok koko rusak ke tabel koko
            if ($jumlah_rusak > 0) {
                $sql_update_koko_rusak = "UPDATE koko SET stok = stok + ? WHERE id_koko = ?";
                $stmt_koko_rusak = $conn->prepare($sql_update_koko_rusak);
                $stmt_koko_rusak->bind_param("ii", $jumlah_rusak, $id_koko);

                if (!$stmt_koko_rusak->execute()) {
                    throw new Exception("Gagal mengembalikan stok rusak koko $nama_koko: " . $conn->error);
                }

                $stok_rusak_dikembalikan[] = [
                    'nama' => $nama_koko,
                    'jumlah' => $jumlah_rusak
                ];
            }
        }

        // Tambahkan hasil finishing ke stok produk
        foreach ($produk_ditambahkan as $id_produk => $data) {
            $jumlah_tambahan = $data['jumlah'];
            $nama_produk = $data['nama'];

            $sql_update_produk = "UPDATE produk SET stok = stok + ? WHERE id_produk = ?";
            $stmt_produk = $conn->prepare($sql_update_produk);
            $stmt_produk->bind_param("ii", $jumlah_tambahan, $id_produk);

            if (!$stmt_produk->execute()) {
                throw new Exception("Gagal menambahkan stok produk $nama_produk: " . $conn->error);
            }
        }

        // Juga tambahkan ke produk utama jika berbeda
        if ($id_produk_utama > 0 && $total_selesai > 0) {
            // Cek apakah produk utama sudah dihitung dari koko
            $produk_utama_sudah_dihitung = false;
            foreach ($produk_ditambahkan as $id_produk => $data) {
                if ($id_produk == $id_produk_utama) {
                    $produk_utama_sudah_dihitung = true;
                    break;
                }
            }

            // Jika produk utama belum dihitung, tambahkan sekarang
            if (!$produk_utama_sudah_dihitung) {
                $sql_update_produk_utama = "UPDATE produk SET stok = stok + ? WHERE id_produk = ?";
                $stmt_produk_utama = $conn->prepare($sql_update_produk_utama);
                $stmt_produk_utama->bind_param("ii", $total_selesai, $id_produk_utama);

                if (!$stmt_produk_utama->execute()) {
                    throw new Exception("Gagal menambahkan stok produk utama: " . $conn->error);
                }

                $produk_ditambahkan[$id_produk_utama] = [
                    'nama' => $main_data['nama_produk'],
                    'jumlah' => $total_selesai
                ];
            }
        }

        // Update total hasil di tabel utama jika semua sudah selesai
        $sql_total_selesai = "SELECT 
            COALESCE(SUM(jumlah_selesai), 0) as total_selesai,
            COALESCE(SUM(jumlah_rusak), 0) as total_rusak
            FROM detail_hasil_finishing_koko dhfk
            JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
            WHERE dh.id_hasil_kirim_finishing = ?";

        $stmt_total = $conn->prepare($sql_total_selesai);
        $stmt_total->bind_param("i", $id_hasil_kirim_finishing);
        $stmt_total->execute();
        $result_total = $stmt_total->get_result();
        $totals = $result_total->fetch_assoc();

        $total_selesai_all = $totals['total_selesai'];
        $total_rusak_all = $totals['total_rusak'];

        // Cek apakah semua koko sudah diproses
        $sql_check_all = "SELECT 
            SUM(dh.jumlah) as total_dikirim,
            COALESCE(SUM(dhfk.jumlah_selesai + dhfk.jumlah_rusak), 0) as total_diproses
            FROM detail_hasil_kirim_finishing dh
            LEFT JOIN detail_hasil_finishing_koko dhfk ON dh.id_detail_hasil_kirim_finishing = dhfk.id_detail_hasil_kirim_finishing
            WHERE dh.id_hasil_kirim_finishing = ?";

        $stmt_all = $conn->prepare($sql_check_all);
        $stmt_all->bind_param("i", $id_hasil_kirim_finishing);
        $stmt_all->execute();
        $result_all = $stmt_all->get_result();
        $all_data = $result_all->fetch_assoc();

        // Update status jika semua sudah diproses
        $status_baru = ($all_data['total_diproses'] >= $all_data['total_dikirim']) ? 'selesai' : 'diproses';

        $sql_update_status = "UPDATE hasil_kirim_finishing 
                             SET status_finishing = ?,
                                 tanggal_hasil_finishing = NOW(),
                                 total_hasil_finishing = ?
                             WHERE id_hasil_kirim_finishing = ?";
        $stmt_status = $conn->prepare($sql_update_status);
        $stmt_status->bind_param("sii", $status_baru, $total_selesai_all, $id_hasil_kirim_finishing);

        if (!$stmt_status->execute()) {
            throw new Exception("Gagal update status finishing: " . $conn->error);
        }

        $conn->commit();
        $conn->autocommit(TRUE);

        // Buat pesan stok yang dikembalikan
        $pesan_stok = "";
        if (!empty($stok_dikembalikan)) {
            $pesan_stok = "<br><strong>Stok koko sisa yang dikembalikan:</strong><br>";
            foreach ($stok_dikembalikan as $koko) {
                $pesan_stok .= "- " . $koko['nama'] . ": " . $koko['jumlah'] . " pcs<br>";
            }
        }

        // Buat pesan stok rusak yang dikembalikan
        $pesan_rusak = "";
        if (!empty($stok_rusak_dikembalikan)) {
            $pesan_rusak = "<br><strong>Stok koko rusak yang dikembalikan:</strong><br>";
            foreach ($stok_rusak_dikembalikan as $koko) {
                $pesan_rusak .= "- " . $koko['nama'] . ": " . $koko['jumlah'] . " pcs<br>";
            }
        }

        // Buat pesan produk yang ditambahkan
        $pesan_produk = "";
        if (!empty($produk_ditambahkan)) {
            $pesan_produk = "<br><strong>Stok produk yang ditambahkan:</strong><br>";
            foreach ($produk_ditambahkan as $id_produk => $data) {
                $pesan_produk .= "- " . $data['nama'] . ": " . $data['jumlah'] . " pcs<br>";
            }
        }

        $_SESSION['success'] = "✅ Hasil finishing koko berhasil disimpan!<br>" .
            "<strong>Total Selesai:</strong> " . $total_selesai . " pcs<br>" .
            "<strong>Total Rusak:</strong> " . $total_rusak . " pcs<br>" .
            "<strong>Total Upah:</strong> " . formatRupiah($total_upah) . "<br>" .
            "<strong>Petugas:</strong> " . htmlspecialchars($main_data['nama_petugas']) . "<br>" .
            $pesan_stok . $pesan_rusak . $pesan_produk;

        header("Location: hasil_finishing_koko.php?id=" . $id_hasil_kirim_finishing);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(TRUE);
        $_SESSION['error'] = "❌ Gagal menyimpan hasil finishing: " . $e->getMessage();
        header("Location: hasil_finishing_koko.php?id=" . $id_hasil_kirim_finishing);
        exit();
    }
}

// Fungsi untuk mendapatkan tarif upah
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

    return 0;
}
?>

<style>
    .form-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .header-info {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .info-item {
        margin-bottom: 10px;
    }

    .info-label {
        font-weight: bold;
        color: #495057;
        min-width: 200px;
        display: inline-block;
    }

    .info-value {
        color: #212529;
    }

    .table-koko th {
        background-color: #e9ecef;
        font-weight: bold;
        font-size: 0.8rem;
        vertical-align: middle;
    }

    .table-koko td {
        font-size: 0.8rem;
        vertical-align: middle;
    }

    .btn-save {
        min-width: 150px;
    }

    .alert-container {
        margin-bottom: 20px;
    }

    .remaining-stock {
        font-size: 0.85rem;
        color: #6c757d;
    }

    .progress-info {
        background-color: #e7f3ff;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .tarif-info {
        background-color: #fff3cd;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .produk-info {
        background-color: #d4edda;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .history-section {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .history-table th {
        background-color: #e9ecef;
        font-size: 0.8rem;
        vertical-align: middle;
    }

    .history-table td {
        font-size: 0.8rem;
        vertical-align: middle;
    }

    .btn-batal-koko {
        background-color: #ff6b6b;
        border-color: #ff6b6b;
        color: white;
        padding: 0.15rem 0.4rem;
        font-size: 0.75rem;
    }

    .btn-batal-koko:hover {
        background-color: #ff5252;
        border-color: #ff5252;
    }

    .btn-batal-semua {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }

    .btn-batal-semua:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }

    .upah-section {
        background-color: #e8f4fd;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 5px;
        border-left: 4px solid #0d6efd;
    }

    .upah-input-group {
        display: flex;
        gap: 5px;
        align-items: center;
        margin-bottom: 5px;
    }

    .upah-dropdown {
        width: 120px;
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    .upah-manual {
        width: 120px;
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    .upah-type-toggle {
        font-size: 0.7rem;
        cursor: pointer;
        color: #0d6efd;
        text-decoration: underline;
    }

    .petugas-info {
        background-color: #f0f9ff;
        padding: 5px;
        border-radius: 4px;
        border-left: 3px solid #0d6efd;
        font-size: 0.8rem;
    }

    .petugas-fixed {
        color: #0d6efd;
        font-weight: bold;
    }

    .petugas-note {
        font-size: 0.7rem;
        color: #6c757d;
        font-style: italic;
    }

    .upah-wrapper {
        min-width: 250px;
        max-width: 500px;
    }

    .upah-wrapper .btn {
        padding: 0.25rem 0.5rem;
    }

    .upah-wrapper small {
        font-size: 0.75rem;
    }

    .table-koko .jumlah-input {
        width: 70px;
        margin: 0 auto;
        text-align: center;
    }

    .table-koko .upah-select {
        min-width: 140px;
        font-size: 0.8rem;
    }

    .table-koko .upah-input {
        min-width: 120px;
        font-size: 0.8rem;
    }

    .table-koko .btn-toggle-upah {
        padding: 0.25rem 0.5rem;
        font-size: 0.7rem;
    }

    .table-koko .upah-wrapper {
        min-width: 200px;
    }

    .table-koko tfoot {
        background-color: #f8f9fa;
    }

    .table-koko tfoot td {
        font-size: 0.9rem;
        padding: 12px 8px;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.25em 0.6em;
    }

    .form-control-sm {
        padding: 0.25rem 0.5rem;
    }

    .form-select {
        padding: 0.25rem 2.25rem 0.25rem 0.5rem;
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
                <div class="form-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Input Hasil Finishing Koko</h2>
                        <div>
                            <a href="finishing.php" class="btn btn-secondary">
                                <i class="ti ti-arrow-left"></i> Kembali ke Daftar
                            </a>
                        </div>
                    </div>

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

                    <!-- Header Info -->
                    <div hidden class="header-info">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <span class="info-label">Seri:</span>
                                    <span class="info-value"><?= htmlspecialchars($main_data['seri']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Produk Utama:</span>
                                    <span class="info-value"><?= htmlspecialchars($main_data['nama_produk']) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Petugas Finishing:</span>
                                    <span class="info-value petugas-fixed"><?= htmlspecialchars($main_data['nama_petugas']) ?></span>
                                    <small class="petugas-note">(Tetap sesuai pengiriman)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <span class="info-label">Tanggal Kirim:</span>
                                    <span class="info-value"><?= date('d/m/Y', strtotime($main_data['tanggal_kirim_finishing'])) ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Total Kirim:</span>
                                    <span class="info-value"><?= $main_data['total_kirim'] ?> pcs</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status:</span>
                                    <span class="info-value">
                                        <span class="badge bg-<?= $main_data['status_finishing'] == 'selesai' ? 'success' : ($main_data['status_finishing'] == 'diproses' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($main_data['status_finishing']) ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Info -->
                    <?php
                    // Hitung total progress
                    $total_dikirim = 0;
                    $total_diproses = 0;
                    foreach ($details_data as $detail) {
                        $total_dikirim += $detail['jumlah'];
                        $total_diproses += ($detail['sudah_selesai'] + $detail['sudah_rusak']);
                    }
                    $persentase = ($total_dikirim > 0) ? round(($total_diproses / $total_dikirim) * 100, 2) : 0;
                    ?>

                    <div hidden class="progress-info">
                        <h5>Progress Finishing Koko</h5>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar"
                                style="width: <?= $persentase ?>%;"
                                aria-valuenow="<?= $persentase ?>"
                                aria-valuemin="0"
                                aria-valuemax="100">
                                <?= $persentase ?>%
                            </div>
                        </div>
                        <p class="mb-1">
                            <strong>Total Dikirim:</strong> <?= $total_dikirim ?> pcs |
                            <strong>Sudah Diproses:</strong> <?= $total_diproses ?> pcs |
                            <strong>Sisa:</strong> <?= $total_dikirim - $total_diproses ?> pcs
                        </p>
                    </div>

                    <!-- History Hasil Finishing Koko -->
                    <?php if (!empty($tanggal_finishing_data)): ?>
                        <div class="history-section">
                            <h5>Riwayat Hasil Finishing Koko</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered history-table">
                                    <thead>
                                        <tr class="text-center">
                                            <th>Tanggal Finishing</th>
                                            <th>Total Selesai</th>
                                            <th>Total Kembali</th>
                                            <th>Total Upah</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tanggal_finishing_data as $history): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?= date('d/m/Y', strtotime($history['tanggal_finishing'])) ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $history['total_selesai'] ?> pcs
                                                </td>
                                                <td class="text-center">
                                                    <?= $history['total_rusak'] ?> pcs
                                                </td>
                                                <td class="text-center">
                                                    <?= formatRupiah($history['total_upah']) ?>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-batal-koko btn-batal-tanggal"
                                                        data-tanggal="<?= $history['tanggal_finishing'] ?>"
                                                        data-seri="<?= htmlspecialchars($main_data['seri']) ?>"
                                                        data-selesai="<?= $history['total_selesai'] ?>"
                                                        data-rusak="<?= $history['total_rusak'] ?>"
                                                        data-upah="<?= $history['total_upah'] ?>"
                                                        title="Batalkan hasil finishing tanggal ini">
                                                        <i class="ti ti-trash"></i> Batal
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" class="text-end">
                                                <button class="btn btn-batal-semua btn-batal-semua-koko"
                                                    data-id="<?= $id_hasil_kirim_finishing ?>"
                                                    data-seri="<?= htmlspecialchars($main_data['seri']) ?>"
                                                    title="Batalkan semua hasil finishing koko">
                                                    <i class="ti ti-trash"></i> Batalkan Semua Hasil
                                                </button>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Informasi Produk -->
                    <div hidden class="produk-info">
                        <h5>Informasi Produk Hasil Finishing</h5>
                        <p class="mb-1">
                            <strong>Produk Utama:</strong> <?= htmlspecialchars($main_data['nama_produk']) ?>
                        </p>
                        <p class="mb-1">
                            <strong>Produk dari Koko:</strong>
                            <?php
                            $produk_koko_list = [];
                            foreach ($details_data as $detail) {
                                if (!empty($detail['nama_produk_koko'])) {
                                    $produk_koko_list[] = $detail['nama_produk_koko'];
                                }
                            }
                            $produk_koko_list = array_unique($produk_koko_list);
                            if (!empty($produk_koko_list)) {
                                echo implode(", ", $produk_koko_list);
                            } else {
                                echo "-";
                            }
                            ?>
                        </p>
                        <p class="mb-0 text-success">
                            <i class="ti ti-info-circle"></i> Hasil finishing selesai akan otomatis ditambahkan ke stok produk yang sesuai.
                        </p>
                    </div>

                    <!-- Form Input Hasil Finishing Koko -->
                    <form method="POST" action="" id="formFinishingKoko">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Input Hasil Finishing Koko</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="tanggal_finishing" class="form-label">
                                            <strong>Tanggal Finishing *</strong>
                                        </label>
                                        <input type="date"
                                            class="form-control"
                                            id="tanggal_finishing"
                                            name="tanggal_finishing"
                                            value="<?= date('Y-m-d') ?>"
                                            required>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="petugas-info">
                                            <strong>Petugas Finishing:</strong>
                                            <span class="petugas-fixed"><?= htmlspecialchars($main_data['nama_petugas']) ?></span>
                                            <small class="petugas-note">(Otomatis sesuai pengiriman, upah akan ditambahkan ke hutang petugas ini)</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover table-koko">
                                        <thead class="table-light">
                                            <tr class="text-center">
                                                <th width="15%">Jenis Koko</th>
                                                <th width="6%">Dikirim</th>
                                                <th width="6%">Sudah Selesai</th>
                                                <th width="6%">Sudah Kembali</th>
                                                <th width="8%">Selesai</th>
                                                <th width="8%">Kembali</th>
                                                <th width="6%">Sisa</th>
                                                <th hidden width="12%">Petugas Finishing</th>
                                                <th width="18%">Upah per Unit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($details_data)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">Tidak ada data koko</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($details_data as $detail): ?>
                                                    <?php
                                                    $id_detail = $detail['id_detail_hasil_kirim_finishing'];
                                                    $sisa = $detail['jumlah'] - ($detail['sudah_selesai'] + $detail['sudah_rusak']);
                                                    $max_input = $sisa;
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <?= htmlspecialchars($detail['nama_koko']) ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?= $detail['jumlah'] ?> pcs
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-success">
                                                                <?= $detail['sudah_selesai'] ?> pcs
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-danger">
                                                                <?= $detail['sudah_rusak'] ?> pcs
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <input type="number"
                                                                class="form-control form-control-sm jumlah-input text-center"
                                                                name="jumlah_selesai_<?= $id_detail ?>"
                                                                id="jumlah_selesai_<?= $id_detail ?>"
                                                                value="0"
                                                                min="0"
                                                                max="<?= $max_input ?>"
                                                                data-max="<?= $max_input ?>"
                                                                onchange="updateSisa(<?= $id_detail ?>)">
                                                        </td>
                                                        <td>
                                                            <input type="number"
                                                                class="form-control form-control-sm jumlah-input text-center"
                                                                name="jumlah_rusak_<?= $id_detail ?>"
                                                                id="jumlah_rusak_<?= $id_detail ?>"
                                                                value="0"
                                                                min="0"
                                                                max="<?= $max_input ?>"
                                                                data-max="<?= $max_input ?>"
                                                                onchange="updateSisa(<?= $id_detail ?>)">
                                                        </td>
                                                        <td class="text-center">
                                                            <span id="sisa_<?= $id_detail ?>" class="badge bg-info">
                                                                <?= $sisa ?> pcs
                                                            </span>
                                                        </td>
                                                        <td hidden class="text-center">
                                                            <span class="badge bg-primary">
                                                                <?= htmlspecialchars($main_data['nama_petugas']) ?>
                                                            </span>
                                                            <input type="hidden"
                                                                name="id_petugas_finishing_<?= $id_detail ?>"
                                                                value="<?= $main_data['id_petugas_finishing'] ?>">
                                                        </td>
                                                        <td>
                                                            <div class="upah-wrapper">
                                                                <!-- mode input -->
                                                                <input type="hidden"
                                                                    name="upah_input_type_<?= $id_detail ?>"
                                                                    id="upah_input_type_<?= $id_detail ?>"
                                                                    value="dropdown">

                                                                <!-- DROPDOWN -->
                                                                <div class="input-group input-group-sm" id="upah_dropdown_<?= $id_detail ?>">
                                                                    <select name="upah_dropdown_<?= $id_detail ?>"
                                                                        class="form-select upah-select"
                                                                        onchange="updateTotalUpah(<?= $id_detail ?>)">
                                                                        <?php foreach ($upah_dropdown_options as $option): ?>
                                                                            <?php if ($option['tarif_per_unit'] > 0): ?>
                                                                                <option value="<?= $option['tarif_per_unit'] ?>">
                                                                                    Rp <?= number_format($option['tarif_per_unit'], 0, ',', '.') ?>
                                                                                    <?= !empty($option['keterangan']) ? ' - ' . htmlspecialchars($option['keterangan']) : '' ?>
                                                                                </option>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </select>

                                                                    <button type="button"
                                                                        class="m-1 btn btn-outline-secondary btn-toggle-upah"
                                                                        title="Input manual"
                                                                        onclick="toggleUpahInput(<?= $id_detail ?>)">
                                                                        <i class="ti ti-pencil"></i>
                                                                    </button>
                                                                </div>

                                                                <!-- MANUAL -->
                                                                <div class="input-group input-group-sm mt-1 d-none"
                                                                    id="upah_manual_<?= $id_detail ?>">
                                                                    <span class="input-group-text">Rp</span>
                                                                    <input type="number"
                                                                        name="upah_manual_<?= $id_detail ?>"
                                                                        id="upah_manual_input_<?= $id_detail ?>"
                                                                        class="form-control upah-input"
                                                                        min="0"
                                                                        step="100"
                                                                        value="0"
                                                                        placeholder="Masukkan upah"
                                                                        onchange="updateTotalUpah(<?= $id_detail ?>)">

                                                                    <button type="button"
                                                                        class="m-1 btn btn-outline-secondary btn-toggle-upah"
                                                                        title="Pilih dari tarif"
                                                                        onclick="toggleUpahInput(<?= $id_detail ?>)">
                                                                        <i class="ti ti-list"></i>
                                                                    </button>
                                                                </div>

                                                                <!-- TOTAL -->
                                                                <div class="mt-1">
                                                                    <small class="text-muted">Total:</small>
                                                                    <div class="fw-bold text-success" id="total_upah_<?= $id_detail ?>">
                                                                        Rp 0
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="6" class="text-end fw-bold">
                                                    <div class="pt-2">Total Upah Keseluruhan:</div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="fw-bold fs-5 text-success" id="grand_total_upah">
                                                        Rp 0
                                                    </div>
                                                    <div class="small text-muted mt-1">
                                                        Hutang: <?= htmlspecialchars($main_data['nama_petugas']) ?>
                                                    </div>
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div class="mt-2">
                                    <button type="submit"
                                        name="submit_finishing_koko"
                                        class="btn btn-primary btn-save">
                                        <i class="ti ti-check"></i> Simpan Hasil Finishing
                                    </button>

                                    <a href="finishing.php" class="btn btn-secondary ms-2">
                                        <i class="ti ti-x"></i> Batal
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
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
    function updateSisa(id_detail) {
        const inputSelesai = document.getElementById('jumlah_selesai_' + id_detail);
        const inputRusak = document.getElementById('jumlah_rusak_' + id_detail);
        const spanSisa = document.getElementById('sisa_' + id_detail);

        const maxInput = parseInt(inputSelesai.getAttribute('data-max'));
        const jumlahSelesai = parseInt(inputSelesai.value) || 0;
        const jumlahRusak = parseInt(inputRusak.value) || 0;
        const totalInput = jumlahSelesai + jumlahRusak;

        // Validasi tidak melebihi maksimum
        if (totalInput > maxInput) {
            alert('Total input tidak boleh melebihi ' + maxInput + ' pcs!');
            inputSelesai.value = 0;
            inputRusak.value = 0;
            spanSisa.textContent = maxInput + ' pcs';
            return;
        }

        const sisa = maxInput - totalInput;
        spanSisa.textContent = sisa + ' pcs';

        // Update badge color based on remaining
        if (sisa === 0) {
            spanSisa.className = 'badge bg-success';
        } else if (sisa < maxInput * 0.5) {
            spanSisa.className = 'badge bg-warning';
        } else {
            spanSisa.className = 'badge bg-info';
        }

        // Update total upah
        updateTotalUpah(id_detail);
    }

    function toggleUpahInput(id_detail) {
        const dropdownDiv = document.getElementById('upah_dropdown_' + id_detail);
        const manualDiv = document.getElementById('upah_manual_' + id_detail);
        const typeInput = document.getElementById('upah_input_type_' + id_detail);

        if (dropdownDiv.classList.contains('d-none')) {
            // Switch to dropdown
            dropdownDiv.classList.remove('d-none');
            manualDiv.classList.add('d-none');
            typeInput.value = 'dropdown';

            // Set default value jika manual kosong
            const manualValue = document.getElementById('upah_manual_input_' + id_detail).value;
            if (manualValue > 0) {
                const dropdownSelect = dropdownDiv.querySelector('select');
                dropdownSelect.value = manualValue;
            }
        } else {
            // Switch to manual
            dropdownDiv.classList.add('d-none');
            manualDiv.classList.remove('d-none');
            typeInput.value = 'manual';

            // Copy value dari dropdown ke manual
            const dropdownSelect = dropdownDiv.querySelector('select');
            const manualInput = document.getElementById('upah_manual_input_' + id_detail);
            manualInput.value = dropdownSelect.value;
        }

        // Update total upah
        updateTotalUpah(id_detail);
    }

    // Fungsi untuk update total upah per baris
    function updateTotalUpah(id_detail) {
        const jumlahSelesai = parseInt(document.getElementById('jumlah_selesai_' + id_detail).value) || 0;
        let upahPerUnit = 0;

        // Get upah value based on input type
        const inputType = document.getElementById('upah_input_type_' + id_detail).value;

        if (inputType === 'dropdown') {
            const dropdown = document.querySelector('select[name="upah_dropdown_' + id_detail + '"]');
            upahPerUnit = parseFloat(dropdown.value) || 0;
        } else {
            const manualInput = document.getElementById('upah_manual_input_' + id_detail);
            upahPerUnit = parseFloat(manualInput.value) || 0;
        }

        const totalUpah = jumlahSelesai * upahPerUnit;
        const totalUpahElement = document.getElementById('total_upah_' + id_detail);
        totalUpahElement.textContent = formatRupiahJS(totalUpah);

        // Update warna berdasarkan nilai
        if (totalUpah > 0) {
            totalUpahElement.className = 'fw-bold text-success';
        } else {
            totalUpahElement.className = 'fw-bold text-muted';
        }

        // Update grand total
        updateGrandTotalUpah();
    }

    function updateGrandTotalUpah() {
        let grandTotal = 0;

        // Loop through all details to calculate grand total
        <?php foreach ($details_data as $detail): ?>
            const jumlahSelesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                parseInt(document.getElementById('jumlah_selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value) || 0;

            let upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?> = 0;
            const inputType_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                document.getElementById('upah_input_type_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value;

            if (inputType_<?= $detail['id_detail_hasil_kirim_finishing'] ?> === 'dropdown') {
                const dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    document.querySelector('select[name="upah_dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?>"]');
                upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    parseFloat(dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?>.value) || 0;
            } else {
                const manualInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    document.getElementById('upah_manual_input_<?= $detail['id_detail_hasil_kirim_finishing'] ?>');
                upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    parseFloat(manualInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?>.value) || 0;
            }

            grandTotal += jumlahSelesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> * upahPerUnit_<?= $detail['id_detail_hasil_kirim_finishing'] ?>;
        <?php endforeach; ?>

        const grandTotalElement = document.getElementById('grand_total_upah');
        grandTotalElement.textContent = formatRupiahJS(grandTotal);

        // Update warna grand total
        if (grandTotal > 0) {
            grandTotalElement.className = 'fw-bold fs-5 text-success';
        } else {
            grandTotalElement.className = 'fw-bold fs-5 text-muted';
        }
    }

    // Initialize semua sisa saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($details_data as $detail): ?>
            updateSisa(<?= $detail['id_detail_hasil_kirim_finishing'] ?>);
        <?php endforeach; ?>

        // Validasi form sebelum submit
        document.getElementById('formFinishingKoko').addEventListener('submit', function(e) {
            let totalInput = 0;
            let hasInput = false;
            let hasUpahError = false;

            // Hitung total input dan validasi upah
            <?php foreach ($details_data as $detail): ?>
                const selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    parseInt(document.getElementById('jumlah_selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value) || 0;
                const rusak_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                    parseInt(document.getElementById('jumlah_rusak_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value) || 0;

                totalInput += selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> + rusak_<?= $detail['id_detail_hasil_kirim_finishing'] ?>;

                if (selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> > 0 || rusak_<?= $detail['id_detail_hasil_kirim_finishing'] ?> > 0) {
                    hasInput = true;

                    // Check upah for items with selesai > 0
                    if (selesai_<?= $detail['id_detail_hasil_kirim_finishing'] ?> > 0) {
                        const inputType_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                            document.getElementById('upah_input_type_<?= $detail['id_detail_hasil_kirim_finishing'] ?>').value;
                        let upahValue = 0;

                        if (inputType_<?= $detail['id_detail_hasil_kirim_finishing'] ?> === 'dropdown') {
                            const dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                                document.querySelector('select[name="upah_dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?>"]');
                            upahValue = parseFloat(dropdown_<?= $detail['id_detail_hasil_kirim_finishing'] ?>.value) || 0;
                        } else {
                            const manualInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?> =
                                document.getElementById('upah_manual_input_<?= $detail['id_detail_hasil_kirim_finishing'] ?>');
                            upahValue = parseFloat(manualInput_<?= $detail['id_detail_hasil_kirim_finishing'] ?>.value) || 0;
                        }

                        if (upahValue <= 0) {
                            hasUpahError = true;
                        }
                    }
                }
            <?php endforeach; ?>

            // Validasi minimal ada 1 input
            if (!hasInput) {
                e.preventDefault();
                alert('Silakan input minimal 1 hasil finishing (selesai atau rusak)');
                return false;
            }

            // Validasi upah untuk item yang selesai > 0
            if (hasUpahError) {
                e.preventDefault();
                alert('Silakan isi upah per unit yang valid (lebih dari 0) untuk koko yang memiliki hasil selesai');
                return false;
            }

            // Konfirmasi sebelum submit
            if (totalInput > 0) {
                const message = 'Anda yakin ingin menyimpan hasil finishing?\n\n' +
                    'Petugas: <?= htmlspecialchars($main_data['nama_petugas']) ?>\n' +
                    'Hasil finishing selesai akan ditambahkan ke stok produk.\n' +
                    'Hasil finishing rusak akan dikembalikan ke stok koko.\n' +
                    'Sisa koko akan dikembalikan ke stok.\n' +
                    'Upah akan ditambahkan ke hutang petugas: <?= htmlspecialchars($main_data['nama_petugas']) ?>';

                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Konfirmasi batal hasil finishing berdasarkan tanggal
        $(document).on('click', '.btn-batal-tanggal', function() {
            const tanggal = $(this).data('tanggal');
            const seri = $(this).data('seri');
            const selesai = $(this).data('selesai');
            const rusak = $(this).data('rusak');
            const upah = $(this).data('upah');

            Swal.fire({
                title: 'Batalkan Hasil Finishing?',
                html: `<div class="text-left">
                      <p>Apakah Anda yakin ingin membatalkan hasil finishing untuk:</p>
                      <ul>
                        <li><strong>Seri:</strong> ${seri}</li>
                        <li><strong>Tanggal:</strong> ${tanggal}</li>
                        <li><strong>Hasil Selesai:</strong> ${selesai} pcs</li>
                        <li><strong>Hasil Rusak:</strong> ${rusak} pcs</li>
                        <li><strong>Total Upah:</strong> ${formatRupiahJS(upah)}</li>
                        <li><strong>Petugas:</strong> <?= htmlspecialchars($main_data['nama_petugas']) ?></li>
                      </ul>
                      <p class="text-warning mt-3"><strong>Konsekuensi:</strong></p>
                      <ul class="text-warning">
                        <li>Stok produk yang sudah ditambahkan akan dikurangi</li>
                        <li>Stok koko akan dikembalikan (selesai + rusak)</li>
                        <li>Hutang upah petugas <?= htmlspecialchars($main_data['nama_petugas']) ?> akan dikurangi</li>
                        <li>Status mungkin berubah</li>
                      </ul>
                    </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff6b6b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Batal',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'hasil_finishing_koko.php?id=<?= $id_hasil_kirim_finishing ?>&action=batal_hasil_koko&tanggal=' + tanggal;
                }
            });
        });

        // Konfirmasi batal semua hasil finishing
        $(document).on('click', '.btn-batal-semua-koko', function() {
            const id = $(this).data('id');
            const seri = $(this).data('seri');

            Swal.fire({
                title: 'Batalkan Semua Hasil Finishing?',
                html: `<div class="text-left">
                      <p>Apakah Anda yakin ingin membatalkan <strong>SEMUA</strong> hasil finishing untuk:</p>
                      <ul>
                        <li><strong>Seri:</strong> ${seri}</li>
                        <li><strong>Petugas:</strong> <?= htmlspecialchars($main_data['nama_petugas']) ?></li>
                        <li><strong>Semua tanggal finishing</strong></li>
                      </ul>
                      <p class="text-danger mt-3"><strong>PERINGATAN:</strong></p>
                      <ul class="text-danger">
                        <li>Semua stok produk yang sudah ditambahkan akan dikurangi</li>
                        <li>Semua stok koko akan dikembalikan</li>
                        <li>Semua hutang upah petugas <?= htmlspecialchars($main_data['nama_petugas']) ?> akan dikurangi</li>
                        <li>Status akan kembali ke "pengiriman"</li>
                        <li><strong>Aksi ini tidak dapat dibatalkan!</strong></li>
                      </ul>
                    </div>`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Batalkan Semua!',
                cancelButtonText: 'Batal',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'hasil_finishing_koko.php?id=<?= $id_hasil_kirim_finishing ?>&action=batal_hasil_koko';
                }
            });
        });
    });

    // Fungsi helper untuk format rupiah di JavaScript
    function formatRupiahJS(angka) {
        if (!angka) return 'Rp 0';
        const number_string = angka.toString().replace(/[^,\d]/g, '');
        const split = number_string.split(',');
        const sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        const ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            const separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
        return 'Rp ' + rupiah;
    }

    // Initialize semua nilai saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($details_data as $detail): ?>
            updateSisa(<?= $detail['id_detail_hasil_kirim_finishing'] ?>);
            updateTotalUpah(<?= $detail['id_detail_hasil_kirim_finishing'] ?>);
        <?php endforeach; ?>
    });
</script>

</html>