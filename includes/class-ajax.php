<?php
/**
 * AJAX handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBM_Ajax {
    
    private $database;
    private $email;
    
    public function __construct() {
        $this->database = new RBM_Database();
        $this->email = new RBM_Email();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_check_availability', array($this, 'check_availability'));
    }
    
    public function check_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rbm_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $party_size = intval($_POST['party_size']);
        
        // Validate inputs
        if (empty($date) || empty($time) || $party_size <= 0) {
            wp_send_json_error(array('message' => 'Invalid input data.'));
        }
        
        $available_seats = $this->database->get_available_seats($date, $time);
        
        wp_send_json_success(array('available_seats' => $available_seats));
    }
    
    public function handle_booking_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rbm_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        // Sanitize and validate inputs
        $booking_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'party_size' => intval($_POST['party_size']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'booking_time' => sanitize_text_field($_POST['booking_time']),
            'special_requests' => sanitize_textarea_field($_POST['special_requests'])
        );
        
        // Validate required fields
        if (empty($booking_data['customer_name']) || empty($booking_data['customer_phone']) || 
            empty($booking_data['customer_email']) || empty($booking_data['party_size']) || 
            empty($booking_data['booking_date']) || empty($booking_data['booking_time'])) {
            wp_send_json_error(array('message' => 'Vui lòng điền đầy đủ thông tin bắt buộc.'));
        }
        
        // Validate email
        if (!is_email($booking_data['customer_email'])) {
            wp_send_json_error(array('message' => 'Email không hợp lệ.'));
        }
        
        // Validate date (must be today or future)
        if (strtotime($booking_data['booking_date']) < strtotime(date('Y-m-d'))) {
            wp_send_json_error(array('message' => 'Ngày đặt bàn phải từ hôm nay trở đi.'));
        }
        
        // Check availability
        if (!$this->database->check_table_availability($booking_data['booking_date'], $booking_data['booking_time'], $booking_data['party_size'])) {
            wp_send_json_error(array('message' => 'Không còn đủ chỗ trống trong khung giờ này.'));
        }
        
        // Insert booking
        if ($this->database->insert_booking($booking_data)) {
            global $wpdb;
            $booking_id = $wpdb->insert_id;
            
            // Update available seats
            $this->database->update_available_seats($booking_data['booking_date'], $booking_data['booking_time'], -$booking_data['party_size']);
            
            // Send notification email to admin
            $this->email->send_admin_notification($booking_id);
            
            wp_send_json_success(array(
                'message' => 'Đặt bàn thành công! Mã đặt bàn của bạn là #' . $booking_id . '. Chúng tôi sẽ xác nhận qua email trong thời gian sớm nhất.',
                'booking_id' => $booking_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Có lỗi xảy ra khi lưu thông tin. Vui lòng thử lại.'));
        }
    }
}