<?php
/**
 * cart.php
 * Giỏ mượn sách: chọn nhiều sách → thanh toán → gửi yêu cầu 1 lần
 * Tích hợp QR chuyển khoản VietQR
 */
session_start();
require_once 'config.php';
require_once 'logger.php';

// Chỉ sinh viên
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php?redirect=cart.php');
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$msg      = '';
$msg_type = 'success';

// ── THÊM VÀO GIỎ (AJAX) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add'])) {
    header('Content-Type: application/json');
    $book_id = (int)$_POST['book_id'];
    $qty     = max(1, (int)($_POST['quantity'] ?? 1));

    // Kiểm tra tồn kho
    $book = $pdo->prepare("SELECT quantity FROM Books WHERE id = ?");
    $book->execute([$book_id]);
    $stock = (int)($book->fetchColumn() ?: 0);

    if ($stock < $qty) {
        echo json_encode(['ok' => false, 'msg' => 'Không đủ số lượng trong kho.']);
        exit;
    }

    // Upsert giỏ
    $pdo->prepare("
        INSERT INTO BorrowCart (user_id, book_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
    ")->execute([$user_id, $book_id, $qty]);

    // Đếm tổng item trong giỏ
    $count = (int)$pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id = ?")
                       ->execute([$user_id]) ? $pdo->query("SELECT COUNT(*) FROM BorrowCart WHERE user_id = {$user_id}")->fetchColumn() : 0;
    echo json_encode(['ok' => true, 'count' => $count]);
    exit;
}

// ── XÓA KHỎI GIỎ ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $book_id = (int)$_POST['book_id'];
    $pdo->prepare("DELETE FROM BorrowCart WHERE user_id = ? AND book_id = ?")->execute([$user_id, $book_id]);
    header('Location: cart.php');
    exit;
}

// ── GỬI YÊU CẦU MƯỢN NHIỀU SÁCH ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $borrow_date = $_POST['borrow_date'];
    $return_date = $_POST['return_date'];

    $days = (strtotime($return_date) - strtotime($borrow_date)) / 86400;

    if ($days <= 0) {
        $msg      = 'Ngày trả phải sau ngày mượn!';
        $msg_type = 'danger';
    } else {
        // Lấy các sách trong giỏ
        $items = $pdo->prepare("
            SELECT bc.book_id, bc.quantity, b.title, b.quantity AS stock
            FROM BorrowCart bc
            JOIN Books b ON bc.book_id = b.id
            WHERE bc.user_id = ?
        ");
        $items->execute([$user_id]);
        $cartItems = $items->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cartItems)) {
            $msg      = 'Giỏ mượn trống!';
            $msg_type = 'warning';
        } else {
            // Kiểm tra tồn kho từng cuốn
            $errors = [];
            foreach ($cartItems as $item) {
                if ($item['quantity'] > $item['stock']) {
                    $errors[] = "«{$item['title']}» không đủ số lượng (tồn kho: {$item['stock']}).";
                }
            }

            if ($errors) {
                $msg      = implode('<br>', $errors);
                $msg_type = 'danger';
            } else {
                // Tạo group_id để liên kết các yêu cầu cùng phiên
                $group_id = sprintf('%08x-%04x-4%03x-%04x-%012x',
                    random_int(0, 0xffffffff), random_int(0, 0xffff),
                    random_int(0, 0x0fff),
                    random_int(0x8000, 0xbfff),
                    random_int(0, 0xffffffffffff));

                $pdo->beginTransaction();
                try {
                    $total_fee = 0;
                    $stmt = $pdo->prepare("
                        INSERT INTO BorrowRequests
                            (user_id, book_id, quantity, borrow_date, return_date, total_fee, borrow_group_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    foreach ($cartItems as $item) {
                        $fee = $days * 2000 * $item['quantity'];
                        $total_fee += $fee;
                        $stmt->execute([
                            $user_id,
                            $item['book_id'],
                            $item['quantity'],
                            $borrow_date,
                            $return_date,
                            $fee,
                            $group_id
                        ]);
                        writeBorrowLog(
                            "USER_ID {$user_id} - USERNAME {$_SESSION['username']} mượn BOOK_ID {$item['book_id']} " .
                            "x{$item['quantity']} cuốn, từ {$borrow_date} đến {$return_date}, phí {$fee}đ [GROUP:{$group_id}]"
                        );
                    }

                    // Xóa giỏ sau khi đặt thành công
                    $pdo->prepare("DELETE FROM BorrowCart WHERE user_id = ?")->execute([$user_id]);
                    $pdo->commit();

                    // Chuyển sang trang thanh toán QR
                    $fee_formatted = number_format($total_fee, 0, ',', '.');
                    $_SESSION['last_borrow'] = [
                        'group_id'    => $group_id,
                        'total_fee'   => $total_fee,
                        'borrow_date' => $borrow_date,
                        'return_date' => $return_date,
                        'books'       => array_column($cartItems, 'title'),
                    ];
                    header('Location: payment_qr.php?group=' . $group_id);
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg      = 'Có lỗi xảy ra: ' . htmlspecialchars($e->getMessage());
                    $msg_type = 'danger';
                }
            }
        }
    }
}

// ── LẤY GIỎ HIỆN TẠI ────────────────────────────────────────────
$cartStmt = $pdo->prepare("
    SELECT bc.*, b.title, b.author, b.image_url, b.quantity AS stock
    FROM BorrowCart bc
    JOIN Books b ON bc.book_id = b.id
    WHERE bc.user_id = ?
    ORDER BY bc.added_at DESC
");
$cartStmt->execute([$user_id]);
$cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);

$totalItems = count($cartItems);
$totalFee   = 0; // sẽ tính live bằng JS

// Đếm tổng số lượng cuốn
$totalQty = array_sum(array_column($cartItems, 'quantity'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ mượn sách | Thư viện Số QNU</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --qnu-blue: #0054a6; --qnu-blue-dark: #003d7a;
            --qnu-gold: #ffc107; --qnu-bg: #f4f7f6;
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--qnu-bg); color: #444; }
        .qnu-header { background: white; border-top: 5px solid var(--qnu-blue);
                      padding: 14px 0; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        .cart-card  { background: white; border-radius: 16px; border: none;
                      box-shadow: 0 4px 20px rgba(0,0,0,.07); padding: 20px; }
        .book-img   { width: 56px; height: 80px; object-fit: cover;
                      border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
        .qty-ctrl input { width: 54px; text-align: center; }
        .summary-box { background: #f8f9fa; border-radius: 12px; padding: 20px; }
        .fee-total   { font-size: 1.6rem; font-weight: 800; color: #c0392b; }
        .empty-cart  { text-align: center; padding: 60px 20px; }
        .btn-checkout { background: linear-gradient(135deg, var(--qnu-blue), #007bff);
                        color: white; border: none; border-radius: 10px;
                        padding: 14px; font-weight: 700; font-size: 16px; width: 100%; }
        .btn-checkout:hover { opacity: .9; color: white; }
        footer { background: #0f172a; color: #fff; padding: 20px 0;
                 border-top: 4px solid var(--qnu-gold); margin-top: 40px; }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="qnu-header sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="text-decoration-none d-flex align-items-center">
            <img src="images/qnu_logo.png" alt="Logo" style="height:56px;" onerror="this.style.display='none'">
            <div class="ms-3">
                <div style="color:var(--qnu-blue);font-weight:900;font-size:1.1rem;">Thư Viện Số</div>
                <div style="color:#ce1126;font-size:.85rem;font-weight:600;">TRUNG TÂM SỐ VÀ HỌC LIỆU</div>
            </div>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-primary fw-bold small">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill">
                <i class="bi bi-house-door"></i> Trang chủ
            </a>
            <a href="logout.php" class="btn btn-danger btn-sm rounded-pill">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</header>

<div class="container py-4">

    <!-- BREADCRUMB -->
    <nav class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php">Thư viện</a></li>
            <li class="breadcrumb-item active">Giỏ mượn sách</li>
        </ol>
    </nav>

    <h3 class="fw-bold text-primary mb-4">
        <i class="bi bi-basket3-fill me-2"></i>Giỏ mượn sách
        <span class="badge bg-primary rounded-pill ms-2" style="font-size:.7rem;"><?php echo $totalItems; ?> đầu sách</span>
    </h3>

    <?php if ($msg): ?>
        <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <div class="cart-card empty-cart">
            <div style="font-size:72px;">🛒</div>
            <h5 class="mt-3 text-muted">Giỏ mượn của bạn đang trống</h5>
            <p class="text-muted small">Hãy chọn sách từ danh sách và nhấn "Thêm vào giỏ mượn".</p>
            <a href="index.php" class="btn btn-primary rounded-pill mt-2 px-5">
                <i class="bi bi-book me-2"></i>Khám phá sách
            </a>
        </div>
    <?php else: ?>

    <form method="POST">
    <div class="row g-4">

        <!-- DANH SÁCH SÁCH TRONG GIỎ -->
        <div class="col-lg-8">
            <div class="cart-card">
                <?php foreach ($cartItems as $i => $item): ?>
                    <?php $cover = !empty($item['image_url']) ? $item['image_url'] : 'img/default.png'; ?>
                    <div class="d-flex align-items-center gap-3 py-3 <?php echo $i > 0 ? 'border-top' : ''; ?>">

                        <!-- ẢNH BÌA -->
                        <img src="<?php echo htmlspecialchars($cover); ?>" class="book-img"
                             alt="Cover" onerror="this.src='img/default.png'">

                        <!-- THÔNG TIN -->
                        <div class="flex-grow-1">
                            <div class="fw-bold" style="font-size:.9rem;">
                                <?php echo htmlspecialchars($item['title']); ?>
                            </div>
                            <div class="text-muted small mb-2">
                                <?php echo htmlspecialchars($item['author']); ?>
                                &nbsp;<span class="badge bg-success ms-1">Kho: <?php echo $item['stock']; ?></span>
                            </div>

                            <!-- ĐIỀU CHỈNH SỐ LƯỢNG -->
                            <div class="qty-ctrl d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="changeQty(<?php echo $item['book_id']; ?>, -1, <?php echo $item['stock']; ?>)">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <input type="number" id="qty_<?php echo $item['book_id']; ?>"
                                       name="qty_<?php echo $item['book_id']; ?>"
                                       value="<?php echo $item['quantity']; ?>"
                                       min="1" max="<?php echo $item['stock']; ?>"
                                       class="form-control form-control-sm"
                                       onchange="recalcFee()">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="changeQty(<?php echo $item['book_id']; ?>, 1, <?php echo $item['stock']; ?>)">
                                    <i class="bi bi-plus"></i>
                                </button>
                                <span class="text-muted small">cuốn</span>
                            </div>
                        </div>

                        <!-- XÓA -->
                        <button type="submit" name="remove_item" value="1"
                                onclick="document.getElementById('rmb').value=<?php echo $item['book_id']; ?>"
                                class="btn btn-sm text-danger"
                                title="Xóa khỏi giỏ">
                            <i class="bi bi-x-circle-fill fs-5"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div><!-- /.cart-card -->

            <!-- CHỌN NGÀY MƯỢN / TRẢ -->
            <div class="cart-card mt-4">
                <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar3 me-2"></i>Thời gian mượn</h6>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold">Ngày bắt đầu mượn</label>
                        <input type="date" name="borrow_date" id="borrow_date" class="form-control"
                               value="<?php echo date('Y-m-d'); ?>"
                               min="<?php echo date('Y-m-d'); ?>"
                               required onchange="recalcFee()">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-bold">Ngày trả dự kiến</label>
                        <input type="date" name="return_date" id="return_date" class="form-control"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               required onchange="recalcFee()">
                    </div>
                </div>
            </div>
        </div><!-- /.col-lg-8 -->

        <!-- TÓM TẮT & THANH TOÁN -->
        <div class="col-lg-4">
            <div class="cart-card summary-box">
                <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2"></i>Tóm tắt đơn mượn</h6>

                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Số đầu sách:</span>
                    <b><?php echo $totalItems; ?> đầu sách</b>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Tổng cuốn:</span>
                    <b id="total-qty"><?php echo $totalQty; ?> cuốn</b>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Số ngày mượn:</span>
                    <b id="total-days">0 ngày</b>
                </div>
                <hr>
                <div class="text-center mb-3">
                    <div class="small text-muted mb-1">Tổng phí ước tính (2.000đ/ngày/cuốn)</div>
                    <div class="fee-total" id="fee-total">0 VNĐ</div>
                </div>

                <button type="submit" name="checkout" value="1"
                        class="btn-checkout btn">
                    <i class="bi bi-qr-code-scan me-2"></i>
                    Xác nhận &amp; Thanh toán QR
                </button>

                <p class="small text-muted text-center mt-3 mb-0">
                    Sau khi xác nhận, bạn sẽ được hướng dẫn thanh toán qua QR chuyển khoản.
                    Thủ thư sẽ duyệt trong 1–2 ngày làm việc.
                </p>
            </div>

            <a href="index.php" class="btn btn-outline-secondary w-100 mt-3 rounded-pill">
                <i class="bi bi-arrow-left me-2"></i>Tiếp tục chọn sách
            </a>
        </div>

    </div>
    <!-- Hidden inputs -->
    <input type="hidden" id="rmb" name="book_id">
    </form>

    <?php endif; ?>
</div>

<footer>
    <div class="container text-center">
        <p class="small mb-0 opacity-75">Hệ thống Quản lý Thư viện Số QNU – Copyright © 2026</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BOOKS = <?php echo json_encode(array_map(fn($i) => [
    'book_id'  => $i['book_id'],
    'quantity' => $i['quantity'],
    'stock'    => $i['stock'],
], $cartItems), JSON_UNESCAPED_UNICODE); ?>;

function changeQty(bookId, delta, maxStock) {
    const el  = document.getElementById('qty_' + bookId);
    let val = parseInt(el.value) + delta;
    val = Math.max(1, Math.min(val, maxStock));
    el.value = val;
    recalcFee();
}

function recalcFee() {
    const bd = document.getElementById('borrow_date').value;
    const rd = document.getElementById('return_date').value;
    let days = 0;
    if (bd && rd) {
        days = Math.max(0, Math.ceil((new Date(rd) - new Date(bd)) / 86400000));
    }
    document.getElementById('total-days').innerText = days + ' ngày';

    let totalQty = 0;
    BOOKS.forEach(b => {
        const el = document.getElementById('qty_' + b.book_id);
        totalQty += parseInt(el ? el.value : b.quantity);
    });
    document.getElementById('total-qty').innerText = totalQty + ' cuốn';

    const fee = days * 2000 * totalQty;
    document.getElementById('fee-total').innerText =
        fee > 0 ? fee.toLocaleString('vi-VN') + ' VNĐ' : '0 VNĐ';
}

// Gán sự kiện cho tất cả input số lượng
document.addEventListener('DOMContentLoaded', recalcFee);
</script>
</body>
</html>
