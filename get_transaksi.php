<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'owner', 'akuntan'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    // Ambil data transaksi debit (entry pertama)
    $query = "SELECT j1.*, j2.kode_akun as akun_kredit 
              FROM jurnal_umum j1 
              LEFT JOIN jurnal_umum j2 ON j1.no_reff = j2.no_reff AND j2.kredit > 0 
              WHERE j1.id = '$id' AND j1.debit > 0 AND j1.no_reff LIKE 'MAN-%'";
    $result = mysqli_query($koneksi, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $transaksi = mysqli_fetch_assoc($result);
        
        $data = [
            'id' => $transaksi['id'],
            'tanggal' => $transaksi['tanggal'],
            'nominal' => $transaksi['debit'],
            'keterangan' => $transaksi['keterangan'],
            'akun_debit' => $transaksi['kode_akun'],
            'akun_kredit' => $transaksi['akun_kredit']
        ];
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data transaksi tidak ditemukan atau tidak dapat diedit']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID transaksi tidak valid']);
}
?>
