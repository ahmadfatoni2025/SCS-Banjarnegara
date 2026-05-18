<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'owner', 'akuntan'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_pesanan = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // Query data pesanan
    $query = "SELECT p.*, u.nama as nama_dapur 
              FROM pesanan p 
              LEFT JOIN user u ON p.id_dapur = u.id 
              WHERE p.id_pesanan = '$id_pesanan'";
    $result = mysqli_query($koneksi, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $pesanan = mysqli_fetch_assoc($result);
        
        // Query items pesanan
        $query_items = "SELECT dp.*, g.nama 
                        FROM detail_pesanan dp 
                        JOIN gudang g ON dp.id_barang = g.id_barang 
                        WHERE dp.id_pesanan = '$id_pesanan'";
        $result_items = mysqli_query($koneksi, $query_items);
        
        $items = [];
        $total_items = 0;
        while ($item = mysqli_fetch_assoc($result_items)) {
            $items[] = [
                'nama' => $item['nama'],
                'qty' => $item['jumlah'],
                'satuan' => $item['harga_satuan'],
                'harga' => 'Rp ' . number_format($item['harga_satuan'], 0, ',', '.'),
                'subtotal' => 'Rp ' . number_format($item['jumlah'] * $item['harga_satuan'], 0, ',', '.')
            ];
            $total_items += $item['jumlah'];
        }
        
        // Format data response
        $data = [
            'id' => $pesanan['id_pesanan'],
            'tanggal' => date('d/m/Y H:i', strtotime($pesanan['tgl_pesan'])),
            'nama_pemesan' => $pesanan['nama_pemesan'],
            'email_pemesan' => $pesanan['email_pemesan'] ?: '-',
            'wa_pemesan' => $pesanan['wa_pemesan'],
            'nama_dapur' => $pesanan['nama_dapur'] ?: '-',
            'nama_driver' => $pesanan['nama_driver'] ?: '-',
            'total' => 'Rp ' . number_format($pesanan['total_harga'], 0, ',', '.'),
            'jumlah_items' => $total_items,
            'status_pembayaran' => $pesanan['status_pembayaran'],
            'status_pengiriman' => $pesanan['status_pengiriman'],
            'badge_pembayaran' => 'badge-' . strtolower(str_replace(' ', '-', $pesanan['status_pembayaran'])),
            'badge_pengiriman' => 'badge-' . strtolower($pesanan['status_pengiriman']),
            'items' => $items
        ];
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data pesanan tidak ditemukan']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid']);
}
?>
