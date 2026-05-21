<?php
// 1. BUFFERING: Tahan semua output agar tidak bocor ke JSON (PENTING!)
ob_start();

// Matikan error display global sementara agar header aman
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
include 'koneksi.php';

// --- LOAD FUNGSI AKUNTANSI (Untuk Admin) ---
include_once 'fungsi_akuntansi.php';

// --- HELPER: Nuclear Clean JSON Output ---
function json_clean_output($data)
{
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Variabel untuk pesan feedback
$feedback_message = '';
$feedback_type = '';

// =========================================================================
// === [API] LOGIKA UPDATE PESANAN (EDIT) DARI DAPUR ===
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    ob_end_clean();
    header('Content-Type: application/json');
    ini_set('display_errors', 0);

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur' || !isset($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login sebagai Dapur.']);
        exit();
    }

    $id_dapur = (int)$_SESSION['user']['id'];
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Format data JSON tidak valid.']);
        exit();
    }

    $cart = $data['cart'] ?? [];
    $customerData = $data['customerData'] ?? [];
    $editOrderId = isset($data['editOrderId']) ? (int)$data['editOrderId'] : 0;

    if ($editOrderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID pesanan tidak valid.']);
        exit();
    }

    $nama_pemesan = $customerData['nama'] ?? '';
    $email_pemesan = $customerData['email'] ?? null;
    $wa_pemesan = $customerData['wa'] ?? '';
    $tgl_digunakan = $customerData['tgl_digunakan'] ?? null;
    $catatan = $customerData['catatan'] ?? null;

    if (empty($nama_pemesan) || empty($wa_pemesan) || empty($tgl_digunakan)) {
        echo json_encode(['success' => false, 'message' => 'Nama, WhatsApp, dan Tanggal Digunakan wajib diisi.']);
        exit();
    }

    if (empty($cart)) {
        echo json_encode(['success' => false, 'message' => 'Keranjang pesanan kosong.']);
        exit();
    }

    $total_harga = 0;
    $valid_items = [];

    foreach ($cart as $id_barang => $item) {
        $id_final = isset($item['id_barang']) ? $item['id_barang'] : $id_barang;

        $harga = floatval($item['harga'] ?? 0);
        $jumlah = intval($item['jumlah'] ?? 0);
        $stok = intval($item['stok'] ?? 0);
        $nama = $item['nama'] ?? '';
        $satuan = $item['satuan'] ?? '';

        $tgl_kirim = !empty($item['tgl_pengiriman']) ? $item['tgl_pengiriman'] : null;

        if ($jumlah <= 0) continue;

        // VERIFIKASI ID BARANG (Mencegah FK Constraint Fail)
        $stmt_v = $koneksi->prepare("SELECT 1 FROM gudang WHERE id_barang = ?");
        $stmt_v->bind_param("i", $id_final);
        $stmt_v->execute();
        if ($stmt_v->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => "Barang dengan ID $id_final tidak ditemukan di gudang."]);
            exit();
        }
        $stmt_v->close();

        $subtotal = $harga * $jumlah;
        $total_harga += $subtotal;

        $valid_items[] = [
            'id_barang' => $id_final,
            'harga' => $harga,
            'jumlah' => $jumlah,
            'subtotal' => $subtotal,
            'nama' => $nama,
            'satuan' => $satuan,
            'stok' => $stok,
            'tgl_pengiriman' => $tgl_kirim
        ];
    }

    if ($total_harga <= 0) {
        echo json_encode(['success' => false, 'message' => 'Total harga tidak valid.']);
        exit();
    }

    $sql_cek = "SELECT id_pesanan FROM pesanan WHERE id_pesanan = ? AND status_pembayaran = 'Belum Bayar' AND status_pengiriman = 'Pending'";
    $stmt_cek = $koneksi->prepare($sql_cek);
    $stmt_cek->bind_param("i", $editOrderId);
    $stmt_cek->execute();
    $stmt_cek->store_result();

    if ($stmt_cek->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak ditemukan atau sudah diproses (Lunas/Dikirim).']);
        exit();
    }
    $stmt_cek->close();

    $koneksi->begin_transaction();

    try {
        $sql_detail_lama = "SELECT id_barang, jumlah FROM detail_pesanan WHERE id_pesanan = ?";
        $stmt_detail_lama = $koneksi->prepare($sql_detail_lama);
        $stmt_detail_lama->bind_param("i", $editOrderId);
        $stmt_detail_lama->execute();
        $stmt_detail_lama->bind_result($old_id, $old_qty);

        $detail_lama = [];
        while ($stmt_detail_lama->fetch()) {
            $detail_lama[] = ['id' => $old_id, 'qty' => $old_qty];
        }
        $stmt_detail_lama->close();

        $sql_hapus_detail = "DELETE FROM detail_pesanan WHERE id_pesanan = ?";
        $stmt_hapus_detail = $koneksi->prepare($sql_hapus_detail);
        $stmt_hapus_detail->bind_param("i", $editOrderId);

        if (!$stmt_hapus_detail->execute()) {
            throw new Exception("Gagal menghapus detail pesanan lama: " . $stmt_hapus_detail->error);
        }
        $stmt_hapus_detail->close();

        $sql_update_pesanan = "UPDATE pesanan SET nama_pemesan = ?, email_pemesan = ?, wa_pemesan = ?, tgl_digunakan = ?, catatan = ?, total_harga = ? WHERE id_pesanan = ?";
        $stmt_update_pesanan = $koneksi->prepare($sql_update_pesanan);
        $stmt_update_pesanan->bind_param(
            "sssssdi",
            $nama_pemesan,
            $email_pemesan,
            $wa_pemesan,
            $tgl_digunakan,
            $catatan,
            $total_harga,
            $editOrderId
        );

        if (!$stmt_update_pesanan->execute()) {
            throw new Exception("Gagal update data pesanan: " . $stmt_update_pesanan->error);
        }
        $stmt_update_pesanan->close();

        // --- GENERATE SJ NUMBERS (Per delivery date) ---
        $bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
        $bulan = (int)date('m');
        $tahun = date('Y');
        $sj_mapping = [];
        foreach ($valid_items as $item) {
            $d = !empty($item['tgl_pengiriman']) ? $item['tgl_pengiriman'] : 'none';
            if (!isset($sj_mapping[$d])) {
                $koneksi->query("UPDATE pengaturan SET nilai = nilai + 1 WHERE kunci = 'sj_counter'");
                $res_sj = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'sj_counter'");
                $sj_val = (int)$res_sj->fetch_assoc()['nilai'];
                $sj_mapping[$d] = str_pad($sj_val, 3, '0', STR_PAD_LEFT) . "/SJ-SCS/" . $bulan_romawi[$bulan] . "/" . $tahun;
            }
        }

        // --- INTEGRASI PELANGGAN (Update data pemesan) ---
        $sql_upsert_pelanggan = "INSERT INTO pelanggan (nama_pelanggan, wa_pelanggan, email_pelanggan) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE nama_pelanggan = VALUES(nama_pelanggan), email_pelanggan = VALUES(email_pelanggan)";
        $stmt_upsert = $koneksi->prepare($sql_upsert_pelanggan);
        $stmt_upsert->bind_param("sss", $nama_pemesan, $wa_pemesan, $email_pemesan);
        $stmt_upsert->execute();
        $stmt_upsert->close();

        $sql_insert_detail = "INSERT INTO detail_pesanan (id_pesanan, id_barang, jumlah, harga_satuan, harga_jual_saat_itu, harga_beli_saat_itu, tgl_pengiriman, no_sj) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_detail = $koneksi->prepare($sql_insert_detail);

        $total_items_saved = 0;
        foreach ($valid_items as $item) {
            $id_barang_int = (int)$item['id_barang'];
            $jumlah = (int)$item['jumlah'];
            $harga_satuan = (float)$item['harga'];

            if ($jumlah <= 0) continue;

            // Fetch current cost price from gudang
            $stmt_v = $koneksi->prepare("SELECT harga_beli FROM gudang WHERE id_barang = ?");
            $stmt_v->bind_param("i", $id_barang_int);
            $stmt_v->execute();
            $res_v = $stmt_v->get_result();
            $row_v = $res_v->fetch_assoc();
            $harga_beli = (float)($row_v['harga_beli'] ?? 0);
            $stmt_v->close();

            $tgl_kirim = $item['tgl_pengiriman'] ?? null;
            $no_sj_item = $sj_mapping[$tgl_kirim ?? 'none'];
            $stmt_insert_detail->bind_param(
                "iiidddss",
                $editOrderId,
                $id_barang_int,
                $jumlah,
                $harga_satuan,
                $harga_satuan,
                $harga_beli,
                $tgl_kirim,
                $no_sj_item
            );

            if (!$stmt_insert_detail->execute()) {
                throw new Exception("Gagal menyimpan detail pesanan: " . $stmt_insert_detail->error);
            }

            $total_items_saved++;
        }
        $stmt_insert_detail->close();

        if ($total_items_saved == 0) {
            throw new Exception("Tidak ada item yang valid untuk disimpan.");
        }

        $koneksi->commit();

        echo json_encode([
            'success' => true,
            'message' => "Pesanan berhasil diupdate! No. Pesanan: #{$editOrderId}",
            'id_pesanan' => $editOrderId
        ]);
    } catch (Exception $e) {
        $koneksi->rollback();
        error_log("Error Update: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengupdate pesanan: ' . $e->getMessage()
        ]);
    }
    exit();
}

// =========================================================================
// === [API] LOGIKA SIMPAN PESANAN BARU ===
// =========================================================================
else if (isset($_GET['action']) && $_GET['action'] === 'simpan' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    ob_end_clean();
    header('Content-Type: application/json');
    ini_set('display_errors', 0);

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur' || !isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login sebagai Dapur.']);
        exit();
    }

    $id_dapur = (int)$_SESSION['user']['id'];
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $cart = $data['cart'] ?? [];
    $customerData = $data['customerData'] ?? [];

    $nama_pemesan = $customerData['nama'] ?? '';
    $email_pemesan = $customerData['email'] ?? null;
    $wa_pemesan = $customerData['wa'] ?? '';
    $tgl_digunakan = $customerData['tgl_digunakan'] ?? null;
    $catatan = $customerData['catatan'] ?? null;

    if (empty($nama_pemesan) || empty($wa_pemesan) || empty($tgl_digunakan)) {
        echo json_encode(['success' => false, 'message' => 'Nama, WhatsApp, dan Tanggal Digunakan wajib diisi.']);
        exit();
    }

    if (empty($cart)) {
        echo json_encode(['success' => false, 'message' => 'Keranjang pesanan kosong.']);
        exit();
    }

    $total_harga = 0;
    foreach ($cart as $item) {
        $total_harga += ($item['jumlah'] * $item['harga']);
    }

    $koneksi->begin_transaction();
    try {
        // AUTO-GENERATE NOMOR INVOICE & PESANAN dari counter terpisah
        $bulan_romawi = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
        $bulan = (int)date('m');
        $tahun = date('Y');

        // Atomic increment invoice counter
        $koneksi->query("UPDATE pengaturan SET nilai = nilai + 1 WHERE kunci = 'invoice_counter'");
        $res_inv = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'invoice_counter'");
        $inv_counter = (int)$res_inv->fetch_assoc()['nilai'];

        // Atomic increment pesanan counter
        $koneksi->query("UPDATE pengaturan SET nilai = nilai + 1 WHERE kunci = 'pesanan_counter'");
        $res_pes = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'pesanan_counter'");
        $pes_counter = (int)$res_pes->fetch_assoc()['nilai'];

        $no_invoice_gen = str_pad($inv_counter, 3, '0', STR_PAD_LEFT) . "/INV-D1/" . $bulan_romawi[$bulan] . "/" . $tahun;
        $no_pesanan_gen = $pes_counter . "/SCS/PO-DP/" . $bulan_romawi[$bulan] . "/" . $tahun;

        // --- GENERATE SJ NUMBERS (Per delivery date) ---
        $sj_mapping = [];
        foreach ($cart as $item) {
            $d = !empty($item['tgl_pengiriman']) ? $item['tgl_pengiriman'] : 'none';
            if (!isset($sj_mapping[$d])) {
                $koneksi->query("UPDATE pengaturan SET nilai = nilai + 1 WHERE kunci = 'sj_counter'");
                $res_sj = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'sj_counter'");
                $sj_val = (int)$res_sj->fetch_assoc()['nilai'];
                $sj_mapping[$d] = str_pad($sj_val, 3, '0', STR_PAD_LEFT) . "/SJ-SCS/" . $bulan_romawi[$bulan] . "/" . $tahun;
            }
        }

        $query_pesanan = "INSERT INTO pesanan (id_dapur, nama_pemesan, email_pemesan, wa_pemesan, tgl_digunakan, catatan, total_harga, no_invoice, no_pesanan, status_pembayaran, status_pengiriman, tgl_pesan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Belum Bayar', 'Pending', NOW())";

        $stmt_pesanan = $koneksi->prepare($query_pesanan);
        if (!$stmt_pesanan) throw new Exception("Gagal prepare: " . $koneksi->error);

        $stmt_pesanan->bind_param("isssssdss", $id_dapur, $nama_pemesan, $email_pemesan, $wa_pemesan, $tgl_digunakan, $catatan, $total_harga, $no_invoice_gen, $no_pesanan_gen);

        if (!$stmt_pesanan->execute()) throw new Exception("Gagal insert pesanan: " . $stmt_pesanan->error);

        $id_pesanan_baru = $koneksi->insert_id;
        $stmt_pesanan->close();

        // --- INTEGRASI PELANGGAN (Simpan data pemesan baru) ---
        $sql_upsert_pelanggan = "INSERT INTO pelanggan (nama_pelanggan, wa_pelanggan, email_pelanggan) 
                                VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE nama_pelanggan = VALUES(nama_pelanggan), email_pelanggan = VALUES(email_pelanggan)";
        $stmt_upsert = $koneksi->prepare($sql_upsert_pelanggan);
        $stmt_upsert->bind_param("sss", $nama_pemesan, $wa_pemesan, $email_pemesan);
        $stmt_upsert->execute();
        $stmt_upsert->close();

        if (!$id_pesanan_baru) throw new Exception("Gagal mendapatkan ID pesanan baru.");

        $stmt_detail = $koneksi->prepare("INSERT INTO detail_pesanan (id_pesanan, id_barang, jumlah, harga_satuan, harga_jual_saat_itu, harga_beli_saat_itu, tgl_pengiriman, no_sj) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($cart as $id => $item) {
            $id_barang_int = (int)($item['id_barang'] ?? $id);
            $jumlah = (int)$item['jumlah'];
            $harga_satuan = (float)$item['harga'];
            $harga_beli = 0;
            $tgl_kirim = !empty($item['tgl_pengiriman']) ? $item['tgl_pengiriman'] : null;

            if ($jumlah > 0) {
                // VERIFIKASI ID BARANG & AMBIL HARGA BELI
                $stmt_v = $koneksi->prepare("SELECT harga_beli FROM gudang WHERE id_barang = ?");
                $stmt_v->bind_param("i", $id_barang_int);
                $stmt_v->execute();
                $res_v = $stmt_v->get_result();
                if ($res_v->num_rows === 0) {
                    throw new Exception("Barang dengan ID $id_barang_int tidak ditemukan di gudang. Silakan refresh halaman.");
                }
                $row_v = $res_v->fetch_assoc();
                $harga_beli = (float)($row_v['harga_beli'] ?? 0);
                $stmt_v->close();

                $no_sj_item = $sj_mapping[$tgl_kirim ?? 'none'];
                $stmt_detail->bind_param("iiidddss", $id_pesanan_baru, $id_barang_int, $jumlah, $harga_satuan, $harga_satuan, $harga_beli, $tgl_kirim, $no_sj_item);
                $stmt_detail->execute();
            }
        }
        $stmt_detail->close();

        $koneksi->commit();

        echo json_encode([
            'success' => true,
            'message' => "Pesanan berhasil disimpan! No. Pesanan: #{$id_pesanan_baru}",
            'id_pesanan' => $id_pesanan_baru
        ]);
    } catch (Exception $e) {
        $koneksi->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal simpan: ' . $e->getMessage()]);
    }
    exit();
}

// =========================================================================
// === API PENCARIAN REALTIME ===
// =========================================================================
else if (isset($_GET['action']) && $_GET['action'] === 'search_realtime' && isset($_GET['keyword'])) {
    ob_end_clean();
    header('Content-Type: application/json');

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit();
    }

    $keyword = trim($_GET['keyword'] ?? '');
    $filter_tanggal = $_GET['tanggal'] ?? '';
    $filter_nama_dapur = $_GET['filter_nama'] ?? '';
    $filter_card = $_GET['filter'] ?? null;

    $sql_pesanan = "SELECT P.*, U.nama as nama_dapur 
                    FROM pesanan AS P 
                    LEFT JOIN user AS U ON P.id_dapur = U.id
                    WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filter_tanggal)) {
        $sql_pesanan .= " AND DATE(P.tgl_pesan) = ?";
        $params[] = $filter_tanggal;
        $types .= "s";
    }
    if (!empty($filter_nama_dapur)) {
        $sql_pesanan .= " AND U.nama LIKE ?";
        $params[] = "%$filter_nama_dapur%";
        $types .= "s";
    }
    if ($filter_card === 'lunas') {
        $sql_pesanan .= " AND P.status_pembayaran = 'Lunas'";
    }
    if ($filter_card === 'belum_bayar') {
        $sql_pesanan .= " AND P.status_pembayaran = 'Belum Bayar'";
    }
    if ($filter_card === 'selesai') {
        $sql_pesanan .= " AND P.status_pengiriman = 'Done'";
    }

    if (!empty($keyword)) {
        if (is_numeric($keyword)) {
            $sql_pesanan .= " AND P.id_pesanan = ?";
            $params[] = $keyword;
            $types .= "i";
        } else {
            $sql_pesanan .= " AND (P.nama_pemesan LIKE ? OR U.nama LIKE ?)";
            $search_val = "%$keyword%";
            $params[] = $search_val;
            $params[] = $search_val;
            $types .= "ss";
        }
    }

    $sql_pesanan .= " ORDER BY P.tgl_pesan DESC";

    $stmt = $koneksi->prepare($sql_pesanan);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];

    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id_pesanan'],
            'tanggal' => date('d F Y H:i', strtotime($row['tgl_pesan'])),
            'status_pembayaran' => $row['status_pembayaran'],
            'status_pengiriman' => $row['status_pengiriman'],
            'total_harga' => $row['total_harga'],
            'nama_dapur' => $row['nama_dapur'] ?? 'User Dihapus',
            'wa_pemesan' => $row['wa_pemesan']
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => count($data)
    ]);
    exit();
}

// =========================================================================
// === BAGIAN TAMPILAN ADMIN (HTML) ===
// =========================================================================

// FLUSH BUFFER DIBUKA SETELAH LOGIKA REDIRECT

// LOGIKA REDIRECT (UPDATE STATUS OLEH ADMIN)
if (isset($_GET['action']) && ($_GET['action'] == 'update_bayar' || $_GET['action'] == 'update_status' || $_GET['action'] == 'assign_driver')) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: login.php");
        exit;
    }
    $id = (int)$_GET['id'];

    if ($_GET['action'] == 'assign_driver') {
        $nopol = $_POST['nopol_driver'] ?? '-';
        $driver = $_POST['nama_driver'] ?? '-';
        $hp = $_POST['no_hp_driver'] ?? '-';

        $stmt = $koneksi->prepare("UPDATE pesanan SET nopol_driver = ?, nama_driver = ?, no_hp_driver = ? WHERE id_pesanan = ?");
        $stmt->bind_param("sssi", $nopol, $driver, $hp, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: laporanPenjualan.php?feedback=Driver Berhasil Ditugaskan&type=success");
        exit();
    }
    $status = $_GET['status'];

    if ($_GET['action'] == 'update_bayar') {
        $id_user = $_SESSION['user']['id'];

        // AMBIL DATA SNAPSHOT TTD & NAMA (Direct dari DB agar akurat)
        $q_u = $koneksi->query("SELECT nama, nama_ttd, tanda_tangan FROM user WHERE id = $id_user");
        $u = $q_u->fetch_assoc();
        $nama_user = !empty($u['nama_ttd']) ? $u['nama_ttd'] : $u['nama'];
        $path_ttd  = $u['tanda_tangan'];

        // Jika lunas, otomatis set status_pengiriman jadi 'Done'
        if ($status === 'Lunas') {
            $akun = isset($_GET['akun']) ? $_GET['akun'] : '1111'; // Default Kas
            $stmt = $koneksi->prepare("UPDATE pesanan SET status_pembayaran = ?, status_pengiriman = 'Done', id_akuntan = ?, nama_penandatangan = ?, path_ttd = ? WHERE id_pesanan = ?");
            $stmt->bind_param("sissi", $status, $id_user, $nama_user, $path_ttd, $id);
            $stmt->execute();
            $stmt->close();

            // Generate Jurnal Akuntansi otomatis
            triggerJurnalPenjualan($koneksi, $id, $akun);
        } else {
            $stmt = $koneksi->prepare("UPDATE pesanan SET status_pembayaran = ?, id_akuntan = ?, nama_penandatangan = ?, path_ttd = ? WHERE id_pesanan = ?");
            $stmt->bind_param("sissi", $status, $id_user, $nama_user, $path_ttd, $id);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmt = $koneksi->prepare("UPDATE pesanan SET status_pengiriman = ? WHERE id_pesanan = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: laporanPenjualan.php?feedback=Update Sukses&type=success");
    exit();
}

// FLUSH BUFFER AGAR HTML BISA TAMPIL
ob_end_flush();

// --- AMBIL DAFTAR AKUN COA UNTUK DROPDOWN (KAS & PIUTANG) ---
$list_kas = [];
$list_piutang = [];

// Kita ambil semua yang depannya 111 (Kas/Bank) dan 112 (Piutang)
$q_coa = $koneksi->query("SELECT kode_akun, nama_akun FROM akun_coa WHERE kode_akun LIKE '111%' OR kode_akun LIKE '112%' ORDER BY kode_akun ASC");

while ($r = $q_coa->fetch_assoc()) {
    // Pisahkan berdasarkan awalan kode
    if (strpos($r['kode_akun'], '111') === 0) {
        $list_kas[] = $r;
    } else if (strpos($r['kode_akun'], '112') === 0) {
        $list_piutang[] = $r;
    }
}

// --- KEAMANAN LAPORAN (Admin) ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (isset($_GET['feedback'])) {
    $feedback_message = htmlspecialchars($_GET['feedback']);
    $feedback_type = $_GET['type'] ?? 'info';
}

// --- FILTER ---
$search = trim($_GET['search'] ?? '');
$filter_tanggal = $_GET['tanggal'] ?? '';
$filter_nama_dapur = trim($_GET['filter_nama'] ?? '');
$filter_card = $_GET['filter'] ?? null;
$show_laporan_harian = empty($filter_nama_dapur);

// --- LAPORAN HARIAN ---
$laporan_harian = [];
if ($show_laporan_harian) {
    $master_dapur = [];
    $dapur_sudah_pesan_ids = [];

    $res = $koneksi->query("SELECT id, nama FROM user WHERE role = 'dapur' ORDER BY nama ASC");
    while ($row = $res->fetch_assoc()) $master_dapur[$row['id']] = $row['nama'];

    $res2 = $koneksi->query("SELECT DISTINCT id_dapur FROM pesanan WHERE DATE(tgl_pesan) = CURDATE()");
    while ($row = $res2->fetch_assoc()) $dapur_sudah_pesan_ids[] = $row['id_dapur'];

    foreach ($master_dapur as $id => $nama) {
        $laporan_harian[] = ['nama' => $nama, 'status' => in_array($id, $dapur_sudah_pesan_ids) ? 'Sudah' : 'Belum'];
    }
}

// --- DATA RIWAYAT ---
$riwayat_pembelian = [];
$sql_pesanan = "SELECT P.*, U.nama as nama_dapur 
                FROM pesanan AS P 
                LEFT JOIN user AS U ON P.id_dapur = U.id
                WHERE 1=1";

if (!empty($filter_tanggal)) $sql_pesanan .= " AND DATE(P.tgl_pesan) = '$filter_tanggal'";
if (!empty($filter_nama_dapur)) $sql_pesanan .= " AND U.nama LIKE '%$filter_nama_dapur%'";
if ($filter_card === 'lunas') $sql_pesanan .= " AND P.status_pembayaran = 'Lunas'";
if ($filter_card === 'belum_bayar') $sql_pesanan .= " AND P.status_pembayaran = 'Belum Bayar'";
if ($filter_card === 'selesai') $sql_pesanan .= " AND P.status_pengiriman = 'Done'";
if (!empty($search)) {
    $sql_pesanan .= " AND (P.id_pesanan = '$search' OR P.nama_pemesan LIKE '%$search%')";
}

$sql_pesanan .= " ORDER BY P.id_pesanan DESC";

$res_main = $koneksi->query($sql_pesanan);
if ($res_main) {
    while ($row = $res_main->fetch_assoc()) {
        // Build fallback no_invoice & no_pesanan jika belum tersimpan di DB
        $bulan_romawi_lp = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
        $m_lp = (int)date('m', strtotime($row['tgl_pesan']));
        $y_lp = date('Y', strtotime($row['tgl_pesan']));
        $no_inv_display  = !empty($row['no_invoice'])  ? $row['no_invoice']  : str_pad($row['id_pesanan'], 3, '0', STR_PAD_LEFT) . '/INV-D1/' . $bulan_romawi_lp[$m_lp] . '/' . $y_lp;
        $no_pes_display  = !empty($row['no_pesanan'])  ? $row['no_pesanan']  : '';

        $riwayat_pembelian[] = [
            'id' => $row['id_pesanan'],
            'tanggal' => date('d F Y H:i', strtotime($row['tgl_pesan'])),
            'status_pembayaran' => $row['status_pembayaran'],
            'status_pengiriman' => $row['status_pengiriman'],
            'total_harga' => $row['total_harga'],
            'nama_dapur' => $row['nama_dapur'] ?? 'User Dihapus',
            'nama_pemesan' => $row['nama_pemesan'] ?? '-',
            'nopol_driver' => $row['nopol_driver'] ?? '-',
            'nama_driver' => $row['nama_driver'] ?? '-',
            'no_hp_driver' => $row['no_hp_driver'] ?? '-',
            'wa_pemesan' => $row['wa_pemesan'],
            'no_invoice' => $no_inv_display,
            'no_pesanan' => $no_pes_display,
        ];
    }
}

// Hitung Statistik
$res_count = $koneksi->query("SELECT status_pembayaran, status_pengiriman FROM pesanan");
$total_pesanan_count = 0;
$lunas_count = 0;
$belum_bayar_count = 0;
$selesai_count = 0;
while ($r = $res_count->fetch_assoc()) {
    $total_pesanan_count++;
    if ($r['status_pembayaran'] == 'Lunas') $lunas_count++;
    if ($r['status_pembayaran'] == 'Belum Bayar') $belum_bayar_count++;
    if ($r['status_pengiriman'] == 'Done') $selesai_count++;
}

$base_query_params = ['filter_nama' => $filter_nama_dapur, 'search' => $search, 'tanggal' => $filter_tanggal];
$base_query_string = http_build_query(array_filter($base_query_params));
if (!empty($base_query_string)) $base_query_string .= '&';

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor & Eksekusi Pesanan - PT. SURYA CERAH SEMESTA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #F8F9FB;
            color: #1E293B;
        }

        .glass-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid #E8ECF0;
        }

        .badge {
            padding: 0.3rem 0.75rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.65rem;
            letter-spacing: 0.02em;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        .badge-pending {
            background-color: #EFF6FF;
            color: #2563EB;
        }

        .badge-pending::before {
            background-color: #2563EB;
        }

        .badge-done {
            background-color: #ECFDF5;
            color: #059669;
        }

        .badge-done::before {
            background-color: #059669;
        }

        .badge-belum-bayar {
            background-color: #FFFBEB;
            color: #D97706;
        }

        .badge-belum-bayar::before {
            background-color: #D97706;
        }

        .badge-lunas {
            background-color: #ECFDF5;
            color: #059669;
        }

        .badge-lunas::before {
            background-color: #059669;
        }

        .badge-batal {
            background-color: #FEF2F2;
            color: #DC2626;
        }

        .badge-batal::before {
            background-color: #DC2626;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(6px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #searchLoading {
            display: none;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #E8ECF0;
            border-radius: 1rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .stat-card:hover {
            border-color: #16A34A;
        }

        .stat-card.active {
            background: #16A34A;
            border-color: #16A34A;
        }

        .stat-card.active * {
            color: #fff !important;
        }

        .stat-card.active .stat-icon {
            background: rgba(255, 255, 255, 0.2) !important;
            color: #fff !important;
        }

        .order-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .order-table thead th {
            background: #F8F9FB;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748B;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #E8ECF0;
            white-space: nowrap;
        }

        .order-table tbody tr {
            transition: background-color 0.15s;
        }

        .order-table tbody tr:hover {
            background-color: #F8FAF8;
        }

        .order-table tbody td {
            padding: 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }

        .order-table tbody tr:last-child td {
            border-bottom: none;
        }

        .btn-action {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.4rem 0.7rem;
            border-radius: 0.5rem;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            text-decoration: none;
        }
    </style>
</head>

<body class="min-h-screen">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 min-h-screen p-4 md:p-8 md:ml-64 transition-all duration-300">

        <!-- Consolidated Header Card -->
        <div class="glass-card mb-6 fade-in overflow-hidden">
            <!-- Title Bar -->
            <div class="px-6 py-5 border-b border-slate-100">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-bold text-slate-900">Monitor Pesanan</h1>
                        <span class="text-xs font-medium text-slate-400 bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-full">
                            <?php echo date('d M Y'); ?>
                        </span>
                        <span class="badge badge-done">Active</span>
                    </div>
                    <?php if ($show_laporan_harian): ?>
                        <span class="text-xs text-slate-400 font-medium">
                            <i class="fas fa-utensils mr-1"></i> <?php echo $count_sudah ?? 0; ?> / <?php echo count($laporan_harian ?? []); ?> dapur sudah PO
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($show_laporan_harian): ?>
                <!-- Dapur Status Tabs -->
                <div class="px-6 py-3 border-b border-slate-100 bg-slate-50/50 flex flex-wrap items-center gap-4 text-xs">
                    <span class="font-semibold text-emerald-600 flex items-center gap-1.5 cursor-default">
                        <i class="fas fa-check-circle"></i> SUDAH MEMESAN
                    </span>
                    <span class="text-slate-300">|</span>
                    <span class="font-semibold text-amber-500 flex items-center gap-1.5 cursor-default">
                        <i class="fas fa-clock"></i> BELUM MEMESAN
                    </span>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-slate-100">
                <div onclick="setFilter('total')" class="px-6 py-5 cursor-pointer hover:bg-slate-50 transition-colors">
                    <p class="text-2xl font-bold text-slate-800 mb-1"><?php echo $total_pesanan_count; ?></p>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total PO</p>
                </div>
                <div onclick="setFilter('lunas')" class="px-6 py-5 cursor-pointer hover:bg-emerald-50/50 transition-colors">
                    <p class="text-2xl font-bold text-emerald-600 mb-1"><?php echo $lunas_count; ?></p>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Lunas</p>
                </div>
                <div onclick="setFilter('belum_bayar')" class="px-6 py-5 cursor-pointer hover:bg-amber-50/50 transition-colors">
                    <p class="text-2xl font-bold text-amber-600 mb-1"><?php echo $belum_bayar_count; ?></p>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Belum Bayar</p>
                </div>
                <div onclick="setFilter('selesai')" class="px-6 py-5 cursor-pointer hover:bg-slate-50 transition-colors">
                    <p class="text-2xl font-bold text-slate-600 mb-1"><?php echo $selesai_count; ?></p>
                    <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Selesai</p>
                </div>
            </div>

            <?php if ($show_laporan_harian): ?>
                <!-- Dapur Detail Row -->
                <div class="px-6 py-4 border-t border-slate-100 grid grid-cols-2 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Sudah PO Hari Ini</p>
                        <div class="flex flex-wrap gap-1.5">
                            <?php
                            $count_sudah = 0;
                            foreach ($laporan_harian as $l) {
                                if ($l['status'] == 'Sudah') {
                                    echo '<span class="inline-flex items-center px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 text-[11px] font-semibold border border-emerald-100">' . $l['nama'] . '</span>';
                                    $count_sudah++;
                                }
                            }
                            if ($count_sudah == 0) echo '<span class="text-slate-400 text-xs italic">Belum ada</span>';
                            ?>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Belum PO Hari Ini</p>
                        <div class="flex flex-wrap gap-1.5">
                            <?php
                            $count_belum = 0;
                            foreach ($laporan_harian as $l) {
                                if ($l['status'] == 'Belum') {
                                    echo '<span class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-50 text-slate-600 text-[11px] font-semibold border border-slate-200">' . $l['nama'] . '</span>';
                                    $count_belum++;
                                }
                            }
                            if ($count_belum == 0) echo '<span class="text-emerald-600 text-xs font-semibold"><i class="fas fa-star mr-1"></i>Semua sudah PO!</span>';
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Filter & Search -->
        <div class="glass-card p-5 mb-6 flex flex-wrap items-center gap-3">
            <div class="relative flex-grow max-w-sm">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Cari ID, Nama, Driver..."
                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm font-medium transition outline-none">
            </div>

            <input type="date" id="dateFilter" value="<?php echo htmlspecialchars($filter_tanggal); ?>"
                class="bg-slate-50 border border-slate-200 rounded-lg px-3.5 py-2.5 font-medium text-sm text-slate-700 focus:ring-2 focus:ring-green-500 outline-none">

            <button onclick="applyFilters()" class="bg-slate-800 hover:bg-slate-900 text-white px-5 py-2.5 rounded-lg font-semibold text-sm transition-colors">
                <i class="fas fa-filter mr-1.5"></i> Filter
            </button>
        </div>

        <!-- Recent Orders Table -->
        <div class="glass-card overflow-hidden mb-6 fade-in">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between px-6 py-5 border-b border-slate-100">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Daftar Pesanan</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Data pesanan masuk dari semua outlet</p>
                </div>
                <div class="flex items-center gap-2 mt-3 md:mt-0">
                    <a href="invoice_manual.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-semibold text-xs transition-colors flex items-center gap-1.5">
                        <i class="fas fa-plus"></i> Buat Invoice
                    </a>
                    <a href="laporanPenjualan.php" class="text-slate-400 hover:text-slate-600 px-3 py-2 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 border border-slate-200 hover:border-slate-300 bg-white">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </div>

            <div id="dataContainer">
                <?php if (empty($riwayat_pembelian)): ?>
                    <div class="p-12 text-center">
                        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-inbox text-2xl text-slate-300"></i>
                        </div>
                        <p class="font-semibold text-slate-500 mt-2">Tidak ada pesanan ditemukan</p>
                        <p class="text-xs text-slate-400 mt-1">Coba ubah filter atau kata kunci pencarian</p>
                    </div>
                <?php else: ?>
                    <div class="p-5 grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <?php foreach ($riwayat_pembelian as $pesanan):
                            $status_bayar = $pesanan['status_pembayaran'];
                            $status_kirim = $pesanan['status_pengiriman'];
                            if ($status_bayar === 'Batal') {
                                $strip_color = 'bg-red-50 border-red-200 text-red-700';
                                $strip_icon  = 'fa-ban';
                                $strip_label = 'Pesanan Dibatalkan';
                            } elseif ($status_kirim === 'Done') {
                                $strip_color = 'bg-emerald-50 border-emerald-200 text-emerald-700';
                                $strip_icon  = 'fa-check-double';
                                $strip_label = 'Terkirim & Selesai';
                            } elseif ($status_kirim === 'Ongoing') {
                                $strip_color = 'bg-blue-50 border-blue-200 text-blue-700';
                                $strip_icon  = 'fa-truck';
                                $strip_label = 'Sedang Dikirim';
                            } elseif ($status_bayar === 'Lunas') {
                                $strip_color = 'bg-amber-50 border-amber-200 text-amber-700';
                                $strip_icon  = 'fa-clock';
                                $strip_label = 'Lunas · Menunggu Pengiriman';
                            } else {
                                $strip_color = 'bg-orange-50 border-orange-200 text-orange-700';
                                $strip_icon  = 'fa-hourglass-half';
                                $strip_label = 'Menunggu Pembayaran';
                            }
                        ?>
                            <!-- Order Card (Amazon-style) -->
                            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow">

                                <!-- Header Row -->
                                <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex flex-wrap gap-x-8 gap-y-1">
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tgl Pesan</p>
                                            <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo $pesanan['tanggal']; ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Driver</p>
                                            <p class="text-sm font-bold <?php echo $pesanan['nama_driver'] !== '-' ? 'text-slate-800' : 'text-amber-500'; ?> mt-0.5">
                                                <?php echo $pesanan['nama_driver'] !== '-' ? htmlspecialchars($pesanan['nama_driver']) : 'Belum ditugaskan'; ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pemesan</p>
                                            <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($pesanan['nama_pemesan']); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Outlet</p>
                                            <p class="text-sm font-semibold text-slate-600 mt-0.5"><?php echo htmlspecialchars($pesanan['nama_dapur']); ?></p>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-start sm:items-end gap-2 flex-shrink-0">
                                        <p class="text-[11px] font-bold text-slate-400 mb-0.5">No. Invoice: <span class="text-slate-700 font-black"><?php echo htmlspecialchars($pesanan['no_invoice']); ?></span></p>
                                        <?php if (!empty($pesanan['no_pesanan'])): ?>
                                            <p class="text-[11px] font-bold text-slate-400">No. PO: <span class="text-slate-700 font-black"><?php echo htmlspecialchars($pesanan['no_pesanan']); ?></span></p>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-2">
                                            <a href="invoice_view.php?id=<?php echo $pesanan['id']; ?>&popup=1"
                                                class="btn-detail-modal px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-bold text-slate-600 hover:border-slate-400 hover:text-indigo-600 hover:bg-slate-50 transition-colors flex items-center gap-1.5">
                                                <i class="fas fa-list-check"></i> Detail & Item
                                            </a>
                                            <?php if ($status_bayar !== 'Batal'): ?>
                                                <a href="cetak_pengantar.php?id=<?php echo $pesanan['id']; ?>" target="_blank"
                                                    class="px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5">
                                                    <i class="fas fa-truck-ramp-box"></i> Pengantar
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Strip -->
                                <div class="px-5 py-2.5 border-b border-slate-100 <?php echo $strip_color; ?> flex items-center gap-2">
                                    <i class="fas <?php echo $strip_icon; ?> text-xs"></i>
                                    <span class="text-xs font-bold"><?php echo $strip_label; ?></span>
                                </div>

                                <!-- Body: Driver + Actions -->
                                <div class="px-5 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                    <!-- Total Info -->
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
                                            <i class="fas fa-money-bill-wave text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Total Harga Pesanan</p>
                                            <p class="text-lg font-black text-emerald-700">
                                                Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?>
                                            </p>
                                        </div>
                                        <?php if ($status_bayar !== 'Batal'): ?>

                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <?php if ($status_bayar === 'Belum Bayar'): ?>
                                            <button onclick="bukaModalTugaskan('<?php echo $pesanan['id']; ?>', '<?php echo addslashes($pesanan['nama_driver']); ?>', '<?php echo addslashes($pesanan['nopol_driver'] ?? ''); ?>', '<?php echo addslashes($pesanan['no_hp_driver'] ?? ''); ?>')"
                                                class="px-4 py-2 bg-blue-600/30 hover:bg-blue-800/40 text-blue-900 text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors shadow-sm border border-blue-200">
                                                <i class="fas fa-motorcycle"></i>
                                                <?php echo $pesanan['nama_driver'] !== '-' ? 'Ubah' : '+Driver'; ?>
                                            </button>
                                            <button onclick="bukaModalBayar('<?php echo $pesanan['id']; ?>', '<?php echo addslashes($pesanan['nama_pemesan']); ?>', <?php echo $pesanan['total_harga']; ?>)"
                                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors shadow-sm">
                                                <i class="fas fa-check-circle"></i> Proses Lunas
                                            </button>
                                            <a href="?action=update_bayar&id=<?php echo $pesanan['id']; ?>&status=Batal"
                                                class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors"
                                                onclick="return confirm('Yakin ingin membatalkan pesanan ini?')">
                                                <i class="fas fa-times"></i> Batalkan
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status_bayar === 'Lunas' && $status_kirim === 'Pending'): ?>
                                            <a href="?action=update_status&id=<?php echo $pesanan['id']; ?>&status=Ongoing"
                                                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors shadow-sm"
                                                onclick="return confirm('Kirim pesanan ini?')">
                                                <i class="fas fa-truck"></i> Kirim Sekarang
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status_kirim === 'Ongoing'): ?>
                                            <a href="?action=update_status&id=<?php echo $pesanan['id']; ?>&status=Done"
                                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition-colors shadow-sm"
                                                onclick="return confirm('Tandai pesanan sebagai selesai?')">
                                                <i class="fas fa-check-double"></i> Tandai Selesai
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($status_bayar === 'Batal'): ?>
                                            <span class="px-4 py-2 bg-red-50 text-red-400 border border-red-100 text-xs font-bold rounded-xl">
                                                <i class="fas fa-ban mr-1"></i> Dibatalkan
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($status_kirim === 'Done' && $status_bayar !== 'Batal'): ?>
                                            <span class="px-4 py-2 bg-emerald-50 text-emerald-600 border border-emerald-100 text-xs font-bold rounded-xl">
                                                <i class="fas fa-star mr-1"></i> Selesai
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <div id="noDataMessage" class="hidden glass-card p-12 text-center mb-6">
            <i class="fas fa-search text-3xl mb-3 text-slate-300"></i>
            <p class="text-slate-500 font-medium">Tidak ada data ditemukan</p>
            <p class="text-xs text-slate-400 mt-1">Coba kata kunci pencarian yang berbeda</p>
        </div>


        <div id="loadingIndicator" class="hidden glass-card p-12 text-center mb-6">
            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-emerald-600 mx-auto mb-3"></div>
            <p class="text-slate-500 text-sm">Memuat data...</p>
        </div>
    </main>

    <div id="detail-modal-overlay" class="modal-overlay p-4 hidden">
        <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-3xl h-5/6 flex flex-col">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h2 class="text-xl font-bold">Detail Pesanan</h2>
                <button id="detail-modal-close" class="text-3xl text-gray-500 hover:text-gray-700">&times;</button>
            </div>
            <iframe id="detail-modal-iframe" src="" class="w-full h-full border-0 rounded-lg"></iframe>
        </div>
    </div>

    <div id="modalBayar" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-50 transition-opacity duration-300">
        <div class="bg-white p-6 rounded-xl shadow-2xl w-96 transform transition-all scale-100">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-slate-800">Proses Transaksi</h3>
                <button onclick="tutupModalBayar()" class="text-slate-400 hover:text-red-500 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mb-5 bg-slate-50 p-4 rounded-lg border border-slate-100">
                <p class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-1">Pesanan Atas Nama:</p>
                <p id="modalNama" class="font-bold text-lg text-slate-800 mb-2 truncate"></p>
                <div class="flex justify-between items-center border-t border-slate-200 pt-2">
                    <span class="text-sm text-slate-500">Total Nominal:</span>
                    <p id="modalHarga" class="font-bold text-green-600 text-xl"></p>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-bold text-slate-700 mb-2">Masuk ke Akun (Debit):</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-wallet text-slate-400"></i>
                    </div>

                    <select id="pilihAkunKas" class="w-full pl-10 p-2.5 border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none text-sm transition appearance-none" onchange="updateLinkBayar(currentActiveId)">

                        <optgroup label="💰 Penerimaan Tunai/Bank">
                            <?php foreach ($list_kas as $ak): ?>
                                <option value="<?= $ak['kode_akun'] ?>">
                                    <?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>

                        <optgroup label="📝 Kredit/Piutang">
                            <?php foreach ($list_piutang as $pi): ?>
                                <option value="<?= $pi['kode_akun'] ?>">
                                    <?= $pi['kode_akun'] ?> - <?= $pi['nama_akun'] ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>

                    </select>

                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <i class="fas fa-chevron-down text-slate-400 text-xs"></i>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2 leading-tight">
                    <i class="fas fa-info-circle mr-1"></i>
                    Pilih <b>Kas/Bank</b> jika lunas sekarang. Pilih <b>Piutang</b> jika pembayaran tempo/bon.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button onclick="tutupModalBayar()" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 font-bold transition text-sm">
                    Batal
                </button>
                <a id="btnConfirmBayar" href="#" class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 font-bold shadow-lg shadow-green-200 transition flex items-center text-sm transform active:scale-95">
                    <i class="fas fa-save mr-2"></i> Proses Jurnal
                </a>
            </div>
        </div>
    </div>

    <!-- Modal Tugaskan Driver -->
    <div id="modalTugaskanDriver" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center z-50 transition-opacity duration-300">
        <div class="bg-white p-6 rounded-xl shadow-2xl w-96 transform transition-all scale-100">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-slate-800">Tugaskan Pengiriman</h3>
                <button onclick="tutupModalTugaskan()" class="text-slate-400 hover:text-red-500 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <?php
            // Ambil Pengaturan Default untuk pre-fill
            $q_dnopol = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'default_nopol'");
            $def_nopol = $q_dnopol ? $q_dnopol->fetch_assoc()['nilai'] : '-';
            $q_ddriver = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'default_driver'");
            $def_driver = $q_ddriver ? $q_ddriver->fetch_assoc()['nilai'] : '-';
            $q_dhp = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'default_no_hp'");
            $def_no_hp = $q_dhp ? $q_dhp->fetch_assoc()['nilai'] : '-';
            ?>

            <form id="formTugaskan" action="laporanPenjualan.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="assign_driver">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nama Driver</label>
                    <input type="text" id="inputDriver" name="nama_driver" class="w-full p-2.5 border border-slate-300 rounded-lg text-sm font-semibold outline-none focus:ring-2 focus:ring-blue-500" placeholder="Masukkan nama driver..." required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nomor Polisi</label>
                    <input type="text" id="inputNopol" name="nopol_driver" class="w-full p-2.5 border border-slate-300 rounded-lg text-sm font-semibold outline-none focus:ring-2 focus:ring-blue-500" placeholder="Contoh: B 1234 ABC" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">No. HP Driver</label>
                    <input type="text" id="inputHp" name="no_hp_driver" class="w-full p-2.5 border border-slate-300 rounded-lg text-sm font-semibold outline-none focus:ring-2 focus:ring-blue-500" placeholder="08xxxxxxx" required>
                </div>

                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="tutupModalTugaskan()" class="px-5 py-2.5 bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 font-bold transition text-sm">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-900 font-bold shadow-lg shadow-slate-200 transition flex items-center text-sm">
                        <i class="fas fa-save mr-2"></i> Simpan Penugasan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const defaultNopol = '<?= addslashes($def_nopol) ?>';
        const defaultDriver = '<?= addslashes($def_driver) ?>';
        const defaultHp = '<?= addslashes($def_no_hp) ?>';

        window.bukaModalTugaskan = function(id, currentDriver, currentNopol, currentHp) {
            const form = document.getElementById('formTugaskan');
            form.action = 'laporanPenjualan.php?action=assign_driver&id=' + id;

            // Jika data saat ini masih default atau kosong, gunakan dari pengaturan
            document.getElementById('inputDriver').value = (currentDriver && currentDriver !== '-') ? currentDriver : (defaultDriver !== '-' ? defaultDriver : '');
            document.getElementById('inputNopol').value = (currentNopol && currentNopol !== '-') ? currentNopol : (defaultNopol !== '-' ? defaultNopol : '');
            document.getElementById('inputHp').value = (currentHp && currentHp !== '-') ? currentHp : (defaultHp !== '-' ? defaultHp : '');

            document.getElementById('modalTugaskanDriver').classList.remove('hidden');
        }

        window.tutupModalTugaskan = function() {
            document.getElementById('modalTugaskanDriver').classList.add('hidden');
        }

        let searchTimeout;
        let currentSearchKeyword = '';
        let currentActiveId = null;

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');

            // Event listener untuk pencarian realtime
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    currentSearchKeyword = this.value.trim();
                    clearTimeout(searchTimeout);

                    if (currentSearchKeyword.length >= 2 || currentSearchKeyword.length === 0) {
                        searchTimeout = setTimeout(function() {
                            searchRealtime();
                        }, 500);
                    }
                });
            }

            // --- LOGIC MODAL DETAIL ---
            const modalDetail = document.getElementById('detail-modal-overlay');
            const iframe = document.getElementById('detail-modal-iframe');

            // Event delegation untuk tombol detail
            document.addEventListener('click', function(e) {
                if (e.target.closest('.btn-detail-modal')) {
                    e.preventDefault();
                    const btn = e.target.closest('.btn-detail-modal');
                    iframe.src = btn.getAttribute('href');
                    modalDetail.classList.remove('hidden');
                    modalDetail.classList.add('flex');
                }
            });

            if (document.getElementById('detail-modal-close')) {
                document.getElementById('detail-modal-close').onclick = () => {
                    modalDetail.classList.add('hidden');
                    modalDetail.classList.remove('flex');
                    iframe.src = '';
                };
            }
        });

        function searchRealtime() {
            const keyword = document.getElementById('searchInput').value;
            const tanggal = document.getElementById('dateFilter') ? document.getElementById('dateFilter').value : '';

            // Menggunakan pendekatan PJAX untuk mengambil HTML render terbaru dari server
            // agar layout kartu UI yang kompleks tetap terjaga tanpa harus mereplika HTML di JS
            const url = new URL(window.location.href);
            url.searchParams.set('keyword', keyword);
            url.searchParams.set('tanggal', tanggal);

            fetch(url.toString())
                .then(response => response.text())
                .then(htmlString => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(htmlString, 'text/html');
                    const newDataContainer = doc.getElementById('dataContainer');
                    const currentDataContainer = document.getElementById('dataContainer');

                    if (newDataContainer && currentDataContainer) {
                        currentDataContainer.innerHTML = newDataContainer.innerHTML;
                    }
                })
                .catch(err => console.error('Error fetching data:', err));
        }

        // Fungsi updateDataContainer tidak lagi digunakan karena sudah ditangani langsung di searchRealtime
        function updateDataContainer(data) {
            // Deprecated
        }

        function formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }

        function setFilter(type) {
            window.location.href = 'laporanPenjualan.php?filter=' + type;
        }

        window.bukaModalBayar = function(id, nama, harga) {
            currentActiveId = id;
            document.getElementById('modalNama').innerText = nama;
            document.getElementById('modalHarga').innerText = "Rp " + formatNumber(harga);
            updateLinkBayar(id);
            document.getElementById('modalBayar').classList.remove('hidden');
        }

        window.tutupModalBayar = function() {
            document.getElementById('modalBayar').classList.add('hidden');
        }

        function updateLinkBayar(id) {
            const akun = document.getElementById('pilihAkunKas').value;
            const link = "?action=update_bayar&id=" + id + "&status=Lunas&akun=" + akun;
            document.getElementById('btnConfirmBayar').setAttribute('href', link);
        }
    </script>
</body>

</html>