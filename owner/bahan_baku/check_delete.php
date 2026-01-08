<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Set proper headers first
header('Content-Type: application/json');

// Check for errors in database connection
if ($conn->connect_error) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Koneksi ke database gagal.'
    ]);
    exit;
}

// Validate input
$id_bahan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_bahan <= 0) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'ID bahan tidak valid.'
    ]);
    exit;
}

try {
    // Daftar relasi yang akan dicek (key = pesan untuk user)
    $relations = [
        'Pengiriman ke Pemotong' => "SELECT 1 FROM pengiriman_pemotong WHERE id_bahan = ? LIMIT 1",
        'Penjualan Bahan' => "SELECT 1 FROM detail_penjualan_bahan WHERE id_bahan = ? LIMIT 1",
    ];

    $reasons = [];

    foreach ($relations as $friendlyName => $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $id_bahan);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $reasons[] = $friendlyName;
        }
        $stmt->close();
    }

    if (!empty($reasons)) {
        echo json_encode([
            'can_delete' => false,
            'message' => 'Data bahan ini tidak bisa dihapus karena masih digunakan pada: ' . implode(', ', $reasons) . '.'
        ]);
    } else {
        echo json_encode([
            'can_delete' => true,
            'message' => ''
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Terjadi kesalahan saat memeriksa relasi bahan: ' . $e->getMessage()
    ]);
}
