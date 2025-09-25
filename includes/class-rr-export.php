<?php
/**
 * Export functionality for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_Export {
    
    /**
     * Export bookings to CSV format
     */
    public function export_csv($start_date, $end_date, $status = '') {
        global $wpdb;
        
        // Validate dates
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            return new WP_Error('invalid_date', __('Invalid date format provided.', 'rr-table-booking'));
        }
        
        // Check if tables exist
        $bookings_table = $wpdb->prefix . 'rr_bookings';
        $tables_table = $wpdb->prefix . 'rr_tables';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return new WP_Error('table_missing', __('Bookings table not found.', 'rr-table-booking'));
        }
        
        try {
            // Build query with optional status filter
            $where_conditions = array("b.date BETWEEN %s AND %s");
            $query_params = array($start_date, $end_date);
            
            if (!empty($status) && in_array($status, $this->get_valid_statuses())) {
                $where_conditions[] = "b.status = %s";
                $query_params[] = $status;
            }
            
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            
            $query = "SELECT b.*, t.name as table_name 
                     FROM $bookings_table b
                     LEFT JOIN $tables_table t ON b.table_id = t.id
                     $where_clause
                     ORDER BY b.date ASC, b.time ASC";
            
            $bookings = $wpdb->get_results($wpdb->prepare($query, $query_params));
            
            if ($wpdb->last_error) {
                rr_log_error('CSV Export Query Error: ' . $wpdb->last_error);
                return new WP_Error('query_error', __('Database query failed.', 'rr-table-booking'));
            }
            
            return $this->generate_csv_from_bookings($bookings);
            
        } catch (Exception $e) {
            rr_log_error('CSV Export Error: ' . $e->getMessage());
            return new WP_Error('export_error', __('Failed to export bookings.', 'rr-table-booking'));
        }
    }
    
    /**
     * Generate CSV content from bookings data
     */
    private function generate_csv_from_bookings($bookings) {
        $csv_data = array();
        
        // CSV Headers
        $csv_data[] = array(
            __('ID', 'rr-table-booking'),
            __('Date', 'rr-table-booking'),
            __('Time', 'rr-table-booking'),
            __('Customer Name', 'rr-table-booking'),
            __('Email', 'rr-table-booking'),
            __('Phone', 'rr-table-booking'),
            __('Party Size', 'rr-table-booking'),
            __('Table', 'rr-table-booking'),
            __('Status', 'rr-table-booking'),
            __('Notes', 'rr-table-booking'),
            __('Created At', 'rr-table-booking'),
            __('Updated At', 'rr-table-booking')
        );
        
        // Add booking data
        foreach ($bookings as $booking) {
            $csv_data[] = array(
                intval($booking->id),
                esc_html($booking->date),
                esc_html($booking->time),
                esc_html($booking->customer_name),
                esc_html($booking->email),
                esc_html($booking->phone),
                intval($booking->party_size),
                esc_html($booking->table_name ?: __('TBD', 'rr-table-booking')),
                esc_html(ucfirst(str_replace('_', ' ', $booking->status))),
                esc_html($booking->notes ?: ''),
                esc_html($booking->created_at),
                esc_html($booking->updated_at ?: $booking->created_at)
            );
        }
        
        return $this->generate_csv($csv_data);
    }
    
    /**
     * Generate CSV string from array data
     */
    private function generate_csv($data) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://memory', 'w');
        
        if (!$output) {
            return new WP_Error('memory_error', __('Failed to create CSV in memory.', 'rr-table-booking'));
        }
        
        // Add BOM for UTF-8 Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");
        
        foreach ($data as $row) {
            if (fputcsv($output, $row) === false) {
                fclose($output);
                return new WP_Error('write_error', __('Failed to write CSV data.', 'rr-table-booking'));
            }
        }
        
        fseek($output, 0);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Send CSV file as download
     */
    public function download_csv($start_date, $end_date, $status = '') {
        // Check permissions
        if (!current_user_can('rr_manage_bookings')) {
            wp_die(__('Insufficient permissions to export bookings.', 'rr-table-booking'));
        }
        
        $csv_content = $this->export_csv($start_date, $end_date, $status);
        
        if (is_wp_error($csv_content)) {
            wp_die($csv_content->get_error_message());
        }
        
        // Generate filename
        $filename = $this->generate_filename($start_date, $end_date, $status);
        
        // Set headers for download
        $this->set_download_headers($filename, strlen($csv_content));
        
        echo $csv_content;
        exit;
    }
    
    /**
     * Generate filename for CSV export
     */
    private function generate_filename($start_date, $end_date, $status = '') {
        $date_part = date('Y-m-d', strtotime($start_date));
        if ($start_date !== $end_date) {
            $date_part .= '_to_' . date('Y-m-d', strtotime($end_date));
        }
        
        $status_part = !empty($status) ? '_' . sanitize_file_name($status) : '';
        
        return sprintf(
            'bookings_%s%s_%s.csv',
            $date_part,
            $status_part,
            date('Y-m-d_H-i-s')
        );
    }
    
    /**
     * Set HTTP headers for CSV download
     */
    private function set_download_headers($filename, $content_length) {
        // Clear any existing output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $content_length);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        header('Pragma: no-cache');
        
        // Prevent caching
        nocache_headers();
    }
    
    /**
     * Export bookings for a specific table
     */
    public function export_table_bookings($table_id, $start_date, $end_date) {
        global $wpdb;
        
        if (!current_user_can('rr_manage_bookings')) {
            return new WP_Error('permission_denied', __('Insufficient permissions.', 'rr-table-booking'));
        }
        
        $table_id = intval($table_id);
        if ($table_id <= 0) {
            return new WP_Error('invalid_table', __('Invalid table ID.', 'rr-table-booking'));
        }
        
        $bookings_table = $wpdb->prefix . 'rr_bookings';
        $tables_table = $wpdb->prefix . 'rr_tables';
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, t.name as table_name 
             FROM $bookings_table b
             LEFT JOIN $tables_table t ON b.table_id = t.id
             WHERE b.table_id = %d 
             AND b.date BETWEEN %s AND %s
             ORDER BY b.date ASC, b.time ASC",
            $table_id,
            $start_date,
            $end_date
        ));
        
        return $this->generate_csv_from_bookings($bookings);
    }
    
    /**
     * Export summary statistics
     */
    public function export_summary($start_date, $end_date) {
        global $wpdb;
        
        if (!current_user_can('rr_manage_bookings')) {
            return new WP_Error('permission_denied', __('Insufficient permissions.', 'rr-table-booking'));
        }
        
        $bookings_table = $wpdb->prefix . 'rr_bookings';
        
        // Get summary data
        $summary = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                status,
                COUNT(*) as count,
                SUM(party_size) as total_guests,
                AVG(party_size) as avg_party_size
             FROM $bookings_table 
             WHERE date BETWEEN %s AND %s
             GROUP BY status
             ORDER BY status",
            $start_date,
            $end_date
        ));
        
        $csv_data = array();
        
        // Headers
        $csv_data[] = array(
            __('Status', 'rr-table-booking'),
            __('Count', 'rr-table-booking'),
            __('Total Guests', 'rr-table-booking'),
            __('Average Party Size', 'rr-table-booking')
        );
        
        // Data rows
        foreach ($summary as $row) {
            $csv_data[] = array(
                esc_html(ucfirst(str_replace('_', ' ', $row->status))),
                intval($row->count),
                intval($row->total_guests),
                round(floatval($row->avg_party_size), 2)
            );
        }
        
        return $this->generate_csv($csv_data);
    }
    
    /**
     * Validate date format (YYYY-MM-DD)
     */
    private function validate_date($date) {
        if (empty($date)) {
            return false;
        }
        
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        return $date_obj && $date_obj->format('Y-m-d') === $date;
    }
    
    /**
     * Get valid booking statuses
     */
    private function get_valid_statuses() {
        return array('pending', 'confirmed', 'cancelled', 'expired', 'seated', 'no_show');
    }
    
    /**
     * Handle AJAX export request
     */
    public static function handle_ajax_export() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rr_export_nonce')) {
            wp_die(__('Security check failed', 'rr-table-booking'));
        }
        
        // Check permissions
        if (!current_user_can('rr_manage_bookings')) {
            wp_die(__('Insufficient permissions', 'rr-table-booking'));
        }
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $status = sanitize_text_field($_POST['status']);
        
        $exporter = new self();
        $exporter->download_csv($start_date, $end_date, $status);
    }
}