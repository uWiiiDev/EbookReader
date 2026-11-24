<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function generateUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function formatRelativeDate(?string $datetime): string
{
    if (!$datetime) {
        return 'Noch nie';
    }
    try {
        $date = new DateTime($datetime);
        $now = new DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $date->format('d.m.Y');
        }
        if ($diff->m > 0 || $diff->d >= 7) {
            return $date->format('d.m.Y');
        }
        if ($diff->d > 1) {
            return 'vor ' . $diff->d . ' Tagen';
        }
        if ($diff->d === 1) {
            return 'Gestern, ' . $date->format('H:i');
        }
        if ($diff->h > 0) {
            return 'vor ' . $diff->h . ' Std.';
        }
        if ($diff->i > 0) {
            return 'vor ' . $diff->i . ' Min.';
        }
        return 'Gerade eben';
    } catch (Exception) {
        return $datetime;
    }
}
