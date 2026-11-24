<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

requireAuth();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Ungültige Buch-ID.');
}

$pdo = getDb();
$stmt = $pdo->prepare('SELECT id, cover_path, title FROM books WHERE id = ?');
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book || empty($book['cover_path'])) {
    renderFallbackCover($book ? $book['title'] : 'Buch');
    exit;
}

$storageDir = realpath(__DIR__ . '/storage/covers');
$safeCover = basename($book['cover_path']);
$filePath = $storageDir . DIRECTORY_SEPARATOR . $safeCover;

if (!file_exists($filePath) || !is_readable($filePath)) {
    renderFallbackCover($book['title']);
    exit;
}

// Check against traversal
if (realpath($filePath) !== $filePath || !str_starts_with(realpath($filePath), $storageDir)) {
    http_response_code(403);
    exit('Zugriff verweigert.');
}

$mtime = filemtime($filePath);
$etag = '"' . md5($safeCover . $mtime) . '"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath) ?: 'image/jpeg';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Cache-Control: private, max-age=86400');

readfile($filePath);
exit;

function renderFallbackCover(string $title): void
{
    // Generate clean SVG placeholder cover
    $safeTitle = htmlspecialchars(mb_strimwidth($title, 0, 40, '...'), ENT_QUOTES, 'UTF-8');
    $initials = mb_substr($title, 0, 2);

    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400');

    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="300" height="450" viewBox="0 0 300 450">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:1" />
            <stop offset="100%" style="stop-color:#1e3a8a;stop-opacity:1" />
        </linearGradient>
    </defs>
    <rect width="300" height="450" fill="url(#grad)" rx="8"/>
    <rect x="0" y="0" width="16" height="450" fill="rgba(0,0,0,0.15)"/>
    <circle cx="150" cy="180" r="50" fill="rgba(255,255,255,0.1)"/>
    <text x="150" y="195" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="32" font-weight="bold" fill="#ffffff" text-anchor="middle">{$initials}</text>
    <text x="150" y="280" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="18" font-weight="600" fill="#ffffff" text-anchor="middle" width="240">
        {$safeTitle}
    </text>
    <text x="150" y="410" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="12" fill="rgba(255,255,255,0.7)" text-anchor="middle">
        EPUB READER
    </text>
</svg>
SVG;
}
