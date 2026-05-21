<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include "koneksi.php";
session_start();

if (!isset($_GET['id'])) {
    header("Location: dapur.php");
    exit;
}

$id_pesanan = (int)$_GET['id'];

// 1. Ambil data pesanan (lengkap dengan no_invoice & no_pesanan)
$query_p = $koneksi->prepare("SELECT p.*, u.nama as nama_dapur FROM pesanan p LEFT JOIN user u ON p.id_dapur = u.id WHERE p.id_pesanan = ?");
$query_p->bind_param("i", $id_pesanan);
$query_p->execute();
$pesanan = $query_p->get_result()->fetch_assoc();

if (!$pesanan) {
    die("Pesanan tidak ditemukan.");
}

// 2. Ambil detail pesanan
$query_d = $koneksi->prepare("SELECT dp.*, g.nama as nama_barang, g.satuan FROM detail_pesanan dp JOIN gudang g ON dp.id_barang = g.id_barang WHERE dp.id_pesanan = ?");
$query_d->bind_param("i", $id_pesanan);
$query_d->execute();
$details = $query_d->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Resolve Nomor Invoice & Pesanan (SAMA dengan invoice_pdf.php — tidak halusinasi)
$bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
$m_pesan = (int)date('m', strtotime($pesanan['tgl_pesan']));
$y_pesan = date('Y', strtotime($pesanan['tgl_pesan']));

$no_invoice = !empty($pesanan['no_invoice'])
    ? $pesanan['no_invoice']
    : str_pad($pesanan['id_pesanan'], 3, '0', STR_PAD_LEFT) . "/INV-D1/" . $bulan_romawi[$m_pesan] . "/" . $y_pesan;

$no_pesanan = !empty($pesanan['no_pesanan'])
    ? $pesanan['no_pesanan']
    : '';

// 4. Hitung total
$grand_total = 0;
foreach ($details as $item) {
    $grand_total += $item['jumlah'] * $item['harga_satuan'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo_scs_jpg.png">
    <title>Invoice <?php echo htmlspecialchars($no_invoice); ?> - SCS Banjarnegara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Google Sans', sans-serif; background: <?php echo isset($_GET['popup']) ? '#ffffff' : '#f1f5f9'; ?>; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
        .item-table tbody tr:hover { background-color: #f8fafc; }
    </style>
</head>
<body class="<?php echo isset($_GET['popup']) ? 'py-4 px-2' : 'min-h-screen py-8 px-4'; ?>">

    <div class="max-w-4xl mx-auto">

        <!-- ── Top Action Bar (no-print) ── -->
        <div class="no-print mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Dokumen Resmi</p>
                <h1 class="text-2xl font-black text-slate-900">Invoice Detail</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php
                // Cek apakah user adalah admin & bukan popup
                $is_popup = isset($_GET['popup']);
                $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
                if (!$is_popup): 
                    if ($is_admin): ?>
                    <a href="laporanPenjualan.php" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-50 transition-colors flex items-center gap-2">
                        <i class="fas fa-arrow-left text-xs"></i> Kembali
                    </a>
                    <?php else: ?>
                    <a href="dapur.php" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-50 transition-colors flex items-center gap-2">
                        <i class="fas fa-arrow-left text-xs"></i> Kembali
                    </a>
                    <?php endif; 
                endif; ?>
                <button onclick="document.getElementById('itemList').classList.toggle('hidden')"
                    class="px-4 py-2.5 bg-indigo-50 border border-indigo-200 text-indigo-700 rounded-xl font-bold text-sm hover:bg-indigo-100 transition-colors flex items-center gap-2">
                    <i class="fas fa-list text-xs"></i> Lihat Item Pesanan
                </button>
                <a href="invoice_pdf.php?id=<?php echo $id_pesanan; ?>" target="_blank"
                    class="px-4 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 transition-all flex items-center gap-2 shadow-sm">
                    <i class="fas fa-file-pdf text-xs"></i> Unduh PDF
                </a>
            </div>
        </div>

        <!-- ── Status Badge ── -->
        <?php
        $status_bayar = $pesanan['status_pembayaran'];
        $status_kirim = $pesanan['status_pengiriman'];
        if ($status_bayar === 'Batal') {
            $badge_cls = 'bg-red-100 text-red-700 border-red-200';
            $badge_icon = 'fa-ban';
            $badge_label = 'PESANAN DIBATALKAN';
        } elseif ($status_kirim === 'Done') {
            $badge_cls = 'bg-emerald-100 text-emerald-700 border-emerald-200';
            $badge_icon = 'fa-check-double';
            $badge_label = 'Terkirim & Selesai';
        } elseif ($status_kirim === 'Ongoing') {
            $badge_cls = 'bg-blue-100 text-blue-700 border-blue-200';
            $badge_icon = 'fa-truck';
            $badge_label = 'Sedang Dikirim';
        } elseif ($status_bayar === 'Lunas') {
            $badge_cls = 'bg-amber-100 text-amber-700 border-amber-200';
            $badge_icon = 'fa-clock';
            $badge_label = 'Lunas · Menunggu Pengiriman';
        } else {
            $badge_cls = 'bg-orange-100 text-orange-700 border-orange-200';
            $badge_icon = 'fa-hourglass-half';
            $badge_label = 'Menunggu Pembayaran';
        }
        ?>
        <div class="no-print mb-4 px-4 py-2.5 rounded-xl border <?php echo $badge_cls; ?> flex items-center gap-2.5 text-sm font-bold">
            <i class="fas <?php echo $badge_icon; ?>"></i>
            <?php echo $badge_label; ?>
        </div>

        <!-- ── Item List (collapsible, no-print) ── -->
        <div id="itemList" class="no-print hidden mb-6 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-800">Daftar Item Pesanan</h3>
                    <p class="text-xs text-slate-400 mt-0.5"><?php echo count($details); ?> item dalam pesanan ini</p>
                </div>
                <button onclick="document.getElementById('itemList').classList.add('hidden')"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="item-table w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-5 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">#</th>
                            <th class="px-5 py-3 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nama Barang</th>
                            <th class="px-5 py-3 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">Jumlah</th>
                            <th class="px-5 py-3 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tgl Kirim</th>
                            <th class="px-5 py-3 text-right text-[10px] font-bold text-slate-400 uppercase tracking-widest">Harga Satuan</th>
                            <th class="px-5 py-3 text-right text-[10px] font-bold text-slate-400 uppercase tracking-widest">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php $no = 1; foreach ($details as $item):
                            $sub = $item['jumlah'] * $item['harga_satuan'];
                        ?>
                        <tr>
                            <td class="px-5 py-3.5 text-slate-400 text-xs"><?php echo $no++; ?></td>
                            <td class="px-5 py-3.5">
                                <p class="font-bold text-slate-800"><?php echo htmlspecialchars($item['nama_barang']); ?></p>
                                <?php if (!empty($item['no_sj'])): ?>
                                <p class="text-[10px] text-slate-400 mt-0.5">SJ: <?php echo htmlspecialchars($item['no_sj']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-block bg-slate-100 text-slate-700 px-2.5 py-1 rounded-lg font-bold text-xs">
                                    <?php echo $item['jumlah']; ?> <span class="text-slate-400 font-medium"><?php echo $item['satuan']; ?></span>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center text-xs text-slate-600">
                                <?php echo !empty($item['tgl_pengiriman']) ? date('d M Y', strtotime($item['tgl_pengiriman'])) : '<span class="text-slate-300">-</span>'; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right text-slate-600 font-medium">Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                            <td class="px-5 py-3.5 text-right font-bold text-slate-900">Rp <?php echo number_format($sub, 0, ',', '.'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 border-t-2 border-slate-200">
                            <td colspan="5" class="px-5 py-4 text-right font-black text-slate-700 uppercase tracking-wider text-xs">Grand Total</td>
                            <td class="px-5 py-4 text-right font-black text-emerald-700 text-base">Rp <?php echo number_format($grand_total, 0, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- ── Main Invoice Card ── -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-6">

            <!-- Invoice Header -->
            <div class="px-8 py-7 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start gap-6">
                <div class="flex items-center gap-4">
                    <img src="logo_scs.png" alt="Logo SCS" class="h-14 w-auto object-contain">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 leading-tight">PT. SURYA CERAH SEMESTA</h2>
                        <p class="text-slate-400 text-xs font-medium mt-1">CATERING &amp; MANAGEMENT SOLUTIONS</p>
                        <p class="text-slate-400 text-xs mt-0.5">Jl. Pemuda No.83, Banjarnegara, Jawa Tengah</p>
                    </div>
                </div>
                <div class="text-left sm:text-right flex-shrink-0">
                    <span class="inline-block px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-[10px] font-bold uppercase tracking-widest mb-2">Dokumen Resmi</span>
                    <div class="space-y-1">
                        <p class="text-[11px] text-slate-400 font-bold">No. Invoice:</p>
                        <p class="text-base font-black text-slate-900"><?php echo htmlspecialchars($no_invoice); ?></p>
                        <?php if (!empty($no_pesanan)): ?>
                        <p class="text-[11px] text-slate-400 font-bold mt-1.5">No. PO:</p>
                        <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($no_pesanan); ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-slate-400 mt-1"><?php echo date('d F Y', strtotime($pesanan['tgl_pesan'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="px-8 py-7 grid grid-cols-1 md:grid-cols-3 gap-6 border-b border-slate-100">
                <!-- Ditujukan Kepada -->
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Ditujukan Kepada</p>
                    <h4 class="font-bold text-slate-900 mb-1.5"><?php echo htmlspecialchars($pesanan['nama_pemesan']); ?></h4>
                    <div class="space-y-1">
                        <p class="text-slate-500 text-xs flex items-center gap-2">
                            <i class="fab fa-whatsapp text-green-500 w-3.5 text-center"></i>
                            <?php echo htmlspecialchars($pesanan['wa_pemesan']); ?>
                        </p>
                        <p class="text-slate-500 text-xs flex items-center gap-2">
                            <i class="fas fa-envelope text-blue-400 w-3.5 text-center"></i>
                            <?php echo htmlspecialchars($pesanan['email_pemesan'] ?: '-'); ?>
                        </p>
                    </div>
                </div>

                <!-- Petugas / Outlet -->
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Petugas / Outlet</p>
                    <h4 class="font-bold text-slate-900 mb-2"><?php echo htmlspecialchars($pesanan['nama_dapur']); ?></h4>
                    <span class="inline-block px-2.5 py-1 text-xs font-bold rounded-lg <?php echo $status_bayar === 'Lunas' ? 'bg-emerald-100 text-emerald-700' : ($status_bayar === 'Batal' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'); ?>">
                        <?php echo $status_bayar; ?>
                    </span>
                    <?php if (!empty($pesanan['tgl_digunakan'])): ?>
                    <div class="mt-3">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Tgl Digunakan</p>
                        <p class="text-sm font-bold text-slate-800"><?php echo date('d F Y', strtotime($pesanan['tgl_digunakan'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Catatan -->
                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Catatan Pesanan</p>
                    <p class="text-slate-600 text-sm leading-relaxed italic">
                        "<?php echo htmlspecialchars($pesanan['catatan'] ?: 'Tidak ada catatan khusus untuk pesanan ini.'); ?>"
                    </p>
                </div>
            </div>

            <!-- Item Table -->
            <div class="px-8 py-7 border-b border-slate-100">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Rincian Barang</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="border-b-2 border-slate-100">
                                <th class="py-3 px-2 text-left text-[10px] font-bold text-slate-400 uppercase tracking-widest">Barang</th>
                                <th class="py-3 px-2 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">Jumlah</th>
                                <th class="py-3 px-2 text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tgl Kirim</th>
                                <th class="py-3 px-2 text-right text-[10px] font-bold text-slate-400 uppercase tracking-widest">Harga</th>
                                <th class="py-3 px-2 text-right text-[10px] font-bold text-slate-400 uppercase tracking-widest">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($details as $item):
                                $sub = $item['jumlah'] * $item['harga_satuan'];
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-2">
                                    <p class="font-bold text-slate-900"><?php echo htmlspecialchars($item['nama_barang']); ?></p>
                                    <?php if (!empty($item['no_sj'])): ?>
                                    <p class="text-[10px] text-slate-400 mt-0.5 font-medium">No. SJ: <?php echo htmlspecialchars($item['no_sj']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-2 text-center">
                                    <span class="inline-block px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg font-bold text-xs">
                                        <?php echo $item['jumlah']; ?> <span class="text-slate-400 text-[10px]"><?php echo $item['satuan']; ?></span>
                                    </span>
                                </td>
                                <td class="py-4 px-2 text-center text-xs text-slate-500">
                                    <?php echo !empty($item['tgl_pengiriman']) ? date('d M Y', strtotime($item['tgl_pengiriman'])) : '—'; ?>
                                </td>
                                <td class="py-4 px-2 text-right text-slate-600 font-medium">Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?></td>
                                <td class="py-4 px-2 text-right font-black text-slate-900">Rp <?php echo number_format($sub, 0, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Total & Info -->
            <div class="px-8 py-7 flex flex-col md:flex-row justify-between items-end gap-8">
                <div class="text-xs text-slate-400 flex-1">
                    <p class="font-bold uppercase tracking-widest mb-3 text-slate-500">Informasi Penting</p>
                    <ul class="space-y-1.5 list-disc list-inside">
                        <li>Simpan invoice ini sebagai bukti pemesanan yang sah.</li>
                        <li>Tunjukkan invoice ini saat serah terima barang atau verifikasi driver.</li>
                        <li>Pembayaran dapat dilakukan melalui kasir atau transfer bank SCS.</li>
                    </ul>
                </div>
                <div class="flex-shrink-0 text-right bg-slate-50 border border-slate-100 rounded-2xl px-8 py-5">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Tagihan</p>
                    <p class="text-4xl font-black text-slate-900">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></p>
                    <p class="text-[10px] text-slate-400 italic mt-1">* Termasuk biaya operasional</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-400">Jl. Pemuda No.83, Kutabanjarnegara, Kec. Banjarnegara, Kab. Banjarnegara, Jawa Tengah 53418</p>
            </div>
        </div>

        <!-- ── Bottom Action Buttons (no-print) ── -->
        <div class="no-print flex flex-col sm:flex-row gap-3 mb-20">
            <?php if (!$is_popup): ?>
                <?php if ($is_admin): ?>
                <a href="laporanPenjualan.php" class="flex-1 py-3.5 px-6 bg-white text-slate-700 border border-slate-200 rounded-2xl font-bold text-center hover:bg-slate-50 transition-colors flex items-center justify-center gap-2 text-sm">
                    <i class="fas fa-list-check"></i> Kembali ke Laporan
                </a>
                <?php else: ?>
                <a href="dapur.php" class="flex-1 py-3.5 px-6 bg-white text-slate-700 border border-slate-200 rounded-2xl font-bold text-center hover:bg-slate-50 transition-colors flex items-center justify-center gap-2 text-sm">
                    <i class="fas fa-shopping-cart"></i> Kembali Belanja
                </a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="invoice_pdf.php?id=<?php echo $id_pesanan; ?>" target="_blank"
                class="flex-1 py-3.5 px-6 bg-slate-900 text-white rounded-2xl font-bold text-center hover:bg-slate-800 transition-all flex items-center justify-center gap-2 text-sm shadow-lg">
                <i class="fas fa-file-pdf"></i> Unduh / Cetak PDF
            </a>
        </div>

    </div>

</body>
</html>
