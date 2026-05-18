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
function json_clean_output($data) {
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
        $stmt_update_pesanan->bind_param("sssssdi", 
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
            $stmt_insert_detail->bind_param("iiidddss", 
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

// FLUSH BUFFER AGAR HTML BISA TAMPIL
ob_end_flush();

// LOGIKA REDIRECT (UPDATE STATUS OLEH ADMIN)
if (isset($_GET['action']) && ($_GET['action'] == 'update_bayar' || $_GET['action'] == 'update_status' || $_GET['action'] == 'assign_driver')) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: login.php"); exit;
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
            $stmt = $koneksi->prepare("UPDATE pesanan SET status_pembayaran = ?, status_pengiriman = 'Done', id_akuntan = ?, nama_penandatangan = ?, path_ttd = ? WHERE id_pesanan = ?");
            $stmt->bind_param("sissi", $status, $id_user, $nama_user, $path_ttd, $id);
        } else {
            $stmt = $koneksi->prepare("UPDATE pesanan SET status_pembayaran = ?, id_akuntan = ?, nama_penandatangan = ?, path_ttd = ? WHERE id_pesanan = ?");
            $stmt->bind_param("sissi", $status, $id_user, $nama_user, $path_ttd, $id);
        }
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $koneksi->prepare("UPDATE pesanan SET status_pengiriman = ? WHERE id_pesanan = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    header("Location: laporanPenjualan.php?feedback=Update Sukses&type=success");
    exit();
}

// --- AMBIL DAFTAR AKUN COA UNTUK DROPDOWN (KAS & PIUTANG) ---
$list_kas = [];
$list_piutang = [];

// Kita ambil semua yang depannya 111 (Kas/Bank) dan 112 (Piutang)
$q_coa = $koneksi->query("SELECT kode_akun, nama_akun FROM akun_coa WHERE kode_akun LIKE '111%' OR kode_akun LIKE '112%' ORDER BY kode_akun ASC");

while($r = $q_coa->fetch_assoc()){
    // Pisahkan berdasarkan awalan kode
    if(strpos($r['kode_akun'], '111') === 0){
        $list_kas[] = $r;
    } else if(strpos($r['kode_akun'], '112') === 0) {
        $list_piutang[] = $r;
    }
}

// --- KEAMANAN LAPORAN (Admin) ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); 
    exit();
}

if(isset($_GET['feedback'])) { 
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
            'wa_pemesan' => $row['wa_pemesan']
        ];
    }
}

// Hitung Statistik
$res_count = $koneksi->query("SELECT status_pembayaran, status_pengiriman FROM pesanan");
$total_pesanan_count = 0; $lunas_count = 0; $belum_bayar_count = 0; $selesai_count = 0;
while($r = $res_count->fetch_assoc()){
    $total_pesanan_count++;
    if($r['status_pembayaran']=='Lunas') $lunas_count++;
    if($r['status_pembayaran']=='Belum Bayar') $belum_bayar_count++;
    if($r['status_pengiriman']=='Done') $selesai_count++;
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #F8FAFC; color: #1E293B; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border-radius: 1.5rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); border: 1px solid rgba(255,255,255,0.5); }
        .invoice-card { background: white; border-radius: 1.25rem; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03); border: 1px solid #e2e8f0; transition: all 0.3s ease; }
        .invoice-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05); }
        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.025em; }
        .badge-pending { background-color: #EFF6FF; color: #2563EB; } 
        .badge-done { background-color: #ECFDF5; color: #059669; } 
        .badge-belum-bayar { background-color: #FFFBEB; color: #D97706; } 
        .badge-lunas { background-color: #ECFDF5; color: #059669; } 
        .badge-batal { background-color: #FEF2F2; color: #DC2626; } 
        .action-btn { font-size: 0.75rem; font-weight: 700; padding: 0.6rem 1rem; border-radius: 0.75rem; transition: all 0.2s; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.8); backdrop-filter: blur(4px); display: flex; justify-content: center; align-items: center; z-index: 1000; }
        main { margin-left: 0; transition: all 0.3s ease; }
        @media (min-width: 1025px) { main { margin-left: 16rem; } }
        #searchLoading { display: none; }
    </style>
</head>
<body class="flex bg-gray-100 min-h-screen">
    
    <?php include 'sidebar.php'; ?>
    
    <main class="flex-1 min-h-screen p-6 md:p-10 transition-all duration-300">
        
        <!-- Area Header -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-10 gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-900 uppercase tracking-tight">Monitor & Eksekusi Pesanan</h1>
                <p class="text-slate-500 font-medium">Pemantauan Pesanan Masuk & Proses Eksekusi</p>
            </div>
        </div>

        <?php if ($show_laporan_harian): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
            <div class="glass-card p-6 border-l-4 border-emerald-500">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                        <i class="fas fa-check-circle text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800">Sudah Memesan</h3>
                        <p class="text-xs text-slate-500">Dapur yang telah melakukan PO hari ini</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php 
                    $count_sudah = 0; 
                    foreach ($laporan_harian as $l) {
                        if($l['status']=='Sudah') { 
                            echo '<span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold border border-emerald-100"><i class="fas fa-utensils mr-2 opacity-50"></i>'.$l['nama'].'</span>'; 
                            $count_sudah++; 
                        } 
                    } 
                    if($count_sudah==0) echo '<p class="text-slate-400 text-sm italic">Belum ada pesanan masuk hari ini</p>';
                    ?>
                </div>
            </div>

            <div class="glass-card p-6 border-l-4 border-amber-400">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600">
                        <i class="fas fa-clock text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800">Belum Memesan</h3>
                        <p class="text-xs text-slate-500">Dapur yang belum melakukan PO hari ini</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php 
                    $count_belum = 0; 
                    foreach ($laporan_harian as $l) {
                        if($l['status']=='Belum') { 
                            echo '<span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-slate-50 text-slate-600 text-xs font-bold border border-slate-200">'.$l['nama'].'</span>'; 
                            $count_belum++; 
                        } 
                    } 
                    if($count_belum==0) echo '<p class="text-emerald-600 text-sm font-bold"><i class="fas fa-star mr-2"></i>Semua dapur sudah memesan!</p>';
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Kontrol & Statistik -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div onclick="setFilter('total')" class="glass-card p-6 cursor-pointer group hover:bg-blue-600 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-50 group-hover:bg-blue-500 rounded-2xl flex items-center justify-center text-blue-600 group-hover:text-white transition-colors">
                        <i class="fas fa-shopping-basket text-xl"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 group-hover:text-blue-200 transition-colors">Total PO</span>
                </div>
                <h3 class="text-3xl font-black text-slate-900 group-hover:text-white transition-colors"><?php echo $total_pesanan_count; ?></h3>
            </div>

            <div onclick="setFilter('lunas')" class="glass-card p-6 cursor-pointer group hover:bg-emerald-600 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-emerald-50 group-hover:bg-emerald-500 rounded-2xl flex items-center justify-center text-emerald-600 group-hover:text-white transition-colors">
                        <i class="fas fa-check-double text-xl"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 group-hover:text-emerald-200 transition-colors">Lunas</span>
                </div>
                <h3 class="text-3xl font-black text-slate-900 group-hover:text-white transition-colors"><?php echo $lunas_count; ?></h3>
            </div>

            <div onclick="setFilter('belum_bayar')" class="glass-card p-6 cursor-pointer group hover:bg-amber-500 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-amber-50 group-hover:bg-amber-400 rounded-2xl flex items-center justify-center text-amber-600 group-hover:text-white transition-colors">
                        <i class="fas fa-receipt text-xl"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 group-hover:text-amber-100 transition-colors">Belum Bayar</span>
                </div>
                <h3 class="text-3xl font-black text-slate-900 group-hover:text-white transition-colors"><?php echo $belum_bayar_count; ?></h3>
            </div>

            <div onclick="setFilter('selesai')" class="glass-card p-6 cursor-pointer group hover:bg-slate-800 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-slate-50 group-hover:bg-slate-700 rounded-2xl flex items-center justify-center text-slate-600 group-hover:text-white transition-colors">
                        <i class="fas fa-box-open text-xl"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 group-hover:text-slate-400 transition-colors">Selesai</span>
                </div>
                <h3 class="text-3xl font-black text-slate-900 group-hover:text-white transition-colors"><?php echo $selesai_count; ?></h3>
            </div>
        </div>

        <!-- Filter & Search -->
        <div class="glass-card p-6 mb-8 flex flex-wrap items-center gap-4">
            <div class="relative flex-grow max-w-md">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Cari ID, Nama Pemesan, Nama Driver..." 
                       class="w-full pl-12 pr-4 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-500 font-medium transition outline-none">
            </div>
            
            <input type="date" id="dateFilter" value="<?php echo htmlspecialchars($filter_tanggal); ?>" 
                   class="bg-slate-50 border-none rounded-xl px-4 py-3 font-bold text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none">
            
            <button onclick="applyFilters()" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-100 transition-all active:scale-95">
                <i class="fas fa-filter mr-2"></i> Filter
            </button>

            <a href="invoice_manual.php" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-blue-200 transition-all active:scale-95 flex items-center gap-2">
                <i class="fas fa-plus"></i> Buat Invoice Manual
            </a>
            <a href="laporanPenjualan.php" class="text-slate-400 hover:text-blue-600 font-bold px-4 py-3 transition">
                <i class="fas fa-sync-alt"></i> Reset
            </a>
        </div>
        
        <div id="dataContainer" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php if (empty($riwayat_pembelian)): ?>
                <div class="col-span-2 bg-white p-8 rounded-xl text-center text-gray-500 shadow">
                    <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                    <p>Tidak ada data ditemukan.</p>
                </div>
            <?php else: ?>
                <?php foreach ($riwayat_pembelian as $pesanan): ?>
                    <div class="bg-white rounded-xl invoice-card overflow-hidden flex flex-col hover:shadow-lg transition-shadow duration-300"> 
                        <div class="p-6 flex-grow"> 
                            <div class="flex justify-between items-start mb-4"> 
                                <div>
                                    <span class="text-sm text-gray-500"><?php echo $pesanan['tanggal']; ?></span>
                                    <h3 class="text-lg font-bold text-gray-800">Pesanan #<?php echo $pesanan['id']; ?></h3>
                                </div>
                                <div class="flex flex-col items-end gap-1 text-xs"> 
                                    <span class="badge <?php echo $pesanan['status_pembayaran']=='Lunas'?'badge-lunas':'badge-belum-bayar'; ?>">
                                        <?php echo $pesanan['status_pembayaran']; ?>
                                    </span>
                                    <span class="badge <?php echo $pesanan['status_pengiriman']=='Done'?'badge-done':'badge-pending'; ?>">
                                        <?php echo $pesanan['status_pengiriman']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex justify-between mb-2">
                                    <span class="text-sm text-gray-500">Total</span>
                                    <span class="font-bold text-xl text-green-600">Rp<?php echo number_format($pesanan['total_harga'],0,',','.'); ?></span>
                                </div>
                                <p class="text-xs text-gray-500">
                                    <b>Dapur:</b> <?php echo $pesanan['nama_dapur']; ?><br>
                                    <b>Pemesan:</b> <?php echo $pesanan['nama_pemesan']; ?><br>
                                    <b>Driver:</b> <?php echo $pesanan['nama_driver'] !== '-' ? $pesanan['nama_driver'] . ' (' . $pesanan['nopol_driver'] . ')' : '<span class="text-amber-600 font-bold">Belum Ditugaskan</span>'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 flex flex-wrap gap-2 justify-end">
                             <a href="cetak_invoice.php?id=<?php echo $pesanan['id']; ?>" target="_blank" class="action-btn bg-blue-600 text-white hover:bg-blue-700 transition">
                                 <i class="fas fa-file-invoice"></i> Invoice
                             </a>

                             <?php if ($pesanan['status_pembayaran'] !== 'Batal'): ?>
                             <button onclick="bukaModalTugaskan('<?php echo $pesanan['id']; ?>', '<?php echo addslashes($pesanan['nama_driver']); ?>', '<?php echo addslashes($pesanan['nopol_driver']); ?>', '<?php echo addslashes($pesanan['no_hp_driver']); ?>')" 
                                     class="action-btn bg-slate-800 text-white hover:bg-slate-900 transition">
                                 <i class="fas fa-user-plus"></i> Tugaskan
                             </button>
                             <a href="cetak_pengantar.php?id=<?php echo $pesanan['id']; ?>" target="_blank" class="action-btn bg-blue-100 text-blue-700 hover:bg-blue-600 hover:text-white transition">
                                 <i class="fas fa-truck-ramp-box"></i> Pengantar
                             </a>
                             <?php endif; ?>
                             
                             <?php if ($pesanan['status_pembayaran'] === 'Belum Bayar'): ?>
                                 <a href="?action=update_bayar&id=<?php echo $pesanan['id']; ?>&status=Lunas" 
                                    class="action-btn bg-green-600 text-white hover:bg-green-700 transition"
                                    onclick="return confirm('Tandai pesanan ini sebagai Lunas?')">
                                     <i class="fas fa-check"></i> Lunas
                                 </a>
                                 
                                 <a href="?action=update_bayar&id=<?php echo $pesanan['id']; ?>&status=Batal" 
                                    class="action-btn bg-red-600 text-white hover:bg-red-700 transition" 
                                    onclick="return confirm('Yakin ingin membatalkan pesanan ini?')">
                                     <i class="fas fa-times"></i> Batal
                                 </a>
                             <?php endif; ?>
                             
                             <?php if ($pesanan['status_pembayaran'] === 'Lunas' && $pesanan['status_pengiriman'] === 'Pending'): ?>
                                 <a href="?action=update_status&id=<?php echo $pesanan['id']; ?>&status=Ongoing" 
                                    class="action-btn bg-yellow-500 text-white hover:bg-yellow-600 transition" 
                                    onclick="return confirm('Kirim pesanan ini?')">
                                     <i class="fas fa-truck"></i> Kirim
                                 </a>
                             <?php endif; ?>
                             
                             <?php if ($pesanan['status_pengiriman'] === 'Ongoing'): ?>
                                 <a href="?action=update_status&id=<?php echo $pesanan['id']; ?>&status=Done" 
                                    class="action-btn bg-teal-600 text-white hover:bg-teal-700 transition" 
                                    onclick="return confirm('Tandai pesanan sebagai selesai?')">
                                     <i class="fas fa-check-double"></i> Selesai
                                 </a>
                             <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="noDataMessage" class="hidden col-span-2 bg-white p-8 rounded-xl text-center text-gray-500 shadow">
            <i class="fas fa-search text-4xl mb-4 text-gray-300"></i>
            <p class="text-lg mb-2">Tidak ada data ditemukan</p>
            <p class="text-sm text-gray-400">Coba kata kunci pencarian yang berbeda</p>
        </div>

        <div id="loadingIndicator" class="hidden col-span-2 bg-white p-8 rounded-xl text-center text-gray-500 shadow">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p>Memuat data...</p>
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
                            <?php foreach($list_kas as $ak): ?>
                                <option value="<?= $ak['kode_akun'] ?>">
                                    <?= $ak['kode_akun'] ?> - <?= $ak['nama_akun'] ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>

                        <optgroup label="📝 Kredit/Piutang">
                            <?php foreach($list_piutang as $pi): ?>
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
                    iframe.src=''; 
                };
            }
        });
        
        function searchRealtime() {
            const keyword = document.getElementById('searchInput').value;
            const tanggal = document.getElementById('dateFilter') ? document.getElementById('dateFilter').value : '';
            
            fetch(`laporanPenjualan.php?action=search_realtime&keyword=${encodeURIComponent(keyword)}&tanggal=${tanggal}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateDataContainer(data.data);
                    }
                });
        }
        
        function updateDataContainer(data) {
            const container = document.getElementById('dataContainer');
            if (!container) return;
            
            if (data.length === 0) {
                container.innerHTML = '<div class="col-span-2 text-center py-10 text-slate-400">Tidak ada data</div>';
                return;
            }
            
            let html = '';
            data.forEach(pesanan => {
                html += `
                    <div class="bg-white rounded-xl invoice-card overflow-hidden flex flex-col hover:shadow-lg transition-shadow duration-300">
                        <!-- Content similar to PHP loop -->
                        <div class="p-6">
                            <h3 class="font-bold">#${pesanan.id} - ${pesanan.nama_pemesan}</h3>
                            <p class="text-sm text-gray-500">${pesanan.tanggal}</p>
                            <p class="text-green-600 font-bold">Rp${formatNumber(pesanan.total_harga)}</p>
                        </div>
                    </div>`;
            });
            container.innerHTML = html;
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
