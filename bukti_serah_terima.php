<?php
session_start();
include 'koneksi.php';

// Keamanan: Hanya Admin atau Driver yang boleh melihat
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'driver')) {
    die("Akses ditolak. Silakan login.");
}

$id_pesanan = (int)($_GET['id'] ?? 0);
if ($id_pesanan <= 0) {
    die("ID Pesanan tidak valid.");
}

// Ambil info pesanan utama
$pesanan = null;
$stmt_pesanan = $koneksi->prepare("SELECT nama_pemesan, tgl_pesan, nama_driver FROM pesanan WHERE id_pesanan = ?");
$stmt_pesanan->bind_param("i", $id_pesanan);
$stmt_pesanan->execute();
$stmt_pesanan->store_result();
$stmt_pesanan->bind_result($nama_pemesan, $tgl_pesan, $nama_driver);
$stmt_pesanan->fetch();
if($stmt_pesanan->num_rows > 0) {
    $pesanan = [
        'nama_pemesan' => $nama_pemesan, 
        'tgl_pesan' => $tgl_pesan, 
        'nama_driver' => $nama_driver ?? 'Driver' // Ambil nama driver dari pesanan
    ];
}
$stmt_pesanan->close();

if (!$pesanan) {
    die("Pesanan tidak ditemukan.");
}

// Ambil rincian barang pesanan
$detail_barang = [];
$stmt_detail = $koneksi->prepare(
    "SELECT G.nama, DP.jumlah, G.satuan 
     FROM detail_pesanan AS DP 
     JOIN gudang AS G ON DP.id_barang = G.id_barang 
     WHERE DP.id_pesanan = ?"
);
$stmt_detail->bind_param("i", $id_pesanan);
$stmt_detail->execute();
$stmt_detail->store_result();
$stmt_detail->bind_result($nama_barang, $jumlah, $satuan);
while($stmt_detail->fetch()) {
    $detail_barang[] = [
        'nama' => $nama_barang, 
        'jumlah' => $jumlah, 
        'satuan' => $satuan
    ];
}
$stmt_detail->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Serah Terima #<?php echo $id_pesanan; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 1cm; background: white; }
            .print-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
        }
        td, th { padding: 8px 12px; border: 1px solid #e5e7eb; }
        body {
            font-family: 'Arial', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 p-4 md:p-8">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-lg print-container">
        
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800">BUKTI SERAH TERIMA BARANG</h1>
            <p class="text-gray-600">Sistem Manajemen Makanan Bergizi Sehat</p>
        </div>
        
        <div class="grid grid-cols-3 gap-4 my-6 text-sm">
            <div>
                <strong class="block text-gray-500">No. Pesanan:</strong>
                <span class="font-medium">#<?php echo $id_pesanan; ?></span>
            </div>
            <div>
                <strong class="block text-gray-500">Tujuan (Dapur):</strong>
                <span class="font-medium"><?php echo htmlspecialchars($pesanan['nama_pemesan']); ?></span>
            </div>
            <div>
                <strong class="block text-gray-500">Tanggal:</strong>
                <span class="font-medium"><?php echo date('d F Y H:i', strtotime($pesanan['tgl_pesan'])); ?></span>
            </div>
        </div>

        <table class="w-full text-left border-collapse text-sm mb-10">
            <thead>
                <tr class="bg-gray-100">
                    <th class="w-10">No.</th>
                    <th>Daftar Pesanan (Nama Barang)</th>
                    <th>Kuantiti</th>
                    <th>Satuan</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($detail_barang)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-gray-500 py-4">Tidak ada barang dalam pesanan ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($detail_barang as $index => $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($item['nama']); ?></td>
                        <td><?php echo htmlspecialchars($item['jumlah']); ?></td>
                        <td><?php echo htmlspecialchars($item['satuan']); ?></td>
                        <td></td> </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="grid grid-cols-3 gap-8 mt-16 pt-8 text-center text-sm">
            <div>
                <p class="mb-16">Admin,</p>
                <p class="border-t border-gray-400 pt-1">(_____________________)</p>
            </div>
            <div>
                <p class="mb-16">Driver,</p>
                <p class="border-t border-gray-400 pt-1">(<?php echo htmlspecialchars($pesanan['nama_driver']); ?>)</p>
            </div>
            <div>
                <p class="mb-16">Penerima,</p>
                <p class="border-t border-gray-400 pt-1">(_____________________)</p>
            </div>
        </div>

        <div class="text-center mt-12 no-print">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
                <i class="fas fa-print mr-2"></i>Cetak Bukti
            </button>
        </div>
        
    </div>
</body>
</html>
