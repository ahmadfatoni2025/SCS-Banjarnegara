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

// 2. FILTER TAHUN
$tahun_aktif = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// 3. AMBIL DATA BULANAN
function fetchMonthlyStats($koneksi, $tahun) {
    $monthly = [];
    for ($m = 1; $m <= 12; $m++) {
        $tgl_awal = "$tahun-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";
        $tgl_akhir = date("Y-m-t", strtotime($tgl_awal));
        
        $sql = "SELECT 
                    SUM(CASE WHEN a.kategori = 'Pendapatan' THEN (j.kredit - j.debit) ELSE 0 END) as p,
                    SUM(CASE WHEN a.kategori = 'Beban' THEN (j.debit - j.kredit) ELSE 0 END) as b
                FROM jurnal_umum j
                JOIN akun_coa a ON j.kode_akun = a.kode_akun
                WHERE (j.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir') 
                AND j.no_reff NOT LIKE 'CLS%'";
        $res = mysqli_query($koneksi, $sql)->fetch_assoc();
        
        $monthly[] = [
            'label' => getBulanIndonesia(str_pad($m, 2, '0', STR_PAD_LEFT)),
            'p' => (float)($res['p'] ?? 0),
            'b' => (float)($res['b'] ?? 0),
            'l' => (float)($res['p'] ?? 0) - (float)($res['b'] ?? 0)
        ];
    }
    return $monthly;
}

$monthly_data = fetchMonthlyStats($koneksi, $tahun_aktif);

// 4. TOP EXPENSES
$sql_top_beban = "SELECT a.nama_akun, SUM(j.debit - j.kredit) as total 
                  FROM jurnal_umum j 
                  JOIN akun_coa a ON j.kode_akun = a.kode_akun 
                  WHERE a.kategori = 'Beban' AND j.tanggal LIKE '$tahun_aktif-%' 
                  AND j.no_reff NOT LIKE 'CLS%'
                  GROUP BY a.kode_akun ORDER BY total DESC LIMIT 5";
$res_top_beban = mysqli_query($koneksi, $sql_top_beban);
$top_beban_labels = [];
$top_beban_values = [];
while($rb = mysqli_fetch_assoc($res_top_beban)) {
    $top_beban_labels[] = $rb['nama_akun'];
    $top_beban_values[] = (float)$rb['total'];
}

// 5. INSIGHT CALCULATIONS
$max_laba = -INF;
$min_laba = INF;
$max_bulan = '';
$min_bulan = '';
$total_p_tahun = 0;
$total_b_tahun = 0;

foreach ($monthly_data as $m) {
    if ($m['l'] > $max_laba) { $max_laba = $m['l']; $max_bulan = $m['label']; }
    if ($m['l'] < $min_laba) { $min_laba = $m['l']; $min_bulan = $m['label']; }
    $total_p_tahun += $m['p'];
    $total_b_tahun += $m['b'];
}
$avg_laba = ($total_p_tahun - $total_b_tahun) / 12;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Visual - PT. SURYA CERAH SEMESTA</title>
    <!-- FAVICON LOGO -->
    <link rel="icon" href="logo_scs_jpg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap');
        
        * { font-family: 'google sans', sans-serif; }
        :root { --primary: #2563eb; --border: #e5e7eb; }
        .card-hover { transition: all 0.3s; border: 1px solid var(--border); }
        .card-hover:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .akuntan-badge {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            color: white;
        }
        
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
            border-radius: 0 0 0 100%;
        }

        .summary-card {
            background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        .summary-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        @media (max-width: 1024px) {
            .ml-64 { margin-left: 0 !important; }
        }
        
        @media print {
            .no-print { display: none !important; }
            .ml-64 { margin-left: 0 !important; padding: 0 !important; }
            .card-hover { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<?php include 'sidebar.php'; ?>

<div class="ml-64 flex flex-col min-h-screen transition-all duration-300">
    <main class="flex-1 p-6 md:p-8 bg-[#f8f9fa]">
        <div class="fade-in max-w-7xl mx-auto">
            
            <!-- Header Dashboard -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Selamat Datang Kembali, <?= htmlspecialchars($_SESSION['user']['nama'] ?? 'User') ?></h1>
                    <p class="text-sm text-gray-500 mt-1">Pantau dan kendalikan keuangan Anda hari ini untuk kesehatan finansial.</p>
                </div>
                
                <div class="no-print flex items-center space-x-3">
                    <form action="" method="GET" class="flex gap-2">
                        <div class="flex items-center bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm">
                            <i class="fas fa-calendar-alt mr-2 text-gray-500"></i>
                            <select name="tahun" onchange="this.form.submit()" class="bg-transparent focus:outline-none cursor-pointer">
                                <option value="2025" <?= $tahun_aktif == '2025' ? 'selected' : '' ?>>Tahun 2025</option>
                                <option value="2026" <?= $tahun_aktif == '2026' ? 'selected' : '' ?>>Tahun 2026</option>
                            </select>
                        </div>
                    </form>
                    <button onclick="window.print()" class="bg-gray-900 hover:bg-black text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 flex items-center shadow-sm">
                        <i class="fas fa-upload mr-2"></i>Ekspor
                    </button>
                </div>
            </div>

            <!-- Top Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Card 1: Account Balance / Pendapatan -->
                <div class="bg-white rounded-[24px] p-6 shadow-sm border border-gray-100 flex flex-col justify-between">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-600">
                                <i class="fas fa-wallet text-lg"></i>
                            </div>
                            <span class="text-gray-700 font-semibold text-base">Total Pendapatan</span>
                        </div>
                        <div class="flex items-center space-x-1 px-3 py-1 rounded-full border border-gray-200 text-xs font-semibold text-gray-600">
                            <i class="fas fa-money-bill-wave text-green-600"></i>
                            <span class="ml-1">IDR</span>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-4xl font-extrabold text-gray-900 mb-4"><?= formatRupiah($total_p_tahun) ?></h2>
                        <div class="inline-flex items-center px-2.5 py-1 rounded-md bg-green-50 text-green-600 text-[11px] font-bold tracking-wide mb-6">
                            <i class="fas fa-arrow-up mr-1"></i> Akumulasi tahunan
                        </div>
                        <div class="flex space-x-3">
                            <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-xl text-sm font-semibold transition-colors flex-1 flex justify-center items-center">
                                <i class="fas fa-arrow-down mr-2"></i>Pemasukan
                            </button>
                            <button class="bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 px-4 py-3 rounded-xl text-sm font-semibold transition-colors flex-1 flex justify-center items-center">
                                <i class="fas fa-info-circle mr-2"></i>Detail
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Total Expenses -->
                <div class="bg-white rounded-[24px] p-6 shadow-sm border border-gray-100 flex flex-col justify-between">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-600">
                                <i class="fas fa-file-invoice-dollar text-lg"></i>
                            </div>
                            <span class="text-gray-700 font-semibold text-base">Total Pengeluaran</span>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-full border border-gray-100 flex items-center justify-center"><i class="fas fa-ellipsis-h"></i></button>
                    </div>
                    <div>
                        <h2 class="text-4xl font-extrabold text-gray-900 mb-4"><?= formatRupiah($total_b_tahun) ?></h2>
                        <div class="inline-flex items-center px-2.5 py-1 rounded-md bg-rose-50 text-rose-500 text-[11px] font-bold tracking-wide">
                            <i class="fas fa-arrow-down mr-1"></i> Seluruh pos beban
                        </div>
                    </div>
                </div>

                <!-- Card 3: Total Savings / Laba Bersih -->
                <div class="bg-white rounded-[24px] p-6 shadow-sm border border-gray-100 flex flex-col justify-between">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-2">
                            <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center text-gray-600">
                                <i class="fas fa-piggy-bank text-lg"></i>
                            </div>
                            <span class="text-gray-700 font-semibold text-base">Laba Bersih</span>
                        </div>
                        <button class="text-gray-400 hover:text-gray-600 w-8 h-8 rounded-full border border-gray-100 flex items-center justify-center"><i class="fas fa-ellipsis-h"></i></button>
                    </div>
                    <div>
                        <h2 class="text-4xl font-extrabold text-gray-900 mb-4"><?= formatRupiah($total_p_tahun - $total_b_tahun) ?></h2>
                        <div class="inline-flex items-center px-2.5 py-1 rounded-md <?= ($total_p_tahun - $total_b_tahun >= 0) ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-500' ?> text-[11px] font-bold tracking-wide">
                            <i class="fas <?= ($total_p_tahun - $total_b_tahun >= 0) ? 'fa-arrow-up' : 'fa-arrow-down' ?> mr-1"></i>
                            Profit / Rugi Bersih
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Section: Wallet & Overview Chart -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Left: My Wallet -> Komposisi Beban -->
                

                <!-- Right: Overview -> Pendapatan vs Beban -->
                <div class="lg:col-span-4 bg-white rounded-[24px] p-6 shadow-sm border border-gray-100 flex flex-col">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg mb-4">Ringkasan pendapatan dan beban</h3>
                            <div class="flex space-x-6">
                                <!-- Gross Volume (Pendapatan) -->
                                <div>
                                    <div class="flex items-center space-x-1 mb-1">
                                        <div class="w-2.5 h-2.5 bg-[#4F46E5] rounded-sm"></div>
                                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Pendapatan</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-2xl font-extrabold text-gray-900"><?= formatRupiah($total_p_tahun) ?></span>
                                        <span class="text-[10px] font-bold text-green-600 bg-green-50 px-1.5 py-0.5 rounded"><i class="fas fa-arrow-up mr-1"></i>Akumulasi</span>
                                    </div>
                                </div>
                                <!-- Net Volume (Beban) -->
                                <div class="border-l border-gray-100 pl-6">
                                    <div class="flex items-center space-x-1 mb-1">
                                        <div class="w-2.5 h-2.5 bg-gray-300 rounded-sm"></div>
                                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Beban</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-2xl font-extrabold text-gray-900"><?= formatRupiah($total_b_tahun) ?></span>
                                        <span class="text-[10px] font-bold text-rose-500 bg-rose-50 px-1.5 py-0.5 rounded"><i class="fas fa-arrow-down mr-1"></i>Seluruh</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                         <div class="flex items-center bg-white border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm">
                            <i class="fas fa-calendar-alt mr-2 text-gray-500"></i>
                            <select name="tahun" onchange="this.form.submit()" class="bg-transparent focus:outline-none cursor-pointer">
                                <option value="2025" <?= $tahun_aktif == '2025' ? 'selected' : '' ?>>Tahun 2025</option>
                                <option value="2026" <?= $tahun_aktif == '2026' ? 'selected' : '' ?>>Tahun 2026</option>
                            </select>
                        </div>
                        </div>
                    </div>
                    <div class="chart-container flex-1" style="min-height: 280px;">
                        <canvas id="chartIncomeExpense"></canvas>
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Savings Plan & Recent Transaction -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left: Savings Plan -> Analisis Strategis -->
                <div class="lg:col-span-1 bg-white rounded-[24px] p-6 shadow-sm border border-gray-100 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-lightbulb text-green-500 text-lg"></i>
                            <h3 class="font-bold text-gray-900 text-lg">Rencana & Analisis</h3>
                        </div>
                        <button class="w-8 h-8 rounded-full border border-gray-100 text-gray-400 flex items-center justify-center hover:bg-gray-50"><i class="fas fa-ellipsis-h"></i></button>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Puncak Performa -->
                        <div class="bg-gray-50 p-4 rounded-[20px] border border-gray-100">
                            <div class="flex items-center space-x-3 mb-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-500">
                                    <i class="fas fa-bullseye text-sm"></i>
                                </div>
                                <span class="font-bold text-gray-800 text-sm">Target Puncak</span>
                            </div>
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-xs text-gray-500 font-bold"><?= formatRupiah($max_laba) ?></span>
                                <span class="text-sm font-bold text-gray-900"><?= $max_bulan ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: 80%"></div>
                            </div>
                        </div>

                        <!-- Titik Terendah -->
                        <div class="bg-gray-50 p-4 rounded-[20px] border border-gray-100">
                            <div class="flex items-center space-x-3 mb-3">
                                <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-500">
                                    <i class="fas fa-exclamation-triangle text-sm"></i>
                                </div>
                                <span class="font-bold text-gray-800 text-sm">Evaluasi Terendah</span>
                            </div>
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-xs text-gray-500 font-bold"><?= formatRupiah($min_laba) ?></span>
                                <span class="text-sm font-bold text-gray-900"><?= $min_bulan ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-orange-400 h-2 rounded-full" style="width: 30%"></div>
                            </div>
                        </div>
                        
                        <!-- Rata-rata -->
                        <div class="mt-auto pt-2">
                            <p class="text-[11px] text-gray-400 font-bold mb-1 uppercase tracking-wider">Rata-rata Laba Bulanan</p>
                            <p class="text-2xl font-extrabold text-gray-900"><?= formatRupiah($avg_laba) ?></p>
                        </div>
                    </div>

                    
                </div>

                <div class="lg:col-span-1 bg-white rounded-[24px] p-6 shadow-sm border border-gray-100 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-gray-900 text-lg">Komposisi Beban</h3>
                        <button class="bg-gray-50 border border-gray-200 hover:bg-gray-100 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center">
                            <i class="fas fa-chart-pie mr-1"></i> Detail
                        </button>
                    </div>
                    
                    <!-- Pie Chart Container -->
                    <div class="chart-container relative flex items-center justify-center" style="min-height: 220px;">
                        <canvas id="chartPieBeban"></canvas>
                        <!-- Center text for doughnut -->
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none mt-2">
                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Total Beban</span>
                            <span class="text-lg font-extrabold text-gray-900"><?= formatRupiah(array_sum($top_beban_values)) ?></span>
                        </div>
                    </div>

                    <!-- Custom Legend -->
                    <div class="mt-6 space-y-3 flex-1 overflow-y-auto max-h-[160px] pr-2 custom-scrollbar">
                        <?php foreach($top_beban_labels as $idx => $label): 
                            $colors = ['#4F46E5', '#10B981', '#F59E0B', '#F43F5E', '#8B5CF6'];
                            $color = $colors[$idx % count($colors)];
                            $val = $top_beban_values[$idx];
                        ?>
                        <div class="flex items-center justify-between p-2 rounded-xl hover:bg-gray-50 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full shadow-sm" style="background-color: <?= $color ?>;"></div>
                                <span class="text-sm font-semibold text-gray-700 truncate max-w-[120px]"><?= $label ?></span>
                            </div>
                            <span class="text-sm font-bold text-gray-900"><?= formatRupiah($val) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right: Recent Transaction -> Tren Laba Bersih Chart -->
                <div class="lg:col-span-2 bg-white rounded-[24px] p-6 shadow-sm border border-gray-100 flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-arrow-right-arrow-left text-green-500 text-lg"></i>
                            <h3 class="font-bold text-gray-900 text-lg">Aktivitas Laba Terakhir</h3>
                        </div>
                        <button class="bg-gray-50 border border-gray-200 hover:bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center">
                            Filter <i class="fas fa-filter ml-2 text-gray-400 text-[10px]"></i>
                        </button>
                    </div>
                    
                    <div class="chart-container flex-1" style="min-height: 250px;">
                        <canvas id="chartProfitTrend"></canvas>
                    </div>
                </div>
                
            </div>

        </div>
    </main>
</div>

<script>
    // DATA PREPARATION FROM PHP
    const labels = <?= json_encode(array_column($monthly_data, 'label')) ?>;
    const incomeData = <?= json_encode(array_column($monthly_data, 'p')) ?>;
    const expenseData = <?= json_encode(array_column($monthly_data, 'b')) ?>;
    const profitData = <?= json_encode(array_column($monthly_data, 'l')) ?>;

    Chart.defaults.font.family = "'Google Sans', sans-serif";
    Chart.defaults.color = '#9CA3AF';

    // Chart 1: Income vs Expense (Line Chart styled like design)
    new Chart(document.getElementById('chartIncomeExpense'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: incomeData,
                    borderColor: '#4F46E5', // Blue color like the image
                    borderWidth: 2,
                    backgroundColor: (context) => {
                        const ctx = context.chart.ctx;
                        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(79, 70, 229, 0.15)');
                        gradient.addColorStop(1, 'rgba(79, 70, 229, 0)');
                        return gradient;
                    },
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#4F46E5',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    order: 1
                },
                {
                    label: 'Beban',
                    data: expenseData,
                    borderColor: '#D1D5DB', // Light gray color
                    borderWidth: 2,
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#D1D5DB',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    order: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    borderColor: '#f3f4f6',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    usePointStyle: true,
                    titleFont: { size: 13, weight: 'bold' }
                }
            },
            scales: {
                y: { 
                    display: true, 
                    beginAtZero: true,
                    grid: { color: '#f3f4f6', drawBorder: false },
                    border: { display: false },
                    ticks: { 
                        font: { size: 10 }, 
                        color: '#9CA3AF',
                        callback: function(value) {
                            if(value >= 1000000) return (value / 1000000) + 'M';
                            if(value >= 1000) return (value / 1000) + 'k';
                            return value;
                        }
                    }
                },
                x: { 
                    grid: { display: false, drawBorder: false }, 
                    border: { display: false },
                    ticks: { font: { size: 10, weight: '500' }, color: '#9CA3AF' } 
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            }
        }
    });

    // Chart 2: Profit Trend (Bar Chart for bottom section)
    new Chart(document.getElementById('chartProfitTrend'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Laba Bersih',
                data: profitData,
                backgroundColor: '#3B82F6', // Blue color
                borderRadius: 4,
                barPercentage: 0.5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { display: false, beginAtZero: true },
                x: { 
                    grid: { display: false, drawBorder: false },
                    border: { display: false },
                    ticks: { font: { size: 10 }, color: '#9CA3AF' }
                }
            }
        }
    });

    // Chart 3: Pie Chart for Komposisi Beban
    const pieLabels = <?= json_encode($top_beban_labels) ?>;
    const pieData = <?= json_encode($top_beban_values) ?>;

    new Chart(document.getElementById('chartPieBeban'), {
        type: 'doughnut',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieData,
                backgroundColor: ['#4F46E5', '#10B981', '#F59E0B', '#F43F5E', '#8B5CF6'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%', // Creates the thin doughnut ring
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    borderColor: '#f3f4f6',
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            let value = context.raw || 0;
                            return ' Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
</script>

</body>
</html>
