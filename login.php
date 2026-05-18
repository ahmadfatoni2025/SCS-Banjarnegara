<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Selalu mulai session di awal halaman
session_start();

// ✅✅✅ TAMBAHKAN INI - Sertakan file koneksi database
include 'koneksi.php';
$dapur_users = [];
$res_dapur = $koneksi->query("SELECT nama FROM user WHERE role = 'dapur' ORDER BY nama ASC");
if ($res_dapur) {
    while ($row = $res_dapur->fetch_assoc()) {
        $dapur_users[] = $row['nama'];
    }
}

$error_message = '';

// Cek apakah form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Ambil data dari form
    $login_type = $_POST['login_type'] ?? 'umum'; 
    
    // Jika dapur, ambil dari dropdown
    if ($login_type === 'dapur') {
        $username = $_POST['username_dapur'] ?? '';
        $password = ''; // Dapur tidak butuh password
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
    }

    // Validasi dasar
    if (empty($username) || ($login_type !== 'dapur' && empty($password))) {
        $error_message = ($login_type === 'dapur') ? "Harap pilih Nama Dapur!" : "Username dan Password wajib diisi!";
    } else {
        
        // --- LOGIKA LOGIN ---
        
        // Sesuaikan query berdasarkan tipe login
        if ($login_type === 'dapur') {
            $sql = "SELECT id, nama, password, role, foto FROM user WHERE nama = ? AND role = 'dapur'";
        } else {
            // Untuk umum (admin/akuntan), cari berdasarkan username dan pastikan bukan role dapur
            $sql = "SELECT id, nama, password, role, foto FROM user WHERE nama = ? AND role != 'dapur'";
        }
        
        $stmt = $koneksi->prepare($sql);
        
        if ($stmt === false) {
             $error_message = "Terjadi kesalahan server. Silakan coba lagi. (Prepare failed)";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            
            // 2. Simpan hasilnya
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Username cocok
                
                // === PERUBAHAN 2: Tambahkan $db_foto untuk menampung data ===
                $stmt->bind_result($db_id, $db_nama, $db_password, $db_role, $db_foto); 
                
                // Ambil datanya
                $stmt->fetch();
                
                // 4. Verifikasi password (Hanya jika role BUKAN dapur)
                $is_password_correct = ($login_type === 'dapur') ? true : password_verify($password, $db_password);
                
                if ($is_password_correct) {
                    
                    // ===== LOGIN BERHASIL =====
                    
                    // === PERUBAHAN 3: Tambahkan 'foto' ke array session ===
                    $user_data = [
                        'id' => $db_id,
                        'nama' => $db_nama,
                        'role' => $db_role,
                        'foto' => $db_foto // Data foto sekarang disimpan
                    ];

                    // 5. Simpan data user dan role DARI DATABASE
                    $_SESSION['user'] = $user_data;
                    $_SESSION['role'] = $user_data['role']; 
                    
                    // 6. Redirect sesuai role
                    if ($_SESSION['role'] === 'admin') {
                        header("Location: dashboard.php");
                    } elseif ($_SESSION['role'] === 'dapur') {
                        header("Location: dapur.php"); 
                    } elseif ($_SESSION['role'] === 'akuntan') {
                        header("Location: visual_report.php");
                    } else {
                        $error_message = "Role tidak valid.";
                    }
                    exit;

                } else {
                    $error_message = "Username atau Password salah!";
                }
            } else {
                if ($login_type === 'dapur') {
                    $error_message = "Akun dapur tidak ditemukan!";
                } else {
                    $error_message = "Username atau Password salah!";
                }
            }
            $stmt->close();
        }
        // --- AKHIR LOGIKA LOGIN ---
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login MBG - Makanan Bergizi Sehat</title>
    <link rel="icon" href="logo_scs_jpg.png">    
    <meta property="og:image" content="https://scsbanjamegara.com/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
            box-sizing: border-box;
        }
        
        html, body {
            overflow: hidden;
            height: 100%;
            position: fixed;
            width: 100%;
        }
        
        body {
            font-family: 'Google Sans', sans-serif;
            min-height: 100vh;
            color: #2d3748;
            overflow: hidden;
            position: relative;
            -webkit-overflow-scrolling: touch;
        }
        
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(-45deg, #1e3a8a, #2563eb, #3b82f6, #60a5fa);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            transition: filter 0.5s ease;
        }
        
        .animated-bg.blur-effect {
            filter: blur(20px) brightness(0.7);
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite linear;
            z-index: -1;
            transition: filter 0.5s ease;
        }
        
        .floating-element.blur-effect {
            filter: blur(15px) opacity(0.3);
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: filter 0.5s ease;
        }
        
        .glass-card.blur-effect {
            filter: blur(15px) brightness(0.8);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        }
        
        .fade-in { animation: fadeIn 0.8s ease-out forwards; }
        .slide-up { animation: slideUp 0.8s ease-out forwards; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }
        
        .input-focus { transition: all 0.3s ease; }
        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .login-card { transition: transform 0.5s ease, box-shadow 0.5s ease, filter 0.5s ease; }
        
        .login-card.blur-effect {
            filter: blur(15px) brightness(0.8);
            transform: scale(0.95);
        }
        
        /* Existing styles for login page */
        .pulse-glow { animation: pulse-glow 2s infinite; }
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        
        .food-icon { filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1)); }
        .gradient-bg { background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%); }
        
        @media (max-width: 768px) {
            .main-container { padding: 1rem; height: 100vh; overflow: hidden; display: flex; align-items: center; }
            .login-card { flex-direction: column; max-height: 90vh; overflow-y: auto; margin: 0 auto; width: 100%; max-width: 400px; }
            .login-card > div { width: 100% !important; }
            .text-4xl { font-size: 1.75rem; }
            .text-3xl { font-size: 1.5rem; }
            .text-lg { font-size: 1rem; }
            .glass-card { padding: 1.5rem; }
            .floating-element { display: none; }
            .gradient-bg { padding: 2rem 1rem; }
            .welcome-section h1 { font-size: 1.5rem; }
            .welcome-section p { font-size: 0.9rem; }
            .food-icon-container { transform: scale(0.8); }
            .input-fields input, .input-fields select { padding: 0.75rem; font-size: 16px; }
            .btn-primary { padding: 0.875rem 1rem; font-size: 1rem; }
            
            /* Responsive modal */
            #agreementModal { padding: 10px; }
            .agreement-card { max-height: 90vh; }
            .terms-container { max-height: 200px; }
            .agreement-header { padding: 20px 25px; }
            .agreement-body { padding: 20px 25px; }
            .agreement-footer { padding: 20px 25px; }
        }
        
        @media (max-width: 480px) {
            .text-4xl { font-size: 1.5rem; }
            .glass-card { padding: 1rem; border-radius: 1rem; }
            .gradient-bg { padding: 1.5rem 1rem; }
            .welcome-section h1 { font-size: 1.25rem; }
            .welcome-section p { font-size: 0.8rem; }
            .food-icon-container { transform: scale(0.7); }
            .input-fields input, .input-fields select { padding: 0.7rem; }
            .btn-primary { padding: 0.75rem 1rem; font-size: 0.9rem; }
            .footer-text { font-size: 0.75rem; }
            
            .agreement-header { padding: 15px 20px; }
            .agreement-body { padding: 15px 20px; }
            .agreement-footer { padding: 15px 20px; }
            .terms-container { padding: 15px; max-height: 180px; }
        }
        
        .no-scroll { position: fixed; width: 100%; height: 100%; overflow: hidden; }
        .btn-touch { min-height: 44px; min-width: 44px; }
        .mobile-optimized { -webkit-transform: translate3d(0,0,0); transform: translate3d(0,0,0); }
        .floating-icon { animation: float-icon 6s ease-in-out infinite; }
        @keyframes float-icon {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
        
        /* Blur effect for background elements */
        .blur-effect {
            transition: all 0.5s ease;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 mobile-optimized">
    
    <div class="animated-bg" id="mainBackground"></div>
    
    <div class="floating-element" id="floating1" style="width: 100px; height: 100px; top: 10%; left: 10%; animation-delay: 0s;"></div>
    <div class="floating-element" id="floating2" style="width: 150px; height: 150px; top: 70%; left: 80%; animation-delay: 5s;"></div>

    <div class="w-full max-w-7xl mx-auto relative z-10 main-container">
        <div class="login-card bg-white rounded-[1rem] shadow-2xl overflow-hidden w-full flex flex-col md:flex-row border border-gray glass-card" id="loginCard">
            
            <div class="gradient-bg text-white p-8 md:p-12 md:w-1/2 hidden md:flex flex-col justify-center items-center text-center relative overflow-hidden welcome-section">
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute top-10 left-10 text-6xl"><i class="fas fa-apple-alt"></i></div>
                    <div class="absolute top-20 right-16 text-4xl"><i class="fas fa-carrot"></i></div>
                    <div class="absolute bottom-20 left-16 text-5xl"><i class="fas fa-fish"></i></div>
                    <div class="absolute bottom-10 right-10 text-3xl"><i class="fas fa-leaf"></i></div>
                </div>
                
                <div class="max-w-md relative z-10 fade-in"> 
                    <div class="mb-6 floating-icon food-icon-container"> 
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto text-white food-icon" viewBox="0 0 100 100" fill="currentColor">
                                <rect x="15" y="60" width="70" height="25" rx="5" fill="#ffffff" opacity="0.9"/>
                                <rect x="10" y="55" width="80" height="5" rx="2" fill="#f5f5f5"/>
                                <circle cx="35" cy="45" r="15" fill="#ffffff" stroke="#e5e5e5" stroke-width="1"/>
                                <ellipse cx="35" cy="45" rx="10" ry="6" fill="#fef3c7"/>
                                <ellipse cx="30" cy="48" rx="4" ry="2" fill="#ef4444"/>
                                <path d="M40,42 Q42,40 44,42 Q46,44 44,46 Q42,48 40,46 Q38,44 40,42Z" fill="#4ade80"/>
                                <circle cx="65" cy="45" r="15" fill="#ffffff" stroke="#e5e5e5" stroke-width="1"/>
                                <circle cx="65" cy="45" r="8" fill="#fdba74"/>
                                <path d="M65,37 Q66,35 67,37" fill="#166534"/>
                                <path d="M60,48 L64,44 L62,50 Z" fill="#f97316"/>
                                <path d="M68,50 L72,46 L70,52 Z" fill="#fb923c"/>
                            </svg>
                            <div class="absolute -top-1 -right-1 bg-yellow-400 rounded-full w-6 h-6 flex items-center justify-center">
                                <i class="fas fa-seedling text-xs text-green-700"></i>
                            </div>
                        </div>
                    </div>
                    
                    <h1 class="text-4xl font-extrabold mb-4">
                        Selamat Datang di SSMBM!
                    </h1>
                    
                    <p class="text-lg text-blue-100 px-4 leading-relaxed mb-6">
                        Sistem Manajemen <b>Makanan Bergizi Sehat</b>. Akses mudah untuk mengelola persediaan dan distribusi makanan sehat.
                    </p>
                    
                    <div class="flex justify-center space-x-6 nutrition-icons">
                        <div class="text-center">
                            <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-apple-alt text-yellow-300"></i>
                            </div>
                            <span class="text-sm">Vitamin</span>
                        </div>
                        <div class="text-center">
                            <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-drumstick-bite text-red-300"></i>
                            </div>
                            <span class="text-sm">Protein</span>
                        </div>
                        <div class="text-center">
                            <div class="bg-white bg-opacity-20 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-bread-slice text-amber-200"></i>
                            </div>
                            <span class="text-sm">Karbohidrat</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col justify-center p-6 sm:p-8 md:p-12 md:w-1/2 bg-white/90 backdrop-blur-sm slide-up input-fields">
                            <div class="text-center md:text-left mb-8">
                    <div class="flex justify-center md:justify-start items-center mb-2">
                        <div class="bg-blue-600 rounded-full w-8 h-8 flex items-center justify-center mr-2 shadow-sm">
                            <i class="fas fa-leaf text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-gray-800">SSMBM</span>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">
                        Selamat Datang Kembali
                    </h2>
                    <p class="text-gray-500 text-sm">Silakan masukkan detail Anda untuk mengakses sistem</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6 flex items-center animate-pulse" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <form class="space-y-5" method="POST" action="login.php">
                    <input type="hidden" name="login_type" id="login_type" value="umum">
                    
                    <!-- Tabs -->
                    <div class="flex space-x-4">
                        <button type="button" id="tab-umum" class="flex-1 py-3 px-4 rounded-xl border border-blue-200 bg-white shadow-sm text-blue-600 font-semibold transition-all duration-300 flex items-center justify-center" onclick="switchTab('umum')">
                            <i class="fas fa-user-shield mr-2"></i>Umum
                        </button>
                        <button type="button" id="tab-dapur" class="flex-1 py-3 px-4 rounded-xl border border-gray-200 bg-gray-50 shadow-sm text-gray-500 font-medium hover:text-gray-700 transition-all duration-300 flex items-center justify-center" onclick="switchTab('dapur')">
                            <i class="fas fa-utensils mr-2"></i>Dapur
                        </button>
                    </div>

                    <!-- Divider -->
                    <div class="relative flex items-center py-2">
                        <div class="flex-grow border-t border-gray-200"></div>
                        <span class="flex-shrink-0 mx-4 text-gray-400 text-sm">Atau</span>
                        <div class="flex-grow border-t border-gray-200"></div>
                    </div>

                    <div id="form-dynamic-fields" class="transition-opacity duration-500 ease-in-out opacity-100">
                        <!-- Username Input (Hidden for Dapur) -->
                        <div class="transform transition-all duration-500" id="usernameInputContainer">
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                                Username / Email <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" id="username" name="username" placeholder="Masukkan username" 
                                       class="input-focus block w-full px-4 py-3 border border-gray-300 bg-gray-50 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 transition duration-300">
                            </div>
                        </div>

                        <!-- Dapur Select (Shown only for Dapur) -->
                        <div class="transform transition-all duration-500 hidden" id="dapurSelectContainer">
                            <label for="dapur_select" class="block text-sm font-medium text-gray-700 mb-2">
                                Pilih Dapur (Outlet) <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select id="dapur_select" name="username_dapur" 
                                        class="input-focus block w-full px-4 py-3 border border-gray-300 bg-gray-50 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 transition duration-300 appearance-none">
                                    <option value="" disabled selected>-- Pilih Nama Dapur --</option>
                                    <?php foreach ($dapur_users as $dapur): ?>
                                        <option value="<?php echo htmlspecialchars($dapur); ?>"><?php echo htmlspecialchars($dapur); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>

                        <div class="transform transition-all duration-500" id="passwordContainer">
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" placeholder="Masukkan password" required 
                                       class="input-focus block w-full px-4 py-3 border border-gray-300 bg-gray-50 rounded-xl shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 transition duration-300">
                                
                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                                    <span class="cursor-pointer" onclick="togglePassword('password', this)">
                                        <i class="fas fa-eye-slash"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between mt-2" id="forgotPasswordLink">
                            <div class="flex items-center">
                                <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer">
                                <label for="remember_me" class="ml-2 block text-sm text-gray-600 cursor-pointer">
                                    Ingat saya
                                </label>
                            </div>
                            <a href="./lupaPassword.php" class="text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors">
                                Lupa Password?
                            </a>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-md text-lg font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-300 transform hover:-translate-y-0.5">
                            Sign in
                        </button>
                    </div>
                </form>

                <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                    <p class="text-gray-500 text-sm">
                        Belum punya akun? <a href="./daftar.php" class="text-blue-600 font-medium cursor-pointer hover:text-blue-800">Daftar sekarang</a>
                    </p>
                </div></div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle function
        function togglePassword(inputId, iconElement) {
            const input = document.getElementById(inputId);
            const icon = iconElement.querySelector('i');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        function switchTab(tab) {
            const currentTab = document.getElementById('login_type').value;
            if (currentTab === tab) return; // Mencegah klik berulang pada tab yang sama
            
            document.getElementById('login_type').value = tab;
            
            const tabUmum = document.getElementById('tab-umum');
            const tabDapur = document.getElementById('tab-dapur');
            
            const usernameContainer = document.getElementById('usernameInputContainer');
            const passwordContainer = document.getElementById('passwordContainer');
            const forgotPasswordLink = document.getElementById('forgotPasswordLink');
            const dapurSelectContainer = document.getElementById('dapurSelectContainer');
            
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const dapurSelect = document.getElementById('dapur_select');

            const activeClass = "flex-1 py-3 px-4 rounded-xl border border-blue-200 bg-white shadow-sm text-blue-600 font-semibold transition-all duration-300 flex items-center justify-center";
            const inactiveClass = "flex-1 py-3 px-4 rounded-xl border border-gray-200 bg-gray-50 shadow-sm text-gray-500 font-medium hover:text-gray-700 transition-all duration-300 flex items-center justify-center";

            const formContainer = document.getElementById('form-dynamic-fields');

            // 1. Fade out animasi
            formContainer.classList.remove('opacity-100');
            formContainer.classList.add('opacity-0');

            // 2. Tunggu sebentar, lalu ubah konten (saat transparan)
            setTimeout(() => {
                if (tab === 'dapur') {
                    tabDapur.className = activeClass;
                    tabUmum.className = inactiveClass;
                    
                    dapurSelectContainer.classList.remove('hidden');
                    usernameContainer.classList.add('hidden');
                    passwordContainer.classList.add('hidden');
                    forgotPasswordLink.classList.add('hidden');
                    
                    dapurSelect.required = true;
                    usernameInput.required = false;
                    passwordInput.required = false;
                } else {
                    tabUmum.className = activeClass;
                    tabDapur.className = inactiveClass;
                    
                    dapurSelectContainer.classList.add('hidden');
                    usernameContainer.classList.remove('hidden');
                    passwordContainer.classList.remove('hidden');
                    forgotPasswordLink.classList.remove('hidden');
                    
                    dapurSelect.required = false;
                    usernameInput.required = true;
                    passwordInput.required = true;
                }
                
                // 3. Fade in animasi kembali
                setTimeout(() => {
                    formContainer.classList.remove('opacity-0');
                    formContainer.classList.add('opacity-100');
                }, 50);
            }, 200); // Harus sesuai dengan durasi transition-opacity di HTML
        }

        // Mobile optimization
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent zoom on focus
            const inputs = document.querySelectorAll('input, select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.fontSize = '16px';
                });
            });
            
            // Handle orientation changes
            window.addEventListener('orientationchange', function() {
                setTimeout(function() { 
                    window.scrollTo(0, 0); 
                }, 100);
            });
        });
    </script>
</body>
</html>
