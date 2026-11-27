<?php


session_start();
require_once __DIR__ . '/ketnoi.php';
$role_session = $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || $role_session !== 'manager') {
    header('Location: dangnhap.php');
    exit();
}
// Vai trò và trạng thái: mã trong database => tên hiển thị
const VAI_TRO = ['customer' => 'Khách hàng', 'manager' => 'Quản trị'];
const TRANG_THAI = ['active' => 'Hoạt động', 'inactive' => 'Không hoạt động'];

// Thông báo lỗi (hiện khi các chức năng bên dưới chuyển hướng về kèm ?loi=...)
$loi = '';
if (($_GET['loi'] ?? '') === 'trung') {
    $loi = 'Không lưu được: tên đăng nhập hoặc email đã có người dùng khác sử dụng.';
} elseif (($_GET['loi'] ?? '') === 'thieu') {
    $loi = 'Vui lòng nhập đủ tên đăng nhập, họ tên, email và mật khẩu (từ 6 ký tự).';
} elseif (($_GET['loi'] ?? '') === 'khongxoa') {
    $loi = 'Không thể xóa hoặc khóa chính tài khoản đang đăng nhập.';
}

// Id của người đang đăng nhập (để không tự xóa / tự khóa mình)
$my_id = (int)($_SESSION['user_id'] ?? 0);

// =========================================================================
// 1. XỬ LÝ THÊM NGƯỜI DÙNG (khi bấm nút có name="btn_add_user")
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = isset(VAI_TRO[$_POST['role'] ?? '']) ? $_POST['role'] : 'customer';
    $status   = isset(TRANG_THAI[$_POST['status'] ?? '']) ? $_POST['status'] : 'active';

    if ($username === '' || $fullname === '' || $email === '' || strlen($password) < 6) {
        header('Location: quanlynguoidung.php?loi=thieu');
        exit();
    }

    // Kiểm tra trùng username hoặc email trước khi thêm
    $stmt_check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_check, "ss", $username, $email);
    mysqli_stmt_execute($stmt_check);
    $trung = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check));
    mysqli_stmt_close($stmt_check);

    if ($trung) {
        header('Location: quanlynguoidung.php?loi=trung');
        exit();
    }

    // Mật khẩu mã hóa md5 giống trang đăng ký (dangki.php)
    $mk = md5($password);
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, fullname, email, phone, address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssssssss", $username, $mk, $fullname, $email, $phone, $address, $role, $status);
    try {
        $ok = mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $e) {
        $ok = false; // thường do trùng username / email
    }
    mysqli_stmt_close($stmt);

    header('Location: quanlynguoidung.php' . ($ok ? '' : '?loi=trung'));
    exit();
}
// =========================================================================
// 2. XỬ LÝ SỬA NGƯỜI DÙNG (khi bấm nút có name="btn_update_user")
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_update_user'])) {
    $id       = (int)($_POST['user_id'] ?? 0);
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $newpass  = $_POST['password'] ?? '';
    $role     = isset(VAI_TRO[$_POST['role'] ?? '']) ? $_POST['role'] : 'customer';
    $status   = isset(TRANG_THAI[$_POST['status'] ?? '']) ? $_POST['status'] : 'active';

    if ($id <= 0 || $fullname === '' || $email === '') {
        header('Location: quanlynguoidung.php?loi=thieu');
        exit();
    }

    if ($newpass !== '') {
        // Có nhập mật khẩu mới thì đổi luôn mật khẩu
        $mk = md5($newpass);
        $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, role = ?, status = ?, password = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "sssssssi", $fullname, $email, $phone, $address, $role, $status, $mk, $id);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, role = ?, status = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssi", $fullname, $email, $phone, $address, $role, $status, $id);
    }
    try {
        $ok = mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $e) {
        $ok = false; // thường do trùng email
    }
    mysqli_stmt_close($stmt);

    header('Location: quanlynguoidung.php' . ($ok ? '' : '?loi=trung'));
    exit();
}

// ============================================================================
// CHỨC NĂNG 3: XÓA NGƯỜI DÙNG (xóa đơn hàng + giỏ hàng của họ trước để không lỗi khóa ngoại)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);

    if ($id > 0 && $id === $my_id) {
        header('Location: quanlynguoidung.php?loi=khongxoa');
        exit();
    }
    if ($id > 0) {
        mysqli_begin_transaction($conn);
        try {
            foreach (
                [
                    "DELETE FROM orders WHERE user_id = ?",
                    "DELETE FROM cart_items WHERE user_id = ?",
                    "DELETE FROM users WHERE user_id = ?"
                ] as $sql
            ) {
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
        }
    }
    header('Location: quanlynguoidung.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 4: ĐỔI TRẠNG THÁI NHANH (Hoạt động <-> Không hoạt động)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $id = (int)($_GET['id'] ?? 0);

    if ($id > 0 && $id === $my_id) {
        header('Location: quanlynguoidung.php?loi=khongxoa');
        exit();
    }
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: quanlynguoidung.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 5: TRUY VẤN LẤY DỮ LIỆU (tìm kiếm + lọc)
// ============================================================================
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter   = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Không lấy cột password để không lộ mật khẩu ra trang
$sql_users = "SELECT user_id, username, fullname, email, phone, address, role, status FROM users WHERE 1=1";
$params = [];
$types = "";

// Tìm theo số điện thoại, họ tên, email hoặc tên đăng nhập
if ($search !== '') {
    $sql_users .= " AND (phone LIKE ? OR fullname LIKE ? OR email LIKE ? OR username LIKE ?)";
    $like = "%" . $search . "%";
    array_push($params, $like, $like, $like, $like);
    $types .= "ssss";
}
if (isset(VAI_TRO[$role_filter])) {
    $sql_users .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}
if (isset(TRANG_THAI[$status_filter])) {
    $sql_users .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$sql_users .= " ORDER BY user_id ASC";

$stmt = mysqli_prepare($conn, $sql_users);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$users = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// =========================================================================
// MỞ MODAL SỬA BẰNG IF PHP (khi bấm nút có name="btn_open_edit")
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_open_edit'])) {
    $edit_id = (int)($_POST['edit_id'] ?? 0);
    $stmt_edit = mysqli_prepare($conn, "SELECT user_id, username, fullname, email, phone, address, role, status FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_edit, "i", $edit_id);
    mysqli_stmt_execute($stmt_edit);
    $edit_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_edit));
    mysqli_stmt_close($stmt_edit);

    if ($edit_user) {
        echo '
        <div class="modal modal--open">
            <div class="modal__dialog">
                <div class="modal__header">
                    <h2 class="modal__title">Sửa thông tin người dùng</h2>
                    <a href="quanlynguoidung.php" class="modal__close">×</a>
                </div>

                <form action="quanlynguoidung.php" method="POST" class="modal__body">
                    <input type="hidden" name="user_id" value="' . htmlspecialchars($edit_user['user_id']) . '">

                    <div class="form-group">
                        <label class="form-label">Tên đăng nhập</label>
                        <input type="text" value="' . htmlspecialchars($edit_user['username']) . '" disabled class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mật khẩu mới (bỏ trống nếu không đổi)</label>
                        <input type="password" name="password" minlength="6" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Họ và tên</label>
                        <input type="text" name="fullname" value="' . htmlspecialchars($edit_user['fullname'] ?? '') . '" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="' . htmlspecialchars($edit_user['email'] ?? '') . '" required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" value="' . htmlspecialchars($edit_user['phone'] ?? '') . '" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Địa chỉ</label>
                        <input type="text" name="address" value="' . htmlspecialchars($edit_user['address'] ?? '') . '" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Vai trò</label>
                        <select name="role" class="form-control">';
        foreach (VAI_TRO as $ma => $ten) {
            $selected = (($edit_user['role'] ?? 'customer') === $ma) ? 'selected' : '';
            echo '<option value="' . $ma . '" ' . $selected . '>' . $ten . '</option>';
        }
        echo '          </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-control">';
        foreach (TRANG_THAI as $ma => $ten) {
            $selected = (($edit_user['status'] ?? 'active') === $ma) ? 'selected' : '';
            echo '<option value="' . $ma . '" ' . $selected . '>' . $ten . '</option>';
        }
        echo '          </select>
                    </div>

                    <div class="modal__footer">
                        <button type="submit" name="btn_update_user" class="btn btn--blue">Lưu thay đổi</button>
                        <a href="quanlynguoidung.php" class="btn btn--light">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
        ';
    }
}

// =========================================================================
// MỞ MODAL THÊM BẰNG IF PHP (khi bấm nút có name="btn_open_add")
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_open_add'])) {
    echo '
    <div class="modal modal--open">
        <div class="modal__dialog">
            <div class="modal__header">
                <h2 class="modal__title">Thêm người dùng mới</h2>
                <a href="quanlynguoidung.php" class="modal__close">×</a>
            </div>

            <form action="quanlynguoidung.php" method="POST" class="modal__body">
                <div class="form-group">
                    <label class="form-label">Tên đăng nhập</label>
                    <input type="text" name="username" required class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Mật khẩu (từ 6 ký tự)</label>
                    <input type="password" name="password" required minlength="6" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Họ và tên</label>
                    <input type="text" name="fullname" required class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" required class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="address" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Vai trò</label>
                    <select name="role" class="form-control">';
    foreach (VAI_TRO as $ma => $ten) {
        echo '<option value="' . $ma . '">' . $ten . '</option>';
    }
    echo '          </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-control">';
    foreach (TRANG_THAI as $ma => $ten) {
        echo '<option value="' . $ma . '">' . $ten . '</option>';
    }
    echo '          </select>
                </div>

                <div class="modal__footer">
                    <button type="submit" name="btn_add_user" class="btn btn--primary">Thêm mới</button>
                    <a href="quanlynguoidung.php" class="btn btn--light">Hủy</a>
                </div>
            </form>
        </div>
    </div>
    ';
}

// =========================================================================
// MỞ MODAL XÁC NHẬN XÓA BẰNG IF PHP (khi bấm nút có name="btn_open_delete")
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_open_delete'])) {
    $del_id = (int)($_POST['delete_id'] ?? 0);
    $stmt_del = mysqli_prepare($conn, "SELECT user_id, username, fullname FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $del_id);
    mysqli_stmt_execute($stmt_del);
    $del_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_del));
    mysqli_stmt_close($stmt_del);

    if ($del_user) {
        echo '
        <div class="modal modal--open">
            <div class="modal__dialog modal__dialog--sm">
                <div class="modal__header">
                    <h2 class="modal__title">Xác nhận xóa người dùng</h2>
                    <a href="quanlynguoidung.php" class="modal__close">×</a>
                </div>

                <div class="modal__body">
                    <p>Xóa người dùng <strong>' . htmlspecialchars($del_user['fullname'] ?: $del_user['username']) . '</strong>? Toàn bộ đơn hàng và giỏ hàng của họ cũng bị xóa theo, không thể hoàn tác.</p>
                    <div class="modal__footer">
                        <a href="quanlynguoidung.php?action=delete&id=' . (int)$del_user['user_id'] . '" class="btn btn--rose">Xác nhận xóa</a>
                        <a href="quanlynguoidung.php" class="btn btn--light">Hủy</a>
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
    <title>Quản lý người dùng - FastFood Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="file css/quanlynguoidung.css">
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
                <a href="quanlynguoidung.php" class="nav-item nav-item--active">
                    <i class="fa-solid fa-user nav-icon"></i> Quản lý người dùng
                </a>
                <a href="quanlidonhang.php" class="nav-item">
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
            <form action="quanlynguoidung.php" method="GET" class="search-form">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm theo SĐT, họ tên, email..." class="search-input">
            </form>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <span class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></span>
            </div>
        </header>

        <main class="content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Quản lý người dùng</h1>
                </div>
                <form action="quanlynguoidung.php" method="POST" style="display: inline;">
                    <button type="submit" name="btn_open_add" class="btn btn--primary">
                        <i class="fa-solid fa-plus"></i> Thêm người dùng
                    </button>
                </form>
            </div>

            <?php if ($loi !== ''): ?>
                <div class="alert alert--error"><?php echo htmlspecialchars($loi); ?></div>
            <?php endif; ?>

            <!-- FORM BỘ LỌC DỮ LIỆU -->
            <form action="quanlynguoidung.php" method="GET" class="filter-card">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">

                <select name="role" class="form-control">
                    <option value="">Tất cả vai trò</option>
                    <?php foreach (VAI_TRO as $ma => $ten): ?>
                        <option value="<?php echo $ma; ?>" <?php echo $role_filter === $ma ? 'selected' : ''; ?>><?php echo $ten; ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach (TRANG_THAI as $ma => $ten): ?>
                        <option value="<?php echo $ma; ?>" <?php echo $status_filter === $ma ? 'selected' : ''; ?>><?php echo $ten; ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn btn--dark">
                    <i class="fa-solid fa-filter"></i> Lọc dữ liệu
                </button>

                <?php if ($search !== '' || isset(VAI_TRO[$role_filter]) || isset(TRANG_THAI[$status_filter])): ?>
                    <a href="quanlynguoidung.php" class="btn btn--light">
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
                                <th class="text-center w-12">ID</th>
                                <th>Họ và tên</th>
                                <th>Email</th>
                                <th>Số điện thoại</th>
                                <th class="text-center">Vai trò</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="table-empty">Không tìm thấy người dùng nào!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="text-center text-muted font-medium">#<?php echo $u['user_id']; ?></td>
                                        <td>
                                            <div class="food-name"><?php echo htmlspecialchars($u['fullname'] ?: $u['username']); ?></div>
                                            <div class="food-desc">@<?php echo htmlspecialchars($u['username']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['phone'] ?? ''); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $u['role'] === 'manager' ? 'badge--orange' : 'badge--slate'; ?>">
                                                <?php echo htmlspecialchars(VAI_TRO[$u['role']] ?? $u['role']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="quanlynguoidung.php?action=toggle_status&id=<?php echo $u['user_id']; ?>"
                                                class="badge-btn <?php echo $u['status'] === 'active' ? 'badge-btn--success' : 'badge-btn--danger'; ?>">
                                                <?php echo htmlspecialchars(TRANG_THAI[$u['status']] ?? $u['status']); ?>
                                            </a>
                                        </td>
                                        <td class="text-right">
                                            <form action="quanlynguoidung.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="edit_id" value="<?php echo $u['user_id']; ?>">
                                                <button type="submit" name="btn_open_edit" class="btn btn--sm btn--blue">
                                                    Sửa
                                                </button>
                                            </form>
                                            <form action="quanlynguoidung.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="delete_id" value="<?php echo $u['user_id']; ?>">
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