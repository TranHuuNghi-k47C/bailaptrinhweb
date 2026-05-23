<?php
/**
 * login.php (phiên bản mới — có xác thực email)
 * Thay thế file login.php cũ bằng file này.
 * Tích hợp: kiểm tra email_verified + nút "Gửi lại email xác thực"
 */
session_start();
require_once 'config.php';
require_once 'email_helper.php'; // Cần để gửi lại email xác thực

$err      = '';
$warn     = ''; // Cảnh báo (chưa xác thực email)
$redirect = trim($_GET['redirect'] ?? '');

// ── XỬ LÝ GỬI LẠI EMAIL XÁC THỰC ────────────────────────────
if (isset($_GET['resend']) && isset($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    $stmt = $pdo->prepare("SELECT username, email, email_verified FROM Users WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($u && !$u['email_verified'] && $u['email']) {
        // Tạo token mới
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $pdo->prepare("UPDATE Users SET verify_token = ?, token_expires_at = ? WHERE id = ?")
            ->execute([$token, $expires, $uid]);
        sendVerifyEmail($u['email'], $u['username'], $token);
        $warn = "Đã gửi lại email xác thực tới <b>{$u['email']}</b>. Vui lòng kiểm tra hộp thư.";
    }
}

// ── XỬ LÝ ĐĂNG NHẬP ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = trim($_POST['redirect'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $err = 'Vui lòng nhập đầy đủ thông tin.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $login_ok = false;

        if ($user) {
            $dbPassword = $user['password'];
            if (password_verify($password, $dbPassword)) {
                $login_ok = true;
            } elseif ($password === $dbPassword) {
                // Nâng cấp hash cũ
                $login_ok = true;
                $hashed   = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE Users SET password = ? WHERE id = ?")
                    ->execute([$hashed, $user['id']]);
            }
        }

        if ($login_ok) {
            // ── KIỂM TRA XÁC THỰC EMAIL (chỉ áp dụng sinh viên) ──
            // Admin & librarian miễn kiểm tra để dễ quản trị
            if ($user['role'] === 'student') {
                $verified = isset($user['email_verified']) ? (int)$user['email_verified'] : 1;
                if ($verified === 0 && !empty($user['email'])) {
                    // Chưa xác thực → hiển thị cảnh báo, không cho vào
                    $warn = "Tài khoản chưa xác thực email. Vui lòng kiểm tra hộp thư <b>{$user['email']}</b> "
                          . "hoặc <a href='login.php?resend=1&uid={$user['id']}' class='alert-link'>gửi lại email xác thực</a>.";
                    goto show_form; // Không set session → không vào được
                }
            }

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Redirect an toàn
            $safeRedirect = '';
            if (!empty($redirect)) {
                $parts = parse_url($redirect);
                if (!isset($parts['scheme']) && !isset($parts['host']) && strpos($redirect, '//') === false) {
                    $safeRedirect = $redirect;
                }
            }
            if ($safeRedirect !== '') { header('Location: ' . $safeRedirect); exit; }

            if ($user['role'] === 'admin')     { header('Location: indexadmin.php');    exit; }
            if ($user['role'] === 'librarian') { header('Location: indexlibrarian.php'); exit; }
            header('Location: index.php'); exit;

        } else {
            $err = 'Tài khoản hoặc mật khẩu không chính xác.';
        }
    }
}

show_form:
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập | Thư viện Số QNU</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --qnu-blue:#0054a6; --qnu-gold:#ffc107; }
        body  { background:linear-gradient(135deg,#003d7a,#0077cc); min-height:100vh;
                display:flex; align-items:center; justify-content:center; font-family:'Segoe UI',Arial,sans-serif; }
        .login-card { background:white; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.25);
                      width:100%; max-width:440px; padding:40px; }
        .logo-area  { text-align:center; margin-bottom:28px; }
        .logo-area h5 { color:var(--qnu-blue); font-weight:900; text-transform:uppercase; font-size:1rem; margin:8px 0 0; }
        .logo-area small { color:#ce1126; font-size:.78rem; font-weight:600; }
        .form-control { border-radius:10px; padding:12px 16px; font-size:.9rem; }
        .form-control:focus { box-shadow:0 0 0 3px rgba(0,84,166,.15); border-color:var(--qnu-blue); }
        .btn-login  { background:linear-gradient(135deg,var(--qnu-blue),#007bff);
                      color:white; border:none; border-radius:10px; padding:13px;
                      font-weight:700; font-size:1rem; width:100%; }
        .btn-login:hover { opacity:.92; color:white; }
        .pass-toggle { cursor:pointer; color:#888; }
        .pass-toggle:hover { color:var(--qnu-blue); }
    </style>
</head>
<body>
<div class="login-card">

    <!-- LOGO -->
    <div class="logo-area">
        <img src="images/qnu_logo.png" alt="QNU" style="height:60px;" onerror="this.style.display='none'">
        <h5>Thư Viện Số QNU</h5>
        <small>TRUNG TÂM SỐ VÀ HỌC LIỆU</small>
    </div>

    <!-- CẢNH BÁO / LỖI -->
    <?php if ($err !== ''): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?php echo htmlspecialchars($err); ?>
        </div>
    <?php endif; ?>

    <?php if ($warn !== ''): ?>
        <div class="alert alert-warning py-2 mb-3 small">
            <i class="bi bi-envelope-exclamation-fill me-1"></i>
            <?php echo $warn; ?>
        </div>
    <?php endif; ?>

    <!-- FORM -->
    <form method="POST">
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

        <div class="mb-3">
            <label class="form-label small fw-bold text-muted">Tài khoản / Mã sinh viên</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-person-fill text-muted"></i></span>
                <input type="text" name="username" class="form-control" placeholder="Nhập mã sinh viên..."
                       autocomplete="username" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold text-muted">Mật khẩu</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-lock-fill text-muted"></i></span>
                <input type="password" name="password" id="passwordInput" class="form-control"
                       placeholder="Nhập mật khẩu..." autocomplete="current-password" required>
                <span class="input-group-text bg-white pass-toggle"
                      onclick="togglePass()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </span>
            </div>
        </div>

        <button type="submit" class="btn-login btn mb-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>ĐĂNG NHẬP
        </button>
    </form>

    <div class="text-center small text-muted">
        <a href="index.php" class="text-decoration-none text-primary">
            <i class="bi bi-arrow-left me-1"></i>Về trang chủ thư viện
        </a>
    </div>

    <hr class="my-3">
    <div class="small text-muted text-center" style="font-size:.75rem;">
        Sinh viên dùng <b>mã số sinh viên</b> làm tài khoản và mật khẩu mặc định.<br>
        Liên hệ thư viện: <b>shl@qnu.edu.vn</b> nếu gặp vấn đề đăng nhập.
    </div>
</div>

<script>
function togglePass() {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        ico.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        ico.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>
