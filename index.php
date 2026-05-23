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
.book-card-body{padding:12px;flex:1;display:flex;flex-direction:column;}
.book-title{font-weight:700;font-size:13px;line-height:1.3;color:#0054a6;margin-bottom:4px;min-height:26px;}
.author-line{font-size:12px;height:20px;line-height:20px;overflow:hidden;}
.author-text{display:inline-block;max-width:100%;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.book-actions{display:flex;gap:6px;margin-top:auto;padding-top:8px;border-top:1px solid #f0f0f0;}
.info-content-fade{animation:fadeIn .5s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.pagination .page-link{color:var(--qnu-blue);border-radius:8px;margin:0 3px;font-weight:500;}
.pagination .page-item.active .page-link{background-color:var(--qnu-blue);border-color:var(--qnu-blue);color:white;}
footer{background:#0f172a;color:#fff;padding:40px 0 0;border-top:4px solid var(--qnu-gold);}
.star-mini{font-size:11px;line-height:1;}
.star-mini .on{color:var(--qnu-gold);}
.star-mini .off{color:#ddd;}
.btn-rate{font-size:11px;padding:2px 10px;border-radius:20px;border:1px solid var(--qnu-gold);color:#7a5800;background:#fff9e6;cursor:pointer;transition:background .2s;display:inline-block;}
.btn-rate:hover{background:var(--qnu-gold);color:#000;}
.btn-add-cart{font-size:11px;padding:3px 10px;border-radius:20px;border:1px solid var(--qnu-blue);color:var(--qnu-blue);background:#f0f6ff;cursor:pointer;transition:background .2s;display:inline-block;flex:1;}
.btn-add-cart:hover{background:var(--qnu-blue);color:white;}
.cart-btn-wrapper{position:relative;display:inline-block;}
.cart-badge{position:absolute;top:-6px;right:-8px;background:#e74c3c;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.toast-container{position:fixed;bottom:24px;right:24px;z-index:9999;}
.empty-state{text-align:center;padding:60px 20px;}
.empty-state-icon{font-size:72px;margin-bottom:15px;}
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ═══════════════════════════════════════════════════════════════
// DỮLIỆU SÁCH TỪ DATABASE
// ═══════════════════════════════════════════════════════════════
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

// ═══════════════════════════════════════════════════════════════
// CẤU HÌNH PHÂN TRANG
// ═══════════════════════════════════════════════════════════════
const itemsPerPage = 16;
let currentPage = 1;
let currentFilteredData = [];
let currentFilter = 'all';

// ═══════════════════════════════════════════════════════════════
// HÀM FILTER SÁCH
// ═══════════════════════════════════════════════════════════════
function filterBooks(type) {
    if (type === 'all') {
        return QNU_DATABASE;
    } else if (type === 'physical') {
        return QNU_DATABASE.filter(b => b.quantity > 0);
    } else if (type === 'ebook') {
        return QNU_DATABASE.filter(b => b.ebook_available > 0);
    }
    return QNU_DATABASE;
}

// ═══════════════════════════════════════════════════════════════
// HÀM RENDER SÁCH
// ═══════════════════════════════════════════════════════════════
function renderBooks(books, page = 1) {
    const start = (page - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const paginatedBooks = books.slice(start, end);

    let html = '<div class="row g-3">';
    
    if (paginatedBooks.length === 0) {
        html = '<div class="empty-state"><div class="empty-state-icon">📚</div><p class="text-muted">Không tìm thấy sách</p></div>';
    } else {
        paginatedBooks.forEach(book => {
            const stars = renderStars(book.avg_rating);
            const cartBtn = IS_STUDENT === 'true' 
                ? `<button class="btn-add-cart" onclick="addToCart(${book.id}, 1)"><i class="bi bi-cart-plus"></i> Mượn</button>`
                : '';
            
            html += `
                <div class="col-md-6 col-lg-3">
                    <div class="book-card shadow-sm">
                        <div class="book-img-box">
                            <img src="${book.img}" alt="${book.title}" onerror="this.src='img/default.png'">
                        </div>
                        <div class="book-card-body">
                            <div class="book-title">${book.title}</div>
                            <div class="author-line">
                                <span class="author-text text-muted small">${book.author}</span>
                            </div>
                            <div class="star-mini mt-2">
                                <span class="on">${stars}</span>
                                <span class="off">${'☆'.repeat(5 - Math.round(book.avg_rating))}</span>
                                <span class="small ms-1">(${book.review_count})</span>
                            </div>
                            <div class="book-actions">
                                <a href="detail.php?id=${book.id}" class="btn btn-sm btn-outline-primary flex-grow-1">
                                    <i class="bi bi-eye"></i> Xem
                                </a>
                                ${cartBtn}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    
    html += '</div>';
    
    // PHÂN TRANG
    const totalPages = Math.ceil(books.length / itemsPerPage);
    if (totalPages > 1) {
        html += '<nav class="mt-4"><ul class="pagination justify-content-center">';
        
        for (let i = 1; i <= totalPages; i++) {
            const activeClass = i === page ? 'active' : '';
            html += `<li class="page-item ${activeClass}"><a class="page-link" onclick="goToPage(${i})">${i}</a></li>`;
        }
        
        html += '</ul></nav>';
    }
    
    document.getElementById('dynamic-workspace').innerHTML = html;
}

// ═══════════════════════════════════════════════════════════════
// HÀM HIỂN THỊ SAO
// ═══════════════════════════════════════════════════════════════
function renderStars(rating) {
    const rounded = Math.round(rating);
    return '★'.repeat(rounded);
}

// ═══════════════════════════════════════════════════════════════
// HÀM LOAD CONTENT (CHÍNH)
// ═══════════════════════════════════════════════════════════════
function loadContent(type, element) {
    currentFilter = type;
    currentPage = 1;
    currentFilteredData = filterBooks(type);
    
    // CẬP NHẬT NÚT ACTIVE
    document.querySelectorAll('#main-nav-group .list-group-item').forEach(btn => {
        btn.classList.remove('active');
    });
    element.classList.add('active');
    
    renderBooks(currentFilteredData, 1);
}

// ═══════════════════════════════════════════════════════════════
// HÀM CHUYỂN TRANG
// ═══════════════════════════════════════════════════════════════
function goToPage(page) {
    currentPage = page;
    renderBooks(currentFilteredData, page);
    document.querySelector('.container.py-5').scrollIntoView({ behavior: 'smooth' });
}

// ═══════════════════════════════════════════════════════════════
// HÀM TÌM KIẾM TOÀN CỤC
// ═══════════════════════════════════════════════════════════════
function executeGlobalSearch() {
    const query = document.getElementById('master-search-input').value.toLowerCase().trim();
    
    if (query === '') {
        loadContent('all', document.querySelector('#main-nav-group .list-group-item'));
        return;
    }
    
    const results = QNU_DATABASE.filter(book => 
        book.title.toLowerCase().includes(query) ||
        book.author.toLowerCase().includes(query) ||
        book.category.toLowerCase().includes(query)
    );
    
    currentFilteredData = results;
    currentPage = 1;
    
    document.querySelectorAll('#main-nav-group .list-group-item').forEach(btn => {
        btn.classList.remove('active');
    });
    
    renderBooks(results, 1);
}

// ═══════════════════════════════════════════════════════════════
// HÀM THÊM VÀO GIỎ
// ═══════════════════════════════════════════════════════════════
function addToCart(bookId, qty) {
    if (IS_STUDENT !== 'true') {
        new bootstrap.Modal(document.getElementById('loginModal')).show();
        return;
    }

    const formData = new FormData();
    formData.append('ajax_cart', '1');
    formData.append('book_id', bookId);
    formData.append('quantity', qty);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(data.msg);
            updateCartBadge(data.count);
        } else if (data.need_login) {
            new bootstrap.Modal(document.getElementById('loginModal')).show();
        } else {
            alert(data.msg || 'Có lỗi xảy ra');
        }
    })
    .catch(e => console.error(e));
}

// ═══════════════════════════════════════════════════════════════
// HÀM CẬP NHẬT BADGE GIỎ
// ═══════════════════════════════════════════════════════════════
function updateCartBadge(count) {
    const badge = document.getElementById('cart-badge');
    const sidebarCount = document.getElementById('sidebar-cart-count');
    
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
    
    if (sidebarCount) {
        sidebarCount.textContent = count;
    }
}

// ═══════════════════════════════════════════════════════════════
// HÀM HIỂN THỊ TOAST
// ═══════════════════════════════════════════════════════════════
function showToast(msg) {
    const toastEl = document.getElementById('cartToast');
    const msgEl = document.getElementById('cartToastMsg');
    msgEl.textContent = msg;
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
}

// ═══════════════════════════════════════════════════════════════
// HÀM HIỂN THỊ THÔNG TIN TĨŃ (NỘI QUY, V.V)
// ═══════════════════════════════════════════════════════════════
function showStaticInfo(type, element) {
    let content = '';
    
    if (type === 'rules') {
        content = `
            <div class="alert alert-info">
                <h5 class="fw-bold text-primary mb-3">📜 NỘI QUY SỬ DỤNG THƯ VIỆN</h5>
                <ul class="small">
                    <li>Mỗi lần mượn tối đa 5 cuốn</li>
                    <li>Thời gian mượn mặc định: 30 ngày</li>
                    <li>Có thể gia hạn tối đa 2 lần (15 ngày/lần)</li>
                    <li>Nếu quá hạn: 10.000 VND/ngày/cuốn</li>
                    <li>Sách bị mất: phải bồi thường bằng tiền</li>
                    <li>Có thể thanh toán qua QR code</li>
                </ul>
            </div>
        `;
    }
    
    document.getElementById('dynamic-workspace').innerHTML = content;
    
    document.querySelectorAll('#main-nav-group .list-group-item').forEach(btn => {
        btn.classList.remove('active');
    });
    element.classList.add('active');
}

// ═══════════════════════════════════════════════════════════════
// KHỞI TẠNG KHI LOAD TRANG
// ═══════════════════════════════════════════════════════════════
window.addEventListener('load', () => {
    loadContent('all', document.querySelector('.list-group-item'));
    <?php if($login_error): ?>
    var myModal = new bootstrap.Modal(document.getElementById('loginModal'));
    myModal.show();
    <?php endif; ?>
});
</script>
</body>
</html>
