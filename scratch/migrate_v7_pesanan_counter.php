<?php
include 'koneksi.php';
$res = $koneksi->query("SELECT * FROM pengaturan WHERE kunci = 'pesanan_counter'");
if ($res->num_rows == 0) {
    $q = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'invoice_counter'");
    $v = $q->fetch_assoc()['nilai'];
    $koneksi->query("INSERT INTO pengaturan (kunci, nilai) VALUES ('pesanan_counter', '$v')");
    echo "Added pesanan_counter = $v\n";
} else {
    echo "Already exists.\n";
}
echo "Done.\n";
?>
