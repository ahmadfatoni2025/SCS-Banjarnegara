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

// --- LOGIKA OTOMATIS: CEK TAHUN DARI JURNAL UMUM ---
// Ambil semua tahun unik yang ada di jurnal_umum untuk membuat slot rekap
$q_tahun_jurnal = mysqli_query($koneksi, "SELECT DISTINCT YEAR(tanggal) as tahun FROM jurnal_umum ORDER BY tahun ASC");
$tahun_sekarang = date('Y');

while($row = mysqli_fetch_assoc($q_tahun_jurnal)) {
    $thn = $row['tahun'];
    
    // Cek apakah tahun ini sudah ada di tabel rekap_tahunan?
    $cek = mysqli_query($koneksi, "SELECT id FROM rekap_tahunan WHERE tahun = '$thn'");
    
    if (mysqli_num_rows($cek) == 0) {
        // Jika belum ada, INSERT otomatis dengan nilai default 0
        $status_awal = ($thn < $tahun_sekarang) ? 'Selesai' : 'Berlangsung';
        
        $stmt = $koneksi->prepare("INSERT INTO rekap_tahunan (tahun, pendapatan, beban, laba_bersih, status, tgl_tutup) VALUES (?, 0, 0, 0, ?, NOW())");
        $stmt->bind_param("is", $thn, $status_awal);
        $stmt->execute();
    }
}

// --- LOGIKA: HAPUS TAHUN (Manual Delete) ---
if (isset($_POST['hapus_tahun'])) {
    $id_hapus = $_POST['id_hapus'];
    $tahun_hapus = $_POST['tahun_hapus'];
    if(mysqli_query($koneksi, "DELETE FROM rekap_tahunan WHERE id = '$id_hapus'")) {
          echo "<script>Swal.fire({icon: 'success', title: 'Terhapus', text: 'Card Tahun $tahun_hapus berhasil dihapus.'}).then(() => { window.location='rekap_tahunan.php'; });</script>";
    }
}

// --- LOGIKA: SELESAIKAN TAHUN (Manual Finish) ---
if (isset($_POST['selesaikan_tahun'])) {
    $tahun = $_POST['tahun'];
    
    // Hitung Ulang Data Final untuk disimpan ke DB
    $tgl_awal = "$tahun-01-01";
    $tgl_akhir = "$tahun-12-31";
    
    $q_inc = mysqli_query($koneksi, "SELECT SUM(kredit - debit) as val FROM jurnal_umum WHERE kode_akun LIKE '4%' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND no_reff NOT LIKE 'CLS%'");
    $inc = mysqli_fetch_assoc($q_inc)['val'] ?? 0;
    
    // Sinkronisasi: Masukkan kategori 6 ke Beban
    $q_exp = mysqli_query($koneksi, "SELECT SUM(debit - kredit) as val FROM jurnal_umum WHERE (kode_akun LIKE '5%' OR kode_akun LIKE '6%') AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND no_reff NOT LIKE 'CLS%'");
    $exp = mysqli_fetch_assoc($q_exp)['val'] ?? 0;
    
    $net = $inc - $exp;

    mysqli_autocommit($koneksi, false);
    try {
        // Update status jadi Selesai dan simpan nilai terakhir
        $stmt = $koneksi->prepare("UPDATE rekap_tahunan SET pendapatan=?, beban=?, laba_bersih=?, status='Selesai', tgl_tutup=NOW() WHERE tahun=?");
        $stmt->bind_param("dddi", $inc, $exp, $net, $tahun);
        $stmt->execute();
        
        mysqli_commit($koneksi);
        echo "<script>Swal.fire({icon: 'success', title: 'Tahun $tahun Selesai!', text: 'Laporan tahun $tahun telah diarsipkan.'}).then(() => { window.location='rekap_tahunan.php'; });</script>";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>Swal.fire('Error', 'Gagal update status', 'error');</script>";
    }
}

// --- AMBIL DATA REKAP UNTUK DITAMPILKAN ---
$data_rekap = [];
$q = mysqli_query($koneksi, "SELECT * FROM rekap_tahunan ORDER BY tahun DESC");

while($r = mysqli_fetch_assoc($q)) {
    // REVISI LOGIKA DI SINI:
    // Selalu ambil data REAL-TIME dari jurnal_umum, tidak peduli statusnya 'Selesai' atau 'Berlangsung'.
    // Ini menjamin jika data di jurnal dihapus, angka di card akan otomatis jadi 0.

    $thn = $r['tahun'];
    
    // Hitung Pendapatan Live
    $q_inc = mysqli_query($koneksi, "SELECT SUM(kredit - debit) as val FROM jurnal_umum WHERE kode_akun LIKE '4%' AND YEAR(tanggal) = '$thn' AND no_reff NOT LIKE 'CLS%'");
    $pendapatan_live = mysqli_fetch_assoc($q_inc)['val'];
    $r['pendapatan'] = ($pendapatan_live === null) ? 0 : $pendapatan_live; // Jika null (kosong), set 0
    
    // Hitung Beban Live (Sinkronisasi: Masukkan kategori 6)
    $q_exp = mysqli_query($koneksi, "SELECT SUM(debit - kredit) as val FROM jurnal_umum WHERE (kode_akun LIKE '5%' OR kode_akun LIKE '6%') AND YEAR(tanggal) = '$thn' AND no_reff NOT LIKE 'CLS%'");
    $beban_live = mysqli_fetch_assoc($q_exp)['val'];
    $r['beban'] = ($beban_live === null) ? 0 : $beban_live; // Jika null (kosong), set 0
    
    // Hitung Laba Bersih Live
    $r['laba_bersih'] = $r['pendapatan'] - $r['beban'];

    $data_rekap[] = $r;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Tahunan - PT. SURYA CERAH SEMESTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-radius: 1.5rem; box-shadow: 0 8px 32px rgba(31, 38, 135, 0.07); border: 1px solid rgba(255,255,255,0.6); }
        @keyframes blob { 0% {transform: translate(0,0) scale(1);} 33% {transform: translate(30px,-50px) scale(1.1);} 66% {transform: translate(-20px,20px) scale(0.9);} 100% {transform: translate(0,0) scale(1);} }
        .animate-blob { animation: blob 7s infinite; } .animation-delay-2000 { animation-delay: 2s; }
    </style>
</head>
<body class="text-slate-800">

    <?php include 'sidebar.php'; ?>

    <div class="lg:ml-64 min-h-screen p-4 sm:p-6 lg:p-8 transition-all duration-300 relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-96 h-96 bg-yellow-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>

        <div class="relative z-10 mb-6 lg:mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-purple-600">Arsip Rekap Tahunan</h1>
                <p class="text-sm lg:text-base text-slate-500 mt-1">Monitoring kinerja keuangan per periode tahun buku</p>
            </div>
        </div>

        <div class="relative z-10 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-y-28 lg:gap-y-24 gap-x-4 sm:gap-x-6 pt-24 lg:pt-16 pb-12">
            
            <?php foreach($data_rekap as $data): ?>
            <div onclick="window.open('laba_rugi.php?tgl_mulai=<?= $data['tahun'] ?>-01-01&tgl_selesai=<?= $data['tahun'] ?>-12-31', '_blank')" class="relative w-[280px] h-[280px] mx-auto group drop-shadow-2xl transition-all duration-300 lg:hover:-translate-y-2 cursor-pointer">
                
                <!-- Back Folder Tab -->
                <div class="absolute top-0 left-0 w-[45%] h-10 bg-[#4281cd] rounded-tl-2xl" style="clip-path: polygon(0 0, 85% 0, 100% 100%, 0% 100%);"></div>
                
                <!-- Back Folder Main -->
                <div class="absolute top-[39px] left-0 w-full h-[calc(100%-39px)] bg-[#4281cd] rounded-tr-2xl rounded-b-2xl"></div>

                <!-- Paper -->
                <div class="absolute top-[25px] left-4 right-4 h-[220px] bg-gradient-to-b from-[#ffffff] to-[#f1f5f9] rounded-t-xl shadow-[0_-2px_10px_rgba(0,0,0,0.05)] transition-transform duration-500 ease-out -translate-y-[75px] lg:translate-y-0 lg:group-hover:-translate-y-[75px] flex flex-col p-4 z-10 border border-gray-200">
                    
                    <div class="w-full text-[11px] font-sans">
                        <div class="flex justify-between items-center text-slate-500 mb-2">
                            <span class="font-medium">Pendapatan</span>
                            <span class="font-bold text-[#059669]">+ <?= formatRupiah($data['pendapatan']) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-slate-500 mb-3">
                            <span class="font-medium">Beban</span>
                            <span class="font-bold text-[#dc2626]">- <?= formatRupiah($data['beban']) ?></span>
                        </div>
                        
                        <div class="w-full border-b border-dotted border-slate-300 mb-3"></div>
                        
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-slate-800 tracking-wide text-[10px]">LABA BERSIH</span>
                            <span class="font-bold text-[13px] <?= ($data['laba_bersih']>=0)?'text-[#059669]':'text-[#dc2626]' ?>">
                                <?= formatRupiah($data['laba_bersih']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 relative z-50 pointer-events-auto">
                        <a href="laba_rugi.php?tgl_mulai=<?= $data['tahun'] ?>-01-01&tgl_selesai=<?= $data['tahun'] ?>-12-31" target="_blank" onclick="event.stopPropagation();"
                           class="block w-full py-1.5 rounded-lg bg-white border border-gray-200 text-center text-[10px] text-blue-600 hover:bg-blue-50 hover:border-blue-300 font-bold transition shadow-sm pointer-events-auto">
                            <i class="fas fa-external-link-alt mr-1"></i> Detail Laporan
                        </a>
                        <?php if ($data['status'] == 'Berlangsung'): ?>
                            <form method="POST" onsubmit="return confirm('ARSIPKAN TAHUN <?= $data['tahun'] ?>?\n\n- Data angka akan dikunci.\n- Status berubah jadi Selesai.')" onclick="event.stopPropagation();">
                                <input type="hidden" name="tahun" value="<?= $data['tahun'] ?>">
                                <button type="submit" name="selesaikan_tahun" class="w-full py-1.5 rounded-lg bg-orange-50 text-orange-600 border border-orange-200 hover:bg-orange-100 text-[10px] font-bold transition shadow-sm pointer-events-auto">
                                    <i class="fas fa-archive mr-1"></i> Arsipkan Tahun
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center mt-1 bg-gray-50 py-1.5 rounded-lg border border-gray-100 shadow-inner" onclick="event.stopPropagation();">
                                <p class="text-[9px] text-gray-500 font-medium">
                                    <i class="fas fa-lock text-gray-400 mr-1"></i> <?= date('d M Y', strtotime($data['tgl_tutup'])) ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Front Folder -->
                <div class="absolute top-[50px] left-0 w-full h-[calc(100%-50px)] bg-gradient-to-b from-[#60a0ea] to-[#4585d5] rounded-2xl shadow-[0_-4px_15px_rgba(0,0,0,0.15)] p-5 z-20 flex flex-col justify-between border-t border-white/30 pointer-events-none lg:group-hover:pointer-events-auto transition-transform duration-500 ease-out translate-y-[45px] lg:translate-y-0 lg:group-hover:translate-y-[45px]">
                    
                    <div class="flex justify-between items-start pointer-events-auto">
                        <div>
                            <h2 class="text-white text-3xl font-semibold tracking-tight"><?= $data['tahun'] ?></h2>
                            <p class="text-blue-100 text-sm mt-1 opacity-90 flex items-center gap-1.5">
                                <?= $data['status'] ?>
                                <span class="w-1.5 h-1.5 rounded-full <?= ($data['status']=='Berlangsung') ? 'bg-yellow-300 shadow-[0_0_5px_#fde047]' : 'bg-green-300 shadow-[0_0_5px_#86efac]' ?>"></span>
                            </p>
                        </div>
                        
                        <form method="POST" onsubmit="return confirm('Hapus Card Tahun <?= $data['tahun'] ?>? (Data transaksi AMAN)')" class="relative z-30 pointer-events-auto" onclick="event.stopPropagation();">
                            <input type="hidden" name="id_hapus" value="<?= $data['id'] ?>">
                            <input type="hidden" name="tahun_hapus" value="<?= $data['tahun'] ?>">
                            <button type="submit" name="hapus_tahun" class="w-8 h-8 rounded-full border border-white/40 flex items-center justify-center text-white hover:bg-red-500 hover:border-red-500 bg-white/10 hover:shadow-lg transition-all" title="Hapus Tahun">
                                <i class="fas fa-trash text-[11px]"></i>
                            </button>
                        </form>
                    </div>

                    <div class="mt-auto relative w-full pt-4 pointer-events-none">
                        <div class="text-blue-100 text-[11px] opacity-90 font-medium relative z-10">
                            Laba Bersih<br>
                            <span class="text-white text-xl font-bold opacity-100 drop-shadow-sm leading-tight inline-block mt-0.5"><?= formatRupiah($data['laba_bersih']) ?></span>
                        </div>
                        <i class="fas fa-folder-open text-blue-300/30 text-5xl absolute bottom-[-10px] right-[-10px] pointer-events-none"></i>
                    </div>

                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>

</body>
</html>
