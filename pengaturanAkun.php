<?php
// Selalu mulai session di awal halaman
session_start();

// Keamanan: Cek apakah pengguna sudah login
if (!isset($_SESSION['user']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Sertakan file koneksi database
include 'koneksi.php';

// Inisialisasi semua pesan
$error_message_profile = '';
$success_message_profile = '';
$error_message_password = '';
$success_message_password = '';
$error_message_create = '';
$success_message_create = '';
$error_message_foto = '';
$success_message_foto = '';
$error_message_reset = ''; 
$success_message_reset = ''; 
$error_message_ttd = '';
$success_message_ttd = '';

// Ambil data user yang sedang login dari session
$user_data = $_SESSION['user'];
$user_role = $_SESSION['role'];
$user_id = $user_data['id'];
$current_name = $user_data['nama'];

// Tentukan folder upload
$upload_dir = 'uploads/profiles/';
$ttd_dir = 'uploads/ttd/';

// === LOGIKA 4: UPLOAD FOTO PROFIL ===
if (isset($_POST['action']) && $_POST['action'] == 'upload_foto') {
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_profil'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error_message_foto = "Hanya file JPEG, JPG, PNG, dan GIF yang diizinkan.";
        } elseif ($file['size'] > $max_size) {
            $error_message_foto = "Ukuran file maksimal 2MB.";
        } else {
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $stmt_old = $koneksi->prepare("SELECT foto FROM user WHERE id = ?");
                $stmt_old->bind_param("i", $user_id);
                $stmt_old->execute();
                $stmt_old->bind_result($current_foto_filename);
                if ($stmt_old->fetch() && $current_foto_filename && file_exists($upload_dir . $current_foto_filename)) {
                    unlink($upload_dir . $current_foto_filename);
                }
                $stmt_old->close();
                
                $stmt_update_foto = $koneksi->prepare("UPDATE user SET foto = ? WHERE id = ?");
                if ($stmt_update_foto) {
                    $stmt_update_foto->bind_param("si", $new_filename, $user_id);
                    if ($stmt_update_foto->execute()) {
                        $_SESSION['user']['foto'] = $new_filename;
                        $success_message_foto = "Foto profil berhasil diupload!";
                    } else {
                        $error_message_foto = "Gagal menyimpan foto: " . $stmt_update_foto->error;
                    }
                    $stmt_update_foto->close();
                }
            } else {
                $error_message_foto = "Gagal mengupload file.";
            }
        }
    } else {
        $error_message_foto = "Silakan pilih file foto yang valid.";
    }
}

// === LOGIKA 5: HAPUS FOTO PROFIL ===
if (isset($_POST['action']) && $_POST['action'] == 'hapus_foto') {
    $stmt_old = $koneksi->prepare("SELECT foto FROM user WHERE id = ?");
    $stmt_old->bind_param("i", $user_id);
    $stmt_old->execute();
    $stmt_old->bind_result($current_foto_filename);
    $stmt_old->fetch();
    $stmt_old->close();

    if ($current_foto_filename) {
        $file_path = $upload_dir . $current_foto_filename;
        if (file_exists($file_path)) { unlink($file_path); }
        
        $stmt_delete_foto = $koneksi->prepare("UPDATE user SET foto = NULL WHERE id = ?");
        if ($stmt_delete_foto) {
            $stmt_delete_foto->bind_param("i", $user_id);
            if ($stmt_delete_foto->execute()) {
                $_SESSION['user']['foto'] = null;
                $success_message_foto = "Foto profil berhasil dihapus!";
            }
            $stmt_delete_foto->close();
        }
    }
}

// === LOGIKA: UPLOAD FOTO SAMPUL ===
if (isset($_POST['action']) && $_POST['action'] == 'upload_sampul') {
    if (isset($_FILES['foto_sampul']) && $_FILES['foto_sampul']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto_sampul'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 3 * 1024 * 1024; // 3MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error_message_foto = "Hanya file JPEG, JPG, PNG, dan GIF yang diizinkan untuk sampul.";
        } elseif ($file['size'] > $max_size) {
            $error_message_foto = "Ukuran file sampul maksimal 3MB.";
        } else {
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = 'cover_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $stmt_old = $koneksi->prepare("SELECT foto_sampul FROM user WHERE id = ?");
                $stmt_old->bind_param("i", $user_id);
                $stmt_old->execute();
                $stmt_old->bind_result($current_sampul);
                if ($stmt_old->fetch() && $current_sampul && file_exists($upload_dir . $current_sampul)) {
                    unlink($upload_dir . $current_sampul);
                }
                $stmt_old->close();
                
                $stmt_update_sampul = $koneksi->prepare("UPDATE user SET foto_sampul = ? WHERE id = ?");
                if ($stmt_update_sampul) {
                    $stmt_update_sampul->bind_param("si", $new_filename, $user_id);
                    if ($stmt_update_sampul->execute()) {
                        $_SESSION['user']['foto_sampul'] = $new_filename;
                        $success_message_foto = "Foto sampul berhasil diupload!";
                    } else {
                        $error_message_foto = "Gagal menyimpan foto sampul: " . $stmt_update_sampul->error;
                    }
                    $stmt_update_sampul->close();
                }
            } else {
                $error_message_foto = "Gagal mengupload file sampul.";
            }
        }
    } else {
        $error_message_foto = "Silakan pilih file foto sampul yang valid.";
    }
}

// === LOGIKA 6: UPLOAD TANDA TANGAN (Hanya Akuntan/Owner/Admin) ===
if (isset($_POST['action']) && $_POST['action'] == 'upload_ttd') {
    if (isset($_FILES['tanda_tangan']) && $_FILES['tanda_tangan']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['tanda_tangan'];
        $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
        $max_size = 1 * 1024 * 1024; // 1MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error_message_ttd = "Hanya file PNG, JPEG, atau JPG yang diizinkan (PNG transparan sangat disarankan).";
        } elseif ($file['size'] > $max_size) {
            $error_message_ttd = "Ukuran file maksimal 1MB.";
        } else {
            if (!is_dir($ttd_dir)) { mkdir($ttd_dir, 0755, true); }
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $new_filename = 'ttd_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $ttd_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Hapus TTD lama jika ada
                $stmt_old = $koneksi->prepare("SELECT tanda_tangan FROM user WHERE id = ?");
                $stmt_old->bind_param("i", $user_id);
                $stmt_old->execute();
                $stmt_old->bind_result($current_ttd);
                if ($stmt_old->fetch() && $current_ttd && file_exists($ttd_dir . $current_ttd)) {
                    unlink($ttd_dir . $current_ttd);
                }
                $stmt_old->close();
                
                $stmt_update_ttd = $koneksi->prepare("UPDATE user SET tanda_tangan = ? WHERE id = ?");
                if ($stmt_update_ttd) {
                    $stmt_update_ttd->bind_param("si", $new_filename, $user_id);
                    if ($stmt_update_ttd->execute()) {
                        $_SESSION['user']['tanda_tangan'] = $new_filename;
                        $success_message_ttd = "Tanda tangan digital berhasil diperbarui!";
                        
                        // Sync ke pengaturan global default
                        $koneksi->query("UPDATE pengaturan SET nilai = '$new_filename' WHERE kunci = 'ttd_akuntan_default'");
                    } else {
                        $error_message_ttd = "Gagal menyimpan data TTD: " . $stmt_update_ttd->error;
                    }
                    $stmt_update_ttd->close();
                }
            } else {
                $error_message_ttd = "Gagal mengupload file TTD.";
            }
        }
    } else {
        $error_message_ttd = "Silakan pilih file tanda tangan yang valid.";
    }

    // Simpan Nama Penandatangan jika diisi
    if (isset($_POST['nama_ttd'])) {
        $nama_ttd = trim($_POST['nama_ttd']);
        $stmt_nama = $koneksi->prepare("UPDATE user SET nama_ttd = ? WHERE id = ?");
        $stmt_nama->bind_param("si", $nama_ttd, $user_id);
        if ($stmt_nama->execute()) {
            $_SESSION['user']['nama_ttd'] = $nama_ttd;
            $success_message_ttd = "Tanda tangan dan nama berhasil diperbarui!";
            
            // Sync ke pengaturan global default
            $stmt_def = $koneksi->prepare("UPDATE pengaturan SET nilai = ? WHERE kunci = 'nama_akuntan_default'");
            $stmt_def->bind_param("s", $nama_ttd);
            $stmt_def->execute();
            $stmt_def->close();
        }
        $stmt_nama->close();
    }
}

// === LOGIKA: UPDATE INVOICE & PESANAN COUNTER ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_invoice_counter') {
    $new_inv = (int)$_POST['invoice_counter'];
    $new_pes = (int)($_POST['pesanan_counter'] ?? $new_inv);
    $new_sj  = (int)($_POST['sj_counter'] ?? 0);
    
    if ($new_inv >= 0) {
        $stmt = $koneksi->prepare("UPDATE pengaturan SET nilai = ? WHERE kunci = 'invoice_counter'");
        $val = (string)$new_inv;
        $stmt->bind_param("s", $val);
        $stmt->execute();
        $stmt->close();
    }
    if ($new_pes >= 0) {
        $stmt2 = $koneksi->prepare("UPDATE pengaturan SET nilai = ? WHERE kunci = 'pesanan_counter'");
        $val2 = (string)$new_pes;
        $stmt2->bind_param("s", $val2);
        $stmt2->execute();
        $stmt2->close();
    }
    if ($new_sj >= 0) {
        $stmt3 = $koneksi->prepare("UPDATE pengaturan SET nilai = ? WHERE kunci = 'sj_counter'");
        $val3 = (string)$new_sj;
        $stmt3->bind_param("s", $val3);
        $stmt3->execute();
        $stmt3->close();
    }
    header("Location: pengaturanAkun.php?inv_success=1");
    exit();
}

// === LOGIKA 1: UPDATE PROFIL ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['nama']);
    if (empty($new_name)) {
        $error_message_profile = "Nama tidak boleh kosong.";
    } elseif (strlen($new_name) < 2) {
        $error_message_profile = "Nama terlalu pendek.";
    } else {
        $stmt_check_nama = $koneksi->prepare("SELECT id FROM user WHERE nama = ? AND id != ?");
        $stmt_check_nama->bind_param("si", $new_name, $user_id);
        $stmt_check_nama->execute();
        $stmt_check_nama->store_result();
        if ($stmt_check_nama->num_rows > 0) {
            $error_message_profile = "Nama '$new_name' sudah digunakan.";
        } else {
            $stmt = $koneksi->prepare("UPDATE user SET nama = ? WHERE id = ?");
            $stmt->bind_param("si", $new_name, $user_id);
            if ($stmt->execute()) {
                $success_message_profile = "Nama berhasil diperbarui!";
                $_SESSION['user']['nama'] = $new_name;
                $current_name = $new_name;
            } else {
                $error_message_profile = "Gagal update: " . $stmt->error;
            }
            $stmt->close();
        }
        $stmt_check_nama->close();
    }
}

// === LOGIKA 2: UPDATE PASSWORD PRIBADI ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    if (empty($password_lama) || empty($password_baru)) {
        $error_message_password = "Password wajib diisi.";
    } elseif ($password_baru !== $konfirmasi_password) {
        $error_message_password = "Password baru tidak cocok.";
    } else {
        $stmt_check = $koneksi->prepare("SELECT password FROM user WHERE id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        $stmt_check->bind_result($hashed_password_db);
        $stmt_check->fetch();
        
        if (password_verify($password_lama, $hashed_password_db)) {
            $new_hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt_update = $koneksi->prepare("UPDATE user SET password = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_hashed_password, $user_id);
            if ($stmt_update->execute()) {
                $success_message_password = "Password berhasil diubah!";
            }
            $stmt_update->close();
        } else {
            $error_message_password = "Password lama salah.";
        }
        $stmt_check->close();
    }
}

// === LOGIKA 3: BUAT AKUN BARU ===
if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    $allowed_roles = ['dapur', 'driver'];
    $nama_baru = trim($_POST['nama_baru'] ?? '');
    $role_baru = $_POST['role_baru'] ?? '';
    $password_baru_create = $_POST['password_baru_create'] ?? '';
    $konfirmasi_password_create = $_POST['konfirmasi_password_create'] ?? '';

    if (empty($nama_baru) || empty($role_baru) || empty($password_baru_create)) {
        $error_message_create = 'Semua field wajib diisi!';
    } elseif ($password_baru_create !== $konfirmasi_password_create) {
        $error_message_create = 'Password tidak cocok!';
    } else {
        $stmt_check = $koneksi->prepare("SELECT id FROM user WHERE nama = ?");
        $stmt_check->bind_param("s", $nama_baru);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $error_message_create = 'Nama sudah digunakan.';
        } else {
            $hashed_password = password_hash($password_baru_create, PASSWORD_DEFAULT);
            $stmt_insert = $koneksi->prepare("INSERT INTO user (nama, password, role) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $nama_baru, $hashed_password, $role_baru);
            if ($stmt_insert->execute()) {
                $success_message_create = 'Akun berhasil dibuat!';
            } else {
                $error_message_create = 'Gagal: ' . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

// === LOGIKA 6: RESET PASSWORD AKUN LAIN (ADMIN ONLY) ===
// PERBAIKAN: Menggunakan check pada field 'reset_other_password' yang dikirim via hidden input
if ($user_role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_other_password'])) {
    $target_user_id = $_POST['target_user_id'];
    $new_pass_reset = $_POST['new_pass_reset'];
    $confirm_pass_reset = $_POST['confirm_pass_reset'];

    if (empty($target_user_id) || empty($new_pass_reset) || empty($confirm_pass_reset)) {
        $error_message_reset = "Semua field harus diisi.";
    } elseif (strlen($new_pass_reset) < 6) {
        $error_message_reset = "Password minimal 6 karakter.";
    } elseif ($new_pass_reset !== $confirm_pass_reset) {
        $error_message_reset = "Password tidak cocok.";
    } else {
        $hashed_reset = password_hash($new_pass_reset, PASSWORD_DEFAULT);
        $stmt_reset = $koneksi->prepare("UPDATE user SET password = ? WHERE id = ?");
        
        if ($stmt_reset) {
            $stmt_reset->bind_param("si", $hashed_reset, $target_user_id);
            if ($stmt_reset->execute()) {
                $stmt_name = $koneksi->prepare("SELECT nama FROM user WHERE id = ?");
                $stmt_name->bind_param("i", $target_user_id);
                $stmt_name->execute();
                $res_name = $stmt_name->get_result();
                $d_name = $res_name->fetch_assoc();
                $stmt_name->close();
                
                $success_message_reset = "Password untuk akun <b>" . htmlspecialchars($d_name['nama']) . "</b> berhasil diubah!";
            } else {
                $error_message_reset = "Gagal mengubah password: " . $stmt_reset->error;
            }
            $stmt_reset->close();
        } else {
            $error_message_reset = "Error system: " . $koneksi->error;
        }
    }
}

// Ambil daftar akun lain untuk dropdown
$list_akun_lain = [];
if ($user_role === 'admin') {
    $query_akun = mysqli_query($koneksi, "SELECT id, nama, role FROM user WHERE role != 'admin' ORDER BY role ASC, nama ASC");
    while ($row = mysqli_fetch_assoc($query_akun)) {
        $list_akun_lain[] = $row;
    }
}

// Ambil data counter dokumen
$current_inv = 0; $current_pes = 0; $current_sj = 0;
$query_counters = mysqli_query($koneksi, "SELECT kunci, nilai FROM pengaturan WHERE kunci IN ('invoice_counter', 'pesanan_counter', 'sj_counter')");
if($query_counters){
    while($row = mysqli_fetch_assoc($query_counters)){
        if($row['kunci'] == 'invoice_counter') $current_inv = (int)$row['nilai'];
        if($row['kunci'] == 'pesanan_counter') $current_pes = (int)$row['nilai'];
        if($row['kunci'] == 'sj_counter') $current_sj = (int)$row['nilai'];
    }
}

$bulan_romawi = [
    '01'=>'I', '02'=>'II', '03'=>'III', '04'=>'IV', '05'=>'V', '06'=>'VI',
    '07'=>'VII', '08'=>'VIII', '09'=>'IX', '10'=>'X', '11'=>'XI', '12'=>'XII'
];
$m = date('m');
$y = date('Y');

$preview_inv = str_pad($current_inv + 1, 3, '0', STR_PAD_LEFT) . "/INV-D1/" . $bulan_romawi[$m] . "/" . $y;
$preview_pes = ($current_pes + 1) . "/SCS/PO-DP/" . $bulan_romawi[$m] . "/" . $y;
$preview_sj  = str_pad($current_sj + 1, 3, '0', STR_PAD_LEFT) . "/SJ-SCS/" . $bulan_romawi[$m] . "/" . $y;

// Foto Profil & Sampul
$stmt_get_user = $koneksi->prepare("SELECT foto, foto_sampul FROM user WHERE id = ?");
$stmt_get_user->bind_param("i", $user_id);
$stmt_get_user->execute();
$stmt_get_user->bind_result($current_foto_filename, $current_sampul_filename);
$stmt_get_user->fetch();
$stmt_get_user->close();

$profile_image_src = 'https://media.istockphoto.com/id/824860820/id/foto/kera-barbary.jpg?s=612x612&w=0&k=20&c=8aFvC6ZREgjco5jHClKOjps2T6XbOOSAyTHJ4JbeOHM=';
if ($current_foto_filename && file_exists($upload_dir . $current_foto_filename)) {
    $profile_image_src = $upload_dir . $current_foto_filename . '?t=' . time();
}

$cover_image_src = '';
if ($current_sampul_filename && file_exists($upload_dir . $current_sampul_filename)) {
    $cover_image_src = $upload_dir . $current_sampul_filename . '?t=' . time();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pengaturan profil dan akun pengguna - SCS Banjarnegara">
    <title>Settings - SCS Banjarnegara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family:'Inter',sans-serif; background:#F8F9FB; color:#1E293B; }
        .sidebar-space { margin-left:16rem; }
        @media(max-width:1024px){.sidebar-space{margin-left:0;}}
        ::-webkit-scrollbar{width:5px;} ::-webkit-scrollbar-track{background:transparent;}
        ::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px;}
        /* Card */
        .settings-card{background:#fff;border:1px solid #E8ECF0;border-radius:1rem;box-shadow:0 1px 3px rgba(0,0,0,.04);}
        /* Section divider row */
        .set-section{display:grid;grid-template-columns:1fr 2fr;gap:2rem;padding:1.75rem 0;border-top:1px solid #F1F5F9;align-items:start;}
        @media(max-width:640px){.set-section{grid-template-columns:1fr;gap:.75rem;}}
        .set-label-title{font-size:.8125rem;font-weight:700;color:#0F172A;margin-bottom:.25rem;}
        .set-label-hint{font-size:.7rem;color:#94A3B8;line-height:1.5;}
        /* Input */
        .fi{width:100%;padding:.65rem .9rem;border:1px solid #E2E8F0;border-radius:.6rem;font-size:.8125rem;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s;background:#fff;}
        .fi:focus{border-color:#3B82F6;box-shadow:0 0 0 3px rgba(59,130,246,.1);}
        /* Buttons */
        .btn-save{display:inline-flex;align-items:center;gap:.4rem;background:#0F172A;color:#fff;padding:.55rem 1.25rem;border-radius:.55rem;font-size:.75rem;font-weight:600;border:none;cursor:pointer;transition:background .15s;}
        .btn-save:hover{background:#1E293B;}
        .btn-outline{display:inline-flex;align-items:center;gap:.4rem;background:#fff;color:#475569;border:1px solid #E2E8F0;padding:.5rem 1.1rem;border-radius:.55rem;font-size:.75rem;font-weight:600;cursor:pointer;transition:all .15s;}
        .btn-outline:hover{border-color:#94A3B8;color:#1E293B;}
        .btn-danger{display:inline-flex;align-items:center;gap:.4rem;background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:.45rem .9rem;border-radius:.5rem;font-size:.72rem;font-weight:600;cursor:pointer;transition:all .15s;}
        .btn-danger:hover{background:#DC2626;color:#fff;}
        .btn-blue{display:inline-flex;align-items:center;gap:.4rem;background:#2563EB;color:#fff;padding:.55rem 1.25rem;border-radius:.55rem;font-size:.75rem;font-weight:600;border:none;cursor:pointer;transition:background .15s;}
        .btn-blue:hover{background:#1D4ED8;}
        /* Alert */
        .alert{display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;border-radius:.65rem;font-size:.8rem;font-weight:500;}
        .alert-success{background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;}
        .alert-error{background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;}
        /* Badge */
        .badge-admin{background:#F5F3FF;color:#7C3AED;padding:.15rem .5rem;border-radius:.3rem;font-size:.65rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;}
        .badge-auto{background:#ECFDF5;color:#059669;padding:.15rem .5rem;border-radius:.3rem;font-size:.65rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;}
        /* Counter card */
        .counter-card{border:1px dashed #E2E8F0;border-radius:.75rem;padding:1rem;background:#FAFAFA;}
        /* Blob animation */
        @keyframes blob{0%{transform:translate(0,0) scale(1);}33%{transform:translate(30px,-50px) scale(1.1);}66%{transform:translate(-20px,20px) scale(.9);}100%{transform:translate(0,0) scale(1);}}
        .animate-blob{animation:blob 7s infinite;} .animation-delay-2000{animation-delay:2s;}
        input[type=number]::-webkit-inner-spin-button,input[type=number]::-webkit-outer-spin-button{opacity:1;}
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="sidebar-space min-h-screen transition-all duration-300">
        <div class="px-6 md:px-10 py-8">
        <div class="max-w-4xl mx-auto">
            
            <!-- ALERTS -->
            <?php
            $alerts = [];
            if($error_message_profile) $alerts[] = ['type' => 'error', 'msg' => $error_message_profile];
            if($success_message_profile) $alerts[] = ['type' => 'success', 'msg' => $success_message_profile];
            if($error_message_password) $alerts[] = ['type' => 'error', 'msg' => $error_message_password];
            if($success_message_password) $alerts[] = ['type' => 'success', 'msg' => $success_message_password];
            if($error_message_create) $alerts[] = ['type' => 'error', 'msg' => $error_message_create];
            if($success_message_create) $alerts[] = ['type' => 'success', 'msg' => $success_message_create];
            if($error_message_foto) $alerts[] = ['type' => 'error', 'msg' => $error_message_foto];
            if($success_message_foto) $alerts[] = ['type' => 'success', 'msg' => $success_message_foto];
            if($error_message_reset) $alerts[] = ['type' => 'error', 'msg' => $error_message_reset];
            if($success_message_reset) $alerts[] = ['type' => 'success', 'msg' => $success_message_reset];
            if($error_message_ttd) $alerts[] = ['type' => 'error', 'msg' => $error_message_ttd];
            if($success_message_ttd) $alerts[] = ['type' => 'success', 'msg' => $success_message_ttd];
            if(isset($_GET['inv_success'])) $alerts[] = ['type' => 'success', 'msg' => 'Pengaturan nomor dokumen berhasil diperbarui!'];
            ?>
            <?php if(!empty($alerts)): ?>
            <div class="mb-6 space-y-2">
                <?php foreach($alerts as $alert): ?>
                    <div class="alert <?php echo $alert['type']=='success' ? 'alert-success' : 'alert-error'; ?>">
                        <i class="fas <?php echo $alert['type']=='success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> flex-shrink-0"></i>
                        <span><?php echo strip_tags($alert['msg'])==$alert['msg'] ? htmlspecialchars($alert['msg']) : $alert['msg']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- SETTINGS CARD: PROFIL -->
            <div class="settings-card mb-5">
                <!-- Card header with avatar -->
                <div class="px-7 py-5 border-b border-slate-100 flex items-center gap-5">
                    <div class="w-14 h-14 rounded-2xl overflow-hidden border border-slate-100 shrink-0 relative group/av">
                        <img id="profileImage" src="<?php echo htmlspecialchars($profile_image_src); ?>" alt="Foto Profil" class="w-full h-full object-cover">
                        <label for="uploadPhotoInput" class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover/av:opacity-100 transition-opacity cursor-pointer">
                            <i class="fas fa-camera text-white text-sm"></i>
                        </label>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars($current_name); ?></div>
                        <div class="text-xs text-slate-400 mt-0.5 capitalize flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 inline-block"></span><?php echo htmlspecialchars($user_role); ?> · Aktif
                        </div>
                    </div>
                    <!-- Hidden cover upload (preserved) -->
                    <form method="POST" enctype="multipart/form-data" id="form-upload-sampul" class="ml-auto">
                        <input type="hidden" name="action" value="upload_sampul">
                        <input id="uploadSampulInput" name="foto_sampul" type="file" accept="image/*" class="hidden" onchange="previewAndSubmitSampul(event)">
                        <label for="uploadSampulInput" class="btn-outline cursor-pointer" style="font-size:.7rem">
                            <i class="fas fa-image text-slate-400"></i> <?php echo $cover_image_src ? 'Ganti Sampul' : 'Sampul'; ?>
                        </label>
                    </form>
                    <?php if ($cover_image_src): ?>
                    <form method="POST" action="pengaturanAkun.php">
                        <input type="hidden" name="action" value="hapus_sampul">
                        <button type="submit" name="hapus_sampul_submit" onclick="return confirm('Hapus foto sampul?')" class="btn-danger"><i class="fas fa-trash-alt"></i></button>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="px-7 pb-6">
                    <!-- FORM 1: Nama -->
                    <form action="pengaturanAkun.php" method="POST" id="form-profile">
                        <div class="set-section">
                            <div>
                                <div class="set-label-title">Nama Lengkap</div>
                                <div class="set-label-hint">Nama tampilan untuk seluruh sistem.</div>
                            </div>
                            <div class="flex gap-2">
                                <input name="nama" value="<?php echo htmlspecialchars($current_name); ?>" type="text" class="fi flex-1" required>
                                <button type="submit" name="update_profile" class="btn-save shrink-0">Simpan</button>
                            </div>
                        </div>
                    </form>

                    <!-- FORM 2: Foto Profil -->
                    <div class="set-section">
                        <div>
                            <div class="set-label-title">Foto Profil</div>
                            <div class="set-label-hint">Terlihat oleh pengguna lain di sistem.</div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-xl overflow-hidden border border-slate-100 shrink-0">
                                <img src="<?php echo htmlspecialchars($profile_image_src); ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex flex-col gap-2">
                                <form method="POST" enctype="multipart/form-data" id="form-upload-foto">
                                    <input type="hidden" name="action" value="upload_foto">
                                    <input id="uploadPhotoInput" name="foto_profil" type="file" accept="image/*" class="hidden" onchange="previewAndSubmit(event)">
                                    <label for="uploadPhotoInput" class="btn-outline cursor-pointer" style="font-size:.72rem">
                                        <i class="fas fa-cloud-upload-alt text-slate-400"></i> Unggah foto baru
                                    </label>
                                </form>
                                <?php if ($current_foto_filename): ?>
                                <form method="POST" action="pengaturanAkun.php">
                                    <input type="hidden" name="action" value="hapus_foto">
                                    <button type="submit" name="hapus_foto_submit" onclick="return confirm('Hapus foto profil?')" class="btn-danger" style="font-size:.7rem">
                                        <i class="fas fa-trash-alt"></i> Hapus foto
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- FORM 3: Password -->
                    <form action="pengaturanAkun.php" method="POST">
                        <div class="set-section">
                            <div>
                                <div class="set-label-title">Keamanan & Kata Sandi</div>
                                <div class="set-label-hint">Perbarui kredensial login Anda.</div>
                            </div>
                            <div class="space-y-2.5">
                                <input name="password_lama" type="password" placeholder="Kata Sandi Saat Ini" class="fi" required>
                                <div class="grid grid-cols-2 gap-2.5">
                                    <input name="password_baru" type="password" placeholder="Kata Sandi Baru" class="fi" required>
                                    <input name="konfirmasi_password" type="password" placeholder="Konfirmasi" class="fi" required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" name="update_password" class="btn-save">Perbarui Kata Sandi</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (in_array($user_role, ['akuntan', 'owner', 'admin'])): ?>
            <!-- SETTINGS CARD: DOKUMEN -->
            <div class="settings-card mb-5">
                <div class="px-7 py-4 border-b border-slate-100">
                    <div class="font-bold text-slate-900 text-sm">Dokumen & Tanda Tangan</div>
                    <div class="text-xs text-slate-400 mt-0.5">Pengaturan untuk invoice dan dokumen resmi.</div>
                </div>
                <div class="px-7 pb-6">
                    <!-- FORM 4: TTD -->
                    <form action="pengaturanAkun.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_ttd">
                        <div class="set-section">
                            <div>
                                <div class="set-label-title flex items-center gap-2">Tanda Tangan <span class="badge-admin" style="background:#EFF6FF;color:#2563EB">Invoice</span></div>
                                <div class="set-label-hint">Diterapkan otomatis pada dokumen resmi.</div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex gap-4 items-center">
                                    <div class="w-28 h-16 bg-slate-50 border border-slate-200 rounded-xl overflow-hidden flex items-center justify-center shrink-0">
                                        <?php $ttd_path = isset($_SESSION['user']['tanda_tangan']) ? $ttd_dir . $_SESSION['user']['tanda_tangan'] : null;
                                        if ($ttd_path && file_exists($ttd_path)): ?>
                                            <img src="<?php echo $ttd_path; ?>" class="max-h-full max-w-full object-contain">
                                        <?php else: ?>
                                            <i class="fas fa-signature text-slate-300 text-xl"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <input type="file" name="tanda_tangan" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-600 hover:file:bg-slate-200 cursor-pointer border border-slate-200 rounded-lg p-1 bg-white">
                                        <input type="text" name="nama_ttd" value="<?php echo htmlspecialchars($_SESSION['user']['nama_ttd'] ?? $_SESSION['user']['nama']); ?>" placeholder="Nama Penanda Tangan" class="fi">
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-outline">Simpan Tanda Tangan</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- FORM 5: Counter -->
                    <form action="pengaturanAkun.php" method="POST">
                        <input type="hidden" name="action" value="update_invoice_counter">
                        <div class="set-section">
                            <div>
                                <div class="set-label-title flex items-center gap-2">Nomor Dokumen <span class="badge-auto">Auto-Increment</span></div>
                                <div class="set-label-hint">Set ke nomor terakhir yang sudah dipakai secara manual.</div>
                            </div>
                            <div class="space-y-3">
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="counter-card">
                                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">Invoice</label>
                                        <input type="number" name="invoice_counter" value="<?= $current_inv ?>" min="0" class="fi text-center font-bold text-base mb-1.5">
                                        <p class="text-[10px] text-slate-400">Next: <span class="text-blue-500"><?= $preview_inv ?></span></p>
                                    </div>
                                    <div class="counter-card">
                                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">PO Dapur</label>
                                        <input type="number" name="pesanan_counter" value="<?= $current_pes ?>" min="0" class="fi text-center font-bold text-base mb-1.5">
                                        <p class="text-[10px] text-slate-400">Next: <span class="text-blue-500"><?= $preview_pes ?></span></p>
                                    </div>
                                    <div class="counter-card">
                                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">Surat Jalan</label>
                                        <input type="number" name="sj_counter" value="<?= $current_sj ?>" min="0" class="fi text-center font-bold text-base mb-1.5">
                                        <p class="text-[10px] text-slate-400">Next: <span class="text-blue-500"><?= $preview_sj ?></span></p>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-outline">Simpan Penghitung</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($user_role === 'admin'): ?>
            <!-- SETTINGS CARD: MANAJEMEN AKUN -->
            <div class="settings-card mb-5">
                <div class="px-7 py-4 border-b border-slate-100">
                    <div class="font-bold text-slate-900 text-sm flex items-center gap-2">
                        Manajemen Pengguna <span class="badge-admin">Admin</span>
                    </div>
                    <div class="text-xs text-slate-400 mt-0.5">Buat dan kelola akun pengguna sistem.</div>
                </div>
                <div class="px-7 pb-6">
                    <!-- FORM 6: Buat Akun Baru -->
                    <form method="POST" action="pengaturanAkun.php">
                        <input type="hidden" name="create_account" value="1">
                        <div class="set-section">
                            <div>
                                <div class="set-label-title">Buat Pengguna Baru</div>
                                <div class="set-label-hint">Tambahkan akun Dapur atau Driver baru ke sistem.</div>
                            </div>
                            <div class="space-y-2.5">
                                <div class="grid grid-cols-2 gap-2.5">
                                    <input type="text" name="nama_baru" placeholder="Nama Lengkap" class="fi" required>
                                    <div class="relative">
                                        <select name="role_baru" class="fi appearance-none cursor-pointer" required>
                                            <option value="" disabled selected>Pilih Peran</option>
                                            <option value="dapur">Dapur</option>
                                            <option value="driver">Driver</option>
                                        </select>
                                        <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[9px] text-slate-400 pointer-events-none"></i>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2.5">
                                    <input type="password" name="password_baru_create" placeholder="Kata Sandi" class="fi" required>
                                    <input type="password" name="konfirmasi_password_create" placeholder="Konfirmasi" class="fi" required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-blue">Buat Akun</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- FORM 7: Reset Password -->
                    <form method="POST" action="pengaturanAkun.php">
                        <input type="hidden" name="reset_other_password" value="true">
                        <div class="set-section">
                            <div>
                                <div class="set-label-title">Setel Ulang Kata Sandi</div>
                                <div class="set-label-hint">Ganti kata sandi untuk akun pengguna yang ada.</div>
                            </div>
                            <div class="space-y-2.5">
                                <div class="relative">
                                    <select name="target_user_id" class="fi appearance-none cursor-pointer" required>
                                        <option value="" disabled selected>Pilih Pengguna...</option>
                                        <?php foreach ($list_akun_lain as $akun): ?>
                                            <option value="<?php echo $akun['id']; ?>">
                                                <?php echo htmlspecialchars($akun['nama']); ?> (<?php echo ucfirst($akun['role']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[9px] text-slate-400 pointer-events-none"></i>
                                </div>
                                <div class="grid grid-cols-2 gap-2.5">
                                    <input type="password" name="new_pass_reset" placeholder="Kata Sandi Baru" class="fi" required>
                                    <input type="password" name="confirm_pass_reset" placeholder="Konfirmasi" class="fi" required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="submit" class="btn-danger" style="font-size:.75rem;padding:.5rem 1.1rem">
                                        <i class="fas fa-key"></i> Paksa Atur Ulang
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /max-w-4xl -->
        </div><!-- /px-6 py-8 -->
    </div><!-- /sidebar-space -->

    <script>
        function previewAndSubmit(event) {
            const file = event.target.files[0];
            if(file) {
                if(file.size > 2*1024*1024) { alert('Ukuran file maksimal 2MB!'); return; }
                document.getElementById('form-upload-foto').submit();
            }
        }

        function previewAndSubmitSampul(event) {
            const file = event.target.files[0];
            if(file) {
                if(file.size > 3*1024*1024) { alert('Ukuran file maksimal 3MB!'); return; }
                document.getElementById('form-upload-sampul').submit();
            }
        }
    </script>
</body>
</html>
