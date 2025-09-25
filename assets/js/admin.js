/**
 * Restaurant Booking Manager - Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Confirmation dialogs for booking actions
    $('.wp-list-table form input[type="submit"]').on('click', function(e) {
        var action = $(this).siblings('input[name="action"]').val();
        var bookingId = $(this).siblings('input[name="booking_id"]').val();
        var customerName = $(this).closest('tr').find('td:nth-child(2) strong').text();
        
        var confirmMessage = '';
        
        if (action === 'confirm_booking') {
            confirmMessage = 'Xác nhận đặt bàn #' + bookingId + ' của ' + customerName + '?\n\n';
            confirmMessage += 'Email xác nhận sẽ được gửi tự động cho khách hàng.';
        } else if (action === 'cancel_booking') {
            confirmMessage = 'Hủy đặt bàn #' + bookingId + ' của ' + customerName + '?\n\n';
            confirmMessage += 'Chỗ ngồi sẽ được trả lại và có thể đặt lại.';
        }
        
        if (confirmMessage && !confirm(confirmMessage)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Auto-hide notices after 5 seconds
    $('.notice').each(function() {
        var $notice = $(this);
        if (!$notice.hasClass('notice-error')) {
            setTimeout(function() {
                $notice.fadeOut(300);
            }, 5000);
        }
    });
    
    // Enhanced table interactions
    $('.wp-list-table tbody tr').hover(
        function() {
            $(this).addClass('hover');
        },
        function() {
            $(this).removeClass('hover');
        }
    );
    
    // Settings form validation
    $('#restaurant_capacity').on('input', function() {
        var value = parseInt($(this).val());
        var $submit = $('input[name="save_settings"]');
        
        if (isNaN(value) || value < 1 || value > 1000) {
            $(this).css('border-color', '#dc3545');
            $submit.prop('disabled', true);
            
            if (!$('.capacity-error').length) {
                $(this).after('<div class="capacity-error" style="color: #dc3545; font-size: 12px; margin-top: 5px;">Sức chứa phải từ 1 đến 1000</div>');
            }
        }