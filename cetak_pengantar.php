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
          WHERE dp.id_pesanan = ?
          ORDER BY dp.tgl_pengiriman ASC, g.nama ASC";
$stmt_d = $koneksi->prepare($sql_d);
$stmt_d->bind_param("i", $id_pesanan);
$stmt_d->execute();
$res_d = $stmt_d->get_result();
$details = [];
while ($row = $res_d->fetch_assoc()) { $details[] = $row; }

// === GROUPING BY tgl_pengiriman ===
$grouped = [];
foreach ($details as $item) {
    $key = !empty($item['tgl_pengiriman']) ? $item['tgl_pengiriman'] : 'tanpa_tanggal';
    $grouped[$key][] = $item;
}
ksort($grouped); // Urutkan tanggal

// Helper Format Romawi
$bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
$m_pesan = (int)date('m', strtotime($pesanan['tgl_pesan']));
$y_pesan = date('Y', strtotime($pesanan['tgl_pesan']));

$no_sj = str_pad($pesanan['id_pesanan'], 3, '0', STR_PAD_LEFT) . "/SJ-SCS/" . $bulan_romawi[$m_pesan] . "/" . $y_pesan;

// Nama tujuan: dapur name, fallback ke nama_pemesan
$nama_tujuan = $pesanan['nama_pemesan'];
if (!empty($pesanan['nama_dapur']) && strtolower($pesanan['nama_dapur']) !== 'admin') {
    $nama_tujuan = $pesanan['nama_dapur'];
}

// Nama bulan Indonesia
$nama_bulan_id = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
$nama_hari_id = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];

$total_pages = count($grouped);
$current_page = 0;

// Ambil Pengaturan Default Surat Jalan
$q_dnopol = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'default_nopol'");
$res_dnopol = $q_dnopol ? $q_dnopol->fetch_assoc() : null;
$def_nopol = $res_dnopol ? $res_dnopol['nilai'] : '-';

$q_ddriver = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'default_driver'");
$res_ddriver = $q_ddriver ? $q_ddriver->fetch_assoc() : null;
$def_driver = $res_ddriver ? $res_ddriver['nilai'] : '-';

$q_dhp = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'default_no_hp'");
$res_dhp = $q_dhp ? $q_dhp->fetch_assoc() : null;
$def_no_hp = $res_dhp ? $res_dhp['nilai'] : '-';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo_scs_jpg.png">
    <title>Surat Permintaan Barang <?= $no_sj ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #f3f4f6; 
            padding: 20px;
            color: #000;
        }

        .document-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 30px auto;
            background: white;
            padding: 10mm 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        @media print {
            @page { margin: 0; size: A4; }
            body { background: none; padding: 0; margin: 0; }
            .document-container { 
                box-shadow: none; 
                margin: 0; 
                width: 100%; 
                padding: 10mm 15mm; 
                min-height: 297mm;
            }
            .page-break { page-break-after: always; }
            .page-break:last-child { page-break-after: auto; }
            .no-print { display: none !important; }
            #action-buttons { display: none !important; }
        }

        .table-sj { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-sj th, .table-sj td { 
            border: 1px solid #000; 
            padding: 4px 6px; 
            font-size: 11px;
        }
        .table-sj th { background-color: #f3f4f6; font-weight: bold; text-transform: uppercase; }

        .metadata-box {
            border: 1px solid #000;
            padding: 8px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
        }

        .metadata-col {
            width: 48%;
        }

        .metadata-table {
            width: 100%;
            font-size: 11px;
        }

        .metadata-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .checkbox-cell {
            width: 25px;
            height: 15px;
            border: 1px solid #000;
            display: inline-block;
            margin-top: 2px;
        }
    </style>
</head>
<body>

<?php foreach ($grouped as $tgl_key => $items): 
    $current_page++;
    
    $item_first = $items[0];
    $display_no_sj = !empty($item_first['no_sj']) ? $item_first['no_sj'] : $no_sj;

    // Format tanggal
    if ($tgl_key !== 'tanpa_tanggal') {
        $ts = strtotime($tgl_key);
        $hari = $nama_hari_id[(int)date('w', $ts)];
        $tgl_formatted = $hari . ', ' . date('d', $ts) . ' ' . $nama_bulan_id[(int)date('m', $ts)] . ' ' . date('Y', $ts);
    } else {
        $tgl_formatted = '-';
    }

    $total_item_qty = 0;
    foreach($items as $it) { $total_item_qty += $it['jumlah']; }
?>
    <div class="document-container page-break">
        
        <!-- HEADER -->
        <div class="flex items-center mb-6 pt-20 pb-2">
            <div class="w-1/4">
                <img src="logo_scs.png" class="h-28 w-auto object-contain">
            </div>
            <div class="w-1/2 text-center">
                <h1 class="text-xl font-bold uppercase tracking-wide">PT. SURYA CERAH SEMESTA</h1>
                <p class="text-[10px] leading-tight mt-1">
                    Jl. Pemuda No. 83, Kutabanjarnegara, Kec. Banjarnegara,<br>
                    Kab. Banjarnegara, Jawa Tengah, 53471
                </p>
            </div>
            <div class="w-1/4 text-right self-start pt-2">
                <span class="text-[10px] text-gray-400">Hal. <?= $current_page ?>/<?= $total_pages ?></span>
            </div>
        </div>
        <hr class="border-t-2 border-black mb-4">

        <div class="text-center my-4">
            <h2 class="text-sm font-bold border-b border-black inline-block px-4">FORMULIR PENGIRIMAN BARANG</h2>
        </div>

        <!-- METADATA BOX -->
        <div class="metadata-box">
            <div class="metadata-col">
                <table class="metadata-table">
                    <tr>
                        <td class="w-20">NO</td>
                        <td class="w-4">:</td>
                        <td class="font-bold"><?= $display_no_sj ?></td>
                    </tr>
                    <tr>
                        <td>GUDANG</td>
                        <td>:</td>
                        <td>SCS-BNA</td>
                    </tr>
                    <tr>
                        <td>TOTAL ITEM</td>
                        <td>:</td>
                        <td><?= count($items) ?> Item (<?= $total_item_qty ?> qty)</td>
                    </tr>
                    <tr>
                        <td>TUJUAN</td>
                        <td>:</td>
                        <td class="font-bold"><?= htmlspecialchars($nama_tujuan) ?></td>
                    </tr>
                </table>
            </div>
            <div class="metadata-col">
                <table class="metadata-table">
                    <tr>
                        <td class="w-20">NOPOL</td>
                        <td class="w-4">:</td>
                        <td class="font-bold">
                            <?php 
                                $disp_nopol = !empty($pesanan['nopol_driver']) && $pesanan['nopol_driver'] !== '-' ? $pesanan['nopol_driver'] : $def_nopol;
                                echo htmlspecialchars($disp_nopol);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>DRIVER</td>
                        <td>:</td>
                        <td class="font-bold">
                            <?php 
                                $disp_driver = !empty($pesanan['nama_driver']) && $pesanan['nama_driver'] !== '-' ? $pesanan['nama_driver'] : $def_driver;
                                echo htmlspecialchars($disp_driver);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>NO HP</td>
                        <td>:</td>
                        <td class="font-bold">
                            <?php 
                                $disp_hp = !empty($pesanan['no_hp_driver']) && $pesanan['no_hp_driver'] !== '-' ? $pesanan['no_hp_driver'] : $def_no_hp;
                                echo htmlspecialchars($disp_hp);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>TGL KIRIM</td>
                        <td>:</td>
                        <td><?= $tgl_formatted ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- TABLE BARANG -->
        <table class="table-sj">
            <thead>
                <tr>
                    <th rowspan="2" class="w-[5%] text-center">NO</th>
                    <th rowspan="2" class="w-[45%] text-left">NAMA BARANG</th>
                    <th rowspan="2" class="w-[15%] text-center">JUMLAH BARANG</th>
                    <th colspan="2" class="w-[35%] text-center">KETERANGAN</th>
                </tr>
                <tr>
                    <th class="w-[17.5%] text-center">SESUAI</th>
                    <th class="w-[17.5%] text-center">TIDAK SESUAI</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach($items as $row): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="font-medium"><?= htmlspecialchars($row['nama_barang']) ?></td>
                    <td class="text-center font-bold"><?= $row['jumlah'] ?> <?= strtoupper($row['satuan']) ?></td>
                    <td class="text-center">
                        <div class="checkbox-cell"></div>
                    </td>
                    <td class="text-center">
                        <div class="checkbox-cell"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- FOOTER SIGNATURES -->
        <div class="mt-20 flex justify-between px-4">
            <div class="w-1/3 text-center">
                <p class="text-[11px] mb-16">Admin Gudang</p>
                <div class="border-b border-black w-32 mx-auto mb-1"></div>
                <p class="text-[10px] text-gray-500">PT. SCS</p>
            </div>
            <div class="w-1/3 text-center">
                <p class="text-[11px] mb-16">Driver</p>
                <div class="border-b border-black w-32 mx-auto mb-1"></div>
                <p class="text-[10px] text-gray-500">Nama Terang</p>
            </div>
            <div class="w-1/3 text-center">
                <p class="text-[11px] mb-16">Penerima</p>
                <div class="border-b border-black w-32 mx-auto mb-1"></div>
                <p class="text-[10px] text-gray-500">Nama & Stempel</p>
            </div>
        </div>

        <div class="absolute bottom-6 left-10 right-10 text-[8px] text-gray-400 text-center uppercase tracking-widest border-t pt-2">
            Dokumen ini dicetak oleh sistem dan merupakan bukti resmi pengiriman barang PT. Surya Cerah Semesta
        </div>

    </div>
<?php endforeach; ?>

    <!-- ACTION BUTTONS -->
    <div id="action-buttons" style="text-align: center; margin: 30px auto; max-width: 600px;">
        <button onclick="window.print()" style="
            background: #1e3a5f; color: white; border: none; padding: 12px 30px; 
            font-size: 14px; font-weight: bold; border-radius: 6px; cursor: pointer; 
            margin: 5px; min-width: 200px;
        ">
            Cetak Formulir (<?= $total_pages ?> Hal)
        </button>
        <?php if ($role_user === 'dapur'): ?>
        <a href="dapur.php" style="
            display: inline-block; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; 
            padding: 10px 30px; font-size: 14px; font-weight: bold; border-radius: 6px; 
            text-decoration: none; margin: 5px; min-width: 200px;
        ">
            Kembali
        </a>
        <?php else: ?>
        <a href="laporanPenjualan.php" style="
            display: inline-block; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; 
            padding: 10px 30px; font-size: 14px; font-weight: bold; border-radius: 6px; 
            text-decoration: none; margin: 5px; min-width: 200px;
        ">
            Kembali
        </a>
        <?php endif; ?>
    </div>

</body>
</html>
