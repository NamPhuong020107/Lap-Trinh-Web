<?php
// quanlidonhang.php
session_start();
require_once __DIR__ . '/ketnoi.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] != "manager" && $_SESSION['role'] != "admin")) {
    header("location:admin.php");
    exit;
}

// Các trạng thái đơn hàng: mã trong database => tên hiển thị
const TRANG_THAI = [
    'dang xu ly' => 'Đang xử lý',
    'dang giao'  => 'Đang giao',
    'hoan thanh' => 'Hoàn thành',
    'da huy'     => 'Đã hủy',
];

// Màu của huy hiệu trạng thái (class trong quanlidonhang.css)
const TRANG_THAI_CLASS = [
    'dang xu ly' => 'pending',
    'dang giao'  => 'shipping',
    'hoan thanh' => 'done',
    'da huy'     => 'cancel',
];

// Định dạng tiền: 265000 -> 265.000đ
function tien($n)
{
    return number_format((float)$n, 0, ',', '.') . 'đ';
}

// ============================================================================
// CHỨC NĂNG 1: XỬ LÝ ĐỔI TRẠNG THÁI ĐƠN HÀNG
// ============================================================================
if (isset($_POST['action_status'])) {
    $id = (int)$_POST['Orders_id'];
    $new_status = $_POST['Orders_status'] ?? '';

    // Chỉ nhận 4 trạng thái hợp lệ
    if ($id > 0 && isset(TRANG_THAI[$new_status])) {
        $status_clean = mysqli_real_escape_string($conn, $new_status);
        mysqli_query($conn, "UPDATE orders SET Orders_status = '$status_clean' WHERE Orders_id = $id");
    }
    header('Location: quanlidonhang.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 2: XỬ LÝ XÓA ĐƠN HÀNG (xóa chi tiết đơn trước, rồi xóa đơn)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        mysqli_query($conn, "DELETE FROM order_details WHERE Orders_ID = $id");
        mysqli_query($conn, "DELETE FROM orders WHERE Orders_id = $id");
    }
    header('Location: quanlidonhang.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 3: TRUY VẤN LẤY DỮ LIỆU
// ============================================================================

// Thống kê nhanh (doanh thu không tính đơn đã hủy, giống trang tổng quan)
$stats = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total,
            SUM(Orders_status = 'dang xu ly') AS pending,
            SUM(Orders_status = 'dang giao')  AS shipping,
            SUM(Orders_status = 'hoan thanh') AS done,
            IFNULL(SUM(CASE WHEN Orders_status <> 'da huy' THEN Orders_total_amount END), 0) AS revenue
     FROM orders"
));

// Danh sách phương thức thanh toán đang có trong đơn (để đổ vào ô lọc)
$pay_methods = [];
$r = mysqli_query($conn, "SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL AND payment_method <> '' ORDER BY payment_method");
while ($p = mysqli_fetch_assoc($r)) {
    $pay_methods[] = $p['payment_method'];
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$pay_filter = isset($_GET['pay']) ? $_GET['pay'] : '';

$where_clauses = ["1=1"];

// Tìm theo mã đơn (có thể gõ #12), tên người nhận hoặc số điện thoại
if (!empty($search)) {
    $search_clean = mysqli_real_escape_string($conn, $search);
    $id_search = (int)ltrim($search, '#');
    $where_clauses[] = "(Orders_id = $id_search OR receiver_name LIKE '%$search_clean%' OR receiver_phone LIKE '%$search_clean%')";
}

if (!empty($status_filter) && isset(TRANG_THAI[$status_filter])) {
    $status_clean = mysqli_real_escape_string($conn, $status_filter);
    $where_clauses[] = "Orders_status = '$status_clean'";
}

if (!empty($pay_filter)) {
    $pay_clean = mysqli_real_escape_string($conn, $pay_filter);
    $where_clauses[] = "payment_method = '$pay_clean'";
}

$where_sql = implode(" AND ", $where_clauses);

// LEFT JOIN vouchers để biết đơn dùng mã giảm giá nào (voucher_id có thể rỗng)
$result_orders = mysqli_query(
    $conn,
    "SELECT o.*, v.code AS voucher_code, v.discount_percent
     FROM orders o
     LEFT JOIN vouchers v ON v.voucher_id = o.voucher_id
     WHERE " . $where_sql . "
     ORDER BY o.Orders_id DESC"
);
$orders = $result_orders ? mysqli_fetch_all($result_orders, MYSQLI_ASSOC) : [];

// ============================================================================
// MỞ MODAL CHI TIẾT ĐƠN BẰNG IF PHP (khi bấm nút có name="btn_open_detail")
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_open_detail'])) {
    $detail_id = (int)($_POST['detail_id'] ?? 0);
    $stmt_o = mysqli_prepare(
        $conn,
        "SELECT o.*, v.code AS voucher_code, v.discount_percent
         FROM orders o
         LEFT JOIN vouchers v ON v.voucher_id = o.voucher_id
         WHERE o.Orders_id = ?"
    );
    mysqli_stmt_bind_param($stmt_o, "i", $detail_id);
    mysqli_stmt_execute($stmt_o);
    $detail_order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_o));
    mysqli_stmt_close($stmt_o);

    if ($detail_order) {
        $stmt_d = mysqli_prepare($conn,
            "SELECT d.Order_Details_Quantity AS sl, d.Order_Details_Price AS tien, f.Foods_Name AS ten
             FROM order_details d
             LEFT JOIN foods f ON f.Foods_ID = d.Foods_ID
             WHERE d.Orders_ID = ?"
        );
        mysqli_stmt_bind_param($stmt_d, "i", $detail_id);
        mysqli_stmt_execute($stmt_d);
        $detail_items = mysqli_fetch_all(mysqli_stmt_get_result($stmt_d), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_d);

        $date_fmt = date('H:i d/m/Y', strtotime($detail_order['Orders_date']));
        $giam = max(0, (float)$detail_order['Subtotal'] + (float)$detail_order['shipping_fee'] - (float)$detail_order['Orders_total_amount']);
        $giam_label = !empty($detail_order['voucher_code'])
            ? 'Voucher ' . $detail_order['voucher_code'] . ' (-' . (int)$detail_order['discount_percent'] . '%)'
            : 'Giảm giá';
        $note_txt = trim((string)($detail_order['Orders_Note'] ?? '')) !== '' ?$detail_order['Orders_Note'] : '—';

        echo '
        <div class="modal modal--open">
            <div class="modal__dialog dh-dialog">
                <div class="modal__header">
                    <h3 class="modal__title">Chi tiết đơn #' . (int)$detail_order['Orders_id'] . '</h3>
                    <a href="quanlidonhang.php" class="modal__close"><i class="fa-solid fa-xmark"></i></a>
                </div>

                <div class="modal__body">
                    <div class="dh-info">
                        <span>Ngày đặt</span><b>' . htmlspecialchars($date_fmt) . '</b>
                        <span>Người nhận</span><b>' . htmlspecialchars($detail_order['receiver_name'] ?? '') . '</b>
                        <span>Số điện thoại</span><b>' . htmlspecialchars($detail_order['receiver_phone'] ?? '') . '</b>
                        <span>Địa chỉ</span><b>' . htmlspecialchars($detail_order['receiver_address'] ?? '') . '</b>
                        <span>Ghi chú</span><b>' . htmlspecialchars($note_txt) . '</b>
                        <span>Thanh toán</span><b>' . htmlspecialchars($detail_order['payment_method'] ?? '') . '</b>
                    </div>

                    <table class="dh-items">
                        <thead>
                            <tr>
                                <th>Món</th>
                                <th class="dh-r">SL</th>
                                <th class="dh-r">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>';
        foreach ($detail_items as$m) {
            echo '<tr>
                    <td>' . htmlspecialchars($m['ten'] ?: '(món đã xóa)') . '</td>
                    <td class="dh-r">' . (int)$m['sl'] . '</td>
                    <td class="dh-r">' . tien($m['tien']) . '</td>
                  </tr>';
        }
        echo '          </tbody>
                    </table>

                    <div class="dh-sum"><span>Tạm tính</span><span>' . tien($detail_order['Subtotal']) . '</span></div>
                    <div class="dh-sum"><span>Phí giao hàng</span><span>' . tien($detail_order['shipping_fee']) . '</span></div>';
        if ($giam > 0) {
            echo '<div class="dh-sum"><span>' . htmlspecialchars($giam_label) . '</span><span>-' . tien($giam) . '</span></div>';
        }
        echo '      <div class="dh-sum dh-sum--total"><span>Tổng cộng</span><span>' . tien($detail_order['Orders_total_amount']) . '</span></div>

                    <form action="quanlidonhang.php" method="POST" class="dh-status-form">
                        <input type="hidden" name="action_status" value="1">
                        <input type="hidden" name="Orders_id" value="' . (int)$detail_order['Orders_id'] . '">
                        <label class="form-label">Trạng thái đơn hàng</label>
                        <select name="Orders_status" class="form-control">';
        foreach (TRANG_THAI as $ma => $ten) {$selected = ($detail_order['Orders_status'] ===$ma) ? 'selected' : '';
            echo '<option value="' . $ma . '" ' . $selected . '>' .$ten . '</option>';
        }
        echo '          </select>

                        <div class="modal__footer">
                            <a href="quanlidonhang.php" class="btn btn--light">Đóng</a>
                            <button type="submit" class="btn btn--blue">Cập nhật trạng thái</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        ';
    }
}

// ============================================================================
// MỞ MODAL XÁC NHẬN XÓA ĐƠN BẰNG IF PHP (khi bấm nút có name="btn_open_delete")
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_open_delete'])) {
    $del_id = (int)($_POST['delete_id'] ?? 0);
    $stmt_del = mysqli_prepare($conn, "SELECT Orders_id, receiver_name, Orders_total_amount FROM orders WHERE Orders_id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $del_id);
    mysqli_stmt_execute($stmt_del);
    $del_order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_del));
    mysqli_stmt_close($stmt_del);

    if ($del_order) {
        echo '
        <div class="modal modal--open">
            <div class="modal__dialog dh-dialog--sm">
                <div class="modal__header">
                    <h3 class="modal__title">Xác nhận xóa đơn #' . (int)$del_order['Orders_id'] . '</h3>
                    <a href="quanlidonhang.php" class="modal__close"><i class="fa-solid fa-xmark"></i></a>
                </div>

                <div class="modal__body">
                    <p>Xóa đơn hàng <strong>#' . (int)$del_order['Orders_id'] . '</strong> của <strong>' . htmlspecialchars($del_order['receiver_name'] ?? '') . '</strong> (' . tien($del_order['Orders_total_amount']) . ')? Chi tiết món trong đơn cũng bị xóa theo, không thể hoàn tác.</p>
                    <div class="modal__footer">
                        <a href="quanlidonhang.php" class="btn btn--light">Hủy</a>
                        <a href="quanlidonhang.php?action=delete&id=' . (int)$del_order['Orders_id'] . '" class="btn btn--rose">Xác nhận xóa</a>
                    </div>
                </div>
            </div>
        </div>
        ';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý đơn hàng - FastFood Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="file css/quanlidonhang.css">
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
                <a href="tongquan.php" class="nav-item">
                    <i class="fa-solid fa-house nav-icon"></i> Dashboard
                </a>
                <a href="quanliloaisanpham.php" class="nav-item">
                    <i class="fa-solid fa-list nav-icon"></i> Quản lý loại sản phẩm
                </a>
                <a href="quanlisanpham.php" class="nav-item">
                    <i class="fa-solid fa-burger nav-icon"></i> Quản lý sản phẩm
                </a>
                <a href="quanlynguoidung.php" class="nav-item">
                    <i class="fa-solid fa-user nav-icon"></i> Quản lý người dùng
                </a>
                <a href="quanlidonhang.php" class="nav-item nav-item--active">
                    <i class="fa-solid fa-cart-shopping nav-icon"></i> Quản lý đơn hàng
                </a>
                <a href="quanlikhuyenmai.php" class="nav-item">
                    <i class="fa-solid fa-ticket nav-icon"></i> Quản lý khuyến mãi
                </a>
            </nav>
        </div>
        <div class="sidebar__footer">
            <a href="dangxuat.php" class="nav-item nav-item--logout">
                <i class="fa-solid fa-right-from-bracket nav-icon"></i> Đăng xuất
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="main-wrapper">
        <!-- HEADER TRÊN CÙNG -->
        <header class="header">
            <form action="quanlidonhang.php" method="GET" class="search-form">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm theo mã đơn, tên, số điện thoại..." class="search-input">
            </form>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <span class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin') ?></span>
            </div>
        </header>

        <main class="content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Quản lý đơn hàng</h1>
                </div>
            </div>

            <!-- 5 Ô THỐNG KÊ -->
            <div class="dh-stats">
                <div class="dh-stat"><small>Tổng đơn</small><b><?php echo (int)$stats['total']; ?></b></div>
                <div class="dh-stat"><small>Đang xử lý</small><b><?php echo (int)$stats['pending']; ?></b></div>
                <div class="dh-stat"><small>Đang giao</small><b><?php echo (int)$stats['shipping']; ?></b></div>
                <div class="dh-stat"><small>Hoàn thành</small><b><?php echo (int)$stats['done']; ?></b></div>
                <div class="dh-stat"><small>Doanh thu</small><b><?php echo tien($stats['revenue']); ?></b></div>
            </div>

            <!-- FORM BỘ LỌC DỮ LIỆU -->
            <form action="quanlidonhang.php" method="GET" class="filter-card">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">

                <select name="status" class="form-control">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (TRANG_THAI as $ma =>$ten): ?>
                        <option value="<?php echo $ma; ?>" <?php echo $status_filter ===$ma ? 'selected' : ''; ?>>
                            <?php echo $ten; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="pay" class="form-control">
                    <option value="">Mọi phương thức thanh toán</option>
                    <?php foreach ($pay_methods as$pm): ?>
                        <option value="<?php echo htmlspecialchars($pm); ?>" <?php echo $pay_filter ===$pm ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pm); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn--dark">
                    <i class="fa-solid fa-filter"></i> Lọc dữ liệu
                </button>

                <?php if (!empty($status_filter) || !empty($pay_filter) || !empty($search)): ?>
                    <a href="quanlidonhang.php" class="btn btn--light">
                        <i class="fa-solid fa-rotate-left"></i> Bỏ lọc
                    </a>
                <?php endif; ?>
            </form>

            <!-- BẢNG HIỂN THỊ DANH SÁCH -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="text-center w-12">Mã</th>
                                <th>Ngày đặt</th>
                                <th>Người nhận</th>
                                <th>Thanh toán</th>
                                <th>Tổng tiền</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="table-empty">Không tìm thấy đơn hàng nào!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as$o): ?>
                                    <?php
                                    $st = $o['Orders_status'];$st_class = TRANG_THAI_CLASS[$st] ?? 'pending';$st_label = TRANG_THAI[$st] ?? $st;
                                    $date_fmt = date('H:i d/m/Y', strtotime($o['Orders_date']));
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted font-medium">
                                            #<?php echo (int)$o['Orders_id']; ?>
                                        </td>
                                        <td><?php echo $date_fmt; ?></td>
                                        <td>
                                            <div class="food-name"><?php echo htmlspecialchars($o['receiver_name']); ?></div>
                                            <div class="food-desc"><?php echo htmlspecialchars($o['receiver_phone']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge badge--slate">
                                                <?php echo htmlspecialchars($o['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td class="food-price"><?php echo tien($o['Orders_total_amount']); ?></td>
                                        <td class="text-center">
                                            <span class="dh-badge dh-badge--<?php echo $st_class; ?>"><?php echo htmlspecialchars($st_label); ?></span>
                                        </td>
                                        <td class="text-right">
                                            <form action="quanlidonhang.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="detail_id" value="<?php echo (int)$o['Orders_id']; ?>">
                                                <button type="submit" name="btn_open_detail" class="btn btn--sm btn--blue">
                                                    Xem
                                                </button>
                                            </form>
                                            <form action="quanlidonhang.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="delete_id" value="<?php echo (int)$o['Orders_id']; ?>">
                                                <button type="submit" name="btn_open_delete" class="btn btn--sm btn--rose">
                                                    Xóa
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>