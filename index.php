<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();

$currentUser = getCurrentUser();
$pdo = getDb();

// Sort options
$sort = $_GET['sort'] ?? 'recent';
$orderBy = match ($sort) {
    'newest' => 'uploaded_at DESC',
    'title'  => 'title COLLATE NOCASE ASC',
    'author' => 'author COLLATE NOCASE ASC',
    default  => '(last_read_at IS NOT NULL) DESC, last_read_at DESC, uploaded_at DESC'
};

$stmt = $pdo->query("SELECT * FROM books ORDER BY {$orderBy}");
$books = $stmt->fetchAll();
$totalBooks = count($books);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(getCsrfToken()) ?>">
    <title>Bibliothek | EPUB Reader</title>
    <script>
        (function() {
            const saved = localStorage.getItem('app_theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">
    <header class="app-header">
        <div class="header-container">
            <div class="brand">
                <a href="index.php" class="brand-link">
                    <span class="brand-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path>
                            <path d="M6 6h10"></path>
                            <path d="M6 10h10"></path>
                        </svg>
                    </span>
                    <span class="brand-title">EPUB Reader</span>
                </a>
            </div>

            <div class="header-search">
                <div class="search-box">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="searchInput" placeholder="Buchtitel oder Autor suchen..." autocomplete="off">
                    <button type="button" id="searchClear" class="search-clear hidden" aria-label="Suche leeren">&times;</button>
                </div>
            </div>

            <div class="header-user">
                <!-- Theme Toggle Button -->
                <button type="button" id="themeToggleBtn" class="theme-toggle-btn" title="Design umschalten (Hell/Dunkel)" aria-label="Design umschalten">
                    <svg class="theme-sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                    <svg class="theme-moon-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </button>

                <span class="user-name">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <?= e($currentUser['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline btn-sm" title="Abmelden">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Abmelden</span>
                </a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <!-- Upload Section -->
        <section class="upload-section">
            <div id="dropzone" class="dropzone">
                <input type="file" id="fileInput" accept=".epub,application/epub+zip" class="file-input-hidden">
                <div class="dropzone-content">
                    <div class="dropzone-icon">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                    </div>
                    <div class="dropzone-text">
                        <h3>EPUB-Datei hochladen</h3>
                        <p>Datei hierher ziehen oder <button type="button" class="btn-link" id="browseBtn">durchsuchen</button></p>
                    </div>
                    <span class="dropzone-hint">Unterstützt .epub Dateien mit automatischem Auslesen von Titel, Autor & Cover</span>
                </div>
                <!-- Progress bar during upload -->
                <div id="uploadProgressContainer" class="upload-progress-container hidden">
                    <div class="upload-progress-info">
                        <span id="uploadFilename" class="upload-filename">Wird hochgeladen...</span>
                        <span id="uploadPercent" class="upload-percent">0%</span>
                    </div>
                    <div class="progress-track">
                        <div id="uploadProgressBar" class="progress-fill" style="width: 0%;"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Library Toolbar -->
        <section class="library-toolbar">
            <div class="toolbar-stats">
                <h2>Ihre Bibliothek</h2>
                <span class="badge-count" id="bookCountBadge"><?= $totalBooks ?> <?= $totalBooks === 1 ? 'Buch' : 'Bücher' ?></span>
            </div>
            <div class="toolbar-controls">
                <label for="sortSelect" class="sr-only">Sortierung</label>
                <div class="select-wrapper">
                    <select id="sortSelect" onchange="location.href='index.php?sort=' + this.value">
                        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Zuletzt gelesen</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Neueste zuerst</option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Titel (A-Z)</option>
                        <option value="author" <?= $sort === 'author' ? 'selected' : '' ?>>Autor (A-Z)</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Books Grid -->
        <section class="books-section">
            <div id="booksGrid" class="books-grid">
                <?php foreach ($books as $b): ?>
                    <?php
                        $progress = (float) $b['progress_percent'];
                        $isFinished = $progress >= 99.0;
                    ?>
                    <article class="book-card" data-id="<?= $b['id'] ?>" data-title="<?= e(mb_strtolower($b['title'])) ?>" data-author="<?= e(mb_strtolower($b['author'])) ?>">
                        <div class="book-cover-container">
                            <a href="reader.php?id=<?= $b['id'] ?>" class="book-cover-link" aria-label="<?= e($b['title']) ?> lesen">
                                <img
                                    src="serve_cover.php?id=<?= $b['id'] ?>"
                                    alt="Cover von <?= e($b['title']) ?>"
                                    class="book-cover-img"
                                    loading="lazy"
                                    onerror="this.src='serve_cover.php?id=0'"
                                >
                            </a>
                            <div class="book-progress-badge <?= $isFinished ? 'badge-finished' : ($progress > 0 ? 'badge-reading' : 'badge-new') ?>">
                                <?= $isFinished ? 'Beendet' : ($progress > 0 ? round($progress) . ' %' : 'Neu') ?>
                            </div>
                            <div class="book-progress-bar-wrap">
                                <div class="book-progress-bar-fill" style="width: <?= min(100, max(0, $progress)) ?>%;"></div>
                            </div>
                        </div>

                        <div class="book-info">
                            <h3 class="book-title" title="<?= e($b['title']) ?>">
                                <a href="reader.php?id=<?= $b['id'] ?>"><?= e($b['title']) ?></a>
                            </h3>
                            <p class="book-author" title="<?= e($b['author']) ?>"><?= e($b['author']) ?></p>

                            <div class="book-meta">
                                <span class="book-last-read">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    <?= $b['last_read_at'] ? formatRelativeDate($b['last_read_at']) : 'Neu' ?>
                                </span>
                            </div>

                            <div class="book-actions">
                                <a href="reader.php?id=<?= $b['id'] ?>" class="btn btn-primary btn-sm btn-read">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                    </svg>
                                    <span><?= $progress > 0 ? 'Weiterlesen' : 'Lesen' ?></span>
                                </a>
                                <button type="button" class="btn btn-icon btn-delete" data-id="<?= $b['id'] ?>" data-title="<?= e($b['title']) ?>" title="Buch löschen" aria-label="Buch löschen">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state <?= $totalBooks > 0 ? 'hidden' : '' ?>">
                <div class="empty-state-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path>
                        <path d="M6 6h10"></path>
                        <path d="M6 10h10"></path>
                    </svg>
                </div>
                <h3>Keine Bücher gefunden</h3>
                <p>Ziehen Sie eine .epub-Datei in das obige Feld oder suchen Sie nach einem anderen Begriff.</p>
            </div>
        </section>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-backdrop hidden">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>Buch löschen</h3>
                <button type="button" class="modal-close" id="modalCloseBtn">&times;</button>
            </div>
            <div class="modal-body">
                <p>Möchten Sie das Buch <strong id="deleteModalTitle"></strong> wirklich unwiderruflich von der Festplatte und aus der Datenbank löschen?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="modalCancelBtn">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="modalConfirmDeleteBtn">Löschen</button>
            </div>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
