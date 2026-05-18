<?php
include 'koneksi.php';

echo "Creating pembelian_suplier table...\n";

$sql = "CREATE TABLE IF NOT EXISTS pembelian_suplier (
    id_pembelian INT AUTO_INCREMENT PRIMARY KEY,
    id_suplier INT(10) UNSIGNED NOT NULL,
    id_barang INT(10) UNSIGNED NOT NULL,
    jumlah DECIMAL(10,2) NOT NULL,
    harga_beli DECIMAL(15,2) NOT NULL,
    total_harga DECIMAL(15,2) NOT NULL,
    tgl_pembelian DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_paid TINYINT(1) DEFAULT 0,
    tgl_bayar DATETIME NULL,
    catatan TEXT NULL,
    FOREIGN KEY (id_suplier) REFERENCES data_suplier(id_suplier) ON DELETE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES gudang(id_barang) ON DELETE CASCADE
)";

if ($koneksi->query($sql)) {
    echo "Table 'pembelian_suplier' created successfully.\n";
} else {
    echo "Error creating table: " . $koneksi->error . "\n";
}
?>
