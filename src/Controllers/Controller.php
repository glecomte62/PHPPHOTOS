<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\View;

abstract class Controller
{
    /** Leaflet 1.9.4 et Leaflet.markercluster 1.5.3, hébergés localement. @return list<string> */
    protected static function leafletCss(): array
    {
        return [asset('vendor/leaflet/leaflet.css')];
    }

    /** @return list<string> */
    protected static function clusterCss(): array
    {
        return [asset('vendor/markercluster/MarkerCluster.css'), asset('vendor/markercluster/MarkerCluster.Default.css')];
    }

    protected static function leafletJs(): string
    {
        return asset('vendor/leaflet/leaflet.js');
    }

    protected static function clusterJs(): string
    {
        return asset('vendor/markercluster/leaflet.markercluster.js');
    }

    public function __construct(protected View $view)
    {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        echo $this->view->render($template, $data);
    }

    protected function notFound(): void
    {
        $this->render('public/error', ['meta' => ['title' => 'Page introuvable'], 'code' => 404, 'message' => 'Cette page n\'existe pas ou plus.'], 404);
    }
}
