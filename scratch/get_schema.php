<?php
include 'koneksi.php';
$res = $koneksi->query("SHOW CREATE TABLE detail_pesanan");
$row = $res->fetch_assoc();
echo $row['Create Table'];
?>
