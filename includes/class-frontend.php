<?php
/**
 * Frontend handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBM_Frontend {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('restaurant_booking_form', array($this, 'booking_form_shortcode'));
    }
    
    public function enqueue_scripts() {
        // Only enqueue on pages that have the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'restaurant_booking_form')) {
            wp_enqueue_script('jquery');
            wp_enqueue_style('rbm-frontend-style', RBM_PLUGIN_URL . 'assets/css/frontend.css', array(), RBM_VERSION);
            wp_enqueue_script('rbm-frontend-script', RBM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), RBM_VERSION, true);
            
            // Localize script
            wp_localize_script('rbm-frontend-script', 'rbm_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rbm_nonce')
            ));
        }
    }
    
    public function booking_form_shortcode($atts = array()) {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'capacity' => get_option('rbm_restaurant_capacity', 50),
            'max_party_size' => 15,
            'start_time' => '10:00',
            'end_time' => '22:00'
        ), $atts, 'restaurant_booking_form');
        
        ob_start();
        include RBM_PLUGIN_PATH . 'templates/frontend/booking-form.php';
        return ob_get_clean();
    }
}