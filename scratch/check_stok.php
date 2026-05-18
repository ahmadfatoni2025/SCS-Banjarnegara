<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT nama, tipe_pengadaan, stok FROM gudang LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo $row['nama'] . ' | ' . $row['tipe_pengadaan'] . ' | ' . $row['stok'] . "\n";
}
?>
