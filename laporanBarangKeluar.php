<?php
session_start();
include 'koneksi.php';

// Cek Login
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Filter Dasar
$tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$id_dapur = $_GET['id_dapur'] ?? '';

// Query Daftar Dapur untuk Filter
$list_dapur = [];
$res_d = $koneksi->query("SELECT id, nama FROM user WHERE role = 'dapur' ORDER BY nama ASC");
while ($row = $res_d->fetch_assoc()) {
    $list_dapur[] = $row;
}

// Query Utama Barang Keluar
$sql = "SELECT 
            p.id_pesanan, 
            p.tgl_pesan, 
            u.nama as nama_dapur, 
            g.nama as nama_barang, 
            dp.jumlah, 
            g.satuan,
            dp.harga_satuan,
            (dp.jumlah * dp.harga_satuan) as total_nilai
        FROM detail_pesanan dp
        JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
        JOIN gudang g ON dp.id_barang = g.id_barang
        JOIN user u ON p.id_dapur = u.id
        WHERE DATE(p.tgl_pesan) BETWEEN ? AND ?
        AND p.status_pembayaran != 'Batal'";

if (!empty($id_dapur)) {
    $sql .= " AND p.id_dapur = " . (int)$id_dapur;
}

$sql .= " ORDER BY p.tgl_pesan DESC";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
$stmt->execute();
$result = $stmt->get_result();

$barang_keluar = [];
$grand_total_qty = 0;
$grand_total_nilai = 0;

while ($row = $result->fetch_assoc()) {
    $barang_keluar[] = $row;
    $grand_total_qty += $row['jumlah'];
    $grand_total_nilai += $row['total_nilai'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Barang Keluar - SCS Banjarnegara</title>
    <link rel="icon" href="logo_scs_jpg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        .card { background: white; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @media print {
            .no-print { display: none !important; }
            .md\:ml-64 { margin-left: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="min-h-screen">

    <?php include 'sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="max-w-7xl mx-auto fade-in">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Laporan Barang Keluar</h1>
                    <p class="text-sm text-slate-500 mt-1">Pantau pergerakan barang dari gudang ke dapur.</p>
                </div>
                <div class="no-print flex gap-2">
                    <button onclick="window.print()" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2 text-sm font-semibold shadow-sm">
                        <i class="fas fa-print"></i> Cetak
                    </button>
                    <a href="export_csv.php?type=barang_keluar&tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&id_dapur=<?= $id_dapur ?>" class="px-4 py-2.5 bg-emerald-600 text-white rounded-xl hover:bg-emerald-700 transition-all flex items-center gap-2 text-sm font-semibold shadow-sm">
                        <i class="fas fa-file-excel"></i> Export
                    </a>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card p-5 mb-6 no-print">
                <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Tanggal Awal</label>
                        <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Tanggal Akhir</label>
                        <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Dapur Tujuan</label>
                        <select name="id_dapur" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 outline-none transition-all">
                            <option value="">Semua Dapur</option>
                            <?php foreach ($list_dapur as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $id_dapur == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-slate-900 text-white p-2.5 rounded-xl font-bold hover:bg-black transition-all text-sm shadow-sm">
                            <i class="fas fa-filter mr-2"></i>Terapkan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl">
                        <i class="fas fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Kuantitas Keluar</p>
                        <p class="text-2xl font-bold text-slate-800"><?= number_format($grand_total_qty, 0, ',', '.') ?> <span class="text-sm font-medium text-slate-400">Unit</span></p>
                    </div>
                </div>
                <div class="card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Nilai Barang</p>
                        <p class="text-2xl font-bold text-emerald-600 whitespace-nowrap">Rp <?= number_format($grand_total_nilai, 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[700px] md:min-w-0">
                        <thead class="bg-slate-50/80 border-b border-slate-200">
                            <tr>
                                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Tanggal</th>
                                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">ID Pesanan</th>
                                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Dapur Tujuan</th>
                                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Nama Barang</th>
                                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-center">Jumlah</th>
                                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-wider text-right">Nilai Barang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($barang_keluar)): ?>
                                <tr>
                                    <td colspan="6" class="p-16 text-center">
                                        <i class="fas fa-box-open text-4xl text-slate-200 mb-3 block"></i>
                                        <p class="text-slate-400 font-medium">Tidak ada data barang keluar untuk periode ini.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($barang_keluar as $item): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="p-4 text-sm text-slate-600 font-medium whitespace-nowrap"><?= date('d/m/Y', strtotime($item['tgl_pesan'])) ?></td>
                                        <td class="p-4">
                                            <span class="text-xs font-bold bg-slate-100 px-2.5 py-1 rounded-lg text-slate-600">#<?= $item['id_pesanan'] ?></span>
                                        </td>
                                        <td class="p-4 text-sm font-semibold text-slate-700"><?= htmlspecialchars($item['nama_dapur']) ?></td>
                                        <td class="p-4 text-sm text-slate-800 font-medium"><?= htmlspecialchars($item['nama_barang']) ?></td>
                                        <td class="p-4 text-sm text-center">
                                            <span class="font-bold text-slate-800"><?= $item['jumlah'] ?></span> 
                                            <span class="text-[10px] text-slate-400 font-semibold uppercase"><?= strtoupper($item['satuan']) ?></span>
                                        </td>
                                        <td class="p-4 text-sm text-right font-bold text-slate-700 whitespace-nowrap">
                                            Rp <?= number_format($item['total_nilai'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
