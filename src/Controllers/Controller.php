<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\View;

abstract class Controller
{
    protected const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    protected const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    protected const CLUSTER_CSS = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css';
    protected const CLUSTER_DEFAULT_CSS = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css';
    protected const CLUSTER_JS = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js';

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
