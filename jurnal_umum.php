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

// ===================== 2. INISIALISASI VARIABEL FILTER =====================
// Filter tanggal
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// Filter pencarian
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter tambahan
$filter_tipe = isset($_GET['filter_tipe']) ? $_GET['filter_tipe'] : '';
$filter_min_nominal = isset($_GET['filter_min_nominal']) ? $_GET['filter_min_nominal'] : '';
$filter_max_nominal = isset($_GET['filter_max_nominal']) ? $_GET['filter_max_nominal'] : '';

// Sorting
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'waktu_desc';
$sort_column = 'tanggal';
$sort_order = 'DESC';

switch($sort_by) {
    case 'waktu_asc':
        $sort_column = 'tanggal';
        $sort_order = 'ASC';
        break;
    case 'input_terbaru':
        $sort_column = 'created_at';
        $sort_order = 'DESC';
        break;
    case 'input_terlama':
        $sort_column = 'created_at';
        $sort_order = 'ASC';
        break;
    case 'harga_tinggi':
        $sort_column = 'total_debit';
        $sort_order = 'DESC';
        break;
    case 'harga_rendah':
        $sort_column = 'total_debit';
        $sort_order = 'ASC';
        break;
    case 'waktu_desc':
    default:
        $sort_column = 'tanggal';
        $sort_order = 'DESC';
        break;
}

// ===================== 3. LOGIC HAPUS TRANSAKSI =====================
$pesan = "";
if (isset($_GET['hapus_reff'])) {
    $reff_hapus = $_GET['hapus_reff'];
    
    // Cek tanggal transaksi tersebut sebelum hapus
    $stmt_check = $koneksi->prepare("SELECT tanggal FROM jurnal_umum WHERE no_reff = ? LIMIT 1");
    $stmt_check->bind_param("s", $reff_hapus);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if ($res_check && isDateLocked($koneksi, $res_check['tanggal'])) {
        $pesan = "<script>Swal.fire({icon: 'error', title: 'Terkunci!', text: 'Periode transaksi ini sudah ditutup. Tidak dapat menghapus.'});</script>";
    } else {
        // Hapus data di jurnal menggunakan Prepared Statement
        $stmt = $koneksi->prepare("DELETE FROM jurnal_umum WHERE no_reff = ?");
        $stmt->bind_param("s", $reff_hapus);
        $q_hapus = $stmt->execute();
        
        if ($q_hapus) {
            $pesan = "<script>Swal.fire({icon: 'success', title: 'Terhapus!', text: 'Data jurnal berhasil dihapus.', timer: 1500, showConfirmButton: false}).then(() => { window.location.href = 'jurnal_umum.php?tgl_mulai=$tgl_mulai&tgl_selesai=$tgl_selesai&sort_by=$sort_by&search=".urlencode($search_keyword)."'; });</script>";
        } else {
            $pesan = "<script>Swal.fire({icon: 'error', title: 'Gagal', text: 'Gagal menghapus data.'});</script>";
        }
        $stmt->close();
    }
}

// ===================== 4. LOGIC SIMPAN TRANSAKSI MULTI AKUN =====================
if (isset($_POST['simpan_transaksi_multi'])) {
    $tgl = $_POST['tanggal'];
    
    // Cek apakah periode dikunci
    if (isDateLocked($koneksi, $tgl)) {
        $pesan = "<script>Swal.fire({icon: 'error', title: 'Terkunci!', text: 'Tanggal yang Anda pilih berada dalam periode yang sudah ditutup.'});</script>";
    } else {
        $ket_umum = mysqli_real_escape_string($koneksi, $_POST['keterangan_umum']);
        $akun_ids = $_POST['akun_id'];
        $tipe_akuns = $_POST['tipe_akun'];
        $nominals = $_POST['nominal'];
        $keterangans = $_POST['keterangan_detail'];
        
        $no_reff = isset($_POST['no_reff']) && !empty($_POST['no_reff']) ? $_POST['no_reff'] : "MAN-" . date('ymdHis');
    
    // Validasi balance
    $total_debit = 0;
    $total_kredit = 0;
    
    foreach ($akun_ids as $index => $akun_id) {
        $nominal = str_replace('.', '', $nominals[$index]);
        $tipe = $tipe_akuns[$index];
        
        if ($tipe === 'debit') {
            $total_debit += (float)$nominal;
        } else {
            $total_kredit += (float)$nominal;
        }
    }
    
    if (abs($total_debit - $total_kredit) > 100) {
        $pesan = "<script>Swal.fire({icon: 'error', title: 'Tidak Balance!', text: 'Total debit (".formatRupiah($total_debit).") dan kredit (".formatRupiah($total_kredit).") harus sama!'});</script>";
    } else {
        mysqli_autocommit($koneksi, false);
        
        try {
            $stmt = $koneksi->prepare("INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, keterangan_umum, kode_akun, debit, kredit, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            foreach ($akun_ids as $index => $akun_id) {
                $nominal = str_replace('.', '', $nominals[$index]);
                $tipe = $tipe_akuns[$index];
                
                // Set keterangan
                $keterangan_detail = !empty($keterangans[$index]) ? $keterangans[$index] : $ket_umum;
                
                if ($nominal > 0) {
                    $debit = $tipe === 'debit' ? $nominal : 0;
                    $kredit = $tipe === 'kredit' ? $nominal : 0;
                    
                    $stmt->bind_param("sssssdd", $tgl, $no_reff, $keterangan_detail, $ket_umum, $akun_id, $debit, $kredit);
                    if (!$stmt->execute()) {
                        throw new Exception("Gagal menyimpan entri akun: " . $stmt->error);
                    }
                }
            }
            $stmt->close();
            
            mysqli_commit($koneksi);
            $pesan = "<script>Swal.fire({icon: 'success', title: 'Berhasil!', text: 'Jurnal multi-akun berhasil disimpan.', timer: 1500, showConfirmButton: false}).then(() => { window.location='jurnal_umum.php?tgl_mulai=$tgl_mulai&tgl_selesai=$tgl_selesai&sort_by=$sort_by&search=".urlencode($search_keyword)."'; });</script>";
            
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan = "<script>Swal.fire({icon: 'error', title: 'Gagal!', text: '".$e->getMessage()."'});</script>";
        }
        
        mysqli_autocommit($koneksi, true);
            }
        }
    }

// ===================== 5. LOGIC EDIT TRANSAKSI MULTI AKUN =====================
if (isset($_POST['edit_transaksi_multi'])) { $no_reff = $_POST['no_reff']; $tgl = $_POST['tanggal']; if (isDateLocked($koneksi, $tgl)) { $pesan = "<script>Swal.fire({icon: 'error', title: 'Terkunci!', text: 'Periode transaksi ini sudah ditutup. Tidak dapat diubah.'});</script>"; } else {
    $ket_umum = mysqli_real_escape_string($koneksi, $_POST['keterangan_umum']);
    $akun_ids = $_POST['akun_id'];
    $tipe_akuns = $_POST['tipe_akun'];
    $nominals = $_POST['nominal'];
    $keterangans = $_POST['keterangan_detail'];
    
    // Validasi balance
    $total_debit = 0;
    $total_kredit = 0;
    
    foreach ($akun_ids as $index => $akun_id) {
        $nominal = str_replace('.', '', $nominals[$index]);
        $tipe = $tipe_akuns[$index];
        
        if ($tipe === 'debit') {
            $total_debit += (float)$nominal;
        } else {
            $total_kredit += (float)$nominal;
        }
    }
    
    if (abs($total_debit - $total_kredit) > 100) {
        $pesan = "<script>Swal.fire({icon: 'error', title: 'Tidak Balance!', text: 'Total debit (".formatRupiah($total_debit).") dan kredit (".formatRupiah($total_kredit).") harus sama!'});</script>";
    } else {
        mysqli_autocommit($koneksi, false);
        
        try {
            // Hapus entri lama menggunakan Prepared Statement
            $stmt_del = $koneksi->prepare("DELETE FROM jurnal_umum WHERE no_reff = ?");
            $stmt_del->bind_param("s", $no_reff);
            if (!$stmt_del->execute()) {
                throw new Exception("Gagal menghapus entri lama");
            }
            $stmt_del->close();
            
            // Simpan entri baru menggunakan Prepared Statement
            $stmt_ins = $koneksi->prepare("INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, keterangan_umum, kode_akun, debit, kredit, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            
            foreach ($akun_ids as $index => $akun_id) {
                $nominal = str_replace('.', '', $nominals[$index]);
                $tipe = $tipe_akuns[$index];
                
                $keterangan_detail = !empty($keterangans[$index]) ? $keterangans[$index] : $ket_umum;
                
                if ($nominal > 0) {
                    $debit = $tipe === 'debit' ? $nominal : 0;
                    $kredit = $tipe === 'kredit' ? $nominal : 0;
                    
                    $stmt_ins->bind_param("sssssdd", $tgl, $no_reff, $keterangan_detail, $ket_umum, $akun_id, $debit, $kredit);
                    if (!$stmt_ins->execute()) {
                        throw new Exception("Gagal menyimpan entri akun: " . $stmt_ins->error);
                    }
                }
            }
            $stmt_ins->close();
            
            mysqli_commit($koneksi);
            $pesan = "<script>Swal.fire({icon: 'success', title: 'Berhasil!', text: 'Jurnal berhasil diupdate.', timer: 1500, showConfirmButton: false}).then(() => { window.location='jurnal_umum.php?tgl_mulai=$tgl_mulai&tgl_selesai=$tgl_selesai&sort_by=$sort_by&search=".urlencode($search_keyword)."'; });</script>";
            
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan = "<script>Swal.fire({icon: 'error', title: 'Gagal!', text: '".$e->getMessage()."'});</script>";
        }
        
        mysqli_autocommit($koneksi, true);
            }
        }
    }

// ===================== 6. AMBIL DATA AKUN COA =====================
$q_akun = mysqli_query($koneksi, "SELECT * FROM akun_coa ORDER BY kode_akun ASC");
$daftar_akun = []; 
while($r = mysqli_fetch_assoc($q_akun)) { 
    $daftar_akun[] = $r; 
}

// ===================== 7. QUERY DATA JURNAL DENGAN FILTER LENGKAP =====================
$where_conditions = ["j.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'"];

// Filter pencarian (Dihapus agar semua data terload di awal untuk client-side search)
/* 
if (!empty($search_keyword)) {
    $search_escaped = mysqli_real_escape_string($koneksi, $search_keyword);
    $where_conditions[] = "j.no_reff IN (
        SELECT DISTINCT no_reff 
        FROM jurnal_umum 
        WHERE (
            no_reff LIKE '%$search_escaped%' OR
            keterangan LIKE '%$search_escaped%' OR
            keterangan_umum LIKE '%$search_escaped%' OR
            kode_akun LIKE '%$search_escaped%'
        )
    )";
}
*/

// Filter tipe transaksi
if (!empty($filter_tipe)) {
    if ($filter_tipe == 'pemasukan') {
        $where_conditions[] = "j.no_reff LIKE 'ORD-%'";
    } elseif ($filter_tipe == 'pengeluaran') {
        $where_conditions[] = "j.no_reff LIKE 'MAN-%'";
    }
}

// Filter nominal range
if (!empty($filter_min_nominal) || !empty($filter_max_nominal)) {
    $min_condition = "";
    $max_condition = "";
    
    if (!empty($filter_min_nominal)) {
        $min_nominal = str_replace('.', '', $filter_min_nominal);
        $min_condition = "GROUP_TOTALS.total_debit >= $min_nominal OR GROUP_TOTALS.total_kredit >= $min_nominal";
    }
    
    if (!empty($filter_max_nominal)) {
        $max_nominal = str_replace('.', '', $filter_max_nominal);
        $max_condition = "GROUP_TOTALS.total_debit <= $max_nominal OR GROUP_TOTALS.total_kredit <= $max_nominal";
    }
    
    // Gabungkan kondisi nominal
    if ($min_condition && $max_condition) {
        $where_conditions[] = "j.no_reff IN (
            SELECT no_reff FROM (
                SELECT no_reff, SUM(debit) as total_debit, SUM(kredit) as total_kredit
                FROM jurnal_umum
                GROUP BY no_reff
                HAVING ($min_condition) AND ($max_condition)
            ) AS GROUP_TOTALS
        )";
    } elseif ($min_condition) {
        $where_conditions[] = "j.no_reff IN (
            SELECT no_reff FROM (
                SELECT no_reff, SUM(debit) as total_debit, SUM(kredit) as total_kredit
                FROM jurnal_umum
                GROUP BY no_reff
                HAVING $min_condition
            ) AS GROUP_TOTALS
        )";
    } elseif ($max_condition) {
        $where_conditions[] = "j.no_reff IN (
            SELECT no_reff FROM (
                SELECT no_reff, SUM(debit) as total_debit, SUM(kredit) as total_kredit
                FROM jurnal_umum
                GROUP BY no_reff
                HAVING $max_condition
            ) AS GROUP_TOTALS
        )";
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Query untuk mendapatkan data grup transaksi
$query_grup_transaksi = "SELECT 
    j.no_reff,
    j.tanggal,
    j.created_at,
    j.keterangan_umum,
    SUM(j.debit) as total_debit_grup,
    SUM(j.kredit) as total_kredit_grup,
    COUNT(*) as jumlah_entries,
    CASE 
        WHEN j.no_reff LIKE 'ORD-%' THEN 'penjualan'
        ELSE 'manual'
    END as tipe_transaksi
    FROM jurnal_umum j
    WHERE $where_clause
    GROUP BY j.no_reff, j.tanggal, j.created_at, j.keterangan_umum
    ORDER BY $sort_column $sort_order, j.no_reff DESC";

$result_grup = mysqli_query($koneksi, $query_grup_transaksi);

// Kelompokkan data berdasarkan no_reff
$jurnal_data_grouped = [];
$total_debit_periode = 0;
$total_kredit_periode = 0;
$total_groups = 0;

if ($result_grup && mysqli_num_rows($result_grup) > 0) {
    while($grup = mysqli_fetch_assoc($result_grup)) {
        $no_reff = $grup['no_reff'];
        
        // Ambil detail entries untuk grup ini menggunakan Prepared Statement
        $stmt_detail = $koneksi->prepare("SELECT j.*, a.nama_akun FROM jurnal_umum j JOIN akun_coa a ON j.kode_akun = a.kode_akun WHERE j.no_reff = ? ORDER BY CASE WHEN j.debit > 0 THEN 0 ELSE 1 END ASC, j.id ASC");
        $stmt_detail->bind_param("s", $no_reff);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        $entries = [];
        
        while($row = $result_detail->fetch_assoc()) {
            $entries[] = $row;
        }
        $stmt_detail->close();
        
        $jurnal_data_grouped[$no_reff] = [
            'tanggal' => $grup['tanggal'],
            'created_at' => $grup['created_at'],
            'is_otomatis' => ($grup['tipe_transaksi'] == 'penjualan'),
            'keterangan_umum' => $grup['keterangan_umum'],
            'total_debit_grup' => $grup['total_debit_grup'],
            'total_kredit_grup' => $grup['total_kredit_grup'],
            'entries' => $entries
        ];
        
        $total_debit_periode += $grup['total_debit_grup'];
        $total_kredit_periode += $grup['total_kredit_grup'];
        $total_groups++;
    }
}

// Helper function untuk highlight text pencarian
function highlightText($text, $search) {
    if (empty($search) || empty($text)) {
        return htmlspecialchars($text);
    }
    
    $pattern = '/(' . preg_quote($search, '/') . ')/i';
    return preg_replace($pattern, '<span class="highlight-search">$1</span>', htmlspecialchars($text));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jurnal Umum - PT. SURYA CERAH SEMESTA</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #F3F4F6; 
        }
        
        .soft-card { 
            background: white; 
            border-radius: 0.75rem; 
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); 
            border: 1px solid #e2e8f0;
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
            transition: border-color 0.15s ease; 
        }
        
        .input-simple {
            background-color: #F8FAFC;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem;
            width: 100%;
            transition: border-color 0.15s ease;
        }
        
        .input-simple:focus {
            background-color: white;
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .input-modern:focus { 
            background-color: #FFF; 
            border-color: #3B82F6; 
            outline: none; 
        }
        
        /* Select2 */
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
        
        .modal-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0, 0, 0, 0.5); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            z-index: 10000; 
        }

        .transaction-group {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: box-shadow 0.15s ease;
        }
        
        .transaction-group:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .transaction-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .transaction-body {
            background: white;
        }
        
        .debit-row {
            background-color: rgba(16, 185, 129, 0.05);
            border-left: 4px solid #10b981;
        }
        
        .kredit-row {
            background-color: rgba(239, 68, 68, 0.05);
            border-left: 4px solid #ef4444;
        }
        
        .highlight-search {
            background-color: #fef3c7;
            border-radius: 4px;
            padding: 2px 4px;
            font-weight: 600;
        }
        
        .input-error {
            border-color: #EF4444 !important;
            background-color: #FEF2F2 !important;
        }

        .balance-true {
            background: #10B981;
            color: white;
        }
        
        .balance-false {
            background: #EF4444;
            color: white;
        }

        .btn-animate {
            transition: all 0.15s ease;
        }
        
        .btn-animate:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .badge-penjualan {
            background: #F59E0B;
            color: white;
            border: 1px solid #F59E0B;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .badge-manual {
            background: #3B82F6;
            color: white;
            border: 1px solid #3B82F6;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .sorting-dropdown {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: #4b5563;
            cursor: pointer;
            transition: border-color 0.15s ease;
            min-width: 140px;
        }
        
        .sorting-dropdown:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        
        .keterangan-cell {
            max-width: 300px;
            word-wrap: break-word;
        }
        
        .keterangan-umum-box {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid rgba(255, 255, 255, 0.5);
            padding: 8px 12px;
            margin-top: 4px;
            border-radius: 4px;
        }
        
        /* Search bar */
        .search-bar {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            background: white;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        
        .search-action-buttons {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 0.25rem;
        }
        
        .search-action-btn {
            background: transparent;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6b7280;
            transition: background-color 0.15s ease;
        }
        
        .search-action-btn:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .filter-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 0.25rem;
        }
        
        .filter-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .active-filters {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-top: 1rem;
        }
        
        .filter-badge {
            display: inline-flex;
            align-items: center;
            background: #e2e8f0;
            border-radius: 16px;
            padding: 4px 12px;
            margin: 2px 4px;
            font-size: 0.75rem;
        }
        
        .filter-badge-remove {
            margin-left: 6px;
            cursor: pointer;
            color: #64748b;
        }
        
        .filter-badge-remove:hover {
            color: #ef4444;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .transaction-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body class="text-slate-800">

    <?php include 'sidebar.php'; ?>
    <?php echo $pesan; ?>

    <div class="md:ml-64 min-h-screen p-4 md:p-6 transition-all duration-300 relative">
        
        <?php 
        $sort_labels = [
            'waktu_desc' => 'Terbaru',
            'waktu_asc' => 'Terlama', 
            'input_terbaru' => 'Input Terbaru',
            'input_terlama' => 'Input Terlama',
            'harga_tinggi' => 'Harga Tertinggi',
            'harga_rendah' => 'Harga Terendah'
        ];
        ?>
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 gap-4">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Jurnal Umum Transaksi</h1>
            </div>
            <div class="flex gap-2 items-center w-full lg:w-auto">
                <button onclick="toggleForm()" class="w-full lg:w-auto flex justify-center items-center gap-2 bg-[#0f9d58] hover:bg-[#0b8043] text-white px-5 py-2.5 rounded-lg shadow-sm transition-all btn-animate font-semibold text-sm">
                    <i class="fas fa-plus"></i> Transaksi Baru
                </button>
            </div>
        </div>

        <!-- Single Big Summary Card like Image -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 mb-8 overflow-hidden">
            <!-- Top Stats Row -->
            <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-slate-100 border-b border-slate-100">
                <div class="p-6 flex flex-col justify-center items-center text-center hover:bg-slate-50 transition-colors">
                    <div class="flex items-end gap-2">
                        <p class="text-3xl font-bold text-slate-800"><?= number_format($total_groups) ?></p>
                    </div>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-2">TOTAL TRANSAKSI</p>
                </div>
                <div class="p-6 flex flex-col justify-center items-center text-center hover:bg-slate-50 transition-colors">
                    <p class="text-2xl font-bold text-slate-800"><?= formatRupiah($total_debit_periode) ?></p>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-2">TOTAL DEBIT</p>
                </div>
                <div class="p-6 flex flex-col justify-center items-center text-center hover:bg-slate-50 transition-colors">
                    <p class="text-2xl font-bold text-slate-800"><?= formatRupiah($total_kredit_periode) ?></p>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-2">TOTAL KREDIT</p>
                </div>
                <div class="p-6 flex flex-col justify-center items-center text-center hover:bg-slate-50 transition-colors">
                    <p class="text-xl font-bold <?= abs($total_debit_periode - $total_kredit_periode) <= 100 ? 'text-[#0f9d58]' : 'text-red-500' ?>"><?= abs($total_debit_periode - $total_kredit_periode) <= 100 ? 'BALANCE' : 'TIDAK BALANCE' ?></p>
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mt-2">STATUS</p>
                </div>
            </div>
            
            <!-- Bottom Info Row -->
            <div class="grid grid-cols-2 md:grid-cols-5 p-5 gap-4 bg-slate-50">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">TANGGAL MULAI</p>
                    <p class="text-sm font-semibold text-slate-700"><?= date('M d, Y', strtotime($tgl_mulai)) ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">TANGGAL SELESAI</p>
                    <p class="text-sm font-semibold text-slate-700"><?= date('M d, Y', strtotime($tgl_selesai)) ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">TIPE FILTER</p>
                    <p class="text-sm font-semibold text-slate-700"><?= empty($filter_tipe) ? 'Semua Tipe' : ucfirst($filter_tipe) ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">PENCARIAN</p>
                    <p class="text-sm font-semibold text-slate-700 truncate"><?= empty($search_keyword) ? '-' : htmlspecialchars($search_keyword) ?></p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">PENGURUTAN</p>
                    <p class="text-sm font-semibold text-slate-700"><?= $sort_labels[$sort_by] ?? 'Terbaru' ?></p>
                </div>
            </div>
        </div>

        <div id="formInput" class="modal-overlay hidden">
            <div class="soft-card p-8 bg-white w-full max-w-4xl max-h-[90vh] overflow-y-auto relative" style="z-index: 50;">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg text-slate-700">Input Jurnal Umum</h3>
                    <button type="button" onclick="toggleForm()" class="text-slate-400 hover:text-red-500 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" action="" id="formJurnalMulti" onsubmit="return validasiSebelumSimpan()" class="space-y-6">
                    <input type="hidden" name="tgl_mulai" value="<?= $tgl_mulai ?>">
                    <input type="hidden" name="tgl_selesai" value="<?= $tgl_selesai ?>">
                    <input type="hidden" name="sort_by" value="<?= $sort_by ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_keyword) ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-6">
                        <div class="md:col-span-3">
                            <label class="text-xs font-bold text-slate-500 uppercase mb-2 block">Tanggal Transaksi</label>
                            <input type="date" name="tanggal" value="<?php echo date('Y-m-d'); ?>" class="input-modern w-full rounded-lg p-3 text-sm" required>
                        </div>
                        
                        <div class="md:col-span-9">
                            <label class="text-xs font-bold text-slate-500 uppercase mb-2 block">Keterangan Umum (Referensi)</label>
                            <input type="text" name="keterangan_umum" id="keterangan_umum" 
                                   class="input-modern w-full rounded-lg p-3 text-sm" 
                                   placeholder="Contoh: Pembelian komputer untuk kantor, Pembayaran gaji bulan Januari, dll..." 
                                   required>
                        </div>
                    </div>

                    <div class="card p-4 mb-4">
                        <div class="mb-4 flex justify-between items-center">
                            <div>
                                <h4 class="font-semibold text-gray-700">Detail Entri Jurnal</h4>
                                <p class="text-xs text-gray-500 mt-1">Tambahkan akun-akun yang terlibat dalam transaksi</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="tambahBaris('debit')" 
                                        class="btn-animate bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg transition-all text-sm font-medium flex items-center gap-2">
                                    <i class="fas fa-plus"></i> Debit
                                </button>
                                <button type="button" onclick="tambahBaris('kredit')" 
                                        class="btn-animate bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg transition-all text-sm font-medium flex items-center gap-2">
                                    <i class="fas fa-plus"></i> Kredit
                                </button>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-4">
                            <div class="border border-gray-300 rounded-lg">
                                <div class="bg-green-600 text-white px-4 py-3 rounded-t-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-arrow-down text-white"></i>
                                            <h5 class="font-bold">DEBIT</h5>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs opacity-90">Total</div>
                                            <div class="font-bold" id="lblTotalDebit">Rp 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3 max-h-80 overflow-y-auto p-4 custom-scrollbar" id="containerDebit">
                                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                                        <div class="space-y-3">
                                            <div>
                                                <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                                                <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun" required>
                                                    <option value="">-- Pilih Akun --</option>
                                                    <?php foreach($daftar_akun as $ak): ?>
                                                        <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="tipe_akun[]" value="debit">
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                                                    <input type="text" name="nominal[]" 
                                                           class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input debit-input" 
                                                           placeholder="0" onkeyup="formatRupiahInput(this); hitungTotal();" required>
                                                </div>
                                                <div>
                                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                                                    <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                                            onclick="hapusBaris(this)" title="Hapus Baris" data-confirm="true">
                                                        <i class="fas fa-times"></i>
                                                        <span class="text-xs">Hapus</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                                                <input type="text" name="keterangan_detail[]" 
                                                       class="w-full rounded border border-gray-300 p-2 text-sm" 
                                                       placeholder="Keterangan spesifik untuk akun ini">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border border-gray-300 rounded-lg">
                                <div class="bg-red-600 text-white px-4 py-3 rounded-t-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-arrow-up text-white"></i>
                                            <h5 class="font-bold">KREDIT</h5>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs opacity-90">Total</div>
                                            <div class="font-bold" id="lblTotalKredit">Rp 0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3 max-h-80 overflow-y-auto p-4 custom-scrollbar" id="containerKredit">
                                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                                        <div class="space-y-3">
                                            <div>
                                                <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                                                <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun" required>
                                                    <option value="">-- Pilih Akun --</option>
                                                    <?php foreach($daftar_akun as $ak): ?>
                                                        <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" name="tipe_akun[]" value="kredit">
                                            </div>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                                                    <input type="text" name="nominal[]" 
                                                           class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input kredit-input" 
                                                           placeholder="0" onkeyup="formatRupiahInput(this); hitungTotal();" required>
                                                </div>
                                                <div>
                                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                                                    <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                                            onclick="hapusBaris(this)" title="Hapus Baris" data-confirm="true">
                                                        <i class="fas fa-times"></i>
                                                        <span class="text-xs">Hapus</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                                                <input type="text" name="keterangan_detail[]" 
                                                       class="w-full rounded border border-gray-300 p-2 text-sm" 
                                                       placeholder="Keterangan spesifik untuk akun ini">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center pt-4 border-t border-gray-200 gap-4">
                        <div class="flex flex-col gap-2">
                            <div id="statusBalanceCompact" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 balance-false">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>BELUM BALANCE!</span>
                                <span id="selisihAmountCompact" class="text-xs">(Rp 0)</span>
                            </div>
                            
                            <div class="text-xs text-gray-500 flex items-center gap-1">
                                <i class="fas fa-lightbulb text-yellow-500"></i>
                                <span>Pastikan total debit dan kredit sama</span>
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="button" onclick="toggleForm()" 
                                    class="bg-slate-500 hover:bg-slate-600 text-white font-medium px-6 py-3 rounded-xl transition-all btn-animate">
                                Batal
                            </button>
                            <button type="submit" name="simpan_transaksi_multi" id="btnSimpan" 
                                    class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-3 rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed btn-animate flex items-center gap-2"
                                    disabled>
                                <i class="fas fa-save"></i> Simpan Transaksi
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sub-header: Search & Filters -->
        <div class="flex flex-col lg:flex-row justify-between items-center mb-6 gap-4">
            <div class="font-bold text-slate-800 tracking-wide text-sm flex items-center gap-2">
                <span id="totalGroupsCount"><?= $total_groups ?></span> TRANSAKSI DITEMUKAN
                <?php if (!empty($active_filters)): ?>
                    <span class="bg-teal-50 text-teal-700 px-2 py-0.5 rounded-md text-[10px] border border-teal-100 font-bold"><?= count($active_filters) ?> Filters Active</span>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-3 w-full lg:w-auto">
                <form method="GET" id="searchForm" class="flex-1 lg:w-[400px] relative">
                    <div class="relative w-full">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search_keyword) ?>" placeholder="Search..." class="w-full bg-slate-100/70 border border-slate-200 rounded-lg py-2.5 pl-11 pr-4 text-sm font-medium focus:ring-2 focus:ring-[#2d8a9d]/20 focus:border-[#2d8a9d] focus:bg-white outline-none transition-all">
                        <?php if (!empty($search_keyword)): ?>
                            <button type="button" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-400 hover:text-slate-600" onclick="clearSearch()"><i class="fas fa-times"></i></button>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="tgl_mulai" value="<?= $tgl_mulai ?>">
                    <input type="hidden" name="tgl_selesai" value="<?= $tgl_selesai ?>">
                    <input type="hidden" name="sort_by" value="<?= $sort_by ?>">
                    <input type="hidden" name="filter_tipe" id="filter_tipe" value="<?= $filter_tipe ?>">
                    <input type="hidden" name="filter_min_nominal" id="filterMinNominal" value="<?= $filter_min_nominal ?>">
                    <input type="hidden" name="filter_max_nominal" id="filterMaxNominal" value="<?= $filter_max_nominal ?>">
                </form>
                
                <button type="button" onclick="toggleFilterForm()" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg px-4 py-2.5 font-semibold text-sm flex items-center gap-2 shadow-sm transition-all whitespace-nowrap">
                    <i class="fas fa-sliders-h"></i> Filters
                </button>
            </div>
        </div>
        
        <!-- Filter Detail Content (Hidden by default) -->
        <div id="filterDetail" class="hidden bg-white border border-slate-200 rounded-xl p-6 mb-8 shadow-sm">
            <form method="GET" id="filterForm" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Periode Tanggal</label>
                        <div class="flex gap-2">
                            <input type="date" name="tgl_mulai" value="<?= $tgl_mulai ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold outline-none focus:border-[#2d8a9d]">
                            <input type="date" name="tgl_selesai" value="<?= $tgl_selesai ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold outline-none focus:border-[#2d8a9d]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Urutkan</label>
                        <select name="sort_by" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold outline-none focus:border-[#2d8a9d]">
                            <option value="waktu_desc" <?= $sort_by == 'waktu_desc' ? 'selected' : '' ?>>Waktu: Terbaru</option>
                            <option value="waktu_asc" <?= $sort_by == 'waktu_asc' ? 'selected' : '' ?>>Waktu: Terlama</option>
                            <option value="input_terbaru" <?= $sort_by == 'input_terbaru' ? 'selected' : '' ?>>Input: Terbaru</option>
                            <option value="input_terlama" <?= $sort_by == 'input_terlama' ? 'selected' : '' ?>>Input: Terlama</option>
                            <option value="harga_tinggi" <?= $sort_by == 'harga_tinggi' ? 'selected' : '' ?>>Harga: Tertinggi</option>
                            <option value="harga_rendah" <?= $sort_by == 'harga_rendah' ? 'selected' : '' ?>>Harga: Terendah</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Range Nominal</label>
                        <div class="flex gap-2 items-center">
                            <input type="number" name="filter_min_nominal" value="<?= $filter_min_nominal ?>" placeholder="Min" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold outline-none focus:border-[#2d8a9d]">
                            <span class="text-slate-400">-</span>
                            <input type="number" name="filter_max_nominal" value="<?= $filter_max_nominal ?>" placeholder="Max" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-semibold outline-none focus:border-[#2d8a9d]">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="clearAllFilters()" class="px-5 py-2 text-slate-500 hover:bg-slate-100 rounded-lg font-semibold text-sm transition-all">Reset</button>
                    <button type="submit" class="px-6 py-2 bg-[#2d8a9d] hover:bg-[#1a5f6b] text-white rounded-lg font-semibold text-sm shadow-sm transition-all">Terapkan Filter</button>
                </div>
            </form>
        </div>
        
        <!-- Cards Grid (Bento Masonry Layout) -->
        <div class="columns-1 md:columns-2 xl:columns-3 gap-6 space-y-6 relative z-0">
            <?php if (!empty($jurnal_data_grouped)): ?>
                <?php foreach($jurnal_data_grouped as $no_reff => $group): ?>
                    <div class="break-inside-avoid bg-white border border-slate-200 shadow-sm overflow-hidden rounded-lg mb-6">
                        <!-- Header -->
                        <div class="bg-[#e6f7f9] px-4 py-3 flex justify-between items-center text-[#2d8a9d] border-b border-[#c8eef3]">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="flex-shrink-0">
                                    <div class="text-xl font-bold leading-none"><?= date('d/m/Y', strtotime($group['tanggal'])) ?></div>
                                    <div class="text-[10px] mt-1 text-[#5fb3c2] font-semibold opacity-90"><?= isset($group['created_at']) ? date('H:i:s', strtotime($group['created_at'])) : '00:00:00' ?></div>
                                </div>
                                <div class="flex flex-col gap-1 flex-shrink-0">
                                    <span class="bg-white px-2 py-0.5 rounded text-[10px] font-bold text-[#2d8a9d] border border-[#c8eef3] shadow-sm inline-flex items-center w-max uppercase"><?= highlightText($no_reff, $search_keyword) ?></span>
                                    <?php if ($group['is_otomatis']): ?>
                                        <span class="bg-[#2d8a9d] px-2 py-0.5 rounded text-[9px] font-bold text-white flex items-center gap-1 shadow-sm w-max uppercase">
                                            <i class="fas fa-shopping-cart"></i> OTOMATIS
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-[#3b82f6] px-2 py-0.5 rounded text-[9px] font-bold text-white flex items-center gap-1 shadow-sm w-max uppercase">
                                            <i class="fas fa-edit"></i> MANUAL
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Keterangan Umum -->
                                <div class="ml-3 flex-1 border-l border-[#c8eef3] pl-3 hidden sm:block min-w-0">
                                    <div class="text-[9px] text-[#5fb3c2] uppercase font-bold opacity-80 mb-0.5">Keterangan Umum</div>
                                    <div class="text-[11px] font-bold text-[#2d8a9d] line-clamp-2 leading-tight break-words" title="<?= htmlspecialchars($group['keterangan_umum'] ?: '-') ?>">
                                        <?= highlightText($group['keterangan_umum'] ?: '-', $search_keyword) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1 ml-2">
                                <button type="button" onclick="editTransaksiMulti('<?= $no_reff ?>')" class="text-[#2d8a9d] hover:text-[#1a5f6b] transition-colors p-1.5" title="Edit Transaksi">
                                    <i class="fas fa-edit text-lg"></i>
                                </button>
                                <?php if ($group['is_otomatis']): ?>
                                     <?php $id_pesanan = str_replace('ORD-', '', $no_reff); ?>
                                     <button type="button" onclick="lihatDetail(<?= $id_pesanan ?>)" class="text-[#2d8a9d] hover:text-green-600 transition-colors p-1.5" title="Lihat Detail Pesanan">
                                         <i class="fas fa-eye text-lg"></i>
                                     </button>
                                <?php else: ?>
                                    <button type="button" onclick="konfirmasiHapus('<?= $no_reff ?>', <?= $group['is_otomatis'] ? 'true' : 'false' ?>)" class="text-[#2d8a9d] hover:text-red-500 transition-colors p-1.5" title="Hapus Transaksi">
                                        <i class="fas fa-trash-alt text-lg"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Table -->
                        <div class="w-full overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse min-w-[550px] md:min-w-0">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/50">
                                        <th class="py-3 px-3 text-[10px] font-bold text-slate-800 uppercase tracking-wider">AKUN</th>
                                        <th class="py-3 px-3 text-[10px] font-bold text-slate-800 uppercase tracking-wider">KETERANGAN</th>
                                        <th class="py-3 px-3 text-[10px] font-bold text-slate-800 uppercase tracking-wider text-right">DEBIT</th>
                                        <th class="py-3 px-3 text-[10px] font-bold text-slate-800 uppercase tracking-wider text-right">KREDIT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($group['entries'] as $entry): ?>
                                    <tr class="border-b border-slate-100 last:border-0 <?= $entry['debit'] > 0 ? 'bg-[#f4fcf9]' : 'bg-[#fff5f5]' ?>">
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-[#2563eb] text-[12px] mb-0.5 break-words whitespace-normal"><?= highlightText($entry['kode_akun'], $search_keyword) ?></div>
                                            <div class="text-slate-500 text-[10px] leading-tight break-words whitespace-normal"><?= highlightText($entry['nama_akun'], $search_keyword) ?></div>
                                        </td>
                                        <td class="py-3 px-3 text-slate-600 text-[11px] leading-tight break-words whitespace-normal">
                                            <?= highlightText($entry['keterangan'] ?: '-', $search_keyword) ?>
                                        </td>
                                        <td class="py-3 px-3 text-right text-[11px] <?= $entry['debit'] > 0 ? 'text-[#0f9d58] font-bold' : 'text-slate-400' ?>">
                                            <span class="whitespace-nowrap"><?= $entry['debit'] > 0 ? 'Rp ' . number_format($entry['debit'], 0, ',', '.') : '-' ?></span>
                                        </td>
                                        <td class="py-3 px-3 text-right text-[11px] <?= $entry['kredit'] > 0 ? 'text-[#dc2626] font-bold' : 'text-slate-400' ?>">
                                            <span class="whitespace-nowrap"><?= $entry['kredit'] > 0 ? 'Rp ' . number_format($entry['kredit'], 0, ',', '.') : '-' ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-6 lg:p-8 items-center justify-center text-center bg-white border border-slate-200 rounded-2xl ">
                    <i class="fas fa-folder-open text-5xl text-slate-300 mb-4"></i>
                    <h4 class="text-xl font-bold text-slate-700 mb-2">Belum Ada Transaksi</h4>
                    <p class="text-slate-500 mb-6">Coba ubah filter atau tambahkan transaksi baru.</p>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <div id="editModalMulti" class="modal-overlay hidden">
        <div class="soft-card p-8 bg-white w-full max-w-4xl max-h-[90vh] overflow-y-auto relative" style="z-index: 50;">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-bold text-lg text-slate-700">Edit Transaksi Manual</h3>
                <button onclick="closeEditModalMulti()" class="text-slate-400 hover:text-red-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="editFormMulti" method="POST" action="" class="space-y-6">
                <input type="hidden" name="no_reff" id="edit_no_reff_multi">
                <input type="hidden" name="tgl_mulai" value="<?= $tgl_mulai ?>">
                <input type="hidden" name="tgl_selesai" value="<?= $tgl_selesai ?>">
                <input type="hidden" name="sort_by" value="<?= $sort_by ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_keyword) ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 mb-6">
                    <div class="md:col-span-3">
                        <label class="text-xs font-bold text-slate-500 uppercase mb-2 block">Tanggal</label>
                        <input type="date" name="tanggal" id="edit_tanggal_multi" class="input-modern w-full rounded-lg p-3 text-sm" required>
                    </div>
                    
                    <div class="md:col-span-9">
                        <label class="text-xs font-bold text-slate-500 uppercase mb-2 block">Keterangan Umum (Referensi)</label>
                        <input type="text" name="keterangan_umum" id="edit_keterangan_umum_multi" 
                               class="input-modern w-full rounded-lg p-3 text-sm" 
                               placeholder="Contoh: Pembelian barang, Bayar hutang, dll..." 
                               required>
                    </div>
                </div>

                <div class="card p-4 mb-4">
                    <div class="mb-4 flex justify-between items-center">
                        <div>
                            <h4 class="font-semibold text-gray-700">Detail Entri Jurnal</h4>
                            <p class="text-xs text-gray-500 mt-1">Edit akun-akun yang terlibat dalam transaksi</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="tambahBarisEdit('debit')" 
                                    class="btn-animate bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg transition-all text-sm font-medium flex items-center gap-2">
                                <i class="fas fa-plus"></i> Debit
                            </button>
                            <button type="button" onclick="tambahBarisEdit('kredit')" 
                                    class="btn-animate bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg transition-all text-sm font-medium flex items-center gap-2">
                                <i class="fas fa-plus"></i> Kredit
                            </button>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-4">
                        <div class="border border-gray-300 rounded-lg">
                            <div class="bg-green-600 text-white px-4 py-3 rounded-t-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-arrow-down text-white"></i>
                                        <h5 class="font-bold">DEBIT</h5>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs opacity-90">Total</div>
                                        <div class="font-bold" id="lblTotalDebitEdit">Rp 0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3 max-h-80 overflow-y-auto p-4 custom-scrollbar" id="containerDebitEdit">
                                </div>
                        </div>

                        <div class="border border-gray-300 rounded-lg">
                            <div class="bg-red-600 text-white px-4 py-3 rounded-t-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-arrow-up text-white"></i>
                                        <h5 class="font-bold">KREDIT</h5>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs opacity-90">Total</div>
                                        <div class="font-bold" id="lblTotalKreditEdit">Rp 0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3 max-h-80 overflow-y-auto p-4 custom-scrollbar" id="containerKreditEdit">
                                </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row justify-between items-start md:items-center pt-4 border-t border-gray-200 gap-4">
                    <div class="flex flex-col gap-2">
                        <div id="statusBalanceCompactEdit" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 balance-false">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>BELUM BALANCE!</span>
                            <span id="selisihAmountCompactEdit" class="text-xs">(Rp 0)</span>
                        </div>
                        
                        <div class="text-xs text-gray-500 flex items-center gap-1">
                            <i class="fas fa-lightbulb text-yellow-500"></i>
                            <span>Pastikan total debit dan kredit sama</span>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditModalMulti()" 
                                class="bg-slate-500 hover:bg-slate-600 text-white font-medium px-6 py-3 rounded-xl transition-all btn-animate">
                            Batal
                        </button>
                        <button type="submit" name="edit_transaksi_multi" id="btnSimpanEdit" 
                                class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-3 rounded-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed btn-animate"
                                disabled>
                            Update Transaksi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="detail-modal-overlay" class="modal-overlay hidden">
        <div class="bg-white p-6 rounded-xl shadow-lg w-full max-w-3xl h-5/6 flex flex-col">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-bold text-slate-700">Detail Transaksi Penjualan</h2>
                <button id="detail-modal-close" class="text-3xl text-slate-400 hover:text-red-500">&times;</button>
            </div>
            <iframe id="detail-modal-iframe" src="" class="w-full h-full border-0 rounded-lg bg-slate-50"></iframe>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // ===================== VARIABEL DAN TEMPLATE =====================
        // Template untuk baris baru debit
        const barisDebitTemplate = `
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                    <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun" required>
                        <option value="">-- Pilih Akun --</option>
                        <?php foreach($daftar_akun as $ak): ?>
                            <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="tipe_akun[]" value="debit">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                        <input type="text" name="nominal[]" 
                               class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input debit-input" 
                               placeholder="0" onkeyup="formatRupiahInput(this); hitungTotal();" required>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                        <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                onclick="hapusBaris(this)" title="Hapus Baris" data-confirm="true">
                            <i class="fas fa-times"></i>
                            <span class="text-xs">Hapus</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                    <input type="text" name="keterangan_detail[]" 
                           class="w-full rounded border border-gray-300 p-2 text-sm" 
                           placeholder="Keterangan spesifik untuk akun ini">
                </div>
            </div>
        </div>
        `;

        // Template untuk baris baru kredit
        const barisKreditTemplate = `
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                    <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun" required>
                        <option value="">-- Pilih Akun --</option>
                        <?php foreach($daftar_akun as $ak): ?>
                            <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="tipe_akun[]" value="kredit">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                        <input type="text" name="nominal[]" 
                               class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input kredit-input" 
                               placeholder="0" onkeyup="formatRupiahInput(this); hitungTotal();" required>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                        <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                onclick="hapusBaris(this)" title="Hapus Baris" data-confirm="true">
                            <i class="fas fa-times"></i>
                            <span class="text-xs">Hapus</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                    <input type="text" name="keterangan_detail[]" 
                           class="w-full rounded border border-gray-300 p-2 text-sm" 
                           placeholder="Keterangan spesifik untuk akun ini">
                </div>
            </div>
        </div>
        `;

        // Template untuk baris baru debit (EDIT)
        const barisDebitTemplateEdit = `
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                    <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun-edit" required>
                        <option value="">-- Pilih Akun --</option>
                        <?php foreach($daftar_akun as $ak): ?>
                            <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="tipe_akun[]" value="debit">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                        <input type="text" name="nominal[]" 
                               class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input debit-input-edit" 
                               placeholder="0" onkeyup="formatRupiahInput(this); hitungTotalEdit();" required>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                        <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                onclick="hapusBarisEdit(this)" title="Hapus Baris" data-confirm="true">
                            <i class="fas fa-times"></i>
                            <span class="text-xs">Hapus</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                    <input type="text" name="keterangan_detail[]" 
                           class="w-full rounded border border-gray-300 p-2 text-sm" 
                           placeholder="Keterangan spesifik untuk akun ini">
                </div>
            </div>
        </div>
        `;

        // Template untuk baris baru kredit (EDIT)
        const barisKreditTemplateEdit = `
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="space-y-3">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                    <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun-edit" required>
                        <option value="">-- Pilih Akun --</option>
                        <?php foreach($daftar_akun as $ak): ?>
                            <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="tipe_akun[]" value="kredit">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                        <input type="text" name="nominal[]" 
                               class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input kredit-input-edit" 
                               placeholder="0" onkeyup="formatRupiahInput(this); hitungTotalEdit();" required>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                        <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                onclick="hapusBarisEdit(this)" title="Hapus Baris" data-confirm="true">
                            <i class="fas fa-times"></i>
                            <span class="text-xs">Hapus</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                    <input type="text" name="keterangan_detail[]" 
                           class="w-full rounded border border-gray-300 p-2 text-sm" 
                           placeholder="Keterangan spesifik untuk akun ini">
                </div>
            </div>
        </div>
        `;

        // ===================== INISIALISASI =====================
        $(document).ready(function() {
            // Inisialisasi Select2 untuk form multi-akun
            $('.select2-akun').select2({
                placeholder: "Pilih Akun",
                width: '100%',
                dropdownParent: $('#formInput')
            });
            
            // Hitung total awal
            hitungTotal();
            
            // Event listener untuk form submission
            $('#filterForm').on('submit', function(e) {
                // Validation untuk tanggal
                const tglMulai = $('input[name="tgl_mulai"]').val();
                const tglSelesai = $('input[name="tgl_selesai"]').val();
                
                if (tglMulai && tglSelesai) {
                    if (new Date(tglMulai) > new Date(tglSelesai)) {
                        e.preventDefault();
                        Swal.fire('Error', 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai', 'error');
                    }
                }
            });
            
            // Client-side Search (Tanpa Refresh)
            $('#searchInput').on('input', function() {
                const keyword = $(this).val().toLowerCase();
                const cards = $('.break-inside-avoid');
                let foundCount = 0;

                cards.each(function() {
                    // Cari teks di dalam seluruh isi kartu
                    const cardText = $(this).text().toLowerCase();
                    if (cardText.includes(keyword)) {
                        $(this).removeClass('hidden').show();
                        foundCount++;
                    } else {
                        $(this).addClass('hidden').hide();
                    }
                });

                $('#totalGroupsCount').text(foundCount);
            });

            // Mencegah refresh halaman saat tekan Enter di search
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                $('#searchInput').trigger('input');
            });

            // Jalankan pencarian jika ada keyword awal dari URL
            if ($('#searchInput').val()) {
                $('#searchInput').trigger('input');
            }
        });

        // ===================== FUNGSI UTAMA =====================
        function toggleForm() {
            const form = document.getElementById('formInput');
            form.classList.toggle('hidden');
            
            if (!form.classList.contains('hidden')) {
                form.scrollIntoView({ behavior: 'smooth' });
                // Reinitialize select2 when form is shown
                $('.select2-akun').select2({
                    placeholder: "Pilih Akun",
                    width: '100%',
                    dropdownParent: $('#formInput')
                });
                
                // Focus on keterangan umum input
                setTimeout(() => {
                    document.getElementById('keterangan_umum').focus();
                }, 100);
            }
        }

        function formatRupiahInput(input) {
            let value = input.value.replace(/\D/g, '');
            input.value = new Intl.NumberFormat('id-ID').format(value);
            
            // Tentukan fungsi hitungTotal mana yang akan dipanggil
            if (input.classList.contains('debit-input') || input.classList.contains('kredit-input')) {
                hitungTotal();
            } else if (input.classList.contains('debit-input-edit') || input.classList.contains('kredit-input-edit')) {
                hitungTotalEdit();
            }
        }

        // Format Rupiah untuk display
        function formatRupiah(angka) {
            if (!angka) return 'Rp 0';
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(angka);
        }

        // FUNGSI tambahBaris
        function tambahBaris(tipe) {
            let container;
            let template;
            
            if (tipe === 'debit') {
                container = document.getElementById('containerDebit');
                template = barisDebitTemplate;
            } else if (tipe === 'kredit') {
                container = document.getElementById('containerKredit');
                template = barisKreditTemplate;
            } else {
                return;
            }
            
            const newRow = document.createElement('div');
            newRow.innerHTML = template;
            container.appendChild(newRow);
            
            // Inisialisasi Select2 untuk baris baru
            $(newRow).find('.select2-akun').select2({
                placeholder: "Pilih Akun",
                width: '100%',
                dropdownParent: $('#formInput')
            });
            
            // Focus on nominal input
            setTimeout(() => {
                const nominalInput = newRow.querySelector('.nominal-input');
                if (nominalInput) {
                    nominalInput.focus();
                }
            }, 50);
            
            hitungTotal();
        }

        function hapusBaris(button) {
            const baris = button.closest('.bg-white');
            const containerDebit = document.getElementById('containerDebit');
            const containerKredit = document.getElementById('containerKredit');
            
            const totalDebitRows = containerDebit.querySelectorAll('.bg-white').length;
            const totalKreditRows = containerKredit.querySelectorAll('.bg-white').length;
            
            if (totalDebitRows + totalKreditRows <= 2) {
                Swal.fire('Peringatan', 'Minimal harus ada 1 baris debit dan 1 baris kredit!', 'warning');
                return;
            }
            
            // Hapus Select2 sebelum menghapus elemen
            const select = $(baris).find('.select2-akun');
            if (select.length && select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }
            
            baris.remove();
            hitungTotal();
        }

        function hitungTotal() {
            let totalDebit = 0;
            let totalKredit = 0;
            
            document.querySelectorAll('.debit-input').forEach(input => {
                const nominalValue = parseFloat(input.value.replace(/\./g, '')) || 0;
                totalDebit += nominalValue;
            });
            
            document.querySelectorAll('.kredit-input').forEach(input => {
                const nominalValue = parseFloat(input.value.replace(/\./g, '')) || 0;
                totalKredit += nominalValue;
            });
            
            document.getElementById('lblTotalDebit').textContent = formatRupiah(totalDebit);
            document.getElementById('lblTotalKredit').textContent = formatRupiah(totalKredit);
            
            const selisih = Math.abs(totalDebit - totalKredit);
            const statusBalanceCompact = document.getElementById('statusBalanceCompact');
            const selisihAmountCompact = document.getElementById('selisihAmountCompact');
            const btnSimpan = document.getElementById('btnSimpan');
            
            if (selisih <= 100) {
                statusBalanceCompact.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <span>BALANCE!</span>
                    <span class="text-xs">(Rp ${formatRupiah(selisih)})</span>
                `;
                statusBalanceCompact.className = 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 balance-true';
                btnSimpan.disabled = false;
                btnSimpan.classList.remove('bg-blue-600');
                btnSimpan.classList.add('bg-green-600', 'hover:bg-green-700');
            } else {
                statusBalanceCompact.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>BELUM BALANCE!</span>
                    <span class="text-xs">(Rp ${formatRupiah(selisih)})</span>
                `;
                statusBalanceCompact.className = 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 balance-false';
                btnSimpan.disabled = true;
                btnSimpan.classList.remove('bg-green-600', 'hover:bg-green-700');
                btnSimpan.classList.add('bg-blue-600');
            }
        }

        function validasiSebelumSimpan() {
            let isValid = true;
            let errorMessage = '';
            
            document.querySelectorAll('.input-error').forEach(el => {
                el.classList.remove('input-error');
            });
            
            // Validasi keterangan umum
            const keteranganUmum = document.getElementById('keterangan_umum').value.trim();
            if (!keteranganUmum) {
                Swal.fire('Error', 'Keterangan umum transaksi harus diisi!', 'error');
                return false;
            }
            
            // Validasi minimal 1 debit dan 1 kredit
            const debitRows = document.querySelectorAll('#containerDebit .bg-white');
            const kreditRows = document.querySelectorAll('#containerKredit .bg-white');
            
            if (debitRows.length === 0 || kreditRows.length === 0) {
                Swal.fire('Error', 'Minimal harus ada 1 baris debit dan 1 baris kredit!', 'error');
                return false;
            }
            
            // Validasi setiap baris
            let hasError = false;
            
            debitRows.forEach((baris, index) => {
                const nominalInput = baris.querySelector('.debit-input');
                const akunSelect = baris.querySelector('select[name="akun_id[]"]');
                const keteranganInput = baris.querySelector('input[name="keterangan_detail[]"]');
                
                const nominal = nominalInput.value.replace(/\./g, '');
                const akunValue = akunSelect.value;
                
                if (!akunValue) {
                    hasError = true;
                    errorMessage = `Baris debit ${index + 1}: Akun harus dipilih`;
                    akunSelect.classList.add('input-error');
                }
                
                if (!nominal || parseInt(nominal) <= 0) {
                    hasError = true;
                    errorMessage = `Baris debit ${index + 1}: Nominal harus lebih dari 0`;
                    nominalInput.classList.add('input-error');
                }
                
                // Jika keterangan spesifik kosong, isi dengan keterangan umum
                if (!keteranganInput.value.trim()) {
                    keteranganInput.value = keteranganUmum;
                }
            });
            
            kreditRows.forEach((baris, index) => {
                const nominalInput = baris.querySelector('.kredit-input');
                const akunSelect = baris.querySelector('select[name="akun_id[]"]');
                const keteranganInput = baris.querySelector('input[name="keterangan_detail[]"]');
                
                const nominal = nominalInput.value.replace(/\./g, '');
                const akunValue = akunSelect.value;
                
                if (!akunValue) {
                    hasError = true;
                    errorMessage = `Baris kredit ${index + 1}: Akun harus dipilih`;
                    akunSelect.classList.add('input-error');
                }
                
                if (!nominal || parseInt(nominal) <= 0) {
                    hasError = true;
                    errorMessage = `Baris kredit ${index + 1}: Nominal harus lebih dari 0`;
                    nominalInput.classList.add('input-error');
                }
                
                // Jika keterangan spesifik kosong, isi dengan keterangan umum
                if (!keteranganInput.value.trim()) {
                    keteranganInput.value = keteranganUmum;
                }
            });
            
            if (hasError) {
                Swal.fire('Error', errorMessage, 'error');
                return false;
            }
            
            // Validasi balance
            let totalDebit = 0;
            let totalKredit = 0;
            
            document.querySelectorAll('.debit-input').forEach(input => {
                totalDebit += parseFloat(input.value.replace(/\./g, '')) || 0;
            });
            
            document.querySelectorAll('.kredit-input').forEach(input => {
                totalKredit += parseFloat(input.value.replace(/\./g, '')) || 0;
            });
            
            if (Math.abs(totalDebit - totalKredit) > 100) {
                Swal.fire({
                    icon: 'error',
                    title: 'Tidak Balance!',
                    html: `Total debit: ${formatRupiah(totalDebit)}<br>Total kredit: ${formatRupiah(totalKredit)}<br>Selisih: ${formatRupiah(Math.abs(totalDebit - totalKredit))}`
                });
                return false;
            }
            
            return true;
        }

        function resetForm() {
            Swal.fire({
                title: 'Reset Form?',
                text: 'Semua data yang belum disimpan akan hilang.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Reset!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formJurnalMulti').reset();
                    
                    // Hapus semua Select2 terlebih dahulu
                    $('#containerDebit .select2-akun, #containerKredit .select2-akun').each(function() {
                        if ($(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2('destroy');
                        }
                    });
                    
                    // Reset container debit (hanya 1 baris)
                    document.getElementById('containerDebit').innerHTML = `
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                                    <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun" required>
                                        <option value="">-- Pilih Akun --</option>
                                        <?php foreach($daftar_akun as $ak): ?>
                                            <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="tipe_akun[]" value="debit">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                                        <input type="text" name="nominal[]" 
                                               class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input debit-input" 
                                               placeholder="0" onkeyup="formatRupiahInput(this); hitungTotal();" required>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                                        <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                                onclick="hapusBaris(this)" title="Hapus Baris" data-confirm="true">
                                            <i class="fas fa-times"></i>
                                            <span class="text-xs">Hapus</span>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                                    <input type="text" name="keterangan_detail[]" 
                                           class="w-full rounded border border-gray-300 p-2 text-sm" 
                                           placeholder="Keterangan spesifik untuk akun ini">
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Reset container kredit (hanya 1 baris)
                    document.getElementById('containerKredit').innerHTML = `
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Akun COA</label>
                                    <select name="akun_id[]" class="w-full rounded border border-gray-300 p-2 text-sm select2-akun" required>
                                        <option value="">-- Pilih Akun --</option>
                                        <?php foreach($daftar_akun as $ak): ?>
                                            <option value="<?= $ak['kode_akun'] ?>"><?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="tipe_akun[]" value="kredit">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 mb-1 block">Nominal</label>
                                        <input type="text" name="nominal[]" 
                                               class="w-full text-right font-mono rounded border border-gray-300 p-2 text-sm nominal-input kredit-input" 
                                               placeholder="0" onkeyup="formatRupiahInput(this); hitungTotal();" required>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 mb-1 block">Aksi</label>
                                        <button type="button" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 rounded p-2 transition-all flex items-center justify-center gap-1 text-sm" 
                                                onclick="hapusBaris(this)" title="Hapus Baris" data-confirm="true">
                                            <i class="fas fa-times"></i>
                                            <span class="text-xs">Hapus</span>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-medium text-gray-600 mb-1 block">Keterangan Spesifik (opsional)</label>
                                    <input type="text" name="keterangan_detail[]" 
                                           class="w-full rounded border border-gray-300 p-2 text-sm" 
                                           placeholder="Keterangan spesifik untuk akun ini">
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Inisialisasi ulang Select2
                    $('.select2-akun').select2({
                        placeholder: "Pilih Akun",
                        width: '100%',
                        dropdownParent: $('#formInput')
                    });
                    
                    hitungTotal();
                    
                    // Focus on keterangan umum
                    setTimeout(() => {
                        document.getElementById('keterangan_umum').focus();
                    }, 100);
                    
                    Swal.fire('Berhasil!', 'Form telah direset.', 'success');
                }
            });
        }

        // ===================== FUNGSI EDIT =====================
        function editTransaksiMulti(no_reff) {
            console.log('Mencoba edit transaksi dengan REF:', no_reff);
            
            showLoading();
            
            fetch(`get_transaksi_by_ref.php?no_reff=${no_reff}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading();
                    
                    if (data.success) {
                        // Isi data umum
                        document.getElementById('edit_no_reff_multi').value = data.data.no_reff;
                        document.getElementById('edit_tanggal_multi').value = data.data.tanggal;
                        
                        // Isi keterangan umum
                        if (data.data.keterangan_umum) {
                            document.getElementById('edit_keterangan_umum_multi').value = data.data.keterangan_umum;
                        } else {
                            // Fallback
                            if (data.data.entries_debit && data.data.entries_debit.length > 0) {
                                document.getElementById('edit_keterangan_umum_multi').value = data.data.entries_debit[0].keterangan;
                            } else if (data.data.entries_kredit && data.data.entries_kredit.length > 0) {
                                document.getElementById('edit_keterangan_umum_multi').value = data.data.entries_kredit[0].keterangan;
                            } else {
                                document.getElementById('edit_keterangan_umum_multi').value = '';
                            }
                        }
                        
                        // Kosongkan container
                        document.getElementById('containerDebitEdit').innerHTML = '';
                        document.getElementById('containerKreditEdit').innerHTML = '';
                        
                        // Isi entri debit
                        if (data.data.entries_debit && data.data.entries_debit.length > 0) {
                            data.data.entries_debit.forEach(entry => {
                                const container = document.getElementById('containerDebitEdit');
                                const newRow = document.createElement('div');
                                newRow.innerHTML = barisDebitTemplateEdit;
                                container.appendChild(newRow);
                                
                                const select = newRow.querySelector('select');
                                const nominalInput = newRow.querySelector('.debit-input-edit');
                                const keteranganInput = newRow.querySelector('input[name="keterangan_detail[]"]');
                                
                                // Set values
                                select.value = entry.kode_akun;
                                nominalInput.value = new Intl.NumberFormat('id-ID').format(entry.debit);
                                
                                // Set keterangan spesifik
                                if (entry.keterangan === data.data.keterangan_umum) {
                                    keteranganInput.value = '';
                                } else {
                                    keteranganInput.value = entry.keterangan;
                                }
                                
                                // Inisialisasi Select2
                                $(select).select2({
                                    placeholder: "Pilih Akun",
                                    width: '100%',
                                    dropdownParent: $('#editModalMulti')
                                });
                            });
                        } else {
                            // Tambahkan baris debit default jika tidak ada
                            const container = document.getElementById('containerDebitEdit');
                            const newRow = document.createElement('div');
                            newRow.innerHTML = barisDebitTemplateEdit;
                            container.appendChild(newRow);
                            $(newRow.querySelector('select')).select2({
                                placeholder: "Pilih Akun",
                                width: '100%',
                                dropdownParent: $('#editModalMulti')
                            });
                        }
                        
                        // Isi entri kredit
                        if (data.data.entries_kredit && data.data.entries_kredit.length > 0) {
                            data.data.entries_kredit.forEach(entry => {
                                const container = document.getElementById('containerKreditEdit');
                                const newRow = document.createElement('div');
                                newRow.innerHTML = barisKreditTemplateEdit;
                                container.appendChild(newRow);
                                
                                const select = newRow.querySelector('select');
                                const nominalInput = newRow.querySelector('.kredit-input-edit');
                                const keteranganInput = newRow.querySelector('input[name="keterangan_detail[]"]');
                                
                                // Set values
                                select.value = entry.kode_akun;
                                nominalInput.value = new Intl.NumberFormat('id-ID').format(entry.kredit);
                                
                                // Set keterangan spesifik
                                if (entry.keterangan === data.data.keterangan_umum) {
                                    keteranganInput.value = '';
                                } else {
                                    keteranganInput.value = entry.keterangan;
                                }
                                
                                // Inisialisasi Select2
                                $(select).select2({
                                    placeholder: "Pilih Akun",
                                    width: '100%',
                                    dropdownParent: $('#editModalMulti')
                                });
                            });
                        } else {
                            // Tambahkan baris kredit default jika tidak ada
                            const container = document.getElementById('containerKreditEdit');
                            const newRow = document.createElement('div');
                            newRow.innerHTML = barisKreditTemplateEdit;
                            container.appendChild(newRow);
                            $(newRow.querySelector('select')).select2({
                                placeholder: "Pilih Akun",
                                width: '100%',
                                dropdownParent: $('#editModalMulti')
                            });
                        }
                        
                        // Hitung total
                        hitungTotalEdit();
                        
                        // Tampilkan modal
                        const modal = document.getElementById('editModalMulti');
                        modal.classList.remove('hidden');
                    } else {
                        Swal.fire('Error', data.message || 'Gagal memuat data transaksi', 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    Swal.fire('Error', 'Terjadi kesalahan saat memuat data. Periksa koneksi internet Anda.', 'error');
                });
        }

        function closeEditModalMulti() {
            const modal = document.getElementById('editModalMulti');
            modal.classList.add('hidden');
            // Hancurkan semua Select2 di modal edit
            $('#editModalMulti .select2-akun-edit').each(function() {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });
        }

        function tambahBarisEdit(tipe) {
            let container;
            let template;
            
            if (tipe === 'debit') {
                container = document.getElementById('containerDebitEdit');
                template = barisDebitTemplateEdit;
            } else if (tipe === 'kredit') {
                container = document.getElementById('containerKreditEdit');
                template = barisKreditTemplateEdit;
            } else {
                return;
            }
            
            const newRow = document.createElement('div');
            newRow.innerHTML = template;
            container.appendChild(newRow);
            
            // Inisialisasi Select2
            $(newRow.querySelector('select')).select2({
                placeholder: "Pilih Akun",
                width: '100%',
                dropdownParent: $('#editModalMulti')
            });
            
            // Focus on nominal input
            setTimeout(() => {
                const nominalInput = newRow.querySelector('.nominal-input');
                if (nominalInput) {
                    nominalInput.focus();
                }
            }, 50);
            
            hitungTotalEdit();
        }

        function hapusBarisEdit(button) {
            const baris = button.closest('.bg-white');
            const containerDebit = document.getElementById('containerDebitEdit');
            const containerKredit = document.getElementById('containerKreditEdit');
            
            const totalDebitRows = containerDebit.querySelectorAll('.bg-white').length;
            const totalKreditRows = containerKredit.querySelectorAll('.bg-white').length;
            
            if (totalDebitRows + totalKreditRows <= 2) {
                Swal.fire('Peringatan', 'Minimal harus ada 1 baris debit dan 1 baris kredit!', 'warning');
                return;
            }
            
            // Hapus Select2 sebelum menghapus elemen
            const select = $(baris).find('.select2-akun-edit');
            if (select.length && select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }
            
            baris.remove();
            hitungTotalEdit();
        }

        function hitungTotalEdit() {
            let totalDebit = 0;
            let totalKredit = 0;
            
            document.querySelectorAll('.debit-input-edit').forEach(input => {
                const nominalValue = parseFloat(input.value.replace(/\./g, '')) || 0;
                totalDebit += nominalValue;
            });
            
            document.querySelectorAll('.kredit-input-edit').forEach(input => {
                const nominalValue = parseFloat(input.value.replace(/\./g, '')) || 0;
                totalKredit += nominalValue;
            });
            
            document.getElementById('lblTotalDebitEdit').textContent = formatRupiah(totalDebit);
            document.getElementById('lblTotalKreditEdit').textContent = formatRupiah(totalKredit);
            
            const selisih = Math.abs(totalDebit - totalKredit);
            const statusBalanceCompact = document.getElementById('statusBalanceCompactEdit');
            const selisihAmountCompact = document.getElementById('selisihAmountCompactEdit');
            const btnSimpan = document.getElementById('btnSimpanEdit');
            
            if (selisih <= 100) {
                statusBalanceCompact.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <span>BALANCE!</span>
                    <span class="text-xs">(Rp ${formatRupiah(selisih)})</span>
                `;
                statusBalanceCompact.className = 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 balance-true';
                btnSimpan.disabled = false;
            } else {
                statusBalanceCompact.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>BELUM BALANCE!</span>
                    <span class="text-xs">(Rp ${formatRupiah(selisih)})</span>
                `;
                statusBalanceCompact.className = 'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-300 balance-false';
                btnSimpan.disabled = true;
            }
        }

        // ===================== FUNGSI FILTER DAN SEARCH =====================
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            window.location.href = 'jurnal_umum.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&sort_by=<?= $sort_by ?>';
        }

        function toggleFilterForm() {
            const filterDetail = document.getElementById('filterDetail');
            const toggleBtn = document.getElementById('toggleFilterBtn');
            
            filterDetail.classList.toggle('hidden');
            
            if (filterDetail.classList.contains('hidden')) {
                toggleBtn.innerHTML = '<i class="fas fa-sliders-h"></i><span class="hidden sm:inline">Filter Lanjutan</span>';
                toggleBtn.classList.remove('bg-blue-50', 'border-blue-200', 'text-blue-700');
                toggleBtn.classList.add('bg-white', 'border-slate-200', 'text-slate-700');
            } else {
                toggleBtn.innerHTML = '<i class="fas fa-times"></i><span class="hidden sm:inline">Tutup Filter</span>';
                toggleBtn.classList.remove('bg-white', 'border-slate-200', 'text-slate-700');
                toggleBtn.classList.add('bg-blue-50', 'border-blue-200', 'text-blue-700');
            }
        }

        function clearSingleFilter(fieldName) {
            const field = document.getElementById(fieldName);
            
            // Reset nilai berdasarkan tipe field
            switch(fieldName) {
                case 'tgl_mulai':
                    field.value = '<?= date('Y-m-01') ?>'; // Tanggal awal bulan
                    break;
                case 'tgl_selesai':
                    field.value = '<?= date('Y-m-d') ?>'; // Hari ini
                    break;
                case 'sort_by':
                    field.value = 'waktu_desc'; // Default sorting
                    break;
                case 'filter_tipe':
                    field.value = ''; // Semua tipe
                    break;
                case 'filter_min_nominal':
                case 'filter_max_nominal':
                    field.value = ''; // Kosongkan nominal
                    break;
            }
            
            // Submit form secara otomatis setelah reset
            setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 300);
        }

        function clearAllFilters() {
            window.location.href = 'jurnal_umum.php';
        }

        function removeFilter(filterType) {
            switch(filterType) {
                case 'search':
                    document.getElementById('searchInput').value = '';
                    break;
                case 'filter_tipe':
                    document.querySelector('#filterForm input[name="filter_tipe"]').value = '';
                    break;
                case 'filter_min_nominal':
                    document.querySelector('#filterForm input[name="filter_min_nominal"]').value = '';
                    break;
                case 'filter_max_nominal':
                    document.querySelector('#filterForm input[name="filter_max_nominal"]').value = '';
                    break;
            }
            
            // Submit the filter form
            document.getElementById('filterForm').submit();
        }

        function konfirmasiHapus(reff, is_otomatis) {
            let warningText = "Hapus transaksi ref: " + reff + "?";
            let confirmText = "Ya, Hapus!";
            
            if(is_otomatis) {
                warningText = "PERINGATAN: Ini adalah transaksi penjualan otomatis!<br><br>Menghapus jurnal ini TIDAK membatalkan pesanan di laporan penjualan. Tetap hapus?";
                confirmText = "Ya, Tetap Hapus";
            }

            Swal.fire({
                title: 'Hapus?', 
                html: warningText, 
                icon: 'warning',
                showCancelButton: true, 
                confirmButtonColor: '#EF4444', 
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?hapus_reff=' + reff + '&tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&sort_by=<?= $sort_by ?>&search=<?= urlencode($search_keyword) ?>';
                }
            });
        }

        // Fungsi bantu loading
        function showLoading() {
            const loading = document.createElement('div');
            loading.id = 'loading-overlay';
            loading.innerHTML = `
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-10000">
                    <div class="bg-white p-6 rounded-xl shadow-lg">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                        <p class="mt-3 text-sm font-medium text-gray-700">Memuat data...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(loading);
        }

        function hideLoading() {
            const loading = document.getElementById('loading-overlay');
            if (loading) {
                loading.remove();
            }
        }

        // --- LOGIC LIHAT DETAIL ---
        function lihatDetail(id_pesanan) {
            const modal = document.getElementById('detail-modal-overlay');
            const iframe = document.getElementById('detail-modal-iframe');
            
            iframe.src = 'detailPesanan.php?id=' + id_pesanan;
            modal.classList.remove('hidden');
        }

        // Tutup Modal
        document.getElementById('detail-modal-close').onclick = function() {
            const modal = document.getElementById('detail-modal-overlay');
            const iframe = document.getElementById('detail-modal-iframe');
            
            modal.classList.add('hidden'); 
            iframe.src = '';
        };

        // Close edit modal when clicking outside
        document.getElementById('editModalMulti').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModalMulti();
            }
        });

        // Close detail modal when clicking outside
        document.getElementById('detail-modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                document.getElementById('detail-modal-close').click();
            }
        });
    </script>
</body>
</html>
