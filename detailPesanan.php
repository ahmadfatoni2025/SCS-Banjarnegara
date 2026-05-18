<?php
// === 1. PENGATURAN DASAR ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "koneksi.php"; // Pastikan koneksi.php ada

// === 2. KEAMANAN BARU ===
// Cek jika ada user login
if (!isset($_SESSION['role']) || !isset($_SESSION['user']['id'])) {
    die("Akses ditolak. Silakan login.");
}

$id_pesanan = (int)($_GET['id'] ?? 0);
$user_role = $_SESSION['role'];
$user_id = (int)$_SESSION['user']['id'];

if ($id_pesanan <= 0) {
    die("ID Pesanan tidak valid.");
}

// Cek kepemilikan pesanan
$id_dapur_pemilik = 0;
$stmt_owner = $koneksi->prepare("SELECT id_dapur FROM pesanan WHERE id_pesanan = ?");
if ($stmt_owner) {
    $stmt_owner->bind_param("i", $id_pesanan);
    $stmt_owner->execute();
    $stmt_owner->store_result();
    $stmt_owner->bind_result($id_dapur_pemilik_db);
    $stmt_owner->fetch();
    $id_dapur_pemilik = (int)$id_dapur_pemilik_db;
    $stmt_owner->close();
}

// Validasi Izin:
// Anda boleh lihat jika (Anda 'admin') ATAU (Anda 'dapur' DAN ID Anda == ID pemilik)
if ($user_role === 'admin' || ($user_role === 'dapur' && $user_id === $id_dapur_pemilik)) {
    // Izin diberikan, lanjutkan skrip
} else {
    // Jika user adalah 'dapur' tapi bukan pemiliknya, atau role lain
    die("Akses ditolak. Anda tidak memiliki izin untuk melihat detail pesanan ini.");
}
// === AKHIR KEAMANAN BARU ===


// === 3. AMBIL DATA PESANAN (Logika dari file lama Anda) ===
$pesanan = null;
$nama_dapur = 'User Dihapus';
$nama_driver = 'Belum Ditugaskan';

// Ambil data pesanan utama, TERMASUK 'nama_driver', 'tgl_digunakan', 'catatan'
$sql = "SELECT id_pesanan, id_dapur, id_driver, nama_pemesan, email_pemesan, wa_pemesan, total_harga, status_pembayaran, status_pengiriman, tgl_pesan, nama_driver, tgl_digunakan, catatan 
        FROM pesanan 
        WHERE id_pesanan = ?";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("i", $id_pesanan);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id_pesanan_db, $id_dapur_db, $id_driver_db, $nama_pemesan_db, $email_pemesan_db, $wa_pemesan_db, $total_harga_db, $status_pembayaran_db, $status_pengiriman_db, $tgl_pesan_db, $nama_driver_db, $tgl_digunakan_db, $catatan_db);

if ($stmt->fetch()) {
    $pesanan = [
        'id_pesanan' => $id_pesanan_db,
        'id_dapur' => $id_dapur_db,
        'id_driver' => $id_driver_db,
        'nama_pemesan' => $nama_pemesan_db,
        'email_pemesan' => $email_pemesan_db,
        'wa_pemesan' => $wa_pemesan_db,
        'total_harga' => $total_harga_db,
        'status_pembayaran' => $status_pembayaran_db,
        'status_pengiriman' => $status_pengiriman_db,
        'tgl_pesan' => $tgl_pesan_db,
        'nama_driver' => $nama_driver_db,
        'tgl_digunakan' => $tgl_digunakan_db,
        'catatan' => $catatan_db
    ];
}
$stmt->free_result();
$stmt->close();


if ($pesanan) {
    // === 4. LOGIKA PENGAMBILAN NAMA (SAMA SEPERTI LAPORAN) ===
    
    // Ambil Nama Dapur
    if ($pesanan['id_dapur']) {
        $stmt_dapur = $koneksi->prepare("SELECT nama FROM user WHERE id = ?"); 
        if ($stmt_dapur) {
            $stmt_dapur->bind_param("i", $pesanan['id_dapur']);
            if ($stmt_dapur->execute()) {
                $stmt_dapur->store_result();
                $stmt_dapur->bind_result($nama_dapur_db);
                if ($stmt_dapur->fetch()) {
                    $nama_dapur = $nama_dapur_db;
                }
                $stmt_dapur->free_result();
            }
            $stmt_dapur->close();
        }
    }

    // Ambil Nama Driver (LOGIKA PRIORITAS)
    if (!empty($pesanan['nama_driver'])) {
        $nama_driver = $pesanan['nama_driver'];
    } 
    elseif ($pesanan['id_driver'] !== null && $pesanan['id_driver'] > 0) { 
        $stmt_driver = $koneksi->prepare("SELECT nama FROM user WHERE id = ?"); 
        if ($stmt_driver) {
            $stmt_driver->bind_param("i", $pesanan['id_driver']);
            if ($stmt_driver->execute()) {
                $stmt_driver->store_result();
                $stmt_driver->bind_result($nama_driver_db);
                if ($stmt_driver->fetch()) {
                    $nama_driver = $nama_driver_db;
                }
                $stmt_driver->free_result();
            }
            $stmt_driver->close();
        }
    }
    
    // === 5. AMBIL RINCIAN BARANG ===
    $rincian_barang = [];
    $sql_detail = "SELECT G.nama, DP.jumlah, DP.harga_satuan, G.satuan 
                   FROM detail_pesanan AS DP 
                   JOIN gudang AS G ON DP.id_barang = G.id_barang 
                   WHERE DP.id_pesanan = ?";
    
    $stmt_detail = $koneksi->prepare($sql_detail);
    $stmt_detail->bind_param("i", $id_pesanan);
    $stmt_detail->execute();
    $stmt_detail->store_result();
    $stmt_detail->bind_result($nama_barang_db, $jumlah_db, $harga_satuan_db, $satuan_db);
    
    while($stmt_detail->fetch()) {
        $rincian_barang[] = [
            'nama' => $nama_barang_db,
            'jumlah' => $jumlah_db,
            'harga_satuan' => $harga_satuan_db,
            'satuan' => $satuan_db,
            'subtotal' => $jumlah_db * $harga_satuan_db
        ];
    }
    $stmt_detail->free_result();
    $stmt_detail->close();
    
} // Akhir dari if ($pesanan)

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - SCS Banjarnegara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { scrollbar-width: none; -ms-overflow-style: none; }
        body::-webkit-scrollbar { display: none; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 500; font-size: 0.75rem; line-height: 1rem; }
        .badge-pending { background-color: #e0f2fe; color: #0284c7; } 
        .badge-ongoing { background-color: #fef3c7; color: #d97706; } 
        .badge-done { background-color: #d1fae5; color: #059669; } 
        .badge-belum-bayar { background-color: #fef3c7; color: #d97706; } 
        .badge-lunas { background-color: #d1fae5; color: #059669; } 
    </style>
<!-- ===== FAVICON ===== -->
    <link rel="icon" href="logo_scs_jpg.png" type="image/png" sizes="32x32">
    <link rel="icon" href="logo_scs_jpg.png" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="logo_scs_jpg.png" sizes="180x180">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#3498db">
</head>
<body class="bg-white p-4">

    <?php if ($pesanan): ?>
        <div class="max-w-4xl mx-auto">
            
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Detail Pesanan #<?php echo $pesanan['id_pesanan']; ?></h1>
                    <p class="text-sm text-gray-500">Tanggal Pesan: <?php echo date('d F Y H:i', strtotime($pesanan['tgl_pesan'])); ?></p>
                </div>
                <div class="flex flex-col items-end gap-1 text-xs">
                    <?php 
                        $pembayaran_color_class = ($pesanan['status_pembayaran'] == 'Lunas') ? 'badge-lunas' : 'badge-belum-bayar';
                        $pengiriman_color_class = 'badge-pending';
                        if ($pesanan['status_pengiriman'] == 'Ongoing') $pengiriman_color_class = 'badge-ongoing';
                        elseif ($pesanan['status_pengiriman'] == 'Done') $pengiriman_color_class = 'badge-done';
                    ?>
                    <span class="badge <?php echo $pembayaran_color_class; ?>"><?php echo htmlspecialchars($pesanan['status_pembayaran']); ?></span>
                    <span class="badge <?php echo $pengiriman_color_class; ?>"><?php echo htmlspecialchars($pesanan['status_pengiriman']); ?></span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 border-t border-b border-gray-200 py-6 my-6">
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pemesan (Dapur)</h3>
                    <p class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($nama_dapur); ?></p>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kontak Pelanggan</h3>
                    <p class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($pesanan['nama_pemesan']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pesanan['wa_pemesan']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pesanan['email_pemesan'] ?? '-'); ?></p>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Driver</h3>
                    <p class="text-base font-medium text-gray-900"><?php echo htmlspecialchars($nama_driver); ?></p>
                </div>
            </div>

            <!-- INFO PENGGUNAAN & CATATAN -->
            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-6 rounded-xl border border-slate-100 mb-8 border-l-4 border-blue-500">
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">📅 Tanggal Digunakan (PO)</h3>
                    <p class="text-lg font-bold text-blue-700">
                        <?php echo $pesanan['tgl_digunakan'] ? date('d F Y', strtotime($pesanan['tgl_digunakan'])) : '-'; ?>
                    </p>
                </div>
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">📝 Catatan Tambahan</h3>
                    <p class="text-sm text-gray-700 italic">
                        "<?php echo !empty($pesanan['catatan']) ? htmlspecialchars($pesanan['catatan']) : 'Tidak ada catatan khusus'; ?>"
                    </p>
                </div>
            </div>

            <div>
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Rincian Barang</h2>
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Nama Barang</th>
                            <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Harga Satuan</th>
                            <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($rincian_barang as $item): ?>
                            <tr>
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo htmlspecialchars($item['nama']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700">Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700"><?php echo $item['jumlah'] . ' ' . htmlspecialchars($item['satuan']); ?></td>
                                <td class="py-3 px-4 text-sm text-gray-700 text-right">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td colspan="3" class="py-4 px-4 text-gray-800 text-right">Total Keseluruhan</td>
                            <td class="py-4 px-4 text-gray-900 text-right text-lg">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="text-right mt-8 no-print">
                <button onclick="window.print();" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>

        </div>
    <?php else: ?>
        <p class="text-center text-red-500">Gagal memuat detail pesanan. ID tidak ditemukan.</p>
    <?php endif; ?>

</body>
</html>
