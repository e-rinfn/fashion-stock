<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Fungsi untuk mendapatkan semua produk
 */
function getAllProducts($withSupplier = false)
{
    global $pdo;

    $sql = "SELECT p.*, c.nama as kategori_nama";
    if ($withSupplier) {
        $sql .= ", s.nama as supplier_nama";
    }
    $sql .= " FROM products p LEFT JOIN categories c ON p.category_id = c.id";
    if ($withSupplier) {
        $sql .= " LEFT JOIN suppliers s ON p.supplier_id = s.id";
    }

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk mendapatkan produk berdasarkan ID
 */
function getProductById($id)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT p.*, c.nama as kategori_nama 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk menambahkan produk baru
 */
function addProduct($data)
{
    global $pdo;

    $sql = "INSERT INTO products (kode_barang, nama, category_id, harga_beli, harga_jual, stok, supplier_id, deskripsi)
            VALUES (:kode_barang, :nama, :category_id, :harga_beli, :harga_jual, :stok, :supplier_id, :deskripsi)";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

/**
 * Fungsi untuk mengupdate produk
 */
function updateProduct($id, $data)
{
    global $pdo;

    $sql = "UPDATE products SET 
            kode_barang = :kode_barang,
            nama = :nama,
            category_id = :category_id,
            harga_beli = :harga_beli,
            harga_jual = :harga_jual,
            stok = :stok,
            supplier_id = :supplier_id,
            deskripsi = :deskripsi,
            updated_at = NOW()
            WHERE id = :id";

    $data['id'] = $id;
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

/**
 * Fungsi untuk menghapus produk
 */
function deleteProduct($id)
{
    global $pdo;

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Fungsi untuk mencari produk berdasarkan keyword
 */
function searchProducts($keyword)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT p.*, c.nama as kategori_nama 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.nama LIKE ? OR p.kode_barang LIKE ? OR c.nama LIKE ?");
    $searchTerm = "%$keyword%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fungsi untuk update stok produk
 */
function updateProductStock($productId, $quantityChange)
{
    global $pdo;

    $stmt = $pdo->prepare("UPDATE products SET stok = stok + ? WHERE id = ?");
    return $stmt->execute([$quantityChange, $productId]);
}


function get_all_products()
{
    global $pdo;
    $stmt = $pdo->query("
        SELECT p.*, c.nama as kategori 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.nama ASC
    ");
    return $stmt->fetchAll();
}

function get_product_by_id($id)
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, c.nama as kategori 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function add_product($data)
{
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO products (
            kode_barang, nama, category_id, harga_beli, 
            harga_jual, stok, supplier_id, deskripsi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([
        $data['kode_barang'],
        $data['nama'],
        $data['category_id'],
        $data['harga_beli'],
        $data['harga_jual'],
        $data['stok'],
        !empty($data['supplier_id']) ? $data['supplier_id'] : null,
        $data['deskripsi']
    ]);
}

function update_product($data)
{
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE products SET
            nama = ?,
            category_id = ?,
            harga_beli = ?,
            harga_jual = ?,
            stok = ?,
            supplier_id = ?,
            deskripsi = ?
        WHERE id = ?
    ");
    return $stmt->execute([
        $data['nama'],
        $data['category_id'],
        $data['harga_beli'],
        $data['harga_jual'],
        $data['stok'],
        !empty($data['supplier_id']) ? $data['supplier_id'] : null,
        $data['deskripsi'],
        $data['id']
    ]);
}

function delete_product($id)
{
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    return $stmt->execute([$id]);
}

function generate_product_code()
{
    global $pdo;
    $prefix = 'MKN';
    $year = date('y');
    $month = date('m');

    // Ambil kode terakhir
    $stmt = $pdo->query("SELECT kode_barang FROM products ORDER BY id DESC LIMIT 1");
    $last_code = $stmt->fetchColumn();

    if ($last_code) {
        $parts = explode('-', $last_code);
        $sequence = (int) end($parts) + 1;
    } else {
        $sequence = 1;
    }

    return $prefix . '-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}
