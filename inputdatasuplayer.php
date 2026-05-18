<?php
session_start();
include 'koneksi.php'; // Pastikan koneksi database sudah benar

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    // Redirect ke halaman login jika belum login
    header("Location: login.php");
    exit();
}

// =========================================================================
// === LOGIKA 1: HAPUS SUPLIER (BARU DITAMBAHKAN) ===
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'hapus_suplier') {
    $id_hapus = $_POST['id_suplier'];

    // LANGKAH 1: Putus dulu hubungan barang di gudang (Set jadi NULL)
    // Supaya barangnya tidak ikut terhapus atau error
    $stmt1 = $koneksi->prepare("UPDATE gudang SET id_suplier = NULL WHERE id_suplier = ?");
    $stmt1->bind_param("i", $id_hapus);
    $stmt1->execute();
    $stmt1->close();

    // LANGKAH 2: Baru hapus data supliernya
    $stmt2 = $koneksi->prepare("DELETE FROM data_suplier WHERE id_suplier = ?");
    $stmt2->bind_param("i", $id_hapus);

    if ($stmt2->execute()) {
        $_SESSION['pesan'] = "Suplier berhasil dihapus. Mapping barang telah direset.";
        $_SESSION['tipe'] = "success";
    } else {
        $_SESSION['pesan'] = "Gagal menghapus: " . $koneksi->error;
        $_SESSION['tipe'] = "error";
    }
    header("Location: inputdatasuplayer.php?tab=rekap");
    exit;
}

// =========================================================================
// === LOGIKA 2: UPDATE LINK SUPLIER KE GUDANG (SINGLE) ===
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_link_suplier') {
    $id_barang = $_POST['id_barang'];
    $id_suplier = $_POST['id_suplier'];

    if ($id_suplier == '0') $id_suplier = NULL;

    $stmt = $koneksi->prepare("UPDATE gudang SET id_suplier = ? WHERE id_barang = ?");
    $stmt->bind_param("ii", $id_suplier, $id_barang);
    
    if ($stmt->execute()) {
        $_SESSION['pesan'] = "Barang berhasil dihubungkan ke suplier!";
        $_SESSION['tipe'] = "success";
    } else {
        $_SESSION['pesan'] = "Gagal update: " . $koneksi->error;
        $_SESSION['tipe'] = "error";
    }
    header("Location: inputdatasuplayer.php?tab=mapping");
    exit;
}

// =========================================================================
// === LOGIKA 3: UPDATE MULTIPLE LINKS (AJAX) ===
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_multiple_links') {
    
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $successCount = 0;
    $errorCount = 0;
    
    if (isset($_POST['changes']) && is_array($_POST['changes'])) {
        foreach ($_POST['changes'] as $index => $change) {
            if (isset($change['id_barang']) && isset($change['id_suplier'])) {
                $id_barang = intval($change['id_barang']);
                $id_suplier = $change['id_suplier'] == '0' ? NULL : intval($change['id_suplier']);
                
                if ($id_barang > 0) {
                    $stmt = $koneksi->prepare("UPDATE gudang SET id_suplier = ? WHERE id_barang = ?");
                    if ($stmt) {
                        $stmt->bind_param("ii", $id_suplier, $id_barang);
                        if ($stmt->execute()) {
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => "Tidak ada data perubahan."]);
        exit;
    }
    
    if ($errorCount === 0 && $successCount > 0) {
        $response = ['success' => true, 'message' => "Semua perubahan ($successCount) berhasil disimpan!"];
        $_SESSION['pesan'] = $response['message'];
        $_SESSION['tipe'] = "success";
    } else if ($successCount > 0) {
        $response = ['success' => true, 'message' => "$successCount berhasil, $errorCount gagal."];
        $_SESSION['pesan'] = $response['message'];
        $_SESSION['tipe'] = "warning";
    } else {
        $response = ['success' => false, 'message' => "Gagal menyimpan perubahan."];
        $_SESSION['pesan'] = $response['message'];
        $_SESSION['tipe'] = "error";
    }
    
    echo json_encode($response);
    exit;
}

// =========================================================================
// === LOGIKA 4: TAMBAH SUPLIER BARU ===
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'tambah_suplier_baru') {
    $nama = trim($_POST['nama_suplier']);
    $raw_hp = trim($_POST['nomer_hp']);
    $hp = preg_replace('/[^0-9+]/', '', $raw_hp); // Sanitasi nomer HP
    
    if (empty($nama)) {
        $_SESSION['pesan'] = "Nama suplier tidak boleh kosong!";
        $_SESSION['tipe'] = "error";
        header("Location: inputdatasuplayer.php?tab=mapping");
        exit;
    }
    
    $sql = "INSERT INTO data_suplier (nama_suplier, nomer_hp, nama_barang, harga, satuan, keterangan) 
            VALUES (?, ?, '-', 0, '-', 'Ditambahkan via Mapping')";
            
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("ss", $nama, $hp);
    
    if ($stmt->execute()) {
        $_SESSION['pesan'] = "Suplier baru berhasil ditambahkan!";
        $_SESSION['tipe'] = "success";
    } else {
        $_SESSION['pesan'] = "Gagal tambah suplier: " . $koneksi->error;
        $_SESSION['tipe'] = "error";
    }
    header("Location: inputdatasuplayer.php?tab=mapping");
    exit;
}

// =========================================================================
// === LOGIKA 5: KONFIRMASI PEMBAYARAN KE SUPLIER ===
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'bayar_hutang') {
    $id_ref = $_POST['id_ref']; // Bisa id_detail atau id_pembelian
    $tipe_ref = $_POST['tipe_ref']; // 'po' atau 'stok'
    $status = (int)$_POST['status']; // 1 = Bayar, 0 = Batal Bayar
    $tgl = ($status === 1) ? date('Y-m-d H:i:s') : NULL;

    if ($tipe_ref === 'po') {
        $stmt = $koneksi->prepare("UPDATE detail_pesanan SET is_paid_to_suplier = ?, tgl_bayar_suplier = ? WHERE id_detail = ?");
    } else {
        $stmt = $koneksi->prepare("UPDATE pembelian_suplier SET is_paid = ?, tgl_bayar = ? WHERE id_pembelian = ?");
    }
    
    $stmt->bind_param("isi", $status, $tgl, $id_ref);
    
    if ($stmt->execute()) {
        $_SESSION['pesan'] = ($status === 1) ? "Pembayaran berhasil dikonfirmasi!" : "Status pembayaran dibatalkan.";
        $_SESSION['tipe'] = "success";
    } else {
        $_SESSION['pesan'] = "Gagal update: " . $koneksi->error;
        $_SESSION['tipe'] = "error";
    }
    header("Location: inputdatasuplayer.php?tab=hutang&id_suplier=" . ($_POST['id_suplier_ref'] ?? ''));
    exit;
}

// === LOGIKA 6: INPUT PEMBELIAN STOK (NOTA DATANG) ===
if (isset($_POST['action']) && $_POST['action'] === 'tambah_pembelian') {
    $id_sup = (int)$_POST['id_suplier'];
    $id_brg = (int)$_POST['id_barang'];
    
    // Validasi Tipe (Harus Stok)
    $check_q = $koneksi->query("SELECT tipe_pengadaan FROM gudang WHERE id_barang = $id_brg");
    $check_data = $check_q->fetch_assoc();
    if ($check_data['tipe_pengadaan'] !== 'Stok') {
        $_SESSION['pesan'] = "Gagal: Barang PO tidak bisa diinput via nota manual.";
        $_SESSION['tipe'] = "error";
        header("Location: inputdatasuplayer.php?tab=hutang&id_suplier=$id_sup");
        exit;
    }

    // Bersihkan format angka (Handle koma desimal dari input tipe number/text)
    $raw_jumlah = str_replace(',', '.', $_POST['jumlah']);
    $jumlah = (float)$raw_jumlah;

    $raw_harga = str_replace(',', '.', $_POST['harga_beli']);
    $harga_beli = (float)$raw_harga;
    
    $total = $jumlah * $harga_beli;
    $catatan = $_POST['catatan'] ?? '';

    $koneksi->begin_transaction();
    try {
        $stmt = $koneksi->prepare("INSERT INTO pembelian_suplier (id_suplier, id_barang, jumlah, harga_beli, total_harga, catatan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiddds", $id_sup, $id_brg, $jumlah, $harga_beli, $total, $catatan);
        $stmt->execute();

        $stmt_stok = $koneksi->prepare("UPDATE gudang SET stok = stok + ?, harga_beli = ? WHERE id_barang = ?");
        $stmt_stok->bind_param("ddi", $jumlah, $harga_beli, $id_brg);
        $stmt_stok->execute();

        $koneksi->commit();
        $_SESSION['pesan'] = "Nota pembelian berhasil dicatat!";
        $_SESSION['tipe'] = "success";
    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['pesan'] = "Gagal: " . $e->getMessage();
        $_SESSION['tipe'] = "error";
    }
    header("Location: inputdatasuplayer.php?tab=hutang&id_suplier=$id_sup");
    exit;
}

// === LOGIKA 7: HAPUS PEMBELIAN STOK ===
if (isset($_POST['action']) && $_POST['action'] === 'hapus_pembelian') {
    $id_pembelian = (int)$_POST['id_pembelian'];
    $id_sup = (int)$_POST['id_suplier_ref'];

    $koneksi->begin_transaction();
    try {
        // Ambil data dulu untuk kurangi stok
        $q = $koneksi->query("SELECT id_barang, jumlah FROM pembelian_suplier WHERE id_pembelian = $id_pembelian");
        $data = $q->fetch_assoc();
        
        if ($data) {
            $id_brg = $data['id_barang'];
            $qty = $data['jumlah'];
            $koneksi->query("UPDATE gudang SET stok = GREATEST(0, stok - $qty) WHERE id_barang = $id_brg");
            $koneksi->query("DELETE FROM pembelian_suplier WHERE id_pembelian = $id_pembelian");
        }

        $koneksi->commit();
        $_SESSION['pesan'] = "Transaksi berhasil dihapus & stok dikembalikan.";
        $_SESSION['tipe'] = "success";
    } catch (Exception $e) {
        $koneksi->rollback();
        $_SESSION['pesan'] = "Gagal hapus: " . $e->getMessage();
    }
    header("Location: inputdatasuplayer.php?tab=hutang&id_suplier=$id_sup");
    exit;
}

// Ambil Tab Aktif
$active_tab = $_GET['tab'] ?? 'rekap';

// =========================================================================
// === QUERY DATA ===
// =========================================================================

// 1. QUERY TAB REKAP
$sql_rekap = "SELECT 
                ds.id_suplier, ds.nama_suplier, ds.nomer_hp,
                g.id_barang, g.nama as nama_barang_gudang, g.stok, g.satuan, g.harga, g.harga_beli, g.tipe_pengadaan
              FROM data_suplier ds
              LEFT JOIN gudang g ON g.id_suplier = ds.id_suplier
              ORDER BY ds.nama_suplier ASC, g.nama ASC";

$result_rekap = $koneksi->query($sql_rekap);
$data_grouped = [];

if ($result_rekap) {
    while($row = $result_rekap->fetch_assoc()) {
        $nama_key = $row['nama_suplier'];
        $data_grouped[$nama_key]['info'] = [
            'hp' => $row['nomer_hp'], 
            'id' => $row['id_suplier']
        ];
        $data_grouped[$nama_key]['barang'][] = $row;
    }
}

// 2. QUERY LIST SUPLIER
$sql_list_suplier = "SELECT id_suplier, nama_suplier FROM data_suplier GROUP BY nama_suplier ORDER BY nama_suplier ASC";
$semua_suplier = $koneksi->query($sql_list_suplier);
$list_suplier = [];
if ($semua_suplier) {
    while($s = $semua_suplier->fetch_assoc()) {
        $list_suplier[] = $s;
    }
}

// 3. QUERY TAB MAPPING
$semua_barang_result = $koneksi->query("SELECT * FROM gudang ORDER BY nama ASC");
$semua_barang_count = $semua_barang_result ? $semua_barang_result->num_rows : 0;

// 4. QUERY TAB HUTANG (REKAP HUTANG PER SUPLIER) - Gabungan PO & Stok
$data_hutang = [];
$sql_hutang = "SELECT 
                ds.id_suplier, ds.nama_suplier, ds.nomer_hp,
                (
                    COALESCE((SELECT SUM(dp.jumlah * dp.harga_beli_saat_itu) 
                     FROM detail_pesanan dp 
                     JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
                     JOIN gudang g2 ON dp.id_barang = g2.id_barang
                     WHERE g2.id_suplier = ds.id_suplier AND g2.tipe_pengadaan = 'PO' AND p.status_pembayaran = 'Lunas' AND dp.is_paid_to_suplier = 0), 0)
                    + 
                    COALESCE((SELECT SUM(total_harga) FROM pembelian_suplier WHERE id_suplier = ds.id_suplier AND is_paid = 0), 0)
                ) as total_hutang,
                (
                    COALESCE((SELECT SUM(dp.jumlah * dp.harga_beli_saat_itu) 
                     FROM detail_pesanan dp 
                     JOIN gudang g2 ON dp.id_barang = g2.id_barang
                     WHERE g2.id_suplier = ds.id_suplier AND dp.is_paid_to_suplier = 1), 0)
                    + 
                    COALESCE((SELECT SUM(total_harga) FROM pembelian_suplier WHERE id_suplier = ds.id_suplier AND is_paid = 1), 0)
                ) as total_terbayar,
                (
                    (SELECT COUNT(*) FROM detail_pesanan dp JOIN pesanan p ON dp.id_pesanan = p.id_pesanan JOIN gudang g2 ON dp.id_barang = g2.id_barang WHERE g2.id_suplier = ds.id_suplier AND g2.tipe_pengadaan = 'PO' AND p.status_pembayaran = 'Lunas' AND dp.is_paid_to_suplier = 0)
                    + 
                    (SELECT COUNT(*) FROM pembelian_suplier WHERE id_suplier = ds.id_suplier AND is_paid = 0)
                ) as count_pending
              FROM data_suplier ds
              ORDER BY total_hutang DESC, ds.nama_suplier ASC";
$res_hutang = $koneksi->query($sql_hutang);
if ($res_hutang) {
    while($h = $res_hutang->fetch_assoc()) { $data_hutang[] = $h; }
}

// 5. QUERY DETAIL HUTANG (Jika ada suplier yang dipilih)
$detail_hutang = [];
$selected_suplier_name = "";
if ($active_tab === 'hutang' && isset($_GET['id_suplier'])) {
    $id_s = (int)$_GET['id_suplier'];
    $stmt_s = $koneksi->prepare("SELECT nama_suplier FROM data_suplier WHERE id_suplier = ?");
    $stmt_s->bind_param("i", $id_s);
    $stmt_s->execute();
    $stmt_s->bind_result($selected_suplier_name);
    $stmt_s->fetch();
    $stmt_s->close();

    $sql_det = "(SELECT 
                    dp.id_detail as id_ref, p.no_invoice as dokumen, p.tgl_pesan as tgl,
                    g.nama as nama_barang, dp.jumlah, g.satuan, dp.harga_beli_saat_itu as harga,
                    (dp.jumlah * dp.harga_beli_saat_itu) as subtotal,
                    dp.is_paid_to_suplier as is_paid, dp.tgl_bayar_suplier as tgl_bayar,
                    'po' as tipe
                FROM detail_pesanan dp
                JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
                JOIN gudang g ON dp.id_barang = g.id_barang
                WHERE g.id_suplier = ? AND g.tipe_pengadaan = 'PO' AND p.status_pembayaran = 'Lunas')
                UNION ALL
                (SELECT 
                    ps.id_pembelian as id_ref, 'NOTA PEMBELIAN' as dokumen, ps.tgl_pembelian as tgl,
                    g.nama as nama_barang, ps.jumlah, g.satuan, ps.harga_beli as harga,
                    ps.total_harga as subtotal,
                    ps.is_paid, ps.tgl_bayar,
                    'stok' as tipe
                FROM pembelian_suplier ps
                JOIN gudang g ON ps.id_barang = g.id_barang
                WHERE ps.id_suplier = ?)
                ORDER BY is_paid ASC, tgl DESC";
    $stmt_det = $koneksi->prepare($sql_det);
    $stmt_det->bind_param("ii", $id_s, $id_s);
    $stmt_det->execute();
    $res_det = $stmt_det->get_result();
    
    $detail_po = [];
    $detail_stok = [];
    
    while($rd = $res_det->fetch_assoc()) { 
        if ($rd['tipe'] === 'po') $detail_po[] = $rd;
        else $detail_stok[] = $rd;
    }
    $stmt_det->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Suplier & Gudang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); font-family: ui-sans-serif, system-ui, sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
        .form-changed { border-left: 4px solid #f59e0b; background-color: #fffbeb; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
        .btn-pulse { animation: pulse 2s infinite; }
        .select-changed { border-color: #f59e0b !important; background-color: #fffbeb !important; }
    </style>
</head>
<body class="flex min-h-screen text-gray-800">

    <?php if (file_exists("sidebar.php")) include "sidebar.php"; ?>

    <main class="flex-1 p-4 md:p-8 <?php echo file_exists("sidebar.php") ? 'md:ml-64' : ''; ?> fade-in">
        
        <div class="mb-8 flex justify-between items-end border-b pb-4 border-gray-200">
            <div>
                <h1 class="text-3xl font-bold text-blue-900">Data Suplier & Stok</h1>
                <p class="text-gray-600 mt-1">Monitoring stok gudang berdasarkan suplier penyedia</p>
            </div>
            <div class="text-sm text-right hidden md:block">
                <div class="text-gray-500">Total Barang Terdata</div>
                <div class="font-bold text-xl text-blue-600"><?php echo $semua_barang_count; ?> Item</div>
            </div>
        </div>

        <div class="flex space-x-2 mb-6 bg-blue-100/50 p-1 rounded-lg w-fit">
            <a href="?tab=rekap" class="flex items-center px-6 py-2.5 rounded-md text-sm font-bold transition-all duration-200 <?php echo $active_tab == 'rekap' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-blue-500 hover:bg-white/50'; ?>">
                <i class="fas fa-clipboard-list mr-2"></i> Data Suplier (View)
            </a>
            <a href="?tab=mapping" class="flex items-center px-6 py-2.5 rounded-md text-sm font-bold transition-all duration-200 <?php echo $active_tab == 'mapping' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-blue-500 hover:bg-white/50'; ?>">
                <i class="fas fa-link mr-2"></i> Atur / Mapping Suplier
            </a>
            <a href="?tab=hutang" class="flex items-center px-6 py-2.5 rounded-md text-sm font-bold transition-all duration-200 <?php echo $active_tab == 'hutang' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-500 hover:text-blue-500 hover:bg-white/50'; ?>">
                <i class="fas fa-hand-holding-usd mr-2"></i> Manajemen Hutang
            </a>
        </div>

        <?php if(isset($_SESSION['pesan'])): ?>
        <div class="flex items-center p-4 mb-6 rounded-lg shadow-sm <?php echo $_SESSION['tipe'] == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : ($_SESSION['tipe'] == 'warning' ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : 'bg-red-50 text-red-800 border border-red-200'); ?>">
            <i class="<?php echo $_SESSION['tipe'] == 'success' ? 'fas fa-check-circle' : ($_SESSION['tipe'] == 'warning' ? 'fas fa-exclamation-triangle' : 'fas fa-exclamation-circle'); ?> text-xl mr-3"></i>
            <span class="font-medium"><?php echo $_SESSION['pesan']; ?></span>
            <?php unset($_SESSION['pesan']); unset($_SESSION['tipe']); ?>
        </div>
        <?php endif; ?>

        <?php if($active_tab == 'rekap'): ?>
        <div class="space-y-8">
            <?php if(empty($data_grouped)): ?>
                <div class="bg-white p-12 rounded-2xl shadow-sm text-center border border-gray-100">
                    <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder-open text-3xl text-blue-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">Belum ada barang terhubung</h3>
                    <p class="text-gray-500 mt-2 max-w-md mx-auto">Data stok gudang belum ada yang dihubungkan ke nama suplier.</p>
                    <a href="?tab=mapping" class="inline-flex items-center mt-6 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition shadow-lg shadow-blue-200">
                        Hubungkan Barang Sekarang <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            <?php else: ?>
                
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <?php foreach($data_grouped as $nama_suplier => $data): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-300 flex flex-col h-full">
                        <div class="p-5 bg-gradient-to-r from-slate-50 to-white border-b flex justify-between items-start">
                            <div>
                                <h2 class="text-lg font-bold text-gray-800 flex items-center">
                                    <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-sm mr-3">
                                        <i class="fas fa-store"></i>
                                    </span>
                                    <?php echo htmlspecialchars($nama_suplier); ?>
                                </h2>
                                <div class="mt-1 ml-11 flex items-center text-sm text-gray-500">
                                    <i class="fas fa-phone-alt text-xs mr-2"></i> 
                                    <?php echo htmlspecialchars($data['info']['hp'] ?? '-'); ?>
                                </div>
                            </div>
                            
                            <div class="flex flex-col items-end gap-2">
                                <span class="bg-blue-50 text-blue-700 py-1 px-3 rounded-full text-xs font-bold border border-blue-100">
                                    <?php echo count($data['barang']); ?> Produk
                                </span>
                                 <?php 
                                    $hasStokItem = false;
                                    foreach($data['barang'] as $check) {
                                        if($check['tipe_pengadaan'] === 'Stok') { $hasStokItem = true; break; }
                                    }
                                    if($hasStokItem): 
                                 ?>
                                 <button type="button" onclick="showModalPembelian(<?php echo $data['info']['id']; ?>, '<?php echo addslashes($nama_suplier); ?>')" class="text-xs bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white px-3 py-1.5 rounded-lg font-bold border border-emerald-100 transition-all flex items-center gap-1">
                                    <i class="fas fa-plus-circle"></i> Input Nota / Stok
                                </button>
                                <?php endif; ?>
                                <form action="" method="POST" onsubmit="return confirm('Yakin ingin menghapus suplier <?php echo htmlspecialchars($nama_suplier); ?>? \n\nBarang dari suplier ini tidak akan terhapus, namun statusnya akan kembali menjadi Belum Ada Suplier.');">
                                    <input type="hidden" name="action" value="hapus_suplier">
                                    <input type="hidden" name="id_suplier" value="<?php echo $data['info']['id']; ?>">
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-600 transition-colors flex items-center gap-1">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </button>
                                </form>
                            </div>
                            
                        </div>
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase font-semibold">
                                    <tr>
                                        <th class="px-6 py-3 border-b">Produk</th>
                                        <th class="px-6 py-3 border-b text-center">Stok</th>
                                        <th class="px-6 py-3 border-b text-right">Harga Beli</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 text-sm">
                                    <?php foreach($data['barang'] as $brg): ?>
                                    <tr class="hover:bg-blue-50/30 transition-colors">
                                        <td class="px-6 py-3 font-medium text-gray-700">
                                            <?php echo htmlspecialchars($brg['nama_barang_gudang']); ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <?php if ($brg['tipe_pengadaan'] === 'PO'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">PO</span>
                                            <?php elseif($brg['stok'] <= 0): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Habis</span>
                                            <?php elseif($brg['stok'] < 10): ?>
                                                <span class="text-orange-600 font-bold"><?php echo $brg['stok']; ?> <?php echo htmlspecialchars($brg['satuan']); ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-700"><?php echo $brg['stok']; ?> <?php echo htmlspecialchars($brg['satuan']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-right text-gray-600 font-mono">
                                            Rp<?php echo number_format($brg['harga'], 0, ',', '.'); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($active_tab == 'mapping'): ?>
        <div class="flex flex-col lg:flex-row gap-6">
            
            <div class="w-full lg:w-1/3">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 sticky top-4">
                    <div class="flex items-center gap-3 mb-5 border-b pb-4">
                        <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800">Tambah Suplier</h3>
                            <p class="text-xs text-gray-500">Input nama suplier baru</p>
                        </div>
                    </div>
                    
                    <form action="" method="POST" class="space-y-4" id="formTambahSuplier">
                        <input type="hidden" name="action" value="tambah_suplier_baru">
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Nama Suplier *</label>
                            <input type="text" name="nama_suplier" placeholder="Contoh: Toko Beras Makmur" required 
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">No. HP / WhatsApp</label>
                            <input type="text" name="nomer_hp" placeholder="08xxxx" 
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition text-sm">
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg font-bold shadow-lg shadow-green-200 transition transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i> Simpan Suplier
                        </button>
                    </form>
                </div>
            </div>

            <div class="w-full lg:w-2/3 flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 border-b bg-gray-50">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="font-bold text-gray-800">Daftar Barang Gudang</h3>
                            <p class="text-sm text-gray-500">Hubungkan barang gudang dengan suplier</p>
                        </div>
                         <div class="flex gap-3 text-xs">
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-red-100 border border-red-300 mr-1"></span> Belum Ada</div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-white border border-gray-300 mr-1"></span> Sudah Ada</div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-yellow-100 border border-yellow-300 mr-1"></span> Perubahan</div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row gap-3">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i class="fas fa-search text-gray-400"></i>
                            </span>
                            <input type="text" id="searchBarangInput" placeholder="Cari nama barang..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm transition shadow-sm">
                        </div>
                        <button id="btnSimpanSemua" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition flex items-center gap-2">
                            <i class="fas fa-save"></i> Simpan Semua
                        </button>
                    </div>
                </div>
                
                <div class="flex-1 overflow-y-auto" style="max-height: 70vh;">
                    <table class="w-full text-left border-collapse" id="tabelMapping">
                        <thead class="bg-white sticky top-0 z-10 shadow-sm text-xs uppercase text-gray-500 font-bold">
                            <tr>
                                <th class="p-4 w-1/2 bg-gray-50">Nama Barang (Stok)</th>
                                <th class="p-4 w-1/2 bg-gray-50">Pilih Suplier</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="tbodyMapping">
                            <?php 
                            if ($semua_barang_result && $semua_barang_result->num_rows > 0):
                                $semua_barang_result->data_seek(0);
                                while($b = $semua_barang_result->fetch_assoc()): 
                            ?>
                            <tr class="group hover:bg-blue-50/50 transition barang-row <?php echo is_null($b['id_suplier']) ? 'bg-red-50/30' : ''; ?>" data-id="<?php echo $b['id_barang']; ?>">
                                <td class="p-4 align-middle">
                                    <div class="font-semibold text-gray-800 nama-barang"><?php echo htmlspecialchars($b['nama']); ?></div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <?php if ($b['tipe_pengadaan'] === 'PO'): ?>
                                            <span class="text-[10px] font-black bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded uppercase">PO</span>
                                        <?php else: ?>
                                            <div class="text-xs text-gray-400">
                                                Stok: <span class="text-gray-600 font-medium"><?php echo $b['stok']; ?> <?php echo htmlspecialchars($b['satuan']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="p-4 align-middle">
                                    <div class="relative">
                                        <i class="fas fa-store absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                        <select name="id_suplier" 
                                            class="w-full pl-9 pr-8 py-2 text-sm border rounded-lg appearance-none cursor-pointer focus:ring-2 focus:ring-blue-500 focus:outline-none transition supplier-select
                                            <?php echo is_null($b['id_suplier']) ? 'border-red-300 bg-white text-red-500 font-medium' : 'border-gray-300 bg-gray-50 text-gray-700'; ?>"
                                            data-original-value="<?php echo $b['id_suplier'] ?? '0'; ?>"
                                            data-barang-id="<?php echo $b['id_barang']; ?>">
                                            <option value="0" class="text-gray-400">-- Pilih Suplier --</option>
                                            <?php foreach($list_suplier as $s): ?>
                                                <option value="<?php echo $s['id_suplier']; ?>" <?php echo ($b['id_suplier'] == $s['id_suplier']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($s['nama_suplier']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-xs text-gray-400 pointer-events-none"></i>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="2" class="p-8 text-center text-gray-500">
                                    <i class="fas fa-box-open text-3xl mb-2 text-gray-300"></i>
                                    <p>Tidak ada data barang di gudang.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div id="noResults" class="hidden p-8 text-center text-gray-500">
                        <i class="fas fa-search text-3xl mb-2 text-gray-300"></i>
                        <p>Barang tidak ditemukan.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($active_tab == 'hutang'): ?>
        <div class="flex flex-col gap-8">
            <!-- REKAP HUTANG GLOBAL -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b flex justify-between items-center bg-slate-50/50">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">Rekapitulasi Hutang ke Suplier</h3>
                        <p class="text-sm text-gray-500">Daftar saldo hutang yang belum dibayarkan ke masing-masing suplier</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <tr>
                                <th class="px-8 py-4">Nama Suplier</th>
                                <th class="px-8 py-4 text-center">Pesanan Pending</th>
                                <th class="px-8 py-4 text-right text-red-500">Total Hutang</th>
                                <th class="px-8 py-4 text-right text-emerald-500">Total Terbayar</th>
                                <th class="px-8 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php foreach($data_hutang as $h): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors <?php echo (isset($_GET['id_suplier']) && $_GET['id_suplier'] == $h['id_suplier']) ? 'bg-blue-50/50' : ''; ?>">
                                <td class="px-8 py-5">
                                    <div class="font-bold text-gray-800"><?php echo htmlspecialchars($h['nama_suplier']); ?></div>
                                    <div class="text-[10px] text-gray-400"><?php echo htmlspecialchars($h['nomer_hp'] ?? '-'); ?></div>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <?php if($h['count_pending'] > 0): ?>
                                        <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full font-bold text-[10px]">
                                            <?php echo $h['count_pending']; ?> TRANSAKSI
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-5 text-right font-black text-red-600">
                                    Rp<?php echo number_format($h['total_hutang'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-8 py-5 text-right font-bold text-emerald-600">
                                    Rp<?php echo number_format($h['total_terbayar'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-8 py-5 text-center">
                                    <a href="?tab=hutang&id_suplier=<?php echo $h['id_suplier']; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-600 hover:border-blue-500 hover:text-blue-600 transition shadow-sm">
                                        <i class="fas fa-search-dollar"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- DETAIL HUTANG PER SUPLIER -->
            <?php if(!empty($detail_po) || !empty($detail_stok)): ?>
            <div id="detail-section" class="space-y-6 fade-in">
                
                <div class="flex justify-between items-center bg-blue-600 p-4 rounded-xl text-white shadow-lg">
                    <h3 class="text-lg font-bold flex items-center gap-2">
                        <i class="fas fa-file-invoice-dollar"></i> Rincian Hutang: <?php echo htmlspecialchars($selected_suplier_name); ?>
                    </h3>
                    <a href="?tab=hutang" class="bg-white/20 hover:bg-white/40 p-2 rounded-lg transition"><i class="fas fa-times"></i></a>
                </div>

                <!-- SEKSI 1: HUTANG PO (DAPUR) -->
                <div class="bg-white rounded-xl shadow-sm border border-blue-100 overflow-hidden">
                    <div class="px-6 py-4 bg-blue-50 border-b">
                        <h4 class="font-bold text-blue-800 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <span class="bg-blue-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px]">1</span>
                            Hutang Otomatis (Pesanan Dapur / PO)
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-[10px] font-black uppercase text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">Invoice / Tgl</th>
                                    <th class="px-6 py-3">Nama Barang</th>
                                    <th class="px-6 py-3 text-center">Jumlah</th>
                                    <th class="px-6 py-3 text-right">Modal</th>
                                    <th class="px-6 py-3 text-right">Subtotal</th>
                                    <th class="px-6 py-3 text-center">Status</th>
                                    <th class="px-6 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if(empty($detail_po)): ?>
                                    <tr><td colspan="7" class="p-6 text-center text-gray-400 italic">Tidak ada transaksi PO</td></tr>
                                <?php else: foreach($detail_po as $d): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-3">
                                            <div class="font-bold text-gray-700"><?php echo $d['dokumen']; ?></div>
                                            <div class="text-[10px] text-gray-400"><?php echo date('d/m/Y', strtotime($d['tgl'])); ?></div>
                                        </td>
                                        <td class="px-6 py-3 font-medium"><?php echo htmlspecialchars($d['nama_barang']); ?></td>
                                        <td class="px-6 py-3 text-center"><?php echo (float)$d['jumlah']; ?> <?php echo $d['satuan']; ?></td>
                                        <td class="px-6 py-3 text-right text-gray-500">Rp<?php echo number_format($d['harga'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-3 text-right font-bold">Rp<?php echo number_format($d['subtotal'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-3 text-center">
                                            <?php if($d['is_paid']): ?>
                                                <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-[10px] font-black uppercase">LUNAS</span>
                                            <?php else: ?>
                                                <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-[10px] font-black uppercase">BELUM BAYAR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <form action="" method="POST">
                                                <input type="hidden" name="action" value="bayar_hutang">
                                                <input type="hidden" name="id_ref" value="<?php echo $d['id_ref']; ?>">
                                                <input type="hidden" name="tipe_ref" value="po">
                                                <input type="hidden" name="id_suplier_ref" value="<?php echo $_GET['id_suplier']; ?>">
                                                <?php if(!$d['is_paid']): ?>
                                                    <input type="hidden" name="status" value="1">
                                                    <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded-lg text-[10px] font-bold">BAYAR</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="status" value="0">
                                                    <button type="submit" class="text-gray-400 text-[10px]" onclick="return confirm('Batal?')">Batal</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SEKSI 2: HUTANG STOK (MANUAL) -->
                <div class="bg-white rounded-xl shadow-sm border border-emerald-100 overflow-hidden">
                    <div class="px-6 py-4 bg-emerald-50 border-b">
                        <h4 class="font-bold text-emerald-800 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <span class="bg-emerald-600 text-white w-6 h-6 rounded-full flex items-center justify-center text-[10px]">2</span>
                            Hutang Manual (Pembelian Stok / Kulakan)
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-[10px] font-black uppercase text-gray-400">
                                <tr>
                                    <th class="px-6 py-3">Tgl Pembelian</th>
                                    <th class="px-6 py-3">Nama Barang</th>
                                    <th class="px-6 py-3 text-center">Jumlah</th>
                                    <th class="px-6 py-3 text-right">Harga Beli</th>
                                    <th class="px-6 py-3 text-right">Subtotal</th>
                                    <th class="px-6 py-3 text-center">Status</th>
                                    <th class="px-6 py-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if(empty($detail_stok)): ?>
                                    <tr><td colspan="7" class="p-6 text-center text-gray-400 italic">Tidak ada transaksi stok manual</td></tr>
                                <?php else: foreach($detail_stok as $d): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-3 font-bold text-gray-700"><?php echo date('d/m/Y', strtotime($d['tgl'])); ?></td>
                                        <td class="px-6 py-3 font-medium"><?php echo htmlspecialchars($d['nama_barang']); ?></td>
                                        <td class="px-6 py-3 text-center"><?php echo (float)$d['jumlah']; ?> <?php echo $d['satuan']; ?></td>
                                        <td class="px-6 py-3 text-right text-gray-500">Rp<?php echo number_format($d['harga'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-3 text-right font-bold text-emerald-600">Rp<?php echo number_format($d['subtotal'], 0, ',', '.'); ?></td>
                                        <td class="px-6 py-3 text-center">
                                            <?php if($d['is_paid']): ?>
                                                <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-[10px] font-black uppercase">LUNAS</span>
                                            <?php else: ?>
                                                <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-[10px] font-black uppercase">BELUM BAYAR</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <form action="" method="POST">
                                                    <input type="hidden" name="action" value="bayar_hutang">
                                                    <input type="hidden" name="id_ref" value="<?php echo $d['id_ref']; ?>">
                                                    <input type="hidden" name="tipe_ref" value="stok">
                                                    <input type="hidden" name="id_suplier_ref" value="<?php echo $_GET['id_suplier']; ?>">
                                                    <?php if(!$d['is_paid']): ?>
                                                        <input type="hidden" name="status" value="1">
                                                        <button type="submit" class="bg-emerald-600 text-white px-3 py-1 rounded-lg text-[10px] font-bold">BAYAR</button>
                                                    <?php else: ?>
                                                        <input type="hidden" name="status" value="0">
                                                        <button type="submit" class="text-gray-400 text-[10px]" onclick="return confirm('Batal?')">Batal</button>
                                                    <?php endif; ?>
                                                </form>
                                                <?php if(!$d['is_paid']): ?>
                                                    <form action="" method="POST" onsubmit="return confirm('Hapus?')">
                                                        <input type="hidden" name="action" value="hapus_pembelian">
                                                        <input type="hidden" name="id_pembelian" value="<?php echo $d['id_ref']; ?>">
                                                        <input type="hidden" name="id_suplier_ref" value="<?php echo $_GET['id_suplier']; ?>">
                                                        <button type="submit" class="text-red-300 hover:text-red-500"><i class="fas fa-trash-alt text-xs"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            <script>document.getElementById('detail-section').scrollIntoView({ behavior: 'smooth' });</script>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal Pembelian Stok -->
    <div id="modalPembelian" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] hidden">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden transform transition-all">
            <div class="bg-emerald-600 p-6 text-white flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold">Input Nota Pembelian</h3>
                    <p class="text-emerald-100 text-xs mt-1" id="modalSupTitle">Suplier: -</p>
                </div>
                <button onclick="closeModalPembelian()" class="text-white/80 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form action="" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="action" value="tambah_pembelian">
                <input type="hidden" name="id_suplier" id="modalSupId">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Pilih Barang dari Suplier Ini *</label>
                    <select name="id_barang" id="modalBarangSelect" required class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm">
                        <option value="">-- Pilih Barang --</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Jumlah (Qty) *</label>
                        <input type="number" step="any" name="jumlah" required class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm" placeholder="Misal: 100">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Harga Beli / Satuan *</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-bold">Rp</span>
                            <input type="number" name="harga_beli" id="modalHargaBeli" required class="w-full pl-9 p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm" placeholder="50.000">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Catatan (Optional)</label>
                    <textarea name="catatan" rows="2" class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none text-sm" placeholder="Misal: Nota No. 12345..."></textarea>
                </div>
                
                <div class="pt-2">
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-4 rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all active:scale-[0.98]">
                        <i class="fas fa-save mr-2"></i> CATAT PEMBELIAN & TAMBAH STOK
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Data Barang untuk Modal (Diparsing dari PHP $data_grouped)
        const suplierData = <?php echo json_encode($data_grouped); ?>;

        function showModalPembelian(idSup, namaSup) {
            document.getElementById('modalSupId').value = idSup;
            document.getElementById('modalSupTitle').innerText = 'Suplier: ' + namaSup;
            
            // Isi dropdown barang
            const select = document.getElementById('modalBarangSelect');
            select.innerHTML = '<option value="">-- Pilih Barang --</option>';
            
            // Cari data barang milik suplier ini (HANYA TIPE STOK)
            for (let name in suplierData) {
                if (suplierData[name].info.id == idSup) {
                    suplierData[name].barang.forEach(b => {
                        if (b.tipe_pengadaan === 'Stok') {
                            const opt = document.createElement('option');
                            opt.value = b.id_barang;
                            opt.dataset.harga = b.harga_beli; // Ambil harga beli asli dari gudang
                            opt.innerText = b.nama_barang_gudang + ' (' + b.satuan + ')';
                            select.appendChild(opt);
                        }
                    });
                    break;
                }
            }
            
            // Auto update harga beli saat barang dipilih
            select.onchange = function() {
                const selected = this.options[this.selectedIndex];
                if (selected.value) {
                    document.getElementById('modalHargaBeli').value = Math.round(selected.dataset.harga);
                }
            };
            
            document.getElementById('modalPembelian').classList.remove('hidden');
        }

        function closeModalPembelian() {
            document.getElementById('modalPembelian').classList.add('hidden');
        }

        // Search logic for Mapping tab
        const searchInput = document.getElementById('searchBarangInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll('.barang-row');
                let found = false;
                
                rows.forEach(row => {
                    const name = row.querySelector('.nama-barang').innerText.toLowerCase();
                    if (name.includes(query)) {
                        row.style.display = '';
                        found = true;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                document.getElementById('noResults').classList.toggle('hidden', found);
            });
        }
    </script>

    <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center"><i class="fas fa-exclamation-triangle"></i></div>
                <h3 class="font-bold text-lg text-gray-800">Perubahan Belum Disimpan</h3>
            </div>
            <p class="text-gray-600 mb-6">Anda memiliki perubahan yang belum disimpan. Yakin ingin keluar?</p>
            <div class="flex justify-end gap-3">
                <button id="cancelLeave" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">Batal</button>
                <button id="confirmLeave" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Ya, Tinggalkan</button>
            </div>
        </div>
    </div>

    <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-6 max-w-sm w-full mx-4 flex flex-col items-center">
            <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mb-4"></div>
            <h3 class="font-bold text-lg text-gray-800">Menyimpan Data</h3>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchBarangInput');
            const supplierSelects = document.querySelectorAll('.supplier-select');
            const btnSimpanSemua = document.getElementById('btnSimpanSemua');
            const loadingModal = document.getElementById('loadingModal');
            const confirmModal = document.getElementById('confirmModal');
            let hasUnsavedChanges = false;
            let targetUrl = '';

            function updateSaveButton() {
                if (btnSimpanSemua) {
                    if (hasUnsavedChanges) {
                        btnSimpanSemua.classList.add('btn-pulse', 'bg-orange-500');
                        btnSimpanSemua.classList.remove('bg-blue-600');
                        btnSimpanSemua.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
                    } else {
                        btnSimpanSemua.classList.remove('btn-pulse', 'bg-orange-500');
                        btnSimpanSemua.classList.add('bg-blue-600');
                        btnSimpanSemua.innerHTML = '<i class="fas fa-save"></i> Simpan Semua';
                    }
                }
            }

            supplierSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const originalValue = this.getAttribute('data-original-value');
                    if (this.value !== originalValue) {
                        this.closest('tr').classList.add('form-changed');
                        this.classList.add('select-changed');
                        hasUnsavedChanges = true;
                    } else {
                        this.closest('tr').classList.remove('form-changed');
                        this.classList.remove('select-changed');
                        hasUnsavedChanges = Array.from(supplierSelects).some(s => s.value !== s.getAttribute('data-original-value'));
                    }
                    updateSaveButton();
                });
            });

            if(searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    let hasResults = false;
                    document.querySelectorAll('.barang-row').forEach(row => {
                        const nama = row.querySelector('.nama-barang').textContent.toLowerCase();
                        if (nama.includes(filter)) { row.style.display = ''; hasResults = true; } 
                        else { row.style.display = 'none'; }
                    });
                    document.getElementById('noResults').classList.toggle('hidden', hasResults);
                });
            }

            if (btnSimpanSemua) {
                btnSimpanSemua.addEventListener('click', function() {
                    const changes = [];
                    supplierSelects.forEach(select => {
                        if (select.value !== select.getAttribute('data-original-value')) {
                            changes.push({ id_barang: select.getAttribute('data-barang-id'), id_suplier: select.value });
                        }
                    });

                    if (changes.length === 0) return;
                    loadingModal.classList.remove('hidden');

                    const formData = new FormData();
                    formData.append('action', 'update_multiple_links');
                    changes.forEach((c, i) => {
                        formData.append(`changes[${i}][id_barang]`, c.id_barang);
                        formData.append(`changes[${i}][id_suplier]`, c.id_suplier);
                    });

                    fetch('', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            loadingModal.classList.add('hidden');
                            if (data.success) location.reload();
                            else alert(data.message);
                        })
                        .catch(err => {
                            loadingModal.classList.add('hidden');
                            alert('Gagal simpan: ' + err);
                        });
                });
            }
            
            document.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (hasUnsavedChanges && !this.href.includes('tab=mapping')) {
                        e.preventDefault();
                        targetUrl = this.href;
                        confirmModal.classList.remove('hidden');
                    }
                });
            });

            document.getElementById('cancelLeave').onclick = () => confirmModal.classList.add('hidden');
            document.getElementById('confirmLeave').onclick = () => window.location.href = targetUrl;
        });
    </script>
</body>
</html>
