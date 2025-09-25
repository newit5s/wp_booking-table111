<?php
/**
 * Plugin activation handler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_Activator {
    
    /**
     * Plugin activation callback
     */
    public static function activate() {
        try {
            self::create_tables();
            self::add_capabilities();
            self::create_default_settings();
            self::schedule_cleanup_cron();
            
            // Update database version
            update_option('rr_booking_db_version', RR_BOOKING_VERSION);
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
        } catch (Exception $e) {
            // Log error and show admin notice
            error_log('RR Booking Activation Error: ' . $e->getMessage());
            
            // Store error for admin notice
            update_option('rr_booking_activation_error', $e->getMessage());
            
            // Deactivate plugin
            deactivate_plugins(plugin_basename(RR_BOOKING_PLUGIN_FILE));
            
            wp_die(
                esc_html__('Restaurant Table Booking activation failed: ', 'rr-table-booking') . 
                esc_html($e->getMessage()),
                esc_html__('Plugin Activation Error', 'rr-table-booking'),
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        $tables_created = array();
        
        // Tables table
        $table_name = $wpdb->prefix . 'rr_tables';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            capacity int(11) NOT NULL DEFAULT 2,
            status varchar(20) NOT NULL DEFAULT 'active',
            location_zone varchar(100) DEFAULT '',
            notes text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY capacity (capacity)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        $tables_created[] = $table_name;
        
        // Shifts table
        $table_name = $wpdb->prefix . 'rr_shifts';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            weekdays varchar(20) NOT NULL DEFAULT '',
            start_time time NOT NULL,
            end_time time NOT NULL,
            slot_length_minutes int(11) NOT NULL DEFAULT 30,
            buffer_minutes int(11) NOT NULL DEFAULT 15,
            max_party_size int(11) NOT NULL DEFAULT 8,
            timezone varchar(50) DEFAULT 'UTC',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY weekdays (weekdays),
            KEY start_time (start_time),
            KEY end_time (end_time)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        $tables_created[] = $table_name;
        
        // Bookings table
        $table_name = $wpdb->prefix . 'rr_bookings';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(200) NOT NULL,
            phone varchar(50) NOT NULL,
            email varchar(100) NOT NULL,
            date date NOT NULL,
            time time NOT NULL,
            party_size int(11) NOT NULL,
            table_id mediumint(9) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            notes text DEFAULT '',
            confirmation_token varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date_time (date, time),
            KEY status (status),
            KEY table_id (table_id),
            KEY confirmation_token (confirmation_token),
            KEY email (email)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        $tables_created[] = $table_name;
        
        // Settings table
        $table_name = $wpdb->prefix . 'rr_settings';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        $result = dbDelta($sql);
        $tables_created[] = $table_name;
        
        // Verify tables were created
        foreach ($tables_created as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                throw new Exception("Failed to create table: $table");
            }
        }
        
        // Create sample data
        self::create_sample_data();
    }
    
    /**
     * Add capabilities to roles
     */
    private static function add_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('rr_manage_bookings');
            $admin_role->add_cap('rr_manage_settings');
            $admin_role->add_cap('rr_manage_tables');
            $admin_role->add_cap('rr_manage_shifts');
        }
        
        // Create restaurant manager role if it doesn't exist
        if (!get_role('restaurant_manager')) {
            add_role('restaurant_manager', __('Restaurant Manager', 'rr-table-booking'), array(
                'read' => true,
                'rr_manage_bookings' => true,
                'rr_manage_tables' => true,
                'rr_manage_shifts' => true
            ));
        }
    }
    
    /**
     * Create default settings
     */
    private static function create_default_settings() {
        $defaults = array(
            'business_timezone' => get_option('timezone_string', 'UTC'),
            'min_party_size' => '1',
            'max_party_size' => '12',
            'hold_timeout' => '30',
            'pooled_seating' => 'no',
            'email_from_name' => get_bloginfo('name'),
            'email_from_address' => get_option('admin_email'),
            'confirmation_email_subject' => __('Your booking is confirmed - {{date}} at {{time}}', 'rr-table-booking'),
            'confirmation_email_body' => self::get_default_email_template('confirmation'),
            'cancellation_email_subject' => __('Your booking has been cancelled', 'rr-table-booking'),
            'cancellation_email_body' => self::get_default_email_template('cancellation'),
            'pending_email_subject' => __('Booking confirmation required - {{date}} at {{time}}', 'rr-table-booking'),
            'pending_email_body' => self::get_default_email_template('pending'),
            'sms_enabled' => 'no',
            'sms_provider' => 'twilio',
            'twilio_sid' => '',
            'twilio_token' => '',
            'twilio_from' => ''
        );
        
        foreach ($defaults as $key => $value) {
            if (!rr_update_setting($key, $value)) {
                throw new Exception("Failed to create default setting: $key");
            }
        }
    }
    
    /**
     * Schedule cleanup cron job
     */
    private static function schedule_cleanup_cron() {
        if (!wp_next_scheduled('rr_booking_cleanup')) {
            $scheduled = wp_schedule_event(time(), 'hourly', 'rr_booking_cleanup');
            if ($scheduled === false) {
                throw new Exception('Failed to schedule cleanup cron job');
            }
        }
    }
    
    /**
     * Create sample data for testing
     */
    private static function create_sample_data() {
        global $wpdb;
        
        // Sample tables
        $tables_table = $wpdb->prefix . 'rr_tables';
        $sample_tables = array(
            array('name' => 'Table 1', 'capacity' => 2, 'location_zone' => 'Main Dining'),
            array('name' => 'Table 2', 'capacity' => 4, 'location_zone' => 'Main Dining'),
            array('name' => 'Table 3', 'capacity' => 6, 'location_zone' => 'Private'),
            array('name' => 'Table 4', 'capacity' => 8, 'location_zone' => 'Outdoor')
        );
        
        foreach ($sample_tables as $table) {
            $wpdb->insert($tables_table, $table);
        }
        
        // Sample shift
        $shifts_table = $wpdb->prefix . 'rr_shifts';
        $sample_shifts = array(
            array(
                'name' => 'Lunch Service',
                'weekdays' => '1,2,3,4,5', // Mon-Fri
                'start_time' => '12:00:00',
                'end_time' => '15:00:00',
                'slot_length_minutes' => 30,
                'buffer_minutes' => 15,
                'max_party_size' => 8
            ),
            array(
                'name' => 'Dinner Service',
                'weekdays' => '0,1,2,3,4,5,6', // All days
                'start_time' => '17:00:00',
                'end_time' => '22:00:00',
                'slot_length_minutes' => 60,
                'buffer_minutes' => 15,
                'max_party_size' => 12
            )
        );
        
        foreach ($sample_shifts as $shift) {
            $wpdb->insert($shifts_table, $shift);
        }
    }
    
    /**
     * Get default email templates
     */
    private static function get_default_email_template($type) {
        switch ($type) {
            case 'confirmation':
                return '<h2>' . __('Booking Confirmed!', 'rr-table-booking') . '</h2>
<p>' . __('Dear {{customer_name}},', 'rr-table-booking') . '</p>
<p>' . __('Your reservation has been confirmed:', 'rr-table-booking') . '</p>
<ul>
    <li><strong>' . __('Date:', 'rr-table-booking') . '</strong> {{date}}</li>
    <li><strong>' . __('Time:', 'rr-table-booking') . '</strong> {{time}}</li>
    <li><strong>' . __('Party Size:', 'rr-table-booking') . '</strong> {{party_size}}</li>
    <li><strong>' . __('Table:', 'rr-table-booking') . '</strong> {{table_name}}</li>
</ul>
<p>' . __('If you need to make any changes, please contact us directly.', 'rr-table-booking') . '</p>
<p>' . __('We look forward to seeing you!', 'rr-table-booking') . '</p>';
                
            case 'cancellation':
                return '<h2>' . __('Booking Cancelled', 'rr-table-booking') . '</h2>
<p>' . __('Dear {{customer_name}},', 'rr-table-booking') . '</p>
<p>' . __('Your reservation for {{date}} at {{time}} has been cancelled.', 'rr-table-booking') . '</p>
<p>' . __('If you\'d like to make a new booking, please visit our website.', 'rr-table-booking') . '</p>';
                
            case 'pending':
                return '<h2>' . __('Please Confirm Your Booking', 'rr-table-booking') . '</h2>
<p>' . __('Dear {{customer_name}},', 'rr-table-booking') . '</p>
<p>' . __('Thank you for your reservation request:', 'rr-table-booking') . '</p>
<ul>
    <li><strong>' . __('Date:', 'rr-table-booking') . '</strong> {{date}}</li>
    <li><strong>' . __('Time:', 'rr-table-booking') . '</strong> {{time}}</li>
    <li><strong>' . __('Party Size:', 'rr-table-booking') . '</strong> {{party_size}}</li>
</ul>
<p>' . __('Please click the link below to confirm your booking:', 'rr-table-booking') . '</p>
<p><a href="{{booking_link}}">' . __('Confirm Booking', 'rr-table-booking') . '</a></p>
<p>' . __('This link will expire in 30 minutes.', 'rr-table-booking') . '</p>';
                
            default:
                return '';
        }
    }
}