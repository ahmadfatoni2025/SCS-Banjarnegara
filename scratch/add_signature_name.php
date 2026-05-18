<?php
include 'koneksi.php';
$sql = "ALTER TABLE pesanan ADD COLUMN nama_penandatangan VARCHAR(100) DEFAULT 'Ruhma Syafia Dewi'";
if (mysqli_query($koneksi, $sql)) {
    echo "Column added.";
} else {
    echo "Error or column exists: " . mysqli_error($koneksi);
}
?>
