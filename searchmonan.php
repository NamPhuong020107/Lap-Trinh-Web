<?php
session_start();
require_once __DIR__ . '/ketnoi.php';

$da_dang_nhap = isset($_SESSION['user_id']);

// Từ khóa tìm kiếm
$tukhoa = trim($_GET['q'] ?? '');
$foods = [];

if ($tukhoa != "") {
    $like = "%" . $tukhoa . "%";
    $stmt = mysqli_prepare($conn, "SELECT Foods_ID, Foods_Name, Foods_Price, Foods_Image, Foods_icon
                                   FROM foods
                                   WHERE Foods_Name LIKE ?");
    mysqli_stmt_bind_param($stmt, "s", $like);
    mysqli_stmt_execute($stmt);
    $foods = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

// Đếm số món trong giỏ để hiện trên header
$cart_count = 0;
if ($da_dang_nhap) {
    $user_id = intval($_SESSION['user_id']);
    $stmt = mysqli_prepare($conn, "SELECT SUM(Cart_Items_Quantity) AS total FROM cart_items WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $cart_count = $data['total'] ? intval($data['total']) : 0;
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm món ăn - FastFood</title>
    <link rel="stylesheet" href="file css/searchmonan.css">
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

    <main class="home-container">
        <section class="featured-section">
            <h2>Tìm món ăn</h2>

            <form method="get" action="searchmonan.php" style="margin-bottom:20px;">
                <input type="text" name="q" placeholder="Nhập tên món ăn..." value="<?= htmlspecialchars($tukhoa) ?>"
                       style="padding:10px; width:280px; max-width:70%;">
                <button type="submit" style="padding:10px 18px; cursor:pointer;">Tìm kiếm</button>
            </form>

            <?php if ($tukhoa == "") { ?>
                <p>Nhập tên món ăn để tìm kiếm.</p>
            <?php } elseif (empty($foods)) { ?>
                <p>Không tìm thấy món nào với từ khóa "<strong><?= htmlspecialchars($tukhoa) ?></strong>".</p>
            <?php } else { ?>
                <p style="margin-bottom:15px;">Tìm thấy <?= count($foods) ?> món cho "<strong><?= htmlspecialchars($tukhoa) ?></strong>":</p>
                <div class="featured-grid">
                    <?php foreach ($foods as $food) { ?>
                        <div class="featured-card">
                            <div class="food-icon">
                                <?php if (!empty($food['Foods_icon'])) { ?>
                                    <?= htmlspecialchars($food['Foods_icon']) ?>
                                <?php } elseif (!empty($food['Foods_Image'])) { ?>
                                    <img src="images/<?= htmlspecialchars($food['Foods_Image']) ?>" alt="<?= htmlspecialchars($food['Foods_Name']) ?>">
                                <?php } else { ?>
                                    🍔
                                <?php } ?>
                            </div>
                            <h3>
                                <a href="chitietsanpham.php?id=<?= $food['Foods_ID'] ?>">
                                    <?= htmlspecialchars($food['Foods_Name']) ?>
                                </a>
                            </h3>
                            <p class="price"><?= number_format($food['Foods_Price'], 0, ',', '.') ?>đ</p>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </section>
    </main>

</body>
</html>