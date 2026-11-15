<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Album;
use App\Models\Page;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\Auth;
use App\Services\Env;
use App\Services\PresentationImages;
use App\Services\SearchQuery;

final class PublicController extends Controller
{
    public function home(): void
    {
        $featured = Theme::featured();
        if ($featured !== null && (int) $featured['published_albums'] === 0) {
            $featured = null;
        }
        $heroId = (int) Setting::get('hero_photo_id', '0');
        $hero = $heroId > 0 ? Photo::find($heroId) : null;
        if ($hero !== null && $hero['album_status'] !== 'published') {
            $hero = null;
        }

        $this->render('public/home', [
            'meta' => [
                'image' => $hero ? media_path((string) $hero['file_key'], 1280, 'jpg') : null,
                'jsonld' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        ['@type' => 'WebSite', 'name' => 'Guillaume Lecomte — Photographe', 'url' => absolute_url('/'), 'inLanguage' => 'fr-FR'],
                        ['@type' => 'Person', 'name' => 'Guillaume Lecomte', 'jobTitle' => 'Photographe amateur et pilote ULM', 'url' => absolute_url('/presentation')],
                    ],
                ],
            ],
            'hero' => $hero,
            'tagline' => (string) Setting::get('site_tagline', 'Photographe & pilote ULM'),
            'featured' => $featured,
            'skyPhotos' => $featured ? Photo::latestInTheme((int) $featured['id'], 5) : [],
            'skyIntro' => (string) Setting::get('sky_intro', ''),
            'latest' => Album::latestPublished(6),
            'themes' => Theme::visible(),
        ]);
    }

    public function theme(string $theme): void
    {
        $t = Theme::findBySlug($theme);
        $isAdmin = Auth::check();
        if ($t === null || ((int) $t['published_albums'] === 0 && !$isAdmin)) {
            $this->notFound();
            return;
        }
        $this->render('public/theme', [
            'meta' => [
                'title' => $t['title'],
                'description' => $t['intro'] ? mb_substr((string) $t['intro'], 0, 160) : null,
                'image' => $t['cover_key'] ? media_path((string) $t['cover_key'], 1280, 'jpg') : null,
            ],
            'theme' => $t,
            'albums' => Album::forTheme((int) $t['id'], !$isAdmin),
        ]);
    }

    public function album(string $theme, string $album, ?string $photo = null): void
    {
        $a = Album::findBySlugs($theme, $album, Auth::check());
        if ($a === null) {
            $this->notFound();
            return;
        }
        $photos = Photo::forAlbum((int) $a['id']);
        $openIndex = null;
        if ($photo !== null) {
            foreach ($photos as $i => $p) {
                if ((int) $p['id'] === (int) $photo) {
                    $openIndex = $i;
                }
            }
            if ($openIndex === null) {
                $this->notFound();
                return;
            }
        }
        $base = '/' . $a['theme_slug'] . '/' . $a['slug'];
        $current = $openIndex !== null ? $photos[$openIndex] : null;
        $imageKey = $current['file_key'] ?? $a['cover_key'];
        $description = $current && $current['caption'] ? (string) $current['caption'] : (string) $a['description'];

        $this->render('public/album', [
            'meta' => [
                'title' => $current ? (($current['title'] !== '' ? $current['title'] . ' — ' : '') . $a['title']) : $a['title'],
                'description' => $description !== '' ? mb_substr($description, 0, 160) : null,
                'image' => $imageKey ? media_path((string) $imageKey, 1280, 'jpg') : null,
                'type' => 'article',
                'jsonld' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'ImageGallery',
                    'name' => $a['title'],
                    'url' => absolute_url($base),
                    'author' => ['@type' => 'Person', 'name' => 'Guillaume Lecomte'],
                ],
            ],
            'album' => $a,
            'photos' => $photos,
            'baseUrl' => $base,
            'openIndex' => $openIndex,
            'viewerPhotos' => array_map(self::viewerPhoto(...), $photos),
            'styles' => self::leafletCss(),
            'scripts' => [self::leafletJs(), asset('js/mosaic.js'), asset('js/viewer.js')],
        ]);
    }

    /** @param array<string, mixed> $p */
    private static function viewerPhoto(array $p): array
    {
        $key = (string) $p['file_key'];
        return [
            'id' => (int) $p['id'],
            'title' => (string) $p['title'],
            'caption' => (string) ($p['caption'] ?? ''),
            'webp' => media_url($key, 2560, 'webp'),
            'jpg' => media_url($key, 2560, 'jpg'),
            'webpM' => media_url($key, 1280, 'webp'),
            'jpgM' => media_url($key, 1280, 'jpg'),
            'taken' => $p['taken_at'] ? format_date_fr((string) $p['taken_at']) : null,
            'camera' => $p['camera'],
            'lens' => $p['lens'],
            'focal' => $p['focal_length'],
            'aperture' => $p['aperture'],
            'shutter' => $p['shutter'],
            'iso' => $p['iso'] !== null ? (string) $p['iso'] : null,
            'lat' => $p['lat'] !== null ? (float) $p['lat'] : null,
            'lng' => $p['lng'] !== null ? (float) $p['lng'] : null,
        ];
    }

    public function map(): void
    {
        $this->render('public/map', [
            'meta' => ['title' => 'Carte des photos', 'description' => 'Les lieux de mes photos sur une carte : paysages, villes et vues du ciel.'],
            'styles' => [...self::leafletCss(), ...self::clusterCss()],
            'scripts' => [self::leafletJs(), self::clusterJs(), asset('js/map.js')],
        ]);
    }

    public function mapData(): void
    {
        header('Cache-Control: public, max-age=300');
        json_response(array_map(static fn (array $p): array => [
            'lat' => (float) $p['lat'],
            'lng' => (float) $p['lng'],
            'title' => (string) $p['title'],
            'album' => (string) $p['album_title'],
            'thumb' => media_url((string) $p['file_key'], 480),
            'url' => '/' . $p['theme_slug'] . '/' . $p['album_slug'] . '/photo/' . $p['id'],
        ], Photo::geotagged()));
    }

    private const SEARCH_PHOTO_LIMIT = 60;

    public function search(): void
    {
        $query = SearchQuery::normalize($_GET['q'] ?? '');
        $terms = SearchQuery::terms($query);
        $photos = Photo::search($terms, self::SEARCH_PHOTO_LIMIT);

        $this->render('public/search', [
            'meta' => [
                'title' => $query !== '' ? 'Recherche : ' . $query : 'Recherche',
                'description' => 'Rechercher une photo ou un album par lieu, sujet ou titre.',
                'robots' => 'noindex, follow',
            ],
            'query' => $query,
            'searched' => $terms !== [],
            'albums' => Album::search($terms, 12),
            'photos' => $photos,
            'photoLimit' => self::SEARCH_PHOTO_LIMIT,
            'scripts' => [asset('js/mosaic.js')],
        ]);
    }

    /** Description OpenSearch : permet de chercher sur le site depuis la barre d'adresse du navigateur. */
    public function openSearch(): void
    {
        header('Content-Type: application/opensearchdescription+xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/" xmlns:moz="http://www.mozilla.org/2006/browser/search/">' . "\n";
        echo "  <ShortName>Guillaume Lecomte</ShortName>\n";
        echo "  <Description>Rechercher dans les photos de Guillaume Lecomte</Description>\n";
        echo "  <InputEncoding>UTF-8</InputEncoding>\n";
        echo "  <Language>fr-FR</Language>\n";
        echo '  <Image width="32" height="32" type="image/png">' . e(absolute_url('/assets/img/favicon-32.png')) . "</Image>\n";
        echo '  <Url type="text/html" method="get" template="' . e(absolute_url('/recherche')) . '?q={searchTerms}"/>' . "\n";
        echo '  <Url type="application/opensearchdescription+xml" rel="self" template="' . e(absolute_url('/opensearch.xml')) . '"/>' . "\n";
        echo '  <moz:SearchForm>' . e(absolute_url('/recherche')) . "</moz:SearchForm>\n";
        echo '</OpenSearchDescription>';
    }

    public function presentation(): void
    {
        $page = Page::find('presentation') ?? [
            'title' => 'Présentation', 'lead' => null, 'body_html' => '', 'pilot_info' => null,
            'banner_image' => null, 'portrait_image' => null,
        ];
        $this->render('public/presentation', [
            'meta' => [
                'title' => $page['title'],
                'description' => $page['lead'],
                'image' => $page['banner_image'] ? ltrim(PresentationImages::url((string) $page['banner_image'], 1280), '/') : null,
                'type' => 'profile',
                'jsonld' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Person',
                    'name' => 'Guillaume Lecomte',
                    'jobTitle' => 'Photographe amateur et pilote ULM',
                    'url' => absolute_url('/'),
                ],
            ],
            'page' => $page,
        ]);
    }

    public function sitemap(): void
    {
        /** @var list<array{loc: string, lastmod?: string, images?: list<array{loc: string, title: string}>}> $entries */
        $entries = [['loc' => '/'], ['loc' => '/presentation'], ['loc' => '/carte'], ['loc' => '/contact']];
        foreach (Theme::visible() as $t) {
            $entries[] = ['loc' => '/' . $t['slug'], 'lastmod' => (string) $t['updated_at']];
        }
        foreach (Album::allPublished() as $a) {
            $images = array_map(static fn (array $p): array => [
                'loc' => media_url((string) $p['file_key'], 1280, 'jpg'),
                'title' => photo_label((string) $p['title'], (string) $a['title']),
            ], Photo::forAlbum((int) $a['id']));
            $entries[] = ['loc' => '/' . $a['theme_slug'] . '/' . $a['slug'], 'lastmod' => (string) $a['updated_at'], 'images' => $images];
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($entries as $entry) {
            echo '  <url><loc>' . e(absolute_url($entry['loc'])) . '</loc>';
            if (!empty($entry['lastmod']) && ($t = strtotime($entry['lastmod'])) !== false) {
                echo '<lastmod>' . date('Y-m-d', $t) . '</lastmod>';
            }
            foreach ($entry['images'] ?? [] as $img) {
                echo '<image:image><image:loc>' . e(absolute_url($img['loc'])) . '</image:loc><image:title>' . e($img['title']) . '</image:title></image:image>';
            }
            echo "</url>\n";
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        if (Env::get('APP_ENV') !== 'prod') {
            echo "User-agent: *\nDisallow: /\n";
            return;
        }
        echo "User-agent: *\nDisallow: /admin\n\nSitemap: " . absolute_url('/sitemap.xml') . "\n";
    }
}
