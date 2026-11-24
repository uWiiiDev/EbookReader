<?php
declare(strict_types=1);

/**
 * Database connection & automatic table schema setup
 */

function getDatabasePath(): string
{
    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0755, true);
    }
    return $storageDir . '/database.sqlite';
}

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dbPath = getDatabasePath();
    $isNew = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Performance and integrity settings
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');

    if ($isNew || !tablesExist($pdo)) {
        initializeDatabase($pdo);
    }

    return $pdo;
}

function tablesExist(PDO $pdo): bool
{
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    return (bool) $stmt->fetch();
}

function initializeDatabase(PDO $pdo): void
{
    // 1. Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Books table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            author TEXT DEFAULT 'Unbekannter Autor',
            filename TEXT NOT NULL,
            cover_path TEXT DEFAULT NULL,
            current_cfi TEXT DEFAULT NULL,
            progress_percent REAL DEFAULT 0.0,
            last_read_at DATETIME DEFAULT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_books_uploaded ON books(uploaded_at DESC);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_books_last_read ON books(last_read_at DESC);");
}

function hasUsers(): bool
{
    $pdo = getDb();
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    return ((int) $stmt->fetchColumn()) > 0;
}
