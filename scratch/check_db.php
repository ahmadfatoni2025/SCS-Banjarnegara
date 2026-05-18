<?php
include 'koneksi.php';
echo "--- GUDANG DATA ---\n";
$res = $koneksi->query("SELECT id_barang, nama FROM gudang LIMIT 5");
while($row = $res->fetch_assoc()) {
    echo $row['id_barang'] . ": " . $row['nama'] . "\n";
}
$res = $koneksi->query("SELECT COUNT(*) as c FROM gudang");
echo "Total items in gudang: " . $res->fetch_assoc()['c'] . "\n";

echo "\n--- FOREIGN KEYS (detail_pesanan) ---\n";
$res = $koneksi->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_NAME = 'detail_pesanan' AND TABLE_SCHEMA = '$database'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "\n--- FOREIGN KEYS (pesanan) ---\n";
$res = $koneksi->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_NAME = 'pesanan' AND TABLE_SCHEMA = '$database'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

?>
