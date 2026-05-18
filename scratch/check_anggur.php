<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT id_barang, nama, tipe_pengadaan FROM gudang WHERE nama LIKE '%Calmeria%'");
while($row = $res->fetch_assoc()) {
    echo "ID: {$row['id_barang']} | Nama: {$row['nama']} | Tipe: {$row['tipe_pengadaan']}\n";
}
?>
