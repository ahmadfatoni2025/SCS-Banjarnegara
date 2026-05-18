<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Ambil produk berdasarkan ID
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id_barang = (int)$_GET['id'];

    $stmt = $koneksi->prepare("SELECT id_barang, nama, kategori, satuan, harga, stok FROM gudang WHERE id_barang = ?");
    $stmt->bind_param("i", $id_barang);
    $stmt->execute();

    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id_barang_res, $nama_res, $kategori_res, $satuan_res, $harga_res, $stok_res);
        $stmt->fetch();
        
        $product = [
            'id_barang' => $id_barang_res,
            'nama' => $nama_res,
            'kategori' => $kategori_res,
            'satuan' => $satuan_res,
            'harga' => $harga_res,
            'stok' => $stok_res
        ];
        
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Produk tidak ditemukan']);
    }

    $stmt->close();
    exit;
}

// Tambah atau edit produk
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'tambah') {
        $nama = $_POST['nama'];
        $kategori = $_POST['kategori'];
        $satuan = $_POST['satuan'];
        $harga = $_POST['harga'];
        $stok = $_POST['stok'];

        if (!empty($nama) && !empty($kategori) && !empty($satuan) && $harga > 0 && $stok >= 0) {
            $stmt = $koneksi->prepare("INSERT INTO gudang (nama, kategori, satuan, harga, stok) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $nama, $kategori, $satuan, $harga, $stok);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => "Barang '$nama' berhasil ditambahkan!"]);
            } else {
                echo json_encode(['success' => false, 'message' => "Gagal menambahkan barang: " . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => "Semua field wajib diisi dengan benar."]);
        }
    } elseif ($_POST['action'] === 'edit') {
        $id_barang = (int)$_POST['id_barang'];
        $nama = $_POST['nama'];
        $kategori = $_POST['kategori'];
        $satuan = $_POST['satuan'];
        $harga = $_POST['harga'];
        $stok = $_POST['stok'];

        if ($id_barang > 0 && !empty($nama) && !empty($kategori) && !empty($satuan) && $harga > 0 && $stok >= 0) {
            $stmt = $koneksi->prepare("UPDATE gudang SET nama=?, kategori=?, satuan=?, harga=?, stok=? WHERE id_barang=?");
            $stmt->bind_param("sssiii", $nama, $kategori, $satuan, $harga, $stok, $id_barang);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => "Barang '$nama' berhasil diperbarui!"]);
            } else {
                echo json_encode(['success' => false, 'message' => "Gagal memperbarui barang: " . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => "Data tidak lengkap atau ID tidak valid."]);
        }
    }
}
?>
