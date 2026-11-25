<?php
session_start();
require_once __DIR__ . '/ketnoi.php';
if (!isset($_SESSION['user_id'])) { header("Location: dangnhap.php"); exit; }
$uid = intval($_SESSION['user_id']);
$h = fn($s) => htmlspecialchars($s ?? '');
$f = fn($n) => number_format($n, 0, ',', '.') . 'đ';
$loi = $ok = "";

// Phương thức thanh toán: mã => [icon, tên, mô tả]
$PAY = [
    'cod'     => ['💵', 'Tiền mặt khi nhận hàng', 'Trả tiền cho nhân viên khi giao hàng'],
    'bank'    => ['🏦', 'Chuyển khoản ngân hàng', 'Chuyển khoản sau khi đặt hàng, cửa hàng sẽ xác nhận'],
    'ewallet' => ['📱', 'Ví điện tử', 'Thanh toán qua ví điện tử khi cửa hàng xác nhận đơn']
];

// Giá trị lưu vào cột payment_method trong bảng orders
$PAY_DB = [
    'cod'     => 'COD',
    'bank'    => 'Online Banking',
    'ewallet' => 'E-wallet'
];

// Món trong giỏ + tiền
$items = mysqli_fetch_all(mysqli_execute_query($conn,
    "SELECT c.Foods_ID, c.Cart_Items_Quantity AS sl, f.Foods_Name, f.Foods_Price, f.Foods_icon
     FROM cart_items c JOIN foods f ON c.Foods_ID = f.Foods_ID WHERE c.user_id = ?", [$uid]), MYSQLI_ASSOC);
if (!$items) { header("Location: giohang.php"); exit; }
$tong = array_sum(array_map(fn($i) => $i['Foods_Price'] * $i['sl'], $items));
$ship = 15000;
$cart_count = array_sum(array_column($items, 'sl'));

// Dữ liệu form (điền sẵn họ tên, sđt từ tài khoản)
$v = [
    'hoten'  => $_SESSION['fullname'] ?? '',
    'sdt'    => mysqli_fetch_assoc(mysqli_query($conn, "SELECT phone FROM users WHERE user_id = $uid"))['phone'] ?? '',
    'diachi' => '',
    'ghichu' => '',
    'pay'    => 'cod'
];

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $v = array_map('trim', array_intersect_key($_POST, $v)) + $v;
    if ($v['hoten'] == "" || $v['diachi'] == "" || !preg_match('/^[0-9]{9,11}$/', $v['sdt'])) {
        $loi = "Vui lòng nhập đủ họ tên, địa chỉ và số điện thoại hợp lệ (9-11 chữ số).";
    } elseif (!isset($PAY[$v['pay']])) {
        $loi = "Vui lòng chọn phương thức thanh toán.";
    } else try {
        $pmethod   = $PAY_DB[$v['pay']];
        $note      = $v['ghichu'] !== '' ? $v['ghichu'] : null;
        $tong_cong = $tong + $ship;

        mysqli_begin_transaction($conn);

        mysqli_execute_query(
            $conn,
            "INSERT INTO orders (user_id, receiver_name, receiver_phone, receiver_address, Orders_Note, payment_method, Subtotal, shipping_fee, Orders_total_amount, Orders_status, Orders_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'dang xu ly', NOW())",
            [$uid, $v['hoten'], $v['sdt'], $v['diachi'], $note, $pmethod, $tong, $ship, $tong_cong]
        );

        $oid = mysqli_insert_id($conn);

        foreach ($items as $i) {
            $thanh_tien = $i['Foods_Price'] * $i['sl'];
            mysqli_execute_query(
                $conn,
                "INSERT INTO order_details (Orders_ID, Foods_ID, Order_Details_Quantity, Order_Details_Price)
                 VALUES (?, ?, ?, ?)",
                [$oid, $i['Foods_ID'], $i['sl'], $thanh_tien]
            );
        }

        mysqli_execute_query($conn, "DELETE FROM cart_items WHERE user_id = ?", [$uid]);
        mysqli_commit($conn);
        $ok = "Đặt hàng thành công! Mã đơn #$oid";
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        $loi = "Không lưu được đơn hàng: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - FastFood</title>
    <link rel="stylesheet" href="file css/thanhtoan.css">
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

    <main class="checkout-page">
        <h1>Thanh toán</h1>
        <?php if ($ok) { ?>
            <div class="checkout-success">
                <h2>✅ <?= $h($ok) ?></h2>
                <p>Phương thức thanh toán: <strong><?= $h($PAY[$v['pay']][1]) ?></strong></p>
                <a href="lichsu.php" class="btn">Xem đơn hàng của tôi</a>
            </div>
        <?php } else { ?>
            <?php if ($loi) { ?><div class="checkout-alert"><?= $h($loi) ?></div><?php } ?>
            <form method="post" class="checkout-layout">

                <div>
                    <section class="checkout-card">
                        <h2>Thông tin giao hàng</h2>
                        <input name="hoten" placeholder="Họ và tên" value="<?= $h($v['hoten']) ?>">
                        <input name="sdt" placeholder="Số điện thoại" value="<?= $h($v['sdt']) ?>">
                        <input name="diachi" placeholder="Địa chỉ giao hàng" value="<?= $h($v['diachi']) ?>">
                        <textarea name="ghichu" rows="3" placeholder="Ghi chú cho đơn hàng"><?= $h($v['ghichu']) ?></textarea>
                    </section>

                    <section class="checkout-card">
                        <h2>Phương thức thanh toán</h2>
                        <?php foreach ($PAY as $ma => [$ico, $ten, $mota]) { ?>
                            <label class="pay">
                                <input type="radio" name="pay" value="<?= $ma ?>" <?= $v['pay'] == $ma ? 'checked' : '' ?>>
                                <span><?= $ico ?> <strong><?= $ten ?></strong><small><?= $mota ?></small></span>
                            </label>
                        <?php } ?>
                    </section>

                    <button class="btn">Đặt hàng</button>
                    <a href="giohang.php" class="back">← Quay lại giỏ hàng</a>
                </div>

                <aside class="checkout-card">
                    <h2>Đơn hàng</h2>
                    <?php foreach ($items as $i) { ?>
                        <p class="row"><span><?= $h($i['Foods_icon']) ?> <?= $h($i['Foods_Name']) ?> × <?= $i['sl'] ?></span><span><?= $f($i['Foods_Price'] * $i['sl']) ?></span></p>
                    <?php } ?>
                    <p class="row"><span>Tạm tính</span><span><?= $f($tong) ?></span></p>
                    <p class="row"><span>Phí giao hàng</span><span><?= $f($ship) ?></span></p>
                    <p class="row total"><span>Tổng cộng</span><span><?= $f($tong + $ship) ?></span></p>
                </aside>

            </form>
        <?php } ?>
    </main>
</body>
</html>