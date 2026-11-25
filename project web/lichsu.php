<?php
session_start();
require_once __DIR__ . '/ketnoi.php';
if (!isset($_SESSION['user_id'])) { header("Location: dangnhap.php"); exit; }
$uid   = intval($_SESSION['user_id']);
$h     = fn($s) => htmlspecialchars($s ?? '');
$money = fn($n) => number_format($n, 0, ',', '.') . 'đ';
// Trạng thái lưu trong CSDL (orders.Orders_status) -> chữ hiển thị / class CSS
$ST    = ['dang xu ly' => 'Đang xử lý', 'dang giao' => 'Đang giao', 'da giao' => 'Đã giao', 'hoan thanh' => 'Hoàn thành', 'da huy' => 'Đã hủy'];
$STcss = ['dang xu ly' => 'pending',    'dang giao' => 'shipping',  'da giao' => 'done',   'hoan thanh' => 'done', 'da huy' => 'cancel'];

$cart_row   = mysqli_fetch_assoc(mysqli_execute_query($conn, "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?", [$uid]));
$cart_count = $cart_row['total'] ? intval($cart_row['total']) : 0;
// Lấy đơn hàng của người dùng kèm các món và voucher đã dùng, gom theo mã đơn
$orders = []; $loi = "";
try {
    $rs = mysqli_execute_query($conn,
        "SELECT o.Orders_id, o.Orders_date, o.Subtotal, o.shipping_fee, o.Orders_total_amount, o.Orders_status,
                v.code AS vcode, v.discount_percent AS vpct,
                d.Order_Details_Quantity AS quantity, d.Order_Details_Price AS price,
                f.Foods_Name, f.Foods_icon
         FROM orders o
         LEFT JOIN vouchers v ON v.voucher_id = o.voucher_id
         LEFT JOIN order_details d ON d.Orders_ID = o.Orders_id
         LEFT JOIN foods f ON f.Foods_ID = d.Foods_ID
         WHERE o.user_id = ? ORDER BY o.Orders_id DESC", [$uid]);
    foreach (mysqli_fetch_all($rs, MYSQLI_ASSOC) as $r) {
        $orders[$r['Orders_id']]['info']    = $r;
        $orders[$r['Orders_id']]['items'][] = $r;
    }
} catch (mysqli_sql_exception $e) {
    $loi = "Không đọc được đơn hàng: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi - FastFood</title>
    <link rel="stylesheet" href="file css/lichsu.css">
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

    <main class="account-page">
        <aside class="account-sidebar">
            <a href="taikhoan.php">👤 Thông tin cá nhân</a>
            <a href="lichsu.php" class="account-active">▣ Đơn hàng của tôi</a>
            <a href="khuyenmai.php">✕ Mã giảm giá</a>
            <a href="dangxuat.php">↪ Đăng xuất</a>
        </aside>

        <section class="account-content">
            <h2>Đơn hàng của tôi</h2>
            <?php if ($loi) { ?><p style="color:#b71c1c;background:#ffebee;padding:10px;border-radius:8px;"><?= $h($loi) ?></p><?php } ?>

            <?php foreach ($orders as $id => $o) { $i = $o['info'];
                $giam = $i['Subtotal'] + $i['shipping_fee'] - $i['Orders_total_amount']; ?>
                <div class="order-card">
                    <div class="order-head">
                        <div><strong>#DH<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></strong><small><?= date('d/m/Y H:i', strtotime($i['Orders_date'])) ?></small></div>
                        <span class="order-status st-<?= $h($STcss[$i['Orders_status']] ?? '') ?>"><?= $ST[$i['Orders_status']] ?? $h($i['Orders_status']) ?></span>
                    </div>
                    <?php foreach ($o['items'] as $it) { if ($it['Foods_Name'] === null) continue; ?>
                        <div class="order-line">
                            <span><?= $h($it['Foods_icon']) ?> <?= $h($it['Foods_Name']) ?> × <?= $it['quantity'] ?></span>
                            <!-- Order_Details_Price là thành tiền của cả dòng nên không nhân thêm số lượng -->
                            <span><?= $money($it['price']) ?></span>
                        </div>
                    <?php } ?>

                    <div class="order-sum"><span>Tạm tính</span><span><?= $money($i['Subtotal']) ?></span></div>
                    <div class="order-sum"><span>Phí giao hàng</span><span><?= $money($i['shipping_fee']) ?></span></div>
                    <?php if ($i['vcode']) { ?>
                        <div class="order-sum">
                            <span>Voucher <?= $h($i['vcode']) ?> (-<?= intval($i['vpct']) ?>%)</span>
                            <span><?= $giam > 0 ? '-' . $money($giam) : '' ?></span>
                        </div>
                    <?php } ?>
                    <div class="order-total"><span>Tổng cộng</span><span><?= $money($i['Orders_total_amount']) ?></span></div>
                </div>
            <?php } ?>

            <?php if (!$orders && !$loi) { ?>
                <p>Bạn chưa có đơn hàng nào. <a href="menu.php" style="color:#f4512a;">Xem thực đơn</a></p>
            <?php } ?>
        </section>
    </main>
</body>
</html>