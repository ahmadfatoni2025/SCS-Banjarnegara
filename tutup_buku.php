<?php
session_start();
include 'koneksi.php';
include 'fungsi_akuntansi.php';

// 1. CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
if (!in_array($role_user, ['admin', 'owner', 'akuntan'])) {
    echo "<script>alert('Akses Ditolak! Hanya Akuntan/Admin yang bisa Tutup Buku.'); window.location='dashboard.php';</script>"; exit;
}

$pesan = "";

// FUNGSI INTI: TUTUP BUKU PER BULAN
function processClosingMonth($koneksi, $bulan, $tahun, $user_executor) {
    // 1. Validasi: Pastikan bulan sebelumnya sudah ditutup (kecuali data pertama)
    $prev_bulan = $bulan - 1;
    $prev_tahun = $tahun;
    if ($prev_bulan == 0) { $prev_bulan = 12; $prev_tahun = $tahun - 1; }
    
    // Cek apakah ada data di bulan sebelumnya
    $q_check_prev_data = mysqli_query($koneksi, "SELECT id FROM jurnal_umum WHERE MONTH(tanggal) = $prev_bulan AND YEAR(tanggal) = $prev_tahun LIMIT 1");
    if (mysqli_num_rows($q_check_prev_data) > 0) {
        if (!isPeriodClosed($koneksi, $prev_bulan, $prev_tahun)) {
            throw new Exception("Bulan " . getBulanIndonesia(str_pad($prev_bulan, 2, '0', STR_PAD_LEFT)) . " $prev_tahun belum ditutup!");
        }
    }

    // 2. Cek apakah sudah ditutup
    if (isPeriodClosed($koneksi, $bulan, $tahun)) {
        return "Already Closed";
    }

    $tgl_akhir = date("Y-m-t", strtotime("$tahun-$bulan-01"));
    $no_reff_cls = "CLS-" . date('Ym', strtotime($tgl_akhir));
    $keterangan_cls = "Tutup Buku " . getBulanIndonesia(str_pad($bulan, 2, '0', STR_PAD_LEFT)) . " $tahun";

    $koneksi->begin_transaction();
    try {
        // A. Ambil semua akun nominal (Pendapatan & Beban)
        $q_nominal = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE kategori IN ('Pendapatan', 'Beban')");
        
        $total_pendapatan = 0;
        $total_beban = 0;
        
        while ($akun = mysqli_fetch_assoc($q_nominal)) {
            $kd = $akun['kode_akun'];
            // Saldo nominal bulan ini (harus di-reset ke nol)
            $saldo = getAccountBalance($koneksi, $kd, $tgl_akhir);
            
            if ($saldo == 0) continue;
            
            $stmt_cls = $koneksi->prepare("INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, kode_akun, debit, kredit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($akun['posisi_normal'] == 'Debit') {
                $debit = 0; $kredit = abs($saldo);
                $total_beban += $saldo;
            } else {
                $debit = abs($saldo); $kredit = 0;
                $total_pendapatan += $saldo;
            }
            
            $stmt_cls->bind_param("ssssdds", $tgl_akhir, $no_reff_cls, $keterangan_cls, $kd, $debit, $kredit, $user_executor);
            $stmt_cls->execute();
            $stmt_cls->close();
        }

        // B. Handle PRIVE (Jika ada akun kepala 3 yang sifatnya pengurang modal)
        // Cari akun Prive (Biasanya mengandung kata Prive)
        $q_prive = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE nama_akun LIKE '%Prive%' AND kategori = 'Modal'");
        while ($prive = mysqli_fetch_assoc($q_prive)) {
            $kd_p = $prive['kode_akun'];
            $saldo_p = getAccountBalance($koneksi, $kd_p, $tgl_akhir);
            if ($saldo_p != 0) {
                // Prive biasanya Debit -> Kreditkan agar nol
                $stmt_p = $koneksi->prepare("INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, kode_akun, debit, kredit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $d = 0; $k = abs($saldo_p);
                $ket_prive_1 = "Penutupan Prive ke Modal";
                $stmt_p->bind_param("ssssdds", $tgl_akhir, $no_reff_cls, $ket_prive_1, $kd_p, $d, $k, $user_executor);
                $stmt_p->execute();
                
                // Debitkan Modal (3111) atau Laba Ditahan (3131)
                $kd_modal = '3131'; // Default ke Laba Ditahan
                $d = abs($saldo_p); $k = 0;
                $ket_prive_2 = "Penutupan Prive";
                $stmt_p->bind_param("ssssdds", $tgl_akhir, $no_reff_cls, $ket_prive_2, $kd_modal, $d, $k, $user_executor);
                $stmt_p->execute();
                $stmt_p->close();
            }
        }
        
        // C. Pindahkan Laba Bersih ke Laba Ditahan (3131)
        $laba_bersih = $total_pendapatan - $total_beban;
        if ($laba_bersih != 0) {
            $stmt_laba = $koneksi->prepare("INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, kode_akun, debit, kredit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $kd_laba = '3131'; // Laba Ditahan
            if ($laba_bersih > 0) {
                $d = 0; $k = abs($laba_bersih);
            } else {
                $d = abs($laba_bersih); $k = 0;
            }
            $ket_laba = "Pemindahan Laba ke Laba Ditahan";
            $stmt_laba->bind_param("ssssdds", $tgl_akhir, $no_reff_cls, $ket_laba, $kd_laba, $d, $k, $user_executor);
            $stmt_laba->execute();
            $stmt_laba->close();
        }

        // D. Update Status Periode
        $stmt_status = $koneksi->prepare("INSERT INTO periode_status (bulan, tahun, status, closed_at, closed_by) VALUES (?, ?, 'Closed', NOW(), ?) ON DUPLICATE KEY UPDATE status='Closed', closed_at=NOW(), closed_by=?");
        $stmt_status->bind_param("iiss", $bulan, $tahun, $user_executor, $user_executor);
        $stmt_status->execute();
        $stmt_status->close();
        
        $koneksi->commit();
        return "Success";
    } catch (Exception $e) {
        $koneksi->rollback();
        throw $e;
    }
}

// 2. HANDLING POST REQUEST
if (isset($_POST['execute_closing'])) {
    $target_month = $_POST['bulan'];
    $target_year = $_POST['tahun'];
    $is_bulk = isset($_POST['is_bulk']) && $_POST['is_bulk'] == '1';
    $user_executor = $_SESSION['user']['nama'];

    try {
        if ($is_bulk) {
            // BULK: Cari semua bulan yang belum ditutup sampai target
            $q_unclosed = mysqli_query($koneksi, "
                SELECT DISTINCT MONTH(tanggal) as b, YEAR(tanggal) as t 
                FROM jurnal_umum 
                WHERE tanggal <= '".date("Y-m-t", strtotime("$target_year-$target_month-01"))."'
                ORDER BY tanggal ASC
            ");
            
            $count = 0;
            while ($row = mysqli_fetch_assoc($q_unclosed)) {
                if (!isPeriodClosed($koneksi, $row['b'], $row['t'])) {
                    $res = processClosingMonth($koneksi, $row['b'], $row['t'], $user_executor);
                    if ($res == "Success") $count++;
                }
            }
            $pesan = "<script>Swal.fire('Berhasil', '$count bulan berhasil ditutup secara berurutan.', 'success');</script>";
        } else {
            // SINGLE: Hanya satu bulan
            $res = processClosingMonth($koneksi, $target_month, $target_year, $user_executor);
            if ($res == "Success") {
                $pesan = "<script>Swal.fire('Berhasil', 'Bulan berhasil ditutup.', 'success');</script>";
            } else {
                $pesan = "<script>Swal.fire('Info', 'Bulan sudah ditutup sebelumnya.', 'info');</script>";
            }
        }
    } catch (Exception $e) {
        $pesan = "<script>Swal.fire('Gagal', '" . $e->getMessage() . "', 'error');</script>";
    }
}

// 3. AMBIL DAFTAR BULAN YANG TERSEDIA DI JURNAL
$q_months = mysqli_query($koneksi, "SELECT DISTINCT MONTH(tanggal) as b, YEAR(tanggal) as t FROM jurnal_umum ORDER BY t DESC, b DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Closing Engine - PT. SURYA CERAH SEMESTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F3F4F6; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-radius: 1.5rem; box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07); border: 1px solid rgba(255,255,255,0.6); }
        @keyframes blob { 0% {transform: translate(0,0) scale(1);} 33% {transform: translate(30px,-50px) scale(1.1);} 66% {transform: translate(-20px,20px) scale(0.9);} 100% {transform: translate(0,0) scale(1);} }
        .animate-blob { animation: blob 7s infinite; } .animation-delay-2000 { animation-delay: 2s; }
        .sidebar-space { margin-left: 16rem; }
        @media (max-width: 1024px) { .sidebar-space { margin-left: 0; } }
    </style>
</head>
<body class="text-slate-800">

    <?php include 'sidebar.php'; ?>
    <?= $pesan ?>

    <div class="sidebar-space min-h-screen bg-white transition-all duration-300 relative overflow-hidden font-sans">
        
        <?php 
        // Grouping data by Year
        mysqli_data_seek($q_months, 0);
        $grouped_months = [];
        while($row = mysqli_fetch_assoc($q_months)){
            $grouped_months[$row['t']][] = $row;
        }
        ?>
        <!-- Header & Action Card -->
        <div class="mx-6 md:mx-10 my-8 p-6 md:p-8 bg-slate-50 rounded-[2rem] border border-gray-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] flex flex-col xl:flex-row justify-between items-start xl:items-center gap-8">
            <div class="max-w-3xl">
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 tracking-tight">
                    <div class="w-12 h-12 rounded-2xl bg-[#7C3AED]/10 text-[#7C3AED] flex items-center justify-center shadow-inner">
                        <i class="fas fa-layer-group text-xl"></i>
                    </div>
                    Closing Engine
                </h1>
            </div>
            
            <form method="POST" id="closingForm" class="flex flex-wrap items-center gap-3 w-full xl:w-auto bg-white p-3 rounded-2xl shadow-sm border border-gray-100">
                <select name="bulan" class="border border-gray-200 bg-white rounded-xl text-xs px-4 py-3 outline-none focus:ring-2 focus:ring-[#7C3AED]/20 focus:border-[#7C3AED] font-bold text-gray-700 cursor-pointer transition-all">
                    <option value="" disabled selected>Pilih Bulan</option>
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?= $m ?>" <?= date('n') == $m ? 'selected' : '' ?>><?= getBulanIndonesia(str_pad($m,2,'0',STR_PAD_LEFT)) ?></option>
                    <?php endfor; ?>
                </select>
                <select name="tahun" class="border border-gray-200 bg-white rounded-xl text-xs px-4 py-3 outline-none focus:ring-2 focus:ring-[#7C3AED]/20 focus:border-[#7C3AED] font-bold text-gray-700 cursor-pointer transition-all">
                    <option value="2025">2025</option>
                    <option value="2026" selected>2026</option>
                </select>
                <label class="hidden md:flex items-center gap-2 text-[11px] font-bold text-gray-600 bg-gray-50 px-4 py-3 rounded-xl border border-gray-100 cursor-pointer hover:bg-gray-100 transition-colors">
                    <input type="checkbox" name="is_bulk" value="1" checked class="rounded text-[#7C3AED] focus:ring-[#7C3AED] w-4 h-4"> Bulk
                </label>
                <button type="submit" name="execute_closing" class="bg-[#7C3AED] hover:bg-[#6D28D9] text-white px-6 py-3 rounded-xl text-xs fon t-bold flex items-center justify-center gap-2 shadow-md shadow-purple-500/20 transition-all active:scale-95">
                    <i class="fas fa-lock"></i> Tutup Periode
                </button>
            </form>
        </div>

        <!-- Kanban Columns (Years) -->
        <div class="flex overflow-x-auto pb-12 px-6 md:px-10 gap-6 items-start hide-scrollbar">
            <?php foreach($grouped_months as $year => $months): ?>
            <!-- Column -->
            <div class="w-[320px] shrink-0">
                <!-- Column Header -->
                <div class="flex items-center gap-2 mb-4 px-1">
                    <span class="w-2.5 h-2.5 rounded-full <?= $year == date('Y') ? 'bg-blue-500' : 'bg-orange-400' ?>"></span>
                    <h2 class="font-bold text-gray-800 text-sm">Tahun <?= $year ?></h2>
                    <span class="w-5 h-5 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-[10px] font-bold ml-1">
                        <?= count($months) ?>
                    </span>
                    <i class="fas fa-ellipsis-v ml-auto text-gray-400 cursor-pointer hover:text-gray-600 transition-colors p-1"></i>
                </div>
                
                <!-- Cards Container -->
                <div class="space-y-4">
                    <?php foreach($months as $row): 
                        $is_closed = isPeriodClosed($koneksi, $row['b'], $row['t']);
                        $tgl_range_akhir = date("Y-m-t", strtotime($row['t']."-".$row['b']."-01"));
                        $laba = getNetIncome($koneksi, $tgl_range_akhir, date("Y-m-01", strtotime($row['t']."-".$row['b']."-01")));
                        $q_log = mysqli_query($koneksi, "SELECT * FROM periode_status WHERE bulan = {$row['b']} AND tahun = {$row['t']}");
                        $log = mysqli_fetch_assoc($q_log);
                        
                        // Styling Logic based on Image 2
                        $header_bg = $is_closed ? 'bg-[#10B981]' : 'bg-[#F97316]';
                        $header_text = $is_closed ? 'TERKUNCI' : 'TERBUKA';
                        $badge_bg = $is_closed ? 'bg-emerald-50 text-emerald-600' : 'bg-orange-50 text-orange-600';
                        $badge_text = $is_closed ? 'Terkunci' : 'Terbuka';
                        $laba_display = ($laba >= 0) ? formatRupiah($laba) : "-".formatRupiah(abs($laba));
                        
                        $desc_text = $is_closed 
                            ? 'Sistem telah mengunci seluruh transaksi untuk periode ini secara permanen. Saldo telah dipindahkan dengan aman.' 
                            : 'Periode ini masih terbuka. Pastikan seluruh transaksi telah diinput sebelum mengeksekusi tutup buku.';
                    ?>
                    
                    <div class="bg-white rounded-2xl shadow-[0_2px_10px_rgba(0,0,0,0.04)] border border-gray-100 overflow-hidden group hover:shadow-[0_4px_15px_rgba(0,0,0,0.08)] transition-all relative">
                        <!-- Clickable area -->
                        <a href="jurnal_umum.php?tgl_mulai=<?= $row['t'].'-'.str_pad($row['b'],2,'0',STR_PAD_LEFT).'-01' ?>&tgl_selesai=<?= $tgl_range_akhir ?>" class="absolute inset-0 z-10"></a>
                        
                        <!-- Colored Header (Like LOW/MODERATE) -->
                        <div class="<?= $header_bg ?> py-1.5 flex justify-center items-center">
                            <span class="text-white text-[10px] font-extrabold tracking-[0.15em] uppercase"><?= $header_text ?></span>
                        </div>
                        
                        <div class="p-4 relative z-20 pointer-events-none">
                            <!-- Dashed inner container -->
                            <div class="border border-dashed border-gray-200 rounded-xl p-3.5 relative bg-gray-50/30">
                                <h3 class="font-bold text-gray-800 text-[15px] mb-1.5">
                                    <?= getBulanIndonesia(str_pad($row['b'],2,'0',STR_PAD_LEFT)) ?> <?= $row['t'] ?>
                                </h3>
                                <p class="text-[11px] text-gray-400 leading-relaxed mb-4 h-8 line-clamp-2">
                                    <?= $desc_text ?>
                                </p>
                                
                                <div class="flex justify-between items-center">
                                    <div class="flex -space-x-1.5">
                                        <?php if($is_closed): ?>
                                        <div class="w-6 h-6 rounded-full border-2 border-white bg-blue-100 flex items-center justify-center text-[10px] font-bold text-blue-600 z-10 shadow-sm" title="<?= $log['closed_by'] ?>">
                                            <?= substr($log['closed_by'], 0, 1) ?>
                                        </div>
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($log['closed_by']) ?>&background=10B981&color=fff" class="w-6 h-6 rounded-full border-2 border-white shadow-sm z-0">
                                        <?php else: ?>
                                        <div class="w-6 h-6 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-[10px] text-gray-400 z-10 shadow-sm">
                                            <i class="fas fa-user-slash text-[8px]"></i>
                                        </div>
                                        <div class="w-6 h-6 rounded-full border-2 border-white bg-gray-50 flex items-center justify-center text-[10px] text-gray-300 z-0 shadow-sm">
                                            <i class="fas fa-question text-[8px]"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="px-2.5 py-1 <?= $badge_bg ?> text-[10px] rounded-lg font-bold tracking-wide"><?= $badge_text ?></span>
                                </div>
                            </div>
                            
                            <!-- Bottom Footer -->
                            <div class="flex justify-between items-center mt-3.5 px-1 text-[11px] text-gray-400 font-bold pointer-events-auto">
                                <div class="flex items-center gap-3.5">
                                    <span class="flex items-center gap-1.5" title="Laba/Rugi">
                                        <i class="far fa-clock text-[13px] text-gray-400"></i> 
                                        <?= substr(formatRupiah($laba), 0, strpos(formatRupiah($laba), ',')) ?>
                                    </span>
                                    <a href="jurnal_umum.php?tgl_mulai=<?= $row['t'].'-'.str_pad($row['b'],2,'0',STR_PAD_LEFT).'-01' ?>&tgl_selesai=<?= $tgl_range_akhir ?>" class="flex items-center gap-1.5 hover:text-[#7C3AED] transition-colors z-30 relative">
                                        <i class="fas fa-paperclip text-[13px] text-gray-400 hover:text-[#7C3AED]"></i> Jurnal
                                    </a>
                                </div>
                                <span class="text-gray-400"><?= date('M, d', strtotime($tgl_range_akhir)) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

    <script>
        document.getElementById('closingForm').onsubmit = function() {
            Swal.fire({
                title: 'Processing Vault...',
                html: 'Sistem sedang menyeimbangkan akun dan mengunci periode.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        };
    </script>

</body>
</html>
