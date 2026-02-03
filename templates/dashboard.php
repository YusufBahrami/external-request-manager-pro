<?php
/**
 * Dashboard Template
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap erm-pro-wrap">
    <div class="erm-header">
        <h1><?php echo esc_html__('External Requests Manager', 'erm-pro'); ?></h1>
        <p class="erm-subtitle"><?php echo esc_html__('Monitor, block, and manage external HTTP requests', 'erm-pro'); ?></p>
    </div>
    
    <!-- Statistics -->
    <div class="erm-stats-grid">
        <div class="erm-stat-card">
            <div class="erm-stat-number"><?php echo number_format($counts['total']); ?></div>
            <div class="erm-stat-label"><?php echo esc_html__('Total Requests', 'erm-pro'); ?></div>
        </div>
        <div class="erm-stat-card erm-stat-blocked">
            <div class="erm-stat-number"><?php echo number_format($counts['blocked']); ?></div>
            <div class="erm-stat-label"><?php echo esc_html__('Blocked', 'erm-pro'); ?></div>
        </div>
        <div class="erm-stat-card erm-stat-allowed">
            <div class="erm-stat-number"><?php echo number_format($counts['allowed']); ?></div>
            <div class="erm-stat-label"><?php echo esc_html__('Allowed', 'erm-pro'); ?></div>
        </div>
    </div>
    
    <!-- Filters & Search -->
    <div class="erm-filters-section">
        <ul class="erm-filter-tabs">
            <li>
                <a href="<?php echo admin_url('admin.php?page=erm-pro-logs'); ?>" 
                   class="<?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <?php echo esc_html__('All', 'erm-pro'); ?> 
                    <span class="count"><?php echo $counts['total']; ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=erm-pro-logs&filter=blocked'); ?>" 
                   class="<?php echo $filter === 'blocked' ? 'active' : ''; ?>">
                    <?php echo esc_html__('Blocked', 'erm-pro'); ?> 
                    <span class="count"><?php echo $counts['blocked']; ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=erm-pro-logs&filter=allowed'); ?>" 
                   class="<?php echo $filter === 'allowed' ? 'active' : ''; ?>">
                    <?php echo esc_html__('Allowed', 'erm-pro'); ?> 
                    <span class="count"><?php echo $counts['allowed']; ?></span>
                </a>
            </li>
        </ul>
        
        <form method="get" class="erm-search-form">
            <input type="hidden" name="page" value="erm-pro-logs">
            <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
            <div class="erm-search-wrapper">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" 
                       placeholder="<?php esc_attr_e('Search host or URL...', 'erm-pro'); ?>" class="erm-search-input">
                <select name="search_by" class="erm-search-by" title="<?php esc_attr_e('Search by field', 'erm-pro'); ?>">
                    <option value="">-- <?php esc_html_e('All Fields', 'erm-pro'); ?> --</option>
                    <option value="host" <?php selected($search_by, 'host'); ?>>Host</option>
                    <option value="url" <?php selected($search_by, 'url'); ?>>URL</option>
                    <option value="plugin" <?php selected($search_by, 'plugin'); ?>>Plugin</option>
                    <option value="theme" <?php selected($search_by, 'theme'); ?>>Theme</option>
                </select>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Search', 'erm-pro'); ?></button>
            </div>
        </form>
    </div>
    
    <!-- Bulk Actions -->
    <form method="post" id="erm-bulk-form" class="erm-requests-form">
        <?php wp_nonce_field('erm_bulk_action', 'erm_nonce'); ?>
        
        <div class="erm-toolbar">
            <div class="erm-bulk-actions">
                <select name="erm_action" id="erm-bulk-action-select" class="erm-action-select">
                    <option value="">-- <?php echo esc_html__('Bulk Actions', 'erm-pro'); ?> --</option>
                    <option value="block"><?php echo esc_html__('Block Selected', 'erm-pro'); ?></option>
                    <option value="unblock"><?php echo esc_html__('Unblock Selected', 'erm-pro'); ?></option>
                    <option value="delete"><?php echo esc_html__('Delete Selected', 'erm-pro'); ?></option>
                </select>
                <button type="button" class="button button-secondary" id="erm-apply-bulk-action">
                    <?php echo esc_html__('Apply', 'erm-pro'); ?>
                </button>
            </div>
            
            <div class="erm-toolbar-right">
                <button type="button" class="button button-link-delete" id="erm-clear-all-btn">
                    <?php echo esc_html__('Clear Logs', 'erm-pro'); ?>
                </button>
            </div>
        </div>
        
        <!-- Requests Table -->
        <div class="erm-table-container">
            <table class="erm-requests-table widefat striped">
                <thead>
                    <tr>
                        <th class="erm-col-checkbox">
                            <input type="checkbox" id="erm-select-all" class="erm-checkbox-all">
                        </th>
                        <?php if (in_array('host', $display_columns)): ?>
                            <th class="erm-col-host"><?php echo esc_html__('Host', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('source', $display_columns)): ?>
                            <th class="erm-col-source"><?php echo esc_html__('Source', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('method', $display_columns)): ?>
                            <th class="erm-col-method"><?php echo esc_html__('Method', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('count', $display_columns)): ?>
                            <th class="erm-col-count"><?php echo esc_html__('Requests', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('size', $display_columns)): ?>
                            <th class="erm-col-size"><?php echo esc_html__('Size', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('status', $display_columns)): ?>
                            <th class="erm-col-status"><?php echo esc_html__('Status', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('first_request', $display_columns)): ?>
                            <th class="erm-col-first"><?php echo esc_html__('First Seen', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('last_request', $display_columns)): ?>
                            <th class="erm-col-last"><?php echo esc_html__('Last Seen', 'erm-pro'); ?></th>
                        <?php endif; ?>
                        <?php if (in_array('actions', $display_columns)): ?>
                            <th class="erm-col-actions"><?php echo esc_html__('Actions', 'erm-pro'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results['data'])): ?>
                        <tr>
                            <td colspan="99" class="erm-empty-state">
                                <div class="erm-no-data">
                                    <p><?php echo esc_html__('No requests found', 'erm-pro'); ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($results['data'] as $request): ?>
                        <tr class="erm-request-row" data-id="<?php echo esc_attr($request->id); ?>">
                            <td class="erm-col-checkbox">
                                <input type="checkbox" class="erm-request-checkbox" value="<?php echo esc_attr($request->id); ?>" name="erm_ids[]">
                            </td>
                            
                            <?php if (in_array('host', $display_columns)): ?>
                                <td class="erm-col-host">
                                    <strong><?php echo esc_html($request->host); ?></strong>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('source', $display_columns)): ?>
                                <td class="erm-col-source">
                                    <?php 
                                    if ($request->source_plugin) {
                                        echo '<span class="erm-source-badge erm-plugin">' . esc_html($request->source_plugin) . '</span>';
                                    } elseif ($request->source_theme) {
                                        echo '<span class="erm-source-badge erm-theme">' . esc_html($request->source_theme) . '</span>';
                                    } else {
                                        echo '<span class="erm-source-badge erm-core">Core</span>';
                                    }
                                    ?>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('method', $display_columns)): ?>
                                <td class="erm-col-method">
                                    <span class="erm-method-badge"><?php echo esc_html($request->request_method); ?></span>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('count', $display_columns)): ?>
                                <td class="erm-col-count">
                                    <?php echo number_format($request->request_count); ?>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('size', $display_columns)): ?>
                                <td class="erm-col-size">
                                    <?php echo esc_html($request->request_size > 0 ? erm_pro_format_bytes($request->request_size) : '-'); ?>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('status', $display_columns)): ?>
                                <td class="erm-col-status">
                                    <?php 
                                        $status_class = 'erm-allowed';
                                        $status_icon = 'dashicons-yes-alt';
                                        $status_text = __('Allowed', 'erm-pro');
                                        
                                        // Check rate limit first (highest priority)
                                        if ($request->rate_limit_interval && $request->rate_limit_interval > 0) {
                                            $status_class = 'erm-rate-limited';
                                            $status_icon = 'dashicons-clock';
                                            $status_text = __('Rate Limited', 'erm-pro');
                                        } elseif ($request->is_blocked) {
                                            $status_class = 'erm-blocked';
                                            $status_icon = 'dashicons-shield';
                                            $status_text = __('Blocked', 'erm-pro');
                                        }
                                    ?>
                                    <span class="erm-status-badge <?php echo esc_attr($status_class); ?>">
                                        <span class="dashicons <?php echo esc_attr($status_icon); ?>"></span> 
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('first_request', $display_columns)): ?>
                                <td class="erm-col-first">
                                    <small><?php echo esc_html(human_time_diff(strtotime($request->first_timestamp), current_time('timestamp')) . ' ago'); ?></small>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('last_request', $display_columns)): ?>
                                <td class="erm-col-last">
                                    <small><?php echo esc_html(human_time_diff(strtotime($request->last_timestamp), current_time('timestamp')) . ' ago'); ?></small>
                                </td>
                            <?php endif; ?>
                            
                            <?php if (in_array('actions', $display_columns)): ?>
                                <td class="erm-col-actions">
                                    <button type="button" class="button button-small erm-review-btn" data-id="<?php echo esc_attr($request->id); ?>" title="<?php esc_attr_e('Review details', 'erm-pro'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <?php if ($request->rate_limit_interval && $request->rate_limit_interval > 0): ?>
                                        <button type="button" class="button button-small erm-remove-rate-limit-btn" data-id="<?php echo esc_attr($request->id); ?>" data-has-rate-limit="1">
                                            <?php esc_html_e('Remove Rate Limit', 'erm-pro'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="button button-small erm-toggle-block-btn" data-id="<?php echo esc_attr($request->id); ?>" data-blocked="<?php echo esc_attr($request->is_blocked); ?>">
                                            <?php echo $request->is_blocked ? esc_html__('Unblock', 'erm-pro') : esc_html__('Block', 'erm-pro'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="button button-small button-link-delete erm-delete-btn" data-id="<?php echo esc_attr($request->id); ?>" data-has-rate-limit="<?php echo esc_attr($request->rate_limit_interval && $request->rate_limit_interval > 0 ? '1' : '0'); ?>" data-is-blocked="<?php echo esc_attr($request->is_blocked); ?>">
                                        <?php echo esc_html__('Delete', 'erm-pro'); ?>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php
        $total_pages = ceil($results['total'] / $per_page);
        if ($total_pages > 1):
        ?>
            <div class="erm-pagination">
                <?php
                for ($i = 1; $i <= $total_pages; $i++):
                    $url = add_query_arg('paged', $i);
                    $url = add_query_arg('filter', $filter, $url);
                    if ($search) $url = add_query_arg('s', $search, $url);
                    if ($search_by) $url = add_query_arg('search_by', $search_by, $url);
                    
                    $class = $i === $paged ? 'active' : '';
                ?>
                    <a href="<?php echo esc_url($url); ?>" class="erm-page-number <?php echo esc_attr($class); ?>">
                        <?php echo number_format($i); ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </form>
    
    <!-- Settings Link -->
    <div class="erm-footer">
        <a href="<?php echo admin_url('admin.php?page=erm-pro-settings'); ?>" class="button button-secondary">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php echo esc_html__('Settings', 'erm-pro'); ?>
        </a>
    </div>
</div>

<!-- Detail Modal -->
<div id="erm-detail-modal" class="erm-modal hidden">
    <div class="erm-modal-content">
        <div class="erm-modal-header">
            <h2><?php echo esc_html__('Request Details', 'erm-pro'); ?></h2>
            <button type="button" class="erm-modal-close">&times;</button>
        </div>
        <div class="erm-modal-body" id="erm-detail-body">
            <!-- Loaded via AJAX -->
        </div>
        <div class="erm-modal-footer">
            <div class="erm-detail-actions" id="erm-detail-actions" style="display:none; width:100%; text-align:left; border-top:1px solid #ddd; padding-top:15px; margin-bottom:15px;">
                <div class="erm-rate-limit-section" style="margin-bottom:20px;">
                    <h3 style="margin-top:0;"><?php echo esc_html__('Rate Limiting', 'erm-pro'); ?></h3>
                    <p><?php echo esc_html__('Allow this host to connect once every (seconds):', 'erm-pro'); ?></p>
                    <div style="display:flex; gap:10px;">
                        <input type="number" id="erm-rate-interval" min="0" placeholder="0 = disabled" style="flex:1; padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <button type="button" class="button button-primary" id="erm-save-rate-limit"><?php echo esc_html__('Save', 'erm-pro'); ?></button>
                    </div>
                    <p style="font-size:12px; color:#666; margin-top:8px;"><?php echo esc_html__('0 = no limit, leave empty to disable rate limiting', 'erm-pro'); ?></p>
                </div>
                
                <div class="erm-modal-actions" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="button" class="button" id="erm-modal-toggle-block"><?php echo esc_html__('Block', 'erm-pro'); ?></button>
                    <button type="button" class="button button-link-delete" id="erm-modal-delete"><?php echo esc_html__('Delete', 'erm-pro'); ?></button>
                </div>
            </div>
            <button type="button" class="button erm-modal-close-btn"><?php echo esc_html__('Close', 'erm-pro'); ?></button>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div id="erm-clear-modal" class="erm-modal hidden">
    <div class="erm-modal-content">
        <div class="erm-modal-header">
            <h2><?php echo esc_html__('Clear Logs', 'erm-pro'); ?></h2>
            <button type="button" class="erm-modal-close">&times;</button>
        </div>
        <div class="erm-modal-body">
            <p><?php echo esc_html__('Choose how to clear logs:', 'erm-pro'); ?></p>
            <div class="erm-clear-options">
                <label class="erm-clear-option">
                    <input type="radio" name="erm_clear_mode" value="except_blocked" checked>
                    <span class="erm-clear-title"><?php echo esc_html__('Clear all logs except blocked', 'erm-pro'); ?></span>
                    <span class="erm-clear-desc"><?php echo esc_html__('Blocked entries will remain in the list', 'erm-pro'); ?></span>
                </label>
                <label class="erm-clear-option">
                    <input type="radio" name="erm_clear_mode" value="all">
                    <span class="erm-clear-title"><?php echo esc_html__('Clear ALL logs', 'erm-pro'); ?></span>
                    <span class="erm-clear-desc"><?php echo esc_html__('All entries including blocked will be cleared and unblocked', 'erm-pro'); ?></span>
                </label>
            </div>
        </div>
        <div class="erm-modal-footer">
            <button type="button" class="button erm-modal-close-btn"><?php echo esc_html__('Cancel', 'erm-pro'); ?></button>
            <button type="button" class="button button-primary" id="erm-confirm-clear-btn"><?php echo esc_html__('Clear', 'erm-pro'); ?></button>
        </div>
    </div>
</div>


