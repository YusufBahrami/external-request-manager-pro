/**
 * External Request Manager Pro - Admin JavaScript
 */

(function($) {
    'use strict';

    const ERM = {
        init: function() {
            this.bindEvents();
            this.setupCheckboxes();
        },

        bindEvents: function() {
            $(document).on('change', '#erm-select-all', this.toggleSelectAll.bind(this));

            $(document).on('change', '.erm-request-checkbox', this.updateSelectAll.bind(this));

            $(document).on('click', '#erm-apply-bulk-action', this.applyBulkAction.bind(this));

            $(document).on('click', '.erm-toggle-block-btn', this.toggleBlock.bind(this));

            $(document).on('click', '.erm-delete-btn', this.deleteItem.bind(this));

            $(document).on('click', '.erm-review-btn', this.showDetail.bind(this));

            $(document).on('click', '#erm-clear-all-btn', this.showClearModal.bind(this));
            $(document).on('click', '#erm-confirm-clear-btn', this.clearLogs.bind(this));

            $(document).on('click', '.erm-modal-close, .erm-modal-close-btn', this.closeModal.bind(this));
            $(document).on('click', '.erm-modal', this.closeModalOnBackdrop.bind(this));
        },

        setupCheckboxes: function() {
            this.updateSelectAll();
        },

        toggleSelectAll: function(e) {
            const checked = $(e.target).prop('checked');
            $('.erm-request-checkbox').prop('checked', checked);
        },

        updateSelectAll: function() {
            const total = $('.erm-request-checkbox').length;
            const checked = $('.erm-request-checkbox:checked').length;
            $('#erm-select-all').prop('checked', total > 0 && total === checked);
        },

        applyBulkAction: function(e) {
            e.preventDefault();

            const action = $('#erm-bulk-action-select').val();
            const ids = $('[name="erm_ids[]"]:checked').map(function() {
                return $(this).val();
            }).get();

            if (!action || action === '') {
                alert('Please select an action');
                return;
            }

            if (ids.length === 0) {
                alert('Please select at least one item');
                return;
            }

            if (action === 'delete' && !confirm(ermProData.messages.confirmDelete)) {
                return;
            }

            this.showLoading(true);

            $.post(ermProData.ajaxUrl, {
                action: 'erm_bulk_action',
                nonce: ermProData.nonce,
                bulk_action: action,
                ids: ids
            }, (response) => {
                this.showLoading(false);

                if (response.success) {
                    alert(response.data.message || 'Action completed');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },

        toggleBlock: function(e) {
            e.preventDefault();
            const btn = $(e.target).closest('button');
            const id = btn.data('id');
            const isBlocked = btn.data('blocked');

            if (!id) return;

            this.showLoading(true);

            $.post(ermProData.ajaxUrl, {
                action: 'erm_toggle_block',
                nonce: ermProData.nonce,
                id: id
            }, (response) => {
                this.showLoading(false);

                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },

        deleteItem: function(e) {
            e.preventDefault();

            if (!confirm(ermProData.messages.confirmDelete)) {
                return;
            }

            const btn = $(e.target).closest('button');
            const id = btn.data('id');

            if (!id) return;

            this.showLoading(true);

            $.post(ermProData.ajaxUrl, {
                action: 'erm_bulk_action',
                nonce: ermProData.nonce,
                action: 'delete',
                ids: [id]
            }, (response) => {
                this.showLoading(false);

                if (response.success) {
                    btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },

        showDetail: function(e) {
            e.preventDefault();

            const btn = $(e.target).closest('button');
            const id = btn.data('id');

            if (!id) return;

            this.showLoading(true);

            $.post(ermProData.ajaxUrl, {
                action: 'erm_get_detail',
                nonce: ermProData.nonce,
                id: id
            }, (response) => {
                this.showLoading(false);

                if (response.success) {
                    this.renderDetailModal(response.data);
                    this.openModal('#erm-detail-modal');
                } else {
                    alert(response.data.message || 'Error loading details');
                }
            });
        },

        renderDetailModal: function(data) {
            const html = `
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Host:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.host)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">URL:</div>
                    <div class="erm-detail-value erm-detail-code">${this.escapeHtml(data.url)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Method:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.method)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Source:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.source)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Request Count:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.count)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Status:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.status)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">First Seen:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.first_request)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Last Seen:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.last_request)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Source File:</div>
                    <div class="erm-detail-value erm-detail-code">${this.escapeHtml(data.source_file)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Request Size:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.request_size)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Response Code:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.response_code)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">Response Time:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.response_time)}</div>
                </div>
            `;

            $('#erm-detail-body').html(html);
            
            // Setup detail actions section
            $('#erm-detail-actions').show();
            $('#erm-rate-interval').val(data.rate_limit_interval);
            
            // Update block button text
            const blockBtn = $('#erm-modal-toggle-block');
            blockBtn.text(data.is_blocked ? 'Unblock' : 'Block')
                   .toggleClass('button-link-delete', !data.is_blocked);
            
            // Setup URLs dropdown if available
            if (data.urls_list && data.urls_list.length > 0) {
                $('#erm-urls-section').show();
                const dropdown = $('#erm-urls-dropdown');
                dropdown.empty().append('<option value="">Select a logged URL</option>');
                
                data.urls_list.forEach((url, index) => {
                    dropdown.append(`<option value="${index}">${this.escapeHtml(url.substring(0, 80))}</option>`);
                });
                
                // Handle URL selection
                dropdown.off('change').on('change', (e) => {
                    const selectedIndex = $(e.target).val();
                    if (selectedIndex !== '') {
                        $('#erm-selected-url-display').text(data.urls_list[selectedIndex]).show();
                    } else {
                        $('#erm-selected-url-display').hide();
                    }
                });
            } else {
                $('#erm-urls-section').hide();
            }
            
            // Store ID for actions
            $('#erm-save-rate-limit').off('click').on('click', (e) => {
                e.preventDefault();
                this.saveRateLimit(data.id);
            });
            
            $('#erm-modal-toggle-block').off('click').on('click', (e) => {
                e.preventDefault();
                this.toggleBlockFromModal(data.id, data.is_blocked);
            });
            
            $('#erm-modal-delete').off('click').on('click', (e) => {
                e.preventDefault();
                if (confirm(ermProData.messages.confirmDelete)) {
                    this.deleteFromModal(data.id);
                }
            });
        },

        showClearModal: function(e) {
            e.preventDefault();
            this.openModal('#erm-clear-modal');
        },

        clearLogs: function(e) {
            e.preventDefault();

            const mode = $('input[name="erm_clear_mode"]:checked').val();

            if (!mode) {
                alert('Please select a clear mode');
                return;
            }

            this.showLoading(true);

            $.post(ermProData.ajaxUrl, {
                action: 'erm_clear_logs',
                nonce: ermProData.nonce,
                mode: mode
            }, (response) => {
                this.showLoading(false);

                if (response.success) {
                    alert(response.data.message || 'Logs cleared');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },

        saveRateLimit: function(id) {
            const interval = parseInt($('#erm-rate-interval').val()) || 0;

            if (id <= 0) {
                alert('Invalid request ID');
                return;
            }

            $.post(ermProData.ajaxUrl, {
                action: 'erm_update_rate_limit',
                nonce: ermProData.nonce,
                id: id,
                interval: interval,
                calls: 1
            }, (response) => {
                if (response.success) {
                    alert(response.data.message || 'Rate limit saved');
                } else {
                    alert(response.data.message || 'Error saving rate limit');
                }
            });
        },

        toggleBlockFromModal: function(id, isCurrentlyBlocked) {
            $.post(ermProData.ajaxUrl, {
                action: 'erm_toggle_block',
                nonce: ermProData.nonce,
                id: id
            }, (response) => {
                if (response.success) {
                    alert(response.data.message || 'Status updated');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },

        deleteFromModal: function(id) {
            $.post(ermProData.ajaxUrl, {
                action: 'erm_bulk_action',
                nonce: ermProData.nonce,
                bulk_action: 'delete',
                ids: [id]
            }, (response) => {
                if (response.success) {
                    alert(response.data.message || 'Item deleted');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },

        openModal: function(selector) {
            $(selector).removeClass('hidden');
            $('body').css('overflow', 'hidden');
        },

        closeModal: function(e) {
            const modal = $(e.target).closest('.erm-modal');
            modal.addClass('hidden');
            $('body').css('overflow', 'auto');
        },

        closeModalOnBackdrop: function(e) {
            if (e.target !== this) return;
            $(this).addClass('hidden');
            $('body').css('overflow', 'auto');
        },

        showLoading: function(show) {
            if (show) {
                $('#erm-bulk-form').addClass('erm-loading');
            } else {
                $('#erm-bulk-form').removeClass('erm-loading');
            }
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, (m) => map[m]);
        }
    };

    $(document).ready(function() {
        ERM.init();
    });

})(jQuery);
