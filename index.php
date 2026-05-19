<?php
session_start();
include 'koneksi.php';

// CEK APAKAH USER SUDAH LOGIN
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    // Redirect ke halaman login jika belum login
    header("Location: login.php");
    exit();
}

// --- PENGATURAN PAGINATION ---
$page = 1; 

// 2. Logika PHP Spesifik untuk Halaman Ini
$pesan_sukses = '';
$pesan_error = '';
$show_modal = false;
$data_edit = null;

// === LOGIKA HAPUS SUPLIER (FITUR BARU) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_suplier_id'])) {
    $id_sup_del = (int)$_POST['hapus_suplier_id'];
    if ($id_sup_del > 0) {
        try {
            // 1. Set semua barang yang punya suplier ini menjadi NULL (Tanpa Suplier) agar tidak error
            $koneksi->query("UPDATE gudang SET id_suplier = NULL WHERE id_suplier = $id_sup_del");
            
            // 2. Hapus suplier dari database
            if ($koneksi->query("DELETE FROM data_suplier WHERE id_suplier = $id_sup_del")) {
                $pesan_sukses = "Suplier berhasil dihapus dari database.";
            } else {
                $pesan_error = "Gagal menghapus suplier.";
            }
        } catch (Exception $e) {
            $pesan_error = "Error: " . $e->getMessage();
        }
    }
    // Redirect supaya bersih
    header("Location: index.php?status=" . urlencode($pesan_sukses . $pesan_error));
    exit;
}

// === AMBIL DATA SUPLIER UNTUK DATALIST DAN FILTER ===
$list_suplier = [];
$q_suplier = $koneksi->query("SELECT id_suplier, nama_suplier FROM data_suplier ORDER BY nama_suplier ASC");
if ($q_suplier) {
    while ($row_s = $q_suplier->fetch_assoc()) {
        $list_suplier[] = $row_s;
    }
}

// =========================================================================
// === BLOK LOGIKA SIMPAN (TAMBAH & EDIT) ===
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'hapus_suplier') { // Cek agar tidak bentrok
    
    $nama = $_POST['nama'] ?? '';
    $keterangan = $_POST['keterangan'] ?? ''; 
    $kategori = $_POST['kategori'] ?? '';
    $satuan = $_POST['satuan'] ?? '';
    $harga_beli = (float)($_POST['harga_beli'] ?? 0);
    $harga = (float)($_POST['harga'] ?? 0);
    $stok = (float)($_POST['stok'] ?? 0); 
    if ($stok < 0) $stok = 0; // Pastikan stok tidak minus
    
    $id_suplier = null; 
    $opsi_suplier = $_POST['opsi_suplier'] ?? 'tanpa';
    $input_nama_suplier = trim($_POST['nama_suplier_input'] ?? '');

    if ($opsi_suplier === 'dengan' && !empty($input_nama_suplier)) {
        $stmt_cek = $koneksi->prepare("SELECT id_suplier FROM data_suplier WHERE nama_suplier = ? LIMIT 1");
        $stmt_cek->bind_param("s", $input_nama_suplier);
        $stmt_cek->execute();
        $stmt_cek->store_result();
        
        if ($stmt_cek->num_rows > 0) {
            $stmt_cek->bind_result($existing_id);
            $stmt_cek->fetch();
            $id_suplier = $existing_id;
        } else {
            $stmt_new = $koneksi->prepare("INSERT INTO data_suplier (nama_suplier, nama_barang, keterangan) VALUES (?, 'General', 'Input via Gudang')");
            $stmt_new->bind_param("s", $input_nama_suplier);
            if ($stmt_new->execute()) {
                $id_suplier = $koneksi->insert_id;
            }
            $stmt_new->close();
        }
        $stmt_cek->close();
    }

    $tipe_pengadaan = $_POST['tipe_pengadaan'] ?? 'Stok';

    if ($_POST['action'] === 'tambah') {
        try {
            $stmt = $koneksi->prepare("INSERT INTO gudang (nama, keterangan, kategori, satuan, harga_beli, harga, stok, id_suplier, tipe_pengadaan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdddis", $nama, $keterangan, $kategori, $satuan, $harga_beli, $harga, $stok, $id_suplier, $tipe_pengadaan); 
            
            if ($stmt->execute()) {
                $pesan_sukses = "Barang berhasil ditambahkan.";
            } else {
                $pesan_error = "Gagal menambah: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
             $pesan_error = "Error: " . $e->getMessage();
        }

    } elseif ($_POST['action'] === 'edit' && isset($_POST['id_barang'])) {
        $id_barang = (int)$_POST['id_barang'];
        try {
            $stmt = $koneksi->prepare("UPDATE gudang SET nama = ?, keterangan = ?, kategori = ?, satuan = ?, harga_beli = ?, harga = ?, stok = ?, id_suplier = ?, tipe_pengadaan = ? WHERE id_barang = ?");
            $stmt->bind_param("ssssdddisi", $nama, $keterangan, $kategori, $satuan, $harga_beli, $harga, $stok, $id_suplier, $tipe_pengadaan, $id_barang); 
            
            if ($stmt->execute()) {
                $pesan_sukses = "Barang berhasil diperbarui.";
            } else {
                $pesan_error = "Gagal update: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $pesan_error = "Error: " . $e->getMessage();
        }
    }
    
    $status_msg = !empty($pesan_sukses) ? $pesan_sukses : $pesan_error;
    header("Location: index.php?status=" . urlencode($status_msg));
    exit;
}

// === HAPUS DATA BARANG ===
if (isset($_GET['action']) && $_GET['action'] === 'hapus') {
    $id_barang = (int)$_GET['id'];
    $nama = 'Barang'; 
    try {
        if ($id_barang > 0) {
            $stmt_nama = $koneksi->prepare("SELECT nama FROM gudang WHERE id_barang = ?");
            $stmt_nama->bind_param("i", $id_barang);
            $stmt_nama->execute();
            $stmt_nama->bind_result($nama_res);
            $stmt_nama->fetch();
            $nama = $nama_res ?? 'Barang';
            $stmt_nama->close();

            $stmt_hapus = $koneksi->prepare("DELETE FROM gudang WHERE id_barang = ?");
            $stmt_hapus->bind_param("i", $id_barang);
            if ($stmt_hapus->execute()) { $pesan_sukses = "Data dihapus."; } 
            else { $pesan_error = "Gagal hapus."; }
            $stmt_hapus->close();
        }
    } catch (Exception $e) { $pesan_error = "Gagal: " . $e->getMessage(); }
    header("Location: index.php?status=" . urlencode($pesan_sukses . $pesan_error));
    exit; 
}

// === MODAL DATA ===
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'tambah') {
        $show_modal = true;
        $data_edit = null;
    } elseif ($_GET['action'] === 'edit' && isset($_GET['id'])) {
        $id_barang = (int)$_GET['id'];
        $sql_edit = "SELECT g.id_barang, g.nama, g.keterangan, g.kategori, g.satuan, g.harga_beli, g.harga, g.stok, g.id_suplier, s.nama_suplier, g.tipe_pengadaan 
                     FROM gudang g 
                     LEFT JOIN data_suplier s ON g.id_suplier = s.id_suplier 
                     WHERE g.id_barang = ?";
        
        $stmt_edit = $koneksi->prepare($sql_edit);
        $stmt_edit->bind_param("i", $id_barang);
        $stmt_edit->execute();
        $stmt_edit->store_result();
        
        if ($stmt_edit->num_rows > 0) {
            $stmt_edit->bind_result($id_barang_res, $nama_res, $ket_res, $kategori_res, $satuan_res, $harga_beli_res, $harga_res, $stok_res, $id_suplier_res, $nama_suplier_res, $tipe_pengadaan_res);
            $stmt_edit->fetch();
            $data_edit = [
                'id_barang' => $id_barang_res,
                'nama' => $nama_res,
                'keterangan' => $ket_res,
                'kategori' => $kategori_res,
                'satuan' => $satuan_res,
                'harga_beli' => $harga_beli_res,
                'harga' => $harga_res,
                'stok' => $stok_res,
                'id_suplier' => $id_suplier_res,
                'nama_suplier' => $nama_suplier_res,
                'tipe_pengadaan' => $tipe_pengadaan_res
            ];
            $show_modal = true;
        }
        $stmt_edit->close();
    }
}

if (isset($_GET['status'])) {
    $status_message = htmlspecialchars($_GET['status']);
    if (strpos(strtolower($status_message), 'gagal') !== false || strpos(strtolower($status_message), 'error') !== false) {
        $pesan_error = $status_message;
    } else {
        $pesan_sukses = $status_message;
    }
}

// === QUERY UTAMA (SEARCH & FILTER) ===
$search_query = ""; 
$sql_where = ""; 
$sql_params_data = []; 
$sql_types_data = "";

// 1. Handle Search Text
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $koneksi->real_escape_string($_GET['search']);
    $sql_where = " WHERE (g.nama LIKE ? OR g.kategori LIKE ? OR g.keterangan LIKE ?)";
    $search_param = "%$search_query%";
    $sql_params_data = [$search_param, $search_param, $search_param];
    $sql_types_data = "sss";
}

// 2. Handle Filter Suplier
$filter_suplier_id = isset($_GET['filter_suplier']) ? $_GET['filter_suplier'] : '';
if (!empty($filter_suplier_id)) {
    if ($sql_where != "") {
        $sql_where .= " AND g.id_suplier = ?";
    } else {
        $sql_where = " WHERE g.id_suplier = ?";
    }
    $sql_params_data[] = $filter_suplier_id;
    $sql_types_data .= "i";
}

$inventory_data = [];
$sql_select = "SELECT g.id_barang, g.nama, g.keterangan, g.kategori, g.satuan, g.harga_beli, g.harga, g.stok, g.is_pinned, s.nama_suplier, g.tipe_pengadaan 
               FROM gudang g 
               LEFT JOIN data_suplier s ON g.id_suplier = s.id_suplier 
               $sql_where 
               ORDER BY g.is_pinned DESC, g.nama ASC";

$stmt_select = $koneksi->prepare($sql_select);
if ($stmt_select) {
    if (!empty($sql_params_data)) { $stmt_select->bind_param($sql_types_data, ...$sql_params_data); }
    $stmt_select->execute();
    $stmt_select->bind_result($id_barang_res, $nama_res, $ket_res, $kategori_res, $satuan_res, $harga_beli_res, $harga_res, $stok_res, $is_pinned_res, $nama_suplier_res, $tipe_pengadaan_res);
    while ($stmt_select->fetch()) {
        $inventory_data[] = [
            'id_barang' => $id_barang_res, 'nama' => $nama_res, 'keterangan' => $ket_res,
            'kategori' => $kategori_res, 'satuan' => $satuan_res, 'harga_beli' => $harga_beli_res,
            'harga' => $harga_res, 'stok' => $stok_res, 'is_pinned' => $is_pinned_res, 'nama_suplier' => $nama_suplier_res,
            'tipe_pengadaan' => $tipe_pengadaan_res
        ];
    }
    $stmt_select->close();
}

// === STATISTIK ===
$stats = $koneksi->query("SELECT COUNT(id_barang) as total_produk, SUM(stok) as total_stok, COUNT(DISTINCT kategori) as total_kategori, SUM(harga * stok) as total_nilai, SUM((harga - COALESCE(harga_beli, 0)) * stok) as total_margin FROM gudang")->fetch_assoc();
$total_produk = $stats['total_produk'] ?? 0; $total_stok = $stats['total_stok'] ?? 0; $total_kategori = $stats['total_kategori'] ?? 0;
$total_nilai = $stats['total_nilai'] ?? 0; $total_margin = $stats['total_margin'] ?? 0;

function getStockStatus($stok) {
    if ($stok < 20) return ['row_class' => 'bg-red-50 hover:bg-red-100', 'text_class' => 'text-red-600 font-bold'];
    if ($stok > 30) return ['row_class' => 'hover:bg-green-50', 'text_class' => 'text-green-600 font-bold'];
    return ['row_class' => 'bg-yellow-50 hover:bg-yellow-100', 'text_class' => 'text-yellow-600 font-bold'];
}
$nama_user = $_SESSION['user']['nama'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris Gudang - MBG Admin</title>
    <link rel="icon" href="logo_scs_jpg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        .modal-overlay { position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 50; backdrop-filter: blur(2px); }
        .modal-content { max-height: 90vh; display: flex; flex-direction: column; }
        .card { background: white; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        .btn { padding: 0.5rem 1rem; border-radius: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background-color: #1e293b; color: white; }
        .btn-primary:hover { background-color: #0f172a; }
        .btn-success { background-color: #10B981; color: white; }
        .btn-success:hover { background-color: #059669; }
        .compact-table th { padding: 0.75rem 1rem; font-size: 0.65rem; font-weight: 700; color: #94a3b8; background-color: rgba(248,250,252,0.8); border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.05em; }
        .compact-table td { padding: 0.75rem 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .sticky-header thead th { position: sticky; top: 0; z-index: 10; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-4 md:p-6 md:ml-64 pb-20 transition-all duration-300">
        <div class="container mx-auto max-w-7xl fade-in">
            <div class="sticky top-0 z-20 bg-[#F3F4F6] pt-2 pb-4 mb-4">
                <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Gudang Bahan Baku</h1>
                        <p class="text-slate-500 text-sm mt-1">Kelola stok dan aset dapur MBG.</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="export_csv.php" class="btn bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-sm text-sm"><i class="fas fa-file-excel mr-2 text-emerald-600"></i> Export</a>
                        <a href="https://scsbanjarnegara.com/index.php?action=tambah" 
   id="add-material-btn" 
   class="btn btn-primary shadow-sm text-sm"
   onclick="event.stopImmediatePropagation(); return true;">
   <i class="fas fa-plus mr-2"></i> Tambah Barang
</a>
                    </div>
                </header>

                <?php if ($pesan_sukses): ?> <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-4 rounded-r text-sm font-medium"><i class="fas fa-check-circle mr-2"></i> <?php echo $pesan_sukses; ?></div> <?php endif; ?>
                <?php if ($pesan_error): ?> <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-r text-sm font-medium"><i class="fas fa-exclamation-circle mr-2"></i> <?php echo $pesan_error; ?></div> <?php endif; ?>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
                     <div class="card p-4 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><i class="fas fa-cube"></i></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Produk</p><p class="text-lg font-bold text-slate-800"><?php echo $total_produk; ?></p></div>
                     </div>
                     <div class="card p-4 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center shrink-0"><i class="fas fa-boxes-stacked"></i></div>
                        <div><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Stok</p><p class="text-lg font-bold text-teal-600"><?php echo number_format($total_stok, 0, ',', '.'); ?></p></div>
                     </div>
                     <div class="card p-4 flex items-center gap-3 col-span-2 sm:col-span-1 lg:col-span-2">
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0"><i class="fas fa-coins"></i></div>
                        <div class="min-w-0"><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Nilai Aset</p><p class="text-lg font-bold text-indigo-600 truncate">Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?></p></div>
                     </div>
                     <div class="card p-4 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center shrink-0"><i class="fas fa-arrow-trend-up"></i></div>
                        <div class="min-w-0"><p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Est. Margin</p><p class="text-lg font-bold text-purple-600 truncate">Rp <?php echo number_format($total_margin, 0, ',', '.'); ?></p></div>
                     </div>
                </div>

                <form method="GET" action="index.php" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" id="search-input" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Cari barang, kategori, atau keterangan..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 text-sm outline-none transition-all">
                    </div>
                    <div class="flex w-full sm:w-auto gap-1">
                        <select name="filter_suplier" onchange="this.form.submit()" class="flex-1 sm:w-48 p-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 text-sm cursor-pointer outline-none transition-all">
                            <option value="">Semua Suplier</option>
                            <option value="null" <?php echo (isset($_GET['filter_suplier']) && $_GET['filter_suplier'] === 'null') ? 'selected' : ''; ?>>Tanpa Suplier</option>
                            <?php foreach ($list_suplier as $sup): ?>
                                <option value="<?php echo $sup['id_suplier']; ?>" <?php echo (isset($_GET['filter_suplier']) && $_GET['filter_suplier'] == $sup['id_suplier']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sup['nama_suplier']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                </div>

            <section class="card overflow-hidden">
                <div class="overflow-x-auto" style="max-height: 65vh;">
                    <table class="min-w-full divide-y divide-gray-100 compact-table sticky-header">
                        <thead>
                            <tr>
                                <th class="uppercase tracking-wider text-left w-1/4">Nama Barang</th>
                                <th class="uppercase tracking-wider text-left">Kategori</th>
                                <th class="uppercase tracking-wider text-left">Suplier</th>
                                <th class="uppercase tracking-wider text-right">Stok</th>
                                <th class="uppercase tracking-wider text-center">Satuan</th>
                                <th class="uppercase tracking-wider text-right">Harga Beli</th>
                                <th class="uppercase tracking-wider text-right">Harga Jual</th>
                                <th class="uppercase tracking-wider text-right">Margin</th>
                                <th class="uppercase tracking-wider text-left">Tipe</th>
                                <th class="uppercase tracking-wider text-right w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="product-table-body" class="bg-white divide-y divide-gray-100">
                            <?php foreach ($inventory_data as $product): 
                                $status = getStockStatus($product['stok']); 
                                
                                // LOGIKA BARU: Cek kelengkapan data harga
                                $harga_jual = (float)$product['harga'];
                                $harga_beli = (float)($product['harga_beli'] ?? 0);
                                // Tampilkan margin hanya jika harga jual DAN harga beli > 0
                                $show_margin = ($harga_jual > 0 && $harga_beli > 0);
                                $margin = $harga_jual - $harga_beli;
                            ?>
                            <tr class="<?= $status['row_class'] ?> product-row group hover:bg-gray-50 transition-colors" data-nama="<?php echo htmlspecialchars(strtolower($product['nama'])); ?>">
                                <td class="px-3 py-3">
                                    <div class="font-bold text-gray-800 text-base"><?php echo htmlspecialchars($product['nama']); ?></div>
                                    <?php if(!empty($product['keterangan'])): ?>
                                        <div class="text-sm text-gray-600 italic mt-1 flex items-center">
                                            <span class="font-bold text-blue-600 not-italic mr-1.5">Ket:</span>
                                            <?php echo htmlspecialchars($product['keterangan']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100"><?php echo htmlspecialchars($product['kategori']); ?></span></td>
                                <td class="text-sm font-bold text-gray-700"><?php echo !empty($product['nama_suplier']) ? htmlspecialchars($product['nama_suplier']) : '<span class="text-gray-400 font-normal italic text-xs">Tanpa Suplier</span>'; ?></td>
                                <td class="text-right font-mono font-bold text-base <?= $status['text_class'] ?>">
                                    <?php if ($product['tipe_pengadaan'] === 'PO'): ?>
                                        <span class="text-gray-400 font-normal">PO</span>
                                    <?php else: ?>
                                        <?php echo $product['stok']; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center text-xs text-gray-500 uppercase font-semibold"><?php echo htmlspecialchars($product['satuan']); ?></td>
                                <td class="text-right text-sm text-gray-600 whitespace-nowrap"><?php echo $product['harga_beli'] ? number_format($product['harga_beli'],0,',','.') : '<span class="text-gray-300">-</span>'; ?></td>
                                <td class="text-right text-sm font-medium text-gray-800 whitespace-nowrap"><?php echo number_format($product['harga'],0,',','.'); ?></td>
                                
                                <td class="text-right text-sm whitespace-nowrap">
                                    <?php if ($show_margin): ?>
                                        <span class="font-bold <?php echo $margin >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo number_format($margin, 0, ',', '.'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-tighter <?php echo ($product['tipe_pengadaan'] == 'PO') ? 'bg-orange-100 text-orange-700 border border-orange-200' : 'bg-green-100 text-green-700 border border-green-200'; ?>">
                                        <i class="fas <?php echo ($product['tipe_pengadaan'] == 'PO') ? 'fa-clock-rotate-left mr-1' : 'fa-box-open mr-1'; ?>"></i>
                                        <?php echo $product['tipe_pengadaan']; ?>
                                    </span>
                                </td>

                                <td class="text-right whitespace-nowrap">
                                    <div class="flex justify-end items-center gap-2 opacity-80 group-hover:opacity-100 transition-opacity">
                                        <button class="pin-button p-1.5 rounded-md border <?php echo $product['is_pinned'] ? 'text-yellow-600 bg-yellow-100 border-yellow-200' : 'text-gray-400 hover:text-yellow-500 hover:bg-gray-100 border-transparent'; ?> transition-all" title="<?php echo $product['is_pinned'] ? 'Lepas Pin' : 'Pin ke Atas'; ?>" data-id="<?php echo $product['id_barang']; ?>" data-pinned="<?php echo $product['is_pinned']; ?>" onclick="event.preventDefault(); togglePin(this);"><i class="fas fa-thumbtack fa-sm"></i></button>
                                        <a href="index.php?action=edit&id=<?php echo $product['id_barang']; ?>" class="p-1.5 rounded-md text-blue-600 hover:bg-blue-50 hover:text-blue-800 transition-all" title="Edit"><i class="fas fa-edit fa-sm"></i></a>
                                        <a href="index.php?action=hapus&id=<?php echo $product['id_barang']; ?>" onclick="return confirm('Hapus barang ini?')" class="p-1.5 rounded-md text-red-500 hover:bg-red-50 hover:text-red-700 transition-all" title="Hapus"><i class="fas fa-trash fa-sm"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <div id="product-modal" class="modal-overlay <?php echo $show_modal ? 'flex' : 'hidden'; ?>">
        <div class="modal-content bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 overflow-hidden border border-slate-100 flex flex-col">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center flex-none">
                <div>
                    <h2 id="modal-title" class="text-lg font-bold text-gray-800"><?php echo ($data_edit) ? 'Edit Data Barang' : 'Tambah Barang Baru'; ?></h2>
                    <p class="text-xs text-gray-500 mt-0.5">Lengkapi form di bawah ini.</p>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors p-1"><i class="fas fa-times text-xl"></i></button>
            </div>
            
            <form id="product-form" method="POST" action="index.php" class="p-6 overflow-y-auto flex-1">
                <?php if ($data_edit): ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_barang" value="<?php echo $data_edit['id_barang']; ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="tambah">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-12"><h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 border-b pb-1">1. Informasi Produk</h3></div>
                    <div class="md:col-span-8"><label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Barang <span class="text-red-500">*</span></label><input type="text" name="nama" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Contoh: Beras Premium" value="<?php echo htmlspecialchars($data_edit['nama'] ?? ''); ?>"></div>
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipe Pengadaan <span class="text-red-500">*</span></label>
                        <select name="tipe_pengadaan" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="Stok" <?php echo (isset($data_edit['tipe_pengadaan']) && $data_edit['tipe_pengadaan'] == 'Stok') ? 'selected' : ''; ?>>STOK (Tersedia)</option>
                            <option value="PO" <?php echo (isset($data_edit['tipe_pengadaan']) && $data_edit['tipe_pengadaan'] == 'PO') ? 'selected' : ''; ?>>PO (Pre-Order)</option>
                        </select>
                    </div>
                    <div class="md:col-span-12"><label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori <span class="text-red-500">*</span></label><input type="text" name="kategori" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Sayur/Daging..." value="<?php echo htmlspecialchars($data_edit['kategori'] ?? ''); ?>"></div>
                    <div class="md:col-span-12"><label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan Tambahan <span class="text-gray-400 font-normal text-xs">(Opsional)</span></label><input type="text" name="keterangan" class="w-full border border-gray-300 p-2.5 rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Detail spesifik, misal: Isi 8 butir, Kualitas Super" value="<?php echo htmlspecialchars($data_edit['keterangan'] ?? ''); ?>"></div>
                    


                    <div class="md:col-span-12 mt-2"><h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 border-b pb-1">2. Harga & Stok</h3></div>
                    <div class="md:col-span-4"><label class="block text-sm font-medium text-gray-700 mb-1.5">Harga Beli (Modal)</label><div class="relative"><span class="absolute left-3 top-2.5 text-gray-400 font-semibold text-sm">Rp</span><input type="number" step="any" id="harga_beli" name="harga_beli" class="w-full pl-9 border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0" value="<?php echo $data_edit['harga_beli'] ?? '0'; ?>"></div></div>
                    <div class="md:col-span-4"><label class="block text-sm font-medium text-gray-700 mb-1.5">Harga Jual <span class="text-red-500">*</span></label><div class="relative"><span class="absolute left-3 top-2.5 text-gray-400 font-semibold text-sm">Rp</span><input type="number" step="any" id="harga" name="harga" required class="w-full pl-9 border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="0" value="<?php echo $data_edit['harga'] ?? '0'; ?>"></div></div>
                    <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Stok</label><input type="number" step="any" name="stok" min="0" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-center font-semibold" value="<?php echo $data_edit['stok'] ?? '0'; ?>"></div>
                    <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">Satuan</label><input type="text" name="satuan" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-center uppercase text-sm" placeholder="Pcs/Kg" value="<?php echo htmlspecialchars($data_edit['satuan'] ?? ''); ?>"></div>
                </div>

                <div class="mt-8 flex justify-end gap-3 border-t border-gray-100 pt-5">
                    <button type="button" onclick="closeModal()" class="btn bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm px-4">Batal</button>
                    <button type="submit" class="btn btn-primary px-6 shadow-lg">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
    
    <form id="form-hapus-suplier" method="POST" action="index.php" style="display:none;">
        <input type="hidden" name="hapus_suplier_id" id="input_hapus_suplier_id">
    </form>

    <script>
        function toggleInputSuplier(selectObject) {
            const wrapper = document.getElementById('wrapper-input-suplier');
            const inputManual = document.getElementById('nama_suplier_input');
            if (selectObject.value === 'dengan') { wrapper.classList.remove('hidden'); inputManual.required = true; setTimeout(() => inputManual.focus(), 100); } 
            else { wrapper.classList.add('hidden'); inputManual.required = false; inputManual.value = ''; }
        }
        
        // FUNGSI HAPUS SUPLIER
        function hapusSuplier(id) {
            if(confirm('PERINGATAN: Anda yakin ingin menghapus suplier ini?\n\nSemua barang yang terhubung ke suplier ini akan statusnya berubah menjadi "Tanpa Suplier".\n\nTindakan ini tidak bisa dibatalkan.')) {
                document.getElementById('input_hapus_suplier_id').value = id;
                document.getElementById('form-hapus-suplier').submit();
            }
        }

        const modal = document.getElementById('product-modal');
        const addBtn = document.getElementById('add-material-btn');
        const dataEdit = <?php echo json_encode($data_edit); ?>;

        if(addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                document.getElementById('product-form').reset();
                document.getElementById('opsi_suplier').value = 'tanpa';
                toggleInputSuplier(document.getElementById('opsi_suplier'));
                document.querySelector('input[name="action"]').value = 'tambah';
                document.getElementById('modal-title').innerText = 'Tambah Barang Baru';
                modal.classList.remove('hidden'); modal.classList.add('flex');
            });
        }
        if (dataEdit) {
            const suplierInput = document.getElementById('nama_suplier_input');
            const opsiSelect = document.getElementById('opsi_suplier');
            if (dataEdit.nama_suplier) { opsiSelect.value = 'dengan'; suplierInput.value = dataEdit.nama_suplier; } 
            else { opsiSelect.value = 'tanpa'; }
            toggleInputSuplier(opsiSelect);
            modal.classList.remove('hidden'); modal.classList.add('flex');
        }
        function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); window.location.href = 'index.php'; }
        document.getElementById('search-input').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.product-row').forEach(row => {
                const nama = row.getAttribute('data-nama');
                row.style.display = nama.includes(term) ? '' : 'none';
            });
        });
        function togglePin(buttonElement) {
            const itemId = buttonElement.getAttribute('data-id');
            let isCurrentlyPinned = buttonElement.getAttribute('data-pinned') === '1';
            const originalIcon = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const formData = new FormData();
            formData.append('id_barang', itemId);
            formData.append('current_pin_state', isCurrentlyPinned ? '1' : '0');
            fetch('aksi_pin.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                if (data.success) {
                    const newState = data.new_pin_state;
                    buttonElement.setAttribute('data-pinned', newState);
                    if (newState === 1) { buttonElement.title = 'Lepas Pin'; buttonElement.className = 'pin-button p-1.5 rounded-md border text-yellow-600 bg-yellow-100 border-yellow-200 shadow-sm transition-all'; } 
                    else { buttonElement.title = 'Pin ke Atas'; buttonElement.className = 'pin-button p-1.5 rounded-md border text-gray-400 hover:text-yellow-500 hover:bg-gray-100 border-transparent transition-all'; }
                } else { alert('Gagal: ' + data.message); }
                buttonElement.innerHTML = originalIcon;
            }).catch(error => { console.error('Error:', error); buttonElement.innerHTML = originalIcon; });
        }
    </script>
</body>
</html>
