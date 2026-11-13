<?php
declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ThemeController;
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
$router->get('/admin/themes', static fn () => (new ThemeController($view))->index());
$router->get('/admin/themes/new', static fn () => (new ThemeController($view))->create());
$router->post('/admin/themes', static fn () => (new ThemeController($view))->store());
$router->post('/admin/themes/reorder', static fn () => (new ThemeController($view))->reorder());
$router->get('/admin/themes/{id:\d+}', static fn (string $id) => (new ThemeController($view))->edit((int) $id));
$router->post('/admin/themes/{id:\d+}', static fn (string $id) => (new ThemeController($view))->update((int) $id));
$router->post('/admin/themes/{id:\d+}/delete', static fn (string $id) => (new ThemeController($view))->delete((int) $id));

// --- Public ---
$router->get('/', static fn () => $public()->home());

return $router;
