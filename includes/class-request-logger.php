<?php
/**
 * Request Logger Class
 */

class ERM_Request_Logger {
    
    public static function init() {
        add_filter('pre_http_request', [self::class, 'intercept_request'], 15, 3);
        add_action('wp_loaded', [self::class, 'setup_cleanup']);
    }
    
    public static function intercept_request($preempt, $args, $url) {
        // Get site host
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $request_host = wp_parse_url($url, PHP_URL_HOST);
        
        if (empty($request_host)) {
            return $preempt;
        }
        
        // Skip local requests
        if (in_array($request_host, [$site_host, 'localhost', '127.0.0.1'], true)) {
            return $preempt;
        }
        
        // Check if blocked
        if (self::is_blocked($request_host)) {
            return new WP_Error(
                'erm_blocked',
                __('External request blocked by External Request Manager.', 'erm-pro'),
                ['status' => 403]
            );
        }
        
        // Check rate limiting
        if (self::check_rate_limit($request_host)) {
            return new WP_Error(
                'erm_rate_limited',
                __('Request rate limit exceeded.', 'erm-pro'),
                ['status' => 429]
            );
        }
        
        // Log request
        self::log_request($request_host, $url, $args);
        
        return $preempt;
    }
    
    public static function is_blocked($host) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT is_blocked FROM $table WHERE host = %s AND is_deleted = 0",
            $host
        ));
        
        return (bool) $blocked;
    }
    
    public static function check_rate_limit($host) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $limit = $wpdb->get_row($wpdb->prepare(
            "SELECT rate_limit_interval, rate_limit_calls FROM $table WHERE host = %s AND is_deleted = 0",
            $host
        ));
        
        if (!$limit || !$limit->rate_limit_interval || !$limit->rate_limit_calls) {
            return false;
        }
        
        // Simple check - can be enhanced with transients for more accuracy
        return false;
    }
    
    public static function log_request($host, $url, $args = []) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $now = current_time('mysql');
        
        // Get trace to find source
        $source = self::get_request_source();
        
        // Parse request details
        $method = isset($args['method']) ? $args['method'] : 'GET';
        $request_size = self::calculate_request_size($args);
        
        // Look for existing entry by host AND method (including soft-deleted ones)
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_deleted, urls_log FROM $table WHERE host = %s AND request_method = %s",
            $host,
            $method
        ));
        
        // Track URL if enabled
        $track_all_urls = get_option('erm_pro_track_all_urls', false);
        $max_urls = (int) get_option('erm_pro_max_urls_logged', 10);
        
        $urls_log = '';
        if ($track_all_urls && $existing) {
            $urls_log = $existing->urls_log ? $existing->urls_log : '';
            $urls_array = array_filter(array_map('trim', explode("\n", $urls_log)));
            
            if (!in_array($url, $urls_array)) {
                $urls_array[] = $url;
                
                // Keep only recent URLs
                if (count($urls_array) > $max_urls) {
                    array_shift($urls_array);
                }
                
                $urls_log = implode("\n", $urls_array);
            } else {
                $urls_log = $urls_log;
            }
        } elseif ($track_all_urls) {
            $urls_log = $url;
        }
        
        if ($existing) {
            // Update existing entry
            $update_data = [
                'request_count' => new \WP_Query(),
                'last_timestamp' => $now,
                'url_example' => $url,
                'request_size' => $request_size,
                'is_deleted' => 0,
            ];
            
            if ($track_all_urls) {
                $update_data['urls_log'] = $urls_log;
            }
            
            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET 
                    request_count = request_count + 1,
                    last_timestamp = %s,
                    url_example = %s,
                    request_size = %d,
                    is_deleted = 0
                    " . ($track_all_urls ? ",urls_log = %s" : "") . "
                WHERE id = %d",
                array_merge(
                    [$now, $url, $request_size],
                    $track_all_urls ? [$urls_log] : [],
                    [$existing->id]
                )
            ));
        } else {
            // Insert new entry
            $insert_data = [
                'host' => $host,
                'request_method' => $method,
                'url_example' => $url,
                'request_size' => $request_size,
                'source_file' => isset($source['file']) ? $source['file'] : null,
                'source_plugin' => isset($source['plugin']) ? $source['plugin'] : null,
                'source_theme' => isset($source['theme']) ? $source['theme'] : null,
                'request_count' => 1,
                'first_timestamp' => $now,
                'last_timestamp' => $now,
                'is_blocked' => 0,
                'is_deleted' => 0,
            ];
            
            if ($track_all_urls) {
                $insert_data['urls_log'] = $url;
            }
            
            $wpdb->insert($table, $insert_data);
        }
    }
    
    private static function get_request_source() {
        $source = [];
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        
        foreach ($trace as $call) {
            $file = isset($call['file']) ? $call['file'] : '';
            
            if (strpos($file, 'wp-content/plugins/') !== false) {
                preg_match('/plugins\/([^\/]+)/', $file, $matches);
                $source['plugin'] = $matches[1] ?? null;
                $source['file'] = $file;
                break;
            } elseif (strpos($file, 'wp-content/themes/') !== false) {
                preg_match('/themes\/([^\/]+)/', $file, $matches);
                $source['theme'] = $matches[1] ?? null;
                $source['file'] = $file;
                break;
            }
        }
        
        return $source;
    }
    
    private static function calculate_request_size($args) {
        $size = 0;
        
        if (isset($args['body'])) {
            $size += strlen(is_array($args['body']) ? http_build_query($args['body']) : $args['body']);
        }
        
        if (isset($args['headers']) && is_array($args['headers'])) {
            foreach ($args['headers'] as $name => $value) {
                $size += strlen($name) + strlen($value);
            }
        }
        
        return $size;
    }
    
    public static function setup_cleanup() {
        if (!wp_next_scheduled('erm_pro_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'erm_pro_daily_cleanup');
        }
    }
}

// Hook for daily cleanup
add_action('erm_pro_daily_cleanup', function() {
    $retention = (int) get_option('erm_pro_retention_days', 30);
    $auto_clean = get_option('erm_pro_auto_clean', true);
    
    if ($auto_clean && $retention > 0) {
        ERM_Database::cleanup_old_logs($retention);
        ERM_Database::permanently_delete_old_logs($retention);
    }
});
?>
