<?php
/**
 * Request Logger Class
 * Handles logging of external HTTP requests with proper tracking and cleanup
 */

class ERM_Request_Logger {
    
    public static function init() {
        add_filter('pre_http_request', [self::class, 'intercept_request'], 15, 3);
        add_filter('http_request_args', [self::class, 'track_request_args'], 10, 2);
        add_filter('http_api_debug', [self::class, 'capture_response'], 10, 5);
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
            // Auto-block this host if rate limit exceeded
            global $wpdb;
            $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
            $wpdb->update(
                $table,
                ['is_blocked' => 1],
                ['host' => $request_host],
                ['%d'],
                ['%s']
            );
            
            return new WP_Error(
                'erm_rate_limited',
                __('Request rate limit exceeded. Host has been blocked.', 'erm-pro'),
                ['status' => 429]
            );
        }
        
        // Get request details before logging
        $method = isset($args['method']) ? $args['method'] : 'GET';
        $request_size = self::calculate_request_size($args);
        $source = self::get_request_source();
        
        // Store request in transient for response tracking
        $request_key = 'erm_request_' . md5($url . time());
        set_transient($request_key, [
            'host' => $request_host,
            'url' => $url,
            'method' => $method,
            'request_size' => $request_size,
            'source' => $source,
            'time_start' => microtime(true)
        ], 60);
        
        // Log request
        self::log_request($request_host, $url, $method, $request_size, $source);
        
        return $preempt;
    }
    
    /**
     * Store request args for later response matching
     */
    public static function track_request_args($args, $url) {
        global $erm_last_request;
        $erm_last_request = [
            'url' => $url,
            'method' => $args['method'] ?? 'GET',
            'time' => microtime(true)
        ];
        return $args;
    }
    
    /**
     * Capture HTTP response and update request log with response details
     */
    public static function capture_response($return, $type, $class, $function, $args) {
        global $erm_last_request;

        // Only track response if enabled
        if (!get_option('erm_pro_track_response', true)) {
            return $return;
        }

        $response_code = null;
        $url = '';
        $full_body = null;

        // If $return is a WP HTTP response array
        if (is_array($return)) {
            if (isset($return['response']) && is_array($return['response']) && isset($return['response']['code'])) {
                $response_code = (int) $return['response']['code'];
            }

            if (isset($return['body'])) {
                $full_body = is_scalar($return['body']) ? $return['body'] : wp_json_encode($return['body']);
            }
        }

        // If WP_Error
        if ($return instanceof WP_Error) {
            $error_data = $return->get_error_data();
            if (is_array($error_data) && isset($error_data['status'])) {
                $response_code = (int) $error_data['status'];
            }
            $full_body = $return->get_error_message();
        }

        // Try to extract URL and fallback response code from $args (various WP versions provide differing shapes)
        if (is_array($args)) {
            if (isset($args['url'])) {
                $url = $args['url'];
            } elseif (isset($args[2]) && is_string($args[2])) {
                $url = $args[2];
            }

            if ($response_code === null && isset($args[1]) && (is_int($args[1]) || ctype_digit((string)$args[1]))) {
                $response_code = (int) $args[1];
            }
        }

        // Fallback: sometimes $erm_last_request may contain the URL
        if (empty($url) && !empty($erm_last_request['url'])) {
            $url = $erm_last_request['url'];
        }

        if (!empty($url) && $response_code !== null) {
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            $request_host = wp_parse_url($url, PHP_URL_HOST);

            // Skip local requests
            if (!in_array($request_host, [$site_host, 'localhost', '127.0.0.1'], true)) {
                $method = isset($erm_last_request['method']) ? $erm_last_request['method'] : 'GET';
                self::update_response($request_host, $url, $method, $response_code, $full_body);
            }
        }

        return $return;
    }
    
    public static function is_blocked($host) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $blocked = $wpdb->get_var($wpdb->prepare(
            "SELECT is_blocked FROM $table WHERE host = %s AND is_deleted = 0 LIMIT 1",
            $host
        ));
        
        return (bool) $blocked;
    }
    
    public static function check_rate_limit($host) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $limit_config = $wpdb->get_row($wpdb->prepare(
            "SELECT rate_limit_interval FROM $table WHERE host = %s AND is_deleted = 0 LIMIT 1",
            $host
        ));
        
        // No rate limit set
        if (!$limit_config || !$limit_config->rate_limit_interval || $limit_config->rate_limit_interval <= 0) {
            return false;
        }
        
        // Get the last request time from transient
        $transient_key = 'erm_rate_limit_' . md5($host);
        $last_request = get_transient($transient_key);
        
        // First request or transient expired
        if ($last_request === false) {
            // Store current time for next check
            set_transient($transient_key, current_time('timestamp'), $limit_config->rate_limit_interval);
            return false;
        }
        
        $now = current_time('timestamp');
        $time_since_last = $now - $last_request;
        
        // If enough time has passed, allow and update timestamp
        if ($time_since_last >= $limit_config->rate_limit_interval) {
            set_transient($transient_key, $now, $limit_config->rate_limit_interval);
            return false;
        }
        
        // Rate limit exceeded - block this request
        return true;
    }
    
    public static function log_request($host, $url, $method = 'GET', $request_size = 0, $source = []) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        $now = current_time('mysql');
        
        // Calculate size if not provided
        if ($request_size === 0) {
            $request_size = self::calculate_request_size([]);
        }
        
        // Look for existing entry by host AND method only
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, urls_log, source_plugin, source_theme, source_file FROM $table WHERE host = %s AND request_method = %s",
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
        
        $is_new_request = false;
        
        if ($existing) {
            // Update existing entry - restore is_deleted flag
            $update_query = "UPDATE $table SET 
                request_count = request_count + 1,
                last_timestamp = %s,
                url_example = %s,
                request_size = %d,
                is_deleted = 0";
            
            $update_params = [$now, $url, $request_size];
            
            // Update source information if not already set (fill missing info)
            if (empty($existing->source_plugin) && !empty($source['plugin'])) {
                $update_query .= ", source_plugin = %s";
                $update_params[] = $source['plugin'];
            }
            if (empty($existing->source_theme) && !empty($source['theme'])) {
                $update_query .= ", source_theme = %s";
                $update_params[] = $source['theme'];
            }
            if (empty($existing->source_file) && !empty($source['file'])) {
                $update_query .= ", source_file = %s";
                $update_params[] = $source['file'];
            }
            
            if ($track_all_urls) {
                $update_query .= ", urls_log = %s";
                $update_params[] = $urls_log;
            }
            
            $update_query .= " WHERE id = %d";
            $update_params[] = $existing->id;
            
            $wpdb->query($wpdb->prepare($update_query, $update_params));
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
                $insert_data['urls_log'] = $urls_log;
            }
            
            $wpdb->insert($table, $insert_data);
            $is_new_request = true;
        }
        
        // Send notification if enabled and new request found
        if ($is_new_request && get_option('erm_pro_enable_notifications', true)) {
            self::send_notification($host, $url, $source);
        }
    }
    
    private static function send_notification($host, $url, $source = []) {
        // Determine source name for notification
        $source_name = 'WordPress Core';
        if (!empty($source['plugin'])) {
            $source_name = $source['plugin'];
        } elseif (!empty($source['theme'])) {
            $source_name = $source['theme'];
        }
        
        $message = sprintf(
            __('New external request detected: %s (from %s)', 'erm-pro'),
            $host,
            $source_name
        );
        
        // Store in transient for display in admin
        $notifications = get_transient('erm_pro_notifications') ?: [];
        $notifications[] = [
            'timestamp' => current_time('timestamp'),
            'host' => $host,
            'url' => $url,
            'source' => $source_name,
            'message' => $message,
        ];
        
        // Keep only last 10 notifications
        if (count($notifications) > 10) {
            $notifications = array_slice($notifications, -10);
        }
        
        set_transient('erm_pro_notifications', $notifications, HOUR_IN_SECONDS);
    }
    
    private static function get_request_source() {
        $source = [];
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);
        
        // Get the directory of the current plugin
        $this_plugin_dir = dirname(dirname(__FILE__));
        
        // Also get wp-content directory for better path detection
        $wp_content_dir = dirname(dirname($this_plugin_dir));
        
        foreach ($trace as $call) {
            $file = isset($call['file']) ? $call['file'] : '';
            
            if (empty($file)) {
                continue;
            }
            
            // Normalize path separators for consistency
            $file = str_replace('\\', '/', $file);
            
            // Skip files in the current plugin (ERM Pro)
            if (strpos($file, str_replace('\\', '/', $this_plugin_dir)) !== false) {
                continue;
            }
            
            // Skip WordPress core files
            if (strpos($file, '/wp-admin/') !== false || 
                strpos($file, '/wp-includes/') !== false ||
                strpos($file, '/wp-content/index.php') !== false ||
                basename($file) === 'wp-load.php' ||
                basename($file) === 'wp-config.php') {
                continue;
            }
            
            // Check for must-use plugins first (highest priority)
            if (preg_match('/wp-content\/mu-plugins\/([^\/]+)/', $file, $matches)) {
                if (!empty($matches[1])) {
                    $source['plugin'] = $matches[1] . ' (MU Plugin)';
                    $source['file'] = $file;
                    return $source;
                }
            }
            
            // Check for regular plugins
            if (preg_match('/wp-content\/plugins\/([^\/]+)/', $file, $matches)) {
                if (!empty($matches[1])) {
                    $source['plugin'] = $matches[1];
                    $source['file'] = $file;
                    return $source;
                }
            }
            
            // Check for themes
            if (preg_match('/wp-content\/themes\/([^\/]+)/', $file, $matches)) {
                if (!empty($matches[1])) {
                    $source['theme'] = $matches[1];
                    $source['file'] = $file;
                    return $source;
                }
            }
            
            // Check for WordPress child plugins or custom code in wp-content
            if (strpos($file, $wp_content_dir) !== false && 
                strpos($file, 'wp-content/') !== false &&
                !preg_match('/wp-content\/(plugins|themes|mu-plugins)/', $file)) {
                
                // It's custom code in wp-content
                $filename = basename($file);
                if (!empty($filename) && $filename !== 'index.php') {
                    $source['file'] = $file;
                    $source['plugin'] = 'Custom Code (wp-content)';
                    return $source;
                }
            }
        }
        
        return $source;
    }
    
    private static function calculate_request_size($args) {
        $size = 0;
        
        // Calculate body size
        if (isset($args['body'])) {
            if (is_array($args['body'])) {
                $size += strlen(http_build_query($args['body']));
            } elseif (is_string($args['body'])) {
                $size += strlen($args['body']);
            }
        }
        
        // Calculate headers size
        if (isset($args['headers']) && is_array($args['headers'])) {
            foreach ($args['headers'] as $name => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $size += strlen((string)$name) + strlen((string)$v) + 4;
                    }
                } else {
                    $size += strlen((string)$name) + strlen((string)$value) + 4;
                }
            }
        }
        
        // Add approximate size for HTTP request line
        $size += 20;
        
        return max(0, $size);
    }
    
    /**
     * Update request with response details
     */
    private static function update_response($host, $url, $method, $response_code, $response_body = null) {
        global $wpdb;
        $table = $wpdb->prefix . ERM_PRO_TABLE_REQUESTS;
        
        // Find the matching request entry by host and method (not URL, since we aggregate)
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, response_time FROM $table WHERE host = %s AND request_method = %s LIMIT 1",
            $host,
            $method
        ));
        
        if ($existing) {
            // Calculate response time if we have the start time
            global $erm_last_request;
            $response_time = 0;
            if (!empty($erm_last_request['time'])) {
                $response_time = microtime(true) - $erm_last_request['time'];
            }

            $update_data = [
                'response_code' => $response_code,
                'response_time' => $response_time > 0 ? $response_time : null,
            ];

            if (!is_null($response_body)) {
                // Respect max length setting
                $max = (int) get_option('erm_pro_max_response_body_length', 65536);

                if ($max === 0) {
                    // Storing disabled
                    $response_body = null;
                } else {
                    // Ensure string and trim to max bytes/characters
                    if (!is_scalar($response_body)) {
                        $response_body = wp_json_encode($response_body);
                    }

                    // Use mb_substr to avoid breaking multibyte characters
                    if (function_exists('mb_substr')) {
                        $truncated = mb_substr($response_body, 0, $max);
                    } else {
                        $truncated = substr($response_body, 0, $max);
                    }

                    if (strlen($response_body) > strlen($truncated)) {
                        $truncated .= "\n...";
                    }

                    $response_body = $truncated;
                }

                if (!is_null($response_body)) {
                    $update_data['response_body'] = $response_body;
                }
            }

            $formats = ['%d', '%f'];
            if (!is_null($response_body)) {
                $formats[] = '%s';
            }

            $wpdb->update(
                $table,
                $update_data,
                ['id' => $existing->id],
                $formats,
                ['%d']
            );
        }
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