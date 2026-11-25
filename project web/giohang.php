<?php

session_start();
require_once __DIR__ . '/ketnoi.php';

// 2. Lấy ID người dùng từ Session (mặc định = 3 nếu chưa đăng nhập)
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 3;

// 3. Xử lý Request POST từ Form (Tăng / Giảm / Xóa sản phẩm)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_item_id = intval($_POST['cart_item_id'] ?? 0);
    $action       = $_POST['action'] ?? '';

    if ($cart_item_id > 0 && !empty($action)) {
        if ($action === 'increase') {
            // Tăng số lượng sản phẩm lên 1
            $stmt = mysqli_prepare($conn, "UPDATE cart_items SET Cart_Items_Quantity = Cart_Items_Quantity + 1 WHERE Cart_Items_ID = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $cart_item_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        } elseif ($action === 'decrease') {
            // Giảm số lượng sản phẩm xuống 1
            $stmt = mysqli_prepare($conn, "UPDATE cart_items SET Cart_Items_Quantity = Cart_Items_Quantity - 1 WHERE Cart_Items_ID = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $cart_item_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Tự động xóa khỏi giỏ hàng nếu số lượng giảm về <= 0
            mysqli_query($conn, "DELETE FROM cart_items WHERE Cart_Items_Quantity <= 0");

        } elseif ($action === 'delete') {
            // Xóa trực tiếp món khỏi giỏ hàng
            $stmt = mysqli_prepare($conn, "DELETE FROM cart_items WHERE Cart_Items_ID = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $cart_item_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Chuyển hướng về lại trang giỏ hàng để cập nhật giao diện
    header('Location: giohang.php');
    exit();
}

// 4. Truy vấn lấy danh sách sản phẩm trong giỏ hàng theo user_id
$sql = "SELECT c.Cart_Items_ID, c.Foods_ID, c.Cart_Items_Quantity, f.Foods_Name, f.Foods_Price, f.Foods_Image , f.Foods_icon
        FROM cart_items c 
        JOIN foods f ON c.Foods_ID = f.Foods_ID 
        WHERE c.user_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Lấy toàn bộ dữ liệu dưới dạng mảng kết hợp
$cart_items = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// 5. Tính toán tổng tiền và số lượng
$subtotal = 0;
$total_quantity = 0;

foreach ($cart_items as $item) {
    $subtotal += $item['Foods_Price'] * $item['Cart_Items_Quantity'];
    $total_quantity += $item['Cart_Items_Quantity'];
}

$shipping_fee = count($cart_items) > 0 ? 15000 : 0;
$total_amount = $subtotal + $shipping_fee;
?>



<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng</title>
    <link rel="stylesheet" href="file css/giohang.css">
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
                    🛒 <?php if ($total_quantity > 0): ?>
                        <span class="cart-badge"><?= $total_quantity ?></span>
                    <?php endif; ?>
                </a>
                <a href="taikhoan.php">👤</a>
            </div>
        </div>
    </header>
    <!-- NỘI DUNG -->
    <main class="content">
        <h1>Giỏ hàng</h1>

        <div class="cart-layout">

            <!-- DANH SÁCH MÓN -->
            <div class="cart-products">
                <h2>Giỏ hàng (<?php echo count($cart_items); ?> món)</h2>

                <?php if (!empty($cart_items)): ?>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="product">
                            <img src="../project web/img/<?= $item['Foods_Image'] ?>" alt="<?php echo htmlspecialchars($item['Foods_Name']); ?>">

                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($item['Foods_Name']); ?></h3>
                                <p><?php echo number_format($item['Foods_Price'], 0, ',', '.'); ?>đ</p>

                                <div class="quantity">
                                    <!-- Nút Giảm -->
                                    <form action="giohang.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="cart_item_id" value="<?php echo $item['Cart_Items_ID']; ?>">
                                        <input type="hidden" name="action" value="decrease">
                                        <button type="submit">-</button>
                                    </form>

                                    <span><?php echo $item['Cart_Items_Quantity']; ?></span>

                                    <!-- Nút Tăng -->
                                    <form action="giohang.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="cart_item_id" value="<?php echo $item['Cart_Items_ID']; ?>">
                                        <input type="hidden" name="action" value="increase">
                                        <button type="submit">+</button>
                                    </form>
                                </div>
                            </div>

                            <!-- NÚT XÓA SẢN PHẨM KHỎI DATABASE -->
                            <form action="giohang.php" method="POST">
                                <input type="hidden" name="cart_item_id" value="<?php echo $item['Cart_Items_ID']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="delete" onclick="return confirm('Bạn có chắc chắn muốn xóa món này khỏi giỏ hàng không?')">
                                    🗑
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="padding: 20px 0;">Giỏ hàng của bạn đang trống! <a href="menuhtml.php" style="color:#f4512a;">Xem thực đơn</a></p>
                <?php endif; ?>
            </div>

            <!-- TỔNG ĐƠN -->
            <div class="summary">
                <h2>Tổng đơn hàng</h2>

                <div class="summary-row">
                    <span>Tạm tính</span>
                    <span><?php echo number_format($subtotal, 0, ',', '.'); ?>đ</span>
                </div>

                <div class="summary-row">
                    <span>Phí giao hàng</span>
                    <span><?php echo number_format($shipping_fee, 0, ',', '.'); ?>đ</span>
                </div>

                <hr>

                <div class="total">
                    <span>Tổng cộng</span>
                    <span><?php echo number_format($total_amount, 0, ',', '.'); ?>đ</span>
                </div>

                <?php if (!empty($cart_items)): ?>
                    <a href="thanhtoan.php" class="checkout">
                        Thanh toán →
                    </a>
                <?php else: ?>
                    <button class="checkout" style="background-color: #ccc; cursor: not-allowed;" disabled>
                        Thanh toán →
                    </button>
                <?php endif; ?>
            </div>

        </div>
    </main>

</body>

</html>