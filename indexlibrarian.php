<?php
session_start();
require_once 'config.php';
require_once 'email_helper.php'; // ← GỬI EMAIL KHI DUYỆT/TỪ CHỐI

// ── UPLOAD ẢNH BÌA ──────────────────────────────────────────
function uploadBookImage($inputName, $oldImage = null) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) return $oldImage;
    $uploadDir = 'images/books/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileTmp  = $_FILES[$inputName]['tmp_name'];
    if (getimagesize($fileTmp) === false) return $oldImage;
    $fileName = $_FILES[$inputName]['name'];
    $fileSize = $_FILES[$inputName]['size'];
    $allowedExt = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt) || $fileSize > 5*1024*1024) return $oldImage;
    $newFileName = 'book_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $targetPath  = $uploadDir . $newFileName;
    return move_uploaded_file($fileTmp, $targetPath) ? $targetPath : $oldImage;
}

// ── KIỂM TRA QUYỀN ──────────────────────────────────────────
if (!isset($_SESSION['username']) || ($_SESSION['role'] !== 'librarian' && $_SESSION['role'] !== 'admin')) {
    header("Location: index.php"); exit;
}

$tab = $_GET['tab'] ?? 'books';

// ── CẬP NHẬT THÔNG TIN CÁ NHÂN ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $pdo->prepare("UPDATE Users SET major_name=?, email=?, phone_number=? WHERE username=?")
        ->execute([trim($_POST['fullname']), trim($_POST['email']), trim($_POST['phone']), $_SESSION['username']]);
    $_GET['profile_msg'] = 'updated';
}

// ── THÔNG TIN THỦ THƯ ────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM Users WHERE username=?");
$stmt->execute([$_SESSION['username']]);
$librarian_info = $stmt->fetch();
if ($librarian_info) {
    $librarian_info['phone_number'] = $librarian_info['phone_number'] ?? $librarian_info['phone'] ?? '';
}

// ── ĐỔI MẬT KHẨU ─────────────────────────────────────────────
$change_msg = '';
if ($tab === 'account' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_password'])) {
    $old = $_POST['old_password']; $new = $_POST['new_password']; $confirm = $_POST['confirm_password'];
    $stmt = $pdo->prepare("SELECT password FROM Users WHERE username=?");
    $stmt->execute([$_SESSION['username']]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($old, $user['password']) && $old !== $user['password']) {
        $change_msg = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Mật khẩu cũ không đúng!</div>";
    } elseif ($new !== $confirm) {
        $change_msg = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Mật khẩu mới không khớp!</div>";
    } elseif (strlen($new) < 4) {
        $change_msg = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Mật khẩu mới phải từ 4 ký tự.</div>";
    } else {
        $pdo->prepare("UPDATE Users SET password=? WHERE username=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['username']]);
        $change_msg = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Đổi mật khẩu thành công!</div>";
    }
}

// ── QUẢN LÝ SÁCH (THÊM / SỬA / XÓA) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_action'])) {
    if ($_POST['book_action'] == 'add') {
        $image_url = uploadBookImage('cover_image');
        $pdo->prepare("INSERT INTO Books (title,author,category,quantity,image_url) VALUES (?,?,?,?,?)")
            ->execute([trim($_POST['title']),trim($_POST['author']),trim($_POST['category']),(int)$_POST['quantity'],$image_url]);
    } elseif ($_POST['book_action'] == 'edit') {
        $b_id = (int)$_POST['book_id'];
        $s = $pdo->prepare("SELECT image_url FROM Books WHERE id=?"); $s->execute([$b_id]);
        $old = $s->fetch(); $image_url = uploadBookImage('cover_image', $old['image_url'] ?? null);
        $pdo->prepare("UPDATE Books SET title=?,author=?,category=?,quantity=?,image_url=? WHERE id=?")
            ->execute([trim($_POST['title']),trim($_POST['author']),trim($_POST['category']),(int)$_POST['quantity'],$image_url,$b_id]);
    } elseif ($_POST['book_action'] == 'delete') {
        $pdo->prepare("DELETE FROM Books WHERE id=?")->execute([$_POST['book_id']]);
    }
    header("Location: indexlibrarian.php?tab=books"); exit;
}

// ── DUYỆT / TỪ CHỐI / TRẢ SÁCH + GỬI EMAIL ─────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['req_action'])) {
    $req_id = (int)$_POST['req_id'];
    $action = $_POST['req_action'];

    $stmt = $pdo->prepare("SELECT br.*, b.title AS book_title, b.id AS book_id_val,
                                   u.email AS u_email, u.username AS u_name
                            FROM BorrowRequests br
                            JOIN Books b ON br.book_id = b.id
                            JOIN Users u ON br.user_id = u.id OR br.username = u.username
                            WHERE br.id = ? LIMIT 1");
    $stmt->execute([$req_id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback nếu JOIN user_id không có
    if (!$req) {
        $stmt = $pdo->prepare("SELECT * FROM BorrowRequests WHERE id=?");
        $stmt->execute([$req_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── DUYỆT ──────────────────────────────────────────────
    if ($action == 'approve' && $req && $req['status'] == 'pending') {
        $book_id = $req['book_id'];
        $s = $pdo->prepare("SELECT quantity FROM Books WHERE id=?"); $s->execute([$book_id]);
        $bookQty = (int)$s->fetchColumn();

        if ($bookQty >= (int)$req['quantity']) {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE BorrowRequests SET status='approved' WHERE id=?")->execute([$req_id]);
            $pdo->prepare("UPDATE Books SET quantity=quantity-? WHERE id=?")->execute([$req['quantity'], $book_id]);
            $pdo->commit();

            // ✉️ Gửi email xác nhận duyệt
            $email = $req['u_email'] ?? '';
            $uname = $req['u_name'] ?? $req['username'] ?? '';
            if (!$email) {
                // Lấy email từ username nếu JOIN chưa có
                $us = $pdo->prepare("SELECT email, username FROM Users WHERE username=? OR id=? LIMIT 1");
                $us->execute([$req['username'] ?? '', $req['user_id'] ?? 0]);
                $urow = $us->fetch(PDO::FETCH_ASSOC);
                $email = $urow['email'] ?? ''; $uname = $urow['username'] ?? $uname;
            }
            if ($email) {
                $bookTitle = $req['book_title'] ?? $req['title'] ?? '';
                sendApproveNotification(
                    $email, $uname,
                    [['title' => $bookTitle, 'quantity' => $req['quantity']]],
                    $req['borrow_date'], $req['return_date']
                );
            }
        }
    }

    // ── TỪ CHỐI ────────────────────────────────────────────
    elseif ($action == 'reject' && $req && $req['status'] == 'pending') {
        $reason = trim($_POST['reject_reason'] ?? 'Không có lý do cụ thể');
        $pdo->prepare("UPDATE BorrowRequests SET status='rejected', reject_reason=? WHERE id=?")
            ->execute([$reason, $req_id]);

        // ✉️ Gửi email thông báo từ chối
        $email = $req['u_email'] ?? '';
        $uname = $req['u_name'] ?? $req['username'] ?? '';
        if (!$email) {
            $us = $pdo->prepare("SELECT email, username FROM Users WHERE username=? OR id=? LIMIT 1");
            $us->execute([$req['username'] ?? '', $req['user_id'] ?? 0]);
            $urow = $us->fetch(PDO::FETCH_ASSOC);
            $email = $urow['email'] ?? ''; $uname = $urow['username'] ?? $uname;
        }
        if ($email) {
            $bookTitle = $req['book_title'] ?? $req['title'] ?? 'Sách đã chọn';
            sendRejectNotification($email, $uname, $bookTitle, $reason);
        }
    }

    // ── THU HỒI / TRẢ SÁCH ─────────────────────────────────
    elseif ($action == 'return' && $req && $req['status'] == 'approved') {
        $today    = date('Y-m-d');
        $late_fee = 0;
        $diff     = (strtotime($today) - strtotime($req['return_date'])) / 86400;
        if ($diff > 0) $late_fee = $diff * 10000 * (int)$req['quantity'];

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE BorrowRequests SET status='returned', actual_return_date=?, late_fee=? WHERE id=?")
            ->execute([$today, $late_fee, $req_id]);
        $pdo->prepare("UPDATE Books SET quantity=quantity+? WHERE id=?")
            ->execute([$req['quantity'], $req['book_id'] ?? $req['book_id_val'] ?? 0]);
        $pdo->commit();
    }

    header("Location: indexlibrarian.php?tab=requests"); exit;
}

// ── LẤY SÁCH ĐỂ SỬA ─────────────────────────────────────────
$edit_book = null;
if (isset($_GET['edit_book'])) {
    $stmt = $pdo->prepare("SELECT * FROM Books WHERE id=?");
    $stmt->execute([$_GET['edit_book']]);
    $edit_book = $stmt->fetch();
}

// ── PHÂN TRANG & TÌM KIẾM SÁCH ──────────────────────────────
$limit  = 32;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page-1)*$limit;
$search = trim($_GET['search'] ?? '');
$search_sql = ''; $params = [];
if ($search !== '') {
    $search_sql = "WHERE title LIKE ? OR author LIKE ? OR category LIKE ?";
    $params = ["%$search%","%$search%","%$search%"];
}
$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM Books $search_sql");
$stmt_total->execute($params);
$total_books = $stmt_total->fetchColumn();
$total_pages = ceil($total_books/$limit);

$stmt_books = $pdo->prepare("SELECT * FROM Books $search_sql ORDER BY id DESC LIMIT $limit OFFSET $offset");
$stmt_books->execute($params);
$books = $stmt_books->fetchAll();

// ── YÊU CẦU MƯỢN ─────────────────────────────────────────────
// Hỗ trợ cả cột user_id lẫn username (schema cũ/mới)
try {
    $reqs = $pdo->query("
        SELECT br.*, b.title,
               u.phone_number AS phone, u.email, u.username AS u_name
        FROM BorrowRequests br
        JOIN Books b ON br.book_id = b.id
        LEFT JOIN Users u ON br.user_id = u.id
        ORDER BY FIELD(br.status,'pending','approved','rejected','returned'), br.id DESC
    ")->fetchAll();
} catch(Exception $e) {
    // Fallback schema cũ
    $reqs = $pdo->query("
        SELECT br.*, b.title, u.phone_number AS phone, u.email
        FROM BorrowRequests br
        JOIN Books b ON br.book_id = b.id
        JOIN Users u ON br.username = u.username
        ORDER BY br.status ASC, br.id DESC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi" dir="ltr">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>
<title>Quản lý Thư viện | Thủ thư QNU</title>
<link rel="shortcut icon" href="https://qnu.edu.vn/favicon.ico"/>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
<style>
:root{--qnu-blue:#0054a6;--qnu-blue-dark:#003d7a;--qnu-gold:#ffc107;--qnu-bg:#f4f7f6;--shadow:0 4px 20px rgba(0,0,0,0.08);}
body{font-family:'Roboto',sans-serif;background:var(--qnu-bg);color:#444;overflow-x:hidden;}
.qnu-header{background:white;border-top:5px solid var(--qnu-blue);padding:15px 0;box-shadow:0 2px 10px rgba(0,0,0,.1);}
.university-name{color:var(--qnu-blue);font-weight:900;font-size:1.4rem;text-transform:uppercase;}
.library-name{color:#ce1126;font-weight:600;font-size:1.1rem;}
.content-card{background:white;border-radius:12px;box-shadow:var(--shadow);border:none;padding:25px;margin-bottom:25px;}
.nav-pills .nav-link{color:#555;font-weight:500;border-radius:8px;padding:12px 20px;margin-bottom:10px;transition:.3s;}
.nav-pills .nav-link:hover{background:#f8f9fa;}
.nav-pills .nav-link.active{background-color:var(--qnu-blue);color:white;}
.table-hover tbody tr:hover{background:#f8f9fa;}
.table th{background:#f8f9fa;color:#333;font-weight:600;}
.pagination .page-link{color:var(--qnu-blue);}
.pagination .page-item.active .page-link{background:var(--qnu-blue);border-color:var(--qnu-blue);}
footer{background:#0f172a;color:#fff;padding:30px 0 0;border-top:4px solid var(--qnu-gold);margin-top:auto;}
/* Email badge */
.email-sent{font-size:10px;background:#d1fae5;color:#065f46;border-radius:9px;padding:1px 6px;display:inline-block;}
</style>
</head>
<body class="d-flex flex-column min-vh-100">

<header class="qnu-header sticky-top">
  <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
    <div style="display:flex;align-items:center;">
      <img src="https://qnu.edu.vn/Resources/Images/0logoDHQNnew.jpg" alt="Logo" style="height:60px;">
      <div class="ms-3 d-none d-md-block">
        <h1 class="university-name m-0 fs-5">HỆ THỐNG QUẢN LÝ THƯ VIỆN</h1>
        <div class="library-name fs-6">DÀNH CHO THỦ THƯ</div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="fw-bold text-primary small"><i class="bi bi-person-badge-fill fs-5 align-middle me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?></span>
      <a href="logout.php" class="btn btn-danger btn-sm rounded-pill px-3"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
    </div>
  </div>
</header>

<main class="container-fluid px-4 py-4 flex-grow-1">
  <div class="row">

    <!-- SIDEBAR -->
    <div class="col-lg-2 mb-4">
      <div class="content-card p-3 sticky-top" style="top:100px;">
        <div class="nav flex-column nav-pills">
          <a href="?tab=books"    class="nav-link <?php echo $tab=='books'?'active':''; ?>"><i class="bi bi-journal-album me-2"></i>Quản lý sách</a>
          <a href="?tab=requests" class="nav-link <?php echo $tab=='requests'?'active':''; ?>"><i class="bi bi-clipboard-check me-2"></i>Duyệt Mượn / Trả</a>
          <hr class="text-muted opacity-25">
          <a href="?tab=profile"  class="nav-link <?php echo $tab=='profile'?'active':''; ?>"><i class="bi bi-person-circle me-2"></i>Tài khoản cá nhân</a>
          <a href="?tab=account"  class="nav-link <?php echo $tab=='account'?'active':''; ?>"><i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu</a>
        </div>
      </div>
    </div>

    <div class="col-lg-10">

      <!-- ── THÔNG TIN CÁ NHÂN ── -->
      <?php if($tab=='profile'): ?>
      <div class="content-card mx-auto" style="max-width:650px;">
        <h4 class="fw-bold text-primary mb-4 text-center"><i class="bi bi-person-vcard"></i> Thông Tin Cá Nhân Thủ Thư</h4>
        <?php if(isset($_GET['profile_msg']) && $_GET['profile_msg']==='updated'): ?>
          <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle-fill me-2"></i>Cập nhật thành công!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <form method="POST" action="?tab=profile">
          <input type="hidden" name="update_profile" value="1">
          <div class="mb-3"><label class="form-label fw-bold small text-muted">Tên đăng nhập</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($librarian_info['username']); ?>" disabled>
            <small class="text-muted">Không thể thay đổi</small></div>
          <div class="mb-3"><label class="form-label fw-bold small text-muted">Họ và tên</label>
            <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($librarian_info['major_name']??''); ?>" required></div>
          <div class="mb-3"><label class="form-label fw-bold small text-muted">Email</label>
            <div class="input-group"><span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($librarian_info['email']??''); ?>" required></div></div>
          <div class="mb-4"><label class="form-label fw-bold small text-muted">Số điện thoại</label>
            <div class="input-group"><span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
              <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($librarian_info['phone_number']??''); ?>"></div></div>
          <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-check-circle me-2"></i>LƯU THAY ĐỔI</button>
        </form>
      </div>
      <?php endif; ?>

      <!-- ── ĐỔI MẬT KHẨU ── -->
      <?php if($tab=='account'): ?>
      <div class="content-card mx-auto" style="max-width:600px;">
        <h4 class="fw-bold text-primary mb-4 text-center"><i class="bi bi-key-fill"></i> Đổi Mật Khẩu Thủ Thư</h4>
        <?php echo $change_msg; ?>
        <form method="POST" action="?tab=account">
          <div class="mb-3"><label class="form-label fw-bold small text-muted">Mật khẩu cũ</label>
            <input type="password" name="old_password" class="form-control" required></div>
          <div class="mb-3"><label class="form-label fw-bold small text-muted">Mật khẩu mới</label>
            <input type="password" name="new_password" class="form-control" required></div>
          <div class="mb-4"><label class="form-label fw-bold small text-muted">Nhập lại mật khẩu mới</label>
            <input type="password" name="confirm_password" class="form-control" required></div>
          <button type="submit" class="btn btn-primary w-100 fw-bold py-2"><i class="bi bi-save"></i> CẬP NHẬT</button>
        </form>
      </div>
      <?php endif; ?>

      <!-- ── QUẢN LÝ SÁCH ── -->
      <?php if($tab=='books'): ?>
      <div class="row">
        <div class="col-lg-3 mb-4">
          <div class="content-card">
            <h5 class="fw-bold text-primary mb-3">
              <?php echo $edit_book ? '<i class="bi bi-pencil-square text-warning"></i> Sửa Thông Tin' : '<i class="bi bi-plus-circle text-success"></i> Thêm Sách Mới'; ?>
            </h5>
            <form method="POST" action="?tab=books" enctype="multipart/form-data">
              <?php if($edit_book): ?>
                <input type="hidden" name="book_action" value="edit">
                <input type="hidden" name="book_id" value="<?php echo $edit_book['id']; ?>">
              <?php else: ?>
                <input type="hidden" name="book_action" value="add">
              <?php endif; ?>
              <div class="mb-2"><label class="form-label small text-muted mb-1">Tên sách</label>
                <input type="text" name="title" class="form-control form-control-sm" value="<?php echo $edit_book?htmlspecialchars($edit_book['title']):''; ?>" required></div>
              <div class="mb-2"><label class="form-label small text-muted mb-1">Tác giả</label>
                <input type="text" name="author" class="form-control form-control-sm" value="<?php echo $edit_book?htmlspecialchars($edit_book['author']):''; ?>" required></div>
              <div class="mb-2"><label class="form-label small text-muted mb-1">Thể loại</label>
                <select name="category" class="form-select form-select-sm">
                  <?php foreach(['Khác','Công nghệ thông tin','Trí tuệ nhân tạo','Kỹ thuật phần mềm','Kỹ thuật xây dựng','Kinh tế','Ngoại ngữ','Khoa học tự nhiên','Xã hội nhân văn'] as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo ($edit_book && $edit_book['category']==$cat)?'selected':''; ?>><?php echo $cat; ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="mb-3"><label class="form-label small text-muted mb-1">Số lượng</label>
                <input type="number" name="quantity" class="form-control form-control-sm" value="<?php echo $edit_book?$edit_book['quantity']:''; ?>" min="0" required></div>
              <div class="mb-3"><label class="form-label small text-muted mb-1">Ảnh bìa</label>
                <input type="file" name="cover_image" class="form-control form-control-sm" accept="image/*"></div>
              <?php if($edit_book && !empty($edit_book['image_url'])): ?>
                <div class="mb-3 text-center"><img src="<?php echo htmlspecialchars($edit_book['image_url']); ?>" style="width:80px;height:110px;object-fit:cover;border-radius:8px;"></div>
              <?php endif; ?>
              <button type="submit" class="btn <?php echo $edit_book?'btn-warning':'btn-success'; ?> w-100 btn-sm fw-bold">
                <?php echo $edit_book?'LƯU THAY ĐỔI':'THÊM VÀO KHO'; ?>
              </button>
              <?php if($edit_book): ?><a href="?tab=books" class="btn btn-secondary w-100 btn-sm mt-2">Hủy</a><?php endif; ?>
            </form>

            <!-- Nhập sách CSV -->
            <hr class="my-3">
            <a href="bookseed.php" class="btn btn-outline-primary btn-sm w-100 rounded-pill">
              <i class="bi bi-file-earmark-spreadsheet me-1"></i>Nhập sách từ CSV
            </a>
          </div>
        </div>

        <div class="col-lg-9">
          <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="fw-bold text-dark m-0"><i class="bi bi-bookshelf text-primary"></i> Kho Sách (<?php echo $total_books; ?>)</h5>
              <form method="GET" class="d-flex" style="width:350px;">
                <input type="hidden" name="tab" value="books">
                <div class="input-group input-group-sm">
                  <input type="text" name="search" class="form-control" placeholder="Tìm tên sách, tác giả..." value="<?php echo htmlspecialchars($search); ?>">
                  <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                  <?php if($search): ?><a href="?tab=books" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a><?php endif; ?>
                </div>
              </form>
            </div>

            <div class="table-responsive">
              <table class="table table-hover table-bordered align-middle text-sm">
                <thead>
                  <tr class="text-center"><th width="5%">ID</th><th width="10%">Ảnh</th><th width="30%">Tên sách</th><th width="20%">Tác giả</th><th width="15%">Thể loại</th><th width="10%">Kho</th><th width="10%">Thao tác</th></tr>
                </thead>
                <tbody>
                  <?php if(count($books)>0): foreach($books as $b):
                    $cover = !empty($b['image_url']) ? $b['image_url'] : 'images/default-book.png'; ?>
                  <tr>
                    <td class="text-center"><?php echo $b['id']; ?></td>
                    <td class="text-center"><img src="<?php echo htmlspecialchars($cover); ?>" style="width:45px;height:65px;object-fit:cover;border-radius:5px;"></td>
                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($b['title']); ?></td>
                    <td><?php echo htmlspecialchars($b['author']); ?></td>
                    <td class="text-center"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($b['category']); ?></span></td>
                    <td class="text-center">
                      <?php if($b['quantity']==0): ?><span class="badge bg-danger">Hết</span>
                      <?php else: ?><span class="badge bg-success"><?php echo $b['quantity']; ?> cuốn</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                      <a href="?tab=books&edit_book=<?php echo $b['id']; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-outline-warning" title="Sửa"><i class="bi bi-pencil"></i></a>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="book_id" value="<?php echo $b['id']; ?>">
                        <button type="submit" name="book_action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa sách này?')"><i class="bi bi-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                  <?php endforeach; else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Không tìm thấy sách nào.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <?php if($total_pages>1): ?>
            <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">
              <li class="page-item <?php echo $page<=1?'disabled':''; ?>"><a class="page-link" href="?tab=books&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>">Trước</a></li>
              <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="?tab=books&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
              <?php endfor; ?>
              <li class="page-item <?php echo $page>=$total_pages?'disabled':''; ?>"><a class="page-link" href="?tab=books&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>">Sau</a></li>
            </ul></nav>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── DUYỆT MƯỢN / TRẢ ── -->
      <?php if($tab=='requests'): ?>
      <div class="content-card">
        <h4 class="fw-bold text-dark mb-1"><i class="bi bi-card-checklist text-primary"></i> Quản lý Yêu cầu Mượn &amp; Trả Sách</h4>
        <p class="small text-muted mb-4"><i class="bi bi-envelope-check text-success me-1"></i>Email xác nhận duyệt / từ chối sẽ được gửi tự động cho sinh viên.</p>

        <div class="table-responsive">
          <table class="table table-hover table-bordered align-middle">
            <thead class="table-light text-center">
              <tr>
                <th>ID</th><th>Mã SV</th><th>Liên hệ</th><th>Sách</th>
                <th>SL</th><th>Ngày mượn</th><th>Hạn trả</th><th>Phí</th>
                <th>Trạng thái</th><th>Hành động</th>
              </tr>
            </thead>
            <tbody>
              <?php if(count($reqs)>0): foreach($reqs as $r):
                $is_overdue = ($r['status']=='approved' && strtotime(date('Y-m-d'))>strtotime($r['return_date']));
                $late_days  = $is_overdue ? (int)ceil((strtotime(date('Y-m-d'))-strtotime($r['return_date']))/86400) : 0;
              ?>
              <tr class="<?php echo $is_overdue?'table-warning':''; ?>">
                <td class="text-center fw-bold"><?php echo $r['id']; ?></td>
                <td class="fw-bold text-primary text-center"><?php echo htmlspecialchars($r['u_name']??$r['username']??''); ?></td>
                <td style="font-size:12px;">
                  <?php if(!empty($r['email'])): ?>
                    <a href="mailto:<?php echo htmlspecialchars($r['email']); ?>" class="text-decoration-none d-block">
                      <i class="bi bi-envelope-fill text-danger"></i> <?php echo htmlspecialchars($r['email']); ?>
                    </a>
                  <?php else: ?><span class="text-muted small">N/A</span><?php endif; ?>
                  <?php if(!empty($r['phone'])): ?>
                    <a href="tel:<?php echo htmlspecialchars($r['phone']); ?>" class="text-decoration-none d-block">
                      <i class="bi bi-telephone-fill text-success"></i> <?php echo htmlspecialchars($r['phone']); ?>
                    </a>
                  <?php endif; ?>
                </td>
                <td style="max-width:180px;font-size:13px;" class="text-truncate" title="<?php echo htmlspecialchars($r['title']); ?>"><?php echo htmlspecialchars($r['title']); ?></td>
                <td class="text-center"><?php echo $r['quantity']; ?></td>
                <td class="text-center small"><?php echo isset($r['borrow_date'])?date('d/m/Y',strtotime($r['borrow_date'])):'—'; ?></td>
                <td class="text-center small">
                  <?php echo date('d/m/Y',strtotime($r['return_date'])); ?>
                  <?php if($is_overdue): ?><br><span class="badge bg-danger">Trễ <?php echo $late_days; ?> ngày</span><?php endif; ?>
                </td>
                <td class="text-center small">
                  <?php echo $r['total_fee']>0 ? number_format($r['total_fee']).'đ' : '—'; ?>
                  <?php if(!empty($r['late_fee']) && $r['late_fee']>0): ?>
                    <br><span class="text-danger fw-bold">Phạt: <?php echo number_format($r['late_fee']); ?>đ</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if($r['status']=='pending'): ?>
                    <span class="badge bg-warning text-dark">Chờ duyệt</span>
                  <?php elseif($r['status']=='approved'): ?>
                    <span class="badge bg-success">Đang mượn</span>
                  <?php elseif($r['status']=='rejected'): ?>
                    <span class="badge bg-danger">Từ chối</span>
                    <?php if(!empty($r['reject_reason'])): ?><br><small class="text-muted"><?php echo htmlspecialchars($r['reject_reason']); ?></small><?php endif; ?>
                  <?php elseif($r['status']=='returned'): ?>
                    <span class="badge bg-secondary">Đã trả</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if($r['status']=='pending'): ?>
                  <div class="d-flex flex-column gap-1 align-items-center">
                    <!-- DUYỆT → tự động gửi email -->
                    <form method="POST">
                      <input type="hidden" name="req_id" value="<?php echo $r['id']; ?>">
                      <button type="submit" name="req_action" value="approve"
                              class="btn btn-sm btn-success px-3"
                              title="Duyệt — email tự động gửi cho sinh viên"
                              onclick="return confirm('Duyệt yêu cầu này? Email xác nhận sẽ tự gửi cho sinh viên.')">
                        <i class="bi bi-check-lg"></i> Duyệt
                        <span style="font-size:9px;display:block;opacity:.8;">✉️ tự gửi mail</span>
                      </button>
                    </form>
                    <!-- TỪ CHỐI → tự động gửi email -->
                    <form method="POST" class="w-100">
                      <input type="hidden" name="req_id" value="<?php echo $r['id']; ?>">
                      <div class="input-group input-group-sm">
                        <input type="text" name="reject_reason" placeholder="Lý do từ chối..." class="form-control" style="font-size:11px;" required>
                        <button type="submit" name="req_action" value="reject" class="btn btn-danger"
                                title="Từ chối — email tự động gửi cho sinh viên"
                                onclick="return confirm('Từ chối? Email sẽ gửi cho sinh viên.')">
                          <i class="bi bi-x-lg"></i>
                        </button>
                      </div>
                      <div style="font-size:9px;color:#999;margin-top:2px;text-align:right;">✉️ mail tự gửi</div>
                    </form>
                  </div>
                  <?php elseif($r['status']=='approved'): ?>
                    <form method="POST">
                      <input type="hidden" name="req_id" value="<?php echo $r['id']; ?>">
                      <button type="submit" name="req_action" value="return"
                              class="btn btn-sm btn-primary"
                              onclick="return confirm('Sinh viên đã hoàn trả sách?')">
                        <i class="bi bi-arrow-return-left"></i> Thu hồi
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">Chưa có yêu cầu mượn sách nào.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.col-lg-10 -->
  </div><!-- /.row -->
</main>

<footer>
  <div class="container text-center pb-3">
    <p class="small text-muted mb-0">Hệ thống Quản lý Thư viện Số QNU – Module dành cho Thủ thư</p>
  </div>
  <div style="background:#000;text-align:center;padding:10px 0;font-size:12px;color:#666;">
    Copyright © 2026 TRUNG TÂM SỐ VÀ HỌC LIỆU
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
