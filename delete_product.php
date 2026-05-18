<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID produk tidak diberikan']);
    exit;
}

$id_barang = (int)$_GET['id'];
$nama = 'Barang';

try {
    if ($id_barang > 0) {
        $stmt_nama = $koneksi->prepare("SELECT nama FROM gudang WHERE id_barang = ?");
        $stmt_nama->bind_param("i", $id_barang);
        $stmt_nama->execute();
        
        $stmt_nama->store_result();
        $stmt_nama->bind_result($nama_res);
        $stmt_nama->fetch();
        $nama = $nama_res ?? 'Barang';
        $stmt_nama->close();

        $stmt_hapus = $koneksi->prepare("DELETE FROM gudang WHERE id_barang = ?");
        $stmt_hapus->bind_param("i", $id_barang);
        
        if ($stmt_hapus->execute()) {
            echo json_encode(['success' => true, 'message' => "Barang '$nama' berhasil dihapus."]);
        } else {
            echo json_encode(['success' => false, 'message' => "Gagal menghapus barang: " . $stmt_hapus->error]);
        }
        $stmt_hapus->close();
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1451) {
        echo json_encode(['success' => false, 'message' => "Gagal menghapus '$nama'. Barang ini sudah terdaftar di riwayat pesanan."]);
    } else {
        echo json_encode(['success' => false, 'message' => "Gagal menghapus barang: " . $e->getMessage()]);
    }
}
?>
