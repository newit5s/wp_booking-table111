<?php
/**
 * Admin functionality for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_rr_admin_action', array($this, 'handle_ajax'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Render all bookings page
     */
    public function render_all_bookings() {
        // Check if list table class exists
        $list_table_file = RR_BOOKING_PLUGIN_DIR . 'includes/class-rr-bookings-list-table.php';
        if (!file_exists($list_table_file)) {
            echo '<div class="wrap"><h1>' . __('All Bookings', 'rr-table-booking') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Bookings list table is not available.', 'rr-table-booking') . '</p></div>';
            echo '</div>';
            return;
        }
        
        require_once $list_table_file;
        
        if (!class_exists('RR_Bookings_List_Table')) {
            echo '<div class="wrap"><h1>' . __('All Bookings', 'rr-table-booking') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Bookings list table class not found.', 'rr-table-booking') . '</p></div>';
            echo '</div>';
            return;
        }
        
        $list_table = new RR_Bookings_List_Table();
        $list_table->prepare_items();
        
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('All Bookings', 'rr-table-booking'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rr-booking-new')); ?>" class="page-title-action">
                    <?php esc_html_e('Add New', 'rr-table-booking'); ?>
                </a>
            </h1>
            
            <form method="post">
                <?php $list_table->search_box(__('Search Bookings', 'rr-table-booking'), 'booking'); ?>
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render new booking page
     */
    public function render_new_booking() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('New Booking', 'rr-table-booking'); ?></h1>
            
            <form method="post" action="" id="rr-new-booking-form">
                <?php wp_nonce_field('rr_new_booking', 'rr_booking_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="customer_name"><?php esc_html_e('Customer Name', 'rr-table-booking'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="customer_name" name="customer_name" class="regular-text" required 
                                   value="<?php echo isset($_POST['customer_name']) ? esc_attr($_POST['customer_name']) : ''; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="email"><?php esc_html_e('Email', 'rr-table-booking'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="email" id="email" name="email" class="regular-text" required 
                                   value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="phone"><?php esc_html_e('Phone', 'rr-table-booking'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="tel" id="phone" name="phone" class="regular-text" required 
                                   value="<?php echo isset($_POST['phone']) ? esc_attr($_POST['phone']) : ''; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="date"><?php esc_html_e('Date', 'rr-table-booking'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="date" id="date" name="date" required min="<?php echo esc_attr(current_time('Y-m-d')); ?>"
                                   value="<?php echo isset($_POST['date']) ? esc_attr($_POST['date']) : ''; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="time"><?php esc_html_e('Time', 'rr-table-booking'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <select id="time" name="time" required>
                                <option value=""><?php esc_html_e('Select a time', 'rr-table-booking'); ?></option>
                                <?php $this->render_time_options(); ?>
                            </select>
                            <p class="description"><?php esc_html_e('Available times will update based on selected date and party size.', 'rr-table-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="party_size"><?php esc_html_e('Party Size', 'rr-table-booking'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <?php 
                            $min_party = intval(rr_get_setting('min_party_size', 1));
                            $max_party = intval(rr_get_setting('max_party_size', 12));
                            ?>
                            <input type="number" id="party_size" name="party_size" 
                                   min="<?php echo esc_attr($min_party); ?>" 
                                   max="<?php echo esc_attr($max_party); ?>" 
                                   required 
                                   value="<?php echo isset($_POST['party_size']) ? esc_attr($_POST['party_size']) : $min_party; ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="notes"><?php esc_html_e('Notes', 'rr-table-booking'); ?></label>
                        </th>
                        <td>
                            <textarea id="notes" name="notes" class="large-text" rows="4"><?php 
                                echo isset($_POST['notes']) ? esc_textarea($_POST['notes']) : ''; 
                            ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Create Booking', 'rr-table-booking'), 'primary', 'submit', true, array('id' => 'rr-create-booking')); ?>
            </form>
        </div>
        
        <style>
        .required { color: #d63638; }
        #rr-new-booking-form .form-table th { width: 200px; }
        </style>
        <?php
    }
    
    /**
     * Render time options for booking form
     */
    private function render_time_options() {
        // Generate basic time slots for manual booking
        $start_time = 9; // 9 AM
        $end_time = 22; // 10 PM
        $interval = 30; // 30 minutes
        
        for ($hour = $start_time; $hour < $end_time; $hour++) {
            for ($minute = 0; $minute < 60; $minute += $interval) {
                $time = sprintf('%02d:%02d:00', $hour, $minute);
                $display_time = sprintf('%02d:%02d', $hour, $minute);
                
                $selected = (isset($_POST['time']) && $_POST['time'] === $time) ? 'selected' : '';
                
                echo '<option value="' . esc_attr($time) . '" ' . $selected . '>' . esc_html($display_time) . '</option>';
            }
        }
    }
    
    /**
     * Render tables page
     */
    public function render_tables() {
        global $wpdb;
        
        $tables_table = $wpdb->prefix . 'rr_tables';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$tables_table'") !== $tables_table) {
            echo '<div class="wrap"><h1>' . __('Tables', 'rr-table-booking') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Tables database table not found.', 'rr-table-booking') . '</p></div>';
            echo '</div>';
            return;
        }
        
        $tables = $wpdb->get_results("SELECT * FROM $tables_table ORDER BY name");
        
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Tables', 'rr-table-booking'); ?>
                <a href="#" class="page-title-action" id="rr-add-table"><?php esc_html_e('Add New', 'rr-table-booking'); ?></a>
            </h1>
            
            <?php if (empty($tables)): ?>
                <p><?php esc_html_e('No tables found. Create your first table to get started.', 'rr-table-booking'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Capacity', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Location/Zone', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Status', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Actions', 'rr-table-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tables as $table): ?>
                        <tr>
                            <td><strong><?php echo esc_html($table->name); ?></strong></td>
                            <td><?php echo esc_html($table->capacity); ?> <?php esc_html_e('people', 'rr-table-booking'); ?></td>
                            <td><?php echo esc_html($table->location_zone ?: '-'); ?></td>
                            <td>
                                <span class="rr-status rr-status-<?php echo esc_attr($table->status); ?>">
                                    <?php echo esc_html(ucfirst($table->status)); ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small rr-edit-table" data-id="<?php echo esc_attr($table->id); ?>">
                                    <?php esc_html_e('Edit', 'rr-table-booking'); ?>
                                </button>
                                <button class="button button-small button-link-delete rr-delete-table" data-id="<?php echo esc_attr($table->id); ?>">
                                    <?php esc_html_e('Delete', 'rr-table-booking'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <?php $this->render_table_modal(); ?>
        <?php
    }
    
    /**
     * Render table add/edit modal
     */
    private function render_table_modal() {
        ?>
        <div id="rr-table-modal" class="rr-modal" style="display: none;">
            <div class="rr-modal-content">
                <span class="rr-modal-close">&times;</span>
                <h2><?php esc_html_e('Add/Edit Table', 'rr-table-booking'); ?></h2>
                
                <form id="rr-table-form">
                    <input type="hidden" id="table_id" name="table_id" value="" />
                    <?php wp_nonce_field('rr_table_action', 'rr_table_nonce'); ?>
                    
                    <p>
                        <label for="table_name"><strong><?php esc_html_e('Table Name', 'rr-table-booking'); ?> *</strong></label>
                        <input type="text" id="table_name" name="name" class="widefat" required />
                    </p>
                    
                    <p>
                        <label for="table_capacity"><strong><?php esc_html_e('Capacity', 'rr-table-booking'); ?> *</strong></label>
                        <input type="number" id="table_capacity" name="capacity" min="1" max="50" class="small-text" required />
                        <span class="description"><?php esc_html_e('Maximum number of people', 'rr-table-booking'); ?></span>
                    </p>
                    
                    <p>
                        <label for="table_location"><strong><?php esc_html_e('Location/Zone', 'rr-table-booking'); ?></strong></label>
                        <input type="text" id="table_location" name="location_zone" class="widefat" 
                               placeholder="<?php esc_attr_e('e.g., Main Dining, Patio, Private Room', 'rr-table-booking'); ?>" />
                    </p>
                    
                    <p>
                        <label for="table_status"><strong><?php esc_html_e('Status', 'rr-table-booking'); ?></strong></label>
                        <select id="table_status" name="status">
                            <option value="active"><?php esc_html_e('Active', 'rr-table-booking'); ?></option>
                            <option value="inactive"><?php esc_html_e('Inactive', 'rr-table-booking'); ?></option>
                        </select>
                    </p>
                    
                    <p>
                        <label for="table_notes"><strong><?php esc_html_e('Notes', 'rr-table-booking'); ?></strong></label>
                        <textarea id="table_notes" name="notes" class="widefat" rows="3" 
                                  placeholder="<?php esc_attr_e('Any special notes about this table...', 'rr-table-booking'); ?>"></textarea>
                    </p>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Table', 'rr-table-booking'); ?></button>
                        <button type="button" class="button rr-modal-close"><?php esc_html_e('Cancel', 'rr-table-booking'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .rr-modal {
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .rr-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #ccc;
            width: 80%;
            max-width: 600px;
            border-radius: 4px;
            position: relative;
        }
        .rr-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }
        .rr-modal-close:hover { color: #d63638; }
        .rr-status {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .rr-status-active { background: #00a32a; color: white; }
        .rr-status-inactive { background: #dba617; color: white; }
        .rr-status-pending { background: #72aee6; color: white; }
        .rr-status-confirmed { background: #00a32a; color: white; }
        .rr-status-cancelled { background: #d63638; color: white; }
        .rr-status-expired { background: #8c8f94; color: white; }
        </style>
        <?php
    }
    
    /**
     * Render shifts page
     */
    public function render_shifts() {
        global $wpdb;
        
        $shifts_table = $wpdb->prefix . 'rr_shifts';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$shifts_table'") !== $shifts_table) {
            echo '<div class="wrap"><h1>' . __('Shifts', 'rr-table-booking') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Shifts database table not found.', 'rr-table-booking') . '</p></div>';
            echo '</div>';
            return;
        }
        
        $shifts = $wpdb->get_results("SELECT * FROM $shifts_table ORDER BY name");
        
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Shifts', 'rr-table-booking'); ?>
                <a href="#" class="page-title-action" id="rr-add-shift"><?php esc_html_e('Add New', 'rr-table-booking'); ?></a>
            </h1>
            
            <?php if (empty($shifts)): ?>
                <p><?php esc_html_e('No shifts found. Create your first shift to define booking availability.', 'rr-table-booking'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Days', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Time', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Slot Length', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Max Party Size', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Actions', 'rr-table-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><strong><?php echo esc_html($shift->name); ?></strong></td>
                            <td><?php echo esc_html(rr_format_weekdays($shift->weekdays)); ?></td>
                            <td><?php echo esc_html(rr_format_time($shift->start_time) . ' - ' . rr_format_time($shift->end_time)); ?></td>
                            <td><?php echo esc_html($shift->slot_length_minutes . ' min'); ?></td>
                            <td><?php echo esc_html($shift->max_party_size); ?></td>
                            <td>
                                <button class="button button-small rr-edit-shift" data-id="<?php echo esc_attr($shift->id); ?>">
                                    <?php esc_html_e('Edit', 'rr-table-booking'); ?>
                                </button>
                                <button class="button button-small button-link-delete rr-delete-shift" data-id="<?php echo esc_attr($shift->id); ?>">
                                    <?php esc_html_e('Delete', 'rr-table-booking'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Settings', 'rr-table-booking'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('rr_table_booking_options'); ?>
                
                <h2 class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php esc_html_e('General', 'rr-table-booking'); ?></a>
                    <a href="#email" class="nav-tab" data-tab="email"><?php esc_html_e('Email', 'rr-table-booking'); ?></a>
                    <a href="#sms" class="nav-tab" data-tab="sms"><?php esc_html_e('SMS', 'rr-table-booking'); ?></a>
                </h2>
                
                <div id="general" class="tab-content active">
                    <?php do_settings_sections('rr_settings_general'); ?>
                </div>
                
                <div id="email" class="tab-content">
                    <?php $this->render_email_settings(); ?>
                </div>
                
                <div id="sms" class="tab-content">
                    <?php $this->render_sms_settings(); ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').removeClass('active');
                $('#' + tab).addClass('active');
            });
        });
        </script>
        
        <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        </style>
        <?php
    }
    
    /**
     * General settings section callback
     */
    public function general_settings_callback() {
        echo '<p>' . esc_html__('Configure general booking system settings.', 'rr-table-booking') . '</p>';
    }
    
    /**
     * Render email settings section
     */
    private function render_email_settings() {
        $from_name = rr_get_setting('email_from_name', get_bloginfo('name'));
        $from_address = rr_get_setting('email_from_address', get_option('admin_email'));
        $confirmation_subject = rr_get_setting('confirmation_email_subject');
        $confirmation_body = rr_get_setting('confirmation_email_body');
        ?>
        <h3><?php esc_html_e('Email Settings', 'rr-table-booking'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('From Name', 'rr-table-booking'); ?></th>
                <td>
                    <input type="text" name="email_from_name" class="regular-text" 
                           value="<?php echo esc_attr($from_name); ?>" />
                    <p class="description"><?php esc_html_e('Name displayed in outgoing emails', 'rr-table-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('From Email', 'rr-table-booking'); ?></th>
                <td>
                    <input type="email" name="email_from_address" class="regular-text" 
                           value="<?php echo esc_attr($from_address); ?>" />
                    <p class="description"><?php esc_html_e('Email address for outgoing emails', 'rr-table-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Confirmation Email Subject', 'rr-table-booking'); ?></th>
                <td>
                    <input type="text" name="confirmation_email_subject" class="large-text" 
                           value="<?php echo esc_attr($confirmation_subject); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Confirmation Email Body', 'rr-table-booking'); ?></th>
                <td>
                    <?php 
                    wp_editor($confirmation_body, 'confirmation_email_body', array(
                        'textarea_rows' => 10,
                        'media_buttons' => false,
                        'teeny' => true
                    )); 
                    ?>
                    <p class="description">
                        <?php esc_html_e('Available placeholders:', 'rr-table-booking'); ?>
                        <code>{{customer_name}}</code>, <code>{{date}}</code>, <code>{{time}}</code>, 
                        <code>{{party_size}}</code>, <code>{{table_name}}</code>, <code>{{booking_link}}</code>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render SMS settings section
     */
    private function render_sms_settings() {
        $sms_enabled = rr_get_setting('sms_enabled', 'no');
        $twilio_sid = rr_get_setting('twilio_sid', '');
        $twilio_token = rr_get_setting('twilio_token', '');
        $twilio_from = rr_get_setting('twilio_from', '');
        ?>
        <h3><?php esc_html_e('SMS Settings', 'rr-table-booking'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable SMS Notifications', 'rr-table-booking'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sms_enabled" value="yes" <?php checked($sms_enabled, 'yes'); ?> />
                        <?php esc_html_e('Send SMS notifications for bookings', 'rr-table-booking'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Twilio Account SID', 'rr-table-booking'); ?></th>
                <td>
                    <input type="text" name="twilio_sid" class="regular-text" 
                           value="<?php echo esc_attr($twilio_sid); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Twilio Auth Token', 'rr-table-booking'); ?></th>
                <td>
                    <input type="password" name="twilio_token" class="regular-text" 
                           value="<?php echo esc_attr($twilio_token); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Twilio Phone Number', 'rr-table-booking'); ?></th>
                <td>
                    <input type="tel" name="twilio_from" class="regular-text" 
                           value="<?php echo esc_attr($twilio_from); ?>" 
                           placeholder="+1234567890" />
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax() {
        check_ajax_referer('rr_admin_nonce', 'nonce');
        
        if (!current_user_can('rr_manage_bookings')) {
            wp_send_json_error(__('Insufficient permissions.', 'rr-table-booking'));
        }
        
        $action = sanitize_text_field($_POST['rr_action']);
        $response = array('success' => false);
        
        switch ($action) {
            case 'confirm_booking':
                $response = $this->ajax_confirm_booking();
                break;
                
            case 'cancel_booking':
                $response = $this->ajax_cancel_booking();
                break;
                
            default:
                $response['message'] = __('Invalid action.', 'rr-table-booking');
                break;
        }
        
        wp_send_json($response);
    }
    
    /**
     * AJAX confirm booking
     */
    private function ajax_confirm_booking() {
        if (!isset($_POST['booking_id'])) {
            return array('success' => false, 'message' => __('Missing booking ID.', 'rr-table-booking'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!class_exists('RR_Bookings')) {
            return array('success' => false, 'message' => __('Booking system unavailable.', 'rr-table-booking'));
        }
        
        $bookings = new RR_Bookings();
        $success = $bookings->confirm_booking($booking_id, true);
        
        return array(
            'success' => $success,
            'message' => $success ? 
                __('Booking confirmed successfully.', 'rr-table-booking') : 
                __('Failed to confirm booking.', 'rr-table-booking')
        );
    }
    
    /**
     * AJAX cancel booking
     */
    private function ajax_cancel_booking() {
        if (!isset($_POST['booking_id'])) {
            return array('success' => false, 'message' => __('Missing booking ID.', 'rr-table-booking'));
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!class_exists('RR_Bookings')) {
            return array('success' => false, 'message' => __('Booking system unavailable.', 'rr-table-booking'));
        }
        
        $bookings = new RR_Bookings();
        $success = $bookings->cancel_booking($booking_id, true);
        
        return array(
            'success' => $success,
            'message' => $success ? 
                __('Booking cancelled successfully.', 'rr-table-booking') : 
                __('Failed to cancel booking.', 'rr-table-booking')
        );
    }
    
    /**
     * Settings field renderers
     */
    public function render_timezone_field() {
        $current = rr_get_setting('business_timezone', get_option('timezone_string', 'UTC'));
        ?>
        <select name="rr_table_booking_options[business_timezone]">
            <?php echo wp_timezone_choice($current); ?>
        </select>
        <p class="description"><?php esc_html_e('Timezone used for all booking calculations and display.', 'rr-table-booking'); ?></p>
        <?php
    }
    
    public function render_number_field($args) {
        $value = rr_get_setting($args['key'], '');
        $min = isset($args['min']) ? intval($args['min']) : 0;
        $max = isset($args['max']) ? intval($args['max']) : 100;
        ?>
        <input type="number" 
               name="rr_table_booking_options[<?php echo esc_attr($args['key']); ?>]" 
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($min); ?>"
               max="<?php echo esc_attr($max); ?>"
               class="small-text" />
        <?php
    }
    
    public function render_checkbox_field($args) {
        $value = rr_get_setting($args['key'], 'no');
        ?>
        <label>
            <input type="checkbox" 
                   name="rr_table_booking_options[<?php echo esc_attr($args['key']); ?>]" 
                   value="yes"
                   <?php checked($value, 'yes'); ?> />
            <?php esc_html_e('Enable', 'rr-table-booking'); ?>
        </label>
        <?php
    }
    
    /**
     * Validate settings before saving
     */
    public function validate_settings($input) {
        if (!current_user_can('rr_manage_settings')) {
            return array();
        }
        
        $validated = array();
        
        // Sanitize and validate each setting
        $settings_map = array(
            'business_timezone' => 'sanitize_text_field',
            'min_party_size' => 'absint',
            'max_party_size' => 'absint', 
            'hold_timeout' => 'absint',
            'pooled_seating' => array($this, 'validate_checkbox'),
            'email_from_name' => 'sanitize_text_field',
            'email_from_address' => 'sanitize_email',
            'confirmation_email_subject' => 'sanitize_text_field',
            'confirmation_email_body' => 'wp_kses_post',
            'sms_enabled' => array($this, 'validate_checkbox'),
            'twilio_sid' => 'sanitize_text_field',
            'twilio_token' => 'sanitize_text_field',
            'twilio_from' => 'sanitize_text_field'
        );
        
        foreach ($settings_map as $key => $sanitizer) {
            if (isset($input[$key])) {
                if (is_callable($sanitizer)) {
                    $validated[$key] = call_user_func($sanitizer, $input[$key]);
                } else {
                    $validated[$key] = $input[$key];
                }
            }
        }
        
        // Additional validation
        if (isset($validated['min_party_size']) && isset($validated['max_party_size'])) {
            if ($validated['min_party_size'] > $validated['max_party_size']) {
                add_settings_error(
                    'rr_table_booking_options',
                    'party_size_error',
                    __('Minimum party size cannot be greater than maximum party size.', 'rr-table-booking'),
                    'error'
                );
                // Swap values
                $temp = $validated['min_party_size'];
                $validated['min_party_size'] = $validated['max_party_size'];
                $validated['max_party_size'] = $temp;
            }
        }
        
        if (isset($validated['hold_timeout']) && ($validated['hold_timeout'] < 5 || $validated['hold_timeout'] > 120)) {
            add_settings_error(
                'rr_table_booking_options',
                'hold_timeout_error',
                __('Hold timeout must be between 5 and 120 minutes.', 'rr-table-booking'),
                'error'
            );
            $validated['hold_timeout'] = 30; // Default value
        }
        
        if (isset($validated['email_from_address']) && !is_email($validated['email_from_address'])) {
            add_settings_error(
                'rr_table_booking_options',
                'email_error',
                __('Please enter a valid email address.', 'rr-table-booking'),
                'error'
            );
            $validated['email_from_address'] = get_option('admin_email');
        }
        
        // Save to custom settings table
        foreach ($validated as $key => $value) {
            if (!rr_update_setting($key, $value)) {
                add_settings_error(
                    'rr_table_booking_options',
                    'save_error',
                    sprintf(__('Failed to save setting: %s', 'rr-table-booking'), $key),
                    'error'
                );
            }
        }
        
        // Success message
        if (empty(get_settings_errors('rr_table_booking_options'))) {
            add_settings_error(
                'rr_table_booking_options',
                'settings_updated',
                __('Settings saved successfully.', 'rr-table-booking'),
                'success'
            );
        }
        
        return $validated;
    }
    
    /**
     * Validate checkbox field
     */
    private function validate_checkbox($value) {
        return ($value === 'yes') ? 'yes' : 'no';
    }
}
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Check if user has required capability
        if (!current_user_can('rr_manage_bookings') && !current_user_can('manage_options')) {
            return;
        }
        
        add_menu_page(
            __('Bookings', 'rr-table-booking'),
            __('Bookings', 'rr-table-booking'),
            'rr_manage_bookings',
            'rr-bookings',
            array($this, 'render_dashboard'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'rr-bookings',
            __('All Bookings', 'rr-table-booking'),
            __('All Bookings', 'rr-table-booking'),
            'rr_manage_bookings',
            'rr-bookings-all',
            array($this, 'render_all_bookings')
        );
        
        add_submenu_page(
            'rr-bookings',
            __('New Booking', 'rr-table-booking'),
            __('New Booking', 'rr-table-booking'),
            'rr_manage_bookings',
            'rr-booking-new',
            array($this, 'render_new_booking')
        );
        
        add_submenu_page(
            'rr-bookings',
            __('Tables', 'rr-table-booking'),
            __('Tables', 'rr-table-booking'),
            'rr_manage_bookings',
            'rr-tables',
            array($this, 'render_tables')
        );
        
        add_submenu_page(
            'rr-bookings',
            __('Shifts', 'rr-table-booking'),
            __('Shifts', 'rr-table-booking'),
            'rr_manage_bookings',
            'rr-shifts',
            array($this, 'render_shifts')
        );
        
        add_submenu_page(
            'rr-bookings',
            __('Settings', 'rr-table-booking'),
            __('Settings', 'rr-table-booking'),
            'rr_manage_settings',
            'rr-settings',
            array($this, 'render_settings')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'rr-') === false) {
            return;
        }
        
        // Ensure files exist before enqueueing
        $css_file = RR_BOOKING_PLUGIN_DIR . 'admin/assets/css/admin.css';
        $js_file = RR_BOOKING_PLUGIN_DIR . 'admin/assets/js/admin.js';
        
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'rr-admin-css',
                RR_BOOKING_PLUGIN_URL . 'admin/assets/css/admin.css',
                array(),
                RR_BOOKING_VERSION
            );
        }
        
        if (file_exists($js_file)) {
            wp_enqueue_script(
                'rr-admin-js',
                RR_BOOKING_PLUGIN_URL . 'admin/assets/js/admin.js',
                array('jquery', 'jquery-ui-datepicker'),
                RR_BOOKING_VERSION,
                true
            );
            
            wp_localize_script('rr-admin-js', 'rr_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rr_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this?', 'rr-table-booking'),
                    'confirm_cancel' => __('Are you sure you want to cancel this booking?', 'rr-table-booking'),
                    'loading' => __('Loading...', 'rr-table-booking'),
                    'error' => __('An error occurred. Please try again.', 'rr-table-booking'),
                    'success' => __('Action completed successfully.', 'rr-table-booking')
                )
            ));
        }
        
        // Enqueue WordPress UI styles for datepicker
        wp_enqueue_style('jquery-ui-datepicker');
    }
    
    /**
     * Register admin settings
     */
    public function register_settings() {
        register_setting(
            'rr_table_booking_options',
            'rr_table_booking_options',
            array($this, 'validate_settings')
        );
        
        // General Settings Section
        add_settings_section(
            'rr_general_settings',
            __('General Settings', 'rr-table-booking'),
            array($this, 'general_settings_callback'),
            'rr_settings_general'
        );
        
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        $fields = array(
            'business_timezone' => array(
                'title' => __('Business Timezone', 'rr-table-booking'),
                'callback' => 'render_timezone_field',
                'description' => __('Timezone for booking calculations', 'rr-table-booking')
            ),
            'min_party_size' => array(
                'title' => __('Minimum Party Size', 'rr-table-booking'),
                'callback' => 'render_number_field',
                'args' => array('key' => 'min_party_size', 'min' => 1, 'max' => 20),
                'description' => __('Smallest party size allowed', 'rr-table-booking')
            ),
            'max_party_size' => array(
                'title' => __('Maximum Party Size', 'rr-table-booking'),
                'callback' => 'render_number_field',
                'args' => array('key' => 'max_party_size', 'min' => 1, 'max' => 50),
                'description' => __('Largest party size allowed', 'rr-table-booking')
            ),
            'hold_timeout' => array(
                'title' => __('Hold Timeout (minutes)', 'rr-table-booking'),
                'callback' => 'render_number_field',
                'args' => array('key' => 'hold_timeout', 'min' => 5, 'max' => 120),
                'description' => __('How long to hold unconfirmed bookings', 'rr-table-booking')
            ),
            'pooled_seating' => array(
                'title' => __('Enable Pooled Seating', 'rr-table-booking'),
                'callback' => 'render_checkbox_field',
                'args' => array('key' => 'pooled_seating'),
                'description' => __('Automatically assign tables based on capacity', 'rr-table-booking')
            )
        );
        
        foreach ($fields as $field_id => $field) {
            add_settings_field(
                $field_id,
                $field['title'],
                array($this, $field['callback']),
                'rr_settings_general',
                'rr_general_settings',
                isset($field['args']) ? $field['args'] : array()
            );
        }
    }
    
    /**
     * Handle admin form submissions
     */
    public function handle_admin_actions() {
        if (!is_admin() || !current_user_can('rr_manage_bookings')) {
            return;
        }
        
        // Handle booking actions
        if (isset($_GET['action']) && isset($_GET['booking']) && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'rr_booking_action')) {
                wp_die(__('Security check failed', 'rr-table-booking'));
            }
            
            $booking_id = intval($_GET['booking']);
            $action = sanitize_text_field($_GET['action']);
            
            $this->process_booking_action($action, $booking_id);
        }
        
        // Handle new booking creation
        if (isset($_POST['rr_booking_nonce']) && wp_verify_nonce($_POST['rr_booking_nonce'], 'rr_new_booking')) {
            $this->handle_new_booking_submission();
        }
    }
    
    /**
     * Process booking actions (confirm, cancel, delete)
     */
    private function process_booking_action($action, $booking_id) {
        if (!class_exists('RR_Bookings')) {
            $this->add_admin_notice('error', __('Booking system unavailable.', 'rr-table-booking'));
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
        
        $this->add_admin_notice($success ? 'success' : 'error', $message);
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
     * Handle new booking form submission
     */
    private function handle_new_booking_submission() {
        // Sanitize and validate input
        $booking_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'date' => sanitize_text_field($_POST['date']),
            'time' => sanitize_text_field($_POST['time']),
            'party_size' => intval($_POST['party_size']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );
        
        // Validate required fields
        $errors = array();
        if (empty($booking_data['customer_name'])) {
            $errors[] = __('Customer name is required.', 'rr-table-booking');
        }
        if (!is_email($booking_data['email'])) {
            $errors[] = __('Valid email is required.', 'rr-table-booking');
        }
        if (empty($booking_data['phone'])) {
            $errors[] = __('Phone number is required.', 'rr-table-booking');
        }
        if (empty($booking_data['date']) || !strtotime($booking_data['date'])) {
            $errors[] = __('Valid date is required.', 'rr-table-booking');
        }
        if (empty($booking_data['time'])) {
            $errors[] = __('Time is required.', 'rr-table-booking');
        }
        if ($booking_data['party_size'] < 1) {
            $errors[] = __('Party size must be at least 1.', 'rr-table-booking');
        }
        
        if (!empty($errors)) {
            $this->add_admin_notice('error', implode('<br>', $errors));
            return;
        }
        
        // Create booking
        if (class_exists('RR_Bookings')) {
            $bookings = new RR_Bookings();
            $booking_id = $bookings->create_booking($booking_data, true); // Admin bypass
            
            if ($booking_id) {
                $this->add_admin_notice('success', __('Booking created successfully.', 'rr-table-booking'));
                
                // Redirect to prevent resubmission
                wp_redirect(admin_url('admin.php?page=rr-bookings-all'));
                exit;
            } else {
                $this->add_admin_notice('error', __('Failed to create booking.', 'rr-table-booking'));
            }
        } else {
            $this->add_admin_notice('error', __('Booking system unavailable.', 'rr-table-booking'));
        }
    }
    
    /**
     * Add admin notice
     */
    private function add_admin_notice($type, $message) {
        set_transient('rr_admin_notice', array(
            'type' => $type,
            'message' => $message
        ), 30);
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        $notice = get_transient('rr_admin_notice');
        if ($notice && is_array($notice)) {
            $class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                wp_kses_post($notice['message'])
            );
            delete_transient('rr_admin_notice');
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        if (!class_exists('RR_Bookings')) {
            echo '<div class="wrap"><h1>' . __('Bookings Dashboard', 'rr-table-booking') . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('Booking system is not available.', 'rr-table-booking') . '</p></div>';
            echo '</div>';
            return;
        }
        
        $bookings = new RR_Bookings();
        $today = current_time('Y-m-d');
        $today_bookings = $bookings->get_bookings_by_date($today);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bookings Dashboard', 'rr-table-booking'); ?></h1>
            
            <div class="rr-dashboard-stats">
                <div class="rr-stat-box">
                    <h3><?php esc_html_e("Today's Bookings", 'rr-table-booking'); ?></h3>
                    <p class="rr-stat-number"><?php echo count($today_bookings); ?></p>
                </div>
            </div>
            
            <h2><?php esc_html_e("Today's Reservations", 'rr-table-booking'); ?></h2>
            <?php if (empty($today_bookings)): ?>
                <p><?php esc_html_e('No bookings for today.', 'rr-table-booking'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Time', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Customer', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Party Size', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Table', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Status', 'rr-table-booking'); ?></th>
                            <th><?php esc_html_e('Actions', 'rr-table-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_bookings as $booking): ?>
                        <tr>
                            <td><?php echo esc_html(rr_format_time($booking->time)); ?></td>
                            <td>
                                <strong><?php echo esc_html($booking->customer_name); ?></strong><br>
                                <small><?php echo esc_html($booking->email); ?></small>
                            </td>
                            <td><?php echo esc_html($booking->party_size); ?></td>
                            <td><?php echo $booking->table_id ? esc_html($bookings->get_table_name($booking->table_id)) : '-'; ?></td>
                            <td>
                                <span class="rr-status rr-status-<?php echo esc_attr($booking->status); ?>">
                                    <?php echo esc_html(ucfirst($booking->status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php $this->render_booking_actions($booking); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render booking action buttons
     */
    private function render_booking_actions($booking) {
        $nonce = wp_create_nonce('rr_booking_action');
        $page = sanitize_text_field($_REQUEST['page']);
        
        if ($booking->status === 'pending'): ?>
            <a href="<?php echo esc_url(admin_url("admin.php?page={$page}&action=confirm&booking={$booking->id}&_wpnonce={$nonce}")); ?>" 
               class="button button-small button-primary">
                <?php esc_html_e('Confirm', 'rr-table-booking'); ?>
            </a>
        <?php endif;
        
        if (in_array($booking->status, ['pending', 'confirmed'])): ?>
            <a href="<?php echo esc_url(admin_url("admin.php?page={$page}&action=cancel&booking={$booking->id}&_wpnonce={$nonce}")); ?>" 
               class="button button-small"
               onclick="return confirm('<?php esc_attr_e('Are you sure you want to cancel this booking?', 'rr-table-booking'); ?>')">
                <?php esc_html_e('Cancel', 'rr-table-booking'); ?>
            </a>
        <?php endif; ?>
        
        <a href="<?php echo esc_url(admin_url("admin.php?page={$page}&action=delete&booking={$booking->id}&_wpnonce={$nonce}")); ?>" 
           class="button button-small button-link-delete"
           onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this booking?', 'rr-table-booking'); ?>')">
            <?php esc_html_e('Delete', 'rr-table-booking'); ?>
        </a>
        <?php
    }
    
    /**