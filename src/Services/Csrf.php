<?php
declare(strict_types=1);

namespace App\Services;

final class Csrf
{
    public static function token(): string
    {
        start_session();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function check(mixed $token): bool
    {
        start_session();
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
