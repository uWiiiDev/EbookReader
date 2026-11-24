<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Nur POST-Anfragen sind zulässig.'], 405);
}

// Support form-data or JSON payload
$input = json_decode(file_get_contents('php://input'), true);
$id = filter_var($input['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT);

if (!$id) {
    jsonResponse(['success' => false, 'error' => 'Ungültige Buch-ID.'], 400);
}

$pdo = getDb();
$stmt = $pdo->prepare('SELECT id, filename, cover_path FROM books WHERE id = ?');
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    jsonResponse(['success' => false, 'error' => 'Buch nicht gefunden.'], 404);
}

$booksDir = realpath(__DIR__ . '/../storage/books');
$coversDir = realpath(__DIR__ . '/../storage/covers');

// Delete EPUB file
if (!empty($book['filename'])) {
    $bookFile = $booksDir . DIRECTORY_SEPARATOR . basename($book['filename']);
    if (file_exists($bookFile) && is_file($bookFile)) {
        unlink($bookFile);
    }
}

// Delete cover image file
if (!empty($book['cover_path'])) {
    $coverFile = $coversDir . DIRECTORY_SEPARATOR . basename($book['cover_path']);
    if (file_exists($coverFile) && is_file($coverFile)) {
        unlink($coverFile);
    }
}

// Delete from DB
$deleteStmt = $pdo->prepare('DELETE FROM books WHERE id = ?');
$deleteStmt->execute([$id]);

jsonResponse(['success' => true, 'message' => 'Buch erfolgreich gelöscht.']);
