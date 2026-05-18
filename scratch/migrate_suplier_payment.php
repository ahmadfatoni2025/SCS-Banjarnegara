<?php
include 'koneksi.php';

echo "Starting Supplier Payment Status migration...\n";

// 1. Add status columns to detail_pesanan
$res = $koneksi->query("SHOW COLUMNS FROM detail_pesanan LIKE 'is_paid_to_suplier'");
if ($res->num_rows == 0) {
    $koneksi->query("ALTER TABLE detail_pesanan ADD COLUMN is_paid_to_suplier TINYINT(1) DEFAULT 0");
    $koneksi->query("ALTER TABLE detail_pesanan ADD COLUMN tgl_bayar_suplier DATETIME DEFAULT NULL");
    echo "- Added payment tracking columns to detail_pesanan.\n";
} else {
    echo "- Payment tracking columns already exist.\n";
}

echo "Migration completed successfully.\n";
?>
