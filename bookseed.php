<?php
session_start();
require_once 'config.php';

// Chỉ admin hoặc thủ thư được nhập sách
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'librarian')) {
    die("Bạn không có quyền truy cập trang này!");
}

$msg = '';

if (isset($_POST['import'])) {
    if (!isset($_FILES['file']) || $_FILES['file']['size'] <= 0) {
        $msg = "<div class='alert alert-danger'>❌ Vui lòng chọn file CSV.</div>";
    } else {
        $fileName = $_FILES['file']['tmp_name'];
        $file = fopen($fileName, "r");

        if (!$file) {
            $msg = "<div class='alert alert-danger'>❌ Không thể đọc file CSV.</div>";
        } else {
            $count = 0;
            $skipRows = 4; 
            $currentRow = 0;

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO Books 
                    (
                        title, 
                        author, 
                        category, 
                        publish_year, 
                        pages, 
                        language, 
                        description, 
                        quantity, 
                        ebook_available, 
                        ebook_url, 
                        image_url
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                while (($column = fgetcsv($file, 10000, ",")) !== false) {
                    $currentRow++;

                    // Bỏ qua 4 dòng đầu nếu file mẫu của bạn có phần hướng dẫn
                    if ($currentRow <= $skipRows) {
                        continue;
                    }

                    // Cột 0 - 4: thông tin cơ bản
                    $title = trim($column[0] ?? '');
                    $author = trim($column[1] ?? '');
                    $category = trim($column[2] ?? '');
                    $quantity = (int)trim($column[3] ?? 0);
                    $imageFile = trim($column[4] ?? '');

                    // Cột 5 - 8: detail.php
                    $publish_year = trim($column[5] ?? '');
                    $pages = trim($column[6] ?? '');
                    $language = trim($column[7] ?? '');
                    $description = trim($column[8] ?? '');

                    // Cột 9 - 11: sách vật lí / ebook
                    $physical_available = trim($column[9] ?? '');
                    $ebook_available = trim($column[10] ?? '');
                    $ebookFile = trim($column[11] ?? '');

                    // Bỏ qua dòng trống
                    if ($title === '' || $author === '') {
                        continue;
                    }

                    if ($category === '') {
                        $category = 'Khác';
                    }

                    if ($language === '') {
                        $language = 'Tiếng Việt';
                    }

                    $publish_year = ($publish_year !== '') ? (int)$publish_year : null;
                    $pages = ($pages !== '') ? (int)$pages : null;

                    // Nếu cột Có sách vật lí để trống thì mặc định có
                    // Nhập 1/Có/co/yes/y thì là có, nhập 0/Không/khong/no/n thì là không
                    if ($physical_available === '') {
                        $physical_available = 1;
                    } else {
                        $physical_available = strtolower($physical_available);
                        $physical_available = in_array($physical_available, ['1', 'co', 'có', 'yes', 'y']) ? 1 : 0;
                    }

                    // Nếu cột Có ebook để trống thì tự xét theo tên file ebook
                    if ($ebook_available === '') {
                        $ebook_available = ($ebookFile !== '') ? 1 : 0;
                    } else {
                        $ebook_available = strtolower($ebook_available);
                        $ebook_available = in_array($ebook_available, ['1', 'co', 'có', 'yes', 'y']) ? 1 : 0;
                    }

                    // Ảnh bìa: chỉ nhập tên file, PHP tự ghép img/books/
                    if ($imageFile !== '') {
                        $image_url = 'img/books/' . basename($imageFile);
                    } else {
                        $image_url = null;
                    }

                    // Ebook: chỉ nhập tên file, PHP tự ghép ebooks/
                    if ($ebookFile !== '') {
                        $ebook_url = 'ebooks/' . basename($ebookFile);
                    } else {
                        $ebook_url = null;
                    }

                    $stmt->execute([
                        $title,
                        $author,
                        $category,
                        $publish_year,
                        $pages,
                        $language,
                        $description,
                        $quantity,
                        $ebook_available,
                        $ebook_url,
                        $image_url
                    ]);

                    $count++;
                }

                $pdo->commit();
                fclose($file);

                $msg = "<div class='alert alert-success'>✅ Đã nhập thành công <strong>$count</strong> đầu sách vào cơ sở dữ liệu!</div>";

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                fclose($file);
                $msg = "<div class='alert alert-danger'>❌ Lỗi database: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nhập sách từ CSV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f7f6;">

<div class="container py-5">
    <div class="card mx-auto shadow" style="max-width:720px;">
        <div class="card-body p-4">
            <h3 class="text-primary fw-bold mb-3">Nhập sách từ file CSV</h3>

            <?php echo $msg; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Chọn file CSV</label>
                    <input type="file" name="file" class="form-control" accept=".csv" required>
                </div>

                <button type="submit" name="import" class="btn btn-primary w-100 fw-bold">
                    NHẬP VÀO CƠ SỞ DỮ LIỆU
                </button>
            </form>

            <hr>

            <p class="small text-muted mb-1">
                File CSV cần có các cột theo thứ tự:
            </p>

            <code style="white-space:normal;display:block;line-height:1.7;">
                Tên sách, Tác giả, Thể loại, Số lượng, Tên file ảnh,
                Năm xuất bản, Số trang, Ngôn ngữ, Nội dung chính,
                Có sách vật lí, Có ebook, Tên file ebook
            </code>

            <div class="alert alert-info mt-3 small mb-0">
                <strong>Lưu ý:</strong><br>
                Ảnh bìa chỉ nhập tên file, ví dụ: <code>lap-trinh-c.jpg</code>. File thật đặt trong <code>img/books/</code>.<br>
                Ebook chỉ nhập tên file, ví dụ: <code>lap-trinh-c.pdf</code>. File thật đặt trong <code>ebooks/</code>.
            </div>

            <div class="mt-3">
                <a href="indexlibrarian.php?tab=books" class="btn btn-outline-secondary btn-sm">
                    Quay lại quản lý sách
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>