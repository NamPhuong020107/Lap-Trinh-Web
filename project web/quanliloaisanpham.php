<?php
// quanliloaisanpham.php
session_start();
require_once __DIR__ . '/ketnoi.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] != "manager" && $_SESSION['role'] != "admin")) {
    header("location:admin.php");
    exit;
}

// ============================================================================
// CHỨC NĂNG 1: XỬ LÝ THÊM LOẠI SẢN PHẨM MỚI
// ============================================================================
if (isset($_POST['action_add'])) {
    $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));

    if (!empty($category_name)) {
        $sql_add = "INSERT INTO categories (category_name) VALUES ('$category_name')";
        mysqli_query($conn, $sql_add);
    }
    header('Location: quanliloaisanpham.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 2: XỬ LÝ SỬA CẬP NHẬT LOẠI SẢN PHẨM
// ============================================================================
if (isset($_POST['action_edit'])) {
    $category_id = (int)$_POST['category_id'];
    $category_name = mysqli_real_escape_string($conn, trim($_POST['category_name']));

    if ($category_id > 0 && !empty($category_name)) {
        $sql_edit = "UPDATE categories SET category_name = '$category_name' WHERE category_id = $category_id";
        mysqli_query($conn, $sql_edit);
    }
    header('Location: quanliloaisanpham.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 3: XỬ LÝ XÓA LOẠI SẢN PHẨM
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $sql_delete = "DELETE FROM categories WHERE category_id = $id";
        mysqli_query($conn, $sql_delete);
    }
    header('Location: quanliloaisanpham.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 4: TRUY VẤN LẤY DỮ LIỆU & ĐẾM SỐ MÓN ĂN THUỘC LOẠI
// ============================================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_sql = "1=1";

if (!empty($search)) {
    $search_clean = mysqli_real_escape_string($conn, $search);
    $where_sql = "c.category_name LIKE '%$search_clean%'";
}

// Truy vấn loại sản phẩm và đếm tổng số lượng món ăn đi kèm trong bảng foods
$sql_categories = "SELECT c.category_id, c.category_name, COUNT(f.Foods_ID) AS total_foods 
                   FROM categories c 
                   LEFT JOIN foods f ON c.category_id = f.Category_id 
                   WHERE $where_sql 
                   GROUP BY c.category_id, c.category_name 
                   ORDER BY c.category_id DESC";

$result_categories = mysqli_query($conn, $sql_categories);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý loại sản phẩm - FastFood Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="file css/quanliloaisanpham.css">
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
                <a href="quanliloaisanpham.php" class="nav-item nav-item--active">
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
            <form action="quanliloaisanpham.php" method="GET" class="search-form">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm kiếm tên loại sản phẩm..." class="search-input">
            </form>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <span class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin') ?></span>
            </div>
        </header>

        <main class="content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Quản lý loại sản phẩm</h1>
                    
                </div>
                <button onclick="toggleAddModal(true)" class="btn btn--primary">
                    <i class="fa-solid fa-plus"></i> Thêm loại mới
                </button>
            </div>

            <!-- FORM BỘ LỌC DỮ LIỆU -->
            <form action="quanliloaisanpham.php" method="GET" class="filter-card">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nhập tên loại sản phẩm..." class="form-control" style="width: 260px;">

                <button type="submit" class="btn btn--dark">
                    <i class="fa-solid fa-filter"></i> Lọc dữ liệu
                </button>

                <?php if (!empty($search)): ?>
                    <a href="quanliloaisanpham.php" class="btn btn--light">
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
                                <th>Tên loại sản phẩm</th>
                                <th class="text-center">Số lượng món ăn</th>
                                <th class="text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$result_categories || mysqli_num_rows($result_categories) == 0): ?>
                                <tr>
                                    <td colspan="4" class="table-empty">Không tìm thấy loại sản phẩm nào!</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($item = mysqli_fetch_assoc($result_categories)): ?>
                                    <tr>
                                        <td class="text-center text-muted font-medium">
                                            #<?php echo $item['category_id']; ?>
                                        </td>
                                        <td>
                                            <div class="category-name"><?php echo htmlspecialchars($item['category_name']); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge--slate">
                                                <?php echo $item['total_foods']; ?> món ăn
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn btn--sm btn--blue">
                                                Sửa
                                            </button>
                                            <a href="quanliloaisanpham.php?action=delete&id=<?php echo $item['category_id']; ?>" onclick="return confirm('Bạn chắc chắn muốn xóa loại này? Các món thuộc loại có thể bị ảnh hưởng!')" class="btn btn--sm btn--rose">
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

    <!-- MODAL 1: THÊM LOẠI SẢN PHẨM MỚI -->
    <div id="categoryModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Thêm loại sản phẩm mới</h3>
                <button onclick="toggleAddModal(false)" class="modal__close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="quanliloaisanpham.php" method="POST" class="modal__body">
                <input type="hidden" name="action_add" value="1">
                <div class="form-group">
                    <label class="form-label">Tên loại sản phẩm</label>
                    <input type="text" name="category_name" required placeholder="Ví dụ: Burger, Nước ngọt, Gà rán..." class="form-control">
                </div>

                <div class="modal__footer">
                    <button type="button" onclick="toggleAddModal(false)" class="btn btn--light">Hủy</button>
                    <button type="submit" class="btn btn--primary">Lưu vào Database</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: SỬA LOẠI SẢN PHẨM -->
    <div id="editCategoryModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Sửa loại sản phẩm</h3>
                <button onclick="toggleEditModal(false)" class="modal__close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="quanliloaisanpham.php" method="POST" class="modal__body">
                <input type="hidden" name="action_edit" value="1">
                <input type="hidden" id="edit_category_id" name="category_id">

                <div class="form-group">
                    <label class="form-label">Tên loại sản phẩm</label>
                    <input type="text" id="edit_category_name" name="category_name" required class="form-control">
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
            const modal = document.getElementById('categoryModal');
            if (show) {
                modal.classList.add('modal--open');
            } else {
                modal.classList.remove('modal--open');
            }
        }

        function toggleEditModal(show) {
            const modal = document.getElementById('editCategoryModal');
            if (show) {
                modal.classList.add('modal--open');
            } else {
                modal.classList.remove('modal--open');
            }
        }

        function openEditModal(item) {
            document.getElementById('edit_category_id').value = item.category_id;
            document.getElementById('edit_category_name').value = item.category_name;

            toggleEditModal(true);
        }
    </script>
</body>

</html>