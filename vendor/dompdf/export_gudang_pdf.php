<?php
require '../koneksi.php';
require '../vendor/dompdf/autoload.inc.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();

// Ambil data dari database
$data = mysqli_query($conn, "SELECT * FROM gudang");

$html = '
<h2 style="text-align:center;">Laporan Data Gudang</h2>
<table border="1" cellspacing="0" cellpadding="6" width="100%">
<tr style="background:#2563eb;color:#fff;">
<th>Nama Barang</th>
<th>Kategori</th>
<th>Harga</th>
<th>Stok</th>
</tr>';

while($row = mysqli_fetch_assoc($data)) {
  $html .= "
  <tr>
    <td>{$row['nama']}</td>
    <td>{$row['kategori']}</td>
    <td>Rp ".number_format($row['harga'],0,',','.')."</td>
    <td>{$row['stok']}</td>
  </tr>";
}

$html .= '</table>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('laporan_gudang.pdf', ['Attachment' => true]);
