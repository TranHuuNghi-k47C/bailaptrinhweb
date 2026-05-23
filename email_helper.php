<?php
/**
 * email_helper.php
 * Gửi email qua Gmail SMTP (PHPMailer).
 *
 * ─── CÀI ĐẶT ─────────────────────────────────────────────
 * composer require phpmailer/phpmailer
 * Hoặc tải thủ công: https://github.com/PHPMailer/PHPMailer
 *
 * ─── CẤU HÌNH ────────────────────────────────────────────
 * Sửa các hằng số bên dưới đúng với Gmail của bạn.
 * Bật "App Password" tại: https://myaccount.google.com/apppasswords
 * ─────────────────────────────────────────────────────────
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Autoload PHPMailer (composer)
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Thủ công (nếu không dùng composer)
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

// ═══════════════════════════════════════════════════
// ⚙️  CẤU HÌNH GMAIL — SỬA CHỖ NÀY
// ═══════════════════════════════════════════════════
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your_email@gmail.com');   // ← Gmail của bạn
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');    // ← App Password (16 ký tự)
define('MAIL_FROM',     'your_email@gmail.com');
define('MAIL_FROM_NAME','Thư Viện Số QNU');

/**
 * Hàm gửi email chung
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = emailTemplate($subject, $htmlBody);
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Template HTML chung cho tất cả email
 */
function emailTemplate(string $title, string $body): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<style>
  body{font-family:Arial,sans-serif;background:#f4f7f6;margin:0;padding:20px;}
  .wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;
        box-shadow:0 4px 20px rgba(0,0,0,.08);}
  .hdr{background:linear-gradient(135deg,#003d7a,#0054a6);padding:28px 30px;text-align:center;}
  .hdr h1{color:#fff;margin:0;font-size:22px;letter-spacing:.5px;}
  .hdr small{color:#ffc107;font-size:12px;}
  .body{padding:28px 30px;color:#444;line-height:1.7;font-size:14px;}
  .footer{background:#0f172a;padding:16px 30px;text-align:center;color:#666;font-size:11px;}
  .btn{display:inline-block;padding:12px 28px;background:#0054a6;color:#fff !important;
       border-radius:8px;text-decoration:none;font-weight:bold;margin:12px 0;}
  .alert{background:#fff8e1;border-left:4px solid #ffc107;padding:12px 16px;border-radius:6px;margin:16px 0;}
  .danger{background:#fff0f0;border-color:#e74c3c;}
  .success{background:#f0fff4;border-color:#27ae60;}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1>📚 Thư Viện Số QNU</h1>
    <small>TRUNG TÂM SỐ VÀ HỌC LIỆU - ĐẠI HỌC QUY NHƠN</small>
  </div>
  <div class="body">$body</div>
  <div class="footer">
    © 2026 Thư viện Số QNU &nbsp;|&nbsp; (84-256) 3846156 &nbsp;|&nbsp; shl@qnu.edu.vn
  </div>
</div>
</body>
</html>
HTML;
}

// ═══════════════════════════════════════════════════
// 📧 CÁC HÀM GỬI EMAIL CỤ THỂ
// ═══════════════════════════════════════════════════

/**
 * 1. Gửi email xác thực tài khoản
 */
function sendVerifyEmail(string $toEmail, string $username, string $token): bool {
    $link = 'https://yourdomain.com/verify_email.php?token=' . $token; // ← sửa domain
    $body = "
        <h3>Xin chào <b>{$username}</b>! 👋</h3>
        <p>Cảm ơn bạn đã đăng ký tài khoản tại <b>Thư Viện Số QNU</b>.</p>
        <p>Vui lòng nhấn nút bên dưới để <b>xác thực địa chỉ email</b> của bạn:</p>
        <div style='text-align:center;'>
            <a href='{$link}' class='btn'>✅ Xác thực Email ngay</a>
        </div>
        <div class='alert'>
            ⏰ Link xác thực có hiệu lực trong <b>24 giờ</b>.
            Nếu bạn không yêu cầu điều này, hãy bỏ qua email này.
        </div>
        <p style='color:#888;font-size:12px;'>Hoặc copy link: <br><a href='{$link}'>{$link}</a></p>
    ";
    return sendMail($toEmail, $username, '✅ Xác thực tài khoản Thư viện Số QNU', $body);
}

/**
 * 2. Nhắc nhở sắp đến hạn trả (3 ngày / 1 ngày trước)
 */
function sendDueSoonReminder(string $toEmail, string $username, string $bookTitle, string $returnDate, int $daysLeft): bool {
    $urgency = $daysLeft <= 1 ? "<div class='alert danger'>" : "<div class='alert'>";
    $icon    = $daysLeft <= 1 ? '🚨' : '⏰';
    $body = "
        <h3>Xin chào <b>{$username}</b>!</h3>
        {$urgency}
            {$icon} Sách <b>«{$bookTitle}»</b> sẽ đến hạn trả vào ngày <b>{$returnDate}</b>
            — còn <b>{$daysLeft} ngày</b>.
        </div>
        <p>Vui lòng trả sách đúng hạn để tránh phí phạt <b>10.000 đ/ngày/cuốn</b>.</p>
        <p>Nếu cần gia hạn, vui lòng liên hệ thủ thư tại quầy hoặc qua email <b>shl@qnu.edu.vn</b>.</p>
        <div style='text-align:center;'>
            <a href='https://yourdomain.com/profile.php' class='btn'>Xem lịch mượn của tôi</a>
        </div>
    ";
    $subject = $daysLeft <= 1
        ? "🚨 Ngày mai hết hạn trả sách «{$bookTitle}»"
        : "⏰ Còn {$daysLeft} ngày — nhớ trả sách «{$bookTitle}»";
    return sendMail($toEmail, $username, $subject, $body);
}

/**
 * 3. Nhắc nhở quá hạn trả
 */
function sendOverdueReminder(string $toEmail, string $username, string $bookTitle, string $returnDate, int $daysLate, float $lateFee): bool {
    $feeFormatted = number_format($lateFee, 0, ',', '.');
    $body = "
        <h3>Xin chào <b>{$username}</b>!</h3>
        <div class='alert danger'>
            🚨 Sách <b>«{$bookTitle}»</b> đã <b>quá hạn {$daysLate} ngày</b>
            (hạn trả: {$returnDate}).
        </div>
        <p>Phí phạt hiện tại: <b style='color:#e74c3c;font-size:18px;'>{$feeFormatted} VNĐ</b></p>
        <p>Vui lòng mang sách trả ngay tại thư viện để tránh phí phạt tăng thêm.
           Phí phạt là <b>10.000 đ/ngày</b>.</p>
        <div style='text-align:center;'>
            <a href='https://yourdomain.com/profile.php' class='btn'>Xem chi tiết</a>
        </div>
    ";
    return sendMail($toEmail, $username, "🚨 Quá hạn {$daysLate} ngày — «{$bookTitle}»", $body);
}

/**
 * 4. Xác nhận yêu cầu mượn sách đã được duyệt
 */
function sendApproveNotification(string $toEmail, string $username, array $books, string $borrowDate, string $returnDate): bool {
    $bookList = '';
    foreach ($books as $b) {
        $bookList .= "<li>📖 <b>{$b['title']}</b> (x{$b['quantity']} cuốn)</li>";
    }
    $body = "
        <h3>Xin chào <b>{$username}</b>!</h3>
        <div class='alert success'>
            ✅ Yêu cầu mượn sách của bạn đã được <b>THỦ THƯ DUYỆT</b>!
        </div>
        <p><b>Sách đã duyệt:</b></p>
        <ul>{$bookList}</ul>
        <p>📅 Ngày mượn: <b>{$borrowDate}</b> &nbsp;→&nbsp; Ngày trả: <b>{$returnDate}</b></p>
        <p>Vui lòng đến quầy thư viện để nhận sách. Mang theo <b>thẻ sinh viên</b>.</p>
        <div style='text-align:center;'>
            <a href='https://yourdomain.com/profile.php' class='btn'>Xem lịch mượn</a>
        </div>
    ";
    return sendMail($toEmail, $username, '✅ Yêu cầu mượn sách đã được duyệt!', $body);
}

/**
 * 5. Thông báo bị từ chối
 */
function sendRejectNotification(string $toEmail, string $username, string $bookTitle, string $reason): bool {
    $body = "
        <h3>Xin chào <b>{$username}</b>!</h3>
        <div class='alert danger'>
            ❌ Yêu cầu mượn sách <b>«{$bookTitle}»</b> đã bị <b>từ chối</b>.
        </div>
        <p><b>Lý do:</b> {$reason}</p>
        <p>Nếu có thắc mắc, vui lòng liên hệ thư viện qua email <b>shl@qnu.edu.vn</b>
           hoặc ĐT: <b>(84-256) 3846156</b>.</p>
    ";
    return sendMail($toEmail, $username, '❌ Yêu cầu mượn sách bị từ chối', $body);
}
