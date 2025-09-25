<?php
/**
 * Gutenberg Block Registration for Restaurant Table Booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RR_Blocks {
    
    public function __construct() {
        add_action('init', array($this, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
    }
    
    /**
     * Register blocks with WordPress
     */
    public function register_blocks() {
        // Check if block registration is available
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register the booking form block
        register_block_type('rr-booking/booking-form', array(
            'editor_script' => 'rr-booking-block-editor',
            'editor_style' => 'rr-booking-block-editor-style',
            'style' => 'rr-booking-block-style',
            'render_callback' => array($this, 'render_booking_block'),
            'attributes' => array(
                'title' => array(
                    'type' => 'string',
                    'default' => __('Make a Reservation', 'rr-table-booking')
                ),
                'showTitle' => array(
                    'type' => 'boolean',
                    'default' => true
                ),
                'theme' => array(
                    'type' => 'string',
                    'default' => 'default'
                ),
                'maxWidth' => array(
                    'type' => 'string',
                    'default' => '600px'
                ),
                'alignment' => array(
                    'type' => 'string',
                    'default' => 'center'
                )
            )
        ));
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_editor_assets() {
        $block_js_path = RR_BOOKING_PLUGIN_DIR . 'public/blocks/booking-block.js';
        $block_css_path = RR_BOOKING_PLUGIN_DIR . 'public/blocks/editor.css';
        
        // Enqueue block editor JavaScript
        if (file_exists($block_js_path)) {
            wp_enqueue_script(
                'rr-booking-block-editor',
                RR_BOOKING_PLUGIN_URL . 'public/blocks/booking-block.js',
                array(
                    'wp-blocks',
                    'wp-element',
                    'wp-components',
                    'wp-block-editor',
                    'wp-i18n'
                ),
                RR_BOOKING_VERSION,
                true
            );
            
            // Localize script for the block editor
            wp_localize_script('rr-booking-block-editor', 'rrBlockData', array(
                'pluginUrl' => RR_BOOKING_PLUGIN_URL,
                'restUrl' => rest_url('rr/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'strings' => array(
                    'blockTitle' => __('Restaurant Booking Form', 'rr-table-booking'),
                    'blockDescription' => __('Display a restaurant table booking form', 'rr-table-booking'),
                    'loadingText' => __('Loading booking form...', 'rr-table-booking'),
                    'errorText' => __('Error loading booking form', 'rr-table-booking')
                )
            ));
            
            // Set script translations
            if (function_exists('wp_set_script_translations')) {
                wp_set_script_translations(
                    'rr-booking-block-editor',
                    'rr-table-booking',
                    RR_BOOKING_PLUGIN_DIR . 'languages'
                );
            }
        }
        
        // Enqueue block editor styles
        if (file_exists($block_css_path)) {
            wp_enqueue_style(
                'rr-booking-block-editor-style',
                RR_BOOKING_PLUGIN_URL . 'public/blocks/editor.css',
                array('wp-edit-blocks'),
                RR_BOOKING_VERSION
            );
        }
    }
    
    /**
     * Enqueue block assets for both editor and frontend
     */
    public function enqueue_block_assets() {
        $frontend_css_path = RR_BOOKING_PLUGIN_DIR . 'public/assets/css/frontend.css';
        
        // Enqueue frontend styles for blocks
        if (file_exists($frontend_css_path)) {
            wp_enqueue_style(
                'rr-booking-block-style',
                RR_BOOKING_PLUGIN_URL . 'public/assets/css/frontend.css',
                array(),
                RR_BOOKING_VERSION
            );
        }
    }
    
    /**
     * Render booking block on frontend
     */
    public function render_booking_block($attributes, $content) {
        // Validate attributes
        $attributes = $this->validate_block_attributes($attributes);
        
        // Check if shortcode class exists
        if (!class_exists('RR_Shortcode')) {
            return '<p class="rr-error">' . esc_html__('Booking form is currently unavailable.', 'rr-table-booking') . '</p>';
        }
        
        // Convert boolean attributes
        $show_title = isset($attributes['showTitle']) && $attributes['showTitle'] ? 'yes' : 'no';
        
        // Prepare shortcode attributes
        $shortcode_atts = array(
            'title' => isset($attributes['title']) ? $attributes['title'] : __('Make a Reservation', 'rr-table-booking'),
            'show_title' => $show_title,
            'theme' => isset($attributes['theme']) ? $attributes['theme'] : 'default',
            'max_width' => isset($attributes['maxWidth']) ? $attributes['maxWidth'] : '600px'
        );
        
        // Add alignment wrapper if specified
        $alignment = isset($attributes['alignment']) ? $attributes['alignment'] : 'center';
        $wrapper_style = '';
        
        switch ($alignment) {
            case 'left':
                $wrapper_style = 'text-align: left;';
                break;
            case 'right':
                $wrapper_style = 'text-align: right;';
                break;
            case 'center':
            default:
                $wrapper_style = 'text-align: center;';
                break;
        }
        
        // Generate shortcode output
        $shortcode_output = RR_Shortcode::render_booking_form($shortcode_atts);
        
        // Wrap in alignment container
        return sprintf(
            '<div class="wp-block-rr-booking-booking-form" style="%s">%s</div>',
            esc_attr($wrapper_style),
            $shortcode_output
        );
    }
    
    /**
     * Validate and sanitize block attributes
     */
    private function validate_block_attributes($attributes) {
        $validated = array();
        
        // Validate title
        if (isset($attributes['title'])) {
            $validated['title'] = sanitize_text_field($attributes['title']);
        }
        
        // Validate showTitle
        if (isset($attributes['showTitle'])) {
            $validated['showTitle'] = (bool) $attributes['showTitle'];
        }
        
        // Validate theme
        if (isset($attributes['theme'])) {
            $valid_themes = array('default', 'modern', 'minimal', 'classic');
            $theme = sanitize_text_field($attributes['theme']);
            $validated['theme'] = in_array($theme, $valid_themes) ? $theme : 'default';
        }
        
        // Validate maxWidth
        if (isset($attributes['maxWidth'])) {
            $max_width = sanitize_text_field($attributes['maxWidth']);
            // Allow px, %, em, rem values
            if (preg_match('/^(\d+)(px|%|em|rem)$/', $max_width)) {
                $validated['maxWidth'] = $max_width;
            } else {
                $validated['maxWidth'] = '600px';
            }
        }
        
        // Validate alignment
        if (isset($attributes['alignment'])) {
            $valid_alignments = array('left', 'center', 'right');
            $alignment = sanitize_text_field($attributes['alignment']);
            $validated['alignment'] = in_array($alignment, $valid_alignments) ? $alignment : 'center';
        }
        
        return $validated;
    }
    
    /**
     * Add block category for our blocks
     */
    public function add_block_category($categories, $post) {
        return array_merge(
            $categories,
            array(
                array(
                    'slug' => 'rr-booking',
                    'title' => __('Restaurant Booking', 'rr-table-booking'),
                    'icon' => 'calendar-alt'
                )
            )
        );
    }
    
    /**
     * Add custom block patterns
     */
    public function register_block_patterns() {
        if (!function_exists('register_block_pattern')) {
            return;
        }
        
        // Register a basic booking form pattern
        register_block_pattern(
            'rr-booking/basic-form',
            array(
                'title' => __('Basic Booking Form', 'rr-table-booking'),
                'description' => __('A simple restaurant booking form with default settings', 'rr-table-booking'),
                'content' => '<!-- wp:rr-booking/booking-form {"title":"' . __('Reserve Your Table', 'rr-table-booking') . '"} /-->',
                'categories' => array('rr-booking'),
                'keywords' => array('booking', 'restaurant', 'reservation')
            )
        );
        
        // Register a styled booking form pattern
        register_block_pattern(
            'rr-booking/styled-form',
            array(
                'title' => __('Styled Booking Form', 'rr-table-booking'),
                'description' => __('A booking form with modern styling and custom width', 'rr-table-booking'),
                'content' => '<!-- wp:rr-booking/booking-form {"title":"' . __('Book Your Experience', 'rr-table-booking') . '","theme":"modern","maxWidth":"800px"} /-->',
                'categories' => array('rr-booking'),
                'keywords' => array('booking', 'restaurant', 'modern', 'styled')
            )
        );
    }
}

// Initialize blocks
new RR_Blocks();