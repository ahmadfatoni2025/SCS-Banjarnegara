<?php
include 'koneksi.php';

$queries = [
    "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS nopol_driver VARCHAR(50) DEFAULT '-'",
    "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS nama_driver VARCHAR(100) DEFAULT '-'",
    "ALTER TABLE pesanan ADD COLUMN IF NOT EXISTS no_hp_driver VARCHAR(20) DEFAULT '-'"
];

foreach ($queries as $q) {
    if ($koneksi->query($q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . $koneksi->error . "\n";
    }
}
?>
