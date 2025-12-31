<?php
require_once '../includes/header.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Redirect jika belum login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil semua kategori
$sql = "SELECT * FROM kas_kategori ORDER BY tipe_kategori, kelompok_kategori, nama_kategori";
$result = $conn->query($sql);
$kategori_data = [];

while ($row = $result->fetch_assoc()) {
    $kategori_data[] = $row;
}

// Proses tambah kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_kategori'])) {
    $nama_kategori = trim($_POST['nama_kategori']);
    $tipe_kategori = $_POST['tipe_kategori'];
    $kelompok_kategori = trim($_POST['kelompok_kategori']);

    if (empty($nama_kategori) || empty($tipe_kategori) || empty($kelompok_kategori)) {
        $_SESSION['error'] = "Semua field harus diisi!";
        header("Location: kas_kategori.php");
        exit();
    }

    // Cek apakah kategori sudah ada
    $sql_check = "SELECT id_kategori FROM kas_kategori WHERE nama_kategori = ? AND tipe_kategori = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("ss", $nama_kategori, $tipe_kategori);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $_SESSION['error'] = "Kategori dengan nama dan tipe yang sama sudah ada!";
        header("Location: kas_kategori.php");
        exit();
    }

    $sql_insert = "INSERT INTO kas_kategori (nama_kategori, kelompok_kategori, tipe_kategori) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql_insert);
    $stmt->bind_param("sss", $nama_kategori, $kelompok_kategori, $tipe_kategori);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Kategori berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan kategori: " . $conn->error;
    }

    header("Location: kas_kategori.php");
    exit();
}

// Proses edit kategori
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_kategori'])) {
    $id_kategori = $_POST['id_kategori'];
    $nama_kategori = trim($_POST['nama_kategori']);
    $tipe_kategori = $_POST['tipe_kategori'];
    $kelompok_kategori = trim($_POST['kelompok_kategori']);

    if (empty($nama_kategori) || empty($tipe_kategori) || empty($kelompok_kategori)) {
        $_SESSION['error'] = "Semua field harus diisi!";
        header("Location: kas_kategori.php");
        exit();
    }

    $sql_update = "UPDATE kas_kategori SET nama_kategori = ?, kelompok_kategori = ?, tipe_kategori = ? WHERE id_kategori = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("sssi", $nama_kategori, $kelompok_kategori, $tipe_kategori, $id_kategori);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Kategori berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal update kategori: " . $conn->error;
    }

    header("Location: kas_kategori.php");
    exit();
}

// Proses hapus kategori
if (isset($_GET['hapus'])) {
    $id_kategori = intval($_GET['hapus']);

    // Cek apakah kategori digunakan dalam transaksi
    $sql_check = "SELECT COUNT(*) as total FROM kas_transaksi WHERE id_kategori = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_kategori);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $check_data = $result_check->fetch_assoc();

    if ($check_data['total'] > 0) {
        $_SESSION['error'] = "Kategori tidak bisa dihapus karena sudah digunakan dalam transaksi!";
        header("Location: kas_kategori.php");
        exit();
    }

    $sql_delete = "DELETE FROM kas_kategori WHERE id_kategori = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $id_kategori);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Kategori berhasil dihapus!";
    } else {
        $_SESSION['error'] = "Gagal menghapus kategori: " . $conn->error;
    }

    header("Location: kas_kategori.php");
    exit();
}
?>

<style>
    /* Style tambahan untuk filter */
    .filter-section {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .filter-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background-color: white;
    }

    .filter-header {
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .filter-body {
        padding: 15px;
    }

    .search-box {
        position: relative;
    }

    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }

    .search-input {
        padding-left: 40px;
    }

    .btn-apply-filter {
        width: 100%;
    }

    .active-filters {
        background-color: #e7f1ff;
        border-left: 4px solid #0d6efd;
        padding: 10px 15px;
        border-radius: 4px;
        margin-bottom: 15px;
    }

    .filter-badge {
        background-color: #0d6efd;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        margin-right: 5px;
        margin-bottom: 5px;
    }

    .filter-badge i {
        margin-right: 4px;
    }

    .btn-clear {
        font-size: 0.85rem;
    }

    /* Style untuk card summary */
    .card-summary {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card-masuk {
        border-left: 5px solid #28a745;
    }

    .card-keluar {
        border-left: 5px solid #dc3545;
    }

    .card-saldo {
        border-left: 5px solid #007bff;
    }

    .table-transaksi th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .badge-masuk {
        background-color: #28a745;
    }

    .badge-keluar {
        background-color: #dc3545;
    }

    .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
    }

    .action-buttons .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .kategori-card {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 15px;
    }

    .kategori-header {
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .kategori-body {
        padding: 15px;
    }

    .kategori-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .kategori-item:last-child {
        border-bottom: none;
        font-weight: 600;
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
    }

    .kategori-name {
        flex: 1;
    }

    .kategori-total {
        font-weight: 600;
        color: #495057;
    }

    .kategori-count {
        background-color: #e9ecef;
        color: #495057;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
        margin-right: 10px;
    }

    .text-success {
        color: #28a745 !important;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .progress-bar-masuk {
        background-color: #28a745;
    }

    .progress-bar-keluar {
        background-color: #dc3545;
    }

    /* Style untuk table kategori */
    .table-kategori th {
        background-color: #f8f9fa;
        font-weight: 600;
        vertical-align: middle;
    }

    .table-kategori td {
        vertical-align: middle;
    }

    .kelompok-badge {
        background-color: #6c757d;
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        display: inline-block;
    }

    .badge-success {
        background-color: #28a745 !important;
    }

    .badge-danger {
        background-color: #dc3545 !important;
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Manajemen Kategori Kas</h2>
                    <div class="d-flex gap-2">
                        <a href="kas.php" class="btn btn-secondary">
                            <i class="ti ti-arrow-left"></i> Kembali
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahModal">
                            <i class="ti ti-circle-plus"></i> Tambah Kategori
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
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

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card card-summary card-masuk">
                        <div class="card-body">
                            <h5 class="card-title"><i class="ti ti-arrow-down-right text-success"></i> Kategori Masuk</h5>
                            <?php
                            $count_masuk = 0;
                            $kelompok_masuk = [];
                            foreach ($kategori_data as $kategori) {
                                if ($kategori['tipe_kategori'] == 'MASUK') {
                                    $count_masuk++;
                                    if (!in_array($kategori['kelompok_kategori'], $kelompok_masuk)) {
                                        $kelompok_masuk[] = $kategori['kelompok_kategori'];
                                    }
                                }
                            }
                            ?>
                            <h2 class="card-text text-success"><?= $count_masuk ?> Kategori</h2>
                            <p class="card-text">
                                <small class="text-muted">
                                    <?= count($kelompok_masuk) ?> Kelompok:
                                    <?= implode(', ', $kelompok_masuk) ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-summary card-keluar">
                        <div class="card-body">
                            <h5 class="card-title"><i class="ti ti-arrow-up-right text-danger"></i> Kategori Keluar</h5>
                            <?php
                            $count_keluar = 0;
                            $kelompok_keluar = [];
                            foreach ($kategori_data as $kategori) {
                                if ($kategori['tipe_kategori'] == 'KELUAR') {
                                    $count_keluar++;
                                    if (!in_array($kategori['kelompok_kategori'], $kelompok_keluar)) {
                                        $kelompok_keluar[] = $kategori['kelompok_kategori'];
                                    }
                                }
                            }
                            ?>
                            <h2 class="card-text text-danger"><?= $count_keluar ?> Kategori</h2>
                            <p class="card-text">
                                <small class="text-muted">
                                    <?= count($kelompok_keluar) ?> Kelompok:
                                    <?= implode(', ', $kelompok_keluar) ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Kategori -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="ti ti-category"></i> Daftar Kategori Kas
                    </h5>
                    <span class="badge bg-primary">
                        Total: <?= count($kategori_data) ?> Kategori
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered table-kategori">
                            <thead class="text-center">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="25%">Nama Kategori</th>
                                    <th width="25%">Kelompok</th>
                                    <th width="15%">Tipe</th>
                                    <th width="20%" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kategori_data)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="ti ti-category-off display-4 text-muted mb-2 d-block"></i>
                                            Belum ada kategori
                                            <br>
                                            <small class="text-muted">Mulai dengan menambahkan kategori baru</small>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; ?>
                                    <?php
                                    $current_tipe = '';
                                    $current_kelompok = '';
                                    ?>
                                    <?php foreach ($kategori_data as $kategori): ?>
                                        <?php if ($current_tipe != $kategori['tipe_kategori']): ?>
                                            <tr class="table-light">
                                                <td colspan="5" class="fw-bold text-<?= $kategori['tipe_kategori'] == 'MASUK' ? 'success' : 'danger' ?>">
                                                    <i class="ti ti-arrow-<?= $kategori['tipe_kategori'] == 'MASUK' ? 'down-right' : 'up-right' ?>"></i>
                                                    <?= $kategori['tipe_kategori'] == 'MASUK' ? 'PEMASUKAN' : 'PENGELUARAN' ?>
                                                </td>
                                            </tr>
                                            <?php $current_tipe = $kategori['tipe_kategori']; ?>
                                        <?php endif; ?>

                                        <?php if ($current_kelompok != $kategori['kelompok_kategori']): ?>
                                            <tr class="table-secondary">
                                                <td colspan="5" class="fw-bold">
                                                    <i class="ti ti-folder"></i> Kelompok: <?= htmlspecialchars($kategori['kelompok_kategori']) ?>
                                                </td>
                                            </tr>
                                            <?php $current_kelompok = $kategori['kelompok_kategori']; ?>
                                        <?php endif; ?>

                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td>

                                                <?= htmlspecialchars($kategori['nama_kategori']) ?>
                                            </td>
                                            <td>
                                                <span class="kelompok-badge">
                                                    </i> <?= htmlspecialchars($kategori['kelompok_kategori']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $kategori['tipe_kategori'] == 'MASUK' ? 'badge-success' : 'badge-danger' ?>">
                                                    <?= $kategori['tipe_kategori'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2">
                                                    <button type="button" class="btn btn-sm btn-warning"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editModal"
                                                        data-id="<?= $kategori['id_kategori'] ?>"
                                                        data-nama="<?= htmlspecialchars($kategori['nama_kategori']) ?>"
                                                        data-kelompok="<?= htmlspecialchars($kategori['kelompok_kategori']) ?>"
                                                        data-tipe="<?= $kategori['tipe_kategori'] ?>"
                                                        title="Edit Kategori">
                                                        <i class="ti ti-pencil"></i>
                                                    </button>
                                                    <a href="kas_kategori.php?hapus=<?= $kategori['id_kategori'] ?>"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Yakin ingin menghapus kategori ini?\n\nNote: Kategori tidak bisa dihapus jika sudah digunakan dalam transaksi.')"
                                                        title="Hapus Kategori">
                                                        <i class="ti ti-trash"></i>
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

        <!-- Modal Tambah Kategori -->
        <div class="modal fade" id="tambahModal" tabindex="-1" aria-labelledby="tambahModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tambahModalLabel">
                                <i class="ti ti-circle-plus"></i> Tambah Kategori Baru
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="nama_kategori" class="form-label">Nama Kategori *</label>
                                <input type="text" class="form-control" id="nama_kategori" name="nama_kategori"
                                    placeholder="Contoh: Gaji, Makan, Transportasi" required>
                                <div class="form-text">Nama kategori yang deskriptif</div>
                            </div>

                            <div class="mb-3">
                                <label for="tipe_kategori" class="form-label">Tipe Kategori *</label>
                                <select class="form-select" id="tipe_kategori" name="tipe_kategori" required>
                                    <option value="">Pilih Tipe Kategori</option>
                                    <option value="MASUK">Masuk (Pemasukan)</option>
                                    <option value="KELUAR">Keluar (Pengeluaran)</option>
                                </select>
                                <div class="form-text">Pilih apakah kategori untuk pemasukan atau pengeluaran</div>
                            </div>

                            <div class="mb-3">
                                <label for="kelompok_kategori" class="form-label">Kelompok Kategori *</label>
                                <select class="form-select" id="kelompok_kategori" name="kelompok_kategori" required>
                                    <option value="">-- Pilih Kelompok Kategori --</option>
                                    <option value="KAS MASUK">KAS MASUK</option>
                                    <option value="PEMASUKAN PARTAI">PEMASUKAN PARTAI</option>
                                    <option value="PEMASUKAN SHOPEE">PEMASUKAN SHOPEE</option>
                                    <option value="PENGELUARAN PRODUKSI">PENGELUARAN PRODUKSI</option>
                                    <option value="PENGELUARAN RUMAH TANGGA">PENGELUARAN RUMAH TANGGA</option>
                                    <option value="PENGELUARAN KANTOR">PENGELUARAN KANTOR</option>
                                    <option value="PENGELUARAN LAIN-LAIN">PENGELUARAN LAIN-LAIN</option>
                                    <option value="PIUTANG">PIUTANG</option>
                                </select>
                                <div class="form-text">
                                    Kelompok untuk mengelompokkan kategori serupa
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Batal
                            </button>
                            <button type="submit" name="tambah_kategori" class="btn btn-primary">
                                <i class="ti ti-check"></i> Simpan Kategori
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Kategori -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <input type="hidden" id="edit_id_kategori" name="id_kategori">

                        <div class="modal-header">
                            <h5 class="modal-title" id="editModalLabel">
                                <i class="ti ti-pencil"></i> Edit Kategori
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <!-- Nama Kategori -->
                            <div class="mb-3">
                                <label for="edit_nama_kategori" class="form-label">Nama Kategori *</label>
                                <input type="text" class="form-control" id="edit_nama_kategori" name="nama_kategori" required>
                            </div>

                            <!-- Tipe Kategori -->
                            <div class="mb-3">
                                <label for="edit_tipe_kategori" class="form-label">Tipe Kategori *</label>
                                <select class="form-select" id="edit_tipe_kategori" name="tipe_kategori" required>
                                    <option value="">Pilih Tipe Kategori</option>
                                    <option value="MASUK">Masuk (Pemasukan)</option>
                                    <option value="KELUAR">Keluar (Pengeluaran)</option>
                                </select>
                            </div>

                            <!-- Kelompok Kategori -->
                            <div class="mb-3">
                                <label for="edit_kelompok_kategori" class="form-label">Kelompok Kategori *</label>
                                <select class="form-select" id="edit_kelompok_kategori" name="kelompok_kategori" required>
                                    <option value="">-- Pilih Kelompok Kategori --</option>
                                    <option value="KAS MASUK">KAS MASUK</option>
                                    <option value="PEMASUKAN PARTAI">PEMASUKAN PARTAI</option>
                                    <option value="PEMASUKAN SHOPEE">PEMASUKAN SHOPEE</option>
                                    <option value="PENGELUARAN PRODUKSI">PENGELUARAN PRODUKSI</option>
                                    <option value="PENGELUARAN RUMAH TANGGA">PENGELUARAN RUMAH TANGGA</option>
                                    <option value="PENGELUARAN KANTOR">PENGELUARAN KANTOR</option>
                                    <option value="PENGELUARAN LAIN-LAIN">PENGELUARAN LAIN-LAIN</option>
                                    <option value="PIUTANG">PIUTANG</option>
                                </select>
                                <div class="form-text">
                                    Kelompok untuk mengelompokkan kategori serupa
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Batal
                            </button>
                            <button type="submit" name="edit_kategori" class="btn btn-primary">
                                <i class="ti ti-check"></i> Update Kategori
                            </button>
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
    // Handle modal edit show
    const editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;

        const id = button.getAttribute('data-id');
        const nama = button.getAttribute('data-nama');
        const kelompok = button.getAttribute('data-kelompok');
        const tipe = button.getAttribute('data-tipe');

        document.getElementById('edit_id_kategori').value = id;
        document.getElementById('edit_nama_kategori').value = nama;
        document.getElementById('edit_kelompok_kategori').value = kelompok;
        document.getElementById('edit_tipe_kategori').value = tipe;
    });

    // Auto focus pada input nama saat modal tambah dibuka
    const tambahModal = document.getElementById('tambahModal');
    tambahModal.addEventListener('shown.bs.modal', function() {
        document.getElementById('nama_kategori').focus();
    });

    // Alert messages auto hide
    document.addEventListener('DOMContentLoaded', function() {
        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });

    // Prevent form submission if validation fails
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('input[required], select[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');

                    // Add error message if not exists
                    if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Field ini wajib diisi';
                        input.parentNode.appendChild(errorDiv);
                    }
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        });
    });

    // Clear validation on input
    document.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const errorDiv = this.nextElementSibling;
            if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                errorDiv.remove();
            }
        });
    });
</script>

</html>