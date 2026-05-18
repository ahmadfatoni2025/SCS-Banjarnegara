<?php
include 'koneksi.php';

// Add id_akuntan to pesanan
$res = $koneksi->query("SHOW COLUMNS FROM pesanan LIKE 'id_akuntan'");
if ($res->num_rows == 0) {
    echo "Adding 'id_akuntan' column to pesanan...\n";
    $koneksi->query("ALTER TABLE pesanan ADD COLUMN id_akuntan INT DEFAULT NULL AFTER is_confirmed_acc");
}

// Add tanda_tangan to user
$res = $koneksi->query("SHOW COLUMNS FROM user LIKE 'tanda_tangan'");
if ($res->num_rows == 0) {
    echo "Adding 'tanda_tangan' column to user...\n";
    $koneksi->query("ALTER TABLE user ADD COLUMN tanda_tangan VARCHAR(255) DEFAULT NULL");
}

echo "Migration V2 finished.\n";
?>
