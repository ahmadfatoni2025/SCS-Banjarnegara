<?php
// search_jurnal.php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get search parameters
$search = isset($_POST['search']) ? mysqli_real_escape_string($koneksi, $_POST['search']) : '';
$tgl_mulai = isset($_POST['tgl_mulai']) ? mysqli_real_escape_string($koneksi, $_POST['tgl_mulai']) : date('Y-m-01');
$tgl_selesai = isset($_POST['tgl_selesai']) ? mysqli_real_escape_string($koneksi, $_POST['tgl_selesai']) : date('Y-m-d');
$filter_debit = isset($_POST['filter_debit']) ? intval($_POST['filter_debit']) : 1;
$filter_kredit = isset($_POST['filter_kredit']) ? intval($_POST['filter_kredit']) : 1;
$filter_manual = isset($_POST['filter_manual']) ? intval($_POST['filter_manual']) : 1;
$filter_otomatis = isset($_POST['filter_otomatis']) ? intval($_POST['filter_otomatis']) : 1;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Build query conditions
    $where_conditions = ["j.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'"];
    
    // Add search conditions if search term exists
    if (!empty($search)) {
        // Check if search is numeric (nominal)
        if (is_numeric($search)) {
            $numeric_search = floatval($search);
            $search_conditions = [
                "j.no_reff LIKE '%$search%'",
                "j.keterangan LIKE '%$search%'",
                "a.nama_akun LIKE '%$search%'",
                "j.kode_akun LIKE '%$search%'",
                "j.debit = '$numeric_search'",
                "j.kredit = '$numeric_search'"
            ];
        } else {
            $search_conditions = [
                "j.no_reff LIKE '%$search%'",
                "j.keterangan LIKE '%$search%'",
                "a.nama_akun LIKE '%$search%'",
                "j.kode_akun LIKE '%$search%'"
            ];
        }
        $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Query to get distinct transactions with details
    $query = "SELECT DISTINCT 
                j.no_reff,
                j.tanggal,
                j.keterangan,
                (SELECT SUM(debit) FROM jurnal_umum WHERE no_reff = j.no_reff) as total_debit,
                (SELECT SUM(kredit) FROM jurnal_umum WHERE no_reff = j.no_reff) as total_kredit,
                (SELECT COUNT(*) FROM jurnal_umum WHERE no_reff = j.no_reff AND debit > 0) as has_debit,
                (SELECT COUNT(*) FROM jurnal_umum WHERE no_reff = j.no_reff AND kredit > 0) as has_kredit
              FROM jurnal_umum j 
              JOIN akun_coa a ON j.kode_akun = a.kode_akun 
              $where_clause
              ORDER BY j.tanggal DESC, j.no_reff DESC";

    $result = mysqli_query($koneksi, $query);
    
    if (!$result) {
        throw new Exception("Query failed: " . mysqli_error($koneksi));
    }
    
    $results = [];
    $stats = [
        'manual' => 0,
        'otomatis' => 0,
        'total_debit' => 0,
        'total_kredit' => 0
    ];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Check if transaction is automatic (ORD-)
        $is_otomatis = (strpos($row['no_reff'], 'ORD-') === 0);
        
        // Apply manual/automatic filter
        if (($is_otomatis && $filter_otomatis == 0) || (!$is_otomatis && $filter_manual == 0)) {
            continue;
        }
        
        // Apply debit/kredit filter
        if (($filter_debit == 0 && $row['has_debit'] > 0 && $row['has_kredit'] == 0) ||
            ($filter_kredit == 0 && $row['has_kredit'] > 0 && $row['has_debit'] == 0)) {
            continue;
        }
        
        // Add to results
        $results[] = [
            'no_reff' => $row['no_reff'],
            'tanggal' => $row['tanggal'],
            'keterangan' => $row['keterangan'],
            'total_debit' => floatval($row['total_debit']),
            'total_kredit' => floatval($row['total_kredit']),
            'is_otomatis' => $is_otomatis
        ];
        
        // Update stats
        if ($is_otomatis) {
            $stats['otomatis']++;
        } else {
            $stats['manual']++;
        }
        $stats['total_debit'] += floatval($row['total_debit']);
        $stats['total_kredit'] += floatval($row['total_kredit']);
    }
    
    // Get total without filters for comparison
    $total_query = "SELECT COUNT(DISTINCT no_reff) as total FROM jurnal_umum 
                    WHERE tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
    $total_result = mysqli_query($koneksi, $total_query);
    $total_data = $total_result ? mysqli_fetch_assoc($total_result) : ['total' => 0];
    
    echo json_encode([
        'success' => true,
        'total_found' => count($results),
        'total_all' => $total_data['total'] ?? 0,
        'results' => $results,
        'stats' => $stats,
        'search_term' => $search
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Search error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
