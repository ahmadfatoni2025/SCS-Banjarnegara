<?php
include 'koneksi.php';

// Tambah key ttd_akuntan_default di tabel pengaturan
$res = $koneksi->query("SELECT * FROM pengaturan WHERE kunci = 'ttd_akuntan_default'");
if ($res->num_rows == 0) {
    // Ambil ttd dari admin pertama yang punya ttd
    $q = $koneksi->query("SELECT tanda_tangan FROM user WHERE tanda_tangan IS NOT NULL AND tanda_tangan != '' LIMIT 1");
    $ttd_val = '';
    if ($q && $row = $q->fetch_assoc()) {
        $ttd_val = $row['tanda_tangan'];
    }
    $stmt = $koneksi->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES ('ttd_akuntan_default', ?)");
    $stmt->bind_param("s", $ttd_val);
    $stmt->execute();
    echo "Added 'ttd_akuntan_default' with value: $ttd_val\n";
} else {
    echo "'ttd_akuntan_default' already exists.\n";
}

echo "Migration V5 finished.\n";
?>
