<?php
/**
 * Helper functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin instance
 */
function rbm_get_plugin() {
    return RestaurantBookingManager::get_instance();
}

/**
 * Get time slots for booking form
 */
function rbm_get_time_slots($start_time = '10:00', $end_time = '22:00', $interval = 30) {
    $slots = array();
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    $interval_seconds = $interval * 60; // Convert minutes to seconds
    
    for ($time = $start; $time <= $end; $time += $interval_seconds) {
        $time_str = date('H:i', $time);
        $slots[$time_str] = $time_str;
    }
    
    return $slots;
}

/**
 * Format booking status for display
 */
function rbm_format_booking_status($status) {
    $statuses = array(
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'cancelled' => 'Đã hủy'
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : 'Không xác định';
}

/**
 * Get status CSS class
 */
function rbm_get_status_class($status) {
    $classes = array(
        'pending' => 'status-pending',
        'confirmed' => 'status-confirmed',
        'cancelled' => 'status-cancelled'
    );
    
    return isset($classes[$status]) ? $classes[$status] : '';
}

/**
 * Format Vietnamese date
 */
function rbm_format_vietnamese_date($date) {
    $timestamp = strtotime($date);
    $day_names = array(
        'Sunday' => 'Chủ nhật',
        'Monday' => 'Thứ 2',
        'Tuesday' => 'Thứ 3', 
        'Wednesday' => 'Thứ 4',
        'Thursday' => 'Thứ 5',
        'Friday' => 'Thứ 6',
        'Saturday' => 'Thứ 7'
    );
    
    $day_name = date('l', $timestamp);
    $vietnamese_day = isset($day_names[$day_name]) ? $day_names[$day_name] : $day_name;
    
    return $vietnamese_day . ', ' . date('d/m/Y', $timestamp);
}

/**
 * Validate booking data
 */
function rbm_validate_booking_data($data) {
    $errors = array();
    
    // Required fields
    $required_fields = array(
        'customer_name' => 'Họ và tên',
        'customer_phone' => 'Số điện thoại',
        'customer_email' => 'Email',
        'party_size' => 'Số lượng khách',
        'booking_date' => 'Ngày đặt bàn',
        'booking_time' => 'Giờ đặt bàn'
    );
    
    foreach ($required_fields as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = $label . ' là bắt buộc.';
        }
    }
    
    // Email validation
    if (!empty($data['customer_email']) && !is_email($data['customer_email'])) {
        $errors[] = 'Email không hợp lệ.';
    }
    
    // Date validation
    if (!empty($data['booking_date']) && strtotime($data['booking_date']) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Ngày đặt bàn phải từ hôm nay trở đi.';
    }
    
    // Party size validation
    if (!empty($data['party_size']) && (intval($data['party_size']) <= 0 || intval($data['party_size']) > 20)) {
        $errors[] = 'Số lượng khách phải từ 1 đến 20 người.';
    }
    
    // Phone validation (basic)
    if (!empty($data['customer_phone']) && !preg_match('/^[0-9+\-\s]+$/', $data['customer_phone'])) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }
    
    return $errors;
}

/**
 * Sanitize booking data
 */
function rbm_sanitize_booking_data($data) {
    return array(
        'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
        'customer_phone' => sanitize_text_field($data['customer_phone'] ?? ''),
        'customer_email' => sanitize_email($data['customer_email'] ?? ''),
        'party_size' => intval($data['party_size'] ?? 0),
        'booking_date' => sanitize_text_field($data['booking_date'] ?? ''),
        'booking_time' => sanitize_text_field($data['booking_time'] ?? ''),
        'special_requests' => sanitize_textarea_field($data['special_requests'] ?? '')
    );
}

/**
 * Log booking activity
 */
function rbm_log_activity($message, $booking_id = null, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $log_message = '[RBM] ' . $message;
        if ($booking_id) {
            $log_message .= ' (Booking ID: ' . $booking_id . ')';
        }
        error_log($log_message);
    }
}

/**
 * Check if current user can manage bookings
 */
function rbm_current_user_can_manage() {
    return current_user_can('manage_options');
}

/**
 * Get plugin version
 */
function rbm_get_version() {
    return RBM_VERSION;
}

/**
 * Check if plugin needs update
 */
function rbm_needs_update() {
    $current_version = get_option('rbm_version');
    return version_compare($current_version, RBM_VERSION, '<');
}