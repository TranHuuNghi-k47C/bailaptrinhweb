<?php
session_start();
require_once 'config.php';

$err = '';

$redirect = trim($_GET['redirect'] ?? '');

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

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Validate redirect to avoid open redirects: allow only relative paths without scheme
            $safeRedirect = '';
            if (!empty($redirect)) {
                $parts = parse_url($redirect);
                if (!isset($parts['scheme']) && !isset($parts['host']) && strpos($redirect, '//') === false) {
                    $safeRedirect = $redirect;
                }
            }

            if ($safeRedirect !== '') {
                header('Location: ' . $safeRedirect);
                exit;
            }

            if ($user['role'] === 'admin') {
                header('Location: indexadmin.php');
                exit;
            } elseif ($user['role'] === 'librarian') {
                header('Location: indexlibrarian.php');
                exit;
            } else {
                header('Location: index.php');
                exit;
            }
        } else {
            $err = 'Tài khoản hoặc mật khẩu không chính xác.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Đăng nhập | Thư viện QNU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body style="background:#f4f7f6;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h4 class="mb-3 text-primary fw-bold text-center">Đăng nhập hệ thống</h4>

                        <?php if ($err !== ''): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                            <div class="mb-3">
                                <label class="form-label small">Tài khoản</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Mật khẩu</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <button class="btn btn-primary w-100">Đăng nhập</button>
                        </form>

                        <div class="text-center mt-3 small text-muted">
                            <a href="index.php">Quay về trang chủ</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
