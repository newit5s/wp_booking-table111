<?php
/**
 * ICS Calendar file generator for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_ICS {
    
    /**
     * Generate ICS calendar file content
     */
    public function generate($booking) {
        if (!$booking || !is_object($booking)) {
            return false;
        }
        
        // Validate required fields
        if (empty($booking->date) || empty($booking->time)) {
            return false;
        }
        
        try {
            // Set timezone
            $timezone = rr_get_setting('business_timezone', 'UTC');
            
            // Parse datetime with timezone handling
            $start_datetime = $this->parse_booking_datetime($booking->date, $booking->time, $timezone);
            if (!$start_datetime) {
                return false;
            }
            
            // Calculate end time (2 hours later by default)
            $end_datetime = clone $start_datetime;
            $end_datetime->add(new DateInterval('PT2H'));
            
            // Format for ICS (UTC format)
            $dtstart = $start_datetime->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
            $dtend = $end_datetime->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
            $dtstamp = gmdate('Ymd\THis\Z');
            
            // Generate unique ID
            $uid = $this->generate_uid($booking);
            
            // Prepare content
            $location = $this->escape_ics_text(get_bloginfo('name', 'display'));
            $summary = $this->escape_ics_text(
                sprintf(
                    __('Table reservation for %d people', 'rr-table-booking'),
                    intval($booking->party_size)
                )
            );
            
            $description = $this->generate_description($booking);
            
            // Build ICS content
            $ics = $this->build_ics_content($uid, $dtstamp, $dtstart, $dtend, $summary, $location, $description);
            
            return $ics;
            
        } catch (Exception $e) {
            rr_log_error('ICS Generation Error: ' . $e->getMessage(), $booking);
            return false;
        }
    }
    
    /**
     * Parse booking date and time into DateTime object
     */
    private function parse_booking_datetime($date, $time, $timezone = 'UTC') {
        try {
            $datetime_string = $date . ' ' . $time;
            $datetime = new DateTime($datetime_string, new DateTimeZone($timezone));
            return $datetime;
        } catch (Exception $e) {
            rr_log_error('DateTime parsing error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique identifier for the event
     */
    private function generate_uid($booking) {
        $site_url = get_site_url();
        $domain = parse_url($site_url, PHP_URL_HOST);
        
        if (!$domain) {
            $domain = 'localhost';
        }
        
        return sprintf(
            'booking-%d-%s@%s',
            intval($booking->id),
            gmdate('Ymd-His'),
            sanitize_title($domain)
        );
    }
    
    /**
     * Generate event description
     */
    private function generate_description($booking) {
        $details = array(
            __('Reservation Details:', 'rr-table-booking'),
            sprintf(__('Name: %s', 'rr-table-booking'), $booking->customer_name),
            sprintf(__('Party Size: %d', 'rr-table-booking'), intval($booking->party_size))
        );
        
        // Add table info if available
        if (!empty($booking->table_name)) {
            $details[] = sprintf(__('Table: %s', 'rr-table-booking'), $booking->table_name);
        } elseif (!empty($booking->table_id)) {
            $table_name = $this->get_table_name($booking->table_id);
            if ($table_name) {
                $details[] = sprintf(__('Table: %s', 'rr-table-booking'), $table_name);
            }
        }
        
        // Add notes if available
        if (!empty($booking->notes)) {
            $details[] = sprintf(__('Notes: %s', 'rr-table-booking'), $booking->notes);
        }
        
        // Add contact info
        if (!empty($booking->phone)) {
            $details[] = sprintf(__('Phone: %s', 'rr-table-booking'), $booking->phone);
        }
        
        if (!empty($booking->email)) {
            $details[] = sprintf(__('Email: %s', 'rr-table-booking'), $booking->email);
        }
        
        return $this->escape_ics_text(implode('\n', $details));
    }
    
    /**
     * Get table name by ID
     */
    private function get_table_name($table_id) {
        global $wpdb;
        
        if (empty($table_id)) {
            return '';
        }
        
        $table = $wpdb->prefix . 'rr_tables';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return '';
        }
        
        $name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $table WHERE id = %d",
            intval($table_id)
        ));
        
        return $name ? $name : '';
    }
    
    /**
     * Build the complete ICS content
     */
    private function build_ics_content($uid, $dtstamp, $dtstart, $dtend, $summary, $location, $description) {
        $ics_lines = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Restaurant Booking//NONSGML v1.0//EN',
            'METHOD:PUBLISH',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            'DTSTART:' . $dtstart,
            'DTEND:' . $dtend,
            'SUMMARY:' . $summary,
            'LOCATION:' . $location,
            'DESCRIPTION:' . $description,
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'SEQUENCE:0',
            'END:VEVENT',
            'END:VCALENDAR'
        );
        
        return implode("\r\n", $ics_lines) . "\r\n";
    }
    
    /**
     * Escape text for ICS format (RFC 5545)
     */
    private function escape_ics_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // Convert to string and strip tags
        $text = wp_strip_all_tags($text);
        
        // Escape special characters according to RFC 5545
        $text = str_replace("\\", "\\\\", $text);  // Backslash first
        $text = str_replace(",", "\\,", $text);    // Comma
        $text = str_replace(";", "\\;", $text);    // Semicolon
        $text = str_replace("\r\n", "\\n", $text); // Windows line breaks
        $text = str_replace("\n", "\\n", $text);   // Unix line breaks
        $text = str_replace("\r", "\\n", $text);   // Mac line breaks
        
        // Fold long lines (RFC 5545 recommends 75 chars)
        return $this->fold_ics_line($text);
    }
    
    /**
     * Fold long lines for ICS format compliance
     */
    private function fold_ics_line($text, $max_length = 70) {
        if (strlen($text) <= $max_length) {
            return $text;
        }
        
        $folded = '';
        $chunks = str_split($text, $max_length);
        
        for ($i = 0; $i < count($chunks); $i++) {
            if ($i === 0) {
                $folded .= $chunks[$i];
            } else {
                $folded .= "\r\n " . $chunks[$i]; // Leading space for continuation
            }
        }
        
        return $folded;
    }
    
    /**
     * Send ICS file as download
     */
    public function send_download($booking, $filename = null) {
        $ics_content = $this->generate($booking);
        
        if (!$ics_content) {
            wp_die(__('Error generating calendar file.', 'rr-table-booking'));
        }
        
        if (!$filename) {
            $filename = sprintf(
                'booking-%d-%s.ics',
                intval($booking->id),
                sanitize_file_name($booking->date)
            );
        }
        
        // Set headers for download
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($ics_content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $ics_content;
        exit;
    }
}