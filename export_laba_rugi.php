<?php
// 1. SETUP HEADER EXCEL
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laba_Rugi_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
include 'koneksi.php'; 

// Cek Login (Keamanan)
if (!isset($_SESSION['user'])) { die("Akses Ditolak"); }

// 2. FILTER PERIODE
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// 3. LOGIKA HITUNG (SAMA PERSIS DENGAN laba_rugi.php)
$query = "
    SELECT 
        a.kode_akun, a.nama_akun, 
        LEFT(a.kode_akun, 1) as kategori_utama,
        LEFT(a.kode_akun, 2) as sub_kategori,
        SUM(j.debit) as total_debit, SUM(j.kredit) as total_kredit
    FROM akun_coa a
    LEFT JOIN jurnal_umum j ON a.kode_akun = j.kode_akun 
         AND (j.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai')
         AND (j.no_reff NOT LIKE 'CLS%') 
    WHERE LEFT(a.kode_akun, 1) IN ('4', '5') 
    GROUP BY a.kode_akun
    ORDER BY a.kode_akun ASC
";

$result = mysqli_query($koneksi, $query);

$pendapatan = []; $hpp = []; $beban_ops = [];
$total_pendapatan = 0; $total_hpp = 0; $total_beban_ops = 0;

while($row = mysqli_fetch_assoc($result)) {
    // Pendapatan
    if ($row['kategori_utama'] == '4') {
        $saldo = $row['total_kredit'] - $row['total_debit'];
        if($saldo != 0) { 
            $pendapatan[] = ['kode' => $row['kode_akun'], 'nama' => $row['nama_akun'], 'nilai'=> $saldo];
            $total_pendapatan += $saldo;
        }
    } 
    // Beban
    elseif ($row['kategori_utama'] == '5') {
        $saldo = $row['total_debit'] - $row['total_kredit'];
        if($saldo != 0) {
            // Pisah HPP (51)
            if ($row['sub_kategori'] == '51') {
                $hpp[] = ['kode' => $row['kode_akun'], 'nama' => $row['nama_akun'], 'nilai'=> $saldo];
                $total_hpp += $saldo;
            } else {
                $beban_ops[] = ['kode' => $row['kode_akun'], 'nama' => $row['nama_akun'], 'nilai'=> $saldo];
                $total_beban_ops += $saldo;
            }
        }
    }
}

// Hitung Berjenjang
$laba_kotor  = $total_pendapatan - $total_hpp;
$laba_bersih = $laba_kotor - $total_beban_ops;
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 5px; }
        .header { background-color: #f0f0f0; font-weight: bold; text-align: center; font-size: 14px; }
        .sub-header { background-color: #e0e0e0; font-weight: bold; }
        .amount { text-align: right; mso-number-format:"\#\,\#\#0"; } /* Format Angka Excel */
        .total-row { font-weight: bold; background-color: #ffffcc; }
        .grand-total { font-weight: bold; background-color: #d1fae5; font-size: 13px; }
        .section-title { font-weight: bold; background-color: #eaddff; }
        .danger { color: red; }
    </style>
</head>
<body>

    <table>
        <tr>
            <td colspan="3" class="header">LAPORAN LABA RUGI</td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center;">Periode: <?= date('d M Y', strtotime($tgl_mulai)) ?> s/d <?= date('d M Y', strtotime($tgl_selesai)) ?></td>
        </tr>
        <tr><td colspan="3"></td></tr> <tr class="sub-header">
            <th width="15%">KODE AKUN</th>
            <th width="50%">NAMA AKUN</th>
            <th width="35%">JUMLAH (Rp)</th>
        </tr>

        <tr>
            <td colspan="3" class="section-title" style="background-color: #dcfce7;">I. PENDAPATAN USAHA</td>
        </tr>
        <?php foreach($pendapatan as $row): ?>
        <tr>
            <td style="text-align: center;"><?= $row['kode'] ?></td>
            <td><?= $row['nama'] ?></td>
            <td class="amount"><?= $row['nilai'] ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2" style="text-align: right;">TOTAL PENDAPATAN</td>
            <td class="amount" style="color: green;"><?= $total_pendapatan ?></td>
        </tr>

        <tr><td colspan="3"></td></tr> 

        <tr>
            <td colspan="3" class="section-title" style="background-color: #fef9c3;">II. HARGA POKOK PENJUALAN (HPP)</td>
        </tr>
        <?php foreach($hpp as $row): ?>
        <tr>
            <td style="text-align: center;"><?= $row['kode'] ?></td>
            <td><?= $row['nama'] ?></td>
            <td class="amount danger">- <?= $row['nilai'] ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2" style="text-align: right;">TOTAL HPP</td>
            <td class="amount danger">( <?= $total_hpp ?> )</td>
        </tr>

        <tr class="grand-total" style="background-color: #dbeafe;">
            <td colspan="2" style="text-align: right;">LABA KOTOR (Gross Profit)</td>
            <td class="amount" style="color: blue;"><?= $laba_kotor ?></td>
        </tr>

        <tr><td colspan="3"></td></tr> 

        <tr>
            <td colspan="3" class="section-title" style="background-color: #ffe4e6;">III. BEBAN OPERASIONAL</td>
        </tr>
        <?php foreach($beban_ops as $row): ?>
        <tr>
            <td style="text-align: center;"><?= $row['kode'] ?></td>
            <td><?= $row['nama'] ?></td>
            <td class="amount danger">- <?= $row['nilai'] ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
            <td colspan="2" style="text-align: right;">TOTAL BEBAN OPERASIONAL</td>
            <td class="amount danger">( <?= $total_beban_ops ?> )</td>
        </tr>

        <tr><td colspan="3"></td></tr> 

        <tr class="grand-total" style="height: 40px; font-size: 14px;">
            <td colspan="2" style="text-align: right; vertical-align: middle;">LABA / RUGI BERSIH (Net Profit)</td>
            <td class="amount" style="vertical-align: middle; <?= ($laba_bersih < 0) ? 'color: red;' : 'color: green;' ?>">
                <?= $laba_bersih ?>
            </td>
        </tr>

    </table>

</body>
</html>
