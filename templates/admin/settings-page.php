<?php
/**
 * Admin settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Cài đặt nhà hàng</h1>
    
    <form method="post" class="rbm-settings-form">
        <?php wp_nonce_field('rbm_settings'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="restaurant_capacity">Sức chứa tối đa</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="restaurant_capacity" 
                               name="restaurant_capacity" 
                               value="<?php echo esc_attr($capacity); ?>" 
                               min="1" 
                               max="1000" 
                               class="regular-text" 
                               required>
                        <p class="description">
                            Số lượng khách tối đa mà nhà hàng có thể phục vụ trong một khung giờ (30 phút).
                            <br>Khuyến nghị: Tính dựa trên số bàn × số ghế/bàn.
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" 
                   name="save_settings" 
                   class="button-primary" 
                   value="Lưu cài đặt">
        </p>
    </form>
    
    <hr>
    
    <div class="rbm-help-section">
        <div class="postbox" style="margin-top: 20px;">
            <div class="postbox-header">
                <h2>Hướng dẫn sử dụng</h2>
            </div>
            <div class="inside" style="padding: 20px;">
                
                <div class="rbm-help-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 30px;">
                    
                    <div class="rbm-help-item">
                        <h3 style="color: #3498db;">Hiển thị form đặt bàn</h3>
                        <p>Thêm shortcode sau vào trang hoặc bài viết:</p>
                        <code style="background: #f4f4f4; padding: 8px; display: block; border-radius: 4px;">[restaurant_booking_form]</code>
                        
                        <h4>Tùy chỉnh shortcode:</h4>
                        <ul style="font-size: 14px;">
                            <li><code>capacity="50"</code> - Sức chứa tùy chỉnh</li>
                            <li><code>max_party_size="10"</code> - Số khách tối đa/lần đặt</li>
                            <li><code>start_time="09:00"</code> - Giờ mở cửa</li>
                            <li><code>end_time="23:00"</code> - Giờ đóng cửa</li>
                        </ul>
                    </div>
                    
                    <div class="rbm-help-item">
                        <h3 style="color: #27ae60;">Quy trình hoạt động</h3>
                        <ol style="font-size: 14px;">
                            <li><strong>Khách đặt bàn:</strong> Điền form trên website</li>
                            <li><strong>Kiểm tra chỗ:</strong> Hệ thống tự động kiểm tra</li>
                            <li><strong>Lưu thông tin:</strong> Trạng thái "Chờ xác nhận"</li>
                            <li><strong>Thông báo admin:</strong> Email tự động</li>
                            <li><strong>Admin xử lý:</strong> Xác nhận/Hủy trong dashboard</li>
                            <li><strong>Email khách:</strong> Thông báo kết quả</li>
                        </ol>
                    </div>
                    
                    <div class="rbm-help-item">
                        <h3 style="color: #f39c12;">Quản lý chỗ ngồi</h3>
                        <ul style="font-size: 14px;">
                            <li><strong>Tự động tính:</strong> Chỗ trống theo khung giờ 30p</li>
                            <li><strong>Hiển thị trạng thái:</strong> Còn chỗ/Có hạn/Hết chỗ</li>
                            <li><strong>Reset tự động:</strong> Khi hủy đặt bàn</li>
                            <li><strong>Flexible:</strong> Điều chỉnh theo thực tế</li>
                        </ul>
                    </div>
                    
                    <div class="rbm-help-item">
                        <h3 style="color: #e74c3c;">Hệ thống email</h3>
                        <ul style="font-size: 14px;">
                            <li><strong>Cho admin:</strong> Thông báo đặt bàn mới ngay lập tức</li>
                            <li><strong>Cho khách:</strong> Xác nhận sau khi admin duyệt</li>
                            <li><strong>Template đẹp:</strong> Thông tin đầy đủ, chuyên nghiệp</li>
                            <li><strong>Tự động:</strong> Không cần can thiệp thủ công</li>
                        </ul>
                    </div>
                    
                    <div class="rbm-help-item">
                        <h3 style="color: #9b59b6;">Tips sử dụng</h3>
                        <ul style="font-size: 14px;">
                            <li>Kiểm tra email thường xuyên</li>
                            <li>Sử dụng bộ lọc để quản lý hiệu quả</li>
                            <li>Điều chỉnh sức chứa phù hợp không gian</li>
                            <li>Form responsive - hoạt động mọi thiết bị</li>
                            <li>Backup database định kỳ</li>
                        </ul>
                    </div>
                    
                    <div class="rbm-help-item">
                        <h3 style="color: #34495e;">Tính năng nâng cao</h3>
                        <p style="font-size: 14px;">Plugin có thể mở rộng thêm:</p>
                        <ul style="font-size: 14px;">
                            <li>SMS notifications</li>
                            <li>Payment integration</li>
                            <li>Customer dashboard</li>
                            <li>Table management với sơ đồ</li>
                            <li>Multi-location support</li>
                            <li>Analytics & reporting</li>
                        </ul>
                    </div>
                    
                </div>
                
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; margin-top: 30px;">
                    <h3 style="margin: 0 0 15px 0; color: white;">MVP Features - Hoàn chỉnh!</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <div>
                            <h4 style="color: #fff; margin: 0 0 8px 0;">Frontend</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                                <li>Form đặt bàn responsive</li>
                                <li>Kiểm tra chỗ trống real-time</li>
                                <li>Validation & UX tốt</li>
                                <li>Mobile-friendly</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: #fff; margin: 0 0 8px 0;">Backend</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                                <li>Dashboard quản lý đầy đủ</li>
                                <li>Xác nhận/Hủy đặt bàn</li>
                                <li>Thống kê & báo cáo</li>
                                <li>Lọc & tìm kiếm</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: #fff; margin: 0 0 8px 0;">Tự động hóa</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                                <li>Email notifications</li>
                                <li>Quản lý capacity thông minh</li>
                                <li>Security & validation</li>
                                <li>Single file - dễ cài đặt</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div style="background: #f8f9fa; padding: 20px; border-left: 4px solid #007cba; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px 0;">Cài đặt nhanh trong 3 bước:</h4>
                    <ol style="margin: 0;">
                        <li>Upload folder plugin vào <code>/wp-content/plugins/</code></li>
                        <li>Kích hoạt plugin trong WordPress Admin</li>
                        <li>Thêm shortcode <code>[restaurant_booking_form]</code> vào trang muốn hiển thị</li>
                    </ol>
                </div>
                
            </div>
        </div>
    </div>
</div>
