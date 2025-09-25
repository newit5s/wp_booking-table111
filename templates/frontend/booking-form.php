<?php
/**
 * Booking form template
 */

if (!defined('ABSPATH')) {
    exit;
}

$time_slots = rbm_get_time_slots($atts['start_time'], $atts['end_time']);
?>

<div id="rbm-booking-form-container">
    <h3>Đặt bàn tại nhà hàng</h3>
    <form id="rbm-booking-form">
        <div class="rbm-form-group">
            <label for="customer_name">Họ và tên <span class="required">*</span></label>
            <input type="text" id="customer_name" name="customer_name" required>
        </div>
        
        <div class="rbm-form-group">
            <label for="customer_phone">Số điện thoại <span class="required">*</span></label>
            <input type="tel" id="customer_phone" name="customer_phone" required>
        </div>
        
        <div class="rbm-form-group">
            <label for="customer_email">Email <span class="required">*</span></label>
            <input type="email" id="customer_email" name="customer_email" required>
        </div>
        
        <div class="rbm-form-row">
            <div class="rbm-form-group">
                <label for="party_size">Số lượng khách <span class="required">*</span></label>
                <select id="party_size" name="party_size" required>
                    <option value="">Chọn số khách</option>
                    <?php for($i = 1; $i <= intval($atts['max_party_size']); $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?> người</option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="rbm-form-group">
                <label for="booking_date">Ngày đặt bàn <span class="required">*</span></label>
                <input type="date" 
                       id="booking_date" 
                       name="booking_date" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                       required>
            </div>
        </div>
        
        <div class="rbm-form-group">
            <label for="booking_time">Giờ đặt bàn <span class="required">*</span></label>
            <select id="booking_time" name="booking_time" required>
                <option value="">Chọn giờ</option>
                <?php foreach ($time_slots as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="rbm-form-group">
            <label for="special_requests">Ghi chú đặc biệt</label>
            <textarea id="special_requests" 
                      name="special_requests" 
                      rows="3" 
                      placeholder="Yêu cầu đặc biệt, dị ứng thực phẩm, v.v. (nếu có)"></textarea>
        </div>
        
        <div class="rbm-availability-info">
            <div id="availability-status"></div>
        </div>
        
        <button type="submit" id="rbm-submit-btn">
            <span class="btn-text">Đặt bàn</span>
            <span class="btn-loading" style="display: none;">Đang xử lý...</span>
        </button>
    </form>
    
    <div id="rbm-booking-result" style="display: none;"></div>
</div>