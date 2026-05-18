<?php
include 'koneksi.php';
$sql = "ALTER TABLE pesanan ADD COLUMN status_approval ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Approved'";
if (mysqli_query($koneksi, $sql)) {
    echo "Column 'status_approval' added.";
} else {
    echo "Error: " . mysqli_error($koneksi);
}
?>
