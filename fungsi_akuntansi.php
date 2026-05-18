<?php
/**
 * FUNGSI AKUNTANSI TERPUSAT (CENTRALIZED ENGINE)
 * SCS BANJARNEGARA - PT. SURYA CERAH SEMESTA
 */

// Format angka ke Rupiah
if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// Sanitasi Input
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        return htmlspecialchars(stripslashes(trim($data)));
    }
}

// Terjemahan Nama Bulan
if (!function_exists('getBulanIndonesia')) {
    function getBulanIndonesia($bulan) {
        $bulanArr = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        return $bulanArr[$bulan] ?? $bulan;
    }
}

// =========================================================================
// === [CORE ENGINE 1] PENCATATAN JURNAL ===
// =========================================================================

/**
 * Mencatat transaksi Jurnal Umum secara manual (Debit & Kredit berpasangan)
 * Menggunakan Prepared Statements & Transaction
 */
function catatJurnal($koneksi, $tanggal, $no_reff, $keterangan, $akun_debit, $akun_kredit, $nominal) {
    $created_by = $_SESSION['user']['nama'] ?? 'System';
    
    $koneksi->begin_transaction();
    try {
        $stmt = $koneksi->prepare("INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, kode_akun, debit, kredit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Entri Debit
        $d = $nominal; $k = 0;
        $stmt->bind_param("ssssdds", $tanggal, $no_reff, $keterangan, $akun_debit, $d, $k, $created_by);
        $stmt->execute();
        
        // Entri Kredit
        $d = 0; $k = $nominal;
        $stmt->bind_param("ssssdds", $tanggal, $no_reff, $keterangan, $akun_kredit, $d, $k, $created_by);
        $stmt->execute();
        
        $koneksi->commit();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        $koneksi->rollback();
        return false;
    }
}

/**
 * Mencatat jurnal penjualan otomatis berdasarkan ID Pesanan
 * Mencakup: Kas (D), Pendapatan (K), HPP (D), Persediaan (K)
 */
function catatJurnalPenjualan($koneksi, $id_pesanan, $kode_akun_kas = '1111') {
    // 1. Ambil data pesanan
    $stmt = $koneksi->prepare("
        SELECT p.*, u.nama as nama_dapur 
        FROM pesanan p 
        LEFT JOIN user u ON p.id_dapur = u.id 
        WHERE p.id_pesanan = ?
    ");
    $stmt->bind_param("i", $id_pesanan);
    $stmt->execute();
    $res_pesanan = $stmt->get_result();
    
    if ($res_pesanan->num_rows == 0) return false;
    $pesanan = $res_pesanan->fetch_assoc();
    $stmt->close();
    
    // 2. Ambil detail items untuk HPP
    $stmt = $koneksi->prepare("
        SELECT dp.*, g.harga_beli 
        FROM detail_pesanan dp 
        JOIN gudang g ON dp.id_barang = g.id_barang 
        WHERE dp.id_pesanan = ?
    ");
    $stmt->bind_param("i", $id_pesanan);
    $stmt->execute();
    $res_items = $stmt->get_result();
    
    $total_harga = $pesanan['total_harga'];
    $tanggal = date('Y-m-d', strtotime($pesanan['tgl_pesan']));
    $no_reff = "ORD-" . $id_pesanan;
    $keterangan = "Penjualan ke " . $pesanan['nama_pemesan'] . " (" . $pesanan['nama_dapur'] . ")";
    $created_by = 'System Auto';
    
    $total_hpp = 0;
    while ($item = $res_items->fetch_assoc()) {
        $harga_beli = $item['harga_beli_saat_itu'] > 0 ? $item['harga_beli_saat_itu'] : $item['harga_beli'];
        $total_hpp += ($item['jumlah'] * $harga_beli);
    }
    $stmt->close();
    
    $koneksi->begin_transaction();
    try {
        $stmt = $koneksi->prepare("INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, kode_akun, debit, kredit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // A. Jurnal Utama (Kas vs Pendapatan)
        $d = $total_harga; $k = 0;
        $stmt->bind_param("ssssdds", $tanggal, $no_reff, $keterangan, $kode_akun_kas, $d, $k, $created_by);
        $stmt->execute();
        
        $d = 0; $k = $total_harga;
        $akun_pendapatan = '4111';
        $stmt->bind_param("ssssdds", $tanggal, $no_reff, $keterangan, $akun_pendapatan, $d, $k, $created_by);
        $stmt->execute();
        
        // B. Jurnal HPP (HPP vs Persediaan)
        if ($total_hpp > 0) {
            $ket_hpp = "HPP - " . $keterangan;
            $d = $total_hpp; $k = 0;
            $akun_hpp = '5111';
            $stmt->bind_param("ssssdds", $tanggal, $no_reff, $ket_hpp, $akun_hpp, $d, $k, $created_by);
            $stmt->execute();
            
            $ket_inv = "Penggunaan Persediaan - " . $keterangan;
            $d = 0; $k = $total_hpp;
            $akun_persediaan = '1131';
            $stmt->bind_param("ssssdds", $tanggal, $no_reff, $ket_inv, $akun_persediaan, $d, $k, $created_by);
            $stmt->execute();
        }
        
        $koneksi->commit();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        $koneksi->rollback();
        return false;
    }
}

// Fungsi untuk trigger ketika status pesanan menjadi Lunas
function triggerJurnalPenjualan($koneksi, $id_pesanan, $kode_akun_kas = '1111') {
    // Cek apakah jurnal untuk pesanan ini sudah ada
    $stmt = $koneksi->prepare("SELECT COUNT(*) as count FROM jurnal_umum WHERE no_reff = ?");
    $no_reff = 'ORD-' . $id_pesanan;
    $stmt->bind_param("s", $no_reff);
    $stmt->execute();
    $check_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($check_data['count'] == 0) {
        return catatJurnalPenjualan($koneksi, $id_pesanan, $kode_akun_kas);
    }
    return true;
}

// =========================================================================
// === [CORE ENGINE 2] PERHITUNGAN SALDO & LABA ===
// =========================================================================

/**
 * Menghitung saldo akhir sebuah akun berdasarkan posisi normalnya
 */
function getAccountBalance($koneksi, $kode_akun, $tgl_akhir = null, $tgl_awal = null) {
    // 1. Dapatkan posisi normal dari COA
    $stmt = $koneksi->prepare("SELECT posisi_normal FROM akun_coa WHERE kode_akun = ?");
    $stmt->bind_param("s", $kode_akun);
    $stmt->execute();
    $res = $stmt->get_result();
    $coa = $res->fetch_assoc();
    $posisi_normal = $coa['posisi_normal'] ?? 'Debit';
    $stmt->close();

    // 2. Query total debit & kredit
    $sql = "SELECT SUM(debit) as d, SUM(kredit) as k FROM jurnal_umum WHERE kode_akun = ?";
    $params = [$kode_akun];
    $types = "s";

    if ($tgl_awal && $tgl_akhir) {
        $sql .= " AND (tanggal BETWEEN ? AND ?)";
        $params[] = $tgl_awal; $params[] = $tgl_akhir;
        $types .= "ss";
    } elseif ($tgl_akhir) {
        $sql .= " AND tanggal <= ?";
        $params[] = $tgl_akhir;
        $types .= "s";
    }
    
    $sql .= " AND no_reff NOT LIKE 'CLS%'";

    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $debit = $data['d'] ?? 0;
    $kredit = $data['k'] ?? 0;

    return ($posisi_normal == 'Debit') ? ($debit - $kredit) : ($kredit - $debit);
}

/**
 * Menghitung Laba/Rugi Bersih dalam periode tertentu
 */
function getNetIncome($koneksi, $tgl_akhir, $tgl_awal = null) {
    $where = "";
    $params = [];
    $types = "";

    if ($tgl_awal) {
        $where = " AND (j.tanggal BETWEEN ? AND ?)";
        $params = [$tgl_awal, $tgl_akhir];
        $types = "ss";
    } else {
        $where = " AND j.tanggal <= ?";
        $params = [$tgl_akhir];
        $types = "s";
    }

    $sql = "SELECT 
                SUM(CASE 
                    WHEN a.kategori = 'Pendapatan' THEN (j.kredit - j.debit)
                    ELSE 0 END) as pendapatan,
                SUM(CASE 
                    WHEN a.kategori = 'Beban' THEN (j.debit - j.kredit)
                    ELSE 0 END) as beban
            FROM jurnal_umum j
            JOIN akun_coa a ON j.kode_akun = a.kode_akun
            WHERE j.no_reff NOT LIKE 'CLS%'" . $where;

    $stmt = $koneksi->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($res['pendapatan'] ?? 0) - ($res['beban'] ?? 0);
}

/**
 * Mendapatkan Total Nilai per Kategori (Aset, Kewajiban, dll)
 */
function getCategoryTotal($koneksi, $kategori, $tgl_akhir) {
    $sql = "SELECT 
                SUM(j.debit) as total_debit, 
                SUM(j.kredit) as total_kredit,
                a.posisi_normal
            FROM jurnal_umum j
            JOIN akun_coa a ON j.kode_akun = a.kode_akun
            WHERE a.kategori = ? AND j.tanggal <= ? AND j.no_reff NOT LIKE 'CLS%'
            GROUP BY a.posisi_normal";
            
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("ss", $kategori, $tgl_akhir);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $total = 0;
    while ($row = $res->fetch_assoc()) {
        if ($row['posisi_normal'] == 'Debit') {
            $total += ($row['total_debit'] - $row['total_kredit']);
        } else {
            $total += ($row['total_kredit'] - $row['total_debit']);
        }
    }
    $stmt->close();
    return $total;
}

/**
 * Mengecek apakah tanggal tertentu berada dalam periode yang sudah dikunci/ditutup
 */
function isDateLocked($koneksi, $tanggal) {
    $bulan = date('n', strtotime($tanggal));
    $tahun = date('Y', strtotime($tanggal));
    
    $stmt = $koneksi->prepare("SELECT status FROM periode_status WHERE bulan = ? AND tahun = ?");
    $stmt->bind_param("ii", $bulan, $tahun);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (($res['status'] ?? 'Open') == 'Closed');
}

/**
 * Mengecek apakah periode tertentu sudah ditutup
 */
function isPeriodClosed($koneksi, $bulan, $tahun) {
    $stmt = $koneksi->prepare("SELECT status FROM periode_status WHERE bulan = ? AND tahun = ?");
    $stmt->bind_param("ii", $bulan, $tahun);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return (($res['status'] ?? 'Open') == 'Closed');
}
?>
