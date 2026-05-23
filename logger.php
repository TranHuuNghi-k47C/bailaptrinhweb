<?php
function writeBorrowLog($message) {
    writeLogToFile('borrow_log.txt', $message);
}

function writeBookLog($message) {
    writeLogToFile('book_log.txt', $message);
}

function writeLogToFile($fileName, $message) {
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/' . $fileName;

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    date_default_timezone_set('Asia/Ho_Chi_Minh');

    $time = date('Y-m-d H:i:s');
    $line = "[$time] " . $message . PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
?>