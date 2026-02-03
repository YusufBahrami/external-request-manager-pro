<?php
/**
 * Admin Pages Class
 */

class ERM_Admin_Pages {
    
    public static function init() {
        add_action('admin_menu', [self::class, 'register_menus']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_notices', [self::class, 'show_notices']);
    }
    
    public static function register_menus() {
        add_menu_page(
            __('External Requests Manager', 'erm-pro'),
            __('Ext. Requests', 'erm-pro'),
            'manage_options',
            'erm-pro-logs',
            [self::class, 'dashboard_page'],
            'dashicons-shield-alt',
            82
        );
        
        add_submenu_page(
            'erm-pro-logs',
            __('Dashboard', 'erm-pro'),
            __('Dashboard', 'erm-pro'),
            'manage_options',
            'erm-pro-logs',
            [self::class, 'dashboard_page']
        );
        
        add_submenu_page(
            'erm-pro-logs',
            __('Settings', 'erm-pro'),
            __('Settings', 'erm-pro'),
            'manage_options',
            'erm-pro-settings',
            [self::class, 'settings_page']
        );

        add_submenu_page(
            'erm-pro-logs',
            __('Deleted', 'erm-pro'),
            __('Deleted', 'erm-pro'),
            'manage_options',
            'erm-pro-deleted',
            [self::class, 'deleted_page']
        );
    }
    
    public static function enqueue_assets($hook_suffix) {
        if (strpos($hook_suffix, 'erm-pro') === false) {
            return;
        }
        
        wp_enqueue_style('erm-pro-admin', ERM_PRO_URL . 'assets/css/admin.css', [], ERM_PRO_VERSION);
        wp_enqueue_script('erm-pro-admin', ERM_PRO_URL . 'assets/js/admin.js', ['jquery'], ERM_PRO_VERSION, true);
        
        wp_localize_script('erm-pro-admin', 'ermProData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('erm_nonce'),
            'messages' => [
                'confirmDelete' => __('Are you sure? This action cannot be undone.', 'erm-pro'),
                'confirmClearAll' => __('Clear ALL logs? This cannot be undone.', 'erm-pro'),
                'confirmClearExceptBlocked' => __('Clear all logs except blocked items? This cannot be undone.', 'erm-pro'),
            ],
        ]);
    }
    
    public static function show_notices() {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', 'erm-pro') === false) {
            return;
        }
        
        if (isset($_GET['cleared'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Logs cleared successfully.', 'erm-pro') . 
                 '</p></div>';
        }

        // Show transient notifications (if enabled)
        $enable_notifications = get_option('erm_pro_enable_notifications', true);
        if ($enable_notifications) {
            $notes = get_transient('erm_pro_notifications');
            if (!empty($notes) && is_array($notes)) {
                foreach ($notes as $n) {
                    $host = isset($n['host']) ? esc_html($n['host']) : '';
                    $msg = isset($n['message']) ? esc_html($n['message']) : '';
                    echo '<div class="notice notice-info is-dismissible"><p>' . $msg . ' <strong>' . $host . '</strong></p></div>';
                }
                // remove after showing once
                delete_transient('erm_pro_notifications');
            }
        }

        // If DB version mismatch, show upgrade notice
        $current_db_version = get_option('erm_pro_db_version', '');
        if ($current_db_version !== ERM_PRO_DB_VERSION) {
            $settings_link = admin_url('admin.php?page=erm-pro-settings');
            echo '<div class="notice notice-warning is-dismissible"><p>' .
                 esc_html__('Database schema is out of date for External Request Manager Pro.', 'erm-pro') . ' ' .
                 sprintf('<a href="%s">%s</a>', esc_url($settings_link), esc_html__('Run DB Updater', 'erm-pro')) .
                 '</p></div>';
        }
    }
    
    public static function dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'erm-pro'));
        }
        
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $search_by = isset($_GET['search_by']) ? sanitize_text_field($_GET['search_by']) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        
        $per_page = get_option('erm_pro_per_page', 25);
        
        $results = ERM_Database::get_requests([
            'filter' => $filter,
            'search' => $search,
            'search_by' => $search_by,
            'per_page' => $per_page,
            'paged' => $paged,
        ]);
        
        $counts = ERM_Database::count_by_status();
        $display_columns = get_option('erm_pro_display_columns', ['host', 'count', 'status', 'last_request']);
        
        require ERM_PRO_DIR . 'templates/dashboard.php';
    }
    
    public static function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'erm-pro'));
        }
        
        $retention_days = get_option('erm_pro_retention_days', 30);
        $auto_clean = get_option('erm_pro_auto_clean', true);
        $per_page = get_option('erm_pro_per_page', 25);
        $display_columns = get_option('erm_pro_display_columns', ['host', 'count', 'status', 'last_request']);
        
        require ERM_PRO_DIR . 'templates/settings.php';
    }

    public static function deleted_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'erm-pro'));
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = get_option('erm_pro_per_page', 25);

        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_DELETED;

        $offset = ($paged - 1) * $per_page;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $data = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY deleted_timestamp DESC LIMIT %d OFFSET %d", $per_page, $offset));

        $results = [
            'total' => $total,
            'data' => $data,
            'paged' => $paged,
            'per_page' => $per_page,
        ];

        require ERM_PRO_DIR . 'templates/deleted.php';
    }
}
?>
