# External Request Manager Pro

![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)

A professional WordPress plugin for monitoring, analyzing, and controlling external HTTP requests made by your website. Perfect for security audits, performance optimization, and compliance monitoring.

## ✨ Features

### Request Monitoring
- **Real-time Tracking**: Monitor all external HTTP requests in real-time
- **Detailed Analytics**: View request count, frequency, methods, and response sizes
- **Source Identification**: Automatically identify requests from plugins, themes, or WordPress core
- **Request Details**: Inspect full request URLs, methods, sizes, and response codes
- **Temporal Analysis**: Track first seen and last seen timestamps

### Request Management
- **Flexible Blocking**: Block or allow specific external hosts
- **Soft Delete**: Mark requests as deleted without losing history
- **Bulk Operations**: Block, unblock, or delete multiple requests at once
- **Request Review**: Comprehensive review modal with all request details
- **Rate Limiting**: Set custom rate limits per host (calls per interval)- 🔗 **Separate by Method**: Track GET and POST requests separately
- **URL Logging**: Optional tracking of all unique URLs per request (configurable limit)
- **URL Review Dropdown**: View all logged URLs in the review modal
- **In-Modal Actions**: Block, delete, or save rate limits directly from review modal

### Professional UI
- **Advanced Filtering**: Filter by status (blocked/allowed) and search hosts
- **Customizable Columns**: Choose which columns to display in the table
- **Professional Pagination**: Configurable items per page (5-200)

### Settings & Configuration
- **Log Retention**: Configurable log retention period (0 = forever)
- **Auto-Cleanup**: Automatically delete old logs based on retention policy
- **Notifications**: Optional admin notifications for detected requests
- **Performance**: Indexed database tables for fast queries
- **Security**: Nonce verification, capability checks, and proper sanitization

### Database & Logs
- **Comprehensive Logging**: Detailed request information stored in dedicated tables
- **Deleted Log**: Track deleted entries with deletion metadata
- **Soft Delete Pattern**: Deleted items remain in database for audit trail
- **Status Counts**: Real-time counts of total, blocked, and allowed requests

## 🚀 Installation

1. Download the plugin files to `/wp-content/plugins/external-request-manager-pro/`
2. Activate the plugin through the WordPress admin panel
3. Navigate to **External Requests** in the main menu
4. Configure settings as needed

### Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.2 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)

## 📖 Usage

### Dashboard
The main dashboard displays:
- **Statistics Cards**: Quick overview of total, blocked, and allowed requests
- **Filter Tabs**: View all requests, blocked only, or allowed only
- **Search**: Search by host name or URL
- **Request Table**: Detailed list of all external requests

### Managing Requests
1. **Block/Unblock**: Click the Block/Unblock button on any row
2. **Bulk Actions**: 
   - Select multiple requests using checkboxes
   - Choose action (Block, Unblock, Delete)
   - Click Apply
3. **Delete**: Soft-delete entries (can be recovered from database)
4. **Review**: Click the eye icon to see full request details

### Review Modal
Access comprehensive request details:
- Request URL and host
- HTTP method used
- Total request count
- Current status (Blocked/Allowed)
- First seen and last seen dates
- Source information (plugin/theme)
- Request size and response code

### Settings
Configure the plugin behavior:
- **Items Per Page**: Set pagination size (5-200 items)
- **Display Columns**: Choose which columns to show
- **Log Retention Period**: Days to keep logs (0 = forever)
- **Auto-Clean**: Enable automatic deletion of old logs
- **Notifications**: Toggle admin notifications

### Clear Logs
Two options available:
1. **Clear All Except Blocked**: Remove allowed entries, keep blocked for reference
2. **Clear All & Unblock**: Remove all entries and unblock all hosts

## Architecture

### File Structure
```
external-request-manager-pro/
├── external-request-manager.php       # Main plugin file
├── includes/
│   ├── class-database.php             # Database operations
│   ├── class-request-logger.php       # Request interception & logging
│   ├── class-admin-pages.php          # Admin pages setup
│   ├── class-settings.php             # Settings management
│   ├── helpers.php                    # Helpers
│   └── class-ajax.php                 # AJAX handlers
├── templates/
│   ├── dashboard.php                  # Main dashboard template
│   └── settings.php                   # Settings page template
├── assets/
│   ├── css/
│   │   └── admin.css                  # Admin styling
│   └── js/
│       └── admin.js                   # Admin JavaScript
└── languages/                          # Localization files
```

### Database Tables

#### `wp_external_requests`
Stores external request data:
- `id` - Primary key
- `host` - Domain/host of external request
- `url_example` - Example URL
- `request_method` - HTTP method (GET, POST, etc.)
- `response_code` - HTTP response code
- `request_size` - Size of request in bytes
- `response_time` - Response time in seconds
- `source_file` - Source file path
- `source_plugin` - Plugin that made the request
- `source_theme` - Theme that made the request
- `request_count` - Total times requested
- `first_timestamp` - First time requested
- `last_timestamp` - Last time requested
- `is_blocked` - Whether requests are blocked (1/0)
- `is_deleted` - Soft delete flag (1/0)
- `rate_limit_interval` - Rate limit interval in seconds
- `rate_limit_calls` - Max calls allowed in interval
- `notes` - Custom notes
- `custom_action` - Custom action setting

#### `wp_external_requests_deleted`
Audit trail for deleted entries:
- `id` - Primary key
- `host` - Deleted host
- `url_example` - Example URL
- `was_blocked` - Status before deletion
- `deleted_timestamp` - When deleted
- `deleted_by_user` - User ID who deleted it

## 🔐 Security

- ✅ **Nonce Verification**: All AJAX actions use nonces
- ✅ **Capability Checks**: Only admins can access the plugin
- ✅ **Input Sanitization**: All inputs are properly sanitized
- ✅ **Output Escaping**: All outputs are properly escaped
- ✅ **Prepared Statements**: Database queries use prepared statements to prevent SQL injection
- ✅ **Current User Tracking**: Deletion actions track which user performed them

## 🎯 Use Cases

### 1. Security Auditing
- Monitor unexpected external connections
- Identify suspicious third-party requests
- Audit plugin and theme network activity

### 2. Performance Optimization
- Identify slow external requests
- Block unnecessary third-party services
- Analyze request patterns and frequency

### 3. Compliance
- Track external data transfers
- Maintain audit trail of blocked hosts
- Document source of requests

### 4. Development
- Debug request issues
- Verify plugin behavior
- Monitor API usage

## 📊 API Reference

### Database Class Methods

```php
// Get paginated requests
ERM_Database::get_requests([
    'filter' => 'all|blocked|allowed',
    'search' => 'search term',
    'per_page' => 25,
    'paged' => 1
]);

// Get single request details
ERM_Database::get_request_detail($id);

// Get status counts
ERM_Database::count_by_status();

// Update block status
ERM_Database::update_request_blocked($id, true/false);

// Delete request
ERM_Database::delete_request($id, true);

// Bulk operations
ERM_Database::bulk_action($ids, 'block|unblock|delete|restore');

// Clear logs
ERM_Database::clear_all_logs($except_blocked = false);

// Cleanup old logs
ERM_Database::cleanup_old_logs($days = 30);
```

### Hooks

#### Filters
```php
// Modify whether a request should be blocked
apply_filters('erm_pro_is_blocked', $is_blocked, $host, $url);

// Modify request before logging
apply_filters('erm_pro_before_log', $log_data, $host, $url, $args);
```

#### Actions
```php
// After request is logged
do_action('erm_pro_after_log', $request_id, $host, $url);

// After logs are cleared
do_action('erm_pro_after_clear', $mode);

// During cleanup
do_action('erm_pro_cleanup', $deleted_count);
```

## 🐛 Troubleshooting

### Requests not being logged
- Ensure plugin is activated
- Check WordPress logs for errors
- Verify database tables were created during installation

### Settings not saving
- Check user has manage_options capability
- Verify database write permissions
- Clear browser cache and try again

### High memory usage
- Reduce log retention period
- Enable auto-cleanup
- Reduce items per page in settings

## 📝 Changelog

### Version 2.0.0 - 2024
- Complete rewrite with modular architecture
- Added request source detection (plugin/theme)
- Implemented professional control panel
- Added rate limiting support
- Improved database schema
- Added soft delete functionality
- Professional UI with statistics cards
- Advanced filtering and search
- Customizable columns and pagination
- AJAX-powered bulk operations
- Comprehensive request details modal

### Version 1.2.1 - Previous
- Basic request monitoring and blocking
- Simple log management

## 📜 License

This plugin is licensed under the GPL-2.0+ license. See LICENSE file for details.

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📧 Support

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/yourusername/external-request-manager-pro/issues) page.

## 👨‍💻 Author

**Yousuf Bahrami**
- Website: https://wcoq.com/
- GitHub: @yourusername

---

**Made with ❤️ for WordPress developers**

