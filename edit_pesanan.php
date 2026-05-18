<?php
// === BAGIAN DEBUGGING ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ========================

session_start();

if (!file_exists("koneksi.php")) {
    die("Error: File koneksi.php tidak ditemukan!");
}
include "koneksi.php";

// 1. KEAMANAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur') {
    header('Location: login.php');
    exit();
}

// 2. VALIDASI ID PESANAN
if (!isset($_GET['id'])) {
    die("Error: Parameter ID tidak ditemukan di URL. <a href='riwayat_dapur.php'>Kembali</a>");
}

$id_pesanan = (int)$_GET['id'];

// 3. AMBIL DATA PESANAN LAMA
$query_cek = "SELECT nama_pemesan, wa_pemesan, email_pemesan, tgl_digunakan, catatan, status_pembayaran, status_pengiriman FROM pesanan WHERE id_pesanan = ? LIMIT 1";
$stmt_cek = $koneksi->prepare($query_cek);

if (!$stmt_cek) { die("Error Query Pesanan: " . $koneksi->error); }

$stmt_cek->bind_param("i", $id_pesanan);
$stmt_cek->execute();
$stmt_cek->bind_result($nama_pemesan, $wa_pemesan, $email_pemesan, $tgl_digunakan, $catatan, $status_pembayaran, $status_pengiriman);

if (!$stmt_cek->fetch()) { die("Error: Pesanan ID $id_pesanan tidak ditemukan."); }

$data_pesanan = [
    'nama_pemesan' => $nama_pemesan,
    'wa_pemesan' => $wa_pemesan,
    'email_pemesan' => $email_pemesan,
    'tgl_digunakan' => $tgl_digunakan,
    'catatan' => $catatan,
    'status_pembayaran' => $status_pembayaran,
    'status_pengiriman' => $status_pengiriman
];
$stmt_cek->close();

if ($data_pesanan['status_pembayaran'] !== 'Belum Bayar' || $data_pesanan['status_pengiriman'] !== 'Pending') {
    header("Location: riwayat_dapur.php?pesan=gagal_edit");
    exit();
}

// 4. AMBIL DETAIL BARANG LAMA (Untuk Inisialisasi Cart)
$query_detail = "SELECT dp.id_barang, dp.jumlah, dp.harga_satuan, g.nama, g.satuan, g.stok 
                 FROM detail_pesanan dp 
                 JOIN gudang g ON dp.id_barang = g.id_barang 
                 WHERE dp.id_pesanan = ?";

$stmt_detail = $koneksi->prepare($query_detail);
if (!$stmt_detail) { die("Error Query Detail: " . $koneksi->error); }

$stmt_detail->bind_param("i", $id_pesanan);
$stmt_detail->execute();
$stmt_detail->bind_result($d_id_barang, $d_jumlah, $d_harga_satuan, $g_nama, $g_satuan, $g_stok);

$cart_init = [];
while ($stmt_detail->fetch()) {
    $cart_init[$d_id_barang] = [
        'id_barang' => $d_id_barang,
        'nama' => $g_nama,
        'harga' => (int)$d_harga_satuan, 
        'satuan' => $g_satuan,
        'jumlah' => (int)$d_jumlah,
        'stok' => 999999
    ];
}
$stmt_detail->close();

// 5. AMBIL SEMUA DATA BARANG GUDANG
$products = [];
// REVISI: Menambahkan kolom 'keterangan'
$sql_gudang = "SELECT id_barang, nama, kategori, harga, stok, satuan, keterangan FROM gudang";
$result_gudang = $koneksi->query($sql_gudang);

if (!$result_gudang) { die("Error Query Gudang: " . $koneksi->error); }

while ($row = $result_gudang->fetch_assoc()) {
    $products[] = [
        'id_barang' => $row['id_barang'],
        'nama' => $row['nama'],
        'kategori' => $row['kategori'],
        'harga' => $row['harga'],
        'stok' => 999999,
        'satuan' => $row['satuan'],
        'keterangan' => $row['keterangan']
    ];
}

usort($products, function($a, $b) {
    return strcasecmp($a['nama'], $b['nama']);
});

// Ambil daftar kategori unik
$categories = array_unique(array_column($products, 'kategori'));
sort($categories);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Pesanan #<?php echo $id_pesanan; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',     // Blue-600
                        primaryDark: '#1e40af', // Blue-800
                        primaryLight: '#eff6ff', // Blue-50
                        warning: '#f59e0b', // Orange for edit context
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #334155; padding-bottom: 120px; }
        
        /* Hero Section - GAMBAR DISAMAKAN DENGAN DAPUR/RIWAYAT */
        .hero-bg {
            background-image: linear-gradient(rgba(37, 99, 235, 0.9), rgba(30, 58, 138, 0.8)), url('https://images.unsplash.com/photo-1498837167922-ddd27525d352?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            height: 220px;
            color: white;
        }

        /* Sticky Summary Header */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        /* Product Card Responsive */
        .product-card {
            transition: all 0.2s ease;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.15); border-color: #93c5fd; }
        
        /* Highlight Terpilih */
        .card-selected { border: 2px solid #2563eb; background-color: #eff6ff; }

        .row-habis { background-color: #f8fafc; opacity: 0.7; }
        .row-habis .card-content { opacity: 0.5; }

        /* Horizontal Scroll untuk Kategori Mobile */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Modal & Inputs */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0.2s; padding: 16px; }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-content { background: white; padding: 0; border-radius: 16px; width: 100%; max-width: 480px; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        
        /* Adjust quantity input for mobile touch */
        .quantity-btn { width: 40px; height: 40px; }
    </style>
</head>
<body>

    <div class="hero-bg flex flex-col items-center justify-center text-center px-4 no-print relative">
        <div class="absolute top-4 left-4 text-[10px] md:text-xs font-bold tracking-widest text-blue-100 bg-white/20 px-3 py-1 rounded backdrop-blur-sm">
            EDIT ID: #<?php echo $id_pesanan; ?>
        </div>
        <div class="absolute top-4 right-4">
             <a href="riwayat_dapur.php" class="text-xs md:text-sm font-semibold text-blue-50 hover:text-white transition bg-white/10 px-3 py-1.5 md:px-4 md:py-2 rounded-full backdrop-blur-sm flex items-center">
                 <i class="fas fa-arrow-left mr-1"></i> <span class="hidden md:inline">Batal &</span> Kembali
             </a>
        </div>
        <div class="mt-4 md:mt-0">
            <h1 class="text-2xl md:text-4xl font-bold text-white mb-1 md:mb-2 drop-shadow-md">EDIT PESANAN</h1>
            <p class="text-blue-100 text-xs md:text-base max-w-xl mx-auto px-2">
                Pemesan: <strong class="text-white"><?php echo htmlspecialchars($data_pesanan['nama_pemesan']); ?></strong>
            </p>
        </div>
    </div>

    <div class="sticky-header py-3 px-4 md:px-8 no-print">
        <div class="max-w-7xl mx-auto flex flex-wrap md:flex-nowrap justify-between items-center gap-3">
            
            <div class="relative w-full md:w-96 order-2 md:order-1">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="search-bar" placeholder="Cari barang revisi..." class="w-full pl-10 pr-4 py-2 border border-slate-300 rounded-full focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary bg-slate-50 text-sm">
            </div>

            <div class="w-full md:w-auto flex justify-between md:justify-end items-center gap-4 order-1 md:order-2">
                <div class="text-left md:text-right">
                    <p class="text-[10px] md:text-xs text-slate-500 uppercase font-bold">Estimasi Total</p>
                    <p id="total-harga-display" class="font-bold text-base md:text-lg text-green-600">Rp 0</p>
                </div>
                
                <button id="simpan-perubahan-btn" class="bg-primary hover:bg-primaryDark text-white px-5 py-2 rounded-full font-semibold shadow-lg shadow-blue-200 transition flex items-center gap-2 text-sm md:text-base">
                    <i class="fas fa-save"></i>
                    <span class="hidden sm:inline">Simpan</span>
                    <span id="total-items-badge" class="bg-white text-green-600 text-xs font-bold px-2 py-0.5 rounded-full ml-1">0</span>
                </button>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 md:px-8 py-6 no-print">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <div class="lg:col-span-1 hidden lg:block">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm sticky top-24">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                        <i class="fas fa-filter text-primary"></i> Filter Kategori
                    </h3>
                    <ul class="space-y-1 max-h-[60vh] overflow-y-auto pr-1">
                        <li>
                            <button onclick="filterCategory('')" class="w-full text-left text-sm text-slate-600 hover:text-primary hover:bg-blue-50 px-2 py-1.5 rounded transition flex justify-between items-center group font-medium">
                                <span>Semua</span>
                                <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-100 text-primary transition"></i>
                            </button>
                        </li>
                        <?php foreach($categories as $cat): ?>
                        <li>
                            <button onclick="filterCategory('<?php echo $cat; ?>')" class="w-full text-left text-sm text-slate-600 hover:text-primary hover:bg-blue-50 px-2 py-1.5 rounded transition flex justify-between items-center group">
                                <span><?php echo ucwords($cat); ?></span>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="lg:col-span-3">
                
                <div class="lg:hidden mb-6 overflow-x-auto whitespace-nowrap pb-2 hide-scrollbar -mx-4 px-4">
                    <button onclick="filterCategory('')" class="inline-block px-4 py-2 bg-white border border-slate-200 rounded-full text-sm text-slate-700 mr-2 focus:bg-primary focus:text-white focus:border-primary shadow-sm transition">
                        Semua
                    </button>
                    <?php foreach($categories as $cat): ?>
                    <button onclick="filterCategory('<?php echo $cat; ?>')" class="inline-block px-4 py-2 bg-white border border-slate-200 rounded-full text-sm text-slate-700 mr-2 focus:bg-primary focus:text-white focus:border-primary shadow-sm transition">
                        <?php echo ucwords($cat); ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-slate-800">Daftar Barang</h2>
                    <div class="text-xs text-slate-500 bg-white px-3 py-1 rounded-full border border-slate-200 shadow-sm">
                        <span class="font-bold text-green-600"><?php echo count($products); ?></span> Item
                    </div>
                </div>

                <div id="product-list" class="space-y-4">
                    <?php foreach ($products as $product): 
                        $in_cart = isset($cart_init[$product['id_barang']]);
                        $qty_val = $in_cart ? $cart_init[$product['id_barang']]['jumlah'] : 0;
                    ?>
                        <div class="product-row product-card p-4 md:p-5 <?php if ($in_cart) echo 'card-selected'; ?>" 
                             data-nama="<?php echo htmlspecialchars(strtolower($product['nama'])); ?>" 
                             data-kategori="<?php echo htmlspecialchars(strtolower($product['kategori'])); ?>"
                             data-keterangan="<?php echo htmlspecialchars(strtolower($product['keterangan'] ?? '')); ?>"
                             data-stok="999999">
                            
                            <div class="flex flex-col sm:flex-row gap-4 sm:items-center">
                                
                                <div class="flex-1 w-full card-content">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="text-base md:text-lg font-bold text-slate-800 uppercase tracking-tight leading-tight">
                                                <?php echo htmlspecialchars($product['nama']); ?>
                                            </h3>
                                            <div class="flex flex-wrap gap-2 mt-1.5">
                                                <span class="text-[10px] font-semibold text-blue-600 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded">
                                                    <?php echo htmlspecialchars($product['kategori']); ?>
                                                </span>
                                                 <span class="bg-green-50 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded border border-green-100 flex items-center gap-1">
                                                     <i class="fas fa-clock"></i> PO 7 HARI
                                                 </span>
                                                <?php if ($in_cart): ?>
                                                    <span class="bg-green-100 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded border border-green-200 flex items-center gap-1">
                                                        <i class="fas fa-check-circle"></i> DIPESAN
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($product['keterangan'])): ?>
                                                <div class="text-[11px] text-slate-500 mt-2 flex items-start gap-1.5 italic bg-slate-50 p-1.5 rounded border border-slate-100 inline-block">
                                                    <i class="fas fa-info-circle text-[10px] mt-0.5 text-blue-400"></i>
                                                    <span><?php echo htmlspecialchars($product['keterangan']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right sm:hidden">
                                            <p class="text-base font-bold text-green-600">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></p>
                                            <p class="text-[10px] text-slate-400">/<?php echo $product['satuan']; ?></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2 text-[11px] md:text-xs text-slate-500 flex items-center gap-2 bg-slate-50 p-2 rounded-lg border border-slate-100 inline-flex">
                                         <i class="fas fa-calendar-alt text-blue-400"></i> 
                                         Sistem: <strong>Pre-Order (PO 7 Hari)</strong>
                                    </div>
                                </div>

                                <div class="w-full sm:w-auto flex flex-row sm:flex-col justify-between sm:justify-center items-center gap-3 sm:border-l sm:border-slate-100 sm:pl-6 min-w-[140px] pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                                    <p class="text-xl font-bold text-primary hidden sm:block">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></p>
                                    
                                    <?php if ($product['stok'] > 0): ?>
                                         <div class="quantity-control flex items-center border border-slate-300 rounded-lg overflow-hidden h-10 w-32 sm:w-full">
                                            <button onclick="updateJumlah(this, -1)" class="quantity-btn bg-slate-50 hover:bg-slate-200 text-slate-600 transition flex-1 flex items-center justify-center"><i class="fas fa-minus text-xs"></i></button>
                                            
                                            <input type="number" name="jumlah" value="<?php echo $qty_val; ?>" min="0" class="w-10 h-full text-center text-sm font-bold border-x border-slate-300 focus:outline-none" 
                                                   oninput="validateJumlah(this)">
                                            
                                            <button onclick="updateJumlah(this, 1)" class="quantity-btn bg-slate-50 hover:bg-slate-200 text-slate-600 transition flex-1 flex items-center justify-center"><i class="fas fa-plus text-xs"></i></button>
                                        </div>

                                         <button 
                                            class="w-full text-xs font-bold py-2.5 px-3 rounded-lg uppercase transition flex items-center justify-center gap-2 shadow-sm btn-cart-action 
                                            <?php echo $in_cart ? 'bg-green-600 text-white hover:bg-green-700 ring-2 ring-green-200' : 'bg-white border border-primary text-primary hover:bg-primary hover:text-white'; ?>"
                                            data-id="<?php echo $product['id_barang']; ?>"
                                            data-nama="<?php echo htmlspecialchars($product['nama']); ?>"
                                            data-harga="<?php echo $product['harga']; ?>"
                                            data-satuan="<?php echo htmlspecialchars($product['satuan']); ?>"
                                            data-stok="999999"
                                            onclick="addToCart(this)">
                                            <?php if ($in_cart): ?>
                                                <i class="fas fa-check"></i> Update
                                            <?php else: ?>
                                                <i class="fas fa-plus"></i> Tambah
                                            <?php endif; ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="w-full bg-slate-100 text-slate-400 text-xs font-bold py-2 rounded-lg cursor-not-allowed border border-slate-200 flex items-center justify-center gap-2" disabled>
                                            <i class="fas fa-ban"></i> Habis
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div> 
    
    <div id="confirm-modal" class="modal-overlay">
        <div class="modal-content animate-fade-in-up">
            <div class="bg-blue-50 px-6 py-4 border-b border-blue-100 flex justify-between items-center">
                <h2 class="text-lg font-bold text-blue-800">Konfirmasi & Lengkapi Data</h2>
                <button onclick="document.getElementById('confirm-modal').classList.remove('show')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-6 overflow-y-auto">
                <form id="edit-form">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nama Pemesan *</label>
                            <input type="text" id="edit-nama" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none text-sm" value="<?php echo htmlspecialchars($data_pesanan['nama_pemesan']); ?>">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">No. WhatsApp *</label>
                                <input type="text" id="edit-wa" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none text-sm" value="<?php echo htmlspecialchars(str_replace('+62', '', $data_pesanan['wa_pemesan'])); ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email (Opsional)</label>
                                <input type="email" id="edit-email" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none text-sm" value="<?php echo htmlspecialchars($data_pesanan['email_pemesan'] ?? ''); ?>">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tanggal Digunakan *</label>
                            <input type="date" id="edit-tgl-digunakan" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none text-sm" value="<?php echo $data_pesanan['tgl_digunakan']; ?>">
                            <p class="text-[10px] text-blue-600 mt-1"><i class="fas fa-info-circle mr-1"></i>Min. 7 hari dari sekarang</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Catatan (Opsional)</label>
                            <textarea id="edit-catatan" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none text-sm"><?php echo htmlspecialchars($data_pesanan['catatan'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-100">
                        <button type="button" onclick="document.getElementById('confirm-modal').classList.remove('show')" class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 text-slate-600 font-bold rounded-xl hover:bg-slate-50 transition text-sm">Batal</button>
                        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-200 transition text-sm">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="message-modal" class="modal-overlay">
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center shadow-2xl transform scale-100 transition-transform mx-4">
            <div id="msg-icon-container" class="mb-4">
                <i id="message-icon" class="fas fa-check-circle text-green-500 text-5xl"></i>
            </div>
            <h3 id="message-title" class="text-xl font-bold text-slate-800 mb-2">Berhasil</h3>
            <p id="message-body" class="text-slate-500 mb-6 text-sm">Pesan anda di sini.</p>
            <button id="message-close-btn" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold py-3 rounded-xl transition">OK, Mengerti</button>
        </div>
    </div>

    <script>
        // INISIALISASI KERANJANG DARI PHP
        let cart = {};
        try {
            cart = <?php echo json_encode($cart_init); ?>;
        } catch(e) {
            console.error("Error parsing cart data", e);
        }
        
        const editOrderId = <?php echo $id_pesanan; ?>;
        let customerData = {
            nama: "<?php echo htmlspecialchars($data_pesanan['nama_pemesan']); ?>",
            wa: "<?php echo htmlspecialchars($data_pesanan['wa_pemesan']); ?>", 
            email: "<?php echo htmlspecialchars($data_pesanan['email_pemesan'] ?? ''); ?>",
            tgl_digunakan: "<?php echo $data_pesanan['tgl_digunakan']; ?>",
            catatan: <?php echo json_encode($data_pesanan['catatan'] ?? ''); ?>
        };

        const totalHargaDisplay = document.getElementById('total-harga-display');
        const totalItemsBadge = document.getElementById('total-items-badge');
        const searchBar = document.getElementById('search-bar');
        const searchBarMobile = document.getElementById('search-bar-mobile');
        const productList = document.getElementById('product-list');
        const messageModal = document.getElementById('message-modal');
        const confirmModal = document.getElementById('confirm-modal');

        function filterCategory(catName) {
            const inputs = [searchBar, searchBarMobile];
            inputs.forEach(input => {
                if(input) {
                    input.value = catName;
                    input.dispatchEvent(new Event('input'));
                }
            });
            // Scroll to product list
            if(productList) {
                const yOffset = -180; 
                const y = productList.getBoundingClientRect().top + window.pageYOffset + yOffset;
                window.scrollTo({top: y, behavior: 'smooth'});
            }
        }

        function updateJumlah(button, amount) {
            const input = button.parentElement.querySelector('input[name="jumlah"]');
            let currentValue = parseInt(input.value);
            let newValue = currentValue + amount;
            if (newValue < 0) newValue = 0;
            input.value = newValue;
            
            // Manual update, tidak otomatis masuk cart
        }

        function validateJumlah(input) {
            let value = parseInt(input.value);
            if (isNaN(value) || value < 0) input.value = 0;
        }

        function addToCart(button) {
            const id = button.getAttribute('data-id');
            const nama = button.getAttribute('data-nama');
            const harga = parseFloat(button.getAttribute('data-harga'));
            const satuan = button.getAttribute('data-satuan');
            const stok = parseInt(button.getAttribute('data-stok'));
            const card = button.closest('.product-card');
            const inputJumlah = card.querySelector('input[name="jumlah"]');
            const jumlah = parseInt(inputJumlah.value);

            if (jumlah > stok) {
                showMessage('Stok Kurang', `Stok ${nama} tidak cukup.`, false);
                return;
            }

            if (jumlah > 0) {
                cart[id] = { id_barang: id, nama, harga, satuan, jumlah, stok };
                button.innerHTML = '<i class="fas fa-check mr-1"></i> Update';
                button.className = "w-full text-xs font-bold py-2.5 px-3 rounded-lg uppercase transition flex items-center justify-center gap-2 shadow-sm btn-cart-action bg-green-600 text-white hover:bg-green-700 ring-2 ring-green-200";
                card.classList.add('card-selected');
            } else {
                delete cart[id];
                button.innerHTML = '<i class="fas fa-plus mr-1"></i> Tambah';
                button.className = "w-full text-xs font-bold py-2.5 px-3 rounded-lg uppercase transition flex items-center justify-center gap-2 shadow-sm btn-cart-action bg-white border border-primary text-primary hover:bg-primary hover:text-white";
                card.classList.remove('card-selected');
            }
            
            updateTotalHarga();
        }

        function updateTotalHarga() {
            let total = 0;
            let totalItems = 0;
            for (const id in cart) {
                total += cart[id].harga * cart[id].jumlah;
                totalItems += cart[id].jumlah;
            }
            totalHargaDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
            totalItemsBadge.textContent = totalItems;
        }

        function handleSearch(e) {
            const query = e.target.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                const nama = card.getAttribute('data-nama') || '';
                const kategori = card.getAttribute('data-kategori') || '';
                // REVISI: TAMBAHKAN PENCARIAN KETERANGAN
                const keterangan = card.getAttribute('data-keterangan') || '';
                
                if (nama.includes(query) || kategori.includes(query) || keterangan.includes(query)) { 
                    card.style.display = 'block'; 
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        if(searchBar) searchBar.addEventListener('input', handleSearch);
        if(searchBarMobile) searchBarMobile.addEventListener('input', handleSearch);

        document.getElementById('simpan-perubahan-btn').addEventListener('click', function() {
            if (Object.keys(cart).length === 0) {
                showMessage('Pesanan Kosong', 'Pesanan tidak boleh kosong.', false);
                return;
            }
            
            // Set min date for tgl-digunakan (today + 7 days)
            const dateInput = document.getElementById('edit-tgl-digunakan');
            if (dateInput) {
                const minDate = new Date();
                minDate.setDate(minDate.getDate() + 7);
                const yyyy = minDate.getFullYear();
                const mm = String(minDate.getMonth() + 1).padStart(2, '0');
                const dd = String(minDate.getDate()).padStart(2, '0');
                dateInput.min = `${yyyy}-${mm}-${dd}`;
            }
            
            confirmModal.classList.add('show');
        });

        // Handle Form Submit
        const editForm = document.getElementById('edit-form');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const nama = document.getElementById('edit-nama').value.trim();
                const wa = document.getElementById('edit-wa').value.trim();
                const email = document.getElementById('edit-email').value.trim();
                const tgl_digunakan = document.getElementById('edit-tgl-digunakan').value;
                const catatan = document.getElementById('edit-catatan').value.trim();
                
                if (!nama || !wa || !tgl_digunakan) {
                    showMessage('Data Kurang', 'Nama, WhatsApp, dan Tanggal Digunakan wajib diisi.', false);
                    return;
                }
                
                const btn = editForm.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Menyimpan...';
                
                // Update customerData object
                customerData = {
                    nama: nama,
                    wa: '+62' + wa.replace(/^\+62|^62|^0/, ''), // Normalize to +62
                    email: email,
                    tgl_digunakan: tgl_digunakan,
                    catatan: catatan
                };
                
                const dataToSend = {
                    editOrderId: editOrderId, 
                    cart: cart,
                    customerData: customerData 
                };
                
                fetch('laporanPenjualan.php?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(dataToSend)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        confirmModal.classList.remove('show');
                        showMessage('Berhasil Diupdate', data.message, true, function() {
                            window.location.href = 'dapur.php'; 
                        });
                    } else {
                        showMessage('Gagal', data.message || 'Terjadi kesalahan.', false);
                        btn.disabled = false;
                        btn.innerHTML = 'Simpan Perubahan';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error', 'Gagal terhubung ke server.', false);
                    btn.disabled = false;
                    btn.innerHTML = 'Simpan Perubahan';
                });
            });
        }

        function showMessage(title, message, isSuccess = true, callback = null) {
            document.getElementById('message-title').textContent = title;
            document.getElementById('message-body').innerHTML = message;
            const icon = document.getElementById('message-icon');
            const closeBtn = document.getElementById('message-close-btn');
            
            icon.className = isSuccess ? "fas fa-check-circle text-green-500 text-5xl mb-3" : "fas fa-times-circle text-red-500 text-5xl mb-3";
            messageModal.classList.add('show');
            closeBtn.onclick = function() {
                messageModal.classList.remove('show');
                if (callback) callback();
            }
        }
        
        updateTotalHarga();
    </script>
</body>
</html>
