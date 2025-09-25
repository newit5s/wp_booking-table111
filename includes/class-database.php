<?php
/**
 * Database handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBM_Database {
    
    public function __construct() {
        // Constructor
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table
        $bookings_table = $wpdb->prefix . 'restaurant_bookings';
        $sql_bookings = "CREATE TABLE $bookings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_email varchar(100) NOT NULL,
            party_size int(3) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            status varchar(20) DEFAULT 'pending',
            special_requests text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            confirmed_at datetime NULL,
            PRIMARY KEY (id),
            KEY booking_date (booking_date),
            KEY status (status),
            KEY customer_email (customer_email)
        ) $charset_collate;";
        
        // Daily capacity table
        $capacity_table = $wpdb->prefix . 'restaurant_daily_capacity';
        $sql_capacity = "CREATE TABLE $capacity_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_date date NOT NULL,
            time_slot time NOT NULL,
            available_seats int(3) NOT NULL,
            total_capacity int(3) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_date_time (booking_date, time_slot),
            KEY booking_date (booking_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_bookings);
        dbDelta($sql_capacity);
    }
    
    /**
     * Remove database tables
     */
    public function remove_tables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}restaurant_bookings");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}restaurant_daily_capacity");
    }
    
    /**
     * Insert new booking
     */
    public function insert_booking($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'restaurant_bookings';
        
        return $wpdb->insert(
            $table_name,
            array(
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'],
                'party_size' => $data['party_size'],
                'booking_date' => $data['booking_date'],
                'booking_time' => $data['booking_time'],
                'special_requests' => $data['special_requests'],
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get booking by ID
     */
    public function get_booking($booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'restaurant_bookings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d", $booking_id
        ));
    }
    
    /**
     * Update booking status
     */
    public function update_booking_status($booking_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'restaurant_bookings';
        
        $update_data = array('status' => $status);
        
        if ($status === 'confirmed') {
            $update_data['confirmed_at'] = current_time('mysql');
        }
        
        return $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $booking_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get bookings with filters
     */
    public function get_bookings($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'restaurant_bookings';
        
        $where_conditions = array();
        $where_values = array();
        
        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where_conditions[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        // Date filter
        if (!empty($filters['date'])) {
            $where_conditions[] = "booking_date = %s";
            $where_values[] = $filters['date'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $where_conditions[] = "(customer_name LIKE %s OR customer_phone LIKE %s OR customer_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
        
        if (empty($where_values)) {
            return $wpdb->get_results("SELECT * FROM $table_name $where_clause ORDER BY booking_date DESC, booking_time DESC LIMIT 100");
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name $where_clause ORDER BY booking_date DESC, booking_time DESC LIMIT 100",
                $where_values
            ));
        }
    }
    
    /**
     * Get booking statistics
     */
    public function get_booking_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'restaurant_bookings';
        
        return array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'"),
            'confirmed' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'confirmed'"),
            'today' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE booking_date = %s", date('Y-m-d')))
        );
    }
    
    /**
     * Check available seats
     */
    public function get_available_seats($date, $time) {
        global $wpdb;
        $capacity_table = $wpdb->prefix . 'restaurant_daily_capacity';
        
        $available_seats = $wpdb->get_var($wpdb->prepare(
            "SELECT available_seats FROM $capacity_table 
             WHERE booking_date = %s AND time_slot = %s",
            $date, $time
        ));
        
        if ($available_seats === null) {
            // Initialize capacity for this time slot
            $total_capacity = get_option('rbm_restaurant_capacity', 50);
            $wpdb->insert(
                $capacity_table,
                array(
                    'booking_date' => $date,
                    'time_slot' => $time,
                    'available_seats' => $total_capacity,
                    'total_capacity' => $total_capacity
                ),
                array('%s', '%s', '%d', '%d')
            );
            $available_seats = $total_capacity;
        }
        
        return intval($available_seats);
    }
    
    /**
     * Update available seats
     */
    public function update_available_seats($date, $time, $change) {
        global $wpdb;
        $capacity_table = $wpdb->prefix . 'restaurant_daily_capacity';
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE $capacity_table 
             SET available_seats = available_seats + %d 
             WHERE booking_date = %s AND time_slot = %s",
            $change, $date, $time
        ));
    }
    
    /**
     * Check table availability
     */
    public function check_table_availability($date, $time, $party_size) {
        $available_seats = $this->get_available_seats($date, $time);
        return $available_seats >= $party_size;
    }
}