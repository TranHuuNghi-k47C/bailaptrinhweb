<?php
/**
 * send_reminders.php
 * ─────────────────────────────────────────────────────────────
 * CRON JOB — chạy mỗi ngày lúc 8:00 sáng để gửi email nhắc nhở.
 *
 * Thêm vào crontab (Linux):
 *   0 8 * * * php /var/www/html/thuvien/send_reminders.php >> /var/www/html/thuvien/logs/cron.log 2>&1
 *
 * Hoặc chạy thủ công để test:
 *   php send_reminders.php
 *   http://localhost/thuvien/send_reminders.php?secret=YOUR_SECRET
 *
 * Bảo vệ URL bằng secret key:
 * ─────────────────────────────────────────────────────────────
 */

define('CRON_SECRET', 'doi_thanh_key_bi_mat_cua_ban'); // ← Đổi key này!

// Nếu chạy qua web, kiểm tra secret
if (php_sapi_name() !== 'cli') {
    $secret = $_GET['secret'] ?? '';
    if ($secret !== CRON_SECRET) {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_helper.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$today = date('Y-m-d');

echo "[" . date('Y-m-d H:i:s') . "] Bắt đầu gửi nhắc nhở...\n";

$sent_count = 0;
$err_count  = 0;

// ═══════════════════════════════════════════════════════════════
// 1. NHẮC 3 NGÀY TRƯỚC HẠN
// ═══════════════════════════════════════════════════════════════
$date_3d = date('Y-m-d', strtotime('+3 days'));

$stmt = $pdo->prepare("
    SELECT
        br.id AS borrow_id,
        br.borrow_group_id,
        br.return_date,
        b.title,
        u.username,
        u.email,
        u.full_name
    FROM BorrowRequests br
    JOIN Books  b ON br.book_id  = b.id
    JOIN Users  u ON br.user_id  = u.id
    LEFT JOIN EmailReminderLog erl
        ON erl.borrow_id = br.id AND erl.reminder_type = '3days'
    WHERE br.status = 'approved'
      AND br.return_date = ?
      AND u.email IS NOT NULL
      AND u.email != ''
      AND erl.id IS NULL
");
$stmt->execute([$date_3d]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $ok = sendDueSoonReminder(
        $row['email'],
        $row['username'],
        $row['title'],
        $row['return_date'],
        3
    );
    if ($ok) {
        $pdo->prepare("INSERT IGNORE INTO EmailReminderLog (borrow_id, reminder_type) VALUES (?, '3days')")
            ->execute([$row['borrow_id']]);
        $sent_count++;
        echo "  ✅ 3d — {$row['username']} — {$row['title']}\n";
    } else {
        $err_count++;
        echo "  ❌ FAIL — {$row['username']} — {$row['email']}\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// 2. NHẮC 1 NGÀY TRƯỚC HẠN
// ═══════════════════════════════════════════════════════════════
$date_1d = date('Y-m-d', strtotime('+1 day'));

$stmt = $pdo->prepare("
    SELECT
        br.id AS borrow_id,
        br.return_date,
        b.title,
        u.username,
        u.email
    FROM BorrowRequests br
    JOIN Books  b ON br.book_id = b.id
    JOIN Users  u ON br.user_id = u.id
    LEFT JOIN EmailReminderLog erl
        ON erl.borrow_id = br.id AND erl.reminder_type = '1day'
    WHERE br.status = 'approved'
      AND br.return_date = ?
      AND u.email IS NOT NULL AND u.email != ''
      AND erl.id IS NULL
");
$stmt->execute([$date_1d]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $ok = sendDueSoonReminder(
        $row['email'],
        $row['username'],
        $row['title'],
        $row['return_date'],
        1
    );
    if ($ok) {
        $pdo->prepare("INSERT IGNORE INTO EmailReminderLog (borrow_id, reminder_type) VALUES (?, '1day')")
            ->execute([$row['borrow_id']]);
        $sent_count++;
        echo "  ✅ 1d — {$row['username']} — {$row['title']}\n";
    } else {
        $err_count++;
        echo "  ❌ FAIL — {$row['username']} — {$row['email']}\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// 3. NHẮC SÁCH QUÁ HẠN
// ═══════════════════════════════════════════════════════════════
$stmt = $pdo->prepare("
    SELECT
        br.id AS borrow_id,
        br.return_date,
        br.quantity,
        b.title,
        u.username,
        u.email
    FROM BorrowRequests br
    JOIN Books  b ON br.book_id = b.id
    JOIN Users  u ON br.user_id = u.id
    LEFT JOIN EmailReminderLog erl
        ON erl.borrow_id = br.id AND erl.reminder_type = 'overdue'
    WHERE br.status = 'approved'
      AND br.return_date < ?
      AND u.email IS NOT NULL AND u.email != ''
      AND erl.id IS NULL
");
$stmt->execute([$today]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $days_late = (int)ceil(
        (strtotime($today) - strtotime($row['return_date'])) / 86400
    );
    $late_fee = $days_late * 10000 * (int)$row['quantity'];

    $ok = sendOverdueReminder(
        $row['email'],
        $row['username'],
        $row['title'],
        $row['return_date'],
        $days_late,
        $late_fee
    );
    if ($ok) {
        $pdo->prepare("INSERT IGNORE INTO EmailReminderLog (borrow_id, reminder_type) VALUES (?, 'overdue')")
            ->execute([$row['borrow_id']]);
        $sent_count++;
        echo "  🚨 overdue — {$row['username']} — {$days_late}d — {$row['title']}\n";
    } else {
        $err_count++;
        echo "  ❌ FAIL overdue — {$row['username']}\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// TỔNG KẾT
// ═══════════════════════════════════════════════════════════════
echo "\n[" . date('Y-m-d H:i:s') . "] Xong! Gửi thành công: {$sent_count} | Lỗi: {$err_count}\n";
