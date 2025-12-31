<?php
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');

$id_supplier = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_supplier <= 0) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'ID supplier tidak valid'
    ]);
    exit;
}

// Check if supplier exists
$supplier = query("SELECT 1 FROM supplier WHERE id_supplier = $id_supplier LIMIT 1");
if (empty($supplier)) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Supplier tidak ditemukan'
    ]);
    exit;
}

// Check relations with other tables
$relations = [
    'pembelian' => "SELECT 1 FROM pembelian WHERE id_supplier = $id_supplier LIMIT 1",
    // Add other relations if needed
];

$reasons = [];
foreach ($relations as $name => $sql) {
    if (query($sql)) {
        $reasons[] = $name;
    }
}

if (!empty($reasons)) {
    echo json_encode([
        'can_delete' => false,
        'message' => 'Supplier tidak dapat dihapus karena terkait dengan data: ' . implode(', ', $reasons)
    ]);
} else {
    echo json_encode([
        'can_delete' => true,
        'message' => ''
    ]);
}
