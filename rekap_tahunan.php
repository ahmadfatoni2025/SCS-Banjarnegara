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

    <div class="md:ml-64 min-h-screen p-6 md:p-8 transition-all duration-300 relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-96 h-96 bg-yellow-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-purple-200 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>

        <div class="relative z-10 mb-8 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-purple-600">Arsip Rekap Tahunan</h1>
                <p class="text-slate-500 mt-1">Monitoring kinerja keuangan per periode tahun buku</p>
            </div>
        </div>

        <div class="relative z-10 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <?php foreach($data_rekap as $data): ?>
            <div class="glass-card p-6 border-t-4 <?= ($data['status']=='Berlangsung') ? 'border-yellow-400' : 'border-green-500' ?> transition hover:-translate-y-1 hover:shadow-xl flex flex-col h-full justify-between relative group">
                
                <div class="absolute top-3 right-3 z-20 opacity-0 group-hover:opacity-100 transition-opacity">
                    <form method="POST" onsubmit="return confirm('Hapus Card Tahun <?= $data['tahun'] ?>? (Data transaksi AMAN)')">
                        <input type="hidden" name="id_hapus" value="<?= $data['id'] ?>">
                        <input type="hidden" name="tahun_hapus" value="<?= $data['tahun'] ?>">
                        <button type="submit" name="hapus_tahun" class="text-slate-300 hover:text-red-500 transition-colors p-1">
                            <i class="fas fa-times-circle text-xl"></i>
                        </button>
                    </form>
                </div>

                <div>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-4xl font-bold text-slate-700"><?= $data['tahun'] ?></h2>
                            <span class="text-[10px] px-2 py-1 rounded-full font-bold uppercase tracking-wider <?= ($data['status']=='Berlangsung') ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700' ?>">
                                <?= $data['status'] ?>
                            </span>
                        </div>
                        <div class="w-12 h-12 rounded-full <?= ($data['status']=='Berlangsung') ? 'bg-yellow-50' : 'bg-green-50' ?> flex items-center justify-center text-xl shadow-inner mr-6">
                            <i class="fas <?= ($data['status']=='Berlangsung') ? 'fa-hourglass-half text-yellow-500' : 'fa-check-circle text-green-500' ?>"></i>
                        </div>
                    </div>

                    <div class="space-y-3 mb-6 bg-white/50 p-4 rounded-xl">
                        <div class="flex justify-between text-sm items-center">
                            <span class="text-slate-500 font-medium">Pendapatan</span>
                            <span class="font-bold text-green-600">+ <?= formatRupiah($data['pendapatan']) ?></span>
                        </div>
                        <div class="flex justify-between text-sm items-center">
                            <span class="text-slate-500 font-medium">Beban</span>
                            <span class="font-bold text-red-600">- <?= formatRupiah($data['beban']) ?></span>
                        </div>
                        <div class="h-px bg-slate-300 my-2 border-dashed border-t"></div>
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-slate-700 text-sm uppercase">Laba Bersih</span>
                            <span class="font-bold text-lg <?= ($data['laba_bersih']>=0)?'text-green-600':'text-red-600' ?>">
                                <?= formatRupiah($data['laba_bersih']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <a href="laba_rugi.php?tgl_mulai=<?= $data['tahun'] ?>-01-01&tgl_selesai=<?= $data['tahun'] ?>-12-31" target="_blank" 
                       class="w-full py-2 rounded-lg border-2 border-blue-100 hover:border-blue-500 text-blue-600 hover:text-blue-700 font-bold transition flex justify-center items-center gap-2 bg-white">
                        <i class="fas fa-external-link-alt"></i> Lihat Detail Laporan
                    </a>

                    <?php if ($data['status'] == 'Berlangsung'): ?>
                        <form method="POST" onsubmit="return confirm('ARSIPKAN TAHUN <?= $data['tahun'] ?>?\n\n- Data angka akan dikunci.\n- Status berubah jadi Selesai.')">
                            <input type="hidden" name="tahun" value="<?= $data['tahun'] ?>">
                            <button type="submit" name="selesaikan_tahun" class="w-full py-3 rounded-lg bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-white font-bold shadow-md hover:shadow-lg transition flex justify-center items-center gap-2">
                                <i class="fas fa-archive"></i> Arsipkan Tahun Ini
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center">
                            <p class="text-[10px] text-slate-400 italic">
                                <i class="fas fa-lock mr-1"></i> Diarsipkan pada: <?= date('d M Y', strtotime($data['tgl_tutup'])) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>

        </div>
    </div>

</body>
</html>
