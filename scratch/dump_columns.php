<?php
include 'koneksi.php';
$res = $koneksi->query("DESCRIBE pesanan");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
