<?php
include 'koneksi.php';
$res = $koneksi->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
?>
