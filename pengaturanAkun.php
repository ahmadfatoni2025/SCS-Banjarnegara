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

// Foto Profil
$current_foto_filename = $_SESSION['user']['foto'] ?? null;
$profile_image_src = 'https://media.istockphoto.com/id/824860820/id/foto/kera-barbary.jpg?s=612x612&w=0&k=20&c=8aFvC6ZREgjco5jHClKOjps2T6XbOOSAyTHJ4JbeOHM=';
if ($current_foto_filename && file_exists($upload_dir . $current_foto_filename)) {
    $profile_image_src = $upload_dir . $current_foto_filename . '?t=' . time();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - MBG Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Styles tetap sama */
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); height: 100vh; overflow: hidden; }
        .full-height-container { height: 100vh; display: flex; flex-direction: column; }
        .content-wrapper { flex: 1; overflow-y: auto; padding: 0; }
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); height: fit-content; }
        .form-input { border: 1px solid #d1d5db; border-radius: 8px; }
        .grid-container { display: grid; grid-template-columns: 1fr; gap: 1.5rem; height: 100%; padding: 1.5rem; }
        @media (min-width: 1024px) {
            .grid-container { grid-template-columns: 1fr 1fr; gap: 2rem; padding: 2rem; }
            .profile-card { grid-column: 1; }
            .admin-column { grid-column: 2; grid-row: 1; display: flex; flex-direction: column; gap: 2rem; }
        }
        .btn-primary { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-info { background-color: #dbeafe; color: #1e40af; }
        .alert { padding: 0.75rem 1rem; border-radius: 8px; display: flex; align-items: center; margin-bottom: 1rem; }
        .alert-error { background-color: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .alert-success { background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; }
        .profile-image-container { position: relative; display: inline-block; }
        .profile-image-overlay { position: absolute; bottom: 8px; right: 8px; background: #2563eb; color: white; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .hidden-file-input { width: 0.1px; height: 0.1px; opacity: 0; position: absolute; z-index: -1; }
        .section-divider { height: 1px; background: #e5e7eb; margin: 2rem 0; }
    </style>
</head>
<body class="full-height-container">
    <?php include 'sidebar.php'; ?>
    
    <div class="content-wrapper ml-0 md:ml-64 fade-in">
        <div class="grid-container">
            <div class="card p-8 profile-card">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                    <h2 class="text-2xl font-semibold text-gray-800 flex items-center mb-4 md:mb-0">
                        <i class="fas fa-user-circle text-blue-600 mr-3"></i>
                        Profil Saya
                    </h2>
                    <span class="badge badge-info capitalize">
                        <i class="fas fa-user-tag mr-1"></i>
                        <?php echo htmlspecialchars($user_role); ?>
                    </span>
                </div>

                <div class="flex flex-col items-center mb-8">
                    <div class="profile-image-container">
                        <img id="profileImage" src="<?php echo htmlspecialchars($profile_image_src); ?>" 
                             alt="Foto Profil" class="w-32 h-32 rounded-full object-cover border-4 border-blue-200 shadow-lg">
                        <label for="uploadPhotoInput" class="profile-image-overlay cursor-pointer">
                            <i class="fas fa-camera text-sm"></i>
                        </label>
                        <?php if ($current_foto_filename): ?>
                        <form method="POST" action="pengaturanAkun.php" class="absolute top-0 left-0">
                            <input type="hidden" name="action" value="hapus_foto">
                            <button type="submit" name="hapus_foto_submit" 
                                    class="w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition"
                                    onclick="return confirm('Hapus foto profil?')" title="Hapus Foto">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="mt-4 text-center" id="form-upload-foto">
                        <input type="hidden" name="action" value="upload_foto">
                        <input id="uploadPhotoInput" name="foto_profil" type="file" accept="image/*" class="hidden-file-input" onchange="previewAndSubmit(event)">
                        <label for="uploadPhotoInput" class="btn-primary px-4 py-2 rounded-lg transition flex items-center mx-auto cursor-pointer">
                            <i class="fas fa-upload mr-2"></i> Upload Foto Baru
                        </label>
                        <?php if ($error_message_foto): ?><div class="alert alert-error mt-4"><?php echo htmlspecialchars($error_message_foto); ?></div><?php endif; ?>
                        <?php if ($success_message_foto): ?><div class="alert alert-success mt-4"><?php echo htmlspecialchars($success_message_foto); ?></div><?php endif; ?>
                    </form>
                </div>

                <form action="pengaturanAkun.php" method="POST" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Profil</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap</label>
                        <input name="nama" value="<?php echo htmlspecialchars($current_name); ?>" type="text" class="w-full px-4 py-3 form-input" required>
                    </div>
                    <?php if ($error_message_profile): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message_profile); ?></div><?php endif; ?>
                    <?php if ($success_message_profile): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message_profile); ?></div><?php endif; ?>
                    <div class="flex justify-end">
                        <button type="submit" name="update_profile" class="btn-primary font-semibold py-3 px-6 rounded-lg transition">Simpan Perubahan</button>
                    </div>
                </form>

                <div class="section-divider"></div>

                <form action="pengaturanAkun.php" method="POST" class="space-y-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Keamanan Akun</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password Lama</label>
                            <input name="password_lama" type="password" class="w-full px-4 py-3 form-input" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                            <input name="password_baru" type="password" class="w-full px-4 py-3 form-input" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                            <input name="konfirmasi_password" type="password" class="w-full px-4 py-3 form-input" required>
                        </div>
                    </div>
                    <?php if ($error_message_password): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message_password); ?></div><?php endif; ?>
                    <?php if ($success_message_password): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message_password); ?></div><?php endif; ?>
                    <div class="flex justify-end">
                        <button type="submit" name="update_password" class="btn-warning text-white font-semibold py-3 px-6 rounded-lg transition">Ubah Password</button>
                    </div>
                </form>

                <?php if (in_array($user_role, ['akuntan', 'owner', 'admin'])): ?>
                <div class="section-divider"></div>
                <form action="pengaturanAkun.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="upload_ttd">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
                        <h3 class="text-lg font-semibold text-gray-800">Tanda Tangan Digital</h3>
                        <span class="text-[10px] bg-blue-50 text-blue-600 px-2 py-1 rounded-md font-bold">AKUNTANSI</span>
                    </div>
                    <p class="text-xs text-gray-500 italic">Tanda tangan ini akan muncul otomatis di setiap Invoice yang Anda konfirmasi. Disarankan menggunakan file PNG transparan.</p>
                    
                    <div class="flex items-center space-x-6 bg-gray-50 p-6 rounded-2xl border border-dashed border-gray-300">
                        <div class="flex-shrink-0 w-32 h-20 bg-white border border-gray-200 rounded-lg overflow-hidden flex items-center justify-center">
                            <?php 
                            $ttd_path = isset($_SESSION['user']['tanda_tangan']) ? $ttd_dir . $_SESSION['user']['tanda_tangan'] : null;
                            if ($ttd_path && file_exists($ttd_path)): ?>
                                <img src="<?php echo $ttd_path; ?>" class="max-h-full max-w-full object-contain">
                            <?php else: ?>
                                <i class="fas fa-signature text-gray-300 text-3xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ganti File TTD</label>
                                <input type="file" name="tanda_tangan" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama yang Muncul di Invoice</label>
                                <input type="text" name="nama_ttd" value="<?php echo htmlspecialchars($_SESSION['user']['nama_ttd'] ?? $_SESSION['user']['nama']); ?>" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-sm font-semibold">
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($error_message_ttd): ?><div class="alert alert-error"><?php echo htmlspecialchars($error_message_ttd); ?></div><?php endif; ?>
                    <?php if ($success_message_ttd): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message_ttd); ?></div><?php endif; ?>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-slate-800 text-white font-semibold py-3 px-6 rounded-lg hover:bg-slate-900 transition flex items-center gap-2">
                            <i class="fas fa-upload text-sm"></i> Simpan Tanda Tangan
                        </button>
                    </div>
                </form>

                <!-- PENGATURAN NOMOR INVOICE & PESANAN -->
                <div class="section-divider"></div>
                <form action="pengaturanAkun.php" method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_invoice_counter">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
                        <h3 class="text-lg font-semibold text-gray-800">Pengaturan Nomor Dokumen</h3>
                        <span class="text-[10px] bg-emerald-50 text-emerald-600 px-2 py-1 rounded-md font-bold">AUTO-INCREMENT</span>
                    </div>
                    <p class="text-xs text-gray-500 italic">Nomor ini akan otomatis naik +1 setiap ada pesanan baru. Set ke nomor terakhir yang sudah dipakai secara manual.</p>
                    
                    <?php
                        $bulan_romawi_p = ["", "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI", "XII"];
                        $q_inv = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'invoice_counter'");
                        $current_inv = $q_inv ? (int)$q_inv->fetch_assoc()['nilai'] : 0;
                        $q_pes = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'pesanan_counter'");
                        $current_pes = $q_pes ? (int)$q_pes->fetch_assoc()['nilai'] : 0;
                        $q_sj = $koneksi->query("SELECT nilai FROM pengaturan WHERE kunci = 'sj_counter'");
                        $current_sj = $q_sj ? (int)$q_sj->fetch_assoc()['nilai'] : 0;
                        
                        $preview_inv = str_pad($current_inv + 1, 3, '0', STR_PAD_LEFT) . "/INV-D1/" . $bulan_romawi_p[(int)date('m')] . "/" . date('Y');
                        $preview_pes = ($current_pes + 1) . "/SCS/PO-DP/" . $bulan_romawi_p[(int)date('m')] . "/" . date('Y');
                        $preview_sj = str_pad($current_sj + 1, 3, '0', STR_PAD_LEFT) . "/SJ-SCS/" . $bulan_romawi_p[(int)date('m')] . "/" . date('Y');
                    ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-xl border border-dashed border-gray-300">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Invoice Terakhir</label>
                            <input type="number" name="invoice_counter" value="<?= $current_inv ?>" min="0" 
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-md font-bold text-center mb-2">
                            <p class="text-[9px] text-gray-500 truncate">Next: <span class="text-blue-600"><?= $preview_inv ?></span></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-dashed border-gray-300">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">PO Terakhir</label>
                            <input type="number" name="pesanan_counter" value="<?= $current_pes ?>" min="0" 
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-md font-bold text-center mb-2">
                            <p class="text-[9px] text-gray-500 truncate">Next: <span class="text-blue-600"><?= $preview_pes ?></span></p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-xl border border-dashed border-gray-300">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">Surat Jalan Terakhir</label>
                            <input type="number" name="sj_counter" value="<?= $current_sj ?>" min="0" 
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none text-md font-bold text-center mb-2">
                            <p class="text-[9px] text-gray-500 truncate">Next: <span class="text-blue-600"><?= $preview_sj ?></span></p>
                        </div>
                    </div>

                    <?php if (isset($_GET['inv_success'])): ?>
                        <div class="alert alert-success">Pengaturan nomor dokumen berhasil diperbarui!</div>
                    <?php endif; ?>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-emerald-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-emerald-700 transition flex items-center gap-2">
                            <i class="fas fa-save text-sm"></i> Simpan Pengaturan Dokumen
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <?php if ($user_role === 'admin'): ?>
            <div class="admin-column">
                
                <div class="card p-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800 flex items-center mb-4 md:mb-0">
                            <i class="fas fa-user-plus text-blue-600 mr-3"></i> Buat Akun Baru
                        </h2>
                        <span class="badge badge-success"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                    </div>
                    <?php if ($error_message_create): ?><div class="alert alert-error mb-6"><?php echo htmlspecialchars($error_message_create); ?></div><?php endif; ?>
                    <?php if ($success_message_create): ?><div class="alert alert-success mb-6"><?php echo htmlspecialchars($success_message_create); ?></div><?php endif; ?>

                    <form method="POST" action="pengaturanAkun.php" class="space-y-6">
                        <input type="hidden" name="create_account" value="1"> <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Akun Baru</label>
                                <input type="text" name="nama_baru" class="w-full px-4 py-3 form-input" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Role Akun</label>
                                <select name="role_baru" class="w-full px-4 py-3 form-input" required>
                                    <option value="" disabled selected>Pilih Role</option>
                                    <option value="dapur">Dapur</option>
                                    <option value="driver">Driver</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                <input type="password" name="password_baru_create" class="w-full px-4 py-3 form-input" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                                <input type="password" name="konfirmasi_password_create" class="w-full px-4 py-3 form-input" required>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn-success font-semibold py-3 px-8 rounded-lg transition">Buat Akun Baru</button>
                        </div>
                    </form>
                </div>

                <div class="card p-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800 flex items-center mb-4 md:mb-0">
                            <i class="fas fa-users-cog text-orange-500 mr-3"></i> Ganti Sandi User
                        </h2>
                        <span class="badge badge-success"><i class="fas fa-shield-alt mr-1"></i> Admin</span>
                    </div>

                    <?php if ($error_message_reset): ?><div class="alert alert-error mb-6"><?php echo htmlspecialchars($error_message_reset); ?></div><?php endif; ?>
                    <?php if ($success_message_reset): ?><div class="alert alert-success mb-6"><?php echo $success_message_reset; ?></div><?php endif; ?>

                    <form method="POST" action="pengaturanAkun.php" class="space-y-6">
                        <input type="hidden" name="reset_other_password" value="true">

                        <div class="space-y-4">
                            <div>
                                <label for="target_user_id" class="block text-sm font-medium text-gray-700 mb-2">Pilih Akun</label>
                                <select id="target_user_id" name="target_user_id" class="w-full px-4 py-3 form-input" required>
                                    <option value="" disabled selected>Pilih User...</option>
                                    <?php foreach ($list_akun_lain as $akun): ?>
                                        <option value="<?php echo $akun['id']; ?>">
                                            <?php echo htmlspecialchars($akun['nama']); ?> (<?php echo ucfirst($akun['role']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                                <input type="password" name="new_pass_reset" class="w-full px-4 py-3 form-input" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                                <input type="password" name="confirm_pass_reset" class="w-full px-4 py-3 form-input" required>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="btn-warning text-white font-semibold py-3 px-8 rounded-lg transition">Ganti Sandi</button>
                        </div>
                    </form>
                </div>

            </div>
            <?php endif; ?>
        </div>
    </div>

<script>
    function previewAndSubmit(event) {
        const file = event.target.files[0];
        if(file) {
            if(file.size > 2*1024*1024) { alert('Ukuran file maksimal 2MB!'); return; }
            document.getElementById('form-upload-foto').submit();
        }
    }

    // JS Loading Spinner
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                // Jangan spinner untuk upload/hapus foto
                if (this.id === 'form-upload-foto' || this.querySelector('button[name="hapus_foto_submit"]')) return;
                
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';
                    submitBtn.disabled = true; // Button disabled = valuenya tidak terkirim
                    // Solusi: Kita sudah pakai input type="hidden" di form, jadi aman.
                    setTimeout(() => { submitBtn.disabled = false; submitBtn.innerHTML = 'Simpan'; }, 8000);
                }
            });
        });
    });
</script>
</body>
</html>
