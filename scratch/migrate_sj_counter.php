<?php
include 'koneksi.php';

echo "Starting SJ counter migration...\n";

// 1. Add sj_counter to pengaturan
$check_sj = $koneksi->query("SELECT 1 FROM pengaturan WHERE kunci = 'sj_counter'");
if ($check_sj->num_rows == 0) {
    $koneksi->query("INSERT INTO pengaturan (kunci, nilai) VALUES ('sj_counter', '0')");
    echo "- Added 'sj_counter' to pengaturan table.\n";
} else {
    echo "- 'sj_counter' already exists in pengaturan table.\n";
}

// 2. Add no_sj to detail_pesanan
// Check if column exists first
$res = $koneksi->query("SHOW COLUMNS FROM detail_pesanan LIKE 'no_sj'");
if ($res->num_rows == 0) {
    $koneksi->query("ALTER TABLE detail_pesanan ADD COLUMN no_sj VARCHAR(50) DEFAULT NULL AFTER tgl_pengiriman");
    echo "- Added 'no_sj' column to detail_pesanan table.\n";
} else {
    echo "- 'no_sj' column already exists in detail_pesanan table.\n";
}

echo "Migration completed successfully.\n";
?>
