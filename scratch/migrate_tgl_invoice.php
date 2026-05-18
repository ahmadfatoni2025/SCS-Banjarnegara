<?php
include 'koneksi.php';
$sql = "ALTER TABLE pesanan ADD COLUMN tgl_invoice DATE NULL AFTER no_pesanan";
mysqli_query($koneksi, $sql);
echo "Added tgl_invoice column.";
?>
