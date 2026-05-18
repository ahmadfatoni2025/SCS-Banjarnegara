<?php
include 'koneksi.php';

echo "Patching detail_pesanan with current cost prices from gudang...\n";

$sql = "SELECT dp.id_detail, dp.id_barang, g.harga as current_price
        FROM detail_pesanan dp
        JOIN gudang g ON dp.id_barang = g.id_barang
        WHERE dp.harga_beli_saat_itu = 0 OR dp.harga_beli_saat_itu IS NULL";

$res = $koneksi->query($sql);
$count = 0;

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $id = $row['id_detail'];
        $price = $row['current_price'];
        $koneksi->query("UPDATE detail_pesanan SET harga_beli_saat_itu = $price WHERE id_detail = $id");
        $count++;
    }
}

echo "Successfully patched $count items.\n";
?>
