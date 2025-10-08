<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

$id_penjahit = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_penjahit <= 0) {
    echo json_encode(['can_delete' => false, 'message' => 'ID penjahit tidak valid']);
    exit;
}

// Cek relasi dengan tabel lain
$relations = [
    'pengiriman' => "SELECT 1 FROM pengiriman_penjahit WHERE id_penjahit = ? LIMIT 1"
];

$reasons = [];
$conn = $GLOBALS['conn'];

try {
    foreach ($relations as $name => $sql) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_penjahit);
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
            'message' => 'Penjahit tidak dapat dihapus karena masih terhubung dengan data: ' . implode(', ', $reasons)
        ]);
    } else {
        echo json_encode(['can_delete' => true, 'message' => '']);
    }
} catch (Exception $e) {
    echo json_encode(['can_delete' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
