<?php
// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../config/functions.php';
require_once '../vendor/autoload.php';

// Filter parameters dari URL atau POST
$filter_types = isset($_GET['type']) ? (array)$_GET['type'] : [];
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-01');
$filter_date_end   = isset($_GET['date_end'])   ? $_GET['date_end']   : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

$base_sql = "
    (SELECT 'pembelian' as type, 'Pembelian Produk' as kategori, tanggal_pembelian as waktu,
        CONCAT('Pembelian ke: ', nama_supplier) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'primary' as badge_color,
        p.id_pembelian as id_ref
     FROM pembelian p
     JOIN supplier r ON p.id_supplier = r.id_supplier)

    UNION ALL
    (SELECT 'penjualan' as type, 'Penjualan Produk' as kategori, tanggal_penjualan as waktu,
        CONCAT('Penjualan ke: ', nama_reseller) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'success' as badge_color,
        p.id_penjualan as id_ref
     FROM penjualan p
     JOIN reseller r ON p.id_reseller = r.id_reseller)

    UNION ALL
    (SELECT 'produksi' as type, 'Hasil Penjahitan' as kategori, created_at as waktu,
        'Hasil Penjahitan' as aktivitas,
        CONCAT(jumlah_produk_jadi, ' pcs produk jadi') as detail,
        'info' as badge_color,
        id_hasil_jahit as id_ref
     FROM hasil_penjahitan)

    UNION ALL
    (SELECT 'pembelian_bahan' as type, 'Pembelian Bahan' as kategori, tanggal_pembelian as waktu,
        CONCAT('Pembelian Bahan dari: ', nama_supplier) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'warning' as badge_color,
        pb.id_pembelian_bahan as id_ref
     FROM pembelian_bahan pb
     JOIN supplier s ON pb.id_supplier = s.id_supplier)

    UNION ALL
    (SELECT 'hasil_jahit_mukena' as type, 'Hasil Jahit Mukena' as kategori, hpf.tanggal_hasil_jahit as waktu,
        CONCAT('Hasil Jahit: ', p.nama_produk) as aktivitas,
        CONCAT(hpf.total_hasil_jahit, ' pcs selesai dari ', hpf.total_hasil, ' pcs potong') as detail,
        'secondary' as badge_color,
        hpf.id_hasil_potong_fix as id_ref
     FROM hasil_potong_fix hpf
     JOIN produk p ON hpf.id_produk = p.id_produk
     WHERE p.tipe_produk = 'mukena'
       AND hpf.tanggal_hasil_jahit IS NOT NULL
       AND hpf.total_hasil_jahit IS NOT NULL)

    UNION ALL
    (SELECT 'penjualan_bahan' as type, 'Penjualan Bahan' as kategori, tanggal_penjualan_bahan as waktu,
        CONCAT('Penjualan Bahan ke: ', nama_reseller) as aktivitas,
        CONCAT('Total Rp.', FORMAT(total_harga, 0)) as detail,
        'danger' as badge_color,
        pb.id_penjualan_bahan as id_ref
     FROM penjualan_bahan pb
     JOIN reseller r ON pb.id_reseller = r.id_reseller)

    UNION ALL
    (SELECT 'hasil_finishing_koko' as type, 'Hasil Finishing Koko' as kategori, dhfk.tanggal_finishing as waktu,
        CONCAT('Finishing: ', COALESCE(p.nama_produk, 'Produk')) as aktivitas,
        CONCAT(dhfk.jumlah_selesai, ' pcs selesai, ', dhfk.jumlah_rusak, ' pcs kembali ke stok finishing koko') as detail,
        'dark' as badge_color,
        dhfk.id_detail_hasil_finishing_koko as id_ref
     FROM detail_hasil_finishing_koko dhfk
     JOIN detail_hasil_kirim_finishing dh ON dhfk.id_detail_hasil_kirim_finishing = dh.id_detail_hasil_kirim_finishing
     LEFT JOIN produk p ON dh.id_produk = p.id_produk
     WHERE dhfk.tanggal_finishing IS NOT NULL)

    UNION ALL
    (SELECT 'pembayaran_upah' as type, 'Pembayaran Upah' as kategori, tanggal_bayar as waktu,
        'Pembayaran Upah' as aktivitas,
        CONCAT('Rp.', FORMAT(jumlah_bayar, 0), ' - ', metode_bayar) as detail,
        'success' as badge_color,
        id_pembayaran as id_ref
     FROM pembayaran_upah_2)
";

// Terapkan filter tanggal
$start_datetime = $filter_date_start . ' 00:00:00';
$end_datetime   = $filter_date_end . ' 23:59:59';
$sql = "SELECT * FROM ($base_sql) AS all_activities
        WHERE waktu BETWEEN '$start_datetime' AND '$end_datetime'";

// Filter tipe (multiple)
if (!empty($filter_types)) {
    $types_escaped = array_map(function ($t) {
        return "'" . addslashes($t) . "'";
    }, $filter_types);
    $sql .= " AND type IN (" . implode(',', $types_escaped) . ")";
}

// Filter pencarian
if (!empty($search)) {
    $search_safe = addslashes($search);
    $sql .= " AND (aktivitas LIKE '%$search_safe%' OR detail LIKE '%$search_safe%')";
}

// Urutkan terbaru
$sql .= " ORDER BY waktu DESC";

$activities = query($sql);
$total_aktivitas = count($activities);

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Sistem Manajemen');
$pdf->SetAuthor('IPENK LEGEND');
$pdf->SetTitle('Laporan Aktivitas');
$pdf->SetSubject('Laporan Aktivitas Sistem');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 15);

// Add a page
$pdf->AddPage();

// Logo (kiri)
$logoPath = __DIR__ . '/Logo-Ipenk.png';
$pdf->Image($logoPath, 10, 10, 22); // x=10 (kiri), y=10, width=22

// Posisi teks header (kanan logo)
$pdf->SetXY(10, 15);

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 6, 'IPENK LEGEND', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Jl. Contoh No. 123, Kota Tasikmalaya', 0, 1, 'C');
$pdf->Cell(0, 5, 'Telp: 0812-3456-7890', 0, 1, 'C');

// Spasi ke bawah
$pdf->Ln(10);

// Garis pemisah
$y = $pdf->GetY();
$pdf->Line(0, $y, 300, $y);
$pdf->Ln(2);



// Judul laporan
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'LAPORAN AKTIVITAS SISTEM', 0, 1, 'C');

// Periode laporan
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Periode: ' . dateIndo($filter_date_start) . ' s/d ' . dateIndo($filter_date_end), 0, 1, 'C');
$pdf->Cell(0, 6, 'Dibuat: ' . dateIndo(date('Y-m-d')), 0, 1, 'C');
$pdf->Ln(5);

// Informasi filter
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(40, 5, 'Filter yang diterapkan:', 0, 0, 'L');
if (empty($filter_types)) {
    $filter_text = 'Semua Jenis';
} else {
    $filter_text = implode(', ', array_map(function ($t) {
        return ucfirst(str_replace('_', ' ', $t));
    }, $filter_types));
}
$pdf->Cell(0, 5, $filter_text, 0, 1, 'L');

if (!empty($search)) {
    $pdf->Cell(40, 5, 'Kata kunci:', 0, 0, 'L');
    $pdf->Cell(0, 5, $search, 0, 1, 'L');
}

$pdf->Cell(40, 5, 'Total Aktivitas:', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, $total_aktivitas . ' aktivitas', 0, 1, 'L');

$pdf->Ln(5);

// Hitung ringkasan per kategori
$type_counts = [];
foreach ($activities as $activity) {
    $type = $activity['kategori'];
    if (!isset($type_counts[$type])) {
        $type_counts[$type] = 0;
    }
    $type_counts[$type]++;
}

if (!empty($type_counts)) {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Ringkasan per Kategori:', 0, 1, 'L');

    $row = 1;
    foreach ($type_counts as $type => $count) {
        $pdf->Cell(10, 5, '', 0, 0, 'L');
        $pdf->Cell(60, 5, $type, 0, 0, 'L');
        $pdf->Cell(0, 5, ': ' . $count . ' aktivitas', 0, 1, 'L');
    }
    $pdf->Ln(8);
}

// Daftar aktivitas dalam tabel
if (empty($activities)) {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'Tidak ada aktivitas ditemukan untuk periode yang dipilih.', 0, 1, 'C');
} else {
    // Header tabel
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetFillColor(220, 220, 220);

    // Lebar kolom
    $col_width_no = 10;
    $col_width_tanggal = 30;
    $col_width_kategori = 40;
    $col_width_aktivitas = 110;

    $pdf->Cell($col_width_no, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell($col_width_tanggal, 8, 'Tanggal', 1, 0, 'C', true);
    $pdf->Cell($col_width_kategori, 8, 'Kategori', 1, 0, 'C', true);
    $pdf->Cell($col_width_aktivitas, 8, 'Aktivitas & Detail', 1, 1, 'C', true);

    // Data aktivitas
    $pdf->SetFont('helvetica', '', 9);
    $no = 1;
    $current_date = '';

    foreach ($activities as $activity) {
        $activity_date = date('Y-m-d', strtotime($activity['waktu']));
        $display_date = dateIndo($activity_date);

        // Cek jika perlu page break
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            // Header tabel di halaman baru
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell($col_width_no, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell($col_width_tanggal, 8, 'Tanggal', 1, 0, 'C', true);
            $pdf->Cell($col_width_kategori, 8, 'Kategori', 1, 0, 'C', true);
            $pdf->Cell($col_width_aktivitas, 8, 'Aktivitas & Detail', 1, 1, 'C', true);
            $pdf->SetFont('helvetica', '', 9);
        }

        // Warna untuk baris berdasarkan kategori
        $color_map = [
            'primary' => [255, 255, 255],
            'success' => [240, 255, 240],
            'info' => [240, 255, 255],
            'warning' => [255, 255, 240],
            'secondary' => [248, 249, 250],
            'danger' => [255, 240, 240],
            'dark' => [245, 245, 245]
        ];

        $bg_color = isset($color_map[$activity['badge_color']]) ? $color_map[$activity['badge_color']] : [255, 255, 255];
        $pdf->SetFillColor($bg_color[0], $bg_color[1], $bg_color[2]);

        // Kolom No
        $pdf->Cell($col_width_no, 8, $no, 1, 0, 'C', true);

        // Kolom Tanggal
        $pdf->Cell($col_width_tanggal, 8, $display_date, 1, 0, 'L', true);

        // Kolom Kategori
        $pdf->Cell($col_width_kategori, 8, $activity['kategori'], 1, 0, 'L', true);

        // Kolom Aktivitas & Detail
        $aktivitas_text = $activity['aktivitas'] . "\n" . $activity['detail'];

        // Simpan posisi Y untuk MultiCell
        $start_y = $pdf->GetY();

        // Hitung tinggi yang dibutuhkan
        $aktivitas_width = $col_width_aktivitas - 2; // Kurangi margin
        $aktivitas_height = $pdf->getStringHeight($aktivitas_width, $aktivitas_text);

        // Pastikan tinggi minimum
        $row_height = max(8, $aktivitas_height);

        // Set posisi X untuk kolom aktivitas
        $pdf->SetX(10 + $col_width_no + $col_width_tanggal + $col_width_kategori);

        // Tampilkan aktivitas dengan MultiCell
        $pdf->MultiCell($col_width_aktivitas, $row_height, $aktivitas_text, 1, 'L', true);

        // Kembalikan posisi Y untuk baris berikutnya
        $pdf->SetXY(10, $start_y + $row_height);

        $no++;
    }
}

// Garis pemisah sebelum footer
$pdf->Ln(10);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(5);

$pdf->Cell(0, 5, 'Halaman ' . $pdf->getAliasNumPage() . ' dari ' . $pdf->getAliasNbPages(), 0, 1, 'C');

// Output PDF
$filename = 'Laporan_Aktivitas_' . date('Ymd_His') . '.pdf';
$pdf->Output($filename, 'I');
