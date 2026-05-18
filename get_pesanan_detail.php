<?php
session_start();
include "koneksi.php";

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Gagal memuat data.'
];

// 1. Keamanan
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur' || !isset($_SESSION['user']['id'])) {
    $response['message'] = 'Akses ditolak.';
    echo json_encode($response);
    exit;
}
$id_dapur_login = (int)$_SESSION['user']['id'];
$id_pesanan = (int)($_GET['id'] ?? 0);

if ($id_pesanan === 0) {
    $response['message'] = 'ID Pesanan tidak valid.';
    echo json_encode($response);
    exit;
}

try {
    // 2. Ambil data customer dari pesanan
    $stmt_cust = $koneksi->prepare(
        "SELECT nama_pemesan, wa_pemesan, email_pemesan 
         FROM pesanan 
         WHERE id_pesanan = ? AND id_dapur = ? AND status_pembayaran = 'Belum Bayar'"
    );
    $stmt_cust->bind_param("ii", $id_pesanan, $id_dapur_login);
    $stmt_cust->execute();
    $stmt_cust->store_result();
    $stmt_cust->bind_result($nama_cust, $wa_cust, $email_cust);

    if (!$stmt_cust->fetch()) {
        throw new Exception('Pesanan tidak ditemukan, tidak dapat diubah, atau bukan milik Anda.');
    }
    
    $response['customer'] = [
        'nama' => $nama_cust,
        'wa' => $wa_cust,
        'email' => $email_cust
    ];
    $stmt_cust->free_result();
    $stmt_cust->close();

    // 3. Ambil item-item pesanan
    $stmt_items = $koneksi->prepare(
        "SELECT dp.id_barang, dp.nama_barang, dp.jumlah, dp.harga_satuan, g.stok, g.satuan
         FROM detail_pesanan dp
         JOIN gudang g ON dp.id_barang = g.id_barang
         WHERE dp.id_pesanan = ?"
    );
    $stmt_items->bind_param("i", $id_pesanan);
    $stmt_items->execute();
    $stmt_items->store_result();
    $stmt_items->bind_result($id_brg, $nama_brg, $jumlah_brg, $harga_brg, $stok_brg, $satuan_brg);

    $items_list = [];
    while ($stmt_items->fetch()) {
        $items_list[] = [
            'id_barang' => $id_brg,
            'nama' => $nama_brg,
            'jumlah_dipesan' => (int)$jumlah_brg,
            'harga_satuan' => (float)$harga_brg,
            'stok_saat_ini' => (int)$stok_brg, // Stok di gudang sekarang
            'satuan' => $satuan_brg
        ];
    }
    $stmt_items->free_result();
    $stmt_items->close();

    $response['success'] = true;
    $response['message'] = 'Data berhasil dimuat.';
    $response['items'] = $items_list;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Gagal get_pesanan_detail.php: " . $e->getMessage());
}

echo json_encode($response);
?>
