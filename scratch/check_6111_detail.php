<?php
include 'koneksi.php';
$q = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE kode_akun = '6111'");
print_r(mysqli_fetch_assoc($q));
?>
