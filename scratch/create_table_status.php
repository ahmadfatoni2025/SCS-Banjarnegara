<?php
include 'koneksi.php';
$sql = "CREATE TABLE IF NOT EXISTS periode_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulan INT,
    tahun INT,
    status VARCHAR(20) DEFAULT 'Open',
    closed_at DATETIME,
    closed_by VARCHAR(100),
    UNIQUE(bulan, tahun)
)";
if (mysqli_query($koneksi, $sql)) {
    echo "Table periode_status created successfully" . PHP_EOL;
} else {
    echo "Error creating table: " . mysqli_error($koneksi) . PHP_EOL;
}
?>
