<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Ungültige Buch-ID.');
}

$pdo = getDb();
$stmt = $pdo->prepare('SELECT id, title, filename FROM books WHERE id = ?');
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    http_response_code(404);
    exit('Buch nicht gefunden.');
}

$storageDir = realpath(__DIR__ . '/storage/books');
$safeFilename = basename($book['filename']);
$filePath = $storageDir . DIRECTORY_SEPARATOR . $safeFilename;

if (!file_exists($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Buchdatei existiert nicht auf dem Server.');
}

// Basic security check against directory traversal
if (realpath($filePath) !== $filePath || !str_starts_with(realpath($filePath), $storageDir)) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}

$fileSize = filesize($filePath);
$downloadName = preg_replace('/[^a-zA-Z0-9_\-\. ]/u', '_', $book['title']) . '.epub';

// Support HTTP Range requests for epub.js
$start = 0;
$end = $fileSize - 1;
$isRangeRequest = false;

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = (int) $matches[1];
        if (!empty($matches[2])) {
            $end = (int) $matches[2];
        }
        if ($start <= $end && $start < $fileSize && $end < $fileSize) {
            $isRangeRequest = true;
        }
    }
}

// Clean any previous output buffer
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/epub+zip');
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
header('Cache-Control: private, no-transform, max-age=3600');

if ($isRangeRequest) {
    http_response_code(206);
    $length = $end - $start + 1;
    header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
    header("Content-Length: {$length}");

    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    $bufferSize = 64 * 1024;
    $bytesRemaining = $length;

    while (!feof($fp) && $bytesRemaining > 0 && connection_status() === CONNECTION_NORMAL) {
        $readSize = min($bufferSize, $bytesRemaining);
        echo fread($fp, $readSize);
        flush();
        $bytesRemaining -= $readSize;
    }
    fclose($fp);
} else {
    http_response_code(200);
    header("Content-Length: {$fileSize}");
    readfile($filePath);
}
exit;
