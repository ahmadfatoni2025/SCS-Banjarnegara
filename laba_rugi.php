<?php
session_start();
include 'koneksi.php'; 
include 'fungsi_akuntansi.php'; 

// --- 1. CEK LOGIN ---
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
if (!in_array($role_user, ['admin', 'owner', 'akuntan'])) {
    echo "<script>alert('Akses Ditolak!'); window.location='dashboard.php';</script>"; exit;
}

// --- 2. FILTER PERIODE ---
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// --- 3. LOGIKA HITUNG LABA RUGI (MENGGUNAKAN ENGINE TERPUSAT) ---
$pendapatan = [];
$hpp        = [];
$beban_ops  = [];

$total_pendapatan = 0;
$total_hpp        = 0;
$total_beban_ops  = 0;

$q_akun = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE LEFT(kode_akun, 1) IN ('4', '5', '6') ORDER BY kode_akun ASC");
while($row = mysqli_fetch_assoc($q_akun)) {
    $val = getAccountBalance($koneksi, $row['kode_akun'], $tgl_selesai, $tgl_mulai);
    
    if ($val == 0) continue;
    
    $kategori_utama = substr($row['kode_akun'], 0, 1);
    
    if ($kategori_utama == '4') {
        $pendapatan[] = ['kode' => $row['kode_akun'], 'nama' => $row['nama_akun'], 'nilai' => $val];
        $total_pendapatan += $val;
    } 
    elseif ($row['kode_akun'] == '5111' || $row['kode_akun'] == '6111') {
        // HPP dan Potongan Pembelian
        $display_val = ($row['kode_akun'] == '6111') ? -$val : $val;
        $hpp[] = ['kode' => $row['kode_akun'], 'nama' => $row['nama_akun'], 'nilai' => $display_val];
        $total_hpp += $display_val;
    }
    else {
        // Beban Operasional lainnya (Kepala 5 selain 5111)
        $beban_ops[] = ['kode' => $row['kode_akun'], 'nama' => $row['nama_akun'], 'nilai' => $val];
        $total_beban_ops += $val;
    }
}

// HITUNG BERJENJANG
// 1. Laba Kotor = Pendapatan - HPP
$laba_kotor = $total_pendapatan - $total_hpp;

// 2. Laba Bersih = Laba Kotor - Beban Operasional
$laba_bersih = $laba_kotor - $total_beban_ops;


// TENTUKAN STATUS & WARNA
if ($laba_bersih > 0) {
    $status_laba = 'LABA BERSIH';
    $warna_status = 'text-emerald-700';
    $bg_status = 'bg-emerald-50 border-emerald-200';
    $icon_laba = 'fa-arrow-trend-up';
    $warna_icon = 'text-emerald-600';
} elseif ($laba_bersih < 0) {
    $status_laba = 'RUGI BERSIH';
    $warna_status = 'text-red-700';
    $bg_status = 'bg-red-50 border-red-200';
    $icon_laba = 'fa-arrow-trend-down';
    $warna_icon = 'text-red-600';
} else {
    $status_laba = 'IMPAS';
    $warna_status = 'text-gray-700';
    $bg_status = 'bg-gray-50 border-gray-200';
    $icon_laba = 'fa-minus';
    $warna_icon = 'text-gray-600';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba Rugi - Sistem Keuangan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f8; /* Soft background matching the image */
            color: #1e293b;
        }
        
        .main-container { margin-left: 16rem; min-height: 100vh; transition: margin-left 0.3s ease; }
        @media (max-width: 1024px) { .main-container { margin-left: 0; } }
        
        .dashboard-card {
            background: white; 
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
        }
        
        /* Custom scrollbar for tables */
        .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="antialiased">

    <?php include 'sidebar.php'; ?>

    <div class="main-container">
        <!-- Top Navigation / Filter Area -->
        <div class="bg-white border-b border-slate-200 px-4 md:px-8 py-4 flex flex-col lg:flex-row justify-between items-start lg:items-center gap- top-0 z-10">
                      <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-[#3b82f6] to-[#1d4ed8] flex items-center justify-center shadow-lg shadow-blue-200/50 text-white">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-800 tracking-tight">Laba Rugi</h1>
                    <p class="text-[11px] font-bold text-slate-400">Periode Laba Rugi per <?= date('d F Y', strtotime($tgl_mulai)) ?> - <?= date('d F Y', strtotime($tgl_selesai)) ?></p>
                </div>
            </div>
            
            <form method="GET" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto">
                <div class="flex items-center justify-between sm:justify-start bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 flex-1 sm:flex-none">
                    <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="bg-transparent text-xs md:text-sm font-medium text-slate-700 outline-none w-[110px] md:w-32 cursor-pointer">
                    <span class="text-slate-300 mx-1 md:mx-2">/</span>
                    <input type="date" name="tgl_selesai" value="<?= $tgl_selesai ?>" class="bg-transparent text-xs md:text-sm font-medium text-slate-700 outline-none w-[110px] md:w-32 cursor-pointer">
                </div>
                <button type="submit" class="bg-[#0e9f6e] hover:bg-[#057a55] text-white px-5 py-2.5 sm:py-2 rounded-xl text-sm font-bold transition-colors shadow-sm whitespace-nowrap"><i class="fas fa-search mr-1"></i> Filter</button>
            </form>
        </div>

        <div class="p-6 lg:p-8 max-w-[1600px] mx-auto min-h-screen font-mono">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Card 1: Pendapatan -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_4px_20px_rgba(0,0,0,0.03)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center">
                            <i class="fas fa-coins text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Total Pendapatan</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-[28px] font-black text-slate-800 tracking-tight font-mono"><?= formatRupiah($total_pendapatan) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-emerald-600 mt-3">+ Total kotor periode ini</p>
                </div>
                
                <!-- Card 2: HPP -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_4px_20px_rgba(0,0,0,0.03)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Harga Pokok Penjualan</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-2xl xl:text-3xl font-black text-slate-800 tracking-tight font-mono"><?= formatRupiah($total_hpp) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-rose-600 mt-3">- Pengurang pendapatan</p>
                </div>
                
                <!-- Card 3: Laba Kotor -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_4px_20px_rgba(0,0,0,0.03)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center">
                            <i class="fas fa-piggy-bank text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Laba Kotor</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-2xl xl:text-3xl font-black text-slate-800 tracking-tight font-mono"><?= formatRupiah($laba_kotor) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-emerald-600 mt-3">+ Pendapatan dikurangi HPP</p>
                </div>

                <!-- Card 4: Beban Ops -->
                <div class="bg-white rounded-3xl p-6 shadow-[0_4px_20px_rgba(0,0,0,0.03)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center">
                            <i class="fas fa-chart-line text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Total Beban Ops</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-2xl xl:text-3xl font-black text-slate-800 tracking-tight font-mono"><?= formatRupiah($total_beban_ops) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-emerald-600 mt-3">+ Biaya operasional berjalan</p>
                </div>
            </div>

            <!-- Main 2-Column Layout -->
            <div class="flex flex-col xl:flex-row gap-6">
                
                <!-- LEFT COLUMN (Transactions Cards) - 65% -->
                <div class="xl:w-[65%] flex flex-col md:flex-row gap-6 items-stretch">
                    
                    <!-- Kiri Dalam (Pendapatan & HPP) -->
                    <div class="flex-1 flex flex-col gap-6">
                        
                        <!-- PENDAPATAN CARD -->
                        <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col">
                            <div class="bg-[#0f8b58] p-5 flex justify-between items-center text-white">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-plus"></i></div>
                                    <div>
                                        <h3 class="text-[16px] font-bold">Pendapatan</h3>
                                        <p class="text-[11px] opacity-80">Pemasukan Usaha</p>
                                    </div>
                                </div>
                                <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($pendapatan) ?> Akun</span>
                            </div>
                            <div class="p-0 flex-1 min-h-[200px] max-h-[300px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                                <?php foreach($pendapatan as $p): ?>
                                <div class="p-4 flex justify-between items-center hover:bg-slate-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-[#10b981]"></div>
                                        <div>
                                            <p class="text-[13px] font-bold text-slate-700"><?= $p['nama'] ?></p>
                                            <p class="text-[10px] text-slate-400">Kode: <?= $p['kode'] ?></p>
                                        </div>
                                    </div>
                                    <span class="text-[14px] font-bold text-[#0f8b58]">Rp <?= number_format($p['nilai'], 0, ',', '.') ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center mt-auto">
                                <span class="text-[13px] font-black text-slate-800">TOTAL PENDAPATAN</span>
                                <span class="text-[16px] font-black text-[#0f8b58]">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <!-- HPP CARD -->
                        <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col">
                            <div class="bg-[#b4710b] p-5 flex justify-between items-center text-white">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-box"></i></div>
                                    <div>
                                        <h3 class="text-[16px] font-bold">HPP</h3>
                                        <p class="text-[11px] opacity-80">Harga Pokok Penjualan</p>
                                    </div>
                                </div>
                                <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($hpp) ?> Akun</span>
                            </div>
                            <div class="p-0 flex-1 min-h-[200px] max-h-[300px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                                <?php foreach($hpp as $h): ?>
                                <div class="p-4 flex justify-between items-center hover:bg-slate-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-[#f59e0b]"></div>
                                        <div>
                                            <p class="text-[13px] font-bold text-slate-700"><?= $h['nama'] ?></p>
                                            <p class="text-[10px] text-slate-400">Kode: <?= $h['kode'] ?></p>
                                        </div>
                                    </div>
                                    <span class="text-[14px] font-bold text-[#b4710b]">Rp <?= number_format($h['nilai'], 0, ',', '.') ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center mt-auto">
                                <span class="text-[13px] font-black text-slate-800">TOTAL HPP</span>
                                <span class="text-[16px] font-black text-[#b4710b]">( Rp <?= number_format(abs($total_hpp), 0, ',', '.') ?> )</span>
                            </div>
                        </div>

                    </div>

                    <!-- Kanan Dalam (Beban Operasional) -->
                    <div class="flex-1 flex flex-col h-full">
                        
                        <!-- BEBAN OPERASIONAL CARD -->
                        <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col h-full">
                            <div class="bg-[#d32f2f] p-5 flex justify-between items-center text-white shrink-0">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-minus"></i></div>
                                    <div>
                                        <h3 class="text-[16px] font-bold">Beban Operasional</h3>
                                        <p class="text-[11px] opacity-80">Biaya Rutin & Lainnya</p>
                                    </div>
                                </div>
                                <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($beban_ops) ?> Akun</span>
                            </div>
                            <div class="p-0 flex-1 min-h-[200px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                                <?php foreach($beban_ops as $b): ?>
                                <div class="p-4 flex justify-between items-center hover:bg-slate-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-[#ef4444]"></div>
                                        <div>
                                            <p class="text-[13px] font-bold text-slate-700"><?= $b['nama'] ?></p>
                                            <p class="text-[10px] text-slate-400">Kode: <?= $b['kode'] ?></p>
                                        </div>
                                    </div>
                                    <span class="text-[14px] font-bold text-[#d32f2f]">Rp <?= number_format($b['nilai'], 0, ',', '.') ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center shrink-0 mt-auto">
                                <span class="text-[13px] font-black text-slate-800">TOTAL BEBAN OPS</span>
                                <span class="text-[16px] font-black text-[#d32f2f]">( Rp <?= number_format(abs($total_beban_ops), 0, ',', '.') ?> )</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- RIGHT COLUMN (Statistics & Sidebar) - 35% -->
                <div class="xl:w-[35%] flex flex-col gap-6">
                    
                    <!-- Blue Card (Laba Bersih) -->
                    <div class="bg-gradient-to-br from-[#3b82f6] to-[#1d4ed8] rounded-[24px] p-6 text-white shadow-lg shadow-blue-200/50 relative overflow-hidden">
                        <!-- Abstract shapes for credit card look -->
                        <div class="absolute -right-10 -top-10 w-40 h-40 border-[8px] border-white/10 rounded-full"></div>
                        <div class="absolute right-6 top-6 w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center border border-white/30">
                            <div class="w-3 h-3 bg-white rounded-full"></div>
                        </div>
                        <div class="absolute left-6 top-6 opacity-50">
                            <svg width="32" height="24" viewBox="0 0 32 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="32" height="24" rx="4" fill="currentColor"/>
                                <rect x="4" y="6" width="8" height="12" rx="2" fill="#3b82f6"/>
                                <line x1="16" y1="8" x2="28" y2="8" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                                <line x1="16" y1="12" x2="24" y2="12" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                                <line x1="16" y1="16" x2="26" y2="16" stroke="#3b82f6" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        
                        <div class="relative z-10 mt-10">
                            <p class="text-[11px] font-medium text-blue-100 mb-1 opacity-80 tracking-wide">LABA BERSIH (NET PROFIT)</p>
                            <h2 class="text-[28px] font-black tracking-tight mb-8"><?= formatRupiah($laba_bersih) ?></h2>
                            
                            <div class="flex justify-between items-end">
                                <div>
                                    <p class="text-[9px] font-bold text-blue-200 uppercase tracking-widest mb-1 opacity-80">RUMUS LABA BERSIH</p>
                                    <p class="text-[11px] font-bold">Laba Kotor - Beban Ops</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[9px] font-bold text-blue-200 uppercase tracking-widest mb-1 opacity-80">STATUS</p>
                                    <p class="text-[13px] font-bold"><?= $laba_bersih >= 0 ? 'LABA' : 'RUGI' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <button onclick="window.print()" class="flex flex-col items-center justify-center gap-2 p-4 bg-white border border-slate-100 rounded-[20px] shadow-[0_2px_10px_rgba(0,0,0,0.02)] hover:shadow-md transition-all hover:bg-slate-50 group">
                            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center text-lg"><i class="fas fa-print"></i></div>
                            <span class="text-[12px] font-bold text-slate-700">Cetak PDF</span>
                        </button>
                        <a href="export_laba_rugi.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>" class="flex flex-col items-center justify-center gap-2 p-4 bg-white border border-slate-100 rounded-[20px] shadow-[0_2px_10px_rgba(0,0,0,0.02)] hover:shadow-md transition-all hover:bg-slate-50 group cursor-pointer text-center">
                            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg"><i class="fas fa-file-excel"></i></div>
                            <span class="text-[12px] font-bold text-slate-700">Export Excel</span>
                        </a>
                    </div>

                    <!-- Statistics Donut -->
                    <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)]">
                        <div class="flex justify-between items-center mb-8">
                            <h3 class="text-[15px] font-bold text-slate-800">Analisis Beban</h3>
                        </div>
                        
                        <!-- CSS Pie Chart representation -->
                        <?php 
                        $tot_beban_all = $total_hpp + $total_beban_ops;
                        $hpp_pct = $tot_beban_all > 0 ? ($total_hpp / $tot_beban_all) * 100 : 50;
                        $ops_pct = $tot_beban_all > 0 ? ($total_beban_ops / $tot_beban_all) * 100 : 50;
                        ?>
                        <div class="relative w-40 h-40 mx-auto mb-6 rounded-full shadow-[inset_0_2px_10px_rgba(0,0,0,0.1)] border-[4px] border-white" style="background: conic-gradient(#1d4ed8 0% <?= $hpp_pct ?>%, #93c5fd <?= $hpp_pct ?>% 100%);">
                        </div>
                        
                        <div class="text-center mb-8">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Total Beban</span>
                            <span class="text-2xl font-black text-slate-800"><?= formatRupiah($tot_beban_all) ?></span>
                        </div>

                        <div class="space-y-4 px-2">
                            <div class="flex justify-between items-center text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-4 h-4 rounded-[4px] bg-[#1d4ed8]"></div>
                                    <span class="font-bold text-slate-500">HPP</span>
                                    <span class="text-[10px] font-bold text-slate-400 opacity-60">• <?= round($hpp_pct) ?>%</span>
                                </div>
                                <span class="font-bold text-slate-800"><?= formatRupiah($total_hpp) ?></span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-4 h-4 rounded-[4px] bg-[#93c5fd]"></div>
                                    <span class="font-bold text-slate-500">Operasional</span>
                                    <span class="text-[10px] font-bold text-slate-400 opacity-60">• <?= round($ops_pct) ?>%</span>
                                </div>
                                <span class="font-bold text-slate-800"><?= formatRupiah($total_beban_ops) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity (Pendapatan Terbesar) Timeline -->
                    <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] flex-1">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-[15px] font-bold text-slate-800">Pendapatan Utama</h3>
                            <i class="fas fa-ellipsis-v text-slate-400 text-sm"></i>
                        </div>
                        
                        <p class="text-[11px] font-bold text-slate-800 mb-6">Periode Ini</p>
                        
                        <div class="space-y-6 relative before:absolute before:inset-0 before:ml-4 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-[2px] before:bg-slate-100">
                            <?php 
                            $top_pendapatan = array_slice($pendapatan, 0, 5);
                            foreach($top_pendapatan as $p):
                            ?>
                            <div class="relative flex items-start justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                                <!-- Icon -->
                                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-[#f0fdf4] text-[#16a34a] shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-[0_0_0_4px_white] relative z-10">
                                    <i class="fas fa-arrow-down text-[10px]"></i>
                                </div>
                                <!-- Content -->
                                <div class="w-[calc(100%-3rem)] md:w-[calc(50%-1.5rem)] pt-1">
                                    <h4 class="text-[12px] font-bold text-slate-800 truncate mb-1 group-hover:text-[#16a34a] transition-colors"><?= $p['nama'] ?></h4>
                                    <p class="text-[10px] font-bold text-slate-400 mb-2">Kode Akun: <?= $p['kode'] ?></p>
                                    <span class="text-[12px] font-black text-slate-800">+<?= formatRupiah($p['nilai']) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function handleResize() {
            const mainContainer = document.querySelector('.main-container');
            if (window.innerWidth <= 1024) {
                mainContainer.style.marginLeft = '0';
            } else {
                mainContainer.style.marginLeft = '16rem';
            }
        }
        window.addEventListener('resize', handleResize);
        handleResize();
    </script>
</body>
</html>
