<?php
require_once '../../../config/auth.php';
require_role('admin');

$title = "Daftar Penjualan";

// Ambil data transaksi penjualan
require_once '../../../config/database.php';
$stmt = $pdo->prepare("SELECT t.*, u.full_name, i.no_invoice 
                      FROM transactions t
                      JOIN users u ON t.user_id = u.id
                      LEFT JOIN invoices i ON t.id = i.transaction_id
                      WHERE t.tipe = 'keluar'
                      ORDER BY t.tanggal DESC");
$stmt->execute();
$transactions = $stmt->fetchAll();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Daftar Penjualan</h2>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Penjualan
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No. Transaksi</th>
                            <th>Tanggal</th>
                            <th>Customer</th>
                            <th>Invoice</th>
                            <th>Total</th>
                            <th>Admin</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $trx): ?>
                            <tr>
                                <td><?= htmlspecialchars($trx['kode_transaksi']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($trx['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($trx['customer_nama'] ?? '-') ?></td>
                                <td><?= $trx['no_invoice'] ?? '-' ?></td>
                                <td>Rp <?= number_format($trx['total'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($trx['full_name']) ?></td>
                                <td>
                                    <a href="detail.php?id=<?= $trx['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>