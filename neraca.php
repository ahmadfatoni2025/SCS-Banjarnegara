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

// 2. FILTER TANGGAL (Neraca itu "Per Tanggal", bukan Periode)
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// 3. LOGIC HITUNG SALDO
// 3. LOGIC SUDAH DIPINDAHKAN KE fungsi_akuntansi.php (getAccountBalance)

// 4. AMBIL DATA AKUN & HITUNG
$aset = []; $kewajiban = []; $modal = [];
$total_aset = 0; $total_kewajiban = 0; $total_modal = 0;

$q_akun = mysqli_query($koneksi, "SELECT * FROM akun_coa ORDER BY kode_akun ASC");
while($row = mysqli_fetch_assoc($q_akun)) {
    $kategori = substr($row['kode_akun'], 0, 1);
    
    if ($kategori == '1') {
        $val = getAccountBalance($koneksi, $row['kode_akun'], $tgl_akhir);
        if($val != 0) { 
            $aset[] = ['nama'=>$row['nama_akun'], 'nilai'=>$val]; 
            $total_aset += $val; 
        }
    }
    elseif ($kategori == '2') {
        $val = getAccountBalance($koneksi, $row['kode_akun'], $tgl_akhir);
        if($val != 0) { 
            $kewajiban[] = ['nama'=>$row['nama_akun'], 'nilai'=>$val]; 
            $total_kewajiban += $val; 
        }
    }
    elseif ($kategori == '3') {
        // SKIP AKUN LABA BERJALAN (3121) karena akan dihitung manual di bawah
        if ($row['kode_akun'] == '3121') continue;
        
        $val = getAccountBalance($koneksi, $row['kode_akun'], $tgl_akhir);
        if($val != 0) { 
            $modal[] = ['nama'=>$row['nama_akun'], 'nilai'=>$val]; 
            $total_modal += $val; 
        }
    }
}

// 5. HITUNG LABA BERJALAN (MENGGUNAKAN ENGINE TERPUSAT)
$laba_berjalan = getNetIncome($koneksi, $tgl_akhir);

// Masukkan Laba Berjalan ke Modal
$total_modal += $laba_berjalan;

// Total Pasiva
$total_pasiva = $total_kewajiban + $total_modal;
$is_balance = (round($total_aset) == round($total_pasiva));
$selisih = abs($total_aset - $total_pasiva);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Neraca - Sistem Keuangan</title>
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
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-slate-800 tracking-tight">Neraca</h1>
                    <p class="text-[11px] font-bold text-slate-400">Posisi Keuangan per <?= date('d F Y', strtotime($tgl_akhir)) ?></p>
                </div>
            </div>

            <!-- Filter Form -->
            <form method="GET" class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto">
                <div class="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-[14px] border border-slate-100 w-full sm:w-auto">
                    <i class="fas fa-calendar text-slate-400 text-sm"></i>
                    <input type="date" name="tgl_akhir" value="<?= $tgl_akhir ?>" class="bg-transparent border-none outline-none text-[13px] font-bold text-slate-700 w-full">
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
            
            <!-- Balance Status Indicator -->
            <div class="mb-6 rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.03)] border relative overflow-hidden <?= $is_balance ? 'bg-emerald-50 border-emerald-100' : 'bg-red-50 border-red-100' ?>">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 <?= $is_balance ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600' ?>">
                            <i class="fas <?= $is_balance ? 'fa-check-double' : 'fa-exclamation-triangle' ?> text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-black <?= $is_balance ? 'text-emerald-800' : 'text-red-800' ?> tracking-tight">
                                <?= $is_balance ? 'Neraca Seimbang Sempurna' : 'Neraca Tidak Seimbang' ?>
                            </h3>
                            <p class="text-[12px] font-bold <?= $is_balance ? 'text-emerald-600' : 'text-red-600' ?> opacity-80 mt-1">
                                Persamaan Akuntansi: Aset = Kewajiban + Modal
                                <?php if (!$is_balance): ?>
                                    <span class="ml-2 px-2 py-0.5 bg-red-200 text-red-800 rounded-md">Selisih: <?= formatRupiah($selisih) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold <?= $is_balance ? 'text-emerald-500' : 'text-red-500' ?> uppercase tracking-widest mb-1">Total Aktiva / Pasiva</p>
                        <p class="text-2xl font-black <?= $is_balance ? 'text-emerald-700' : 'text-red-700' ?>"><?= formatRupiah(max($total_aset, $total_pasiva)) ?></p>
                    </div>
                </div>
            </div>

            <!-- Top Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Card 1: Aset -->
                <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i class="fas fa-wallet text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Total Aset (Aktiva)</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-[28px] font-black text-slate-800 tracking-tight"><?= formatRupiah($total_aset) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-blue-600 mt-3">+ Aset lancar dan tidak lancar</p>
                </div>

                <!-- Card 2: Kewajiban -->
                <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Total Kewajiban</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-[28px] font-black text-slate-800 tracking-tight"><?= formatRupiah($total_kewajiban) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-purple-600 mt-3">+ Hutang jangka pendek dan panjang</p>
                </div>

                <!-- Card 3: Modal -->
                <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 transition-transform hover:-translate-y-1">
                    <div class="flex items-center gap-2 mb-4 text-slate-400">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <i class="fas fa-landmark text-sm"></i>
                        </div>
                        <span class="text-[13px] font-bold">Total Modal</span>
                    </div>
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-[28px] font-black text-slate-800 tracking-tight"><?= formatRupiah($total_modal) ?></h3>
                    </div>
                    <p class="text-[11px] font-bold text-emerald-600 mt-3">+ Investasi pemilik dan laba ditahan</p>
                </div>
            </div>

            <!-- Main 2-Column Layout (Bento Grid) -->
            <div class="flex flex-col xl:flex-row gap-6 items-stretch">
                
                <!-- KIRI: Aset (Aktiva) -->
                <div class="flex-1 flex flex-col h-full">
                    <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col h-full">
                        <div class="bg-[#1d4ed8] p-5 flex justify-between items-center text-white shrink-0">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-wallet"></i></div>
                                <div>
                                    <h3 class="text-[16px] font-bold">Aktiva (Aset)</h3>
                                    <p class="text-[11px] opacity-80">Sumber daya yang dimiliki perusahaan</p>
                                </div>
                            </div>
                            <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($aset) ?> Akun</span>
                        </div>
                        <div class="p-0 flex-1 min-h-[300px] max-h-[600px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                            <?php if(empty($aset)): ?>
                                <div class="p-8 text-center text-slate-400">
                                    <i class="fas fa-inbox text-3xl mb-3 opacity-50"></i>
                                    <p class="text-[13px] font-bold">Tidak ada akun aset</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($aset as $item): ?>
                                <div class="p-4 flex justify-between items-center hover:bg-slate-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-[#3b82f6]"></div>
                                        <p class="text-[13px] font-bold text-slate-700"><?= $item['nama'] ?></p>
                                    </div>
                                    <span class="text-[14px] font-bold text-[#1d4ed8]">Rp <?= number_format($item['nilai'], 0, ',', '.') ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center shrink-0 mt-auto">
                            <span class="text-[13px] font-black text-slate-800">TOTAL AKTIVA</span>
                            <span class="text-[18px] font-black text-[#1d4ed8]">Rp <?= number_format($total_aset, 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>

                <!-- KANAN: Kewajiban & Modal (Pasiva) -->
                <div class="flex-1 flex flex-col gap-6">
                    
                    <!-- KEWAJIBAN -->
                    <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col">
                        <div class="bg-[#7e22ce] p-5 flex justify-between items-center text-white shrink-0">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-file-invoice-dollar"></i></div>
                                <div>
                                    <h3 class="text-[16px] font-bold">Kewajiban</h3>
                                    <p class="text-[11px] opacity-80">Klaim terhadap sumber daya</p>
                                </div>
                            </div>
                            <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($kewajiban) ?> Akun</span>
                        </div>
                        <div class="p-0 flex-1 min-h-[150px] max-h-[250px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                            <?php if(empty($kewajiban)): ?>
                                <div class="p-6 text-center text-slate-400">
                                    <p class="text-[12px] font-bold">Tidak ada akun kewajiban</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($kewajiban as $item): ?>
                                <div class="p-4 flex justify-between items-center hover:bg-slate-50 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full bg-[#a855f7]"></div>
                                        <p class="text-[13px] font-bold text-slate-700"><?= $item['nama'] ?></p>
                                    </div>
                                    <span class="text-[14px] font-bold text-[#7e22ce]">Rp <?= number_format($item['nilai'], 0, ',', '.') ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center shrink-0 mt-auto">
                            <span class="text-[13px] font-black text-slate-800">TOTAL KEWAJIBAN</span>
                            <span class="text-[16px] font-black text-[#7e22ce]">Rp <?= number_format($total_kewajiban, 0, ',', '.') ?></span>
                        </div>
                    </div>

                    <!-- MODAL -->
                    <div class="bg-white rounded-[24px] shadow-[0_4px_20px_rgba(0,0,0,0.02)] border border-slate-100 overflow-hidden flex flex-col">
                        <div class="bg-[#047857] p-5 flex justify-between items-center text-white shrink-0">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-lg"><i class="fas fa-landmark"></i></div>
                                <div>
                                    <h3 class="text-[16px] font-bold">Modal</h3>
                                    <p class="text-[11px] opacity-80">Investasi & Laba Ditahan</p>
                                </div>
                            </div>
                            <span class="bg-white/20 px-3 py-1 rounded-full text-[11px] font-bold"><?= count($modal) + 1 ?> Akun</span>
                        </div>
                        <div class="p-0 flex-1 min-h-[150px] max-h-[250px] overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                            <?php foreach($modal as $item): ?>
                            <div class="p-4 flex justify-between items-center hover:bg-slate-50 transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-[#10b981]"></div>
                                    <p class="text-[13px] font-bold text-slate-700"><?= $item['nama'] ?></p>
                                </div>
                                <span class="text-[14px] font-bold text-[#047857]">Rp <?= number_format($item['nilai'], 0, ',', '.') ?></span>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Laba Periode Berjalan -->
                            <div class="p-4 flex justify-between items-center bg-amber-50">
                                <div class="flex items-center gap-3">
                                    <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                                    <p class="text-[13px] font-bold text-slate-800">Laba Periode Berjalan</p>
                                </div>
                                <span class="text-[14px] font-black <?= ($laba_berjalan >= 0) ? 'text-emerald-600' : 'text-red-600' ?>">
                                    <?= ($laba_berjalan < 0 ? '-' : '') ?> Rp <?= number_format(abs($laba_berjalan), 0, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                        <div class="p-5 bg-slate-50 border-t border-slate-100 flex justify-between items-center shrink-0 mt-auto">
                            <span class="text-[13px] font-black text-slate-800">TOTAL MODAL</span>
                            <span class="text-[16px] font-black text-[#047857]">Rp <?= number_format($total_modal, 0, ',', '.') ?></span>
                        </div>
                        <!-- SUPER TOTAL PASIVA -->
                        <div class="p-5 bg-slate-800 border-t border-slate-700 flex justify-between items-center shrink-0">
                            <span class="text-[13px] font-black text-white">TOTAL PASIVA (KEWAJIBAN + MODAL)</span>
                            <span class="text-[18px] font-black text-white">Rp <?= number_format($total_pasiva, 0, ',', '.') ?></span>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</body>
</html>
