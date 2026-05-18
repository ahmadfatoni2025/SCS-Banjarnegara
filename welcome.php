<!-- <?php
// Selalu mulai session di awal halaman
session_start();
include 'koneksi.php'; // Sertakan koneksi database

// --- Ambil Data Statistik ---
$total_bahan = 0;
$total_driver = 0;
$pesanan_hari_ini = 0;

// 1. Ambil Total Stok Bahan
$stmt_stok = $koneksi->prepare("SELECT SUM(stok) as total_stok FROM gudang");
if($stmt_stok){
    $stmt_stok->execute();
    $stmt_stok->store_result();
    $stmt_stok->bind_result($total_stok_db);
    $stmt_stok->fetch();
    $total_bahan = $total_stok_db ?? 0;
    $stmt_stok->close();
}

// 2. Ambil Total Driver
$stmt_driver = $koneksi->prepare("SELECT COUNT(id) as total_driver FROM user WHERE role = 'driver'");
if($stmt_driver){
    $stmt_driver->execute();
    $stmt_driver->store_result();
    $stmt_driver->bind_result($total_driver_db);
    $stmt_driver->fetch();
    $total_driver = $total_driver_db ?? 0;
    $stmt_driver->close();
}

// 3. Ambil Pesanan Hari Ini
$stmt_pesanan = $koneksi->prepare("SELECT COUNT(id_pesanan) FROM pesanan WHERE DATE(tgl_pesan) = CURDATE()");
if($stmt_pesanan){
    $stmt_pesanan->execute();
    $stmt_pesanan->store_result();
    $stmt_pesanan->bind_result($pesanan_hari_ini_db);
    $stmt_pesanan->fetch();
    $pesanan_hari_ini = $pesanan_hari_ini_db ?? 0;
    $stmt_pesanan->close();
}
?> -->
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MBG - Makanan Bergizi Sehat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            overflow: hidden;
            height: 100%;
            position: fixed;
            width: 100%;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            color: #2d3748;
            overflow: hidden;
            position: relative;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Animated Background */
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
        }
        
        @keyframes gradientShift {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        /* Floating Elements */
        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite linear;
            z-index: -1;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
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
        
        .feature-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .timer-card {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
            transition: all 0.5s ease;
        }
        
        .timer-card.warning {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            animation: pulse-warning 1.5s infinite;
        }
        
        .timer-card.critical {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            animation: pulse-critical 1s infinite, shake 0.5s infinite;
        }
        
        .fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }
        
        .slide-up {
            animation: slideUp 0.8s ease-out forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(30px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(37, 99, 235, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
            }
        }
        
        @keyframes pulse-warning {
            0% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(245, 158, 11, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }
        
        @keyframes pulse-critical {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6);
            }
            70% {
                box-shadow: 0 0 0 20px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Simplified Stats */
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        
        /* Timer Styling */
        .timer-display {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            transition: all 0.3s ease;
        }
        
        .timer-display.warning {
            color: #fef3c7;
            font-size: 1.6rem;
        }
        
        .timer-display.critical {
            color: #fef2f2;
            font-size: 1.8rem;
            font-weight: 900;
        }
        
        .timer-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: white;
            border-radius: 3px;
            transition: width 1s ease, background 0.5s ease;
        }
        
        .urgency-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-5px);
            }
            60% {
                transform: translateY(-3px);
            }
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 1024px) {
            .stat-value {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
                height: 100vh;
                overflow: hidden;
            }
            
            .grid-cols-1 {
                gap: 1.5rem;
            }
            
            .text-8xl {
                font-size: 4rem;
            }
            
            .text-2xl {
                font-size: 1.5rem;
            }
            
            .text-xl {
                font-size: 1.1rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .timer-display {
                font-size: 1.25rem;
            }
            
            .timer-display.warning {
                font-size: 1.35rem;
            }
            
            .timer-display.critical {
                font-size: 1.5rem;
            }
            
            .glass-card {
                padding: 1.5rem;
            }
            
            .feature-card {
                padding: 1rem;
            }
            
            .w-16 {
                width: 3rem;
            }
            
            .h-16 {
                height: 3rem;
            }
        }
        
        @media (max-width: 480px) {
            .text-8xl {
                font-size: 3rem;
            }
            
            .text-2xl {
                font-size: 1.25rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .timer-display {
                font-size: 1.1rem;
            }
            
            .glass-card {
                padding: 1rem;
            }
            
            .grid.grid-cols-3 {
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .w-12 {
                width: 2.5rem;
            }
            
            .h-12 {
                height: 2.5rem;
            }
            
            .btn-primary {
                padding: 0.875rem 1rem;
                font-size: 1rem;
            }
        }
        
        /* Prevent scroll on mobile */
        .no-scroll {
            position: fixed;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        /* Touch-friendly buttons */
        .btn-touch {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* Optimize for mobile performance */
        .mobile-optimized {
            -webkit-transform: translate3d(0,0,0);
            transform: translate3d(0,0,0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            -webkit-perspective: 1000;
            perspective: 1000;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 no-scroll mobile-optimized">
    <!-- Animated Background -->
    <div class="animated-bg"></div>
    
    <!-- Floating Elements -->
    <div class="floating-element" style="width: 100px; height: 100px; top: 10%; left: 10%; animation-delay: 0s;"></div>
    <div class="floating-element" style="width: 150px; height: 150px; top: 70%; left: 80%; animation-delay: 5s;"></div>
    <div class="floating-element" style="width: 80px; height: 80px; top: 40%; left: 90%; animation-delay: 10s;"></div>
    <div class="floating-element" style="width: 120px; height: 120px; top: 80%; left: 20%; animation-delay: 15s;"></div>
    
    <!-- Main Container -->
    <div class="w-full max-w-7xl mx-auto relative z-10 main-container">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center h-full">
            
            <!-- Left Side - Welcome Section -->
            <div class="space-y-6 lg:space-y-8 fade-in">

                <!-- Welcome Content -->
                <div class="space-y-4 lg:space-y-6">
                    <h1 class="text-lg lg:text-3xl font-bold text-white leading-tight">
                        Selamat Datang,<br>
                        <span class="bg-gradient-to-r text-8xl lg:text-8xl mt-4 lg:mt-6 font-bold from-white to-blue-100 bg-clip-text text-transparent">
                            SSMBS
                        </span>
                    </h1>
                    
                    <p class="text-lg lg:text-xl text-white/90 leading-relaxed max-w-lg">
                        Sistem Suplai Makanan Bergizi Sehat Muhammadiyah adalah platform terintegrasi yang mengatur distribusi dan pemantauan makanan sehat di lingkungan lembaga Muhammadiyah  
                    </p>

                    <!-- Features Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 lg:gap-4 mt-6 lg:mt-8">
                        <div class="feature-card bg-white/80 glass-card rounded-2xl p-4 lg:p-6 text-center">
                            <div class="w-12 h-12 lg:w-16 lg:h-16 mx-auto mb-3 lg:mb-4 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-user-shield text-white text-xl lg:text-2xl"></i>
                            </div>
                            <h3 class="font-semibold text-gray-800 mb-2 text-sm lg:text-base">Administrator</h3>
                            <p class="text-xs lg:text-sm text-gray-600">Kelola stok dan pantau laporan</p>
                        </div>
                        
                        <div class="feature-card bg-white/80 glass-card rounded-2xl p-4 lg:p-6 text-center">
                            <div class="w-12 h-12 lg:w-16 lg:h-16 mx-auto mb-3 lg:mb-4 bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-utensils text-white text-xl lg:text-2xl"></i>
                            </div>
                            <h3 class="font-semibold text-gray-800 mb-2 text-sm lg:text-base">Dapur</h3>
                            <p class="text-xs lg:text-sm text-gray-600">Kelola pesanan bahan baku</p>
                        </div>
                        
                        <div class="feature-card bg-white/80 glass-card rounded-2xl p-4 lg:p-6 text-center">
                            <div class="w-12 h-12 lg:w-16 lg:h-16 mx-auto mb-3 lg:mb-4 bg-gradient-to-br from-blue-700 to-blue-900 rounded-2xl flex items-center justify-center">
                                <i class="fas fa-truck text-white text-xl lg:text-2xl"></i>
                            </div>
                            <h3 class="font-semibold text-gray-800 mb-2 text-sm lg:text-base">Driver</h3>
                            <p class="text-xs lg:text-sm text-gray-600">Lacak pengantaran real-time</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Stats & Login Section -->
            <div class="slide-up" style="animation-delay: 0.2s;">
                <div class="glass-card bg-blue-300 rounded-3xl p-6 lg:p-8 xl:p-10">
                    <h2 class="text-2xl lg:text-3xl font-bold text-gray-800 text-center mb-2">
                        System Overview
                    </h2>
                    <p class="text-gray-600 text-center mb-6 lg:mb-8 text-sm lg:text-base">Real-time statistics and access</p>

                    <!-- Simplified Stats Grid -->
                    <div class="grid grid-cols-3 gap-3 lg:gap-4 mb-6 lg:mb-8">
                        <div class="stat-card rounded-2xl p-3 lg:p-4 text-center">
                            <div class="w-10 h-10 lg:w-12 lg:h-12 mx-auto mb-2 lg:mb-3 bg-blue-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-boxes text-blue-600 text-base lg:text-lg"></i>
                            </div>
                            <div class="stat-value text-blue-700"><?php echo number_format($total_bahan); ?></div>
                            <div class="stat-label text-gray-600 text-xs lg:text-sm">Bahan Tersedia</div>
                        </div>
                        
                        <div class="stat-card rounded-2xl p-3 lg:p-4 text-center">
                            <div class="w-10 h-10 lg:w-12 lg:h-12 mx-auto mb-2 lg:mb-3 bg-green-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-users text-green-600 text-base lg:text-lg"></i>
                            </div>
                            <div class="stat-value text-green-700"><?php echo $total_driver; ?></div>
                            <div class="stat-label text-gray-600 text-xs lg:text-sm">Driver</div>
                        </div>
                        
                        <div class="stat-card rounded-2xl p-3 lg:p-4 text-center">
                            <div class="w-10 h-10 lg:w-12 lg:h-12 mx-auto mb-2 lg:mb-3 bg-orange-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-clipboard-list text-orange-600 text-base lg:text-lg"></i>
                            </div>
                            <div class="stat-value text-orange-700"><?php echo $pesanan_hari_ini; ?></div>
                            <div class="stat-label text-gray-600 text-xs lg:text-sm">Pesanan Hari Ini</div>
                        </div>
                    </div>

                    <!-- Timer Section -->
                    <div class="relative">
                        <div id="timer-card" class="timer-card rounded-2xl p-4 lg:p-6 mb-4 lg:mb-6">
                            <div class="flex items-center justify-between mb-2 lg:mb-3">
                                <div class="flex items-center gap-2 lg:gap-3">
                                    <div class="w-8 h-8 lg:w-10 lg:h-10 bg-white/20 rounded-xl flex items-center justify-center">
                                        <i class="fas fa-clock text-white text-sm lg:text-base"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-white text-sm lg:text-base">Waktu Pemesanan</div>
                                        <div id="timer-status" class="timer-label text-white/90 text-xs lg:text-sm">Tersisa</div>
                                    </div>
                                </div>
                                <div id="urgency-badge" class="urgency-badge hidden">
                                    <i class="fas fa-exclamation text-xs"></i>
                                </div>
                            </div>
                            <div id="countdown-timer" class="timer-display text-center py-1 lg:py-2">
                                Memuat...
                            </div>
                            <div class="progress-bar">
                                <div id="progress-fill" class="progress-fill" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Login Button -->
                    <button 
                        id="login-button"
                        class="btn-primary btn-touch w-full text-white py-3 lg:py-4 px-6 rounded-2xl font-semibold text-base lg:text-lg mb-4 lg:mb-6 flex items-center justify-center gap-2 lg:gap-3"
                        onclick="window.location.href='login.php'"
                    >
                        <i class="fas fa-sign-in-alt"></i>
                        <span id="login-text">Masuk ke Sistem</span>
                    </button>

                    <!-- Footer -->
                    <div class="text-center pt-3 lg:pt-4 border-t border-gray-200/50">
                        <p class="text-xs lg:text-sm text-gray-600">
                            Dikelola oleh <span class="font-semibold">Muhammadiyah</span><br>
                            <span class="text-xs text-gray-500">Berbagai dapur di Banjarnegara</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prevent all scrolling
        function preventDefault(e) {
            e.preventDefault();
        }

        function preventDefaultForScrollKeys(e) {
            const keys = {37: 1, 38: 1, 39: 1, 40: 1};
            if (keys[e.keyCode]) {
                preventDefault(e);
                return false;
            }
        }

        function disableScroll() {
            window.addEventListener('DOMMouseScroll', preventDefault, false);
            window.addEventListener('wheel', preventDefault, { passive: false });
            window.addEventListener('touchmove', preventDefault, { passive: false });
            window.addEventListener('keydown', preventDefaultForScrollKeys, false);
        }

        function enableScroll() {
            window.removeEventListener('DOMMouseScroll', preventDefault, false);
            window.removeEventListener('wheel', preventDefault, { passive: false });
            window.removeEventListener('touchmove', preventDefault, { passive: false });
            window.removeEventListener('keydown', preventDefaultForScrollKeys, false);
        }

        // Fungsi Countdown dengan visual urgency
        function startCountdown() {
            const countdownElement = document.getElementById('countdown-timer');
            const timerCard = document.getElementById('timer-card');
            const progressFill = document.getElementById('progress-fill');
            const timerStatus = document.getElementById('timer-status');
            const urgencyBadge = document.getElementById('urgency-badge');
            const loginButton = document.getElementById('login-button');
            const loginText = document.getElementById('login-text');

            if (!countdownElement) return;

            const targetTime = new Date();
            targetTime.setHours(17, 0, 0, 0); // Target: 5:00 PM (17:00)
            
            const totalTime = 17 * 60 * 60 * 1000; // Total waktu dari 00:00 sampai 17:00

            const timer = setInterval(function() {
                const now = new Date();
                let distance = targetTime - now;

                if (distance < 0) {
                    countdownElement.textContent = "00:00:00";
                    countdownElement.className = "timer-display critical text-center py-1 lg:py-2";
                    timerCard.className = "timer-card critical rounded-2xl p-4 lg:p-6 mb-4 lg:mb-6";
                    timerStatus.textContent = "Pemesanan telah tutup!";
                    progressFill.style.width = "0%";
                    progressFill.style.background = "#ef4444";
                    urgencyBadge.classList.remove('hidden');
                    clearInterval(timer);
                    return;
                }
                
                const hours = Math.floor(distance / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Format waktu
                countdownElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Hitung progress persentase
                const currentTime = new Date();
                const startOfDay = new Date();
                startOfDay.setHours(0, 0, 0, 0);
                const elapsed = currentTime - startOfDay;
                const progress = Math.max(0, Math.min(100, 100 - (distance / totalTime * 100)));
                
                progressFill.style.width = `${progress}%`;
                
                // Update visual berdasarkan waktu tersisa
                if (hours < 1) { // Kurang dari 1 jam - CRITICAL
                    timerCard.className = "timer-card critical rounded-2xl p-4 lg:p-6 mb-4 lg:mb-6";
                    countdownElement.className = "timer-display critical text-center py-1 lg:py-2";
                    timerStatus.textContent = "Segera pesan! Waktu hampir habis!";
                    progressFill.style.background = "#ef4444";
                    urgencyBadge.classList.remove('hidden');
                    loginButton.classList.add('pulse');
                    
                    // Tambah efek pada login button
                    loginButton.style.background = 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)';
                    loginText.textContent = 'SEGERA PESAN SEKARANG!';
                    
                } else if (hours < 3) { // Kurang dari 3 jam - WARNING
                    timerCard.className = "timer-card warning rounded-2xl p-4 lg:p-6 mb-4 lg:mb-6";
                    countdownElement.className = "timer-display warning text-center py-1 lg:py-2";
                    timerStatus.textContent = "Waktu pemesanan hampir habis";
                    progressFill.style.background = "#f59e0b";
                    urgencyBadge.classList.remove('hidden');
                    loginButton.classList.add('pulse');
                    
                } else { // Normal
                    timerCard.className = "timer-card rounded-2xl p-4 lg:p-6 mb-4 lg:mb-6";
                    countdownElement.className = "timer-display text-center py-1 lg:py-2";
                    timerStatus.textContent = "Waktu pemesanan tersisa";
                    progressFill.style.background = "white";
                    urgencyBadge.classList.add('hidden');
                    loginButton.classList.remove('pulse');
                    
                    // Reset login button ke normal
                    loginButton.style.background = '';
                    loginText.textContent = 'Masuk ke Sistem';
                }
                
            }, 1000);
        }
        
        // Animasi saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            // Disable scroll
            disableScroll();
            
            // Start countdown
            startCountdown();
            
            // Create additional floating elements dynamically
            for (let i = 0; i < 3; i++) {
                createFloatingElement();
            }
            
            // Handle orientation change
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    window.scrollTo(0, 0);
                }, 100);
            });
        });
        
        // Create floating elements dynamically
        function createFloatingElement() {
            const element = document.createElement('div');
            element.classList.add('floating-element');
            
            const size = Math.random() * 80 + 30;
            const left = Math.random() * 100;
            const animationDelay = Math.random() * 20;
            
            element.style.width = `${size}px`;
            element.style.height = `${size}px`;
            element.style.left = `${left}%`;
            element.style.top = '100%';
            element.style.animationDelay = `${animationDelay}s`;
            
            document.body.appendChild(element);
            
            // Remove element after animation completes
            setTimeout(() => {
                if (element.parentNode) {
                    element.parentNode.removeChild(element);
                }
            }, 20000);
            
            // Create new element after a delay
            setTimeout(createFloatingElement, 5000 + Math.random() * 10000);
        }
        
        // Prevent context menu on mobile
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
    </script>
</body>
</html>
