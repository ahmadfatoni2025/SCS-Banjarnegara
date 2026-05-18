<?php
include 'koneksi.php';
// Tambah kolom is_confirmed_acc dan set default status_approval ke Pending untuk pesanan baru
$sql1 = "ALTER TABLE pesanan ADD COLUMN is_confirmed_acc TINYINT(1) DEFAULT 0";
$sql2 = "ALTER TABLE pesanan MODIFY COLUMN status_approval ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending'";
mysqli_query($koneksi, $sql1);
mysqli_query($koneksi, $sql2);
echo "Database updated for Confirmation Workflow.";
?>
