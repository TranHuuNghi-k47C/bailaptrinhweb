<?php
/**
 * review_action.php
 * Xử lý API đánh giá sách (AJAX — GET & POST)
 *
 * ── Tạo bảng trước khi dùng ──────────────────────────────
 * CREATE TABLE IF NOT EXISTS Reviews (
 *     id         INT AUTO_INCREMENT PRIMARY KEY,
 *     book_id    INT     NOT NULL,
 *     user_id    INT     NOT NULL,
 *     rating     TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
 *     comment    TEXT,
 *     created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 *     UNIQUE KEY unique_review (book_id, user_id),
 *     FOREIGN KEY (book_id) REFERENCES Books(id) ON DELETE CASCADE,
 *     FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
 * );
 */

session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

function out(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ─── GET: lấy danh sách đánh giá của 1 cuốn sách ────────
if ($action === 'get') {
    $book_id = (int)($_GET['book_id'] ?? 0);
    if ($book_id <= 0) out(['error' => 'book_id không hợp lệ']);

    // Danh sách 30 đánh giá mới nhất
    $stmt = $pdo->prepare("
        SELECT r.rating, r.comment, r.created_at, u.username
        FROM   Reviews r
        JOIN   Users   u ON r.user_id = u.id
        WHERE  r.book_id = ?
        ORDER  BY r.created_at DESC
        LIMIT  30
    ");
    $stmt->execute([$book_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Điểm trung bình & tổng
    $s = $pdo->prepare("SELECT ROUND(AVG(rating),1) AS avg, COUNT(*) AS total FROM Reviews WHERE book_id=?");
    $s->execute([$book_id]);
    $stats = $s->fetch(PDO::FETCH_ASSOC);

    // Phân bổ theo từng mức sao
    $d = $pdo->prepare("SELECT rating, COUNT(*) AS cnt FROM Reviews WHERE book_id=? GROUP BY rating");
    $d->execute([$book_id]);
    $dist = [];
    foreach ($d->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dist[(int)$row['rating']] = (int)$row['cnt'];
    }

    // Đánh giá của user hiện tại (nếu có)
    $my_review = null;
    if (isset($_SESSION['user_id'])) {
        $m = $pdo->prepare("SELECT rating, comment FROM Reviews WHERE book_id=? AND user_id=?");
        $m->execute([$book_id, $_SESSION['user_id']]);
        $my_review = $m->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    out([
        'reviews'      => $reviews,
        'avg_rating'   => $stats['avg']   ?? 0,
        'total'        => (int)($stats['total'] ?? 0),
        'distribution' => $dist,
        'my_review'    => $my_review,
    ]);
}

// ─── POST: gửi / cập nhật đánh giá ──────────────────────
if ($action === 'submit') {

    // Chỉ sinh viên đã đăng nhập
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
        out(['success' => false, 'message' => 'Bạn cần đăng nhập bằng tài khoản sinh viên để đánh giá.']);
    }

    $book_id = (int)($_POST['book_id'] ?? 0);
    $rating  = (int)($_POST['rating']  ?? 0);
    $comment = trim($_POST['comment']  ?? '');
    $user_id = (int)$_SESSION['user_id'];

    if ($book_id <= 0)           out(['success'=>false,'message'=>'Sách không tồn tại.']);
    if ($rating < 1 || $rating > 5) out(['success'=>false,'message'=>'Số sao phải từ 1 đến 5.']);

    // Kiểm tra sách có trong DB
    $chk = $pdo->prepare("SELECT id FROM Books WHERE id=?");
    $chk->execute([$book_id]);
    if (!$chk->fetch()) out(['success'=>false,'message'=>'Sách không tồn tại trong hệ thống.']);

    // Upsert
    $upsert = $pdo->prepare("
        INSERT INTO Reviews (book_id, user_id, rating, comment)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment), created_at=NOW()
    ");
    $upsert->execute([$book_id, $user_id, $rating, $comment]);

    // Trả về thống kê mới
    $s = $pdo->prepare("SELECT ROUND(AVG(rating),1) AS avg, COUNT(*) AS total FROM Reviews WHERE book_id=?");
    $s->execute([$book_id]);
    $stats = $s->fetch(PDO::FETCH_ASSOC);

    out([
        'success'    => true,
        'avg_rating' => $stats['avg']   ?? $rating,
        'total'      => (int)($stats['total'] ?? 1),
    ]);
}

out(['error' => 'Action không hợp lệ.']);
