<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

function bulanTahunIndo($tanggal)
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

    $timestamp = strtotime($tanggal);
    $bulan = $bulanIndo[(int)date('n', $timestamp)];
    $tahun = date('Y', $timestamp);

    return $bulan . ' ' . $tahun;
}

// Ambil data untuk filter
$pemotong = query("SELECT * FROM pemotong ORDER BY nama_pemotong");
$penjahit = query("SELECT * FROM penjahit ORDER BY nama_penjahit");
$petugas_finishing = query("SELECT * FROM petugas_finishing ORDER BY nama_petugas");

// Filter parameters
$jenis_karyawan = isset($_GET['jenis_karyawan']) ? $_GET['jenis_karyawan'] : 'all';
$id_karyawan = isset($_GET['id_karyawan']) ? intval($_GET['id_karyawan']) : 0;
$status_hutang = isset($_GET['status_hutang']) ? $_GET['status_hutang'] : 'all';
$periode = isset($_GET['periode']) ? $_GET['periode'] : '';
$search_karyawan = isset($_GET['search_karyawan']) ? $_GET['search_karyawan'] : '';

// PERBAIKAN: Gunakan 'finishing' bukan 'petugas_finishing'
$sql = "SELECT h.*, 
               CASE 
                   WHEN h.jenis_karyawan = 'pemotong' THEN p.nama_pemotong
                   WHEN h.jenis_karyawan = 'penjahit' THEN j.nama_penjahit
                   WHEN h.jenis_karyawan = 'finishing' THEN pf.nama_petugas
                   ELSE '-'
               END AS nama_karyawan
        FROM hutang_upah h
        LEFT JOIN pemotong p 
            ON h.jenis_karyawan = 'pemotong' 
           AND h.id_karyawan = p.id_pemotong
        LEFT JOIN penjahit j 
            ON h.jenis_karyawan = 'penjahit' 
           AND h.id_karyawan = j.id_penjahit
        LEFT JOIN petugas_finishing pf 
            ON h.jenis_karyawan = 'finishing' 
           AND h.id_karyawan = pf.id_petugas_finishing
        WHERE 1=1";

$params = [];

// PERBAIKAN: Sesuaikan filter jenis karyawan
if ($jenis_karyawan !== 'all') {
    // Ubah 'petugas_finishing' menjadi 'finishing' jika dipilih
    $jenis_filter = ($jenis_karyawan == 'petugas_finishing') ? 'finishing' : $jenis_karyawan;
    $sql .= " AND h.jenis_karyawan = ?";
    $params[] = $jenis_filter;
}

// Filter pencarian karyawan
if (!empty($search_karyawan)) {
    $sql .= " AND (
        p.nama_pemotong LIKE ?
        OR j.nama_penjahit LIKE ?
        OR pf.nama_petugas LIKE ?
    )";

    $search_param = "%" . $search_karyawan . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Filter karyawan spesifik (jika menggunakan ID)
if ($id_karyawan > 0 && $jenis_karyawan !== 'all') {
    $sql .= " AND h.id_karyawan = ?";
    $params[] = $id_karyawan;
}

// Filter status
if ($status_hutang != 'all') {
    $sql .= " AND h.status = ?";
    $params[] = $status_hutang;
}

// Filter periode
if (!empty($periode)) {
    $sql .= " AND h.periode = ?";
    $params[] = $periode;
}

$sql .= " ORDER BY h.periode DESC, h.jenis_karyawan";

// Prepare and execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $hutang = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $hutang = query($sql);
}

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Bayar Hutang
    if (isset($_POST['bayar_hutang'])) {
        $id_hutang = intval($_POST['id_hutang']);
        $tanggal_bayar = $conn->real_escape_string($_POST['tanggal_bayar']);
        $jumlah_bayar = floatval($_POST['jumlah_bayar']);
        $metode_bayar = $conn->real_escape_string($_POST['metode_bayar']);
        $keterangan = $conn->real_escape_string($_POST['keterangan']);

        // Validasi
        $detail_hutang = getDetailHutang($id_hutang);
        if ($jumlah_bayar <= 0) {
            $error = "Jumlah pembayaran harus lebih dari 0";
        } elseif ($jumlah_bayar > $detail_hutang['sisa_hutang']) {
            $error = "Jumlah pembayaran tidak boleh melebihi sisa hutang";
        } else {
            if (bayarHutangUpah($id_hutang, $tanggal_bayar, $jumlah_bayar, $metode_bayar, $keterangan)) {
                $_SESSION['success'] = "Pembayaran berhasil dicatat";
                header("Location: upah.php?" . http_build_query($_GET));
                exit();
            } else {
                $error = "Gagal mencatat pembayaran";
            }
        }
    }

    // Batal Pembayaran
    if (isset($_POST['batal_pembayaran'])) {
        $id_pembayaran = intval($_POST['id_pembayaran']);

        if (batalPembayaranUpah($id_pembayaran)) {
            $_SESSION['success'] = "Pembayaran berhasil dibatalkan";
            header("Location: upah.php?" . http_build_query($_GET));
            exit();
        } else {
            $error = "Gagal membatalkan pembayaran";
        }
    }
}
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

    .filter-section {
        background-color: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .search-input-group {
        position: relative;
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ced4da;
        border-top: none;
        border-radius: 0 0 0.375rem 0.375rem;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .search-result-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f8f9fa;
    }

    .search-result-item:hover {
        background-color: #f8f9fa;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-item small {
        color: #6c757d;
        font-size: 0.8em;
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

            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Hutang Upah</h2>
                    <button type="button" class="btn btn-outline-primary" onclick="toggleFilter()">
                        <i class="ti ti-filter"></i> Filter
                    </button>
                </div>

                <!-- Filter Form -->
                <div id="filterSection" style="display: none;">
                    <div class="card shadow-sm mb-3 mt-2">
                        <div class="card-body">
                            <form method="GET" class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Jenis Karyawan</label>
                                    <select name="jenis_karyawan" class="form-select" id="jenisKaryawanSelect">
                                        <option value="all">Semua Jenis</option>
                                        <option value="pemotong" <?= $jenis_karyawan == 'pemotong' ? 'selected' : '' ?>>Pemotong</option>
                                        <option value="penjahit" <?= $jenis_karyawan == 'penjahit' ? 'selected' : '' ?>>Penjahit</option>
                                        <option value="finishing" <?= $jenis_karyawan == 'finishing' || $jenis_karyawan == 'petugas_finishing' ? 'selected' : '' ?>>Finishing</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Cari Karyawan</label>
                                    <div class="search-input-group">
                                        <input type="text"
                                            name="search_karyawan"
                                            class="form-control"
                                            id="searchKaryawan"
                                            placeholder="Ketik nama karyawan..."
                                            value="<?= htmlspecialchars($search_karyawan) ?>"
                                            autocomplete="off">
                                        <input type="hidden" name="id_karyawan" id="selectedKaryawanId" value="<?= $id_karyawan ?>">
                                        <div class="search-results" id="searchResults"></div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Status Hutang</label>
                                    <select name="status_hutang" class="form-select">
                                        <option value="all" <?= $status_hutang == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                        <option value="belum_lunas" <?= $status_hutang == 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                                        <option value="lunas" <?= $status_hutang == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                                    </select>
                                </div>

                                <div hidden class="col-md-4">
                                    <label class="form-label">Periode</label>
                                    <input type="month" name="periode" class="form-control" value="<?= $periode ?>">
                                </div>

                                <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-filter"></i> Filter
                                    </button>
                                    <?php if ($jenis_karyawan != 'all' || !empty($search_karyawan) || $status_hutang != 'all' || !empty($periode)): ?>
                                        <a href="upah.php" class="btn btn-secondary">
                                            <i class="ti ti-rotate"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
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
                    <!-- /Tampilkan pesan error atau success -->

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width: 5%;">No</th>
                                    <!-- <th style="width: 12%;">Periode</th> -->
                                    <th style="width: 15%;">Karyawan</th>
                                    <th style="width: 10%;">Jenis</th>
                                    <th style="width: 13%;">Total Upah</th>
                                    <th style="width: 13%;">Total Dibayar</th>
                                    <th style="width: 13%;">Sisa Upah</th>
                                    <th style="width: 10%;">Status</th>
                                    <th style="width: 9%;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($hutang)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Tidak ada data hutang upah</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php foreach ($hutang as $h): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <!-- <td><?= bulanTahunIndo($h['periode']) ?></td> -->
                                            <td><?= htmlspecialchars($h['nama_karyawan']) ?></td>
                                            <td class="text-center">
                                                <?php
                                                // PERBAIKAN: Gunakan 'finishing' untuk pengecekan
                                                $jenis_display = $h['jenis_karyawan'];
                                                $badgeColor = match ($jenis_display) {
                                                    'pemotong' => 'warning',
                                                    'penjahit' => 'info',
                                                    'finishing' => 'success',
                                                    default => 'secondary'
                                                };

                                                // Tampilkan dengan format yang benar
                                                $jenis_text = match ($jenis_display) {
                                                    'pemotong' => 'Pemotong',
                                                    'penjahit' => 'Penjahit',
                                                    'finishing' => 'Finishing',
                                                    default => $jenis_display
                                                };
                                                ?>

                                                <span class="badge bg-<?= $badgeColor ?>">
                                                    <?= $jenis_text ?>
                                                </span>

                                            </td>
                                            <td><?= formatRupiah($h['total_upah']) ?></td>
                                            <td><?= formatRupiah($h['total_dibayar']) ?></td>
                                            <td class="<?= $h['sisa_hutang'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                                <?= formatRupiah($h['sisa_hutang']) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $h['sisa_hutang'] <= 0 ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($h['sisa_hutang'] <= 0 ? 'Lunas' : 'Belum Lunas') ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group-actions">
                                                    <button class="btn btn-sm btn-primary btn-bayar"
                                                        data-id="<?= $h['id_hutang'] ?>"
                                                        data-nama="<?= htmlspecialchars($h['nama_karyawan']) ?>"
                                                        data-sisa="<?= $h['sisa_hutang'] ?>"
                                                        <?= $h['sisa_hutang'] <= 0 ? 'disabled' : '' ?>
                                                        title="Bayar Hutang">
                                                        <i class="ti ti-cash"></i>
                                                    </button>
                                                    <a href="detail_hutang.php?id=<?= $h['id_hutang'] ?>" class="btn btn-sm btn-info" title="Detail">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
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

    <!-- Modal Pembayaran -->
    <div class="modal fade" id="modalBayar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Bayar Hutang Upah</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_hutang" id="bayar_id_hutang">

                        <div class="mb-3">
                            <label>Karyawan</label>
                            <input type="text" class="form-control" id="bayar_nama_karyawan" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Sisa Hutang</label>
                            <input type="text" class="form-control" id="bayar_sisa_hutang" readonly>
                        </div>

                        <div class="mb-3">
                            <label>Tanggal Bayar *</label>
                            <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label>Jumlah Bayar *</label>
                            <input type="number" name="jumlah_bayar" class="form-control" min="1" value="">
                        </div>

                        <div class="mb-3">
                            <label>Metode Bayar</label>
                            <select name="metode_bayar" class="form-control" required>
                                <option value="tunai">Tunai</option>
                                <option value="transfer">Transfer</option>
                                <option value="e-wallet">E-Wallet</option>
                            </select>
                        </div>

                        <div hidden class="mb-3">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="bayar_hutang" class="btn btn-primary">Simpan Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- [ Main Content ] end -->

    <?php include_once '../includes/footer.php'; ?>

</body>
<!-- [Body] end -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    function toggleFilter() {
        const filter = document.getElementById('filterSection');
        filter.style.display = filter.style.display === 'none' ? 'block' : 'none';
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalBayar = new bootstrap.Modal(document.getElementById('modalBayar'));
        const searchInput = document.getElementById('searchKaryawan');
        const searchResults = document.getElementById('searchResults');
        const selectedKaryawanId = document.getElementById('selectedKaryawanId');
        const jenisSelect = document.getElementById('jenisKaryawanSelect');

        // Data karyawan untuk pencarian - PERBAIKAN: tambah petugas finishing
        const karyawanData = [
            <?php if ($jenis_karyawan == 'pemotong' || $jenis_karyawan == 'all'): ?>
                <?php foreach ($pemotong as $p): ?> {
                        id: <?= $p['id_pemotong'] ?>,
                        nama: "<?= htmlspecialchars($p['nama_pemotong']) ?>",
                        jenis: "pemotong",
                        display: "<?= htmlspecialchars($p['nama_pemotong']) ?> (Pemotong)"
                    },
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($jenis_karyawan == 'penjahit' || $jenis_karyawan == 'all'): ?>
                <?php foreach ($penjahit as $j): ?> {
                        id: <?= $j['id_penjahit'] ?>,
                        nama: "<?= htmlspecialchars($j['nama_penjahit']) ?>",
                        jenis: "penjahit",
                        display: "<?= htmlspecialchars($j['nama_penjahit']) ?> (Penjahit)"
                    },
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($jenis_karyawan == 'finishing' || $jenis_karyawan == 'all'): ?>
                <?php foreach ($petugas_finishing as $pf): ?> {
                        id: <?= $pf['id_petugas_finishing'] ?>,
                        nama: "<?= htmlspecialchars($pf['nama_petugas']) ?>",
                        jenis: "finishing", // PERBAIKAN: gunakan 'finishing'
                        display: "<?= htmlspecialchars($pf['nama_petugas']) ?> (Finishing)"
                    },
                <?php endforeach; ?>
            <?php endif; ?>
        ];

        // Filter data berdasarkan jenis karyawan
        function getFilteredKaryawanData() {
            const selectedJenis = jenisSelect.value;
            if (selectedJenis === 'all') {
                return karyawanData;
            }
            return karyawanData.filter(k => k.jenis === selectedJenis);
        }

        // Pencarian karyawan
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const filteredData = getFilteredKaryawanData();

            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                selectedKaryawanId.value = '';
                return;
            }

            const results = filteredData.filter(karyawan =>
                karyawan.nama.toLowerCase().includes(searchTerm) ||
                karyawan.display.toLowerCase().includes(searchTerm)
            );

            displaySearchResults(results);
        });

        // Tampilkan hasil pencarian
        function displaySearchResults(results) {
            searchResults.innerHTML = '';

            if (results.length === 0) {
                searchResults.innerHTML = '<div class="search-result-item text-muted">Tidak ditemukan</div>';
                searchResults.style.display = 'block';
                return;
            }

            results.forEach(karyawan => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                div.innerHTML = `
                    <div>${karyawan.display}</div>
                `;
                div.addEventListener('click', function() {
                    searchInput.value = karyawan.nama;
                    selectedKaryawanId.value = karyawan.id;
                    searchResults.style.display = 'none';
                });
                searchResults.appendChild(div);
            });

            searchResults.style.display = 'block';
        }

        // Sembunyikan hasil pencarian ketika klik di luar
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        // Reset pencarian ketika jenis karyawan berubah
        jenisSelect.addEventListener('change', function() {
            searchInput.value = '';
            selectedKaryawanId.value = '';
            searchResults.style.display = 'none';
        });

        // Modal pembayaran
        document.querySelectorAll('.btn-bayar').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const nama = this.dataset.nama;
                const sisa = this.dataset.sisa;

                document.getElementById('bayar_id_hutang').value = id;
                document.getElementById('bayar_nama_karyawan').value = nama;
                document.getElementById('bayar_sisa_hutang').value = formatRupiah(sisa);

                // Set max value untuk input jumlah bayar
                const jumlahInput = document.querySelector('input[name="jumlah_bayar"]');
                jumlahInput.max = sisa;
                jumlahInput.value = ''; // Default isi dengan sisa hutang

                modalBayar.show();
            });
        });

        function formatRupiah(amount) {
            return 'Rp ' + Number(amount).toLocaleString('id-ID');
        }
    });
</script>

</html>