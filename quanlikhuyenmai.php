<?php
// quanlikhuyenmai.php
session_start();
require_once __DIR__ . '/ketnoi.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] != "manager" && $_SESSION['role'] != "admin")) {
    header("location:admin.php");
    exit;
}

function syncVoucherFoods(mysqli $conn, int $voucher_id, array $food_ids): void {
    mysqli_execute_query($conn, "DELETE FROM voucher_foods WHERE voucher_id = ?", [$voucher_id]);
    $clean_ids = array_unique(array_filter(array_map('intval', $food_ids), fn($id) => $id > 0));
    foreach ($clean_ids as $fid) {
        mysqli_execute_query(
            $conn,
            "INSERT IGNORE INTO voucher_foods (voucher_id, Foods_ID) VALUES (?, ?)",
            [$voucher_id, $fid]
        );
    }
}

// ============================================================================
// CHỨC NĂNG 1: THÊM KHUYẾN MÃI MỚI
// ============================================================================
if (isset($_POST['action_add'])) {
    $voucher_name     = mysqli_real_escape_string($conn, trim($_POST['voucher_name'] ?? ''));
    $code             = mysqli_real_escape_string($conn, strtoupper(trim($_POST['code'] ?? '')));
    $discount_percent = (int)($_POST['discount_percent'] ?? 0);
    $date_from        = mysqli_real_escape_string($conn, trim($_POST['date_from'] ?? ''));
    $date_to          = mysqli_real_escape_string($conn, trim($_POST['date_to'] ?? ''));
    $is_active        = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $food_ids         = $_POST['food_ids'] ?? [];

    if ($voucher_name !== '' && $code !== '' && $discount_percent > 0 && $discount_percent <= 100 && $date_from !== '' && $date_to !== '') {
        $sql_add = "INSERT INTO vouchers (voucher_name, code, discount_percent, date_from, date_to, is_active)
                    VALUES ('$voucher_name', '$code', $discount_percent, '$date_from', '$date_to', $is_active)";
        if (mysqli_query($conn, $sql_add)) {
            $new_id = (int)mysqli_insert_id($conn);
            syncVoucherFoods($conn, $new_id, is_array($food_ids) ? $food_ids : []);
        }
    }
    header('Location: quanlikhuyenmai.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 2: SỬA CẬP NHẬT KHUYẾN MÃI
// ============================================================================
if (isset($_POST['action_edit'])) {
    $voucher_id       = (int)($_POST['voucher_id'] ?? 0);
    $voucher_name     = mysqli_real_escape_string($conn, trim($_POST['voucher_name'] ?? ''));
    $code             = mysqli_real_escape_string($conn, strtoupper(trim($_POST['code'] ?? '')));
    $discount_percent = (int)($_POST['discount_percent'] ?? 0);
    $date_from        = mysqli_real_escape_string($conn, trim($_POST['date_from'] ?? ''));
    $date_to          = mysqli_real_escape_string($conn, trim($_POST['date_to'] ?? ''));
    $is_active        = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
    $food_ids         = $_POST['food_ids'] ?? [];

    if ($voucher_id > 0 && $voucher_name !== '' && $code !== '' && $discount_percent > 0 && $discount_percent <= 100 && $date_from !== '' && $date_to !== '') {
        $sql_edit = "UPDATE vouchers
                     SET voucher_name = '$voucher_name',
                         code = '$code',
                         discount_percent = $discount_percent,
                         date_from = '$date_from',
                         date_to = '$date_to',
                         is_active = $is_active
                     WHERE voucher_id = $voucher_id";
        mysqli_query($conn, $sql_edit);
        syncVoucherFoods($conn, $voucher_id, is_array($food_ids) ? $food_ids : []);
    }
    header('Location: quanlikhuyenmai.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 3: ĐỔI TRẠNG THÁI NHANH
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $voucher_id = (int)($_GET['id'] ?? 0);
    $current    = (int)($_GET['current'] ?? 0);
    $next       = ($current === 1) ? 0 : 1;

    if ($voucher_id > 0) {
        mysqli_query($conn, "UPDATE vouchers SET is_active = $next WHERE voucher_id = $voucher_id");
    }
    header('Location: quanlikhuyenmai.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 4: XÓA KHUYẾN MÃI
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $voucher_id = (int)($_GET['id'] ?? 0);
    if ($voucher_id > 0) {
        mysqli_execute_query($conn, "DELETE FROM voucher_foods WHERE voucher_id = ?", [$voucher_id]);
        mysqli_query($conn, "DELETE FROM vouchers WHERE voucher_id = $voucher_id");
    }
    header('Location: quanlikhuyenmai.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 5: LỌC & TRUY VẤN DỮ LIỆU
// ============================================================================
$all_foods = mysqli_fetch_all(
    mysqli_query($conn, "SELECT Foods_ID, Foods_Name FROM foods ORDER BY Foods_Name ASC"),
    MYSQLI_ASSOC
);

$vf_rows = mysqli_fetch_all(
    mysqli_query($conn, "SELECT voucher_id, Foods_ID FROM voucher_foods"),
    MYSQLI_ASSOC
);
$voucher_foods_map = [];
foreach ($vf_rows as $r) {
    $voucher_foods_map[(int)$r['voucher_id']][] = (int)$r['Foods_ID'];
}

$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_clauses = ["1=1"];

if ($search !== '') {
    $search_clean = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(voucher_name LIKE '%$search_clean%' OR code LIKE '%$search_clean%')";
}

if ($status_filter === '1' || $status_filter === '0') {
    $where_clauses[] = "is_active = " . (int)$status_filter;
}

$where_sql = implode(" AND ", $where_clauses);
$sql_vouchers = "SELECT voucher_id, voucher_name, code, discount_percent, date_from, date_to, is_active
                 FROM vouchers
                 WHERE $where_sql
                 ORDER BY voucher_id DESC";
$result_vouchers = mysqli_query($conn, $sql_vouchers);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khuyến mãi - FastFood Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="file css/quanlikhuyenmai.css">
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
                <a href="quanlidonhang.php" class="nav-item">
                    <i class="fa-solid fa-cart-shopping nav-icon"></i> Quản lý đơn hàng
                </a>
                <a href="quanlikhuyenmai.php" class="nav-item nav-item--active">
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

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper">
        <!-- HEADER TRÊN CÙNG -->
        <header class="header">
            <form action="quanlikhuyenmai.php" method="GET" class="search-form">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm tên hoặc mã khuyến mãi..." class="search-input">
            </form>

            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <span class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin') ?></span>
            </div>
        </header>

        <!-- NỘI DUNG CHÍNH -->
        <main class="content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Quản lý khuyến mãi</h1>
                </div>
                <button onclick="toggleAddModal(true)" class="btn btn--primary">
                    <i class="fa-solid fa-plus"></i> Thêm khuyến mãi
                </button>
            </div>

            <!-- BỘ LỌC -->
            <form action="quanlikhuyenmai.php" method="GET" class="filter-card">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">

                <select name="status" class="form-control">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Đang áp dụng</option>
                    <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Tạm ngưng</option>
                </select>

                <button type="submit" class="btn btn--dark">
                    <i class="fa-solid fa-filter"></i> Lọc dữ liệu
                </button>

                <?php if ($search !== '' || $status_filter !== ''): ?>
                    <a href="quanlikhuyenmai.php" class="btn btn--light">
                        <i class="fa-solid fa-rotate-left"></i> Bỏ lọc
                    </a>
                <?php endif; ?>
            </form>

            <!-- BẢNG DANH SÁCH -->
            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="text-center w-12">ID</th>
                                <th>Tên khuyến mãi</th>
                                <th>Mã code</th>
                                <th>Món áp dụng</th>
                                <th class="text-center">Giảm giá</th>
                                <th>Ngày áp dụng</th>
                                <th>Ngày kết thúc</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$result_vouchers || mysqli_num_rows($result_vouchers) == 0): ?>
                                <tr>
                                    <td colspan="9" class="table-empty">Không tìm thấy khuyến mãi nào!</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($item = mysqli_fetch_assoc($result_vouchers)): ?>
                                    <?php
                                    $vid = (int)$item['voucher_id'];
                                    $active = (int)($item['is_active'] ?? 0) === 1;
                                    $linked_ids = $voucher_foods_map[$vid] ?? [];
                                    $item['food_ids'] = $linked_ids;
                                    ?>
                                    <tr>
                                        <td class="text-center text-muted font-medium">#<?= $vid ?></td>
                                        <td><div class="food-name"><?= htmlspecialchars($item['voucher_name']) ?></div></td>
                                        <td><span class="badge badge--orange"><?= htmlspecialchars($item['code']) ?></span></td>
                                        <td>
                                            <?php if (empty($linked_ids)): ?>
                                                <span class="text-muted">Tất cả món</span>
                                            <?php else: ?>
                                                <?= count($linked_ids) ?> món đã chọn
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center font-medium"><?= (int)$item['discount_percent'] ?>%</td>
                                        <td><?= htmlspecialchars($item['date_from']) ?></td>
                                        <td><?= htmlspecialchars($item['date_to']) ?></td>
                                        <td class="text-center">
                                            <a href="quanlikhuyenmai.php?action=toggle_status&id=<?= $vid ?>&current=<?= $active ? 1 : 0 ?>"
                                               class="badge-btn <?= $active ? 'badge-btn--success' : 'badge-btn--danger' ?>">
                                                <?= $active ? 'Đang áp dụng' : 'Tạm ngưng' ?>
                                            </a>
                                        </td>
                                        <td class="text-right">
                                            <button onclick='openEditModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, "UTF-8") ?>)'
                                                    class="btn btn--sm btn--blue">
                                                Sửa
                                            </button>
                                            <a href="quanlikhuyenmai.php?action=delete&id=<?= $vid ?>"
                                               onclick="return confirm('Bạn chắc chắn muốn xóa khuyến mãi này?')"
                                               class="btn btn--sm btn--rose">
                                                Xóa
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL 1: THÊM KHUYẾN MÃI -->
    <div id="addVoucherModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Thêm khuyến mãi mới</h3>
                <button onclick="toggleAddModal(false)" class="modal__close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="quanlikhuyenmai.php" method="POST" class="modal__body">
                <input type="hidden" name="action_add" value="1">

                <div class="form-group">
                    <label class="form-label">Tên khuyến mãi</label>
                    <input type="text" name="voucher_name" required placeholder="Ví dụ: Combo nhiều món" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Mã code</label>
                        <input type="text" name="code" required placeholder="COMBO20" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phần trăm giảm (%)</label>
                        <input type="number" name="discount_percent" min="1" max="100" required placeholder="20" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Ngày bắt đầu</label>
                        <input type="date" name="date_from" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ngày kết thúc</label>
                        <input type="date" name="date_to" required class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Món áp dụng (để trống = tất cả món)</label>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; background: #f8fafc;">
                        <?php foreach ($all_foods as $fd): ?>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 14px; cursor: pointer;">
                                <input type="checkbox" name="food_ids[]" value="<?= (int)$fd['Foods_ID'] ?>">
                                <span><?= htmlspecialchars($fd['Foods_Name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select name="is_active" class="form-control">
                        <option value="1">Đang áp dụng</option>
                        <option value="0">Tạm ngưng</option>
                    </select>
                </div>

                <div class="modal__footer">
                    <button type="button" onclick="toggleAddModal(false)" class="btn btn--light">Hủy</button>
                    <button type="submit" class="btn btn--primary">Lưu vào Database</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: SỬA KHUYẾN MÃI -->
    <div id="editVoucherModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Sửa khuyến mãi</h3>
                <button onclick="toggleEditModal(false)" class="modal__close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="quanlikhuyenmai.php" method="POST" class="modal__body">
                <input type="hidden" name="action_edit" value="1">
                <input type="hidden" id="edit_voucher_id" name="voucher_id">

                <div class="form-group">
                    <label class="form-label">Tên khuyến mãi</label>
                    <input type="text" id="edit_voucher_name" name="voucher_name" required class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Mã code</label>
                        <input type="text" id="edit_code" name="code" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phần trăm giảm (%)</label>
                        <input type="number" id="edit_discount_percent" name="discount_percent" min="1" max="100" required class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Ngày bắt đầu</label>
                        <input type="date" id="edit_date_from" name="date_from" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ngày kết thúc</label>
                        <input type="date" id="edit_date_to" name="date_to" required class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Món áp dụng (để trống = tất cả món)</label>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; background: #f8fafc;">
                        <?php foreach ($all_foods as $fd): ?>
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 14px; cursor: pointer;">
                                <input type="checkbox" class="edit-food-checkbox" name="food_ids[]" value="<?= (int)$fd['Foods_ID'] ?>">
                                <span><?= htmlspecialchars($fd['Foods_Name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select id="edit_is_active" name="is_active" class="form-control">
                        <option value="1">Đang áp dụng</option>
                        <option value="0">Tạm ngưng</option>
                    </select>
                </div>

                <div class="modal__footer">
                    <button type="button" onclick="toggleEditModal(false)" class="btn btn--light">Hủy</button>
                    <button type="submit" class="btn btn--blue">Cập nhật thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleAddModal(show) {
            const modal = document.getElementById('addVoucherModal');
            modal.classList.toggle('modal--open', show);
        }

        function toggleEditModal(show) {
            const modal = document.getElementById('editVoucherModal');
            modal.classList.toggle('modal--open', show);
        }

        function openEditModal(item) {
            document.getElementById('edit_voucher_id').value = item.voucher_id;
            document.getElementById('edit_voucher_name').value = item.voucher_name;
            document.getElementById('edit_code').value = item.code;
            document.getElementById('edit_discount_percent').value = item.discount_percent;
            document.getElementById('edit_date_from').value = item.date_from;
            document.getElementById('edit_date_to').value = item.date_to;
            document.getElementById('edit_is_active').value = parseInt(item.is_active) === 1 ? '1' : '0';

            const selectedIds = new Set((item.food_ids || []).map(id => parseInt(id)));
            document.querySelectorAll('.edit-food-checkbox').forEach(cb => {
                cb.checked = selectedIds.has(parseInt(cb.value));
            });

            toggleEditModal(true);
        }
    </script>
</body>

</html>