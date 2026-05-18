<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT * FROM detail_pesanan WHERE id_pesanan = 89");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
