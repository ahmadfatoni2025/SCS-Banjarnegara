<?php
session_start();
include 'koneksi.php';
include_once 'fungsi_akuntansi.php';

// CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
if (!in_array($role_user, ['admin', 'owner', 'akuntan', 'dapur'])) {
    echo "Akses Ditolak!"; exit;
}

$id_pesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_pesanan <= 0) { die("ID Pesanan tidak valid!"); }

// FETCH DATA PESANAN
$sql_p = "SELECT p.*, u.nama as nama_dapur 
          FROM pesanan p 
          LEFT JOIN user u ON p.id_dapur = u.id 
          WHERE p.id_pesanan = ?";
$stmt_p = $koneksi->prepare($sql_p);
$stmt_p->bind_param("i", $id_pesanan);
$stmt_p->execute();
$res_p = $stmt_p->get_result();
$pesanan = $res_p->fetch_assoc();

if (!$pesanan) { die("Pesanan tidak ditemukan!"); }

// FETCH DETAIL PESANAN
$sql_d = "SELECT dp.*, g.nama as nama_barang, g.satuan 
          FROM detail_pesanan dp 
          JOIN gudang g ON dp.id_barang = g.id_barang 
          WHERE dp.id_pesanan = ?";
$stmt_d = $koneksi->prepare($sql_d);
$stmt_d->bind_param("i", $id_pesanan);
$stmt_d->execute();
$res_d = $stmt_d->get_result();
$details = [];
while ($row = $res_d->fetch_assoc()) { $details[] = $row; }

// Helper Format Romawi
$bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
$m_pesan = (int)date('m', strtotime($pesanan['tgl_pesan']));
$y_pesan = date('Y', strtotime($pesanan['tgl_pesan']));

// Generate No Invoice & No Pesanan
$no_invoice = !empty($pesanan['no_invoice']) ? $pesanan['no_invoice'] : str_pad($pesanan['id_pesanan'], 3, '0', STR_PAD_LEFT) . "/INV-D1/" . $bulan_romawi[$m_pesan] . "/" . $y_pesan;
$no_pesanan = !empty($pesanan['no_pesanan']) ? $pesanan['no_pesanan'] : $pesanan['id_pesanan'] . "/SCS/PO-DP/" . $bulan_romawi[$m_pesan] . "/" . $y_pesan;

// FETCH DATA AKUNTAN — SELALU tampilkan TTD di semua invoice (Lunas maupun Belum Bayar)
$ttd_image = null;
$ttd_nama_display = '';

// 1. SNAPSHOT PERMANEN (dari pesanan yang sudah dikonfirmasi)
if (!empty($pesanan['path_ttd'])) {
    $snap_path = 'uploads/ttd/' . $pesanan['path_ttd'];
    if (file_exists($snap_path)) $ttd_image = $snap_path;
    $ttd_nama_display = !empty($pesanan['nama_penandatangan']) ? $pesanan['nama_penandatangan'] : '';
}

// 2. FALLBACK: dari user table (jika ada id_akuntan tapi belum ada snapshot)
if (empty($ttd_image) && !empty($pesanan['id_akuntan'])) {
    $stmt_acc = $koneksi->prepare("SELECT tanda_tangan, nama_ttd, nama FROM user WHERE id = ?");
    $stmt_acc->bind_param("i", $pesanan['id_akuntan']);
    $stmt_acc->execute();
    $res_acc = $stmt_acc->get_result();
    if ($acc = $res_acc->fetch_assoc()) {
        if (!empty($acc['tanda_tangan'])) {
            $acc_path = 'uploads/ttd/' . $acc['tanda_tangan'];
            if (file_exists($acc_path)) $ttd_image = $acc_path;
        }
        if (empty($ttd_nama_display)) {
            $ttd_nama_display = !empty($acc['nama_ttd']) ? $acc['nama_ttd'] : $acc['nama'];
        }
    }
}

// 3. FALLBACK GLOBAL: dari tabel pengaturan (untuk pesanan baru dari dapur)
if (empty($ttd_image) || empty($ttd_nama_display)) {
    $res_def = $koneksi->query("SELECT kunci, nilai FROM pengaturan WHERE kunci IN ('nama_akuntan_default', 'ttd_akuntan_default')");
    while ($res_def && $row_def = $res_def->fetch_assoc()) {
        if ($row_def['kunci'] === 'nama_akuntan_default' && !empty($row_def['nilai']) && empty($ttd_nama_display)) {
            $ttd_nama_display = $row_def['nilai'];
        }
        if ($row_def['kunci'] === 'ttd_akuntan_default' && !empty($row_def['nilai']) && empty($ttd_image)) {
            $def_path = 'uploads/ttd/' . $row_def['nilai'];
            if (file_exists($def_path)) $ttd_image = $def_path;
        }
    }
}

// Fallback terakhir jika database kosong
if (empty($ttd_nama_display)) $ttd_nama_display = 'Bagian Keuangan';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo_scs_jpg.png">
    <title>Invoice <?= $no_invoice ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Times+New+Roman&display=swap');
        
        body { 
            font-family: 'Times New Roman', Times, serif; 
            background-color: #f3f4f6; 
            padding: 20px;
            color: #000;
        }

        .invoice-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 10mm 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        @media print {
            @page { margin: 0; size: A4; }
            body { background: none; padding: 0; margin: 0; }
            .invoice-container { box-shadow: none; margin: 0; width: 100%; padding: 10mm 15mm; min-height: 297mm; }
            .no-print { display: none !important; }
        }

        .table-invoice { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .table-invoice th, .table-invoice td { 
            border: 1px solid #000; 
            padding: 2px 8px; 
            font-size: 13px;
        }
        .table-invoice th { background-color: #fff; font-weight: normal; }

        .title-banner {
            background-color: #d1d5db;
            text-align: center;
            font-size: 16px;
            padding: 4px;
            margin: 20px auto;
            width: 40%;
            text-transform: uppercase;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(220, 38, 38, 0.1);
            font-weight: bold;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
            text-transform: uppercase;
            border: 15px solid rgba(220, 38, 38, 0.1);
            padding: 20px 50px;
            border-radius: 20px;
        }

        .status-table td {
            padding: 1px 0;
            vertical-align: top;
        }
        
        .currency-cell {
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    <div class="max-w-[210mm] mx-auto mb-4 no-print flex justify-end gap-2">
        <a href="laporanPenjualan.php" class="bg-gray-500 text-white px-4 py-2 rounded text-sm font-bold">Kembali</a>
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold">Cetak Invoice</button>
    </div>

    <div class="invoice-container">
        <?php if ($pesanan['status_pembayaran'] === 'Batal'): ?>
            <div class="watermark">BATAL</div>
        <?php endif; ?>
        
        <!-- HEADER -->
        <div class="flex items-center mb-8 pt-12 pb-4">
            <div class="w-1/4">
                <img src="logo_scs.png" class="h-32 w-auto object-contain">
            </div>
            <div class="w-1/2 text-center">
                <h1 class="text-xl font-bold uppercase tracking-wide">PT. SURYA CERAH SEMESTA</h1>
                <p class="text-[11px] leading-tight mt-1">
                    Jl. Pemuda No. 83, Kutabanjarnegara, Kec. Banjarnegara,<br>
                    Kab. Banjarnegara, Jawa Tengah, 53471
                </p>
            </div>
            <div class="w-1/4"></div>
        </div>

        <div class="flex justify-between items-start mb-2 mt-4">
            <div class="w-1/2">
                <p class="font-bold text-[14px] mb-1">Kepada:</p>
                <?php 
                    $nama_tujuan = $pesanan['nama_pemesan'];
                    if (!empty($pesanan['nama_dapur']) && strtolower($pesanan['nama_dapur']) !== 'admin') {
                        $nama_tujuan = $pesanan['nama_dapur'];
                    }
                ?>
                <p class="text-[13px]"><?= $nama_tujuan ?></p>
                <p class="text-[11px] italic mt-1">cc: <?= $nama_tujuan ?></p>
            </div>
            <div class="w-1/2 flex justify-end">
                <table class="status-table text-[13px] w-full max-w-[280px]">
                    <tr>
                        <td class="w-32">Tanggal Invoice</td>
                        <td class="px-2 text-center w-4">:</td>
                        <td><?= !empty($pesanan['tgl_invoice']) ? date('d/m/Y', strtotime($pesanan['tgl_invoice'])) : date('d/m/Y', strtotime($pesanan['tgl_pesan'])) ?></td>
                    </tr>
                    <tr>
                        <td>Nomor Invoice</td>
                        <td class="px-2 text-center">:</td>
                        <td><?= $no_invoice ?></td>
                    </tr>
                    <tr>
                        <td>Nomor Pesanan</td>
                        <td class="px-2 text-center">:</td>
                        <td><?= $no_pesanan ?></td>
                    </tr>
                    <tr>
                        <td>Tanggal Pesan</td>
                        <td class="px-2 text-center">:</td>
                        <td><?= date('d/m/Y', strtotime($pesanan['tgl_pesan'])) ?></td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td class="px-2 text-center">:</td>
                        <td class="font-bold <?= $pesanan['status_pembayaran'] === 'Lunas' ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $pesanan['status_pembayaran'] === 'Batal' ? 'DIBATALKAN' : ($pesanan['status_pembayaran'] === 'Lunas' ? 'Lunas' : 'Belum Bayar') ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- BANNER TITLE -->
        <div class="title-banner">INVOICE</div>

        <!-- TABLE -->
        <p class="font-bold text-[14px] mb-1 mt-6">Rincian Barang:</p>
        <table class="table-invoice">
            <thead>
                <tr>
                    <th class="w-[5%] text-center">No</th>
                    <th class="w-[35%] text-center">Nama Barang</th>
                    <th class="w-[10%] text-center">Jumlah</th>
                    <th class="w-[10%] text-center">Satuan</th>
                    <th class="w-[20%] text-center">Harga Satuan</th>
                    <th class="w-[20%] text-center">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $total = 0;
                foreach($details as $row): 
                    $sub = $row['jumlah'] * $row['harga_satuan'];
                    $total += $sub;
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= $row['nama_barang'] ?></td>
                    <td class="text-center"><?= $row['jumlah'] ?></td>
                    <td class="text-center"><?= $row['satuan'] ?></td>
                    <td>
                        <div class="currency-cell">
                            <span>Rp</span>
                            <span class="text-right"><?= number_format($row['harga_satuan'], 0, ',', '.') ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="currency-cell">
                            <span>Rp</span>
                            <span class="text-right"><?= number_format($sub, 0, ',', '.') ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="font-bold">
                    <td colspan="4" class="text-center font-bold">TOTAL</td>
                    <td colspan="2">
                        <div class="currency-cell">
                            <span>Rp</span>
                            <span class="text-right"><?= number_format($total, 0, ',', '.') ?></span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- FOOTER: PAYMENT INFO & SIGNATURE -->
        <div class="mt-8 flex justify-between items-start">
            <!-- Left Side: Payment Info -->
            <div class="w-1/2">
                <?php if ($pesanan['status_pembayaran'] !== 'Lunas'): ?>
                <p class="text-red-600 italic text-[12px] mb-4">*Pembayaran dilakukan maksimal satu (1) hari setelah barang diterima</p>
                
                <div class="bg-gray-300 text-[13px] font-bold px-4 py-1 inline-block mb-2 min-w-[200px] border-b border-black">
                    Keterangan
                </div>
                <div class="text-[12px] leading-relaxed">
                    <p class="mb-1">Pembayaran via</p>
                    <p class="font-bold">Bank BRI</p>
                    <p>Atas nama PT Surya Cerah Semesta</p>
                    <p>0004-01-002303-56-1</p>
                    <p>NPWP 20.870.347.0-529.000</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Side: Signature -->
            <div class="w-1/3 text-center pt-8"> <!-- Adjust pt-8 to align with 'Keterangan' if red text is present -->
                <p class="text-[13px] mb-2">Hormat kami,</p>
                <div class="relative h-20 flex items-center justify-center mb-1">
                    <!-- Background Logo Subtle -->
                    <img src="logo_scs.png" class="h-28 opacity-80 absolute left-1/2 -translate-x-1/2">
                    
                    <?php if ($ttd_image && file_exists($ttd_image)): ?>
                    <img src="<?= $ttd_image ?>" class="h-20 relative z-10 object-contain">
                    <?php endif; ?>
                </div>

                <div class="w-full">
                    <p class="font-bold text-[14px] border-b border-black inline-block px-10 pb-0.5 min-w-[180px]">
                        <?= $ttd_nama_display ?>
                    </p>
                    <p class="text-[12px] mt-1">Bagian Keuangan</p>
                </div>
            </div>
        </div>

    </div>
</body>
</html>


<?php 
// End of file
?>
