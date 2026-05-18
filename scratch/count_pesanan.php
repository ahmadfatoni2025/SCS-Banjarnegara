<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT COUNT(*) FROM pesanan");
$count = $res->fetch_array()[0];
echo "Total Pesanan: " . $count . "\n";
