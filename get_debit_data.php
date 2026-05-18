<?php
session_start();
include 'koneksi.php';

header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validasi ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// Query untuk mengambil data debit transaksi manual
$query = "SELECT j.*, a.nama_akun 
          FROM jurnal_umum j 
          JOIN akun_coa a ON j.kode_akun = a.kode_akun 
          WHERE j.id = '$id' AND j.no_reff LIKE 'MAN-%' AND j.debit > 0";

$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($koneksi)]);
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Data debit transaksi manual tidak ditemukan']);
    exit;
}

$data = mysqli_fetch_assoc($result);

// Format response
$response = [
    'success' => true,
    'data' => [
        'id' => $data['id'],
        'tanggal' => date('Y-m-d', strtotime($data['tanggal'])),
        'nominal' => $data['debit'],
        'keterangan' => $data['keterangan'],
        'akun_debit' => $data['kode_akun']
    ]
];

echo json_encode($response);
?>
