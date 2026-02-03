<?php
/**
 * Database Management Class
 */

class ERM_Database {
    
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Clean up old tables if upgrading from v1.x (only during install)
        self::migrate_from_old_version();

        // Create or update tables
        self::create_requests_table();
        self::create_deleted_table();
        
        // Store versions
        update_option('erm_pro_version', ERM_PRO_VERSION);
        update_option('erm_pro_db_version', ERM_PRO_DB_VERSION);
        
        // Initialize default settings
        if (!get_option('erm_pro_per_page')) {
            update_option('erm_pro_per_page', 25);
        }
        if (!get_option('erm_pro_display_columns')) {
            update_option('erm_pro_display_columns', ['host', 'count', 'status', 'last_request']);
        }
    }

    /**
     * Upgrade DB schema safely for existing installs without renaming legacy tables.
     * This will run dbDelta to add missing columns/tables but will not attempt to
     * rename or move user data.
     */
    public static function upgrade() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create or update tables (dbDelta will ALTER existing tables safely)
        self::create_requests_table();
        self::create_deleted_table();

        // Update stored DB version (schema) and plugin version
        update_option('erm_pro_db_version', ERM_PRO_DB_VERSION);
        update_option('erm_pro_version', ERM_PRO_VERSION);

        return true;
    }
    
    private static function migrate_from_old_version() {
        global $wpdb;
        $old_table = $wpdb->prefix . 'external_requests';
        
        // Check if old table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$old_table'");
        
        if ($table_exists) {
            // Migrate data from old table to new structure
            $old_data = $wpdb->get_results("SELECT * FROM $old_table");
            
            if (!empty($old_data)) {
                // We'll preserve the data in the new table creation
                $wpdb->query("RENAME TABLE $old_table TO {$old_table}_backup");
            }
        }
    }
    
    private static function create_requests_table() {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            host varchar(255) NOT NULL,
            request_method varchar(20) DEFAULT 'GET',
            url_example text NOT NULL,
            urls_log longtext,
            response_code INT DEFAULT NULL,
            request_size BIGINT DEFAULT 0,
            response_time FLOAT DEFAULT 0,
            response_body LONGTEXT DEFAULT NULL,
            source_file varchar(500) DEFAULT NULL,
            source_plugin varchar(255) DEFAULT NULL,
            source_theme varchar(255) DEFAULT NULL,
            request_count bigint(20) UNSIGNED NOT NULL DEFAULT 1,
            first_timestamp datetime NOT NULL,
            last_timestamp datetime NOT NULL,
            is_blocked tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
            is_deleted tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
            rate_limit_interval int DEFAULT 0,
            rate_limit_calls int DEFAULT 0,
            notes text,
            custom_action varchar(100),
            PRIMARY KEY (id),
            UNIQUE KEY host_method (host, request_method),
            KEY is_blocked (is_blocked),
            KEY is_deleted (is_deleted),
            KEY last_timestamp (last_timestamp),
            KEY request_count (request_count),
            KEY host (host)
        ) $charset;";
        
        dbDelta($sql);
    }
    
    private static function create_deleted_table() {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_DELETED;
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            host varchar(255) NOT NULL,
            url_example text,
            was_blocked tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
            deleted_timestamp datetime NOT NULL,
            deleted_by_user int UNSIGNED,
            PRIMARY KEY (id),
            KEY host (host),
            KEY deleted_timestamp (deleted_timestamp)
        ) $charset;";
        
        dbDelta($sql);
    }
    
    public static function deactivate() {
        wp_clear_scheduled_hook('erm_pro_daily_cleanup');
    }
    
    public static function get_requests($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $defaults = [
            'filter' => 'all',
            'search' => '',
            'search_by' => '',
            'per_page' => get_option('erm_pro_per_page', 25),
            'paged' => 1,
            'orderby' => 'request_count',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['is_deleted = 0'];
        
        if ($args['filter'] === 'blocked') {
            $where[] = 'is_blocked = 1';
        } elseif ($args['filter'] === 'allowed') {
            $where[] = 'is_blocked = 0';
        }
        
        if (!empty($args['search'])) {
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            
            if ($args['search_by'] === 'host') {
                $where[] = $wpdb->prepare('host LIKE %s', $search_term);
            } elseif ($args['search_by'] === 'url') {
                $where[] = $wpdb->prepare('url_example LIKE %s', $search_term);
            } elseif ($args['search_by'] === 'plugin') {
                $where[] = $wpdb->prepare('source_plugin LIKE %s', $search_term);
            } elseif ($args['search_by'] === 'theme') {
                $where[] = $wpdb->prepare('source_theme LIKE %s', $search_term);
            } else {
                // Search all fields
                $where[] = $wpdb->prepare('(host LIKE %s OR url_example LIKE %s OR source_plugin LIKE %s OR source_theme LIKE %s)', 
                    $search_term, $search_term, $search_term, $search_term
                );
            }
        }
        
        $where_sql = implode(' AND ', $where);
        
        $offset = ($args['paged'] - 1) * $args['per_page'];
        
        $results = [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_sql"),
            'data' => $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE $where_sql 
                ORDER BY {$args['orderby']} {$args['order']} 
                LIMIT %d OFFSET %d",
                $args['per_page'], $offset
            ))
        ];
        
        return $results;
    }
    
    public static function get_request_detail($id) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND is_deleted = 0",
            $id
        ));
    }
    
    public static function count_by_status() {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        return [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_deleted = 0"),
            'blocked' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_blocked = 1 AND is_deleted = 0"),
            'allowed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE is_blocked = 0 AND is_deleted = 0"),
        ];
    }
    
    public static function update_request_blocked($id, $blocked) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        return $wpdb->update($table, ['is_blocked' => $blocked ? 1 : 0], ['id' => $id]);
    }
    
    public static function delete_request($id, $hard_delete = true) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        $deleted_table = $wpdb->prefix . ERM_PRO_TABLE_DELETED;
        
        $request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        
        if (!$request) return false;
        
        // Log to deleted table first
        $wpdb->insert($deleted_table, [
            'host' => $request->host,
            'url_example' => $request->url_example,
            'was_blocked' => $request->is_blocked,
            'deleted_timestamp' => current_time('mysql'),
            'deleted_by_user' => get_current_user_id(),
        ]);

        if ($hard_delete) {
            return $wpdb->delete($table, ['id' => $id]);
        } else {
            return $wpdb->update($table, ['is_deleted' => 1], ['id' => $id]);
        }
    }
    
    public static function bulk_action($ids, $action) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        if (empty($ids) || !is_array($ids)) return false;
        
        $ids = array_map('intval', array_filter($ids));
        if (empty($ids)) return false;
        
        $ids_list = implode(',', $ids);
        
        switch ($action) {
            case 'block':
                return $wpdb->query("UPDATE $table SET is_blocked = 1 WHERE id IN ($ids_list)");
            case 'unblock':
                return $wpdb->query("UPDATE $table SET is_blocked = 0 WHERE id IN ($ids_list)");
            case 'delete':
                // For deletes we want to permanently remove rows and log them in the deleted table.
                foreach ($ids as $id) {
                    $id = (int) $id;
                    // Ensure it's unblocked first and rate limits removed
                    $wpdb->update($table, ['is_blocked' => 0, 'rate_limit_interval' => 0, 'rate_limit_calls' => 0], ['id' => $id]);
                    self::delete_request($id, true);
                }
                return true;
            case 'restore':
                return $wpdb->query("UPDATE $table SET is_deleted = 0 WHERE id IN ($ids_list)");
            default:
                return false;
        }
    }
    
    public static function clear_all_logs($except_blocked = false) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        $deleted_table = $wpdb->prefix . ERM_PRO_TABLE_DELETED;
        $user_id = get_current_user_id();
        
        if ($except_blocked) {
            // Log allowed entries to deleted table then delete them
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $deleted_table (host, url_example, was_blocked, deleted_timestamp, deleted_by_user)
                 SELECT host, url_example, is_blocked, NOW(), %d FROM $table WHERE is_blocked = 0 AND is_deleted = 0",
                $user_id
            ));

            return $wpdb->query("DELETE FROM $table WHERE is_blocked = 0 AND is_deleted = 0");
        } else {
            // Log all entries to deleted table, then delete them. Also ensure any block/rate-limit fields are cleared (rows removed).
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $deleted_table (host, url_example, was_blocked, deleted_timestamp, deleted_by_user)
                 SELECT host, url_example, is_blocked, NOW(), %d FROM $table WHERE is_deleted = 0",
                $user_id
            ));

            $wpdb->query("UPDATE $table SET is_blocked = 0 WHERE is_deleted = 0");
            return $wpdb->query("DELETE FROM $table WHERE is_deleted = 0");
        }
    }
    
    public static function cleanup_old_logs($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        if ($days < 1) return false;
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table SET is_deleted = 1 
            WHERE last_timestamp < DATE_SUB(NOW(), INTERVAL %d DAY) AND is_deleted = 0",
            $days
        ));
    }
    
    public static function permanently_delete_old_logs($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        $deleted_table = $wpdb->prefix . ERM_PRO_TABLE_DELETED;
        
        if ($days < 1) return false;
        
        // Move to deleted table first
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $deleted_table (host, url_example, was_blocked, deleted_timestamp, deleted_by_user)
            SELECT host, url_example, is_blocked, NOW(), 0 
            FROM $table
            WHERE last_timestamp < DATE_SUB(NOW(), INTERVAL %d DAY) AND is_deleted = 1",
            $days
        ));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table 
            WHERE last_timestamp < DATE_SUB(NOW(), INTERVAL %d DAY) AND is_deleted = 1",
            $days
        ));
    }
}
?>
