<?php
session_start();
require_once __DIR__ . '/ketnoi.php';

// 1. Lấy 4 món ăn còn hàng có đánh giá cao nhất từ bảng foods
$sql_foods = "SELECT Foods_ID, Foods_Name, Foods_Price, Foods_Image, Foods_icon, Foods_Rating 
              FROM foods 
              WHERE Foods_Status = 'còn hàng' 
              ORDER BY Foods_Rating DESC, Foods_ID ASC 
              LIMIT 4";
$result_foods = mysqli_query($conn, $sql_foods);

// 2. Lấy voucher đang active mới nhất từ bảng vouchers
$sql_voucher = "SELECT voucher_name, discount_percent FROM vouchers WHERE is_active = 1 LIMIT 1";
$result_voucher = mysqli_query($conn, $sql_voucher);
$voucher = $result_voucher ? mysqli_fetch_assoc($result_voucher) : null;

// 3. Lấy tổng số lượng món trong giỏ hàng từ bảng cart_items
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $sql_cart = "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = $user_id";
    $result_cart = mysqli_query($conn, $sql_cart);
    if ($result_cart) {
        $cart_data = mysqli_fetch_assoc($result_cart);
        $cart_count = $cart_data['total'] ? (int)$cart_data['total'] : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - FastFood</title>
    <link rel="stylesheet" href="file css/trangchu.css">
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
                <a href="trangchu.php" class="active">Trang chủ</a>
                <a href="menu.php">Thực đơn</a>
                <a href="khuyenmai.php">Khuyến mãi</a>
            
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

    <!-- ================= NỘI DUNG TRANG CHỦ ================= -->
    <main class="home-container">

        <!-- BANNER QUẢNG CÁO -->
        <section class="banner">
            <div class="banner-text">
                <h2>Ngon tuyệt<br>Giao tận nơi!</h2>
                <p>Thức ăn nhanh - Đậm vị - Tiện lợi</p>
                <a href="menu.php" class="btn-order">Đặt món ngay ➔</a>
            </div>
            <div class="banner-icon">
                🍔 🍟 🥤
            </div>
        </section>

        <!-- MÓN NỔI BẬT -->
        <section class="featured-section">
            <h2>Món nổi bật</h2>
            
            <div class="featured-grid">
                <?php 
                if ($result_foods && mysqli_num_rows($result_foods) > 0) {
                    while ($food = mysqli_fetch_assoc($result_foods)) { 
                ?>
                    <a href="chitietsanpham.php?id=<?= (int)$food['Foods_ID'] ?>" class="featured-card">
                        <div class="food-icon">
                            <?php if (!empty($food['Foods_icon'])): ?>
                                <?= htmlspecialchars($food['Foods_icon']) ?>
                            <?php elseif (!empty($food['Foods_Image'])): ?>
                                <img src="images/<?= htmlspecialchars($food['Foods_Image']) ?>" alt="<?= htmlspecialchars($food['Foods_Name']) ?>">
                            <?php else: ?>
                                🍔
                            <?php endif; ?>
                        </div>
                        <h3><?= htmlspecialchars($food['Foods_Name']) ?></h3>
                        <p class="price"><?= number_format($food['Foods_Price'], 0, ',', '.') ?>đ</p>
                    </a>
                <?php 
                    }
                } else {
                    echo "<p>Chưa có món ăn nổi bật.</p>";
                }
                ?>
            </div>
        </section>

        <!-- BANNER KHUYẾN MÃI -->
        <section class="promo-box">
            <div class="promo-icon">%</div>
            <div class="promo-info">
                <h3><?= isset($voucher['voucher_name']) ? htmlspecialchars($voucher['voucher_name']) : 'Khuyến mãi hôm nay' ?></h3>
                <p><?= isset($voucher['discount_percent']) ? 'Giảm ' . $voucher['discount_percent'] . '% cho các đơn hàng ưu đãi' : 'Giảm 20% cho đơn từ 150.000đ' ?></p>
            </div>
            <a href="khuyenmai.php" class="promo-arrow">➔</a>
        </section>

    </main>

</body>
</html>