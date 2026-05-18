<?php
// FILE: export_jurnal.php
// VERSI FINAL: DOWNLOAD MODE

// 1. Mulai Buffer (Penting untuk mencegah error spasi kosong)
ob_start();

// Matikan error display agar tidak merusak file Excel
ini_set('display_errors', 0);
error_reporting(0);

session_start();

// 2. Cek Koneksi
if (file_exists('koneksi.php')) {
    include 'koneksi.php';
} else {
    die("Error: File koneksi.php tidak ditemukan.");
}

// 3. Cek Login
if (!isset($_SESSION['user'])) {
    die("Error: Anda belum login.");
}

// 4. Tangkap Filter Tanggal
$tgl_mulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// 5. Nama File Excel
$filename = "Laporan_Jurnal_" . date('d-M', strtotime($tgl_mulai)) . "_sd_" . date('d-M-Y', strtotime($tgl_selesai)) . ".xls";

// 6. BERSIHKAN BUFFER & KIRIM HEADER DOWNLOAD
// Ini perintah wajib supaya browser menganggap ini file download
if (ob_get_length()) ob_end_clean(); // Hapus output sampah sebelumnya

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// --- MULAI ISI FILE EXCEL DI BAWAH INI ---
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; background-color: #fff; }
        
        /* Table Style */
        table { width: 100%; border-collapse: collapse; border: none; margin-top: 10px; }
        
        /* Header Hijau Teal */
        thead th { 
            background-color: #0f766e; 
            color: #ffffff; 
            padding: 12px 15px; 
            text-align: left; 
            font-weight: bold; 
            border: 1px solid #0f766e;
            font-size: 11px;
            text-transform: uppercase;
        }

        /* Body & Zebra Striping */
        tbody td { 
            padding: 10px 15px; 
            color: #334155; 
            border: 1px solid #e2e8f0; 
            vertical-align: middle; 
        }
        tbody tr:nth-child(even) { background-color: #f8fafc; }
        tbody tr:nth-child(odd) { background-color: #ffffff; }

        /* Helpers */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge-reff { 
            background-color: #f1f5f9; 
            color: #475569; 
            border: 1px solid #cbd5e1; 
            padding: 3px 8px; 
            font-family: monospace; 
            font-size: 11px;
        }
        .money-debit { color: #059669; font-weight: bold; }
        .money-kredit { color: #dc2626; font-weight: bold; }
        
        /* Footer Total */
        tfoot td { 
            background-color: #e0f2fe; 
            color: #0369a1; 
            font-weight: bold; 
            padding: 12px 15px; 
            border: 1px solid #0284c7; 
            font-size: 13px;
        }
    </style>
</head>
<body>

    <h2 style="text-align:center; margin-bottom:5px;">LAPORAN JURNAL UMUM</h2>
    <p style="text-align:center; color:#64748b; margin-top:0;">Periode: <?php echo date('d F Y', strtotime($tgl_mulai)); ?> s/d <?php echo date('d F Y', strtotime($tgl_selesai)); ?></p>

    <table border="1">
        <thead>
            <tr>
                <th class="text-center" width="50">NO</th>
                <th class="text-center" width="100">TANGGAL</th>
                <th width="150">NO. REF</th>
                <th width="300">KETERANGAN</th>
                <th width="200">AKUN COA</th>
                <th class="text-right" width="120">DEBIT</th>
                <th class="text-right" width="120">KREDIT</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT j.*, a.nama_akun 
                      FROM jurnal_umum j 
                      JOIN akun_coa a ON j.kode_akun = a.kode_akun 
                      WHERE j.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai' 
                      ORDER BY j.tanggal ASC, j.id ASC";
            
            $result = mysqli_query($koneksi, $query);

            if ($result && mysqli_num_rows($result) > 0) {
                $no = 1; $td = 0; $tk = 0;
                while ($row = mysqli_fetch_assoc($result)) {
                    $td += $row['debit'];
                    $tk += $row['kredit'];
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td class="text-center" style="mso-number-format:'Short Date';"><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                <td style="mso-number-format:'@';"><span class="badge-reff"><?php echo $row['no_reff']; ?></span></td>
                <td><strong><?php echo htmlspecialchars($row['keterangan']); ?></strong></td>
                <td>
                    <div style="font-size:10px; color:#64748b;"><?php echo $row['kode_akun']; ?></div>
                    <div><?php echo htmlspecialchars($row['nama_akun']); ?></div>
                </td>
                <td class="text-right">
                    <?php if($row['debit'] > 0): ?>
                        <span class="money-debit">Rp <?php echo number_format($row['debit'], 0, ',', '.'); ?></span>
                    <?php else: ?> - <?php endif; ?>
                </td>
                <td class="text-right">
                    <?php if($row['kredit'] > 0): ?>
                        <span class="money-kredit">Rp <?php echo number_format($row['kredit'], 0, ',', '.'); ?></span>
                    <?php else: ?> - <?php endif; ?>
                </td>
            </tr>
            <?php 
                }
            } else {
                echo "<tr><td colspan='7' class='text-center' style='padding:20px; color:#94a3b8;'>Tidak ada data transaksi</td></tr>";
                $td = 0; $tk = 0;
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right">TOTAL</td>
                <td class="text-right">Rp <?php echo number_format($td, 0, ',', '.'); ?></td>
                <td class="text-right">Rp <?php echo number_format($tk, 0, ',', '.'); ?></td>
            </tr>
            <tr>
                <td colspan="5" class="text-right" style="background:#fff; border:none; color:#64748b;">STATUS:</td>
                <td colspan="2" class="text-center" style="background:<?php echo ($td == $tk) ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo ($td == $tk) ? '#166534' : '#991b1b'; ?>;">
                    <?php echo ($td == $tk) ? 'SEIMBANG (BALANCE)' : 'TIDAK SEIMBANG'; ?>
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
