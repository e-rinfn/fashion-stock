  <!-- TOMBOL PEMBATALAN BERDASARKAN TAHAP -->
  <?php if ($tahap_pembatalan != 'tidak_ada'): ?>
      <button class="btn btn-sm btn-outline-danger btn-batal-produksi-tahap"
          data-id="<?= $data['id'] ?>"
          data-produk="<?= htmlspecialchars($data['produk']) ?>"
          data-seri="<?= htmlspecialchars($data['seri']) ?>"
          data-total-potong="<?= $data['total_hasil'] ?>"
          data-total-bordir="<?= $data['total_hasil_bordir'] ?>"
          data-total-jahit="<?= $data['total_hasil_jahit'] ?>"
          data-tahap="<?= $tahap_pembatalan ?>"
          data-status="<?= $data['status'] ?>"
          data-pemotong="<?= htmlspecialchars($data['pemotong']) ?>"
          data-bordir="<?= htmlspecialchars($data['bordir']) ?>"
          data-penjahit="<?= htmlspecialchars($data['penjahit']) ?>"
          title="Batalkan Tahap <?= ucfirst(str_replace('_', ' ', $tahap_pembatalan)) ?>">
          <i class="ti ti-x"></i>
      </button>
  <?php endif; ?>

  <!-- JAVASCRIPT -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>


  <script>
      // Event listener untuk tombol pembatalan tahap
      document.addEventListener('click', function(e) {
          if (e.target.closest('.btn-batal-produksi-tahap')) {
              // e.preventDefault();
              const button = e.target.closest('.btn-batal-produksi-tahap');
              const id = button.getAttribute('data-id');
              const produk = button.getAttribute('data-produk');
              const seri = button.getAttribute('data-seri');
              const totalPotong = button.getAttribute('data-total-potong');
              const totalBordir = button.getAttribute('data-total-bordir') || 0;
              const totalJahit = button.getAttribute('data-total-jahit') || 0;
              const tahap = button.getAttribute('data-tahap');
              const status = button.getAttribute('data-status');
              const pemotong = button.getAttribute('data-pemotong');
              const bordir = button.getAttribute('data-bordir');
              const penjahit = button.getAttribute('data-penjahit');

              // Set data ke modal
              document.getElementById('batal_id').value = id;
              document.getElementById('batal_tahap').value = tahap;

              // Buat detail berdasarkan tahap
              let detail = '';
              let keterangan = '';

              switch (tahap) {
                  case 'hasil_jahit':
                      detail = `Apakah Anda yakin ingin membatalkan <strong>hasil jahit</strong> untuk:`;
                      detail += `<br><strong>Produk:</strong> ${produk}`;
                      detail += `<br><strong>Seri:</strong> ${seri}`;
                      detail += `<br><strong>Penjahit:</strong> ${penjahit || '-'}`;
                      detail += `<br><strong>Hasil Jahit:</strong> ${totalJahit} Pcs`;

                      keterangan = `<i class="ti ti-info-circle"></i>
  <strong>Keterangan:</strong><br>
  1. Hasil jahit akan dihapus<br>
  2. Status akan kembali ke "Penjahitan"<br>
  3. Stok akan disesuaikan<br>
  4. Hutang upah penjahit akan dikurangi`;
                      break;

                  case 'tanggal_kirim_jahit':
                      detail = `Apakah Anda yakin ingin membatalkan <strong>tanggal kirim jahit</strong> untuk:`;
                      detail += `<br><strong>Produk:</strong> ${produk}`;
                      detail += `<br><strong>Seri:</strong> ${seri}`;
                      detail += `<br><strong>Penjahit:</strong> ${penjahit || '-'}`;

                      keterangan = `<i class="ti ti-info-circle"></i>
  <strong>Keterangan:</strong><br>
  1. Tanggal kirim jahit akan dihapus<br>
  2. Data penjahit akan dihapus<br>
  3. Status akan kembali ke "Bordir"`;
                      break;

                  case 'hasil_bordir':
                      detail = `Apakah Anda yakin ingin membatalkan <strong>hasil bordir</strong> untuk:`;
                      detail += `<br><strong>Produk:</strong> ${produk}`;
                      detail += `<br><strong>Seri:</strong> ${seri}`;
                      detail += `<br><strong>Bordir:</strong> ${bordir || '-'}`;
                      detail += `<br><strong>Hasil Bordir:</strong> ${totalBordir} Pcs`;

                      keterangan = `<i class="ti ti-info-circle"></i>
  <strong>Keterangan:</strong><br>
  1. Hasil bordir akan dihapus<br>
  2. Status akan kembali ke "Bordir"<br>
  3. Hutang upah bordir akan dikurangi`;
                      break;

                  case 'tanggal_kirim_bordir':
                      detail = `Apakah Anda yakin ingin membatalkan <strong>tanggal kirim bordir</strong> untuk:`;
                      detail += `<br><strong>Produk:</strong> ${produk}`;
                      detail += `<br><strong>Seri:</strong> ${seri}`;
                      detail += `<br><strong>Bordir:</strong> ${bordir || '-'}`;

                      keterangan = `<i class="ti ti-info-circle"></i>
  <strong>Keterangan:</strong><br>
  1. Tanggal kirim bordir akan dihapus<br>
  2. Data bordir akan dihapus<br>
  3. Status akan kembali ke "Potong"`;
                      break;

                  case 'pemotongan':
                      detail = `Apakah Anda yakin ingin <strong>membatalkan produksi</strong> untuk:`;
                      detail += `<br><strong>Produk:</strong> ${produk}`;
                      detail += `<br><strong>Seri:</strong> ${seri}`;
                      detail += `<br><strong>Pemotong:</strong> ${pemotong || '-'}`;
                      detail += `<br><strong>Hasil Potong:</strong> ${totalPotong} Pcs`;

                      keterangan = `<i class="ti ti-info-circle"></i>
  <strong>Keterangan:</strong><br>
  1. Semua data produksi akan dihapus<br>
  2. Data pemotongan akan dihapus<br>
  3. Hutang upah pemotong akan dikurangi<br>
  4. Data akan hilang permanen`;
                      break;
              }

              document.getElementById('batal_detail').innerHTML = detail;
              document.getElementById('batal_keterangan').innerHTML = keterangan;

              // Tampilkan modal
              const modalBatalTahap = new bootstrap.Modal(document.getElementById('modalBatalTahap'));
              modalBatalTahap.show();
          }
      });
  </script>

  <!-- MODAL BATAL TAHAP -->
  <!-- Modal Konfirmasi Pembatalan Tahap -->
  <div class="modal fade" id="modalBatalTahap" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="modalBatalTitle">Konfirmasi Pembatalan</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="POST" id="formBatalTahap">
                  <div class="modal-body">
                      <input type="hidden" name="id_hasil_potong_fix" id="batal_id">
                      <input type="hidden" name="tahap" id="batal_tahap">

                      <p id="batal_detail"></p>

                      <div class="alert alert-warning" id="batal_keterangan">
                          <!-- Keterangan akan diisi oleh JavaScript -->
                      </div>

                      <p class="text-danger"><strong>Tindakan ini tidak dapat dikembalikan!</strong></p>
                  </div>
                  <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                      <button type="submit" class="btn btn-danger" name="batal_tahap">Ya, Lanjutkan</button>
                  </div>
              </form>
          </div>
      </div>
  </div>