<?php
/**
 * REST API endpoints for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_REST {
    
    private $namespace = 'rr/v1';
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Availability endpoint
        register_rest_route($this->namespace, '/availability', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_availability'),
            'permission_callback' => array($this, 'public_permission_check'),
            'args' => $this->get_availability_args()
        ));
        
        // Book endpoint
        register_rest_route($this->namespace, '/book', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_booking'),
            'permission_callback' => array($this, 'booking_permission_check'),
            'args' => $this->get_booking_args()
        ));
        
        // Confirm booking endpoint
        register_rest_route($this->namespace, '/confirm', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'confirm_booking'),
            'permission_callback' => array($this, 'public_permission_check'),
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_token')
                )
            )
        ));
        
        // Cancel booking endpoint
        register_rest_route($this->namespace, '/cancel', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'cancel_booking'),
            'permission_callback' => array($this, 'public_permission_check'),
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_token')
                )
            )
        ));
        
        // Get booking details endpoint
        register_rest_route($this->namespace, '/booking/(?P<token>[a-zA-Z0-9]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_booking_details'),
            'permission_callback' => array($this, 'public_permission_check'),
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_token')
                )
            )
        ));
    }
    
    /**
     * Get availability arguments
     */
    private function get_availability_args() {
        return array(
            'date' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_date'),
                'description' => __('Date in YYYY-MM-DD format', 'rr-table-booking')
            ),
            'party_size' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => array($this, 'validate_party_size'),
                'description' => __('Number of people in the party', 'rr-table-booking')
            )
        );
    }
    
    /**
     * Get booking arguments
     */
    private function get_booking_args() {
        return array(
            'customer_name' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_customer_name'),
                'description' => __('Customer full name', 'rr-table-booking')
            ),
            'email' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => 'is_email',
                'description' => __('Customer email address', 'rr-table-booking')
            ),
            'phone' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_phone'),
                'description' => __('Customer phone number', 'rr-table-booking')
            ),
            'date' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_booking_date'),
                'description' => __('Booking date in YYYY-MM-DD format', 'rr-table-booking')
            ),
            'time' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_time'),
                'description' => __('Booking time in HH:MM format', 'rr-table-booking')
            ),
            'party_size' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => array($this, 'validate_party_size'),
                'description' => __('Number of people in the party', 'rr-table-booking')
            ),
            'notes' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => array($this, 'validate_notes'),
                'description' => __('Special requests or notes', 'rr-table-booking')
            )
        );
    }
    
    /**
     * Public permission check (with rate limiting)
     */
    public function public_permission_check($request) {
        return $this->rate_limit_check($request);
    }
    
    /**
     * Booking permission check (stricter rate limiting)
     */
    public function booking_permission_check($request) {
        return $this->rate_limit_check($request, 5); // Stricter limit for bookings
    }
    
    /**
     * Rate limiting check
     */
    public function rate_limit_check($request, $limit = 10) {
        $ip = $this->get_client_ip();
        $transient_key = 'rr_api_limit_' . md5($ip);
        $attempts = get_transient($transient_key);
        
        if ($attempts && $attempts >= $limit) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again later.', 'rr-table-booking'),
                array('status' => 429)
            );
        }
        
        // Increment counter
        set_transient($transient_key, ($attempts ? $attempts + 1 : 1), HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    
    /**
     * Get availability
     */
    public function get_availability($request) {
        try {
            $date = $request->get_param('date');
            $party_size = intval($request->get_param('party_size'));
            
            if (!class_exists('RR_Bookings')) {
                return $this->error_response('booking_system_unavailable', __('Booking system is currently unavailable.', 'rr-table-booking'));
            }
            
            $bookings = new RR_Bookings();
            $slots = $bookings->get_available_slots($date, $party_size);
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'date' => $date,
                    'party_size' => $party_size,
                    'available_slots' => $slots,
                    'total_slots' => count($slots)
                ),
                'message' => count($slots) > 0 ? 
                    __('Available time slots found.', 'rr-table-booking') : 
                    __('No available time slots for the selected date and party size.', 'rr-table-booking')
            ));
            
        } catch (Exception $e) {
            rr_log_error('API availability error: ' . $e->getMessage());
            return $this->error_response('internal_error', __('An error occurred while checking availability.', 'rr-table-booking'));
        }
    }
    
    /**
     * Create booking
     */
    public function create_booking($request) {
        try {
            if (!class_exists('RR_Bookings')) {
                return $this->error_response('booking_system_unavailable', __('Booking system is currently unavailable.', 'rr-table-booking'));
            }
            
            $bookings = new RR_Bookings();
            
            $booking_data = array(
                'customer_name' => $request->get_param('customer_name'),
                'email' => $request->get_param('email'),
                'phone' => $request->get_param('phone'),
                'date' => $request->get_param('date'),
                'time' => $request->get_param('time'),
                'party_size' => $request->get_param('party_size'),
                'notes' => $request->get_param('notes') ?: ''
            );
            
            $booking_id = $bookings->create_booking($booking_data);
            
            if (is_wp_error($booking_id)) {
                return $this->error_response(
                    $booking_id->get_error_code(),
                    $booking_id->get_error_message()
                );
            }
            
            $booking = $bookings->get_booking($booking_id);
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'booking_id' => $booking_id,
                    'confirmation_token' => $booking->confirmation_token,
                    'status' => $booking->status,
                    'customer_name' => $booking->customer_name,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'party_size' => $booking->party_size
                ),
                'message' => __('Booking created successfully. Please check your email to confirm.', 'rr-table-booking')
            ));
            
        } catch (Exception $e) {
            rr_log_error('API booking creation error: ' . $e->getMessage());
            return $this->error_response('internal_error', __('An error occurred while creating the booking.', 'rr-table-booking'));
        }
    }
    
    /**
     * Confirm booking
     */
    public function confirm_booking($request) {
        try {
            $token = $request->get_param('token');
            
            if (!class_exists('RR_Bookings')) {
                return $this->error_response('booking_system_unavailable', __('Booking system is currently unavailable.', 'rr-table-booking'));
            }
            
            $bookings = new RR_Bookings();
            $result = $bookings->confirm_booking_by_token($token);
            
            if (is_wp_error($result)) {
                return $this->error_response(
                    $result->get_error_code(),
                    $result->get_error_message()
                );
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Booking confirmed successfully!', 'rr-table-booking')
            ));
            
        } catch (Exception $e) {
            rr_log_error('API booking confirmation error: ' . $e->getMessage());
            return $this->error_response('internal_error', __('An error occurred while confirming the booking.', 'rr-table-booking'));
        }
    }
    
    /**
     * Cancel booking
     */
    public function cancel_booking($request) {
        try {
            $token = $request->get_param('token');
            
            if (!class_exists('RR_Bookings')) {
                return $this->error_response('booking_system_unavailable', __('Booking system is currently unavailable.', 'rr-table-booking'));
            }
            
            $bookings = new RR_Bookings();
            $result = $bookings->cancel_booking_by_token($token);
            
            if (is_wp_error($result)) {
                return $this->error_response(
                    $result->get_error_code(),
                    $result->get_error_message()
                );
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Booking cancelled successfully.', 'rr-table-booking')
            ));
            
        } catch (Exception $e) {
            rr_log_error('API booking cancellation error: ' . $e->getMessage());
            return $this->error_response('internal_error', __('An error occurred while cancelling the booking.', 'rr-table-booking'));
        }
    }
    
    /**
     * Get booking details
     */
    public function get_booking_details($request) {
        try {
            $token = $request->get_param('token');
            
            global $wpdb;
            $table = $wpdb->prefix . 'rr_bookings';
            
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT b.*, t.name as table_name 
                 FROM $table b
                 LEFT JOIN {$wpdb->prefix}rr_tables t ON b.table_id = t.id
                 WHERE b.confirmation_token = %s",
                $token
            ));
            
            if (!$booking) {
                return $this->error_response('booking_not_found', __('Booking not found.', 'rr-table-booking'));
            }
            
            return rest_ensure_response(array(
                'success' => true,
                'data' => array(
                    'id' => intval($booking->id),
                    'customer_name' => $booking->customer_name,
                    'email' => $booking->email,
                    'phone' => $booking->phone,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'party_size' => intval($booking->party_size),
                    'table_name' => $booking->table_name,
                    'status' => $booking->status,
                    'notes' => $booking->notes,
                    'created_at' => $booking->created_at
                )
            ));
            
        } catch (Exception $e) {
            rr_log_error('API get booking details error: ' . $e->getMessage());
            return $this->error_response('internal_error', __('An error occurred while retrieving booking details.', 'rr-table-booking'));
        }
    }
    
    /**
     * Validation functions
     */
    public function validate_date($param, $request, $key) {
        if (empty($param)) {
            return false;
        }
        
        // Check format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $param)) {
            return false;
        }
        
        // Check if valid date
        $date_obj = DateTime::createFromFormat('Y-m-d', $param);
        return $date_obj && $date_obj->format('Y-m-d') === $param;
    }
    
    public function validate_booking_date($param, $request, $key) {
        if (!$this->validate_date($param, $request, $key)) {
            return false;
        }
        
        // Check if date is not in the past
        $booking_date = strtotime($param);
        $today = strtotime(current_time('Y-m-d'));
        
        return $booking_date >= $today;
    }
    
    public function validate_time($param, $request, $key) {
        if (empty($param)) {
            return false;
        }
        
        // Accept both HH:MM and HH:MM:SS formats
        return preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $param);
    }
    
    public function validate_party_size($param, $request, $key) {
        $party_size = intval($param);
        $min_size = intval(rr_get_setting('min_party_size', 1));
        $max_size = intval(rr_get_setting('max_party_size', 12));
        
        return $party_size >= $min_size && $party_size <= $max_size;
    }
    
    public function validate_customer_name($param, $request, $key) {
        $name = trim($param);
        return !empty($name) && strlen($name) >= 2 && strlen($name) <= 100;
    }
    
    public function validate_phone($param, $request, $key) {
        $phone = trim($param);
        return !empty($phone) && strlen($phone) >= 10 && strlen($phone) <= 20;
    }
    
    public function validate_notes($param, $request, $key) {
        return strlen($param) <= 1000; // Max 1000 characters for notes
    }
    
    public function validate_token($param, $request, $key) {
        $token = trim($param);
        return !empty($token) && strlen($token) >= 10 && ctype_alnum($token);
    }
    
    /**
     * Create error response
     */
    private function error_response($code, $message, $status = 400) {
        return new WP_Error($code, $message, array('status' => $status));
    }
    
    /**
     * Get API documentation
     */
    public function get_api_documentation() {
        return array(
            'namespace' => $this->namespace,
            'endpoints' => array(
                'availability' => array(
                    'method' => 'GET',
                    'url' => rest_url($this->namespace . '/availability'),
                    'description' => __('Get available time slots', 'rr-table-booking'),
                    'parameters' => array(
                        'date' => __('Date in YYYY-MM-DD format', 'rr-table-booking'),
                        'party_size' => __('Number of people (integer)', 'rr-table-booking')
                    )
                ),
                'book' => array(
                    'method' => 'POST',
                    'url' => rest_url($this->namespace . '/book'),
                    'description' => __('Create a new booking', 'rr-table-booking'),
                    'parameters' => array(
                        'customer_name' => __('Customer full name', 'rr-table-booking'),
                        'email' => __('Customer email address', 'rr-table-booking'),
                        'phone' => __('Customer phone number', 'rr-table-booking'),
                        'date' => __('Booking date in YYYY-MM-DD format', 'rr-table-booking'),
                        'time' => __('Booking time in HH:MM format', 'rr-table-booking'),
                        'party_size' => __('Number of people (integer)', 'rr-table-booking'),
                        'notes' => __('Special requests (optional)', 'rr-table-booking')
                    )
                ),
                'confirm' => array(
                    'method' => 'POST',
                    'url' => rest_url($this->namespace . '/confirm'),
                    'description' => __('Confirm a pending booking', 'rr-table-booking'),
                    'parameters' => array(
                        'token' => __('Confirmation token from booking email', 'rr-table-booking')
                    )
                ),
                'cancel' => array(
                    'method' => 'POST',
                    'url' => rest_url($this->namespace . '/cancel'),
                    'description' => __('Cancel a booking', 'rr-table-booking'),
                    'parameters' => array(
                        'token' => __('Confirmation token from booking email', 'rr-table-booking')
                    )
                )
            )
        );
    }
}