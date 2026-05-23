<?php
set_time_limit(0);
require_once 'config.php'; // Gọi file cấu hình kết nối

try {
    // 1. Tạo bảng Users với thiết kế mới
    $sqlCreateTable = "
        CREATE TABLE IF NOT EXISTS Users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            full_name VARCHAR(100) NULL,
            email VARCHAR(100) UNIQUE NULL,
            phone_number VARCHAR(20) NULL,
            password VARCHAR(255) NOT NULL, 
            role ENUM('student', 'admin', 'librarian') NOT NULL DEFAULT 'student',
            major_name VARCHAR(100) NULL,
            cohort INT NULL
        )   ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($sqlCreateTable);
    $pdo->exec("TRUNCATE TABLE Users"); // Xóa data cũ (nếu có) để làm mới

    echo "<h3>Đang khởi tạo tài khoản hệ thống (3 Roles)...</h3>";
    $pdo->beginTransaction(); 
    $stmt = $pdo->prepare("INSERT INTO Users (username, full_name, email, phone_number, password, role, major_name, cohort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $totalCount = 0;

    // 2. Tạo tài khoản Sinh viên (Role: student)
    $majors_config = [
        '101' => ['name' => 'Công nghệ thông tin', 'cohorts' => range(44, 48), 'count' => 250],
        '102' => ['name' => 'Trí tuệ nhân tạo', 'cohorts' => range(44, 48), 'count' => 50],
        '103' => ['name' => 'Kỹ thuật phần mềm', 'cohorts' => range(43, 48), 'count' => 50]
    ];

    foreach ($majors_config as $major_code => $info) {
        foreach ($info['cohorts'] as $cohort) {
            for ($seq = 1; $seq <= $info['count']; $seq++) {
                $username = sprintf("%02d%s%04d", $cohort, $major_code, $seq);
                $full_name = "Sinh viên " . $username;
                $email = $username . "@student.qnu.edu.vn";
                $phone_number = "09" . str_pad((string)rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);

                $stmt->execute([$username, $full_name, $email, $phone_number, $username, 'student', $info['name'], $cohort]);
                $totalCount++;
            }
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }

    // 3. Tạo tài khoản Admin (Role: admin)
    $stmt->execute(['admin', 'Quản trị viên', 'admin@example.com', '0900000000', 'admin', 'admin', 'Quản trị hệ thống', 0]);
    $totalCount++;

    // 4. Tạo tài khoản Thủ thư (Role: librarian)
    $stmt->execute(['thuthu01', 'Thủ thư 01', 'thuthu01@example.com', '0900000001', '123456', 'librarian', 'Ban Thư Viện', 0]);
    $stmt->execute(['thuthu02', 'Thủ thư 02', 'thuthu02@example.com', '0900000002', '123456', 'librarian', 'Ban Thư Viện', 0]);
    $totalCount += 2;

    $pdo->commit(); 

    echo "<h2 style='color: green;'>Thành công!</h2>";
    echo "<p>Đã tạo tổng cộng <strong>$totalCount</strong> tài khoản.</p>";
    echo "<ul>
            <li><strong>Admin:</strong> admin / admin</li>
            <li><strong>Thủ thư:</strong> thuthu01 / 123456 (và thuthu02)</li>
            <li><strong>Sinh viên:</strong> Dùng mã 9 số (ví dụ: 441010001 / 441010001)</li>
          </ul>";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "<h2 style='color: red;'>Lỗi:</h2> " . $e->getMessage();
}
?>