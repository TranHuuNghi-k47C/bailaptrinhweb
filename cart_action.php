<?php
/**
 * cart_action.php
 * AJAX endpoint: thêm sách vào giỏ, xóa, đếm số lượng.
 * Được gọi từ index.php và detail.php qua fetch().
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function out(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    out(['ok' => false, 'msg' => 'Bạn cần đăng nhập bằng tài khoản sinh viên.', 'need_login' => true]);
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// ── ĐẾM GIỎ (GET) ──────────────────────────────────────────────
if ($action === 'count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    out(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
}

// ── THÊM VÀO GIỎ (POST) ────────────────────────────────────────
if ($action === 'add') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $qty     = max(1, (int)($_POST['quantity'] ?? 1));

    // Kiểm tra tồn kho
    $stmt = $pdo->prepare("SELECT quantity FROM Books WHERE id = ?");
    $stmt->execute([$book_id]);
    $stock = (int)($stmt->fetchColumn() ?: 0);

    if ($stock <= 0) out(['ok' => false, 'msg' => 'Sách đã hết trong kho.']);
    if ($qty > $stock) out(['ok' => false, 'msg' => "Chỉ còn {$stock} cuốn trong kho."]);

    $pdo->prepare("
        INSERT INTO BorrowCart (user_id, book_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = ?
    ")->execute([$user_id, $book_id, $qty, $qty]);

    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id = ?");
    $stmt2->execute([$user_id]);
    $count = (int)$stmt2->fetchColumn();

    out(['ok' => true, 'msg' => 'Đã thêm vào giỏ mượn!', 'count' => $count]);
}

// ── XÓA KHỎI GIỎ (POST) ────────────────────────────────────────
if ($action === 'remove') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $pdo->prepare("DELETE FROM BorrowCart WHERE user_id = ? AND book_id = ?")->execute([$user_id, $book_id]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM BorrowCart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    out(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
}

// ── CẬP NHẬT SỐ LƯỢNG (POST) ───────────────────────────────────
if ($action === 'update_qty') {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $qty     = max(1, (int)($_POST['quantity'] ?? 1));

    // Giới hạn theo tồn kho
    $stmt = $pdo->prepare("SELECT quantity FROM Books WHERE id = ?");
    $stmt->execute([$book_id]);
    $stock = (int)($stmt->fetchColumn() ?: 1);
    $qty   = min($qty, $stock);

    $pdo->prepare("UPDATE BorrowCart SET quantity = ? WHERE user_id = ? AND book_id = ?")
        ->execute([$qty, $user_id, $book_id]);
    out(['ok' => true, 'quantity' => $qty]);
}

out(['ok' => false, 'msg' => 'Action không hợp lệ.']);
