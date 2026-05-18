<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

$id_dapur_login = 6;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;

// Hitung offset
$offset = ($current_page - 1) * $items_per_page;

// Query untuk mengambil data pesanan dengan pagination
$sql_pesanan_dapur = "SELECT 
    p.id_pesanan, 
    p.nama_pemesan, 
    p.wa_pemesan, 
    p.total_harga, 
    p.status_pembayaran, 
    p.status_pengiriman, 
    p.tgl_pesan,
    p.nama_driver,
    u.nama as nama_dapur,
    GROUP_CONCAT(CONCAT(dp.jumlah, 'x ', g.nama) SEPARATOR ', ') as items
FROM pesanan p
LEFT JOIN user u ON p.id_dapur = u.id
LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
LEFT JOIN gudang g ON dp.id_barang = g.id_barang
WHERE p.id_dapur = ? 
GROUP BY p.id_pesanan
ORDER BY p.tgl_pesan DESC
LIMIT ? OFFSET ?";

$stmt_pesanan_dapur = $koneksi->prepare($sql_pesanan_dapur);
$html = '';
$current_count = 0;

if ($stmt_pesanan_dapur) {
    $stmt_pesanan_dapur->bind_param("iii", $id_dapur_login, $items_per_page, $offset);
    $stmt_pesanan_dapur->execute();
    $stmt_pesanan_dapur->store_result();
    $stmt_pesanan_dapur->bind_result(
        $id_pesanan_db, 
        $nama_pemesan_db, 
        $wa_pemesan_db, 
        $total_harga_db, 
        $status_pembayaran_db, 
        $status_pengiriman_db, 
        $tgl_pesan_db,
        $nama_driver_db,
        $nama_dapur_db,
        $items_db
    );

    while ($stmt_pesanan_dapur->fetch()) {
        $current_count++;
        $nama_driver = !empty($nama_driver_db) ? $nama_driver_db : 'Belum Ditugaskan';
        
        $pembayaran_color_class = 'badge-belum-bayar'; 
        if ($status_pembayaran_db == 'Lunas') $pembayaran_color_class = 'badge-lunas'; 
        elseif ($status_pembayaran_db == 'Batal') $pembayaran_color_class = 'badge-batal'; 
        
        $pengiriman_color_class = 'badge-pending'; 
        if ($status_pengiriman_db == 'Ongoing') $pengiriman_color_class = 'badge-ongoing'; 
        elseif ($status_pengiriman_db == 'Done') $pengiriman_color_class = 'badge-done'; 
        
        $html .= `
        <div class="invoice-card p-4">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-xxs text-gray-500 block">` . date('d F Y H:i', strtotime($tgl_pesan_db)) . `</span>
                    <h3 class="text-lg font-bold text-gray-900">#` . $id_pesanan_db . `</h3>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <span class="badge ` . $pembayaran_color_class . `">` . $status_pembayaran_db . `</span>
                    <span class="badge ` . $pengiriman_color_class . `">` . $status_pengiriman_db . `</span>
                </div>
            </div>
            
            ` . (!empty($items_db) ? `
            <div class="mb-3">
                <p class="text-xs text-gray-500 font-medium mb-1">Items:</p>
                <p class="text-sm text-gray-700 line-clamp-2 leading-tight">` . $items_db . `</p>
            </div>
            ` : '') . `
            
            <div class="mb-3">
                <p class="text-xs text-gray-500">Total Pembelian</p>
                <p class="text-xl font-bold text-gray-900">Rp` . number_format($total_harga_db, 0, ',', '.') . `</p>
            </div>

            <div class="text-xs text-gray-600 space-y-1 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-user-circle w-3 mr-1 text-gray-400"></i>
                    <span class="truncate">` . $nama_pemesan_db . `</span>
                </div>
                <div class="flex items-center">
                    <i class="fab fa-whatsapp w-3 mr-1 text-gray-400"></i>
                    <span class="truncate">` . $wa_pemesan_db . `</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-motorcycle w-3 mr-1 text-gray-400"></i>
                    <span class="truncate">` . $nama_driver . `</span>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-2">
                <button onclick="showDetailModal(` . $id_pesanan_db . `)" class="action-btn bg-blue-600 text-white hover:bg-blue-700 flex-1 text-center">
                    <i class="fas fa-eye mr-1"></i>Detail
                </button>

                ` . ($status_pembayaran_db == 'Belum Bayar' ? `
                <a href="update_status.php?id=` . $id_pesanan_db . `&aksi=lunas" class="action-btn bg-green-500 text-white hover:bg-green-600 flex-1 text-center" onclick="return confirm('Tandai pesanan #` . $id_pesanan_db . ` sebagai Lunas?');">
                    <i class="fas fa-check mr-1"></i>Lunas
                </a>
                ` : `
                <button class="action-btn bg-green-100 text-green-700 cursor-not-allowed flex-1 text-center" disabled>
                    <i class="fas fa-check-double mr-1"></i>Lunas
                </button>
                `) . `
            </div>
        </div>
        `;
    }
    $stmt_pesanan_dapur->close();
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'current_count' => $current_count,
        'total_pages' => ceil($current_count / $items_per_page)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data pesanan']);
}
?>
