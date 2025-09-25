/**
 * Restaurant Booking Manager - Frontend JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is required for Restaurant Booking Manager');
        return;
    }
    
    jQuery(document).ready(function($) {
        var $form = $('#rbm-booking-form');
        var $submitBtn = $('#rbm-submit-btn');
        var $result = $('#rbm-booking-result');
        var $availabilityStatus = $('#availability-status');
        
        // Check availability when inputs change
        $('#booking_date, #booking_time, #party_size').on('change', function() {
            checkAvailability();
        });
        
        function checkAvailability() {
            var date = $('#booking_date').val();
            var time = $('#booking_time').val();
            var party_size = $('#party_size').val();
            
            if (date && time && party_size) {
                $.ajax({
                    url: rbm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'check_availability',
                        date: date,
                        time: time,
                        party_size: party_size,
                        nonce: rbm_ajax.nonce
                    },
                    beforeSend: function() {
                        $availabilityStatus.html('<div style="text-align: center; padding: 10px;">Đang kiểm tra...</div>');
                    },
                    success: function(response) {
                        $availabilityStatus.removeClass('availability-available availability-limited availability-full');
                        
                        if (response.success && response.data) {
                            var available = parseInt(response.data.available_seats);
                            var partySize = parseInt(party_size);
                            
                            if (available >= partySize) {
                                if (available > partySize * 2) {
                                    $availabilityStatus.addClass('availability-available');
                                    $availabilityStatus.html('✓ Còn nhiều chỗ trống (' + available + ' chỗ)');
                                } else {
                                    $availabilityStatus.addClass('availability-limited');
                                    $availabilityStatus.html('⚠ Chỗ còn hạn (' + available + ' chỗ còn lại)');
                                }
                                $submitBtn.prop('disabled', false);
                            } else {
                                $availabilityStatus.addClass('availability-full');
                                $availabilityStatus.html('✗ Đã hết chỗ trong khung giờ này');
                                $submitBtn.prop('disabled', true);
                            }
                        } else {
                            $availabilityStatus.addClass('availability-full');
                            $availabilityStatus.html('⚠ Không thể kiểm tra chỗ trống');
                            $submitBtn.prop('disabled', true);
                        }
                    },
                    error: function() {
                        $availabilityStatus.addClass('availability-full');
                        $availabilityStatus.html('⚠ Lỗi kết nối. Vui lòng thử lại.');
                        $submitBtn.prop('disabled', true);
                    }
                });
            } else {
                $availabilityStatus.empty();
                $submitBtn.prop('disabled', false);
            }
        }
        
        // Submit booking form
        $form.on('submit', function(e) {
            e.preventDefault();
            
            if ($submitBtn.prop('disabled')) {
                return;
            }
            
            var formData = {
                action: 'submit_booking',
                customer_name: $('#customer_name').val().trim(),
                customer_phone: $('#customer_phone').val().trim(),
                customer_email: $('#customer_email').val().trim(),
                party_size: $('#party_size').val(),
                booking_date: $('#booking_date').val(),
                booking_time: $('#booking_time').val(),
                special_requests: $('#special_requests').val().trim(),
                nonce: rbm_ajax.nonce
            };
            
            // Basic validation
            if (!formData.customer_name || !formData.customer_phone || !formData.customer_email || 
                !formData.party_size || !formData.booking_date || !formData.booking_time) {
                $result.show().html('<div class="rbm-error">Vui lòng điền đầy đủ thông tin bắt buộc.</div>');
                $result[0].scrollIntoView({behavior: 'smooth'});
                return;
            }
            
            // Email validation
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(formData.customer_email)) {
                $result.show().html('<div class="rbm-error">Email không hợp lệ.</div>');
                $result[0].scrollIntoView({behavior: 'smooth'});
                return;
            }
            
            // Phone validation  
            var phoneRegex = /^[0-9+\-\s]+$/;
            if (!phoneRegex.test(formData.customer_phone) || formData.customer_phone.length < 10) {
                $result.show().html('<div class="rbm-error">Số điện thoại không hợp lệ.</div>');
                $result[0].scrollIntoView({behavior: 'smooth'});
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $('.btn-text').hide();
            $('.btn-loading').show();
            
            $.ajax({
                url: rbm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                timeout: 30000,
                success: function(response) {
                    $result.show();
                    
                    if (response.success && response.data) {
                        $result.html('<div class="rbm-success">' + response.data.message + '</div>');
                        $form[0].reset();
                        $availabilityStatus.empty();
                        
                        // Scroll to result
                        $result[0].scrollIntoView({behavior: 'smooth'});
                        
                        // Show success animation
                        $result.hide().fadeIn(500);
                        
                    } else {
                        var message = response.data && response.data.message ? 
                                     response.data.message : 'Có lỗi xảy ra. Vui lòng thử lại.';
                        $result.html('<div class="rbm-error">' + message + '</div>');
                        $result[0].scrollIntoView({behavior: 'smooth'});
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Có lỗi kết nối. Vui lòng kiểm tra internet và thử lại.';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Kết nối quá chậm. Vui lòng thử lại.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Lỗi máy chủ. Vui lòng thử lại sau.';
                    }
                    
                    $result.show().html('<div class="rbm-error">' + errorMessage + '</div>');
                    $result[0].scrollIntoView({behavior: 'smooth'});
                },
                complete: function() {
                    // Hide loading state
                    $submitBtn.prop('disabled', false);
                    $('.btn-text').show();
                    $('.btn-loading').hide();
                }
            });
        });
        
        // Phone number formatting
        $('#customer_phone').on('input', function() {
            var value = $(this).val().replace(/\D/g, '');
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            $(this).val(value);
        });
        
        // Name validation (only letters and spaces)
        $('#customer_name').on('input', function() {
            var value = $(this).val();
            // Allow Vietnamese characters, spaces, and common punctuation
            var validName = value.replace(/[^a-zA-ZÀ-ỹ\s\.]/g, '');
            if (value !== validName) {
                $(this).val(validName);
            }
        });
        
        // Date validation (prevent past dates)
        $('#booking_date').on('change', function() {
            var selectedDate = new Date($(this).val());
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                $(this).val('');
                alert('Không thể chọn ngày trong quá khứ.');
            }
        });
        
        // Auto-hide messages after 10 seconds
        $(document).on('click', '.rbm-success, .rbm-error', function() {
            $(this).fadeOut(300);
        });
        
        setTimeout(function() {
            $('.rbm-success, .rbm-error').fadeOut(300);
        }, 10000);
    });
});