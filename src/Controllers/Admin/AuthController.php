<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\Auth;
use App\Services\Csrf;
use App\Services\RateLimiter;

final class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW = 900;

    public function form(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        $this->show();
    }

    public function login(): void
    {
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            $this->show('Session expirée, réessayez.', 419);
            return;
        }
        $limiter = new RateLimiter(ROOT . '/storage/ratelimit');
        $key = 'login:' . client_ip();
        if ($limiter->tooMany($key, self::MAX_ATTEMPTS, self::WINDOW)) {
            $this->show('Trop de tentatives. Réessayez dans 15 minutes.', 429);
            return;
        }
        if (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            $limiter->clear($key);
            redirect('/admin');
        }
        $limiter->hit($key, self::WINDOW);
        $this->show('E-mail ou mot de passe incorrect.', 401);
    }

    public function logout(): void
    {
        if (Csrf::check($_POST['_csrf'] ?? null)) {
            Auth::logout();
        }
        redirect('/admin/login');
    }

    private function show(?string $error = null, int $status = 200): void
    {
        http_response_code($status);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store');
        echo $this->view->render('admin/login', ['error' => $error, 'title' => 'Connexion'], 'admin/layout');
    }
}
