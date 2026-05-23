<?php
session_start();
require_once 'config.php';

// 1. KIỂM TRA QUYỀN ADMIN
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit;
}

// 2. XÁC ĐỊNH TAB HIỆN TẠI
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'students';

// 2.5 XỬ LÝ CẬP NHẬT PROFILE ADMIN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_admin_profile'])) {
    $admin_email = trim($_POST['admin_email']);
    $admin_phone = trim($_POST['admin_phone']);
    $admin_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; // use session id if available

    if ($admin_id === 0) {
        $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$_SESSION['username']]);
        $admin_user = $stmt->fetch();
        $admin_id = $admin_user ? (int)$admin_user['id'] : 0;
    }

    if ($admin_id > 0) {
        $stmt = $pdo->prepare("UPDATE Users SET email = ?, phone_number = ? WHERE id = ? AND role = 'admin'");
        $stmt->execute([$admin_email, $admin_phone, $admin_id]);
    }
    header("Location: indexadmin.php?tab=profile&msg=updated"); exit;
}

// 2.6 TRUY XUẤT THÔNG TIN ADMIN
$admin_info = null;
$stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ? AND role = 'admin' LIMIT 1");
$stmt->execute([$_SESSION['username']]);
$admin_info = $stmt->fetch();
if ($admin_info) {
    $admin_info['phone_number'] = $admin_info['phone_number'] ?? $admin_info['phone'] ?? '';
}
function readBorrowLogEntries($limit = 200) {
    $filePath = __DIR__ . '/logs/borrow_log.txt';

    if (!file_exists($filePath)) {
        return [];
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!$lines) {
        return [];
    }

    // Borrow log giữ nguyên thứ tự cũ -> mới
    return array_slice($lines, -$limit);
}

function readBookLogEntries($limit = 200) {
    $filePath = __DIR__ . '/logs/book_log.txt';

    if (!file_exists($filePath)) {
        return [];
    }

    $content = trim(file_get_contents($filePath));

    if ($content === '') {
        return [];
    }

    // Tách mỗi sự kiện bắt đầu bằng timestamp
    $entries = preg_split(
        '/(?=^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\])/m',
        $content,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $entries = array_map('trim', $entries);

    // Book log đảo để xem cái mới nhất trước
    $entries = array_reverse($entries);

    return array_slice($entries, 0, $limit);
}

// 2.7 RESET MẬT KHẨU USER VỀ MẶC ĐỊNH = USERNAME
if (isset($_GET['reset_password']) && is_numeric($_GET['reset_password'])) {
    $reset_id = (int)$_GET['reset_password'];

    // Không cho admin tự reset ở đây nếu muốn tránh nhầm; vẫn cho phép nếu cần
    $stmt = $pdo->prepare("SELECT id, username, role FROM Users WHERE id = ? LIMIT 1");
    $stmt->execute([$reset_id]);
    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($targetUser) {
        // Mật khẩu mặc định = username/MSSV của tài khoản
        $defaultPassword = $targetUser['username'];
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE Users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $reset_id]);
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
    header("Location: indexadmin.php?tab=" . $tab . "&search=$search" . ($page > 1 ? "&page=$page" : "") . "&reset_msg=success");
    exit;
}

// 3. XỬ LÝ THÊM NGƯỜI DÙNG"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $fullname = trim($_POST['fullname']);
    $cohort = isset($_POST['cohort']) && $_POST['cohort'] !== '' ? (int)$_POST['cohort'] : 0;
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO Users (username, email, password, role, major_name, cohort) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword, $role, $fullname, $cohort]);
    header("Location: indexadmin.php?tab=" . $tab); exit;
}

// 4. XỬ LÝ XÓA NGƯỜI DÙNG
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $target_role = $_GET['role'] ?? 'student';
    $stmt = $pdo->prepare("DELETE FROM Users WHERE id = ? AND role = ?");
    $stmt->execute([$delete_id, $target_role]);
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
    header("Location: indexadmin.php?tab=" . $tab . "&search=$search" . ($page > 1 ? "&page=$page" : "")); exit;
}

// 5. XỬ LÝ SỬA NGƯỜI DÙNG
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_user'])) {
    $edit_id = (int)$_POST['edit_id'];
    $edit_role = $_POST['edit_role'];
    $edit_email = trim($_POST['email']);
    $edit_fullname = trim($_POST['fullname']);
    $edit_cohort = isset($_POST['cohort']) && $_POST['cohort'] !== '' ? (int)$_POST['cohort'] : 0;
    
    if ($edit_role === 'student') {
        $stmt = $pdo->prepare("UPDATE Users SET email = ?, major_name = ?, cohort = ? WHERE id = ? AND role = 'student'");
        $stmt->execute([$edit_email, $edit_fullname, $edit_cohort, $edit_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE Users SET email = ?, major_name = ? WHERE id = ? AND role = 'librarian'");
        $stmt->execute([$edit_email, $edit_fullname, $edit_id]);
    }
    
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $search = isset($_GET['search']) ? urlencode($_GET['search']) : '';
    header("Location: indexadmin.php?tab=" . $tab . "&search=$search" . ($page > 1 ? "&page=$page" : "")); exit;
}

// 6. TÌM KIẾM & PHÂN TRANG (50 dòng/trang)
$limit = 50; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = "";
$params = [];

if ($search !== '') {
    $search_sql = " AND (username LIKE ? OR email LIKE ? OR major_name LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Phân luồng query dựa theo Tab
if ($tab === 'students') {
    $role_filter = "role = 'student'";
} else {
    $role_filter = "role = 'librarian'";
}

// Đếm tổng số dữ liệu để tính trang
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE $role_filter $search_sql");
$total_stmt->execute($params);
$total_items = $total_stmt->fetchColumn();
$total_pages = ceil($total_items / $limit);

// Lấy danh sách hiển thị
$stmt = $pdo->prepare("SELECT * FROM Users WHERE $role_filter $search_sql ORDER BY username ASC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

// 7. LẤY THÔNG TIN NGƯỜI DÙNG CẦN SỬA
$edit_user = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="vi" dir="ltr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Quản trị Hệ thống | Admin QNU</title>
    
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
            --admin-dark: #1e293b;
        }

        body { font-family: 'Roboto', sans-serif; background-color: var(--qnu-bg); color: #444; overflow-x: hidden; }

        /* HEADER */
        .admin-header { background: var(--admin-dark); padding: 12px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-bottom: 3px solid var(--qnu-gold); }
        .university-name { color: white; font-weight: 900; font-size: 1.2rem; text-transform: uppercase; }
        .system-name { color: #94a3b8; font-weight: 600; font-size: 0.9rem; }
        
        /* SIDEBAR & CONTENT */
        .content-card { background: white; border-radius: 12px; box-shadow: var(--shadow); border: none; padding: 25px; margin-bottom: 25px;}
        .nav-pills .nav-link { color: #555; font-weight: 500; border-radius: 8px; padding: 12px 20px; margin-bottom: 10px; transition: 0.3s; }
        .nav-pills .nav-link:hover { background: #f1f5f9; }
        .nav-pills .nav-link.active { background-color: var(--admin-dark); color: white; }
        .log-box {
    background: #0f172a;
    color: #d1fae5;
    padding: 18px;
    border-radius: 12px;
    max-height: 650px;
    overflow: auto;
    font-size: 13px;
    line-height: 1.7;
    white-space: pre-wrap;
    border: 1px solid #1e293b;
}
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .table th { background-color: #f1f5f9; color: #334155; font-weight: 600; border-bottom: 2px solid #e2e8f0;}
        
        /* Compact Table cho 50 dòng */
        .table-sm td, .table-sm th { padding: 8px 12px; font-size: 14px; vertical-align: middle; }
        
        .pagination .page-link { color: var(--admin-dark); }
        .pagination .page-item.active .page-link { background-color: var(--admin-dark); border-color: var(--admin-dark); }
        
        footer { background: #0f172a; color: #fff; padding: 20px 0; border-top: 4px solid var(--qnu-gold); margin-top: auto; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <header class="admin-header sticky-top">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <div style="display: flex; align-items: center;">
                <img src="images/qnu_logo.png" alt="Logo thư viện" style="height: 65px;">
                <div class="ms-3 d-none d-md-block">
                    <h1 class="university-name m-0"></h1>
                    <div class="system-name">QUẢN TRỊ HỆ THỐNG THƯ VIỆN</div>
                </div>
            </div>
            
            <div class="d-flex align-items-center">
                <span class="me-3 fw-bold text-white small"><i class="bi bi-shield-lock-fill text-danger fs-5 align-middle me-1"></i> SUPER ADMIN</span>
                <a href="logout.php" class="btn btn-outline-light btn-sm rounded-pill px-3"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
            </div>
        </div>
    </header>

    <main class="container-fluid px-4 py-4 flex-grow-1">
        <div class="row">
            <div class="col-lg-2 mb-4">
                <div class="content-card p-3 sticky-top" style="top: 90px;">
                    <div class="nav flex-column nav-pills">
                        <a href="?tab=students" class="nav-link <?php echo $tab=='students'?'active':''; ?>">
                            <i class="bi bi-mortarboard-fill me-2"></i> Sinh viên
                        </a>
                        <a href="?tab=librarians" class="nav-link <?php echo $tab=='librarians'?'active':''; ?>">
                            <i class="bi bi-person-badge-fill me-2"></i> Thủ thư
                        </a>
                        <a href="?tab=logs" class="nav-link <?php echo $tab=='logs'?'active':''; ?>">
                            <i class="bi bi-journal-text me-2"></i> Nhật ký hệ thống
                        </a>
                        <hr class="text-muted">
                        <a href="?tab=profile" class="nav-link <?php echo $tab=='profile'?'active':''; ?>">
                            <i class="bi bi-person-circle me-2"></i> Tài khoản của tôi
                        </a>

                    </div>
                    <hr class="text-muted">
                    <div class="text-center">
                        <small class="text-muted d-block mb-2">Truy cập nhanh</small>
                        <a href="indexlibrarian.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-box-arrow-up-right"></i> Chế độ Thủ thư</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-10">
                
                <?php if ($tab === 'profile'): ?>
                <div class="row">
                    <div class="col-lg-6 offset-lg-3">
                        <div class="content-card">
                            <h5 class="fw-bold text-dark mb-4">
                                <i class="bi bi-person-circle text-primary"></i> Quản lý tài khoản Admin
                            </h5>
                            
                            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i> Cập nhật thông tin thành công!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <input type="hidden" name="update_admin_profile" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Tên đăng nhập</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>" disabled>
                                    <small class="text-muted">Không thể thay đổi</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($admin_info['email'] ?? ''); ?>" placeholder="Nhập email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Số điện thoại</label>
                                    <input type="tel" name="admin_phone" class="form-control" value="<?php echo htmlspecialchars($admin_info['phone_number'] ?? ''); ?>" placeholder="Nhập số điện thoại">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Vai trò</label>
                                    <input type="text" class="form-control" value="Quản trị viên (Super Admin)" disabled>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php elseif ($tab === 'logs'): ?>
<?php
    $borrowLogs = readBorrowLogEntries(200);
    $bookLogs = readBookLogEntries(200);
?>

<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold text-dark m-0">
            <i class="bi bi-journal-text text-primary"></i>
            Nhật ký hệ thống
        </h5>

        <div>
            <a href="?tab=logs" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-clockwise"></i> Làm mới
            </a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="logTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="borrow-tab" data-bs-toggle="tab" data-bs-target="#borrow-log-pane" type="button" role="tab">
                <i class="bi bi-arrow-left-right"></i> Mượn / Trả sách
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="book-tab" data-bs-toggle="tab" data-bs-target="#book-log-pane" type="button" role="tab">
                <i class="bi bi-bookshelf"></i> Quản lý sách
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="borrow-log-pane" role="tabpanel">
            <div class="alert alert-info small">
                Hiển thị tối đa 200 dòng mới nhất từ <code>logs/borrow_log.txt</code>.
            </div>

            <?php if (empty($borrowLogs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Chưa có nhật ký mượn/trả sách.
                </div>
            <?php else: ?>
                <pre class="log-box"><?php echo htmlspecialchars(implode("\n", $borrowLogs)); ?></pre>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="book-log-pane" role="tabpanel">
            <div class="alert alert-info small">
                Hiển thị tối đa 200 dòng mới nhất từ <code>logs/book_log.txt</code>.
            </div>

            <?php if (empty($bookLogs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    Chưa có nhật ký thêm/sửa/xóa sách.
                </div>
            <?php else: ?>
                <pre class="log-box"><?php echo htmlspecialchars(implode("\n", $bookLogs)); ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>
                <?php else: ?>
                <div class="row">
                    <div class="col-lg-3 mb-4">
                        <div class="content-card">
                            <h5 class="fw-bold text-dark mb-3">
                                <?php if($edit_user): ?>
                                    <i class="bi bi-pencil-square text-warning"></i> Cập nhật <?php echo ($tab == 'students') ? 'Sinh viên' : 'Thủ thư'; ?>
                                <?php else: ?>
                                    <i class="bi bi-plus-circle text-success"></i> Thêm <?php echo ($tab == 'students') ? 'Sinh viên' : 'Thủ thư'; ?> mới
                                <?php endif; ?>
                            </h5>
                            
                            <form method="POST">
                                <?php if($edit_user): ?>
                                    <input type="hidden" name="edit_user" value="1">
                                    <input type="hidden" name="edit_id" value="<?php echo $edit_user['id']; ?>">
                                    <input type="hidden" name="edit_role" value="<?php echo ($tab == 'students') ? 'student' : 'librarian'; ?>">
                                <?php else: ?>
                                    <input type="hidden" name="add_user" value="1">
                                    <input type="hidden" name="role" value="<?php echo ($tab == 'students') ? 'student' : 'librarian'; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold mb-1">Mã Định Danh / Tài khoản</label>
                                    <input type="text" name="username" class="form-control form-control-sm bg-light" 
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" 
                                           placeholder="Nhập mã/tên đăng nhập" 
                                           <?php echo $edit_user ? 'readonly' : 'required'; ?>>
                                </div>
                                
                                <?php if(!$edit_user): ?>
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold mb-1">Mật khẩu</label>
                                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Nhập mật khẩu" required>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted fw-bold mb-1"><?php echo ($tab == 'students') ? 'Khoa / Ngành' : 'Họ và tên Thủ thư'; ?></label>
                                    <input type="text" name="fullname" class="form-control form-control-sm" 
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['major_name']) : ''; ?>" 
                                           placeholder="<?php echo ($tab == 'students') ? 'VD: Công nghệ thông tin' : 'Nhập họ tên'; ?>" required>
                                </div>
                                
                                <?php if($tab == 'students'): ?>
                                <div class="mb-4">
                                    <label class="form-label small text-muted fw-bold mb-1">Khóa học</label>
                                    <input type="number" name="cohort" class="form-control form-control-sm" 
                                           value="<?php echo $edit_user ? $edit_user['cohort'] : ''; ?>" 
                                           placeholder="VD: 2024" min="2010" max="2050" required>
                                </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn <?php echo $edit_user ? 'btn-warning text-dark' : 'btn-dark'; ?> w-100 btn-sm fw-bold py-2">
                                    <?php echo $edit_user ? 'LƯU THAY ĐỔI' : 'TẠO TÀI KHOẢN'; ?>
                                </button>
                                
                                <?php if($edit_user): ?>
                                    <a href="?tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" class="btn btn-secondary w-100 btn-sm mt-2">Hủy</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="content-card">
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                <h5 class="fw-bold text-dark m-0">
                                    <i class="bi <?php echo ($tab == 'students') ? 'bi-people-fill' : 'bi-person-badge-fill'; ?> text-primary"></i> 
                                    Danh sách <?php echo ($tab == 'students') ? 'Sinh viên' : 'Thủ thư'; ?> (<?php echo $total_items; ?>)
                                </h5>
                                
                                <form method="GET" class="d-flex" style="width: 350px;">
                                    <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                                    <div class="input-group input-group-sm shadow-sm">
                                        <input type="text" name="search" class="form-control border-end-0" placeholder="Tìm theo Mã, Tên, Ngành..." value="<?php echo htmlspecialchars($search); ?>">
                                        <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
                                        <?php if($search): ?>
                                            <a href="?tab=<?php echo $tab; ?>" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>

                            <?php if(isset($_GET['reset_msg']) && $_GET['reset_msg'] === 'success'): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    Đã reset mật khẩu thành công! Mật khẩu mặc định là tên đăng nhập/MSSV của tài khoản.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered align-middle mb-0">
                                    <thead>
                                        <tr class="text-center">
                                            <th width="20%"><?php echo ($tab == 'students') ? 'Mã SV' : 'Tên Đăng Nhập'; ?></th>
                                            <th width="35%"><?php echo ($tab == 'students') ? 'Khoa / Ngành' : 'Họ và tên'; ?></th>
                                            <?php if($tab == 'students'): ?><th width="10%">Khóa</th><?php endif; ?>
                                            <th width="15%">Trạng thái</th>
                                            <th width="20%">Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($users) > 0): ?>
                                            <?php foreach($users as $u): ?>
                                            <tr>
                                                <td class="fw-bold text-primary text-center"><?php echo htmlspecialchars($u['username']); ?></td>
                                                <td><?php echo htmlspecialchars($u['major_name']); ?></td>
                                                
                                                <?php if($tab == 'students'): ?>
                                                    <td class="text-center"><span class="badge bg-secondary">K<?php echo htmlspecialchars($u['cohort']); ?></span></td>
                                                <?php endif; ?>
                                                
                                                <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-check-circle-fill"></i> Hoạt động</span></td>
                                                <td class="text-center">
                                                    <a href="?tab=<?php echo $tab; ?>&edit=<?php echo $u['id']; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Sửa"><i class="bi bi-pencil-square"></i></a>
                                                    <a href="?tab=<?php echo $tab; ?>&reset_password=<?php echo $u['id']; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-outline-warning py-0 px-2" onclick="return confirm('Reset mật khẩu tài khoản <?php echo htmlspecialchars($u['username']); ?> về mặc định là tên đăng nhập/MSSV?');" title="Reset mật khẩu"><i class="bi bi-arrow-clockwise"></i></a>
                                                    <a href="?tab=<?php echo $tab; ?>&delete=<?php echo $u['id']; ?>&role=<?php echo ($tab == 'students') ? 'student' : 'librarian'; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="return confirm('Cảnh báo: Việc xóa người dùng sẽ xóa toàn bộ lịch sử mượn sách của họ. Tiếp tục?')" title="Xóa"><i class="bi bi-trash-fill"></i></a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i> Không tìm thấy người dùng nào.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination pagination-sm justify-content-center mb-0">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>">Trước</a>
                                    </li>
                                    
                                    <?php 
                                        $start = max(1, $page - 2);
                                        $end = min($total_pages, $page + 2);
                                        if($start > 1) { echo '<li class="page-item"><a class="page-link" href="?tab='.$tab.'&search='.urlencode($search).'&page=1">1</a></li>'; if($start > 2) echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>'; }
                                        
                                        for($i = $start; $i <= $end; $i++): 
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php 
                                        endfor; 
                                        if($end < $total_pages) { if($end < $total_pages - 1) echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>'; echo '<li class="page-item"><a class="page-link" href="?tab='.$tab.'&search='.urlencode($search).'&page='.$total_pages.'">'.$total_pages.'</a></li>'; }
                                    ?>

                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?tab=<?php echo $tab; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>">Sau</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </main>

    <footer>
        <div class="container text-center">
            <p class="small mb-0 opacity-75">Hệ thống Quản lý Thư viện Số QNU - Module Administrator - Copyright © 2026</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>