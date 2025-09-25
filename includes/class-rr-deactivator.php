<?php
/**
 * Plugin deactivation handler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_Deactivator {
    
    /**
     * Plugin deactivation callback
     */
    public static function deactivate() {
        try {
            // Clear scheduled events
            self::clear_scheduled_events();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Clean up temporary data (optional)
            self::cleanup_temporary_data();
            
            // Log deactivation
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('RR Booking Plugin deactivated successfully');
            }
            
        } catch (Exception $e) {
            // Log error but don't stop deactivation
            error_log('RR Booking Deactivation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Clear all scheduled cron events
     */
    private static function clear_scheduled_events() {
        // Clear the main cleanup event
        wp_clear_scheduled_hook('rr_booking_cleanup');
        
        // Clear any individual booking cleanup events
        $cron_jobs = _get_cron_array();
        if (!empty($cron_jobs)) {
            foreach ($cron_jobs as $timestamp => $cron) {
                foreach ($cron as $hook => $jobs) {
                    if (strpos($hook, 'rr_booking_') === 0) {
                        wp_clear_scheduled_hook($hook);
                    }
                }
            }
        }
    }
    
    /**
     * Clean up temporary data (transients, expired tokens, etc.)
     */
    private static function cleanup_temporary_data() {
        global $wpdb;
        
        // Delete plugin transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rr_booking_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rr_booking_%'");
        
        // Clean up expired bookings (optional - mark as expired instead of delete)
        $bookings_table = $wpdb->prefix . 'rr_bookings';
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table) {
            $expiry_time = gmdate('Y-m-d H:i:s', time() - (30 * 60)); // 30 minutes ago
            
            $wpdb->query($wpdb->prepare(
                "UPDATE $bookings_table SET status = 'expired' 
                 WHERE status = 'pending' AND created_at < %s",
                $expiry_time
            ));
        }
    }
    
    /**
     * Remove plugin capabilities from roles (optional - usually keep for data integrity)
     */
    private static function remove_capabilities() {
        $roles = array('administrator', 'restaurant_manager');
        $capabilities = array(
            'rr_manage_bookings',
            'rr_manage_settings',
            'rr_manage_tables',
            'rr_manage_shifts'
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        
        // Note: We don't remove the 'restaurant_manager' role on deactivation
        // to preserve user accounts. This should only be done on uninstall.
    }
}