<?php
declare(strict_types=1);

use App\Controllers\Admin\AlbumController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CommentController;
use App\Controllers\Admin\ContentController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PhotoController;
use App\Controllers\Admin\ThemeController;
use App\Controllers\ContactController;
use App\Controllers\EngagementController;
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
$router->post('/admin/albums/{id:\d+}/tags', static fn (string $id) => (new PhotoController($view))->addAlbumTags((int) $id));
$router->post('/admin/albums/{id:\d+}/tags/remove', static fn (string $id) => (new PhotoController($view))->removeAlbumTag((int) $id));
$router->post('/admin/albums/{id:\d+}/photos/reorder', static fn (string $id) => (new PhotoController($view))->reorder((int) $id));
$router->post('/admin/photos/{id:\d+}', static fn (string $id) => (new PhotoController($view))->update((int) $id));
$router->post('/admin/photos/{id:\d+}/gps', static fn (string $id) => (new PhotoController($view))->gps((int) $id));
$router->post('/admin/photos/{id:\d+}/delete', static fn (string $id) => (new PhotoController($view))->delete((int) $id));
$router->post('/admin/photos/{id:\d+}/move', static fn (string $id) => (new PhotoController($view))->move((int) $id));
$router->post('/admin/photos/{id:\d+}/cover', static fn (string $id) => (new PhotoController($view))->cover((int) $id));
$router->post('/admin/photos/{id:\d+}/theme-cover', static fn (string $id) => (new PhotoController($view))->themeCover((int) $id));
$router->post('/admin/photos/{id:\d+}/hero', static fn (string $id) => (new PhotoController($view))->hero((int) $id));
$router->get('/admin/commentaires', static fn () => (new CommentController($view))->index());
$router->post('/admin/commentaires/{id:\d+}/publier', static fn (string $id) => (new CommentController($view))->approve((int) $id));
$router->post('/admin/commentaires/{id:\d+}/supprimer', static fn (string $id) => (new CommentController($view))->delete((int) $id));
$router->get('/admin/content', static fn () => (new ContentController($view))->edit());
$router->post('/admin/content', static fn () => (new ContentController($view))->update());

// --- Public ---
$router->get('/', static fn () => $public()->home());
$router->get('/carte', static fn () => $public()->map());
$router->get('/carte.json', static fn () => $public()->mapData());
$router->get('/presentation', static fn () => $public()->presentation());
$router->get('/contact', static fn () => (new ContactController($view))->show());
$router->post('/contact', static fn () => (new ContactController($view))->send());
$router->get('/recherche', static fn () => $public()->search());
$router->get('/photos', static fn () => $public()->stream());
// Pas d'accolades dans les motifs : le routeur s'arrête à la première « } »
$router->get('/photos/{year:\d\d\d\d}', static fn (string $year) => $public()->stream($year));
$router->get('/photos/{year:\d\d\d\d}/{month:\d\d}', static fn (string $year, string $month) => $public()->stream($year, $month));
$router->get('/rss.xml', static fn () => $public()->rss());
$router->get('/mots-cles', static fn () => $public()->tags());
$router->get('/mots-cles/{slug:[a-z0-9-]+}', static fn (string $slug) => $public()->tag($slug));
$router->get('/api/photos/{id:\d+}', static fn (string $id) => $public()->photoApi($id));
$router->get('/telecharger/{id:\d+}', static fn (string $id) => $public()->download($id));
$router->post('/api/photos/{id:\d+}/vue', static fn (string $id) => (new EngagementController($view))->view($id));
$router->post('/api/photos/{id:\d+}/aime', static fn (string $id) => (new EngagementController($view))->like($id));
$router->post('/api/photos/{id:\d+}/commentaires', static fn (string $id) => (new EngagementController($view))->comment($id));
$router->get('/populaires', static fn () => $public()->popular());
$router->get('/opensearch.xml', static fn () => $public()->openSearch());
$router->get('/sitemap.xml', static fn () => $public()->sitemap());
$router->get('/robots.txt', static fn () => $public()->robots());
$router->get('/{theme}/{album}/photo/{photo:\d+}', static fn (string $theme, string $album, string $photo) => $public()->album($theme, $album, $photo));
$router->get('/{theme}/{album}', static fn (string $theme, string $album) => $public()->album($theme, $album));
$router->get('/{theme}', static fn (string $theme) => $public()->theme($theme));

return $router;
