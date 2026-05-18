<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'koneksi.php';
include 'fungsi_akuntansi.php';

// CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
if (!in_array($_SESSION['user']['role'], ['admin', 'owner', 'akuntan'])) {
    echo "<script>alert('Akses Ditolak!'); window.location='dashboard.php';</script>"; exit;
}

// ==========================================
// 1. LOGIKA EXPORT EXCEL (FULL 6 SHEETS)
// ==========================================
if (isset($_POST['download_excel'])) {
    $tahun = (int)$_POST['tahun'];
    $bulan = (int)$_POST['bulan'];
    
    if ($bulan > 0) {
        $tgl_awal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
        $tgl_akhir = date("Y-m-t", strtotime($tgl_awal));
        $period_label = strtoupper(getBulanIndonesia(str_pad($bulan, 2, '0', STR_PAD_LEFT))) . " $tahun";
        $filename = "Laporan_Bulanan_" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "_$tahun.xls";
    } else {
        $tgl_awal = "$tahun-01-01";
        $tgl_akhir = "$tahun-12-31";
        $period_label = "TAHUN $tahun";
        $filename = "Laporan_Tahunan_$tahun.xls";
    }

    $company_name = "PT SURYA CERAH SEMESTA"; 
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    echo '<?xml version="1.0"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
    
    echo '<Styles>
            <Style ss:ID="Default" ss:Name="Normal">
                <Alignment ss:Vertical="Bottom"/>
                <Borders/>
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
                <Interior/>
                <NumberFormat/>
                <Protection/>
            </Style>
            <Style ss:ID="HeaderBlue">
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
                <Interior ss:Color="#2563EB" ss:Pattern="Solid"/>
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
                <Borders>
                    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                </Borders>
            </Style>
            <Style ss:ID="HeaderGreen">
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
                <Interior ss:Color="#10B981" ss:Pattern="Solid"/>
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            </Style>
             <Style ss:ID="HeaderYellow">
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000" ss:Bold="1"/>
                <Interior ss:Color="#FCD34D" ss:Pattern="Solid"/>
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            </Style>
            <Style ss:ID="Title">
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="14" ss:Color="#000000" ss:Bold="1"/>
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            </Style>
             <Style ss:ID="SubTitle">
                <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="12" ss:Color="#555555" ss:Bold="1"/>
                <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
            </Style>
            <Style ss:ID="Currency">
                 <NumberFormat ss:Format="Rp #,##0"/>
            </Style>
            <Style ss:ID="DateFmt">
                 <NumberFormat ss:Format="dd-mmm-yyyy"/>
                <Alignment ss:Horizontal="Center"/>
            </Style>
            <Style ss:ID="Bold">
                 <Font ss:Bold="1"/>
            </Style>
             <Style ss:ID="Center">
                 <Alignment ss:Horizontal="Center"/>
            </Style>
          </Styles>';

    // SHEET 1: COA
    echo '<Worksheet ss:Name="01. Master Akun">';
    echo '<Table>';
    echo '<Column ss:Width="100"/><Column ss:Width="200"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="100"/>';
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="Title"><Data ss:Type="String">'.$company_name.'</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="SubTitle"><Data ss:Type="String">DAFTAR AKUN (CHART OF ACCOUNTS)</Data></Cell></Row><Row></Row>';
    echo '<Row><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">KODE AKUN</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">NAMA AKUN</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">KATEGORI</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">TIPE LAPORAN</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">POSISI NORMAL</Data></Cell></Row>';
    $q_coa = mysqli_query($koneksi, "SELECT * FROM akun_coa ORDER BY kode_akun ASC");
    while($r = mysqli_fetch_assoc($q_coa)) {
        echo '<Row><Cell ss:StyleID="Center"><Data ss:Type="String">'.$r['kode_akun'].'</Data></Cell><Cell><Data ss:Type="String">'.$r['nama_akun'].'</Data></Cell><Cell><Data ss:Type="String">'.$r['kategori'].'</Data></Cell><Cell><Data ss:Type="String">'.$r['tipe_laporan'].'</Data></Cell><Cell><Data ss:Type="String">'.$r['posisi_normal'].'</Data></Cell></Row>';
    }
    echo '</Table></Worksheet>';

    // SHEET 2: JURNAL UMUM
    echo '<Worksheet ss:Name="02. Jurnal Umum">';
    echo '<Table>';
    echo '<Column ss:Width="80"/><Column ss:Width="100"/><Column ss:Width="250"/><Column ss:Width="200"/><Column ss:Width="100"/><Column ss:Width="100"/>';
    echo '<Row><Cell ss:MergeAcross="5" ss:StyleID="Title"><Data ss:Type="String">'.$company_name.'</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="5" ss:StyleID="SubTitle"><Data ss:Type="String">JURNAL UMUM PERIODE '.$period_label.'</Data></Cell></Row><Row></Row>';
    echo '<Row><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">TANGGAL</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">NO REF</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">KETERANGAN</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">AKUN</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">DEBIT</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">KREDIT</Data></Cell></Row>';
    $q_jurnal = mysqli_query($koneksi, "SELECT j.*, a.nama_akun FROM jurnal_umum j JOIN akun_coa a ON j.kode_akun = a.kode_akun WHERE j.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND j.no_reff NOT LIKE 'CLS-%' ORDER BY j.tanggal ASC, j.id ASC");
    while($r = mysqli_fetch_assoc($q_jurnal)) {
        echo '<Row><Cell ss:StyleID="DateFmt"><Data ss:Type="String">'.date('Y-m-d', strtotime($r['tanggal'])).'</Data></Cell><Cell ss:StyleID="Center"><Data ss:Type="String">'.$r['no_reff'].'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($r['keterangan']).'</Data></Cell><Cell><Data ss:Type="String">'.$r['kode_akun'].' - '.$r['nama_akun'].'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$r['debit'].'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$r['kredit'].'</Data></Cell></Row>';
    }
    echo '</Table></Worksheet>';

    // SHEET 3: BUKU BESAR
    echo '<Worksheet ss:Name="03. Buku Besar">';
    echo '<Table>';
    echo '<Column ss:Width="80"/><Column ss:Width="100"/><Column ss:Width="250"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="100"/>';
    echo '<Row><Cell ss:MergeAcross="5" ss:StyleID="Title"><Data ss:Type="String">'.$company_name.'</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="5" ss:StyleID="SubTitle"><Data ss:Type="String">BUKU BESAR PERIODE '.$period_label.'</Data></Cell></Row><Row></Row>';
    $q_akun = mysqli_query($koneksi, "SELECT * FROM akun_coa ORDER BY kode_akun ASC");
    while($akun = mysqli_fetch_assoc($q_akun)) {
        $kd = $akun['kode_akun'];
        $q_awal = mysqli_query($koneksi, "SELECT SUM(debit) as d, SUM(kredit) as k FROM jurnal_umum WHERE kode_akun = '$kd' AND tanggal < '$tgl_awal'");
        $r_awal = mysqli_fetch_assoc($q_awal);
        $prefix = substr($kd, 0, 1);
        $is_debit = in_array($prefix, ['1', '5', '6']); 
        $saldo = $is_debit ? (($r_awal['d']??0) - ($r_awal['k']??0)) : (($r_awal['k']??0) - ($r_awal['d']??0));
        $q_trx = mysqli_query($koneksi, "SELECT * FROM jurnal_umum WHERE kode_akun = '$kd' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND no_reff NOT LIKE 'CLS-%' ORDER BY tanggal ASC");
        if($saldo == 0 && mysqli_num_rows($q_trx) == 0) continue;
        echo '<Row><Cell ss:StyleID="HeaderGreen"><Data ss:Type="String">AKUN: '.$kd.' - '.$akun['nama_akun'].'</Data></Cell></Row>';
        echo '<Row><Cell ss:StyleID="Bold"><Data ss:Type="String">Tanggal</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">No. Ref</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">Keterangan</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">Debit</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">Kredit</Data></Cell><Cell ss:StyleID="Bold"><Data ss:Type="String">Saldo</Data></Cell></Row>';
        echo '<Row><Cell><Data ss:Type="String">-</Data></Cell><Cell><Data ss:Type="String">-</Data></Cell><Cell><Data ss:Type="String">Saldo Awal</Data></Cell><Cell><Data ss:Type="String">-</Data></Cell><Cell><Data ss:Type="String">-</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$saldo.'</Data></Cell></Row>';
        while($t = mysqli_fetch_assoc($q_trx)) {
            if ($is_debit) $rumus = "=R[-1]C + RC[-2] - RC[-1]"; else $rumus = "=R[-1]C + RC[-1] - RC[-2]";
            echo '<Row><Cell ss:StyleID="DateFmt"><Data ss:Type="String">'.date('Y-m-d', strtotime($t['tanggal'])).'</Data></Cell><Cell ss:StyleID="Center"><Data ss:Type="String">'.$t['no_reff'].'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($t['keterangan']).'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$t['debit'].'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$t['kredit'].'</Data></Cell><Cell ss:StyleID="Currency" ss:Formula="'.$rumus.'"><Data ss:Type="Number"></Data></Cell></Row>';
        }
        echo '<Row></Row>'; 
    }
    echo '</Table></Worksheet>';

    // SHEET 4: LABA RUGI
    echo '<Worksheet ss:Name="04. Laba Rugi">';
    echo '<Table>';
    echo '<Column ss:Width="100"/><Column ss:Width="250"/><Column ss:Width="120"/>';
    echo '<Row><Cell ss:MergeAcross="2" ss:StyleID="Title"><Data ss:Type="String">'.$company_name.'</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="2" ss:StyleID="SubTitle"><Data ss:Type="String">LAPORAN LABA RUGI PERIODE '.$period_label.'</Data></Cell></Row><Row></Row>';
    echo '<Row><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">KATEGORI</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">NAMA AKUN</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">JUMLAH</Data></Cell></Row>';
    $q_lr = mysqli_query($koneksi, "SELECT a.nama_akun, a.kode_akun, LEFT(a.kode_akun, 1) as kat, LEFT(a.kode_akun, 2) as sub, SUM(COALESCE(j.debit, 0)) as d, SUM(COALESCE(j.kredit, 0)) as k FROM akun_coa a LEFT JOIN jurnal_umum j ON a.kode_akun = j.kode_akun AND j.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND j.no_reff NOT LIKE 'CLS-%' WHERE LEFT(a.kode_akun, 1) IN ('4', '5', '6') GROUP BY a.kode_akun");
    $total_pendapatan = 0; $total_hpp = 0; $total_beban = 0;
    while($row = mysqli_fetch_assoc($q_lr)) {
        $val = 0; $kat_name = "";
        if ($row['kat'] == '4') { $val = $row['k'] - $row['d']; $total_pendapatan += $val; $kat_name = "Pendapatan"; } 
        else { $val = $row['d'] - $row['k']; if($row['sub'] == '51') { $total_hpp += $val; $kat_name = "HPP"; } else { $total_beban += $val; $kat_name = "Beban Ops"; } }
        if($val != 0) echo '<Row><Cell><Data ss:Type="String">'.$kat_name.'</Data></Cell><Cell><Data ss:Type="String">'.$row['nama_akun'].'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$val.'</Data></Cell></Row>';
    }
    $laba_bersih = ($total_pendapatan - $total_hpp) - $total_beban;
    echo '<Row></Row><Row><Cell><Data ss:Type="String">RESULT</Data></Cell><Cell><Data ss:Type="String">Total Pendapatan</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$total_pendapatan.'</Data></Cell></Row><Row><Cell><Data ss:Type="String">RESULT</Data></Cell><Cell><Data ss:Type="String">Total HPP</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$total_hpp.'</Data></Cell></Row><Row><Cell><Data ss:Type="String">RESULT</Data></Cell><Cell><Data ss:Type="String">Laba Kotor</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.($total_pendapatan - $total_hpp).'</Data></Cell></Row><Row><Cell><Data ss:Type="String">RESULT</Data></Cell><Cell><Data ss:Type="String">Total Beban Operasional</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$total_beban.'</Data></Cell></Row><Row><Cell ss:StyleID="HeaderGreen"><Data ss:Type="String">FINAL</Data></Cell><Cell ss:StyleID="HeaderGreen"><Data ss:Type="String">LABA BERSIH</Data></Cell><Cell ss:StyleID="HeaderGreen"><Data ss:Type="Number">'.$laba_bersih.'</Data></Cell></Row>';
    echo '</Table></Worksheet>';

    // SHEET 5: NERACA
    echo '<Worksheet ss:Name="05. Neraca">';
    echo '<Table>';
    echo '<Column ss:Width="100"/><Column ss:Width="250"/><Column ss:Width="120"/>';
    echo '<Row><Cell ss:MergeAcross="2" ss:StyleID="Title"><Data ss:Type="String">'.$company_name.'</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="2" ss:StyleID="SubTitle"><Data ss:Type="String">LAPORAN NERACA PER '.$tgl_akhir.'</Data></Cell></Row><Row></Row>';
    echo '<Row><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">KATEGORI</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">NAMA AKUN</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">SALDO</Data></Cell></Row>';
    $q_ner = mysqli_query($koneksi, "SELECT a.nama_akun, LEFT(a.kode_akun, 1) as kat, SUM(COALESCE(j.debit, 0)) as d, SUM(COALESCE(j.kredit, 0)) as k FROM akun_coa a LEFT JOIN jurnal_umum j ON a.kode_akun = j.kode_akun AND j.tanggal <= '$tgl_akhir' WHERE LEFT(a.kode_akun, 1) IN ('1', '2', '3') GROUP BY a.kode_akun");
    while($r = mysqli_fetch_assoc($q_ner)) {
        $saldo = 0; $kat_name = "";
        if($r['kat'] == '1') { $saldo = $r['d'] - $r['k']; $kat_name = "ASET"; } 
        else { $saldo = $r['k'] - $r['d']; $kat_name = ($r['kat']=='2') ? "KEWAJIBAN" : "MODAL"; }
        if($saldo != 0) echo '<Row><Cell><Data ss:Type="String">'.$kat_name.'</Data></Cell><Cell><Data ss:Type="String">'.$r['nama_akun'].'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$saldo.'</Data></Cell></Row>';
    }
    echo '</Table></Worksheet>';

    // SHEET 6: REKAP TAHUNAN
    echo '<Worksheet ss:Name="06. Rekap Tahunan">';
    echo '<Table>';
    echo '<Column ss:Width="60"/><Column ss:Width="120"/><Column ss:Width="120"/><Column ss:Width="120"/><Column ss:Width="150"/>';
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="Title"><Data ss:Type="String">'.$company_name.'</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="4" ss:StyleID="SubTitle"><Data ss:Type="String">KINERJA KEUANGAN TAHUN '.$tahun.'</Data></Cell></Row><Row></Row>';
    echo '<Row><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">PERIODE</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">PENDAPATAN</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">BEBAN</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">LABA BERSIH</Data></Cell><Cell ss:StyleID="HeaderYellow"><Data ss:Type="String">STATUS DATA</Data></Cell></Row>';
    $q_inc = mysqli_query($koneksi, "SELECT SUM(COALESCE(kredit, 0) - COALESCE(debit, 0)) as val FROM jurnal_umum WHERE kode_akun LIKE '4%' AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND no_reff NOT LIKE 'CLS-%'");
    $pendapatan = mysqli_fetch_assoc($q_inc)['val'] ?? 0;
    $q_exp = mysqli_query($koneksi, "SELECT SUM(COALESCE(debit, 0) - COALESCE(kredit, 0)) as val FROM jurnal_umum WHERE (kode_akun LIKE '5%' OR kode_akun LIKE '6%') AND tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND no_reff NOT LIKE 'CLS-%'");
    $beban = mysqli_fetch_assoc($q_exp)['val'] ?? 0;
    $laba_bersih = $pendapatan - $beban;
    $q_status = mysqli_query($koneksi, "SELECT status FROM rekap_tahunan WHERE tahun = '$tahun'");
    $d_status = mysqli_fetch_assoc($q_status);
    $status_label = ($d_status) ? $d_status['status'] : "Ongoing";
    echo '<Row><Cell ss:StyleID="Center"><Data ss:Type="String">'.$period_label.'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$pendapatan.'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$beban.'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$laba_bersih.'</Data></Cell><Cell ss:StyleID="Center"><Data ss:Type="String">'.$status_label.'</Data></Cell></Row>';
    echo '</Table></Worksheet>';

    // SHEET 7: JURNAL PENUTUP (TUTUP BUKU)
    echo '<Worksheet ss:Name="07. Jurnal Penutup">';
    echo '<Table>';
    echo '<Column ss:Width="80"/><Column ss:Width="120"/><Column ss:Width="250"/><Column ss:Width="200"/><Column ss:Width="100"/><Column ss:Width="100"/>';
    echo '<Row><Cell ss:MergeAcross="5" ss:StyleID="Title"><Data ss:Type="String">'.$company_name.'</Data></Cell></Row>';
    echo '<Row><Cell ss:MergeAcross="5" ss:StyleID="SubTitle"><Data ss:Type="String">JURNAL PENUTUP PERIODE '.$period_label.'</Data></Cell></Row><Row></Row>';
    echo '<Row><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">TANGGAL</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">NO REF</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">KETERANGAN</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">AKUN</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">DEBIT</Data></Cell><Cell ss:StyleID="HeaderBlue"><Data ss:Type="String">KREDIT</Data></Cell></Row>';
    $q_cls = mysqli_query($koneksi, "SELECT j.*, a.nama_akun FROM jurnal_umum j JOIN akun_coa a ON j.kode_akun = a.kode_akun WHERE j.tanggal BETWEEN '$tgl_awal' AND '$tgl_akhir' AND j.no_reff LIKE 'CLS-%' ORDER BY j.tanggal ASC, j.id ASC");
    while($r = mysqli_fetch_assoc($q_cls)) {
        echo '<Row><Cell ss:StyleID="DateFmt"><Data ss:Type="String">'.date('Y-m-d', strtotime($r['tanggal'])).'</Data></Cell><Cell ss:StyleID="Center"><Data ss:Type="String">'.$r['no_reff'].'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($r['keterangan']).'</Data></Cell><Cell><Data ss:Type="String">'.$r['kode_akun'].' - '.$r['nama_akun'].'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$r['debit'].'</Data></Cell><Cell ss:StyleID="Currency"><Data ss:Type="Number">'.$r['kredit'].'</Data></Cell></Row>';
    }
    if(mysqli_num_rows($q_cls) == 0) echo '<Row><Cell ss:MergeAcross="5" ss:StyleID="Center"><Data ss:Type="String">Tidak ada jurnal penutup pada periode ini.</Data></Cell></Row>';
    echo '</Table></Worksheet>';

    echo '</Workbook>';
    exit;
}

// ==========================================
// 2. LOGIKA IMPORT EXCEL (ANTI-DUPLIKAT)
// ==========================================
if (isset($_POST['upload_excel'])) {
    if (isset($_FILES['file_excel']['name']) && $_FILES['file_excel']['name'] != "") {
        $file_name = $_FILES['file_excel']['name'];
        $tmp_name  = $_FILES['file_excel']['tmp_name'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($file_ext == 'csv') {
            $target = "uploads/" . basename($file_name);
            if (!is_dir("uploads")) mkdir("uploads");
            move_uploaded_file($tmp_name, $target);

            $handle = fopen($target, "r");
            if ($handle !== FALSE) {
                $line = fgets($handle); 
                $delimiter = (strpos($line, ';') !== false) ? ';' : ',';
                rewind($handle);

                $valid_accounts = [];
                $q_v = mysqli_query($koneksi, "SELECT kode_akun FROM akun_coa");
                while($rw = mysqli_fetch_assoc($q_v)) { $valid_accounts[] = $rw['kode_akun']; }

                fgetcsv($handle, 1000, $delimiter); // Skip header

                $berhasil = 0; $gagal = 0; $duplikat = 0;
                $last_fingerprint = ""; 
                $current_no_reff = "";
                $row_counter = 0;

                while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    if (empty($data[1]) && empty($data[4])) continue; 

                    $raw_tgl = isset($data[1]) ? trim($data[1]) : '';
                    if (strpos($raw_tgl, '/') !== false) {
                        $ex = explode('/', $raw_tgl); 
                        $tanggal = (count($ex) == 3) ? $ex[2].'-'.$ex[1].'-'.$ex[0] : date('Y-m-d');
                    } else {
                        $tanggal = date('Y-m-d', strtotime($raw_tgl)); 
                    }

                    $keterangan = isset($data[2]) ? mysqli_real_escape_string($koneksi, trim($data[2])) : '';
                    $raw_akun = isset($data[4]) ? trim($data[4]) : '';
                    $kode_akun = (in_array($raw_akun, $valid_accounts)) ? $raw_akun : '0000';
                    $debit  = (int) preg_replace('/[^0-9]/', '', (isset($data[5]) ? $data[5] : '0'));
                    $kredit = (int) preg_replace('/[^0-9]/', '', (isset($data[6]) ? $data[6] : '0'));

                    // ANTI-DUPLIKAT
                    $cek_sql = "SELECT id FROM jurnal_umum WHERE tanggal = '$tanggal' AND keterangan = '$keterangan' AND kode_akun = '$kode_akun' AND debit = '$debit' AND kredit = '$kredit' LIMIT 1";
                    $cek_res = mysqli_query($koneksi, $cek_sql);
                    if (mysqli_num_rows($cek_res) > 0) { $duplikat++; continue; }

                    // AUTO-GROUPING NO REFF
                    $current_fingerprint = $tanggal . $keterangan;
                    $no_reff_excel = isset($data[3]) ? trim($data[3]) : '';
                    if (!empty($no_reff_excel) && $no_reff_excel != '-') {
                        $no_reff = mysqli_real_escape_string($koneksi, $no_reff_excel);
                    } else {
                        if ($current_fingerprint !== $last_fingerprint) {
                            $current_no_reff = "IMP-" . date('ymdHi') . "-" . str_pad($row_counter, 4, '0', STR_PAD_LEFT);
                            $last_fingerprint = $current_fingerprint;
                        }
                        $no_reff = $current_no_reff;
                    }

                    if(!empty($kode_akun)) {
                        $q = "INSERT INTO jurnal_umum (tanggal, no_reff, keterangan, kode_akun, debit, kredit, created_at) VALUES ('$tanggal', '$no_reff', '$keterangan', '$kode_akun', '$debit', '$kredit', NOW())";
                        if (mysqli_query($koneksi, $q)) { $berhasil++; } else { $gagal++; }
                    }
                    $row_counter++;
                }
                fclose($handle);
                echo "<script>alert('Import Selesai! Berhasil: $berhasil, Duplikat Ditolak: $duplikat, Gagal: $gagal'); window.location='jurnal_umum.php';</script>";
            }
        } else { echo "<script>alert('Format harus .CSV'); window.location='master_export.php';</script>"; }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Export & Import Data</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F1F5F9; }</style>
</head>
<body class="text-slate-800">
    <?php include 'sidebar.php'; ?>
    <div class="md:ml-64 min-h-screen p-8 transition-all duration-300">
        <div class="max-w-7xl mx-auto mt-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- EXPORT -->
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-indigo-600 to-blue-600 p-8 text-center text-white">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                            <i class="fas fa-file-excel text-3xl"></i>
                        </div>
                        <h1 class="text-2xl font-bold">Export Data Keuangan</h1>
                        <p class="text-indigo-100 mt-2">Unduh semua laporan dalam satu file Excel.</p>
                    </div>
                    <div class="p-8">
                        <form method="POST">
                            <label class="block text-sm font-bold text-slate-700 mb-2">Pilih Periode Laporan</label>
                            <div class="flex flex-col md:flex-row gap-3">
                                <select name="bulan" class="flex-1 p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <option value="0">Semua Bulan (Tahunan)</option>
                                    <?php 
                                    for($m=1; $m<=12; $m++) {
                                        $selected = (date('n') == $m) ? "selected" : "";
                                        echo "<option value='$m' $selected>".getBulanIndonesia(str_pad($m, 2, '0', STR_PAD_LEFT))."</option>";
                                    }
                                    ?>
                                </select>
                                <select name="tahun" class="w-full md:w-32 p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm font-semibold focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <?php 
                                    $cur = date('Y');
                                    for($y=$cur; $y>=$cur-3; $y--) echo "<option value='$y'>$y</option>";
                                    ?>
                                </select>
                            </div>
                            <button type="submit" name="download_excel" class="w-full mt-4 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg shadow-green-600/30 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-download"></i> Download Full Report (XLS)
                            </button>
                            <div class="mt-6 p-4 bg-blue-50 rounded-xl border border-blue-100 text-sm text-blue-700">
                                <p class="font-bold mb-1"><i class="fas fa-info-circle mr-1"></i> Informasi:</p>
                                <p>File Excel yang diunduh akan berisi 6 Sheet (Tab):</p>
                                <ul class="list-disc list-inside mt-1 ml-1 space-y-1 text-xs text-blue-600">
                                    <li><b>Sheet 1:</b> Master Akun (COA)</li>
                                    <li><b>Sheet 2:</b> Jurnal Umum (Detail Transaksi)</li>
                                    <li><b>Sheet 3:</b> Buku Besar (Mutasi per Akun)</li>
                                    <li><b>Sheet 4:</b> Laba Rugi (Kinerja Bisnis)</li>
                                    <li><b>Sheet 5:</b> Neraca (Posisi Harta & Modal)</li>
                                    <li><b>Sheet 6:</b> Rekapitulasi Periode</li>
                                    <li><b>Sheet 7:</b> Jurnal Penutup (Tutup Buku)</li>
                                </ul>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- IMPORT -->
                <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-orange-500 to-red-600 p-8 text-center text-white">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                            <i class="fas fa-file-import text-3xl"></i>
                        </div>
                        <h1 class="text-2xl font-bold">Import Data Transaksi</h1>
                        <p class="text-orange-100 mt-2">Upload data Jurnal Umum dari Excel.</p>
                    </div>
                    <div class="p-8">
                        <form method="POST" enctype="multipart/form-data">
                            <label class="block text-sm font-bold text-slate-700 mb-2">Upload File Excel (CSV)</label>
                            <div class="mb-4">
                                <input type="file" name="file_excel" accept=".csv" class="block w-full text-sm text-slate-500 file:mr-4 file:py-3 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100 cursor-pointer border border-slate-300 rounded-xl bg-slate-50 p-1" required>
                            </div>
                            <button type="submit" name="upload_excel" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg shadow-blue-600/30 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-upload"></i> Upload & Proses Data
                            </button>
                            <div class="mt-6 p-4 bg-orange-50 rounded-xl border border-orange-100 text-[10px] text-orange-800 italic">
                                *Baris dengan Tanggal, Keterangan, Akun, dan Nominal yang sama akan otomatis ditolak jika sudah ada di database.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
