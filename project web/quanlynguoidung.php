<?php
// quanlynguoidung.php
session_start();
require_once __DIR__ . '/ketnoi.php';

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

// ============================================================================
// CHỨC NĂNG 1: THÊM NGƯỜI DÙNG MỚI
// ============================================================================
if (isset($_POST['action_add'])) {
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

// ============================================================================
// CHỨC NĂNG 2: SỬA THÔNG TIN NGƯỜI DÙNG
// ============================================================================
if (isset($_POST['action_edit'])) {
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
        // order_details tự xóa theo orders (ON DELETE CASCADE), cart_items cũng vậy
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
                <button onclick="toggleAddModal(true)" class="btn btn--primary">
                    <i class="fa-solid fa-plus"></i> Thêm người dùng
                </button>
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
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($u)); ?>)" class="btn btn--sm btn--blue">
                                                Sửa
                                            </button>
                                            <a href="quanlynguoidung.php?action=delete&id=<?php echo $u['user_id']; ?>" onclick="return confirm('Xóa người dùng này? Toàn bộ đơn hàng và giỏ hàng của họ cũng bị xóa theo, không thể hoàn tác.')" class="btn btn--sm btn--rose">
                                                Xóa
                                            </a>
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

    <!-- MODAL 1: THÊM NGƯỜI DÙNG MỚI -->
    <div id="addModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Thêm người dùng mới</h3>
                <button onclick="toggleAddModal(false)" class="modal__close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="quanlynguoidung.php" method="POST" class="modal__body">
                <input type="hidden" name="action_add" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tên đăng nhập</label>
                        <input type="text" name="username" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mật khẩu (từ 6 ký tự)</label>
                        <input type="password" name="password" required minlength="6" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Họ và tên</label>
                    <input type="text" name="fullname" required class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" name="address" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Vai trò</label>
                        <select name="role" class="form-control">
                            <?php foreach (VAI_TRO as $ma => $ten): ?>
                                <option value="<?php echo $ma; ?>"><?php echo $ten; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-control">
                            <?php foreach (TRANG_THAI as $ma => $ten): ?>
                                <option value="<?php echo $ma; ?>"><?php echo $ten; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal__footer">
                    <button type="button" onclick="toggleAddModal(false)" class="btn btn--light">Hủy</button>
                    <button type="submit" class="btn btn--primary">Thêm mới</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: SỬA NGƯỜI DÙNG -->
    <div id="editModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Sửa thông tin người dùng</h3>
                <button onclick="toggleEditModal(false)" class="modal__close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="quanlynguoidung.php" method="POST" class="modal__body">
                <input type="hidden" name="action_edit" value="1">
                <input type="hidden" id="edit_user_id" name="user_id">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tên đăng nhập</label>
                        <input type="text" id="edit_username" disabled class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mật khẩu mới (bỏ trống nếu không đổi)</label>
                        <input type="password" name="password" minlength="6" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Họ và tên</label>
                    <input type="text" id="edit_fullname" name="fullname" required class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" id="edit_email" name="email" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" id="edit_phone" name="phone" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Địa chỉ</label>
                    <input type="text" id="edit_address" name="address" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Vai trò</label>
                        <select id="edit_role" name="role" class="form-control">
                            <?php foreach (VAI_TRO as $ma => $ten): ?>
                                <option value="<?php echo $ma; ?>"><?php echo $ten; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Trạng thái</label>
                        <select id="edit_status" name="status" class="form-control">
                            <?php foreach (TRANG_THAI as $ma => $ten): ?>
                                <option value="<?php echo $ma; ?>"><?php echo $ten; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal__footer">
                    <button type="button" onclick="toggleEditModal(false)" class="btn btn--light">Hủy</button>
                    <button type="submit" class="btn btn--blue">Cập nhật thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS ĐIỀU KHIỂN MODAL POPUP -->
    <script>
        function toggleAddModal(show) {
            const modal = document.getElementById('addModal');
            if (show) {
                modal.classList.add('modal--open');
            } else {
                modal.classList.remove('modal--open');
            }
        }

        function toggleEditModal(show) {
            const modal = document.getElementById('editModal');
            if (show) {
                modal.classList.add('modal--open');
            } else {
                modal.classList.remove('modal--open');
            }
        }

        function openEditModal(u) {
            document.getElementById('edit_user_id').value = u.user_id;
            document.getElementById('edit_username').value = u.username;
            document.getElementById('edit_fullname').value = u.fullname || '';
            document.getElementById('edit_email').value = u.email || '';
            document.getElementById('edit_phone').value = u.phone || '';
            document.getElementById('edit_address').value = u.address || '';
            document.getElementById('edit_role').value = u.role;
            document.getElementById('edit_status').value = u.status;

            toggleEditModal(true);
        }
    </script>
</body>

</html>