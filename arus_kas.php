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

// 3. LOGIKA ARUS KAS (REFACTORED)
// 3.1 Saldo Awal Kas (Prepared Statement)
$stmt_awal = $koneksi->prepare("SELECT SUM(debit) as d, SUM(kredit) as k FROM jurnal_umum WHERE kode_akun LIKE '111%' AND tanggal < ?");
$stmt_awal->bind_param("s", $tgl_mulai);
$stmt_awal->execute();
$r_awal = $stmt_awal->get_result()->fetch_assoc();
$saldo_awal_kas = ($r_awal['d'] ?? 0) - ($r_awal['k'] ?? 0);

// 3.2 Ambil Semua Mutasi Kas (Masuk & Keluar) dalam satu query + Join Akun Lawan
$query_mutasi = "
    SELECT 
        j.*, 
        GROUP_CONCAT(DISTINCT a.nama_akun SEPARATOR ', ') as akun_lawan,
        GROUP_CONCAT(DISTINCT a.kategori SEPARATOR ', ') as kategori_lawan
    FROM jurnal_umum j
    LEFT JOIN jurnal_umum j2 ON j.no_reff = j2.no_reff AND j.tanggal = j2.tanggal AND j.kode_akun != j2.kode_akun
    LEFT JOIN akun_coa a ON j2.kode_akun = a.kode_akun
    WHERE j.kode_akun LIKE '111%' AND (j.tanggal BETWEEN ? AND ?)
    GROUP BY j.id
    ORDER BY j.tanggal ASC, j.id ASC
";
$stmt_mutasi = $koneksi->prepare($query_mutasi);
$stmt_mutasi->bind_param("ss", $tgl_mulai, $tgl_selesai);
$stmt_mutasi->execute();
$res_mutasi = $stmt_mutasi->get_result();

$arus_masuk = []; $arus_keluar = [];
$total_masuk = 0; $total_keluar = 0;

while($row = $res_mutasi->fetch_assoc()) {
    if ($row['debit'] > 0) {
        $arus_masuk[] = $row;
        $total_masuk += $row['debit'];
    } elseif ($row['kredit'] > 0) {
        $arus_keluar[] = $row;
        $total_keluar += $row['kredit'];
    }
}

$arus_kas_bersih = $total_masuk - $total_keluar;
$saldo_akhir_kas = $saldo_awal_kas + $arus_kas_bersih;

// Helper untuk Badge Kategori
function getBadgeColor($kat) {
    $kat = strtolower(trim($kat));
    if ($kat == 'aset') return 'bg-blue-100 text-blue-700 border-blue-200';
    if ($kat == 'kewajiban') return 'bg-purple-100 text-purple-700 border-purple-200';
    if ($kat == 'modal') return 'bg-amber-100 text-amber-700 border-amber-200';
    if ($kat == 'pendapatan') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if ($kat == 'beban') return 'bg-rose-100 text-rose-700 border-rose-200';
    return 'bg-gray-100 text-gray-600 border-gray-200';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Arus Kas - Sistem Keuangan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f8; 
            color: #1e293b;
        }
        
        .main-container { margin-left: 16rem; min-height: 100vh; transition: margin-left 0.3s ease; }
        @media (max-width: 1024px) { .main-container { margin-left: 0; } }
        
        .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        @media print {
            .main-container { margin-left: 0 !important; }
            .sidebar { display: none !important; }
            .no-print { display: none !important; }
            body { background-color: white !important; }
        }
    </style>
</head>
<body class="antialiased">

    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <!-- Top Navigation / Filter Area -->
        <div class="bg-white border-b border-slate-200 px-4 md:px-8 py-4 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 no-print">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-[#3b82f6] to-[#1d4ed8] flex items-center justify-center shadow-lg shadow-blue-200/50 text-white">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-800 tracking-tight">Arus Kas</h1>
                    <p class="text-[11px] font-bold text-slate-400">Periode Arus Kas per <?= date('d F Y', strtotime($tgl_mulai)) ?> - <?= date('d F Y', strtotime($tgl_selesai)) ?></p>
                </div>
            </div>

            <!-- Filter Form -->
            <form method="GET" class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                <div class="flex items-center justify-between sm:justify-start bg-slate-50 border border-slate-200 rounded-[14px] px-3 py-2 flex-1 sm:flex-none">
                    <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="bg-transparent border-none outline-none text-[13px] font-bold text-slate-700 w-[110px] sm:w-auto">
                    <span class="text-slate-300 mx-2">/</span>
                    <input type="date" name="tgl_selesai" value="<?= $tgl_selesai ?>" class="bg-transparent border-none outline-none text-[13px] font-bold text-slate-700 w-[110px] sm:w-auto">
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white text-[13px] font-bold rounded-[14px] transition-colors flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-filter text-[10px]"></i> Terapkan
                </button>
                <button type="button" onclick="window.print()" class="w-full sm:w-auto px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-[13px] font-bold rounded-[14px] transition-colors flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-print text-[10px]"></i> Cetak
                </button>
            </form>
        </div>

        <div class="p-4 md:p-8 max-w-[1400px] mx-auto">
            
            <!-- Top Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                <!-- Card 1: Saldo Awal -->
                <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i class="fas fa-wallet text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Saldo Kas Awal</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-2xl xl:text-3xl font-black text-slate-800 tracking-tight truncate font-mono" title="<?= formatRupiah($saldo_awal_kas) ?>"><?= formatRupiah($saldo_awal_kas) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-blue-600 mt-3">+ Total sisa kas periode lalu</p>
                </div>

                <!-- Card 2: Penerimaan -->
                <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i class="fas fa-circle-arrow-down text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Total Penerimaan</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-2xl xl:text-3xl font-black text-slate-800 tracking-tight truncate font-mono" title="<?= formatRupiah($total_masuk) ?>"><?= formatRupiah($total_masuk) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-emerald-600 mt-3">+ Uang masuk periode ini</p>
                </div>

                <!-- Card 3: Pengeluaran -->
                <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center">
                            <i class="fas fa-circle-arrow-up text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Total Pengeluaran</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-2xl xl:text-3xl font-black text-slate-800 tracking-tight truncate font-mono" title="<?= formatRupiah($total_keluar) ?>"><?= formatRupiah($total_keluar) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-rose-600 mt-3">+ Uang keluar periode ini</p>
                </div>

                <!-- Card 4: Saldo Akhir -->
                <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                            <i class="fas fa-vault text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Saldo Kas Akhir</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-2xl xl:text-3xl font-black text-slate-800 tracking-tight truncate font-mono" title="<?= formatRupiah($saldo_akhir_kas) ?>"><?= formatRupiah($saldo_akhir_kas) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-purple-600 mt-3">+ Sisa kas saat ini</p>
                </div>
            </div>

            <!-- Net Change Footer (Dipindah Ke Atas Kartu Arus Kas) -->
            <div class="mb-8 rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.03)] border relative overflow-hidden <?= $arus_kas_bersih >= 0 ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100' ?>">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 <?= $arus_kas_bersih >= 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' ?>">
                            <i class="fas <?= $arus_kas_bersih >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?> text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-black <?= $arus_kas_bersih >= 0 ? 'text-emerald-800' : 'text-rose-800' ?> tracking-tight font-mono">Net Arus Kas (Periode Ini)</h3>
                            <p class="text-[12px] font-bold <?= $arus_kas_bersih >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> opacity-80 mt-1">Selisih antara kas masuk dan keluar bulan ini.</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-3xl font-black <?= $arus_kas_bersih >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> font-mono">
                            <?= $arus_kas_bersih >= 0 ? '+' : '' ?> <?= formatRupiah($arus_kas_bersih) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Kartu Arus Kas -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                
                <!-- PENERIMAAN CARD -->
                <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col">
                    <div class="bg-[#059669] p-5 flex justify-between items-center text-white shrink-0">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-circle-arrow-down"></i></div>
                            <div>
                                <h3 class="text-[16px] font-bold">Penerimaan Kas</h3>
                                <p class="text-[11px] opacity-80">Rincian uang masuk periode ini</p>
                            </div>
                        </div>
                        <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($arus_masuk) ?> Trx</span>
                    </div>
                    
                    <div class="p-0 flex-1 min-h-[300px] max-h-[600px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                        <?php if(empty($arus_masuk)): ?>
                            <div class="p-8 text-center text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-3 opacity-50"></i>
                                <p class="text-[13px] font-bold">Tidak ada transaksi masuk</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($arus_masuk as $item): ?>
                            <div class="p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center hover:bg-slate-50 transition-colors gap-3">
                                <div class="flex-1 min-w-0">
                                    <span class="text-[13px] font-bold text-slate-800 block leading-tight mb-1"><?= $item['keterangan'] ?></span>
                                    <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">Lawan: <?= $item['akun_lawan'] ?: '-' ?></span>
                                        <?php 
                                        $kats = explode(',', $item['kategori_lawan'] ?? '');
                                        foreach($kats as $kat): if(trim($kat)):
                                        ?>
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border <?= getBadgeColor($kat) ?> uppercase"><?= trim($kat) ?></span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= date('d M Y', strtotime($item['tanggal'])) ?> • <?= $item['no_reff'] ?></span>
                                </div>
                                <span class="text-[14px] font-bold text-[#059669] shrink-0">+ Rp <?= number_format($item['debit'], 0, ',', '.') ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center shrink-0 mt-auto">
                        <span class="text-[13px] font-black text-slate-800">TOTAL PENERIMAAN</span>
                        <span class="text-[18px] font-black text-[#059669]">Rp <?= number_format($total_masuk, 0, ',', '.') ?></span>
                    </div>
                </div>

                <!-- PENGELUARAN CARD -->
                <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col">
                    <div class="bg-[#e11d48] p-5 flex justify-between items-center text-white shrink-0">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-circle-arrow-up"></i></div>
                            <div>
                                <h3 class="text-[16px] font-bold">Pengeluaran Kas</h3>
                                <p class="text-[11px] opacity-80">Rincian uang keluar periode ini</p>
                            </div>
                        </div>
                        <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($arus_keluar) ?> Trx</span>
                    </div>
                    
                    <div class="p-0 flex-1 min-h-[300px] max-h-[600px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                        <?php if(empty($arus_keluar)): ?>
                            <div class="p-8 text-center text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-3 opacity-50"></i>
                                <p class="text-[13px] font-bold">Tidak ada transaksi keluar</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($arus_keluar as $item): ?>
                            <div class="p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center hover:bg-slate-50 transition-colors gap-3">
                                <div class="flex-1 min-w-0">
                                    <span class="text-[13px] font-bold text-slate-800 block leading-tight mb-1"><?= $item['keterangan'] ?></span>
                                    <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">Lawan: <?= $item['akun_lawan'] ?: '-' ?></span>
                                        <?php 
                                        $kats = explode(',', $item['kategori_lawan'] ?? '');
                                        foreach($kats as $kat): if(trim($kat)):
                                        ?>
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full border <?= getBadgeColor($kat) ?> uppercase"><?= trim($kat) ?></span>
                                        <?php endif; endforeach; ?>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"><?= date('d M Y', strtotime($item['tanggal'])) ?> • <?= $item['no_reff'] ?></span>
                                </div>
                                <span class="text-[14px] font-bold text-[#e11d48] shrink-0">- Rp <?= number_format($item['kredit'], 0, ',', '.') ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center shrink-0 mt-auto">
                        <span class="text-[13px] font-black text-slate-800">TOTAL PENGELUARAN</span>
                        <span class="text-[18px] font-black text-[#e11d48]">Rp <?= number_format($total_keluar, 0, ',', '.') ?></span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
