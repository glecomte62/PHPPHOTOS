<?php
declare(strict_types=1);

// Serveur PHP intégré (local) : servir les fichiers statiques directement
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

// realpath : la racine web peut être un lien symbolique vers public/
require dirname((string) realpath(__DIR__)) . '/src/bootstrap.php';

use App\Services\Env;
use App\Services\View;

$view = new View(ROOT . '/templates');

if (Env::get('APP_ENV') !== 'prod') {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
}

set_exception_handler(static function (\Throwable $e) use ($view): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (Env::get('APP_DEBUG') === '1') {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    try {
        echo $view->render('public/error', ['meta' => ['title' => 'Erreur'], 'code' => 500, 'message' => 'Une erreur est survenue. Réessayez dans un instant.']);
    } catch (\Throwable) {
        echo 'Une erreur est survenue.';
    }
});

/** @var App\Services\Router $router */
$router = require ROOT . '/src/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', static function () use ($view): void {
    App\Controllers\Controller::redirectLegacy();
    http_response_code(404);
    echo $view->render('public/error', ['meta' => ['title' => 'Page introuvable'], 'code' => 404, 'message' => 'Cette page n\'existe pas ou plus.']);
});
