<?php
/**
 * Helper functions for Restaurant Table Booking plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get a setting value from the database
 */
function rr_get_setting($key, $default = '') {
    global $wpdb;
    
    if (empty($key)) {
        return $default;
    }
    
    $table = $wpdb->prefix . 'rr_settings';
    
    // Check if table exists first
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return $default;
    }
    
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM $table WHERE setting_key = %s",
        sanitize_text_field($key)
    ));
    
    return ($value !== null) ? $value : $default;
}

/**
 * Update a setting value in the database
 */
function rr_update_setting($key, $value) {
    global $wpdb;
    
    if (empty($key)) {
        return false;
    }
    
    $table = $wpdb->prefix . 'rr_settings';
    
    // Check if table exists first
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return false;
    }
    
    $result = $wpdb->replace(
        $table,
        array(
            'setting_key' => sanitize_text_field($key),
            'setting_value' => wp_kses_post($value)
        ),
        array('%s', '%s')
    );
    
    return $result !== false;
}

/**
 * Format weekdays CSV into readable string
 */
function rr_format_weekdays($weekdays_csv) {
    if (empty($weekdays_csv)) {
        return '';
    }
    
    $days = array(
        0 => __('Sunday', 'rr-table-booking'),
        1 => __('Monday', 'rr-table-booking'),
        2 => __('Tuesday', 'rr-table-booking'),
        3 => __('Wednesday', 'rr-table-booking'),
        4 => __('Thursday', 'rr-table-booking'),
        5 => __('Friday', 'rr-table-booking'),
        6 => __('Saturday', 'rr-table-booking')
    );
    
    $weekdays = array_map('trim', explode(',', $weekdays_csv));
    $formatted = array();
    
    foreach ($weekdays as $day) {
        $day = intval($day);
        if (isset($days[$day])) {
            $formatted[] = $days[$day];
        }
    }
    
    return implode(', ', $formatted);
}

/**
 * Get short weekday names
 */
function rr_get_short_weekdays() {
    return array(
        0 => __('Sun', 'rr-table-booking'),
        1 => __('Mon', 'rr-table-booking'),
        2 => __('Tue', 'rr-table-booking'),
        3 => __('Wed', 'rr-table-booking'),
        4 => __('Thu', 'rr-table-booking'),
        5 => __('Fri', 'rr-table-booking'),
        6 => __('Sat', 'rr-table-booking')
    );
}

/**
 * Sanitize and validate party size
 */
function rr_sanitize_party_size($party_size) {
    $party_size = intval($party_size);
    $min_size = intval(rr_get_setting('min_party_size', 1));
    $max_size = intval(rr_get_setting('max_party_size', 12));
    
    if ($party_size < $min_size) {
        return $min_size;
    }
    
    if ($party_size > $max_size) {
        return $max_size;
    }
    
    return $party_size;
}

/**
 * Format date for display
 */
function rr_format_date($date, $format = null) {
    if (empty($date)) {
        return '';
    }
    
    if (!$format) {
        $format = get_option('date_format', 'Y-m-d');
    }
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    
    if (!$timestamp) {
        return $date; // Return original if can't parse
    }
    
    return date_i18n($format, $timestamp);
}

/**
 * Format time for display
 */
function rr_format_time($time, $format = null) {
    if (empty($time)) {
        return '';
    }
    
    if (!$format) {
        $format = get_option('time_format', 'H:i');
    }
    
    $timestamp = is_numeric($time) ? $time : strtotime($time);
    
    if (!$timestamp) {
        return $time; // Return original if can't parse
    }
    
    return date_i18n($format, $timestamp);
}

/**
 * Generate secure token
 */
function rr_generate_token($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } else {
        return wp_generate_password($length, false, false);
    }
}

/**
 * Log errors (for debugging)
 */
function rr_log_error($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('RR Booking Error: ' . $message);
        if ($data) {
            error_log('Data: ' . print_r($data, true));
        }
    }
}

/**
 * Cleanup expired bookings - CRON JOB
 */
function rr_cleanup_expired_bookings($booking_id = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'rr_bookings';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        return;
    }
    
    $hold_timeout = intval(rr_get_setting('hold_timeout', 30));
    
    if ($booking_id) {
        // Cleanup specific booking
        $result = $wpdb->update(
            $table,
            array('status' => 'expired'),
            array(
                'id' => intval($booking_id),
                'status' => 'pending'
            ),
            array('%s'),
            array('%d', '%s')
        );
        
        if ($result === false) {
            rr_log_error('Failed to expire booking', $booking_id);
        }
    } else {
        // Cleanup all expired bookings
        $expiry_time = gmdate('Y-m-d H:i:s', time() - ($hold_timeout * 60));
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'expired' 
             WHERE status = 'pending' AND created_at < %s",
            $expiry_time
        ));
        
        if ($result === false) {
            rr_log_error('Failed to cleanup expired bookings');
        }
    }
}

// Register cleanup cron job
add_action('rr_booking_cleanup', 'rr_cleanup_expired_bookings');