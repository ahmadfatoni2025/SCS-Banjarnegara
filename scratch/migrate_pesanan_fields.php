<?php
include 'koneksi.php';

$sql = "ALTER TABLE pesanan 
        ADD COLUMN no_invoice VARCHAR(50) NULL AFTER id_pesanan, 
        ADD COLUMN no_pesanan VARCHAR(50) NULL AFTER no_invoice";

if (mysqli_query($koneksi, $sql)) {
    echo "Migration successful: Columns added to 'pesanan' table.";
} else {
    echo "Migration failed: " . mysqli_error($koneksi);
}
?>
