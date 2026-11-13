<?php
declare(strict_types=1);

use App\Controllers\Admin\AlbumController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\ContentController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PhotoController;
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
$router->get('/admin/albums', static fn () => (new AlbumController($view))->index());
$router->get('/admin/albums/new', static fn () => (new AlbumController($view))->create());
$router->post('/admin/albums', static fn () => (new AlbumController($view))->store());
$router->post('/admin/albums/reorder', static fn () => (new AlbumController($view))->reorder());
$router->get('/admin/albums/{id:\d+}', static fn (string $id) => (new AlbumController($view))->edit((int) $id));
$router->post('/admin/albums/{id:\d+}', static fn (string $id) => (new AlbumController($view))->update((int) $id));
$router->post('/admin/albums/{id:\d+}/delete', static fn (string $id) => (new AlbumController($view))->delete((int) $id));
$router->get('/admin/albums/{id:\d+}/photos', static fn (string $id) => (new AlbumController($view))->photos((int) $id));
$router->post('/admin/albums/{id:\d+}/photos', static fn (string $id) => (new PhotoController($view))->upload((int) $id));
$router->post('/admin/albums/{id:\d+}/photos/reorder', static fn (string $id) => (new PhotoController($view))->reorder((int) $id));
$router->post('/admin/photos/{id:\d+}', static fn (string $id) => (new PhotoController($view))->update((int) $id));
$router->post('/admin/photos/{id:\d+}/gps', static fn (string $id) => (new PhotoController($view))->gps((int) $id));
$router->post('/admin/photos/{id:\d+}/delete', static fn (string $id) => (new PhotoController($view))->delete((int) $id));
$router->post('/admin/photos/{id:\d+}/move', static fn (string $id) => (new PhotoController($view))->move((int) $id));
$router->post('/admin/photos/{id:\d+}/cover', static fn (string $id) => (new PhotoController($view))->cover((int) $id));
$router->post('/admin/photos/{id:\d+}/theme-cover', static fn (string $id) => (new PhotoController($view))->themeCover((int) $id));
$router->post('/admin/photos/{id:\d+}/hero', static fn (string $id) => (new PhotoController($view))->hero((int) $id));
$router->get('/admin/content', static fn () => (new ContentController($view))->edit());
$router->post('/admin/content', static fn () => (new ContentController($view))->update());

// --- Public ---
$router->get('/', static fn () => $public()->home());

return $router;
