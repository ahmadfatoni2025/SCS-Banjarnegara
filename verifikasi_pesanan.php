<?php
session_start();
include 'koneksi.php';

// CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user_role = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
$is_admin = ($user_role === 'admin');
$is_akuntan = in_array($user_role, ['akuntan', 'owner']);

if (!$is_admin && !$is_akuntan) {
    echo "Akses Ditolak!"; exit;
}

// LOGIKA APPROVAL (Hanya Akuntan/Owner)
if (isset($_GET['action']) && $is_akuntan) {
    $id = (int)$_GET['id'];
    $status = $_GET['action'] === 'approve' ? 'Approved' : 'Rejected';
    
    $stmt = $koneksi->prepare("UPDATE pesanan SET status_approval = ? WHERE id_pesanan = ?");
    $stmt->bind_param("si", $status, $id);
    if ($stmt->execute()) {
        $msg = "Pesanan berhasil di-" . ($status === 'Approved' ? 'setujui' : 'tolak');
        header("Location: verifikasi_pesanan.php?status=success&msg=" . urlencode($msg));
        exit;
    }
}

// FETCH DATA
$sql = "SELECT p.*, u.nama as nama_admin 
        FROM pesanan p 
        LEFT JOIN user u ON p.id_dapur = u.id 
        WHERE p.status_approval = 'Pending' OR p.status_approval = 'Rejected'";

if ($is_admin) {
    // Admin hanya melihat yang mereka ajukan
    $sql = "SELECT p.*, u.nama as nama_admin 
            FROM pesanan p 
            LEFT JOIN user u ON p.id_dapur = u.id 
            WHERE p.id_dapur = " . $_SESSION['user']['id'] . "
            ORDER BY p.id_pesanan DESC";
} else {
    // Akuntan melihat semua yang butuh approval
    $sql = "SELECT p.*, u.nama as nama_admin 
            FROM pesanan p 
            LEFT JOIN user u ON p.id_dapur = u.id 
            ORDER BY CASE WHEN p.status_approval = 'Pending' THEN 0 ELSE 1 END, p.id_pesanan DESC";
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
    <title>Verifikasi Pesanan - SCS</title>
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
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Verifikasi Pesanan</h1>
                    <p class="text-slate-500 mt-1">
                        <?= $is_akuntan ? 'Setujui atau tolak pengajuan invoice dari Admin.' : 'Pantau status persetujuan invoice Anda.' ?>
                    </p>
                </div>
                <?php if ($is_admin): ?>
                <a href="invoice_manual.php" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all flex items-center gap-2">
                    <i class="fas fa-plus"></i> Buat Pengajuan
                </a>
                <?php endif; ?>
            </header>

            <?php if (isset($_GET['msg'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3">
                    <i class="fas fa-check-circle text-xl"></i>
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
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($data_pesanan)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada data pengajuan.</td>
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
                                <div class="text-[10px] text-blue-600 font-bold uppercase">Oleh: <?= $p['nama_admin'] ?></div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="font-black text-slate-900">Rp<?= number_format($p['total_harga'], 0, ',', '.') ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php 
                                $status = $p['status_approval'];
                                $color = 'bg-slate-100 text-slate-600';
                                if ($status === 'Approved') $color = 'bg-emerald-100 text-emerald-700';
                                if ($status === 'Rejected') $color = 'bg-red-100 text-red-700';
                                if ($status === 'Pending') $color = 'bg-amber-100 text-amber-700';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?= $color ?>">
                                    <?= $status ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="cetak_invoice.php?id=<?= $p['id_pesanan'] ?>" target="_blank" class="p-2 text-slate-400 hover:text-blue-600 transition-colors" title="Lihat">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($is_akuntan && $p['status_approval'] === 'Pending'): ?>
                                        <a href="?action=approve&id=<?= $p['id_pesanan'] ?>" onclick="return confirm('Setujui invoice ini?')" class="bg-emerald-500 text-white px-3 py-1 rounded-lg text-[10px] font-bold hover:bg-emerald-600 transition-all">
                                            SETUJUI
                                        </a>
                                        <a href="?action=reject&id=<?= $p['id_pesanan'] ?>" onclick="return confirm('Tolak invoice ini?')" class="bg-red-500 text-white px-3 py-1 rounded-lg text-[10px] font-bold hover:bg-red-600 transition-all">
                                            TOLAK
                                        </a>
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
