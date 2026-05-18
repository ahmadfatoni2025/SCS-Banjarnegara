<?php
include 'koneksi.php';
$q = mysqli_query($koneksi, 'SELECT YEAR(tanggal) as thn, COUNT(*) as jml FROM jurnal_umum GROUP BY thn');
while($r = mysqli_fetch_assoc($q)) {
    echo $r['thn'] . ': ' . $r['jml'] . PHP_EOL;
}
?>
