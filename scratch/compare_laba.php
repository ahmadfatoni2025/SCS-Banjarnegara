<?php
include 'koneksi.php';
include 'fungsi_akuntansi.php';

$tgl_awal = '2025-01-01';
$tgl_akhir = '2025-12-31';

$laba_2025 = getNetIncome($koneksi, $tgl_akhir, $tgl_awal);
echo "Laba 2025 (getNetIncome): " . formatRupiah($laba_2025) . PHP_EOL;

// Compare with rekap_tahunan logic for 2025
$q_inc = mysqli_query($koneksi, "SELECT SUM(kredit - debit) as val FROM jurnal_umum WHERE kode_akun LIKE '4%' AND YEAR(tanggal) = '2025' AND no_reff NOT LIKE 'CLS%'");
$inc = mysqli_fetch_assoc($q_inc)['val'] ?? 0;
$q_exp = mysqli_query($koneksi, "SELECT SUM(debit - kredit) as val FROM jurnal_umum WHERE (kode_akun LIKE '5%' OR kode_akun LIKE '6%') AND YEAR(tanggal) = '2025' AND no_reff NOT LIKE 'CLS%'");
$exp = mysqli_fetch_assoc($q_exp)['val'] ?? 0;
echo "Rekap 2025 Logic: " . formatRupiah($inc - $exp) . PHP_EOL;
?>
