<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }

$msg = '';

// --- XỬ LÝ CẬP NHẬT HỒ SƠ NGƯỜI DÙNG ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Cập nhật full_name thay vì major_name
    $stmt = $pdo->prepare("UPDATE Users SET full_name = ?, email = ?, phone_number = ? WHERE username = ?");
    $stmt->execute([$fullname, $email, $phone, $_SESSION['username']]);
    $msg = "<div class='alert alert-success alert-dismissible fade show' role='alert'><i class='bi bi-check-circle-fill'></i> Cập nhật thông tin thành công!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

// --- XỬ LÝ ĐỔI MẬT KHẨU ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_pass'])) {
    $current_pass = $_POST['current_pass'];
    $new_pass = $_POST['new_pass'];
    $confirm_pass = $_POST['confirm_pass'];

    // Kiểm tra mật khẩu cũ
    $stmt = $pdo->prepare("SELECT password FROM Users WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch();

    if (!password_verify($current_pass, $user['password'])) {
        $msg = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Mật khẩu hiện tại không đúng!</div>";
    } elseif ($new_pass !== $confirm_pass) {
        $msg = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Mật khẩu mới không khớp nhau!</div>";
    } else {
        // Cập nhật mật khẩu mới với hash an toàn
        $hashedNewPass = password_hash($new_pass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE Users SET password = ? WHERE username = ?");
        $upd->execute([$hashedNewPass, $_SESSION['username']]);
        $msg = "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> Đổi mật khẩu thành công!</div>";
    }
}

// --- TRUY XUẤT THÔNG TIN NGƯỜI DÙNG ---
$stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
$stmt->execute([$_SESSION['username']]);
$user_info = $stmt->fetch();
if ($user_info) {
    if (!isset($user_info['phone_number']) && isset($user_info['phone'])) {
        $user_info['phone_number'] = $user_info['phone'];
    }
    if (!isset($user_info['phone_number']) || $user_info['phone_number'] === null) {
        $user_info['phone_number'] = '';
    }
}

// --- TRUY XUẤT LỊCH SỬ MƯỢN SÁCH (chỉ pending và approved) ---
$stmt = $pdo->prepare("SELECT br.*, b.title FROM BorrowRequests br JOIN Books b ON br.book_id = b.id WHERE br.user_id = ? AND br.status IN ('pending', 'approved') ORDER BY br.id DESC");
$stmt->execute([$_SESSION['user_id']]);
$requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi" dir="ltr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Hồ sơ Cá nhân | Thư viện Số QNU</title>
    
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
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        body { font-family: 'Roboto', sans-serif; background-color: var(--qnu-bg); color: #444; overflow-x: hidden; }

        /* HEADER & FOOTER ĐỒNG BỘ VỚI INDEX */
        .qnu-header { background: white; border-top: 5px solid var(--qnu-blue); padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .university-name { color: var(--qnu-blue); font-weight: 900; font-size: 1.4rem; text-transform: uppercase; }
        .library-name { color: #ce1126; font-weight: 600; font-size: 1.1rem; }
        .top-marquee { background: var(--qnu-blue-dark); color: white; padding: 7px 0; font-size: 13px; border-bottom: 3px solid var(--qnu-gold); }

        /* PROFILE SPECIFIC */
        .profile-header-bg { background: linear-gradient(135deg, var(--qnu-blue) 0%, #007bff 100%); padding: 40px 0; color: white; margin-bottom: -50px; }
        .content-card { background: white; border-radius: 15px; box-shadow: var(--shadow); border: none; padding: 30px; }
        
        .nav-pills .nav-link { color: #555; font-weight: 500; border-radius: 8px; padding: 12px 20px; margin-bottom: 10px; transition: 0.3s; }
        .nav-pills .nav-link:hover { background: #f8f9fa; }
        .nav-pills .nav-link.active, .nav-pills .show>.nav-link { background-color: var(--qnu-blue); color: white; }
        
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .badge { font-weight: 500; padding: 6px 10px; }

        footer { background: #0f172a; color: #fff; padding: 40px 0 0 0; border-top: 4px solid var(--qnu-gold); margin-top: 50px; }
    </style>
</head>
<body>

    <div class="top-marquee">
        <div class="container"><marquee behavior="scroll" direction="left">Cổng thông tin mượn trả và quản lý tài khoản cá nhân.</marquee></div>
    </div>

    <header class="qnu-header sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div onclick="location.href='index.php'" style="cursor: pointer; display: flex; align-items: center;">
                <img src="images/qnu_logo.png" alt="Logo thư viện" style="height: 65px;">
                <div class="ms-3 d-none d-md-block">
                    <h1 class="university-name m-0">Thư Viện Số</h1>
                    <div class="library-name">TRUNG TÂM SỐ VÀ HỌC LIỆU</div>
                </div>
            </div>
            
            <div class="d-flex align-items-center">
                <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill me-3"><i class="bi bi-house-door-fill"></i> Trang chủ</a>
                <span class="me-3 fw-bold text-primary small"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['username']; ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm rounded-pill">Thoát</a>
            </div>
        </div>
    </header>

    <div class="profile-header-bg text-center">
        <div class="container">
            <h2 class="fw-bold mb-2">HỒ SƠ CÁ NHÂN & LỊCH SỬ MƯỢN TRẢ</h2>
            <p class="opacity-75">Quản lý các ấn phẩm đã mượn và bảo mật tài khoản của bạn</p>
        </div>
    </div>

    <main class="container position-relative" style="z-index: 10;">
        <div class="row">
            <div class="col-lg-3 mb-4">
                <div class="content-card p-3">
                    <div class="text-center mb-4 mt-2">
                        <div class="bg-light text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 35px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <h5 class="fw-bold text-dark m-0"><?php echo $_SESSION['username']; ?></h5>
                        <small class="text-muted">Sinh viên QNU</small>
                    </div>
                    <hr class="text-muted opacity-25">
                    
                    <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <button class="nav-link active text-start" id="v-pills-profile-tab" data-bs-toggle="pill" data-bs-target="#v-pills-profile" type="button" role="tab">
                            <i class="bi bi-person-circle me-2"></i> Thông tin cá nhân
                        </button>
                        <button class="nav-link text-start" id="v-pills-history-tab" data-bs-toggle="pill" data-bs-target="#v-pills-history" type="button" role="tab">
                            <i class="bi bi-journal-text me-2"></i> Lịch sử mượn sách
                        </button>
                        <button class="nav-link text-start" id="v-pills-security-tab" data-bs-toggle="pill" data-bs-target="#v-pills-security" type="button" role="tab">
                            <i class="bi bi-shield-lock me-2"></i> Đổi mật khẩu
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="content-card">
                    <div class="tab-content" id="v-pills-tabContent">
                        
                        <div class="tab-pane fade show active" id="v-pills-profile" role="tabpanel">
                            <h4 class="fw-bold text-primary mb-4"><i class="bi bi-person-vcard"></i> Thông tin cá nhân</h4>
                            
                            <div class="row">
                                <div class="col-md-8 col-lg-7">
                                    <?php echo $msg; ?>
                                    <form method="POST" action="profile.php">
                                        <input type="hidden" name="update_profile" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-muted fw-bold small">Tên đăng nhập</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_info['username']); ?>" disabled>
                                            <small class="text-muted">Không thể thay đổi</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-muted fw-bold small">Họ và tên</label>
                                            <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user_info['full_name'] ?? ''); ?>" placeholder="Nhập họ và tên" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label text-muted fw-bold small">Email</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" placeholder="Nhập email" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label text-muted fw-bold small">Số điện thoại</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user_info['phone_number'] ?? ''); ?>" placeholder="Nhập số điện thoại">
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                            <i class="bi bi-check-circle me-2"></i> LƯU THAY ĐỔI
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="v-pills-history" role="tabpanel">
                            <h4 class="fw-bold text-primary mb-4"><i class="bi bi-clock-history"></i> Tình trạng mượn trả</h4>
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle border">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tên sách</th>
                                            <th class="text-center">SL</th>
                                            <th>Thời hạn</th>
                                            <th>Phí mượn</th>
                                            <th class="text-center">Trạng thái</th>
                                            <th>Ghi chú</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($requests) > 0): ?>
                                            <?php foreach($requests as $r): ?>
                                            <tr>
                                                <td class="fw-bold text-dark" style="max-width: 250px;">
                                                    <div class="text-truncate" title="<?php echo htmlspecialchars($r['title']); ?>">
                                                        <?php echo htmlspecialchars($r['title']); ?>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?php echo $r['quantity']; ?></td>
                                                <td style="font-size: 14px;">
                                                    <span class="text-muted">Từ:</span> <?php echo date('d/m/Y', strtotime($r['borrow_date'])); ?><br>
                                                    <span class="text-muted">Đến:</span> <strong class="text-danger"><?php echo date('d/m/Y', strtotime($r['return_date'])); ?></strong>
                                                </td>
                                                <td class="fw-bold text-primary"><?php echo number_format($r['total_fee']); ?>đ</td>
                                                <td class="text-center">
                                                    <?php 
                                                        if($r['status'] == 'pending') echo "<span class='badge bg-warning text-dark'>⏳ Chờ duyệt</span>";
                                                        if($r['status'] == 'approved') echo "<span class='badge bg-success'>📖 Đang mượn</span>";
                                                        if($r['status'] == 'rejected') echo "<span class='badge bg-danger'>❌ Bị từ chối</span>";
                                                        if($r['status'] == 'returned') echo "<span class='badge bg-secondary'>✅ Đã trả</span>";
                                                    ?>
                                                </td>
                                                <td style="font-size: 13px;">
                                                    <?php 
                                                        if($r['status'] == 'rejected') echo "<span class='text-danger'>" . htmlspecialchars($r['reject_reason']) . "</span>"; 
                                                        elseif($r['late_fee'] > 0) echo "<span class='text-danger fw-bold'>Phạt: " . number_format($r['late_fee']) . "đ</span>";
                                                        else echo "<span class='text-muted'>-</span>";
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">
                                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i> Bạn chưa mượn cuốn sách nào.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="v-pills-security" role="tabpanel">
                            <h4 class="fw-bold text-primary mb-4"><i class="bi bi-key"></i> Đổi mật khẩu bảo mật</h4>
                            
                            <div class="row">
                                <div class="col-md-8 col-lg-6">
                                    <?php echo $msg; ?>
                                    <form method="POST" action="profile.php">
                                        <div class="mb-3">
                                            <label class="form-label text-muted fw-bold small">Mật khẩu hiện tại</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-shield-lock"></i></span>
                                                <input type="password" name="current_pass" class="form-control" placeholder="Nhập mật khẩu cũ..." required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted fw-bold small">Mật khẩu mới</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-key-fill"></i></span>
                                                <input type="password" name="new_pass" class="form-control" placeholder="Tạo mật khẩu mới..." required>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label text-muted fw-bold small">Xác nhận mật khẩu mới</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-check-circle"></i></span>
                                                <input type="password" name="confirm_pass" class="form-control" placeholder="Nhập lại mật khẩu mới..." required>
                                            </div>
                                        </div>
                                        <button type="submit" name="change_pass" class="btn btn-primary rounded-pill px-4 fw-bold">
                                            CẬP NHẬT MẬT KHẨU
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container text-center pb-4">
            <h6 style="font-weight: 700; letter-spacing: 1px; margin-bottom: 15px; color: var(--qnu-gold);">TRUNG TÂM SỐ VÀ HỌC LIỆU</h6>
            <p class="small text-muted mb-0">170 An Dương Vương, Nguyễn Văn Cừ, Thành phố Qui Nhơn, Bình Định</p>
        </div>
        <div style="background: #000; text-align: center; padding: 15px 0; font-size: 12px; color: #666;">
            Copyright © 2026 TRUNG TÂM SỐ VÀ HỌC LIỆU
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // JS nhỏ để tự động mở đúng Tab nếu có thông báo
        <?php if($msg != ''): ?>
            // Kiểm xem là cập nhật profile hay đổi pass để show tab tương ứng
            if(<?php echo isset($_POST['update_profile']) ? 'true' : 'false'; ?>) {
                var triggerEl = document.querySelector('#v-pills-profile-tab');
            } else {
                var triggerEl = document.querySelector('#v-pills-security-tab');
            }
            var tab = new bootstrap.Tab(triggerEl);
            tab.show();
        <?php endif; ?>
    </script>
</body>
</html>
