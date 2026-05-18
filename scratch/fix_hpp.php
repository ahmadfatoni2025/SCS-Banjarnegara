<?php
include 'koneksi.php';

// 1. Ambil semua detail pesanan yang harga beli == harga jual (kemungkinan besar salah input)
$res = $koneksi->query("SELECT dp.id_detail, dp.id_barang, g.harga_beli as harga_asli 
                       FROM detail_pesanan dp
                       JOIN gudang g ON dp.id_barang = g.id_barang
                       WHERE dp.harga_beli_saat_itu = dp.harga_satuan");

echo "Memperbaiki " . $res->num_rows . " data...\n";

while($row = $res->fetch_assoc()) {
    $id = $row['id_detail'];
    $h_asli = $row['harga_asli'];
    $koneksi->query("UPDATE detail_pesanan SET harga_beli_saat_itu = $h_asli WHERE id_detail = $id");
}

echo "Selesai memperbaiki detail_pesanan.\n";

// 2. Sekarang kita harus update Jurnal Umum yang HPP nya salah.
// HPP di Jurnal Umum pakai no_reff = 'ORD-XX' dan kode_akun = '5111' atau '1131'
// Tapi karena jurnal umum itu gabungan (total), kita harus hitung ulang total HPP per pesanan.

$res_pes = $koneksi->query("SELECT id_pesanan FROM pesanan WHERE is_confirmed_acc = 1");
while($p = $res_pes->fetch_assoc()) {
    $id_pesanan = $p['id_pesanan'];
    $no_reff = "ORD-" . $id_pesanan;
    
    // Hitung HPP asli
    $res_hpp = $koneksi->query("SELECT SUM(jumlah * harga_beli_saat_itu) as total_hpp FROM detail_pesanan WHERE id_pesanan = $id_pesanan");
    $total_hpp = (float)$res_hpp->fetch_assoc()['total_hpp'];
    
    if ($total_hpp > 0) {
        // Update Jurnal HPP (Debit 5111)
        $koneksi->query("UPDATE jurnal_umum SET debit = $total_hpp WHERE no_reff = '$no_reff' AND kode_akun = '5111'");
        // Update Jurnal Persediaan (Kredit 1131)
        $koneksi->query("UPDATE jurnal_umum SET kredit = $total_hpp WHERE no_reff = '$no_reff' AND kode_akun = '1131'");
    }
}

echo "Selesai sinkronisasi Jurnal Umum.\n";
