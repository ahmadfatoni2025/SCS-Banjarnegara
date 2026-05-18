<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

try {
    // Ambil semua produk untuk autocomplete
    $sql = "SELECT id_barang, nama, kategori, satuan, harga, stok FROM gudang ORDER BY nama ASC";
    $stmt = $koneksi->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $koneksi->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => count($products)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_products.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengambil data produk: ' . $e->getMessage(),
        'products' => []
    ]);
}

if (isset($stmt)) {
    $stmt->close();
}
?>
