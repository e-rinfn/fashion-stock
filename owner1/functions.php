<?php
// functions.php
function query($sql)
{
    global $conn;
    $result = $conn->query($sql);

    if (!$result) {
        // Untuk debugging, tampilkan error
        error_log("Query Error: " . $conn->error);
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function formatRupiah($angka)
{
    if (empty($angka) || !is_numeric($angka)) {
        return 'Rp 0';
    }
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
