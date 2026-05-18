<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'koneksi.php';
$res = $koneksi->query("SHOW TABLES");
if(!$res) die("Gagal query: " . $koneksi->error);
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
