<?php
/**
 * payment_qr.php
 * Trang thanh toán QR VietQR sau khi sinh viên gửi yêu cầu mượn sách.
 * QR được tạo hoàn toàn miễn phí qua api.vietqr.io (không cần đăng ký).
 */
session_start();
require_once 'config.php';

// Bảo vệ trang
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// ═══════════════════════════════════════════════════
// ⚙️  CẤU HÌNH NGÂN HÀNG — SỬA CHỖ NÀY
// ═══════════════════════════════════════════════════
define('BANK_ID',       'BIDV');              // Mã ngân hàng: VCB, TCB, MB, BIDV, ACB…
define('ACCOUNT_NO',    '8804009095');       // Số tài khoản nhận tiền
define('ACCOUNT_NAME',  'Thu Vien So QNU'); // Tên chủ tài khoản (không dấu)

// ─────────────────────────────────────────────────────────────────
$group_id = trim($_GET['group'] ?? '');

// Lấy thông tin từ session (được set ở cart.php) hoặc query DB
$borrow_info = null;
if (!empty($_SESSION['last_borrow']) && ($_SESSION['last_borrow']['group_id'] ?? '') === $group_id) {
    $borrow_info = $_SESSION['last_borrow'];
} elseif ($group_id !== '') {
    // Fallback: query DB
    $stmt = $pdo->prepare("
        SELECT br.total_fee, br.borrow_date, br.return_date, b.title
        FROM BorrowRequests br
        JOIN Books b ON br.book_id = b.id
        WHERE br.borrow_group_id = ? AND br.user_id = ?
    ");
    $stmt->execute([$group_id, $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $borrow_info = [
            'group_id'    => $group_id,
            'total_fee'   => array_sum(array_column($rows, 'total_fee')),
            'borrow_date' => $rows[0]['borrow_date'],
            'return_date' => $rows[0]['return_date'],
            'books'       => array_column($rows, 'title'),
        ];
    }
}

if (!$borrow_info) {
    die("<div style='text-align:center;padding:60px;'><h2>❌ Không tìm thấy thông tin đơn mượn.</h2><a href='cart.php'>Quay lại</a></div>");
}

$total_fee    = (float)$borrow_info['total_fee'];
$borrow_date  = $borrow_info['borrow_date'];
$return_date  = $borrow_info['return_date'];
$books        = (array)$borrow_info['books'];
$fee_int      = (int)round($total_fee); // VietQR cần số nguyên

// Nội dung chuyển khoản
$description  = 'Phi muon sach ' . $_SESSION['username'] . ' ' . date('dmY');
$description  = preg_replace('/[^a-zA-Z0-9 ]/', '', $description);
$description  = substr($description, 0, 50); // max 50 ký tự

// URL ảnh QR VietQR (miễn phí, không cần API key)
$qr_url = sprintf(
    'https://api.vietqr.io/image/%s-%s-compact2.jpg?amount=%d&addInfo=%s&accountName=%s',
    BANK_ID,
    ACCOUNT_NO,
    $fee_int,
    urlencode($description),
    urlencode(ACCOUNT_NAME)
);

$fee_formatted = number_format($total_fee, 0, ',', '.');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toán QR | Thư viện Số QNU</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --qnu-blue:#0054a6; --qnu-gold:#ffc107; }
        body  { background:#f4f7f6; font-family:'Segoe UI',Arial,sans-serif; }
        .qnu-header { background:white; border-top:5px solid var(--qnu-blue);
                      padding:14px 0; box-shadow:0 2px 10px rgba(0,0,0,.1); }
        .payment-card { background:white; border-radius:20px; border:none;
                        box-shadow:0 8px 32px rgba(0,0,0,.09); max-width:520px; margin:0 auto; }
        .qr-box { background:linear-gradient(135deg,#f8f9ff,#e8f0ff);
                  border-radius:16px; padding:24px; text-align:center; }
        .qr-img { width:260px; height:260px; object-fit:contain; border-radius:12px;
                  box-shadow:0 4px 20px rgba(0,0,0,.12); }
        .amount { font-size:2rem; font-weight:900; color:#c0392b; }
        .info-row { display:flex; justify-content:space-between; padding:8px 0;
                    border-bottom:1px solid #f0f0f0; font-size:.87rem; }
        .info-row:last-child { border-bottom:none; }
        .step-badge { background:var(--qnu-blue); color:white; border-radius:50%;
                      width:28px; height:28px; display:inline-flex;
                      align-items:center; justify-content:center;
                      font-size:.75rem; font-weight:700; flex-shrink:0; }
        .timer { font-size:1.4rem; font-weight:800; color:var(--qnu-blue); }
        footer { background:#0f172a; color:#fff; padding:20px 0;
                 border-top:4px solid var(--qnu-gold); margin-top:40px; }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="qnu-header">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="text-decoration-none d-flex align-items-center">
            <img src="images/qnu_logo.png" alt="Logo" style="height:50px;" onerror="this.style.display='none'">
            <div class="ms-3">
                <div style="color:var(--qnu-blue);font-weight:900;font-size:1rem;">Thư Viện Số QNU</div>
            </div>
        </a>
        <a href="profile.php" class="btn btn-outline-primary btn-sm rounded-pill">
            <i class="bi bi-person-circle me-1"></i>Hồ sơ của tôi
        </a>
    </div>
</header>

<div class="container py-4">

    <div class="payment-card p-4 p-md-5">

        <!-- TIÊU ĐỀ -->
        <div class="text-center mb-4">
            <div style="font-size:48px;">📱</div>
            <h4 class="fw-bold text-primary mt-2">Thanh toán qua QR</h4>
            <p class="text-muted small mb-0">Quét mã QR bên dưới để chuyển khoản phí mượn sách</p>
        </div>

        <!-- SÁCH ĐÃ MƯỢN -->
        <div class="mb-3 p-3 rounded-3" style="background:#f0f6ff;">
            <div class="fw-bold small text-primary mb-2">
                <i class="bi bi-book-half me-1"></i>Sách trong đơn mượn:
            </div>
            <?php foreach ($books as $idx => $title): ?>
                <div class="small text-dark">📖 <?php echo htmlspecialchars($title); ?></div>
            <?php endforeach; ?>
            <div class="small text-muted mt-1">
                📅 <?php echo $borrow_date; ?> → <?php echo $return_date; ?>
            </div>
        </div>

        <!-- QR CODE -->
        <div class="qr-box mb-4">
            <div class="small text-muted mb-2 fw-bold">SỐ TIỀN CẦN CHUYỂN</div>
            <div class="amount mb-3"><?php echo $fee_formatted; ?> VNĐ</div>

            <img src="<?php echo htmlspecialchars($qr_url); ?>"
                 class="qr-img"
                 alt="QR Chuyển khoản"
                 onerror="this.src='';this.parentElement.innerHTML+='<p class=\'text-danger small mt-2\'>❌ Không thể tải QR. Kiểm tra kết nối hoặc thông tin ngân hàng.</p>'">

            <div class="mt-3 small">
                <span class="badge bg-success me-2">✅ Miễn phí qua VietQR</span>
                <span class="badge bg-primary"><?php echo BANK_ID; ?></span>
            </div>
        </div>

        <!-- THÔNG TIN CHUYỂN KHOẢN -->
        <div class="mb-4">
            <h6 class="fw-bold mb-3">Thông tin chuyển khoản</h6>
            <div class="info-row">
                <span class="text-muted">Ngân hàng</span>
                <b><?php echo BANK_ID; ?></b>
            </div>
            <div class="info-row">
                <span class="text-muted">Số tài khoản</span>
                <b><?php echo ACCOUNT_NO; ?></b>
            </div>
            <div class="info-row">
                <span class="text-muted">Chủ tài khoản</span>
                <b><?php echo ACCOUNT_NAME; ?></b>
            </div>
            <div class="info-row">
                <span class="text-muted">Số tiền</span>
                <b style="color:#c0392b;"><?php echo $fee_formatted; ?> VNĐ</b>
            </div>
            <div class="info-row">
                <span class="text-muted">Nội dung CK</span>
                <b class="text-end" style="max-width:260px;"><?php echo htmlspecialchars($description); ?></b>
            </div>
        </div>

        <!-- HƯỚNG DẪN CÁC BƯỚC -->
        <div class="mb-4">
            <h6 class="fw-bold mb-3">Hướng dẫn thanh toán</h6>
            <?php
            $steps = [
                'Mở app ngân hàng → chọn <b>Quét QR / VietQR</b>',
                'Quét mã QR phía trên hoặc nhập thông tin thủ công',
                'Kiểm tra số tiền và nội dung chuyển khoản — nhấn <b>Xác nhận</b>',
                'Chụp màn hình xác nhận giao dịch để lưu làm bằng chứng',
                'Đến quầy thư viện xuất trình biên lai khi nhận sách',
            ];
            foreach ($steps as $i => $step):
            ?>
            <div class="d-flex align-items-start gap-3 mb-2">
                <span class="step-badge"><?php echo $i + 1; ?></span>
                <span class="small text-muted" style="line-height:1.6;"><?php echo $step; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- NÚT HÀNH ĐỘNG -->
        <div class="d-grid gap-2">
            <a href="profile.php" class="btn btn-primary rounded-pill fw-bold py-2">
                <i class="bi bi-person-circle me-2"></i>Xem lịch mượn của tôi
            </a>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill py-2">
                <i class="bi bi-house-door me-2"></i>Về trang chủ
            </a>
        </div>

        <!-- LƯU Ý -->
        <div class="alert alert-warning mt-4 small mb-0">
            <i class="bi bi-info-circle-fill me-2"></i>
            <b>Lưu ý:</b> Phí mượn là phí dự kiến. Nếu trả sách đúng hạn sẽ không phát sinh thêm.
            Trả trễ sẽ bị phạt thêm <b>10.000 đ/ngày/cuốn</b>. Thủ thư sẽ duyệt yêu cầu sau khi xác nhận thanh toán.
        </div>

    </div>
</div>

<footer>
    <div class="container text-center">
        <p class="small mb-0 opacity-75">Hệ thống Quản lý Thư viện Số QNU – Copyright © 2026</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
