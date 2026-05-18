<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Panggil koneksi dan autoload Dompdf
require('koneksi.php');
// Pastikan path ini benar sesuai lokasi folder dompdf Anda
// --- PERBAIKAN: Menyesuaikan nama folder ---
require 'public_html'; 

// 2. Impor namespace Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

// 3. Buat instance Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// 4. Mulai "menangkap" output HTML
// Semua 'echo' di bawah ini akan disimpan ke dalam variabel, bukan dikirim ke browser
ob_start(); 
?>

<!-- Ini adalah HTML yang akan kita ubah jadi PDF -->
<style>
    body { 
        font-family: 'Helvetica', 'Arial', sans-serif; 
        font-size: 12px;
    }
    table { 
        border-collapse: collapse; 
        width: 100%; 
        margin-top: 20px;
    }
    th, td { 
        border: 1px solid #000; 
        padding: 8px; 
        text-align: left; 
    }
    th { 
        background-color: #f2f2f2; 
        font-weight: bold;
    }
    h2 { 
        text-align: center; 
    }
    .text-right {
        text-align: right;
    }
</style>

<h2>Laporan Data Barang Gudang</h2>

<table>
    <tr>
        <th>No</th>
        <th>Nama Barang</th>
        <th>Kategori</th>
        <th>Satuan</th>
        <th>Harga (IDR)</th>
        <th>Stok</th>
    </tr>

<?php
// 5. Lakukan query database
$query = $koneksi->query("SELECT nama, kategori, satuan, harga, stok FROM gudang ORDER BY nama ASC");
$no = 1;
while ($row = $query->fetch_assoc()) {
    // Gunakan htmlspecialchars untuk keamanan jika data mengandung karakter aneh
    echo "<tr>
            <td>".$no++."</td>
            <td>".htmlspecialchars($row['nama'])."</td>
            <td>".htmlspecialchars($row['kategori'])."</td>
            <td>".htmlspecialchars($row['satuan'])."</td>
            <td class='text-right'>Rp ".number_format($row['harga'], 0, ',', '.')."</td>
            <td class='text-right'>".htmlspecialchars($row['stok'])."</td>
        </tr>";
}
?>
</table>

<?php
// 6. Ambil HTML yang sudah ditangkap
$html = ob_get_clean(); 

// 7. Load HTML ke Dompdf
$dompdf->loadHtml($html);

// 8. Set Ukuran Kertas (Opsional, default A4)
$dompdf->setPaper('A4', 'portrait'); // 'portrait' (tegak) atau 'landscape' (tidur)

// 9. Render HTML sebagai PDF
$dompdf->render();

// 10. Hapus header() manual Anda, ganti dengan stream() dari Dompdf
// Ini akan mengirim PDF ke browser dan memaksa download
$tglFile = date('Y-m-d');
$dompdf->stream("laporan-gudang-".$tglFile.".pdf", ["Attachment" => true]);

// Hentikan script setelah file dikirim
exit;
?>

