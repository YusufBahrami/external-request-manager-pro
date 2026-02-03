<?php
/**
 * Settings Management Class
 */

class ERM_Settings {
    
    public static function init() {
        add_action('admin_init', [self::class, 'register_settings']);
    }
    
    public static function register_settings() {
        // General Settings
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_retention_days', [
            'type' => 'integer',
            'sanitize_callback' => [self::class, 'sanitize_retention_days'],
            'default' => 30
        ]);
        
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_auto_clean', [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_checkbox'],
            'default' => true
        ]);
        
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_per_page', [
            'type' => 'integer',
            'sanitize_callback' => [self::class, 'sanitize_per_page'],
            'default' => 25
        ]);
        
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_display_columns', [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_columns'],
            'default' => ['host', 'count', 'status', 'last_request']
        ]);
        
        // UI Settings
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_enable_notifications', [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_checkbox'],
            'default' => true
        ]);
        
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_notification_threshold', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 100
        ]);
        
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_track_all_urls', [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_checkbox'],
            'default' => false
        ]);
        
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_max_urls_logged', [
            'type' => 'integer',
            'sanitize_callback' => [self::class, 'sanitize_max_urls'],
            'default' => 10
        ]);
        
        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_track_response', [
            'type' => 'boolean',
            'sanitize_callback' => [self::class, 'sanitize_checkbox'],
            'default' => true
        ]);

        register_setting(ERM_PRO_OPTION_GROUP, 'erm_pro_max_response_body_length', [
            'type' => 'integer',
            'sanitize_callback' => [self::class, 'sanitize_max_response_body_length'],
            'default' => 65536
        ]);
        
        // Add Settings Sections
        add_settings_section(
            'erm_pro_general',
            __('General Settings', 'erm-pro'),
            null,
            'erm-pro-settings'
        );
        
        add_settings_section(
            'erm_pro_display',
            __('Display Settings', 'erm-pro'),
            null,
            'erm-pro-settings'
        );
        
        add_settings_section(
            'erm_pro_cleanup',
            __('Log Management', 'erm-pro'),
            null,
            'erm-pro-settings'
        );
        
        // Add Settings Fields
        add_settings_field(
            'erm_pro_per_page',
            __('Items Per Page', 'erm-pro'),
            [self::class, 'field_per_page'],
            'erm-pro-settings',
            'erm_pro_display'
        );
        
        add_settings_field(
            'erm_pro_display_columns',
            __('Display Columns', 'erm-pro'),
            [self::class, 'field_display_columns'],
            'erm-pro-settings',
            'erm_pro_display'
        );
        
        add_settings_field(
            'erm_pro_retention_days',
            __('Log Retention Period', 'erm-pro'),
            [self::class, 'field_retention_days'],
            'erm-pro-settings',
            'erm_pro_cleanup'
        );
        
        add_settings_field(
            'erm_pro_auto_clean',
            __('Auto-Clean Old Logs', 'erm-pro'),
            [self::class, 'field_auto_clean'],
            'erm-pro-settings',
            'erm_pro_cleanup'
        );
        
        add_settings_field(
            'erm_pro_enable_notifications',
            __('Enable Notifications', 'erm-pro'),
            [self::class, 'field_enable_notifications'],
            'erm-pro-settings',
            'erm_pro_general'
        );
        
        add_settings_field(
            'erm_pro_track_all_urls',
            __('Track All Request URLs', 'erm-pro'),
            [self::class, 'field_track_all_urls'],
            'erm-pro-settings',
            'erm_pro_display'
        );
        
        add_settings_field(
            'erm_pro_max_urls_logged',
            __('Max URLs to Log Per Request', 'erm-pro'),
            [self::class, 'field_max_urls_logged'],
            'erm-pro-settings',
            'erm_pro_display'
        );
        
        add_settings_field(
            'erm_pro_track_response',
            __('Track Response Code & Time', 'erm-pro'),
            [self::class, 'field_track_response'],
            'erm-pro-settings',
            'erm_pro_display'
        );

        add_settings_field(
            'erm_pro_max_response_body_length',
            __('Max Response Body Length', 'erm-pro'),
            [self::class, 'field_max_response_body_length'],
            'erm-pro-settings',
            'erm_pro_display'
        );
    }
    
    // Sanitize Functions
    public static function sanitize_retention_days($value) {
        $value = (int) $value;
        return max(0, $value);
    }
    
    public static function sanitize_checkbox($value) {
        return $value ? 1 : 0;
    }
    
    public static function sanitize_per_page($value) {
        $value = (int) $value;
        return max(5, min(200, $value));
    }
    
    public static function sanitize_max_urls($value) {
        $value = (int) $value;
        return max(1, min(100, $value));
    }

    public static function sanitize_max_response_body_length($value) {
        $value = (int) $value;
        // Allow 0 to disable storing bodies, otherwise cap to 1MB
        return max(0, min(1024 * 1024, $value));
    }
    
    public static function sanitize_columns($value) {
        $allowed = ['host', 'source', 'method', 'count', 'size', 'status', 'first_request', 'last_request', 'actions'];
        if (!is_array($value)) {
            return ['host', 'count', 'status', 'last_request'];
        }
        return array_intersect($value, $allowed);
    }
    
    // Field Renderers
    public static function field_per_page() {
        $value = get_option('erm_pro_per_page', 25);
        ?>
        <input type="number" name="erm_pro_per_page" value="<?php echo esc_attr($value); ?>" 
               min="5" max="200" class="small-text">
        <span class="description"><?php _e('Number of items to display per page (5-200)', 'erm-pro'); ?></span>
        <?php
    }
    
    public static function field_display_columns() {
        $columns = get_option('erm_pro_display_columns', ['host', 'count', 'status', 'last_request']);
        $available = [
            'host' => __('Host', 'erm-pro'),
            'source' => __('Source (Plugin/Theme)', 'erm-pro'),
            'method' => __('Request Method', 'erm-pro'),
            'count' => __('Request Count', 'erm-pro'),
            'size' => __('Request Size', 'erm-pro'),
            'status' => __('Status (Blocked/Allowed)', 'erm-pro'),
            'first_request' => __('First Request', 'erm-pro'),
            'last_request' => __('Last Request', 'erm-pro'),
            'actions' => __('Actions', 'erm-pro'),
        ];
        ?>
        <fieldset>
            <?php foreach ($available as $key => $label): ?>
                <label style="display: block; margin: 8px 0;">
                    <input type="checkbox" name="erm_pro_display_columns[]" value="<?php echo esc_attr($key); ?>"
                           <?php checked(in_array($key, $columns)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description"><?php _e('Select which columns to display in the logs table', 'erm-pro'); ?></p>
        <?php
    }
    
    public static function field_retention_days() {
        $days = get_option('erm_pro_retention_days', 30);
        ?>
        <div style="margin-bottom: 15px;">
            <p style="margin: 0 0 8px 0;">
                <label for="erm_pro_retention_days"><?php _e('Keep logs for', 'erm-pro'); ?>:</label>
            </p>
            <input type="number" id="erm_pro_retention_days" name="erm_pro_retention_days" 
                   value="<?php echo esc_attr($days); ?>" min="0" max="3650" class="small-text">
            <span><?php _e('days', 'erm-pro'); ?></span>
            <p class="description">
                <?php _e('Logs older than this period will be marked for deletion. Set to 0 to keep logs forever.', 'erm-pro'); ?>
            </p>
        </div>
        <?php
    }
    
    public static function field_auto_clean() {
        $checked = get_option('erm_pro_auto_clean', true) ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" name="erm_pro_auto_clean" value="1" <?php echo $checked; ?>>
            <span><?php _e('Automatically delete old logs', 'erm-pro'); ?></span>
        </label>
        <p class="description">
            <?php _e('When enabled, logs older than the retention period will be permanently deleted automatically.', 'erm-pro'); ?>
        </p>
        <?php
    }
    
    public static function field_enable_notifications() {
        $checked = get_option('erm_pro_enable_notifications', true) ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" name="erm_pro_enable_notifications" value="1" <?php echo $checked; ?>>
            <span><?php _e('Show admin notifications', 'erm-pro'); ?></span>
        </label>
        <p class="description">
            <?php _e('Display notifications when new external requests are detected', 'erm-pro'); ?>
        </p>
        <?php
    }
    
    public static function field_track_all_urls() {
        $checked = get_option('erm_pro_track_all_urls', false) ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" name="erm_pro_track_all_urls" value="1" <?php echo $checked; ?>>
            <span><?php _e('Keep log of all requested URLs', 'erm-pro'); ?></span>
        </label>
        <p class="description">
            <?php _e('When enabled, all unique URLs from each request will be logged. Disable to reduce database usage.', 'erm-pro'); ?>
        </p>
        <?php
    }
    
    public static function field_max_urls_logged() {
        $value = get_option('erm_pro_max_urls_logged', 10);
        ?>
        <input type="number" name="erm_pro_max_urls_logged" value="<?php echo esc_attr($value); ?>" 
               min="1" max="100" class="small-text">
        <span class="description"><?php _e('Maximum number of unique URLs to keep per request (1-100)', 'erm-pro'); ?></span>
        <?php
    }
    
    public static function field_track_response() {
        $checked = get_option('erm_pro_track_response', true) ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" name="erm_pro_track_response" value="1" <?php echo $checked; ?>>
            <span><?php _e('Track HTTP Response Code & Time', 'erm-pro'); ?></span>
        </label>
        <p class="description">
            <?php _e('When enabled, response codes (200, 404, etc.) will be recorded for each request. This helps monitor external service health.', 'erm-pro'); ?>
        </p>
        <?php
    }

    public static function field_max_response_body_length() {
        $value = get_option('erm_pro_max_response_body_length', 65536);
        ?>
        <div>
            <input type="number" name="erm_pro_max_response_body_length" value="<?php echo esc_attr($value); ?>" min="0" max="1048576" class="small-text">
            <span class="description"><?php _e('Maximum number of bytes to store from response body. Set to 0 to disable saving response bodies (useful to save DB space). Default 65536 (64KB).', 'erm-pro'); ?></span>
        </div>
        <?php
    }
}
?>
