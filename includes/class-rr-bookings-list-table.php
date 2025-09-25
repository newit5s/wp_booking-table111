<?php
/**
 * Bookings List Table for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class RR_Bookings_List_Table extends WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'booking',
            'plural' => 'bookings',
            'ajax' => false
        ));
        
        // Process bulk actions
        $this->process_bulk_action();
    }
    
    /**
     * Get table columns
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', 'rr-table-booking'),
            'customer' => __('Customer', 'rr-table-booking'),
            'date_time' => __('Date & Time', 'rr-table-booking'),
            'party_size' => __('Party Size', 'rr-table-booking'),
            'table' => __('Table', 'rr-table-booking'),
            'status' => __('Status', 'rr-table-booking'),
            'created' => __('Created', 'rr-table-booking')
        );
    }
    
    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'id' => array('id', true),
            'date_time' => array('date', false),
            'status' => array('status', false),
            'created' => array('created_at', false),
            'party_size' => array('party_size', false)
        );
    }
    
    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        $actions = array();
        
        if (current_user_can('rr_manage_bookings')) {
            $actions['confirm'] = __('Confirm', 'rr-table-booking');
            $actions['cancel'] = __('Cancel', 'rr-table-booking');
            $actions['delete'] = __('Delete', 'rr-table-booking');
        }
        
        return $actions;
    }
    
    /**
     * Prepare table items
     */
    public function prepare_items() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rr_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $this->items = array();
            return;
        }
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        // Get search term
        $search = isset($_REQUEST['s']) ? trim(sanitize_text_field($_REQUEST['s'])) : '';
        
        // Get sorting
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        // Validate orderby
        $allowed_orderby = array('id', 'date', 'status', 'created_at', 'party_size', 'customer_name');
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'id';
        }
        
        // Validate order
        $order = strtoupper($order);
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'DESC';
        }
        
        // Build WHERE clause
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($search)) {
            $where_conditions[] = "(customer_name LIKE %s OR email LIKE %s OR phone LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Add status filter if provided
        if (isset($_REQUEST['status']) && !empty($_REQUEST['status'])) {
            $status = sanitize_text_field($_REQUEST['status']);
            $allowed_statuses = array('pending', 'confirmed', 'cancelled', 'expired', 'seated', 'no_show');
            
            if (in_array($status, $allowed_statuses)) {
                $where_conditions[] = "status = %s";
                $where_values[] = $status;
            }
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Get total items count
        $count_query = "SELECT COUNT(*) FROM $table" . $where_clause;
        
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        
        $total_items = $wpdb->get_var($count_query);
        
        // Get items
        $items_query = "SELECT b.*, t.name as table_name 
                       FROM $table b
                       LEFT JOIN {$wpdb->prefix}rr_tables t ON b.table_id = t.id
                       $where_clause
                       ORDER BY b.$orderby $order
                       LIMIT %d OFFSET %d";
        
        $query_values = $where_values;
        $query_values[] = $per_page;
        $query_values[] = $offset;
        
        $this->items = $wpdb->get_results($wpdb->prepare($items_query, $query_values));
        
        // Set pagination
        $this->set_pagination_args(array(
            'total_items' => intval($total_items),
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
        
        // Set column headers
        $this->_column_headers = array(
            $this->get_columns(),
            array(), // Hidden columns
            $this->get_sortable_columns()
        );
    }
    
    /**
     * Default column output
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return intval($item->id);
                
            case 'date_time':
                $date = rr_format_date($item->date);
                $time = rr_format_time($item->time);
                return esc_html($date . ' ' . $time);
                
            case 'party_size':
                return intval($item->party_size);
                
            case 'table':
                return $item->table_name ? esc_html($item->table_name) : '-';
                
            case 'status':
                return sprintf(
                    '<span class="rr-status rr-status-%s">%s</span>',
                    esc_attr($item->status),
                    esc_html(ucfirst(str_replace('_', ' ', $item->status)))
                );
                
            case 'created':
                return esc_html(rr_format_date($item->created_at, get_option('date_format') . ' ' . get_option('time_format')));
                
            default:
                return '';
        }
    }
    
    /**
     * Checkbox column
     */
    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="bookings[]" value="%d" />', intval($item->id));
    }
    
    /**
     * Customer column with row actions
     */
    public function column_customer($item) {
        $customer_info = sprintf(
            '<strong>%s</strong><br><small>%s | %s</small>',
            esc_html($item->customer_name),
            esc_html($item->email),
            esc_html($item->phone)
        );
        
        // Build row actions
        $actions = $this->get_row_actions($item);
        
        return $customer_info . $this->row_actions($actions);
    }
    
    /**
     * Get row actions for a booking
     */
    private function get_row_actions($item) {
        $actions = array();
        $nonce = wp_create_nonce('rr_booking_action');
        $page = sanitize_text_field($_REQUEST['page']);
        
        if (!current_user_can('rr_manage_bookings')) {
            return $actions;
        }
        
        // View action
        $actions['view'] = sprintf(
            '<a href="#" onclick="return false;">%s</a>',
            __('View', 'rr-table-booking')
        );
        
        // Status-specific actions
        if ($item->status === 'pending') {
            $actions['confirm'] = sprintf(
                '<a href="%s" class="rr-confirm-action">%s</a>',
                esc_url(admin_url("admin.php?page={$page}&action=confirm&booking={$item->id}&_wpnonce={$nonce}")),
                __('Confirm', 'rr-table-booking')
            );
        }
        
        if (in_array($item->status, ['pending', 'confirmed'])) {
            $actions['cancel'] = sprintf(
                '<a href="%s" class="rr-cancel-action" onclick="return confirm(\'%s\')">%s</a>',
                esc_url(admin_url("admin.php?page={$page}&action=cancel&booking={$item->id}&_wpnonce={$nonce}")),
                esc_js(__('Are you sure you want to cancel this booking?', 'rr-table-booking')),
                __('Cancel', 'rr-table-booking')
            );
        }
        
        if ($item->status === 'confirmed') {
            $actions['seated'] = sprintf(
                '<a href="%s" class="rr-seated-action">%s</a>',
                esc_url(admin_url("admin.php?page={$page}&action=seated&booking={$item->id}&_wpnonce={$nonce}")),
                __('Mark Seated', 'rr-table-booking')
            );
            
            $actions['no_show'] = sprintf(
                '<a href="%s" class="rr-no-show-action" onclick="return confirm(\'%s\')">%s</a>',
                esc_url(admin_url("admin.php?page={$page}&action=no_show&booking={$item->id}&_wpnonce={$nonce}")),
                esc_js(__('Are you sure this was a no-show?', 'rr-table-booking')),
                __('No Show', 'rr-table-booking')
            );
        }
        
        // Edit action
        $actions['edit'] = sprintf(
            '<a href="#" onclick="return false;">%s</a>',
            __('Edit', 'rr-table-booking')
        );
        
        // Delete action
        $actions['delete'] = sprintf(
            '<a href="%s" class="delete-link" onclick="return confirm(\'%s\')">%s</a>',
            esc_url(admin_url("admin.php?page={$page}&action=delete&booking={$item->id}&_wpnonce={$nonce}")),
            esc_js(__('Are you sure you want to delete this booking?', 'rr-table-booking')),
            __('Delete', 'rr-table-booking')
        );
        
        return $actions;
    }
    
    /**
     * Process bulk actions
     */
    public function process_bulk_action() {
        if (!current_user_can('rr_manage_bookings')) {
            return;
        }
        
        $action = $this->current_action();
        
        if (!$action) {
            return;
        }
        
        // Single item actions
        if (isset($_REQUEST['booking']) && isset($_REQUEST['_wpnonce'])) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'rr_booking_action')) {
                wp_die(__('Security check failed', 'rr-table-booking'));
            }
            
            $booking_id = intval($_REQUEST['booking']);
            $this->process_single_action($action, $booking_id);
            return;
        }
        
        // Bulk actions
        if (isset($_REQUEST['bookings']) && is_array($_REQUEST['bookings'])) {
            // Check bulk nonce
            check_admin_referer('bulk-' . $this->_args['plural']);
            
            $booking_ids = array_map('intval', $_REQUEST['bookings']);
            $this->process_bulk_items($action, $booking_ids);
        }
    }
    
    /**
     * Process single booking action
     */
    private function process_single_action($action, $booking_id) {
        if (!class_exists('RR_Bookings')) {
            $this->add_notice('error', __('Booking system unavailable.', 'rr-table-booking'));
            return;
        }
        
        $bookings = new RR_Bookings();
        $success = false;
        $message = '';
        
        switch ($action) {
            case 'confirm':
                $success = $bookings->confirm_booking($booking_id, true);
                $message = $success ? 
                    __('Booking confirmed successfully.', 'rr-table-booking') : 
                    __('Failed to confirm booking.', 'rr-table-booking');
                break;
                
            case 'cancel':
                $success = $bookings->cancel_booking($booking_id, true);
                $message = $success ? 
                    __('Booking cancelled successfully.', 'rr-table-booking') : 
                    __('Failed to cancel booking.', 'rr-table-booking');
                break;
                
            case 'seated':
                $success = $bookings->update_booking_status($booking_id, 'seated');
                $message = $success ? 
                    __('Booking marked as seated.', 'rr-table-booking') : 
                    __('Failed to update booking status.', 'rr-table-booking');
                break;
                
            case 'no_show':
                $success = $bookings->update_booking_status($booking_id, 'no_show');
                $message = $success ? 
                    __('Booking marked as no-show.', 'rr-table-booking') : 
                    __('Failed to update booking status.', 'rr-table-booking');
                break;
                
            case 'delete':
                $success = $this->delete_booking($booking_id);
                $message = $success ? 
                    __('Booking deleted successfully.', 'rr-table-booking') : 
                    __('Failed to delete booking.', 'rr-table-booking');
                break;
                
            default:
                $message = __('Invalid action.', 'rr-table-booking');
                break;
        }
        
        $this->add_notice($success ? 'success' : 'error', $message);
        
        // Redirect to prevent resubmission
        $redirect_url = remove_query_arg(array('action', 'booking', '_wpnonce'));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Process bulk action on multiple items
     */
    private function process_bulk_items($action, $booking_ids) {
        if (empty($booking_ids)) {
            return;
        }
        
        $success_count = 0;
        $total_count = count($booking_ids);
        
        foreach ($booking_ids as $booking_id) {
            switch ($action) {
                case 'confirm':
                    if (class_exists('RR_Bookings')) {
                        $bookings = new RR_Bookings();
                        if ($bookings->confirm_booking($booking_id, true)) {
                            $success_count++;
                        }
                    }
                    break;
                    
                case 'cancel':
                    if (class_exists('RR_Bookings')) {
                        $bookings = new RR_Bookings();
                        if ($bookings->cancel_booking($booking_id, true)) {
                            $success_count++;
                        }
                    }
                    break;
                    
                case 'delete':
                    if ($this->delete_booking($booking_id)) {
                        $success_count++;
                    }
                    break;
            }
        }
        
        // Show result message
        if ($success_count > 0) {
            $message = sprintf(
                _n(
                    'Successfully processed %d booking.',
                    'Successfully processed %d bookings.',
                    $success_count,
                    'rr-table-booking'
                ),
                $success_count
            );
            
            if ($success_count < $total_count) {
                $failed_count = $total_count - $success_count;
                $message .= ' ' . sprintf(
                    _n(
                        '%d booking failed to process.',
                        '%d bookings failed to process.',
                        $failed_count,
                        'rr-table-booking'
                    ),
                    $failed_count
                );
            }
            
            $this->add_notice('success', $message);
        } else {
            $this->add_notice('error', __('No bookings were processed.', 'rr-table-booking'));
        }
        
        // Redirect to prevent resubmission
        $redirect_url = remove_query_arg(array('action', 'bookings', '_wpnonce', 'action2'));
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Delete a booking
     */
    private function delete_booking($booking_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'rr_bookings';
        
        $result = $wpdb->delete(
            $table,
            array('id' => intval($booking_id)),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Add admin notice
     */
    private function add_notice($type, $message) {
        set_transient('rr_admin_notice', array(
            'type' => $type,
            'message' => $message
        ), 30);
    }
    
    /**
     * Display status filter dropdown
     */
    protected function extra_tablenav($which) {
        if ($which === 'top') {
            $current_status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
            ?>
            <div class="alignleft actions">
                <select name="status">
                    <option value=""><?php esc_html_e('All Statuses', 'rr-table-booking'); ?></option>
                    <option value="pending" <?php selected($current_status, 'pending'); ?>><?php esc_html_e('Pending', 'rr-table-booking'); ?></option>
                    <option value="confirmed" <?php selected($current_status, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'rr-table-booking'); ?></option>
                    <option value="seated" <?php selected($current_status, 'seated'); ?>><?php esc_html_e('Seated', 'rr-table-booking'); ?></option>
                    <option value="cancelled" <?php selected($current_status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'rr-table-booking'); ?></option>
                    <option value="no_show" <?php selected($current_status, 'no_show'); ?>><?php esc_html_e('No Show', 'rr-table-booking'); ?></option>
                    <option value="expired" <?php selected($current_status, 'expired'); ?>><?php esc_html_e('Expired', 'rr-table-booking'); ?></option>
                </select>
                <?php submit_button(__('Filter', 'rr-table-booking'), 'secondary', 'filter_action', false); ?>
            </div>
            <?php
        }
    }
    
    /**
     * Message when no items found
     */
    public function no_items() {
        esc_html_e('No bookings found.', 'rr-table-booking');
    }
}