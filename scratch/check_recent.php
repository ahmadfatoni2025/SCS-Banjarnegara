<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT id_pesanan, total_harga FROM pesanan ORDER BY id_pesanan DESC LIMIT 10");
while($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id_pesanan'] . " - Total: " . $row['total_harga'] . "\n";
}
