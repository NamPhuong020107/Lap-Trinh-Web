<?php
// Bắt đầu session để lấy thông tin người dùng đang đăng nhập
session_start();
// Nối vào database bainhom8 (file ketnoi.php tạo biến $conn)
require_once __DIR__ . '/ketnoi.php';
// Chưa đăng nhập thì chuyển về trang đăng nhập và dừng code
if (!isset($_SESSION['user_id'])) { header("Location: dangnhap.php"); exit; }
// $uid: id của người dùng đang đăng nhập (intval ép về số nguyên)
$uid = intval($_SESSION['user_id']);
// Hàm rút gọn $h(): hiển thị an toàn (htmlspecialchars chống chèn mã HTML)
$h = fn($s) => htmlspecialchars($s ?? '');
// $ok: thông báo thành công; $loi: thông báo lỗi
$ok = $loi = "";

// Chỉ chạy khi bấm nút 'Lưu thay đổi' (form gửi bằng POST)
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    // Lấy 3 giá trị từ form: họ tên, số điện thoại, email
    [$ten, $sdt, $email] = [trim($_POST['hoten']), trim($_POST['sdt']), trim($_POST['email'])];
    // Kiểm tra: họ tên không rỗng, sđt gồm 9-11 chữ số, email đúng định dạng
    if ($ten == "" || !preg_match('/^[0-9]{9,11}$/', $sdt) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loi = "Vui lòng nhập họ tên, số điện thoại (9-11 số) và email hợp lệ.";
    } else try {
        // Cập nhật thông tin vào bảng users cho đúng user đang đăng nhập (dấu ? được thay bằng giá trị trong mảng)
        mysqli_execute_query($conn, "UPDATE users SET fullname = ?, phone = ?, email = ? WHERE user_id = ?", [$ten, $sdt, $email, $uid]);
        // Cập nhật lại tên trong session để các trang khác hiện tên mới
        $_SESSION['fullname'] = $ten;
        // Báo cập nhật thành công
        $ok = "Đã cập nhật thông tin.";
    } catch (mysqli_sql_exception $e) {
        // Nếu SQL báo lỗi (thường do email trùng người khác) thì hiện thông báo lỗi
        $loi = "Không cập nhật được (email có thể đã được tài khoản khác dùng).";
    }
}
// Lấy thông tin mới nhất của user từ database để hiện lên form
$u = mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT fullname, phone, email FROM users WHERE user_id = ?", [$uid]));
// Đếm tổng số món trong giỏ hàng của user
$res_cart = mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?", [$uid]));
$cart_count = $res_cart['total'] ? intval($res_cart['total']) : 0;
?>
<!-- Phần giao diện HTML bắt đầu từ đây -->
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản - FastFood</title>
    <link rel="stylesheet" href="file css/taikhoan.css">
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

    <!-- Nội dung trang tài khoản gồm menu bên trái và thông tin bên phải -->
    <main class="account-page">
        <!-- Menu bên trái: thông tin cá nhân, đơn hàng, mã giảm giá, đăng xuất -->
        <aside class="account-sidebar">
            <a href="taikhoan.php" class="account-active">👤 Thông tin cá nhân</a>
            <a href="lichsu.php">▣ Đơn hàng của tôi</a>
            <a href="khuyenmai.php">✕ Mã giảm giá</a>
            <a href="dangxuat.php">↪ Đăng xuất</a>
        </aside>

        <!-- Phần bên phải: ảnh đại diện, tên, số điện thoại và form sửa thông tin -->
        <section class="account-content">
            <div class="user-info">
                <div class="user-avatar">👤</div>
                <div><h2><?= $h($u['fullname']) ?></h2><p><?= $h($u['phone']) ?></p></div>
            </div>
            <hr>
            <h2>Thông tin cá nhân</h2>

            <!-- Hiện thông báo thành công (màu xanh) nếu có -->
            <?php if ($ok) { ?><p style="color:#1b5e20;background:#e8f5e9;padding:10px;border-radius:8px;"><?= $h($ok) ?></p><?php } ?>
            <!-- Hiện thông báo lỗi (màu đỏ) nếu có -->
            <?php if ($loi) { ?><p style="color:#b71c1c;background:#ffebee;padding:10px;border-radius:8px;"><?= $h($loi) ?></p><?php } ?>

            <!-- Form gửi POST về chính trang này, các ô đã điền sẵn thông tin hiện tại -->
            <form method="post">
                <div class="info-box"><label>Họ và tên</label><input type="text" name="hoten" value="<?= $h($u['fullname']) ?>"></div>
                <div class="info-box"><label>Số điện thoại</label><input type="text" name="sdt" value="<?= $h($u['phone']) ?>"></div>
                <div class="info-box"><label>Email</label><input type="email" name="email" value="<?= $h($u['email']) ?>"></div>
                <!-- Nút lưu thay đổi -->
                <button type="submit" class="register-button">Lưu thay đổi</button>
            </form>
        </section>
    </main>
</body>
</html>