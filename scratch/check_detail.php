<?php
include 'koneksi.php';
$res = $koneksi->query("DESCRIBE detail_pesanan");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
