<?php
/**
 * Plugin Name: External Request Manager Pro
 * Plugin URI: https://github.com/YusufBahrami/external-request-manager-pro
 * Description: Advanced external HTTP requests monitor, blocker & manager with professional UI, detailed analytics, and comprehensive controls.
 * Version: 2.0.0
 * Author: Yusuf Bahrami
 * Author URI: https://wcoq.com/
 * License: GPL-2.0+
 * Text Domain: erm-pro
 * Domain Path: /languages
 * GitHub Plugin URI: YusufBahrami/external-request-manager-pro
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// ────────────────────────────────────────────────
// Define Constants
// ────────────────────────────────────────────────
define('ERM_PRO_VERSION', '2.0.0');
define('ERM_PRO_DIR', plugin_dir_path(__FILE__));
define('ERM_PRO_URL', plugin_dir_url(__FILE__));
define('ERM_PRO_FILE', __FILE__);
define('ERM_PRO_TABLE_REQUESTS', 'external_requests');
define('ERM_PRO_TABLE_DELETED', 'external_requests_deleted');
define('ERM_PRO_OPTION_GROUP', 'erm_pro_settings');

// ────────────────────────────────────────────────
// Load Core Files
// ────────────────────────────────────────────────
require_once ERM_PRO_DIR . 'includes/helpers.php';
require_once ERM_PRO_DIR . 'includes/class-database.php';
require_once ERM_PRO_DIR . 'includes/class-request-logger.php';
require_once ERM_PRO_DIR . 'includes/class-admin-pages.php';
require_once ERM_PRO_DIR . 'includes/class-settings.php';
require_once ERM_PRO_DIR . 'includes/class-ajax.php';

// ────────────────────────────────────────────────
// Plugin Activation & Deactivation
// ────────────────────────────────────────────────
register_activation_hook(ERM_PRO_FILE, ['ERM_Database', 'install']);
register_deactivation_hook(ERM_PRO_FILE, ['ERM_Database', 'deactivate']);

// ────────────────────────────────────────────────
// Initialize Plugin
// ────────────────────────────────────────────────
add_action('plugins_loaded', function() {
    load_plugin_textdomain('erm-pro', false, dirname(plugin_basename(ERM_PRO_FILE)) . '/languages');
    
    // Initialize classes
    if (is_admin()) {
        ERM_Admin_Pages::init();
        ERM_Settings::init();
        ERM_AJAX::init();
    }
    
    ERM_Request_Logger::init();
});

// ────────────────────────────────────────────────
// Plugin Action Links
// ────────────────────────────────────────────────
add_filter('plugin_action_links_' . plugin_basename(ERM_PRO_FILE), function($links) {
    array_unshift($links, '<a href="' . admin_url('admin.php?page=erm-pro-logs') . '">Dashboard</a>');
    array_push($links, '<a href="' . admin_url('admin.php?page=erm-pro-settings') . '">Settings</a>');
    return $links;
});

// ────────────────────────────────────────────────
// Load Text Domain
// ────────────────────────────────────────────────
add_action('init', function() {
    load_plugin_textdomain('erm-pro', false, dirname(plugin_basename(ERM_PRO_FILE)) . '/languages');
});
?>
