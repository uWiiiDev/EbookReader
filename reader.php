<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = getDb();
$stmt = $pdo->prepare('SELECT id, title, author, filename, current_cfi, progress_percent FROM books WHERE id = ?');
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: index.php');
    exit;
}

$bookFile = __DIR__ . '/storage/books/' . basename($book['filename']);
if (!file_exists($bookFile)) {
    echo 'Fehler: Buchdatei existiert nicht auf dem Server. <a href="index.php">Zurück</a>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= e(getCsrfToken()) ?>">
    <title><?= e($book['title']) ?> | EPUB Reader</title>
    <script>
        (function() {
            const saved = localStorage.getItem('epub_theme') || localStorage.getItem('app_theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <link rel="stylesheet" href="assets/css/reader.css?v=<?= filemtime(__DIR__ . '/assets/css/reader.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Inter:wght@400;500;600&family=Lora:ital,wght@0,400;0,600;1,400&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300&display=swap" rel="stylesheet">
</head>
<body class="reader-body">

    <!-- Top Navigation Bar -->
    <header id="readerHeader" class="reader-header">
        <div class="header-left">
            <a href="index.php" class="header-btn" title="Zurück zur Bibliothek" aria-label="Zurück zur Bibliothek">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <span class="btn-text">Bibliothek</span>
            </a>
            <div class="book-meta-header">
                <h1 class="book-meta-title" title="<?= e($book['title']) ?>"><?= e($book['title']) ?></h1>
                <span class="book-meta-author"><?= e($book['author']) ?></span>
            </div>
        </div>

        <div class="header-right">
            <!-- Table of contents toggle -->
            <button id="tocToggleBtn" class="header-btn" title="Inhaltsverzeichnis" aria-label="Inhaltsverzeichnis">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <span class="btn-text">Kapitel</span>
            </button>

            <!-- Settings toggle -->
            <button id="settingsToggleBtn" class="header-btn" title="Lese-Einstellungen" aria-label="Lese-Einstellungen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span class="btn-text">Ansicht</span>
            </button>

            <!-- Fullscreen toggle -->
            <button id="fullscreenBtn" class="header-btn" title="Vollbild umschalten" aria-label="Vollbild">
                <svg id="fullscreenIconExpand" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                </svg>
                <svg id="fullscreenIconCompress" class="hidden" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
                </svg>
            </button>
        </div>
    </header>

    <!-- Sidebar: Table of Contents -->
    <aside id="tocSidebar" class="toc-sidebar">
        <div class="toc-header">
            <h2>Inhaltsverzeichnis</h2>
            <button id="tocCloseBtn" class="close-btn" aria-label="Schließen">&times;</button>
        </div>
        <div id="tocContent" class="toc-content">
            <div class="toc-loading">Inhalt wird geladen...</div>
        </div>
    </aside>
    <div id="tocBackdrop" class="toc-backdrop"></div>

    <!-- Settings Dropdown Panel -->
    <div id="settingsPanel" class="settings-panel hidden">
        <div class="settings-group">
            <span class="settings-label">Farbschema</span>
            <div class="theme-buttons">
                <button type="button" class="theme-btn theme-light active" data-theme="light">
                    <span class="theme-preview"></span>
                    <span>Hell</span>
                </button>
                <button type="button" class="theme-btn theme-sepia" data-theme="sepia">
                    <span class="theme-preview"></span>
                    <span>Sepia</span>
                </button>
                <button type="button" class="theme-btn theme-dark" data-theme="dark">
                    <span class="theme-preview"></span>
                    <span>Dunkel</span>
                </button>
            </div>
        </div>

        <div class="settings-group">
            <span class="settings-label">Schriftgröße</span>
            <div class="font-size-controls">
                <button type="button" id="fontDecreaseBtn" class="font-btn" title="Schrift verkleinern" aria-label="Schrift verkleinern">A-</button>
                <span id="fontSizeDisplay" class="font-size-display">100%</span>
                <button type="button" id="fontIncreaseBtn" class="font-btn" title="Schrift vergrößern" aria-label="Schrift vergrößern">A+</button>
            </div>
        </div>

        <div class="settings-group">
            <span class="settings-label">Schriftart</span>
            <div class="font-family-controls">
                <button type="button" class="font-family-btn active" data-font="serif">Serif (Buch)</button>
                <button type="button" class="font-family-btn" data-font="sans-serif">Sans-Serif</button>
            </div>
        </div>

        <div class="settings-group">
            <span class="settings-label">Lesemodus</span>
            <div class="flow-controls">
                <button type="button" class="flow-btn active" data-flow="paginated" title="Seitenweise umblättern">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path>
                        <polyline points="10 9 7 12 10 15"></polyline>
                        <polyline points="14 9 17 12 14 15"></polyline>
                    </svg>
                    <span>Blättern</span>
                </button>
                <button type="button" class="flow-btn" data-flow="scrolled" title="Kontinuierlich von oben nach unten scrollen">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <polyline points="19 12 12 19 5 12"></polyline>
                    </svg>
                    <span>Scrollen</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Reader Viewport -->
    <main class="reader-viewport" id="readerViewport">
        <!-- Tap zone left (prev) -->
        <div id="prevZone" class="tap-zone tap-zone-left" title="Vorherige Seite">
            <button class="nav-arrow-btn nav-prev-btn" aria-label="Vorherige Seite">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
        </div>

        <!-- The epub.js viewer container -->
        <div id="viewer" class="viewer"></div>

        <!-- Tap zone right (next) -->
        <div id="nextZone" class="tap-zone tap-zone-right" title="Nächste Seite">
            <button class="nav-arrow-btn nav-next-btn" aria-label="Nächste Seite">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>

        <!-- Loading spinner -->
        <div id="readerLoading" class="reader-loading">
            <div class="spinner"></div>
            <p>Buch wird geöffnet...</p>
        </div>
    </main>

    <!-- Bottom Progress & Status Bar -->
    <footer id="readerFooter" class="reader-footer">
        <div class="footer-left">
            <span id="currentChapterTitle" class="current-chapter-title">-</span>
        </div>
        <div class="footer-center">
            <div class="progress-bar-container">
                <div id="readingProgressTrack" class="reading-progress-track">
                    <div id="readingProgressBar" class="reading-progress-fill" style="width: <?= min(100, max(0, (float)$book['progress_percent'])) ?>%;"></div>
                </div>
            </div>
            <span id="readingProgressText" class="reading-progress-text"><?= round((float)$book['progress_percent']) ?>%</span>
        </div>
        <div class="footer-right">
            <span id="syncStatus" class="sync-status" title="Speicherstatus">
                <span class="sync-dot"></span>
                <span class="sync-label">Synchronisiert</span>
            </span>
        </div>
    </footer>

    <!-- Pass book configuration safely to JS -->
    <script>
        window.BOOK_CONFIG = {
            id: <?= (int) $book['id'] ?>,
            url: "serve_epub.php?id=<?= (int) $book['id'] ?>",
            initialCfi: <?= json_encode($book['current_cfi']) ?>,
            initialPercent: <?= (float) $book['progress_percent'] ?>,
            title: <?= json_encode($book['title']) ?>,
            author: <?= json_encode($book['author']) ?>
        };
    </script>

    <!-- JS dependencies with local copy and CDN fallback -->
    <script src="assets/js/jszip.min.js"></script>
    <script>
        if (typeof JSZip === 'undefined') {
            document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"><\/script>');
        }
    </script>

    <script src="assets/js/epub.min.js"></script>
    <script>
        if (typeof ePub === 'undefined') {
            document.write('<script src="https://cdn.jsdelivr.net/npm/epubjs@0.3.93/dist/epub.min.js"><\/script>');
        }
    </script>

    <script src="assets/js/reader.js?v=<?= filemtime(__DIR__ . '/assets/js/reader.js') ?>"></script>
</body>
</html>
