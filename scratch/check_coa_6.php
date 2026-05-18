<?php
include 'koneksi.php';
$q = mysqli_query($koneksi, "SELECT * FROM akun_coa WHERE kode_akun LIKE '6%'");
while($r = mysqli_fetch_assoc($q)) {
    echo $r['kode_akun'] . ': ' . $r['nama_akun'] . ' (' . $r['kategori'] . ')' . PHP_EOL;
}
?>
