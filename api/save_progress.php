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

// Support JSON body or POST form data
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true) ?: [];

$bookId = filter_var($input['book_id'] ?? $_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
$cfi = trim((string) ($input['cfi'] ?? $_POST['cfi'] ?? ''));
$progress = filter_var($input['progress_percent'] ?? $_POST['progress_percent'] ?? 0.0, FILTER_VALIDATE_FLOAT);

if (!$bookId) {
    jsonResponse(['success' => false, 'error' => 'Buch-ID fehlt oder ist ungültig.'], 400);
}

if ($progress === false || $progress < 0) {
    $progress = 0.0;
} elseif ($progress > 100) {
    $progress = 100.0;
}

$pdo = getDb();
$stmt = $pdo->prepare('
    UPDATE books
    SET current_cfi = ?,
        progress_percent = ?,
        last_read_at = CURRENT_TIMESTAMP
    WHERE id = ?
');
$stmt->execute([
    $cfi !== '' ? $cfi : null,
    round($progress, 2),
    $bookId
]);

if ($stmt->rowCount() === 0) {
    // Check if book exists
    $checkStmt = $pdo->prepare('SELECT id FROM books WHERE id = ?');
    $checkStmt->execute([$bookId]);
    if (!$checkStmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Buch nicht gefunden.'], 404);
    }
}

jsonResponse([
    'success' => true,
    'saved_cfi' => $cfi,
    'progress_percent' => round($progress, 2)
]);
