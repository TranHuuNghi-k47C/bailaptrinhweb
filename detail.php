<?php
session_start();
require_once 'config.php';

// ── XỬ LÝ AJAX: THÊM VÀO GIỎ TỪ TRANG CHI TIẾT ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cart'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        echo json_encode(['ok'=>false,'need_login'=>true]); exit;
    }
    $uid = (int)$_SESSION['user_id'];
    $bid = (int)($_POST['book_id'] ?? 0);
    $qty = max(1,(int)($_POST['quantity']??1));
    $s = $pdo->prepare("SELECT quantity FROM Books WHERE id=?");
    $s->execute([$bid]);
    $stock = (int)($s->fetchColumn()?:0);
    if ($stock < $qty) { echo json_encode(['ok'=>false,'msg'=>'Không đủ trong kho.']); exit; }
    $pdo->prepare("INSERT INTO BorrowCart(user_id,book_id,quantity) VALUES(?,?,?) ON DUPLICATE KEY UPDATE quantity=?")->execute([$uid,$bid,$qty,$qty]);
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM BorrowCart WHERE user_id=$uid")->fetchColumn();
    echo json_encode(['ok'=>true,'msg'=>'Đã thêm vào giỏ!','count'=>$cnt]); exit;
}

$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM Books WHERE id=?");
$stmt->execute([$book_id]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    die("<div style='font-family:Arial;text-align:center;margin-top:80px;'><h2>❌ Không tìm thấy sách</h2><a href='index.php'>Quay lại trang chủ</a></div>");
}

$cover = !empty($book['image_url']) ? $book['image_url'] : 'images/default-book.png';

// Đếm giỏ mượn
$cart_count = 0;
if (isset($_SESSION['user_id']) && ($_SESSION['role']??'') === 'student') {
    $s = $pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id=?");
    $s->execute([$_SESSION['user_id']]);
    $cart_count = (int)$s->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($book['title']); ?> | Chi tiết sách</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="shortcut icon" href="https://qnu.edu.vn/favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--blue:#0054a6;--blue-dark:#003478;--gold:#f5a623;--red:#ce1126;--bg:#eef2f7;--text:#1e2d42;--muted:#6c7a8d;}
body{font-family:'Be Vietnam Pro',sans-serif;background:var(--bg);color:var(--text);}
.top-bar{background:var(--blue-dark);color:white;font-size:13px;padding:7px 0;border-bottom:3px solid var(--gold);}
.qnu-header{background:white;border-top:5px solid var(--blue);box-shadow:0 3px 20px rgba(0,60,130,.1);position:sticky;top:0;z-index:999;}
.header-inner{max-width:1200px;margin:0 auto;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;}
.brand{display:flex;align-items:center;gap:14px;cursor:pointer;}
.brand img{height:62px;}
.uni-name{font-size:1.2rem;font-weight:900;color:var(--blue);text-transform:uppercase;}
.lib-name{font-size:.85rem;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.05em;}
.hero{background:linear-gradient(135deg,var(--blue-dark),var(--blue));padding:45px 20px 80px;color:white;text-align:center;}
.hero h2{font-weight:900;margin-bottom:8px;}
.detail-wrapper{max-width:1150px;margin:-50px auto 60px;padding:0 20px;position:relative;z-index:5;}
.detail-card{background:white;border-radius:22px;box-shadow:0 18px 50px rgba(0,60,130,.16);overflow:hidden;}
.book-cover-box{background:linear-gradient(135deg,#e8f0fb,#c8d8f5);padding:35px;text-align:center;height:100%;}
.book-cover{width:260px;max-width:100%;aspect-ratio:2/3;object-fit:cover;border-radius:14px;box-shadow:0 15px 35px rgba(0,0,0,.25);background:white;}
.book-info{padding:38px;}
.book-title{font-size:1.8rem;font-weight:900;color:var(--blue);line-height:1.35;margin-bottom:12px;}
.book-author{color:var(--muted);font-size:1rem;margin-bottom:22px;}
.meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:25px 0;}
.meta-item{background:#f4f8fd;border:1px solid #e1ecf8;border-radius:14px;padding:14px 16px;}
.meta-label{font-size:12px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px;}
.meta-value{font-size:15px;font-weight:800;color:var(--text);}
.description-box{background:#fffaf0;border-left:5px solid var(--gold);padding:20px;border-radius:14px;margin-top:25px;}
.description-box h5{color:var(--blue-dark);font-weight:900;margin-bottom:10px;}
.description-box p{color:#4b5563;line-height:1.8;margin-bottom:0;}
.action-box{margin-top:30px;display:flex;gap:12px;flex-wrap:wrap;}
.btn-borrow{background:linear-gradient(135deg,var(--blue),#1a6cc4);color:white;border:none;padding:13px 28px;border-radius:50px;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 22px rgba(0,84,166,.28);}
.btn-borrow:hover{color:white;filter:brightness(1.07);}
.btn-back{background:#eef2f7;color:var(--text);border:none;padding:13px 24px;border-radius:50px;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
.btn-cart{background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#1a1a1a;border:none;padding:13px 28px;border-radius:50px;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 22px rgba(245,158,11,.28);cursor:pointer;}
.btn-cart:hover{filter:brightness(1.05);}
.status-badge{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:50px;font-size:13px;font-weight:800;}
.available{background:#dcfce7;color:#166534;}
.unavailable{background:#fee2e2;color:#991b1b;}
.cart-btn-wrapper{position:relative;}
.cart-badge{position:absolute;top:-6px;right:-8px;background:#e74c3c;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;}
footer{background:#0f172a;color:rgba(255,255,255,.6);text-align:center;padding:18px;font-size:13px;}
.toast-container{position:fixed;bottom:24px;right:24px;z-index:9999;}
/* Qty spinner */
.qty-ctrl{display:flex;align-items:center;gap:8px;background:#f4f8fd;border:1px solid #e1ecf8;border-radius:50px;padding:6px 16px;width:fit-content;}
.qty-ctrl button{background:none;border:none;font-size:1.2rem;font-weight:800;color:var(--blue);cursor:pointer;line-height:1;padding:0 4px;}
.qty-ctrl input{width:40px;text-align:center;border:none;background:transparent;font-weight:800;font-size:1rem;}
@media(max-width:768px){.book-info{padding:28px 22px;}.meta-grid{grid-template-columns:1fr;}.book-title{font-size:1.4rem;}}
</style>
</head>
<body>

<div class="top-bar">
  <div class="container"><marquee>🎓 Thư viện Số - Chi tiết học liệu và đăng ký mượn sách trực tuyến</marquee></div>
</div>

<header class="qnu-header">
  <div class="header-inner">
    <div class="brand" onclick="location.href='index.php'">
      <img src="images/qnu_logo.png" alt="Logo" onerror="this.style.display='none'">
      <div>
        <div class="uni-name">Thư viện Số</div>
        <div class="lib-name">Trung tâm số và Học liệu</div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <?php if(isset($_SESSION['username']) && ($_SESSION['role']??'')==='student'): ?>
      <!-- NÚT GIỎ MƯỢN -->
      <a href="cart.php" class="btn btn-warning btn-sm rounded-pill cart-btn-wrapper px-3">
        <i class="bi bi-basket3-fill me-1"></i>Giỏ mượn
        <?php if($cart_count>0): ?>
          <span class="cart-badge" id="cart-badge"><?php echo $cart_count; ?></span>
        <?php else: ?>
          <span class="cart-badge" id="cart-badge" style="display:none">0</span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if(isset($_SESSION['username'])): ?>
        <span class="fw-bold text-primary me-1 small"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="logout.php" class="btn btn-sm btn-danger rounded-pill px-3">Thoát</a>
      <?php else: ?>
        <a href="index.php" class="btn btn-sm btn-primary rounded-pill px-3">Đăng nhập</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<section class="hero">
  <h2>CHI TIẾT TÀI LIỆU</h2>
  <p class="mb-0">Thông tin đầy đủ về sách và tình trạng trong kho</p>
</section>

<main class="detail-wrapper">
  <div class="detail-card">
    <div class="row g-0">
      <!-- ẢNH BÌA -->
      <div class="col-lg-4">
        <div class="book-cover-box">
          <img src="<?php echo htmlspecialchars($cover); ?>"
               alt="<?php echo htmlspecialchars($book['title']); ?>"
               class="book-cover"
               onerror="this.src='images/default-book.png'">

          <!-- Tình trạng ebook -->
          <?php if(($book['ebook_available']??0)==1 && !empty($book['ebook_url'])): ?>
          <div class="mt-3">
            <span class="badge bg-success px-3 py-2 rounded-pill">
              <i class="bi bi-book-half me-1"></i>Có bản điện tử
            </span>
          </div>
          <?php endif; ?>

          <!-- Số lượng kho -->
          <div class="mt-3">
            <span class="badge <?php echo $book['quantity']>0 ? 'bg-primary' : 'bg-secondary'; ?> px-3 py-2 rounded-pill">
              <i class="bi bi-archive me-1"></i>Kho: <?php echo (int)$book['quantity']; ?> cuốn
            </span>
          </div>
        </div>
      </div>

      <!-- THÔNG TIN -->
      <div class="col-lg-8">
        <div class="book-info">
          <!-- TRẠNG THÁI -->
          <div class="mb-3">
            <?php if((int)$book['quantity']>0): ?>
              <span class="status-badge available"><i class="bi bi-check-circle-fill"></i> Còn sách</span>
            <?php elseif((int)($book['ebook_available']??0)===1): ?>
              <span class="status-badge" style="background:#e6f7ff;color:#055;"><i class="bi bi-book-half"></i> Chỉ có bản điện tử</span>
            <?php else: ?>
              <span class="status-badge unavailable"><i class="bi bi-x-circle-fill"></i> Hết sách</span>
            <?php endif; ?>
          </div>

          <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>
          <div class="book-author"><i class="bi bi-person-fill"></i> Tác giả: <?php echo htmlspecialchars($book['author']??'Chưa cập nhật'); ?></div>

          <!-- METADATA -->
          <div class="meta-grid">
            <div class="meta-item">
              <div class="meta-label">Mã sách</div>
              <div class="meta-value">#<?php echo htmlspecialchars($book['id']); ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Thể loại</div>
              <div class="meta-value"><?php echo htmlspecialchars($book['category']??'Chưa cập nhật'); ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Năm xuất bản</div>
              <div class="meta-value"><?php echo !empty($book['publish_year'])?htmlspecialchars($book['publish_year']):'Chưa cập nhật'; ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Số trang</div>
              <div class="meta-value"><?php echo !empty($book['pages'])?htmlspecialchars($book['pages']).' trang':'Chưa cập nhật'; ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Ngôn ngữ</div>
              <div class="meta-value"><?php echo !empty($book['language'])?htmlspecialchars($book['language']):'Tiếng Việt'; ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Số lượng còn</div>
              <div class="meta-value"><?php echo (int)$book['quantity']; ?> cuốn</div>
            </div>
          </div>

          <!-- MÔ TẢ -->
          <div class="description-box">
            <h5><i class="bi bi-journal-text"></i> Nội dung chính</h5>
            <p><?php echo !empty($book['description']) ? nl2br(htmlspecialchars($book['description'])) : 'Nội dung chính của tài liệu này chưa được cập nhật. Vui lòng liên hệ thủ thư để biết thêm thông tin.'; ?></p>
          </div>

          <!-- CHỌN SỐ LƯỢNG (khi còn sách) -->
          <?php if((int)$book['quantity']>0 && isset($_SESSION['username']) && $_SESSION['role']==='student'): ?>
          <div class="mt-4">
            <label class="small fw-bold text-muted mb-2">Số lượng muốn thêm vào giỏ</label>
            <div class="qty-ctrl">
              <button type="button" onclick="changeQty(-1)">−</button>
              <input type="number" id="qty-input" value="1" min="1" max="<?php echo (int)$book['quantity']; ?>" readonly>
              <button type="button" onclick="changeQty(1)">+</button>
              <span class="small text-muted ms-2">/ tối đa <?php echo (int)$book['quantity']; ?></span>
            </div>
          </div>
          <?php endif; ?>

          <!-- NÚT HÀNH ĐỘNG -->
          <div class="action-box">
            <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i>Quay lại</a>

            <?php if((int)$book['quantity']>0): ?>
              <?php if(isset($_SESSION['username']) && $_SESSION['role']==='student'): ?>
                <!-- NÚT MượN VẬT LÍ ĐƠN LẺ -->
                <a href="borrow.php?id=<?php echo $book['id']; ?>" class="btn-borrow">
                  <i class="bi bi-bag-check-fill"></i>Mượn vật lí
                </a>
                <!-- NÚT THÊM VÀO GIỎ (MỚI) -->
                <button class="btn-cart" onclick="addToCartDetail(<?php echo $book['id']; ?>)">
                  <i class="bi bi-basket-plus-fill"></i>Thêm vào giỏ
                </button>
              <?php elseif(isset($_SESSION['username'])): ?>
                <button class="btn-borrow" onclick="alert('Chỉ tài khoản sinh viên mới được mượn sách.')">
                  <i class="bi bi-bag-check-fill"></i>Mượn sách vật lí
                </button>
              <?php else: ?>
                <a href="borrow.php?id=<?php echo $book['id']; ?>" class="btn-borrow">
                  <i class="bi bi-box-arrow-in-right"></i>Đăng nhập để mượn
                </a>
              <?php endif; ?>
            <?php else: ?>
              <button class="btn-borrow" style="background:#9ca3af;box-shadow:none;" disabled>
                <i class="bi bi-x-circle-fill"></i>Hết sách vật lí
              </button>
            <?php endif; ?>

            <?php if(($book['ebook_available']??0)==1 && !empty($book['ebook_url'])): ?>
              <a href="<?php echo htmlspecialchars($book['ebook_url']); ?>" target="_blank" class="btn-borrow" style="background:linear-gradient(135deg,#16a34a,#22c55e);">
                <i class="bi bi-book-half"></i>Đọc sách điện tử
              </a>
            <?php else: ?>
              <button class="btn-borrow" style="background:#9ca3af;box-shadow:none;" disabled>
                <i class="bi bi-book-half"></i>Chưa có bản điện tử
              </button>
            <?php endif; ?>
          </div>

          <!-- LINK ĐẾN GIỎ -->
          <?php if(isset($_SESSION['role']) && $_SESSION['role']==='student'): ?>
          <div class="mt-3 p-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
            <small class="text-muted">
              <i class="bi bi-lightbulb-fill text-warning me-1"></i>
              Thêm nhiều sách vào giỏ rồi <a href="cart.php" class="fw-bold text-warning">thanh toán một lần qua QR</a> — tiện hơn mượn từng cuốn!
            </small>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</main>

<footer>Copyright © 2026 Trung tâm Số và Học liệu - Trường Đại học Quy Nhơn</footer>

<!-- TOAST -->
<div class="toast-container">
  <div id="cartToast" class="toast align-items-center border-0" role="alert" style="min-width:240px;">
    <div class="d-flex">
      <div class="toast-body fw-bold" id="cartToastMsg">✅ Đã thêm vào giỏ!</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const MAX_QTY = <?php echo (int)$book['quantity']; ?>;

function changeQty(delta) {
    const el = document.getElementById('qty-input');
    if (!el) return;
    let val = parseInt(el.value) + delta;
    val = Math.max(1, Math.min(val, MAX_QTY));
    el.value = val;
}

function addToCartDetail(bookId) {
    const qtyEl = document.getElementById('qty-input');
    const qty = qtyEl ? parseInt(qtyEl.value) : 1;

    const fd = new FormData();
    fd.append('ajax_cart', '1');
    fd.append('book_id',   bookId);
    fd.append('quantity',  qty);

    fetch('detail.php?id=<?php echo $book_id; ?>', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
            if (d.need_login) { location.href='login.php?redirect=detail.php?id=<?php echo $book_id; ?>'; return; }
            if (d.ok) {
                const badge = document.getElementById('cart-badge');
                if (badge) { badge.innerText = d.count; badge.style.display='flex'; }

                const toastEl = document.getElementById('cartToast');
                document.getElementById('cartToastMsg').innerHTML = `✅ Đã thêm <b>${qty} cuốn</b> vào giỏ!`;
                toastEl.className = 'toast align-items-center text-bg-success border-0';
                bootstrap.Toast.getOrCreateInstance(toastEl, {delay:2500}).show();
            } else {
                alert('❌ ' + (d.msg || 'Có lỗi xảy ra.'));
            }
        })
        .catch(() => alert('Không thể kết nối máy chủ.'));
}
</script>
</body>
</html>
