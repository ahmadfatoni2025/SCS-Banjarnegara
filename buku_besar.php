<?php
session_start();
include 'koneksi.php'; 
include 'fungsi_akuntansi.php'; 

// 1. CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
if (!in_array($role_user, ['admin', 'owner', 'akuntan'])) {
    echo "<script>alert('Akses Ditolak!'); window.location='dashboard.php';</script>"; exit;
}

// 2. FILTER
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$kode_akun   = isset($_GET['kode_akun']) ? $_GET['kode_akun'] : ''; 

// Ambil Daftar Akun untuk Dropdown
$q_akun_list = mysqli_query($koneksi, "SELECT * FROM akun_coa ORDER BY kode_akun ASC");
$daftar_akun = []; 
while($r = mysqli_fetch_assoc($q_akun_list)) { $daftar_akun[] = $r; }

// --- LOGIKA DATA BUKU BESAR ---
$data_buku_besar = [];

$where_akun = ($kode_akun != '') ? "WHERE kode_akun = '$kode_akun'" : "";
$q_target_akun = mysqli_query($koneksi, "SELECT * FROM akun_coa $where_akun ORDER BY kode_akun ASC");

while($akun = mysqli_fetch_assoc($q_target_akun)) {
    $kd = $akun['kode_akun'];
    
    // 1. Hitung Saldo Awal (Gunakan tanggal hari sebelumnya)
    $tgl_sebelum = date('Y-m-d', strtotime($tgl_mulai . ' -1 day'));
    $saldo_awal = getAccountBalance($koneksi, $kd, $tgl_sebelum);
    
    // Tentukan jenis akun (untuk display)
    $prefix = substr($kd, 0, 1);
    $is_debit = in_array($prefix, ['1', '5']);

    // 2. Ambil Transaksi (Gunakan Prepared Statement)
    $stmt_trans = $koneksi->prepare("SELECT * FROM jurnal_umum WHERE kode_akun = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
    $stmt_trans->bind_param("sss", $kd, $tgl_mulai, $tgl_selesai);
    $stmt_trans->execute();
    $res_trans = $stmt_trans->get_result();
    
    $transaksi = [];
    while($t = $res_trans->fetch_assoc()) {
        $transaksi[] = $t;
    }
    $stmt_trans->close();

    if ($saldo_awal != 0 || count($transaksi) > 0) {
        $data_buku_besar[] = [
            'info' => $akun,
            'saldo_awal' => $saldo_awal,
            'transaksi' => $transaksi,
            'is_debit' => $is_debit
        ];
    }
}

// Hitung Total Keseluruhan untuk Metric Cards
$grand_total_debit = 0;
$grand_total_kredit = 0;
foreach($data_buku_besar as $bb) {
    foreach($bb['transaksi'] as $t) {
        $grand_total_debit += $t['debit'];
        $grand_total_kredit += $t['kredit'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Besar - Sistem Keuangan</title>
      <link rel="icon" href="logo_scs_jpg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        :root {
            /* Warna Teal dari Palet Baru */
            --primary: #2d8a9d; 
            --primary-dark: #1a5f6b;
            --bg-page: #f8fafc;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg-page); color: #1e293b; line-height: 1.6; }
        
        .main-container { margin-left: 16rem; min-height: 100vh; transition: margin-left 0.3s ease; }
        @media (max-width: 1024px) { .main-container { margin-left: 0; } }

        .glass-card { 
            background: white; 
            border-radius: 24px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
        }

        .metric-card { 
            background: white; 
            border-radius: 20px; 
            padding: 1.5rem; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e2e8f0;
            position: relative;
            overflow: hidden;
        }
        .metric-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1); border-color: var(--primary); }
        
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 500; font-size: 0.875rem; transition: all 0.3s; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-light { background: white; color: #475569; border: 1px solid #e2e8f0; }
        .btn-light:hover { background: #f8fafc; border-color: #cbd5e1; }

        .container-optimized { max-width: 1600px; padding: 0 2rem; margin: 0 auto; }
        .grid-optimized { display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        
        .select2-container .select2-selection--single { height: 46px !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; display: flex; align-items: center; padding-left: 0.5rem; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 44px !important; }
    </style>
</head>
<body class="antialiased">

    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <div class="content-wrapper">
            <div class="container-optimized py-8">
                
                <div class="mb-10 flex flex-col md:flex-row justify-between items-start md:items-end gap-6">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Buku Besar</h1>
                        <p class="text-slate-500 mt-2">Rincian mutasi debit & kredit per akun periode ini.</p>
                    </div>
                    
                    <div class="flex gap-3">
                    </div>
                </div>

                <div class="glass-card p-6 mb-10">
                    <form method="GET" class="flex flex-col lg:flex-row gap-5 items-end">
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2 tracking-wide">Pilih Akun</label>
                            <select name="kode_akun" class="select2-search w-full">
                                <option value="">-- SEMUA AKUN --</option>
                                <?php foreach($daftar_akun as $ak): ?>
                                    <option value="<?= $ak['kode_akun'] ?>" <?= ($kode_akun == $ak['kode_akun']) ? 'selected' : '' ?>>
                                        <?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-full lg:w-48">
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2 tracking-wide">Dari Tanggal</label>
                            <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-[#2d8a9d] focus:border-[#2d8a9d] px-4 py-2.5 text-sm border bg-slate-50">
                        </div>
                        <div class="w-full lg:w-48">
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-2 tracking-wide">Sampai Tanggal</label>
                            <input type="date" name="tgl_selesai" value="<?= $tgl_selesai ?>" class="w-full border-gray-200 rounded-xl shadow-sm focus:ring-[#2d8a9d] focus:border-[#2d8a9d] px-4 py-2.5 text-sm border bg-slate-50">
                        </div>
                        <div class="flex gap-2 w-full lg:w-auto">
                            <button type="submit" class="btn btn-primary w-full lg:w-auto justify-center">
                                <i class="fas fa-search"></i> Cari
                            </button>
                            <a href="buku_besar.php" class="btn btn-light w-full lg:w-auto justify-center" title="Reset">
                                <i class="fas fa-redo-alt"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <!-- Metric Card 1: Total Akun -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-1.5 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                Total Akun Tampil
                                <i class="fas fa-info-circle text-[10px] opacity-50"></i>
                            </div>
                            <button class="text-slate-300 hover:text-slate-500 transition-colors"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <h3 class="text-2xl xl:text-3xl font-extrabold text-slate-800 tracking-tight font-mono whitespace-nowrap truncate" title="<?= count($data_buku_besar) ?>"><?= count($data_buku_besar) ?></h3>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="bg-cyan-50 text-[#2d8a9d] px-2 py-0.5 rounded-full text-[10px] font-bold">Active Akun</span>
                            <span class="text-slate-400 text-[10px]">periode terpilih</span>
                        </div>
                    </div>

                    <!-- Metric Card 2: Total Debit -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-1.5 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                Total Mutasi Debit
                                <i class="fas fa-info-circle text-[10px] opacity-50"></i>
                            </div>
                            <button class="text-slate-300 hover:text-slate-500 transition-colors"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <h3 class="text-2xl xl:text-3xl font-extrabold text-slate-800 tracking-tight font-mono whitespace-nowrap truncate" title="<?= formatRupiah($grand_total_debit) ?>"><?= formatRupiah($grand_total_debit) ?></h3>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1">
                                <i class="fas fa-arrow-up"></i> Inflow
                            </span>
                            <span class="text-slate-400 text-[10px]">vs total mutasi</span>
                        </div>
                    </div>

                    <!-- Metric Card 3: Total Kredit -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-1.5 text-slate-500 font-bold text-xs uppercase tracking-wider">
                                Total Mutasi Kredit
                                <i class="fas fa-info-circle text-[10px] opacity-50"></i>
                            </div>
                            <button class="text-slate-300 hover:text-slate-500 transition-colors"><i class="fas fa-ellipsis-h"></i></button>
                        </div>
                        <h3 class="text-2xl xl:text-3xl font-extrabold text-slate-800 tracking-tight font-mono whitespace-nowrap truncate" title="<?= formatRupiah($grand_total_kredit) ?>"><?= formatRupiah($grand_total_kredit) ?></h3>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="bg-rose-50 text-rose-600 px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1">
                                <i class="fas fa-arrow-down"></i> Outflow
                            </span>
                            <span class="text-slate-400 text-[10px]">vs total mutasi</span>
                        </div>
                    </div>
                </div>

                <!-- Cards Grid (Bento Masonry Layout) -->
                <div class="columns-1 xl:columns-2 gap-6 space-y-6 relative z-0">
                    <?php if(empty($data_buku_besar)): ?>
                        <div class="col-span-full py-20 text-center bg-white border border-slate-200 rounded-2xl border-dashed break-inside-avoid">
                            <i class="fas fa-search text-5xl text-slate-300 mb-4"></i>
                            <h4 class="text-xl font-bold text-slate-700 mb-2">Tidak ada data ditemukan</h4>
                            <p class="text-slate-500 mb-6">Coba ubah filter periode atau pilih akun lain untuk melihat data transaksi.</p>
                        </div>
                    <?php else: ?>

                        <?php foreach($data_buku_besar as $bb): ?>
                        <div class="break-inside-avoid bg-white border border-slate-200 shadow-sm overflow-hidden rounded-lg mb-6">
                            <!-- Header -->
                            <div class="bg-[#e6f7f9] px-4 py-4 flex justify-between items-center text-[#2d8a9d] border-b border-[#c8eef3]">
                                <div class="flex items-center gap-3 flex-1 min-w-0">
                                    <div class="bg-white/50 w-10 h-10 rounded-lg flex items-center justify-center text-[#2d8a9d] border border-[#c8eef3] flex-shrink-0">
                                        <i class="fas fa-wallet text-lg"></i>
                                    </div>
                                    <div class="flex-shrink-0 min-w-0">
                                        <div class="text-lg font-bold leading-none truncate"><?= $bb['info']['nama_akun'] ?></div>
                                        <div class="text-[11px] mt-1.5 text-[#5fb3c2] font-semibold opacity-90 flex items-center gap-2">
                                            <span class="bg-white px-1.5 py-0.5 rounded text-[10px] font-bold text-[#2d8a9d] border border-[#c8eef3] shadow-sm font-mono"><?= $bb['info']['kode_akun'] ?></span>
                                            <span class="opacity-50">•</span>
                                            <span class="truncate"><?= $bb['info']['kategori'] ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-white px-3 py-1.5 rounded-lg border border-[#c8eef3] text-right flex-shrink-0 ml-2 shadow-sm">
                                    <p class="text-[#5fb3c2] text-[9px] font-bold uppercase tracking-wider mb-0.5">Saldo Awal</p>
                                    <p class="text-[#2d8a9d] font-bold text-sm font-mono tracking-tight"><?= formatRupiah($bb['saldo_awal']) ?></p>
                                </div>
                            </div>

                            <!-- Table -->
                            <div class="w-full overflow-x-auto">
                                <?php if(empty($bb['transaksi'])): ?>
                                    <div class="p-8 text-center bg-slate-50">
                                        <p class="text-slate-400 text-xs italic">Hanya menampilkan Saldo Awal. Tidak ada pergerakan transaksi pada periode ini.</p>
                                    </div>
                                <?php else: ?>
                                <table class="w-full text-left border-collapse min-w-[700px]">
                                    <thead>
                                        <tr class="border-b border-slate-100 bg-slate-50/50">
                                            <th class="py-3 px-4 text-[10px] font-bold text-slate-800 uppercase tracking-wider w-[12%]">TANGGAL</th>
                                            <th class="py-3 px-4 text-[10px] font-bold text-slate-800 uppercase tracking-wider w-[15%]">NO. REF</th>
                                            <th class="py-3 px-4 text-[10px] font-bold text-slate-800 uppercase tracking-wider w-[25%]">KETERANGAN</th>
                                            <th class="py-3 px-4 text-[10px] font-bold text-slate-800 uppercase tracking-wider text-right w-[16%]">DEBIT</th>
                                            <th class="py-3 px-4 text-[10px] font-bold text-slate-800 uppercase tracking-wider text-right w-[16%]">KREDIT</th>
                                            <th class="py-3 px-4 text-[10px] font-bold text-slate-800 uppercase tracking-wider text-right w-[16%] bg-slate-100/50">SALDO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Saldo Awal Row -->
                                        <tr class="border-b border-slate-100 bg-amber-50/40">
                                            <td class="py-3 px-4 text-slate-400 text-[11px]">-</td>
                                            <td class="py-3 px-4 text-slate-400 text-[11px]">-</td>
                                            <td class="py-3 px-4 font-bold text-amber-700 text-[11px]">SALDO AWAL PERIODE</td>
                                            <td class="py-3 px-4 text-right text-slate-300">-</td>
                                            <td class="py-3 px-4 text-right text-slate-300">-</td>
                                            <td class="py-3 px-4 text-right font-bold text-amber-700 font-mono text-[11px] bg-amber-50/60 whitespace-nowrap"><?= formatRupiah($bb['saldo_awal']) ?></td>
                                        </tr>

                                        <?php 
                                        $saldo_run = $bb['saldo_awal'];
                                        $sub_debit = 0;
                                        $sub_kredit = 0;
                                        
                                        foreach($bb['transaksi'] as $trx): 
                                            if ($bb['is_debit']) $saldo_run += ($trx['debit'] - $trx['kredit']);
                                            else $saldo_run += ($trx['kredit'] - $trx['debit']);
                                            $sub_debit += $trx['debit'];
                                            $sub_kredit += $trx['kredit'];
                                        ?>
                                        <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors group">
                                            <td class="py-3 px-4 text-slate-600 text-[11px] whitespace-nowrap font-medium">
                                                <?= date('d M Y', strtotime($trx['tanggal'])) ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-[10px] font-mono border border-slate-200"><?= $trx['no_reff'] ?></span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-700 text-[11px] break-words whitespace-normal leading-tight">
                                                <?= $trx['keterangan'] ?>
                                            </td>
                                            <td class="py-3 px-4 text-right text-[11px] <?= $trx['debit']>0 ? 'text-[#0f9d58] font-bold bg-[#f4fcf9]/50' : 'text-slate-300' ?>">
                                                <span class="whitespace-nowrap"><?= ($trx['debit']>0) ? formatRupiah($trx['debit']) : '-' ?></span>
                                            </td>
                                            <td class="py-3 px-4 text-right text-[11px] <?= $trx['kredit']>0 ? 'text-[#dc2626] font-bold bg-[#fff5f5]/50' : 'text-slate-300' ?>">
                                                <span class="whitespace-nowrap"><?= ($trx['kredit']>0) ? formatRupiah($trx['kredit']) : '-' ?></span>
                                            </td>
                                            <td class="py-3 px-4 text-right font-bold text-slate-800 text-[11px] bg-slate-50/50 group-hover:bg-slate-100/50 whitespace-nowrap">
                                                <?= formatRupiah($saldo_run) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    
                                    <tfoot class="bg-slate-50 border-t border-slate-200">
                                        <tr>
                                            <td colspan="3" class="py-3 px-4 text-right text-[10px] font-bold text-slate-500 uppercase tracking-wider">TOTAL PERGERAKAN</td>
                                            <td class="py-3 px-4 text-right font-bold text-[#0f9d58] text-[11px] bg-emerald-50/50 border-t border-emerald-100 whitespace-nowrap">
                                                <?= formatRupiah($sub_debit) ?>
                                            </td>
                                            <td class="py-3 px-4 text-right font-bold text-[#dc2626] text-[11px] bg-rose-50/50 border-t border-rose-100 whitespace-nowrap">
                                                <?= formatRupiah($sub_kredit) ?>
                                            </td>
                                            <td class="py-3 px-4 text-right font-bold text-white text-[11px] bg-[#2d8a9d] whitespace-nowrap">
                                                <?= formatRupiah($saldo_run) ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-search').select2({ placeholder: "Pilih Akun / Semua Akun", width: '100%', dropdownCssClass: "text-sm text-slate-600" });
            const cards = document.querySelectorAll('.glass-card, .metric-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0'; card.style.transform = 'translateY(15px)';
                setTimeout(() => { card.style.transition = 'all 0.6s cubic-bezier(0.16, 1, 0.3, 1)'; card.style.opacity = '1'; card.style.transform = 'translateY(0)'; }, index * 50);
            });
            function handleResize() {
                const sidebar = document.querySelector('aside'); const mainContainer = document.querySelector('.main-container');
                if (window.innerWidth <= 1024) mainContainer.style.marginLeft = '0'; else if (sidebar) mainContainer.style.marginLeft = '16rem';
            }
            window.addEventListener('resize', handleResize); handleResize();
        });
    </script>
</body>
</html>
