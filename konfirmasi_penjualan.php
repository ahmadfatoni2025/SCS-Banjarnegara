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
if ($res) {
    while ($row = $res->fetch_assoc()) { $data_pesanan[] = $row; }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Penjualan - SCS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
    </style>
</head>
<body class="flex flex-col md:flex-row min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-6 md:ml-64">
        <div class="max-w-6xl mx-auto">
            <header class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Konfirmasi Penjualan</h1>
                    <p class="text-slate-500 mt-1">
                        <?= $is_akuntan ? 'Konfirmasi transaksi yang sudah diselesaikan Admin untuk masuk ke Jurnal Umum.' : 'Pantau status konfirmasi akuntansi pesanan Anda.' ?>
                    </p>
                </div>
            </header>

            <?php if (isset($_GET['msg'])): ?>
                <?php 
                $status = $_GET['status'] ?? 'info';
                $bg = $status === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : ($status === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-blue-50 border-blue-200 text-blue-700');
                ?>
                <div class="<?= $bg ?> border px-6 py-4 rounded-2xl mb-8 flex items-center gap-3">
                    <i class="fas fa-info-circle text-xl"></i>
                    <span class="font-bold"><?= htmlspecialchars($_GET['msg']) ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-6 py-4">Invoice / Tgl</th>
                            <th class="px-6 py-4">Pelanggan</th>
                            <th class="px-6 py-4 text-right">Total</th>
                            <th class="px-6 py-4 text-center">Status Akuntansi</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($data_pesanan)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada transaksi yang menunggu konfirmasi.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach($data_pesanan as $p): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800"><?= $p['no_invoice'] ?></div>
                                <div class="text-[10px] text-slate-400 uppercase"><?= date('d M Y', strtotime($p['tgl_pesan'])) ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-700"><?= $p['nama_pemesan'] ?></div>
                                <div class="text-[10px] text-blue-600 font-bold uppercase">Selesai Oleh: <?= $p['nama_admin'] ?></div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-black text-slate-900">Rp<?= number_format($p['total_harga'], 0, ',', '.') ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($p['is_confirmed_acc']): ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700">
                                        TERKONFIRMASI
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-100 text-amber-700">
                                        MENUNGGU KONFIRMASI
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end items-center gap-3">
                                    <a href="cetak_invoice.php?id=<?= $p['id_pesanan'] ?>" target="_blank" class="p-2 text-slate-400 hover:text-blue-600 transition-colors" title="Lihat Invoice">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                    <?php if ($is_akuntan && !$p['is_confirmed_acc']): ?>
                                        <form action="" method="POST" class="flex items-center gap-2">
                                            <input type="hidden" name="id_pesanan" value="<?= $p['id_pesanan'] ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <select name="kode_akun_kas" class="text-[10px] font-bold p-1 border border-slate-200 rounded-lg outline-none bg-white">
                                                <?php foreach($akun_list as $ak): ?>
                                                    <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-[10px] font-bold hover:bg-blue-700 shadow-md shadow-blue-100 transition-all">
                                                KONFIRMASI
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
    </main>

</body>
</html>
