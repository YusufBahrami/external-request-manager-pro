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
            const self = this;
            
            $(document).on('change', '#erm-select-all', this.toggleSelectAll.bind(this));

            $(document).on('change', '.erm-request-checkbox', this.updateSelectAll.bind(this));

            $(document).on('click', '#erm-apply-bulk-action', this.applyBulkAction.bind(this));

            $(document).on('click', '.erm-toggle-block-btn', this.toggleBlock.bind(this));

            $(document).on('click', '.erm-remove-rate-limit-btn', this.removeRateLimit.bind(this));

            $(document).on('click', '.erm-delete-btn', this.deleteItem.bind(this));

            $(document).on('click', '.erm-review-btn', this.showDetail.bind(this));

            $(document).on('click', '#erm-clear-all-btn', this.showClearModal.bind(this));
            $(document).on('click', '#erm-confirm-clear-btn', this.clearLogs.bind(this));
            
            // Settings page clear buttons
            $(document).on('click', '#erm-settings-clear-except-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.showSettingsConfirmModal(e, 'except_blocked');
                return false;
            });

                // Run DB Updater
                $(document).on('click', '#erm-run-db-upgrade', function(e) {
                    e.preventDefault();
                    const btn = $(this);
                    btn.prop('disabled', true);
                    $('#erm-db-upgrade-status').text('Running...');

                    $.post(ermProData.ajaxUrl, {
                        action: 'erm_run_db_upgrade',
                        nonce: ermProData.nonce
                    }, (response) => {
                        if (response.success) {
                            $('#erm-db-upgrade-status').text('Done');
                            alert(response.data.message || 'DB upgraded');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            btn.prop('disabled', false);
                            $('#erm-db-upgrade-status').text('Failed');
                            alert(response.data.message || 'Upgrade failed');
                        }
                    }).fail(() => {
                        btn.prop('disabled', false);
                        $('#erm-db-upgrade-status').text('Error');
                        alert('AJAX Error');
                    });
                });
            
            $(document).on('click', '#erm-settings-clear-all-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.showSettingsConfirmModal(e, 'all');
                return false;
            });
            
            $(document).on('click', '#erm-settings-confirm-action', this.confirmSettingsClear.bind(this));
            $(document).on('click', '[data-action="cancel"], [data-action="close"]', this.closeSettingsModal.bind(this));

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
                    // Update counts if provided
                    if (response.data.counts) {
                        this.updateStats(response.data.counts);
                    }
                    
                    alert(response.data.message || 'Action completed');
                    
                    // Reload to refresh the request list
                    setTimeout(() => {
                        location.reload();
                    }, 500);
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
                    // Update counts if provided
                    if (response.data.counts) {
                        this.updateStats(response.data.counts);
                    }
                    
                    setTimeout(() => {
                        location.reload();
                    }, 300);
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },

        removeRateLimit: function(e) {
            e.preventDefault();
            const btn = $(e.target).closest('button');
            const id = btn.data('id');

            if (!id) return;

            this.showLoading(true);

            $.post(ermProData.ajaxUrl, {
                action: 'erm_update_rate_limit',
                nonce: ermProData.nonce,
                id: id,
                rate_limit: 0
            }, (response) => {
                this.showLoading(false);

                if (response.success) {
                    // Update counts if provided
                    if (response.data.counts) {
                        this.updateStats(response.data.counts);
                    }
                    
                    setTimeout(() => {
                        location.reload();
                    }, 300);
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
            const hasRateLimit = btn.data('has-rate-limit');
            const isBlocked = btn.data('is-blocked');

            if (!id) return;

            this.showLoading(true);

            // Helper function to perform delete after cleanup
            const performDelete = () => {
                $.post(ermProData.ajaxUrl, {
                    action: 'erm_bulk_action',
                    nonce: ermProData.nonce,
                    bulk_action: 'delete',
                    ids: [id]
                }, (response) => {
                    this.showLoading(false);

                    if (response.success) {
                        // Update counts if provided
                        if (response.data.counts) {
                            this.updateStats(response.data.counts);
                        }
                        
                        btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Error occurred');
                    }
                });
            };

            // If has rate limit, remove it first
            if (hasRateLimit) {
                $.post(ermProData.ajaxUrl, {
                    action: 'erm_update_rate_limit',
                    nonce: ermProData.nonce,
                    id: id,
                    rate_limit: 0
                }, () => {
                    // Then unblock if needed
                    if (isBlocked) {
                        $.post(ermProData.ajaxUrl, {
                            action: 'erm_toggle_block',
                            nonce: ermProData.nonce,
                            id: id
                        }, performDelete);
                    } else {
                        performDelete();
                    }
                });
            } else if (isBlocked) {
                // Just unblock then delete
                $.post(ermProData.ajaxUrl, {
                    action: 'erm_toggle_block',
                    nonce: ermProData.nonce,
                    id: id
                }, performDelete);
            } else {
                // Just delete
                performDelete();
            }
        },

        showDetail: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const btn = $(e.target).closest('button');
            const id = btn.data('id');

            if (!id) {
                alert('Error: Invalid request ID');
                return;
            }

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
            }).fail((xhr, status, error) => {
                this.showLoading(false);
                alert('AJAX Error: ' + (error || 'Unknown error'));
            });
        },

        renderDetailModal: function(data) {
            const self = this;
            
            // Build HTML piece by piece for clarity
            let html = '';
            
            // Basic info
            html += `<div class="erm-detail-item">
                    <div class="erm-detail-label">Host:</div>
                    <div class="erm-detail-value">${this.escapeHtml(data.host)}</div>
                </div>
                <div class="erm-detail-item">
                    <div class="erm-detail-label">URL:</div>
                    <div class="erm-detail-value erm-detail-code">${this.escapeHtml(data.url)}</div>
                </div>`;
            
            // Logged URLs section (if enabled and URLs exist)
            if (data.track_all_urls && data.urls_list && data.urls_list.length > 0) {
                let urlsList = '';
                
                data.urls_list.forEach(function(url, idx) {
                    const displayUrl = self.escapeHtml(url);
                    urlsList += `<div class="erm-logged-url" style="padding:6px 0; border-bottom:1px solid #eee; font-size:12px; word-break:break-all;">
                        <span style="color:#666;">${idx + 1}.</span> <code style="background:#f5f5f5; padding:2px 4px; border-radius:2px;">${displayUrl}</code>
                    </div>`;
                });
                
                html += `<div class="erm-detail-item">
                        <div class="erm-detail-label">Logged URLs (${data.urls_list.length}):</div>
                        <div class="erm-detail-value" id="erm-urls-container" style="max-height:200px; overflow-y:auto;">
                            ${urlsList}
                        </div>
                    </div>`;
            } else if (data.track_all_urls) {
                // No URLs available
                html += `<div class="erm-detail-item">
                        <div class="erm-detail-label">Logged URLs:</div>
                        <div class="erm-detail-value" style="color:#999;">no url is available</div>
                    </div>`;
            }
            
            // Rest of the details
            html += `<div class="erm-detail-item">
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
                </div>`;

            if (data.response_data && data.response_data.length > 0) {
                const resp = this.escapeHtml(data.response_data);
                html += `<div class="erm-detail-item">
                    <div class="erm-detail-label">Response Data:</div>
                    <div class="erm-detail-value erm-detail-code" style="max-height:200px; overflow:auto; white-space:pre-wrap;"><pre style="margin:0;">${resp}</pre></div>
                    <div style="margin-top:6px;"><button id="erm-download-response" class="button">Download Full Response</button></div>
                    </div>`;
            }

            $('#erm-detail-body').html(html);

            // Bind download handler for response body (if present)
            if (data.response_data && data.response_data.length > 0) {
                $('#erm-download-response').off('click').on('click', function(e) {
                    e.preventDefault();
                    try {
                        const content = data.response_data;
                        const blob = new Blob([content], { type: 'application/json' });
                        const filename = (data.host ? data.host.replace(/[^a-z0-9]/gi, '_') : 'response') + '_' + (data.id || Date.now()) + '.json';
                        if (window.navigator && window.navigator.msSaveOrOpenBlob) {
                            window.navigator.msSaveOrOpenBlob(blob, filename);
                        } else {
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            URL.revokeObjectURL(url);
                        }
                    } catch (ex) {
                        alert('Error preparing download');
                    }
                });
            }
            
            // Setup detail actions section
            $('#erm-detail-actions').show();
            $('#erm-rate-interval').val(data.rate_limit_interval);
            
            // Update block button text based on state
            const blockBtn = $('#erm-modal-toggle-block');
            let btnText = 'Block';
            let btnDelete = true;
            
            if (data.rate_limit_interval > 0) {
                // Rate limit is set - show "Remove Rate Limit"
                btnText = 'Remove Rate Limit';
                btnDelete = false;
            } else if (data.is_blocked) {
                // Blocked without rate limit - show "Unblock"
                btnText = 'Unblock';
                btnDelete = true;
            }
            
            blockBtn.text(btnText).toggleClass('button-link-delete', btnDelete);
            
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
        
        showSettingsConfirmModal: function(e, mode) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            if (typeof mode === 'object') {
                // Called via bind - mode is first parameter passed to bind
                mode = arguments[1]; // This is the mode parameter from bind
            }
            
            const messages = {
                'except_blocked': {
                    title: 'Clear All Except Blocked?',
                    message: 'This will delete all allowed logs while keeping blocked entries. This action cannot be undone.',
                    button: 'Clear Allowed Logs'
                },
                'all': {
                    title: 'Clear ALL Logs?',
                    message: 'This will permanently delete ALL logs including blocked entries and unblock them. This action cannot be undone.',
                    button: 'Clear All Logs'
                }
            };
            
            const config = messages[mode] || messages['except_blocked'];
            
            // Store mode in a data attribute
            $('#erm-settings-confirm-action').data('clear-mode', mode);
            
            // Update modal content
            let messageHtml = '<p style="font-size: 16px; line-height: 1.6;">';
            messageHtml += config.message;
            messageHtml += '</p>';
            
            $('#erm-settings-confirm-message').html(messageHtml);
            $('#erm-settings-confirm-action').text(config.button);
            
            // Show modal
            this.openModal('#erm-settings-confirm-modal');
        },
        
        confirmSettingsClear: function(e) {
            e.preventDefault();
            
            const mode = $(e.target).data('clear-mode') || 'except_blocked';
            
            this.showLoading(true);
            const self = this;
            
            const nonce = (typeof ermProData !== 'undefined' && ermProData.nonce) ? ermProData.nonce : '';
            const ajaxUrl = (typeof ermProData !== 'undefined' && ermProData.ajaxUrl) ? ermProData.ajaxUrl : ajaxurl;
            
            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    action: 'erm_clear_logs',
                    nonce: nonce,
                    mode: mode
                },
                success: function(response) {
                    self.showLoading(false);
                    
                    if (response.success) {
                        self.closeSettingsModal();
                        // Show success message briefly before reloading
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        alert(response.data.message || 'Error occurred');
                    }
                },
                error: function(xhr, status, error) {
                    self.showLoading(false);
                    alert('Error: ' + (error || 'Unknown error'));
                }
            });
        },
        
        closeSettingsModal: function(e) {
            if (e) {
                e.preventDefault();
            }
            $('#erm-settings-confirm-modal').addClass('hidden');
        },
        
        updateSettingsStats: function(counts) {
            // Update the database info on settings page
            const text = 'Total requests logged: ' + this.formatNumber(counts.total);
            $('.erm-maintenance-box').find('p').first().text(text);
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
                    // Update counts if provided
                    if (response.data.counts) {
                        this.updateStats(response.data.counts);
                    }
                    
                    alert(response.data.message || 'Logs cleared');
                    
                    // Reload to refresh the request list
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert(response.data.message || 'Error occurred');
                }
            });
        },
        
        updateStats: function(counts) {
            // Update the statistics cards
            $('.erm-stat-number').eq(0).text(this.formatNumber(counts.total));
            $('.erm-stat-blocked .erm-stat-number').text(this.formatNumber(counts.blocked));
            $('.erm-stat-allowed .erm-stat-number').text(this.formatNumber(counts.allowed));
            
            // Update filter tabs
            $('a:contains("All")').find('.count').text(counts.total);
            $('a:contains("Blocked")').find('.count').text(counts.blocked);
            $('a:contains("Allowed")').find('.count').text(counts.allowed);
        },
        
        formatNumber: function(num) {
            return Number(num).toLocaleString();
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

        toggleBlockFromModal: function(id, isCurrentlyBlocked, rateLimit) {
            // If rate limit is active, prompt to remove it
            const rateInterval = parseInt($('#erm-rate-interval').val()) || 0;
            
            if (rateInterval > 0) {
                // Has rate limit - remove it
                if (confirm('Remove Rate Limit for this host?')) {
                    $.post(ermProData.ajaxUrl, {
                        action: 'erm_update_rate_limit',
                        nonce: ermProData.nonce,
                        id: id,
                        interval: 0,
                        calls: 0
                    }, (response) => {
                        if (response.success) {
                            alert('Rate limit removed');
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        } else {
                            alert(response.data.message || 'Error removing rate limit');
                        }
                    });
                }
            } else {
                // No rate limit - toggle block status
                $.post(ermProData.ajaxUrl, {
                    action: 'erm_toggle_block',
                    nonce: ermProData.nonce,
                    id: id
                }, (response) => {
                    if (response.success) {
                        // Update counts if provided
                        if (response.data.counts) {
                            this.updateStats(response.data.counts);
                        }
                        
                        alert(response.data.message || 'Status updated');
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        alert(response.data.message || 'Error occurred');
                    }
                });
            }
        },

        deleteFromModal: function(id) {
            $.post(ermProData.ajaxUrl, {
                action: 'erm_bulk_action',
                nonce: ermProData.nonce,
                bulk_action: 'delete',
                ids: [id]
            }, (response) => {
                if (response.success) {
                    // Update counts if provided
                    if (response.data.counts) {
                        this.updateStats(response.data.counts);
                    }
                    
                    alert(response.data.message || 'Item deleted');
                    setTimeout(() => {
                        location.reload();
                    }, 500);
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
            e.preventDefault();
            e.stopPropagation();
            const modal = $(e.target).closest('.erm-modal');
            modal.addClass('hidden');
            $('body').css('overflow', 'auto');
        },

        closeModalOnBackdrop: function(e) {
            // Only close if clicking on the backdrop (the modal itself), not content
            if ($(e.target).hasClass('erm-modal')) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('hidden');
                $('body').css('overflow', 'auto');
            }
        },

        showLoading: function(show) {
            if (show) {
                // For dashboard page
                const bulkForm = $('#erm-bulk-form');
                if (bulkForm.length) {
                    bulkForm.addClass('erm-loading');
                }
                
                // For settings page - add overlay
                if ($('.erm-maintenance-box').length) {
                    $('body').css('pointer-events', 'none').css('opacity', '0.6');
                }
            } else {
                // For dashboard page
                const bulkForm = $('#erm-bulk-form');
                if (bulkForm.length) {
                    bulkForm.removeClass('erm-loading');
                }
                
                // For settings page - remove overlay
                if ($('.erm-maintenance-box').length) {
                    $('body').css('pointer-events', 'auto').css('opacity', '1');
                }
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
