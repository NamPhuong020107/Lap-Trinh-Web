<?php
$servername = "localhost";
$username   = "root";     // Mặc định của XAMPP là root
$password   = "";         // Mặc định của XAMPP là để trống
$dbname     = "bainhom8"; // Tên database của bạn

// Tạo kết nối
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}

// Thiết lập tiếng Việt utf8 để không bị lỗi font chữ
mysqli_set_charset($conn, "utf8mb4");
?>