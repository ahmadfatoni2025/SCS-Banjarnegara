<?php
include 'koneksi.php';
$koneksi->query("UPDATE gudang SET stok = 0 WHERE tipe_pengadaan = 'PO'");
echo "PO stocks reset to 0.\n";
?>
