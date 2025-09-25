<?php
/**
 * Core booking functionality for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_Bookings {
    
    private $table_bookings;
    private $table_tables;
    private $table_shifts;
    
    public function __construct() {
        global $wpdb;
        $this->table_bookings = $wpdb->prefix . 'rr_bookings';
        $this->table_tables = $wpdb->prefix . 'rr_tables';
        $this->table_shifts = $wpdb->prefix . 'rr_shifts';
    }
    
    /**
     * Create a new booking
     */
    public function create_booking($data, $is_admin = false) {
        global $wpdb;
        
        // Validate required fields
        $validation_errors = $this->validate_booking_data($data);
        if (!empty($validation_errors)) {
            return new WP_Error('validation_failed', implode(', ', $validation_errors));
        }
        
        // Check table existence
        if (!$this->verify_tables_exist()) {
            return new WP_Error('tables_missing', __('Database tables not found.', 'rr-table-booking'));
        }
        
        // Validate availability (skip for admin bookings)
        if (!$is_admin && !$this->is_slot_available($data['date'], $data['time'], $data['party_size'])) {
            return new WP_Error('not_available', __('This time slot is not available.', 'rr-table-booking'));
        }
        
        // Generate secure confirmation token
        $confirmation_token = $this->generate_confirmation_token();
        
        // Prepare booking data
        $booking_data = array(
            'customer_name' => sanitize_text_field($data['customer_name']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'date' => sanitize_text_field($data['date']),
            'time' => $this->normalize_time($data['time']),
            'party_size' => intval($data['party_size']),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'status' => $is_admin ? 'confirmed' : 'pending',
            'confirmation_token' => $confirmation_token,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Insert booking
        $result = $wpdb->insert($this->table_bookings, $booking_data, array(
            '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
        ));
        
        if ($result === false) {
            rr_log_error('Failed to insert booking', $wpdb->last_error);
            return new WP_Error('db_error', __('Failed to create booking.', 'rr-table-booking'));
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Handle post-creation tasks
        $this->handle_post_booking_creation($booking_id, $is_admin);
        
        do_action('rr_booking_created', $booking_id, $booking_data);
        
        return $booking_id;
    }
    
    /**
     * Confirm a booking
     */
    public function confirm_booking($booking_id, $is_admin = false) {
        global $wpdb;
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('not_found', __('Booking not found.', 'rr-table-booking'));
        }
        
        if ($booking->status !== 'pending') {
            return new WP_Error('invalid_status', __('Booking cannot be confirmed.', 'rr-table-booking'));
        }
        
        // Assign table if not already assigned
        $table_id = $booking->table_id;
        if (!$table_id) {
            $table_id = $this->assign_table($booking->date, $booking->time, $booking->party_size);
            if (!$table_id) {
                return new WP_Error('no_table', __('No suitable table available.', 'rr-table-booking'));
            }
        }
        
        // Update booking status and table assignment
        $result = $wpdb->update(
            $this->table_bookings,
            array(
                'status' => 'confirmed',
                'table_id' => $table_id,
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($booking_id)),
            array('%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            rr_log_error('Failed to confirm booking', array('booking_id' => $booking_id, 'error' => $wpdb->last_error));
            return new WP_Error('db_error', __('Failed to confirm booking.', 'rr-table-booking'));
        }
        
        // Send confirmation email
        if (class_exists('RR_Email')) {
            $email = new RR_Email();
            $email->send_confirmation($booking_id);
        }
        
        // Clear any cleanup schedules
        wp_clear_scheduled_hook('rr_booking_cleanup', array($booking_id));
        
        do_action('rr_booking_confirmed', $booking_id);
        
        return true;
    }
    
    /**
     * Confirm booking by token
     */
    public function confirm_booking_by_token($token) {
        global $wpdb;
        
        if (empty($token)) {
            return new WP_Error('invalid_token', __('Invalid confirmation token.', 'rr-table-booking'));
        }
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM $this->table_bookings WHERE confirmation_token = %s",
            sanitize_text_field($token)
        ));
        
        if (!$booking) {
            return new WP_Error('invalid_token', __('Invalid or expired confirmation token.', 'rr-table-booking'));
        }
        
        return $this->confirm_booking($booking->id);
    }
    
    /**
     * Cancel a booking
     */
    public function cancel_booking($booking_id, $is_admin = false) {
        global $wpdb;
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('not_found', __('Booking not found.', 'rr-table-booking'));
        }
        
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return new WP_Error('invalid_status', __('Booking cannot be cancelled.', 'rr-table-booking'));
        }
        
        // Update booking status
        $result = $wpdb->update(
            $this->table_bookings,
            array(
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($booking_id)),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            rr_log_error('Failed to cancel booking', array('booking_id' => $booking_id, 'error' => $wpdb->last_error));
            return new WP_Error('db_error', __('Failed to cancel booking.', 'rr-table-booking'));
        }
        
        // Send cancellation email
        if (class_exists('RR_Email')) {
            $email = new RR_Email();
            $email->send_cancellation($booking_id);
        }
        
        do_action('rr_booking_cancelled', $booking_id);
        
        return true;
    }
    
    /**
     * Cancel booking by token
     */
    public function cancel_booking_by_token($token) {
        global $wpdb;
        
        if (empty($token)) {
            return new WP_Error('invalid_token', __('Invalid token.', 'rr-table-booking'));
        }
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM $this->table_bookings WHERE confirmation_token = %s",
            sanitize_text_field($token)
        ));
        
        if (!$booking) {
            return new WP_Error('invalid_token', __('Invalid or expired token.', 'rr-table-booking'));
        }
        
        return $this->cancel_booking($booking->id);
    }
    
    /**
     * Update booking status
     */
    public function update_booking_status($booking_id, $status) {
        global $wpdb;
        
        $valid_statuses = array('pending', 'confirmed', 'cancelled', 'expired', 'seated', 'no_show');
        if (!in_array($status, $valid_statuses)) {
            return new WP_Error('invalid_status', __('Invalid booking status.', 'rr-table-booking'));
        }
        
        $result = $wpdb->update(
            $this->table_bookings,
            array(
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => intval($booking_id)),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update booking status.', 'rr-table-booking'));
        }
        
        do_action('rr_booking_status_updated', $booking_id, $status);
        
        return $result !== 0; // Return true if rows were affected
    }
    
    /**
     * Get available time slots for a date and party size
     */
    public function get_available_slots($date, $party_size) {
        global $wpdb;
        
        if (!$this->verify_tables_exist()) {
            return array();
        }
        
        // Validate inputs
        if (!$this->validate_date($date) || intval($party_size) <= 0) {
            return array();
        }
        
        $day_of_week = date('w', strtotime($date));
        
        // Get applicable shifts for this day
        $shifts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $this->table_shifts 
             WHERE FIND_IN_SET(%s, weekdays) > 0 
             AND max_party_size >= %d
             ORDER BY start_time",
            $day_of_week,
            intval($party_size)
        ));
        
        if (empty($shifts)) {
            return array();
        }
        
        $all_slots = array();
        
        foreach ($shifts as $shift) {
            $shift_slots = $this->generate_time_slots($shift, $date, $party_size);
            $all_slots = array_merge($all_slots, $shift_slots);
        }
        
        // Remove duplicates and sort
        $all_slots = array_unique($all_slots);
        sort($all_slots);
        
        return $all_slots;
    }
    
    /**
     * Generate time slots for a shift
     */
    private function generate_time_slots($shift, $date, $party_size) {
        $slots = array();
        
        try {
            $start_time = strtotime($date . ' ' . $shift->start_time);
            $end_time = strtotime($date . ' ' . $shift->end_time);
            $slot_interval = intval($shift->slot_length_minutes) * 60;
            $buffer_time = intval($shift->buffer_minutes) * 60;
            
            // Generate slots with buffer consideration
            for ($time = $start_time; $time < ($end_time - $buffer_time); $time += $slot_interval) {
                $slot_time = date('H:i:s', $time);
                
                // Check if slot is available and not in the past
                if ($this->is_slot_available($date, $slot_time, $party_size) && 
                    $this->is_future_slot($date, $slot_time)) {
                    $slots[] = date('H:i', $time);
                }
            }
            
        } catch (Exception $e) {
            rr_log_error('Error generating time slots: ' . $e->getMessage(), $shift);
        }
        
        return $slots;
    }
    
    /**
     * Check if a time slot is available
     */
    private function is_slot_available($date, $time, $party_size) {
        global $wpdb;
        
        if (!$this->verify_tables_exist()) {
            return false;
        }
        
        $pooled_seating = rr_get_setting('pooled_seating', 'no') === 'yes';
        
        if ($pooled_seating) {
            return $this->check_pooled_availability($date, $time, $party_size);
        } else {
            return $this->check_table_availability($date, $time, $party_size);
        }
    }
    
    /**
     * Check pooled seating availability
     */
    private function check_pooled_availability($date, $time, $party_size) {
        global $wpdb;
        
        // Get total restaurant capacity
        $total_capacity = $wpdb->get_var(
            "SELECT SUM(capacity) FROM $this->table_tables WHERE status = 'active'"
        );
        
        if (!$total_capacity) {
            return false;
        }
        
        // Get current bookings for this slot
        $booked_capacity = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(party_size), 0) FROM $this->table_bookings 
             WHERE date = %s AND time = %s AND status IN ('pending', 'confirmed')",
            $date,
            $time
        ));
        
        return ($total_capacity - intval($booked_capacity)) >= intval($party_size);
    }
    
    /**
     * Check individual table availability
     */
    private function check_table_availability($date, $time, $party_size) {
        global $wpdb;
        
        $available_tables = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.capacity FROM $this->table_tables t
             WHERE t.status = 'active' 
             AND t.capacity >= %d
             AND t.id NOT IN (
                 SELECT COALESCE(table_id, 0) FROM $this->table_bookings 
                 WHERE date = %s AND time = %s 
                 AND status IN ('pending', 'confirmed')
                 AND table_id IS NOT NULL
             )
             ORDER BY t.capacity ASC",
            intval($party_size),
            $date,
            $time
        ));
        
        return !empty($available_tables);
    }
    
    /**
     * Check if slot is in the future
     */
    private function is_future_slot($date, $time) {
        $slot_timestamp = strtotime($date . ' ' . $time);
        $current_timestamp = current_time('timestamp');
        
        // Add 1 hour buffer for current day bookings
        $buffer_minutes = ($date === current_time('Y-m-d')) ? 60 : 0;
        
        return $slot_timestamp > ($current_timestamp + ($buffer_minutes * 60));
    }
    
    /**
     * Assign a table to a booking
     */
    private function assign_table($date, $time, $party_size) {
        global $wpdb;
        
        // Find best fitting table (smallest that fits the party)
        $available_tables = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.capacity, t.name FROM $this->table_tables t
             WHERE t.status = 'active' 
             AND t.capacity >= %d
             AND t.id NOT IN (
                 SELECT COALESCE(table_id, 0) FROM $this->table_bookings 
                 WHERE date = %s AND time = %s 
                 AND status IN ('pending', 'confirmed')
                 AND table_id IS NOT NULL
             )
             ORDER BY t.capacity ASC, t.id ASC
             LIMIT 1",
            intval($party_size),
            $date,
            $time
        ));
        
        return $available_tables ? intval($available_tables[0]->id) : null;
    }
    
    /**
     * Get a booking by ID
     */
    public function get_booking($booking_id) {
        global $wpdb;
        
        if (!$this->verify_tables_exist()) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_bookings WHERE id = %d",
            intval($booking_id)
        ));
    }
    
    /**
     * Get bookings by date
     */
    public function get_bookings_by_date($date) {
        global $wpdb;
        
        if (!$this->verify_tables_exist()) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, t.name as table_name 
             FROM $this->table_bookings b
             LEFT JOIN $this->table_tables t ON b.table_id = t.id
             WHERE b.date = %s 
             ORDER BY b.time ASC, b.created_at ASC",
            $date
        ));
    }
    
    /**
     * Get table name by ID
     */
    public function get_table_name($table_id) {
        global $wpdb;
        
        if (!$table_id || !$this->verify_tables_exist()) {
            return '';
        }
        
        $table = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM $this->table_tables WHERE id = %d",
            intval($table_id)
        ));
        
        return $table ? $table->name : '';
    }
    
    /**
     * Search bookings
     */
    public function search_bookings($search_term, $limit = 20, $offset = 0) {
        global $wpdb;
        
        if (!$this->verify_tables_exist()) {
            return array();
        }
        
        $search_term = '%' . $wpdb->esc_like(sanitize_text_field($search_term)) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, t.name as table_name 
             FROM $this->table_bookings b
             LEFT JOIN $this->table_tables t ON b.table_id = t.id
             WHERE b.customer_name LIKE %s 
             OR b.email LIKE %s 
             OR b.phone LIKE %s
             ORDER BY b.created_at DESC
             LIMIT %d OFFSET %d",
            $search_term,
            $search_term,
            $search_term,
            intval($limit),
            intval($offset)
        ));
    }
    
    /**
     * Validation and helper methods
     */
    private function validate_booking_data($data) {
        $errors = array();
        
        // Required fields
        $required_fields = array('customer_name', 'email', 'phone', 'date', 'time', 'party_size');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('%s is required.', 'rr-table-booking'), ucwords(str_replace('_', ' ', $field)));
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !is_email($data['email'])) {
            $errors[] = __('Please enter a valid email address.', 'rr-table-booking');
        }
        
        // Date validation
        if (!empty($data['date']) && !$this->validate_date($data['date'])) {
            $errors[] = __('Please enter a valid date.', 'rr-table-booking');
        }
        
        // Time validation
        if (!empty($data['time']) && !$this->validate_time($data['time'])) {
            $errors[] = __('Please enter a valid time.', 'rr-table-booking');
        }
        
        // Party size validation
        if (!empty($data['party_size'])) {
            $party_size = intval($data['party_size']);
            $min_size = intval(rr_get_setting('min_party_size', 1));
            $max_size = intval(rr_get_setting('max_party_size', 12));
            
            if ($party_size < $min_size || $party_size > $max_size) {
                $errors[] = sprintf(
                    __('Party size must be between %d and %d.', 'rr-table-booking'),
                    $min_size,
                    $max_size
                );
            }
        }
        
        return $errors;
    }
    
    private function validate_date($date) {
        if (empty($date)) return false;
        
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        return $date_obj && $date_obj->format('Y-m-d') === $date;
    }
    
    private function validate_time($time) {
        if (empty($time)) return false;
        
        return preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $time);
    }
    
    private function normalize_time($time) {
        // Ensure time is in HH:MM:SS format
        if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $time)) {
            return $time . ':00';
        }
        return $time;
    }
    
    private function generate_confirmation_token() {
        return wp_generate_password(32, false, false);
    }
    
    private function verify_tables_exist() {
        global $wpdb;
        
        $tables = array($this->table_bookings, $this->table_tables, $this->table_shifts);
        
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                rr_log_error('Missing database table: ' . $table);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Handle post-booking creation tasks
     */
    private function handle_post_booking_creation($booking_id, $is_admin) {
        if ($is_admin) {
            // Admin bookings are automatically confirmed
            return;
        }
        
        // Send confirmation request email
        if (class_exists('RR_Email')) {
            $email = new RR_Email();
            $email->send_pending_confirmation($booking_id);
        }
        
        // Schedule cleanup for unconfirmed bookings
        $hold_timeout = intval(rr_get_setting('hold_timeout', 30));
        if ($hold_timeout > 0) {
            wp_schedule_single_event(
                time() + ($hold_timeout * 60),
                'rr_booking_cleanup',
                array($booking_id)
            );
        }
    }
    
    /**
     * Get booking statistics
     */
    public function get_booking_stats($start_date = null, $end_date = null) {
        global $wpdb;
        
        if (!$this->verify_tables_exist()) {
            return array();
        }
        
        $where_clause = '';
        $params = array();
        
        if ($start_date && $end_date) {
            $where_clause = 'WHERE date BETWEEN %s AND %s';
            $params = array($start_date, $end_date);
        }
        
        $query = "SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(party_size) as total_guests,
                    AVG(party_size) as avg_party_size
                  FROM $this->table_bookings 
                  $where_clause
                  GROUP BY status";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Clean up expired bookings
     */
    public function cleanup_expired_bookings($booking_id = null) {
        global $wpdb;
        
        if (!$this->verify_tables_exist()) {
            return;
        }
        
        $hold_timeout = intval(rr_get_setting('hold_timeout', 30));
        
        if ($booking_id) {
            // Clean up specific booking
            $result = $wpdb->update(
                $this->table_bookings,
                array('status' => 'expired', 'updated_at' => current_time('mysql')),
                array('id' => intval($booking_id), 'status' => 'pending'),
                array('%s', '%s'),
                array('%d', '%s')
            );
            
            do_action('rr_booking_expired', $booking_id);
        } else {
            // Clean up all expired bookings
            $expiry_time = date('Y-m-d H:i:s', time() - ($hold_timeout * 60));
            
            $expired_bookings = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $this->table_bookings 
                 WHERE status = 'pending' AND created_at < %s",
                $expiry_time
            ));
            
            if (!empty($expired_bookings)) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $this->table_bookings 
                     SET status = 'expired', updated_at = %s 
                     WHERE status = 'pending' AND created_at < %s",
                    current_time('mysql'),
                    $expiry_time
                ));
                
                foreach ($expired_bookings as $booking_id) {
                    do_action('rr_booking_expired', $booking_id);
                }
            }
        }
    }
}