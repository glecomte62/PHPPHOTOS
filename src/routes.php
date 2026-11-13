<?php
declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\PublicController;
use App\Services\Router;
use App\Services\View;

/** @var View $view */
$router = new Router();
$public = static fn (): PublicController => new PublicController($view);

// --- Administration ---
$router->get('/admin/login', static fn () => (new AuthController($view))->form());
$router->post('/admin/login', static fn () => (new AuthController($view))->login());
$router->post('/admin/logout', static fn () => (new AuthController($view))->logout());
$router->get('/admin', static fn () => (new DashboardController($view))->index());

// --- Public ---
$router->get('/', static fn () => $public()->home());

return $router;
