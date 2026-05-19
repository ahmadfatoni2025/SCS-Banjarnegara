<?php
session_start();
include 'koneksi.php';
include_once 'fungsi_akuntansi.php';

// CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user_role = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
$is_admin = ($user_role === 'admin');
$is_akuntan = in_array($user_role, ['akuntan', 'owner']);

if (!$is_admin && !$is_akuntan) {
    echo "Akses Ditolak!"; exit;
}

// AMBIL DAFTAR AKUN UNTUK KONFIRMASI (KAS & BANK)
$akun_list = [];
$res_akun = $koneksi->query("SELECT kode_akun, nama_akun FROM akun_coa WHERE kode_akun LIKE '111%' OR kode_akun LIKE '112%' ORDER BY kode_akun ASC");
while ($a = $res_akun->fetch_assoc()) { $akun_list[] = $a; }

// LOGIKA KONFIRMASI (Hanya Akuntan/Owner)
if (isset($_POST['action']) && $_POST['action'] === 'confirm' && $is_akuntan) {
    $id = (int)$_POST['id_pesanan'];
    $akun_kas = $_POST['kode_akun_kas'] ?? '1111';
    
    // 1. Update Status Konfirmasi & Simpan Data Akuntan
    $id_akuntan = $_SESSION['user']['id'];
    $nama_akuntan = $_SESSION['user']['nama'];
    
    $stmt = $koneksi->prepare("UPDATE pesanan SET 
        is_confirmed_acc = 1, 
        status_approval = 'Approved',
        id_akuntan = ?,
        nama_penandatangan = ?
        WHERE id_pesanan = ?");
    $stmt->bind_param("isi", $id_akuntan, $nama_akuntan, $id);
    
    if ($stmt->execute()) {
        // 2. TRIGGER JURNAL UMUM (Pencatatan resmi ke Akuntansi)
        if (triggerJurnalPenjualan($koneksi, $id, $akun_kas)) {
            $msg = "Penjualan berhasil dikonfirmasi ke akun $akun_kas dan dicatat ke Jurnal Umum.";
            header("Location: konfirmasi_penjualan.php?status=success&msg=" . urlencode($msg));
        } else {
            $msg = "Penjualan dikonfirmasi, tapi GAGAL mencatat ke Jurnal. Hubungi Developer.";
            header("Location: konfirmasi_penjualan.php?status=warning&msg=" . urlencode($msg));
        }
        exit;
    }
}

// FETCH DATA
// Hanya tampilkan yang sudah "Done" (Selesai oleh Admin) tapi belum dikonfirmasi Akuntan
$sql = "SELECT p.*, u.nama as nama_admin 
        FROM pesanan p 
        LEFT JOIN user u ON p.id_dapur = u.id 
        WHERE p.status_pengiriman = 'Done' AND p.is_confirmed_acc = 0
        ORDER BY p.id_pesanan DESC";

if ($is_admin) {
    // Admin melihat status pengajuan SEMUA pesanan yang sudah selesai (Done)
    $sql = "SELECT p.*, u.nama as nama_admin 
            FROM pesanan p 
            LEFT JOIN user u ON p.id_dapur = u.id 
            WHERE p.status_pengiriman = 'Done'
            ORDER BY p.is_confirmed_acc ASC, p.id_pesanan DESC";
}

$res = $koneksi->query($sql);
$data_pesanan = [];
$count_pending = 0;
$count_confirmed = 0;
$total_nilai_pending = 0;
if ($res) {
    while ($row = $res->fetch_assoc()) { 
        $data_pesanan[] = $row;
        if ($row['is_confirmed_acc']) {
            $count_confirmed++;
        } else {
            $count_pending++;
            $total_nilai_pending += $row['total_harga'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Penjualan - SCS</title>
    <link rel="icon" href="logo_scs_jpg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        .card { background: white; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="min-h-screen">

    <?php include 'sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="max-w-7xl mx-auto fade-in">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Konfirmasi Penjualan</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        <?= $is_akuntan ? 'Konfirmasi transaksi yang sudah diselesaikan Admin untuk masuk ke Jurnal Umum.' : 'Pantau status konfirmasi akuntansi pesanan Anda.' ?>
                    </p>
                </div>
            </div>

            <!-- Alert Message -->
            <?php if (isset($_GET['msg'])): ?>
                <?php 
                $status = $_GET['status'] ?? 'info';
                $bg = $status === 'success' ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : ($status === 'warning' ? 'bg-amber-50 border-amber-300 text-amber-700' : 'bg-blue-50 border-blue-300 text-blue-700');
                $icon = $status === 'success' ? 'fa-check-circle' : ($status === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle');
                ?>
                <div class="<?= $bg ?> border px-5 py-4 rounded-xl mb-6 flex items-center gap-3 text-sm font-medium">
                    <i class="fas <?= $icon ?> text-lg"></i>
                    <span><?= htmlspecialchars($_GET['msg']) ?></span>
                </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-xl">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Menunggu</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $count_pending ?></p>
                    </div>
                </div>
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Terkonfirmasi</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $count_confirmed ?></p>
                    </div>
                </div>
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-xl">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Nilai Pending</p>
                        <p class="text-xl font-bold text-slate-800 whitespace-nowrap">Rp <?= number_format($total_nilai_pending, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[700px] md:min-w-0">
                        <thead class="bg-slate-50/80 border-b border-slate-200">
                            <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                <th class="px-5 py-4">Invoice / Tanggal</th>
                                <th class="px-5 py-4">Pelanggan</th>
                                <th class="px-5 py-4 text-right">Total</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($data_pesanan)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center">
                                        <i class="fas fa-inbox text-4xl text-slate-200 mb-3 block"></i>
                                        <p class="text-slate-400 font-medium">Tidak ada transaksi yang menunggu konfirmasi.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach($data_pesanan as $p): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-slate-800 text-sm"><?= $p['no_invoice'] ?></div>
                                    <div class="text-[10px] text-slate-400 font-medium mt-0.5"><?= date('d M Y · H:i', strtotime($p['tgl_pesan'])) ?></div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-700 text-sm"><?= $p['nama_pemesan'] ?></div>
                                    <div class="text-[10px] text-blue-600 font-bold uppercase mt-0.5">
                                        <i class="fas fa-user-check mr-1"></i><?= $p['nama_admin'] ?>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="font-bold text-slate-900 text-sm whitespace-nowrap">Rp <?= number_format($p['total_harga'], 0, ',', '.') ?></div>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <?php if ($p['is_confirmed_acc']): ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700">
                                            <i class="fas fa-check-circle"></i> Terkonfirmasi
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 animate-pulse">
                                            <i class="fas fa-hourglass-half"></i> Menunggu
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end items-center gap-2 flex-wrap">
                                        <a href="cetak_invoice.php?id=<?= $p['id_pesanan'] ?>" target="_blank" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all" title="Lihat Invoice">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>
                                        <?php if ($is_akuntan && !$p['is_confirmed_acc']): ?>
                                            <form action="" method="POST" class="flex items-center gap-1.5">
                                                <input type="hidden" name="id_pesanan" value="<?= $p['id_pesanan'] ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <select name="kode_akun_kas" class="text-[10px] font-semibold p-1.5 border border-slate-200 rounded-lg outline-none bg-slate-50 focus:bg-white focus:border-blue-400 transition-colors">
                                                    <?php foreach($akun_list as $ak): ?>
                                                        <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold hover:bg-blue-700 shadow-sm transition-all whitespace-nowrap">
                                                    <i class="fas fa-check mr-1"></i>KONFIRMASI
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
