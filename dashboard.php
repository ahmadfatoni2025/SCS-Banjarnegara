<?php
session_start();

// Cek apakah user adalah admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Tentukan halaman aktif
$current_page = basename($_SERVER['PHP_SELF']);

// Ambil data user dari session
$nama_user = $_SESSION['user']['nama'] ?? 'Administrator';

// Include koneksi database dengan error handling
$koneksi = null;
try {
    include 'koneksi.php';
    if (!isset($koneksi) || !$koneksi) {
        throw new Exception("Koneksi database gagal");
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Fungsi untuk mendapatkan data statistik dengan error handling
function getStatistik($koneksi) {
    $statistik = [
        'total_pesanan_hari_ini' => 0,
        'bahan_tersedia' => 0,
        'outlet_aktif' => 0,
        'total_pendapatan_hari_ini' => 0,
        'total_stok_gudang' => 0,
        'pesanan_diproses' => 0,
        'last_update' => date('Y-m-d H:i:s')
    ];

    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        // Query untuk total pesanan hari ini
        $query_pesanan = "SELECT COUNT(*) as total FROM pesanan WHERE DATE(tgl_pesan) = CURDATE()";
        $result_pesanan = $koneksi->query($query_pesanan);
        if ($result_pesanan && $result_pesanan->num_rows > 0) {
            $row = $result_pesanan->fetch_assoc();
            $statistik['total_pesanan_hari_ini'] = (int)$row['total'];
        }

        // Query untuk pesanan yang sedang diproses
        $query_diproses = "SELECT COUNT(*) as total FROM pesanan WHERE status_pembayaran = 'Belum Bayar' AND status_pengiriman = 'Pending'";
        $result_diproses = $koneksi->query($query_diproses);
        if ($result_diproses && $result_diproses->num_rows > 0) {
            $row = $result_diproses->fetch_assoc();
            $statistik['pesanan_diproses'] = (int)$row['total'];
        }

        // Query untuk total pendapatan hari ini (DARI JURNAL)
        $query_pendapatan = "SELECT COALESCE(SUM(kredit - debit), 0) as total FROM jurnal_umum WHERE DATE(tanggal) = CURDATE() AND LEFT(kode_akun, 1) = '4' AND no_reff NOT LIKE 'CLS%'";
        $result_pendapatan = $koneksi->query($query_pendapatan);
        if ($result_pendapatan && $result_pendapatan->num_rows > 0) {
            $row = $result_pendapatan->fetch_assoc();
            $statistik['total_pendapatan_hari_ini'] = (float)$row['total'] ?? 0;
        }

        // Query untuk bahan tersedia
        $query_bahan = "SELECT COUNT(*) as total FROM gudang WHERE stok > 0";
        $result_bahan = $koneksi->query($query_bahan);
        if ($result_bahan && $result_bahan->num_rows > 0) {
            $row = $result_bahan->fetch_assoc();
            $statistik['bahan_tersedia'] = (int)$row['total'];
        }

        // Query untuk outlet aktif - FIXED: tambah kolom default untuk is_active
        $query_outlet = "SELECT COUNT(*) as total FROM user WHERE role = 'dapur'";
        $result_outlet = $koneksi->query($query_outlet);
        if ($result_outlet && $result_outlet->num_rows > 0) {
            $row = $result_outlet->fetch_assoc();
            $statistik['outlet_aktif'] = (int)$row['total'];
        }

        // Query untuk total stok gudang
        $query_total_stok = "SELECT COALESCE(SUM(stok), 0) as total_stok FROM gudang";
        $result_total_stok = $koneksi->query($query_total_stok);
        if ($result_total_stok && $result_total_stok->num_rows > 0) {
            $row = $result_total_stok->fetch_assoc();
            $statistik['total_stok_gudang'] = (float)$row['total_stok'] ?? 0;
        }

    } catch (Exception $e) {
        error_log("Error in getStatistik: " . $e->getMessage());
    }

    return $statistik;
}

// Fungsi untuk mendapatkan data laba kotor dengan perbaikan query
function getLabaKotor($koneksi, $period = 'month') {
    $laba_kotor = [
        'total_pendapatan' => 0,
        'total_biaya_bahan' => 0,
        'laba_kotor' => 0,
        'margin_laba' => 0,
        'data_per_dapur' => []
    ];
    
    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        // Buat klausa WHERE berdasarkan periode
        $whereClause = "";
        $dateField = "p.tgl_pesan";
        
        switch($period) {
            case 'today':
                $whereClause = "AND DATE($dateField) = CURDATE()";
                break;
            case 'week':
                $whereClause = "AND $dateField >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $whereClause = "AND MONTH($dateField) = MONTH(CURDATE()) AND YEAR($dateField) = YEAR(CURDATE())";
                break;
            case 'year':
                $whereClause = "AND YEAR($dateField) = YEAR(CURDATE())";
                break;
            case 'all':
                $whereClause = ""; // Tidak ada filter waktu
                break;
            default: // Default ke bulan ini
                $whereClause = "AND MONTH($dateField) = MONTH(CURDATE()) AND YEAR($dateField) = YEAR(CURDATE())";
        }

        // Query untuk total pendapatan dengan FIX (DARI JURNAL)
        $query_pendapatan = "SELECT 
            COALESCE(SUM(kredit - debit), 0) as total_pendapatan
        FROM jurnal_umum
        WHERE LEFT(kode_akun, 1) = '4' AND no_reff NOT LIKE 'CLS%'
            " . str_replace("p.tgl_pesan", "tanggal", $whereClause);
        
        $result_pendapatan = $koneksi->query($query_pendapatan);
        $total_pendapatan = 0;
        
        if ($result_pendapatan && $result_pendapatan->num_rows > 0) {
            $row = $result_pendapatan->fetch_assoc();
            $total_pendapatan = (float)$row['total_pendapatan'];
        }
        
        // Query untuk total biaya bahan (DARI JURNAL - Kategori 5 dan 6)
        $query_biaya = "SELECT 
            COALESCE(SUM(CASE WHEN LEFT(kode_akun, 1) = '5' THEN debit - kredit ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN LEFT(kode_akun, 1) = '6' THEN kredit - debit ELSE 0 END), 0) as total_biaya
        FROM jurnal_umum
        WHERE (LEFT(kode_akun, 1) = '5' OR LEFT(kode_akun, 1) = '6') AND no_reff NOT LIKE 'CLS%'
            " . str_replace("p.tgl_pesan", "tanggal", $whereClause);
        
        $result_biaya = $koneksi->query($query_biaya);
        $total_biaya = 0;
        
        if ($result_biaya && $result_biaya->num_rows > 0) {
            $row = $result_biaya->fetch_assoc();
            $total_biaya = (float)$row['total_biaya'];
        }
        
        // Query untuk data per dapur (disederhanakan untuk menghindari error)
        $query_per_dapur = "SELECT 
            u.nama as nama_dapur,
            COALESCE(SUM(p.total_harga), 0) as total_pendapatan,
            COALESCE(SUM(dp.jumlah * COALESCE(g.harga_beli, 0)), 0) as total_biaya
        FROM user u
        LEFT JOIN pesanan p ON u.id = p.id_dapur 
            AND p.status_pembayaran = 'Lunas'
            $whereClause
        LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
        LEFT JOIN gudang g ON dp.id_barang = g.id_barang
        WHERE u.role = 'dapur'
        GROUP BY u.id, u.nama";
        
        $result_per_dapur = $koneksi->query($query_per_dapur);
        $data_per_dapur = [];
        
        if ($result_per_dapur) {
            while ($row = $result_per_dapur->fetch_assoc()) {
                $pendapatan = (float)$row['total_pendapatan'];
                $biaya = (float)$row['total_biaya'];
                $laba = $pendapatan - $biaya;
                $margin = $pendapatan > 0 ? ($laba / $pendapatan) * 100 : 0;
                
                $data_per_dapur[$row['nama_dapur']] = [
                    'pendapatan' => $pendapatan,
                    'biaya_bahan' => $biaya,
                    'laba_kotor' => $laba,
                    'margin' => round($margin, 2)
                ];
            }
        }
        
        // Hitung total laba kotor
        $total_laba = $total_pendapatan - $total_biaya;
        $total_margin = $total_pendapatan > 0 ? ($total_laba / $total_pendapatan) * 100 : 0;
        
        $laba_kotor = [
            'total_pendapatan' => $total_pendapatan,
            'total_biaya_bahan' => $total_biaya,
            'laba_kotor' => $total_laba,
            'margin_laba' => round($total_margin, 2),
            'data_per_dapur' => $data_per_dapur
        ];
        
    } catch (Exception $e) {
        error_log("Error in getLabaKotor: " . $e->getMessage());
    }
    
    return $laba_kotor;
}

// Fungsi untuk mendapatkan data grafik aktivitas dapur dengan perbaikan
function getDataGrafikAktivitas($koneksi) {
    $data_grafik = [
        'labels' => [],
        'dapur_aktif' => [],
        'data_per_dapur' => [],
        'aktivitas_hari_ini' => [],
        'aktivitas_kemarin' => [],
        'aktivitas_bulan_lalu' => [],
        'metadata' => []
    ];
    
    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        // Generate labels dari tanggal 1 sampai akhir bulan
        $currentYear = date('Y');
        $currentMonth = date('m');
        $hariIni = date('d');
        $jumlahHari = date('t');
        
        // Buat array labels untuk seluruh bulan
        $labels = [];
        for ($i = 1; $i <= $jumlahHari; $i++) {
            $timestamp = mktime(0, 0, 0, $currentMonth, $i, $currentYear);
            $labels[] = date('d M', $timestamp);
        }
        
        $data_grafik['labels'] = $labels;
        
        // Query untuk mendapatkan dapur aktif
        $query_dapur = "SELECT id, nama FROM user WHERE role = 'dapur' ORDER BY nama";
        $result_dapur = $koneksi->query($query_dapur);
        
        $dapur_aktif = [];
        $dapur_ids = [];
        
        if ($result_dapur && $result_dapur->num_rows > 0) {
            while ($row = $result_dapur->fetch_assoc()) {
                $dapur_aktif[] = $row['nama'];
                $dapur_ids[$row['nama']] = (int)$row['id'];
            }
        }
        
        $data_grafik['dapur_aktif'] = $dapur_aktif;
        
        // Inisialisasi data untuk semua dapur
        $data_per_dapur = [];
        foreach ($dapur_aktif as $dapur) {
            $data_per_dapur[$dapur] = [];
            foreach ($labels as $label) {
                $data_per_dapur[$dapur][$label] = [
                    'pesanan' => 0,
                    'pendapatan' => 0
                ];
            }
        }
        
        // Query untuk data pesanan seluruh bulan
        $firstDayOfMonth = date('Y-m-01');
        $lastDayOfMonth = date('Y-m-t');
        
        if (!empty($dapur_ids)) {
            $dapur_conditions = implode(',', array_values($dapur_ids));
            
            // Query yang lebih sederhana untuk menghindari error
            $query_pesanan = "SELECT 
                u.nama as nama_dapur,
                DATE(p.tgl_pesan) as tanggal,
                COUNT(p.id_pesanan) as jumlah_pesanan,
                COALESCE(SUM(p.total_harga), 0) as total_pendapatan
            FROM pesanan p
            JOIN user u ON p.id_dapur = u.id
            WHERE p.tgl_pesan BETWEEN '$firstDayOfMonth 00:00:00' AND '$lastDayOfMonth 23:59:59'
                AND p.status_pembayaran = 'Lunas'
                AND u.id IN ($dapur_conditions)
            GROUP BY u.nama, DATE(p.tgl_pesan)
            ORDER BY tanggal ASC";
            
            $result_pesanan = $koneksi->query($query_pesanan);
            
            if ($result_pesanan && $result_pesanan->num_rows > 0) {
                while ($row = $result_pesanan->fetch_assoc()) {
                    if (!empty($row['tanggal'])) {
                        $tanggal = date('d M', strtotime($row['tanggal']));
                        $dapur = $row['nama_dapur'];
                        
                        if (isset($data_per_dapur[$dapur][$tanggal])) {
                            $data_per_dapur[$dapur][$tanggal] = [
                                'pesanan' => (int)$row['jumlah_pesanan'],
                                'pendapatan' => (float)$row['total_pendapatan']
                            ];
                        }
                    }
                }
            }
        }
        
        $data_grafik['data_per_dapur'] = $data_per_dapur;
        
// Data aktivitas hari ini - VERSI DIPERBAIKI
$query_hari_ini = "SELECT 
    u.nama as nama_dapur,
    COUNT(DISTINCT p.id_pesanan) as pesanan_hari_ini,  -- Tambah DISTINCT
    COALESCE(SUM(p.total_harga), 0) as pendapatan_hari_ini
FROM user u
LEFT JOIN pesanan p ON u.id = p.id_dapur 
    AND DATE(p.tgl_pesan) = CURDATE()
    AND p.status_pembayaran = 'Lunas'
WHERE u.role = 'dapur'
    AND (p.id_pesanan IS NOT NULL OR 1=1)  -- Pastikan menghitung NULL dengan benar
GROUP BY u.id, u.nama";
        
        $result_hari_ini = $koneksi->query($query_hari_ini);
        $aktivitas_hari_ini = [];
        
        if ($result_hari_ini && $result_hari_ini->num_rows > 0) {
            while ($row = $result_hari_ini->fetch_assoc()) {
                $aktivitas_hari_ini[$row['nama_dapur']] = [
                    'pesanan' => (int)$row['pesanan_hari_ini'],
                    'pendapatan' => (float)$row['pendapatan_hari_ini']
                ];
            }
        }
        
        // Isi dengan 0 untuk dapur yang tidak memiliki data hari ini
        foreach ($dapur_aktif as $dapur) {
            if (!isset($aktivitas_hari_ini[$dapur])) {
                $aktivitas_hari_ini[$dapur] = [
                    'pesanan' => 0,
                    'pendapatan' => 0
                ];
            }
        }
        
        $data_grafik['aktivitas_hari_ini'] = $aktivitas_hari_ini;
        
        // Data aktivitas kemarin
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $query_kemarin = "SELECT 
            u.nama as nama_dapur,
            COUNT(p.id_pesanan) as pesanan_kemarin,
            COALESCE(SUM(p.total_harga), 0) as pendapatan_kemarin
        FROM user u
        LEFT JOIN pesanan p ON u.id = p.id_dapur 
            AND DATE(p.tgl_pesan) = '$yesterday'
            AND p.status_pembayaran = 'Lunas'
        WHERE u.role = 'dapur'
        GROUP BY u.id, u.nama";
        
        $result_kemarin = $koneksi->query($query_kemarin);
        $aktivitas_kemarin = [];
        
        if ($result_kemarin && $result_kemarin->num_rows > 0) {
            while ($row = $result_kemarin->fetch_assoc()) {
                $aktivitas_kemarin[$row['nama_dapur']] = [
                    'pesanan' => (int)$row['pesanan_kemarin'],
                    'pendapatan' => (float)$row['pendapatan_kemarin']
                ];
            }
        }
        
        // Isi dengan 0 untuk dapur yang tidak memiliki data kemarin
        foreach ($dapur_aktif as $dapur) {
            if (!isset($aktivitas_kemarin[$dapur])) {
                $aktivitas_kemarin[$dapur] = [
                    'pesanan' => 0,
                    'pendapatan' => 0
                ];
            }
        }
        
        $data_grafik['aktivitas_kemarin'] = $aktivitas_kemarin;
        
        // Data aktivitas bulan lalu
        $firstDayLastMonth = date('Y-m-01', strtotime('-1 month'));
        $lastDayLastMonth = date('Y-m-t', strtotime('-1 month'));
        
        $query_bulan_lalu = "SELECT 
            u.nama as nama_dapur,
            COUNT(p.id_pesanan) as pesanan_bulan_lalu,
            COALESCE(SUM(p.total_harga), 0) as pendapatan_bulan_lalu
        FROM user u
        LEFT JOIN pesanan p ON u.id = p.id_dapur 
            AND p.tgl_pesan BETWEEN '$firstDayLastMonth 00:00:00' AND '$lastDayLastMonth 23:59:59'
            AND p.status_pembayaran = 'Lunas'
        WHERE u.role = 'dapur'
        GROUP BY u.id, u.nama";
        
        $result_bulan_lalu = $koneksi->query($query_bulan_lalu);
        $aktivitas_bulan_lalu = [];
        
        if ($result_bulan_lalu && $result_bulan_lalu->num_rows > 0) {
            while ($row = $result_bulan_lalu->fetch_assoc()) {
                $aktivitas_bulan_lalu[$row['nama_dapur']] = [
                    'pesanan' => (int)$row['pesanan_bulan_lalu'],
                    'pendapatan' => (float)$row['pendapatan_bulan_lalu']
                ];
            }
        }
        
        // Isi dengan 0 untuk dapur yang tidak memiliki data bulan lalu
        foreach ($dapur_aktif as $dapur) {
            if (!isset($aktivitas_bulan_lalu[$dapur])) {
                $aktivitas_bulan_lalu[$dapur] = [
                    'pesanan' => 0,
                    'pendapatan' => 0
                ];
            }
        }
        
        $data_grafik['aktivitas_bulan_lalu'] = $aktivitas_bulan_lalu;
        
        // Hitung total untuk metadata
        $total_pesanan_bulan_ini = 0;
        $total_pendapatan_bulan_ini = 0;
        
        foreach ($data_per_dapur as $dapur_data) {
            foreach ($dapur_data as $day_data) {
                $total_pesanan_bulan_ini += $day_data['pesanan'];
                $total_pendapatan_bulan_ini += $day_data['pendapatan'];
            }
        }
        
        // Metadata dengan informasi bulan lengkap
        $data_grafik['metadata'] = [
            'periode' => 'Bulan ' . date('F Y') . ' (1 - ' . date('t') . ')',
            'total_hari' => $jumlahHari,
            'hari_ini' => date('d M Y'),
            'bulan_berjalan' => date('F Y'),
            'hari_terakhir_bulan' => date('t'),
            'total_pesanan_bulan_ini' => $total_pesanan_bulan_ini,
            'total_pendapatan_bulan_ini' => $total_pendapatan_bulan_ini,
            'rata_pesanan_per_hari' => $jumlahHari > 0 ? round($total_pesanan_bulan_ini / $jumlahHari, 2) : 0,
            'rata_pendapatan_per_hari' => $jumlahHari > 0 ? round($total_pendapatan_bulan_ini / $jumlahHari, 2) : 0,
            'jumlah_dapur_aktif' => count($dapur_aktif),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        error_log("Error in getDataGrafikAktivitas: " . $e->getMessage());
        
        // Return data default untuk menghindari error
        $currentYear = date('Y');
        $currentMonth = date('m');
        $jumlahHari = date('t');
        
        $labels = [];
        for ($i = 1; $i <= $jumlahHari; $i++) {
            $timestamp = mktime(0, 0, 0, $currentMonth, $i, $currentYear);
            $labels[] = date('d M', $timestamp);
        }
        
        $data_grafik['labels'] = $labels;
        $data_grafik['dapur_aktif'] = ['SPPG MBS Wanayasa', 'MBG Kutabanjarnegara', 'SPPG Merden'];
        $data_grafik['metadata'] = [
            'periode' => 'Bulan ' . date('F Y') . ' (1 - ' . date('t') . ')',
            'total_hari' => $jumlahHari,
            'hari_ini' => date('d M Y'),
            'bulan_berjalan' => date('F Y'),
            'note' => 'Data default - ' . $e->getMessage()
        ];
    }
    
    return $data_grafik;
}

// Fungsi untuk mendapatkan aktivitas real-time dengan detail produk
function getAktivitasRealtime($koneksi) {
    $aktivitas = [];
    
    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        $query = "SELECT 
            p.id_pesanan,
            p.nama_pemesan,
            p.tgl_pesan,
            g.nama as nama_barang,
            dp.jumlah,
            dp.harga_satuan,
            (dp.jumlah * dp.harga_satuan) as harga_total,
            g.stok,
            g.satuan,
            p.total_harga,
            u.nama as nama_dapur
        FROM pesanan p
        JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
        JOIN gudang g ON dp.id_barang = g.id_barang
        JOIN user u ON p.id_dapur = u.id
        WHERE p.tgl_pesan >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY p.tgl_pesan DESC, u.nama ASC
        LIMIT 50";
        
        $result = $koneksi->query($query);
        if ($result && $result->num_rows > 0) {
            date_default_timezone_set('Asia/Jakarta');
            
            while ($row = $result->fetch_assoc()) {
                $waktu_sekarang = new DateTime();
                $waktu_pesan = new DateTime($row['tgl_pesan']);
                $selisih = $waktu_sekarang->diff($waktu_pesan);
                
                if ($selisih->days > 0) {
                    $time_ago = $selisih->days . ' hari lalu';
                } elseif ($selisih->h > 0) {
                    $time_ago = $selisih->h . ' jam lalu';
                } elseif ($selisih->i > 0) {
                    $time_ago = $selisih->i . ' menit lalu';
                } else {
                    $time_ago = 'Baru saja';
                }
                
                $aktivitas[] = [
                    'id_pesanan' => $row['id_pesanan'],
                    'dapur' => $row['nama_dapur'],
                    'barang' => $row['nama_barang'],
                    'jumlah' => $row['jumlah'],
                    'harga_satuan' => $row['harga_satuan'],
                    'harga_total' => $row['harga_total'],
                    'stok_sisa' => $row['stok'],
                    'satuan' => $row['satuan'],
                    'total_harga' => $row['total_harga'],
                    'waktu' => $time_ago,
                    'timestamp' => $row['tgl_pesan']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error in getAktivitasRealtime: " . $e->getMessage());
    }
    
    return $aktivitas;
}

// Fungsi untuk mendapatkan pesanan terbaru dengan perbaikan query
function getPesananTerbaru($koneksi) {
    $pesanan_terbaru = [];
    
    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        $query = "SELECT 
            p.id_pesanan,
            p.nama_pemesan,
            p.tgl_pesan,
            p.status_pembayaran,
            p.status_pengiriman,
            (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan = p.id_pesanan) as jumlah_item,
            p.total_harga,
            u.nama as nama_dapur
        FROM pesanan p
        JOIN user u ON p.id_dapur = u.id
        WHERE p.tgl_pesan >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY p.tgl_pesan DESC
        LIMIT 10";
        
        $result = $koneksi->query($query);
        if ($result && $result->num_rows > 0) {
            date_default_timezone_set('Asia/Jakarta');
            
            while ($row = $result->fetch_assoc()) {
                $waktu_sekarang = new DateTime();
                $waktu_pesan = new DateTime($row['tgl_pesan']);
                $selisih = $waktu_sekarang->diff($waktu_pesan);
                
                if ($selisih->days > 0) {
                    $time_ago = $selisih->days . ' hari lalu';
                } elseif ($selisih->h > 0) {
                    $time_ago = $selisih->h . ' jam lalu';
                } elseif ($selisih->i > 0) {
                    $time_ago = $selisih->i . ' menit lalu';
                } else {
                    $time_ago = 'Baru saja';
                }
                
                // Tentukan status berdasarkan kombinasi pembayaran dan pengiriman
                $status = 'diproses';
                $statusColor = 'bg-yellow-100 text-yellow-800';
                
                if ($row['status_pembayaran'] == 'Lunas' && $row['status_pengiriman'] == 'Done') {
                    $status = 'selesai';
                    $statusColor = 'bg-green-100 text-green-800';
                } elseif ($row['status_pembayaran'] == 'Lunas' && $row['status_pengiriman'] == 'Ongoing') {
                    $status = 'dikirim';
                    $statusColor = 'bg-blue-100 text-blue-800';
                } elseif ($row['status_pembayaran'] == 'Batal') {
                    $status = 'dibatalkan';
                    $statusColor = 'bg-red-100 text-red-800';
                }
                
                // Dapatkan barang yang dipesan
                $query_barang = "SELECT GROUP_CONCAT(g.nama SEPARATOR ', ') as barang_dipesan
                               FROM detail_pesanan dp
                               JOIN gudang g ON dp.id_barang = g.id_barang
                               WHERE dp.id_pesanan = " . $row['id_pesanan'];
                $result_barang = $koneksi->query($query_barang);
                $barang_dipesan = '';
                if ($result_barang && $result_barang->num_rows > 0) {
                    $row_barang = $result_barang->fetch_assoc();
                    $barang_dipesan = $row_barang['barang_dipesan'] ?? '';
                }
                
                $pesanan_terbaru[] = [
                    'id' => $row['id_pesanan'],
                    'customer' => $row['nama_pemesan'],
                    'dapur' => $row['nama_dapur'],
                    'items' => $row['jumlah_item'],
                    'barang_dipesan' => $barang_dipesan,
                    'time' => $time_ago,
                    'status' => $status,
                    'status_color' => $statusColor,
                    'total' => $row['total_harga'],
                    'timestamp' => $row['tgl_pesan']
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error in getPesananTerbaru: " . $e->getMessage());
    }
    
    return $pesanan_terbaru;
}

// Fungsi untuk mendapatkan data gabungan aktivitas dan pesanan
function getAktivitasGabungan($koneksi) {
    $aktivitas = getAktivitasRealtime($koneksi);
    $pesanan = getPesananTerbaru($koneksi);
    
    // Gabungkan dan urutkan berdasarkan timestamp
    $gabungan = [];
    
    foreach ($aktivitas as $item) {
        $gabungan[] = [
            'type' => 'aktivitas',
            'data' => $item,
            'timestamp' => $item['timestamp']
        ];
    }
    
    foreach ($pesanan as $item) {
        $gabungan[] = [
            'type' => 'pesanan',
            'data' => $item,
            'timestamp' => $item['timestamp']
        ];
    }
    
    // Urutkan berdasarkan timestamp terbaru
    usort($gabungan, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Batasi hanya 10 item terbaru
    return array_slice($gabungan, 0, 10);
}

// Fungsi untuk mendapatkan total income dengan filter periode
function getTotalIncome($koneksi, $period = 'today') {
    $total_income = 0;
    
    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        $query = "SELECT COALESCE(SUM(total_harga), 0) as total FROM pesanan WHERE status_pembayaran = 'Lunas'";
        
        switch($period) {
            case 'today':
                $query .= " AND DATE(tgl_pesan) = CURDATE()";
                break;
            case 'week':
                $query .= " AND tgl_pesan >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $query .= " AND MONTH(tgl_pesan) = MONTH(CURDATE()) AND YEAR(tgl_pesan) = YEAR(CURDATE())";
                break;
            case 'year':
                $query .= " AND YEAR(tgl_pesan) = YEAR(CURDATE())";
                break;
            case 'all':
                break;
            default:
                $query .= " AND DATE(tgl_pesan) = CURDATE()";
        }
        
        $result = $koneksi->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $total_income = (float)$row['total'];
        }
        
    } catch (Exception $e) {
        error_log("Error in getTotalIncome: " . $e->getMessage());
    }
    
    return $total_income;
}

// Fungsi untuk mendapatkan statistik income
function getIncomeStatistics($koneksi) {
    $stats = [
        'average_daily' => 0,
        'growth_rate' => 0,
        'previous_period' => 0
    ];
    
    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        // Rata-rata harian (7 hari terakhir)
        $query_avg = "SELECT 
            COALESCE(AVG(daily_total), 0) as avg_daily 
        FROM (
            SELECT DATE(tgl_pesan) as day, SUM(total_harga) as daily_total 
            FROM pesanan 
            WHERE status_pembayaran = 'Lunas' 
            AND tgl_pesan >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(tgl_pesan)
        ) as daily_totals";
        
        $result_avg = $koneksi->query($query_avg);
        if ($result_avg && $result_avg->num_rows > 0) {
            $row = $result_avg->fetch_assoc();
            $stats['average_daily'] = (float)$row['avg_daily'];
        }
        
        // Hitung pertumbuhan (minggu ini vs minggu lalu)
        $current_week = getTotalIncome($koneksi, 'week');
        
        $query_prev_week = "SELECT COALESCE(SUM(total_harga), 0) as total 
                           FROM pesanan 
                           WHERE status_pembayaran = 'Lunas' 
                           AND tgl_pesan BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
                           AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        
        $result_prev = $koneksi->query($query_prev_week);
        $previous_week = 0;
        if ($result_prev && $result_prev->num_rows > 0) {
            $row = $result_prev->fetch_assoc();
            $previous_week = (float)$row['total'];
        }
        
        // Hitung growth rate
        if ($previous_week > 0) {
            $stats['growth_rate'] = (($current_week - $previous_week) / $previous_week) * 100;
        } else {
            $stats['growth_rate'] = $current_week > 0 ? 100 : 0;
        }
        
        $stats['previous_period'] = $previous_week;
        
    } catch (Exception $e) {
        error_log("Error in getIncomeStatistics: " . $e->getMessage());
    }
    
    return $stats;
}

// Fungsi untuk mendapatkan data real-time (digunakan oleh AJAX)
function getRealTimeData($koneksi, $type) {
    $data = [];
    
    try {
        if (!$koneksi || $koneksi->connect_error) {
            throw new Exception("Koneksi database tidak valid");
        }

        switch($type) {
            case 'statistik':
                $data = getStatistik($koneksi);
                break;
                
            case 'pesanan_terbaru':
                $data = getPesananTerbaru($koneksi);
                break;
                
            case 'aktivitas_realtime':
                $data = getAktivitasRealtime($koneksi);
                break;
                
            case 'aktivitas_gabungan':
                $data = getAktivitasGabungan($koneksi);
                break;
                
            case 'grafik_aktivitas':
                $data = getDataGrafikAktivitas($koneksi);
                break;
                
            case 'laba_kotor':
                $period = $_GET['period'] ?? 'month';
                $data = getLabaKotor($koneksi, $period);
                break;
                
            case 'total_income':
                $period = $_GET['period'] ?? 'today';
                $data = [
                    'total_income' => getTotalIncome($koneksi, $period), 
                    'period' => $period,
                    'stats' => getIncomeStatistics($koneksi)
                ];
                break;
                
            default:
                $data = ['error' => 'Tipe data tidak valid'];
                break;
        }
    } catch (Exception $e) {
        error_log("Error in getRealTimeData: " . $e->getMessage());
        $data = ['error' => $e->getMessage()];
    }
    
    return $data;
}

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'statistik';
    $data = getRealTimeData($koneksi, $type);
    echo json_encode($data);
    exit;
}

// Ambil data awal untuk tampilan pertama
$statistik = getStatistik($koneksi);
$aktivitas_gabungan = getAktivitasGabungan($koneksi);
$grafik_aktivitas = getDataGrafikAktivitas($koneksi);
$laba_kotor = getLabaKotor($koneksi);
$income_stats = getIncomeStatistics($koneksi);

// Handle AJAX request untuk total income
if (isset($_GET['action']) && $_GET['action'] == 'get_total_income') {
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? 'today';
    $total_income = getTotalIncome($koneksi, $period);
    $stats = getIncomeStatistics($koneksi);
    echo json_encode([
        'total_income' => $total_income, 
        'period' => $period,
        'stats' => $stats
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Makan Bergizi Sehat</title>
    <!-- FAVICON LOGO -->
    <link rel="icon" href="logo_scs_jpg.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        :root { --primary: #2563eb; --border: #e5e7eb; }
        .card-hover { transition: all 0.50s; border: 1px solid var(--border); }
        .card-hover:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .fade-in { animation: fadeIn 0.5s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .admin-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .custom-scrollbar {
            max-height: 400px;
            overflow-y: auto;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .aksi-cepat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .aksi-cepat-item {
            display: block;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .aksi-cepat-item:hover {
            border-color: #3b82f6;
            background-color: #eff6ff;
            transform: translateY(-2px);
        }
        .profit-positive { color: #10b981; }
        .profit-negative { color: #ef4444; }
        .profit-neutral { color: #6b7280; }
        .income-card {
            transition: all 0.3s ease;
            border-left: 4px solid #10B981;
        }
        .laba-kotor-card {
            transition: all 0.3s ease;
            border-left: 4px solid #8B5CF6;
        }
        .laba-kotor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.15);
        }
        .hide-scrollbar {
          scrollbar-width: none;
          -ms-overflow-style: none;
        }
        .hide-scrollbar::-webkit-scrollbar {
          display: none;
        }
        .tab-active {
            background-color: white;
            color: #3b82f6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-weight: 600;
        }
        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 10px;
            margin-bottom: 5px;
            padding: 4px 8px;
            background-color: #f8fafc;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .legend-item:hover {
            background-color: #e2e8f0;
            transform: translateY(-1px);
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .chart-tooltip {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .highlight-dapur {
            border-width: 3px !important;
            opacity: 1 !important;
        }
        .dapur-inactive {
            opacity: 0.4;
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
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
            border-radius: 0 0 0 100%;
        }
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .dashboard-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }
        .chart-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 8px;
        }
        .date-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .data-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            text-align: center;
            color: #6b7280;
        }
        .data-empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        .data-empty-state p {
            margin: 0.5rem 0;
        }
        .trend-up {
            color: #10b981;
            display: inline-flex;
            align-items: center;
        }
        .trend-down {
            color: #ef4444;
            display: inline-flex;
            align-items: center;
        }
        .trend-neutral {
            color: #6b7280;
            display: inline-flex;
            align-items: center;
        }
        .sidebar-minimized ~ .ml-64 {
            margin-left: 4rem !important;
        }
        @media (max-width: 768px) {
            .ml-64 {
                margin-left: 0 !important;
            }
            .grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .chart-container {
                height: 250px;
            }
        }
        @media (max-width: 640px) {
            .grid-cols-4 {
                grid-template-columns: 1fr !important;
            }
            .grid-cols-1.lg\\:grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .chart-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 300px;
            color: #6b7280;
        }
        .chart-loading i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
<?php 
if (file_exists('sidebar.php')) {
    include 'sidebar.php'; 
} else {
    echo '<div class="hidden"></div>';
}
?>

<div id="refreshIndicator" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 hidden">
    <div class="bg-green-500 text-white px-4 py-2 rounded-full shadow-lg flex items-center space-x-2 animate-bounce">
        <i class="fas fa-sync-alt fa-spin text-sm"></i>
        <span class="text-sm font-medium">Memperbarui data...</span>
    </div>
</div>

<div class="ml-64 flex flex-col min-h-screen">
    <main class="flex-1 p-6">
        <div class="fade-in">
            <!-- Header Dashboard -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <div class="flex items-center space-x-3">
                        <h2 class="text-2xl font-bold text-gray-800">Dashboard Admin</h2>
                        <span class="admin-badge px-3 py-1 rounded-full text-xs font-medium">
                            <i class="fas fa-shield-alt mr-1"></i>Administrator
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 flex items-center mt-1">
                        <i class="fas fa-clock mr-1 text-blue-500"></i>
                        <span class="font-medium">Waktu Indonesia:</span>
                        <span id="waktuIndonesia" class="ml-1">
                            <?php 
                            date_default_timezone_set('Asia/Jakarta');
                            echo date('H:i:s');
                            ?>
                        </span>
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="refreshData()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-all duration-200 flex items-center shadow-sm hover:shadow-md">
                        <i class="fas fa-sync-alt mr-2 loading-spinner hidden" id="refreshSpinner"></i>
                        <span id="refreshText">Refresh Data</span>
                    </button>
                    <span class="text-xs text-gray-500 hidden md:inline" id="lastUpdateTime">
                        Update: <?php echo date('H:i:s'); ?>
                    </span>
                </div>
            </div>

            <!-- Statistik Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8" id="statistikCards">
                <div class="stat-card card-hover bg-white rounded-xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Total Pesanan</p>
                            <p class="text-2xl font-bold text-gray-900" id="totalPesanan"><?php echo $statistik['total_pesanan_hari_ini']; ?></p>
                            <div class="flex items-center mt-1">
                                <p class="text-xs text-gray-500">Hari ini</p>
                                <?php if ($statistik['pesanan_diproses'] > 0): ?>
                                <span class="ml-2 text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded-full">
                                    <?php echo $statistik['pesanan_diproses']; ?> diproses
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card card-hover bg-white rounded-xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Bahan Tersedia</p>
                            <p class="text-2xl font-bold text-gray-900" id="bahanTersedia"><?php echo $statistik['bahan_tersedia']; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Total stok: <?php echo number_format($statistik['total_stok_gudang']); ?> unit</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-boxes-stacked text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card card-hover bg-white rounded-xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Outlet Aktif</p>
                            <p class="text-2xl font-bold text-gray-900" id="outletAktif"><?php echo $statistik['outlet_aktif']; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Total dapur aktif</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-store text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card card-hover bg-white rounded-xl p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 mb-1">Pendapatan</p>
                            <p class="text-2xl font-bold text-gray-900" id="pendapatanHariIni">Rp <?php echo number_format($statistik['total_pendapatan_hari_ini'], 0, ',', '.'); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Hari ini</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Income & Laba Kotor -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="income-card bg-white rounded-xl p-6 card-hover">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Total Pendapatan</h3>
                            <p class="text-sm text-gray-500" id="incomePeriodText">Hari ini</p>
                        </div>
                        <div class="relative self-end md:self-auto">
                            <select id="incomePeriod" onchange="changeIncomePeriod(this.value)" 
                                    class="bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer appearance-none pr-8">
                                <option value="today">Hari Ini</option>
                                <option value="week">7 Hari Terakhir</option>
                                <option value="month">Bulan Ini</option>
                                <option value="year">Tahun Ini</option>
                                <option value="all">Semua Waktu</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                <i class="fas fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-end justify-between mb-4">
                        <div>
                            <p class="text-2xl md:text-3xl font-bold text-green-600 mb-2" id="totalIncomeAmount">
                                Rp <?php echo number_format(getTotalIncome($koneksi, 'today'), 0, ',', '.'); ?>
                            </p>
                            <div class="flex items-center text-sm text-gray-600" id="incomeTrend">
                                <i class="fas fa-chart-line mr-1 text-green-500"></i>
                                <span>Total pendapatan</span>
                            </div>
                        </div>
                        <div class="w-12 h-12 md:w-14 md:h-14 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-wallet text-green-600 text-xl md:text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-sm pt-4 border-t border-gray-100">
                        <div>
                            <p class="text-gray-500 mb-1">Rata-rata Harian</p>
                            <p class="font-semibold text-gray-900" id="averageDaily">
                                Rp <?php echo number_format($income_stats['average_daily'], 0, ',', '.'); ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-gray-500 mb-1">Pertumbuhan</p>
                            <p class="font-semibold <?php echo $income_stats['growth_rate'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>" id="growthRate">
                                <span class="<?php echo $income_stats['growth_rate'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                                    <i class="fas fa-<?php echo $income_stats['growth_rate'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> mr-1"></i>
                                    <?php echo number_format(abs($income_stats['growth_rate']), 1); ?>%
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="laba-kotor-card bg-white rounded-xl p-6 card-hover">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-2">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Laba Kotor</h3>
                            <p class="text-sm text-gray-500" id="labaPeriodText">Bulan ini</p>
                        </div>
                        <div class="flex items-center space-x-2 self-end md:self-auto">
                            <div class="relative">
                                <select id="labaPeriod" onchange="changeLabaPeriod(this.value)" 
                                        class="bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 cursor-pointer appearance-none pr-8">
                                    <option value="today">Hari Ini</option>
                                    <option value="week">7 Hari Terakhir</option>
                                    <option value="month" selected>Bulan Ini</option>
                                    <option value="year">Tahun Ini</option>
                                    <option value="all">Semua Waktu</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <button onclick="showLabaKotor()" class="text-purple-600 hover:text-purple-800 transition p-1 hover:bg-purple-50 rounded-full w-8 h-8 flex items-center justify-center" title="Lihat detail">
                                <i class="fas fa-expand-alt text-sm"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex items-end justify-between mb-4">
                        <div>
                            <p class="text-2xl md:text-3xl font-bold 
                                <?php 
                                echo $laba_kotor['laba_kotor'] > 0 ? 'text-green-600' : 
                                     ($laba_kotor['laba_kotor'] < 0 ? 'text-red-600' : 'text-gray-600'); 
                                ?> 
                                mb-2" id="totalLabaKotor">
                                Rp <?php echo number_format($laba_kotor['laba_kotor'], 0, ',', '.'); ?>
                            </p>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-chart-line mr-1 
                                    <?php 
                                    echo $laba_kotor['margin_laba'] > 0 ? 'text-green-500' : 
                                         ($laba_kotor['margin_laba'] < 0 ? 'text-red-500' : 'text-gray-500'); 
                                    ?>"></i>
                                <span>Margin: <span id="labaMargin" class="font-medium"><?php echo $laba_kotor['margin_laba']; ?>%</span></span>
                            </div>
                        </div>
                        <div class="w-12 h-12 md:w-14 md:h-14 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-pie text-purple-600 text-xl md:text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-3 pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-gray-600">Pendapatan</span>
                            </div>
                            <span class="font-semibold text-gray-900" id="labaPendapatan">
                                Rp <?php echo number_format($laba_kotor['total_pendapatan'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-orange-500 rounded-full mr-2"></div>
                                <span class="text-gray-600">Biaya Bahan</span>
                            </div>
                            <span class="font-semibold text-gray-900" id="labaBiaya">
                                Rp <?php echo number_format($laba_kotor['total_biaya_bahan'], 0, ',', '.'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafik Aktivitas Dapur -->
            <div class="card-hover bg-white rounded-xl p-6 mb-8">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Aktivitas Dapur</h3>
                        <p class="text-sm text-gray-500" id="grafikPeriodeText">
                            <?php 
                            if (isset($grafik_aktivitas['metadata']['periode'])) {
                                echo $grafik_aktivitas['metadata']['periode'];
                            } else {
                                echo 'Periode: 1 - ' . date('t') . ' ' . date('F Y');
                            }
                            ?>
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <?php if (isset($grafik_aktivitas['metadata']['total_pesanan_bulan_ini'])): ?>
                        <div class="text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded-full font-medium">
                            <i class="fas fa-shopping-cart mr-1"></i>
                            <?php echo number_format($grafik_aktivitas['metadata']['total_pesanan_bulan_ini']); ?> pesanan
                        </div>
                        <?php endif; ?>
                        
                        <span class="text-xs text-gray-500 hidden md:inline" id="grafikUpdateTime">
                            Update: <?php echo date('H:i:s'); ?>
                        </span>
                        <button onclick="refreshGrafik()" class="text-gray-500 hover:text-blue-600 transition hover:bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center" title="Refresh grafik">
                            <i class="fas fa-sync-alt text-sm"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Tab Navigasi -->
                <div class="flex space-x-1 mb-6 bg-gray-100 rounded-lg p-1">
                    <button onclick="switchChart('pesanan')" id="tabPesanan" class="flex-1 py-2.5 px-3 text-sm font-medium rounded-lg tab-active transition-all duration-200">
                        <i class="fas fa-shopping-cart mr-2"></i> Pesanan
                    </button>
                    <button onclick="switchChart('pendapatan')" id="tabPendapatan" class="flex-1 py-2.5 px-3 text-sm font-medium rounded-lg text-gray-600 hover:text-gray-900 transition-all duration-200">
                        <i class="fas fa-money-bill-wave mr-2"></i> Pendapatan
                    </button>
                    <button onclick="switchChart('perbandingan')" id="tabPerbandingan" class="flex-1 py-2.5 px-3 text-sm font-medium rounded-lg text-gray-600 hover:text-gray-900 transition-all duration-200">
                        <i class="fas fa-chart-bar mr-2"></i> Perbandingan
                    </button>
                </div>
                
                <!-- Container Chart -->
                <div class="chart-container mb-4" id="chartContainer">
                    <div class="chart-loading" id="chartLoading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Memuat grafik...</p>
                    </div>
                    <canvas id="aktivitasChart"></canvas>
                </div>
                
                <!-- Legend dan Kontrol -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex flex-wrap gap-2" id="chartLegend">
                        <!-- Legend akan diisi oleh JavaScript -->
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="toggleAllDapur()" id="toggleAllBtn" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-eye mr-1"></i> Tampilkan Semua
                        </button>
                        <button onclick="downloadChart()" class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-download mr-1"></i> Unduh
                        </button>
                    </div>
                </div>
                
                <!-- Ringkasan Statistik -->
                <?php if (isset($grafik_aktivitas['metadata'])): ?>
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 pt-6 border-t border-gray-100">
                    <div class="text-center">
                        <p class="text-sm text-gray-500 mb-1">Total Pesanan Bulan Ini</p>
                        <p class="text-xl font-bold text-gray-900" id="totalPesananBulanIni">
                            <?php echo number_format($grafik_aktivitas['metadata']['total_pesanan_bulan_ini']); ?>
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 mb-1">Total Pendapatan</p>
                        <p class="text-xl font-bold text-green-600" id="totalPendapatanBulanIni">
                            Rp <?php echo number_format($grafik_aktivitas['metadata']['total_pendapatan_bulan_ini'], 0, ',', '.'); ?>
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 mb-1">Rata-rata per Hari</p>
                        <p class="text-xl font-bold text-blue-600" id="rataPesananPerHari">
                            <?php echo number_format($grafik_aktivitas['metadata']['rata_pesanan_per_hari'], 1); ?> pesanan
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Modal Laba Kotor -->
            <div id="labaKotorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
                <div class="bg-white rounded-2xl p-6 w-full max-w-6xl max-h-[90vh] overflow-y-auto custom-scrollbar">
                    <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Analisis Laba Kotor</h3>
                            <p class="text-sm text-gray-500" id="modalLabaPeriodText">Bulan ini</p>
                        </div>
                        <button onclick="hideLabaKotor()" class="text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full w-10 h-10 flex items-center justify-center transition">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    
                    <!-- Ringkasan Laba Kotor -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-5 rounded-xl border border-blue-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-blue-600 font-medium mb-1">Total Pendapatan</p>
                                    <p class="text-2xl font-bold text-blue-700" id="modalTotalPendapatan">
                                        Rp <?php echo number_format($laba_kotor['total_pendapatan'], 0, ',', '.'); ?>
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-money-bill-wave text-blue-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-orange-50 p-5 rounded-xl border border-orange-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-orange-600 font-medium mb-1">Total Biaya Bahan</p>
                                    <p class="text-2xl font-bold text-orange-700" id="modalTotalBiaya">
                                        Rp <?php echo number_format($laba_kotor['total_biaya_bahan'], 0, ',', '.'); ?>
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-receipt text-orange-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 p-5 rounded-xl border border-green-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-green-600 font-medium mb-1">Laba Kotor</p>
                                    <p class="text-2xl font-bold 
                                        <?php 
                                        echo $laba_kotor['laba_kotor'] > 0 ? 'text-green-700' : 
                                             ($laba_kotor['laba_kotor'] < 0 ? 'text-red-700' : 'text-gray-700'); 
                                        ?>" 
                                        id="modalLabaKotor">
                                        Rp <?php echo number_format($laba_kotor['laba_kotor'], 0, ',', '.'); ?>
                                    </p>
                                    <p class="text-sm font-medium 
                                        <?php 
                                        echo $laba_kotor['margin_laba'] > 0 ? 'text-green-600' : 
                                             ($laba_kotor['margin_laba'] < 0 ? 'text-red-600' : 'text-gray-600'); 
                                        ?>" 
                                        id="modalMarginLaba">
                                        <i class="fas fa-chart-line mr-1"></i>
                                        Margin: <?php echo $laba_kotor['margin_laba']; ?>%
                                    </p>
                                </div>
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-chart-line text-green-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detail per Dapur -->
                    <div class="mb-8">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-3">
                            <h4 class="text-lg font-semibold text-gray-900">Detail per Dapur</h4>
                            <div class="relative">
                                <select id="modalLabaPeriod" onchange="changeModalLabaPeriod(this.value)" 
                                        class="bg-gray-50 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 cursor-pointer appearance-none pr-8">
                                    <option value="month" selected>Bulan Ini</option>
                                    <option value="week">7 Hari Terakhir</option>
                                    <option value="today">Hari Ini</option>
                                    <option value="year">Tahun Ini</option>
                                    <option value="all">Semua Waktu</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-gray-200">
                            <table class="w-full text-sm text-left">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 font-semibold">Dapur</th>
                                        <th class="px-6 py-3 font-semibold text-right">Pendapatan</th>
                                        <th class="px-6 py-3 font-semibold text-right">Biaya Bahan</th>
                                        <th class="px-6 py-3 font-semibold text-right">Laba Kotor</th>
                                        <th class="px-6 py-3 font-semibold text-right">Margin</th>
                                        <th class="px-6 py-3 font-semibold text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="modalDetailLaba" class="divide-y divide-gray-100">
                                    <?php if (!empty($laba_kotor['data_per_dapur'])): ?>
                                        <?php foreach ($laba_kotor['data_per_dapur'] as $dapur => $data): ?>
                                        <tr class="bg-white hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap"><?php echo htmlspecialchars($dapur); ?></td>
                                            <td class="px-6 py-4 text-right">Rp <?php echo number_format($data['pendapatan'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 text-right">Rp <?php echo number_format($data['biaya_bahan'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 text-right font-semibold 
                                                <?php echo $data['laba_kotor'] > 0 ? 'profit-positive' : 
                                                       ($data['laba_kotor'] < 0 ? 'profit-negative' : 'profit-neutral'); ?>">
                                                Rp <?php echo number_format($data['laba_kotor'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="px-6 py-4 text-right font-semibold 
                                                <?php echo $data['margin'] > 0 ? 'profit-positive' : 
                                                       ($data['margin'] < 0 ? 'profit-negative' : 'profit-neutral'); ?>">
                                                <?php echo $data['margin']; ?>%
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?php echo $data['margin'] > 20 ? 'bg-green-100 text-green-800' : 
                                                           ($data['margin'] > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo $data['margin'] > 20 ? 'Baik' : 
                                                           ($data['margin'] > 0 ? 'Sedang' : 'Perhatian'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr class="bg-white">
                                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                                <div class="data-empty-state">
                                                    <i class="fas fa-chart-pie text-3xl mb-3"></i>
                                                    <p class="font-medium">Tidak ada data laba kotor</p>
                                                    <p class="text-sm">untuk periode ini</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Grafik Laba Kotor -->
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Visualisasi Laba Kotor</h4>
                        <div class="chart-container" style="height: 350px;">
                            <canvas id="labaKotorChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Footer Modal -->
                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-3 pt-6 border-t border-gray-200">
                        <button onclick="printLabaKotor()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition flex items-center justify-center">
                            <i class="fas fa-print mr-2"></i> Cetak
                        </button>
                        <button onclick="hideLabaKotor()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>

            <!-- Aktivitas & Pesanan Terbaru -->
            <div class="grid grid-cols-1 gap-6 mb-8">
                <div class="card-hover bg-white rounded-xl p-6">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between mb-6 pb-4 border-b border-gray-100 gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Aktivitas & Pesanan Terbaru</h3>
                            <p class="text-sm text-gray-500">24 jam terakhir</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500 hidden md:inline" id="lastAktivitasUpdate">
                                Update: <?php echo date('H:i:s'); ?>
                            </span>
                            <button onclick="refreshAktivitasGabungan()" class="text-gray-500 hover:text-blue-600 transition hover:bg-gray-100 rounded-full w-8 h-8 flex items-center justify-center">
                                <i class="fas fa-sync-alt text-xs"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="custom-scrollbar" id="aktivitasGabunganList">
                        <?php 
                        if (empty($aktivitas_gabungan)) {
                            echo '
                            <div class="data-empty-state">
                                <i class="fas fa-store"></i>
                                <p class="font-medium text-gray-700">Belum ada aktivitas atau pesanan</p>
                                <p class="text-sm text-gray-500">24 jam terakhir</p>
                            </div>';
                        } else {
                            // Kelompokkan data berdasarkan dapur
                            $data_per_dapur = [];
                            
                            foreach ($aktivitas_gabungan as $item) {
                                $dapur = '';
                                if ($item['type'] === 'aktivitas') {
                                    $dapur = $item['data']['dapur'];
                                } else {
                                    $dapur = $item['data']['dapur'];
                                }
                                
                                if (!isset($data_per_dapur[$dapur])) {
                                    $data_per_dapur[$dapur] = [];
                                }
                                $data_per_dapur[$dapur][] = $item;
                            }
                            
                            // Tampilkan data per dapur
                            foreach ($data_per_dapur as $nama_dapur => $items_dapur) {
                                echo '
                                <div class="mb-6 border border-gray-200 rounded-xl overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-5 py-3 border-b border-blue-200">
                                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm">
                                                    <i class="fas fa-store text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <h4 class="font-semibold text-gray-900">' . htmlspecialchars($nama_dapur) . '</h4>
                                                    <p class="text-xs text-gray-600">' . count($items_dapur) . ' aktivitas</p>
                                                </div>
                                            </div>
                                            <span class="text-xs bg-white text-blue-700 px-3 py-1 rounded-full font-medium shadow-sm">
                                                <i class="fas fa-clock mr-1"></i>Aktif
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="divide-y divide-gray-100">';
                                
                                foreach ($items_dapur as $item) {
                                    if ($item['type'] === 'aktivitas') {
                                        $data = $item['data'];
                                        echo '
                                        <div class="flex flex-col md:flex-row items-start md:items-center space-y-3 md:space-y-0 md:space-x-4 p-5 hover:bg-gray-50 transition">
                                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-shopping-cart text-green-600"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-2 mb-2">
                                                    <span class="font-medium text-gray-900">Pembelian Bahan</span>
                                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full font-medium self-start">Stok</span>
                                                </div>
                                                <div class="text-sm text-gray-600 space-y-1">
                                                    <div class="flex items-center space-x-2">
                                                        <i class="fas fa-box text-gray-400"></i>
                                                        <span><span class="font-medium">' . htmlspecialchars($data['barang']) . '</span> • ' . $data['jumlah'] . ' ' . htmlspecialchars($data['satuan']) . '</span>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <i class="fas fa-database text-gray-400"></i>
                                                        <span>Stok tersisa: ' . $data['stok_sisa'] . ' ' . htmlspecialchars($data['satuan']) . '</span>
                                                    </div>
                                                </div>
                                                <p class="text-xs text-blue-600 font-medium mt-3 flex items-center">
                                                    <i class="fas fa-clock mr-1"></i>' . htmlspecialchars($data['waktu']) . '
                                                </p>
                                            </div>
                                            <div class="text-right flex-shrink-0 self-end md:self-auto">
                                                <p class="text-base font-bold text-green-600">
                                                    Rp ' . number_format($data['total_harga'], 0, ',', '.') . '
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">Total</p>
                                            </div>
                                        </div>';
                                    } else {
                                        $data = $item['data'];
                                        $barang_list = $data['barang_dipesan'] ? explode(', ', $data['barang_dipesan']) : [];
                                        $barang_display = array_slice($barang_list, 0, 2);
                                        
                                        echo '
                                        <a href="../laporanPenjualan.php' . htmlspecialchars($data['link']) . '" class="block p-5 hover:bg-gray-50 transition border-l-4 border-blue-300 no-underline text-gray-900 hover:text-gray-900">
                                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-3 mb-3">
                                                        <span class="font-semibold text-gray-900">' . htmlspecialchars($data['customer']) . '</span>
                                                        <span class="px-3 py-1 text-xs font-medium rounded-full ' . htmlspecialchars($data['status_color']) . ' self-start">
                                                            ' . ucfirst($data['status']) . '
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <p class="text-sm text-gray-500 mb-2 flex items-center">
                                                            <i class="fas fa-list mr-2"></i> Produk dipesan:
                                                        </p>
                                                        <div class="flex flex-wrap gap-2">';
                                                        
                                        foreach ($barang_display as $barang) {
                                            echo '
                                                            <span class="inline-block bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded-lg">
                                                                ' . htmlspecialchars(trim($barang)) . '
                                                            </span>';
                                        }
                                        
                                        if (count($barang_list) > 2) {
                                            echo '
                                                            <span class="inline-block bg-blue-100 text-blue-700 text-sm px-3 py-1.5 rounded-lg">
                                                                +' . (count($barang_list) - 2) . ' lainnya
                                                            </span>';
                                        }
                                        
                                        echo '
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-4 text-sm text-gray-500">
                                                        <span class="flex items-center">
                                                            <i class="fas fa-cube mr-2"></i>
                                                            ' . $data['items'] . ' item
                                                        </span>
                                                        <span class="flex items-center">
                                                            <i class="fas fa-clock mr-2"></i>
                                                            ' . htmlspecialchars($data['time']) . '
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="text-right ml-0 md:ml-5 flex-shrink-0 self-end md:self-auto">
                                                    <p class="text-base font-bold text-green-600">
                                                        Rp ' . number_format($data['total'], 0, ',', '.') . '
                                                    </p>
                                                    <p class="text-xs text-gray-500 mt-1">Total</p>
                                                    <span class="inline-block mt-2 text-xs text-blue-600 font-medium">
                                                    </span>
                                                </div>
                                            </div>
                                        </a>';
                                    }
                                }
                                
                                echo '
                                    </div>
                                </div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Aksi Cepat Admin -->
            <div class="card-hover bg-white rounded-xl p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Aksi Cepat Admin</h3>
                <div class="aksi-cepat-grid">
                    <a href="../index.php" class="aksi-cepat-item group">
                        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-3 group-hover:bg-blue-200 transition-colors">
                            <i class="fas fa-boxes-stacked text-blue-600 text-lg"></i>
                        </div>
                        <p class="font-semibold text-gray-900">Inventaris</p>
                        <p class="text-sm text-gray-500 mt-1">Kelola stok barang</p>
                    </a>
                    <a href="../laporanPenjualan.php" class="aksi-cepat-item group">
                        <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-3 group-hover:bg-purple-200 transition-colors">
                            <i class="fas fa-chart-bar text-purple-600 text-lg"></i>
                        </div>
                        <p class="font-semibold text-gray-900">Laporan Penjualan</p>
                        <p class="text-sm text-gray-500 mt-1">Analisis data</p>
                    </a>
                    <a href="../inputdatasuplayer.php" class="aksi-cepat-item group">
                        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mb-3 group-hover:bg-red-200 transition-colors">
                            <i class="fas fa-users text-red-600 text-lg"></i>
                        </div>
                        <p class="font-semibold text-gray-900">Supplier</p>
                        <p class="text-sm text-gray-500 mt-1">Kelola data</p>
                    </a>
                    <a href="../pengaturanAkun.php" class="aksi-cepat-item group">
                        <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center mb-3 group-hover:bg-indigo-200 transition-colors">
                            <i class="fas fa-cog text-indigo-600 text-lg"></i>
                        </div>
                        <p class="font-semibold text-gray-900">Pengaturan</p>
                        <p class="text-sm text-gray-500 mt-1">Konfigurasi sistem</p>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Variabel global
let aktivitasChart = null;
let labaKotorChart = null;
let currentChartType = 'pesanan';
let allDapurVisible = true;
const chartColors = [
    '#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899',
    '#06B6D4', '#84CC16', '#F97316', '#6366F1', '#EF4444'
];

// Fungsi untuk mengubah periode income
async function changeIncomePeriod(period) {
    try {
        const incomeAmount = document.getElementById('totalIncomeAmount');
        const oldText = incomeAmount.textContent;
        incomeAmount.innerHTML = '<i class="fas fa-spinner fa-spin text-gray-400 mr-2"></i>Loading...';
        
        const response = await fetch(`?action=get_total_income&period=${period}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.total_income !== undefined) {
            incomeAmount.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.total_income);
            
            document.getElementById('averageDaily').textContent = 
                'Rp ' + new Intl.NumberFormat('id-ID').format(data.stats.average_daily || 0);
            
            const growthElement = document.getElementById('growthRate');
            if (growthElement) {
                const growthRate = data.stats.growth_rate || 0;
                const trendClass = growthRate >= 0 ? 'trend-up' : 'trend-down';
                const icon = growthRate >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                
                growthElement.innerHTML = `
                    <span class="${trendClass}">
                        <i class="fas ${icon} mr-1"></i>
                        ${Math.abs(growthRate).toFixed(1)}%
                    </span>
                `;
            }
            
            let label = '';
            switch(period) {
                case 'today': label = 'Hari ini'; break;
                case 'week': label = '7 Hari Terakhir'; break;
                case 'month': label = 'Bulan ini'; break;
                case 'year': label = 'Tahun ini'; break;
                case 'all': label = 'Semua Waktu'; break;
            }
            document.getElementById('incomePeriodText').textContent = label;
            
            updateTimestamp('lastUpdateTime');
            
            // Animasi perubahan
            incomeAmount.classList.add('scale-110');
            setTimeout(() => {
                incomeAmount.classList.remove('scale-110');
            }, 300);
        } else {
            incomeAmount.textContent = oldText;
            console.error('Invalid response:', data);
            showError('Data tidak valid diterima dari server');
        }
        
    } catch (error) {
        console.error('Error changing income period:', error);
        document.getElementById('totalIncomeAmount').textContent = oldText || 'Error';
        showError('Gagal memuat data pendapatan');
    }
}

// Fungsi untuk mengubah periode laba kotor
async function changeLabaPeriod(period) {
    try {
        let label = '';
        switch(period) {
            case 'today': label = 'Hari ini'; break;
            case 'week': label = '7 Hari Terakhir'; break;
            case 'month': label = 'Bulan ini'; break;
            case 'year': label = 'Tahun ini'; break;
            case 'all': label = 'Semua Waktu'; break;
        }
        document.getElementById('labaPeriodText').textContent = label;

        await refreshLabaKotor();
        
    } catch (error) {
        console.error('Error changing laba period:', error);
        showError('Gagal memuat data laba kotor');
    }
}

// Fungsi untuk mengubah periode laba kotor di modal
async function changeModalLabaPeriod(period) {
    try {
        await showLabaKotor(period);
    } catch (error) {
        console.error('Error changing modal laba period:', error);
    }
}

// Fungsi untuk menampilkan refresh indicator
function showRefreshIndicator() {
    const indicator = document.getElementById('refreshIndicator');
    indicator.classList.remove('hidden');
    setTimeout(() => {
        indicator.classList.add('hidden');
    }, 2000);
}

// Fungsi untuk update waktu Indonesia real-time
function updateWaktuIndonesia() {
    try {
        const now = new Date();
        
        const options = {
            timeZone: 'Asia/Jakarta',
            hour12: false,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            weekday: 'long'
        };
        
        const formatter = new Intl.DateTimeFormat('id-ID', options);
        const parts = formatter.formatToParts(now);
        
        let waktu = {}, tanggal = {};
        parts.forEach(part => {
            if (part.type === 'hour') waktu.jam = part.value;
            if (part.type === 'minute') waktu.menit = part.value;
            if (part.type === 'second') waktu.detik = part.value;
            if (part.type === 'weekday') tanggal.hari = part.value;
            if (part.type === 'day') tanggal.tanggal = part.value;
            if (part.type === 'month') tanggal.bulan = part.value;
            if (part.type === 'year') tanggal.tahun = part.value;
        });
        
        const waktuElement = document.getElementById('waktuIndonesia');
        if (waktuElement) {
            waktuElement.innerHTML = `
                <span class="font-bold text-blue-600">${waktu.jam}:${waktu.menit}:${waktu.detik}</span>
                <span class="text-gray-400 mx-2">•</span>
                <span class="text-gray-600">${tanggal.hari}, ${tanggal.tanggal} ${tanggal.bulan} ${tanggal.tahun}</span>
            `;
        }
    } catch (error) {
        console.error('Error updating time:', error);
    }
}

// Fungsi untuk update timestamp
function updateTimestamp(elementId) {
    try {
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = `<i class="fas fa-clock mr-1"></i>Update: ${timeString}`;
        }
    } catch (error) {
        console.error('Error updating timestamp:', error);
    }
}

// Fungsi untuk menampilkan modal laba kotor
async function showLabaKotor(period = null) {
    try {
        const periodToUse = period || document.getElementById('labaPeriod').value;
        
        document.getElementById('modalLabaPeriodText').textContent = 
            periodToUse === 'today' ? 'Hari ini' :
            periodToUse === 'week' ? '7 Hari Terakhir' :
            periodToUse === 'month' ? 'Bulan ini' :
            periodToUse === 'year' ? 'Tahun ini' : 'Semua Waktu';
        
        const response = await fetch(`?ajax=true&type=laba_kotor&period=${periodToUse}&t=` + Date.now());
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        document.getElementById('modalTotalPendapatan').textContent = 
            'Rp ' + new Intl.NumberFormat('id-ID').format(data.total_pendapatan);
        document.getElementById('modalTotalBiaya').textContent = 
            'Rp ' + new Intl.NumberFormat('id-ID').format(data.total_biaya_bahan);
        document.getElementById('modalLabaKotor').textContent = 
            'Rp ' + new Intl.NumberFormat('id-ID').format(data.laba_kotor);
        document.getElementById('modalMarginLaba').textContent = 
            'Margin: ' + data.margin_laba + '%';
        
        const labaElement = document.getElementById('modalLabaKotor');
        const marginElement = document.getElementById('modalMarginLaba');
        
        if (data.laba_kotor > 0) {
            labaElement.className = 'text-2xl font-bold text-green-700';
            marginElement.className = 'text-sm font-medium text-green-600';
        } else if (data.laba_kotor < 0) {
            labaElement.className = 'text-2xl font-bold text-red-700';
            marginElement.className = 'text-sm font-medium text-red-600';
        } else {
            labaElement.className = 'text-2xl font-bold text-gray-700';
            marginElement.className = 'text-sm font-medium text-gray-600';
        }
        
        let detailHtml = '';
        if (data.data_per_dapur && Object.keys(data.data_per_dapur).length > 0) {
            Object.entries(data.data_per_dapur).forEach(([dapur, dapurData]) => {
                const labaClass = dapurData.laba_kotor > 0 ? 'profit-positive' : 
                                (dapurData.laba_kotor < 0 ? 'profit-negative' : 'profit-neutral');
                const marginClass = dapurData.margin > 0 ? 'profit-positive' : 
                                 (dapurData.margin < 0 ? 'profit-negative' : 'profit-neutral');
                
                let statusColor = 'bg-red-100 text-red-800';
                let statusText = 'Perhatian';
                
                if (dapurData.margin > 20) {
                    statusColor = 'bg-green-100 text-green-800';
                    statusText = 'Baik';
                } else if (dapurData.margin > 0) {
                    statusColor = 'bg-yellow-100 text-yellow-800';
                    statusText = 'Sedang';
                }
                
                detailHtml += `
                    <tr class="bg-white hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">${escapeHtml(dapur)}</td>
                        <td class="px-6 py-4 text-right">Rp ${new Intl.NumberFormat('id-ID').format(dapurData.pendapatan)}</td>
                        <td class="px-6 py-4 text-right">Rp ${new Intl.NumberFormat('id-ID').format(dapurData.biaya_bahan)}</td>
                        <td class="px-6 py-4 text-right font-semibold ${labaClass}">Rp ${new Intl.NumberFormat('id-ID').format(dapurData.laba_kotor)}</td>
                        <td class="px-6 py-4 text-right font-semibold ${marginClass}">${dapurData.margin}%</td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColor}">
                                ${statusText}
                            </span>
                        </td>
                    </tr>
                `;
            });
        } else {
            detailHtml = `
                <tr class="bg-white">
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                        <div class="data-empty-state">
                            <i class="fas fa-chart-pie text-3xl mb-3"></i>
                            <p class="font-medium">Tidak ada data laba kotor</p>
                            <p class="text-sm">untuk periode ini</p>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        document.getElementById('modalDetailLaba').innerHTML = detailHtml;
        
        createLabaKotorChart(data);
        
        document.getElementById('labaKotorModal').classList.remove('hidden');
        
    } catch (error) {
        console.error('Error loading laba kotor data:', error);
        showError('Gagal memuat data laba kotor');
    }
}

// Fungsi untuk menyembunyikan modal laba kotor
function hideLabaKotor() {
    document.getElementById('labaKotorModal').classList.add('hidden');
}

// Fungsi untuk membuat grafik laba kotor
function createLabaKotorChart(data) {
    const ctx = document.getElementById('labaKotorChart');
    
    if (labaKotorChart) {
        labaKotorChart.destroy();
    }
    
    if (!ctx) {
        console.error('Canvas untuk laba kotor tidak ditemukan');
        return;
    }
    
    let dapurs = [];
    if (data.data_per_dapur && Object.keys(data.data_per_dapur).length > 0) {
        dapurs = Object.keys(data.data_per_dapur);
    }
    
    if (dapurs.length === 0) {
        const chartCtx = ctx.getContext('2d');
        labaKotorChart = new Chart(chartCtx, {
            type: 'bar',
            data: {
                labels: ['Tidak ada data'],
                datasets: [{
                    label: 'Tidak ada data',
                    data: [0],
                    backgroundColor: '#e5e7eb',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
        return;
    }
    
    const pendapatanData = dapurs.map(dapur => data.data_per_dapur[dapur].pendapatan);
    const labaData = dapurs.map(dapur => data.data_per_dapur[dapur].laba_kotor);
    
    const chartCtx = ctx.getContext('2d');
    labaKotorChart = new Chart(chartCtx, {
        type: 'bar',
        data: {
            labels: dapurs,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: pendapatanData,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: '#3B82F6',
                    borderWidth: 2,
                    borderRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Laba Kotor',
                    data: labaData,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: '#10B981',
                    borderWidth: 2,
                    borderRadius: 6,
                    yAxisID: 'y'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                    },
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah (Rp)',
                        font: {
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) {
                                return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                            } else if (value >= 1000) {
                                return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                            }
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
}

// Fungsi refresh laba kotor
async function refreshLabaKotor() {
    try {
        const period = document.getElementById('labaPeriod').value;
        
        const response = await fetch(`?ajax=true&type=laba_kotor&period=${period}&t=` + Date.now());
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        updateLabaKotorDisplay(data);
        
    } catch (error) {
        console.error('Error refreshing laba kotor:', error);
        showError('Gagal memuat data laba kotor');
    }
}

// Fungsi untuk update tampilan laba kotor
function updateLabaKotorDisplay(data) {
    try {
        const labaElement = document.getElementById('totalLabaKotor');
        const marginElement = document.getElementById('labaMargin');
        const pendapatanElement = document.getElementById('labaPendapatan');
        const biayaElement = document.getElementById('labaBiaya');
        
        labaElement.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.laba_kotor);
        marginElement.textContent = data.margin_laba + '%';
        pendapatanElement.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.total_pendapatan);
        biayaElement.textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.total_biaya_bahan);
        
        const labaColor = data.laba_kotor > 0 ? 'text-green-600' : 
                         (data.laba_kotor < 0 ? 'text-red-600' : 'text-gray-600');
        const marginColor = data.margin_laba > 0 ? 'text-green-500' : 
                           (data.margin_laba < 0 ? 'text-red-500' : 'text-gray-500');
        
        labaElement.className = `text-2xl md:text-3xl font-bold ${labaColor} mb-2`;
        const marginIcon = marginElement.parentElement.querySelector('i');
        if (marginIcon) {
            marginIcon.className = `fas fa-chart-line mr-1 ${marginColor}`;
        }
    } catch (error) {
        console.error('Error updating laba kotor display:', error);
    }
}

// Fungsi switch chart type
function switchChart(type) {
    currentChartType = type;
    
    // Update tab aktif
    document.getElementById('tabPesanan').classList.remove('tab-active');
    document.getElementById('tabPendapatan').classList.remove('tab-active');
    document.getElementById('tabPerbandingan').classList.remove('tab-active');
    
    document.getElementById('tabPesanan').classList.add('text-gray-600', 'hover:text-gray-900');
    document.getElementById('tabPendapatan').classList.add('text-gray-600', 'hover:text-gray-900');
    document.getElementById('tabPerbandingan').classList.add('text-gray-600', 'hover:text-gray-900');
    
    const activeTab = document.getElementById('tab' + type.charAt(0).toUpperCase() + type.slice(1));
    activeTab.classList.remove('text-gray-600', 'hover:text-gray-900');
    activeTab.classList.add('tab-active');
    
    refreshGrafik();
}

// Fungsi refresh semua data
async function refreshData() {
    const refreshBtn = document.querySelector('button[onclick="refreshData()"]');
    const spinner = document.getElementById('refreshSpinner');
    const text = document.getElementById('refreshText');
    
    // Tampilkan loading state
    refreshBtn.classList.add('opacity-75', 'cursor-not-allowed');
    spinner.classList.remove('hidden');
    text.textContent = 'Memperbarui...';
    
    showRefreshIndicator();
    
    try {
        await Promise.all([
            refreshStatistik(),
            refreshTotalIncome(),
            refreshLabaKotor(),
            refreshAktivitasGabungan(),
            refreshGrafik()
        ]);
        
        // Animasi sukses
        refreshBtn.classList.add('bg-green-600');
        setTimeout(() => {
            refreshBtn.classList.remove('bg-green-600');
        }, 1000);
        
    } catch (error) {
        console.error('Error refreshing data:', error);
        showError('Gagal memperbarui data');
    } finally {
        // Reset button state
        setTimeout(() => {
            refreshBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            spinner.classList.add('hidden');
            text.textContent = 'Refresh Data';
        }, 500);
    }
}

// Fungsi refresh grafik
async function refreshGrafik() {
    try {
        const chartLoading = document.getElementById('chartLoading');
        const chartCanvas = document.getElementById('aktivitasChart');
        
        // Tampilkan loading
        chartLoading.classList.remove('hidden');
        chartCanvas.classList.add('hidden');
        
        const response = await fetch('?ajax=true&type=grafik_aktivitas&t=' + Date.now());
        
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        if (data.metadata && data.metadata.periode) {
            document.getElementById('grafikPeriodeText').textContent = data.metadata.periode;
        }
        
        // Update ringkasan statistik
        if (data.metadata) {
            document.getElementById('totalPesananBulanIni').textContent = 
                new Intl.NumberFormat('id-ID').format(data.metadata.total_pesanan_bulan_ini);
            document.getElementById('totalPendapatanBulanIni').textContent = 
                'Rp ' + new Intl.NumberFormat('id-ID').format(data.metadata.total_pendapatan_bulan_ini);
            document.getElementById('rataPesananPerHari').textContent = 
                data.metadata.rata_pesanan_per_hari.toFixed(1) + ' pesanan';
        }
        
        updateTimestamp('grafikUpdateTime');
        
        if (aktivitasChart) {
            aktivitasChart.destroy();
        }
        
        const canvas = document.getElementById('aktivitasChart');
        
        if (!canvas) {
            console.error('Canvas element tidak ditemukan!');
            return;
        }
        
        const ctx = canvas.getContext('2d');
        
        let chartConfig;
        if (currentChartType === 'pesanan') {
            chartConfig = createPesananChartData(data);
        } else if (currentChartType === 'pendapatan') {
            chartConfig = createPendapatanChartData(data);
        } else {
            chartConfig = createPerbandinganChartData(data);
        }
        
        // Sembunyikan loading dan tampilkan chart
        chartLoading.classList.add('hidden');
        chartCanvas.classList.remove('hidden');
        
        aktivitasChart = new Chart(ctx, {
            type: chartConfig.type,
            data: chartConfig.data,
            options: chartConfig.options,
            plugins: chartConfig.plugins || []
        });
        
        updateChartLegend(data);
        
    } catch (error) {
        console.error('Error refreshing grafik:', error);
        
        // Tampilkan error state
        const chartLoading = document.getElementById('chartLoading');
        chartLoading.innerHTML = `
            <i class="fas fa-exclamation-triangle text-red-500"></i>
            <p class="mt-2">Gagal memuat grafik</p>
            <p class="text-sm text-gray-500 mt-1">Coba refresh halaman</p>
        `;
    }
}

// Fungsi untuk membuat chart default (fallback)
function createDefaultChart() {
    const canvas = document.getElementById('aktivitasChart');
    
    if (!canvas) {
        console.error('Canvas element tidak ditemukan untuk default chart');
        return;
    }
    
    if (aktivitasChart) {
        aktivitasChart.destroy();
    }
    
    const ctx = canvas.getContext('2d');
    const today = new Date();
    const labels = [];
    
    // Buat label untuk 7 hari terakhir
    for (let i = 6; i >= 0; i--) {
        const date = new Date();
        date.setDate(today.getDate() - i);
        labels.push(date.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric' }));
    }
    
    aktivitasChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Data Sample',
                data: [12, 19, 3, 5, 2, 3, 7],
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah'
                    }
                },
                x: {
                    grid: { display: true }
                }
            }
        }
    });
    
    document.getElementById('chartLegend').innerHTML = `
        <div class="text-xs text-red-500">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Menampilkan data sample karena data asli tidak tersedia
        </div>
    `;
}

// Fungsi untuk membuat data chart pesanan
function createPesananChartData(data) {
    const datasets = [];
    const dapurAktif = data.dapur_aktif || [];
    const labels = data.labels || [];
    
    dapurAktif.forEach((dapur, index) => {
        const dataPesanan = labels.map(label => {
            const value = (data.data_per_dapur && data.data_per_dapur[dapur] && data.data_per_dapur[dapur][label]) ? 
                data.data_per_dapur[dapur][label].pesanan : 0;
            return value;
        });
        
        datasets.push({
            label: dapur,
            data: dataPesanan,
            borderColor: chartColors[index % chartColors.length],
            backgroundColor: chartColors[index % chartColors.length] + '20',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: chartColors[index % chartColors.length],
            borderDash: [],
            hidden: !allDapurVisible && index > 2
        });
    });
    
    if (datasets.length === 0) {
        datasets.push({
            label: 'Belum ada data',
            data: Array(labels.length).fill(0),
            borderColor: '#e5e7eb',
            backgroundColor: '#f9fafb',
            borderWidth: 1,
            fill: true,
            borderDash: [5, 5],
            pointRadius: 0,
            tension: 0
        });
    }
    
    return {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: { 
                    display: false 
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y + ' pesanan';
                            }
                            return label;
                        },
                        title: function(tooltipItems) {
                            return tooltipItems[0].label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Pesanan',
                        font: {
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        precision: 0,
                        callback: function(value) {
                            return value.toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: { 
                        display: false 
                    },
                    ticks: {
                        maxTicksLimit: 15,
                        callback: function(value, index) {
                            const labels = this.getLabels();
                            if (labels.length > 20) {
                                if (index % 3 === 0 || index === labels.length - 1) {
                                    return labels[index];
                                }
                                return '';
                            }
                            return labels[index];
                        },
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    };
}

// Fungsi untuk membuat data chart pendapatan
function createPendapatanChartData(data) {
    const datasets = [];
    const dapurAktif = data.dapur_aktif || [];
    const labels = data.labels || [];
    
    dapurAktif.forEach((dapur, index) => {
        const dataPendapatan = labels.map(label => {
            return (data.data_per_dapur && data.data_per_dapur[dapur] && data.data_per_dapur[dapur][label]) ? 
                data.data_per_dapur[dapur][label].pendapatan : 0;
        });
        
        datasets.push({
            label: dapur,
            data: dataPendapatan,
            borderColor: chartColors[index % chartColors.length],
            backgroundColor: chartColors[index % chartColors.length] + '20',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: chartColors[index % chartColors.length],
            borderDash: [],
            hidden: !allDapurVisible && index > 2
        });
    });
    
    if (datasets.length === 0) {
        datasets.push({
            label: 'Belum ada data',
            data: Array(labels.length).fill(0),
            borderColor: '#e5e7eb',
            backgroundColor: '#f9fafb',
            borderWidth: 1,
            fill: true,
            borderDash: [5, 5],
            pointRadius: 0,
            tension: 0
        });
    }
    
    return {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: { 
                    display: false 
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                            return label;
                        },
                        title: function(tooltipItems) {
                            return tooltipItems[0].label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Pendapatan (Rp)',
                        font: {
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) {
                                return 'Rp ' + (value / 1000000).toFixed(1) + 'jt';
                            } else if (value >= 1000) {
                                return 'Rp ' + (value / 1000).toFixed(0) + 'rb';
                            }
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        maxTicksLimit: 15,
                        callback: function(value, index) {
                            const labels = this.getLabels();
                            if (labels.length > 20) {
                                if (index % 3 === 0 || index === labels.length - 1) {
                                    return labels[index];
                                }
                                return '';
                            }
                            return labels[index];
                        },
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    };
}

// Fungsi untuk membuat data chart perbandingan
function createPerbandinganChartData(data) {
    const dapurAktif = data.dapur_aktif || [];
    const labels = ['Hari Ini', 'Kemarin', 'Bulan Lalu'];
    const datasets = [];
    
    dapurAktif.forEach((dapur, index) => {
        const aktivitasHariIni = data.aktivitas_hari_ini && data.aktivitas_hari_ini[dapur] ? data.aktivitas_hari_ini[dapur] : { pesanan: 0, pendapatan: 0 };
        const aktivitasKemarin = data.aktivitas_kemarin && data.aktivitas_kemarin[dapur] ? data.aktivitas_kemarin[dapur] : { pesanan: 0, pendapatan: 0 };
        const aktivitasBulanLalu = data.aktivitas_bulan_lalu && data.aktivitas_bulan_lalu[dapur] ? data.aktivitas_bulan_lalu[dapur] : { pesanan: 0, pendapatan: 0 };
        
        datasets.push({
            label: dapur,
            data: [
                aktivitasHariIni.pesanan,
                aktivitasKemarin.pesanan,
                aktivitasBulanLalu.pesanan
            ],
            backgroundColor: chartColors[index % chartColors.length] + '80',
            borderColor: chartColors[index % chartColors.length],
            borderWidth: 2,
            borderRadius: 6,
            hidden: !allDapurVisible && index > 2
        });
    });
    
    if (datasets.length === 0) {
        datasets.push({
            label: 'Belum ada data',
            data: [0, 0, 0],
            backgroundColor: '#e5e7eb',
            borderColor: '#d1d5db',
            borderWidth: 1,
            borderRadius: 6
        });
    }
    
    return {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    display: false 
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1f2937',
                    bodyColor: '#4b5563',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    padding: 12,
                    cornerRadius: 8,
                    boxPadding: 6,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' pesanan';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Pesanan',
                        font: {
                            weight: 'bold'
                        }
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('id-ID');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    };
}

// Fungsi untuk update chart legend
function updateChartLegend(data) {
    const legendContainer = document.getElementById('chartLegend');
    const dapurAktif = data.dapur_aktif || [];
    
    let html = '';
    dapurAktif.forEach((dapur, index) => {
        // Hitung total pesanan bulan ini
        let totalPesananBulanIni = 0;
        if (data.data_per_dapur && data.data_per_dapur[dapur]) {
            Object.values(data.data_per_dapur[dapur]).forEach(dayData => {
                totalPesananBulanIni += dayData.pesanan || 0;
            });
        }
        
        // Hitung total pendapatan bulan ini
        let totalPendapatanBulanIni = 0;
        if (data.data_per_dapur && data.data_per_dapur[dapur]) {
            Object.values(data.data_per_dapur[dapur]).forEach(dayData => {
                totalPendapatanBulanIni += dayData.pendapatan || 0;
            });
        }
        
        const aktivitasHariIni = (data.aktivitas_hari_ini && data.aktivitas_hari_ini[dapur]) ? data.aktivitas_hari_ini[dapur] : { pesanan: 0, pendapatan: 0 };
        const isHidden = !allDapurVisible && index > 2;
        
        html += `
            <div class="legend-item hover:shadow-sm transition-all ${isHidden ? 'opacity-50' : ''}" 
                 onclick="toggleDapur(${index})" 
                 data-dapur-index="${index}"
                 title="${escapeHtml(dapur)}
Total Pesanan: ${totalPesananBulanIni}
Total Pendapatan: Rp ${totalPendapatanBulanIni.toLocaleString('id-ID')}
Hari Ini: ${aktivitasHariIni.pesanan} pesanan">
                <div class="legend-color" style="background-color: ${chartColors[index % chartColors.length]}; ${isHidden ? 'opacity: 0.5;' : ''}"></div>
                <span class="text-xs font-medium truncate max-w-[120px]">${escapeHtml(dapur)}</span>
                <span class="text-xs font-bold text-gray-700 ml-1">${totalPesananBulanIni}</span>
            </div>
        `;
    });
    
    if (html === '' && dapurAktif.length === 0) {
        html = '<div class="text-xs text-gray-500 italic">Tidak ada data dapur untuk ditampilkan</div>';
    }
    
    legendContainer.innerHTML = html;
}

// Fungsi untuk toggle visibility dapur tertentu
function toggleDapur(index) {
    if (aktivitasChart) {
        const meta = aktivitasChart.getDatasetMeta(index);
        const legendItem = document.querySelector(`[data-dapur-index="${index}"]`);
        
        if (meta) {
            meta.hidden = meta.hidden === null ? true : !meta.hidden;
            aktivitasChart.update();
            
            if (legendItem) {
                if (meta.hidden) {
                    legendItem.classList.add('opacity-50');
                    const legendColor = legendItem.querySelector('.legend-color');
                    if (legendColor) legendColor.style.opacity = '0.5';
                } else {
                    legendItem.classList.remove('opacity-50');
                    const legendColor = legendItem.querySelector('.legend-color');
                    if (legendColor) legendColor.style.opacity = '1';
                }
            }
        }
    }
}

// Fungsi untuk toggle semua dapur
function toggleAllDapur() {
    if (aktivitasChart) {
        const datasets = aktivitasChart.data.datasets;
        const toggleBtn = document.getElementById('toggleAllBtn');
        const legendItems = document.querySelectorAll('[data-dapur-index]');
        
        allDapurVisible = !allDapurVisible;
        
        datasets.forEach((dataset, index) => {
            const meta = aktivitasChart.getDatasetMeta(index);
            if (meta) {
                meta.hidden = !allDapurVisible && index > 2;
            }
        });
        
        aktivitasChart.update();
        
        // Update tombol
        if (allDapurVisible) {
            toggleBtn.innerHTML = '<i class="fas fa-eye-slash mr-1"></i> Sembunyikan Beberapa';
            toggleBtn.classList.remove('bg-gray-100');
            toggleBtn.classList.add('bg-blue-100', 'text-blue-700');
        } else {
            toggleBtn.innerHTML = '<i class="fas fa-eye mr-1"></i> Tampilkan Semua';
            toggleBtn.classList.remove('bg-blue-100', 'text-blue-700');
            toggleBtn.classList.add('bg-gray-100');
        }
        
        // Update legend
        legendItems.forEach((item, index) => {
            if (!allDapurVisible && index > 2) {
                item.classList.add('opacity-50');
                const legendColor = item.querySelector('.legend-color');
                if (legendColor) legendColor.style.opacity = '0.5';
            } else {
                item.classList.remove('opacity-50');
                const legendColor = item.querySelector('.legend-color');
                if (legendColor) legendColor.style.opacity = '1';
            }
        });
    }
}

// Fungsi untuk highlight dapur tertentu
function highlightDapur(index) {
    if (aktivitasChart) {
        const datasets = aktivitasChart.data.datasets;
        const legendItems = document.querySelectorAll('[data-dapur-index]');
        
        datasets.forEach((dataset, i) => {
            const meta = aktivitasChart.getDatasetMeta(i);
            if (meta) {
                meta.hidden = i !== index;
                
                // Update border width untuk highlight
                dataset.borderWidth = i === index ? 4 : 2;
                
                if (aktivitasChart.options.elements && aktivitasChart.options.elements.line) {
                    aktivitasChart.options.elements.line.tension = i === index ? 0.2 : 0.4;
                }
            }
        });
        
        aktivitasChart.update();
        
        // Update legend
        legendItems.forEach((item, i) => {
            if (i === index) {
                item.classList.add('bg-blue-50', 'border', 'border-blue-200');
                item.classList.remove('opacity-50');
                const legendColor = item.querySelector('.legend-color');
                if (legendColor) {
                    legendColor.style.opacity = '1';
                    legendColor.style.transform = 'scale(1.2)';
                }
            } else {
                item.classList.remove('bg-blue-50', 'border', 'border-blue-200');
                item.classList.add('opacity-50');
                const legendColor = item.querySelector('.legend-color');
                if (legendColor) {
                    legendColor.style.opacity = '0.3';
                    legendColor.style.transform = 'scale(1)';
                }
            }
        });
        
        // Reset highlight setelah 5 detik
        setTimeout(() => {
            datasets.forEach((dataset, i) => {
                const meta = aktivitasChart.getDatasetMeta(i);
                if (meta) {
                    meta.hidden = !allDapurVisible && i > 2;
                    dataset.borderWidth = 2;
                    
                    if (aktivitasChart.options.elements && aktivitasChart.options.elements.line) {
                        aktivitasChart.options.elements.line.tension = 0.4;
                    }
                }
            });
            
            aktivitasChart.update();
            
            // Reset legend
            legendItems.forEach((item, i) => {
                item.classList.remove('bg-blue-50', 'border', 'border-blue-200');
                if (!allDapurVisible && i > 2) {
                    item.classList.add('opacity-50');
                    const legendColor = item.querySelector('.legend-color');
                    if (legendColor) legendColor.style.opacity = '0.5';
                } else {
                    item.classList.remove('opacity-50');
                    const legendColor = item.querySelector('.legend-color');
                    if (legendColor) legendColor.style.opacity = '1';
                }
                const legendColor = item.querySelector('.legend-color');
                if (legendColor) legendColor.style.transform = 'scale(1)';
            });
        }, 5000);
    }
}

// Fungsi untuk download chart
function downloadChart() {
    if (aktivitasChart) {
        try {
            const link = document.createElement('a');
            link.download = `grafik-aktivitas-${currentChartType}-${new Date().toISOString().slice(0,10)}.png`;
            link.href = aktivitasChart.toBase64Image();
            link.click();
            
            // Tampilkan notifikasi
            showNotification('Chart berhasil diunduh', 'success');
        } catch (error) {
            console.error('Error downloading chart:', error);
            showError('Gagal mengunduh chart');
        }
    }
}

// Fungsi untuk print laba kotor
function printLabaKotor() {
    try {
        const modalContent = document.getElementById('labaKotorModal').querySelector('.bg-white');
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            throw new Error('Popup diblokir oleh browser');
        }
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Laporan Laba Kotor - ${document.getElementById('modalLabaPeriodText').textContent}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header h1 { margin: 0; color: #333; }
                    .header p { margin: 5px 0; color: #666; }
                    .summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
                    .summary-card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; text-align: center; }
                    .summary-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; }
                    .summary-card p { margin: 0; font-size: 24px; font-weight: bold; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                    th { background-color: #f8f9fa; font-weight: bold; }
                    .profit-positive { color: #28a745; }
                    .profit-negative { color: #dc3545; }
                    .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                    @media print {
                        body { margin: 0; }
                        .no-print { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Laporan Laba Kotor</h1>
                    <p>${document.getElementById('modalLabaPeriodText').textContent}</p>
                    <p>Dicetak pada: ${new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                </div>
                
                <div class="summary">
                    <div class="summary-card">
                        <h3>Total Pendapatan</h3>
                        <p>${document.getElementById('modalTotalPendapatan').textContent}</p>
                    </div>
                    <div class="summary-card">
                        <h3>Total Biaya Bahan</h3>
                        <p>${document.getElementById('modalTotalBiaya').textContent}</p>
                    </div>
                    <div class="summary-card">
                        <h3>Laba Kotor</h3>
                        <p>${document.getElementById('modalLabaKotor').textContent}</p>
                    </div>
                </div>
                
                <h2>Detail per Dapur</h2>
                ${document.getElementById('modalDetailLaba').outerHTML}
                
                <div class="footer">
                    <p>Dokumen ini dicetak dari Sistem Manajemen Makan Bergizi Sehat</p>
                </div>
                
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            window.close();
                        };
                    };
                <\/script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
    } catch (error) {
        console.error('Error printing:', error);
        showError('Gagal mencetak laporan');
    }
}

// Fungsi untuk menampilkan notifikasi
function showNotification(message, type = 'info') {
    try {
        // Hapus notifikasi sebelumnya
        const oldNotifications = document.querySelectorAll('.notification');
        oldNotifications.forEach(notif => notif.remove());
        
        const notification = document.createElement('div');
        const colors = {
            success: 'bg-green-100 border-green-200 text-green-800',
            error: 'bg-red-100 border-red-200 text-red-800',
            info: 'bg-blue-100 border-blue-200 text-blue-800'
        };
        
        notification.className = `notification px-4 py-3 rounded-lg border ${colors[type]} shadow-lg`;
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Hapus setelah 3 detik
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    } catch (error) {
        console.error('Error showing notification:', error);
    }
}

// Fungsi untuk menampilkan error
function showError(message) {
    showNotification(message, 'error');
}

// Fungsi refresh statistik
async function refreshStatistik() {
    try {
        const response = await fetch('?ajax=true&type=statistik&t=' + Date.now());
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        document.getElementById('totalPesanan').textContent = data.total_pesanan_hari_ini;
        document.getElementById('bahanTersedia').textContent = data.bahan_tersedia;
        document.getElementById('outletAktif').textContent = data.outlet_aktif;
        document.getElementById('pendapatanHariIni').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.total_pendapatan_hari_ini);
        
        // Animasi update
        const cards = document.querySelectorAll('#statistikCards > div');
        cards.forEach(card => {
            card.classList.add('scale-105');
            setTimeout(() => {
                card.classList.remove('scale-105');
            }, 300);
        });
        
        updateTimestamp('lastUpdateTime');
        
    } catch (error) {
        console.error('Error refreshing statistik:', error);
        showError('Gagal memuat data statistik');
    }
}

// Fungsi refresh total income
async function refreshTotalIncome() {
    const currentPeriod = document.getElementById('incomePeriod').value;
    await changeIncomePeriod(currentPeriod);
}

// Fungsi refresh aktivitas gabungan
async function refreshAktivitasGabungan() {
    try {
        const response = await fetch('?ajax=true&type=aktivitas_gabungan&t=' + Date.now());
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        const container = document.getElementById('aktivitasGabunganList');
        updateTimestamp('lastAktivitasUpdate');
        
        if (data.length === 0) {
            container.innerHTML = `
                <div class="data-empty-state">
                    <i class="fas fa-store"></i>
                    <p class="font-medium text-gray-700">Belum ada aktivitas atau pesanan</p>
                    <p class="text-sm text-gray-500">24 jam terakhir</p>
                </div>
            `;
            return;
        }
        
        const dataPerDapur = {};
        
        data.forEach(item => {
            const dapur = item.type === 'aktivitas' ? item.data.dapur : item.data.dapur;
            
            if (!dataPerDapur[dapur]) {
                dataPerDapur[dapur] = [];
            }
            dataPerDapur[dapur].push(item);
        });
        
        let html = '';
        
        Object.entries(dataPerDapur).forEach(([namaDapur, itemsDapur]) => {
            html += `
                <div class="mb-6 border border-gray-200 rounded-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-5 py-3 border-b border-blue-200">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm">
                                    <i class="fas fa-store text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">${escapeHtml(namaDapur)}</h4>
                                    <p class="text-xs text-gray-600">${itemsDapur.length} aktivitas</p>
                                </div>
                            </div>
                            <span class="text-xs bg-white text-blue-700 px-3 py-1 rounded-full font-medium shadow-sm">
                                <i class="fas fa-clock mr-1"></i>Aktif
                            </span>
                        </div>
                    </div>
                    
                    <div class="divide-y divide-gray-100">`;
            
            itemsDapur.forEach(item => {
                if (item.type === 'aktivitas') {
                    const aktivitas = item.data;
                    
                    html += `
                        <div class="flex flex-col md:flex-row items-start md:items-center space-y-3 md:space-y-0 md:space-x-4 p-5 hover:bg-gray-50 transition">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-shopping-cart text-green-600"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-2 mb-2">
                                    <span class="font-medium text-gray-900">Pembelian Bahan</span>
                                    <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full font-medium self-start">Stok</span>
                                </div>
                                <div class="text-sm text-gray-600 space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-box text-gray-400"></i>
                                        <span><span class="font-medium">${escapeHtml(aktivitas.barang)}</span> • ${aktivitas.jumlah} ${escapeHtml(aktivitas.satuan)}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-database text-gray-400"></i>
                                        <span>Stok tersisa: ${aktivitas.stok_sisa} ${escapeHtml(aktivitas.satuan)}</span>
                                    </div>
                                </div>
                                <p class="text-xs text-blue-600 font-medium mt-3 flex items-center">
                                    <i class="fas fa-clock mr-1"></i>${escapeHtml(aktivitas.waktu)}
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0 self-end md:self-auto">
                                <p class="text-base font-bold text-green-600">
                                    Rp ${new Intl.NumberFormat('id-ID').format(aktivitas.total_harga)}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Total</p>
                            </div>
                        </div>`;
                } else {
                    const pesanan = item.data;
                    const barangList = pesanan.barang_dipesan ? pesanan.barang_dipesan.split(', ') : [];
                    const barangDisplay = barangList.slice(0, 2);
                    
                    html += `
                        <a href="${escapeHtml(pesanan.link)}" class="block p-5 hover:bg-gray-50 transition border-l-4 border-blue-300 no-underline text-gray-900 hover:text-gray-900">
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-3 mb-3">
                                        <span class="font-semibold text-gray-900">${escapeHtml(pesanan.customer)}</span>
                                        <span class="px-3 py-1 text-xs font-medium rounded-full ${escapeHtml(pesanan.status_color)} self-start">
                                            ${pesanan.status.charAt(0).toUpperCase() + pesanan.status.slice(1)}
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="text-sm text-gray-500 mb-2 flex items-center">
                                            <i class="fas fa-list mr-2"></i> Produk dipesan:
                                        </p>
                                        <div class="flex flex-wrap gap-2">`;
                    
                    barangDisplay.forEach(barang => {
                        html += `
                                            <span class="inline-block bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded-lg">
                                                ${escapeHtml(barang.trim())}
                                            </span>`;
                    });
                    
                    if (barangList.length > 2) {
                        html += `
                                            <span class="inline-block bg-blue-100 text-blue-700 text-sm px-3 py-1.5 rounded-lg">
                                                +${barangList.length - 2} lainnya
                                            </span>`;
                    }
                    
                    html += `
                                        </div>
                                    </div>
                                    
                                    <div class="flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-4 text-sm text-gray-500">
                                        <span class="flex items-center">
                                            <i class="fas fa-cube mr-2"></i>
                                            ${pesanan.items} item
                                        </span>
                                        <span class="flex items-center">
                                            <i class="fas fa-clock mr-2"></i>
                                            ${escapeHtml(pesanan.time)}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right ml-0 md:ml-5 flex-shrink-0 self-end md:self-auto">
                                    <p class="text-base font-bold text-green-600">
                                        Rp ${new Intl.NumberFormat('id-ID').format(pesanan.total)}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">Total</p>
                                    <span class="inline-block mt-2 text-xs text-blue-600 font-medium">
                                        <i class="fas fa-external-link-alt mr-1"></i>Detail
                                    </span>
                                </div>
                            </div>
                        </a>`;
                }
            });
            
            html += `
                    </div>
                </div>`;
        });
        
        container.innerHTML = html;
        
        // Animasi fade in
        container.style.opacity = '0';
        setTimeout(() => {
            container.style.transition = 'opacity 0.3s';
            container.style.opacity = '1';
        }, 10);
        
    } catch (error) {
        console.error('Error refreshing aktivitas gabungan:', error);
        const container = document.getElementById('aktivitasGabunganList');
        container.innerHTML = `
            <div class="data-empty-state">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                <p class="font-medium text-gray-700">Gagal memuat data aktivitas</p>
                <p class="text-sm text-gray-500">Coba refresh halaman</p>
            </div>
        `;
    }
}

// Fungsi untuk escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Inisialisasi saat halaman load
document.addEventListener('DOMContentLoaded', function() {
    updateWaktuIndonesia();
    setInterval(updateWaktuIndonesia, 1000);
    
    // Inisialisasi grafik setelah halaman selesai load
    setTimeout(() => {
        refreshGrafik();
    }, 500);
    
    // Auto-refresh setiap 30 detik
    setInterval(() => {
        refreshStatistik();
        refreshAktivitasGabungan();
    }, 30000);
    
    // Auto-refresh grafik setiap 60 detik
    setInterval(() => {
        refreshGrafik();
    }, 60000);
    
    // Tambahkan keyboard shortcut
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshData();
        }
    });
});
</script>

<?php
if (isset($koneksi) && $koneksi) {
    $koneksi->close();
}
?>
