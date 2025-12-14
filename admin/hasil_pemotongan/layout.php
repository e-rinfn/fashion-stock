<?php
require_once '../includes/header.php';
require_once '../../config/functions.php';


// Fungsi untuk mendapatkan tarif upah terkini
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

// Ambil semua produk untuk dropdown
$produk = query("SELECT * FROM produk");
$pemotong = query("SELECT * FROM pemotong");
$penjahit = query("SELECT * FROM penjahit");

// Cek filter yang diterapkan
$id_produk = isset($_GET['id_produk']) ? (int)$_GET['id_produk'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Tambah filter pemotong dan penjahit
$id_pemotong = isset($_GET['id_pemotong']) ? (int)$_GET['id_pemotong'] : 0;
$id_penjahit = isset($_GET['id_penjahit']) ? $_GET['id_penjahit'] : 0; // Bisa string untuk nilai -1

$start_date_default = date('Y-m-01');
$end_date_default   = date('Y-m-t');

// Query untuk mengambil data produksi
$sql = "SELECT h.*, p.nama_produk, p.tipe_produk, pem.nama_pemotong, 
               pen.nama_penjahit,
               (SELECT SUM(jumlah) FROM detail_hasil_potong_fix WHERE id_hasil_potong_fix = h.id_hasil_potong_fix) as total_hasil_potong
        FROM hasil_potong_fix h 
        JOIN produk p ON h.id_produk = p.id_produk 
        JOIN pemotong pem ON h.id_pemotong = pem.id_pemotong 
        LEFT JOIN penjahit pen ON h.id_penjahit = pen.id_penjahit 
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
    // Filter untuk data yang belum ada penjahit
    $sql .= " AND (h.id_penjahit IS NULL OR h.id_penjahit = 0)";
} elseif ($id_penjahit > 0) {
    $sql .= " AND h.id_penjahit = $id_penjahit";
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

$sql .= " ORDER BY h.tanggal_hasil_potong DESC";

$produksi = query($sql);

// Gabungkan data produksi untuk tampilan dengan perhitungan upah
$all_data = [];
foreach ($produksi as $prod) {
    // Dapatkan tarif upah berdasarkan tanggal produksi
    $tarif_pemotong = getTarifUpah('pemotongan', $prod['tanggal_hasil_potong']);
    $tarif_penjahit = !empty($prod['tanggal_hasil_jahit']) ?
        getTarifUpah('penjahitan', $prod['tanggal_hasil_jahit']) :
        getTarifUpah('penjahitan', $prod['tanggal_hasil_potong']);

    // Hitung upah
    $upah_pemotong = $prod['total_hasil'] * $tarif_pemotong;
    $upah_penjahit = !empty($prod['total_hasil_jahit']) ? $prod['total_hasil_jahit'] * $tarif_penjahit : 0;
    $total_upah = $upah_pemotong + $upah_penjahit;

    $all_data[] = [
        'type' => 'produksi',
        'id' => $prod['id_hasil_potong_fix'],
        'tanggal' => $prod['tanggal_hasil_potong'],
        'produk' => $prod['nama_produk'],
        'tipe_produk' => $prod['tipe_produk'],
        'seri' => $prod['seri'],
        'pemotong' => $prod['nama_pemotong'],
        'penjahit' => $prod['nama_penjahit'],
        'id_penjahit' => $prod['id_penjahit'],
        'status' => $prod['status_potong'],
        'total_hasil' => $prod['total_hasil'],
        'total_harga' => $prod['total_harga'],
        'tanggal_kirim_jahit' => $prod['tanggal_kirim_jahit'],
        'tanggal_hasil_jahit' => $prod['tanggal_hasil_jahit'],
        'total_hasil_jahit' => $prod['total_hasil_jahit'],
        'upah_pemotong' => $upah_pemotong,
        'upah_penjahit' => $upah_penjahit,
        'total_upah' => $total_upah,
        'rate_pemotong' => $tarif_pemotong,
        'rate_penjahit' => $tarif_penjahit
    ];
}

// Urutkan berdasarkan tanggal descending
usort($all_data, function ($a, $b) {
    return strtotime($b['tanggal']) - strtotime($a['tanggal']);
});

// PROSES INPUT PENJAHITAN JIKA ADA FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Simpan Tanggal Kirim (Modal Pertama)
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

    // Simpan Hasil Jahit (Modal Kedua)
    if (isset($_POST['simpan_hasil_jahit'])) {
        $id_hasil_potong_fix = intval($_POST['id_hasil_potong_fix']);
        $tanggal_hasil_jahit = isset($_POST['tanggal_hasil_jahit']) && !empty($_POST['tanggal_hasil_jahit'])
            ? $conn->real_escape_string($_POST['tanggal_hasil_jahit'])
            : null;
        $total_hasil_jahit = isset($_POST['total_hasil_jahit']) ? intval($_POST['total_hasil_jahit']) : 0;

        // Cek apakah data sudah ada
        $existing = isHasilJahitExist($id_hasil_potong_fix);

        // Validasi tambahan jika data sudah ada
        if ($existing) {
            // Ambil data existing untuk perbandingan
            $existing_data = getHasilJahitExisting($id_hasil_potong_fix);

            // Tanya konfirmasi (ini sudah dilakukan di JavaScript, tapi double check)
            if (!isset($_POST['confirm_override'])) {
                $_SESSION['error'] = "Data hasil jahit sudah ada.";
                header("Location: list.php");
                exit();
            }

            // Log perubahan
            $changes = [];
            if ($existing_data['tanggal_hasil_jahit'] != $tanggal_hasil_jahit) {
                $changes[] = "Tanggal: " . $existing_data['tanggal_hasil_jahit'] . " → " . $tanggal_hasil_jahit;
            }
            if ($existing_data['total_hasil_jahit'] != $total_hasil_jahit) {
                $changes[] = "Jumlah: " . $existing_data['total_hasil_jahit'] . " → " . $total_hasil_jahit . " Pcs";
            }

            if (!empty($changes)) {
                $change_log = "Update hasil jahit: " . implode(", ", $changes);
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
                $tarif_penjahit = getTarifUpah('penjahitan', $tanggal_hasil_jahit);
                $upah_penjahit = $total_hasil_jahit * $tarif_penjahit;

                // 1. Update data hasil jahit
                $sql_update = "UPDATE hasil_potong_fix 
                      SET tanggal_hasil_jahit = '$tanggal_hasil_jahit', 
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
                    // KOKO: Update tabel finishing
                    if ($existing) {
                        // Update data finishing yang sudah ada
                        $sql_update_finishing = "UPDATE finishing 
                                       SET total_masuk = $total_hasil_jahit,
                                           tanggal_masuk = '$tanggal_hasil_jahit'
                                       WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

                        if (!$conn->query($sql_update_finishing)) {
                            throw new Exception("Gagal update data finishing: " . $conn->error);
                        }
                        $pesan_stok = "Data finishing diupdate";
                    } else {
                        // Data baru
                        if (!catatKeFinishing($id_hasil_potong_fix, $id_produk, $seri, $total_hasil_jahit, $tanggal_hasil_jahit, 'Hasil jahit dari penjahitan')) {
                            throw new Exception("Gagal mencatat ke tabel finishing");
                        }
                        $pesan_stok = "Produk masuk ke proses finishing";
                    }
                }

                // 3. Catat/Update hutang upah penjahit
                if ($existing) {
                    // Update hutang yang sudah ada
                    $periode_existing = date('Y-m-01', strtotime($existing_tanggal));
                    $periode_new = date('Y-m-01', strtotime($tanggal_hasil_jahit));

                    // Hitung upah sebelumnya
                    $upah_sebelumnya = $existing_jumlah * getTarifUpah('penjahitan', $existing_tanggal);
                    $selisih_upah = $upah_penjahit - $upah_sebelumnya;

                    if ($selisih_upah != 0) {
                        // Update hutang
                        if ($periode_existing == $periode_new) {
                            // Periode sama, update hutang yang ada
                            updateHutangUpahPenjahit($id_penjahit, $periode_new, $selisih_upah);
                        } else {
                            // Periode berbeda, kurangi dari periode lama, tambah ke periode baru
                            updateHutangUpahPenjahit($id_penjahit, $periode_existing, -$upah_sebelumnya);
                            updateHutangUpahPenjahit($id_penjahit, $periode_new, $upah_penjahit);
                        }
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

    // Batal Penjahitan (hanya hapus hasil jahit, pertahankan penjahit & tanggal kirim)
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

        // CEK APAKAH UPAH SUDAH DIBERIKAN
        $upah_dibayar = false;
        if ($total_hasil_jahit > 0 && $id_penjahit > 0 && !empty($tanggal_hasil_jahit)) {
            $periode = date('Y-m-01', strtotime($tanggal_hasil_jahit));
            $check_upah_dibayar = $conn->prepare("
            SELECT COUNT(*) as total_pembayaran 
            FROM pembayaran_upah_2 pu
            JOIN hutang_upah hu ON pu.id_hutang = hu.id_hutang
            WHERE hu.id_karyawan = ? 
            AND hu.jenis_karyawan = 'penjahit' 
            AND hu.periode = ?
            AND hu.total_dibayar > 0
        ");
            $check_upah_dibayar->bind_param("is", $id_penjahit, $periode);
            $check_upah_dibayar->execute();
            $result_upah = $check_upah_dibayar->get_result();
            $upah_dibayar = $result_upah->fetch_assoc()['total_pembayaran'] > 0;
            $check_upah_dibayar->close();
        }

        if ($upah_dibayar) {
            $_SESSION['error'] = "Tidak dapat membatalkan penjahitan karena upah penjahit untuk produksi ini sudah dibayar. Silakan batalkan pembayaran upah penjahitan terlebih dahulu.";
            header("Location: list.php");
            exit();
        }

        $conn->autocommit(FALSE);
        try {
            // HITUNG UPAH YANG AKAN DIHAPUS
            $upah_dihapus = 0;
            if ($total_hasil_jahit > 0 && $id_penjahit > 0 && !empty($tanggal_hasil_jahit)) {
                $tarif_penjahit = getTarifUpah('penjahitan', $tanggal_hasil_jahit);
                $upah_dihapus = $total_hasil_jahit * $tarif_penjahit;
            }

            // 1. Reset HANYA data hasil jahit
            $sql_batal = "UPDATE hasil_potong_fix 
             SET tanggal_hasil_jahit = NULL, 
                 total_hasil_jahit = NULL,
                 status_potong = 'penjahitan'
             WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

            if (!$conn->query($sql_batal)) {
                throw new Exception("Gagal membatalkan data hasil jahit: " . $conn->error);
            }

            // 2. LOGIKA BERBEDA BERDASARKAN TIPE PRODUK
            if ($tipe_produk == 'mukena' && $total_hasil_jahit > 0) {
                // MUKENA: Kurangi stok dari tabel produk
                $sql_kurangi_stok = "UPDATE produk 
                        SET stok = stok - $total_hasil_jahit 
                        WHERE id_produk = $id_produk";

                if (!$conn->query($sql_kurangi_stok)) {
                    throw new Exception("Gagal mengurangi stok produk: " . $conn->error);
                }
                $pesan_stok = "Stok produk dikurangi -$total_hasil_jahit";
            } elseif ($tipe_produk == 'koko' && $total_hasil_jahit > 0) {
                // KOKO: Hapus dari tabel finishing jika ada
                $sql_hapus_finishing = "DELETE FROM finishing WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
                if (!$conn->query($sql_hapus_finishing)) {
                    throw new Exception("Gagal menghapus data finishing: " . $conn->error);
                }
                $pesan_stok = "Data finishing dihapus";
            }

            // 3. Hapus/Update hutang upah penjahit (hanya jika ada upah)
            if ($upah_dihapus > 0) {
                // Cari hutang yang terkait
                $periode = date('Y-m-01', strtotime($tanggal_hasil_jahit));
                $check_hutang = $conn->prepare("SELECT id_hutang, total_upah, sisa_hutang FROM hutang_upah 
                                      WHERE id_karyawan = ? AND jenis_karyawan = 'penjahit' AND periode = ?");
                $check_hutang->bind_param("is", $id_penjahit, $periode);
                $check_hutang->execute();
                $result_hutang = $check_hutang->get_result();

                if ($result_hutang->num_rows > 0) {
                    $hutang = $result_hutang->fetch_assoc();
                    $total_upah_baru = $hutang['total_upah'] - $upah_dihapus;
                    $sisa_hutang_baru = $hutang['sisa_hutang'] - $upah_dihapus;

                    if ($total_upah_baru <= 0) {
                        // Hapus record hutang jika total upah menjadi 0
                        $delete_hutang = $conn->prepare("DELETE FROM hutang_upah WHERE id_hutang = ?");
                        $delete_hutang->bind_param("i", $hutang['id_hutang']);
                        $delete_hutang->execute();
                    } else {
                        // Update hutang yang sudah ada
                        $update_hutang = $conn->prepare("UPDATE hutang_upah SET total_upah = ?, sisa_hutang = ? 
                                              WHERE id_hutang = ?");
                        $update_hutang->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
                        $update_hutang->execute();
                    }
                }
            }

            $conn->commit();
            $conn->autocommit(TRUE);

            $pesan_success = "Data hasil jahit berhasil dibatalkan";
            if ($total_hasil_jahit > 0) {
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
            $error_modal = "Gagal membatalkan data hasil jahit: " . $e->getMessage();
        }
    }


    // Hapus Penjahit dan Tanggal Kirim
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

        // CEK APAKAH UPAH SUDAH DIBERIKAN (jika ada hasil jahit)
        $upah_dibayar = false;
        if ($total_hasil_jahit > 0 && $id_penjahit > 0 && !empty($tanggal_hasil_jahit)) {
            $periode = date('Y-m-01', strtotime($tanggal_hasil_jahit));
            $check_upah_dibayar = $conn->prepare("
            SELECT COUNT(*) as total_pembayaran 
            FROM pembayaran_upah_2 pu
            JOIN hutang_upah hu ON pu.id_hutang = hu.id_hutang
            WHERE hu.id_karyawan = ? 
            AND hu.jenis_karyawan = 'penjahit' 
            AND hu.periode = ?
            AND hu.total_dibayar > 0
        ");
            $check_upah_dibayar->bind_param("is", $id_penjahit, $periode);
            $check_upah_dibayar->execute();
            $result_upah = $check_upah_dibayar->get_result();
            $upah_dibayar = $result_upah->fetch_assoc()['total_pembayaran'] > 0;
            $check_upah_dibayar->close();
        }

        if ($upah_dibayar) {
            $_SESSION['error'] = "Tidak dapat menghapus penjahit karena upah penjahit untuk produksi ini sudah dibayar. Silakan batalkan pembayaran upah penjahitan terlebih dahulu.";
            header("Location: list.php");
            exit();
        }

        $conn->autocommit(FALSE);
        try {
            // HITUNG UPAH YANG AKAN DIHAPUS (jika ada hasil jahit)
            $upah_dihapus = 0;
            if ($total_hasil_jahit > 0 && $id_penjahit > 0 && !empty($tanggal_hasil_jahit)) {
                $tarif_penjahit = getTarifUpah('penjahitan', $tanggal_hasil_jahit);
                $upah_dihapus = $total_hasil_jahit * $tarif_penjahit;
            }

            // 1. Reset SEMUA data penjahitan
            $sql_hapus = "UPDATE hasil_potong_fix 
             SET id_penjahit = NULL, 
                 tanggal_kirim_jahit = NULL, 
                 tanggal_hasil_jahit = NULL, 
                 total_hasil_jahit = NULL,
                 status_potong = 'diproses'
             WHERE id_hasil_potong_fix = $id_hasil_potong_fix";

            if (!$conn->query($sql_hapus)) {
                throw new Exception("Gagal menghapus data penjahit: " . $conn->error);
            }

            // 2. LOGIKA BERBEDA BERDASARKAN TIPE PRODUK
            if ($tipe_produk == 'mukena' && $total_hasil_jahit > 0) {
                // MUKENA: Kurangi stok produk
                $sql_kurangi_stok = "UPDATE produk 
                        SET stok = stok - $total_hasil_jahit 
                        WHERE id_produk = $id_produk";

                if (!$conn->query($sql_kurangi_stok)) {
                    throw new Exception("Gagal mengurangi stok produk: " . $conn->error);
                }
                $pesan_stok = "stok produk dikurangi -$total_hasil_jahit";
            } elseif ($tipe_produk == 'koko' && $total_hasil_jahit > 0) {
                // KOKO: Hapus dari tabel finishing
                $sql_hapus_finishing = "DELETE FROM finishing WHERE id_hasil_potong_fix = $id_hasil_potong_fix";
                if (!$conn->query($sql_hapus_finishing)) {
                    throw new Exception("Gagal menghapus data finishing: " . $conn->error);
                }
                $pesan_stok = "data finishing dihapus";
            }

            // 3. Hapus/Update hutang upah penjahit (hanya jika ada upah)
            if ($upah_dihapus > 0) {
                // Cari hutang yang terkait
                $periode = date('Y-m-01', strtotime($tanggal_hasil_jahit));
                $check_hutang = $conn->prepare("SELECT id_hutang, total_upah, sisa_hutang FROM hutang_upah 
                                      WHERE id_karyawan = ? AND jenis_karyawan = 'penjahit' AND periode = ?");
                $check_hutang->bind_param("is", $id_penjahit, $periode);
                $check_hutang->execute();
                $result_hutang = $check_hutang->get_result();

                if ($result_hutang->num_rows > 0) {
                    $hutang = $result_hutang->fetch_assoc();
                    $total_upah_baru = $hutang['total_upah'] - $upah_dihapus;
                    $sisa_hutang_baru = $hutang['sisa_hutang'] - $upah_dihapus;

                    if ($total_upah_baru <= 0) {
                        // Hapus record hutang jika total upah menjadi 0
                        $delete_hutang = $conn->prepare("DELETE FROM hutang_upah WHERE id_hutang = ?");
                        $delete_hutang->bind_param("i", $hutang['id_hutang']);
                        $delete_hutang->execute();
                    } else {
                        // Update hutang yang sudah ada
                        $update_hutang = $conn->prepare("UPDATE hutang_upah SET total_upah = ?, sisa_hutang = ? 
                                              WHERE id_hutang = ?");
                        $update_hutang->bind_param("ddi", $total_upah_baru, $sisa_hutang_baru, $hutang['id_hutang']);
                        $update_hutang->execute();
                    }
                }
            }

            $conn->commit();
            $conn->autocommit(TRUE);

            // Pesan sukses berdasarkan kondisi
            $pesan_success = "Data penjahit berhasil dihapus";
            if ($total_hasil_jahit > 0) {
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
            $error_modal = "Gagal menghapus data penjahit: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Finishing Koko</title>
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
                    <h2>Master Data Finishing Koko</h2>
                    <div class="btn-group">
                        <div>
                            <a href="new.php" class="btn btn-success">
                                <i class="ti ti-circle-plus"></i> Tambah Produksi
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filter Form -->
                <form method="GET" class="row g-3 mb-3">
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

                    <!-- Tambah filter pemotong -->
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

                    <!-- Tambah filter penjahit -->
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
                            <option value="penjahitan" <?= ($status == 'penjahitan') ? 'selected' : '' ?>>Penjahitan</option>
                            <option value="selesai" <?= ($status == 'selesai') ? 'selected' : '' ?>>Selesai</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control"
                            value="<?= htmlspecialchars($start_date ?: $start_date_default) ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control"
                            value="<?= htmlspecialchars($end_date ?: $end_date_default) ?>">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="ti ti-filter"></i> Filter
                        </button>
                        <?php
                        // Cek apakah ada filter yang aktif
                        $is_filtered = $id_produk > 0 || $id_pemotong > 0 || $id_penjahit != 0 ||
                            $status != 'all' || !empty($start_date) || !empty($end_date);
                        ?>

                        <?php if ($is_filtered): ?>
                            <a href="list.php" class="btn btn-secondary me-2">
                                <i class="ti ti-rotate"></i> Reset
                            </a>
                        <?php endif; ?>

                        <button type="button" class="btn btn-danger" id="btnPrintPDF">
                            <i class="ti ti-file-text"></i> Print PDF
                        </button>
                    </div>
                </form>

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
                                    <th class="align-middle">Status</th>
                                    <th class="bg-warning text-white align-middle">Seri</th>
                                    <th class="bg-warning text-white align-middle">Pemotong</th>
                                    <th class="bg-warning text-white align-middle">Tgl Potong</th>
                                    <th class="bg-warning text-white align-middle">Produk</th>
                                    <th class="bg-warning text-white align-middle">Hasil Potong</th>
                                    <th class="upah-column align-middle">Upah Pemotong</th>
                                    <th class="bg-info text-white align-middle">Tgl Kirim Jahit</th>
                                    <th class="bg-info text-white align-middle">Penjahit</th>
                                    <th class="bg-info text-white align-middle">Tgl Jahit</th>
                                    <th class="bg-info text-white align-middle">Hasil Jahit</th>
                                    <th class="upah-column align-middle">Upah Penjahit</th>
                                    <th class="upah-column align-middle">Total Upah</th>
                                    <th class="align-middle">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($all_data)): ?>
                                    <tr>
                                        <td colspan="13" class="text-center">Tidak ada data produksi</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($all_data as $data): ?>
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
                                                        $label = 'Potong'; // ubah tampilan
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
                                                <br><small class="tarif-info"><?= formatRupiah($data['rate_pemotong']) ?>/pcs</small>
                                            </td>
                                            <td><?= dateIndo($data['tanggal']) ?></td>
                                            <!-- <td><?= htmlspecialchars($data['produk']) ?></td> -->
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
                                            <td class="text-center upah-column">
                                                <?= formatRupiah($data['upah_pemotong']) ?>
                                            </td>


                                            <td>
                                                <?= !empty($data['tanggal_kirim_jahit']) ? dateIndo($data['tanggal_kirim_jahit']) : '-' ?>
                                            </td>
                                            <td class="">
                                                <?php if (!empty($data['penjahit'])): ?>
                                                    <?= htmlspecialchars($data['penjahit']) ?>
                                                    <br><small class="tarif-info"><?= formatRupiah($data['rate_penjahit']) ?>/pcs</small>
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
                                            <td class="text-center upah-column">
                                                <?= !empty($data['total_hasil_jahit']) ? formatRupiah($data['upah_penjahit']) : '-' ?>
                                            </td>
                                            <td class="text-center upah-column fw-bold">
                                                <?= formatRupiah($data['total_upah']) ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group-actions text-center">
                                                    <!-- Tombol Detail -->
                                                    <a href="detail.php?id=<?= $data['id'] ?>" class="btn btn-sm btn-primary" title="Detail">
                                                        <i class="ti ti-eye"></i>
                                                    </a>

                                                    <?php
                                                    // Logika tombol berdasarkan status
                                                    $has_tanggal_kirim = !empty($data['tanggal_kirim_jahit']);
                                                    $has_hasil_jahit = !empty($data['total_hasil_jahit']);
                                                    $is_diproses = $data['status'] == 'diproses';
                                                    $is_penjahitan = $data['status'] == 'penjahitan';
                                                    $is_selesai = $data['status'] == 'selesai';
                                                    ?>

                                                    <?php if ($is_diproses): ?>
                                                        <!-- Status: Diproses -->
                                                        <button class="btn btn-sm btn-info btn-input-tanggal-penjahitan"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            data-total-potong="<?= $data['total_hasil'] ?>"
                                                            data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                            title="Input Tanggal Kirim Jahit">
                                                            <i class="ti ti-calendar"></i>
                                                        </button>

                                                        <button class="btn btn-sm btn-danger btn-batal-produksi"
                                                            data-id="<?= $data['id'] ?>"
                                                            data-pemotong="<?= htmlspecialchars($data['pemotong']) ?>"
                                                            data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                            title="Batalkan Produksi">
                                                            <i class="ti ti-trash"></i>
                                                        </button>

                                                    <?php elseif ($is_penjahitan || $is_selesai): ?>
                                                        <!-- Status: Penjahitan atau Selesai -->

                                                        <?php if ($has_tanggal_kirim && !$has_hasil_jahit): ?>
                                                            <!-- Ada tanggal kirim tapi belum ada hasil jahit -->
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

                                                        <?php if ($has_tanggal_kirim): ?>
                                                            <!-- Tombol Edit Tanggal Kirim -->
                                                            <button class="btn btn-sm btn-info btn-edit-tanggal-penjahitan" hidden
                                                                data-id="<?= $data['id'] ?>"
                                                                data-produk="<?= htmlspecialchars($data['produk']) ?>"
                                                                data-seri="<?= htmlspecialchars($data['seri']) ?>"
                                                                data-total-potong="<?= $data['total_hasil'] ?>"
                                                                data-tanggal-potong="<?= $data['tanggal'] ?>"
                                                                data-penjahit="<?= $data['id_penjahit'] ?>"
                                                                data-tanggal-kirim="<?= $data['tanggal_kirim_jahit'] ?>"
                                                                title="Edit Tanggal Kirim">
                                                                <i class="ti ti-edit"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($has_hasil_jahit): ?>
                                                            <!-- Tombol Batal Hasil Jahit (hanya hapus hasil jahit) -->
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

                                                        <!-- Tombol Hapus Penjahit (hapus semua data penjahitan) -->
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
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Input Tanggal Kirim Jahit (Modal Pertama) -->
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

    <!-- Modal Input Hasil Jahit (Modal Kedua) -->
    <div class="modal fade" id="modalHasilPenjahitan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitleHasil">Input Hasil Penjahitan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="formHasilPenjahitan">
                    <div class="modal-body">
                        <?php if (isset($error_modal)): ?>
                            <div class="alert alert-danger"><?= $error_modal ?></div>
                        <?php endif; ?>

                        <input type="hidden" name="id_hasil_potong_fix" id="modal_hasil_id_hasil_potong">
                        <input type="hidden" id="modal_hasil_tanggal_potong">
                        <input type="hidden" id="modal_hasil_penjahit">
                        <input type="hidden" id="modal_hasil_tanggal_kirim">
                        <input type="hidden" id="modal_hasil_existing" value="0">

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

                            <div class="col-md-6">
                                <label class="form-label">Tanggal Kirim</label>
                                <input type="text" class="form-control" id="modal_hasil_tanggal_kirim_text" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tanggal Hasil Jahit <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_hasil_jahit" class="form-control"
                                id="modal_hasil_tanggal_jahit" required>
                            <small class="text-muted">Tanggal ketika penjahitan selesai</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Hasil Jahit (Pcs) <span class="text-danger">*</span></label>
                            <input type="number" name="total_hasil_jahit" class="form-control"
                                min="1" max="" id="modal_hasil_total_jahit" required>
                            <small class="text-muted">Maksimal: <span id="modal_hasil_max_total">0</span> Pcs</small>
                        </div>

                        <div class="alert alert-warning" id="modal_hasil_alert">
                            <i class="ti ti-alert-triangle"></i>
                            <span id="modal_hasil_alert_text">
                                Pastikan jumlah hasil jahit sesuai dengan kondisi fisik.
                            </span>
                            <div id="modal_hasil_override_info" class="d-none mt-2">
                                <i class="ti ti-alert-triangle text-danger"></i>
                                <strong class="text-danger">Anda akan mengupdate data yang sudah ada!</strong>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
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
                            4. <span id="batal_modal_keterangan_stok"></span>
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

    <!-- Modal Konfirmasi Hapus Penjahit dan Tanggal Kirim -->
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
                            2. Status akan kembali ke "Potong"<br>
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

            // Inisialisasi semua modal terlebih dahulu
            const modalTanggalPenjahitan = new bootstrap.Modal(document.getElementById('modalTanggalPenjahitan'));
            const modalHasilPenjahitan = new bootstrap.Modal(document.getElementById('modalHasilPenjahitan'));
            const modalBatalPenjahitan = new bootstrap.Modal(document.getElementById('modalBatalPenjahitan'));
            const modalHapusPenjahit = new bootstrap.Modal(document.getElementById('modalHapusPenjahit'));

            // --- EVENT LISTENER UNTUK TOMBOL INPUT TANGGAL KIRIM ---
            document.addEventListener('click', function(e) {
                // Tombol Input Tanggal Kirim (Status: Diproses)
                if (e.target.closest('.btn-input-tanggal-penjahitan')) {
                    e.preventDefault();
                    console.log('Tombol Input Tanggal Kirim diklik');

                    const button = e.target.closest('.btn-input-tanggal-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const tanggalPotong = button.getAttribute('data-tanggal-potong');

                    console.log('Data:', {
                        id,
                        produk,
                        seri
                    });

                    // Isi data ke modal
                    document.getElementById('modalTitleTanggal').textContent = 'Input Tanggal Kirim Penjahitan';
                    document.getElementById('modal_tanggal_id_hasil_potong').value = id;
                    document.getElementById('modal_tanggal_produk').value = produk;
                    document.getElementById('modal_tanggal_seri').value = seri;
                    document.getElementById('modal_tanggal_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_tanggal_kirim_jahit').value = '<?= date('Y-m-d') ?>';

                    // Reset select penjahit
                    document.getElementById('modal_tanggal_penjahit').selectedIndex = 0;

                    // Tampilkan modal
                    modalTanggalPenjahitan.show();
                }

                // Tombol Input Hasil Jahit (Status: Penjahitan/Selesai)
                if (e.target.closest('.btn-input-hasil-penjahitan')) {
                    e.preventDefault();
                    console.log('Tombol Input Hasil Jahit diklik');

                    const button = e.target.closest('.btn-input-hasil-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const namaPenjahit = button.getAttribute('data-nama-penjahit');
                    const tanggalKirim = button.getAttribute('data-tanggal-kirim');

                    // Isi data ke modal hasil
                    document.getElementById('modal_hasil_id_hasil_potong').value = id;
                    document.getElementById('modal_hasil_produk').value = produk;
                    document.getElementById('modal_hasil_seri').value = seri;
                    document.getElementById('modal_hasil_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_hasil_nama_penjahit').value = namaPenjahit || '-';
                    document.getElementById('modal_hasil_tanggal_kirim_text').value = formatDate(tanggalKirim) || '-';
                    document.getElementById('modal_hasil_total_jahit').value = totalPotong;
                    document.getElementById('modal_hasil_total_jahit').max = totalPotong;
                    document.getElementById('modal_hasil_max_total').textContent = totalPotong;
                    document.getElementById('modal_hasil_tanggal_jahit').value = '<?= date('Y-m-d') ?>';

                    // Reset alert existing
                    document.getElementById('modalHasilExistAlert').classList.add('d-none');
                    document.getElementById('modal_hasil_override_info').classList.add('d-none');
                    document.getElementById('modalHasilSubmitBtn').textContent = 'Simpan Hasil Jahit';

                    // Tampilkan modal
                    modalHasilPenjahitan.show();
                }

                // Tombol Edit Tanggal Kirim
                if (e.target.closest('.btn-edit-tanggal-penjahitan')) {
                    e.preventDefault();
                    console.log('Tombol Edit Tanggal Kirim diklik');

                    const button = e.target.closest('.btn-edit-tanggal-penjahitan');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const totalPotong = button.getAttribute('data-total-potong');
                    const penjahit = button.getAttribute('data-penjahit');
                    const tanggalKirim = button.getAttribute('data-tanggal-kirim');

                    // Isi data ke modal
                    document.getElementById('modalTitleTanggal').textContent = 'Edit Tanggal Kirim Penjahitan';
                    document.getElementById('modal_tanggal_id_hasil_potong').value = id;
                    document.getElementById('modal_tanggal_produk').value = produk;
                    document.getElementById('modal_tanggal_seri').value = seri;
                    document.getElementById('modal_tanggal_total_potong').value = totalPotong + ' Pcs';
                    document.getElementById('modal_tanggal_penjahit').value = penjahit || '';
                    document.getElementById('modal_tanggal_kirim_jahit').value = formatDate(tanggalKirim) || '';

                    // Tampilkan modal
                    modalTanggalPenjahitan.show();
                }

                // Tombol Batal Hasil Jahit
                if (e.target.closest('.btn-batal-hasil-jahit')) {
                    e.preventDefault();
                    console.log('Tombol Batal Hasil Jahit diklik');

                    const button = e.target.closest('.btn-batal-hasil-jahit');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const hasilJahit = button.getAttribute('data-hasil-jahit');
                    const tipeProduk = button.getAttribute('data-tipe-produk') || 'mukena';

                    // Isi data ke modal batal
                    document.getElementById('batal_modal_id').value = id;
                    document.getElementById('batal_modal_tipe_produk').value = tipeProduk;
                    document.getElementById('batal_modal_produk').textContent = produk;
                    document.getElementById('batal_modal_seri').textContent = seri;

                    // Tampilkan tipe produk
                    const tipeBadge = document.getElementById('batal_modal_tipe_text');
                    tipeBadge.textContent = tipeProduk.toUpperCase();
                    tipeBadge.className = 'badge bg-' + (tipeProduk === 'koko' ? 'info' : 'secondary');

                    // Tampilkan keterangan
                    const keteranganStok = document.getElementById('batal_modal_keterangan_stok');
                    if (tipeProduk === 'mukena') {
                        keteranganStok.textContent = `Stok produk akan dikurangi ${hasilJahit || 0} Pcs`;
                    } else {
                        keteranganStok.textContent = `Data finishing akan dihapus (${hasilJahit || 0} Pcs)`;
                    }

                    // Tampilkan modal
                    modalBatalPenjahitan.show();
                }

                // Tombol Hapus Penjahit
                if (e.target.closest('.btn-hapus-penjahit')) {
                    e.preventDefault();
                    console.log('Tombol Hapus Penjahit diklik');

                    const button = e.target.closest('.btn-hapus-penjahit');
                    const id = button.getAttribute('data-id');
                    const produk = button.getAttribute('data-produk');
                    const seri = button.getAttribute('data-seri');
                    const penjahit = button.getAttribute('data-penjahit');

                    // Isi data ke modal hapus penjahit
                    document.getElementById('hapus_penjahit_id').value = id;
                    document.getElementById('hapus_penjahit_produk').textContent = produk;
                    document.getElementById('hapus_penjahit_seri').textContent = seri + (penjahit ? ` (Penjahit: ${penjahit})` : '');

                    // Tampilkan modal
                    modalHapusPenjahit.show();
                }

                // Tombol Batal Produksi
                if (e.target.closest('.btn-batal-produksi')) {
                    e.preventDefault();
                    console.log('Tombol Batal Produksi diklik');

                    const button = e.target.closest('.btn-batal-produksi');
                    const id = button.getAttribute('data-id');
                    const pemotong = button.getAttribute('data-pemotong');
                    const seri = button.getAttribute('data-seri');

                    // Konfirmasi dengan SweetAlert
                    Swal.fire({
                        title: 'Yakin ingin membatalkan produksi ini?',
                        html: `<p>Produksi <strong>Seri ${seri}</strong> akan dibatalkan.</p>
                           <p class="text-danger">Tindakan ini tidak dapat dikembalikan!</p>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Ya, batalkan!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'batal_pemotongan.php?id=' + id;
                        }
                    });
                }
            });

            // Fungsi format tanggal
            function formatDate(dateString) {
                if (!dateString || dateString === '-') return '';
                try {
                    const date = new Date(dateString);
                    return date.toISOString().split('T')[0];
                } catch (e) {
                    return '';
                }
            }

            // Validasi form tanggal kirim
            document.getElementById('formTanggalPenjahitan')?.addEventListener('submit', function(e) {
                const idPenjahit = document.getElementById('modal_tanggal_penjahit')?.value;
                const tanggalKirim = document.getElementById('modal_tanggal_kirim_jahit')?.value;

                if (!idPenjahit) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Penjahit harus dipilih',
                        confirmButtonText: 'Oke'
                    });
                    return;
                }

                if (!tanggalKirim) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Tanggal kirim harus diisi',
                        confirmButtonText: 'Oke'
                    });
                    return;
                }
            });

            // Validasi form hasil jahit
            document.getElementById('formHasilPenjahitan')?.addEventListener('submit', function(e) {
                const totalJahit = parseInt(document.getElementById('modal_hasil_total_jahit')?.value) || 0;
                const maxJahit = parseInt(document.getElementById('modal_hasil_total_jahit')?.max) || 0;
                const tanggalHasilJahit = document.getElementById('modal_hasil_tanggal_jahit')?.value;

                let errorMessages = [];

                if (!tanggalHasilJahit) {
                    errorMessages.push('Tanggal hasil jahit harus diisi');
                }

                if (totalJahit <= 0) {
                    errorMessages.push('Total hasil jahit harus lebih dari 0');
                }

                if (totalJahit > maxJahit) {
                    errorMessages.push(`Total hasil jahit tidak boleh melebihi ${maxJahit} Pcs`);
                }

                if (errorMessages.length > 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Validasi Error',
                        html: '<div class="text-start">' +
                            errorMessages.map(msg => `<p>• ${msg}</p>`).join('') +
                            '</div>',
                        confirmButtonText: 'Oke'
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

            // Set default date range (30 hari terakhir)
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