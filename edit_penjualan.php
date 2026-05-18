<?php
session_start();
include 'koneksi.php';
include 'fungsi_akuntansi.php';

// ===================== 1. CEK LOGIN =====================
if (!isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
if (!in_array($role_user, ['admin', 'owner', 'akuntan'])) {
    echo "<script>alert('Akses Ditolak!'); window.location='dashboard.php';</script>"; 
    exit;
}

// ===================== 2. AMBIL DATA TRANSAKSI =====================
$no_reff = isset($_GET['no_reff']) ? mysqli_real_escape_string($koneksi, $_GET['no_reff']) : '';
$id_pesanan = str_replace('ORD-', '', $no_reff);

if (empty($no_reff) || !str_starts_with($no_reff, 'ORD-')) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Referensi transaksi tidak valid!'
        }).then(() => {
            window.location.href = 'jurnal_umum.php';
        });
    </script>";
    exit;
}

// Ambil data transaksi penjualan
$query_transaksi = "SELECT 
    j.*,
    a.nama_akun,
    a.kategori,
    p.total_harga,
    p.nama_customer,
    p.tanggal as tanggal_pesanan,
    p.jenis_pembayaran,
    p.keterangan as keterangan_pesanan
    FROM jurnal_umum j
    LEFT JOIN akun_coa a ON j.kode_akun = a.kode_akun
    LEFT JOIN pesanan p ON j.no_reff = CONCAT('ORD-', p.id)
    WHERE j.no_reff = '$no_reff'
    ORDER BY 
        CASE WHEN j.debit > 0 THEN 0 ELSE 1 END ASC,
        j.id ASC";

$result = mysqli_query($koneksi, $query_transaksi);

if (mysqli_num_rows($result) === 0) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Transaksi tidak ditemukan!'
        }).then(() => {
            window.location.href = 'jurnal_umum.php';
        });
    </script>";
    exit;
}

$entries = [];
$transaksi_data = null;
$total_debit = 0;
$total_kredit = 0;
$akun_piutang_sekarang = '';
$metode_bayar_sekarang = 'tunai';
$nominal_penjualan = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $entries[] = $row;
    $total_debit += $row['debit'];
    $total_kredit += $row['kredit'];
    
    if ($transaksi_data === null) {
        $transaksi_data = $row;
    }
    
    // Deteksi metode pembayaran berdasarkan akun debit
    if ($row['debit'] > 0 && strpos($row['nama_akun'], 'Kas') !== false) {
        $metode_bayar_sekarang = 'tunai';
    } elseif ($row['debit'] > 0 && strpos($row['nama_akun'], 'Piutang') !== false) {
        $metode_bayar_sekarang = 'kredit';
        $akun_piutang_sekarang = $row['kode_akun'];
    }
    
    // Ambil nominal penjualan dari kredit
    if ($row['kredit'] > 0 && (strpos($row['nama_akun'], 'Penjualan') !== false || $row['kode_akun'] == '40000' || $row['kode_akun'] == '41000')) {
        $nominal_penjualan = $row['kredit'];
    }
}

// ===================== 3. AMBIL DATA AKUN COA =====================
// Ambil semua akun COA
$q_akun = mysqli_query($koneksi, "SELECT * FROM akun_coa ORDER BY kode_akun ASC");
$daftar_akun = []; 
while($r = mysqli_fetch_assoc($q_akun)) { 
    $daftar_akun[] = $r; 
}

// Ambil akun untuk tunai (Kas)
$akun_kas = [];
$q_kas = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE kategori = 'Aset' AND (nama_akun LIKE '%Kas%' OR kode_akun LIKE '111%') ORDER BY kode_akun ASC");
while($r = mysqli_fetch_assoc($q_kas)) {
    $akun_kas[] = $r;
}

// Ambil akun untuk piutang
$akun_piutang = [];
$q_piutang = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE kategori = 'Aset' AND nama_akun LIKE '%Piutang%' ORDER BY kode_akun ASC");
while($r = mysqli_fetch_assoc($q_piutang)) {
    $akun_piutang[] = $r;
}

// Ambil akun untuk pendapatan penjualan
$akun_pendapatan = [];
$q_pendapatan = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE kategori = 'Pendapatan' AND (nama_akun LIKE '%Penjualan%' OR kode_akun IN ('40000', '41000')) ORDER BY kode_akun ASC");
while($r = mysqli_fetch_assoc($q_pendapatan)) {
    $akun_pendapatan[] = $r;
}

// ===================== 4. PROSES UPDATE TRANSAKSI =====================
$pesan = "";
if (isset($_POST['update_transaksi_penjualan'])) {
    $no_reff_update = $_POST['no_reff'];
    $akun_debit_baru = $_POST['akun_debit'];
    $akun_kredit_baru = $_POST['akun_kredit'];
    $keterangan_update = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $metode_bayar_update = $_POST['metode_bayar'];
    
    // Validasi input
    if (empty($akun_debit_baru) || empty($akun_kredit_baru)) {
        $pesan = "<script>Swal.fire({icon: 'error', title: 'Error!', text: 'Akun debit dan kredit harus dipilih!'});</script>";
    } elseif ($nominal_penjualan <= 0) {
        $pesan = "<script>Swal.fire({icon: 'error', title: 'Error!', text: 'Nominal penjualan tidak valid!'});</script>";
    } else {
        mysqli_autocommit($koneksi, false);
        
        try {
            // Hapus entri lama untuk no_reff ini
            $q_hapus = mysqli_query($koneksi, "DELETE FROM jurnal_umum WHERE no_reff = '$no_reff_update'");
            if (!$q_hapus) {
                throw new Exception("Gagal menghapus entri lama: " . mysqli_error($koneksi));
            }
            
            // Buat keterangan umum
            $keterangan_umum = "Penjualan " . 
                               ($transaksi_data['nama_customer'] ? "kepada " . $transaksi_data['nama_customer'] : "kepada customer") . 
                               " (" . ($metode_bayar_update == 'tunai' ? 'Tunai' : 'Kredit') . ")";
            
            // Entri 1: Debit ke akun yang dipilih
            $keterangan_debit = $keterangan_update ?: 
                               ($metode_bayar_update == 'tunai' ? "Penerimaan tunai penjualan" : "Piutang penjualan");
            
            $query1 = "INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, keterangan_umum, kode_akun, debit, kredit, created_at) 
                      VALUES ('{$transaksi_data['tanggal']}', '$no_reff_update', '$keterangan_debit', '$keterangan_umum', 
                              '$akun_debit_baru', '$nominal_penjualan', '0', NOW())";
            
            if (!mysqli_query($koneksi, $query1)) {
                throw new Exception("Gagal menyimpan entri debit: " . mysqli_error($koneksi));
            }
            
            // Entri 2: Kredit ke akun pendapatan yang dipilih
            $keterangan_kredit = $keterangan_update ?: "Pendapatan penjualan";
            
            $query2 = "INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, keterangan_umum, kode_akun, debit, kredit, created_at) 
                      VALUES ('{$transaksi_data['tanggal']}', '$no_reff_update', '$keterangan_kredit', '$keterangan_umum', 
                              '$akun_kredit_baru', '0', '$nominal_penjualan', NOW())";
            
            if (!mysqli_query($koneksi, $query2)) {
                throw new Exception("Gagal menyimpan entri kredit: " . mysqli_error($koneksi));
            }
            
            // Cari dan salin entri HPP jika ada
            foreach ($entries as $entry) {
                if (strpos($entry['nama_akun'], 'HPP') !== false || $entry['kode_akun'] == '51000' || $entry['kode_akun'] == '50001') {
                    // Entri 3: Debit HPP
                    $query3 = "INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, keterangan_umum, kode_akun, debit, kredit, created_at) 
                              VALUES ('{$transaksi_data['tanggal']}', '$no_reff_update', 'HPP penjualan', '$keterangan_umum', 
                                      '{$entry['kode_akun']}', '{$entry['debit']}', '0', NOW())";
                    
                    if (!mysqli_query($koneksi, $query3)) {
                        throw new Exception("Gagal menyimpan entri HPP: " . mysqli_error($koneksi));
                    }
                    
                    // Entri 4: Kredit Persediaan
                    // Cari akun persediaan
                    $akun_persediaan = '11300'; // Default
                    $q_persediaan = mysqli_query($koneksi, "SELECT kode_akun FROM akun_coa WHERE nama_akun LIKE '%Persediaan%' LIMIT 1");
                    if ($r_persediaan = mysqli_fetch_assoc($q_persediaan)) {
                        $akun_persediaan = $r_persediaan['kode_akun'];
                    }
                    
                    $query4 = "INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, keterangan_umum, kode_akun, debit, kredit, created_at) 
                              VALUES ('{$transaksi_data['tanggal']}', '$no_reff_update', 'Pengurangan persediaan', '$keterangan_umum', 
                                      '$akun_persediaan', '0', '{$entry['debit']}', NOW())";
                    
                    if (!mysqli_query($koneksi, $query4)) {
                        throw new Exception("Gagal menyimpan entri persediaan: " . mysqli_error($koneksi));
                    }
                    break;
                }
            }
            
            mysqli_commit($koneksi);
            
            // Update metode pembayaran di tabel pesanan jika ada
            if (isset($transaksi_data['jenis_pembayaran'])) {
                $query_update_pesanan = "UPDATE pesanan SET jenis_pembayaran = '$metode_bayar_update' WHERE id = '$id_pesanan'";
                mysqli_query($koneksi, $query_update_pesanan);
            }
            
            $pesan = "<script>
                Swal.fire({
                    icon: 'success', 
                    title: 'Berhasil!', 
                    text: 'Transaksi penjualan berhasil diupdate.',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => { 
                    window.location.href = 'jurnal_umum.php'; 
                });
            </script>";
            
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan = "<script>
                Swal.fire({
                    icon: 'error', 
                    title: 'Gagal!', 
                    text: '" . addslashes($e->getMessage()) . "'
                });
            </script>";
        }
        
        mysqli_autocommit($koneksi, true);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaksi Penjualan - PT. SURYA CERAH SEMESTA</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F3F4F6;
        }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        .input-modern {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 0.5rem;
            padding: 0.75rem;
            width: 100%;
            transition: border-color 0.15s ease;
        }
        
        .input-modern:focus {
            background-color: white;
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .select2-container {
            z-index: 9999 !important;
        }
        
        .select2-container--open {
            z-index: 10001 !important;
        }
        
        .select2-container .select2-selection--single {
            height: 46px !important;
            border: 1px solid #E2E8F0 !important;
            border-radius: 0.5rem !important;
            background-color: #F8FAFC !important;
            display: flex;
            align-items: center;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px !important;
            right: 10px !important;
        }
        
        .select2-dropdown {
            z-index: 10002 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        }
        
        .debit-badge {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid #10b981;
        }
        
        .kredit-badge {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid #ef4444;
        }
        
        .btn-animate {
            transition: all 0.15s ease;
        }
        
        .btn-animate:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="text-gray-800">
    <?php include 'sidebar.php'; ?>
    
    <div class="md:ml-64 min-h-screen p-4 md:p-6">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Edit Transaksi Penjualan</h1>
                    <p class="text-gray-600 mt-1">Ubah metode pembayaran dan akun transaksi</p>
                </div>
                <a href="jurnal_umum.php" class="flex items-center gap-2 text-blue-600 hover:text-blue-800 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    <span>Kembali ke Jurnal</span>
                </a>
            </div>
        </div>
        
        <?php echo $pesan; ?>
        
        <!-- Informasi Transaksi -->
        <div class="card p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-600"></i>
                Informasi Transaksi
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">No. Referensi</p>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($transaksi_data['no_reff']) ?></p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Tanggal</p>
                    <p class="font-semibold text-gray-800"><?= date('d/m/Y', strtotime($transaksi_data['tanggal'])) ?></p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Customer</p>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($transaksi_data['nama_customer'] ?: 'Tidak diketahui') ?></p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Total Transaksi</p>
                    <p class="font-semibold text-green-600"><?= formatRupiah($nominal_penjualan > 0 ? $nominal_penjualan : $total_kredit) ?></p>
                </div>
            </div>
            
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Metode Pembayaran Saat Ini</p>
                    <p class="font-semibold text-blue-600">
                        <?= $metode_bayar_sekarang == 'tunai' ? 'Tunai' : 'Kredit' ?>
                    </p>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm text-gray-500 mb-1">Akun Debit Saat Ini</p>
                    <p class="font-semibold text-blue-600">
                        <?= $akun_piutang_sekarang ? 
                            $akun_piutang_sekarang . ' - ' . 
                            (array_reduce($entries, function($carry, $item) use ($akun_piutang_sekarang) {
                                return $carry ?: ($item['kode_akun'] == $akun_piutang_sekarang ? $item['nama_akun'] : '');
                            }, '')) : 
                            'Kas (Tunai)' ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Form Edit -->
        <div class="card p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-6 flex items-center gap-2">
                <i class="fas fa-edit text-blue-600"></i>
                Ubah Pengaturan Penjualan
            </h2>
            
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="no_reff" value="<?= htmlspecialchars($no_reff) ?>">
                
                <div class="space-y-6">
                    <!-- Metode Pembayaran -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Metode Pembayaran</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors <?= $metode_bayar_sekarang == 'tunai' ? 'border-blue-500 bg-blue-50' : '' ?>">
                                <input type="radio" name="metode_bayar" value="tunai" 
                                       class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300"
                                       <?= $metode_bayar_sekarang == 'tunai' ? 'checked' : '' ?>
                                       onchange="toggleAkunPilihan('tunai')">
                                <div class="ml-4">
                                    <span class="block text-sm font-medium text-gray-700">Tunai</span>
                                    <span class="block text-sm text-gray-500">Transaksi dibayar secara tunai</span>
                                </div>
                                <div class="ml-auto">
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Debit Kas</span>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-colors <?= $metode_bayar_sekarang == 'kredit' ? 'border-blue-500 bg-blue-50' : '' ?>">
                                <input type="radio" name="metode_bayar" value="kredit" 
                                       class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300"
                                       <?= $metode_bayar_sekarang == 'kredit' ? 'checked' : '' ?>
                                       onchange="toggleAkunPilihan('kredit')">
                                <div class="ml-4">
                                    <span class="block text-sm font-medium text-gray-700">Kredit</span>
                                    <span class="block text-sm text-gray-500">Transaksi secara kredit/piutang</span>
                                </div>
                                <div class="ml-auto">
                                    <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Debit Piutang</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Pilihan Akun Debit -->
                    <div id="akunDebitContainer">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Akun Debit</label>
                        <select name="akun_debit" id="akunDebitSelect" class="w-full p-3 input-modern" required>
                            <option value="">-- Pilih Akun Debit --</option>
                            <!-- Opsi untuk tunai -->
                            <optgroup label="Akun Kas (Tunai)">
                                <?php foreach($akun_kas as $akun): ?>
                                    <option value="<?= $akun['kode_akun'] ?>"
                                        <?= ($metode_bayar_sekarang == 'tunai' && $akun['kode_akun'] == ($akun_piutang_sekarang ?: '11101')) ? 'selected' : '' ?>>
                                        <?= $akun['kode_akun'] ?> - <?= $akun['nama_akun'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <!-- Opsi untuk piutang -->
                            <optgroup label="Akun Piutang (Kredit)">
                                <?php foreach($akun_piutang as $akun): ?>
                                    <option value="<?= $akun['kode_akun'] ?>"
                                        <?= ($metode_bayar_sekarang == 'kredit' && $akun['kode_akun'] == $akun_piutang_sekarang) ? 'selected' : '' ?>>
                                        <?= $akun['kode_akun'] ?> - <?= $akun['nama_akun'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Akun yang akan didebit saat transaksi</p>
                    </div>
                    
                    <!-- Pilihan Akun Kredit -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Akun Kredit (Pendapatan)</label>
                        <select name="akun_kredit" id="akunKreditSelect" class="w-full p-3 input-modern" required>
                            <option value="">-- Pilih Akun Pendapatan --</option>
                            <?php foreach($akun_pendapatan as $akun): ?>
                                <option value="<?= $akun['kode_akun'] ?>"
                                    <?php 
                                    // Cari akun kredit yang digunakan saat ini
                                    $akun_kredit_sekarang = '';
                                    foreach ($entries as $entry) {
                                        if ($entry['kredit'] > 0 && ($entry['kode_akun'] == '40000' || $entry['kode_akun'] == '41000' || strpos($entry['nama_akun'], 'Penjualan') !== false)) {
                                            $akun_kredit_sekarang = $entry['kode_akun'];
                                            break;
                                        }
                                    }
                                    echo ($akun_kredit_sekarang == $akun['kode_akun']) ? 'selected' : '';
                                    ?>>
                                    <?= $akun['kode_akun'] ?> - <?= $akun['nama_akun'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Akun pendapatan yang akan dikredit</p>
                    </div>
                    
                    <!-- Keterangan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Keterangan Penjualan</label>
                        <textarea name="keterangan" id="keterangan" rows="3" 
                                  class="w-full p-3 input-modern"
                                  placeholder="Masukkan keterangan untuk transaksi penjualan ini..."><?= 
                                  htmlspecialchars($transaksi_data['keterangan_pesanan'] ?: ($entries[0]['keterangan'] ?? '')) ?></textarea>
                    </div>
                    
                    <!-- Preview Perubahan -->
                    <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                        <h3 class="font-medium text-gray-800 mb-3 flex items-center gap-2">
                            <i class="fas fa-eye text-blue-600"></i>
                            Preview Jurnal Baru
                        </h3>
                        <div class="space-y-3" id="previewJurnal">
                            <!-- Preview akan diisi oleh JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Informasi Tambahan -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium text-yellow-800 mb-1">Informasi Penting</h4>
                                <ul class="text-sm text-yellow-700 space-y-1">
                                    <li>• Perubahan akan mengganti seluruh entri jurnal untuk transaksi ini</li>
                                    <li>• Entri HPP dan persediaan akan tetap dipertahankan jika ada</li>
                                    <li>• No. Referensi akan tetap sama (<?= htmlspecialchars($no_reff) ?>)</li>
                                    <li>• Pastikan akun yang dipilih sesuai dengan transaksi</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tombol Aksi -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t border-gray-200">
                        <a href="jurnal_umum.php" 
                           class="flex-1 text-center px-5 py-3 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition-all font-medium btn-animate">
                            <i class="fas fa-times mr-2"></i> Batal
                        </a>
                        <button type="submit" name="update_transaksi_penjualan" id="btnSimpan"
                                class="flex-1 text-center px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all font-medium btn-animate disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-save mr-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Detail Entri Saat Ini -->
        <div class="card p-6 mt-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fas fa-list text-blue-600"></i>
                Detail Jurnal Saat Ini
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Akun</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $counter = 1; ?>
                        <?php foreach($entries as $entry): ?>
                        <tr class="hover:bg-gray-50 transition-colors <?= $entry['debit'] > 0 ? 'debit-row' : 'kredit-row' ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= $counter++ ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($entry['kode_akun']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($entry['nama_akun']) ?></div>
                                <div class="text-xs mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $entry['debit'] > 0 ? 'debit-badge' : 'kredit-badge' ?>">
                                        <?= $entry['debit'] > 0 ? 'Debit' : 'Kredit' ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700 max-w-xs">
                                <?= htmlspecialchars($entry['keterangan']) ?>
                                <?php if (!empty($entry['keterangan_umum']) && $entry['keterangan_umum'] != $entry['keterangan']): ?>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= htmlspecialchars($entry['keterangan_umum']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= $entry['debit'] > 0 ? 'text-green-600' : 'text-gray-500' ?>">
                                <?= $entry['debit'] > 0 ? formatRupiah($entry['debit']) : '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= $entry['kredit'] > 0 ? 'text-red-600' : 'text-gray-500' ?>">
                                <?= $entry['kredit'] > 0 ? formatRupiah($entry['kredit']) : '-' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-100 font-bold">
                            <td class="px-6 py-4" colspan="3">TOTAL</td>
                            <td class="px-6 py-4 text-green-600"><?= formatRupiah($total_debit) ?></td>
                            <td class="px-6 py-4 text-red-600"><?= formatRupiah($total_kredit) ?></td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 italic text-sm" colspan="3">
                                <?php if (abs($total_debit - $total_kredit) <= 100): ?>
                                    <span class="text-green-600">
                                        <i class="fas fa-check-circle mr-1"></i> BALANCE
                                    </span>
                                <?php else: ?>
                                    <span class="text-red-600">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> TIDAK BALANCE
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 italic text-sm text-gray-500" colspan="2">
                                Selisih: <?= formatRupiah($total_debit - $total_kredit) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Inisialisasi Select2
        $(document).ready(function() {
            $('#akunDebitSelect, #akunKreditSelect').select2({
                placeholder: "Pilih Akun",
                width: '100%',
                dropdownParent: $('body')
            });
            
            // Update preview pertama kali
            updatePreview();
            
            // Event listeners untuk update preview
            $('input[name="metode_bayar"]').on('change', updatePreview);
            $('#akunDebitSelect, #akunKreditSelect').on('change', updatePreview);
            $('#keterangan').on('keyup', updatePreview);
            
            // Inisialisasi toggle akun pilihan
            toggleAkunPilihan('<?= $metode_bayar_sekarang ?>');
        });
        
        // Format Rupiah untuk display
        function formatRupiah(angka) {
            if (!angka) return 'Rp 0';
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(parseFloat(angka));
        }
        
        // Toggle pilihan akun berdasarkan metode bayar
        function toggleAkunPilihan(metode) {
            const selectElement = $('#akunDebitSelect');
            const options = selectElement.find('option');
            
            // Reset semua option untuk tampilkan
            options.each(function() {
                $(this).show();
            });
            
            // Sembunyikan optgroup yang tidak sesuai
            if (metode === 'tunai') {
                selectElement.find('optgroup[label="Akun Kas (Tunai)"]').show();
                selectElement.find('optgroup[label="Akun Piutang (Kredit)"]').hide();
                
                // Select default kas jika belum ada pilihan
                if (!selectElement.val()) {
                    const defaultKas = selectElement.find('optgroup[label="Akun Kas (Tunai)"] option:first').val();
                    selectElement.val(defaultKas).trigger('change');
                }
            } else {
                selectElement.find('optgroup[label="Akun Kas (Tunai)"]').hide();
                selectElement.find('optgroup[label="Akun Piutang (Kredit)"]').show();
                
                // Select default piutang jika belum ada pilihan
                if (!selectElement.val()) {
                    const defaultPiutang = selectElement.find('optgroup[label="Akun Piutang (Kredit)"] option:first').val();
                    selectElement.val(defaultPiutang).trigger('change');
                }
            }
            
            updatePreview();
        }
        
        // Update preview jurnal
        function updatePreview() {
            const metodeBayar = $('input[name="metode_bayar"]:checked').val();
            const akunDebit = $('#akunDebitSelect option:selected');
            const akunKredit = $('#akunKreditSelect option:selected');
            const keterangan = $('#keterangan').val();
            const total = <?= $nominal_penjualan ?: $total_kredit ?>;
            
            let previewHTML = '';
            
            if (akunDebit.val() && akunKredit.val()) {
                const namaDebit = akunDebit.text();
                const namaKredit = akunKredit.text();
                const keteranganText = keterangan || 'Penjualan kepada customer';
                
                previewHTML = `
                    <div class="space-y-4">
                        <!-- Header -->
                        <div class="flex items-center justify-between">
                            <div class="font-medium text-gray-700">Jurnal Baru:</div>
                            <div class="text-sm text-gray-500">
                                Total: <span class="font-bold text-green-600">${formatRupiah(total)}</span>
                            </div>
                        </div>
                        
                        <!-- Entri Debit -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-arrow-down text-green-600"></i>
                                    <span class="font-medium text-green-700">DEBIT</span>
                                </div>
                                <span class="font-bold text-green-600">${formatRupiah(total)}</span>
                            </div>
                            <div class="text-sm">
                                <div class="font-medium">${namaDebit}</div>
                                <div class="text-gray-600 mt-1">${keteranganText}</div>
                            </div>
                        </div>
                        
                        <!-- Entri Kredit -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-arrow-up text-red-600"></i>
                                    <span class="font-medium text-red-700">KREDIT</span>
                                </div>
                                <span class="font-bold text-red-600">${formatRupiah(total)}</span>
                            </div>
                            <div class="text-sm">
                                <div class="font-medium">${namaKredit}</div>
                                <div class="text-gray-600 mt-1">${keteranganText}</div>
                            </div>
                        </div>
                        
                        <!-- Status Balance -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-center gap-2 text-blue-700">
                                <i class="fas fa-check-circle"></i>
                                <span class="font-medium">JURNAL AKAN BALANCE</span>
                            </div>
                            <div class="text-sm text-blue-600 mt-1">
                                Total Debit (${formatRupiah(total)}) = Total Kredit (${formatRupiah(total)})
                            </div>
                        </div>
                    </div>
                `;
                
                // Enable submit button
                $('#btnSimpan').prop('disabled', false);
            } else {
                previewHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                        <p class="text-gray-500">Pilih akun debit dan kredit untuk melihat preview</p>
                    </div>
                `;
                
                // Disable submit button
                $('#btnSimpan').prop('disabled', true);
            }
            
            $('#previewJurnal').html(previewHTML);
        }
        
        // Validasi form sebelum submit
        $('#editForm').on('submit', function(e) {
            const akunDebit = $('#akunDebitSelect').val();
            const akunKredit = $('#akunKreditSelect').val();
            
            if (!akunDebit || !akunKredit) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Peringatan',
                    text: 'Silakan pilih akun debit dan kredit terlebih dahulu!'
                });
                return false;
            }
            
            // Konfirmasi sebelum submit
            e.preventDefault();
            
            Swal.fire({
                title: 'Konfirmasi Perubahan',
                html: `
                    <div class="text-left">
                        <p class="mb-2">Apakah Anda yakin ingin mengubah transaksi ini?</p>
                        <div class="bg-gray-50 p-3 rounded text-sm">
                            <div class="mb-1"><strong>No. Referensi:</strong> <?= htmlspecialchars($no_reff) ?></div>
                            <div class="mb-1"><strong>Total:</strong> ${formatRupiah(<?= $nominal_penjualan ?: $total_kredit ?>)}</div>
                            <div class="mb-1"><strong>Metode:</strong> ${$('input[name="metode_bayar"]:checked').val() == 'tunai' ? 'Tunai' : 'Kredit'}</div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Perubahan tidak dapat dibatalkan.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Ubah!',
                cancelButtonText: 'Batal',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Menyimpan...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit form secara manual
                    const formData = new FormData(this);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    }).then(response => response.text())
                      .then(html => {
                          // Replace entire page with response
                          document.open();
                          document.write(html);
                          document.close();
                      })
                      .catch(error => {
                          Swal.fire({
                              icon: 'error',
                              title: 'Error!',
                              text: 'Terjadi kesalahan saat menyimpan: ' + error.message
                          });
                      });
                }
            });
            
            return false;
        });
    </script>
</body>
</html>
