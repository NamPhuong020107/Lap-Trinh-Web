<?php
session_start();
require_once __DIR__ . '/ketnoi.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] != "manager" && $_SESSION['role'] != "admin")) {
    header("location:admin.php");
    exit;
}

// ============================================================================
// CHỨC NĂNG 1: XỬ LÝ THÊM MÓN ĂN MỚI
// ============================================================================
if (isset($_POST['action_add'])) {
    $name = mysqli_real_escape_string($conn, $_POST['Foods_Name']);
    $category_id = (int)$_POST['Category_id'];
    $price = (float)$_POST['Foods_Price'];
    $description = mysqli_real_escape_string($conn, $_POST['Foods_Description']);
    $status = mysqli_real_escape_string($conn, $_POST['Foods_Status']);

    $image_name = 'burger.jpg';
    if (isset($_FILES['Foods_Image']) && $_FILES['Foods_Image']['error'] === 0) {
        $ext = pathinfo($_FILES['Foods_Image']['name'], PATHINFO_EXTENSION);
        $image_name = time() . '_' . uniqid() . '.' . $ext;
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0777, true);
        }
        move_uploaded_file($_FILES['Foods_Image']['tmp_name'], __DIR__ . '/img/' . $image_name);
    }

    $sql_add = "INSERT INTO foods (Foods_Name, Category_id, Foods_Price, Foods_Description, Foods_Status, Foods_Image) 
                VALUES ('$name', $category_id, $price, '$description', '$status', '$image_name')";

    mysqli_query($conn, $sql_add);
    header('Location: quanlisanpham.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 2: XỬ LÝ SỬA CẬP NHẬT MÓN ĂN
// ============================================================================
if (isset($_POST['action_edit'])) {
    $id = (int)$_POST['Foods_ID'];
    $name = mysqli_real_escape_string($conn, $_POST['Foods_Name']);
    $category_id = (int)$_POST['Category_id'];
    $price = (float)$_POST['Foods_Price'];
    $description = mysqli_real_escape_string($conn, $_POST['Foods_Description']);
    $status = mysqli_real_escape_string($conn, $_POST['Foods_Status']);

    if (isset($_FILES['Foods_Image']) && $_FILES['Foods_Image']['error'] === 0) {
        $ext = pathinfo($_FILES['Foods_Image']['name'], PATHINFO_EXTENSION);
        $image_name = time() . '_' . uniqid() . '.' . $ext;
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0777, true);
        }
        move_uploaded_file($_FILES['Foods_Image']['tmp_name'], __DIR__ . '/img/' . $image_name);
        $sql_edit = "UPDATE foods SET Foods_Name='$name', Category_id=$category_id, Foods_Price=$price, Foods_Description='$description', Foods_Status='$status', Foods_Image='$image_name' WHERE Foods_ID=$id";
    } else {
        $sql_edit = "UPDATE foods SET Foods_Name='$name', Category_id=$category_id, Foods_Price=$price, Foods_Description='$description', Foods_Status='$status' WHERE Foods_ID=$id";
    }

    mysqli_query($conn, $sql_edit);
    header('Location: quanlisanpham.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 3: XỬ LÝ XÓA MÓN ĂN
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $sql_delete = "DELETE FROM foods WHERE Foods_ID = $id";
        mysqli_query($conn, $sql_delete);
    }
    header('Location: quanlisanpham.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 4: XỬ LÝ ĐỔI TRẠNG THÁI NHANH
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $id = (int)($_GET['id'] ?? 0);
    $current_status = $_GET['current'] ?? '';

    $new_status = (mb_strtolower($current_status) === 'còn hàng') ? 'hết hàng' : 'còn hàng';

    if ($id > 0) {
        $sql_toggle = "UPDATE foods SET Foods_Status = '$new_status' WHERE Foods_ID = $id";
        mysqli_query($conn, $sql_toggle);
    }
    header('Location: quanlisanpham.php');
    exit();
}

// ============================================================================
// CHỨC NĂNG 5: TRUY VẤN LẤY DỮ LIỆU
// ============================================================================
$sql_categories = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
$result_categories = mysqli_query($conn, $sql_categories);
$categories = [];
if ($result_categories) {
    while ($cat = mysqli_fetch_assoc($result_categories)) {
        $categories[$cat['category_id']] = $cat['category_name'];
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category_id']) ? $_GET['category_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_clauses = ["1=1"];

if (!empty($search)) {
    $search_clean = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(f.Foods_Name LIKE '%$search_clean%' OR f.Foods_Description LIKE '%$search_clean%')";
}

if (!empty($category_filter)) {
    $category_filter_clean = (int)$category_filter;
    $where_clauses[] = "f.Category_id = $category_filter_clean";
}

if (!empty($status_filter)) {
    $status_clean = mysqli_real_escape_string($conn, $status_filter);
    $where_clauses[] = "f.Foods_Status = '$status_clean'";
}

$where_sql = implode(" AND ", $where_clauses);

$sql_foods = "SELECT f.Foods_ID, f.Foods_Name, f.Foods_Price, f.Foods_Description, 
                     f.Foods_Image, f.Foods_Status, f.Category_id, 
                     c.category_name 
              FROM foods f 
              LEFT JOIN categories c ON f.Category_id = c.category_id 
              WHERE " . $where_sql . " 
              ORDER BY f.Foods_ID DESC";

$result_foods = mysqli_query($conn, $sql_foods);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - FastFood Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="file css/quanlisanpham.css">
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
                <a href="quanlisanpham.php" class="nav-item nav-item--active">
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
            <form action="quanlisanpham.php" method="GET" class="search-form">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm kiếm tên món ăn..." class="search-input">
            </form>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <span class="user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin') ?></span>
            </div>
        </header>

        <main class="content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Quản lý sản phẩm</h1>
                    
                </div>
                <button onclick="toggleAddModal(true)" class="btn btn--primary">
                    <i class="fa-solid fa-plus"></i> Thêm món mới
                </button>
            </div>

            <!-- FORM BỘ LỌC DỮ LIỆU -->
            <form action="quanlisanpham.php" method="GET" class="filter-card">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">

                <select name="category_id" class="form-control">
                    <option value="">Tất cả loại sản phẩm</option>
                    <?php foreach ($categories as $cat_id => $cat_name): ?>
                        <option value="<?php echo $cat_id; ?>" <?php echo $category_filter == $cat_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control">
                    <option value="">Tất cả trạng thái</option>
                    <option value="còn hàng" <?php echo strtolower($status_filter) === 'còn hàng' ? 'selected' : ''; ?>>Còn hàng</option>
                    <option value="hết hàng" <?php echo strtolower($status_filter) === 'hết hàng' ? 'selected' : ''; ?>>Hết hàng</option>
                </select>

                <button type="submit" class="btn btn--dark">
                    <i class="fa-solid fa-filter"></i> Lọc dữ liệu
                </button>

                <?php if (!empty($category_filter) || !empty($status_filter) || !empty($search)): ?>
                    <a href="quanlisanpham.php" class="btn btn--light">
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
                                <th>Hình ảnh</th>
                                <th>Tên món & Mô tả</th>
                                <th>Loại sản phẩm</th>
                                <th>Giá bán</th>
                                <th class="text-center">Trạng thái bếp</th>
                                <th class="text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$result_foods || mysqli_num_rows($result_foods) == 0): ?>
                                <tr>
                                    <td colspan="7" class="table-empty">Không tìm thấy món ăn nào!</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($item = mysqli_fetch_assoc($result_foods)): ?>
                                    <tr>
                                        <td class="text-center text-muted font-medium">
                                            #<?php echo $item['Foods_ID']; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $image_name = !empty($item['Foods_Image']) ? $item['Foods_Image'] : 'burger.jpg';
                                            $image_path = 'img/' . $image_name;

                                            // Kiểm tra xem file có thực sự nằm trong thư mục img/ hay không
                                            if (!file_exists(__DIR__ . '/' . $image_path)) {
                                                $display_src = 'https://placehold.co/100x100?text=No+Image';
                                            } else {
                                                $display_src = $image_path;
                                            }
                                            ?>
                                            <img src="<?php echo htmlspecialchars($display_src); ?>"
                                                alt="<?php echo htmlspecialchars($item['Foods_Name']); ?>"
                                                class="food-img">
                                        </td>
                                        <td>
                                            <div class="food-name"><?php echo htmlspecialchars($item['Foods_Name']); ?></div>
                                            <div class="food-desc"><?php echo htmlspecialchars($item['Foods_Description']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge badge--slate">
                                                <?php echo htmlspecialchars($item['category_name'] ?? 'Chưa phân loại'); ?>
                                            </span>
                                        </td>
                                        <td class="food-price">
                                            <?php echo number_format($item['Foods_Price'], 0, ',', '.'); ?>đ
                                        </td>
                                        <td class="text-center">
                                            <a href="quanlisanpham.php?action=toggle_status&id=<?php echo $item['Foods_ID']; ?>&current=<?php echo urlencode($item['Foods_Status']); ?>"
                                                class="badge-btn <?php echo (mb_strtolower($item['Foods_Status']) === 'còn hàng') ? 'badge-btn--success' : 'badge-btn--danger'; ?>">
                                                <?php echo htmlspecialchars($item['Foods_Status']); ?>
                                            </a>
                                        </td>
                                        <td class="text-right">
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="btn btn--sm btn--blue">
                                                Sửa
                                            </button>
                                            <a href="quanlisanpham.php?action=delete&id=<?php echo $item['Foods_ID']; ?>" onclick="return confirm('Bạn chắc chắn muốn xóa món này?')" class="btn btn--sm btn--rose">
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

    <!-- MODAL 1: THÊM MÓN ĂN MỚI -->
    <div id="productModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Thêm món ăn mới</h3>
                <button onclick="toggleAddModal(false)" class="modal__close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="quanlisanpham.php" method="POST" enctype="multipart/form-data" class="modal__body">
                <input type="hidden" name="action_add" value="1">
                <div class="form-group">
                    <label class="form-label">Tên món ăn</label>
                    <input type="text" name="Foods_Name" required placeholder="Nhập tên món..." class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Loại sản phẩm</label>
                        <select name="Category_id" required class="form-control">
                            <?php foreach ($categories as $cat_id => $cat_name): ?>
                                <option value="<?php echo $cat_id; ?>"><?php echo htmlspecialchars($cat_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Giá bán (VNĐ)</label>
                        <input type="number" name="Foods_Price" required placeholder="55000" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả món ăn</label>
                    <textarea name="Foods_Description" rows="2" placeholder="Mô tả..." class="form-control"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Trạng thái bếp</label>
                        <select name="Foods_Status" class="form-control">
                            <option value="còn hàng">Còn hàng</option>
                            <option value="hết hàng">Hết hàng</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hình ảnh</label>
                        <input type="file" name="Foods_Image" accept="image/*" class="form-file">
                    </div>
                </div>

                <div class="modal__footer">
                    <button type="button" onclick="toggleAddModal(false)" class="btn btn--light">Hủy</button>
                    <button type="submit" class="btn btn--primary">Lưu vào Database</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: SỬA MÓN ĂN -->
    <div id="editModal" class="modal">
        <div class="modal__dialog">
            <div class="modal__header">
                <h3 class="modal__title">Sửa món ăn</h3>
                <button onclick="toggleEditModal(false)" class="modal__close"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="quanlisanpham.php" method="POST" enctype="multipart/form-data" class="modal__body">
                <input type="hidden" name="action_edit" value="1">
                <input type="hidden" id="edit_Foods_ID" name="Foods_ID">

                <div class="form-group">
                    <label class="form-label">Tên món ăn</label>
                    <input type="text" id="edit_Foods_Name" name="Foods_Name" required class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Loại sản phẩm</label>
                        <select id="edit_Category_id" name="Category_id" required class="form-control">
                            <?php foreach ($categories as $cat_id => $cat_name): ?>
                                <option value="<?php echo $cat_id; ?>"><?php echo htmlspecialchars($cat_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Giá bán (VNĐ)</label>
                        <input type="number" id="edit_Foods_Price" name="Foods_Price" required class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả món ăn</label>
                    <textarea id="edit_Foods_Description" name="Foods_Description" rows="2" class="form-control"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Trạng thái bếp</label>
                        <select id="edit_Foods_Status" name="Foods_Status" class="form-control">
                            <option value="còn hàng">Còn hàng</option>
                            <option value="hết hàng">Hết hàng</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Thay ảnh mới (nếu muốn)</label>
                        <input type="file" name="Foods_Image" accept="image/*" class="form-file">
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
            const modal = document.getElementById('productModal');
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

        function openEditModal(item) {
            document.getElementById('edit_Foods_ID').value = item.Foods_ID;
            document.getElementById('edit_Foods_Name').value = item.Foods_Name;
            document.getElementById('edit_Category_id').value = item.Category_id;
            document.getElementById('edit_Foods_Price').value = item.Foods_Price;
            document.getElementById('edit_Foods_Description').value = item.Foods_Description || '';
            document.getElementById('edit_Foods_Status').value = item.Foods_Status.toLowerCase();

            toggleEditModal(true);
        }
    </script>
</body>

</html>