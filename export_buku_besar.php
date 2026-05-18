<?php
// FILE: export_buku_besar.php
// EXPORT EXCEL KHUSUS BUKU BESAR

ob_start();
ini_set('display_errors', 0);
error_reporting(0);
session_start();

if (file_exists('koneksi.php')) { include 'koneksi.php'; } else { die("Error Koneksi"); }
if (!isset($_SESSION['user'])) { die("Error Login"); }

// Ambil Filter
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
$kode_akun   = isset($_GET['kode_akun']) ? $_GET['kode_akun'] : '';

// Ambil Info Akun
$q_akun = mysqli_query($koneksi, "SELECT nama_akun FROM akun_coa WHERE kode_akun = '$kode_akun'");
$d_akun = mysqli_fetch_assoc($q_akun);
$nama_akun = $d_akun['nama_akun'] ?? 'Unknown';

// Nama File
$filename = "BB_" . $kode_akun . "_" . date('d-M', strtotime($tgl_mulai)) . ".xls";

if (ob_get_length()) ob_end_clean();

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #0f766e; color: #fff; padding: 10px; border: 1px solid #0f766e; }
        td { padding: 8px; border: 1px solid #cbd5e1; vertical-align: middle; }
        .text-right { text-align: right; } .text-center { text-align: center; }
        .bold { font-weight: bold; }
    </style>
</head>
<body>
    <h2 style="text-align:center; margin:0;">BUKU BESAR (GENERAL LEDGER)</h2>
    <p style="text-align:center; margin:5px 0 20px 0; color:#64748b;">
        Akun: <strong><?= $kode_akun ?> - <?= $nama_akun ?></strong> <br>
        Periode: <?= date('d F Y', strtotime($tgl_mulai)) ?> s/d <?= date('d F Y', strtotime($tgl_selesai)) ?>
    </p>

    <table border="1">
        <thead>
            <tr>
                <th width="100">TANGGAL</th>
                <th width="150">NO. REF</th>
                <th width="300">KETERANGAN</th>
                <th width="120">DEBIT</th>
                <th width="120">KREDIT</th>
                <th width="120">SALDO</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // 1. Hitung Saldo Awal
            $q_awal = mysqli_query($koneksi, "SELECT SUM(debit) as d, SUM(kredit) as k FROM jurnal_umum WHERE kode_akun='$kode_akun' AND tanggal < '$tgl_mulai'");
            $r_awal = mysqli_fetch_assoc($q_awal);
            $saldo = $r_awal['d'] - $r_awal['k'];
            ?>
            
            <tr style="background-color: #fef3c7;">
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td class="bold">SALDO AWAL</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right bold"><?= number_format($saldo,0,',','.') ?></td>
            </tr>

            <?php
            // 2. Loop Transaksi
            $query = "SELECT * FROM jurnal_umum WHERE kode_akun='$kode_akun' AND tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai' ORDER BY tanggal ASC, id ASC";
            $result = mysqli_query($koneksi, $query);
            
            $td=0; $tk=0;
            while($row = mysqli_fetch_assoc($result)):
                $saldo += ($row['debit'] - $row['kredit']);
                $td += $row['debit'];
                $tk += $row['kredit'];
            ?>
            <tr>
                <td class="text-center" style="mso-number-format:'Short Date';"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                <td class="text-center" style="mso-number-format:'@';"><?= $row['no_reff'] ?></td>
                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                <td class="text-right"><?= ($row['debit']>0)? number_format($row['debit'],0,',','.') : '-' ?></td>
                <td class="text-right"><?= ($row['kredit']>0)? number_format($row['kredit'],0,',','.') : '-' ?></td>
                <td class="text-right bold"><?= number_format($saldo,0,',','.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #e0f2fe;">
                <td colspan="3" class="text-right bold">TOTAL MUTASI</td>
                <td class="text-right bold"><?= number_format($td,0,',','.') ?></td>
                <td class="text-right bold"><?= number_format($tk,0,',','.') ?></td>
                <td class="text-right bold" style="background-color:#dcfce7; color:#166534;"><?= number_format($saldo,0,',','.') ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
