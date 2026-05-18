<?php
include 'koneksi.php';

// 1. Check if column exists
$res = $koneksi->query("SHOW COLUMNS FROM pesanan LIKE 'nama_akuntan'");
if ($res->num_rows == 0) {
    echo "Adding 'nama_akuntan' column...\n";
    $koneksi->query("ALTER TABLE pesanan ADD COLUMN nama_akuntan VARCHAR(100) DEFAULT NULL AFTER is_confirmed_acc");
} else {
    echo "Column 'nama_akuntan' already exists.\n";
}

// 2. Check if settings table exists
$res = $koneksi->query("SHOW TABLES LIKE 'pengaturan'");
if ($res->num_rows == 0) {
    echo "Creating 'pengaturan' table...\n";
    $koneksi->query("CREATE TABLE pengaturan (
        kunci VARCHAR(50) PRIMARY KEY,
        nilai TEXT
    )");
    $koneksi->query("INSERT INTO pengaturan (kunci, nilai) VALUES ('nama_akuntan_default', 'Ruhma Syafia Dewi')");
} else {
    echo "Table 'pengaturan' already exists.\n";
}

echo "Migration finished.\n";
?>
