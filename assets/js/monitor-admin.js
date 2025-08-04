/**
 * Monitor Admin JavaScript
 * Admin panel functionality for managing vendor monitor permissions
 */

class MonitorAdmin {
    constructor() {
        this.config = window.monitor_admin_ajax || {};
        this.isLoading = false;
        this.originalData = [];
        this.filteredData = [];
        
        this.init();
    }

    init() {
        console.log('Initializing Monitor Admin', this.config);
        
        // Initialize DOM elements
        this.table = document.getElementById('monitor-vendor-table');
        this.tableBody = document.getElementById('vendor-table-body');
        this.searchInput = document.getElementById('vendor-search');
        this.statusFilter = document.getElementById('status-filter');
        this.filterBtn = document.getElementById('filter-btn');
        this.resetBtn = document.getElementById('reset-filters');
        this.refreshBtn = document.getElementById('refresh-data');
        this.loadingOverlay = document.getElementById('monitor-loading-overlay');
        this.selectAllCheckbox = document.getElementById('cb-select-all-1');
        this.form = document.getElementById('monitor-vendor-form');
        
        // Store original table data for filtering
        this.storeOriginalData();
        
        // Bind events
        this.bindEvents();
    }

    storeOriginalData() {
        if (!this.tableBody) return;
        
        const rows = this.tableBody.querySelectorAll('tr');
        this.originalData = Array.from(rows).map(row => {
            return {
                element: row.cloneNode(true),
                vendorId: row.dataset.vendorId,
                enabled: row.dataset.enabled === '1',
                active: row.dataset.active === '1',
                shopName: row.querySelector('.shop-name strong').textContent.toLowerCase(),
                email: row.querySelector('.email a').textContent.toLowerCase(),
                city: row.querySelector('.city').textContent.toLowerCase()
            };
        });
        
        this.filteredData = [...this.originalData];
    }

    bindEvents() {
        // Toggle switches
        this.bindToggleEvents();
        
        // Search and filter
        if (this.searchInput) {
            this.searchInput.addEventListener('input', () => this.handleSearch());
        }
        
        if (this.statusFilter) {
            this.statusFilter.addEventListener('change', () => this.handleFilter());
        }
        
        if (this.filterBtn) {
            this.filterBtn.addEventListener('click', () => this.applyFilters());
        }
        
        if (this.resetBtn) {
            this.resetBtn.addEventListener('click', () => this.resetFilters());
        }
        
        if (this.refreshBtn) {
            this.refreshBtn.addEventListener('click', () => this.refreshData());
        }
        
        // Select all checkbox
        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }
        
        // Form submission
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.handleBulkAction(e));
        }
        
        // Table sorting
        this.bindSortingEvents();
    }

    bindToggleEvents() {
        if (!this.tableBody) return;
        
        const toggles = this.tableBody.querySelectorAll('.monitor-toggle-checkbox');
        toggles.forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                this.handleToggle(e.target);
            });
        });
    }

    async handleToggle(checkbox) {
        const vendorId = checkbox.dataset.vendorId;
        const enabled = checkbox.checked;
        const originalState = !enabled;
        
        try {
            this.showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'monitor_toggle_vendor');
            formData.append('vendor_id', vendorId);
            formData.append('enabled', enabled);
            formData.append('nonce', this.config.nonce);
            
            const response = await fetch(this.config.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showMessage(data.data.message, 'success');
                this.updateVendorRow(vendorId, enabled);
                this.updateStatsCards();
            } else {
                throw new Error(data.data || 'Errore sconosciuto');
            }
        } catch (error) {
            console.error('Error toggling vendor:', error);
            checkbox.checked = originalState; // Revert checkbox
            this.showMessage('Errore durante l\'aggiornamento: ' + error.message, 'error');
        } finally {
            this.showLoading(false);
        }
    }

    updateVendorRow(vendorId, enabled) {
        const row = this.tableBody.querySelector(`tr[data-vendor-id="${vendorId}"]`);
        if (!row) return;
        
        // Update row data attributes
        row.dataset.enabled = enabled ? '1' : '0';
        row.dataset.active = '0'; // Reset active status when toggling
        
        // Update status badge
        const statusBadge = row.querySelector('.status-badge');
        if (statusBadge) {
            statusBadge.className = 'status-badge ' + (enabled ? 'status-inactive' : 'status-disabled');
            statusBadge.textContent = enabled ? 'Inattivo' : 'Disabilitato';
        }
        
        // Update monitor URL column
        const urlColumn = row.querySelector('.monitor-url');
        if (urlColumn) {
            if (enabled) {
                const vendorIdText = vendorId;
                const shopName = row.querySelector('.shop-name strong').textContent;
                const urlSlug = this.generateUrlSlug(shopName, vendorId);
                
                urlColumn.innerHTML = `
                    <code>${urlSlug}</code>
                    <br>
                    <a href="${window.location.origin}/monitor/display/${vendorId}/${urlSlug}" 
                       target="_blank" class="button button-small">
                        <i class="dashicons dashicons-external"></i>
                        Apri Monitor
                    </a>
                `;
            } else {
                urlColumn.innerHTML = '<span class="description">Non configurato</span>';
            }
        }
        
        // Update original data for filtering
        const originalItem = this.originalData.find(item => item.vendorId === vendorId);
        if (originalItem) {
            originalItem.enabled = enabled;
            originalItem.active = false;
            originalItem.element = row.cloneNode(true);
        }
    }

    generateUrlSlug(shopName, vendorId) {
        return shopName.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') + '-' + vendorId;
    }

    updateStatsCards() {
        const rows = this.tableBody.querySelectorAll('tr');
        const stats = {
            total: rows.length,
            enabled: 0,
            active: 0,
            inactive: 0
        };
        
        rows.forEach(row => {
            const enabled = row.dataset.enabled === '1';
            const active = row.dataset.active === '1';
            
            if (enabled) {
                stats.enabled++;
                if (active) {
                    stats.active++;
                } else {
                    stats.inactive++;
                }
            }
        });
        
        // Update stat cards
        const statCards = document.querySelectorAll('.monitor-stat-card');
        if (statCards.length >= 4) {
            statCards[0].querySelector('.stat-number').textContent = stats.total;
            statCards[1].querySelector('.stat-number').textContent = stats.enabled;
            statCards[2].querySelector('.stat-number').textContent = stats.active;
            statCards[3].querySelector('.stat-number').textContent = stats.inactive;
        }
    }

    handleSearch() {
        this.applyFilters();
    }

    handleFilter() {
        this.applyFilters();
    }

    applyFilters() {
        const searchTerm = this.searchInput ? this.searchInput.value.toLowerCase().trim() : '';
        const statusFilter = this.statusFilter ? this.statusFilter.value : '';
        
        this.filteredData = this.originalData.filter(item => {
            // Search filter
            if (searchTerm) {
                const matchesSearch = item.shopName.includes(searchTerm) || 
                                    item.email.includes(searchTerm) || 
                                    item.city.includes(searchTerm) ||
                                    item.vendorId.includes(searchTerm);
                if (!matchesSearch) return false;
            }
            
            // Status filter
            if (statusFilter) {
                switch (statusFilter) {
                    case 'enabled':
                        return item.enabled;
                    case 'disabled':
                        return !item.enabled;
                    case 'active':
                        return item.enabled && item.active;
                    case 'inactive':
                        return item.enabled && !item.active;
                }
            }
            
            return true;
        });
        
        this.renderFilteredTable();
    }

    renderFilteredTable() {
        if (!this.tableBody) return;
        
        // Clear current table
        this.tableBody.innerHTML = '';
        
        // Add filtered rows
        this.filteredData.forEach(item => {
            const row = item.element.cloneNode(true);
            
            // Re-bind toggle event for cloned element
            const toggle = row.querySelector('.monitor-toggle-checkbox');
            if (toggle) {
                toggle.addEventListener('change', (e) => {
                    this.handleToggle(e.target);
                });
            }
            
            this.tableBody.appendChild(row);
        });
        
        // Update select all checkbox
        if (this.selectAllCheckbox) {
            this.selectAllCheckbox.checked = false;
        }
        
        // Show message if no results
        if (this.filteredData.length === 0) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.innerHTML = `
                <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                    <i class="dashicons dashicons-search" style="font-size: 48px; margin-bottom: 10px;"></i>
                    <br>Nessun vendor trovato con i filtri applicati.
                </td>
            `;
            this.tableBody.appendChild(noResultsRow);
        }
    }

    resetFilters() {
        if (this.searchInput) this.searchInput.value = '';
        if (this.statusFilter) this.statusFilter.value = '';
        
        this.filteredData = [...this.originalData];
        this.renderFilteredTable();
    }

    refreshData() {
        window.location.reload();
    }

    toggleSelectAll(checked) {
        const checkboxes = this.tableBody.querySelectorAll('input[name="vendor_ids[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
        });
    }

    async handleBulkAction(e) {
        e.preventDefault();
        
        const formData = new FormData(this.form);
        const bulkAction = formData.get('bulk_action');
        const vendorIds = formData.getAll('vendor_ids[]');
        
        if (bulkAction === '-1') {
            this.showMessage('Seleziona un\'azione da eseguire.', 'error');
            return;
        }
        
        if (vendorIds.length === 0) {
            this.showMessage('Seleziona almeno un vendor.', 'error');
            return;
        }
        
        const actionText = bulkAction === 'enable' ? 'abilitare' : 'disabilitare';
        if (!confirm(`Sei sicuro di voler ${actionText} ${vendorIds.length} vendor?`)) {
            return;
        }
        
        try {
            this.showLoading(true);
            
            // Submit form normally for bulk actions
            this.form.submit();
        } catch (error) {
            console.error('Error with bulk action:', error);
            this.showMessage('Errore durante l\'operazione: ' + error.message, 'error');
            this.showLoading(false);
        }
    }

    bindSortingEvents() {
        const sortableHeaders = this.table ? this.table.querySelectorAll('.sortable') : [];
        
        sortableHeaders.forEach(header => {
            header.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleSort(header);
            });
        });
    }

    handleSort(header) {
        const sortBy = header.dataset.sort;
        const currentOrder = header.classList.contains('asc') ? 'desc' : 'asc';
        
        // Remove sorting classes from all headers
        const allHeaders = this.table.querySelectorAll('.sortable');
        allHeaders.forEach(h => h.classList.remove('asc', 'desc'));
        
        // Add sorting class to current header
        header.classList.add(currentOrder);
        
        // Sort filtered data
        this.filteredData.sort((a, b) => {
            let aVal, bVal;
            
            switch (sortBy) {
                case 'id':
                    aVal = parseInt(a.vendorId);
                    bVal = parseInt(b.vendorId);
                    break;
                case 'name':
                    aVal = a.shopName;
                    bVal = b.shopName;
                    break;
                case 'email':
                    aVal = a.email;
                    bVal = b.email;
                    break;
                case 'last_access':
                    const aAccess = a.element.querySelector('.last-access').textContent.trim();
                    const bAccess = b.element.querySelector('.last-access').textContent.trim();
                    aVal = aAccess === 'Mai' ? 0 : new Date(aAccess.split(' ').reverse().join('-'));
                    bVal = bAccess === 'Mai' ? 0 : new Date(bAccess.split(' ').reverse().join('-'));
                    break;
                default:
                    return 0;
            }
            
            if (currentOrder === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });
        
        this.renderFilteredTable();
    }

    showLoading(show) {
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = show ? 'flex' : 'none';
        }
        this.isLoading = show;
    }

    showMessage(message, type = 'info') {
        // Create notice element
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `
            <p>${message}</p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        `;
        
        // Insert after the header
        const header = document.querySelector('.wp-header-end');
        if (header) {
            header.parentNode.insertBefore(notice, header.nextSibling);
        }
        
        // Add dismiss functionality
        const dismissBtn = notice.querySelector('.notice-dismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                notice.remove();
            });
        }
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.remove();
            }
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (window.monitor_admin_ajax) {
        window.monitorAdmin = new MonitorAdmin();
    }
});