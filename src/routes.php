<?php
declare(strict_types=1);

use App\Controllers\PublicController;
use App\Services\Router;
use App\Services\View;

/** @var View $view */
$router = new Router();
$public = static fn (): PublicController => new PublicController($view);

// --- Public ---
$router->get('/', static fn () => $public()->home());

return $router;
