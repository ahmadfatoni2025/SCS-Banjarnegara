<?php
// FILE: export_arus_kas.php
ob_start();
ini_set('display_errors', 0); error_reporting(0);
session_start();

if (file_exists('koneksi.php')) { include 'koneksi.php'; } else { die("Error Koneksi"); }
if (!isset($_SESSION['user'])) { die("Error Login"); }

$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

$filename = "ArusKas_" . date('d-M', strtotime($tgl_mulai)) . "_sd_" . date('d-M-Y', strtotime($tgl_selesai)) . ".xls";

if (ob_get_length()) ob_end_clean();
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// --- LOGIC HITUNG ---
// Saldo Awal
$q_a = mysqli_query($koneksi, "SELECT SUM(debit) as d, SUM(kredit) as k FROM jurnal_umum WHERE kode_akun LIKE '111%' AND tanggal < '$tgl_mulai'");
$r_a = mysqli_fetch_assoc($q_a);
$awal = $r_a['d'] - $r_a['k'];

// Masuk
$q_in = mysqli_query($koneksi, "SELECT * FROM jurnal_umum WHERE kode_akun LIKE '111%' AND debit > 0 AND (tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai') ORDER BY tanggal ASC");
// Keluar
$q_out = mysqli_query($koneksi, "SELECT * FROM jurnal_umum WHERE kode_akun LIKE '111%' AND kredit > 0 AND (tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai') ORDER BY tanggal ASC");

$tot_in = 0;
$tot_out = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .title { font-size: 16px; font-weight: bold; text-align: center; }
        .subtitle { font-size: 12px; text-align: center; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px; border-bottom: 1px solid #eee; }
        .header-green { background-color: #dcfce7; color: #166534; font-weight: bold; }
        .header-red { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
        .amount { text-align: right; font-family: monospace; }
        .subtotal { font-weight: bold; background-color: #f8fafc; }
        .final-row { background-color: #0f766e; color: white; font-weight: bold; font-size: 14px; }
        .start-row { background-color: #f1f5f9; font-weight: bold; }
    </style>
</head>
<body>
    <div class="title">LAPORAN ARUS KAS (CASH FLOW)</div>
    <div class="subtitle">Periode: <?= date('d F Y', strtotime($tgl_mulai)) ?> s/d <?= date('d F Y', strtotime($tgl_selesai)) ?></div>
    <br>

    <table border="0">
        <tr class="start-row">
            <td colspan="2">SALDO KAS AWAL</td>
            <td class="amount">Rp <?= number_format($awal,0,',','.') ?></td>
        </tr>
        <tr><td colspan="3">&nbsp;</td></tr>

        <tr class="header-green"><td colspan="3">ARUS KAS MASUK (+)</td></tr>
        <?php if(mysqli_num_rows($q_in)>0): ?>
            <?php while($r=mysqli_fetch_assoc($q_in)): $tot_in+=$r['debit']; ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                <td><?= htmlspecialchars($r['keterangan']) ?></td>
                <td class="amount">Rp <?= number_format($r['debit'],0,',','.') ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3" style="color:#999; font-style:italic;">Tidak ada pemasukan</td></tr>
        <?php endif; ?>
        <tr class="subtotal">
            <td colspan="2">TOTAL MASUK</td>
            <td class="amount" style="color:green;">Rp <?= number_format($tot_in,0,',','.') ?></td>
        </tr>

        <tr><td colspan="3">&nbsp;</td></tr>

        <tr class="header-red"><td colspan="3">ARUS KAS KELUAR (-)</td></tr>
        <?php if(mysqli_num_rows($q_out)>0): ?>
            <?php while($r=mysqli_fetch_assoc($q_out)): $tot_out+=$r['kredit']; ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                <td><?= htmlspecialchars($r['keterangan']) ?></td>
                <td class="amount">Rp <?= number_format($r['kredit'],0,',','.') ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3" style="color:#999; font-style:italic;">Tidak ada pengeluaran</td></tr>
        <?php endif; ?>
        <tr class="subtotal">
            <td colspan="2">TOTAL KELUAR</td>
            <td class="amount" style="color:red;">( Rp <?= number_format($tot_out,0,',','.') ?> )</td>
        </tr>

        <tr><td colspan="3">&nbsp;</td></tr>

        <?php $bersih = $tot_in - $tot_out; $akhir = $awal + $bersih; ?>
        <tr>
            <td colspan="2">KENAIKAN/PENURUNAN KAS BERSIH</td>
            <td class="amount">Rp <?= number_format($bersih,0,',','.') ?></td>
        </tr>
        
        <tr class="final-row">
            <td colspan="2" style="padding:10px;">SALDO KAS AKHIR</td>
            <td class="amount" style="padding:10px;">Rp <?= number_format($akhir,0,',','.') ?></td>
        </tr>
    </table>
</body>
</html>
