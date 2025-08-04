/**
 * Monitor Vendor JavaScript
 * Handles AJAX interactions for vendor monitor selection page
 */

const MonitorVendor = {
    currentPage: 1,
    isLoading: false,
    searchTimeout: null,

    init: function() {
        this.bindEvents();
        this.loadDefunti();
    },

    bindEvents: function() {
        const self = this;

        // Search functionality
        $('#defunto-search').on('input', function() {
            clearTimeout(self.searchTimeout);
            self.searchTimeout = setTimeout(function() {
                self.currentPage = 1;
                self.loadDefunti();
            }, 500);
        });

        $('#search-btn').on('click', function() {
            self.currentPage = 1;
            self.loadDefunti();
        });

        // Refresh button
        $('#refresh-list').on('click', function() {
            self.currentPage = 1;
            self.loadDefunti();
        });

        // Delegation for dynamically loaded content
        $(document).on('click', '.btn-associate', function(e) {
            e.preventDefault();
            const postId = $(this).data('post-id');
            const postTitle = $(this).data('post-title');
            self.showAssociateConfirm(postId, postTitle);
        });

        $(document).on('click', '.btn-remove-association', function(e) {
            e.preventDefault();
            self.showRemoveConfirm();
        });

        // Modal confirmation
        $('#confirmModalAction').on('click', function() {
            const action = $(this).data('action');
            const postId = $(this).data('post-id');
            
            if (action === 'associate') {
                self.associateDefunto(postId);
            } else if (action === 'remove') {
                self.removeAssociation();
            }
        });

        // Pagination
        $(document).on('click', '.page-link', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page && page !== self.currentPage) {
                self.currentPage = page;
                self.loadDefunti();
            }
        });

        // Enter key in search
        $('#defunto-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                self.currentPage = 1;
                self.loadDefunti();
            }
        });
    },

    loadDefunti: function() {
        if (this.isLoading) return;

        this.isLoading = true;
        const search = $('#defunto-search').val();

        // Show loading
        $('#defunti-loading').show();
        $('#defunti-table-container').hide();
        $('#defunti-empty').hide();
        $('#defunti-pagination').hide();

        $.ajax({
            url: monitor_vendor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'monitor_get_defunti',
                nonce: monitor_vendor_ajax.nonce,
                page: this.currentPage,
                search: search
            },
            success: (response) => {
                this.isLoading = false;
                $('#defunti-loading').hide();

                if (response.success && response.data.defunti.length > 0) {
                    this.renderDefunti(response.data.defunti);
                    this.renderPagination(response.data.current_page, response.data.total_pages);
                    $('#defunti-table-container').show();
                    
                    if (response.data.total_pages > 1) {
                        $('#defunti-pagination').show();
                    }
                } else {
                    $('#defunti-empty').show();
                }
            },
            error: (xhr) => {
                this.isLoading = false;
                $('#defunti-loading').hide();
                $('#defunti-empty').show();
                
                console.error('Error loading defunti:', xhr);
                this.showMessage('Errore nel caricamento dei dati. Riprova.', 'danger');
            }
        });
    },

    renderDefunti: function(defunti) {
        const tbody = $('#defunti-tbody');
        tbody.empty();

        defunti.forEach(defunto => {
            const row = this.createDefuntoRow(defunto);
            tbody.append(row);
        });
    },

    createDefuntoRow: function(defunto) {
        const fotoHtml = defunto.foto ? 
            `<img src="${defunto.foto}" alt="${defunto.title}" class="defunto-foto">` :
            `<div class="defunto-foto bg-light d-flex align-items-center justify-content-center">
                <i class="dashicons dashicons-admin-users text-muted"></i>
            </div>`;

        const statusHtml = defunto.is_associated ?
            `<span class="status-badge active">ATTIVO</span>` :
            `<span class="status-badge inactive">INATTIVO</span>`;

        const actionHtml = defunto.is_associated ?
            `<button type="button" class="btn btn-sm btn-outline-danger btn-remove-association">
                <i class="dashicons dashicons-no"></i> Rimuovi
            </button>` :
            `<button type="button" class="btn btn-sm btn-primary btn-associate" 
                     data-post-id="${defunto.id}" data-post-title="${defunto.title}">
                <i class="dashicons dashicons-yes"></i> Associa
            </button>`;

        return `
            <tr ${defunto.is_associated ? 'class="table-success"' : ''}>
                <td>${fotoHtml}</td>
                <td>
                    <strong>${defunto.title}</strong>
                    ${defunto.is_associated ? '<br><small class="text-success"><i class="dashicons dashicons-yes"></i> Attualmente sul monitor</small>' : ''}
                </td>
                <td>${defunto.data_morte}</td>
                <td>${defunto.data_pubblicazione}</td>
                <td class="text-center">${statusHtml}</td>
                <td class="text-center">${actionHtml}</td>
            </tr>
        `;
    },

    renderPagination: function(currentPage, totalPages) {
        if (totalPages <= 1) return;

        const pagination = $('#defunti-pagination ul');
        pagination.empty();

        // Previous button
        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        pagination.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">
                    <i class="dashicons dashicons-arrow-left-alt2"></i> Precedente
                </a>
            </li>
        `);

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            pagination.append(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
            if (startPage > 2) {
                pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === currentPage ? 'active' : '';
            pagination.append(`
                <li class="page-item ${activeClass}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            pagination.append(`<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`);
        }

        // Next button
        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        pagination.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">
                    Successiva <i class="dashicons dashicons-arrow-right-alt2"></i>
                </a>
            </li>
        `);
    },

    showAssociateConfirm: function(postId, postTitle) {
        $('#confirmModalTitle').text('Conferma Associazione');
        $('#confirmModalBody').html(`
            <p>Sei sicuro di voler associare <strong>"${postTitle}"</strong> al monitor digitale?</p>
            <div class="alert alert-info">
                <i class="dashicons dashicons-info"></i>
                Il defunto verrà mostrato immediatamente sul monitor e sostituirà quello attualmente visualizzato (se presente).
            </div>
        `);
        
        $('#confirmModalAction')
            .text('Associa')
            .removeClass('btn-danger')
            .addClass('btn-primary')
            .data('action', 'associate')
            .data('post-id', postId);
        
        $('#confirmModal').modal('show');
    },

    showRemoveConfirm: function() {
        $('#confirmModalTitle').text('Conferma Rimozione');
        $('#confirmModalBody').html(`
            <p>Sei sicuro di voler rimuovere l'associazione corrente?</p>
            <div class="alert alert-warning">
                <i class="dashicons dashicons-warning"></i>
                Il monitor diventerà inattivo e non mostrerà più alcun defunto fino alla prossima associazione.
            </div>
        `);
        
        $('#confirmModalAction')
            .text('Rimuovi')
            .removeClass('btn-primary')
            .addClass('btn-danger')
            .data('action', 'remove')
            .removeData('post-id');
        
        $('#confirmModal').modal('show');
    },

    associateDefunto: function(postId) {
        $('#confirmModal').modal('hide');
        this.showMessage('Associazione in corso...', 'info');

        $.ajax({
            url: monitor_vendor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'monitor_associate_defunto',
                nonce: monitor_vendor_ajax.nonce,
                post_id: postId
            },
            success: (response) => {
                if (response.success) {
                    this.showMessage(response.data.message, 'success');
                    this.loadDefunti(); // Reload to update status
                    
                    // Update header status
                    setTimeout(() => {
                        location.reload(); // Refresh to update monitor status in header
                    }, 1500);
                } else {
                    this.showMessage('Errore: ' + response.data, 'danger');
                }
            },
            error: (xhr) => {
                console.error('Error associating defunto:', xhr);
                this.showMessage('Errore di connessione. Riprova.', 'danger');
            }
        });
    },

    removeAssociation: function() {
        $('#confirmModal').modal('hide');
        this.showMessage('Rimozione in corso...', 'info');

        $.ajax({
            url: monitor_vendor_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'monitor_remove_association',
                nonce: monitor_vendor_ajax.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.showMessage(response.data.message, 'success');
                    this.loadDefunti(); // Reload to update status
                    
                    // Update header status
                    setTimeout(() => {
                        location.reload(); // Refresh to update monitor status in header
                    }, 1500);
                } else {
                    this.showMessage('Errore: ' + response.data, 'danger');
                }
            },
            error: (xhr) => {
                console.error('Error removing association:', xhr);
                this.showMessage('Errore di connessione. Riprova.', 'danger');
            }
        });
    },

    showMessage: function(message, type = 'info') {
        // Remove existing messages
        $('#monitor-messages .alert').remove();
        
        const alertClass = `alert-${type}`;
        const iconClass = type === 'success' ? 'dashicons-yes' : 
                         type === 'danger' ? 'dashicons-no' : 'dashicons-info';
        
        const messageHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="dashicons ${iconClass}"></i> ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        
        $('#monitor-messages').append(messageHtml);
        
        // Auto dismiss after 5 seconds for success/info messages
        if (type === 'success' || type === 'info') {
            setTimeout(() => {
                $('#monitor-messages .alert').fadeOut(() => {
                    $(this).remove();
                });
            }, 5000);
        }
    }
};

// Initialize when document is ready
jQuery(document).ready(function($) {
    if (typeof monitor_vendor_ajax !== 'undefined') {
        MonitorVendor.init();
    }
});