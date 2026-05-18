<?php
// Selalu mulai session di awal halaman
session_start();

// (OPSIONAL) Jika pengguna sudah login, arahkan ke dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: index.php');
        exit();
    } elseif ($_SESSION['role'] === 'dapur') {
        header('Location: dapur.php');
        exit();
    } elseif ($_SESSION['role'] === 'driver') {
        header('Location: driver.php');
        exit();
    }
}

// Sertakan file koneksi database
include 'koneksi.php';

$error_message = '';
$success_message = '';

// ================== LOGIKA BARU (UNTUK TABEL 'user') ==================
// Role yang diizinkan untuk mendaftar
$allowed_roles = ['dapur', 'driver'];

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil semua data dari form
    $nama = $_POST['nama'] ?? '';
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    // Validasi sederhana
    if (empty($nama) || empty($role) || empty($password) || empty($konfirmasi_password)) {
        $error_message = 'Semua field wajib diisi!';
    } elseif ($password !== $konfirmasi_password) {
        $error_message = 'Password dan konfirmasi password tidak cocok!';
    } elseif (!in_array($role, $allowed_roles)) {
         $error_message = 'Role yang dipilih tidak valid!'; // Hanya 'dapur' dan 'driver'
    } else {
        
        // 1. Cek duplikat 'nama' di tabel 'user'
        // Nama harus unik di seluruh sistem
        $stmt_check = $koneksi->prepare("SELECT id FROM user WHERE nama = ?");
        
        if ($stmt_check === false) {
            $error_message = 'Kesalahan konfigurasi database (check). Hubungi admin.';
        } else {
            $stmt_check->bind_param("s", $nama);
            $stmt_check->execute();
            $stmt_check->store_result();
            
            if ($stmt_check->num_rows > 0) {
                // Nama sudah terpakai
                $error_message = 'Nama "' . htmlspecialchars($nama) . '" sudah digunakan. Silakan pilih nama lain.';
            } else {
                // Nama tersedia, lanjutkan pendaftaran
                
                // --- KEAMANAN PENTING: HASH PASSWORD ---
                // PASTIKAN kolom 'password' di tabel 'user' adalah VARCHAR(255)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 2. Insert ke satu tabel 'user'
                $stmt_insert = $koneksi->prepare("INSERT INTO user (nama, password, role) VALUES (?, ?, ?)");
                
                if ($stmt_insert === false) {
                    $error_message = 'Kesalahan konfigurasi database (insert). Hubungi admin.';
                } else {
                    // "sss" = string, string, string (untuk nama, password, role)
                    $stmt_insert->bind_param("sss", $nama, $hashed_password, $role);

                    if ($stmt_insert->execute()) {
                        $success_message = 'Akun ' . $role . ' berhasil dibuat! Silakan login.';
                        $_POST = array(); // Kosongkan form setelah sukses
                    } else {
                        $error_message = 'Terjadi kesalahan pada server. Coba lagi nanti.';
                    }
                    
                    $stmt_insert->close();
                }
            }
            
            $stmt_check->close();
        }
    }
}
// ================== AKHIR LOGIKA BARU ==================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Akun Baru - MBG Catering</title>
    <!-- Desain tidak diubah -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }
    </style>
</head>
<!-- Desain tidak diubah -->
<body class="bg-gray-100 text-gray-900 flex items-center justify-center min-h-screen p-4 sm:p-8">

    <div class="bg-white rounded-3xl shadow-2xl overflow-hidden max-w-4xl w-full flex flex-col md:flex-row border border-gray-200">

        <!-- Kolom Kiri (Biru) -->
        <div class="bg-blue-600 text-white p-12 md:w-7/12 flex flex-col justify-center items-center text-center">
            <div class="max-w-md"> 
                <div class="mb-6"> 
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h1 class="text-4xl font-extrabold mb-4">
                    Selamat Datang!
                </h1>
                <p class="text-lg text-blue-200 px-4">
                    Buat akun baru untuk mulai mengelola inventaris dapur dan pengiriman.
                </p>
            </div>
        </div>

        <!-- Kolom Kanan (Form) -->
        <div class="flex flex-col justify-center p-12 sm:p-16 md:w-7/12">
            
            <h1 id="judul-registrasi" class="text-3xl font-bold text-gray-800 mb-8">
                Buat Akun Baru
            </h1>

            <!-- Pesan Error (Tidak diubah) -->
            <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Pesan Sukses (Tidak diubah) -->
            <?php if ($success_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Pendaftaran (Desain tidak diubah) -->
            <form method="POST" action="buatAkun.php" class="space-y-6">
                
                <div>
                    <label for="nama" class="block text-base font-medium text-gray-700">Nama</label>
                    <input type="text" id="nama" name="nama" 
                           class="mt-2 block w-full px-5 py-4 border border-gray-300 bg-white rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 text-lg transition duration-150" 
                           value="<?php echo htmlspecialchars($_POST['nama'] ?? ''); ?>" required>
                </div>
                
                <div>
                    <label for="role" class="block text-base font-medium text-gray-700">Daftar Sebagai</label>
                    <select id="role" name="role" 
                            class="mt-2 block w-full px-5 py-4 border border-gray-300 bg-white rounded-xl shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 text-lg transition duration-150" 
                            required>
                        <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>-- Pilih Role --</option>
                        <option value="dapur" <?php echo ($_POST['role'] ?? '') === 'dapur' ? 'selected' : ''; ?>>Dapur</option>
                        <option value="driver" <?php echo ($_POST['role'] ?? '') === 'driver' ? 'selected' : ''; ?>>Driver</option>
                    </select>
                </div>
                
                <div>
                    <label for="password" class="block text-base font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" 
                           class="mt-2 block w-full px-5 py-4 border border-gray-300 bg-white rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 text-lg transition duration-150" 
                           required>
                </div>

                <div>
                    <label for="konfirmasi_password" class="block text-base font-medium text-gray-700">Konfirmasi Password</label>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" 
                           class="mt-2 block w-full px-5 py-4 border border-gray-300 bg-white rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-900 text-lg transition duration-150" 
                           required>
                </div>

                <div>
                    <button type="submit" 
                            class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg text-lg font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 focus:ring-offset-white transition-colors">
                        Daftar
                    </button>
                </div>
            </form>

            <div class="mt-8 text-center text-base text-gray-600">
                <p>
                    Sudah punya akun? 
                    <a href="login.php" class="font-bold text-blue-600 hover:text-blue-800 transition-colors">
                        Login di sini
                    </a>
                </p>
            </div>
        </div>

    </div>

    <!-- JavaScript untuk judul dinamis (Tidak diubah) -->
    <script>
        function updateJudul() {
            var judulH1 = document.getElementById('judul-registrasi');
            var selectRole = document.getElementById('role');
            var selectedOption = selectRole.options[selectRole.selectedIndex];
            var roleText = selectedOption.value; // 'driver', 'dapur'

            if (roleText) {
                var roleDisplay = roleText.charAt(0).toUpperCase() + roleText.slice(1);
                judulH1.innerHTML = 'Buat Akun ' + roleDisplay;
            } else {
                judulH1.innerHTML = 'Buat Akun Baru';
            }
        }
    
        document.getElementById('role').addEventListener('change', updateJudul);
        document.addEventListener('DOMContentLoaded', updateJudul);
    </script>

</body>
</html>

