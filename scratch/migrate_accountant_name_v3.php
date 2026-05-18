<?php
include 'koneksi.php';

// Add nama_ttd to user
$res = $koneksi->query("SHOW COLUMNS FROM user LIKE 'nama_ttd'");
if ($res->num_rows == 0) {
    echo "Adding 'nama_ttd' column to user...\n";
    $koneksi->query("ALTER TABLE user ADD COLUMN nama_ttd VARCHAR(100) DEFAULT NULL");
}

echo "Migration V3 finished.\n";
?>
