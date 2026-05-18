<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT id_pesanan, id_akuntan, nama_penandatangan, path_ttd FROM pesanan ORDER BY id_pesanan DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
