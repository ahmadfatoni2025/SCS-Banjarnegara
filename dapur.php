<?php

// === PAKSA TAMPILKAN SEMUA ERROR ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "koneksi.php";

// --- KEAMANAN ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dapur' || !isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit();
}
$id_dapur_login = (int)$_SESSION['user']['id'];
$nama_dapur_login = htmlspecialchars($_SESSION['user']['nama'] ?? 'Dapur');

// --- AMBIL PENGATURAN --- (Default 7 hari jika tidak ada di DB)
$min_po_days = 7;
$sql_pref = "SELECT nilai_pengaturan FROM pengaturan_sistem WHERE nama_pengaturan = 'min_po_days'";
$res_pref = $koneksi->query($sql_pref);
if ($res_pref && $res_pref->num_rows > 0) {
    $row_pref = $res_pref->fetch_assoc();
    $min_po_days = (int)$row_pref['nilai_pengaturan'];
}

// --- AMBIL DATA BARANG ---
$products = [];
$sql = "SELECT id_barang, nama, kategori, harga, stok, satuan, keterangan, tipe_pengadaan FROM gudang";
$stmt = $koneksi->prepare($sql);

if ($stmt) {
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $nama, $kategori, $harga, $stok, $satuan, $keterangan, $tipe_pengadaan);
    while ($stmt->fetch()) {
        $products[] = [
            'id_barang' => $id,
            'nama' => $nama,
            'kategori' => $kategori,
            'harga' => $harga,
            'stok' => $stok,
            'satuan' => $satuan,
            'keterangan' => $keterangan,
            'tipe_pengadaan' => $tipe_pengadaan
        ];
    }
    $stmt->free_result();
    $stmt->close();
} else {
    die("Gagal mengambil data barang.");
}

$categories = array_unique(array_column($products, 'kategori'));
sort($categories);

// Sorting: Abjad Nama
usort($products, function ($a, $b) {
    return strcasecmp($a['nama'], $b['nama']);
});
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pemesanan - <?php echo $nama_dapur_login; ?> | SCS Banjarnegara</title>

    <!-- Favicon & Meta Tags -->
    <link rel="icon" href="logo_scs_jpg.png">

    <!-- Styles & Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.3s ease-in-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'bounce': 'bounce 0.5s',
                        'pulse': 'pulse 2s infinite',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Google Sans', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #1e293b;
            min-height: 100vh;
        }

        /* Glass Effect */
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        /* Product Card - Diperbaiki */
        .product-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.15);
            border-color: #93c5fd;
        }

        .card-selected {
            border: 2px solid #2563eb;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.2);
        }

        /* Product Card Out of Stock (Red Theme) */
        .product-card-out-of-stock {
            background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%);
            border: 1px solid #fee2e2;
            position: relative;
            overflow: hidden;
        }

        .product-card-out-of-stock::before {
            content: 'STOK HABIS';
            position: absolute;
            top: 20px;
            right: -35px;
            background: #dc2626;
            color: white;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 40px;
            transform: rotate(45deg);
            letter-spacing: 1px;
            z-index: 10;
        }

        .product-card-out-of-stock:hover {
            transform: none;
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.1);
            border-color: #fca5a5;
        }

        /* Category Badge */
        .category-badge {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .category-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px -3px rgba(37, 99, 235, 0.2);
        }

        .category-badge.active {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 5px 15px -3px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }


        /* Category Grid Container */
        .category-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        /* category-item custom CSS has been removed in favor of Tailwind utility classes */

        /* Grid Layout Controls */
        .grid-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            padding: 0.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .grid-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .grid-btn:hover {
            border-color: #93c5fd;
            color: #2563eb;
            background: #eff6ff;
        }

        .grid-btn.active {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            border-color: #2563eb;
            color: white;
        }

        .grid-btn i {
            font-size: 1rem;
        }

        /* Grid Columns */
        .grid-1 {
            grid-template-columns: 1fr !important;
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr) !important;
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr) !important;
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr) !important;
        }

        /* Sticky Header */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 16px;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            padding: 0;
            border-radius: 20px;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal-content {
            transform: translateY(0);
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        @keyframes bounce {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }

        /* Cart Item Animation */
        .cart-item-added {
            animation: cartItemAdded 0.5s ease-out;
        }

        @keyframes cartItemAdded {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }

            70% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Scrollbar Custom */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%);
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        }

        /* Print Styles */
        @media print {
            body>* {
                visibility: hidden;
            }

            #struk-print,
            #struk-print * {
                visibility: visible;
            }

            #struk-print {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                background: white;
                padding: 20px;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Quantity Input Hide Arrows */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
        }

        /* Clear Button Animation */
        .clear-btn {
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.3s ease;
        }

        .clear-btn.show {
            opacity: 1;
            transform: scale(1);
        }

        /* Badge Pulse */
        .badge-pulse {
            animation: pulse 2s infinite;
        }

        /* Update Button Style */
        .update-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .update-btn:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .add-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .add-btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }

        /* Disabled Button Style - Red Theme */
        .disabled-btn-red {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            cursor: not-allowed;
            position: relative;
            overflow: hidden;
        }

        .disabled-btn-red::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% {
                left: -100%;
            }

            100% {
                left: 100%;
            }
        }

        /* Toggle Button Style */
        .toggle-btn {
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            transform: translateY(-1px);
        }

        /* Stats Cards Transition */
        .stats-transition {
            transition: all 0.3s ease;
        }

        /* New Styles for Product Card Layout */
        .product-card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-card-top {
            flex: 1;
        }

        .product-card-bottom {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }

        /* Quantity Control New Styles */
        .quantity-control-container {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            border: 1px solid #e2e8f0;
        }

        .quantity-control-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: white;
            border: 1px solid #cbd5e1;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: bold;
        }

        .quantity-btn:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #2563eb;
            transform: scale(1.05);
        }

        .quantity-btn:active {
            transform: scale(0.95);
        }

        .quantity-input-container {
            flex: 1;
            position: relative;
        }

        .quantity-input {
            width: 100%;
            height: 40px;
            text-align: center;
            font-size: 1.125rem;
            font-weight: bold;
            color: #1e293b;
            background: white;
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            outline: none;
            transition: all 0.2s ease;
        }

        .quantity-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .quantity-label {
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
        }

        /* Add to Cart Button New Styles */
        .add-to-cart-btn {
            width: 100%;
            height: 46px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .add-to-cart-btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.3);
        }

        .add-to-cart-btn:active {
            transform: translateY(0);
        }

        .add-to-cart-btn.added {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .add-to-cart-btn.updated {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .add-to-cart-btn.disabled {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            cursor: not-allowed;
            opacity: 0.8;
        }

        .add-to-cart-btn.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Out of Stock Styles */
        .out-of-stock-container {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            border: 1px solid #fecaca;
        }

        .out-of-stock-label {
            color: #dc2626;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Hidden Class */
        .hidden-section {
            opacity: 0;
            height: 0;
            overflow: hidden;
            margin: 0;
            padding: 0;
            transition: all 0.3s ease;
        }

        .visible-section {
            opacity: 1;
            height: auto;
            transition: all 0.3s ease;
        }

        /* Responsive Grid */
        @media (max-width: 640px) {
            .category-grid-container {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }

            .grid-2,
            .grid-3,
            .grid-4 {
                grid-template-columns: 1fr !important;
            }

            .product-grid {
                grid-template-columns: 1fr !important;
            }

            .quantity-control-container {
                padding: 0.5rem;
            }

            .quantity-btn {
                width: 36px;
                height: 36px;
            }

            .quantity-input {
                height: 36px;
                font-size: 1rem;
            }

            .add-to-cart-btn {
                height: 42px;
                font-size: 0.875rem;
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            .grid-2 {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .grid-3,
            .grid-4 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .grid-2 {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .grid-3 {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .grid-4 {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        @media (min-width: 1025px) {
            .grid-2 {
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .grid-3 {
                grid-template-columns: repeat(3, 1fr) !important;
            }

            .grid-4 {
                grid-template-columns: repeat(4, 1fr) !important;
            }
        }

        /* Product Grid */
        .product-grid {
            transition: grid-template-columns 0.3s ease;
            display: grid;
            gap: 1.5rem;
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1);
        }

        /* Price Styles */
        .product-price {
            margin-top: 0.5rem;
        }

        .price-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e293b;
        }

        .price-unit {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Stock Bar */
        .stock-bar-container {
            margin: 0.5rem 0;
        }

        .stock-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .stock-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .stock-bar-fill.green {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .stock-bar-fill.yellow {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .stock-bar-fill.red {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        /* Product Description */
        .product-description {
            margin: 0.5rem 0;
            line-height: 1.4;
        }
    </style>
</head>

<body class="antialiased">

    <!-- Dark Hero Header -->
    <div class="max-w-7xl mx-auto py-6">
        <div class="rounded-[16px] p-6 md:p-10 relative overflow-hidden">

            <!-- Top Nav Row -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10 text-black">
                <!-- Brand -->
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
                        <i class="fas fa-utensils text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-black tracking-tight">Dapur <?php echo $nama_dapur_login; ?></h1>
                        <p class="text-sm text-black-400">Formulir Pemesanan</p>
                    </div>
                </div>

                <!-- User Actions -->
                <div class="flex items-center space-x-4">
                    <a href="logout.php" class="flex items-center space-x-2 px-8 py-2.5 rounded-[16px] text-white hover:white/75 transition-colors border border-transparent hover:border-black/10 bg-red-700 hover:bg-red-900">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="hidden sm:inline font-medium">Keluar</span>
                    </a>
                </div>
            </div>

            <!-- Hero Body (Title) -->
            <div class="mt-12 relative z-10 text-center md:text-left">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Katalog Produk</h2>
                        <p class="text-gray-500">Pilih bahan baku yang dibutuhkan</p>
                    </div>


                    <!-- Grid Controls -->
                    <div class="flex items-center space-x-4">
                        <div class="grid-controls">
                            <span class="text-sm text-gray-500 mr-2">Grid:</span>
                            <button class="grid-btn" data-columns="2" title="2 Kolom">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button class="grid-btn active" data-columns="3" title="3 Kolom">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="grid-btn" data-columns="4" title="4 Kolom">
                                <i class="fas fa-border-all"></i>
                            </button>
                            <button id="toggle-categories-btn"
                                class="toggle-btn flex items-center space-x-2 px-3 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-all shadow hover:shadow-md">
                                <i class="fas fa-eye-slash"></i>
                                <span class="text-sm">Sembunyikan Kategori</span>
                            </button>
                        </div>

                    </div>
                </div>

                <div class="border border-t-1 m-2"></div>


                <!-- Categories Grid (Tampil semua) -->
                <style>
                    .category-item.active {
                        background-color: #1f2937 !important;
                        /* bg-gray-800 */
                        color: #ffffff !important;
                        border-color: #1f2937 !important;
                    }

                    .category-item.active i {
                        color: #9ca3af !important;
                        /* text-gray-400 */
                    }
                </style>

                <!-- Categories Flex UI (Mimics Time Picker Style) -->
                <div id="categories-container" class="mb-6 mt-2 stats-transition">
                    <div class="flex flex-wrap gap-2.5">
                        <!-- Tombol "Semua" -->
                        <div class="category-item active border border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 rounded-[16px] px-5 py-2 cursor-pointer transition-colors shadow-sm flex items-center justify-center gap-2" onclick="filterCategory('')">
                            <i class="fas fa-th-large text-[11px] opacity-70"></i>
                            <span class="text-[13px] font-semibold whitespace-nowrap">Semua</span>
                        </div>

                        <!-- Looping Kategori -->
                        <?php foreach ($categories as $cat): ?>
                            <div class="category-item border border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 rounded-[16px] px-5 py-2 cursor-pointer transition-colors shadow-sm flex items-center justify-center gap-2" onclick="filterCategory('<?php echo htmlspecialchars($cat); ?>')">
                                <i class="fas fa-tag text-[11px] opacity-70"></i>
                                <span class="text-[13px] font-semibold whitespace-nowrap"><?php echo ucwords($cat); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Category Section - Grid Layout -->



            <!-- Giant Search Pill -->
            <div class="z-10 bg-white rounded-[16px] p-2 flex items-center shadow-2xl border border-gray-200 mx-auto md:mx-0 mx-auto m-8">
                <div class="flex-1 flex items-center pl-6">
                    <i class="fas fa-search text-gray-400 text-lg"></i>
                    <input type="text"
                        id="search-bar"
                        placeholder="Ketik nama barang, kategori, atau keterangan... (CTRL + K)"
                        class="w-full bg-transparent border-none py-3 px-4 text-gray-800 font-medium placeholder-gray-400 focus:outline-none focus:ring-0 text-base">
                </div>

                <!-- Clear Button -->
                <button id="clear-search" class="clear-btn text-gray-300 hover:text-gray-500 transition-colors px-4">
                    <i class="fas fa-times-circle text-lg"></i>
                </button>

                <div class="hidden sm:block h-8 w-[1px] bg-gray-200 mx-2"></div>

                <button onclick="document.getElementById('search-bar').focus()" class="hidden sm:block bg-blue-600 hover:bg-blue-700 text-white px-20 py-3.5 rounded-[16px] font-bold text-sm transition-colors shadow-md shadow-blue-500/30 whitespace-nowrap">
                    Cari
                </button>
            </div>

            <!-- Products Grid -->
            <div id="product-list" class="product-grid grid-3">
                <?php foreach ($products as $index => $product):
                    $isPO = ($product['tipe_pengadaan'] === 'PO');
                    $isOutOfStock = (!$isPO && $product['stok'] <= 0);
                    $isLimited = (!$isPO && $product['stok'] > 0 && $product['stok'] <= 5);

                    $stockPercentage = $product['stok'] > 20 ? 100 : ($product['stok'] / 20) * 100;
                    $stockColor = $isOutOfStock ? 'red' : ($product['stok'] > 10 ? 'green' : ($product['stok'] > 5 ? 'yellow' : 'red'));

                    $cardClass = $isOutOfStock ? 'product-card product-card-out-of-stock' : 'product-card';
                ?>
                    <div class="<?php echo $cardClass; ?>"
                        style="animation-delay: <?php echo $index * 0.05; ?>s"
                        data-id="<?php echo $product['id_barang']; ?>"
                        data-nama="<?php echo htmlspecialchars(strtolower($product['nama'])); ?>"
                        data-kategori="<?php echo htmlspecialchars(strtolower($product['kategori'])); ?>"
                        data-keterangan="<?php echo htmlspecialchars(strtolower($product['keterangan'] ?? '')); ?>"
                        data-stock="<?php echo $isPO ? '999999' : $product['stok']; ?>"
                        data-harga="<?php echo $product['harga']; ?>"
                        data-satuan="<?php echo $product['satuan']; ?>"
                        data-out-of-stock="<?php echo $isOutOfStock ? 'true' : 'false'; ?>">

                        <?php $bgClass = $isOutOfStock ? ' border-red-100' : 'bg-white'; ?>
                        <div class="product-card-content p-5 flex flex-col h-full <?php echo $bgClass; ?> rounded-[24px]">

                            <!-- Top Status Text -->
                            <div class="flex items-center gap-1.5 mb-4 text-gray-500 text-[13px] font-semibold tracking-wide">
                                <i class="fas fa-leaf text-gray-800"></i>
                                <span class="text-gray-900"><?php echo htmlspecialchars($product['kategori']); ?></span>
                                <span class="mx-1">•</span>
                                <?php if ($isOutOfStock): ?>
                                    <span class="text-red-500">Stok Habis</span>
                                <?php else: ?>
                                    <span class="text-gray-600">100% Ready</span>
                                <?php endif; ?>
                            </div>

                            <!-- Avatar & Title Row -->
                            <?php
                            $firstChar = strtoupper(substr($product['nama'], 0, 1));
                            $charValue = ord($firstChar);
                            if ($charValue >= 65 && $charValue <= 69) {
                                $gradient = 'bg-purple-200 text-purple-700';
                            } elseif ($charValue >= 70 && $charValue <= 74) {
                                $gradient = 'bg-pink-200 text-pink-700';
                            } elseif ($charValue >= 75 && $charValue <= 79) {
                                $gradient = 'bg-green-200 text-green-700';
                            } elseif ($charValue >= 80 && $charValue <= 84) {
                                $gradient = 'bg-orange-200 text-orange-700';
                            } else {
                                $gradient = 'bg-blue-200 text-blue-700';
                            }
                            ?>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 flex-shrink-0 rounded-full flex items-center justify-center font-bold text-sm <?php echo $gradient; ?>">
                                    <?php echo $firstChar; ?>
                                </div>
                                <div class="overflow-hidden">
                                    <h3 class="font-bold text-gray-900 text-base leading-snug truncate" title="<?php echo htmlspecialchars($product['nama']); ?>">
                                        <?php echo htmlspecialchars($product['nama']); ?>
                                    </h3>
                                    <span class="text-[13px] text-gray-500 font-medium block mt-0.5">Dapur Banjarnegara</span>
                                </div>
                            </div>

                            <!-- Prominent Big Price -->
                            <div class="mb-4">
                                <div class="text-2xl font-extrabold text-gray-900 tracking-tight">
                                    Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?>
                                </div>
                            </div>

                            <!-- Information Pills Row -->
                            <div class="flex flex-wrap gap-2 mb-4">
                                <?php if ($isPO): ?>
                                    <span class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-full border border-gray-200">
                                        🛒 PO
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1.5 bg-green-100  text-green-700 text-xs font-bold rounded-full shadow-sm">
                                        <?php echo $product['stok']; ?> Tersedia
                                    </span>
                                <?php endif; ?>

                                <span class="px-3 py-1.5 bg-green-50 text-green-700 text-xs font-bold rounded-full border border-green-100 flex items-center gap-1">
                                    <i class="fas fa-balance-scale text-[10px]"></i>
                                    Satuan: <?php echo htmlspecialchars($product['satuan']); ?>
                                </span>
                            </div>

                            <!-- Description Text -->
                            <?php if (!empty($product['keterangan'])): ?>
                                <div class="mb-auto">
                                    <p class="text-[14px] text-gray-600 leading-relaxed line-clamp-2">
                                        <?php echo htmlspecialchars($product['keterangan']); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="mb-auto">
                                    <p class="text-[14px] text-gray-400 italic leading-relaxed">
                                        Tidak ada keterangan tambahan.
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Bottom Action Buttons -->
                            <div class="flex flex-col gap-2.5 mt-6 pt-2">
                                <!-- Top: Quantity Controls -->
                                <div class="flex items-center justify-between bg-gray-100 rounded-[14px] p-1.5 border border-gray-200 w-full">
                                    <button onclick="decreaseQuantity(<?php echo $product['id_barang']; ?>)" class="w-10 h-10 rounded-[10px] flex items-center justify-center bg-white text-gray-600 hover:bg-gray-50 border border-gray-200 shadow-sm transition-colors">
                                        <i class="fas fa-minus text-[11px]"></i>
                                    </button>
                                    <input type="number"
                                        id="quantity-<?php echo $product['id_barang']; ?>"
                                        data-product-id="<?php echo $product['id_barang']; ?>"
                                        value="0"
                                        min="0"
                                        class="w-16 text-center bg-transparent border-none text-gray-900 font-bold text-base focus:outline-none"
                                        onchange="validateQuantity(<?php echo $product['id_barang']; ?>)">
                                    <button onclick="increaseQuantity(<?php echo $product['id_barang']; ?>)" class="w-10 h-10 rounded-[10px] flex items-center justify-center bg-white text-gray-600 hover:bg-gray-50 border border-gray-200 shadow-sm transition-colors">
                                        <i class="fas fa-plus text-[11px]"></i>
                                    </button>
                                </div>

                                <!-- Bottom: Action Button -->
                                <button onclick="addToCart(<?php echo $product['id_barang']; ?>)"
                                    id="cart-btn-<?php echo $product['id_barang']; ?>"
                                    class="cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 <?php echo $isOutOfStock ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-green-600 text-white hover:bg-green-700 shadow-md'; ?>"
                                    <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                                    <?php if ($isOutOfStock): ?>
                                        <i class="fas fa-times"></i> Habis
                                    <?php else: ?>
                                        <i class="fas fa-cart-plus"></i> Pesan
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <div id="empty-state" class="hidden text-center py-16">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-search text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-600 mb-2">Tidak ada barang ditemukan</h3>
                <p class="text-gray-500 mb-6">Coba gunakan kata kunci pencarian lain atau pilih kategori yang berbeda.</p>
                <button onclick="clearSearch()" class="px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors">
                    <i class="fas fa-undo mr-2"></i>Reset Pencarian
                </button>
            </div>

            <!-- Dummy Hidden Elements for JS Compatibility -->
            <span id="mobile-total-harga" class="hidden"></span>
            <span id="mobile-items-badge" class="hidden"></span>

            <!-- Universal Cart Summary Sticky Bottom -->
            <div class="fixed bottom-0 left-0 right-0 z-50 pointer-events-none p-2 pb-6">
                <div class="max-w-6xl mx-auto pointer-events-auto">
                    <div class="bg-white/95 backdrop-blur-md border border-gray-200 shadow-[0_-10px_40px_-10px_rgba(0,0,0,0.15)] rounded-2xl p-4 md:p-5 flex items-center justify-between transition-all hover:shadow-[0_-10px_40px_-10px_rgba(0,0,0,0.2)]">
                        <div class="flex flex-col">
                            <p class="text-xs md:text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Pesanan</p>
                            <p id="total-harga-display" class="text-xl md:text-2xl font-extrabold text-green-600 tracking-tight">Rp 0</p>
                        </div>
                        <button onclick="showCartModalMobile()" id="lanjut-bayar-btn" disabled
                            class="relative bg-green-600 hover:bg-black disabled:bg-gray-300 text-white px-2 md:p-3 rounded-xl font-bold shadow-xl shadow-gray-900/20 disabled:shadow-none transition-all flex items-center space-x-3 active:scale-[0.98]">
                            <i class="fas fa-shopping-cart md:text-lg"></i>
                            <span class="text-sm md:text-lg">Checkout</span>
                            <span id="total-items-badge" class="absolute -top-3 -right-3 bg-red-500 text-white text-xs md:text-sm font-bold w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center border-2 border-white shadow-sm">0</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Diri Modal -->
        <div id="data-diri-modal" class="modal-overlay no-print">
            <div class="modal-content">
                <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 text-white">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold">Konfirmasi Pesanan</h2>
                            <p class="text-primary-100 text-sm">Lengkapi data diri Anda</p>
                        </div>
                        <button id="close-data-diri" class="text-white hover:text-primary-200 transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <div class="p-6 overflow-y-auto">
                    <form id="data-diri-form">
                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Nama Lengkap *</label>
                                <input type="text"
                                    id="nama-pemesan"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all placeholder-gray-400"
                                    placeholder="Masukkan nama lengkap">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email (Opsional)</label>
                                <input type="email"
                                    id="email-pemesan"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all placeholder-gray-400"
                                    placeholder="nama@email.com">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">No. WhatsApp *</label>
                                <div class="relative">
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">+62</div>
                                    <input type="tel"
                                        id="wa-pemesan"
                                        required
                                        placeholder="81234567890"
                                        class="w-full pl-14 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all placeholder-gray-400">
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Contoh: 81234567890</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Catatan (Opsional)</label>
                                <textarea id="catatan-pesanan"
                                    rows="2"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all placeholder-gray-400"
                                    placeholder="Tambahkan catatan khusus jika ada..."></textarea>
                            </div>

                            <!-- JADWAL PENGIRIMAN PER-ITEM -->
                            <div class="border-t border-gray-200 pt-5 mt-2">
                                <div class="flex justify-between items-center mb-3">
                                    <label class="block text-sm font-semibold text-gray-700">
                                        <i class="fas fa-truck text-primary-600 mr-1"></i> Jadwal Pengiriman Per Barang *
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input type="date" id="set-all-date" class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-primary-500 outline-none">
                                        <button type="button" onclick="setAllDates()" class="text-xs bg-primary-100 text-primary-700 px-3 py-1.5 rounded-lg font-bold hover:bg-primary-200 transition">
                                            <i class="fas fa-calendar-check mr-1"></i>Samakan Semua
                                        </button>
                                    </div>
                                </div>
                                <p id="po-info-text" class="text-xs text-blue-600 mb-3 font-medium"><i class="fas fa-info-circle mr-1"></i>Pilih tanggal pengiriman untuk setiap barang. Bisa beda-beda tanggalnya.</p>

                                <div class="overflow-x-auto rounded-xl border border-gray-200">
                                    <table class="w-full text-sm" id="cart-review-table">
                                        <thead>
                                            <tr class="bg-gray-50 text-xs text-gray-500 uppercase">
                                                <th class="px-3 py-2.5 text-left">No</th>
                                                <th class="px-3 py-2.5 text-left">Nama Barang</th>
                                                <th class="px-3 py-2.5 text-center">Jumlah</th>
                                                <th class="px-3 py-2.5 text-center">Satuan</th>
                                                <th class="px-3 py-2.5 text-center">Tgl Pengiriman</th>
                                                <th class="px-3 py-2.5 text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cart-review-body">
                                            <!-- Diisi dinamis oleh JS -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Ringkasan Per Tanggal -->
                                <div id="date-grouping-preview" class="mt-4 space-y-2 hidden">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider"><i class="fas fa-layer-group mr-1"></i>Ringkasan Jadwal Kirim:</p>
                                    <div id="date-groups-container"></div>
                                </div>
                            </div>
                        </div>

                        <div class="flex space-x-3 pt-8">
                            <button type="button"
                                id="batal-data-diri-btn"
                                class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-all active:scale-[0.98]">
                                Batal
                            </button>
                            <button type="submit"
                                class="flex-1 px-6 py-3 bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-semibold rounded-xl transition-all shadow-lg hover:shadow-xl active:scale-[0.98]">
                                Lanjutkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Struk Modal (Removed as requested: langsung berupa pdf saja) -->

        <!-- Message Modal -->
        <div id="message-modal" class="modal-overlay no-print">
            <div class="modal-content max-w-sm w-full">
                <div class="p-8 text-center">
                    <div id="msg-icon-container" class="mb-6">
                        <i id="message-icon" class="fas fa-check-circle text-green-500 text-6xl"></i>
                    </div>
                    <h3 id="message-title" class="text-2xl font-bold text-gray-900 mb-3">Berhasil!</h3>
                    <p id="message-body" class="text-gray-600 mb-6">Pesan anda di sini.</p>

                    <div id="modal-actions" class="space-y-3">
                        <button id="message-close-btn"
                            class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors active:scale-[0.98]">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Global Variables
            let cart = {};
            let customerData = {};
            let currentGridColumns = 3; // Default grid columns
            let isCategoriesHidden = false; // State untuk kategori
            let originalStats = {
                available: <?php echo count($products); ?>,
                limited: 0,
                outOfStock: 0
            };

            // DOM Elements
            const totalHargaDisplay = document.getElementById('total-harga-display');
            const totalItemsBadge = document.getElementById('total-items-badge');
            const cartTotalItems = document.getElementById('cart-total-items');
            const searchBar = document.getElementById('search-bar');
            const clearSearchBtn = document.getElementById('clear-search');
            const dataDiriModal = document.getElementById('data-diri-modal');
            const dataDiriForm = document.getElementById('data-diri-form');
            const messageModal = document.getElementById('message-modal');
            const productList = document.getElementById('product-list');
            const productCount = document.getElementById('product-count');
            const emptyState = document.getElementById('empty-state');
            const mobileTotalHarga = document.getElementById('mobile-total-harga');
            const mobileItemsBadge = document.getElementById('mobile-items-badge');
            const availableCount = document.getElementById('available-count');
            const outOfStockCount = document.getElementById('out-of-stock-count');
            const limitedCount = document.getElementById('limited-count');
            const gridButtons = document.querySelectorAll('.grid-btn');
            const toggleCategoriesBtn = document.getElementById('toggle-categories-btn');
            const categoriesContainer = document.getElementById('categories-container');
            const quickStats = document.getElementById('quick-stats');

            // SweetAlert2 Toast Configuration
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            // Initialize
            document.addEventListener('DOMContentLoaded', function() {
                updateCartSummary();

                // Search functionality with clear button
                searchBar.addEventListener('input', function() {
                    filterProducts();
                    updateClearButton();
                });

                // Clear search button
                clearSearchBtn.addEventListener('click', clearSearch);

                // Global Keyboard Shortcuts
                document.addEventListener('keydown', function(e) {
                    // CTRL + K (atau CMD + K) untuk fokus ke pencarian
                    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                        e.preventDefault(); // Cegah fungsi bawaan browser
                        searchBar.focus();
                    }

                    // ESC untuk menutup modal konfirmasi pesanan atau menghapus pencarian
                    if (e.key === 'Escape') {
                        if (dataDiriModal.classList.contains('show')) {
                            dataDiriModal.classList.remove('show');
                        } else if (document.activeElement === searchBar) {
                            clearSearch();
                            searchBar.blur();
                        }
                    }
                });

                // Modal close buttons
                document.getElementById('close-data-diri').addEventListener('click', function() {
                    dataDiriModal.classList.remove('show');
                });

                document.getElementById('batal-data-diri-btn').addEventListener('click', function() {
                    dataDiriModal.classList.remove('show');
                });

                document.getElementById('message-close-btn').addEventListener('click', function() {
                    messageModal.classList.remove('show');
                });

                // Form submission
                dataDiriForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleDataDiriSubmit();
                });

                // Checkout button
                document.getElementById('lanjut-bayar-btn').addEventListener('click', function() {
                    if (Object.keys(cart).length === 0) {
                        Toast.fire({
                            icon: 'warning',
                            title: 'Keranjang Kosong'
                        });
                        return;
                    }
                    showDataDiriModal();
                });

                // Grid controls
                gridButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const columns = parseInt(this.getAttribute('data-columns'));
                        changeGridLayout(columns);
                    });
                });

                // Initialize clear button visibility
                updateClearButton();

                // Set default grid layout
                changeGridLayout(3);

                // Save grid preference to localStorage
                const savedGrid = localStorage.getItem('productGridColumns');
                if (savedGrid) {
                    changeGridLayout(parseInt(savedGrid));
                }

                // Initialize categories toggle
                initCategoriesToggle();

                // Load saved categories state
                loadCategoriesState();
            });

            // Categories Toggle Functionality
            function initCategoriesToggle() {
                toggleCategoriesBtn.addEventListener('click', toggleCategories);

                // Add event listeners to category items to auto-show categories when clicked
                document.querySelectorAll('.category-item').forEach(item => {
                    item.addEventListener('click', function() {
                        if (isCategoriesHidden) {
                            showCategories();
                        }
                    });
                });
            }

            function loadCategoriesState() {
                const savedState = localStorage.getItem('categoriesHidden');
                if (savedState === 'true') {
                    hideCategories();
                    updateToggleButton(true);
                }
            }

            function toggleCategories() {
                if (isCategoriesHidden) {
                    showCategories();
                } else {
                    hideCategories();
                }

                // Save state to localStorage
                localStorage.setItem('categoriesHidden', isCategoriesHidden.toString());
            }

            function hideCategories() {
                isCategoriesHidden = true;

                // Add hidden class with animation
                categoriesContainer.classList.add('hidden-section');
                quickStats.classList.add('hidden-section');

                // Update toggle button
                updateToggleButton(true);

                // Smooth scroll to products after hiding
                setTimeout(() => {
                    productList.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 300);
            }

            function showCategories() {
                isCategoriesHidden = false;

                // Remove hidden class with animation
                categoriesContainer.classList.remove('hidden-section');
                quickStats.classList.remove('hidden-section');

                // Update toggle button
                updateToggleButton(false);

                // Smooth scroll to categories
                setTimeout(() => {
                    categoriesContainer.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 300);
            }

            function updateToggleButton(isHidden) {
                const icon = toggleCategoriesBtn.querySelector('i');
                const text = toggleCategoriesBtn.querySelector('span');

                if (isHidden) {
                    icon.className = 'fas fa-eye';
                    text.textContent = 'Tampilkan Kategori';
                    toggleCategoriesBtn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
                    toggleCategoriesBtn.classList.add('bg-gray-600', 'hover:bg-gray-700');
                } else {
                    icon.className = 'fas fa-eye-slash';
                    text.textContent = 'Sembunyikan Kategori';
                    toggleCategoriesBtn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                    toggleCategoriesBtn.classList.add('bg-primary-600', 'hover:bg-primary-700');
                }
            }

            // Change grid layout
            function changeGridLayout(columns) {
                currentGridColumns = columns;

                // Update grid buttons
                gridButtons.forEach(button => {
                    const btnColumns = parseInt(button.getAttribute('data-columns'));
                    if (btnColumns === columns) {
                        button.classList.add('active');
                    } else {
                        button.classList.remove('active');
                    }
                });

                // Update product grid class
                productList.className = 'product-grid';
                productList.classList.add(`grid-${columns}`);

                // Save to localStorage
                localStorage.setItem('productGridColumns', columns);

                // Adjust card content based on grid size
                adjustCardContent(columns);
            }

            // Adjust card content based on grid size
            function adjustCardContent(columns) {
                const cards = document.querySelectorAll('.product-card');

                cards.forEach(card => {
                    const title = card.querySelector('h3');
                    const description = card.querySelector('.product-description p');
                    const price = card.querySelector('.price-amount');

                    // Adjust based on grid size
                    switch (columns) {
                        case 2:
                            // Medium size - show more content
                            title.classList.remove('text-lg');
                            title.classList.add('text-xl');
                            if (description) {
                                description.classList.remove('line-clamp-2');
                                description.classList.add('line-clamp-3');
                            }
                            break;
                        case 3:
                            // Default size
                            title.classList.remove('text-xl');
                            title.classList.add('text-lg');
                            if (description) {
                                description.classList.remove('line-clamp-3');
                                description.classList.add('line-clamp-2');
                            }
                            break;
                        case 4:
                            // Small size - compact view
                            title.classList.remove('text-lg');
                            title.classList.add('text-base');
                            if (description) {
                                description.classList.remove('line-clamp-2');
                                description.classList.add('line-clamp-1');
                            }
                            if (price) {
                                price.classList.remove('text-2xl');
                                price.classList.add('text-xl');
                            }
                            break;
                    }
                });
            }

            // Update stats counts
            function updateStatsCounts() {
                const cards = document.querySelectorAll('.product-card');
                let available = 0;

                cards.forEach(card => {
                    if (card.style.display !== 'none') {
                        available++;
                    }
                });

                if (availableCount) availableCount.textContent = available;
            }

            // Update clear button visibility
            function updateClearButton() {
                if (searchBar.value.trim() !== '') {
                    clearSearchBtn.classList.add('show');
                } else {
                    clearSearchBtn.classList.remove('show');
                }
            }

            // Clear search function
            function clearSearch() {
                searchBar.value = '';
                filterProducts();
                updateClearButton();
                searchBar.focus();
            }

            // Filter Functions
            function filterCategory(catName) {
                const query = catName.toLowerCase().trim();
                const cards = document.querySelectorAll('.product-card');
                const categoryItems = document.querySelectorAll('.category-item');

                // Update active category
                categoryItems.forEach(item => {
                    item.classList.remove('active');

                    if (query === '') {
                        if (item.textContent.includes('Semua')) {
                            item.classList.add('active');
                        }
                    } else {
                        if (item.textContent.toLowerCase().includes(query.toLowerCase())) {
                            item.classList.add('active');
                        }
                    }
                });

                // Filter products
                let visibleCount = 0;
                cards.forEach(card => {
                    const kategori = card.getAttribute('data-kategori') || '';

                    if (query === '' || kategori.includes(query)) {
                        card.style.display = 'block';
                        visibleCount++;

                        // Add animation
                        card.style.animation = 'fadeIn 0.3s ease-in-out';
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Update search bar
                searchBar.value = catName;
                updateClearButton();

                // Show/hide empty state
                updateEmptyState(visibleCount);

                // Update stats counts
                updateStatsCounts();

                // Scroll to top of products
                productList.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }

            function filterProducts() {
                const query = searchBar.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.product-card');

                let visibleCount = 0;
                cards.forEach(card => {
                    const nama = card.getAttribute('data-nama') || '';
                    const kategori = card.getAttribute('data-kategori') || '';
                    const keterangan = card.getAttribute('data-keterangan') || '';

                    if (nama.includes(query) || kategori.includes(query) || keterangan.includes(query)) {
                        card.style.display = 'block';
                        visibleCount++;
                        card.style.animation = 'fadeIn 0.3s ease-in-out';
                    } else {
                        card.style.display = 'none';
                    }
                });

                updateEmptyState(visibleCount);
                updateStatsCounts();
            }

            function updateEmptyState(visibleCount) {
                if (visibleCount === 0) {
                    emptyState.classList.remove('hidden');
                    productCount.textContent = '0';
                } else {
                    emptyState.classList.add('hidden');
                    productCount.textContent = visibleCount;
                }
            }

            // Quantity Functions - Manual Update (TIDAK realtime)
            function increaseQuantity(productId) {
                const input = document.getElementById(`quantity-${productId}`);
                let currentValue = parseInt(input.value) || 0;

                input.value = currentValue + 1;
                updateButtonState(productId);
            }

            function decreaseQuantity(productId) {
                const input = document.getElementById(`quantity-${productId}`);
                let currentValue = parseInt(input.value) || 0;

                if (currentValue > 0) {
                    input.value = currentValue - 1;
                    updateButtonState(productId);
                }
            }

            function validateQuantity(productId) {
                const input = document.getElementById(`quantity-${productId}`);
                let value = parseInt(input.value) || 0;

                if (isNaN(value) || value < 0) {
                    input.value = 0;
                }

                updateButtonState(productId);
            }

            function updateButtonState(productId) {
                const input = document.getElementById(`quantity-${productId}`);
                const quantity = parseInt(input.value) || 0;
                const button = document.getElementById(`cart-btn-${productId}`);

                if (!button) return;

                if (quantity === 0) {
                    // Reset button jika quantity 0
                    button.innerHTML = '<i class="fas fa-cart-plus"></i> Pesan';
                    button.className = 'cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 bg-green-600 text-white hover:bg-gray-800 shadow-md';
                } else if (cart[productId] && cart[productId].jumlah === quantity) {
                    // Jika sudah di cart dan quantity sama
                    button.innerHTML = '<i class="fas fa-check"></i> Selesai';
                    button.className = 'cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 bg-green-600 text-white shadow-md animate-pulse';
                } else if (cart[productId] && cart[productId].jumlah !== quantity) {
                    // Jika sudah di cart tapi quantity berbeda
                    button.innerHTML = '<i class="fas fa-sync-alt"></i> Simpan';
                    button.className = 'cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 bg-amber-500 text-white shadow-md';
                } else {
                    // Jika belum di cart
                    button.innerHTML = '<i class="fas fa-cart-plus"></i> Pesan';
                    button.className = 'cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 bg-green-600 text-white hover:bg-gray-800 shadow-md';
                }
            }

            // Cart Functions - Manual Update (bukan realtime)
            function addToCart(productId) {
                const card = document.querySelector(`[data-id="${productId}"]`);
                if (card && card.getAttribute('data-out-of-stock') === 'true') {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Stok Habis'
                    });
                    return;
                }

                const input = document.getElementById(`quantity-${productId}`);
                const quantity = parseInt(input.value) || 0;

                // Get product data from attributes
                const nama = card.querySelector('h3').textContent.trim();
                const harga = parseInt(card.getAttribute('data-harga'));
                const satuan = card.getAttribute('data-satuan');
                const stok = parseInt(card.getAttribute('data-stock'));

                if (quantity <= 0) {
                    // Remove from cart if exists
                    if (cart[productId]) {
                        delete cart[productId];
                        const button = document.getElementById(`cart-btn-${productId}`);
                        button.innerHTML = '<i class="fas fa-cart-plus"></i> Pesan';
                        button.className = 'cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 bg-green-600 text-white hover:bg-gray-800 shadow-md';
                        Toast.fire({
                            icon: 'info',
                            title: 'Item dihapus'
                        });
                        card.classList.remove('card-selected');
                    } else {
                        // Set quantity to 1 and add to cart automatically for better UX
                        input.value = 1;
                        quantity = 1;

                        // Add to cart
                        cart[productId] = {
                            id_barang: productId,
                            nama: nama,
                            harga: harga,
                            satuan: satuan,
                            jumlah: quantity,
                            stok: 999999
                        };

                        const button = document.getElementById(`cart-btn-${productId}`);
                        button.innerHTML = '<i class="fas fa-check"></i> Selesai';
                        button.className = 'cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 bg-green-600 text-white shadow-md animate-pulse';
                        button.classList.add('cart-item-added');
                        setTimeout(() => {
                            button.classList.remove('cart-item-added');
                        }, 500);
                        card.classList.add('card-selected');

                        animateFlyToCart(productId);
                    }
                } else {
                    // Check if this is an update or new addition
                    const isUpdate = cart[productId];

                    // Add/Update to cart
                    cart[productId] = {
                        id_barang: productId,
                        nama: nama,
                        harga: harga,
                        satuan: satuan,
                        jumlah: quantity,
                        stok: 999999
                    };

                    // Update button state
                    const button = document.getElementById(`cart-btn-${productId}`);
                    button.innerHTML = '<i class="fas fa-check"></i> Selesai';
                    button.className = 'cart-action-btn w-full h-[48px] rounded-[14px] font-bold text-[14px] transition-all flex items-center justify-center gap-2 bg-green-600 text-white shadow-md animate-pulse';

                    // Animate button
                    button.classList.add('cart-item-added');
                    setTimeout(() => {
                        button.classList.remove('cart-item-added');
                    }, 500);

                    // Add card selected class
                    card.classList.add('card-selected');

                    animateFlyToCart(productId);
                }

                updateCartSummary();

                // Reset button state after 1.5 seconds
                setTimeout(() => {
                    updateButtonState(productId);
                }, 1500);
            }

            function animateFlyToCart(productId) {
                const button = document.getElementById(`cart-btn-${productId}`);
                const targetBtn = document.getElementById('lanjut-bayar-btn');
                if (!button || !targetBtn) return;

                // Create flying element
                const flyingDot = document.createElement('div');
                flyingDot.className = 'fixed bg-green-500 rounded-full shadow-lg z-[9999] pointer-events-none flex items-center justify-center';
                flyingDot.style.width = '55px';
                flyingDot.style.height = '55px';
                flyingDot.innerHTML = '<i class="fas fa-shopping-cart text-white text-[18px]"></i>';

                // Get coordinates
                const sourceRect = button.getBoundingClientRect();
                const targetRect = targetBtn.getBoundingClientRect();

                // Initial position (center of button)
                const startX = sourceRect.left + sourceRect.width / 2 - 27.5;
                const startY = sourceRect.top + sourceRect.height / 2 - 27.5;

                flyingDot.style.left = startX + 'px';
                flyingDot.style.top = startY + 'px';
                flyingDot.style.transition = 'transform 1.2s cubic-bezier(0.2, 1, 0.3, 1), opacity 1.2s ease';

                document.body.appendChild(flyingDot);

                // Force reflow
                flyingDot.offsetHeight;

                // Move and scale down
                const dx = targetRect.left + targetRect.width / 2 - 22 - startX;
                const dy = targetRect.top + targetRect.height / 2 - 22 - startY;

                flyingDot.style.transform = `translate(${dx}px, ${dy}px) scale(0.6)`;
                flyingDot.style.opacity = '0.4';

                // Animate target button on arrival
                setTimeout(() => {
                    if (flyingDot.parentNode) flyingDot.parentNode.removeChild(flyingDot);
                    targetBtn.style.transform = 'scale(1.05)';
                    targetBtn.style.transition = 'transform 0.2s';
                    setTimeout(() => {
                        targetBtn.style.transform = '';
                    }, 200);
                }, 1200);
            }

            function updateCartSummary() {
                let total = 0;
                let totalItems = 0;
                let itemCount = 0;

                for (const id in cart) {
                    total += cart[id].harga * cart[id].jumlah;
                    totalItems += cart[id].jumlah;
                    itemCount++;
                }

                // Update displays
                if (totalHargaDisplay) totalHargaDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
                if (totalItemsBadge) totalItemsBadge.textContent = totalItems;
                if (cartTotalItems) cartTotalItems.textContent = totalItems;
                if (mobileTotalHarga) mobileTotalHarga.textContent = 'Rp ' + total.toLocaleString('id-ID');
                if (mobileItemsBadge) mobileItemsBadge.textContent = totalItems;

                // Update quantity inputs to match cart (jangan reset ke 0)
                for (const id in cart) {
                    const input = document.getElementById(`quantity-${id}`);
                    if (input && parseInt(input.value) !== cart[id].jumlah) {
                        input.value = cart[id].jumlah;
                        updateButtonState(id);
                    }
                }

                // Enable/disable checkout button
                const lanjutBtn = document.getElementById('lanjut-bayar-btn');
                if (totalItems > 0) {
                    lanjutBtn.disabled = false;
                    totalItemsBadge.style.animation = 'bounce 0.5s';
                    setTimeout(() => {
                        totalItemsBadge.style.animation = '';
                    }, 500);
                } else {
                    lanjutBtn.disabled = true;
                }
            }

            // Show mobile cart modal
            function showCartModalMobile() {
                if (Object.keys(cart).length === 0) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Keranjang Kosong'
                    });
                    return;
                }
                showDataDiriModal();
            }

            // Modal Functions
            function showDataDiriModal() {
                dataDiriForm.reset();
                customerData = {};

                const tbody = document.getElementById('cart-review-body');
                tbody.innerHTML = '';

                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const minDate = tomorrow.toISOString().split('T')[0];
                document.getElementById('set-all-date').min = minDate;

                let no = 1;
                for (const id in cart) {
                    const item = cart[id];
                    addReviewRow(id, item.jumlah, true, no++);
                }

                dataDiriModal.classList.add('show');
                setTimeout(() => {
                    document.getElementById('nama-pemesan').focus();
                }, 300);
            }

            function addReviewRow(id, jumlah, isFirst, no = '') {
                const item = cart[id];
                const tbody = document.getElementById('cart-review-body');
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const minDate = tomorrow.toISOString().split('T')[0];

                const row = document.createElement('tr');
                row.className = `border-t border-gray-100 hover:bg-gray-50 review-row-${id}`;
                row.dataset.itemId = id;

                row.innerHTML = `
                <td class="px-3 py-2.5 text-center text-gray-400">${isFirst ? no : ''}</td>
                <td class="px-3 py-2.5">
                    <div class="font-semibold text-gray-800">${item.nama}</div>
                    ${!isFirst ? '<span class="text-[10px] bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded font-bold uppercase">Split</span>' : ''}
                </td>
                <td class="px-3 py-2.5 text-center">
                    <input type="number" 
                           class="item-qty-input w-20 text-center border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-primary-500 outline-none font-bold"
                           value="${jumlah}" 
                           min="1"
                           onchange="updateDateGrouping()">
                </td>
                <td class="px-3 py-2.5 text-center text-xs text-gray-500 uppercase">${item.satuan || '-'}</td>
                <td class="px-3 py-2.5 text-center">
                    <input type="date" 
                           class="item-date-picker text-xs border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-primary-500 outline-none w-full"
                           min="${minDate}"
                           onchange="updateDateGrouping()"
                           required>
                </td>
                <td class="px-3 py-2.5 text-center">
                    ${isFirst ? `
                        <button type="button" onclick="splitItem('${id}')" class="text-primary-600 hover:text-primary-800 p-2" title="Split Pengiriman">
                            <i class="fas fa-plus-circle text-lg"></i>
                        </button>
                    ` : `
                        <button type="button" onclick="this.closest('tr').remove(); updateDateGrouping();" class="text-red-500 hover:text-red-700 p-2" title="Hapus Split">
                            <i class="fas fa-minus-circle text-lg"></i>
                        </button>
                    `}
                </td>
            `;

                // Append after existing rows of same ID
                const existingRows = document.querySelectorAll(`.review-row-${id}`);
                if (existingRows.length > 0) {
                    existingRows[existingRows.length - 1].after(row);
                } else {
                    tbody.appendChild(row);
                }
            }

            function splitItem(id) {
                // Find current total in inputs for this ID to suggest remaining
                let currentAllocated = 0;
                document.querySelectorAll(`.review-row-${id} .item-qty-input`).forEach(input => {
                    currentAllocated += parseInt(input.value) || 0;
                });

                const totalInCart = cart[id].jumlah;
                const remaining = Math.max(0, totalInCart - currentAllocated);

                addReviewRow(id, remaining > 0 ? remaining : 0, false);
                updateDateGrouping();
            }

            // Set all item dates to same value
            function setAllDates() {
                const globalDate = document.getElementById('set-all-date').value;
                if (!globalDate) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Pilih tanggal dulu!'
                    });
                    return;
                }
                document.querySelectorAll('.item-date-picker').forEach(input => {
                    input.value = globalDate;
                });
                updateDateGrouping();
                Toast.fire({
                    icon: 'success',
                    title: 'Semua tanggal disamakan!'
                });
            }

            // Auto-grouping preview by date
            function updateDateGrouping() {
                const groups = {};
                document.querySelectorAll('#cart-review-body tr').forEach(row => {
                    const id = row.dataset.itemId;
                    const qty = parseInt(row.querySelector('.item-qty-input').value) || 0;
                    const date = row.querySelector('.item-date-picker').value;
                    if (!date || qty <= 0) return;

                    if (!groups[date]) groups[date] = [];
                    groups[date].push(`${cart[id].nama} (${qty})`);
                });

                const container = document.getElementById('date-groups-container');
                const wrapper = document.getElementById('date-grouping-preview');

                if (Object.keys(groups).length === 0) {
                    wrapper.classList.add('hidden');
                    return;
                }

                wrapper.classList.remove('hidden');
                container.innerHTML = '';

                const sortedDates = Object.keys(groups).sort();
                sortedDates.forEach(date => {
                    const items = groups[date];
                    const formattedDate = new Date(date + 'T00:00:00').toLocaleDateString('id-ID', {
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });
                    const div = document.createElement('div');
                    div.className = 'bg-primary-50 border border-primary-100 rounded-lg p-3';
                    div.innerHTML = `
                    <p class="text-xs font-bold text-primary-700"><i class="fas fa-calendar-day mr-1"></i>${formattedDate}</p>
                    <p class="text-xs text-gray-600 mt-1">${items.join(', ')}</p>
                `;
                    container.appendChild(div);
                });
            }

            function handleDataDiriSubmit() {
                const nama = document.getElementById('nama-pemesan').value.trim();
                const wa = document.getElementById('wa-pemesan').value.trim();
                const email = document.getElementById('email-pemesan').value.trim();
                const catatan = document.getElementById('catatan-pesanan').value.trim();

                if (!nama || !wa) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Data Belum Lengkap',
                        text: 'Nama dan WhatsApp wajib diisi.',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }

                // Validate WhatsApp number
                if (!/^\d+$/.test(wa)) {
                    Toast.fire({
                        icon: 'error',
                        title: 'Format WhatsApp Salah (Hanya angka)'
                    });
                    return;
                }

                // Collect per-item delivery dates & split quantities
                const finalCartItems = [];
                const idTotals = {};

                let allValid = true;
                document.querySelectorAll('#cart-review-body tr').forEach(row => {
                    const id = row.dataset.itemId;
                    const qty = parseInt(row.querySelector('.item-qty-input').value) || 0;
                    const date = row.querySelector('.item-date-picker').value;

                    if (!date || qty <= 0) {
                        allValid = false;
                        return;
                    }

                    if (!idTotals[id]) idTotals[id] = 0;
                    idTotals[id] += qty;

                    finalCartItems.push({
                        ...cart[id],
                        jumlah: qty,
                        tgl_pengiriman: date
                    });
                });

                if (!allValid) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Data Belum Lengkap',
                        text: 'Pastikan semua jumlah dan tanggal pengiriman sudah diisi.',
                        confirmButtonColor: '#2563eb'
                    });
                    return;
                }

                // Validate total quantities match cart
                for (const id in cart) {
                    if (idTotals[id] !== cart[id].jumlah) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Jumlah Tidak Sesuai',
                            text: `Total jumlah untuk "${cart[id].nama}" (${idTotals[id]}) tidak sama dengan jumlah di keranjang (${cart[id].jumlah}).`,
                            confirmButtonColor: '#2563eb'
                        });
                        return;
                    }
                }

                // Use finalCartItems instead of cart for submission
                const earliestDate = finalCartItems.reduce((min, item) => !min || item.tgl_pengiriman < min ? item.tgl_pengiriman : min, null);

                customerData = {
                    nama: nama,
                    email: email,
                    wa: '+62' + wa,
                    tgl_digunakan: earliestDate,
                    catatan: catatan
                };

                saveOrder(finalCartItems);
            }

            function populateStruk() {
                // Function no longer strictly necessary but kept for potential future use or to avoid breakage if called
                console.log("Struk population skipped - directing to PDF receipt");
            }

            function saveOrder(finalCartArray) {
                const submitBtn = document.querySelector('#data-diri-form button[type="submit"]');
                const originalText = submitBtn.innerHTML;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';

                // Prepare data
                const orderData = {
                    cart: finalCartArray,
                    customerData: customerData,
                    id_dapur: <?php echo $id_dapur_login; ?>,
                    nama_dapur: "<?php echo $nama_dapur_login; ?>",
                    total_items: finalCartArray.reduce((sum, item) => sum + item.jumlah, 0),
                    total_harga: finalCartArray.reduce((sum, item) => sum + (item.harga * item.jumlah), 0)
                };

                // Send to server
                fetch('laporanPenjualan.php?action=simpan', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(orderData)
                    })
                    .then(response => {
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON Parse Error:', e);
                                console.error('Raw Response:', text);
                                throw new Error('Server returned invalid JSON: ' + text.substring(0, 50));
                            }
                        });
                    })
                    .then(data => {
                        dataDiriModal.classList.remove('show');
                        if (data.success) {
                            // Reset Cart FIRST so if they back-button it's empty
                            cart = {};
                            localStorage.removeItem('dapur_cart');

                            // Success Notification then Redirect
                            Swal.fire({
                                title: 'Pesanan Berhasil!',
                                text: 'Mengalihkan ke Invoice...',
                                icon: 'success',
                                timer: 1500,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                backdrop: `rgba(15, 23, 42, 0.5)`
                            }).then(() => {
                                window.location.href = 'invoice_view.php?id=' + data.id_pesanan;
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Menyimpan',
                                text: data.message || 'Terjadi kesalahan saat menyimpan pesanan.',
                                confirmButtonColor: '#ef4444'
                            });
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('Checkout error:', error);
                        const errorMsg = error.message.includes('Server returned invalid JSON') ? error.message : 'Gagal menyambung ke server. Periksa koneksi internet Anda.';

                        Swal.fire({
                            icon: 'error',
                            title: 'Kesalahan Server',
                            text: errorMsg,
                            confirmButtonColor: '#ef4444'
                        });

                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
            }

            function showMessage(title, message, isSuccess = true, idPesanan = null) {
                const messageIcon = document.getElementById('message-icon');
                const messageTitle = document.getElementById('message-title');
                const messageBody = document.getElementById('message-body');
                const modalActions = document.getElementById('modal-actions');

                messageTitle.textContent = title;
                messageBody.textContent = message;

                // Clear previous action buttons besides the main close button
                modalActions.innerHTML = '';

                if (isSuccess) {
                    messageIcon.className = "fas fa-check-circle text-green-500 text-6xl";

                    if (idPesanan) {
                        const downloadBtn = document.createElement('button');
                        downloadBtn.className = "w-full px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl transition-colors flex items-center justify-center gap-2 active:scale-[0.98]";
                        downloadBtn.innerHTML = '<i class="fas fa-file-pdf"></i> Unduh Ulang Struk (PDF)';
                        downloadBtn.onclick = function() {
                            downloadReceipt(idPesanan);
                        };
                        modalActions.appendChild(downloadBtn);
                    }
                } else {
                    messageIcon.className = "fas fa-times-circle text-red-500 text-6xl";
                }

                const closeBtn = document.createElement('button');
                closeBtn.id = "message-close-btn";
                closeBtn.className = "w-full px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-xl transition-colors active:scale-[0.98]";
                closeBtn.textContent = "Tutup & Kembali Belanja";
                closeBtn.onclick = function() {
                    messageModal.classList.remove('show');
                };
                modalActions.appendChild(closeBtn);

                messageModal.classList.add('show');
            }

            function downloadReceipt(id) {
                // Penggunaan iframe tersembunyi untuk auto-download tanpa flicker atau pindah halaman
                let iframe = document.getElementById('download-iframe');
                if (!iframe) {
                    iframe = document.createElement('iframe');
                    iframe.id = 'download-iframe';
                    iframe.style.display = 'none';
                    document.body.appendChild(iframe);
                }
                iframe.src = 'generate_receipt_pdf.php?id=' + id;
            }

            function resetQuantityInputs() {
                const inputs = document.querySelectorAll('input[type="number"][id^="quantity-"]');
                inputs.forEach(input => {
                    input.value = 0;
                    const productId = input.getAttribute('data-product-id');
                    updateButtonState(productId);
                });
            }
        </script>
</body>

</html>