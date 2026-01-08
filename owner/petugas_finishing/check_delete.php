<?php
// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/functions.php';

// Set header JSON
header('Content-Type: application/json');

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Metode request tidak valid'
    ]);
    exit();
}

// Get ID
$id_petugas_finishing = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_petugas_finishing <= 0) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'ID petugas finishing tidak valid'
    ]);
    exit();
}

try {
    // Check if petugas finishing exists
    $sql = "SELECT 1 FROM petugas_finishing WHERE id_petugas_finishing = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_petugas_finishing);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'can_delete' => false,
            'message' => 'Petugas finishing tidak ditemukan'
        ]);
        exit();
    }
    $stmt->close();

    // Check relations with hasil_kirim_finishing
    $sql_check = "SELECT COUNT(*) as total FROM hasil_kirim_finishing WHERE id_petugas_finishing = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_petugas_finishing);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $data_check = $result_check->fetch_assoc();
    $total_relations = $data_check['total'];
    $stmt_check->close();

    if ($total_relations > 0) {
        echo json_encode([
            'can_delete' => false,
            'message' => 'Petugas finishing tidak dapat dihapus karena masih memiliki ' . $total_relations . ' data hasil kirim finishing'
        ]);
    } else {
        echo json_encode([
            'can_delete' => true,
            'message' => 'Petugas finishing dapat dihapus'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}
