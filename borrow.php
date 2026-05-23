<?php
session_start();
require_once 'config.php';
require_once 'logger.php';

// Kiểm tra quyền Sinh viên: nếu chưa đăng nhập thì chỉ hiện thông báo yêu cầu đăng nhập
$require_login = false;
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    $require_login = true;
}

// Lấy thông tin sách
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM Books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    die("<h2 style='text-align:center; margin-top:50px; font-family:Arial;'>❌ Không tìm thấy sách!</h2>");
}

// Link đăng nhập kèm redirect về trang mượn này
$login_link = 'login.php?redirect=' . urlencode("borrow.php?id={$book_id}");

$msg = '';

// Xử lý Form Mượn sách
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($require_login) {
        $msg = "<div class='alert alert-warning py-2'><i class='bi bi-exclamation-circle'></i> Vui lòng đăng nhập với tài khoản Sinh viên để mượn sách. <a href='{$login_link}' class='alert-link'>Đăng nhập ngay</a></div>";
    } else {
        // Kiểm tra user_id có tồn tại
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            $msg = "<div class='alert alert-danger py-2'><i class='bi bi-exclamation-triangle-fill'></i> Lỗi: Không tìm thấy thông tin người dùng. Vui lòng đăng nhập lại.</div>";
        } else {
            // Validate và sanitize input
            $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
            $b_date = isset($_POST['borrow_date']) ? trim($_POST['borrow_date']) : '';
            $r_date = isset($_POST['return_date']) ? trim($_POST['return_date']) : '';
            
            // Kiểm tra input không được rỗng
            if (empty($qty) || empty($b_date) || empty($r_date)) {
                $msg = "<div class='alert alert-danger py-2'><i class='bi bi-exclamation-triangle-fill'></i> Vui lòng điền đầy đủ thông tin!</div>";
            } else {
                // Tính số ngày và phí
                $days = (strtotime($r_date) - strtotime($b_date)) / 86400;
                
                if ($days <= 0) {
                    $msg = "<div class='alert alert-danger py-2'><i class='bi bi-exclamation-triangle-fill'></i> Ngày trả phải sau ngày mượn!</div>";
                } elseif ($qty <= 0) {
                    $msg = "<div class='alert alert-danger py-2'><i class='bi bi-exclamation-triangle-fill'></i> Số lượng phải lớn hơn 0!</div>";
                } elseif ($qty > $book['quantity']) {
                    $msg = "<div class='alert alert-danger py-2'><i class='bi bi-exclamation-triangle-fill'></i> Không đủ số lượng sách trong kho!</div>";
                } else {
                    // Tính phí (2000 VNĐ/ngày/cuốn)
                    $fee = round($days * 2000 * $qty);
                    
                    try {
                        // Bắt đầu transaction
                        $pdo->beginTransaction();
                        
                        // Lưu vào DB
                        $stmt = $pdo->prepare(
                            "INSERT INTO BorrowRequests 
                            (user_id, book_id, quantity, borrow_date, return_date, total_fee) 
                            VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        
                        $result = $stmt->execute([
                            $_SESSION['user_id'], 
                            $book_id, 
                            $qty, 
                            $b_date, 
                            $r_date, 
                            $fee
                        ]);
                        
                        if ($result) {
                            $request_id = $pdo->lastInsertId();
                            
                            // Ghi log
                            writeBorrowLog(
                                "USER_ID {$_SESSION['user_id']} - USERNAME {$_SESSION['username']} yêu cầu mượn BOOK_ID {$book_id}, REQUEST_ID {$request_id}, số lượng {$qty}, từ {$b_date} đến {$r_date}, phí: {$fee} VNĐ."
                            );
                            
                            // Commit transaction
                            $pdo->commit();
                            
                            $msg = "<div class='alert alert-success py-2'><i class='bi bi-check-circle-fill'></i> Đã gửi yêu cầu thành công! Vui lòng chờ duyệt.</div>";
                        } else {
                            $pdo->rollBack();
                            $msg = "<div class='alert alert-danger py-2'><i class='bi bi-exclamation-triangle-fill'></i> Lỗi khi lưu dữ liệu. Vui lòng thử lại.</div>";
                        }
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $msg = "<div class='alert alert-danger py-2'><i class='bi bi-exclamation-triangle-fill'></i> Lỗi cơ sở dữ liệu: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi" dir="ltr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Đăng ký Mượn Sách | Thư viện Số QNU</title>
    
    <link rel="shortcut icon" href="https://qnu.edu.vn/favicon.ico" type="image/vnd.microsoft.icon" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    
    <style type="text/css">
        :root {
            --qnu-blue: #0054a6;
            --qnu-blue-dark: #003d7a;
            --qnu-gold: #ffc107;
            --qnu-bg: #f4f7f6;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        body { font-family: 'Roboto', sans-serif; background-color: var(--qnu-bg); color: #444; }

        /* HEADER */
        .qnu-header { background: white; border-top: 5px solid var(--qnu-blue); padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .university-name { color: var(--qnu-blue); font-weight: 900; font-size: 1.4rem; text-transform: uppercase; }
        .library-name { color: #ce1126; font-weight: 600; font-size: 1.1rem; }

        /* CONTENT */
        .borrow-card { background: white; border-radius: 20px; box-shadow: var(--shadow); overflow: hidden; border: none; margin-top: 30px;}
        .book-preview { background: #f8f9fa; padding: 40px 20px; text-align: center; border-right: 1px solid #eee;}
        .book-cover { width: 180px; height: 260px; object-fit: cover; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.15); margin-bottom: 20px; }
        
        .form-control { border-radius: 8px; padding: 12px 15px; }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(0, 84, 166, 0.2); border-color: var(--qnu-blue); }
        
        .fee-box { background: #fff3cd; border: 1px solid #ffe69c; border-radius: 12px; padding: 15px; text-align: center; margin-bottom: 20px;}
        .fee-amount { font-size: 28px; font-weight: 800; color: #b02a37; margin: 0;}

        footer { background: #0f172a; color: #fff; padding: 20px 0; border-top: 4px solid var(--qnu-gold); margin-top: auto; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <header class="qnu-header sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div onclick="location.href='index.php'" style="cursor: pointer; display: flex; align-items: center;">
                <img src="https://qnu.edu.vn/Resources/Images/0logoDHQNnew.jpg" alt="Logo" style="height: 60px;">
                <div class="ms-3 d-none d-md-block">
                    <h1 class="university-name m-0 fs-5">Trường Đại học Quy Nhơn</h1>
                    <div class="library-name fs-6">TRUNG TÂM SỐ VÀ HỌC LIỆU</div>
                </div>
            </div>
            
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill me-3"><i class="bi bi-house-door"></i> Về trang chủ</a>
                <?php if (isset($_SESSION['username'])): ?>
                    <span class="me-3 fw-bold text-primary small"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-success btn-sm rounded-pill ms-3"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container py-4 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <div class="borrow-card">
                    <div class="row g-0">
                        
                        <div class="col-md-5 book-preview d-flex flex-column align-items-center justify-content-center">
                            <?php $cover = !empty($book['image_url']) ? $book['image_url'] : 'img/default.png'; ?>
                            <img src="<?php echo htmlspecialchars($cover); ?>" class="book-cover" alt="Cover">
                            
                            <h5 class="fw-bold text-primary px-3"><?php echo htmlspecialchars($book['title']); ?></h5>
                            <p class="text-muted mb-2 small">Tác giả: <b><?php echo htmlspecialchars($book['author']); ?></b></p>
                            
                            <div class="d-flex gap-2 justify-content-center mt-2">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($book['category']); ?></span>
                                <span class="badge bg-success">Kho: <?php echo $book['quantity']; ?> cuốn</span>
                            </div>
                        </div>

                        <div class="col-md-7 p-4 p-md-5">
                            <h3 class="fw-bold mb-1"><i class="bi bi-journal-plus text-primary"></i> Đăng Ký Mượn Sách</h3>
                            <p class="text-muted small mb-4">Vui lòng điền thông tin để gửi yêu cầu đến Thủ thư.</p>

                            <?php echo $msg; ?>

                            <?php if ($require_login): ?>
                                <div class="alert alert-warning py-3 text-center">
                                    <i class="bi bi-person-x-fill"></i>
                                    Bạn cần <a href="<?php echo $login_link; ?>" class="alert-link">đăng nhập</a> với tài khoản Sinh viên để mượn sách.
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="<?php echo $login_link; ?>" class="btn btn-primary">Đăng nhập</a>
                                    <a href="index.php" class="btn btn-outline-secondary">Quay lại thư viện</a>
                                </div>
                            <?php else: ?>
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-bold small text-muted">Số lượng mượn (Tối đa: <?php echo $book['quantity']; ?>)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-123"></i></span>
                                            <input type="number" name="quantity" id="qty" class="form-control" min="1" max="<?php echo $book['quantity']; ?>" value="1" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small text-muted">Ngày bắt đầu mượn</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-calendar-event"></i></span>
                                            <input type="date" name="borrow_date" id="b_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small text-muted">Ngày trả dự kiến</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white"><i class="bi bi-calendar-check"></i></span>
                                            <input type="date" name="return_date" id="r_date" class="form-control" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="fee-box mt-2">
                                    <p class="mb-1 text-muted fw-bold">Tạm tính Phí mượn (2.000đ / ngày / cuốn)</p>
                                    <p class="fee-amount"><span id="total_fee">0</span> VNĐ</p>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill text-uppercase shadow-sm">
                                    Gửi Yêu Cầu Mượn Sách
                                </button>
                                
                                <div class="text-center mt-3">
                                    <a href="index.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left"></i> Hủy và quay lại thư viện</a>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </div>
    </main>

    <footer>
        <div class="container text-center">
            <p class="small mb-0 opacity-75">Hệ thống Quản lý Thư viện Số QNU - Copyright © 2026</p>
        </div>
    </footer>

    <script>
        const qty = document.getElementById('qty');
        const bDate = document.getElementById('b_date');
        const rDate = document.getElementById('r_date');
        const feeDisplay = document.getElementById('total_fee');

        if (qty && bDate && rDate && feeDisplay) {
            function calcFee() {
                if (bDate.value && rDate.value) {
                    const borrowD = new Date(bDate.value);
                    const returnD = new Date(rDate.value);
                    
                    // Set min date cho Ngày trả phải luôn sau Ngày mượn
                    rDate.min = bDate.value;

                    // Tính toán số ngày
                    const diffTime = returnD - borrowD;
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
                    
                    if (diffDays > 0) {
                        const fee = diffDays * 2000 * parseInt(qty.value || 0);
                        feeDisplay.innerText = fee.toLocaleString('vi-VN');
                    } else { 
                        feeDisplay.innerText = '0'; 
                    }
                }
            }
            
            // Gắn sự kiện lắng nghe thay đổi
            qty.addEventListener('input', calcFee);
            bDate.addEventListener('change', calcFee);
            rDate.addEventListener('change', calcFee);
            
            // Kích hoạt tính toán lần đầu khi load trang
            calcFee();
        }
    </script>
</body>
</html>
