<?php
/**
 * Settings Template
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap erm-pro-wrap">
    <div class="erm-header">
        <h1><?php echo esc_html__('Settings', 'erm-pro'); ?></h1>
        <p class="erm-subtitle"><?php echo esc_html__('Configure External Requests Manager', 'erm-pro'); ?></p>
    </div>
    
    <div class="erm-settings-container">
        <form method="post" action="options.php" class="erm-settings-form">
            <?php
            settings_fields(ERM_PRO_OPTION_GROUP);
            do_settings_sections('erm-pro-settings');
            submit_button(esc_html__('Save Settings', 'erm-pro'), 'primary');
            ?>
        </form>

        <!-- Side Panel: Database Updater, Maintenance, About -->
        <aside class="erm-side-panel">
        <!-- Database Updater -->
        <div class="erm-settings-section erm-database-updater">
            <h2><?php echo esc_html__('Database Updater', 'erm-pro'); ?></h2>
            <p><?php echo esc_html__('Plugin version:', 'erm-pro'); ?> <strong><?php echo esc_html(ERM_PRO_VERSION); ?></strong></p>
            <p><?php echo esc_html__('DB schema version:', 'erm-pro'); ?> <strong><?php echo esc_html(ERM_PRO_DB_VERSION); ?></strong></p>
            <p><?php echo esc_html__('Installed DB version:', 'erm-pro'); ?> <strong><?php echo esc_html(get_option('ERM_PRO_DB_VERSION', '1.0')); ?></strong></p>
            <?php $installed_db = get_option('erm_pro_db_version', ''); ?>
            <?php if ($installed_db !== ERM_PRO_DB_VERSION): ?>
                <p class="description" style="color:#a00;"><?php echo esc_html__('Your database schema is out of date. Run the updater to safely apply schema changes without losing user data.', 'erm-pro'); ?></p>
                <button id="erm-run-db-upgrade" class="button button-primary"><?php echo esc_html__('Run DB Updater', 'erm-pro'); ?></button>
                <span id="erm-db-upgrade-status" style="margin-left:10px;"></span>
            <?php else: ?>
                <p class="description" style="color:#060;"><?php echo esc_html__('Database is up to date.', 'erm-pro'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Maintenance Section -->
        <div class="erm-settings-section erm-maintenance-section">
            <h2><?php echo esc_html__('Maintenance', 'erm-pro'); ?></h2>
            
            <div class="erm-maintenance-box">
                <h3><?php echo esc_html__('Clear Logs', 'erm-pro'); ?></h3>
                <p><?php echo esc_html__('Manage your request logs:', 'erm-pro'); ?></p>
                
                <div class="erm-maintenance-options">
                    <button type="button" class="button button-secondary" id="erm-settings-clear-except-btn">
                        <span class="dashicons dashicons-trash"></span>
                        <?php echo esc_html__('Clear All Except Blocked', 'erm-pro'); ?>
                    </button>
                    
                    <button type="button" class="button button-link-delete" id="erm-settings-clear-all-btn">
                        <span class="dashicons dashicons-trash"></span>
                        <?php echo esc_html__('Clear All & Unblock', 'erm-pro'); ?>
                    </button>
                    <p style="font-size: 12px; color: #666; margin-top: 10px;">
                        💡 <?php echo esc_html__('These actions cannot be undone. All logs will be permanently deleted.', 'erm-pro'); ?>
                    </p>
                </div>
            </div>
            
            <div class="erm-maintenance-box">
                <h3><?php echo esc_html__('Database Info', 'erm-pro'); ?></h3>
                <p>
                    <?php 
                    // Get correct count of only non-deleted requests
                    $counts = ERM_Database::count_by_status();
                    printf(
                        esc_html__('Total requests logged: %d', 'erm-pro'),
                        number_format($counts['total'])
                    );
                    ?>
                </p>
            </div>
        </div>
        
        <!-- About Section -->
        <div class="erm-settings-section erm-about-section">
            <h2><?php echo esc_html__('About', 'erm-pro'); ?></h2>
            <p>
                <strong><?php echo esc_html__('External Request Manager Pro', 'erm-pro'); ?></strong> v<?php echo esc_html(ERM_PRO_VERSION); ?>
            </p>
            <p>
                <?php echo esc_html__('A professional WordPress plugin for monitoring and managing external HTTP requests.', 'erm-pro'); ?>
            </p>
            <p>
                <a href="https://github.com/yusufbahrami/external-request-manager-pro" target="_blank" class="button button-secondary">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php echo esc_html__('GitHub Repository', 'erm-pro'); ?>
                </a>
            </p>
        </div>
        </aside>
    </div>
    
    <!-- Back to Dashboard -->
    <div class="erm-footer">
        <a href="<?php echo admin_url('admin.php?page=erm-pro-logs'); ?>" class="button">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php echo esc_html__('Back to Dashboard', 'erm-pro'); ?>
        </a>
    </div>
</div>

<!-- Settings Clear Confirmation Modal -->
<div id="erm-settings-confirm-modal" class="erm-modal hidden">
    <div class="erm-modal-content">
        <div class="erm-modal-header">
            <h2><?php echo esc_html__('Clear Logs Confirmation', 'erm-pro'); ?></h2>
            <button type="button" class="erm-modal-close" data-action="close">&times;</button>
        </div>
        <div class="erm-modal-body">
            <div id="erm-settings-confirm-message" style="margin-bottom: 15px;"></div>
            <div class="erm-warning-box" style="margin-top: 15px; padding: 10px; background: #fff8e5; border-left: 4px solid #ffb81c; border-radius: 4px;">
                <p style="margin: 0; color: #856404;">
                    <strong><?php echo esc_html__('⚠️ Warning:', 'erm-pro'); ?></strong>
                    <?php echo esc_html__('This action cannot be undone!', 'erm-pro'); ?>
                </p>
            </div>
        </div>
        <div class="erm-modal-footer">
            <button type="button" class="button erm-modal-close-btn" data-action="cancel"><?php echo esc_html__('Cancel', 'erm-pro'); ?></button>
            <button type="button" class="button button-primary" id="erm-settings-confirm-action"><?php echo esc_html__('Yes, Clear', 'erm-pro'); ?></button>
        </div>
    </div>
</div>
