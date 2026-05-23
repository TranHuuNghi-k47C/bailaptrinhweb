<?php
function createNotification($pdo, $user_id, $title, $message, $type = 'info') {
    $stmt = $pdo->prepare("
        INSERT INTO Notifications (user_id, title, message, type)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $user_id,
        $title,
        $message,
        $type
    ]);
}
?>