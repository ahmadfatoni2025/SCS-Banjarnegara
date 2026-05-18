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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body class="flex flex-col md:flex-row min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 p-4 md:p-8 md:ml-64">
        <div class="max-w-7xl mx-auto">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Laporan Barang Keluar</h1>
                    <p class="text-slate-500">Pantau pergerakan barang dari gudang ke dapur.</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="window.print()" class="px-4 py-2 bg-white border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition flex items-center gap-2">
                        <i class="fas fa-print"></i> Cetak
                    </button>
                    <a href="export_csv.php?type=barang_keluar&tgl_awal=<?= $tgl_awal ?>&tgl_akhir=<?= $tgl_akhir ?>&id_dapur=<?= $id_dapur ?>" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="bg-white p-6 rounded-xl shadow-sm mb-8 border border-slate-100">
                <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tanggal Awal</label>
                        <input type="date" name="tgl_awal" value="<?= $tgl_awal ?>" class="w-full p-2.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tanggal Akhir</label>
                        <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="w-full p-2.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Dapur Tujuan</label>
                        <select name="id_dapur" class="w-full p-2.5 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                            <option value="">Semua Dapur</option>
                            <?php foreach ($list_dapur as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $id_dapur == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white p-2.5 rounded-lg font-bold hover:bg-blue-700 transition">
                            <i class="fas fa-filter mr-2"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm">
                    <p class="text-sm text-slate-500 mb-1">Total Kuantitas Keluar</p>
                    <h3 class="text-2xl font-bold text-slate-800"><?= number_format($grand_total_qty, 0, ',', '.') ?> <small class="text-slate-400 font-normal">Unit/Pcs</small></h3>
                </div>
                <div class="bg-white p-6 rounded-xl border border-slate-100 shadow-sm">
                    <p class="text-sm text-slate-500 mb-1">Total Nilai Barang Keluar</p>
                    <h3 class="text-2xl font-bold text-green-600">Rp <?= number_format($grand_total_nilai, 0, ',', '.') ?></h3>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container overflow-hidden border border-slate-200">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Tanggal</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">ID Pesanan</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Dapur Tujuan</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Nama Barang</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase text-center">Jumlah</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase text-right">Nilai Barang</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($barang_keluar)): ?>
                            <tr>
                                <td colspan="6" class="p-12 text-center text-slate-400">
                                    <i class="fas fa-box-open text-4xl mb-4 block opacity-20"></i>
                                    Tidak ada data barang keluar untuk periode ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($barang_keluar as $item): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-4 text-sm text-slate-600"><?= date('d/m/Y', strtotime($item['tgl_pesan'])) ?></td>
                                    <td class="p-4">
                                        <span class="text-xs font-bold bg-slate-100 px-2 py-1 rounded text-slate-600">#<?= $item['id_pesanan'] ?></span>
                                    </td>
                                    <td class="p-4 text-sm font-semibold text-slate-700"><?= htmlspecialchars($item['nama_dapur']) ?></td>
                                    <td class="p-4 text-sm text-slate-800 font-medium"><?= htmlspecialchars($item['nama_barang']) ?></td>
                                    <td class="p-4 text-sm text-center">
                                        <span class="font-bold"><?= $item['jumlah'] ?></span> 
                                        <span class="text-xs text-slate-400"><?= strtoupper($item['satuan']) ?></span>
                                    </td>
                                    <td class="p-4 text-sm text-right font-bold text-slate-700">
                                        Rp <?= number_format($item['total_nilai'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

</body>
</html>
