<?php
// === DEBUGGING MODE ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ======================

session_start();

// --- 1. KEAMANAN ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur' || !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id_dapur_login = (int)$_SESSION['user']['id'];

// --- 2. KONEKSI DATABASE ---
if (!file_exists("koneksi.php")) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'File koneksi tidak ditemukan']);
    exit();
}
include "koneksi.php";

// --- 3. CEK METHOD REQUEST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// --- 4. BACA DATA JSON ---
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action']) || $input['action'] !== 'update_pesanan') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// --- 5. VALIDASI DATA ---
if (!isset($input['editOrderId']) || !isset($input['cart'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit();
}

$id_pesanan = (int)$input['editOrderId'];
$cart = $input['cart'];

// --- 6. VALIDASI PESANAN ---
$query_cek = "SELECT id_pesanan, status_pembayaran, status_pengiriman FROM pesanan WHERE id_pesanan = ? AND id_dapur = ? LIMIT 1";
$stmt_cek = $koneksi->prepare($query_cek);

if (!$stmt_cek) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error query: ' . $koneksi->error]);
    exit();
}

$stmt_cek->bind_param("ii", $id_pesanan, $id_dapur_login);
$stmt_cek->execute();
$stmt_cek->store_result();
$stmt_cek->bind_result($db_id_pesanan, $db_status_pembayaran, $db_status_pengiriman);

if (!$stmt_cek->fetch()) {
    $stmt_cek->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan']);
    exit();
}

$stmt_cek->close();

// --- 7. CEK STATUS PESANAN (harus belum bayar dan pending) ---
if ($db_status_pembayaran !== 'Belum Bayar' || $db_status_pengiriman !== 'Pending') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Pesanan sudah tidak bisa diedit']);
    exit();
}

// --- 8. TRANSACTION START ---
$koneksi->begin_transaction();

try {
    // --- 9. AMBIL DETAIL PESANAN LAMA ---
    $query_detail_lama = "SELECT id_barang, jumlah FROM detail_pesanan WHERE id_pesanan = ?";
    $stmt_detail_lama = $koneksi->prepare($query_detail_lama);
    if (!$stmt_detail_lama) throw new Exception("Error query detail lama: " . $koneksi->error);
    
    $stmt_detail_lama->bind_param("i", $id_pesanan);
    $stmt_detail_lama->execute();
    $result_detail = $stmt_detail_lama->get_result();
    
    $detail_lama = [];
    while ($row = $result_detail->fetch_assoc()) {
        $detail_lama[$row['id_barang']] = $row['jumlah'];
    }
    $stmt_detail_lama->close();
    
    // --- 10. KEMBALIKAN STOK BARANG LAMA KE GUDANG ---
    foreach ($detail_lama as $id_barang => $jumlah_lama) {
        $query_kembalikan = "UPDATE gudang SET stok = stok + ? WHERE id_barang = ?";
        $stmt_kembalikan = $koneksi->prepare($query_kembalikan);
        if (!$stmt_kembalikan) throw new Exception("Error query kembalikan stok: " . $koneksi->error);
        
        $stmt_kembalikan->bind_param("ii", $jumlah_lama, $id_barang);
        if (!$stmt_kembalikan->execute()) {
            throw new Exception("Gagal mengembalikan stok barang ID $id_barang");
        }
        $stmt_kembalikan->close();
    }
    
    // --- 11. HAPUS DETAIL PESANAN LAMA ---
    $query_hapus_detail = "DELETE FROM detail_pesanan WHERE id_pesanan = ?";
    $stmt_hapus_detail = $koneksi->prepare($query_hapus_detail);
    if (!$stmt_hapus_detail) throw new Exception("Error query hapus detail: " . $koneksi->error);
    
    $stmt_hapus_detail->bind_param("i", $id_pesanan);
    if (!$stmt_hapus_detail->execute()) {
        throw new Exception("Gagal menghapus detail pesanan lama");
    }
    $stmt_hapus_detail->close();
    
    // --- 12. HITUNG TOTAL HARGA BARU ---
    $total_harga_baru = 0;
    $cart_items = [];
    
    foreach ($cart as $item) {
        if (!is_array($item)) continue;
        
        $id_barang = (int)$item['id_barang'];
        $jumlah = (int)$item['jumlah'];
        $harga = (float)$item['harga'];
        
        if ($jumlah > 0) {
            $total_harga_baru += ($harga * $jumlah);
            $cart_items[] = [
                'id_barang' => $id_barang,
                'jumlah' => $jumlah,
                'harga' => $harga
            ];
        }
    }
    
    // --- 13. KURANGI STOK UNTUK BARANG BARU ---
    foreach ($cart_items as $item) {
        // Cek stok tersedia
        $query_cek_stok = "SELECT stok, nama FROM gudang WHERE id_barang = ?";
        $stmt_cek_stok = $koneksi->prepare($query_cek_stok);
        if (!$stmt_cek_stok) throw new Exception("Error query cek stok: " . $koneksi->error);
        
        $stmt_cek_stok->bind_param("i", $item['id_barang']);
        $stmt_cek_stok->execute();
        $stmt_cek_stok->store_result();
        $stmt_cek_stok->bind_result($stok_gudang, $nama_barang);
        
        if (!$stmt_cek_stok->fetch()) {
            $stmt_cek_stok->close();
            throw new Exception("Barang dengan ID {$item['id_barang']} tidak ditemukan di gudang");
        }
        
        $stmt_cek_stok->close();
        
        // Validasi stok
        if ($stok_gudang < $item['jumlah']) {
            throw new Exception("Stok $nama_barang tidak cukup. Tersedia: $stok_gudang, Dibutuhkan: {$item['jumlah']}");
        }
        
        // Kurangi stok
        $query_kurangi = "UPDATE gudang SET stok = GREATEST(0, stok - ?) WHERE id_barang = ?";
        $stmt_kurangi = $koneksi->prepare($query_kurangi);
        if (!$stmt_kurangi) throw new Exception("Error query kurangi stok: " . $koneksi->error);
        
        $stmt_kurangi->bind_param("ii", $item['jumlah'], $item['id_barang']);
        if (!$stmt_kurangi->execute()) {
            throw new Exception("Gagal mengurangi stok barang ID {$item['id_barang']}");
        }
        $stmt_kurangi->close();
        
        // Insert detail pesanan baru
        $query_insert_detail = "INSERT INTO detail_pesanan (id_pesanan, id_barang, jumlah, harga_satuan) VALUES (?, ?, ?, ?)";
        $stmt_insert_detail = $koneksi->prepare($query_insert_detail);
        if (!$stmt_insert_detail) throw new Exception("Error query insert detail: " . $koneksi->error);
        
        $stmt_insert_detail->bind_param("iiid", $id_pesanan, $item['id_barang'], $item['jumlah'], $item['harga']);
        if (!$stmt_insert_detail->execute()) {
            throw new Exception("Gagal menambahkan detail pesanan untuk barang ID {$item['id_barang']}");
        }
        $stmt_insert_detail->close();
    }
    
    // --- 14. UPDATE TOTAL HARGA DI TABEL PESANAN ---
    $query_update_pesanan = "UPDATE pesanan SET total_harga = ? WHERE id_pesanan = ?";
    $stmt_update_pesanan = $koneksi->prepare($query_update_pesanan);
    if (!$stmt_update_pesanan) throw new Exception("Error query update pesanan: " . $koneksi->error);
    
    $stmt_update_pesanan->bind_param("di", $total_harga_baru, $id_pesanan);
    if (!$stmt_update_pesanan->execute()) {
        throw new Exception("Gagal mengupdate total harga pesanan");
    }
    $stmt_update_pesanan->close();
    
    // --- 15. TAMBAH LOG ---
    $query_insert_log = "INSERT INTO log_pesanan (id_pesanan, action, keterangan, created_by) VALUES (?, ?, ?, ?)";
    $stmt_insert_log = $koneksi->prepare($query_insert_log);
    if (!$stmt_insert_log) throw new Exception("Error query insert log: " . $koneksi->error);
    
    $action = "UPDATE";
    $keterangan = "Pesanan diedit oleh dapur. Jumlah item baru: " . count($cart_items);
    $created_by = $id_dapur_login;
    
    $stmt_insert_log->bind_param("issi", $id_pesanan, $action, $keterangan, $created_by);
    $stmt_insert_log->execute();
    $stmt_insert_log->close();
    
    // --- 16. COMMIT TRANSACTION ---
    $koneksi->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pesanan berhasil diperbarui!',
        'data' => [
            'id_pesanan' => $id_pesanan,
            'total_baru' => $total_harga_baru,
            'jumlah_item' => count($cart_items)
        ]
    ]);
    
} catch (Exception $e) {
    // --- ROLLBACK JIKA ADA ERROR ---
    $koneksi->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>
