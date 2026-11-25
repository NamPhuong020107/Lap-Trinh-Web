<?php
// Bắt đầu session để biết người dùng đã đăng nhập chưa
session_start();
// Nối vào database bainhom8 (file ketnoi.php tạo biến $conn)
require_once __DIR__ . '/ketnoi.php';

// $da_dang_nhap = true nếu đã đăng nhập (có user_id trong session)
$da_dang_nhap = isset($_SESSION['user_id']);

// Lấy voucher, chỉ giữ voucher còn hiệu lực (kiểm tra cột nào có thì dùng)
$vouchers = [];

// Lấy tất cả voucher trong bảng vouchers
$r = mysqli_query($conn, "SELECT * FROM vouchers");
// Duyệt từng voucher, bỏ qua voucher không còn hiệu lực
foreach (mysqli_fetch_all($r, MYSQLI_ASSOC) as $v) {
    // Bỏ qua voucher đã tắt (is_active = 0)
    if (isset($v['is_active']) && !$v['is_active']) continue;
    // Bỏ qua voucher chưa đến ngày bắt đầu
    if (!empty($v['date_from']) && date('Y-m-d') < $v['date_from']) continue;
    // Bỏ qua voucher đã hết hạn
    if (!empty($v['date_to']) && date('Y-m-d') > $v['date_to']) continue;
    // Voucher hợp lệ thì thêm vào danh sách $vouchers để hiển thị
    $vouchers[] = $v;
}

// Đếm số món trong giỏ để hiện trên header
// $cart_count: số món trong giỏ, hiện trên biểu tượng giỏ hàng
$cart_count = 0;
// Chỉ đếm khi đã đăng nhập
if ($da_dang_nhap) {
    $user_id = intval($_SESSION['user_id']);
    // Tính tổng số lượng trong giỏ của user (prepared statement, dấu ? sẽ được thay bằng $user_id)
    $stmt = mysqli_prepare($conn, "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    // Lấy kết quả tổng; nếu giỏ trống thì bằng 0
    $cart_count = $data['total'] ? intval($data['total']) : 0;
    mysqli_stmt_close($stmt);
}
?>
<!-- Phần giao diện HTML bắt đầu từ đây -->
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến mãi - FastFood</title>
    <!-- CSS riêng của trang khuyến mãi -->
    <link rel="stylesheet" href="file css/khuyenmai.css">
</head>
<body>

     <!-- ================= HEADER ================= -->
    <header class="header">
        <div class="header-container">
            <!-- LOGO -->
            <div class="logo">
                <a href="trangchu.php">
                    <span class="logo-icon">🍔</span>
                    <span class="logo-fast">Fast</span>
                    <span class="logo-food">Food</span>
                </a>
            </div>

            <!-- MENU -->
            <nav class="navbar">
                <a href="trangchu.php" >Trang chủ</a>
                <a href="menu.php">Thực đơn</a>
                <a href="khuyenmai.php" class="active">Khuyến mãi</a>
                
            </nav>

            <!-- ICON -->
            <div class="header-actions">
                <a href="searchmonan.php">🔍</a>
                <a href="giohang.php">
                    🛒 <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="taikhoan.php">👤</a>
            </div>
        </div>
    </header>

    <!-- Nội dung chính: danh sách khuyến mãi -->
    <main class="home-container">
        <section class="featured-section">
            <h2>Khuyến mãi</h2>
            <p style="margin-bottom:15px;color:#777;">Mỗi đơn hàng chỉ được áp dụng 1 mã giảm giá.</p>

            <!-- Có voucher thì hiện danh sách, không thì hiện thông báo ở cuối -->
            <?php if (!empty($vouchers)) { ?>
                <!-- Lặp qua từng voucher, mỗi voucher là một khung promo-box -->
                <?php foreach ($vouchers as $v) { ?>
                    <div class="promo-box" style="margin-bottom:15px;">
                        <div class="promo-icon">%</div>
                        <div class="promo-info">
                            <!-- Tên voucher (htmlspecialchars chống chèn mã HTML) -->
                            <h3><?= htmlspecialchars($v['voucher_name'] ?? $v['code'] ?? '') ?></h3>
                            <!-- Phần trăm giảm áp dụng cho toàn bộ đơn hàng -->
                            <p>Giảm <?= intval($v['discount_percent']) ?>% cho <strong>toàn bộ đơn hàng</strong></p>
                            <!-- Ngày bắt đầu - kết thúc hiệu lực (định dạng ngày/tháng/năm) -->
                            <?php if (!empty($v['date_from']) && !empty($v['date_to'])) { ?>
                                <p>Hiệu lực: <?= date('d/m/Y', strtotime($v['date_from'])) ?> - <?= date('d/m/Y', strtotime($v['date_to'])) ?></p>
                            <?php } ?>
                            <!-- Mã voucher để khách nhập khi thanh toán -->
                            <?php if (!empty($v['code'])) { ?>
                                <p>Mã: <strong><?= htmlspecialchars($v['code']) ?></strong></p>
                            <?php } ?>
                        </div>
                        <a href="menu.php" class="promo-arrow">➔</a>
                    </div>
                <?php } ?>
            <!-- Không có voucher nào hợp lệ -->
            <?php } else { ?>
                <p>Hiện chưa có chương trình khuyến mãi nào. <a href="menu.php">Xem thực đơn</a></p>
            <?php } ?>
        </section>
    </main>

</body>
</html>