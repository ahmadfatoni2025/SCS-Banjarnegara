<?php
include 'koneksi.php';
$res = mysqli_query($koneksi, "DESCRIBE pesanan");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
