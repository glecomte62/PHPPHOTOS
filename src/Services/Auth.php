<?php
declare(strict_types=1);

namespace App\Services;

final class Auth
{
    public const IDLE_SECONDS = 7200;

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::one('SELECT id, password_hash FROM admin_users WHERE email = ?', [mb_strtolower(trim($email))]);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        start_session();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_seen'] = time();
        Database::run('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        return true;
    }

    /** Ne démarre pas de session pour un visiteur sans cookie (pages publiques sans cookie). */
    public static function check(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[SESSION_NAME])) {
            return false;
        }
        start_session();
        if (empty($_SESSION['admin_id'])) {
            return false;
        }
        if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > self::IDLE_SECONDS) {
            self::logout();
            return false;
        }
        $_SESSION['admin_seen'] = time();
        return true;
    }

    public static function logout(): void
    {
        start_session();
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
