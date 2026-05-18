<?php
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
include 'koneksi.php';

// CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }

$id_pesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_pesanan <= 0) { die("ID Pesanan tidak valid!"); }

// FETCH DATA PESANAN
$sql_p = "SELECT p.*, u.nama as nama_dapur 
          FROM pesanan p 
          LEFT JOIN user u ON p.id_dapur = u.id 
          WHERE p.id_pesanan = ?";
$stmt_p = $koneksi->prepare($sql_p);
$stmt_p->bind_param("i", $id_pesanan);
$stmt_p->execute();
$res_p = $stmt_p->get_result();
$pesanan = $res_p->fetch_assoc();

if (!$pesanan) { die("Pesanan tidak ditemukan!"); }

// FETCH DETAIL PESANAN
$sql_d = "SELECT dp.*, g.nama as nama_barang, g.satuan 
          FROM detail_pesanan dp 
          JOIN gudang g ON dp.id_barang = g.id_barang 
          WHERE dp.id_pesanan = ?";
$stmt_d = $koneksi->prepare($sql_d);
$stmt_d->bind_param("i", $id_pesanan);
$stmt_d->execute();
$details = $stmt_d->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper Format Romawi
$bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
$m_pesan = (int)date('m', strtotime($pesanan['tgl_pesan']));
$y_pesan = date('Y', strtotime($pesanan['tgl_pesan']));

$no_invoice = !empty($pesanan['no_invoice']) ? $pesanan['no_invoice'] : str_pad($pesanan['id_pesanan'], 3, '0', STR_PAD_LEFT) . "/INV-D1/" . $bulan_romawi[$m_pesan] . "/" . $y_pesan;
$no_pesanan = !empty($pesanan['no_pesanan']) ? $pesanan['no_pesanan'] : $pesanan['id_pesanan'] . "/SCS/PO-DP/" . $bulan_romawi[$m_pesan] . "/" . $y_pesan;

// FETCH DATA AKUNTAN — SELALU tampilkan TTD (helper base64)
function _to_base64($filepath) {
    if (!$filepath || !file_exists($filepath)) return '';
    $type = pathinfo($filepath, PATHINFO_EXTENSION);
    $data = file_get_contents($filepath);
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

$ttd_base64 = '';
$ttd_nama_display = '';

// 1. SNAPSHOT PERMANEN
if (!empty($pesanan['path_ttd'])) {
    $ttd_base64 = _to_base64('uploads/ttd/' . $pesanan['path_ttd']);
    $ttd_nama_display = !empty($pesanan['nama_penandatangan']) ? $pesanan['nama_penandatangan'] : '';
}

// 2. FALLBACK: user table
if (empty($ttd_base64) && !empty($pesanan['id_akuntan'])) {
    $stmt_acc = $koneksi->prepare("SELECT tanda_tangan, nama_ttd, nama FROM user WHERE id = ?");
    $stmt_acc->bind_param("i", $pesanan['id_akuntan']);
    $stmt_acc->execute();
    $res_acc = $stmt_acc->get_result();
    if ($acc = $res_acc->fetch_assoc()) {
        if (!empty($acc['tanda_tangan'])) {
            $ttd_base64 = _to_base64('uploads/ttd/' . $acc['tanda_tangan']);
        }
        if (empty($ttd_nama_display)) {
            $ttd_nama_display = !empty($acc['nama_ttd']) ? $acc['nama_ttd'] : $acc['nama'];
        }
    }
}

// 3. FALLBACK GLOBAL: pengaturan default
if (empty($ttd_base64) || empty($ttd_nama_display)) {
    $res_def = $koneksi->query("SELECT kunci, nilai FROM pengaturan WHERE kunci IN ('nama_akuntan_default', 'ttd_akuntan_default')");
    while ($res_def && $row_def = $res_def->fetch_assoc()) {
        if ($row_def['kunci'] === 'nama_akuntan_default' && !empty($row_def['nilai']) && empty($ttd_nama_display)) {
            $ttd_nama_display = $row_def['nilai'];
        }
        if ($row_def['kunci'] === 'ttd_akuntan_default' && !empty($row_def['nilai']) && empty($ttd_base64)) {
            $ttd_base64 = _to_base64('uploads/ttd/' . $row_def['nilai']);
        }
    }
}

if (empty($ttd_nama_display)) $ttd_nama_display = 'Bagian Keuangan';

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: "Times New Roman", serif; color: #000; font-size: 11pt; margin: 0; padding: 0; }
        .header { margin-bottom: 20px; width: 100%; }
        .header table { width: 100%; }
        .logo-cell { width: 120px; text-align: left; vertical-align: top; }
        .logo { width: 100px; }
        .center-cell { text-align: center; vertical-align: top; }
        .placeholder-cell { width: 120px; }
        .company-name { font-size: 18pt; font-weight: bold; margin: 0; }
        .company-addr { font-size: 10pt; margin: 0; }
        
        .meta-section { margin-top: 40px; margin-bottom: 10px; width: 100%; }
        .recipient { float: left; width: 50%; font-size: 10pt; }
        .metadata { float: right; width: 50%; font-size: 10pt; text-align: right; }
        
        .title-banner { background-color: #d1d5db; text-align: center; padding: 5px; font-size: 14pt; margin: 20px auto; width: 40%; }
        
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border: 1px solid #000; padding: 4px 8px; font-size: 10pt; }
        table.items th { background-color: #fff; font-weight: normal; }
        
        .currency { display: table; width: 100%; }
        .currency-symbol { display: table-cell; text-align: left; }
        .currency-value { display: table-cell; text-align: right; }
        
        .payment-info { margin-top: 30px; float: left; width: 50%; }
        .signature-area { margin-top: 30px; float: right; text-align: center; width: 220px; position: relative; }
        .stamp { position: absolute; width: 100px; opacity: 0.3; left: 50%; margin-left: -50px; top: 10px; z-index: 1; }
        .signature-img { position: absolute; height: 70px; left: 50%; margin-left: -70px; top: -10px; z-index: 3; }
        .signature-line { border-bottom: 1px solid #000; margin: 45px auto 5px; width: 200px; font-weight: bold; position: relative; z-index: 2; }
        
        .footer-note { color: red; font-style: italic; font-weight: bold; font-size: 8pt; margin-top: 10px; }
        .keterangan-title { background-color: #d1d5db; font-weight: bold; padding: 2px 5px; display: inline-block; font-size: 9pt; border-bottom: 1px solid #000; min-width: 100px; }
        .keterangan-body { font-size: 9pt; margin-top: 10px; line-height: 1.6; }
        .watermark {
            position: absolute;
            top: 40%;
            left: 50%;
            margin-left: -200px;
            transform: rotate(-45deg);
            font-size: 80pt;
            color: rgba(220, 38, 38, 0.15);
            font-weight: bold;
            z-index: -1;
            text-transform: uppercase;
            border: 10px solid rgba(220, 38, 38, 0.15);
            padding: 20px;
            text-align: center;
            width: 400px;
        }
        .clear { clear: both; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td class="logo-cell"><img src="logo_scs.png" class="logo"></td>
                <td class="center-cell">
                    <div class="company-name">PT. SURYA CERAH SEMESTA</div>
                    <div class="company-addr">Jl. Pemuda No. 83, Kutabanjarnegara, Kec. Banjarnegara, Kab. Banjarnegara, Jawa Tengah, 53471</div>
                </td>
                <td class="placeholder-cell"></td>
            </tr>
        </table>
    </div>';

if ($pesanan['status_pembayaran'] === 'Batal') {
    $html .= '<div class="watermark">BATAL</div>';
}

$html .= '
    <div class="meta-section">
        <div class="recipient">
            <strong>Kepada:</strong><br>
            ' . htmlspecialchars($pesanan['nama_pemesan']) . '<br>
            <span style="font-size: 9pt; font-style: italic;">cc: Akuntan ' . htmlspecialchars($pesanan['nama_pemesan']) . '</span>
        </div>
        <div class="metadata">
            <table width="100%" style="font-size: 10pt;">
                <tr><td align="right" width="60%">Tanggal Invoice</td><td align="center" width="10%">:</td><td align="left">' . date('d/m/Y', strtotime($pesanan['tgl_pesan'])) . '</td></tr>
                <tr><td align="right">Nomor Invoice</td><td align="center">:</td><td align="left">' . $no_invoice . '</td></tr>
                <tr><td align="right">Nomor Pesanan</td><td align="center">:</td><td align="left">' . $no_pesanan . '</td></tr>
                <tr><td align="right">Tanggal Pesan</td><td align="center">:</td><td align="left">' . date('d/m/Y', strtotime($pesanan['tgl_pesan'])) . '</td></tr>
                <tr><td align="right">Status</td><td align="center">:</td><td align="left"><strong style="color: ' . ($pesanan['status_pembayaran'] === 'Lunas' ? '#15803d' : '#dc2626') . ';">' . ($pesanan['status_pembayaran'] === 'Batal' ? 'DIBATALKAN' : ($pesanan['status_pembayaran'] === 'Lunas' ? 'Lunas' : 'Belum Bayar')) . '</strong></td></tr>
            </table>
        </div>
        <div class="clear"></div>
    </div>

    <div class="title-banner">INVOICE</div>

    <p style="font-weight: bold; font-size: 10pt; margin-bottom: 5px;">Rincian Barang:</p>
    <table class="items">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="25%">Nama Barang</th>
                <th width="8%">Jumlah</th>
                <th width="8%">Satuan</th>
                <th width="12%">Tgl Kirim</th>
                <th width="16%">Harga Satuan</th>
                <th width="26%">Subtotal</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
$total = 0;
foreach ($details as $row) {
    $sub = $row['jumlah'] * $row['harga_satuan'];
    $total += $sub;
    $html .= '
            <tr>
                <td align="center">' . $no++ . '</td>
                <td>' . htmlspecialchars($row['nama_barang']) . '</td>
                <td align="center">' . $row['jumlah'] . '</td>
                <td align="center">' . $row['satuan'] . '</td>
                <td align="center" style="font-size:8pt;">' . (!empty($row['tgl_pengiriman']) ? date('d/m/Y', strtotime($row['tgl_pengiriman'])) : '-') . '</td>
                <td>
                    <div class="currency">
                        <span class="currency-symbol">Rp</span>
                        <span class="currency-value">' . number_format($row['harga_satuan'], 0, ',', '.') . '</span>
                    </div>
                </td>
                <td>
                    <div class="currency">
                        <span class="currency-symbol">Rp</span>
                        <span class="currency-value">' . number_format($sub, 0, ',', '.') . '</span>
                    </div>
                </td>
            </tr>';
}

$html .= '
            <tr style="font-weight: bold;">
                <td colspan="6" align="center" style="letter-spacing: 5px;">TOTAL</td>
                <td>
                    <div class="currency">
                        <span class="currency-symbol">Rp</span>
                        <span class="currency-value">' . number_format($total, 0, ',', '.') . '</span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>';

if ($pesanan['status_pembayaran'] !== 'Lunas') {
    $html .= '
    <div class="footer-note">*Pembayaran dilakukan maksimal satu (1) hari setelah barang diterima</div>

    <div class="payment-info">
        <div class="keterangan-title">Keterangan</div>
        <div class="keterangan-body">
            Pembayaran via<br><br>
            <strong>Bank BRI</strong><br>
            Atas nama PT Surya Cerah Semesta<br>
            0004-01-002303-56-1<br>
            NPWP 20.870.347.0-529.000
        </div>
    </div>';
}

$html .= '
    <div class="signature-area">
        <p style="font-size: 10pt; margin: 0;">Hormat kami,</p>
        <img src="logo_scs.png" class="stamp">';
        
        if (!empty($ttd_base64)) {
            $html .= '<img src="' . $ttd_base64 . '" class="signature-img">';
        }

$html .= '
        <div class="signature-line">
            ' . $ttd_nama_display . '
        </div>
        <p style="font-size: 9pt; margin: 0;">Bagian Keuangan</p>
    </div>
    <div class="clear"></div>

</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Times-Roman');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Invoice-" . str_replace('/', '-', $no_invoice) . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);
?>
