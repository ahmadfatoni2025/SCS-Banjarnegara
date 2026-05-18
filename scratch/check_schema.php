<?php
include 'koneksi.php';
$r = $koneksi->query('DESCRIBE detail_pesanan');
echo "=== detail_pesanan ===\n";
while($row = $r->fetch_assoc()) echo $row['Field'].' | '.$row['Type'].' | '.$row['Default']."\n";

$r2 = $koneksi->query('DESCRIBE pesanan');
echo "\n=== pesanan (key columns) ===\n";
while($row = $r2->fetch_assoc()) echo $row['Field'].' | '.$row['Type'].' | '.$row['Default']."\n";
?>
