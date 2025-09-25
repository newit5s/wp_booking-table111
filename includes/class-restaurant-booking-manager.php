<?php
/**
 * Main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RestaurantBookingManager {
    
    private static $instance = null;
    private $database;
    private $admin;
    private $frontend;
    private $ajax;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->init_classes();
    }
    
    private function init_hooks() {
        // Plugin lifecycle hooks
        register_activation_hook(RBM_PLUGIN_PATH . 'restaurant-booking-manager.php', array($this, 'activate'));
        register_deactivation_hook(RBM_PLUGIN_PATH . 'restaurant-booking-manager.php', array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        
        // Settings link
        add_filter('plugin_action_links_' . RBM_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }
    
    private function init_classes() {
        // Initialize database handler
        $this->database = new RBM_Database();
        
        // Initialize admin interface
        if (is_admin()) {
            $this->admin = new RBM_Admin();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            $this->frontend = new RBM_Frontend();
        }
        
        // Initialize AJAX handlers
        $this->ajax = new RBM_Ajax();
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('restaurant-booking-manager', false, dirname(RBM_PLUGIN_BASENAME) . '/languages');
    }
    
    public function activate() {
        // Create database tables
        $this->database->create_tables();
        
        // Set default options
        add_option('rbm_restaurant_capacity', 50);
        add_option('rbm_version', RBM_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        wp_cache_flush();
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('rbm_daily_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public static function uninstall() {
        // Remove database tables and options
        $database = new RBM_Database();
        $database->remove_tables();
        
        // Remove options
        delete_option('rbm_restaurant_capacity');
        delete_option('rbm_version');
        
        // Clear cache
        wp_cache_flush();
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=restaurant-settings') . '">Cài đặt</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    // Getter methods for accessing classes
    public function get_database() {
        return $this->database;
    }
    
    public function get_admin() {
        return $this->admin;
    }
    
    public function get_frontend() {
        return $this->frontend;
    }
    
    public function get_ajax() {
        return $this->ajax;
    }
}