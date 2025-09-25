<?php
/**
 * Admin interface class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBM_Admin {
    
    private $database;
    private $email;
    
    public function __construct() {
        $this->database = new RBM_Database();
        $this->email = new RBM_Email();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'restaurant-') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style('rbm-admin-style', RBM_PLUGIN_URL . 'assets/css/admin.css', array(), RBM_VERSION);
            wp_enqueue_script('rbm-admin-script', RBM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), RBM_VERSION, true);
        }
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Quản lý đặt bàn',
            'Đặt bàn',
            'manage_options',
            'restaurant-bookings',
            array($this, 'bookings_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'restaurant-bookings',
            'Danh sách đặt bàn',
            'Danh sách',
            'manage_options',
            'restaurant-bookings',
            array($this, 'bookings_page')
        );
        
        add_submenu_page(
            'restaurant-bookings',
            'Cài đặt nhà hàng',
            'Cài đặt',
            'manage_options',
            'restaurant-settings',
            array($this, 'settings_page')
        );
    }
    
    public function bookings_page() {
        // Handle booking actions
        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'rbm_admin_action')) {
            $booking_id = intval($_POST['booking_id']);
            
            if ($_POST['action'] === 'confirm_booking') {
                $this->database->update_booking_status($booking_id, 'confirmed');
                $this->email->send_customer_confirmation($booking_id);
                echo '<div class="notice notice-success"><p><strong>Thành công!</strong> Đã xác nhận đặt bàn và gửi email cho khách hàng.</p></div>';
                
            } elseif ($_POST['action'] === 'cancel_booking') {
                $booking = $this->database->get_booking($booking_id);
                
                if ($booking && $booking->status === 'pending') {
                    $this->database->update_booking_status($booking_id, 'cancelled');
                    // Return available seats
                    $this->database->update_available_seats($booking->booking_date, $booking->booking_time, $booking->party_size);
                    echo '<div class="notice notice-success"><p><strong>Thành công!</strong> Đã hủy đặt bàn và trả lại chỗ ngồi.</p></div>';
                }
            }
        }
        
        // Get filters
        $filters = array(
            'status' => isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all',
            'date' => isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field($_GET['search']) : ''
        );
        
        // Get bookings and stats
        $bookings = $this->database->get_bookings($filters);
        $stats = $this->database->get_booking_stats();
        
        // Include template
        include RBM_PLUGIN_PATH . 'templates/admin/bookings-page.php';
    }
    
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'rbm_settings')) {
            $capacity = intval($_POST['restaurant_capacity']);
            
            if ($capacity > 0 && $capacity <= 1000) {
                update_option('rbm_restaurant_capacity', $capacity);
                echo '<div class="notice notice-success"><p><strong>Thành công!</strong> Đã lưu cài đặt.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p><strong>Lỗi!</strong> Sức chứa phải từ 1 đến 1000.</p></div>';
            }
        }
        
        $capacity = get_option('rbm_restaurant_capacity', 50);
        
        // Include template
        include RBM_PLUGIN_PATH . 'templates/admin/settings-page.php';
    }
}