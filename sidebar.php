<?php
// Pastikan session sudah start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Tentukan halaman aktif berdasarkan nama file
$current_page = basename($_SERVER['PHP_SELF']);

// Ambil data dari session dengan fallback yang aman
$user_foto = isset($_SESSION['user']['foto']) ? $_SESSION['user']['foto'] : null;
$user_nama = isset($_SESSION['user']['nama']) ? $_SESSION['user']['nama'] : 'Administrator';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Admin';

// Konfigurasi Akses Keuangan
$akses_keuangan = in_array(strtolower($user_role), ['akuntan', 'owner']);
$halaman_keuangan = [
    'master_akun.php', 'jurnal_umum.php', 'buku_besar.php', 
    'laba_rugi.php', 'neraca.php', 'arus_kas.php', 
    'input_jurnal.php', 'tutup_buku.php', 'rekap_tahunan.php', 
    'master_export.php', 'visual_report.php'
];
$is_keuangan_active = in_array($current_page, $halaman_keuangan);

// Fungsi sederhana untuk mendapatkan foto profil
function getProfileImage($foto) {
    $default_image = 'https://upload.wikimedia.org/wikipedia/commons/4/44/Logo_Muhammadiyah.svg';
    
    if (!$foto) {
        return $default_image;
    }
    
    // Cek path yang umum digunakan
    $possible_paths = [
        '../uploads/profiles/' . $foto,
        '../../uploads/profiles/' . $foto,
        'uploads/profiles/' . $foto,
        '../assets/images/profiles/' . $foto
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_file($path)) {
            return $path;
        }
    }
    
    return $default_image;
}

// Dapatkan path gambar profil
$profile_image_src = getProfileImage($user_foto);

// Fallback tambahan jika foto tidak ada
if (empty($user_foto) || $profile_image_src === 'https://upload.wikimedia.org/wikipedia/commons/4/44/Logo_Muhammadiyah.svg') {
    $profile_image_src = 'https://upload.wikimedia.org/wikipedia/commons/4/44/Logo_Muhammadiyah.svg';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sidebar SCS</title>
  <link rel="icon" href="logo_scs_jpg.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap');
    
    * {
        margin: 0;
        padding: 0;
        -webkit-tap-highlight-color: transparent;
        box-sizing: border-box;
        font-family: 'Google Sans', sans-serif;
    }

    /* Base Sidebar styles */
    .sidebar {
        width: 16rem; /* 256px */
        /* background-color applied via Tailwind */
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        flex-direction: column;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 50;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Collapsed state for Desktop */
    .sidebar.collapsed {
        width: 5rem; /* 80px */
    }
    
    .sidebar.collapsed .sidebar-text,
    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .menu-group-title {
        display: none;
    }
    
    .sidebar.collapsed .sidebar-search {
        padding: 0.5rem;
        justify-content: center;
    }
    .sidebar.collapsed .sidebar-search input,
    .sidebar.collapsed .sidebar-search span {
        display: none;
    }
    .sidebar.collapsed .sidebar-search i {
        margin: 0;
    }

    .sidebar.collapsed .nav-link {
        justify-content: center;
        padding: 0.75rem 0;
        margin-left: 0.75rem;
        margin-right: 0.75rem;
    }
    .sidebar.collapsed .nav-link i {
        margin-right: 0;
    }
    
    .sidebar.collapsed .sidebar-box {
        background: transparent;
        border: none;
        padding: 0;
        margin: 0 0.5rem 1rem 0.5rem;
        justify-content: center;
    }
    .sidebar.collapsed .sidebar-box img {
        margin: 0 auto;
    }
    .sidebar.collapsed .sidebar-box .box-content {
        display: none;
    }

    /* Active & Hover states */
    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        margin: 0.25rem 1rem;
        border-radius: 12px;
        color: #d1d5db;
        text-decoration: none;
        transition: all 0.2s ease;
        position: relative;
    }
    
    .nav-link:hover:not(.active-menu) {
        background-color: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }

    .active-menu {
        background-color: #ffffff;
        color: #1d4ed8;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    
    .nav-link i {
        font-size: 1.1rem;
        width: 1.5rem;
        text-align: center;
        margin-right: 0.75rem;
    }

    /* Scrollbar */
    .sidebar-scroll {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 1rem;
    }
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #d1d5db; }

    /* Group Title */
    .menu-group-title {
        font-size: 0.65rem;
        font-weight: 700;
        color: #93c5fd;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin: 1.5rem 1.5rem 0.5rem 1.5rem;
    }

    /* Responsive */
    @media (min-width: 1025px) { 
        .mobile-menu-button { display: none; } 
        .sidebar-overlay { display: none; } 
    }
    
    @media (max-width: 1024px) { 
        .sidebar { transform: translateX(-100%); } 
        .sidebar.open { transform: translateX(0); }
        .mobile-menu-button { display: block; }
        .sidebar-overlay {
            display: none;
        }
        .sidebar-overlay.open { 
            display: block; 
            position: fixed; 
            top: 0; left: 0; right: 0; bottom: 0; 
            background: rgba(17, 24, 39, 0.4); 
            backdrop-filter: blur(2px);
            z-index: 40; 
        }
        .collapse-btn-desktop { display: none; }
    }
    
    /* Search Bar */
    .sidebar-search-container {
        padding: 0 1rem;
        margin-top: 0.5rem;
        margin-bottom: 1rem;
    }
    .sidebar-search {
        display: flex;
        align-items: center;
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 0.6rem 0.8rem;
        color: #d1d5db;
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.2s;
    }
    .sidebar-search:focus-within {
        background-color: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .sidebar-search i {
        font-size: 0.9rem;
        margin-right: 0.5rem;
    }
    .sidebar-search input {
        border: none;
        background: transparent;
        outline: none;
        width: 100%;
        font-size: 0.85rem;
        color: #ffffff;
    }
    .sidebar-search input::placeholder {
        color: #93c5fd;
    }
    .sidebar-search span {
        font-size: 0.7rem;
        background-color: rgba(255, 255, 255, 0.2);
        padding: 0.1rem 0.3rem;
        border-radius: 4px;
        color: #ffffff;
        font-weight: 600;
    }
  </style>
</head>
<body class="bg-[#f8f9fa] bg-gradient-to-br from-[#f8f9fa] to-[#e5e7eb]">
  <div id="sidebar-overlay" class="sidebar-overlay transition-opacity duration-300" onclick="toggleMobileSidebar()"></div>

  <aside id="sidebar" class="sidebar bg-gradient-to-br from-[#3b82f6] to-[#1d4ed8]">
    <!-- Brand / Logo Area -->
    <div class="flex items-center justify-between p-5">
        <div class="flex items-center gap-3">
            <span class="font-bold text-white text-lg brand-text tracking-tight">SCS<span class="font-light text-blue-200"> Akuntan</span></span>
        </div>
        <button onclick="toggleDesktopCollapse()" class="collapse-btn-desktop text-blue-100 hover:text-white transition-colors bg-white/10 hover:bg-white/20 w-8 h-8 rounded-lg flex items-center justify-center">
            <i class="fas fa-bars-staggered text-sm"></i>
        </button>
        <!-- Mobile close button -->
        <button onclick="toggleMobileSidebar()" class="lg:hidden text-blue-100 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Search Bar -->
    <div class="sidebar-search-container">
        <div class="sidebar-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search">
            <span class="sidebar-text">⌘K</span>
        </div>
    </div>

    <!-- Navigation Scrollable Area -->
    <div class="sidebar-scroll">

        <div class="menu-group-title">Main Menu</div>
        
        <?php 
        $dashboard_url = "../dashboard.php";
        if ($user_role === 'akuntan') $dashboard_url = "../visual_report.php";
        if ($user_role === 'dapur') $dashboard_url = "../dapur.php";
        ?>
        <a href="<?php echo $dashboard_url; ?>" class="nav-link <?php echo ($current_page == 'dashboard.php' || $current_page == 'visual_report.php' || $current_page == 'welcome.php') ? 'active-menu' : ''; ?>">
           <i class="fas fa-table-cells-large"></i> 
           <span class="font-semibold text-sm sidebar-text">Dashboard</span>
        </a>

      <?php if ($user_role === 'admin'): ?>
      <a href="../index.php" class="nav-link <?php echo ($current_page == 'index.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-box-open"></i> 
        <span class="font-semibold text-sm sidebar-text flex-1">Inventaris Bahan</span>
        <span class="sidebar-text text-[10px] font-bold bg-white/20 text-white px-2 py-0.5 rounded-full">12</span>
      </a>
      
      <div class="menu-group-title">Penjualan</div>
      <a href="../laporanPenjualan.php" class="nav-link <?php echo ($current_page == 'laporanPenjualan.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-desktop"></i> 
        <span class="font-semibold text-sm sidebar-text">Monitor Pesanan</span>
      </a>
      <a href="../konfirmasi_penjualan.php" class="nav-link <?php echo ($current_page == 'konfirmasi_penjualan.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-check-circle"></i> 
        <span class="font-semibold text-sm sidebar-text flex-1">Konfirmasi</span>
        <span class="sidebar-text text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">New</span>
      </a>
      <a href="../laporanBarangKeluar.php" class="nav-link <?php echo ($current_page == 'laporanBarangKeluar.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-arrow-right-from-bracket"></i> 
        <span class="font-semibold text-sm sidebar-text">Barang Keluar</span>
      </a>

      <div class="menu-group-title">Lainnya</div>
      <a href="../inputdatasuplayer.php" class="nav-link <?php echo ($current_page == 'inputdatasuplayer.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-truck-moving"></i> 
        <span class="font-semibold text-sm sidebar-text">Data Supplier</span>
      </a>
      <a href="../analytics.php" class="nav-link <?php echo ($current_page == 'analytics.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-brain"></i>
        <span class="font-semibold text-sm sidebar-text">Smart Analytics</span>
      </a>
      <?php endif; ?>

      <?php if ($akses_keuangan): ?>
      <!-- DATA MASTER & INPUT -->
      <div class="menu-group-title">Master & Input</div>
      
      <a href="../master_akun.php" class="nav-link <?php echo ($current_page == 'master_akun.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-list"></i> 
        <span class="font-semibold text-sm sidebar-text">Master Akun (COA)</span>
      </a>
      <a href="../jurnal_umum.php" class="nav-link <?php echo ($current_page == 'jurnal_umum.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-book-journal-whills"></i> 
        <span class="font-semibold text-sm sidebar-text">Jurnal Umum</span>
      </a>
      <a href="../konfirmasi_penjualan.php" class="nav-link <?php echo ($current_page == 'konfirmasi_penjualan.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-clipboard-check"></i> 
        <span class="font-semibold text-sm sidebar-text flex-1">Konfirmasi</span>
        <span class="sidebar-text text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">New</span>
      </a>

      <!-- REPORTS -->
      <div class="menu-group-title">Laporan Keuangan</div>
      <a href="../buku_besar.php" class="nav-link <?php echo ($current_page == 'buku_besar.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-book-open"></i> 
        <span class="font-semibold text-sm sidebar-text">Buku Besar</span>
      </a>
      <a href="../laba_rugi.php" class="nav-link <?php echo ($current_page == 'laba_rugi.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-chart-pie"></i> 
        <span class="font-semibold text-sm sidebar-text">Laba Rugi</span>
      </a>
      <a href="../neraca.php" class="nav-link <?php echo ($current_page == 'neraca.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-scale-balanced"></i> 
        <span class="font-semibold text-sm sidebar-text">Neraca</span>
      </a>
      <a href="../arus_kas.php" class="nav-link <?php echo ($current_page == 'arus_kas.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-money-bill-transfer"></i> 
        <span class="font-semibold text-sm sidebar-text">Arus Kas</span>
      </a>

      <!-- ANALYSIS -->
      <div class="menu-group-title">Analisis & Tools</div>
      <a href="../rekap_tahunan.php" class="nav-link <?php echo ($current_page == 'rekap_tahunan.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-folder-open"></i> 
        <span class="font-semibold text-sm sidebar-text">Rekap Tahunan</span>
      </a>
      <a href="../tutup_buku.php" class="nav-link <?php echo ($current_page == 'tutup_buku.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-calendar-check"></i> 
        <span class="font-semibold text-sm sidebar-text">Tutup Buku</span>
      </a>
      <a href="../master_export.php" class="nav-link <?php echo ($current_page == 'master_export.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-file-export"></i> 
        <span class="font-semibold text-sm sidebar-text">Import & Export</span>
      </a>
      <?php endif; ?>
      
      <div class="menu-group-title">General</div>
      <a href="../pengaturanAkun.php" class="nav-link <?php echo ($current_page == 'pengaturanAkun.php') ? 'active-menu' : ''; ?>">
        <i class="fas fa-gear"></i> 
        <span class="font-semibold text-sm sidebar-text">Settings</span>
      </a>
      <a href="#" class="nav-link">
        <i class="fas fa-circle-question"></i> 
        <span class="font-semibold text-sm sidebar-text">Help Desk</span>
      </a>
      <a href="../logout.php" class="nav-link hover:text-red-600">
        <i class="fas fa-arrow-right-from-bracket"></i> 
        <span class="font-semibold text-sm sidebar-text">Log out</span>
      </a>

    </div>

    <!-- Upgrade / Profile Box -->
    <div class="sidebar-box p-4 mx-4 mb-4 mt-2 bg-gradient-to-br from-[#f8f9fa] to-white rounded-[20px] border border-gray-200/80 shadow-sm flex flex-col gap-3">
        <div class="flex items-center gap-3">
            <img src="<?php echo htmlspecialchars($profile_image_src); ?>" alt="Profil" class="w-10 h-10 rounded-full object-cover border border-gray-200" onerror="handleProfileImageError(this)">
            <div class="box-content flex-1 min-w-0">
                <p class="font-bold text-sm text-gray-900 truncate"><?php echo htmlspecialchars($user_nama); ?></p>
                <p class="text-xs text-gray-500 truncate capitalize"><?php echo htmlspecialchars($user_role); ?></p>
            </div>
        </div>
        <a href="../pengaturanAkun.php" class="box-content text-center bg-[#056041] hover:bg-[#044d34] text-white text-[13px] font-bold py-2 rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2">
            <i class="fas fa-crown text-[#fde047]"></i> Edit Profile
        </a>
    </div>

  </aside>

  <!-- Mobile Toggle Button (Visible only on mobile) -->
  <button id="mobile-menu-button" onclick="toggleMobileSidebar()" class="mobile-menu-button fixed top-4 left-4 z-40 bg-white border border-gray-200 text-gray-700 w-10 h-10 rounded-xl shadow-sm hover:bg-gray-50 transition-colors flex items-center justify-center">
      <i class="fas fa-bars text-lg"></i>
  </button>

  <script>
    function handleProfileImageError(img) {
      img.onerror = null; 
      img.src = 'https://upload.wikimedia.org/wikipedia/commons/4/44/Logo_Muhammadiyah.svg';
    }

    function toggleMobileSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebar-overlay');
      
      // If desktop collapse is active, remove it when opening on mobile
      sidebar.classList.remove('collapsed');
      
      sidebar.classList.toggle('open');
      overlay.classList.toggle('open');
    }

    function toggleDesktopCollapse() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
        
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        // Find main content wrappers that use ml-64 variations and adjust them
        const contentWrappers = document.querySelectorAll('.ml-64, .md\\:ml-64, .lg\\:ml-64, .ml-20, .md\\:ml-20, .lg\\:ml-20');
        contentWrappers.forEach(wrapper => {
            if(isCollapsed) {
                if(wrapper.classList.contains('lg:ml-64')) { wrapper.classList.remove('lg:ml-64'); wrapper.classList.add('lg:ml-20'); }
                if(wrapper.classList.contains('md:ml-64')) { wrapper.classList.remove('md:ml-64'); wrapper.classList.add('md:ml-20'); }
                if(wrapper.classList.contains('ml-64')) { wrapper.classList.remove('ml-64'); wrapper.classList.add('ml-20'); }
            } else {
                if(wrapper.classList.contains('lg:ml-20')) { wrapper.classList.remove('lg:ml-20'); wrapper.classList.add('lg:ml-64'); }
                if(wrapper.classList.contains('md:ml-20')) { wrapper.classList.remove('md:ml-20'); wrapper.classList.add('md:ml-64'); }
                if(wrapper.classList.contains('ml-20')) { wrapper.classList.remove('ml-20'); wrapper.classList.add('ml-64'); }
            }
        });
        
        // Save state to localStorage for persistence
        localStorage.setItem('sidebarState', isCollapsed ? 'collapsed' : 'expanded');
    }

    // Load state on start
    document.addEventListener('DOMContentLoaded', function() {
        if(window.innerWidth > 1024) {
            const state = localStorage.getItem('sidebarState');
            if(state === 'collapsed') {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.add('collapsed');
                
                // Adjust content wrappers on load
                const contentWrappers = document.querySelectorAll('.ml-64, .md\\:ml-64, .lg\\:ml-64');
                contentWrappers.forEach(wrapper => {
                    if(wrapper.classList.contains('lg:ml-64')) { wrapper.classList.remove('lg:ml-64'); wrapper.classList.add('lg:ml-20'); }
                    if(wrapper.classList.contains('md:ml-64')) { wrapper.classList.remove('md:ml-64'); wrapper.classList.add('md:ml-20'); }
                    if(wrapper.classList.contains('ml-64')) { wrapper.classList.remove('ml-64'); wrapper.classList.add('ml-20'); }
                });
            }
        }
    });

    // Handle resize
    window.addEventListener('resize', function() {
      if (window.innerWidth > 1024) {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebar-overlay').classList.remove('open');
      }
    });
  </script>
</body>
</html>
