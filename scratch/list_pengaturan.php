<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT * FROM pengaturan");
while($row = $res->fetch_assoc()) {
    echo $row['kunci'] . ": " . $row['nilai'] . "\n";
}
?>
