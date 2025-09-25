<?php
/**
 * Email handler class
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBM_Email {
    
    private $database;
    
    public function __construct() {
        $this->database = new RBM_Database();
    }
    
    /**
     * Send admin notification when new booking is created
     */
    public function send_admin_notification($booking_id) {
        $booking = $this->database->get_booking($booking_id);
        
        if ($booking) {
            $admin_email = get_option('admin_email');
            $site_name = get_bloginfo('name');
            $subject = '[' . $site_name . '] Đặt bàn mới #' . $booking_id;
            
            $message = "Xin chào,\n\n";
            $message .= "Có đặt bàn mới trên website " . $site_name . ":\n\n";
            $message .= "Mã đặt bàn: #" . $booking_id . "\n";
            $message .= "Khách hàng: " . $booking->customer_name . "\n";
            $message .= "Điện thoại: " . $booking->customer_phone . "\n";
            $message .= "Email: " . $booking->customer_email . "\n";
            $message .= "Số khách: " . $booking->party_size . " người\n";
            $message .= "Ngày: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
            $message .= "Giờ: " . date('H:i', strtotime($booking->booking_time)) . "\n";
            $message .= "Ghi chú: " . $booking->special_requests . "\n\n";
            $message .= "Vào trang quản lý để xác nhận: " . admin_url('admin.php?page=restaurant-bookings') . "\n\n";
            $message .= "Trân trọng,\n";
            $message .= "Hệ thống quản lý đặt bàn";
            
            return wp_mail($admin_email, $subject, $message);
        }
        
        return false;
    }
    
    /**
     * Send confirmation email to customer
     */
    public function send_customer_confirmation($booking_id) {
        $booking = $this->database->get_booking($booking_id);
        
        if ($booking) {
            $site_name = get_bloginfo('name');
            $site_url = home_url();
            $subject = '[' . $site_name . '] Xác nhận đặt bàn #' . $booking_id;
            
            $message = "Xin chào " . $booking->customer_name . ",\n\n";
            $message .= "Chúng tôi rất vui thông báo đặt bàn của bạn đã được xác nhận!\n\n";
            $message .= "THÔNG TIN ĐẶT BÀN:\n";
            $message .= "Mã đặt bàn: #" . $booking_id . "\n";
            $message .= "Số khách: " . $booking->party_size . " người\n";
            $message .= "Ngày: " . date('Thứ N, d/m/Y', strtotime($booking->booking_date)) . "\n";
            $message .= "Giờ: " . date('H:i', strtotime($booking->booking_time)) . "\n";
            
            if ($booking->special_requests) {
                $message .= "Ghi chú đặc biệt: " . $booking->special_requests . "\n";
            }
            
            $message .= "\n";
            $message .= "Vui lòng đến đúng giờ để có trải nghiệm tốt nhất.\n";
            $message .= "Nếu có thay đổi, vui lòng liên hệ trước ít nhất 2 tiếng.\n\n";
            $message .= "Cảm ơn bạn đã chọn " . $site_name . "!\n\n";
            $message .= "Trân trọng,\n";
            $message .= $site_name . "\n";
            $message .= $site_url . "\n";
            $message .= "Email: " . get_option('admin_email');
            
            return wp_mail($booking->customer_email, $subject, $message);
        }
        
        return false;
    }
    
    /**
     * Send cancellation email to customer
     */
    public function send_cancellation_email($booking_id, $reason = '') {
        $booking = $this->database->get_booking($booking_id);
        
        if ($booking) {
            $site_name = get_bloginfo('name');
            $site_url = home_url();
            $subject = '[' . $site_name . '] Hủy đặt bàn #' . $booking_id;
            
            $message = "Xin chào " . $booking->customer_name . ",\n\n";
            $message .= "Chúng tôi xin thông báo đặt bàn #" . $booking_id . " đã được hủy.\n\n";
            $message .= "THÔNG TIN ĐẶT BÀN:\n";
            $message .= "Số khách: " . $booking->party_size . " người\n";
            $message .= "Ngày: " . date('d/m/Y', strtotime($booking->booking_date)) . "\n";
            $message .= "Giờ: " . date('H:i', strtotime($booking->booking_time)) . "\n";
            
            if ($reason) {
                $message .= "Lý do: " . $reason . "\n";
            }
            
            $message .= "\n";
            $message .= "Chúng tôi xin lỗi vì sự bất tiện này. Bạn có thể đặt lại bàn với thời gian khác.\n\n";
            $message .= "Liên hệ: " . get_option('admin_email') . "\n";
            $message .= "Website: " . $site_url . "\n\n";
            $message .= "Trân trọng,\n";
            $message .= $site_name;
            
            return wp_mail($booking->customer_email, $subject, $message);
        }
        
        return false;
    }
}