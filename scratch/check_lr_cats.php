<?php
include 'koneksi.php';
$q = mysqli_query($koneksi, "SELECT DISTINCT kategori FROM akun_coa WHERE tipe_laporan = 'Laba Rugi'");
while($r = mysqli_fetch_assoc($q)) {
    echo $r['kategori'] . PHP_EOL;
}
?>
