<?php
session_start();
include 'koneksi.php';

// Cek login
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

// Ambil no_reff dari parameter GET
$no_reff = isset($_GET['no_reff']) ? mysqli_real_escape_string($koneksi, $_GET['no_reff']) : '';

if (empty($no_reff)) {
    die(json_encode(['success' => false, 'message' => 'No reference provided']));
}

// Ambil data transaksi berdasarkan no_reff
$query = "SELECT * FROM jurnal_umum WHERE no_reff = '$no_reff' ORDER BY debit DESC, id ASC";
$result = mysqli_query($koneksi, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die(json_encode(['success' => false, 'message' => 'Data tidak ditemukan']));
}

$entries = [];
$tanggal = '';
$keterangan_umum = '';

// Ambil semua entri untuk transaksi ini
while ($row = mysqli_fetch_assoc($result)) {
    $entries[] = $row;
    
    // Set tanggal dari entri pertama
    if (empty($tanggal)) {
        $tanggal = $row['tanggal'];
    }
    
    // PERBAIKAN: Ambil keterangan_umum dari entri pertama
    if (empty($keterangan_umum) && !empty($row['keterangan_umum'])) {
        $keterangan_umum = $row['keterangan_umum'];
    }
}

// Jika keterangan_umum masih kosong, gunakan keterangan dari entri pertama
if (empty($keterangan_umum) && !empty($entries[0]['keterangan'])) {
    $keterangan_umum = $entries[0]['keterangan'];
}

// Pisahkan entri debit dan kredit
$entries_debit = [];
$entries_kredit = [];

foreach ($entries as $entry) {
    if ($entry['debit'] > 0) {
        $entries_debit[] = $entry;
    } else if ($entry['kredit'] > 0) {
        $entries_kredit[] = $entry;
    }
}

// Return data dalam format JSON
echo json_encode([
    'success' => true,
    'data' => [
        'no_reff' => $no_reff,
        'tanggal' => $tanggal,
        'keterangan_umum' => $keterangan_umum, // Keterangan Umum dikembalikan
        'entries_debit' => $entries_debit,
        'entries_kredit' => $entries_kredit
    ]
]);

mysqli_close($koneksi);
?>
