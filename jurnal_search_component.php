<?php
// jurnal_search_component.php
// Komponen search bar untuk Jurnal Umum

// Check if search parameter exists
$search_keyword = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
?>

<!-- Search Bar Component -->
<div class="relative z-10 mb-6">
    <div class="soft-card p-5">
        <div class="flex flex-col lg:flex-row gap-4 justify-between items-center">
            <!-- Search Input Section -->
            <div class="flex-1 w-full">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400 text-lg"></i>
                    </div>
                    <input type="text" 
                           id="searchInput" 
                           value="<?= $search_keyword ?>"
                           class="w-full pl-12 pr-10 py-3.5 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-slate-700 placeholder-slate-400 text-base"
                           placeholder="Cari transaksi: no referensi, keterangan, nama akun, kode akun, nominal...">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none hidden" id="searchLoading">
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                    </div>
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center" id="searchClearContainer">
                        <button id="clearSearch" class="text-gray-400 hover:text-gray-600 transition-colors hidden">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Quick Search Tips -->
                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                    <span class="flex items-center gap-1">
                        <i class="fas fa-lightbulb text-blue-400"></i>
                        <span>Tips:</span>
                    </span>
                    <span class="bg-slate-100 px-2 py-1 rounded">ORD-20240101</span>
                    <span class="bg-slate-100 px-2 py-1 rounded">Pembelian</span>
                    <span class="bg-slate-100 px-2 py-1 rounded">Kas</span>
                    <span class="bg-slate-100 px-2 py-1 rounded">1000000</span>
                </div>
            </div>
            
            <!-- Search Results Info -->
            <div class="flex flex-col lg:flex-row items-center gap-3 lg:gap-4">
                <div id="searchResultsCount" class="hidden">
                    <div class="flex items-center gap-2 bg-blue-50 border border-blue-200 px-4 py-2.5 rounded-xl">
                        <i class="fas fa-check-circle text-blue-600"></i>
                        <span class="text-blue-700 font-medium">
                            <span id="foundCount">0</span> ditemukan
                        </span>
                    </div>
                </div>
                
                <!-- Search Actions -->
                <div class="flex gap-2">
                    <button id="advancedSearchToggle" 
                            class="flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl transition-all font-medium text-sm">
                        <i class="fas fa-sliders-h"></i>
                        <span>Filter</span>
                    </button>
                    
                    <?php if(!empty($search_keyword)): ?>
                    <a href="jurnal_umum.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&sort_by=<?= $sort_by ?>"
                       class="flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2.5 rounded-xl transition-all font-medium text-sm">
                        <i class="fas fa-times"></i>
                        <span>Reset</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Advanced Search Filters (Initially Hidden) -->
        <div id="advancedSearchFilters" class="mt-4 p-4 bg-slate-50 rounded-xl border border-slate-200 hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Transaction Type Filter -->
                <div>
                    <h4 class="text-sm font-semibold text-slate-700 mb-2">Tipe Transaksi</h4>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" id="filterDebit" class="rounded text-green-600 focus:ring-green-500" checked>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-arrow-down text-green-600 text-xs"></i>
                                <span>Transaksi Debit</span>
                            </span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" id="filterKredit" class="rounded text-red-600 focus:ring-red-500" checked>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-arrow-up text-red-600 text-xs"></i>
                                <span>Transaksi Kredit</span>
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Source Filter -->
                <div>
                    <h4 class="text-sm font-semibold text-slate-700 mb-2">Sumber Transaksi</h4>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" id="filterManual" class="rounded text-blue-600 focus:ring-blue-500" checked>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-edit text-blue-600 text-xs"></i>
                                <span>Manual (Jurnal)</span>
                            </span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" id="filterOtomatis" class="rounded text-orange-600 focus:ring-orange-500" checked>
                            <span class="flex items-center gap-1">
                                <i class="fas fa-shopping-cart text-orange-600 text-xs"></i>
                                <span>Otomatis (Penjualan)</span>
                            </span>
                        </label>
                    </div>
                </div>
                
                <!-- Date Range for Search -->
                <div>
                    <h4 class="text-sm font-semibold text-slate-700 mb-2">Rentang Tanggal</h4>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-sm">
                            <i class="fas fa-calendar text-slate-400"></i>
                            <span><?= date('d M', strtotime($tgl_mulai)) ?> - <?= date('d M Y', strtotime($tgl_selesai)) ?></span>
                        </div>
                        <a href="?tgl_mulai=<?= date('Y-m-01') ?>&tgl_selesai=<?= date('Y-m-d') ?>"
                           class="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1">
                            <i class="fas fa-sync text-xs"></i>
                            <span>Reset ke bulan ini</span>
                        </a>
                    </div>
                </div>
                
                <!-- Search Actions -->
                <div class="flex flex-col justify-end">
                    <div class="space-y-2">
                        <button id="applyFilters" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg transition-all text-sm font-medium flex items-center justify-center gap-2">
                            <i class="fas fa-filter"></i>
                            <span>Terapkan Filter</span>
                        </button>
                        <button id="resetFilters" 
                                class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2.5 rounded-lg transition-all text-sm font-medium flex items-center justify-center gap-2">
                            <i class="fas fa-redo"></i>
                            <span>Reset Filter</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div id="searchQuickStats" class="mt-4 hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-white border border-slate-200 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500">Transaksi Manual</p>
                            <p class="text-lg font-bold text-blue-600" id="statsManual">0</p>
                        </div>
                        <div class="bg-blue-100 p-2 rounded-full">
                            <i class="fas fa-edit text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500">Transaksi Otomatis</p>
                            <p class="text-lg font-bold text-orange-600" id="statsOtomatis">0</p>
                        </div>
                        <div class="bg-orange-100 p-2 rounded-full">
                            <i class="fas fa-shopping-cart text-orange-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 rounded-lg p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-500">Total Nilai</p>
                            <p class="text-lg font-bold text-green-600" id="statsTotal">Rp 0</p>
                        </div>
                        <div class="bg-green-100 p-2 rounded-full">
                            <i class="fas fa-coins text-green-600"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- No Results Template (Hidden) -->
<template id="noResultsTemplate">
    <div class="text-center p-12 text-slate-500" id="noResultsMessage">
        <div class="flex flex-col items-center gap-4">
            <div class="bg-slate-100 p-6 rounded-full">
                <i class="fas fa-search text-4xl text-slate-300"></i>
            </div>
            <div>
                <h4 class="text-xl font-semibold text-slate-400 mb-2">Tidak ditemukan</h4>
                <p class="text-slate-400 mb-4">Tidak ada transaksi yang sesuai dengan kriteria pencarian</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <button id="resetSearchBtn" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg transition-all font-medium flex items-center justify-center gap-2">
                        <i class="fas fa-redo"></i>
                        <span>Reset Pencarian</span>
                    </button>
                    <button id="expandDateRangeBtn" 
                            class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-lg transition-all font-medium flex items-center justify-center gap-2">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Perluas Rentang Tanggal</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
