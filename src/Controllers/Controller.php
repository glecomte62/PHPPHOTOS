<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\LegacyRedirect;
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

    /** Ancienne adresse de l'ancien site : redirection permanente vers la page équivalente. */
    public static function redirectLegacy(): void
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            return;
        }
        $path = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
        $target = LegacyRedirect::resolve($path, static function (string $slug): ?string {
            $row = Database::one(
                "SELECT t.slug AS theme, a.slug AS album FROM albums a JOIN themes t ON t.id = a.theme_id
                 WHERE a.slug = ? AND a.status = 'published' LIMIT 1",
                [$slug]
            );
            return $row ? '/' . $row['theme'] . '/' . $row['album'] : null;
        });
        if ($target !== null && $target !== '/' . trim($path, '/')) {
            header('Cache-Control: public, max-age=86400');
            redirect($target, 301);
        }
    }

    protected function notFound(): void
    {
        self::redirectLegacy();
        $this->render('public/error', ['meta' => ['title' => 'Page introuvable'], 'code' => 404, 'message' => 'Cette page n\'existe pas ou plus.'], 404);
    }
}
