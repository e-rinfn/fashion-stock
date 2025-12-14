<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ Main Content ] start -->
        <div class="row">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Manajemen Hutang Upah (Akumulatif)</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahHutang">
                    <i class="ti ti-plus"></i> Tambah Hutang
                </button>
            </div>

            <!-- Ringkasan Card -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-2">Total Hutang Aktif</h6>
                            <?php
                            $total_hutang_aktif = 0;
                            foreach ($hutang_akumulatif as $ha) {
                                $total_hutang_aktif += $ha['sisa_hutang'];
                            }
                            ?>
                            <h3 class="mb-0"><?= formatRupiah($total_hutang_aktif) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body py-3">
                            <h6 class="mb-2">Karyawan Berhutang</h6>
                            <h3 class="mb-0"><?= count($hutang_akumulatif) ?> Orang</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="filter-section">
                <form method="GET" class="row">
                    <!-- ... (filter sama seperti sebelumnya) ... -->
                </form>
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

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th style="width: 5%;">No</th>
                                <th style="width: 15%;">Karyawan</th>
                                <th style="width: 10%;">Jenis</th>
                                <th style="width: 15%;">Total Hutang</th>
                                <th style="width: 15%;">Total Dibayar</th>
                                <th style="width: 15%;">Sisa Hutang</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 15%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hutang_akumulatif)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Tidak ada data hutang upah</td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; ?>
                                <?php foreach ($hutang_akumulatif as $ha): ?>
                                    <?php
                                    // Ambil detail hutang bulanan
                                    $sql_detail = "SELECT * FROM hutang_bulanan 
                                                  WHERE id_akumulatif = ? 
                                                  ORDER BY periode DESC";
                                    $stmt_detail = $conn->prepare($sql_detail);
                                    $stmt_detail->bind_param("i", $ha['id_akumulatif']);
                                    $stmt_detail->execute();
                                    $detail_bulanan = $stmt_detail->get_result()->fetch_all(MYSQLI_ASSOC);
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($ha['nama_karyawan']) ?></strong>
                                            <!-- Tombol toggle detail hutang bulanan -->
                                            <button class="btn btn-sm btn-link text-primary p-0"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#detailHutang<?= $ha['id_akumulatif'] ?>">
                                                <small>
                                                    <i class="ti ti-chevron-down"></i>
                                                    <?= count($detail_bulanan) ?> bulan
                                                </small>
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $ha['jenis_karyawan'] == 'pemotong' ? 'warning' : 'info' ?>">
                                                <?= ucfirst($ha['jenis_karyawan']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= formatRupiah($ha['total_hutang']) ?></td>
                                        <td class="text-end"><?= formatRupiah($ha['total_dibayar']) ?></td>
                                        <td class="text-end <?= $ha['sisa_hutang'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                            <?= formatRupiah($ha['sisa_hutang']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $ha['status'] == 'lunas' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($ha['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group-actions">
                                                <button class="btn btn-sm btn-primary btn-bayar-akumulatif"
                                                    data-id="<?= $ha['id_akumulatif'] ?>"
                                                    data-nama="<?= htmlspecialchars($ha['nama_karyawan']) ?>"
                                                    data-sisa="<?= $ha['sisa_hutang'] ?>"
                                                    <?= $ha['sisa_hutang'] <= 0 ? 'disabled' : '' ?>
                                                    title="Bayar Hutang">
                                                    <i class="ti ti-cash"></i> Bayar
                                                </button>
                                                <a href="detail_hutang_akumulatif.php?id=<?= $ha['id_akumulatif'] ?>"
                                                    class="btn btn-sm btn-info"
                                                    title="Detail & Riwayat">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Detail hutang bulanan (collapse) -->
                                    <tr class="collapse" id="detailHutang<?= $ha['id_akumulatif'] ?>">
                                        <td colspan="8" class="p-0">
                                            <div class="p-3 bg-light">
                                                <h6 class="mb-3">Detail Hutang per Bulan:</h6>
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Periode</th>
                                                            <th>Jumlah Hutang</th>
                                                            <th>Keterangan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($detail_bulanan)): ?>
                                                            <tr>
                                                                <td colspan="3" class="text-center">Tidak ada detail</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($detail_bulanan as $detail): ?>
                                                                <tr>
                                                                    <td><?= bulanTahunIndo($detail['periode']) ?></td>
                                                                    <td><?= formatRupiah($detail['jumlah_hutang']) ?></td>
                                                                    <td><?= htmlspecialchars($detail['keterangan']) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
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