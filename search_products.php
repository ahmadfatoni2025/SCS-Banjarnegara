<?php
// File: search_products.php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');
$response = ['success' => false, 'products' => []];

// Keamanan: Hanya admin yang boleh mengambil data ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $response['message'] = 'Akses ditolak.';
    echo json_encode($response);
    exit;
}

try {
    // Ambil SEMUA produk. JavaScript yang akan memfilter
    $sql = "SELECT id_barang, nama, kategori, stok, satuan FROM gudang ORDER BY nama ASC";
    $stmt = $koneksi->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Gagal prepare statement: " . $koneksi->error);
    }
    
    $stmt->execute();
    $stmt->store_result();
    
    // Bind ke variabel (karena server Anda tidak mendukung get_result)
    $stmt->bind_result($id_barang, $nama, $kategori, $stok, $satuan);
    
    $products = [];
    while ($stmt->fetch()) {
        $products[] = [
            'id_barang' => $id_barang,
            'nama' => $nama,
            'kategori' => $kategori,
            'stok' => $stok,
            'satuan' => $satuan
        ];
    }
    
    $stmt->free_result();
    $stmt->close();
    
    $response['success'] = true;
    $response['products'] = $products;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error di search_products.php: " . $e->getMessage());
}

echo json_encode($response);
?>
