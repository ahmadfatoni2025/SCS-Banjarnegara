<?php
// Selalu mulai session di awal halaman
session_start();

// --- Logika PHP ---
// Jika pengguna sebenarnya sudah login (misalnya, dia tidak sengaja ke halaman ini),
// jangan tampilkan halaman "lupa password". Langsung arahkan ke dashboard-nya.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'driver') {
        header('Location: driver.php');
        exit();
    }
    // Asumsikan Anda punya halaman admin.php
    if ($_SESSION['role'] === 'admin') { 
        header('Location: admin.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="logo_scs_jpg.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemberitahuan - Lupa Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Gaya kustom untuk shadow yang lebih halus (opsional) */
        .modern-shadow {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-xl modern-shadow p-8 sm:p-10 text-center border border-gray-100">

        <div class="mb-6">
            <svg class="mx-auto h-16 w-16 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>

        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-3">
            Tidak Ada Opsi Pemulihan
        </h1>
        
        <p class="text-gray-600 mb-8 leading-relaxed">
            Untuk alasan keamanan, kami tidak dapat memberikan informasi lebih lanjut. Silakan <b>hubungi administrator sistem</b> Anda untuk mendapatkan bantuan pemulihan password.
        </p>

        <!-- 
            Struktur <button> berisi <a> ini tidak standar,
            tapi saya biarkan agar sesuai dengan kode asli Anda.
            Link di dalamnya diubah ke login.php 
        -->
        <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-indigo-500 focus:ring-opacity-50 flex items-center justify-center w-full mb-4">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.717 21 3 14.283 3 6V5z"/>
            </svg>
            <!-- DIUBAH: Mengarah ke login.php -->
            <a href="login.php" class="text-white">Hubungi Administrator</a>
        </button>

        <a href="javascript:history.back()" class="mt-4 text-sm font-medium text-gray-500 hover:text-indigo-600 transition duration-150 inline-block">
            ← Kembali ke Halaman Sebelumnya
        </a>
    </div>
</body>
</html>
