<?php
require_once __DIR__ . '../../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

function dateIndo($tanggal)
{
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
    $tanggal = date('Y-m-d', strtotime($tanggal));
    $pecah = explode('-', $tanggal);
    return $pecah[2] . ' ' . $bulanIndo[(int)$pecah[1]] . ' ' . $pecah[0];
}

// Ambil filter GET
$tgl_awal = $_GET['tgl_awal'] ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';
$id_pemotong = $_GET['pemotong'] ?? '';
$id_bahan = $_GET['bahan'] ?? '';

// Ambil data filter untuk select option
$list_pemotong = query("SELECT * FROM pemotong ORDER BY nama_pemotong");
$list_bahan = query("SELECT * FROM bahan_baku ORDER BY nama_bahan");

// Query riwayat dengan filter
$where = [];

if ($tgl_awal && $tgl_akhir) {
    $where[] = "h.tanggal_selesai BETWEEN '$tgl_awal' AND '$tgl_akhir'";
}
if ($id_pemotong) {
    $where[] = "pg.id_pemotong = $id_pemotong";
}
if ($id_bahan) {
    $where[] = "pg.id_bahan = $id_bahan";
}

$where_sql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";


// Ubah query riwayat untuk mendapatkan lebih banyak data pembayaran
$riwayat = query("
    SELECT 
        h.id_hasil_potong, h.tanggal_selesai, h.jumlah_hasil, h.total_upah,
        p.nama_bahan, p.satuan, 
        pm.nama_pemotong, 
        pg.jumlah_bahan, pg.id_pemotong,
        t.tarif_per_unit,
        d.id_detail, pb.id_pembayaran, pb.status as status_pembayaran
    FROM hasil_pemotongan h
    JOIN pengiriman_pemotong pg ON h.id_pengiriman_potong = pg.id_pengiriman_potong
    JOIN bahan_baku p ON pg.id_bahan = p.id_bahan
    JOIN pemotong pm ON pg.id_pemotong = pm.id_pemotong
    LEFT JOIN tarif_upah t ON h.id_tarif = t.id_tarif
    LEFT JOIN detail_pembayaran_upah d ON h.id_hasil_potong = d.id_hasil AND d.jenis_hasil = 'potong'
    LEFT JOIN pembayaran_upah pb ON d.id_pembayaran = pb.id_pembayaran
    $where_sql
    ORDER BY h.tanggal_selesai DESC
");


$total_upah_per_pemotong = query("
    SELECT pm.id_pemotong, pm.nama_pemotong, 
           SUM(h.total_upah) - IFNULL((
               SELECT SUM(c.jumlah_cicilan) 
               FROM cicilan_upah c
               JOIN pembayaran_upah pu ON c.id_pembayaran = pu.id_pembayaran
               WHERE pu.id_penerima = pm.id_pemotong AND pu.jenis_penerima = 'pemotong'
           ), 0) as total_upah,
           COUNT(h.id_hasil_potong) as jumlah_produksi
    FROM hasil_pemotongan h
    JOIN pengiriman_pemotong pg ON h.id_pengiriman_potong = pg.id_pengiriman_potong
    JOIN pemotong pm ON pg.id_pemotong = pm.id_pemotong
    LEFT JOIN detail_pembayaran_upah d ON h.id_hasil_potong = d.id_hasil AND d.jenis_hasil = 'potong'
    WHERE d.id_detail IS NULL
    " . ($where_sql ? " AND $where_sql" : "") . "
    GROUP BY pm.id_pemotong, pm.nama_pemotong
    HAVING total_upah > 0
    ORDER BY total_upah DESC
");

?>


<style>
    /* Paksa SweetAlert berada di atas segalanya */
    .swal2-container {
        z-index: 99999 !important;
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

    <?php include_once '../includes/navbar.php'; ?>

    <!-- [ Main Content ] start -->
    <div class="pc-container">
        <div class="pc-content">
            <!-- [ breadcrumb ] start -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h5 class="m-b-10">Home</h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="../dashboard/index.html">Home</a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="javascript: void(0)">Dashboard</a>
                                </li>
                                <li class="breadcrumb-item" aria-current="page">Home</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->
            <!-- [ Main Content ] start -->
            <div class="row">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Biaya Upah Pemotong</h2>
                    <div>
                        <a href="list.php" class="btn btn-success">
                            <i class="bx bx-plus-circle"></i> Kembali
                        </a>
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
                    <!-- /Tampilkan pesan error atau success -->

                    <div class="card p-4 shadow-sm">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <form class="row g-3 mb-4" method="get" hidden>
                            <div class="col-md-3">
                                <label for="tgl_awal" class="form-label">Tanggal Awal</label>
                                <input type="date" id="tgl_awal" name="tgl_awal" class="form-control" value="<?= htmlspecialchars($tgl_awal) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="tgl_akhir" class="form-label">Tanggal Akhir</label>
                                <input type="date" id="tgl_akhir" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="pemotong" class="form-label">Pemotong</label>
                                <select name="pemotong" id="pemotong" class="form-select">
                                    <option value="">-- Semua Pemotong --</option>
                                    <?php foreach ($list_pemotong as $p): ?>
                                        <option value="<?= $p['id_pemotong'] ?>" <?= $id_pemotong == $p['id_pemotong'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nama_pemotong']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="bahan" class="form-label">Bahan Baku</label>
                                <select name="bahan" id="bahan" class="form-select">
                                    <option value="">-- Semua Bahan --</option>
                                    <?php foreach ($list_bahan as $b): ?>
                                        <option value="<?= $b['id_bahan'] ?>" <?= $id_bahan == $b['id_bahan'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['nama_bahan']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary me-2">Terapkan Filter</button>
                                <a href="riwayat_hasil_pemotongan.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </form>


                        <!-- tabel -->
                        <h4 class="mb-3">Tabel Riwayat Hasil Pemotongan</h4>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle">
                                <thead class="table-light">
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Bahan Baku</th>
                                        <th>Pemotong</th>
                                        <th>Bahan Digunakan</th>
                                        <th>Jumlah Hasil (pcs)</th>
                                        <th>Tarif per Unit</th>
                                        <th>Total Upah</th>
                                        <th>Status Pembayaran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($riwayat)): ?>
                                        <?php $no = 1;
                                        foreach ($riwayat as $r): ?>
                                            <tr>
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td><?= dateIndo($r['tanggal_selesai'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['nama_bahan'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['nama_pemotong'] ?? '') ?></td>
                                                <td class="text-center">
                                                    <?= isset($r['jumlah_bahan'], $r['satuan']) ?
                                                        number_format($r['jumlah_bahan'], 0, "", "") . " " . htmlspecialchars($r['satuan']) : '-' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= isset($r['jumlah_hasil']) ? number_format($r['jumlah_hasil'], 0, "", "") . ' pcs' : '-' ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= isset($r['tarif_per_unit']) ? 'Rp ' . number_format($r['tarif_per_unit'], 0, ',', '.') : '-' ?>
                                                </td>
                                                <td class="text-end">
                                                    <?= isset($r['total_upah']) ? 'Rp ' . number_format($r['total_upah'], 0, ',', '.') : '-' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (isset($r['status_pembayaran'])): ?>
                                                        <?php if ($r['status_pembayaran'] == 'dibayar'): ?>
                                                            <span class="badge bg-success">Sudah Dibayar</span>
                                                            <?php if (!empty($r['id_pembayaran'])): ?>
                                                                <div class="btn-group mt-1" role="group">
                                                                    <a href="detail_pembayaran.php?id=<?= $r['id_pembayaran'] ?>"
                                                                        class="btn btn-sm btn-info" hidden>Detail</a>
                                                                    <button class="btn btn-sm btn-danger btn-batal-bayar"
                                                                        data-id="<?= $r['id_pembayaran'] ?>"
                                                                        data-nama="<?= htmlspecialchars($r['nama_pemotong']) ?>" hidden>
                                                                        Batal
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>

                                                        <?php elseif ($r['status_pembayaran'] == 'terhitung'): ?>
                                                            <span class="badge bg-warning text-dark">Terhitung</span>

                                                        <?php elseif ($r['status_pembayaran'] == 'dibatalkan'): ?>
                                                            <span class="badge bg-danger">Dibatalkan</span>

                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Status Tidak Dikenal</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Status Tidak Diketahui</span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($r['id_pembayaran'])): ?>
                                                        <a href="detail_pembayaran.php?id=<?= $r['id_pembayaran'] ?>"
                                                            class="btn btn-sm btn-primary mt-1">
                                                            Detail Pembayaran
                                                        </a>
                                                    <?php endif; ?>
                                                </td>

                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Belum ada data hasil pemotongan.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Tambahkan setelah tabel riwayat -->
                        <!-- <div class="card mt-4">
                                <div class="card-header">
                                    <h5>Ringkasan Total Upah Belum Dibayar per Pemotong</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered">
                                            <thead>
                                                <tr class="text-center">
                                                    <th>No</th>
                                                    <th>Nama Pemotong</th>
                                                    <th>Jumlah Produksi</th>
                                                    <th>Total Upah Belum Dibayar</th>

                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($total_upah_per_pemotong)): ?>
                                                    <?php $no_total = 1;
                                                    foreach ($total_upah_per_pemotong as $total): ?>
                                                        <tr>
                                                            <td class="text-center"><?= $no_total++ ?></td>
                                                            <td><?= htmlspecialchars($total['nama_pemotong']) ?></td>
                                                            <td class="text-center"><?= $total['jumlah_hasil'] ?></td>
                                                            <td class="text-end">Rp <?= number_format($total['total_upah'], 0, ',', '.') ?></td>

                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center">Tidak ada upah yang belum dibayarkan</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-hapus');

        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');

                // Cek relasi produk via AJAX
                fetch(`check_produk.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.can_delete) {
                            Swal.fire({
                                title: 'Yakin hapus data produk?',
                                text: "Data yang dihapus tidak bisa dikembalikan!",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#d33',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Ya, hapus!',
                                cancelButtonText: 'Batal'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'delete.php?id=' + id;
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Tidak Dapat Dihapus',
                                html: data.message,
                                icon: 'error',
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
            });
        });
    });
</script>


</html>