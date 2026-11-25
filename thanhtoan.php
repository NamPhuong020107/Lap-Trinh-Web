<?php
session_start();
require_once __DIR__ . '/ketnoi.php';
if (!isset($_SESSION['user_id'])) { header("Location: dangnhap.php"); exit; }
$uid = intval($_SESSION['user_id']);
$h = fn($s) => htmlspecialchars($s ?? '');
$f = fn($n) => number_format((float)$n, 0, ',', '.') . 'đ';
$loi = $ok = $voucher_msg = "";

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

// Lấy danh sách voucher_id user này đã sử dụng trong bảng orders
$used_res = mysqli_execute_query(
    $conn,
    "SELECT DISTINCT voucher_id FROM orders WHERE user_id = ? AND voucher_id IS NOT NULL",
    [$uid]
);
$used_voucher_ids = [];
foreach (mysqli_fetch_all($used_res, MYSQLI_ASSOC) as $u) {
    $used_voucher_ids[(int)$u['voucher_id']] = true;
}

// Lấy các voucher đang hoạt động, còn hạn sử dụng và chưa từng dùng bởi user này
$raw_vouchers = mysqli_fetch_all(mysqli_query($conn,
    "SELECT voucher_id, voucher_name, code, discount_percent, date_from, date_to
     FROM vouchers
     WHERE is_active = 1 AND CURDATE() BETWEEN date_from AND date_to
     ORDER BY discount_percent DESC, voucher_id DESC"), MYSQLI_ASSOC);

$vouchers = array_values(array_filter(
    $raw_vouchers,
    fn($vc) => !isset($used_voucher_ids[(int)$vc['voucher_id']])
));

// Lấy danh sách món gắn với từng voucher từ bảng trung gian voucher_foods
$vf_rows = mysqli_fetch_all(mysqli_query($conn, "SELECT voucher_id, Foods_ID FROM voucher_foods"), MYSQLI_ASSOC);
$vf_map = [];
foreach ($vf_rows as $r) {
    $vf_map[(int)$r['voucher_id']][] = (int)$r['Foods_ID'];
}

$voucher_map = [];
foreach ($vouchers as $vc) {
    $vc['food_ids'] = $vf_map[(int)$vc['voucher_id']] ?? [];
    $voucher_map[strtoupper(trim($vc['code']))] = $vc;
}

// Dữ liệu form (điền sẵn họ tên, sđt từ tài khoản)
$v = [
    'hoten'        => $_SESSION['fullname'] ?? '',
    'sdt'          => mysqli_fetch_assoc(mysqli_query($conn, "SELECT phone FROM users WHERE user_id = $uid"))['phone'] ?? '',
    'diachi'       => '',
    'ghichu'       => '',
    'pay'          => 'cod',
    'voucher_code' => ''
];

$applied_voucher = null;
$discount_amount = 0;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $v = array_map('trim', array_intersect_key($_POST, $v)) + $v;
    $code_input = strtoupper(trim($v['voucher_code'] ?? ''));
    $v['voucher_code'] = $code_input;

    $matched_names = [];

    if ($code_input !== '') {
        if (isset($voucher_map[$code_input])) {
            $candidate = $voucher_map[$code_input];
            $rate = ((int)$candidate['discount_percent']) / 100;
            $allowed_foods = $candidate['food_ids'];

            foreach ($items as $i) {
                $fid = (int)$i['Foods_ID'];
                $is_eligible = empty($allowed_foods) || in_array($fid, $allowed_foods, true);
                if ($is_eligible) {
                    $discount_amount += round(($i['Foods_Price'] * $i['sl']) * $rate);
                    $matched_names[] = $i['Foods_Name'];
                }
            }

            if ($discount_amount > 0) {
                $applied_voucher = $candidate;
                $voucher_msg = "Đã áp dụng mã {$candidate['code']} (-{$candidate['discount_percent']}%) cho: " . implode(', ', array_unique($matched_names));
            } else {
                $voucher_msg = "Mã {$code_input} không áp dụng cho món nào trong giỏ hàng của bạn.";
            }
        } else {
            $voucher_msg = "Mã giảm giá không hợp lệ, đã hết hạn, đã được sử dụng hoặc chưa áp dụng.";
        }
    }

    $tong_cong = max(0, $tong - $discount_amount) + $ship;

    // Nếu chỉ bấm nút "Áp dụng" thì dừng ở đây để cập nhật giao diện
    if (!isset($_POST['apply_voucher'])) {
        if ($v['hoten'] == "" || $v['diachi'] == "" || !preg_match('/^[0-9]{9,11}$/', $v['sdt'])) {
            $loi = "Vui lòng nhập đủ họ tên, địa chỉ và số điện thoại hợp lệ (9-11 chữ số).";
        } elseif (!isset($PAY[$v['pay']])) {
            $loi = "Vui lòng chọn phương thức thanh toán.";
        } elseif ($code_input !== '' && !$applied_voucher) {
            $loi = "Vui lòng kiểm tra lại mã giảm giá trước khi đặt hàng.";
        } else try {
            $pmethod    = $PAY_DB[$v['pay']];
            $note       = $v['ghichu'] !== '' ? $v['ghichu'] : null;
            $voucher_id = $applied_voucher ? (int)$applied_voucher['voucher_id'] : null;

            mysqli_begin_transaction($conn);

            // Kiểm tra lại lần cuối trước khi insert để tránh race condition
            if ($voucher_id !== null) {
                $chk = mysqli_fetch_assoc(mysqli_execute_query(
                    $conn,
                    "SELECT COUNT(*) AS cnt FROM orders WHERE user_id = ? AND voucher_id = ?",
                    [$uid, $voucher_id]
                ));
                if ((int)($chk['cnt'] ?? 0) > 0) {
                    throw new mysqli_sql_exception("Bạn đã sử dụng mã giảm giá này rồi.");
                }
            }

            mysqli_execute_query(
                $conn,
                "INSERT INTO orders (user_id, receiver_name, receiver_phone, receiver_address, Orders_Note, payment_method, Subtotal, shipping_fee, Orders_total_amount, Orders_status, Orders_date, voucher_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'dang xu ly', NOW(), ?)",
                [$uid, $v['hoten'], $v['sdt'], $v['diachi'], $note, $pmethod, $tong, $ship, $tong_cong, $voucher_id]
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
} else {
    $tong_cong = $tong + $ship;
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
                <?php if ($applied_voucher): ?>
                    <p>Mã giảm giá đã dùng: <strong><?= $h($applied_voucher['code']) ?></strong> (-<?= (int)$applied_voucher['discount_percent'] ?>%)</p>
                <?php endif; ?>
                <p>Tổng thanh toán: <strong><?= $f($tong_cong) ?></strong></p>
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
                        <h2>Mã giảm giá</h2>
                        <div class="voucher-box">
                            <input type="text" id="voucherCodeInput" name="voucher_code"
                                   placeholder="Nhập hoặc chọn mã giảm giá"
                                   value="<?= $h($v['voucher_code']) ?>">
                            <button type="submit" name="apply_voucher" value="1" class="btn">Áp dụng</button>
                        </div>

                        <?php if (!empty($vouchers)): ?>
                            <select id="voucherSelect" class="voucher-select"
                                    onchange="document.getElementById('voucherCodeInput').value = this.value;">
                                <option value="">-- Chọn mã khuyến mãi có hiệu lực --</option>
                                <?php foreach ($vouchers as $vc): ?>
                                    <option value="<?= $h($vc['code']) ?>" <?= strtoupper($v['voucher_code']) === strtoupper($vc['code']) ? 'selected' : '' ?>>
                                        <?= $h($vc['code']) ?> - <?= $h($vc['voucher_name']) ?> (-<?= (int)$vc['discount_percent'] ?>%)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <small style="color: #777;">Hiện không có chương trình khuyến mãi nào khả dụng.</small>
                        <?php endif; ?>

                        <?php if ($voucher_msg): ?>
                            <p class="voucher-msg <?= $applied_voucher ? 'voucher-msg--ok' : 'voucher-msg--err' ?>">
                                <?= $h($voucher_msg) ?>
                            </p>
                        <?php endif; ?>
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

                    <button type="submit" name="place_order" value="1" class="btn">Đặt hàng</button>
                    <a href="giohang.php" class="back">← Quay lại giỏ hàng</a>
                </div>

                <aside class="checkout-card">
                    <h2>Đơn hàng</h2>
                    <?php foreach ($items as $i) { ?>
                        <p class="row"><span><?= $h($i['Foods_icon']) ?> <?= $h($i['Foods_Name']) ?> × <?= $i['sl'] ?></span><span><?= $f($i['Foods_Price'] * $i['sl']) ?></span></p>
                    <?php } ?>
                    <p class="row"><span>Tạm tính</span><span><?= $f($tong) ?></span></p>
                    <?php if ($discount_amount > 0): ?>
                        <p class="row discount-row">
                            <span>Giảm giá (<?= $h($applied_voucher['code']) ?>)</span>
                            <span>-<?= $f($discount_amount) ?></span>
                        </p>
                    <?php endif; ?>
                    <p class="row"><span>Phí giao hàng</span><span><?= $f($ship) ?></span></p>
                    <p class="row total"><span>Tổng cộng</span><span><?= $f($tong_cong) ?></span></p>
                </aside>

            </form>
        <?php } ?>
    </main>
</body>
</html>