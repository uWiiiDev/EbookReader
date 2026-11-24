<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

$hasExistingUsers = hasUsers();
$isSetup = !$hasExistingUsers || isset($_GET['setup']);

if ($hasExistingUsers && isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Sicherheits-Token ungültig. Bitte versuchen Sie es erneut.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$hasExistingUsers) {
            // Setup Mode
            $confirmPassword = (string) ($_POST['password_confirm'] ?? '');

            if ($username === '') {
                $error = 'Bitte geben Sie einen Benutzernamen an.';
            } elseif (strlen($password) < 6) {
                $error = 'Das Passwort muss mindestens 6 Zeichen lang sein.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Die Passwörter stimmen nicht überein.';
            } else {
                $pdo = getDb();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
                try {
                    $stmt->execute([$username, $hash]);
                    $userId = (int) $pdo->lastInsertId();
                    loginUser($userId, $username);
                    header('Location: index.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Fehler beim Erstellen des Benutzers: ' . $e->getMessage();
                }
            }
        } else {
            // Login Mode
            if ($username === '' || $password === '') {
                $error = 'Bitte Benutzername und Passwort eingeben.';
            } else {
                $pdo = getDb();
                $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? COLLATE NOCASE');
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Check if rehash needed
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                        $upStmt->execute([$newHash, $user['id']]);
                    }

                    loginUser((int) $user['id'], $user['username']);
                    $redirect = $_GET['redirect'] ?? 'index.php';
                    // Safety check on redirect URL
                    if (!str_starts_with($redirect, '/') && !preg_match('/^[a-zA-Z0-9_\-\.\?&=]+$/', $redirect)) {
                        $redirect = 'index.php';
                    }
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'Ungültiger Benutzername oder falsches Passwort.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !$hasExistingUsers ? 'Setup' : 'Anmelden' ?> | EPUB Reader</title>
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
<body class="auth-page">
    <div class="auth-theme-toggle">
        <button type="button" id="themeToggleBtn" class="theme-toggle-btn" title="Design umschalten" aria-label="Design umschalten">
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
    </div>
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path>
                    <path d="M6 6h10"></path>
                    <path d="M6 10h10"></path>
                </svg>
            </div>
            <h1><?= !$hasExistingUsers ? 'Willkommen beim EPUB-Reader' : 'EPUB Reader' ?></h1>
            <p class="auth-subtitle">
                <?= !$hasExistingUsers
                    ? 'Legen Sie Ihren Administrator-Zugang fest, um die Bibliothek einzurichten.'
                    : 'Bitte melden Sie sich an, um auf Ihre Bibliothek zuzugreifen.' ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php<?= !$hasExistingUsers ? '?setup=1' : '' ?>" class="auth-form">
            <?= csrfField() ?>

            <div class="form-group">
                <label for="username">Benutzername</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    autofocus
                    value="<?= e($_POST['username'] ?? (!$hasExistingUsers ? 'admin' : '')) ?>"
                    placeholder="z. B. admin"
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="password">Passwort</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    placeholder="<?= !$hasExistingUsers ? 'Mindestens 6 Zeichen' : 'Ihr Passwort' ?>"
                    autocomplete="<?= !$hasExistingUsers ? 'new-password' : 'current-password' ?>"
                >
            </div>

            <?php if (!$hasExistingUsers): ?>
                <div class="form-group">
                    <label for="password_confirm">Passwort wiederholen</label>
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        required
                        placeholder="Passwort erneut eingeben"
                        autocomplete="new-password"
                    >
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block">
                <?= !$hasExistingUsers ? 'Bibliothek initialisieren' : 'Anmelden' ?>
            </button>
        </form>

        <div class="auth-footer">
            <span class="text-muted">Self-Hosted EPUB Reader &bull; PHP & SQLite</span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('themeToggleBtn');
            const sunIcon = toggleBtn?.querySelector('.theme-sun-icon');
            const moonIcon = toggleBtn?.querySelector('.theme-moon-icon');

            function updateIcons(theme) {
                if (theme === 'dark') {
                    sunIcon?.classList.remove('hidden');
                    moonIcon?.classList.add('hidden');
                } else {
                    sunIcon?.classList.add('hidden');
                    moonIcon?.classList.remove('hidden');
                }
            }

            const current = document.documentElement.getAttribute('data-theme') || 'light';
            updateIcons(current);

            toggleBtn?.addEventListener('click', () => {
                const now = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', now);
                localStorage.setItem('app_theme', now);
                localStorage.setItem('epub_theme', now);
                updateIcons(now);
            });
        });
    </script>
</body>
</html>
