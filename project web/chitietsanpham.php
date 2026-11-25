<?php
session_start();
require_once __DIR__ . '/ketnoi.php';

$loi = "";
$thanhcong = "";

if (isset($_SESSION['thanhcong'])) {
    $thanhcong = $_SESSION['thanhcong'];
    unset($_SESSION['thanhcong']);
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: menu.php");
    exit;
}

$foods_id = intval($_GET['id']);
$sql_detail = "SELECT f.*, c.category_name 
               FROM foods f 
               LEFT JOIN categories c ON f.category_id = c.category_id 
               WHERE f.Foods_ID = ?";
$stmt_detail = mysqli_prepare($conn, $sql_detail);
mysqli_stmt_bind_param($stmt_detail, "i", $foods_id);
mysqli_stmt_execute($stmt_detail);
$result_detail = mysqli_stmt_get_result($stmt_detail);

if (!$result_detail || mysqli_num_rows($result_detail) === 0) {
    header("Location: menu.php");
    exit;
}

$food = mysqli_fetch_assoc($result_detail);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: dangnhap.php");
        exit;
    }

    $user_id = intval($_SESSION['user_id']);
    $soluong = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    if ($soluong < 1) $soluong = 1;

    $sql_check_cart = "SELECT Cart_Items_Quantity FROM cart_items WHERE user_id = ? AND Foods_ID = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check_cart);
    mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $foods_id);
    mysqli_stmt_execute($stmt_check);
    $res_check = mysqli_stmt_get_result($stmt_check);

    if (mysqli_num_rows($res_check) > 0) {
        $sql_update = "UPDATE cart_items 
                       SET Cart_Items_Quantity = Cart_Items_Quantity + ? 
                       WHERE user_id = ? AND Foods_ID = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "iii", $soluong, $user_id, $foods_id);
        mysqli_stmt_execute($stmt_update);
    } else {
        $sql_insert = "INSERT INTO cart_items (user_id, Foods_ID, Cart_Items_Quantity) VALUES (?, ?, ?)";
        $stmt_insert = mysqli_prepare($conn, $sql_insert);
        mysqli_stmt_bind_param($stmt_insert, "iii", $user_id, $foods_id, $soluong);
        mysqli_stmt_execute($stmt_insert);
    }

    $_SESSION['thanhcong'] = "Đã thêm sản phẩm vào giỏ hàng thành công!";
    header("Location: chitietsanpham.php?id=" . $foods_id);
    exit;
}

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $sql_cart = "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?";
    $stmt_cart = mysqli_prepare($conn, $sql_cart);
    mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
    mysqli_stmt_execute($stmt_cart);
    $result_cart = mysqli_stmt_get_result($stmt_cart);

    if ($result_cart) {
        $cart_data = mysqli_fetch_assoc($result_cart);
        $cart_count = $cart_data['total'] ? intval($cart_data['total']) : 0;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($food['Foods_Name']) ?> - FastFood</title>
    <link rel="stylesheet" href="../project web/file css/chitietsanpham.css">
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

    <main class="food-detail-container">
        <?php if (!empty($thanhcong)): ?>
            <div class="thongbao ok" style="background:#e8f5e9; color:#1b5e20; padding:12px; margin-bottom:15px; border-radius:8px;">
                <?= htmlspecialchars($thanhcong) ?>
            </div>
        <?php endif; ?>

        <div class="detail-wrapper">

            <div class="detail-image">
                <?php if (!empty($food['Foods_Image'])): ?>
                    <img src="img/<?= htmlspecialchars($food['Foods_Image']) ?>" alt="<?= htmlspecialchars($food['Foods_Name']) ?>">
                <?php elseif (!empty($food['Foods_icon'])): ?>
                    <span class="detail-icon"><?= htmlspecialchars($food['Foods_icon']) ?></span>
                <?php else: ?>
                    <span class="detail-icon">🍔</span>
                <?php endif; ?>
            </div>

            <div class="detail-info">
                <p class="category-name">Danh mục: <strong><?= htmlspecialchars($food['category_name'] ?? 'Thức ăn nhanh') ?></strong></p>
                <h1><?= htmlspecialchars($food['Foods_Name']) ?></h1>
                <p class="detail-price"><?= number_format($food['Foods_Price'], 0, ',', '.') ?>đ</p>
                <p class="description">
                    <?= htmlspecialchars($food['Foods_Description'] ?? 'Món ăn thơm ngon, bổ dưỡng, chuẩn vị được chế biến từ nguyên liệu tươi sạch mỗi ngày.') ?>
                </p>

                <form method="post" action="chitietsanpham.php?id=<?= $food['Foods_ID'] ?>" class="add-cart-form">
                    <div class="quantity-selector">
                        <label for="quantity">Số lượng:</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="1" max="50">
                    </div>
                    <button type="submit" name="add_to_cart" class="btn-add-cart">🛒 Thêm vào giỏ hàng</button>
                </form>
            </div>
        </div>
    </main>
</body>

</html>