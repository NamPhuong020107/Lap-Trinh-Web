<?php
// Bắt đầu session để lấy thông tin người dùng đang đăng nhập
session_start();
// Nối vào database bainhom8 (file ketnoi.php tạo biến $conn)
require_once __DIR__ . '/ketnoi.php';
// Chỉ manager hoặc admin mới được vào; người khác bị chuyển về trang admin.php
if (!isset($_SESSION['role']) || ($_SESSION['role'] != "manager" && $_SESSION['role'] != "admin")) {
    header("location:admin.php");
    exit;
}
// Hàm dem(): chạy câu SELECT và trả về giá trị ở cột đầu tiên
function dem($conn, $sql) { $r = mysqli_fetch_row(mysqli_query($conn, $sql)); return $r[0]; }

// Thống kê 4 ô số liệu
$tongmon  = dem($conn, "SELECT COUNT(*) FROM foods");
$tongnd   = dem($conn, "SELECT COUNT(*) FROM users");
$tongdon  = dem($conn, "SELECT COUNT(*) FROM orders");
$doanhthu = dem($conn, "SELECT IFNULL(SUM(Orders_total_amount),0) FROM orders WHERE Orders_status <> 'da huy'");

// Biểu đồ: doanh thu theo ngày trong 7 ngày gần nhất
$bieudo  = mysqli_query($conn, "SELECT DATE(Orders_date) AS ngay, SUM(Orders_total_amount) AS tien FROM orders
           WHERE Orders_status <> 'da huy' AND DATE(Orders_date) >= CURDATE() - INTERVAL 6 DAY GROUP BY ngay");
// Đếm số đơn hàng theo từng trạng thái
$trangthai = mysqli_query($conn, "SELECT Orders_status, COUNT(*) AS sl FROM orders GROUP BY Orders_status");
// Lấy 5 đơn hàng mới nhất
$donmoi  = mysqli_query($conn, "SELECT * FROM orders ORDER BY Orders_id DESC LIMIT 5");
// Top 5 món bán chạy
$banchay = mysqli_query($conn, "SELECT f.Foods_Name, SUM(d.Order_Details_Quantity) AS sl FROM order_details d
           JOIN foods f ON d.Foods_ID = f.Foods_ID GROUP BY f.Foods_ID, f.Foods_Name ORDER BY sl DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tổng quan - FastFood Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="file css/tongquan.css">
</head>
<body class="app-layout">

<!-- SIDEBAR BÊN TRÁI -->
<aside class="sidebar">
    <div>
        <div class="sidebar__brand">
            <span class="brand-icon">🍔</span>
            <span class="brand-text">Fast <span class="brand-highlight">Food</span></span>
        </div>
        <nav class="sidebar__nav">
            <a class="nav-item nav-item--active" href="tongquan.php"><i class="fa-solid fa-house nav-icon"></i> Dashboard</a>
            <a class="nav-item" href="quanliloaisanpham.php"><i class="fa-solid fa-list nav-icon"></i> Quản lý loại sản phẩm</a>
            <a class="nav-item" href="quanlisanpham.php"><i class="fa-solid fa-burger nav-icon"></i> Quản lý sản phẩm</a>
            <a class="nav-item" href="quanlynguoidung.php"><i class="fa-solid fa-user nav-icon"></i> Quản lý người dùng</a>
            <a class="nav-item" href="quanlidonhang.php"><i class="fa-solid fa-cart-shopping nav-icon"></i> Quản lý đơn hàng</a>
            <a class="nav-item" href="quanlikhuyenmai.php"><i class="fa-solid fa-ticket nav-icon"></i> Quản lý khuyến mãi</a>
        </nav>
    </div>
    <div class="sidebar__footer">
        <a class="nav-item nav-item--logout" href="dangxuat.php"><i class="fa-solid fa-right-from-bracket nav-icon"></i> Đăng xuất</a>
    </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <!-- HEADER TRÊN CÙNG (GIỐNG QUẢN LÝ SẢN PHẨM) -->
    <header class="header">
        <form action="quanlisanpham.php" method="GET" class="search-form">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="text" name="search" placeholder="Tìm kiếm tên món ăn..." class="search-input">
        </form>
        <div class="user-profile">
            <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></span>
        </div>
    </header>

    <!-- NỘI DUNG CHÍNH -->
    <main class="content">
        <div class="page-header">
            <h1 class="page-title">Chào mừng <?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?>!</h1>
            
        </div>

        <!-- 4 ô thống kê -->
        <div class="g4">
            <div class="o"><small>🍔 Tổng món ăn</small><b><?= $tongmon ?></b></div>
            <div class="o"><small>👥 Tổng người dùng</small><b><?= $tongnd ?></b></div>
            <div class="o"><small>📋 Tổng đơn hàng</small><b><?= $tongdon ?></b></div>
            <div class="o"><small>💰 Doanh thu</small><b><?= number_format($doanhthu, 0, ",", ".") ?>đ</b></div>
        </div>

        <!-- Hàng 1: biểu đồ doanh thu 7 ngày và đơn hàng theo trạng thái -->
        <div class="g2">
            <div class="o">
                <h2>Doanh thu 7 ngày gần đây</h2>
                <?php if (mysqli_num_rows($bieudo) == 0) { ?>
                    <div class="trong">Chưa có dữ liệu</div>
                <?php } else { $ds = mysqli_fetch_all($bieudo, MYSQLI_ASSOC); $max = max(array_column($ds, 'tien')); ?>
                    <div class="cot">
                        <?php foreach ($ds as $d) { ?>
                            <div style="height:<?= $max > 0 ? round($d['tien'] / $max * 100) : 0 ?>%"><?= round($d['tien'] / 1000) ?>k</div>
                        <?php } ?>
                    </div>
                    <div class="ngay">
                        <?php foreach ($ds as $d) { ?><span><?= date("d/m", strtotime($d['ngay'])) ?></span><?php } ?>
                    </div>
                <?php } ?>
            </div>

            <div class="o">
                <h2>Đơn hàng theo trạng thái</h2>
                <?php if ($tongdon == 0) { ?>
                    <div class="trong">Chưa có dữ liệu</div>
                <?php } else { ?>
                    <table>
                        <?php while ($t = mysqli_fetch_assoc($trangthai)) { ?>
                            <tr><td><?= htmlspecialchars($t['Orders_status']) ?></td><td><b><?= $t['sl'] ?></b></td></tr>
                        <?php } ?>
                    </table>
                <?php } ?>
            </div>
        </div>

        <!-- Hàng 2: đơn hàng gần đây và món bán chạy -->
        <div class="g2">
            <div class="o">
                <h2>Đơn hàng gần đây</h2>
                <?php if (mysqli_num_rows($donmoi) == 0) { ?>
                    <div class="trong">Chưa có dữ liệu</div>
                <?php } else { ?>
                    <table>
                        <thead>
                            <tr><th>Mã</th><th>Người nhận</th><th>Tổng tiền</th><th>Trạng thái</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($d = mysqli_fetch_assoc($donmoi)) { ?>
                                <tr>
                                    <td>#<?= $d['Orders_id'] ?></td>
                                    <td><?= htmlspecialchars($d['receiver_name'] ?? '') ?></td>
                                    <td><?= number_format($d['Orders_total_amount'], 0, ",", ".") ?>đ</td>
                                    <td><?= htmlspecialchars($d['Orders_status']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>

            <div class="o">
                <h2>Món bán chạy</h2>
                <?php if (mysqli_num_rows($banchay) == 0) { ?>
                    <div class="trong">Chưa có dữ liệu</div>
                <?php } else { ?>
                    <table>
                        <thead>
                            <tr><th>Món ăn</th><th>Đã bán</th></tr>
                        </thead>
                        <tbody>
                            <?php while ($m = mysqli_fetch_assoc($banchay)) { ?>
                                <tr><td><?= htmlspecialchars($m['Foods_Name']) ?></td><td><b><?= $m['sl'] ?></b></td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>