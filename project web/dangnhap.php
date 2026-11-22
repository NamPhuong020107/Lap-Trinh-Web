<?php
// Bắt đầu session để lưu thông tin người dùng sau khi đăng nhập ($_SESSION)
session_start();
// Nối vào database bainhom8 (file ketnoi.php tạo biến $conn)
require_once __DIR__ . "/ketnoi.php";

// $loi: chứa thông báo lỗi để hiện ra màn hình (rỗng = không lỗi)
$loi = "";
// $taikhoan: giữ lại nội dung ô tài khoản để không bị mất khi nhập sai
$taikhoan = "";
$cart_count = 0;
// Chỉ chạy khi người dùng bấm nút 'Đăng nhập' (nút có name=dangnhap)
if (isset($_POST['dangnhap'])) {
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
            } else {
                // Đăng nhập đúng: lưu thông tin vào session để các trang khác dùng
                $_SESSION['user_id']  = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['role']     = $row['role'];

                // Chuyển về trang chủ
                header("location:trangchu.php");
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
<title>Đăng nhập - FastFood</title>
<link rel="stylesheet" href="file css/dangnhap.css">
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

<!-- Khung form đăng nhập -->
<div class="khung">
    <h1>Đăng nhập / Đăng ký</h1>

    <!-- Nếu $loi có nội dung thì hiện thông báo lỗi -->
    <?php if ($loi != "") { ?>
        <div class="loi"><?= $loi ?></div>
    <?php } ?>

    <!-- Form gửi dữ liệu bằng POST về chính trang dangnhap.php -->
    <form method="post" action="dangnhap.php">
        <!-- Ô nhập tài khoản (giữ lại giá trị cũ, htmlspecialchars chống chèn mã HTML) -->
        <input type="text" name="taikhoan" placeholder="Số điện thoại / Email"
               value="<?= htmlspecialchars($taikhoan) ?>">
        <!-- Ô nhập mật khẩu -->
        <input type="password" name="matkhau" placeholder="Mật khẩu">

        <!-- Ô tích 'Ghi nhớ đăng nhập' (chỉ để hiển thị) -->
        <div class="nho">
            Ghi nhớ đăng nhập <input type="checkbox" name="nho">
        </div>

        <!-- Nút gửi form, name=dangnhap để PHP nhận biết -->
        <button type="submit" name="dangnhap" class="btn btn-chinh">Đăng nhập</button>
        <!-- Liên kết sang trang đăng nhập Admin (admin.php) -->
        <a href="admin.php" class="btn btn-admin">Đăng nhập Admin</a>
    </form>

    <!-- Liên kết sang trang đăng ký -->
    <div class="dangki"><a href="dangki.php">Đăng ký tài khoản</a></div>
</div>

</body>
</html>