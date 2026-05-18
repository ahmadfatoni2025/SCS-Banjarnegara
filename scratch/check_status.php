<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT id_pesanan, status_pembayaran, status_pengiriman FROM pesanan ORDER BY id_pesanan DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
