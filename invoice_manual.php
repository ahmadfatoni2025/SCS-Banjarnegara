<?php
session_start();
include 'koneksi.php';
include_once 'fungsi_akuntansi.php';

// CEK LOGIN
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$role_user = isset($_SESSION['user']['role']) ? strtolower($_SESSION['user']['role']) : '';
if (!in_array($role_user, ['admin', 'owner'])) {
    echo "Akses Ditolak!"; exit;
}

$error = "";
$success = "";

// 1. AMBIL DATA BARANG UNTUK DROPDOWN
$q_barang = mysqli_query($koneksi, "SELECT id_barang, nama, harga, satuan FROM gudang ORDER BY nama ASC");
$barang_list = [];
while($row = mysqli_fetch_assoc($q_barang)) { $barang_list[] = $row; }

// 2. HANDLING POST SIMPAN
if (isset($_POST['simpan_invoice'])) {
    $nama_pelanggan = $_POST['nama_pelanggan'];
    $wa_pelanggan   = $_POST['wa_pelanggan'];
    $tgl_pesan      = $_POST['tgl_pesan'];
    $tgl_invoice    = $_POST['tgl_invoice']; // Baru
    $no_invoice     = trim($_POST['no_invoice']);
    $no_pesanan     = trim($_POST['no_pesanan']);
    
    // Auto-generate dari counter jika tidak diisi manual
    $bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
    $bulan = (int)date('m');
    $tahun = date('Y');
    
    if (empty($no_invoice)) {
        $koneksi->query("UPDATE pengaturan SET nilai = nilai + 1 WHERE kunci = 'invoice_counter'");
        $res_inv = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'invoice_counter'");
        $inv_counter = (int)$res_inv->fetch_assoc()['nilai'];
        $no_invoice = str_pad($inv_counter, 3, '0', STR_PAD_LEFT) . "/INV-D1/" . $bulan_romawi[$bulan] . "/" . $tahun;
    }
    if (empty($no_pesanan)) {
        $koneksi->query("UPDATE pengaturan SET nilai = nilai + 1 WHERE kunci = 'pesanan_counter'");
        $res_pes = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'pesanan_counter'");
        $pes_counter = (int)$res_pes->fetch_assoc()['nilai'];
        $no_pesanan = $pes_counter . "/SCS/PO-DP/" . $bulan_romawi[$bulan] . "/" . $tahun;
    }
    $tgl_digunakan  = $_POST['tgl_digunakan'];
    $catatan        = $_POST['catatan'];
    $status_bayar   = $_POST['status_pembayaran'];
    $id_user        = $_SESSION['user']['id'];
    
    // AMBIL DATA SNAPSHOT TTD & NAMA (Direct dari DB agar akurat)
    $q_u = $koneksi->query("SELECT nama, nama_ttd, tanda_tangan FROM user WHERE id = $id_user");
    $u = $q_u->fetch_assoc();
    $nama_user      = !empty($u['nama_ttd']) ? $u['nama_ttd'] : $u['nama'];
    $path_ttd       = $u['tanda_tangan'];
    $id_dapur       = $id_user; 
    $status_kirim   = ($status_bayar === 'Lunas') ? 'Done' : 'Pending';

    $items          = $_POST['items']; 

    $koneksi->begin_transaction();
    try {
        $total_harga = 0;
        foreach($items as $it) {
            $total_harga += ($it['jumlah'] * $it['harga']);
        }

        // B. Insert Header Pesanan
        $sql_p = "INSERT INTO pesanan (
                    id_dapur, no_invoice, no_pesanan, tgl_invoice, nama_pemesan, 
                    wa_pemesan, status_pembayaran, status_pengiriman, tgl_pesan, 
                    tgl_digunakan, catatan, total_harga, nama_penandatangan, 
                    id_akuntan, path_ttd, status_approval
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Approved')";
        
        $stmt_p = $koneksi->prepare($sql_p);
        $stmt_p->bind_param("isssssssssdsiss", 
            $id_dapur, $no_invoice, $no_pesanan, $tgl_invoice, $nama_pelanggan, 
            $wa_pelanggan, $status_bayar, $status_kirim, $tgl_pesan, 
            $tgl_digunakan, $catatan, $total_harga, $nama_user, 
            $id_user, $path_ttd
        );
        $stmt_p->execute();
        $id_pesanan_baru = $stmt_p->insert_id;
        $stmt_p->close();

        // C. Insert Detail Pesanan
        $stmt_d = $koneksi->prepare("INSERT INTO detail_pesanan (id_pesanan, id_barang, jumlah, harga_satuan, harga_jual_saat_itu, harga_beli_saat_itu) VALUES (?, ?, ?, ?, ?, ?)");
        foreach($items as $it) {
            $id_brg = $it['id_barang'];
            $qty = $it['jumlah'];
            $prc = $it['harga'];
            // Fetch actual cost price for HPP
            $res_g = $koneksi->query("SELECT harga_beli FROM gudang WHERE id_barang = $id_brg");
            $row_g = $res_g->fetch_assoc();
            $prc_beli = (float)($row_g['harga_beli'] ?? 0);
            
            $stmt_d->bind_param("iiiddd", $id_pesanan_baru, $id_brg, $qty, $prc, $prc, $prc_beli);
            $stmt_d->execute();
            
            // POTONG STOK (Hanya jika tipe = Stok)
            $res_tp = $koneksi->query("SELECT tipe_pengadaan FROM gudang WHERE id_barang = $id_brg");
            $row_tp = $res_tp->fetch_assoc();
            if ($row_tp && $row_tp['tipe_pengadaan'] === 'Stok') {
                $koneksi->query("UPDATE gudang SET stok = GREATEST(0, stok - $qty) WHERE id_barang = $id_brg");
            }
        }
        $stmt_d->close();

        $koneksi->commit();
        header("Location: cetak_invoice.php?id=$id_pesanan_baru");
        exit;

    } catch (Exception $e) {
        $koneksi->rollback();
        $error = "Gagal menyimpan: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo_scs_jpg.png">`n    <title>Input Invoice Manual - SCS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .form-card { background: white; border-radius: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #E2E8F0; }
    </style>
</head>
<body class="p-6 md:p-12">

    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Input Invoice Manual</h1>
                <p class="text-slate-500 mt-1">Buat pesanan & invoice kustom tanpa melalui sistem Dapur.</p>
            </div>
            <a href="laporanPenjualan.php" class="text-slate-500 hover:text-blue-600 font-bold transition">
                <i class="fas fa-arrow-left mr-2"></i> Kembali
            </a>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-8 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span class="font-bold"><?= $error ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="invoiceForm">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- SISI KIRI: DATA PELANGGAN -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="form-card p-6">
                        <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                            <i class="fas fa-user-circle text-blue-600"></i> Informasi Pelanggan
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Nama Pelanggan / SPPG</label>
                                <input type="text" name="nama_pelanggan" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-slate-700" placeholder="Contoh: SPPG Banjarnegara" required>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Nomor WhatsApp</label>
                                <input type="text" name="wa_pelanggan" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-slate-700" placeholder="0812xxx" required>
                            </div>
                            <div class="pt-4 border-t border-slate-100">
                                <h4 class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-4 italic">Detail Dokumen (Manual)</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Nomor Invoice</label>
                                        <input type="text" name="no_invoice" class="w-full p-3 bg-blue-50/50 border border-blue-100 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-blue-900 text-xs" placeholder="Contoh: 035/INV-D1/V/2026">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Nomor Pesanan</label>
                                        <input type="text" name="no_pesanan" class="w-full p-3 bg-blue-50/50 border border-blue-100 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-blue-900 text-xs" placeholder="Contoh: 35/SCS/PO-DP/V/2026">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Tanggal Invoice</label>
                                        <input type="date" name="tgl_invoice" value="<?= date('Y-m-d') ?>" class="w-full p-3 bg-blue-50/50 border border-blue-100 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-blue-900 text-xs">
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 pt-4 border-t border-slate-100">
                                <div>
                                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Tgl Pesan</label>
                                    <input type="date" name="tgl_pesan" value="<?= date('Y-m-d') ?>" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-slate-700 text-xs" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Tgl Digunakan</label>
                                    <input type="date" name="tgl_digunakan" value="<?= date('Y-m-d') ?>" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-slate-700 text-xs" required>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Status Pembayaran</label>
                                <select name="status_pembayaran" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-bold text-slate-700">
                                    <option value="Lunas">Lunas</option>
                                    <option value="Belum Bayar">Belum Bayar</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-card p-6">
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Catatan Tambahan (Opsional)</label>
                        <textarea name="catatan" rows="3" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none font-medium text-slate-600 text-sm" placeholder="Misal: Barang diantar ke lobby..."></textarea>
                    </div>
                </div>

                <!-- SISI KANAN: RINCIAN BARANG -->
                <div class="lg:col-span-2">
                    <div class="form-card p-8 min-h-full flex flex-col">
                        <div class="flex justify-between items-center mb-8">
                            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                                <i class="fas fa-shopping-basket text-emerald-600"></i> Rincian Barang
                            </h3>
                            <button type="button" onclick="tambahBaris()" class="bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white px-4 py-2 rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                                <i class="fas fa-plus"></i> Tambah Item
                            </button>
                        </div>

                        <div class="overflow-x-auto flex-grow">
                            <table class="w-full text-left border-separate border-spacing-y-2" id="tabelItem" style="min-width: 800px;">
                                <thead>
                                    <tr class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                        <th class="pb-2 pl-2">Nama Barang</th>
                                        <th class="pb-2 w-20 text-center">Jumlah</th>
                                        <th class="pb-2 w-36 text-right">Harga Satuan</th>
                                        <th class="pb-2 w-36 text-right pr-4">Subtotal</th>
                                        <th class="pb-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <!-- Dinamis oleh JS -->
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-10 pt-8 border-t border-slate-100">
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-extrabold text-slate-900 uppercase tracking-widest">Grand Total</span>
                                <span class="text-4xl font-black text-blue-600" id="grandTotalDisplay">Rp0</span>
                            </div>
                            <button type="submit" name="simpan_invoice" class="w-full mt-8 bg-blue-600 hover:bg-blue-700 text-white py-5 rounded-2xl font-black text-lg shadow-xl shadow-blue-200 transition-all active:scale-95 flex items-center justify-center gap-3">
                                <i class="fas fa-save"></i> SIMPAN & CETAK INVOICE
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- Hidden Template for JS -->
    <template id="barisTemplate">
        <tr class="group item-row bg-slate-50/50 hover:bg-white transition-all">
            <td class="py-3 px-2">
                <select name="items[{INDEX}][id_barang]" class="w-full p-3 bg-white border border-slate-200 rounded-xl outline-none text-sm font-bold select-barang focus:ring-2 focus:ring-blue-500 transition-all shadow-sm" required onchange="updateHarga(this, {INDEX})">
                    <option value="">-- Pilih Barang --</option>
                    <?php foreach($barang_list as $b): ?>
                        <option value="<?= $b['id_barang'] ?>" data-harga="<?= $b['harga'] ?>" data-satuan="<?= $b['satuan'] ?>">
                            <?= $b['nama'] ?> (<?= $b['satuan'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="py-3 px-2">
                <input type="number" name="items[{INDEX}][jumlah]" value="1" min="1" class="w-full p-3 bg-white border border-slate-200 rounded-xl text-center font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500 input-jumlah shadow-sm" required oninput="hitungSubtotal(this, {INDEX})">
            </td>
            <td class="py-3 px-2">
                <div class="relative">
                    <span class="absolute left-3 top-3 text-[10px] text-slate-400 font-bold">Rp</span>
                    <input type="number" name="items[{INDEX}][harga]" class="w-full pl-8 p-3 bg-white border border-slate-200 rounded-xl text-right font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500 input-harga shadow-sm" required oninput="hitungSubtotal(this, {INDEX})">
                </div>
            </td>
            <td class="py-3 px-4 text-right">
                <span class="font-black text-slate-900 text-sm display-subtotal">Rp0</span>
            </td>
            <td class="py-3 text-center">
                <button type="button" onclick="hapusBaris(this)" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-300 hover:bg-red-50 hover:text-red-500 transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </td>
        </tr>
    </template>

    <script>
        let rowCount = 0;

        function tambahBaris() {
            const template = document.getElementById('barisTemplate').innerHTML;
            const html = template.replace(/{INDEX}/g, rowCount);
            document.querySelector('#tabelItem tbody').insertAdjacentHTML('beforeend', html);
            rowCount++;
        }

        function hapusBaris(btn) {
            btn.closest('tr').remove();
            updateGrandTotal();
        }

        function updateHarga(select, index) {
            const selected = select.options[select.selectedIndex];
            const harga = selected.dataset.harga || 0;
            const row = select.closest('tr');
            row.querySelector('.input-harga').value = Math.round(harga);
            hitungSubtotal(select, index);
        }

        function hitungSubtotal(el, index) {
            const row = el.closest('tr');
            const jumlah = row.querySelector('.input-jumlah').value || 0;
            const harga = row.querySelector('.input-harga').value || 0;
            const subtotal = jumlah * harga;
            
            row.querySelector('.display-subtotal').innerText = 'Rp' + new Intl.NumberFormat('id-ID').format(subtotal);
            updateGrandTotal();
        }

        function updateGrandTotal() {
            let total = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const jumlah = row.querySelector('.input-jumlah').value || 0;
                const harga = row.querySelector('.input-harga').value || 0;
                total += (jumlah * harga);
            });
            document.getElementById('grandTotalDisplay').innerText = 'Rp' + new Intl.NumberFormat('id-ID').format(total);
        }

        // Init satu baris di awal
        tambahBaris();

        document.getElementById('invoiceForm').onsubmit = function() {
            if (document.querySelectorAll('.item-row').length === 0) {
                Swal.fire('Oops!', 'Minimal harus ada 1 item barang.', 'warning');
                return false;
            }
            return true;
        };
    </script>

</body>
</html>
