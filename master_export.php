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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .sidebar-space { margin-left: 16rem; }
        @media (max-width: 1024px) { .sidebar-space { margin-left: 0; } }
        /* Custom scrollbar for clean look */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-slate-800">
    
    <?php include 'sidebar.php'; ?>

    <div class="sidebar-space min-h-screen p-6 md:p-10 transition-all duration-300">
        <div class="max-w-5xl mx-auto">
            
            <!-- Page Header -->
            <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-white shadow-sm border border-gray-100 flex items-center justify-center text-blue-600">
                        <i class="fas fa-exchange-alt text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Export & Import Data</h1>
                        <p class="text-xs text-gray-500 mt-1 font-medium">Kelola pergerakan data sistem (Unduh & Unggah CSV)</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-1 gap-8">
                
                <!-- EXPORT SECTION (Left) -->
                <div class="bg-white rounded-[1.5rem] shadow-[0_4px_24px_rgba(0,0,0,0.02)] border border-gray-100 overflow-hidden h-fit">
                    <div class="p-8">
                        <div class="flex items-start gap-4 mb-8">
                            <div class="w-10 h-10 rounded-full border border-gray-200 text-gray-500 flex items-center justify-center shadow-sm shrink-0">
                                <i class="fas fa-download"></i>
                            </div>
                            <div>
                                <h2 class="text-[15px] font-bold text-gray-900">Download files</h2>
                                <p class="text-[11px] text-gray-500 mt-0.5">Pilih periode dan export data keuangan ke Excel</p>
                            </div>
                        </div>

                        <form method="POST">
                            <label class="block text-[11px] font-bold text-gray-700 mb-2 uppercase tracking-wide">Pilih Periode Laporan</label>
                            <div class="flex flex-col sm:flex-row gap-3 mb-6">
                                <div class="relative flex-1">
                                    <select name="bulan" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all appearance-none cursor-pointer shadow-sm">
                                        <option value="0">Semua Bulan (Tahunan)</option>
                                        <?php 
                                        for($m=1; $m<=12; $m++) {
                                            $selected = (date('n') == $m) ? "selected" : "";
                                            echo "<option value='$m' $selected>".getBulanIndonesia(str_pad($m, 2, '0', STR_PAD_LEFT))."</option>";
                                        }
                                        ?>
                                    </select>
                                    <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"></i>
                                </div>
                                <div class="relative w-full sm:w-32">
                                    <select name="tahun" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all appearance-none cursor-pointer shadow-sm">
                                        <?php 
                                        $cur = date('Y');
                                        for($y=$cur; $y>=$cur-3; $y--) echo "<option value='$y'>$y</option>";
                                        ?>
                                    </select>
                                    <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-[10px] text-gray-400 pointer-events-none"></i>
                                </div>
                            </div>
                            
                            <!-- Premium Information Box -->
                            <div class="p-5 rounded-[1.25rem] border border-dashed border-gray-200 bg-gray-50/50 mb-8">
                                <h3 class="text-xs font-bold text-gray-800 flex items-center gap-2 mb-3">
                                    <i class="fas fa-layer-group text-blue-500"></i> Isi File Excel (6 Sheet)
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-4">
                                    <div class="flex items-center gap-2 text-[11px] font-medium text-gray-500"><i class="fas fa-check-circle text-emerald-500 text-[10px]"></i> Master Akun (COA)</div>
                                    <div class="flex items-center gap-2 text-[11px] font-medium text-gray-500"><i class="fas fa-check-circle text-emerald-500 text-[10px]"></i> Jurnal Umum</div>
                                    <div class="flex items-center gap-2 text-[11px] font-medium text-gray-500"><i class="fas fa-check-circle text-emerald-500 text-[10px]"></i> Buku Besar</div>
                                    <div class="flex items-center gap-2 text-[11px] font-medium text-gray-500"><i class="fas fa-check-circle text-emerald-500 text-[10px]"></i> Laba Rugi & Neraca</div>
                                    <div class="flex items-center gap-2 text-[11px] font-medium text-gray-500"><i class="fas fa-check-circle text-emerald-500 text-[10px]"></i> Rekap Tahunan</div>
                                    <div class="flex items-center gap-2 text-[11px] font-medium text-gray-500"><i class="fas fa-check-circle text-emerald-500 text-[10px]"></i> Jurnal Penutup</div>
                                </div>
                            </div>

                            <button type="submit" name="download_excel" class="w-full bg-gray-900 hover:bg-gray-800 text-white font-bold py-3.5 px-6 rounded-xl text-xs transition-all flex items-center justify-center gap-2 shadow-lg shadow-gray-900/20">
                                <i class="fas fa-file-excel"></i> Export Laporan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- IMPORT SECTION (Right - EXACT UI MATCH) -->
                <div class="bg-white rounded-[1.5rem] shadow-[0_4px_24px_rgba(0,0,0,0.02)] border border-gray-100 overflow-hidden h-fit relative">
                    <div class="p-8 pb-6">
                        <div class="flex items-start gap-4 mb-6 relative">
                            <div class="w-10 h-10 rounded-full border border-gray-200 text-gray-500 flex items-center justify-center shadow-sm shrink-0">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div>
                                <h2 class="text-[15px] font-bold text-gray-900">Upload files</h2>
                                <p class="text-[11px] text-gray-500 mt-0.5">Select and upload the files of your choice</p>
                            </div>
                            <i class="fas fa-times absolute right-0 top-1 text-gray-400 cursor-pointer hover:text-gray-600 transition-colors"></i>
                        </div>

                        <form method="POST" id="form-import" enctype="multipart/form-data" onsubmit="handleUploadSubmit(event)">
                            
                            <!-- Dropzone -->
                            <div class="border-[1.5px] border-dashed border-gray-300 rounded-[1.25rem] p-10 text-center relative hover:bg-gray-50/50 transition-colors group mb-4" id="dropzone">
                                <input type="file" name="file_excel" id="file_excel" accept=".csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" required onchange="handleFileSelect(this)">
                                
                                <div class="w-10 h-10 rounded-full text-gray-600 flex items-center justify-center mx-auto mb-3 group-hover:-translate-y-1 transition-transform">
                                    <i class="fas fa-cloud-upload-alt text-[22px]"></i>
                                </div>
                                
                                <h3 class="font-bold text-gray-900 text-[13px] mb-1">Choose a file or drag & drop it here.</h3>
                                <p class="text-[11px] text-gray-400 mb-6 font-medium">CSV format, up to 10 MB.</p>
                                
                                <button type="button" class="bg-gray-50 border border-gray-200 text-gray-800 font-bold py-2 px-5 rounded-[0.5rem] text-[11px] transition-colors pointer-events-none">
                                    Browse File
                                </button>
                            </div>

                            <!-- File Preview Area -->
                            <div id="file-preview" class="hidden mb-6 border border-gray-200 rounded-xl p-3.5 flex justify-between items-center bg-white shadow-sm">
                                <div class="flex items-center gap-3 overflow-hidden">
                                    <div class="w-8 h-8 rounded-lg bg-red-50 border border-red-100 text-red-600 flex items-center justify-center shrink-0">
                                        <i class="fas fa-file-csv text-[15px]"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p id="file-name" class="font-bold text-[11px] text-gray-800 truncate leading-tight">data.csv</p>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span id="file-size" class="text-[9px] text-gray-500 font-medium">0 KB of 120 KB</span>
                                            <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                                            <span class="text-[9px] font-bold text-emerald-500 flex items-center gap-1"><i class="fas fa-check-circle"></i> Completed</span>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" onclick="clearFile()" class="w-8 h-8 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 flex items-center justify-center transition-colors shrink-0">
                                    <i class="fas fa-trash-alt text-[11px]"></i>
                                </button>
                            </div>

                            <!-- OR Divider -->
                            <div class="flex items-center gap-4 mb-6">
                                <div class="h-px bg-gray-200 flex-1"></div>
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">OR</span>
                                <div class="h-px bg-gray-200 flex-1"></div>
                            </div>
                            
                            <!-- Import from URL Link -->
                            <div class="mb-8">
                                <label class="block text-[10px] font-bold text-gray-700 mb-2">Import from URL Link</label>
                                <div class="flex border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all bg-white shadow-sm">
                                    <div class="px-3 py-2.5 bg-gray-50 border-r border-gray-200 text-gray-500 text-xs font-medium flex items-center">
                                        http://
                                    </div>
                                    <input type="text" placeholder="Paste file URL" class="w-full px-3 py-2.5 text-xs text-gray-700 outline-none disabled:bg-gray-50 disabled:cursor-not-allowed" disabled title="Feature coming soon">
                                    <div class="px-3 py-2.5 text-gray-400 flex items-center">
                                        <i class="far fa-question-circle text-[11px]"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Action -->
                            <button type="submit" name="upload_excel" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-6 rounded-xl text-xs transition-all flex items-center justify-center shadow-lg shadow-blue-600/20">
                                Upload Now
                            </button>
                            
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Progress Modal (Image 2 Match) -->
    <div id="upload-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/10 backdrop-blur-[1px] hidden opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 w-full max-w-[340px] p-6 pb-5 transform scale-95 transition-transform duration-300 relative" id="upload-modal-content">
            <button type="button" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
            <div class="flex items-start gap-4 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="pt-0.5">
                    <h3 class="font-bold text-gray-900 text-[15px] leading-tight mb-1">Uploading "<span id="uploading-filename" class="text-blue-600 font-semibold truncate max-w-[120px] inline-block align-bottom">file.csv</span>"</h3>
                    <p class="text-[12px] text-gray-500 font-medium">Please wait while we upload your file.</p>
                </div>
            </div>
            
            <div class="w-full bg-gray-100 rounded-full h-2 mb-2 overflow-hidden mt-2">
                <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300 ease-out w-0"></div>
            </div>
            <p id="progress-text" class="text-[11px] font-medium text-gray-600 mb-6">0% uploaded...</p>
            
            <div class="flex items-center gap-5 text-[13px] font-bold">
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors">Cancel</button>
                <button type="button" class="text-gray-900 transition-colors">Upload More</button>
            </div>
        </div>
    </div>

    <script>
        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('file-name').innerText = file.name;
                document.getElementById('uploading-filename').innerText = file.name;
                
                let size = file.size / 1024;
                let sizeStr = size > 1024 ? (size/1024).toFixed(1) + ' MB' : Math.round(size) + ' KB';
                document.getElementById('file-size').innerText = sizeStr;
                
                document.getElementById('file-preview').classList.remove('hidden');
                document.getElementById('dropzone').classList.add('border-solid', 'border-blue-300', 'bg-blue-50/20');
                document.getElementById('dropzone').classList.remove('border-dashed', 'border-gray-300');
            }
        }

        function clearFile() {
            document.getElementById('file_excel').value = '';
            document.getElementById('file-preview').classList.add('hidden');
            document.getElementById('dropzone').classList.remove('border-solid', 'border-blue-300', 'bg-blue-50/20');
            document.getElementById('dropzone').classList.add('border-dashed', 'border-gray-300');
        }

        function handleUploadSubmit(e) {
            e.preventDefault();
            const fileInput = document.getElementById('file_excel');
            if(!fileInput.files.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih File',
                    text: 'Silakan pilih file CSV terlebih dahulu.',
                    confirmButtonColor: '#2563EB'
                });
                return;
            }
            
            // Show Modal
            const modal = document.getElementById('upload-modal');
            modal.classList.remove('hidden');
            
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                document.getElementById('upload-modal-content').classList.remove('scale-95');
            }, 10);
            
            let progress = 0;
            const bar = document.getElementById('progress-bar');
            const text = document.getElementById('progress-text');
            
            // Simulate Upload Progress Animation
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if(progress > 95) {
                    progress = 95;
                    clearInterval(interval);
                    // Actual form submit when animation reaches near 100%
                    setTimeout(() => {
                        document.getElementById('form-import').submit();
                    }, 200);
                }
                bar.style.width = progress + '%';
                text.innerText = Math.floor(progress) + '% uploaded...';
            }, 150);
        }
    </script>

</body>
</html>
