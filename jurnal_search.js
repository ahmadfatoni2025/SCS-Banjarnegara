// jurnal_search.js
// JavaScript untuk search realtime jurnal

class JurnalSearch {
    constructor() {
        this.searchTimeout = null;
        this.currentSearchResults = [];
        this.currentStats = {};
        this.isSearching = false;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initSearch();
        this.restoreFilters();
    }
    
    bindEvents() {
        // Advanced search toggle
        document.getElementById('advancedSearchToggle')?.addEventListener('click', () => {
            this.toggleAdvancedFilters();
        });
        
        // Apply filters button
        document.getElementById('applyFilters')?.addEventListener('click', () => {
            this.performSearch();
        });
        
        // Reset filters button
        document.getElementById('resetFilters')?.addEventListener('click', () => {
            this.resetFilters();
        });
    }
    
    initSearch() {
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        
        if (!searchInput) return;
        
        // Initialize search input value from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const searchParam = urlParams.get('search');
        if (searchParam) {
            searchInput.value = decodeURIComponent(searchParam);
            this.updateClearButton();
        }
        
        // Handle search input
        searchInput.addEventListener('input', (e) => {
            this.handleSearchInput(e.target.value);
        });
        
        // Handle clear search
        clearSearchBtn.addEventListener('click', () => {
            this.clearSearch();
        });
        
        // Handle Enter key
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.performSearch();
                e.preventDefault();
            }
        });
        
        // Handle filter changes
        const filters = ['filterDebit', 'filterKredit', 'filterManual', 'filterOtomatis'];
        filters.forEach(filterId => {
            document.getElementById(filterId)?.addEventListener('change', () => {
                this.saveFilters();
                const searchTerm = searchInput.value.trim();
                if (searchTerm.length > 0) {
                    this.performSearch();
                }
            });
        });
        
        // Handle reset search button
        document.addEventListener('click', (e) => {
            if (e.target.id === 'resetSearchBtn' || e.target.closest('#resetSearchBtn')) {
                this.clearSearch();
            }
            
            if (e.target.id === 'expandDateRangeBtn' || e.target.closest('#expandDateRangeBtn')) {
                this.expandDateRange();
            }
        });
        
        // Perform initial search if there's a search term
        if (searchInput.value.trim().length > 0) {
            setTimeout(() => this.performSearch(), 500);
        }
    }
    
    handleSearchInput(searchTerm) {
        this.updateClearButton();
        
        // Clear previous timeout
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        // Show loading if term is long enough
        if (searchTerm.length >= 2) {
            this.showLoading(true);
        }
        
        // Set new timeout for debouncing
        this.searchTimeout = setTimeout(() => {
            this.performSearch();
        }, 300); // 300ms delay for debouncing
    }
    
    updateClearButton() {
        const searchInput = document.getElementById('searchInput');
        const clearSearchBtn = document.getElementById('clearSearch');
        
        if (searchInput.value.trim().length > 0) {
            clearSearchBtn.classList.remove('hidden');
        } else {
            clearSearchBtn.classList.add('hidden');
        }
    }
    
    async performSearch() {
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput.value.trim();
        
        // Update URL with search parameter
        this.updateUrlWithSearch(searchTerm);
        
        // Show loading
        this.showLoading(true);
        
        // Prepare form data
        const formData = new FormData();
        formData.append('search', searchTerm);
        formData.append('tgl_mulai', '<?= $tgl_mulai ?>');
        formData.append('tgl_selesai', '<?= $tgl_selesai ?>');
        formData.append('filter_debit', document.getElementById('filterDebit')?.checked ? '1' : '0');
        formData.append('filter_kredit', document.getElementById('filterKredit')?.checked ? '1' : '0');
        formData.append('filter_manual', document.getElementById('filterManual')?.checked ? '1' : '0');
        formData.append('filter_otomatis', document.getElementById('filterOtomatis')?.checked ? '1' : '0');
        
        try {
            const response = await fetch('search_jurnal.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentSearchResults = data.results;
                this.currentStats = data.stats || {};
                
                // Update UI
                this.updateSearchResultsUI(data);
                this.highlightSearchResults(data.results, searchTerm);
                this.filterAndDisplayResults(data.results);
                this.updateQuickStats(data.stats);
            } else {
                console.error('Search error:', data.message);
                this.showError('Gagal melakukan pencarian');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Terjadi kesalahan saat melakukan pencarian');
        } finally {
            this.showLoading(false);
        }
    }
    
    updateSearchResultsUI(data) {
        const searchResultsCount = document.getElementById('searchResultsCount');
        const foundCount = document.getElementById('foundCount');
        const searchTerm = document.getElementById('searchInput').value.trim();
        
        if (searchTerm.length > 0) {
            searchResultsCount.classList.remove('hidden');
            foundCount.textContent = data.total_found;
            
            // Show quick stats
            document.getElementById('searchQuickStats').classList.remove('hidden');
        } else {
            searchResultsCount.classList.add('hidden');
            document.getElementById('searchQuickStats').classList.add('hidden');
        }
    }
    
    updateQuickStats(stats) {
        if (!stats) return;
        
        document.getElementById('statsManual').textContent = stats.manual || 0;
        document.getElementById('statsOtomatis').textContent = stats.otomatis || 0;
        
        // Format total value
        const total = (stats.total_debit || 0) + (stats.total_kredit || 0);
        document.getElementById('statsTotal').textContent = this.formatRupiah(total);
    }
    
    highlightSearchResults(results, searchTerm) {
        // Remove previous highlights
        this.removeHighlights();
        
        if (!searchTerm || searchTerm.length < 2) return;
        
        const searchTermLower = searchTerm.toLowerCase();
        const searchTerms = searchTermLower.split(' ').filter(term => term.length > 2);
        
        if (searchTerms.length === 0) return;
        
        // Highlight transaction groups
        results.forEach(result => {
            const groupElement = document.querySelector(`.transaction-group .font-mono:contains("${result.no_reff}")`)?.closest('.transaction-group');
            
            if (groupElement) {
                groupElement.classList.add('search-highlight');
                
                // Highlight matching text within the group
                this.highlightTextInElement(groupElement, searchTerms);
            }
        });
    }
    
    highlightTextInElement(element, searchTerms) {
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const nodes = [];
        let node;
        while (node = walker.nextNode()) {
            if (node.textContent.trim()) {
                nodes.push(node);
            }
        }
        
        nodes.forEach(textNode => {
            const text = textNode.textContent;
            let highlighted = false;
            
            searchTerms.forEach(term => {
                const regex = new RegExp(`(${this.escapeRegExp(term)})`, 'gi');
                if (regex.test(text)) {
                    highlighted = true;
                    const newHTML = text.replace(regex, '<mark class="search-match">$1</mark>');
                    const span = document.createElement('span');
                    span.innerHTML = newHTML;
                    textNode.parentNode.replaceChild(span, textNode);
                }
            });
        });
    }
    
    removeHighlights() {
        // Remove highlight classes
        document.querySelectorAll('.search-highlight').forEach(element => {
            element.classList.remove('search-highlight');
        });
        
        // Remove mark elements
        document.querySelectorAll('mark.search-match').forEach(mark => {
            const parent = mark.parentNode;
            const text = document.createTextNode(mark.textContent);
            parent.replaceChild(text, mark);
            parent.normalize();
        });
    }
    
    filterAndDisplayResults(results) {
        const filterDebit = document.getElementById('filterDebit')?.checked || true;
        const filterKredit = document.getElementById('filterKredit')?.checked || true;
        const filterManual = document.getElementById('filterManual')?.checked || true;
        const filterOtomatis = document.getElementById('filterOtomatis')?.checked || true;
        const searchTerm = document.getElementById('searchInput').value.trim();
        
        const transactionGroups = document.querySelectorAll('.transaction-group');
        let visibleCount = 0;
        
        transactionGroups.forEach(group => {
            const noReffElement = group.querySelector('.transaction-header span.font-mono');
            if (!noReffElement) return;
            
            const noReff = noReffElement.textContent.trim();
            const isOtomatis = group.querySelector('.badge-penjualan') !== null;
            
            // Find if this transaction is in search results
            const foundInResults = results.some(result => result.no_reff === noReff);
            
            // Apply filters
            let shouldShow = true;
            
            // Apply search filter
            if (searchTerm && !foundInResults) {
                shouldShow = false;
            }
            
            // Apply manual/automatic filter
            if (shouldShow) {
                if ((!filterManual && !isOtomatis) || (!filterOtomatis && isOtomatis)) {
                    shouldShow = false;
                }
            }
            
            // Apply debit/kredit filter
            if (shouldShow) {
                const hasDebit = group.querySelector('.debit-row') !== null;
                const hasKredit = group.querySelector('.kredit-row') !== null;
                
                if ((!filterDebit && hasDebit && !hasKredit) || 
                    (!filterKredit && hasKredit && !hasDebit)) {
                    shouldShow = false;
                }
            }
            
            // Toggle visibility
            if (shouldShow) {
                group.classList.remove('hidden');
                visibleCount++;
            } else {
                group.classList.add('hidden');
            }
        });
        
        // Show/hide no results message
        this.toggleNoResultsMessage(visibleCount, searchTerm);
    }
    
    toggleNoResultsMessage(visibleCount, searchTerm) {
        const tableContainer = document.querySelector('.overflow-x-auto');
        const existingMessage = document.getElementById('noResultsMessage');
        
        if (visibleCount === 0 && searchTerm.length > 0) {
            if (!existingMessage) {
                const template = document.getElementById('noResultsTemplate');
                const clone = template.content.cloneNode(true);
                tableContainer.appendChild(clone);
            }
        } else if (existingMessage) {
            existingMessage.remove();
        }
    }
    
    clearSearch() {
        const searchInput = document.getElementById('searchInput');
        searchInput.value = '';
        
        this.updateClearButton();
        this.updateUrlWithSearch('');
        
        // Reset highlights and show all
        this.removeHighlights();
        this.performSearch();
        
        // Hide advanced filters if open
        document.getElementById('advancedSearchFilters').classList.add('hidden');
    }
    
    resetFilters() {
        // Reset all filter checkboxes
        document.getElementById('filterDebit').checked = true;
        document.getElementById('filterKredit').checked = true;
        document.getElementById('filterManual').checked = true;
        document.getElementById('filterOtomatis').checked = true;
        
        this.saveFilters();
        this.performSearch();
    }
    
    toggleAdvancedFilters() {
        const filtersSection = document.getElementById('advancedSearchFilters');
        filtersSection.classList.toggle('hidden');
        
        const toggleBtn = document.getElementById('advancedSearchToggle');
        const icon = toggleBtn.querySelector('i');
        
        if (filtersSection.classList.contains('hidden')) {
            icon.className = 'fas fa-sliders-h';
            toggleBtn.innerHTML = '<i class="fas fa-sliders-h"></i><span>Filter</span>';
        } else {
            icon.className = 'fas fa-times';
            toggleBtn.innerHTML = '<i class="fas fa-times"></i><span>Tutup</span>';
        }
    }
    
    updateUrlWithSearch(searchTerm) {
        const url = new URL(window.location);
        
        if (searchTerm) {
            url.searchParams.set('search', encodeURIComponent(searchTerm));
        } else {
            url.searchParams.delete('search');
        }
        
        // Update URL without reloading
        window.history.pushState({}, '', url);
    }
    
    showLoading(show) {
        const loadingElement = document.getElementById('searchLoading');
        if (show) {
            loadingElement.classList.remove('hidden');
        } else {
            loadingElement.classList.add('hidden');
        }
    }
    
    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }
    
    formatRupiah(amount) {
        if (!amount) return 'Rp 0';
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
    }
    
    escapeRegExp(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    saveFilters() {
        const filters = {
            debit: document.getElementById('filterDebit').checked,
            kredit: document.getElementById('filterKredit').checked,
            manual: document.getElementById('filterManual').checked,
            otomatis: document.getElementById('filterOtomatis').checked
        };
        
        localStorage.setItem('jurnalSearchFilters', JSON.stringify(filters));
    }
    
    restoreFilters() {
        const savedFilters = localStorage.getItem('jurnalSearchFilters');
        if (savedFilters) {
            const filters = JSON.parse(savedFilters);
            document.getElementById('filterDebit').checked = filters.debit;
            document.getElementById('filterKredit').checked = filters.kredit;
            document.getElementById('filterManual').checked = filters.manual;
            document.getElementById('filterOtomatis').checked = filters.otomatis;
        }
    }
    
    expandDateRange() {
        // Calculate date range 3 months back
        const currentDate = new Date('<?= $tgl_selesai ?>');
        const startDate = new Date('<?= $tgl_mulai ?>');
        
        // Set start date to 3 months before current end date
        const newStartDate = new Date(currentDate);
        newStartDate.setMonth(newStartDate.getMonth() - 3);
        
        // Redirect with new date range
        const url = new URL(window.location);
        url.searchParams.set('tgl_mulai', newStartDate.toISOString().split('T')[0]);
        url.searchParams.set('tgl_selesai', currentDate.toISOString().split('T')[0]);
        
        window.location.href = url.toString();
    }
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.jurnalSearch = new JurnalSearch();
});