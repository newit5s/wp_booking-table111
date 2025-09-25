<?php
/**
 * Plugin Name: Restaurant Booking Manager
 * Plugin URI: https://yourwebsite.com
 * Description: Quản lý đặt bàn nhà hàng hoàn chỉnh
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * Text Domain: restaurant-booking-manager
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Define plugin constants
define('RBM_VERSION', '1.0.0');
define('RBM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RBM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('RBM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'RBM_') === 0) {
        $class_file = strtolower(str_replace('RBM_', '', $class));
        $class_file = str_replace('_', '-', $class_file);
        $file = RBM_PLUGIN_PATH . 'includes/class-' . $class_file . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Include core files
require_once RBM_PLUGIN_PATH . 'includes/class-restaurant-booking-manager.php';
require_once RBM_PLUGIN_PATH . 'includes/functions.php';

// Initialize the plugin
function rbm_init() {
    return RestaurantBookingManager::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'rbm_init', 10);

// Activation hook
register_activation_hook(__FILE__, function() {
    rbm_init();
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Uninstall hook
register_uninstall_hook(__FILE__, array('RestaurantBookingManager', 'uninstall'));