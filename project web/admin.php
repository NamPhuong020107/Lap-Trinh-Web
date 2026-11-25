<?php
// Bắt đầu session để lưu thông tin người dùng sau khi đăng nhập ($_SESSION)
session_start();
// Nối vào database bainhom8 (file ketnoi.php tạo biến $conn)
require_once __DIR__ . '/ketnoi.php';

// Nếu đã đăng nhập với quyền quản trị thì chuyển thẳng sang trang tổng quan
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'manager'])) {
    header("location:tongquan.php");
    exit;
}

// $loi: chứa thông báo lỗi để hiện ra màn hình (rỗng = không lỗi)
$loi = "";
// $taikhoan: giữ lại nội dung ô tài khoản để không bị mất khi nhập sai
$taikhoan = "";
// Khởi tạo số lượng giỏ hàng trên Header để tránh lỗi Undefined variable
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    $stmt = mysqli_prepare($conn, "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $cart_count = $data['total'] ? intval($data['total']) : 0;
    mysqli_stmt_close($stmt);
}
// Chỉ chạy khi người dùng bấm nút 'Đăng nhập Admin' (nút có name=dangnhapadmin)
if (isset($_POST['dangnhapadmin'])) {
    // Lấy dữ liệu từ form, trim() để xóa khoảng trắng thừa hai đầu
    $taikhoan = trim($_POST['taikhoan']);
    $matkhau  = $_POST['matkhau'];

    // Bước 1: kiểm tra có bỏ trống ô nào không
    if ($taikhoan == "" || $matkhau == "") {
        $loi = "Vui lòng nhập đầy đủ thông tin.";
    } else {
        // Bước 2: xử lý ký tự đặc biệt để tránh lỗi/tấn công SQL
        $tk = mysqli_real_escape_string($conn, $taikhoan);
        // Tìm user theo tên đăng nhập, email hoặc số điện thoại
        $sql = "SELECT * FROM users
                WHERE username='$tk' OR email='$tk' OR phone='$tk'";
        // Chạy câu SQL
        $kq = mysqli_query($conn, $sql);

        // Bước 3: không tìm thấy dòng nào => tài khoản không tồn tại
        if (mysqli_num_rows($kq) == 0) {
            $loi = "Tài khoản không tồn tại.";
        } else {
            // Lấy dòng user tìm được ra mảng $row
            $row = mysqli_fetch_assoc($kq);
            // $mk: mật khẩu đang lưu trong database
            $mk  = $row['password'];

            // Bước 4: tài khoản bị khóa (status khác active) thì không cho đăng nhập
            if ($row['status'] != "active") {
                $loi = "Tài khoản đã bị khóa.";
                // Bước 5: so sánh mật khẩu (chấp nhận md5, password_hash hoặc mật khẩu thường)
            } elseif (!($mk == md5($matkhau) || password_verify($matkhau, $mk) || $mk == $matkhau)) {
                $loi = "Sai mật khẩu.";
                // Bước 6: chỉ role manager hoặc admin mới được vào trang quản trị
            } elseif ($row['role'] != "manager" && $row['role'] != "admin") {
                $loi = "Tài khoản này không có quyền quản trị.";
            } else {
                // Đăng nhập đúng: lưu thông tin vào session để các trang khác dùng
                $_SESSION['user_id']  = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['role']     = $row['role'];
                // Chuyển sang trang tổng quan quản trị
                header("location:tongquan.php");
                // Dừng code ngay sau khi chuyển trang
                exit;
            }
        }
    }
}
?>
<!-- Phần giao diện HTML bắt đầu từ đây -->
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin - FastFood</title>
    <link rel="stylesheet" href="file css/admin.css">

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

    <!-- Khung form đăng nhập admin -->
    <div class="khung">
        <div class="avatar">👨‍💼</div>
        <h1>Đăng nhập Admin</h1>
        <p class="phu">Đăng nhập để quản lý hệ thống FastFood</p>

        <!-- Nếu $loi có nội dung thì hiện thông báo lỗi -->
        <?php if ($loi != "") { ?>
            <div class="loi"><?= $loi ?></div>
        <?php } ?>

        <!-- Form gửi dữ liệu bằng POST về chính trang admin.php -->
        <form method="post" action="admin.php">
            <label>Tài khoản</label>
            <!-- Ô nhập tài khoản (giữ lại giá trị cũ, htmlspecialchars chống chèn mã HTML) -->
            <input type="text" name="taikhoan" placeholder="Nhập tài khoản" value="<?= htmlspecialchars($taikhoan) ?>">

            <label>Mật khẩu</label>
            <!-- Ô nhập mật khẩu -->
            <input type="password" name="matkhau" placeholder="Nhập mật khẩu">

            <!-- Ô tích 'Ghi nhớ đăng nhập' (chỉ để hiển thị) -->
            <div class="nho"><input type="checkbox" name="nho">Ghi nhớ đăng nhập</div>

            <!-- Nút gửi form, name=dangnhapadmin để PHP nhận biết -->
            <button type="submit" name="dangnhapadmin" class="btn">Đăng nhập Admin</button>
        </form>

        <!-- Liên kết quay lại trang đăng nhập người dùng -->
        <div class="quaylai"><a href="dangnhap.php">← Quay lại đăng nhập User</a></div>
    </div>

</body>

</html>