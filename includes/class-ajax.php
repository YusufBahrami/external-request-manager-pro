<?php
/**
 * AJAX Handler Class
 */

class ERM_AJAX {
    
    public static function init() {
        add_action('wp_ajax_erm_toggle_block', [self::class, 'toggle_block']);
        add_action('wp_ajax_erm_bulk_action', [self::class, 'bulk_action']);
        add_action('wp_ajax_erm_get_detail', [self::class, 'get_detail']);
        add_action('wp_ajax_erm_clear_logs', [self::class, 'clear_logs']);
        add_action('wp_ajax_erm_update_rate_limit', [self::class, 'update_rate_limit']);
        add_action('wp_ajax_erm_restore_deleted', [self::class, 'restore_deleted']);
        add_action('wp_ajax_erm_run_db_upgrade', [self::class, 'run_db_upgrade']);
    }
    
    public static function toggle_block() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'erm-pro')]);
        }
        
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid request', 'erm-pro')]);
        }
        
        $request = ERM_Database::get_request_detail($id);
        if (!$request) {
            wp_send_json_error(['message' => __('Request not found', 'erm-pro')]);
        }
        
        $new_status = !$request->is_blocked;
        ERM_Database::update_request_blocked($id, $new_status);
        
        wp_send_json_success([
            'message' => $new_status ? __('Blocked', 'erm-pro') : __('Allowed', 'erm-pro'),
            'status' => $new_status,
            'counts' => ERM_Database::count_by_status()
        ]);
    }
    
    public static function bulk_action() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'erm-pro')]);
        }
        
        $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : [];
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        
        if (empty($ids) || empty($action)) {
            wp_send_json_error(['message' => __('Invalid request', 'erm-pro')]);
        }
        
        $valid_actions = ['block', 'unblock', 'delete', 'restore'];
        if (!in_array($action, $valid_actions)) {
            wp_send_json_error(['message' => __('Invalid action', 'erm-pro')]);
        }
        
        if ($action === 'delete') {
            foreach ($ids as $id) {
                $id = (int) $id;
                ERM_Database::delete_request($id, true);
            }

            wp_send_json_success([
                'message' => sprintf(__('%d items deleted', 'erm-pro'), count($ids)),
                'count' => count($ids),
                'counts' => ERM_Database::count_by_status()
            ]);
        }

        $result = ERM_Database::bulk_action($ids, $action);

        if ($result !== false) {
            wp_send_json_success([
                'message' => sprintf(__('%d items updated', 'erm-pro'), count($ids)),
                'count' => count($ids),
                'counts' => ERM_Database::count_by_status()
            ]);
        } else {
            wp_send_json_error(['message' => __('Action failed', 'erm-pro')]);
        }
    }
    
    public static function get_detail() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'erm-pro')]);
        }
        
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid request', 'erm-pro')]);
        }
        
        $request = ERM_Database::get_request_detail($id);
        if (!$request) {
            wp_send_json_error(['message' => __('Request not found', 'erm-pro')]);
        }
        
        // Calculate response time (if stored)
        $time_calc = '';
        if ($request->response_time) {
            $time_calc = sprintf(__('%f seconds', 'erm-pro'), $request->response_time);
        }
        
        // Determine source
        $source = __('Unknown', 'erm-pro');
        if (!empty($request->source_plugin)) {
            $source = sprintf(__('Plugin: %s', 'erm-pro'), $request->source_plugin);
        } elseif (!empty($request->source_theme)) {
            $source = sprintf(__('Theme: %s', 'erm-pro'), $request->source_theme);
        } else {
            $source = __('WordPress Core', 'erm-pro');
        }
        
        // Determine status - check for rate limit first (highest priority)
        $status = __('Allowed', 'erm-pro');
        if ($request->rate_limit_interval && $request->rate_limit_interval > 0) {
            $status = __('Rate Limited', 'erm-pro');
        } elseif ($request->is_blocked) {
            $status = __('Blocked', 'erm-pro');
        }
        
        // Parse URLs log
        $urls_list = [];
        $track_all_urls = get_option('erm_pro_track_all_urls', false);
        if ($track_all_urls && !empty($request->urls_log)) {
            $urls_list = array_filter(array_map('trim', explode("\n", $request->urls_log)));
        }
        
        wp_send_json_success([
            'id' => $request->id,
            'host' => $request->host,
            'url' => $request->url_example,
            'method' => $request->request_method,
            'count' => $request->request_count,
            'first_request' => $request->first_timestamp,
            'last_request' => $request->last_timestamp,
            'status' => $status,
            'source' => $source,
            'source_plugin' => $request->source_plugin ?: '-',
            'source_theme' => $request->source_theme ?: '-',
            'source_file' => $request->source_file ?: '-',
            'request_size' => $request->request_size ? self::format_bytes($request->request_size) : '-',
            'response_code' => !empty($request->response_code) ? $request->response_code : '-',
                'response_time' => $time_calc ?: '-',
                'response_data' => !empty($request->response_body) ? $request->response_body : '',
            'rate_limit_interval' => $request->rate_limit_interval ?: 0,
            'is_blocked' => (bool) $request->is_blocked,
            'urls_list' => $urls_list,
            'track_all_urls' => (bool) $track_all_urls,
        ]);
    }
    
    public static function clear_logs() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'erm-pro')]);
        }
        
        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'all';
        
        if ($mode === 'except_blocked') {
            ERM_Database::clear_all_logs(true);
            wp_send_json_success([
                'message' => __('All allowed logs cleared', 'erm-pro'),
                'counts' => ERM_Database::count_by_status()
            ]);
        } elseif ($mode === 'all') {
            ERM_Database::clear_all_logs(false);
            wp_send_json_success([
                'message' => __('All logs cleared', 'erm-pro'),
                'counts' => ERM_Database::count_by_status()
            ]);
        } else {
            wp_send_json_error(['message' => __('Invalid mode', 'erm-pro')]);
        }
    }
    
    public static function update_rate_limit() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'erm-pro')]);
        }
        
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $interval = isset($_POST['interval']) ? (int) $_POST['interval'] : 0;
        $calls = isset($_POST['calls']) ? (int) $_POST['calls'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid request', 'erm-pro')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $wpdb->update($table, [
            'rate_limit_interval' => max(0, $interval),
            'rate_limit_calls' => max(0, $calls),
        ], ['id' => $id]);
        
        wp_send_json_success(['message' => __('Rate limit updated', 'erm-pro')]);
    }
    
    public static function restore_deleted() {
        check_ajax_referer('erm_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'erm-pro')]);
        }
        
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            wp_send_json_error(['message' => __('Invalid request', 'erm-pro')]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $wpdb->update($table, ['is_deleted' => 0], ['id' => $id]);
        
        wp_send_json_success(['message' => __('Item restored', 'erm-pro')]);
    }

    public static function run_db_upgrade() {
        check_ajax_referer('erm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'erm-pro')]);
        }

        $result = ERM_Database::upgrade();

        if ($result) {
            wp_send_json_success([
                'message' => __('Database upgraded successfully.', 'erm-pro'),
                'db_version' => get_option('erm_pro_db_version')
            ]);
        }

        wp_send_json_error(['message' => __('Upgrade failed', 'erm-pro')]);
    }
    
    private static function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
?>
