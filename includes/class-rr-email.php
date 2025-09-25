<?php
/**
 * Email functionality for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_Email {
    
    /**
     * Send booking confirmation email
     */
    public function send_confirmation($booking_id) {
        $booking = $this->get_booking_with_table($booking_id);
        if (!$booking) {
            rr_log_error('Confirmation email failed: booking not found', $booking_id);
            return false;
        }
        
        try {
            $subject = $this->replace_placeholders(
                rr_get_setting('confirmation_email_subject', __('Booking Confirmed', 'rr-table-booking')),
                $booking
            );
            
            $body = $this->replace_placeholders(
                rr_get_setting('confirmation_email_body', $this->get_default_confirmation_template()),
                $booking
            );
            
            // Generate ICS attachment
            $attachments = $this->prepare_ics_attachment($booking);
            
            // Prepare email headers
            $headers = $this->get_email_headers('confirmation');
            
            // Send customer email
            $result = wp_mail($booking->email, $subject, $body, $headers, $attachments);
            
            // Clean up ICS file
            $this->cleanup_attachments($attachments);
            
            // Send admin notification
            $this->send_admin_notification($booking, 'confirmed');
            
            // Log result
            if ($result) {
                rr_log_error('Confirmation email sent successfully to: ' . $booking->email);
            } else {
                rr_log_error('Failed to send confirmation email to: ' . $booking->email);
            }
            
            return $result;
            
        } catch (Exception $e) {
            rr_log_error('Email sending error: ' . $e->getMessage(), $booking);
            return false;
        }
    }
    
    /**
     * Send booking cancellation email
     */
    public function send_cancellation($booking_id) {
        $booking = $this->get_booking_with_table($booking_id);
        if (!$booking) {
            rr_log_error('Cancellation email failed: booking not found', $booking_id);
            return false;
        }
        
        try {
            $subject = $this->replace_placeholders(
                rr_get_setting('cancellation_email_subject', __('Booking Cancelled', 'rr-table-booking')),
                $booking
            );
            
            $body = $this->replace_placeholders(
                rr_get_setting('cancellation_email_body', $this->get_default_cancellation_template()),
                $booking
            );
            
            $headers = $this->get_email_headers('cancellation');
            
            $result = wp_mail($booking->email, $subject, $body, $headers);
            
            // Send admin notification
            $this->send_admin_notification($booking, 'cancelled');
            
            return $result;
            
        } catch (Exception $e) {
            rr_log_error('Cancellation email error: ' . $e->getMessage(), $booking);
            return false;
        }
    }
    
    /**
     * Send pending booking confirmation request
     */
    public function send_pending_confirmation($booking_id) {
        $booking = $this->get_booking_with_table($booking_id);
        if (!$booking) {
            return false;
        }
        
        try {
            $subject = $this->replace_placeholders(
                rr_get_setting('pending_email_subject', __('Please confirm your booking', 'rr-table-booking')),
                $booking
            );
            
            $body = $this->replace_placeholders(
                rr_get_setting('pending_email_body', $this->get_default_pending_template()),
                $booking
            );
            
            $headers = $this->get_email_headers('pending');
            
            return wp_mail($booking->email, $subject, $body, $headers);
            
        } catch (Exception $e) {
            rr_log_error('Pending email error: ' . $e->getMessage(), $booking);
            return false;
        }
    }
    
    /**
     * Send admin notification
     */
    private function send_admin_notification($booking, $type) {
        $admin_email = get_option('admin_email');
        
        if (empty($admin_email) || !is_email($admin_email)) {
            return false;
        }
        
        try {
            $subject = sprintf(
                __('[%s] Booking %s: %s - %s at %s', 'rr-table-booking'),
                get_bloginfo('name'),
                ucfirst($type),
                esc_html($booking->customer_name),
                esc_html($booking->date),
                esc_html($booking->time)
            );
            
            $body = $this->get_admin_notification_body($booking, $type);
            
            $headers = array(
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>'
            );
            
            return wp_mail($admin_email, $subject, $body, $headers);
            
        } catch (Exception $e) {
            rr_log_error('Admin notification error: ' . $e->getMessage(), $booking);
            return false;
        }
    }
    
    /**
     * Get admin notification body
     */
    private function get_admin_notification_body($booking, $type) {
        $lines = array(
            sprintf(__('A booking has been %s:', 'rr-table-booking'), $type),
            '',
            sprintf(__('Customer: %s', 'rr-table-booking'), $booking->customer_name),
            sprintf(__('Email: %s', 'rr-table-booking'), $booking->email),
            sprintf(__('Phone: %s', 'rr-table-booking'), $booking->phone),
            sprintf(__('Date: %s', 'rr-table-booking'), rr_format_date($booking->date)),
            sprintf(__('Time: %s', 'rr-table-booking'), rr_format_time($booking->time)),
            sprintf(__('Party Size: %d', 'rr-table-booking'), $booking->party_size),
            sprintf(__('Table: %s', 'rr-table-booking'), $booking->table_name ?: __('TBD', 'rr-table-booking')),
            sprintf(__('Status: %s', 'rr-table-booking'), ucfirst($booking->status))
        );
        
        if (!empty($booking->notes)) {
            $lines[] = sprintf(__('Notes: %s', 'rr-table-booking'), $booking->notes);
        }
        
        $lines[] = '';
        $lines[] = sprintf(
            __('Manage booking: %s', 'rr-table-booking'),
            admin_url('admin.php?page=rr-bookings-all&s=' . urlencode($booking->email))
        );
        
        return implode("\n", $lines);
    }
    
    /**
     * Prepare ICS attachment
     */
    private function prepare_ics_attachment($booking) {
        if (!class_exists('RR_ICS')) {
            return array();
        }
        
        try {
            $ics = new RR_ICS();
            $ics_content = $ics->generate($booking);
            
            if (!$ics_content) {
                return array();
            }
            
            // Create temporary file
            $upload_dir = wp_upload_dir();
            if (!$upload_dir['error']) {
                $ics_filename = 'booking-' . intval($booking->id) . '-' . time() . '.ics';
                $ics_filepath = $upload_dir['basedir'] . '/' . $ics_filename;
                
                if (file_put_contents($ics_filepath, $ics_content) !== false) {
                    return array($ics_filepath);
                }
            }
            
            return array();
            
        } catch (Exception $e) {
            rr_log_error('ICS attachment error: ' . $e->getMessage(), $booking);
            return array();
        }
    }
    
    /**
     * Clean up attachment files
     */
    private function cleanup_attachments($attachments) {
        if (empty($attachments)) {
            return;
        }
        
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Get email headers
     */
    private function get_email_headers($type = 'default') {
        $from_name = rr_get_setting('email_from_name', get_bloginfo('name'));
        $from_email = rr_get_setting('email_from_address', get_option('admin_email'));
        
        // Validate from email
        if (!is_email($from_email)) {
            $from_email = get_option('admin_email');
        }
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . wp_strip_all_tags($from_name) . ' <' . $from_email . '>'
        );
        
        return apply_filters('rr_email_headers', $headers, $type);
    }
    
    /**
     * Replace email template placeholders
     */
    private function replace_placeholders($template, $booking) {
        if (empty($template) || !$booking) {
            return $template;
        }
        
        // Generate booking management link
        $booking_link = $this->generate_booking_link($booking);
        
        // Prepare replacements
        $replacements = array(
            '{{customer_name}}' => esc_html($booking->customer_name),
            '{{date}}' => esc_html(rr_format_date($booking->date)),
            '{{time}}' => esc_html(rr_format_time($booking->time)),
            '{{party_size}}' => intval($booking->party_size),
            '{{table_name}}' => esc_html($booking->table_name ?: __('To be assigned', 'rr-table-booking')),
            '{{booking_link}}' => esc_url($booking_link),
            '{{booking_id}}' => intval($booking->id),
            '{{status}}' => esc_html(ucfirst(str_replace('_', ' ', $booking->status))),
            '{{restaurant_name}}' => esc_html(get_bloginfo('name')),
            '{{restaurant_email}}' => esc_html(get_option('admin_email')),
            '{{notes}}' => esc_html($booking->notes ?: __('None', 'rr-table-booking'))
        );
        
        // Add date/time in different formats
        $date_obj = DateTime::createFromFormat('Y-m-d', $booking->date);
        if ($date_obj) {
            $replacements['{{date_long}}'] = esc_html($date_obj->format('l, F j, Y'));
            $replacements['{{date_short}}'] = esc_html($date_obj->format('M j, Y'));
        }
        
        $time_obj = DateTime::createFromFormat('H:i:s', $booking->time);
        if ($time_obj) {
            $replacements['{{time_12h}}'] = esc_html($time_obj->format('g:i A'));
            $replacements['{{time_24h}}'] = esc_html($time_obj->format('H:i'));
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Generate booking management link
     */
    private function generate_booking_link($booking) {
        return add_query_arg(array(
            'rr_action' => 'manage',
            'token' => sanitize_text_field($booking->confirmation_token)
        ), home_url());
    }
    
    /**
     * Get booking with table information
     */
    private function get_booking_with_table($booking_id) {
        global $wpdb;
        
        $booking_id = intval($booking_id);
        if ($booking_id <= 0) {
            return null;
        }
        
        $bookings_table = $wpdb->prefix . 'rr_bookings';
        $tables_table = $wpdb->prefix . 'rr_tables';
        
        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return null;
        }
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, t.name as table_name 
             FROM $bookings_table b
             LEFT JOIN $tables_table t ON b.table_id = t.id
             WHERE b.id = %d",
            $booking_id
        ));
        
        if ($wpdb->last_error) {
            rr_log_error('Get booking error: ' . $wpdb->last_error);
            return null;
        }
        
        return $booking;
    }
    
    /**
     * Default email templates
     */
    private function get_default_confirmation_template() {
        return '<h2>' . __('Booking Confirmed!', 'rr-table-booking') . '</h2>
<p>' . __('Dear {{customer_name}},', 'rr-table-booking') . '</p>
<p>' . __('Your reservation has been confirmed:', 'rr-table-booking') . '</p>
<ul>
    <li><strong>' . __('Date:', 'rr-table-booking') . '</strong> {{date}}</li>
    <li><strong>' . __('Time:', 'rr-table-booking') . '</strong> {{time}}</li>
    <li><strong>' . __('Party Size:', 'rr-table-booking') . '</strong> {{party_size}}</li>
    <li><strong>' . __('Table:', 'rr-table-booking') . '</strong> {{table_name}}</li>
</ul>
<p>' . __('We look forward to seeing you!', 'rr-table-booking') . '</p>
<p>' . __('If you need to make any changes, please contact us directly.', 'rr-table-booking') . '</p>';
    }
    
    private function get_default_cancellation_template() {
        return '<h2>' . __('Booking Cancelled', 'rr-table-booking') . '</h2>
<p>' . __('Dear {{customer_name}},', 'rr-table-booking') . '</p>
<p>' . __('Your reservation for {{date}} at {{time}} has been cancelled.', 'rr-table-booking') . '</p>
<p>' . __('If you\'d like to make a new booking, please visit our website.', 'rr-table-booking') . '</p>';
    }
    
    private function get_default_pending_template() {
        return '<h2>' . __('Please Confirm Your Booking', 'rr-table-booking') . '</h2>
<p>' . __('Dear {{customer_name}},', 'rr-table-booking') . '</p>
<p>' . __('Thank you for your reservation request:', 'rr-table-booking') . '</p>
<ul>
    <li><strong>' . __('Date:', 'rr-table-booking') . '</strong> {{date}}</li>
    <li><strong>' . __('Time:', 'rr-table-booking') . '</strong> {{time}}</li>
    <li><strong>' . __('Party Size:', 'rr-table-booking') . '</strong> {{party_size}}</li>
</ul>
<p><a href="{{booking_link}}" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">' . __('Confirm Booking', 'rr-table-booking') . '</a></p>
<p><small>' . __('This link will expire in 30 minutes.', 'rr-table-booking') . '</small></p>';
    }
    
    /**
     * Test email configuration
     */
    public function test_email_settings() {
        if (!current_user_can('rr_manage_settings')) {
            return new WP_Error('permission_denied', __('Insufficient permissions.', 'rr-table-booking'));
        }
        
        $test_email = get_option('admin_email');
        $subject = __('[Test] Restaurant Booking Email Settings', 'rr-table-booking');
        $body = __('This is a test email to verify your booking system email settings are working correctly.', 'rr-table-booking');
        
        $headers = $this->get_email_headers('test');
        
        $result = wp_mail($test_email, $subject, $body, $headers);
        
        if ($result) {
            return array(
                'success' => true,
                'message' => __('Test email sent successfully.', 'rr-table-booking')
            );
        } else {
            return new WP_Error('email_failed', __('Failed to send test email.', 'rr-table-booking'));
        }
    }
}