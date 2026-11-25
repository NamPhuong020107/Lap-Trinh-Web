<?php
session_start();
// Nối vào database bainhom8 (file ketnoi.php tạo biến $conn)
require_once __DIR__ . "/ketnoi.php";

// Nếu đã đăng nhập rồi thì chuyển thẳng sang trang tài khoản
if (isset($_SESSION['user_id'])) {
    header("Location: taikhoan.php");
    exit;
}

// $loi: thông báo lỗi; $thanhcong: thông báo thành công
$loi = "";
$thanhcong = "";
// Các biến giữ lại dữ liệu đã nhập để form không bị xóa khi nhập sai
$hoten = $username = $sdt = $email = "";
// Khởi tạo biến giỏ hàng trên Header để tránh lỗi Undefined variable
$cart_count = 0;
// Chỉ chạy khi form được gửi đi (phương thức POST)
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    // Lấy dữ liệu từ form, trim() để xóa khoảng trắng thừa hai đầu
    $hoten    = trim($_POST['hoten']);
    $username = trim($_POST['username']);
    $sdt      = trim($_POST['sdt']);
    $email    = trim($_POST['email']);
    // Mật khẩu không trim để giữ nguyên ký tự người dùng nhập
    $matkhau  = $_POST['matkhau'];
    $nhaplai  = $_POST['nhaplai'];

    // Bước 1: kiểm tra có bỏ trống ô nào không
    if ($hoten == "" || $username == "" || $sdt == "" || $email == "" || $matkhau == "") {
        $loi = "Vui lòng nhập đầy đủ thông tin.";
    // Bước 2: mật khẩu phải từ 6 ký tự trở lên (strlen đếm độ dài)
    } elseif (strlen($matkhau) < 6) {
        $loi = "Mật khẩu phải có ít nhất 6 ký tự.";
    // Bước 3: mật khẩu nhập lại phải giống mật khẩu
    } elseif ($matkhau != $nhaplai) {
        $loi = "Mật khẩu nhập lại không khớp.";
    // Dữ liệu hợp lệ: chuẩn bị lưu vào database
    } else {
        
        // Xử lý ký tự đặc biệt để tránh lỗi/tấn công SQL
        $h = mysqli_real_escape_string($conn, $hoten);
        $u = mysqli_real_escape_string($conn, $username);
        $s = mysqli_real_escape_string($conn, $sdt);
        $e = mysqli_real_escape_string($conn, $email);

        
        // Bước 4: kiểm tra tên đăng nhập hoặc email đã có người dùng chưa
        $kq = mysqli_query($conn, "SELECT user_id FROM users WHERE username='$u' OR email='$e'");

        // Có ít nhất 1 dòng => đã tồn tại
        if (mysqli_num_rows($kq) > 0) {
            $loi = "Tên đăng nhập hoặc email đã tồn tại.";
        } else {
            // Mã hóa mật khẩu bằng md5 trước khi lưu
            $mk = md5($matkhau);   
            // Bước 5: thêm tài khoản mới vào bảng users (role customer, status active)
            mysqli_query($conn, "INSERT INTO users (username, password, fullname, email, phone, role, status)
                                 VALUES ('$u', '$mk', '$h', '$e', '$s', 'customer', 'active')");
            // Báo đăng ký thành công
            $thanhcong = "Đăng ký thành công!";
            // Xóa dữ liệu cũ trên form để nhập tiếp
            $hoten = $username = $sdt = $email = "";
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
    <title>Đăng ký - FastFood</title>
    <!-- CSS riêng của trang đăng ký -->
    <link rel="stylesheet" href="file css/dangki.css">
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

    <!-- Khung form đăng ký -->
    <section class="register-section">
        <div class="register-container">
            <h1>Đăng ký tài khoản</h1>

            <!-- Hiện thông báo lỗi nếu có -->
            <?php if ($loi != "") { ?>
                <div class="thongbao loi"><?= $loi ?></div>
            <?php } ?>

            <!-- Hiện thông báo thành công nếu có -->
            <?php if ($thanhcong != "") { ?>
                <div class="thongbao ok"><?= $thanhcong ?></div>
            <?php } ?>

            <!-- Form gửi dữ liệu bằng POST về chính trang dangki.php -->
            <form method="post" action="dangki.php">
                <!-- Ô Họ và tên (giữ lại giá trị cũ bằng value) -->
                <div class="form-group">
                    <input type="text" name="hoten" placeholder="Họ và tên" value="<?= $hoten ?>">
                </div>
                <!-- Ô Tên đăng nhập -->
                <div class="form-group">
                    <input type="text" name="username" placeholder="Tên đăng nhập" value="<?= $username ?>">
                </div>
                <!-- Ô Số điện thoại -->
                <div class="form-group">
                    <input type="text" name="sdt" placeholder="Số điện thoại" value="<?= $sdt ?>">
                </div>
                <!-- Ô Email -->
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" value="<?= $email ?>">
                </div>
                <!-- Ô Mật khẩu -->
                <div class="form-group">
                    <input type="password" name="matkhau" placeholder="Mật khẩu">
                </div>
                <!-- Ô Nhập lại mật khẩu -->
                <div class="form-group">
                    <input type="password" name="nhaplai" placeholder="Nhập lại mật khẩu">
                </div>
                <!-- Nút gửi form -->
                <button type="submit" class="register-button">Đăng ký</button>
            </form>

            <!-- Liên kết sang trang đăng nhập nếu đã có tài khoản -->
            <p class="login-link">
                Đã có tài khoản? <a href="dangnhap.php">Đăng nhập ngay</a>
            </p>
        </div>
    </section>

</body>
</html>