<?php
// 1. DETEKSI ERROR
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'koneksi.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$type = $_GET['type'] ?? 'gudang';

if ($type === 'barang_keluar') {
    $tgl_awal = $_GET['tgl_awal'] ?? date('Y-m-01');
    $tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
    $id_dapur = $_GET['id_dapur'] ?? '';

    $sql = "SELECT 
                p.id_pesanan, 
                p.tgl_pesan, 
                u.nama as nama_dapur, 
                g.nama as nama_barang, 
                dp.jumlah, 
                g.satuan,
                dp.harga_satuan,
                (dp.jumlah * dp.harga_satuan) as total_nilai
            FROM detail_pesanan dp
            JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
            JOIN gudang g ON dp.id_barang = g.id_barang
            JOIN user u ON p.id_dapur = u.id
            WHERE DATE(p.tgl_pesan) BETWEEN ? AND ?
            AND p.status_pembayaran != 'Batal'";

    if (!empty($id_dapur)) {
        $sql .= " AND p.id_dapur = " . (int)$id_dapur;
    }
    $sql .= " ORDER BY p.tgl_pesan DESC";

    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="barang_keluar_' . $tgl_awal . '_to_' . $tgl_akhir . '.xls"');
    ?>
    <html>
    <body>
        <h2 style="text-align: center;">LAPORAN BARANG KELUAR</h2>
        <p style="text-align: center;">Periode: <?= $tgl_awal ?> s/d <?= $tgl_akhir ?></p>
        <table border="1">
            <thead>
                <tr style="background: #2563EB; color: white;">
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>ID Pesanan</th>
                    <th>Dapur Tujuan</th>
                    <th>Nama Barang</th>
                    <th>Jumlah</th>
                    <th>Satuan</th>
                    <th>Harga Satuan</th>
                    <th>Total Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $t_qty = 0;
                $t_nilai = 0;
                while($row = $res->fetch_assoc()): 
                    $t_qty += $row['jumlah'];
                    $t_nilai += $row['total_nilai'];
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= date('d/m/Y', strtotime($row['tgl_pesan'])) ?></td>
                    <td>#<?= $row['id_pesanan'] ?></td>
                    <td><?= htmlspecialchars($row['nama_dapur']) ?></td>
                    <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                    <td><?= $row['jumlah'] ?></td>
                    <td><?= strtoupper($row['satuan']) ?></td>
                    <td><?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                    <td><?= number_format($row['total_nilai'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f2f2f2; font-weight: bold;">
                    <td colspan="5">TOTAL</td>
                    <td><?= number_format($t_qty, 0, ',', '.') ?></td>
                    <td></td>
                    <td></td>
                    <td>Rp <?= number_format($t_nilai, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>
    <?php
    exit;
} else {
    // LOGIKA GUDANG (EXISTING)
    $search_query = ""; 
    $sql_where = ""; 
    $sql_params_data = []; 
    $sql_types_data = "";

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_query = $koneksi->real_escape_string($_GET['search']);
        $sql_where = " WHERE (g.nama LIKE ? OR g.kategori LIKE ? OR g.keterangan LIKE ?)";
        $search_param = "%$search_query%";
        $sql_params_data = [$search_param, $search_param, $search_param];
        $sql_types_data = "sss";
    }

    $filter_suplier_id = isset($_GET['filter_suplier']) ? $_GET['filter_suplier'] : '';
    if (!empty($filter_suplier_id)) {
        if ($sql_where != "") $sql_where .= " AND g.id_suplier = ?";
        else $sql_where = " WHERE g.id_suplier = ?";
        $sql_params_data[] = $filter_suplier_id;
        $sql_types_data .= "i";
    }

    $sql = "SELECT g.nama, g.keterangan, g.kategori, g.harga_beli, g.harga, g.stok, g.satuan, s.nama_suplier 
            FROM gudang g LEFT JOIN data_suplier s ON g.id_suplier = s.id_suplier $sql_where ORDER BY g.is_pinned DESC, g.nama ASC";

    $stmt = $koneksi->prepare($sql);
    if (!empty($sql_params_data)) $stmt->bind_param($sql_types_data, ...$sql_params_data);
    $stmt->execute();
    $stmt->bind_result($res_nama, $res_ket, $res_kat, $res_hbeli, $res_hjual, $res_stok, $res_satuan, $res_suplier);

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_gudang_' . date('Y-m-d_H-i') . '.xls"');
    ?>
    <html>
    <head><meta charset="UTF-8"><style>.table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; } .table th { background-color: #2563EB; color: white; font-weight: bold; padding: 10px; text-align: center; border: 1px solid #000; } .table td { padding: 8px; border: 1px solid #000; vertical-align: top; } .number { text-align: right; } .text-center { text-align: center; } .total-row { background-color: #e8f5e8; font-weight: bold; border-top: 2px solid #000; }</style></head>
    <body>
        <h2 style="text-align: center;">LAPORAN STOK DAN ASET GUDANG</h2>
        <table class="table" border="1">
            <thead>
                <tr><th>No</th><th>Nama Barang</th><th>Kategori</th><th>Suplier</th><th>Harga Beli</th><th>Harga Jual</th><th>Margin</th><th>Stok</th><th>Satuan</th></tr>
            </thead>
            <tbody>
                <?php
                $no = 1; $total_nilai_beli = 0; $total_nilai_jual = 0; $total_margin = 0; $total_stok = 0;
                while ($stmt->fetch()) {
                    $harga_beli = $res_hbeli ? floatval($res_hbeli) : 0;
                    $harga_jual = floatval($res_hjual);
                    $margin = $harga_jual - $harga_beli;
                    $total_nilai_beli += ($harga_beli * $res_stok);
                    $total_nilai_jual += ($harga_jual * $res_stok);
                    $total_margin += ($margin * $res_stok);
                    $total_stok += $res_stok;
                    $nama_suplier = !empty($res_suplier) ? $res_suplier : 'Tanpa Suplier';
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($res_nama) ?></strong><?php if(!empty($res_ket)): ?><br><small>(<?= htmlspecialchars($res_ket) ?>)</small><?php endif; ?></td>
                        <td class="text-center"><?= htmlspecialchars($res_kat) ?></td>
                        <td class="text-center"><?= htmlspecialchars($nama_suplier) ?></td>
                        <td class="number"><?= number_format($harga_beli, 0, ',', '.') ?></td>
                        <td class="number"><?= number_format($harga_jual, 0, ',', '.') ?></td>
                        <td class="number" style="color: <?= $margin >= 0 ? 'green' : 'red' ?>;"><?= number_format($margin, 0, ',', '.') ?></td>
                        <td class="text-center"><strong><?= $res_stok ?></strong></td>
                        <td class="text-center"><?= htmlspecialchars($res_satuan) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr class="total-row"><td colspan="4" class="text-center">TOTAL KESELURUHAN</td><td class="number">Rp <?= number_format($total_nilai_beli, 0, ',', '.') ?></td><td class="number">Rp <?= number_format($total_nilai_jual, 0, ',', '.') ?></td><td class="number">Rp <?= number_format($total_margin, 0, ',', '.') ?></td><td class="text-center"><?= number_format($total_stok, 0, ',', '.') ?></td><td></td></tr>
            </tfoot>
        </table>
    </body>
    </html>
    <?php
    $stmt->close();
    $koneksi->close();
    exit;
}
?>
