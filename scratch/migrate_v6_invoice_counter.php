<?php
include 'koneksi.php';

// Tambah invoice_counter ke pengaturan
$res = $koneksi->query("SELECT * FROM pengaturan WHERE kunci = 'invoice_counter'");
if ($res->num_rows == 0) {
    // Cari nomor id_pesanan terbesar sebagai starting point
    $q = $koneksi->query("SELECT MAX(id_pesanan) as max_id FROM pesanan");
    $max = $q->fetch_assoc()['max_id'] ?? 0;
    
    $koneksi->query("INSERT INTO pengaturan (kunci, nilai) VALUES ('invoice_counter', '$max')");
    echo "Added 'invoice_counter' with value: $max\n";
} else {
    $r = $res->fetch_assoc();
    echo "'invoice_counter' already exists with value: " . $r['nilai'] . "\n";
}

echo "Migration V6 finished.\n";
?>
