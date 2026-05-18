<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT * FROM pengaturan");
if ($res) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Table 'pengaturan' not found.\n";
}
?>
