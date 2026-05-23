<?php
session_start();
session_destroy(); // Xóa toàn bộ phiên đăng nhập
header("Location: index.php"); // Quay về trang chủ thay vì trang login
exit;
?>