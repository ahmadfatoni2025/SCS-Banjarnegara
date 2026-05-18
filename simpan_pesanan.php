<?php
// Selalu mulai session
session_start();
// Sertakan koneksi
include "koneksi.php";

// Atur header sebagai JSON
header('Content-Type: application/json');

// Siapkan respons default
$response = ['success' => false, 'message' => 'Terjadi kesalahan.'];

// 1. Keamanan: Cek apakah user adalah 'dapur'
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur' || !isset($_SESSION['user']['id'])) {
    $response['message'] = 'Akses ditolak. Silakan login ulang.';
    echo json_encode($response);
    exit;
}
$id_dapur_login = (int)$_SESSION['user']['id'];

// 2. Ambil data JSON yang dikirim dari JavaScript
$data = json_decode(file_get_contents('php://input'), true);

$cart = $data['cart'] ?? null;
$customer = $data['customerData'] ?? null;

// 3. Validasi data
if (empty($cart) || empty($customer) || !isset($customer['nama']) || !isset($customer['wa'])) {
    $response['message'] = 'Data keranjang atau pemesan tidak lengkap.';
    echo json_encode($response);
    exit;
}

// 4. Mulai Transaksi Database (SANGAT PENTING!)
$koneksi->begin_transaction();

try {
    // Hitung total harga di sisi server (lebih aman)
    $total_harga = 0;
    $total_items = 0;
    
    // Array untuk menyimpan harga server
    $harga_server = [];
    $id_barang_list = array_keys($cart);
    
    // Ambil harga asli & tipe dari DB
    $sql_harga = "SELECT id_barang, harga, stok, tipe_pengadaan FROM gudang WHERE id_barang IN (?" . str_repeat(",?", count($id_barang_list) - 1) . ")";
    $stmt_harga = $koneksi->prepare($sql_harga);
    $types = str_repeat('i', count($id_barang_list));
    $stmt_harga->bind_param($types, ...$id_barang_list);
    $stmt_harga->execute();
    $stmt_harga->store_result();
    $stmt_harga->bind_result($id_brg, $harga_db, $stok_db, $tipe_db);

    while ($stmt_harga->fetch()) {
        $harga_server[$id_brg] = [
            'harga' => $harga_db, 
            'stok' => $stok_db,
            'tipe' => $tipe_db
        ];
    }
    $stmt_harga->free_result();
    $stmt_harga->close();

    // Validasi stok dan hitung total
    foreach ($cart as $id => $item) {
        $jumlah_pesan = (int)$item['jumlah'];
        if (!isset($harga_server[$id])) {
            throw new Exception("Barang '{$item['nama']}' tidak ditemukan di database.");
        }
        if ($harga_server[$id]['tipe'] === 'Stok' && $jumlah_pesan > $harga_server[$id]['stok']) {
            throw new Exception("Stok untuk '{$item['nama']}' tidak mencukupi (sisa {$harga_server[$id]['stok']}).");
        }
        
        $total_harga += $harga_server[$id]['harga'] * $jumlah_pesan;
        $total_items += $jumlah_pesan;
    }

    // 5. Masukkan ke tabel 'pesanan'
    $stmt_pesanan = $koneksi->prepare(
        "INSERT INTO pesanan (id_dapur, nama_pemesan, wa_pemesan, email_pemesan, total_harga, status_pembayaran, status_pengiriman, tgl_pesan) 
         VALUES (?, ?, ?, ?, ?, 'Belum Bayar', 'Pending', NOW())"
    );
    $stmt_pesanan->bind_param("isssd", $id_dapur_login, $customer['nama'], $customer['wa'], $customer['email'], $total_harga);
    
    if (!$stmt_pesanan->execute()) {
        throw new Exception("Gagal menyimpan pesanan: " . $stmt_pesanan->error);
    }
    
    // Ambil ID pesanan yang baru saja dibuat
    $id_pesanan_baru = $koneksi->insert_id;
    $stmt_pesanan->close();

    // 6. Masukkan ke tabel 'detail_pesanan' dan Kurangi Stok
    $stmt_detail = $koneksi->prepare("INSERT INTO detail_pesanan (id_pesanan, id_barang, nama_barang, jumlah, harga_satuan) VALUES (?, ?, ?, ?, ?)");
    $stmt_stok = $koneksi->prepare("UPDATE gudang SET stok = GREATEST(0, stok - ?) WHERE id_barang = ?");

    foreach ($cart as $id_barang => $item) {
        $jumlah = (int)$item['jumlah'];
        $harga_satuan = $harga_server[$id_barang]['harga'];
        
        // Masukkan detail
        $stmt_detail->bind_param("iisid", $id_pesanan_baru, $id_barang, $item['nama'], $jumlah, $harga_satuan);
        if (!$stmt_detail->execute()) {
            throw new Exception("Gagal menyimpan detail barang: " . $stmt_detail->error);
        }
        
        // Kurangi stok (Hanya jika tipe = Stok)
        if ($harga_server[$id_barang]['tipe'] === 'Stok') {
            $stmt_stok->bind_param("ii", $jumlah, $id_barang);
            if (!$stmt_stok->execute()) {
                throw new Exception("Gagal update stok barang: " . $stmt_stok->error);
            }
        }
    }
    
    $stmt_detail->close();
    $stmt_stok->close();

    // 7. Jika semua berhasil, commit transaksi
    $koneksi->commit();
    
    $response['success'] = true;
    $response['message'] = "Pesanan #{$id_pesanan_baru} berhasil disimpan!";
    $response['id_pesanan'] = $id_pesanan_baru;

} catch (Exception $e) {
    // 8. Jika ada error, batalkan semua (rollback)
    $koneksi->rollback();
    $response['message'] = $e->getMessage();
    // Catat error ini ke server log Anda
    error_log("Gagal Simpan Pesanan: " . $e->getMessage());
}

// 9. Kembalikan respons JSON
echo json_encode($response);
?>
