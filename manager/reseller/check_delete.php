<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Set headers first to ensure no output before them
header('Content-Type: application/json');

// Check database connection
if ($conn->connect_error) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Database connection error'
    ]);
    exit;
}

// Validate input
$id_reseller = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_reseller <= 0) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Invalid reseller ID'
    ]);
    exit;
}

try {
    // Check if reseller exists
    $stmt = $conn->prepare("SELECT 1 FROM reseller WHERE id_reseller = ? LIMIT 1");
    $stmt->bind_param("i", $id_reseller);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode([
            'can_delete' => false,
            'message' => 'Reseller not found'
        ]);
        exit;
    }

    // Check relations with other tables
    $relations = [
        'penjualan' => "SELECT 1 FROM penjualan WHERE id_reseller = ? LIMIT 1",
        'penjualan_bahan' => "SELECT 1 FROM penjualan_bahan WHERE id_reseller = ? LIMIT 1",
    ];

    $reasons = [];

    foreach ($relations as $table => $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $id_reseller);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $reasons[] = $table;
        }
        $stmt->close();
    }

    echo json_encode([
        'can_delete' => empty($reasons),
        'message' => empty($reasons) ? '' : 'Reseller tidak dapat dihapus karena masih digunakan dalam data penjualan atau penjualan bahan.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Terjadi kesalahan saat memeriksa relasi reseller: ' . $e->getMessage()
    ]);
}
