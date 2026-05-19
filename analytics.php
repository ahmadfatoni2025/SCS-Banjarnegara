<?php
// 1. KONFIGURASI ERROR REPORTING (Aktifkan untuk Debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Cek koneksi otomatis (Root atau Subfolder)
if (file_exists('koneksi.php')) { include 'koneksi.php'; } 
elseif (file_exists('../koneksi.php')) { include '../koneksi.php'; } 
else { die("Error: File koneksi.php tidak ditemukan."); }

// Load Engine ML (Jika ada)
if (file_exists('ml_engine.php')) { include_once 'ml_engine.php'; }

// 2. CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';

// Hanya Admin
if ($role_user !== 'admin') {
    echo "<script>alert('Halaman ini khusus untuk Admin.'); window.location='dashboard.php';</script>"; exit;
}

// 3. FILTER PERIODE
$tgl_selesai_default = date('Y-m-d');
$tgl_mulai_default   = date('Y-m-d', strtotime('-30 days'));

$tgl_akhir = (isset($_GET['tgl_selesai']) && !empty($_GET['tgl_selesai'])) ? $_GET['tgl_selesai'] : $tgl_selesai_default;
$tgl_mulai = (isset($_GET['tgl_mulai']) && !empty($_GET['tgl_mulai'])) ? $_GET['tgl_mulai'] : date('Y-m-d', strtotime('-30 days', strtotime($tgl_akhir)));

// ==========================================
// BAGIAN 1: KPI UTAMA (KARTU ATAS)
// ==========================================

// A. Total Omzet & Transaksi Periode Ini
$q_now = mysqli_query($koneksi, "SELECT SUM(total_harga) as omzet, COUNT(id_pesanan) as trx FROM pesanan WHERE status_pembayaran='Lunas' AND DATE(tgl_pesan) BETWEEN '$tgl_mulai' AND '$tgl_akhir'");
$d_now = mysqli_fetch_assoc($q_now);
$omzet_now = $d_now['omzet'] ?? 0;
$trx_now   = $d_now['trx'] ?? 0;

// B. Rata-rata Belanja per Orang (AOV)
// FIX: Cegah Division by Zero
$aov = ($trx_now > 0) ? $omzet_now / $trx_now : 0;

// C. Perbandingan dengan Periode Sebelumnya
$date1 = new DateTime($tgl_mulai);
$date2 = new DateTime($tgl_akhir);
$interval = $date1->diff($date2)->days + 1;

$tgl_lalu_start = date('Y-m-d', strtotime("-$interval days", strtotime($tgl_mulai)));
$tgl_lalu_end   = date('Y-m-d', strtotime("-1 day", strtotime($tgl_mulai)));

$q_last = mysqli_query($koneksi, "SELECT SUM(total_harga) as omzet FROM pesanan WHERE status_pembayaran='Lunas' AND DATE(tgl_pesan) BETWEEN '$tgl_lalu_start' AND '$tgl_lalu_end'");
$omzet_last = mysqli_fetch_assoc($q_last)['omzet'] ?? 0;

$growth = 0;
if ($omzet_last > 0) {
    $growth = (($omzet_now - $omzet_last) / $omzet_last) * 100;
} else if ($omzet_now > 0) {
    $growth = 100; // Jika dulu 0 skrg ada, naik 100%
}

// ==========================================
// BAGIAN 2: DATA GRAFIK TREN (Line Chart)
// ==========================================
$chart_label = [];
$chart_data_real = [];
$historical_data = []; 

$q_trend = mysqli_query($koneksi, "
    SELECT DATE(tgl_pesan) as tgl, SUM(total_harga) as total 
    FROM pesanan 
    WHERE status_pembayaran='Lunas' AND DATE(tgl_pesan) BETWEEN '$tgl_mulai' AND '$tgl_akhir'
    GROUP BY DATE(tgl_pesan) 
    ORDER BY tgl ASC
");

while($r = mysqli_fetch_assoc($q_trend)) {
    $chart_label[] = date('d M', strtotime($r['tgl']));
    $chart_data_real[] = $r['total'];
    $historical_data[] = ['tgl' => $r['tgl'], 'total' => $r['total']];
}

// --- PREDIKSI / FORECASTING ---
$forecast_labels = [];
$forecast_data = [];
$trend_status = "Data Belum Cukup";
$prediksi_besok = 0; // Default 0 biar ga error undefined

// FIX: Cek apakah data historis cukup (minimal 2 titik) agar ML tidak error
if (class_exists('SimpleML') && count($historical_data) >= 2) {
    try {
        $ml = new SimpleML();
        $prediction_result = $ml->predict($historical_data, 7); // Prediksi 7 hari

        if (!empty($prediction_result['forecast'])) {
            foreach ($prediction_result['forecast'] as $pred) {
                $forecast_labels[] = date('d M', strtotime($pred['tgl']));
                $forecast_data[] = $pred['prediksi'];
            }
            
            if ($prediction_result['slope'] > 5000) $trend_status = "NAIK 🚀";
            elseif ($prediction_result['slope'] > 0) $trend_status = "Naik Tipis 📈";
            elseif ($prediction_result['slope'] < -5000) $trend_status = "TURUN 📉";
            else $trend_status = "Stabil ➖";
            
            // Ambil prediksi besok
            $prediksi_besok = !empty($forecast_data) ? $forecast_data[0] : 0;
        }
    } catch (Exception $e) {
        // Jika ML error, fallback gracefully
        $trend_status = "Analisa Error";
    }
} else {
    // Fallback jika data kosong atau ML Engine tidak ada
    $trend_status = ($growth > 0) ? "Positif" : (($growth < 0) ? "Negatif" : "Belum Ada Tren");
}

// Gabung data untuk Chart.js
$final_labels = array_merge($chart_label, $forecast_labels);
$final_data_real = array_merge($chart_data_real, array_fill(0, count($forecast_data), null));
$last_real_val = !empty($chart_data_real) ? end($chart_data_real) : 0;

// Buat garis sambung (agar grafik tidak putus antara Real & Prediksi)
$connected_forecast = [];
if (!empty($forecast_data)) {
    // Isi null sepanjang data real - 1
    $connected_forecast = array_fill(0, count($chart_data_real) > 0 ? count($chart_data_real) - 1 : 0, null);
    // Tambahkan titik terakhir data real sebagai jembatan
    if (count($chart_data_real) > 0) {
        $connected_forecast[] = $last_real_val;
    }
    // Masukkan data prediksi
    $connected_forecast = array_merge($connected_forecast, $forecast_data);
}


// ==========================================
// BAGIAN 3: TOP PRODUK (Pie Chart)
// ==========================================
$prod_labels = []; $prod_data = [];
$q_prod = mysqli_query($koneksi, "
    SELECT g.nama, SUM(dp.jumlah) as terjual
    FROM detail_pesanan dp
    JOIN gudang g ON dp.id_barang = g.id_barang
    JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
    WHERE p.status_pembayaran='Lunas' AND DATE(p.tgl_pesan) BETWEEN '$tgl_mulai' AND '$tgl_akhir'
    GROUP BY dp.id_barang, g.nama
    ORDER BY terjual DESC LIMIT 5
");
if($q_prod){ while($r = mysqli_fetch_assoc($q_prod)) { $prod_labels[] = $r['nama']; $prod_data[] = $r['terjual']; } }

// ==========================================
// BAGIAN 4: REKOMENDASI (BAHASA MANUSIA)
// ==========================================
$insights = [];
if ($omzet_now == 0) {
    $insights[] = ["type" => "warning", "icon" => "fa-exclamation-circle", "text" => "Belum ada penjualan periode ini. Yuk mulai promosi!"];
} else {
    if ($growth < -10) {
        $insights[] = ["type" => "danger", "icon" => "fa-arrow-trend-down", "text" => "Penjualan turun drastis (" . round(abs($growth),1) . "%). Cek stok atau promosi."];
    } elseif ($growth > 5) {
        $insights[] = ["type" => "success", "icon" => "fa-fire", "text" => "Mantap! Penjualan naik " . round($growth,1) . "%. Pertahankan stok barang terlaris."];
    }
    if ($aov < 20000 && $aov > 0) {
        $insights[] = ["type" => "warning", "icon" => "fa-basket-shopping", "text" => "Pelanggan rata-rata belanja sedikit. Tawarkan paket hemat."];
    }
}
if (empty($insights)) {
    $insights[] = ["type" => "info", "icon" => "fa-check-circle", "text" => "Bisnis berjalan stabil. Lanjutkan operasional seperti biasa."];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisa Bisnis - PT. SURYA CERAH SEMESTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        .paper-card { background: white; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        .metric-card { transition: all 0.3s ease; }
        .metric-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -4px rgba(0,0,0,0.1); }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="text-slate-800 min-h-screen">

    <?php include 'sidebar.php'; ?>

    <div class="md:ml-64 min-h-screen p-4 md:p-8 transition-all duration-300 fade-in">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-8">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold uppercase tracking-wider mb-2">
                    <i class="fas fa-robot"></i> Analisa Cerdas
                </div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Analisa Bisnis</h1>
                <p class="text-sm text-slate-500 mt-1">Lihat tren penjualan dan prediksi masa depan.</p>
            </div>
            
            <div class="paper-card p-1.5 flex items-center w-full lg:w-auto">
                <form method="GET" class="flex flex-col sm:flex-row items-center gap-2 w-full">
                    <div class="flex items-center bg-slate-50 rounded-xl px-3 py-2.5 border border-slate-200 w-full sm:w-auto">
                        <span class="text-[10px] text-slate-400 mr-2 font-bold uppercase">Dari</span>
                        <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="bg-transparent text-sm font-medium text-slate-700 outline-none w-full">
                    </div>
                    <span class="text-slate-300 hidden sm:block"><i class="fas fa-arrow-right text-xs"></i></span>
                    <div class="flex items-center bg-slate-50 rounded-xl px-3 py-2.5 border border-slate-200 w-full sm:w-auto">
                        <span class="text-[10px] text-slate-400 mr-2 font-bold uppercase">Sampai</span>
                        <input type="date" name="tgl_selesai" value="<?= $tgl_akhir ?>" class="bg-transparent text-sm font-medium text-slate-700 outline-none w-full">
                    </div>
                    <button type="submit" class="bg-slate-900 hover:bg-black text-white w-full sm:w-10 h-10 rounded-xl flex items-center justify-center transition-all shadow-sm text-sm font-bold">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            
            <div class="paper-card p-5 metric-card flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg shrink-0">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Omzet</p>
                    <h3 class="text-xl font-bold text-slate-800 mt-0.5 whitespace-nowrap">Rp <?= number_format($omzet_now/1000, 0) ?>k</h3>
                    <div class="mt-1 flex items-center gap-1 text-[10px] font-bold <?= $growth>=0 ? 'text-emerald-600' : 'text-red-500' ?>">
                        <i class="fas <?= $growth>=0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                        <span><?= round(abs($growth), 1) ?>%</span>
                        <span class="text-slate-400 font-normal">vs lalu</span>
                    </div>
                </div>
            </div>

            <div class="paper-card p-5 metric-card flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg shrink-0">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Jumlah Pesanan</p>
                    <h3 class="text-xl font-bold text-slate-800 mt-0.5"><?= number_format($trx_now) ?></h3>
                    <p class="text-[10px] mt-1 text-slate-400">Transaksi lunas</p>
                </div>
            </div>

            <div class="paper-card p-5 metric-card flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg shrink-0">
                    <i class="fas fa-user-tag"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Rata-rata / Orang</p>
                    <h3 class="text-xl font-bold text-slate-800 mt-0.5 whitespace-nowrap">Rp <?= number_format($aov/1000, 0) ?>k</h3>
                    <p class="text-[10px] mt-1 text-slate-400">Per struk belanja</p>
                </div>
            </div>

            <div class="paper-card p-5 metric-card flex items-start gap-4 bg-gradient-to-br from-white to-teal-50">
                <div class="w-11 h-11 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center text-lg shrink-0">
                    <i class="fas fa-wand-magic-sparkles"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-teal-700 uppercase tracking-wider">Prediksi Besok</p>
                    <h3 class="text-xl font-bold text-slate-800 mt-0.5 whitespace-nowrap">~ Rp <?= number_format($prediksi_besok/1000, 0) ?>k</h3>
                    <p class="text-[10px] mt-1 text-teal-600 font-semibold"><?= $trend_status ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            
            <div class="lg:col-span-2 paper-card p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg">Grafik Penjualan</h3>
                        <p class="text-xs text-slate-500">Garis putus-putus adalah prediksi sistem (AI)</p>
                    </div>
                    <span class="text-xs bg-slate-100 px-2 py-1 rounded text-slate-500 font-medium">Harian</span>
                </div>
                <div class="relative h-72 w-full">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="paper-card p-6 flex flex-col">
                <h3 class="font-bold text-slate-800 text-lg mb-2">Produk Terlaris</h3>
                <p class="text-xs text-slate-500 mb-6">Top 5 menu paling laku</p>
                <div class="relative h-48 w-full flex-1 flex items-center justify-center">
                    <?php if(empty($prod_labels)): ?>
                        <p class="text-sm text-slate-400 italic">Belum ada data penjualan</p>
                    <?php else: ?>
                        <canvas id="productChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="paper-card p-6">
            <h3 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
                <div class="w-8 h-8 bg-yellow-100 text-yellow-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <span>Saran Perbaikan Bisnis</span>
            </h3>
            <div class="space-y-3">
                <?php foreach($insights as $msg): ?>
                <div class="flex gap-4 items-start p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-white transition-colors">
                    <div class="mt-1 w-2 h-2 rounded-full 
                        <?= ($msg['type']=='danger')?'bg-red-500':(($msg['type']=='success')?'bg-green-500':'bg-yellow-500') ?> shrink-0"></div>
                    <p class="text-sm text-slate-600 leading-relaxed"><?= $msg['text'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-8 text-center">
        </div>

    </div>

    <script>
        // 1. Trend Chart
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?= json_encode($final_labels) ?>,
                datasets: [
                    {
                        label: 'Omzet Real',
                        data: <?= json_encode($final_data_real) ?>,
                        borderColor: '#4f46e5', // Indigo
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 3
                    },
                    {
                        label: 'Prediksi',
                        data: <?= json_encode($connected_forecast) ?>,
                        borderColor: '#f59e0b', // Amber
                        borderWidth: 3,
                        borderDash: [8, 4],
                        tension: 0.3,
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });

        // 2. Product Chart (Hanya render jika ada data)
        <?php if(!empty($prod_labels)): ?>
        const ctxProd = document.getElementById('productChart').getContext('2d');
        new Chart(ctxProd, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($prod_labels) ?>,
                datasets: [{
                    data: <?= json_encode($prod_data) ?>,
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 }, padding: 15 } }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
