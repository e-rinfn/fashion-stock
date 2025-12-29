<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

$id_bordir = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_bordir <= 0) {
    echo json_encode(['can_delete' => false, 'message' => 'ID bordir tidak valid']);
    exit;
}

// Cek relasi dengan tabel lain - sesuaikan dengan tabel yang ada di database Anda
// Misalnya jika ada tabel pesanan_bordir atau lainnya
$relations = [
    // Contoh: 'pesanan' => "SELECT 1 FROM pesanan_bordir WHERE id_bordir = ? LIMIT 1"
    // Tambahkan tabel yang berelasi dengan bordir di sini
];

$reasons = [];
$conn = $GLOBALS['conn'];

try {
    foreach ($relations as $name => $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_bordir);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $reasons[] = $name;
        }
        $stmt->close();
    }

    if (!empty($reasons)) {
        echo json_encode([
            'can_delete' => false,
            'message' => 'Bordir tidak dapat dihapus karena masih terhubung dengan data: ' . implode(', ', $reasons)
        ]);
    } else {
        echo json_encode(['can_delete' => true, 'message' => '']);
    }
} catch (Exception $e) {
    echo json_encode(['can_delete' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
