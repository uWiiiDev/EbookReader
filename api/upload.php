<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/epub_parser.php';

requireAuth();
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Nur POST-Anfragen sind zulässig.'], 405);
}

if (!isset($_FILES['epub']) || !is_array($_FILES['epub'])) {
    jsonResponse(['success' => false, 'error' => 'Keine Datei übertragen.'], 400);
}

$file = $_FILES['epub'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'Die Datei überschreitet upload_max_filesize in der php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'Die Datei überschreitet die maximale Dateigröße des Formulars.',
        UPLOAD_ERR_PARTIAL    => 'Die Datei wurde nur teilweise übertragen.',
        UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei ausgewählt.',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Ordner fehlt.',
        UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben auf die Festplatte.',
        UPLOAD_ERR_EXTENSION  => 'Eine PHP-Erweiterung hat den Upload gestoppt.'
    ];
    $msg = $errorMessages[$file['error']] ?? 'Unbekannter Upload-Fehler (' . $file['error'] . ')';
    jsonResponse(['success' => false, 'error' => $msg], 400);
}

$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($ext !== 'epub') {
    jsonResponse(['success' => false, 'error' => 'Nur Dateien mit der Endung .epub sind erlaubt.'], 400);
}

$tmpPath = $file['tmp_name'];

if (!is_uploaded_file($tmpPath)) {
    jsonResponse(['success' => false, 'error' => 'Ungültige Upload-Quelle.'], 400);
}

// Strict EPUB validation (ZIP header + container.xml)
if (!EpubParser::isValidEpub($tmpPath)) {
    jsonResponse(['success' => false, 'error' => 'Die Datei ist kein gültiges oder beschädigtes EPUB-Archiv.'], 400);
}

$booksDir = __DIR__ . '/../storage/books';
$coversDir = __DIR__ . '/../storage/covers';

if (!is_dir($booksDir)) {
    mkdir($booksDir, 0755, true);
}
if (!is_dir($coversDir)) {
    mkdir($coversDir, 0755, true);
}

$uuid = generateUuid();
$destinationBookFilename = $uuid . '.epub';
$destinationBookPath = $booksDir . '/' . $destinationBookFilename;

// Extract metadata and cover before moving
try {
    $parser = new EpubParser($tmpPath);
    $meta = $parser->parse();
} catch (Exception $e) {
    // If metadata parsing failed, fallback gracefully with filename
    $meta = [
        'title' => pathinfo($originalName, PATHINFO_FILENAME),
        'author' => 'Unbekannter Autor',
        'cover_data' => null,
        'cover_mime' => null
    ];
}

// Save cover image if extracted
$coverFilename = null;
if (!empty($meta['cover_data'])) {
    $coverExt = 'jpg';
    $mime = $meta['cover_mime'] ?? 'image/jpeg';
    if (str_contains($mime, 'png')) {
        $coverExt = 'png';
    } elseif (str_contains($mime, 'webp')) {
        $coverExt = 'webp';
    } elseif (str_contains($mime, 'gif')) {
        $coverExt = 'gif';
    }

    $coverFilename = $uuid . '.' . $coverExt;
    $destinationCoverPath = $coversDir . '/' . $coverFilename;
    if (file_put_contents($destinationCoverPath, $meta['cover_data']) === false) {
        $coverFilename = null;
    }
}

// Move EPUB file to destination
if (!move_uploaded_file($tmpPath, $destinationBookPath)) {
    if ($coverFilename && file_exists($coversDir . '/' . $coverFilename)) {
        unlink($coversDir . '/' . $coverFilename);
    }
    jsonResponse(['success' => false, 'error' => 'Konnte EPUB-Datei nicht im Speicher ablegen.'], 500);
}

// Insert into database
try {
    $pdo = getDb();
    $stmt = $pdo->prepare('
        INSERT INTO books (title, author, filename, cover_path, current_cfi, progress_percent, uploaded_at)
        VALUES (?, ?, ?, ?, NULL, 0, CURRENT_TIMESTAMP)
    ');
    $stmt->execute([
        $meta['title'],
        $meta['author'],
        $destinationBookFilename,
        $coverFilename
    ]);
    $bookId = (int) $pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Buch erfolgreich hinzugefügt.',
        'book' => [
            'id' => $bookId,
            'title' => $meta['title'],
            'author' => $meta['author'],
            'has_cover' => $coverFilename !== null,
            'cover_url' => 'serve_cover.php?id=' . $bookId,
            'reader_url' => 'reader.php?id=' . $bookId,
            'progress_percent' => 0
        ]
    ]);
} catch (Exception $e) {
    if (file_exists($destinationBookPath)) {
        unlink($destinationBookPath);
    }
    if ($coverFilename && file_exists($coversDir . '/' . $coverFilename)) {
        unlink($coversDir . '/' . $coverFilename);
    }
    jsonResponse(['success' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()], 500);
}
