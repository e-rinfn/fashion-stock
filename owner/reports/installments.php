<?php
require_once '../../config/auth.php';
require_role('owner');

$title = "Laporan Angsuran";
include '../../includes/header.php';

// Status filter
$status_filter = '';
if (isset($_GET['status']) && in_array($_GET['status'], ['lunas', 'belum_lunas', 'terlambat'])) {
    $status_filter = "AND i.status = '" . $_GET['status'] . "'";
}

require_once '../../config/database.php';

// Query untuk data angsuran
$stmt = $pdo->prepare("
    SELECT 
        i.*, 
        inv.no_invoice, 
        t.customer_nama, 
        t.customer_kontak,
        inv.total as invoice_total,
        inv.dibayar as paid_amount,
        inv.sisa as remaining_amount
    FROM installments i
    JOIN invoices inv ON i.invoice_id = inv.id
    JOIN transactions t ON inv.transaction_id = t.id
    WHERE inv.status_bayar IN ('cicilan', 'belum_lunas')
    $status_filter
    ORDER BY i.tanggal_jatuh_tempo ASC
");
$stmt->execute();
$installments = $stmt->fetchAll();
?>

<div class="container py-4">
    <h1 class="mb-4">Laporan Angsuran</h1>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Filter Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="belum_lunas" <?= isset($_GET['status']) && $_GET['status'] == 'belum_lunas' ? 'selected' : '' ?>>Belum Lunas</option>
                        <option value="lunas" <?= isset($_GET['status']) && $_GET['status'] == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="terlambat" <?= isset($_GET['status']) && $_GET['status'] == 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="installments.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Data Angsuran</h5>
            <a href="export_installments.php?status=<?= $_GET['status'] ?? '' ?>" class="btn btn-sm btn-success">Export Excel</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Customer</th>
                            <th>Kontak</th>
                            <th>Jatuh Tempo</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Total Invoice</th>
                            <th>Dibayar</th>
                            <th>Sisa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($installments as $installment):
                            $is_overdue = $installment['status'] == 'belum_lunas' &&
                                strtotime($installment['tanggal_jatuh_tempo']) < time();
                            $status_class = $installment['status'] == 'lunas' ? 'bg-success' : ($is_overdue ? 'bg-danger' : 'bg-warning');
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($installment['no_invoice']) ?></td>
                                <td><?= htmlspecialchars($installment['customer_nama']) ?></td>
                                <td><?= htmlspecialchars($installment['customer_kontak']) ?></td>
                                <td><?= date('d/m/Y', strtotime($installment['tanggal_jatuh_tempo'])) ?></td>
                                <td>Rp <?= number_format($installment['jumlah'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge <?= $status_class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $installment['status'])) ?>
                                        <?= $is_overdue ? ' (Terlambat)' : '' ?>
                                    </span>
                                </td>
                                <td>Rp <?= number_format($installment['invoice_total'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($installment['paid_amount'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($installment['remaining_amount'], 0, ',', '.') ?></td>
                                <td>
                                    <a href="../invoices/view.php?id=<?= $installment['invoice_id'] ?>" class="btn btn-sm btn-info">Detail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>