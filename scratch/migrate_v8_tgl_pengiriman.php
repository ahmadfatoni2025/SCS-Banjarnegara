<?php
include 'koneksi.php';
$r = $koneksi->query("SHOW COLUMNS FROM detail_pesanan LIKE 'tgl_pengiriman'");
if ($r->num_rows == 0) {
    $koneksi->query("ALTER TABLE detail_pesanan ADD COLUMN tgl_pengiriman DATE DEFAULT NULL");
    echo "Added tgl_pengiriman column to detail_pesanan.\n";
} else {
    echo "Column tgl_pengiriman already exists.\n";
}
echo "Done.\n";
?>
