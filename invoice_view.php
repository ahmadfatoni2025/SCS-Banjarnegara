<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "koneksi.php";

if (!isset($_GET['id'])) {
    header("Location: dapur.php");
    exit;
}

$id_pesanan = (int)$_GET['id'];

// 1. Ambil data pesanan
$query_p = $koneksi->prepare("SELECT p.*, u.nama as nama_dapur FROM pesanan p LEFT JOIN user u ON p.id_dapur = u.id WHERE p.id_pesanan = ?");
$query_p->bind_param("i", $id_pesanan);
$query_p->execute();
$result_p = $query_p->get_result();
$pesanan = $result_p->fetch_assoc();

if (!$pesanan) {
    die("Pesanan tidak ditemukan.");
}

// 2. Ambil detail pesanan
$query_d = $koneksi->prepare("SELECT dp.*, g.nama as nama_barang, g.satuan FROM detail_pesanan dp JOIN gudang g ON dp.id_barang = g.id_barang WHERE dp.id_pesanan = ?");
$query_d->bind_param("i", $id_pesanan);
$query_d->execute();
$details = $query_d->get_result();

// 3. Hitung Batch PO
$tgl_pesan = new DateTime($pesanan['tgl_pesan']);
$dayOfWeek = (int)$tgl_pesan->format('w'); 

$cutoffDate = clone $tgl_pesan;
if ($dayOfWeek === 6) {
    // Tetap hari itu
} else {
    $cutoffDate->modify('next saturday');
}

$minDelivery = clone $cutoffDate;
$minDelivery->modify('+1 day'); 
$maxDelivery = clone $cutoffDate;
$maxDelivery->modify('+7 days'); 

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$formatIndo = function($date) use ($months) {
    return $date->format('d') . ' ' . $months[(int)$date->format('m')] . ' ' . $date->format('Y');
};

$batch_info = $formatIndo($minDelivery) . " - " . $formatIndo($maxDelivery);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo_scs_jpg.png">
    <title>Invoice #<?php echo $id_pesanan; ?> - SCS Banjarnegara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .success-accent {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }
        @media print {
            .no-print { display: none; }
            .print-only { display: block; }
            body { background: white; }
            .glass-card { border: none; box-shadow: none; }
        }
    </style>
</head>
<body class="min-h-screen py-8 px-4 md:py-12">
    
    <div class="max-w-4xl mx-auto">
        <div class="no-print mb-8 flex flex-col items-center text-center">
            <div class="h-20 w-20 success-accent rounded-full flex items-center justify-center text-white text-4xl shadow-lg">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Pesanan Berhasil!</h1>
            <p class="text-slate-500">Invoice Anda telah diterbitkan dan siap untuk diproses.</p>
        </div>

        <div class="glass-card rounded-3xl shadow-2xl overflow-hidden mb-8">
            <div class="p-8 md:p-12 border-b border-slate-100 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-4">
                    <img src="logo_scs.png" alt="Logo" class="h-16 w-auto object-contain">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 leading-none mb-1">PT. SURYA CERAH SEMESTA</h2>
                        <p class="text-slate-400 text-sm font-medium tracking-wide border-t border-slate-100 pt-1 mt-1">CATERING & MANAGEMENT SOLUTIONS</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="inline-block px-4 py-1 bg-blue-50 text-blue-600 rounded-full text-xs font-bold uppercase tracking-widest mb-3">Invoice Resmi</div>
                    <h3 class="text-4xl font-black text-slate-900 leading-none">#<?php echo $id_pesanan; ?></h3>
                    <p class="text-slate-400 font-medium mt-2"><?php echo date('d F Y', strtotime($pesanan['tgl_pesan'])); ?></p>
                </div>
            </div>

            <div class="p-8 md:p-12">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Ditujukan Kepada</p>
                        <h4 class="text-lg font-bold text-slate-900 mb-1"><?php echo htmlspecialchars($pesanan['nama_pemesan']); ?></h4>
                        <p class="text-slate-500 text-sm flex items-center gap-2 mb-1">
                            <i class="fab fa-whatsapp text-green-500"></i> <?php echo htmlspecialchars($pesanan['wa_pemesan']); ?>
                        </p>
                        <p class="text-slate-500 text-sm flex items-center gap-2">
                            <i class="far fa-envelope text-blue-400"></i> <?php echo htmlspecialchars($pesanan['email_pemesan'] ?: '-'); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Petugas / Unit</p>
                        <h4 class="text-lg font-bold text-slate-900 mb-1"><?php echo htmlspecialchars($pesanan['nama_dapur']); ?></h4>
                        <p class="text-slate-500 text-sm">Status Pembayaran:</p>
                        <span class="inline-block mt-1 px-3 py-1 <?php echo $pesanan['status_pembayaran'] === 'Lunas' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'; ?> rounded-lg text-xs font-bold">
                            <?php echo $pesanan['status_pembayaran']; ?>
                        </span>
                    </div>
                    <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100">
                        <p class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-3">Batch Pengiriman (PO)</p>
                        <h4 class="text-lg font-bold text-blue-900 mb-1"><?php echo $batch_info; ?></h4>
                        <p class="text-blue-600/70 text-xs font-medium">Siklus PO Mingguan Aktif</p>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row gap-6 mb-12 p-6 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="flex-1">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Tanggal Digunakan</p>
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-blue-600">
                                <i class="far fa-calendar-check"></i>
                            </div>
                            <span class="font-bold text-slate-900"><?php echo date('d F Y', strtotime($pesanan['tgl_digunakan'])); ?></span>
                        </div>
                    </div>
                    <div class="flex-[2]">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Catatan Pesanan</p>
                        <p class="text-slate-600 text-sm italic">"<?php echo $pesanan['catatan'] ?: 'Tidak ada catatan khusus untuk pesanan ini.'; ?>"</p>
                    </div>
                </div>

                <div class="overflow-x-auto mb-12">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b-2 border-slate-100">
                                <th class="py-4 px-2 text-xs font-bold text-slate-400 uppercase tracking-widest">Deskripsi Barang</th>
                                <th class="py-4 px-2 text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Harga Unit</th>
                                <th class="py-4 px-2 text-xs font-bold text-slate-400 uppercase tracking-widest text-center">Jumlah</th>
                                <th class="py-4 px-2 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php 
                            while ($item = $details->fetch_assoc()): 
                                $subtotal = $item['jumlah'] * $item['harga_satuan'];
                            ?>
                            <tr>
                                <td class="py-6 px-2">
                                    <h5 class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars($item['nama_barang']); ?></h5>
                                    <p class="text-slate-400 text-xs mt-1">Kebutuhan Dapur & Bahan Baku</p>
                                </td>
                                <td class="py-6 px-2 text-center text-slate-600 font-medium">Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                                <td class="py-6 px-2 text-center">
                                    <span class="inline-block px-3 py-1 bg-slate-100 text-slate-700 rounded-lg font-bold text-sm">
                                        <?php echo $item['jumlah']; ?> <span class="text-[10px] uppercase ml-0.5 text-slate-400"><?php echo $item['satuan']; ?></span>
                                    </span>
                                </td>
                                <td class="py-6 px-2 text-right font-black text-slate-900 text-lg">Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-end gap-12 pt-8 border-t-2 border-slate-100">
                    <div class="flex-1 w-full text-slate-400 text-xs">
                        <p class="font-bold uppercase tracking-widest mb-4">Informasi Penting</p>
                        <ul class="space-y-2 list-disc list-inside">
                            <li>Simpan invoice ini sebagai bukti pemesanan yang sah.</li>
                            <li>Tunjukkan invoice ini saat serah terima barang atau verifikasi driver.</li>
                            <li>Pembayaran dapat dilakukan melalui kasir atau transfer bank SCS.</li>
                        </ul>
                    </div>
                    <div class="flex-1 w-full md:max-w-xs text-slate-950 pl-14">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Total Akhir</p>
                        <h4 class="text-4xl font-black mb-1">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></h4>
                        <p class="text-[10px] text-slate-500 italic">* Termasuk pajak & biaya operasional</p>
                    </div>
                </div>
            </div>

            <div class="p-8 bg-slate-50 text-center border-t border-slate-100">
                <p class="text-xs font-medium text-slate-400">
                    Jl. Pemuda No.83, Kutabanjarnegara, Kec. Banjarnegara, Kab. Banjarnegara, Jawa Tengah 53418, Indonesia
                </p>
            </div>
        </div>

        <div class="no-print flex flex-col md:flex-row gap-4 mb-20">
            <a href="dapur.php" class="flex-1 py-4 px-8 bg-white text-slate-900 border border-slate-200 rounded-2xl font-bold text-center hover:bg-slate-50 transition-colors flex items-center justify-center gap-3 active:scale-[0.98]">
                <i class="fas fa-shopping-cart"></i> Kembali Belanja
            </a>
            <a href="cetak_invoice.php?id=<?php echo $id_pesanan; ?>" target="_blank" class="flex-1 py-4 px-8 bg-slate-900 text-white rounded-2xl font-bold text-center hover:bg-slate-800 transition-all flex items-center justify-center gap-3 shadow-lg active:scale-[0.98]">
                <i class="fas fa-print"></i> Cetak / Unduh PDF
            </a>
        </div>
    </div>

</body>
</html>
