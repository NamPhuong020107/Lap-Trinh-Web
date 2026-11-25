<?php
session_start();
require_once __DIR__ . '/ketnoi.php';

// Lấy user_id từ Session đăng nhập (mặc định gán = 3 nếu chưa có)
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 3;

// =========================================================================
// XỬ LÝ AJAX KHI KHÁCH HÀNG BẤM NÚT "+" Ở TRANG THỰC ĐƠN (menu.php)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cart'])) {
    header('Content-Type: application/json');

    $food_id = intval($_POST['food_id'] ?? 0);
    $action  = $_POST['action'] ?? 'add';

    if ($food_id > 0) {
        if ($action === 'add' || $action === 'increase') {
            $sql = "INSERT INTO cart_items (user_id, Foods_ID, Cart_Items_Quantity) 
                    VALUES (?, ?, 1) 
                    ON DUPLICATE KEY UPDATE Cart_Items_Quantity = Cart_Items_Quantity + 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $food_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($action === 'decrease') {
            $sql = "UPDATE cart_items 
                    SET Cart_Items_Quantity = Cart_Items_Quantity - 1 
                    WHERE user_id = ? AND Foods_ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $food_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Tự động xóa sản phẩm nếu số lượng giảm về <= 0
            mysqli_query($conn, "DELETE FROM cart_items WHERE Cart_Items_Quantity <= 0");
        }

        // Đếm lại tổng số lượng trong giỏ hàng
        $sql_total = "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?";
        $stmt_total = mysqli_prepare($conn, $sql_total);
        mysqli_stmt_bind_param($stmt_total, "i", $user_id);
        mysqli_stmt_execute($stmt_total);
        $res_total = mysqli_stmt_get_result($stmt_total);
        $row_total = mysqli_fetch_assoc($res_total);
        $total_items = $row_total['total'] ?? 0;
        mysqli_stmt_close($stmt_total);

        echo json_encode([
            'status'      => 'success',
            'total_items' => $total_items
        ]);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Sản phẩm không hợp lệ'
        ]);
    }
    exit();
}

// =========================================================================
// XỬ LÝ KHI NGƯỜI DÙNG BẤM NÚT "+" GỬI FORM THÔNG THƯỜNG
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_add_cart'])) {
    $food_id  = intval($_POST['food_id'] ?? 0);
    $action   = $_POST['action'] ?? 'add';
    $cat_post = $_POST['cat'] ?? 'all';

    if ($food_id > 0) {
        if ($action === 'add' || $action === 'increase') {
            $sql = "INSERT INTO cart_items (user_id, Foods_ID, Cart_Items_Quantity) 
                    VALUES (?, ?, 1) 
                    ON DUPLICATE KEY UPDATE Cart_Items_Quantity = Cart_Items_Quantity + 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $food_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($action === 'decrease') {
            $sql = "UPDATE cart_items 
                    SET Cart_Items_Quantity = Cart_Items_Quantity - 1 
                    WHERE user_id = ? AND Foods_ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $food_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_query($conn, "DELETE FROM cart_items WHERE Cart_Items_Quantity <= 0");
        }
    }

    // Tải lại chính trang menu.php đúng danh mục đang xem
    header("Location: menu.php?cat=" . urlencode($cat_post));
    exit();
}

// =========================================================================
// HIỂN THỊ DANH SÁCH SẢN PHẨM & TỔNG GIỎ HÀNG
// =========================================================================

// 1. Lấy mã danh mục từ URL
$cat = $_GET['cat'] ?? 'all';

// Map mã danh mục trên URL với Category_id trong CSDL
$category_map = [
    'burger'            => 1,
    'spaghetti'         => 2,
    'pizza'             => 3,
    'garan'             => 4,
    'nuocngot'          => 5,
    'khoaitaychien'     => 6
];

// 2. Truy vấn danh sách món ăn từ CSDL
if (isset($category_map[$cat])) {
    $category_id = $category_map[$cat];
    $sql_foods   = "SELECT * FROM foods WHERE Category_id = ?";
    $stmt_foods  = mysqli_prepare($conn, $sql_foods);
    mysqli_stmt_bind_param($stmt_foods, "i", $category_id);
    mysqli_stmt_execute($stmt_foods);
    $res_foods = mysqli_stmt_get_result($stmt_foods);
    $foods     = mysqli_fetch_all($res_foods, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_foods);
} else {
    $sql_foods = "SELECT * FROM foods";
    $res_foods = mysqli_query($conn, $sql_foods);
    $foods     = mysqli_fetch_all($res_foods, MYSQLI_ASSOC);
}

// 3. Đếm tổng số món trong giỏ hàng để hiển thị lên Header
$sql_cart_total  = "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?";
$stmt_cart_total = mysqli_prepare($conn, $sql_cart_total);
mysqli_stmt_bind_param($stmt_cart_total, "i", $user_id);
mysqli_stmt_execute($stmt_cart_total);
$res_cart_total  = mysqli_stmt_get_result($stmt_cart_total);
$row_cart_total  = mysqli_fetch_assoc($res_cart_total);
$cart_total      = $row_cart_total['total'] ?? 0;
mysqli_stmt_close($stmt_cart_total);
?>


<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thực đơn - FastFood</title>
    <link rel="stylesheet" href="file css/menu.css">
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
                <a href="menu.php" class="active">Thực đơn</a>
                <a href="khuyenmai.php">Khuyến mãi</a>
              
            </nav>

            <!-- ICON -->
            <div class="header-actions">
                <a href="searchmonan.php">🔍</a>
                <a href="giohang.php">
                    🛒 <?php if ($cart_total > 0): ?>
                        <span class="cart-badge"><?= $cart_total ?></span>
                    <?php endif; ?>
                </a>
                <a href="taikhoan.php">👤</a>
            </div>
        </div>
    </header>

    <!-- ================= PHẦN NỘI DUNG TRANG ================= -->
    <section class="menu-page">
        <h1 class="menu-page-title">Thực đơn</h1>

        <div class="menu-container">

            <!-- ================= BÊN TRÁI (SIDEBAR DANH MỤC SẢN PHẨM) ================= -->
            <div class="menu-sidebar">
                <h2 class="category-title">Danh mục</h2>

                <div class="menu-category-list">
                    <a href="menu.php?cat=all" class="category-btn <?= ($cat == 'all') ? 'active' : '' ?>">
                        Tất cả
                    </a>
                    <a href="menu.php?cat=burger" class="category-btn <?= ($cat == 'burger') ? 'active' : '' ?>">
                        🍔 Burger
                    </a>
                    <a href="menu.php?cat=garan" class="category-btn <?= ($cat == 'garan') ? 'active' : '' ?>">
                        🍗 Gà rán
                    </a>
                    <a href="menu.php?cat=pizza" class="category-btn <?= ($cat == 'pizza') ? 'active' : '' ?>">
                        🍕 Pizza
                    </a>
                    <a href="menu.php?cat=khoaitaychien" class="category-btn <?= ($cat == 'khoaitaychien') ? 'active' : '' ?>">
                        🍟 Khoai tây
                    </a>
                    <a href="menu.php?cat=nuocngot" class="category-btn <?= ($cat == 'nuocngot') ? 'active' : '' ?>">
                        🥤 Đồ uống
                    </a>
                    <a href="menu.php?cat=spaghetti" class="category-btn <?= ($cat == 'spaghetti') ? 'active' : '' ?>">
                        🍝 Spaghetti
                    </a>
                </div>
            </div>

            <!-- ================= BÊN PHẢI (DANH SÁCH MÓN ĂN) ================= -->
            <div class="menu-content">

                <div class="menu-food-grid">
                    <?php if (!empty($foods)): ?>
                        <?php foreach ($foods as $food): ?>
                            <div class="menu-food-card">
                                <a href="chitietsanpham.php?id=<?= $food['Foods_ID'] ?>" class="menu-food-link">
                                    <div class="menu-food-icon">
                                        <?= htmlspecialchars($food['Foods_icon'] ?? '🍔') ?>
                                    </div>
                                    <h3><?= htmlspecialchars($food['Foods_Name']) ?></h3>
                                    <p class="menu-price"><?= number_format($food['Foods_Price'], 0, ',', '.') ?>đ</p>
                                </a>

                                <!-- FORM PHP THUẦN GỬI VỀ TRANG XỬ LÝ -->
                                <form action="menu.php" method="POST">
                                    <input type="hidden" name="food_id" value="<?= $food['Foods_ID'] ?>">
                                    <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <button type="submit" name="btn_add_cart" class="menu-add">+</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="grid-column: 1/-1; text-align: center; padding: 30px; color: #777;">
                            Không có sản phẩm nào trong danh mục này!
                        </p>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </section>

</body>

</html>