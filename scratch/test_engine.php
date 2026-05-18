<?php
include 'koneksi.php';
include 'fungsi_akuntansi.php';

echo "=== TEST ENGINE AKUNTANSI ===\n";

$tgl_akhir = date('Y-m-d');

// Test 1: getAccountBalance for Kas (1111)
$saldo_kas = getAccountBalance($koneksi, '1111', $tgl_akhir);
echo "Saldo Kas (1111): " . formatRupiah($saldo_kas) . "\n";

// Test 2: getNetIncome
$laba = getNetIncome($koneksi, $tgl_akhir);
echo "Laba Berjalan: " . formatRupiah($laba) . "\n";

// Test 3: getCategoryTotal for Aset
$total_aset = getCategoryTotal($koneksi, 'Aset', $tgl_akhir);
echo "Total Aset: " . formatRupiah($total_aset) . "\n";

echo "\n=== SELESAI ===\n";
?>
