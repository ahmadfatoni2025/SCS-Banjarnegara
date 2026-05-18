<?php
include 'koneksi.php';
$q = mysqli_query($koneksi, "SELECT SUM(kredit - debit) as bal FROM jurnal_umum WHERE kode_akun = '6111' AND YEAR(tanggal) = 2025");
echo "Saldo 6111 (2025): " . mysqli_fetch_assoc($q)['bal'] . PHP_EOL;
?>
