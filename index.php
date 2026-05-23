<?php
session_start();
require_once 'config.php';

$login_error = '';

// ── XỬ LÝ AJAX: THÊM VÀO GIỎ MƯỢN ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cart'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        echo json_encode(['ok' => false, 'need_login' => true]);
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];
    $book_id = (int)($_POST['book_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    $s = $pdo->prepare("SELECT quantity FROM Books WHERE id = ?");
    $s->execute([$book_id]);
    $stock = (int)($s->fetchColumn() ?: 0);

    if ($stock < $qty) {
        echo json_encode(['ok' => false, 'msg' => 'Không đủ số lượng trong kho.']);
        exit;
    }

    $pdo->prepare("
        INSERT INTO BorrowCart (user_id, book_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = ?
    ")->execute([$user_id, $book_id, $qty, $qty]);

    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id = ?");
    $cnt_stmt->execute([$user_id]);
    $cnt = (int)$cnt_stmt->fetchColumn();

    echo json_encode(['ok' => true, 'msg' => 'Đã thêm vào giỏ!', 'count' => $cnt]);
    exit;
}

// ── XỬ LÝ AJAX: ĐẾM GIỎ ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_cart_count'])) {
    header('Content-Type: application/json');
    $cnt = 0;
    if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
        $s = $pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id=?");
        $s->execute([$_SESSION['user_id']]);
        $cnt = (int)$s->fetchColumn();
    }
    echo json_encode(['count' => $cnt]);
    exit;
}

// ── XỬ LÝ ĐĂNG NHẬP TỪ MODAL ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_action'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $login_ok = false;
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $login_ok = true;
        } elseif ($password === $user['password']) {
            $login_ok = true;
            $pdo->prepare("UPDATE Users SET password=? WHERE id=?")
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
    }

    if ($login_ok) {
        if ($user['role'] === 'student') {
            $verified = isset($user['email_verified']) ? (int)$user['email_verified'] : 1;
            if ($verified === 0 && !empty($user['email'])) {
                $login_error = "Tài khoản chưa xác thực email. Kiểm tra hộp thư <b>{$user['email']}</b> hoặc liên hệ thủ thư.";
                goto show_page;
            }
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] === 'admin') { header("Location: indexadmin.php"); exit; }
        if ($user['role'] === 'librarian') { header("Location: indexlibrarian.php"); exit; }
        header("Location: index.php"); exit;
    } else {
        $login_error = "Tài khoản hoặc mật khẩu không chính xác!";
    }
}

show_page:

// ── ĐẾM THÔNG BÁO CHƯA ĐỌC ──────────────────────────────────
$unread_count = 0;
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
    $s = $pdo->prepare("SELECT COUNT(*) FROM Notifications WHERE user_id=? AND is_read=0");
    $s->execute([$_SESSION['user_id']]);
    $unread_count = (int)$s->fetchColumn();
}

// ── ĐẾM GIỎ MƯỢN ─────────────────────────────────────────────
$cart_count = 0;
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
    $s = $pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id=?");
    $s->execute([$_SESSION['user_id']]);
    $cart_count = (int)$s->fetchColumn();
}

// ── LẤY SÁCH KÈM ĐÁNH GIÁ ───────────────────────────────────
try {
    $stmt = $pdo->query("
        SELECT b.*,
               ROUND(COALESCE(AVG(r.rating),0),1) AS avg_rating,
               COUNT(r.id) AS review_count
        FROM Books b
        LEFT JOIN Reviews r ON b.id = r.book_id
        GROUP BY b.id
        ORDER BY b.id DESC
    ");
    $books_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($books_db as &$book) {
        $book['image_url'] = !empty($book['image_url']) && trim($book['image_url']) !== ''
            ? $book['image_url'] : 'img/default.png';
    }
    unset($book);
} catch (Exception $e) {
    $books_db = [];
    error_log("Lỗi query sách: " . $e->getMessage());
}

$books_json = json_encode($books_db, JSON_UNESCAPED_UNICODE);
$is_student = (isset($_SESSION['role']) && $_SESSION['role'] === 'student') ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="vi" dir="ltr">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>
<title>Thư viện Số | Hệ thống Tra cứu Học liệu Đại học</title>
<link rel="shortcut icon" href="https://qnu.edu.vn/favicon.ico" type="image/vnd.microsoft.icon"/>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
<style>
:root{--qnu-blue:#0054a6;--qnu-blue-dark:#003d7a;--qnu-gold:#ffc107;--qnu-bg:#f4f7f6;--shadow:0 4px 20px rgba(0,0,0,0.08);}
body{font-family:'Roboto',sans-serif;background:var(--qnu-bg);color:#444;overflow-x:hidden;}
.qnu-header{background:white;border-top:5px solid var(--qnu-blue);padding:15px 0;box-shadow:0 2px 10px rgba(0,0,0,.1);}
.university-name{color:var(--qnu-blue);font-weight:900;font-size:1.4rem;text-transform:uppercase;}
.library-name{color:#ce1126;font-weight:600;font-size:1.1rem;}
.top-marquee{background:var(--qnu-blue-dark);color:white;padding:7px 0;font-size:13px;border-bottom:3px solid var(--qnu-gold);}
.search-container{background:linear-gradient(135deg,var(--qnu-blue) 0%,#007bff 100%);padding:60px 0;}
.main-search-input{height:65px;border-radius:50px!important;border:none;padding-left:30px;font-size:1.15rem;box-shadow:0 10px 30px rgba(0,0,0,.2);}
.btn-search-trigger{border-radius:50px!important;padding:0 40px;background:var(--qnu-gold);color:var(--qnu-blue-dark);font-weight:800;border:none;}
.sidebar-card{background:white;border-radius:15px;overflow:hidden;box-shadow:var(--shadow);border:none;margin-bottom:25px;}
.sidebar-header{background:linear-gradient(to right,var(--qnu-blue-dark),var(--qnu-blue));color:white;padding:18px;font-weight:700;text-transform:uppercase;font-size:14px;}
.list-group-item{padding:14px 20px;border:none;font-size:14px;cursor:pointer;transition:.3s;}
.list-group-item.active{background-color:var(--qnu-blue)!important;color:white!important;}
.book-card{background:white;border-radius:16px;border:1px solid rgba(0,0,0,.06);transition:transform .28s,box-shadow .28s;cursor:pointer;height:100%;overflow:hidden;display:flex;flex-direction:column;}
.book-card:hover{transform:translateY(-8px);box-shadow:0 18px 40px rgba(0,0,0,.10);}
.book-img-box{aspect-ratio:2/3;overflow:hidden;background:linear-gradient(180deg,#fbfdff,#f0f6ff);display:flex;align-items:center;justify-content:center;padding:8px;border-radius:12px;}
.book-img-box img{width:100%;height:100%;object-fit:cover;transition:transform .35s;border-radius:10px;}
.book-card:hover .book-img-box img{transform:scale(1.02);}
.author-line{font-size:12px;height:20px;line-height:20px;overflow:hidden;}
.author-text{display:inline-block;max-width:100%;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.info-content-fade{animation:fadeIn .5s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.pagination .page-link{color:var(--qnu-blue);border-radius:8px;margin:0 3px;font-weight:500;}
.pagination .page-item.active .page-link{background-color:var(--qnu-blue);border-color:var(--qnu-blue);color:white;}
footer{background:#0f172a;color:#fff;padding:40px 0 0;border-top:4px solid var(--qnu-gold);}
/* Sao đánh giá */
.star-mini{font-size:11px;line-height:1;}
.star-mini .on{color:var(--qnu-gold);}
.star-mini .off{color:#ddd;}
.btn-rate{font-size:11px;padding:2px 10px;border-radius:20px;border:1px solid var(--qnu-gold);color:#7a5800;background:#fff9e6;cursor:pointer;transition:background .2s;display:inline-block;margin-top:4px;}
.btn-rate:hover{background:var(--qnu-gold);color:#000;}
/* Modal đánh giá */
.review-modal-header{background:linear-gradient(135deg,var(--qnu-blue-dark),#1a7ce8);color:white;border:none;padding:16px 20px;}
.avg-box{background:linear-gradient(135deg,var(--qnu-blue),#1a7ce8);color:white;border-radius:14px;padding:16px 22px;text-align:center;min-width:100px;}
.avg-box .score{font-size:2.4rem;font-weight:900;line-height:1;}
.avg-box .out{font-size:11px;opacity:.75;margin-top:2px;}
.star-input{display:flex;gap:4px;}
.star-input .si{font-size:28px;color:#ddd;cursor:pointer;transition:color .12s,transform .12s;line-height:1;user-select:none;}
.star-input .si.on{color:var(--qnu-gold);}
.star-input .si:hover{transform:scale(1.2);}
.review-list{max-height:360px;overflow-y:auto;padding-right:4px;}
.review-item{border-bottom:1px solid #f0f0f0;padding:11px 0;}
.review-item:last-child{border-bottom:none;}
.bar-row{display:flex;align-items:center;gap:6px;font-size:11px;margin-bottom:3px;}
.bar-track{flex:1;background:#f0f0f0;border-radius:9px;height:6px;overflow:hidden;}
.bar-fill{height:100%;background:var(--qnu-gold);border-radius:9px;}
/* Nút giỏ mượn */
.cart-btn-wrapper{position:relative;display:inline-block;}
.cart-badge{position:absolute;top:-6px;right:-8px;background:#e74c3c;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;line-height:1;}
/* Nút thêm giỏ trên card */
.btn-add-cart{font-size:11px;padding:3px 10px;border-radius:20px;border:1px solid var(--qnu-blue);color:var(--qnu-blue);background:#f0f6ff;cursor:pointer;transition:background .2s;display:inline-block;margin-top:4px;width:100%;}
.btn-add-cart:hover{background:var(--qnu-blue);color:white;}
/* Toast thông báo */
.toast-container{position:fixed;bottom:24px;right:24px;z-index:9999;}
</style>
</head>
<body>

<div class="top-marquee">
  <div class="container"><marquee>🎓 Chào mừng đến Thư viện Số QNU — Mượn nhiều sách, thanh toán QR, nhận nhắc nhở qua Gmail!</marquee></div>
</div>

<?php if($login_error): ?>
<div class="alert alert-danger text-center m-0 rounded-0 py-2">
  <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $login_error; ?>
</div>
<?php endif; ?>

<!-- HEADER -->
<header class="qnu-header sticky-top">
<div class="container d-flex justify-content-between align-items-center">
  <div onclick="location.href='index.php'" style="cursor:pointer;display:flex;align-items:center;">
    <img src="images/qnu_logo.png" alt="Logo" style="height:65px;" onerror="this.style.display='none'">
    <div class="ms-3 d-none d-md-block">
      <h1 class="university-name m-0">Thư Viện Số</h1>
      <div class="library-name">TRUNG TÂM SỐ VÀ HỌC LIỆU</div>
    </div>
  </div>

  <div id="auth-zone">
    <?php if(!isset($_SESSION['username'])): ?>
      <button class="btn btn-primary btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#loginModal">
        <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
      </button>
    <?php else: ?>
      <div class="d-flex align-items-center flex-wrap gap-2">
        <?php if(($_SESSION['role']??'') === 'student'): ?>
          <a href="cart.php" class="btn btn-warning btn-sm rounded-pill cart-btn-wrapper px-3" id="cart-header-btn">
            <i class="bi bi-basket3-fill me-1"></i>Giỏ mượn
            <?php if($cart_count > 0): ?>
              <span class="cart-badge" id="cart-badge"><?php echo $cart_count; ?></span>
            <?php else: ?>
              <span class="cart-badge" id="cart-badge" style="display:none">0</span>
            <?php endif; ?>
          </a>
          <a href="notifications.php" class="btn btn-outline-warning btn-sm rounded-pill position-relative">
            <i class="bi bi-bell-fill me-1"></i>Thông báo
            <?php if($unread_count > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $unread_count; ?></span>
            <?php endif; ?>
          </a>
        <?php endif; ?>

        <span class="fw-bold text-primary small"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></span>

        <?php if(($_SESSION['role']??'') === 'student'): ?>
          <a href="profile.php" class="btn btn-outline-primary btn-sm rounded-pill"><i class="bi bi-bookmark me-1"></i>Cá nhân</a>
        <?php elseif(($_SESSION['role']??'') === 'librarian'): ?>
          <a href="indexlibrarian.php" class="btn btn-outline-primary btn-sm rounded-pill"><i class="bi bi-person-badge me-1"></i>Trang thủ thư</a>
        <?php elseif(($_SESSION['role']??'') === 'admin'): ?>
          <a href="indexadmin.php" class="btn btn-outline-primary btn-sm rounded-pill"><i class="bi bi-shield-lock me-1"></i>Trang admin</a>
        <?php endif; ?>

        <a href="logout.php" class="btn btn-danger btn-sm rounded-pill"><i class="bi bi-box-arrow-right me-1"></i>Thoát</a>
      </div>
    <?php endif; ?>
  </div>
</div>
</header>

<!-- THANH TÌM KIẾM -->
<section class="search-container text-center" id="search-section">
  <div class="container">
    <h2 class="text-white fw-bold mb-4">HỆ THỐNG TRA CỨU TÀI LIỆU TRỰC TUYẾN</h2>
    <div class="input-group mx-auto shadow-lg" style="max-width:850px;border-radius:50px;">
      <input type="text" id="master-search-input" class="form-control main-search-input"
             placeholder="Tìm tên sách, tác giả, thể loại..."
             onkeyup="if(event.key==='Enter') executeGlobalSearch()">
      <button class="btn btn-search-trigger" onclick="executeGlobalSearch()">TÌM KIẾM</button>
    </div>
  </div>
</section>

<!-- NỘI DUNG CHÍNH -->
<main class="container py-5">
  <div class="row">
    <aside class="col-lg-3 mb-4">
      <div class="sidebar-card shadow-sm">
        <div class="sidebar-header">Phân loại học liệu</div>
        <div class="list-group list-group-flush" id="main-nav-group">
          <button class="list-group-item list-group-item-action active" onclick="loadContent('all',this)">📚 Tất cả tài liệu</button>
          <button class="list-group-item list-group-item-action" onclick="loadContent('physical',this)">📘 Tài liệu vật lí</button>
          <button class="list-group-item list-group-item-action" onclick="loadContent('ebook',this)">📱 Tài liệu điện tử</button>
          <button class="list-group-item list-group-item-action" onclick="showStaticInfo('rules',this)">📜 Nội quy</button>
        </div>
      </div>

      <?php if(isset($_SESSION['role']) && $_SESSION['role']==='student'): ?>
      <div class="sidebar-card p-3 shadow-sm text-center">
        <h6 class="fw-bold text-warning mb-2"><i class="bi bi-basket3-fill me-1"></i>Giỏ mượn của tôi</h6>
        <p class="small text-muted mb-2">Chọn nhiều sách rồi thanh toán QR một lần</p>
        <a href="cart.php" class="btn btn-warning btn-sm w-100 rounded-pill fw-bold">
          Xem giỏ mượn <span id="sidebar-cart-count" class="badge bg-dark ms-1"><?php echo $cart_count; ?></span>
        </a>
      </div>
      <?php endif; ?>

      <div class="sidebar-card p-4 text-center mt-2">
        <h6 class="fw-bold text-primary mb-3">HỖ TRỢ TRỰC TUYẾN</h6>
        <a href="https://m.me/qnu" target="_blank" class="btn btn-sm btn-info w-100 text-white rounded-pill">
          <i class="bi bi-chat-dots-fill"></i> Chat với Thư viện
        </a>
      </div>
    </aside>

    <section class="col-lg-9">
      <div id="dynamic-workspace"></div>
    </section>
  </div>
</main>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div style="margin-bottom:10px;">
      <h6 style="font-weight:700;letter-spacing:1px;margin-bottom:15px;">THÔNG TIN LIÊN HỆ</h6>
      <ul style="list-style:none;padding:0;font-size:14px;line-height:1.8;opacity:.8;">
        <li><i class="bi bi-geo-alt-fill me-2 text-warning"></i>Phòng 121 Nhà 15 tầng, Trường ĐH Quy Nhơn</li>
        <li><i class="bi bi-envelope-fill me-2 text-warning"></i>shl@qnu.edu.vn</li>
        <li><i class="bi bi-telephone-fill me-2 text-warning"></i>(84-256) 3846156</li>
      </ul>
    </div>
  </div>
  <div style="background:#000;text-align:center;padding:15px 0;font-size:12px;color:#666;margin-top:30px;">
    Copyright © 2026 TRUNG TÂM SỐ VÀ HỌC LIỆU - ĐH QUY NHƠN
  </div>
</footer>

<!-- TOAST -->
<div class="toast-container">
  <div id="cartToast" class="toast align-items-center border-0" role="alert" style="min-width:220px;">
    <div class="d-flex">
      <div class="toast-body fw-bold" id="cartToastMsg">✅ Đã thêm vào giỏ!</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<!-- MODAL LOGIN -->
<div class="modal fade" id="loginModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content p-4">
      <h5 class="fw-bold text-primary mb-3 text-center">ĐĂNG NHẬP</h5>
      <form method="POST" action="index.php">
        <input type="hidden" name="login_action" value="1">
        <div class="mb-2">
          <input type="text" name="username" class="form-control rounded-pill" placeholder="Mã SV / Tài khoản" required>
        </div>
        <div class="mb-3 input-group">
          <input type="password" name="password" id="loginPassInput" class="form-control rounded-start-pill" placeholder="Mật khẩu" required>
          <button type="button" class="btn btn-outline-secondary rounded-end-pill px-3"
                  onclick="var i=document.getElementById('loginPassInput');i.type=i.type==='password'?'text':'password'">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">XÁC NHẬN</button>
      </form>
    </div>
  </div>
</div>

<!-- MODAL ĐÁNH GIÁ (giữ nguyên phần cũ) -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <!-- ... (giữ nguyên nội dung modal đánh giá như bản cũ của bạn) ... -->
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Dữ liệu sách
const rawData = <?php echo $books_json; ?>;
const IS_STUDENT = <?php echo $is_student; ?>;

const QNU_DATABASE = rawData.map(b => ({
    id: b.id,
    category: b.category || 'Khác',
    title: b.title,
    author: b.author,
    quantity: parseInt(b.quantity || 0),
    ebook_available: parseInt(b.ebook_available || 0),
    ebook_url: b.ebook_url || '',
    img: (b.image_url && b.image_url.trim() !== '') ? b.image_url : 'img/default.png',
    avg_rating: parseFloat(b.avg_rating || 0),
    review_count: parseInt(b.review_count || 0),
}));

// Phần JavaScript còn lại (giữ nguyên từ file cũ của bạn)
const itemsPerPage = 16;
let currentPage = 1, currentFilteredData = [];

// ... (Bạn có thể copy phần JavaScript từ file cũ của bạn vào đây, từ function initWorkspace trở xuống)

window.onload = () => {
    loadContent('all', document.querySelector('.list-group-item'));
    <?php if($login_error): ?>
    var myModal = new bootstrap.Modal(document.getElementById('loginModal'));
    myModal.show();
    <?php endif; ?>
};
</script>
</body>
</html>