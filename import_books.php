<?php
session_start();
require_once 'config.php';

// Chỉ cho phép Admin hoặc Thủ thư truy cập
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    die("Bạn không có quyền truy cập trang này!");
}

$msg = '';

if (isset($_POST["import"])) {
    // Kiểm tra xem đã chọn file chưa và file có phải là CSV không
    $fileName = $_FILES["file"]["tmp_name"];
    
    if ($_FILES["file"]["size"] > 0) {
        $file = fopen($fileName, "r");
        
        // Bỏ qua dòng đầu tiên (dòng tiêu đề: Book Title, Author, Quantity)
        fgetcsv($file, 10000, ",");
        
        $count = 0;
        // Vòng lặp đọc từng dòng trong file CSV
        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            // Cột A trong Excel là $column[0] (Tên sách)
            $title = trim($column[0]);
            // Cột B trong Excel là $column[1] (Tác giả)
            $author = trim($column[1]);
            // Cột C trong Excel là $column[2] (Số lượng)
            $quantity = (int)$column[2];
            
            // Vì file của bạn không có cột Thể loại, ta sẽ set mặc định là "Chuyên ngành"
            $category = "Trí tuệ nhân tạo"; // Hoặc "Chưa phân loại"
            
            // Nếu dòng không bị rỗng thì tiến hành Insert
            if (!empty($title)) {
                $stmt = $pdo->prepare("INSERT INTO Books (title, author, category, quantity) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $author, $category, $quantity]);
                $count++;
            }
        }
        fclose($file);
        $msg = "<div style='color:green; padding: 10px; background: #dcfce7; border-radius: 5px; margin-bottom: 15px;'>✅ Đã nhập thành công $count đầu sách vào cơ sở dữ liệu!</div>";
    } else {
        $msg = "<div style='color:red; padding: 10px; background: #fee2e2; border-radius: 5px; margin-bottom: 15px;'>❌ Vui lòng chọn file CSV hợp lệ.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhập sách từ Excel (CSV)</title>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Be Vietnam Pro', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { margin-top: 0; color: #0054a5; }
        input[type="file"] { border: 1px dashed #ccc; padding: 20px; width: 100%; box-sizing: border-box; margin-bottom: 15px; border-radius: 5px; cursor: pointer; }
        .btn { background: #0054a5; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; width: 100%; font-weight: bold; }
        .btn:hover { background: #003d7a; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h2><i class="fas fa-file-excel"></i> Nhập kho sách tự động</h2>
        <p style="font-size: 14px; color: #555;">Vui lòng chuyển file Excel thành đuôi <b>.csv</b> trước khi tải lên. Hệ thống sẽ tự động bỏ qua dòng tiêu đề.</p>
        
        <?php echo $msg; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" accept=".csv" required>
            <button type="submit" name="import" class="btn">Tiến hành nhập dữ liệu</button>
        </form>
        
        <a href="index.php" class="back-link">⬅ Quay lại Bảng điều khiển</a>
    </div>
</body>
</html>