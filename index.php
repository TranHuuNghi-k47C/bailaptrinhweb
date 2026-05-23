<?php

session_start();
require_once 'config.php';
$login_error = '';

// --- XỬ LÝ ĐĂNG NHẬP TỪ MODAL ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_action'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $login_ok = false;

    if ($user) {
        $dbPassword = $user['password'];

        // Trường hợp 1: mật khẩu đã được mã hóa bằng password_hash()
        if (password_verify($password, $dbPassword)) {
            $login_ok = true;
        }
        // Trường hợp 2: dữ liệu cũ vẫn còn lưu mật khẩu thường
        // Nếu đúng, cho đăng nhập và tự động mã hóa lại mật khẩu đó
        elseif ($password === $dbPassword) {
            $login_ok = true;

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE Users SET password = ? WHERE id = ?");
            $update->execute([$hashedPassword, $user['id']]);
        }
    }

    if ($login_ok) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        if ($user['role'] === 'admin') { header("Location: indexadmin.php"); exit; } 
        elseif ($user['role'] === 'librarian') { header("Location: indexlibrarian.php"); exit; } 
        else { header("Location: index.php"); exit; }
    } else {
        $login_error = "Tài khoản hoặc mật khẩu không chính xác!";
    }

}
$unread_count = 0;

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'student') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = (int)$stmt->fetchColumn();
}

// --- LẤY DỮ LIỆU SÁCH KÈM ĐIỂM ĐÁNH GIÁ TỪ DATABASE ---
$stmt = $pdo->query("
    SELECT b.*,
           ROUND(COALESCE(AVG(r.rating), 0), 1) AS avg_rating,
           COUNT(r.id) AS review_count
    FROM Books b
    LEFT JOIN Reviews r ON b.id = r.book_id
    GROUP BY b.id
    ORDER BY b.id DESC
");
$books_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($books_db as &$book) {
    $book['image_url'] = !empty($book['image_url']) && trim($book['image_url']) !== ''
        ? $book['image_url']
        : 'img/default.png';
}
unset($book);
$books_json = json_encode($books_db, JSON_UNESCAPED_UNICODE);

// Truyền trạng thái đăng nhập sang JS
$is_student = (isset($_SESSION['role']) && $_SESSION['role'] === 'student') ? 'true' : 'false';
?>

<!DOCTYPE html>
<html lang="vi" dir="ltr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <title>Thư viện Số| Hệ thống Tra cứu Học liệu Đại học</title>
    
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

        .qnu-header { background: white; border-top: 5px solid var(--qnu-blue); padding: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .university-name { color: var(--qnu-blue); font-weight: 900; font-size: 1.4rem; text-transform: uppercase; }
        .library-name { color: #ce1126; font-weight: 600; font-size: 1.1rem; }
        .top-marquee { background: var(--qnu-blue-dark); color: white; padding: 7px 0; font-size: 13px; border-bottom: 3px solid var(--qnu-gold); }

        .search-container { background: linear-gradient(135deg, var(--qnu-blue) 0%, #007bff 100%); padding: 60px 0; }
        .main-search-input { height: 65px; border-radius: 50px !important; border: none; padding-left: 30px; font-size: 1.15rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .btn-search-trigger { border-radius: 50px !important; padding: 0 40px; background: var(--qnu-gold); color: var(--qnu-blue-dark); font-weight: 800; border: none; transition: 0.3s; }

        .sidebar-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: var(--shadow); border: none; margin-bottom: 25px; }
        .sidebar-header { background: linear-gradient(to right, var(--qnu-blue-dark), var(--qnu-blue)); color: white; padding: 18px; font-weight: 700; text-transform: uppercase; font-size: 14px; }
        .list-group-item { padding: 14px 20px; border: none; font-size: 14px; cursor: pointer; transition: 0.3s; }
        .list-group-item.active { background-color: var(--qnu-blue) !important; color: white !important; }

        .book-card { background: white; border-radius: 16px; border: 1px solid rgba(0,0,0,0.06); transition: transform 0.28s ease, box-shadow 0.28s ease; cursor: pointer; height: 100%; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
        .book-card:hover { transform: translateY(-8px); box-shadow: 0 18px 40px rgba(0,0,0,0.10); border-color: rgba(0,84,166,0.12); }
        .book-img-box { aspect-ratio: 2/3; overflow: hidden; background: linear-gradient(180deg,#fbfdff,#f0f6ff); display:flex; align-items:center; justify-content:center; padding:8px; border-radius:12px; }
        .book-img-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.35s ease; border-radius:10px; }
        .book-card:hover .book-img-box img { transform: scale(1.02); }
        /* Author single-line truncation */
        .author-line { font-size: 12px; height: 20px; line-height: 20px; overflow: hidden; }
        .author-text { display: inline-block; max-width: 100%; vertical-align: middle; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .info-content-fade { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Style phân trang */
        .pagination .page-link { color: var(--qnu-blue); border-radius: 8px; margin: 0 3px; font-weight: 500; }
        .pagination .page-item.active .page-link { background-color: var(--qnu-blue); border-color: var(--qnu-blue); color: white; }
        .pagination .page-item.disabled .page-link { color: #6c757d; background-color: transparent; border-color: transparent; }

        footer { background: #0f172a; color: #fff; padding: 40px 0 0 0; border-top: 4px solid var(--qnu-gold); }
        .footer-container { max-width: 1140px; margin: 0 auto; padding: 0 15px; }
        .map-wrapper { width: 100%; height: 220px; border-radius: 10px; overflow: hidden; border: 1px solid #334155; margin-top: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }

        /* ===== RATING ===== */
        .star-mini { font-size: 11px; line-height: 1; }
        .star-mini .on  { color: var(--qnu-gold); }
        .star-mini .off { color: #ddd; }

        .btn-rate {
            font-size: 11px; padding: 2px 10px;
            border-radius: 20px;
            border: 1px solid var(--qnu-gold);
            color: #7a5800; background: #fff9e6;
            cursor: pointer; transition: background .2s;
            display: inline-block; margin-top: 4px;
        }
        .btn-rate:hover { background: var(--qnu-gold); color: #000; }

        /* Modal đánh giá */
        .review-modal-header {
            background: linear-gradient(135deg, var(--qnu-blue-dark), #1a7ce8);
            color: white; border: none; padding: 16px 20px;
        }
        .avg-box {
            background: linear-gradient(135deg, var(--qnu-blue), #1a7ce8);
            color: white; border-radius: 14px; padding: 16px 22px;
            text-align: center; min-width: 100px;
        }
        .avg-box .score { font-size: 2.4rem; font-weight: 900; line-height: 1; }
        .avg-box .out   { font-size: 11px; opacity: .75; margin-top: 2px; }

        .star-input { display: flex; gap: 4px; }
        .star-input .si {
            font-size: 28px; color: #ddd; cursor: pointer;
            transition: color .12s, transform .12s; line-height: 1; user-select: none;
        }
        .star-input .si.on    { color: var(--qnu-gold); }
        .star-input .si:hover { transform: scale(1.2); }

        .review-list { max-height: 360px; overflow-y: auto; padding-right: 4px; }
        .review-list::-webkit-scrollbar { width: 4px; }
        .review-list::-webkit-scrollbar-thumb { background: #cde; border-radius: 4px; }
        .review-item { border-bottom: 1px solid #f0f0f0; padding: 11px 0; }
        .review-item:last-child { border-bottom: none; }

        .bar-row { display:flex; align-items:center; gap:6px; font-size:11px; margin-bottom:3px; }
        .bar-track { flex:1; background:#f0f0f0; border-radius:9px; height:6px; overflow:hidden; }
        .bar-fill  { height:100%; background: var(--qnu-gold); border-radius:9px; }
    </style>
</head>
<body>

    <div class="top-marquee">
        <div class="container"><marquee behavior="scroll" direction="left">Chào mừng bạn đến với Thư viện Số</marquee></div>
    </div>

    <?php if($login_error != ''): ?>
        <div class="alert alert-danger text-center m-0 rounded-0" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $login_error; ?>
        </div>
    <?php endif; ?>

<header class="qnu-header sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        
        <!-- LOGO + TÊN WEB -->
        <div onclick="location.href='index.php'" style="cursor: pointer; display: flex; align-items: center;">
            <img src="images/qnu_logo.png" alt="Logo thư viện" style="height: 65px;">
            <div class="ms-3 d-none d-md-block">
                <h1 class="university-name m-0">Thư Viện Số</h1>
                <div class="library-name">TRUNG TÂM SỐ VÀ HỌC LIỆU</div>
            </div>
        </div>

        <!-- KHU VỰC ĐĂNG NHẬP / TÀI KHOẢN -->
        <div id="auth-zone">
            <?php if(!isset($_SESSION['username'])): ?>

                <button 
                    class="btn btn-primary btn-sm rounded-pill px-4" 
                    data-bs-toggle="modal" 
                    data-bs-target="#loginModal">
                    <i class="bi bi-box-arrow-in-right me-1"></i>
                    Đăng nhập
                </button>

            <?php else: ?>

                <div class="d-flex align-items-center flex-wrap gap-2">

                    <?php if(($_SESSION['role'] ?? '') === 'student'): ?>
                        <a href="notifications.php" 
                           class="btn btn-outline-warning btn-sm rounded-pill position-relative">
                            <i class="bi bi-bell-fill me-1"></i>
                            Thông báo

                            <?php if(isset($unread_count) && $unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <span class="fw-bold text-primary small">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>

                    <?php if(($_SESSION['role'] ?? '') === 'student'): ?>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="bi bi-bookmark me-1"></i>
                            Cá nhân
                        </a>
                    <?php elseif(($_SESSION['role'] ?? '') === 'librarian'): ?>
                        <a href="indexlibrarian.php" class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="bi bi-person-badge me-1"></i>
                            Trang thủ thư
                        </a>
                    <?php elseif(($_SESSION['role'] ?? '') === 'admin'): ?>
                        <a href="indexadmin.php" class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="bi bi-shield-lock me-1"></i>
                            Trang admin
                        </a>
                    <?php endif; ?>

                    <a href="logout.php" class="btn btn-danger btn-sm rounded-pill">
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Thoát
                    </a>

                </div>

            <?php endif; ?>
        </div>
    </div>
</header>

    <section class="search-container text-center" id="search-section">
        <div class="container">
            <h2 class="text-white fw-bold mb-4">HỆ THỐNG TRA CỨU TÀI LIỆU TRỰC TUYẾN</h2>
            <div class="input-group mx-auto shadow-lg" style="max-width: 850px; border-radius: 50px;">
                <input type="text" id="master-search-input" class="form-control main-search-input" placeholder="Tìm tên sách, tác giả, thể loại..." onkeyup="if(event.key === 'Enter') executeGlobalSearch()">
                <button class="btn btn-search-trigger" onclick="executeGlobalSearch()">TÌM KIẾM</button>
            </div>
        </div>
    </section>

    <main class="container py-5">
        <div class="row">
            <aside class="col-lg-3 mb-4">
                <div class="sidebar-card shadow-sm">
                    <div class="sidebar-header">Phân loại học liệu</div>
                    <div class="list-group list-group-flush" id="main-nav-group">
                        <button class="list-group-item list-group-item-action active" onclick="loadContent('all', this)">📚 Tất cả tài liệu</button>
                        <button class="list-group-item list-group-item-action" onclick="loadContent('physical', this)">📘 Tài liệu vật lí</button>
                        <button class="list-group-item list-group-item-action" onclick="loadContent('ebook', this)">📱 Tài liệu điện tử</button>
                        <button class="list-group-item list-group-item-action" onclick="showStaticInfo('rules', this)">📜 Nội quy</button>
                    </div>
                </div>

                <div class="sidebar-card p-4 text-center mt-4">
                    <h6 class="fw-bold text-primary mb-3">HỖ TRỢ TRỰC TUYẾN</h6>
                    <a href="https://m.me/qnu" target="_blank" class="btn btn-sm btn-info w-100 text-white rounded-pill"><i class="bi bi-chat-dots-fill"></i> Chat với Thư viện</a>
                </div>

                <div class="sidebar-card p-3 mt-4 text-center">
                    <img src="img/fillblank.png" alt="Khuyến mãi" class="img-fluid rounded shadow-sm" style="max-width:100%; height:auto;">
                </div>
            </aside>

            <section class="col-lg-9">
                <div id="dynamic-workspace"></div>
            </section>
        </div>
    </main>

    <footer>
        <div class="footer-container">
            <div style="margin-bottom: 10px;">
                <h6 style="font-weight: 700; letter-spacing: 1px; margin-bottom: 15px;">THÔNG TIN LIÊN HỆ</h6>
                <ul style="list-style: none; padding: 0; font-size: 14px; line-height: 1.8; opacity: 0.8;">
                    <li><i class="bi bi-geo-alt-fill me-2 text-warning"></i> Phòng 121 Nhà 15 tầng, Trường Đại học Quy Nhơn</li>
                    <li><i class="bi bi-envelope-fill me-2 text-warning"></i> shl@qnu.edu.vn</li>
                    <li><i class="bi bi-telephone-fill me-2 text-warning"></i> (84-256) 3846156</li>
                </ul>
            </div>
        </div>
        <div style="background: #000; text-align: center; padding: 15px 0; font-size: 12px; color: #666; margin-top: 30px;">
            Copyright © 2026 TRUNG TÂM SỐ VÀ HỌC LIỆU - ĐH QUY NHƠN
        </div>
    </footer>

    <!-- MODAL ĐĂNG NHẬP (giữ nguyên) -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content p-4">
                <h5 class="fw-bold text-primary mb-3 text-center">ĐĂNG NHẬP</h5>
                <form method="POST" action="index.php">
                    <input type="hidden" name="login_action" value="1">
                    <input type="text" name="username" class="form-control mb-3 rounded-pill" placeholder="Mã SV / Admin" required>
                    <input type="password" name="password" class="form-control mb-3 rounded-pill" placeholder="Mật khẩu" required>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">XÁC NHẬN</button>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL ĐÁNH GIÁ -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:18px; overflow:hidden; border:none;">

                <div class="modal-header review-modal-header">
                    <div class="flex-grow-1" style="min-width:0;">
                        <div style="font-size:11px; opacity:.75; margin-bottom:3px;">
                            <i class="bi bi-star-fill me-1"></i>ĐÁNH GIÁ TÀI LIỆU
                        </div>
                        <h6 class="modal-title fw-bold text-white m-0"
                            id="reviewModalTitle"
                            style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></h6>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-3 flex-shrink-0"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">

                    <!-- Tóm tắt điểm -->
                    <div class="d-flex align-items-center gap-4 mb-4 p-3 rounded-3"
                         style="background:#f4f8ff; border:1px solid #d6e8ff;">
                        <div class="avg-box">
                            <div class="score" id="modal-avg-score">—</div>
                            <div class="out">/ 5.0</div>
                        </div>
                        <div class="flex-grow-1">
                            <div id="modal-avg-stars" style="font-size:20px; letter-spacing:2px;" class="mb-1"></div>
                            <div class="text-muted small mb-2" id="modal-total-reviews">0 đánh giá</div>
                            <div id="modal-dist-bars"></div>
                        </div>
                    </div>

                    <!-- Form đánh giá (sinh viên) -->
                    <div id="review-form-zone" class="mb-4"></div>

                    <!-- Danh sách nhận xét -->
                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">
                        <i class="bi bi-chat-square-text me-1"></i>Nhận xét từ người dùng
                    </h6>
                    <div class="review-list" id="reviews-list">
                        <div class="text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script type="text/javascript">
        // =============================================
        // 1. CHUẨN BỊ DỮ LIỆU
        // =============================================
        const rawData = <?php echo $books_json; ?>;
        const QNU_DATABASE = rawData.map(book => ({
            id: book.id,
            category: book.category || 'Khác',
            title: book.title,
            author: book.author,
            quantity: parseInt(book.quantity || 0),
            ebook_available: parseInt(book.ebook_available || 0),
            ebook_url: book.ebook_url || '',
            img: book.image_url && book.image_url.trim() !== ''
                ? book.image_url
                : 'img/default.png',
            avg_rating:   parseFloat(book.avg_rating  || 0),
            review_count: parseInt(book.review_count  || 0),
        }));

        const IS_STUDENT = <?php echo $is_student; ?>;

        // =============================================
        // 2. BIẾN PHÂN TRANG (PAGINATION)
        // =============================================
        const itemsPerPage = 16;
        let currentPage = 1;
        let currentFilteredData = [];

        // =============================================
        // 3. KHỞI TẠO KHUNG LÀM VIỆC
        // =============================================
        function initWorkspace(title) {
            document.getElementById('dynamic-workspace').innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold m-0 text-primary">${title}</h4>
                    <span class="badge bg-primary rounded-pill px-3 py-2" id="item-count">0 tài liệu</span>
                </div>
                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-4" id="render-target"></div>
                <div id="pagination-container" class="mt-5"></div>
            `;
        }

        // =============================================
        // 4. LỌC THEO THỂ LOẠI
        // =============================================
        function loadContent(type, btn) {
            document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');

            let title = 'TẤT CẢ TÀI LIỆU';
            if (type === 'physical') title = 'TÀI LIỆU VẬT LÍ';
            else if (type === 'ebook') title = 'TÀI LIỆU ĐIỆN TỬ';
            initWorkspace(title);
            
            if (type === 'all') {
                currentFilteredData = QNU_DATABASE;
            } else if (type === 'physical') {
                currentFilteredData = QNU_DATABASE.filter(x => x.quantity > 0);
            } else if (type === 'ebook') {
                currentFilteredData = QNU_DATABASE.filter(x => x.ebook_available === 1 && x.ebook_url.trim() !== '');
            } else {
                currentFilteredData = QNU_DATABASE;
            }

            currentPage = 1;
            renderLibrary();
        }

        // =============================================
        // 5. TÌM KIẾM SÁCH
        // =============================================
        function executeGlobalSearch() {
            const kw = document.getElementById('master-search-input').value.toLowerCase();
            document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));
            initWorkspace("KẾT QUẢ TÌM KIẾM: '" + kw + "'");
            
            currentFilteredData = QNU_DATABASE.filter(b => 
                b.title.toLowerCase().includes(kw) || 
                b.author.toLowerCase().includes(kw) || 
                b.category.toLowerCase().includes(kw)
            );
            currentPage = 1;
            renderLibrary();
        }

        // =============================================
        // 6. HELPER: VẼ SAO (chỉ đọc)
        // =============================================
        function starsHtml(avg, size) {
            size = size || 12;
            let h = '<span class="star-mini">';
            for (let i = 1; i <= 5; i++) {
                h += `<span class="${avg >= i - 0.25 ? 'on' : 'off'}" style="font-size:${size}px;">★</span>`;
            }
            return h + '</span>';
        }

        // =============================================
        // 7. RENDER SÁCH (CHỈ RENDER THEO SỐ TRANG HIỆN TẠI)
        // =============================================
        function renderLibrary() {
            const target = document.getElementById('render-target');
            document.getElementById('item-count').innerText = currentFilteredData.length + " tài liệu";
            
            if (currentFilteredData.length === 0) {
                target.innerHTML = `<div class="col-12 text-center text-muted mt-5"><i class="bi bi-inbox fs-1"></i><p>Không tìm thấy tài liệu phù hợp.</p></div>`;
                document.getElementById('pagination-container').innerHTML = '';
                return;
            }

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageData = currentFilteredData.slice(startIndex, endIndex);

            target.innerHTML = pageData.map(i => {
                const titleSafe = i.title.replace(/'/g, "\\'");

                let statusHtml = i.quantity > 0
                    ? `<span class="badge bg-success">Còn ${i.quantity}</span>`
                    : (i.ebook_available == 1
                        ? `<span class="badge bg-info text-white">Chỉ có bản điện tử</span>`
                        : `<span class="badge bg-danger">Đã hết</span>`);

                let borrowBtn = i.quantity > 0
                    ? `<a href="borrow.php?id=${i.id}" 
                          onclick="event.stopPropagation();" 
                          class="btn btn-sm btn-primary w-100 rounded-pill mt-auto">
                          Mượn sách vật lí
                       </a>` 
                    : (i.ebook_available == 1 && i.ebook_url && i.ebook_url.trim() !== '' ? '' : `<button 
                          class="btn btn-sm btn-secondary w-100 rounded-pill mt-auto" 
                          onclick="event.stopPropagation();" 
                          disabled>
                          Hết sách vật lí
                       </button>`);

                let ebookBtn = (i.ebook_available == 1 && i.ebook_url && i.ebook_url.trim() !== '')
                    ? `<a href="${i.ebook_url}" 
                          target="_blank"
                          onclick="event.stopPropagation();" 
                          class="btn btn-sm btn-success w-100 rounded-pill mt-2">
                          Đọc ebook
                       </a>`
                    : '';

                // --- RATING MINI ---
                const ratingMini = i.review_count > 0
                    ? `${starsHtml(i.avg_rating, 12)}
                       <span class="text-muted" style="font-size:10px;">
                           ${i.avg_rating.toFixed(1)} (${i.review_count})
                       </span>`
                    : `<span class="star-mini">
                           <span class="off" style="font-size:12px;">★★★★★</span>
                       </span>
                       <span class="text-muted" style="font-size:10px;">Chưa có đánh giá</span>`;

                return `
                    <div class="col info-content-fade d-flex">
                        <div class="book-card shadow-sm w-100 p-2" 
                             onclick="window.location.href='detail.php?id=${i.id}'" 
                             style="cursor:pointer;">
                             
                            <div class="book-img-box rounded">
                                <img 
                                    src="${i.img || 'img/default.png'}"
                                    alt="${i.title}"
                                    onerror="this.onerror=null;this.src='img/default.png';"
                                    class="rounded">
                            </div>

                            <div class="p-2 d-flex flex-column flex-grow-1">
                                <div class="fw-bold small mb-1 book-title" 
                                     style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 38px;" 
                                     title="${i.title}">
                                    ${i.title}
                                </div>

                                <div class="text-muted mb-1 author-line" style="font-size: 12px;">
                                    Tác giả: <span class="author-text">${i.author}</span>
                                </div>

                                <!-- RATING MINI (thêm mới) -->
                                <div class="d-flex align-items-center gap-1 mb-2" style="flex-wrap:wrap;">
                                    ${ratingMini}
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    ${statusHtml}
                                    <span class="badge bg-light text-dark border" style="font-size: 10px;">
                                        ${i.category}
                                    </span>
                                </div>

                                <div class="d-grid gap-2" style="margin-top:auto;">
                                    ${borrowBtn}
                                    ${ebookBtn}
                                    <!-- NÚT ĐÁNH GIÁ (thêm mới) -->
                                    <button class="btn-rate w-100"
                                            onclick="openReviewModal(${i.id}, '${titleSafe}', event)">
                                        <i class="bi bi-star me-1"></i>Xem & Đánh giá
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            renderPagination();
        }

        // =============================================
        // 8. VẼ THANH CHUYỂN TRANG BÊN DƯỚI
        // =============================================
        function renderPagination() {
            const totalPages = Math.ceil(currentFilteredData.length / itemsPerPage);
            const pagContainer = document.getElementById('pagination-container');
            
            if (totalPages <= 1) {
                pagContainer.innerHTML = ''; return;
            }

            let html = `<nav><ul class="pagination justify-content-center mb-0">`;
            
            html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link shadow-sm" href="javascript:void(0)" onclick="changePage(${currentPage - 1})"><i class="bi bi-chevron-left"></i> Trước</a></li>`;
            
            const range = 2;
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - range && i <= currentPage + range)) {
                    html += `<li class="page-item ${currentPage === i ? 'active' : ''}"><a class="page-link shadow-sm" href="javascript:void(0)" onclick="changePage(${i})">${i}</a></li>`;
                } else if (i === currentPage - range - 1 || i === currentPage + range + 1) {
                    html += `<li class="page-item disabled"><a class="page-link shadow-sm" href="javascript:void(0)">...</a></li>`;
                }
            }
            
            html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link shadow-sm" href="javascript:void(0)" onclick="changePage(${currentPage + 1})">Sau <i class="bi bi-chevron-right"></i></a></li>`;
            
            html += `</ul></nav>`;
            pagContainer.innerHTML = html;
        }

        // =============================================
        // 9. HÀM CHUYỂN TRANG
        // =============================================
        function changePage(page) {
            currentPage = page;
            renderLibrary();
            document.getElementById('search-section').scrollIntoView({ behavior: 'smooth' });
        }

        // =============================================
        // 10. NỘI QUY TĨNH (giữ nguyên)
        // =============================================
        function showStaticInfo(type, btn) {
            document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('dynamic-workspace').innerHTML = `
                <div class="info-content-fade">
                    <h4 class="fw-bold text-primary mb-4">NỘI QUY MƯỢN TRẢ</h4>
                    <div class="card border-0 shadow-sm p-4 bg-white" style="border-radius:15px;">
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <h6 class="text-danger fw-bold"><i class="bi bi-book-half"></i> Mượn tại chỗ</h6>
                                <p class="small text-muted">Xuất trình thẻ Sinh viên khi mượn. Tối đa 3 cuốn mỗi lần.</p>
                            </div>
                            <div class="col-md-6 ps-md-4">
                                <h6 class="text-success fw-bold"><i class="bi bi-house-door"></i> Mượn về nhà</h6>
                                <p class="small text-muted">Hỗ trợ cho giáo trình học tập. Phạt quá hạn 2.000 VNĐ/ngày.</p>
                            </div>
                        </div>
                    </div>
                </div>`;
        }

        // =============================================
        // 11. MỞ MODAL ĐÁNH GIÁ
        // =============================================
        let _currentBookId = null;
        let _selectedStar  = 0;

        function openReviewModal(bookId, bookTitle, event) {
            event.stopPropagation();
            _currentBookId = bookId;
            _selectedStar  = 0;

            document.getElementById('reviewModalTitle').innerText     = bookTitle;
            document.getElementById('modal-avg-score').innerText      = '…';
            document.getElementById('modal-avg-stars').innerHTML      = '';
            document.getElementById('modal-total-reviews').innerText  = 'Đang tải...';
            document.getElementById('modal-dist-bars').innerHTML      = '';
            document.getElementById('review-form-zone').innerHTML     = '';
            document.getElementById('reviews-list').innerHTML         =
                '<div class="text-center text-muted py-4"><div class="spinner-border spinner-border-sm"></div></div>';

            new bootstrap.Modal(document.getElementById('reviewModal')).show();
            fetchReviews(bookId);
        }

        // =============================================
        // 12. TẢI ĐÁNH GIÁ TỪ SERVER
        // =============================================
        function fetchReviews(bookId) {
            fetch('review_action.php?action=get&book_id=' + bookId)
                .then(r => r.json())
                .then(data => {
                    const avg   = parseFloat(data.avg_rating) || 0;
                    const total = parseInt(data.total) || 0;

                    document.getElementById('modal-avg-score').innerText    = avg > 0 ? avg.toFixed(1) : '—';
                    document.getElementById('modal-avg-stars').innerHTML    = starsHtml(avg, 20);
                    document.getElementById('modal-total-reviews').innerText = total + ' đánh giá';

                    // Thanh phân bổ sao
                    const dist = data.distribution || {};
                    let barsHtml = '';
                    for (let s = 5; s >= 1; s--) {
                        const cnt = parseInt(dist[s] || 0);
                        const pct = total > 0 ? Math.round(cnt / total * 100) : 0;
                        barsHtml += `
                            <div class="bar-row">
                                <span style="color:var(--qnu-gold); width:16px; text-align:right;">${s}★</span>
                                <div class="bar-track"><div class="bar-fill" style="width:${pct}%;"></div></div>
                                <span class="text-muted" style="width:22px;">${cnt}</span>
                            </div>`;
                    }
                    document.getElementById('modal-dist-bars').innerHTML = barsHtml;

                    // Form đánh giá
                    buildForm(data.my_review);

                    // Danh sách nhận xét
                    buildList(data.reviews);
                })
                .catch(() => {
                    document.getElementById('reviews-list').innerHTML =
                        '<div class="text-center text-danger small py-3"><i class="bi bi-wifi-off me-1"></i>Không thể tải đánh giá.</div>';
                });
        }

        // =============================================
        // 13. DỰNG FORM GỬI ĐÁNH GIÁ
        // =============================================
        function buildForm(myReview) {
            const zone = document.getElementById('review-form-zone');
            if (!IS_STUDENT) {
                zone.innerHTML = `
                    <div class="text-center text-muted small py-2 rounded-3"
                         style="background:#f8f9fa; border:1px dashed #ccc;">
                        <i class="bi bi-info-circle me-1"></i>
                        Đăng nhập bằng tài khoản <strong>sinh viên</strong> để viết đánh giá.
                    </div>`;
                return;
            }

            _selectedStar = myReview ? parseInt(myReview.rating) : 0;
            const myComment = myReview ? (myReview.comment || '') : '';
            const LABELS = ['','Rất tệ','Tệ','Bình thường','Tốt','Xuất sắc'];

            zone.innerHTML = `
                <div class="p-3 rounded-3" style="background:#fffdf3; border:1px solid #ffe082;">
                    <div class="fw-bold mb-2" style="color:#7a5800; font-size:13px;">
                        <i class="bi bi-pencil-square me-1"></i>
                        ${myReview ? 'CẬP NHẬT ĐÁNH GIÁ CỦA BẠN' : 'VIẾT ĐÁNH GIÁ CỦA BẠN'}
                    </div>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="star-input" id="input-stars">
                            ${[1,2,3,4,5].map(s =>
                                `<span class="si ${s <= _selectedStar ? 'on' : ''}"
                                       onmouseover="hoverStar(${s})"
                                       onmouseout="unhoverStar()"
                                       onclick="selectStar(${s})">★</span>`
                            ).join('')}
                        </div>
                        <span id="star-label" class="small text-muted">
                            ${_selectedStar > 0 ? LABELS[_selectedStar] : 'Chọn số sao'}
                        </span>
                    </div>
                    <textarea id="review-comment" class="form-control mb-2 rounded-3" rows="2"
                              placeholder="Nhận xét ngắn về tài liệu này (tùy chọn)..."
                              style="resize:none; font-size:13px;">${escHtml(myComment)}</textarea>
                    <button onclick="submitReview()"
                            class="btn btn-warning btn-sm rounded-pill fw-bold px-4">
                        <i class="bi bi-send me-1"></i>Gửi đánh giá
                    </button>
                </div>`;
        }

        // =============================================
        // 14. DỰNG DANH SÁCH NHẬN XÉT
        // =============================================
        function buildList(reviews) {
            const el = document.getElementById('reviews-list');
            if (!reviews || reviews.length === 0) {
                el.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-chat-square fs-2 d-block mb-2 opacity-25"></i>
                        Chưa có đánh giá nào. Hãy là người đầu tiên!
                    </div>`;
                return;
            }
            const LABELS = ['','Rất tệ','Tệ','Bình thường','Tốt','Xuất sắc'];
            el.innerHTML = reviews.map(rv => `
                <div class="review-item">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <strong class="small">
                                <i class="bi bi-person-circle me-1 text-primary"></i>${escHtml(rv.username)}
                            </strong>
                            <span class="badge rounded-pill ms-1"
                                  style="background:#fff3cd; color:#856404; font-size:10px;">
                                ${LABELS[rv.rating] || ''}
                            </span>
                        </div>
                        <span class="text-muted" style="font-size:11px; white-space:nowrap;">
                            ${rv.created_at ? rv.created_at.substring(0,10) : ''}
                        </span>
                    </div>
                    <div class="mb-1">${starsHtml(rv.rating, 12)}</div>
                    ${rv.comment
                        ? `<div class="small text-secondary" style="line-height:1.5;">${escHtml(rv.comment)}</div>`
                        : ''}
                </div>`).join('');
        }

        // =============================================
        // 15. TƯƠNG TÁC SAO CHỌN
        // =============================================
        const LABELS = ['Chọn số sao','Rất tệ','Tệ','Bình thường','Tốt','Xuất sắc'];

        function hoverStar(val) {
            document.querySelectorAll('#input-stars .si').forEach((s, i) => {
                s.style.color = i < val ? 'var(--qnu-gold)' : '#ddd';
            });
            const lbl = document.getElementById('star-label');
            if (lbl) lbl.innerText = LABELS[val];
        }

        function unhoverStar() {
            document.querySelectorAll('#input-stars .si').forEach((s, i) => {
                s.style.color = i < _selectedStar ? 'var(--qnu-gold)' : '#ddd';
            });
            const lbl = document.getElementById('star-label');
            if (lbl) lbl.innerText = LABELS[_selectedStar] || 'Chọn số sao';
        }

        function selectStar(val) {
            _selectedStar = val;
            document.querySelectorAll('#input-stars .si').forEach((s, i) => {
                s.classList.toggle('on', i < val);
                s.style.color = i < val ? 'var(--qnu-gold)' : '#ddd';
            });
            const lbl = document.getElementById('star-label');
            if (lbl) lbl.innerText = LABELS[val];
        }

        // =============================================
        // 16. GỬI ĐÁNH GIÁ
        // =============================================
        function submitReview() {
            if (_selectedStar < 1) { alert('Vui lòng chọn ít nhất 1 sao!'); return; }
            const commentEl = document.getElementById('review-comment');
            const fd = new FormData();
            fd.append('action',  'submit');
            fd.append('book_id', _currentBookId);
            fd.append('rating',  _selectedStar);
            fd.append('comment', commentEl ? commentEl.value.trim() : '');

            fetch('review_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        // Cập nhật dữ liệu local để card sách phản ánh ngay
                        const book = QNU_DATABASE.find(b => b.id == _currentBookId);
                        if (book) {
                            book.avg_rating   = parseFloat(data.avg_rating);
                            book.review_count = parseInt(data.total);
                        }
                        fetchReviews(_currentBookId); // tải lại modal
                        renderLibrary();              // cập nhật card
                    } else {
                        alert(data.message || 'Có lỗi xảy ra, vui lòng thử lại.');
                    }
                })
                .catch(() => alert('Không thể kết nối đến máy chủ.'));
        }

        // =============================================
        // 17. TIỆN ÍCH
        // =============================================
        function escHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // =============================================
        // 18. KHỞI TẠO
        // =============================================
        window.onload = () => {
            loadContent('all', document.querySelector('.list-group-item'));
            <?php if($login_error != ''): ?>
                var myModal = new bootstrap.Modal(document.getElementById('loginModal'));
                myModal.show();
            <?php endif; ?>
        };
    </script>
</body>
</html>