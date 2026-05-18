<?php
// Selalu mulai session DI PALING ATAS
session_start();

// Sertakan file koneksi database
include "koneksi.php"; 

// --- 1. KEAMANAN ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur' || !isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit();
}
$id_dapur_login = (int)$_SESSION['user']['id'];
$nama_dapur_login = htmlspecialchars($_SESSION['user']['nama'] ?? 'Dapur');

// Variabel untuk pesan feedback
$feedback_message = '';
$feedback_type = 'info';

// === 2. LOGIKA BATAL PESANAN (TIDAK BERUBAH) ===
if (isset($_GET['action']) && $_GET['action'] === 'batal' && isset($_GET['id'])) {
    $id_pesanan_batal = (int)$_GET['id'];

    $stmt_cek = $koneksi->prepare("SELECT status_pembayaran, status_pengiriman FROM pesanan WHERE id_pesanan = ? AND id_dapur = ?");
    $stmt_cek->bind_param("ii", $id_pesanan_batal, $id_dapur_login);
    $stmt_cek->execute();
    $stmt_cek->store_result();
    $stmt_cek->bind_result($status_bayar, $status_kirim);
    
    if ($stmt_cek->fetch() && $status_bayar === 'Belum Bayar' && $status_kirim === 'Pending') {
        $koneksi->begin_transaction();
        try {
            // 1. Ambil detail barang
            $stmt_get_details = $koneksi->prepare("SELECT id_barang, jumlah FROM detail_pesanan WHERE id_pesanan = ?");
            $stmt_get_details->bind_param("i", $id_pesanan_batal);
            $stmt_get_details->execute();
            $stmt_get_details->store_result();
            $stmt_get_details->bind_result($id_brg_db, $jumlah_db);
            
            $items_to_rollback = [];
            while($stmt_get_details->fetch()){
                $items_to_rollback[$id_brg_db] = $jumlah_db;
            }
            $stmt_get_details->free_result();
            $stmt_get_details->close();

            // 2. Rollback Stok
            if (!empty($items_to_rollback)) {
                $sql_rollback_stok = "UPDATE gudang SET stok = stok + ? WHERE id_barang = ?";
                $stmt_rollback = $koneksi->prepare($sql_rollback_stok);
                foreach ($items_to_rollback as $id_brg => $jml) {
                    $stmt_rollback->bind_param("ii", $jml, $id_brg);
                    if (!$stmt_rollback->execute()) {
                        throw new Exception("Gagal rollback stok ID {$id_brg}");
                    }
                }
                $stmt_rollback->close();
            }

            // 3. Update Status
            $stmt_update_batal = $koneksi->prepare("UPDATE pesanan SET status_pembayaran = 'Batal' WHERE id_pesanan = ? AND id_dapur = ?");
            $stmt_update_batal->bind_param("ii", $id_pesanan_batal, $id_dapur_login);
            if (!$stmt_update_batal->execute()) {
                throw new Exception("Gagal update status pesanan.");
            }
            $stmt_update_batal->close();
            
            if (!$koneksi->commit()) {
                throw new Exception("Gagal commit transaksi.");
            }
            $feedback_message = "Pesanan #{$id_pesanan_batal} berhasil dibatalkan.";
            $feedback_type = 'success';

        } catch (Exception $e) {
            $koneksi->rollback();
            $feedback_message = "Gagal batal: " . $e->getMessage();
            $feedback_type = 'error';
        }
    } else {
        $feedback_message = "Pesanan tidak bisa dibatalkan (Sudah diproses).";
        $feedback_type = 'error';
    }
    $stmt_cek->free_result();
    $stmt_cek->close();
}

// --- 3. LOGIKA PENGAMBILAN DATA ---
$riwayat_pesanan = [];
$sql = "SELECT 
            P.id_pesanan, P.nama_pemesan, P.tgl_pesan, P.total_harga, P.status_pembayaran, P.status_pengiriman,
            GROUP_CONCAT(CONCAT(G.nama, ' (', DP.jumlah, ')') SEPARATOR ', ') as daftar_barang
        FROM pesanan AS P
        LEFT JOIN detail_pesanan AS DP ON P.id_pesanan = DP.id_pesanan
        LEFT JOIN gudang AS G ON DP.id_barang = G.id_barang
        WHERE P.id_dapur = ?
        GROUP BY P.id_pesanan
        ORDER BY P.tgl_pesan DESC";

$stmt = $koneksi->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $id_dapur_login);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id_db, $nama_pemesan_db, $tgl_db, $total_db, $status_bayar_db, $status_kirim_db, $barang_db);
    while ($stmt->fetch()) {
        $riwayat_pesanan[] = [
            'id' => $id_db,
            'nama_pemesan' => $nama_pemesan_db,
            'tanggal' => date('d M Y, H:i', strtotime($tgl_db)),
            'total_harga' => $total_db,
            'status_pembayaran' => $status_bayar_db,
            'status_pengiriman' => $status_kirim_db,
            'daftar_barang' => $barang_db ?? 'Detail tidak tersedia'
        ];
    }
    $stmt->free_result();
    $stmt->close();
}

// Fungsi styling badge (Blue Theme Consistent)
function get_badge_class($status, $type = 'bayar') {
    if ($type == 'bayar') {
        if ($status == 'Lunas') return 'bg-green-100 text-green-700 border border-green-200';
        if ($status == 'Batal') return 'bg-red-100 text-red-700 border border-red-200';
        return 'bg-orange-100 text-orange-700 border border-orange-200'; 
    } else { // type == 'kirim'
        if ($status == 'Done') return 'bg-blue-100 text-blue-700 border border-blue-200';
        if ($status == 'Ongoing') return 'bg-yellow-100 text-yellow-700 border border-yellow-200';
        return 'bg-slate-100 text-slate-600 border border-slate-200'; 
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat - <?php echo $nama_dapur_login; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',     
                        primaryDark: '#1e40af', 
                        primaryLight: '#eff6ff',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding-bottom: 100px;
        }

        /* Hero Section - GAMBAR DISAMAKAN */
        .hero-bg {
            background-image: linear-gradient(rgba(37, 99, 235, 0.9), rgba(30, 58, 138, 0.8)), url('https://images.unsplash.com/photo-1498837167922-ddd27525d352?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            height: 220px;
            color: white;
        }
        
        @media (max-width: 640px) {
            .hero-bg { height: 180px; }
        }

        /* Order Card Styling - Matches new design */
        .order-card {
            transition: all 0.2s ease;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.15);
            border-color: #93c5fd;
        }

        /* Feedback Alert */
        .feedback { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.75rem; border-width: 1px; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
        .feedback-success { background-color: #ecfdf5; border-color: #34d399; color: #065f46; }
        .feedback-error { background-color: #fef2f2; border-color: #f87171; color: #991b1b; }

        /* Modal Responsive */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0.2s; padding: 16px; }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-content-responsive { width: 100%; max-width: 800px; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); height: 80vh; display: flex; flex-direction: column; }
        
        @media (max-width: 640px) {
            .modal-content-responsive { height: 90vh; }
        }

        /* Badge */
        .badge { font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.025em; white-space: nowrap; }
    </style>
</head>
<body>

    <div class="hero-bg flex flex-col items-center justify-center text-center px-4 mb-6 md:mb-8 relative">
        <div class="absolute top-4 left-4 text-[10px] md:text-xs font-bold tracking-widest text-blue-100 bg-white/20 px-3 py-1 rounded backdrop-blur-sm">
            DAPUR: <?php echo strtoupper($nama_dapur_login); ?>
        </div>
        <div class="absolute top-4 right-4 flex gap-2">
             <a href="logout.php" class="text-[10px] md:text-xs font-bold text-blue-50 hover:text-white transition bg-white/10 px-3 py-1.5 rounded-full backdrop-blur-sm flex items-center">
                 <i class="fas fa-sign-out-alt mr-1"></i> Logout
             </a>
        </div>
        <div class="mt-4">
            <h1 class="text-2xl md:text-4xl font-bold text-white mb-1 drop-shadow-md">RIWAYAT PESANAN</h1>
            <p class="text-blue-100 text-xs md:text-base max-w-lg mx-auto px-2">
                Pantau status pembelian dan riwayat transaksi dapur Anda di sini.
            </p>
            <a href="dapur.php" class="inline-flex items-center mt-4 px-5 py-2 bg-white text-primary text-xs md:text-sm font-bold rounded-full hover:bg-blue-50 transition shadow-lg">
                <i class="fas fa-plus mr-2"></i> Buat Pesanan Baru
            </a>
        </div>
    </div>

    <main class="w-full px-4 md:px-8 max-w-7xl mx-auto">
        
        <?php if (!empty($feedback_message)): ?>
            <div class="feedback feedback-<?php echo $feedback_type; ?> mb-6 shadow-sm animate-bounce-in">
                <i class="fas <?php echo $feedback_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> text-lg"></i>
                <span><?php echo htmlspecialchars($feedback_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
            
            <?php if (empty($riwayat_pesanan)): ?>
                <div class="col-span-full text-center py-16 bg-white rounded-2xl border border-dashed border-slate-300">
                    <div class="inline-block p-4 bg-blue-50 rounded-full mb-4">
                        <i class="fas fa-history text-4xl text-blue-300"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-700">Belum Ada Riwayat</h3>
                    <p class="text-slate-500 text-sm mt-1">Anda belum melakukan pemesanan apapun.</p>
                </div>
            <?php else: ?>
                
                <?php foreach ($riwayat_pesanan as $pesanan): ?>
                    <div class="order-card flex flex-col h-full relative group cursor-pointer" 
                         onclick="openDetailModal('detailPesanan.php?id=<?php echo $pesanan['id']; ?>')">
                        
                        <div class="px-4 py-3 md:px-5 md:py-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-500 bg-white border border-slate-200 px-2 py-1 rounded shadow-sm">
                                #<?php echo $pesanan['id']; ?>
                            </span>
                            <span class="text-[10px] md:text-xs text-slate-500 font-medium flex items-center gap-1">
                                <i class="far fa-calendar-alt"></i> <?php echo $pesanan['tanggal']; ?>
                            </span>
                        </div>

                        <div class="p-4 md:p-5 flex-1 flex flex-col">
                            <div class="mb-3">
                                <h3 class="font-bold text-slate-800 text-base md:text-lg truncate"><?php echo htmlspecialchars($pesanan['nama_pemesan']); ?></h3>
                            </div>
                            
                            <div class="text-xs md:text-sm text-slate-600 mb-4 bg-blue-50/50 p-3 rounded-lg border border-blue-50 flex-1">
                                <p class="line-clamp-3 leading-relaxed" title="<?php echo htmlspecialchars($pesanan['daftar_barang']); ?>">
                                    <?php echo htmlspecialchars($pesanan['daftar_barang']); ?>
                                </p>
                            </div>

                            <div class="flex justify-between items-center pt-2 border-t border-dashed border-slate-200">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold">Total Belanja</p>
                                    <p class="text-lg font-bold text-primary">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="px-4 py-3 md:px-5 md:py-4 border-t border-slate-100 bg-white flex justify-between items-center">
                            <div class="flex gap-1 md:gap-2 overflow-x-auto hide-scrollbar max-w-[60%]">
                                <span class="badge <?php echo get_badge_class($pesanan['status_pembayaran'], 'bayar'); ?>">
                                    <?php echo htmlspecialchars($pesanan['status_pembayaran']); ?>
                                </span>
                                <span class="badge <?php echo get_badge_class($pesanan['status_pengiriman'], 'kirim'); ?>">
                                    <?php echo htmlspecialchars($pesanan['status_pengiriman']); ?>
                                </span>
                            </div>

                            <div class="flex gap-2" onclick="event.stopPropagation();">
                                <?php if ($pesanan['status_pembayaran'] == 'Belum Bayar' && $pesanan['status_pengiriman'] == 'Pending'): ?>
                                    
                                    <a href="edit_pesanan.php?id=<?php echo $pesanan['id']; ?>" 
                                       class="flex items-center justify-center w-8 h-8 md:w-9 md:h-9 text-yellow-600 hover:text-yellow-700 bg-yellow-50 hover:bg-yellow-100 rounded-full transition border border-yellow-200"
                                       title="Edit">
                                        <i class="fas fa-pen text-xs md:text-sm"></i>
                                    </a>

                                    <a href="riwayat_dapur.php?action=batal&id=<?php echo $pesanan['id']; ?>" 
                                       class="flex items-center justify-center w-8 h-8 md:w-9 md:h-9 text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 rounded-full transition border border-red-200"
                                       title="Batal"
                                       onclick="return confirm('Yakin ingin membatalkan pesanan #<?php echo $pesanan['id']; ?>? Stok akan dikembalikan.');">
                                        <i class="fas fa-trash-alt text-xs md:text-sm"></i>
                                    </a>
                                    
                                <?php else: ?>
                                    <span class="text-slate-300 text-xs italic pr-2 flex items-center">
                                        <i class="fas fa-lock mr-1"></i> Selesai
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </main>

    <div id="detail-modal-overlay" class="modal-overlay hidden">
        <div class="modal-content-responsive animate-fade-in-up">
            <div class="px-4 py-3 md:px-6 md:py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h2 class="text-base md:text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-file-invoice text-primary"></i> Detail Pesanan
                </h2>
                <button id="detail-modal-close" class="w-8 h-8 flex items-center justify-center rounded-full bg-white text-slate-400 hover:text-red-500 hover:bg-red-50 transition shadow-sm">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="flex-1 bg-slate-100 p-0 relative">
                <div id="iframe-loader" class="absolute inset-0 flex items-center justify-center text-slate-400">
                    <i class="fas fa-spinner fa-spin text-3xl"></i>
                </div>
                <iframe id="detail-modal-iframe" src="" class="w-full h-full border-0" onload="document.getElementById('iframe-loader').style.display='none'"></iframe>
            </div>
        </div>
    </div>

    <script>
        function openDetailModal(url) {
            const modalOverlay = document.getElementById('detail-modal-overlay');
            const modalIframe = document.getElementById('detail-modal-iframe');
            const loader = document.getElementById('iframe-loader');

            if (modalOverlay && modalIframe) {
                loader.style.display = 'flex'; 
                modalIframe.setAttribute('src', url);
                modalOverlay.classList.remove('hidden');
                modalOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const modalOverlay = document.getElementById('detail-modal-overlay');
            const modalIframe = document.getElementById('detail-modal-iframe');
            const modalCloseBtn = document.getElementById('detail-modal-close');

            if (modalOverlay && modalCloseBtn) {
                
                const closeModal = () => {
                    modalOverlay.classList.remove('show');
                    modalOverlay.classList.add('hidden');
                    document.body.style.overflow = '';
                    setTimeout(() => {
                        modalIframe.setAttribute('src', ''); 
                    }, 300);
                };

                modalCloseBtn.addEventListener('click', closeModal);
                
                modalOverlay.addEventListener('click', function(event) {
                    if (event.target === modalOverlay) {
                        closeModal();
                    }
                });
            }
        });
    </script>
</body>
</html>
