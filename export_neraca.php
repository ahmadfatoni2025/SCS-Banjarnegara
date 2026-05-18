<?php
// FILE: export_neraca.php
ob_start(); ini_set('display_errors', 0); error_reporting(0);
session_start();
if (file_exists('koneksi.php')) { include 'koneksi.php'; } else { die("Error Koneksi"); }
if (!isset($_SESSION['user'])) { die("Error Login"); }

$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$filename = "Neraca_Per_" . date('d-M-Y', strtotime($tgl_akhir)) . ".xls";

if (ob_get_length()) ob_end_clean();
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache"); header("Expires: 0");

// --- LOGIC HITUNG SAMA DENGAN WEB ---
function hitung($koneksi, $kode, $tgl, $posisi) {
    $q = mysqli_query($koneksi, "SELECT SUM(debit) as d, SUM(kredit) as k FROM jurnal_umum WHERE kode_akun='$kode' AND tanggal<='$tgl'");
    $r = mysqli_fetch_assoc($q);
    return ($posisi=='Debit') ? ($r['d']-$r['k']) : ($r['k']-$r['d']);
}

$aset = []; $kewajiban = []; $modal = [];
$tot_a = 0; $tot_k = 0; $tot_m = 0;

$q_akun = mysqli_query($koneksi, "SELECT * FROM akun_coa ORDER BY kode_akun ASC");
while($r = mysqli_fetch_assoc($q_akun)) {
    $kat = substr($r['kode_akun'],0,1);
    if($kat=='1') { $v=hitung($koneksi,$r['kode_akun'],$tgl_akhir,'Debit'); if($v!=0){$aset[]=$r; $aset[count($aset)-1]['val']=$v; $tot_a+=$v;} }
    elseif($kat=='2') { $v=hitung($koneksi,$r['kode_akun'],$tgl_akhir,'Kredit'); if($v!=0){$kewajiban[]=$r; $kewajiban[count($kewajiban)-1]['val']=$v; $tot_k+=$v;} }
    elseif($kat=='3') { $v=hitung($koneksi,$r['kode_akun'],$tgl_akhir,'Kredit'); if($v!=0){$modal[]=$r; $modal[count($modal)-1]['val']=$v; $tot_m+=$v;} }
}

// Laba Berjalan
$q_l = mysqli_query($koneksi, "SELECT SUM(CASE WHEN LEFT(kode_akun,1)='4' THEN kredit-debit ELSE 0 END) as p, SUM(CASE WHEN LEFT(kode_akun,1)='5' THEN debit-kredit ELSE 0 END) as b FROM jurnal_umum WHERE tanggal <= '$tgl_akhir'");
$r_l = mysqli_fetch_assoc($q_l);
$laba = $r_l['p'] - $r_l['b'];
$tot_m += $laba;
$tot_pasiva = $tot_k + $tot_m;
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
        th { background-color: #0f766e; color: white; padding: 8px; border: 1px solid #0f766e; }
        td { padding: 5px; border: 1px solid #cbd5e1; }
        .header-section { background-color: #e0f2fe; font-weight: bold; color: #0369a1; }
        .amount { text-align: right; }
        .total { font-weight: bold; background-color: #f1f5f9; }
        .laba { background-color: #fef3c7; }
    </style>
</head>
<body>
    <div class="title">LAPORAN NERACA (BALANCE SHEET)</div>
    <div class="subtitle">Per Tanggal: <?= date('d F Y', strtotime($tgl_akhir)) ?></div>
    <br>

    <table border="1">
        <thead>
            <tr>
                <th colspan="2">AKTIVA (ASET)</th>
                <th colspan="2">PASIVA (KEWAJIBAN + MODAL)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td valign="top" width="50%">
                    <table width="100%" border="0">
                        <?php foreach($aset as $a): ?>
                        <tr>
                            <td style="border:none;"><?= $a['nama_akun'] ?></td>
                            <td style="border:none;" class="amount">Rp <?= number_format($a['val'],0,',','.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </td>

                <td valign="top" width="50%">
                    <table width="100%" border="0">
                        <tr><td colspan="2" class="header-section" style="font-size:10px;">KEWAJIBAN</td></tr>
                        <?php foreach($kewajiban as $k): ?>
                        <tr>
                            <td style="border:none;"><?= $k['nama_akun'] ?></td>
                            <td style="border:none;" class="amount">Rp <?= number_format($k['val'],0,',','.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr><td colspan="2" style="border:none;">&nbsp;</td></tr>
                        
                        <tr><td colspan="2" class="header-section" style="font-size:10px;">MODAL</td></tr>
                        <?php foreach($modal as $m): ?>
                        <tr>
                            <td style="border:none;"><?= $m['nama_akun'] ?></td>
                            <td style="border:none;" class="amount">Rp <?= number_format($m['val'],0,',','.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="laba">
                            <td style="border:none;">Laba Periode Berjalan</td>
                            <td style="border:none;" class="amount">Rp <?= number_format($laba,0,',','.') ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td class="total">
                    TOTAL AKTIVA: <span style="float:right;">Rp <?= number_format($tot_a,0,',','.') ?></span>
                </td>
                <td class="total">
                    TOTAL PASIVA: <span style="float:right;">Rp <?= number_format($tot_pasiva,0,',','.') ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center" style="font-weight:bold; background-color:<?= ($tot_a==$tot_pasiva)?'#dcfce7':'#fee2e2' ?>; color:<?= ($tot_a==$tot_pasiva)?'#166534':'#991b1b' ?>;">
                    STATUS: <?= ($tot_a==$tot_pasiva)?'BALANCE':'TIDAK SEIMBANG' ?>
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
