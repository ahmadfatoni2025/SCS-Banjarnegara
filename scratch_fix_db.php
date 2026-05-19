<?php
include 'koneksi.php';
$koneksi->query("ALTER TABLE pesanan ADD COLUMN no_hp_driver VARCHAR(50) DEFAULT '-'");
echo "Done";
?>
