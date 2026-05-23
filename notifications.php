<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Đánh dấu tất cả thông báo là đã đọc khi mở trang
$stmt = $pdo->prepare("UPDATE Notifications SET is_read = 1 WHERE user_id = ?");
$stmt->execute([$user_id]);

$stmt = $pdo->prepare("
    SELECT *
    FROM Notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông báo của tôi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f7f6;">

<div class="container py-5">
    <div class="card shadow border-0 rounded-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="text-primary fw-bold m-0">Thông báo của tôi</h3>
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    Quay lại trang chủ
                </a>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="alert alert-info">
                    Bạn chưa có thông báo nào.
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($n['type']); ?> rounded-3">
                        <div class="fw-bold">
                            <?php echo htmlspecialchars($n['title']); ?>
                        </div>
                        <div>
                            <?php echo htmlspecialchars($n['message']); ?>
                        </div>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($n['created_at']); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>