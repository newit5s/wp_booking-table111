<?php
/**
 * Admin bookings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Quản lý đặt bàn</h1>
    
    <!-- Statistics -->
    <div class="rbm-stats" style="margin: 20px 0;">
        <div class="rbm-stat-boxes" style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div class="rbm-stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;">
                <h3 style="margin: 0; color: #666;">Tổng đặt bàn</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 5px 0 0 0; color: #333;"><?php echo $stats['total']; ?></p>
            </div>
            <div class="rbm-stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;">
                <h3 style="margin: 0; color: #666;">Chờ xác nhận</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 5px 0 0 0; color: #f39c12;"><?php echo $stats['pending']; ?></p>
            </div>
            <div class="rbm-stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;">
                <h3 style="margin: 0; color: #666;">Đã xác nhận</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 5px 0 0 0; color: #27ae60;"><?php echo $stats['confirmed']; ?></p>
            </div>
            <div class="rbm-stat-box" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;">
                <h3 style="margin: 0; color: #666;">Hôm nay</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 5px 0 0 0; color: #3498db;"><?php echo $stats['today']; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="page" value="restaurant-bookings">
                
                <select name="status" style="min-width: 120px;">
                    <option value="all" <?php selected($filters['status'], 'all'); ?>>Tất cả trạng thái</option>
                    <option value="pending" <?php selected($filters['status'], 'pending'); ?>>Chờ xác nhận</option>
                    <option value="confirmed" <?php selected($filters['status'], 'confirmed'); ?>>Đã xác nhận</option>
                    <option value="cancelled" <?php selected($filters['status'], 'cancelled'); ?>>Đã hủy</option>
                </select>
                
                <input type="date" name="date" value="<?php echo esc_attr($filters['date']); ?>" style="min-width: 150px;">
                
                <input type="text" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Tìm theo tên, SĐT, email..." style="min-width: 200px;">
                
                <input type="submit" class="button" value="Lọc">
                
                <?php if ($filters['status'] !== 'all' || $filters['date'] || $filters['search']): ?>
                    <a href="<?php echo admin_url('admin.php?page=restaurant-bookings'); ?>" class="button">Xóa bộ lọc</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Bookings Table -->
    <?php if (empty($bookings)): ?>
        <div class="notice notice-info" style="margin: 20px 0;">
            <p><strong>Không có đặt bàn nào</strong> phù hợp với tiêu chí tìm kiếm.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">Mã</th>
                    <th>Khách hàng</th>
                    <th>Liên hệ</th>
                    <th style="width: 80px;">Số khách</th>
                    <th style="width: 120px;">Ngày/Giờ</th>
                    <th style="width: 100px;">Trạng thái</th>
                    <th>Ghi chú</th>
                    <th style="width: 150px;">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><strong>#<?php echo $booking->id; ?></strong></td>
                    <td>
                        <strong><?php echo esc_html($booking->customer_name); ?></strong>
                        <div style="color: #666; font-size: 12px;">
                            <?php echo date('d/m/Y H:i', strtotime($booking->created_at)); ?>
                        </div>
                    </td>
                    <td>
                        <div><?php echo esc_html($booking->customer_phone); ?></div>
                        <div style="color: #666; font-size: 12px;">
                            <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>">
                                <?php echo esc_html($booking->customer_email); ?>
                            </a>
                        </div>
                    </td>
                    <td><strong><?php echo $booking->party_size; ?></strong> người</td>
                    <td>
                        <div><strong><?php echo date('d/m/Y', strtotime($booking->booking_date)); ?></strong></div>
                        <div style="color: #666;"><?php echo date('H:i', strtotime($booking->booking_time)); ?></div>
                    </td>
                    <td>
                        <span class="<?php echo rbm_get_status_class($booking->status); ?>">
                            <?php echo rbm_format_booking_status($booking->status); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($booking->special_requests): ?>
                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="<?php echo esc_attr($booking->special_requests); ?>">
                                <?php echo esc_html(substr($booking->special_requests, 0, 50)) . (strlen($booking->special_requests) > 50 ? '...' : ''); ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #999;">Không có</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($booking->status === 'pending'): ?>
                            <form method="post" style="display: inline-block; margin-right: 5px;">
                                <?php wp_nonce_field('rbm_admin_action'); ?>
                                <input type="hidden" name="booking_id" value="<?php echo $booking->id; ?>">
                                <input type="hidden" name="action" value="confirm_booking">
                                <input type="submit" class="button button-primary button-small" value="Xác nhận" 
                                       onclick="return confirm('Xác nhận đặt bàn này và gửi email cho khách hàng?')">
                            </form>
                            
                            <form method="post" style="display: inline-block;">
                                <?php wp_nonce_field('rbm_admin_action'); ?>
                                <input type="hidden" name="booking_id" value="<?php echo $booking->id; ?>">
                                <input type="hidden" name="action" value="cancel_booking">
                                <input type="submit" class="button button-secondary button-small" value="Hủy" 
                                       onclick="return confirm('Hủy đặt bàn này? Chỗ ngồi sẽ được trả lại.')">
                            </form>
                        <?php elseif ($booking->status === 'confirmed'): ?>
                            <span style="color: #27ae60;">✓ Đã xác nhận</span>
                            <?php if ($booking->confirmed_at): ?>
                                <div style="font-size: 11px; color: #666;">
                                    <?php echo date('d/m H:i', strtotime($booking->confirmed_at)); ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #999;">Đã hủy</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>