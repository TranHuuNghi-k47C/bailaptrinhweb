<?php
/**
 * verify_email.php
 * Sinh viên click link trong email → tài khoản được xác thực
 */
session_start();
require_once 'config.php';

$msg   = '';
$type  = 'danger';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    $msg = 'Link xác thực không hợp lệ.';
} else {
    $stmt = $pdo->prepare("
        SELECT id, username, email_verified, token_expires_at
        FROM Users
        WHERE verify_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $msg = 'Link xác thực không đúng hoặc đã được dùng rồi.';
    } elseif ($user['email_verified']) {
        $msg  = 'Email của bạn đã được xác thực trước đó. Bạn có thể đăng nhập bình thường!';
        $type = 'success';
    } elseif ($user['token_expires_at'] && strtotime($user['token_expires_at']) < time()) {
        $msg = 'Link xác thực đã hết hạn (24 giờ). Vui lòng yêu cầu gửi lại.';
    } else {
        // Xác thực thành công
        $pdo->prepare("
            UPDATE Users
            SET email_verified = 1, verify_token = NULL, token_expires_at = NULL
            WHERE id = ?
        ")->execute([$user['id']]);

        $msg  = 'Xác thực email thành công! Tài khoản của bạn đã được kích hoạt. Bạn có thể đăng nhập ngay.';
        $type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác thực Email | Thư viện Số QNU</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: Arial, sans-serif; }
        .card { border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
        .icon-big { font-size: 64px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="card p-5 text-center" style="max-width:480px; width:100%;">
        <div class="icon-big mb-3">
            <?php echo $type === 'success' ? '✅' : '❌'; ?>
        </div>
        <h4 class="fw-bold mb-3 text-<?php echo $type === 'success' ? 'success' : 'danger'; ?>">
            <?php echo $type === 'success' ? 'Xác thực thành công!' : 'Xác thực thất bại'; ?>
        </h4>
        <div class="alert alert-<?php echo $type; ?> text-start">
            <?php echo htmlspecialchars($msg); ?>
        </div>
        <a href="login.php" class="btn btn-primary rounded-pill px-5 mt-2">Đăng nhập</a>
        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-5 mt-2 ms-2">Trang chủ</a>
    </div>
</body>
</html>
