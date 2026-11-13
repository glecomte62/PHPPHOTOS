<?php
declare(strict_types=1);

use App\Services\Csrf;
use App\Services\Env;

const SESSION_NAME = 'gl_session';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function absolute_url(string $path = '/'): string
{
    return rtrim((string) Env::get('APP_URL', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = ROOT . '/public/assets/' . $path;
    return '/assets/' . $path . '?v=' . (is_file($file) ? filemtime($file) : '0');
}

function media_path(string $key, int $width, string $ext = 'webp'): string
{
    return 'media/' . substr($key, 0, 2) . '/' . $key . '-' . $width . '.' . $ext;
}

function media_url(string $key, int $width, string $ext = 'webp'): string
{
    return '/' . media_path($key, $width, $ext);
}

function media_dir(string $key): string
{
    return ROOT . '/public/media/' . substr($key, 0, 2);
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, ?string $message = null): ?string
{
    start_session();
    if ($message !== null) {
        $_SESSION['_flash'][$type] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$type] ?? null;
    unset($_SESSION['_flash'][$type]);
    return $value;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function redirect(string $to, int $code = 303): never
{
    header('Location: ' . $to, true, $code);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

const MONTHS_FR = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function format_date_fr(?string $date): string
{
    $t = $date ? strtotime($date) : false;
    if ($t === false) {
        return '';
    }
    return (int) date('j', $t) . ' ' . MONTHS_FR[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

function format_month_fr(?string $date): string
{
    $t = $date ? strtotime($date) : false;
    if ($t === false) {
        return '';
    }
    return MONTHS_FR[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}
