<?php
// Bắt đầu session để biết người dùng đã đăng nhập chưa
session_start();
// Nối vào database bainhom8 (file ketnoi.php tạo biến $conn)
require_once __DIR__ . '/ketnoi.php';

// $da_dang_nhap = true nếu đã đăng nhập (có user_id trong session)
$da_dang_nhap = isset($_SESSION['user_id']);
$user_id = $da_dang_nhap ? intval($_SESSION['user_id']) : 0;

// Lấy danh sách voucher_id mà user hiện tại đã sử dụng trong bảng orders
$used_voucher_ids = [];
if ($da_dang_nhap) {
    $stmt_used = mysqli_prepare($conn, "SELECT DISTINCT voucher_id FROM orders WHERE user_id = ? AND voucher_id IS NOT NULL");
    mysqli_stmt_bind_param($stmt_used, "i", $user_id);
    mysqli_stmt_execute($stmt_used);
    $res_used = mysqli_stmt_get_result($stmt_used);
    while ($row_used = mysqli_fetch_assoc($res_used)) {
        $used_voucher_ids[(int)$row_used['voucher_id']] = true;
    }
    mysqli_stmt_close($stmt_used);
}

// Lấy voucher, chỉ giữ voucher còn hiệu lực và chưa từng dùng bởi user này
$vouchers = [];
$r = mysqli_query($conn, "SELECT * FROM vouchers");
foreach (mysqli_fetch_all($r, MYSQLI_ASSOC) as $v) {
    // Bỏ qua voucher đã tắt (is_active = 0)
    if (isset($v['is_active']) && !$v['is_active']) continue;
    // Bỏ qua voucher chưa đến ngày bắt đầu
    if (!empty($v['date_from']) && date('Y-m-d') < $v['date_from']) continue;
    // Bỏ qua voucher đã hết hạn
    if (!empty($v['date_to']) && date('Y-m-d') > $v['date_to']) continue;
    // Bỏ qua voucher user này đã sử dụng
    if ($da_dang_nhap && isset($used_voucher_ids[(int)($v['voucher_id'] ?? 0)])) continue;

    $vouchers[] = $v;
}

// Đếm số món trong giỏ để hiện trên header
$cart_count = 0;
if ($da_dang_nhap) {
    $stmt = mysqli_prepare($conn, "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $cart_count = $data['total'] ? intval($data['total']) : 0;
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến mãi - FastFood</title>
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
                <a href="trangchu.php">Trang chủ</a>
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
            <p style="margin-bottom:15px;color:#777;">Mỗi đơn hàng chỉ được áp dụng 1 mã giảm giá và mỗi mã chỉ dùng 1 lần cho mỗi tài khoản.</p>

            <?php if (!empty($vouchers)) { ?>
                <?php foreach ($vouchers as $v) { ?>
                    <div class="promo-box" style="margin-bottom:15px;">
                        <div class="promo-icon">%</div>
                        <div class="promo-info">
                            <h3><?= htmlspecialchars($v['voucher_name'] ?? $v['code'] ?? '') ?></h3>
                            <p>Giảm <?= intval($v['discount_percent']) ?>% cho các món áp dụng</p>
                            <?php if (!empty($v['date_from']) && !empty($v['date_to'])) { ?>
                                <p>Hiệu lực: <?= date('d/m/Y', strtotime($v['date_from'])) ?> - <?= date('d/m/Y', strtotime($v['date_to'])) ?></p>
                            <?php } ?>
                            <?php if (!empty($v['code'])) { ?>
                                <p>Mã: <strong><?= htmlspecialchars($v['code']) ?></strong></p>
                            <?php } ?>
                        </div>
                        <a href="menu.php" class="promo-arrow">➔</a>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <p>Hiện chưa có chương trình khuyến mãi nào khả dụng cho tài khoản của bạn. <a href="menu.php">Xem thực đơn</a></p>
            <?php } ?>
        </section>
    </main>

</body>
</html>