<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Album;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Theme;
use App\Services\Auth;
use App\Services\ContactForm;
use App\Services\Env;
use App\Services\Geo;
use App\Services\License;
use App\Services\Slug;
use App\Services\PhotoFilter;
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
        $reel = Photo::heroReel(8, $hero, Setting::heroReelPlaces(), Setting::heroReelNight());

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
            'reel' => $reel,
            'scripts' => count($reel) > 1 ? [asset('js/hero-reel.js')] : [],
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
        $license = License::get(Setting::get('photo_license'));
        $downloads = Setting::get('downloads_enabled', '0') === '1';
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
                    'author' => ['@type' => 'Person', 'name' => License::AUTHOR],
                    'copyrightHolder' => ['@type' => 'Person', 'name' => License::AUTHOR],
                ] + ($license['url'] ? ['license' => $license['url']] : []),
            ],
            'album' => $a,
            'photos' => $photos,
            'baseUrl' => $base,
            'openIndex' => $openIndex,
            'viewerPhotos' => array_map(static fn (array $p): array => self::viewerPhoto($p, $license, $downloads), $photos),
            // Jeton horodaté du formulaire de commentaire (anti-robots, comme le formulaire de contact)
            'commentToken' => ContactForm::token((string) Env::get('APP_KEY', ''), time()),
            'styles' => self::leafletCss(),
            'scripts' => [self::leafletJs(), asset('js/mosaic.js'), asset('js/viewer.js')],
        ]);
    }

    /**
     * @param array<string, mixed> $p
     * @param array{short: string, url: ?string} $license
     */
    private static function viewerPhoto(array $p, array $license, bool $downloads): array
    {
        $key = (string) $p['file_key'];
        return [
            'id' => (int) $p['id'],
            'title' => (string) $p['title'],
            'caption' => (string) ($p['caption'] ?? ''),
            'file' => $p['original_name'] ?? null,
            'contact' => '/contact?photo=' . (int) $p['id'],
            'credit' => License::notice($license, $p['taken_at'] !== null ? (string) $p['taken_at'] : null),
            'licenseUrl' => $license['url'],
            'download' => $downloads ? '/telecharger/' . (int) $p['id'] : null,
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
        $facets = Photo::facets();
        $filter = PhotoFilter::fromInput($_GET, array_map(static fn (array $list): array => array_column($list, 'value'), $facets));

        $total = Photo::countSearch($terms, $filter);
        $pages = max(1, (int) ceil($total / self::SEARCH_PHOTO_LIMIT));
        $page = min($pages, max(1, (int) (is_string($_GET['page'] ?? null) ? $_GET['page'] : 1)));
        $photos = Photo::search($terms, $filter, self::SEARCH_PHOTO_LIMIT, ($page - 1) * self::SEARCH_PHOTO_LIMIT);

        $this->render('public/search', [
            'meta' => [
                'title' => $query !== '' ? 'Recherche : ' . $query : 'Recherche',
                'description' => 'Rechercher une photo ou un album par lieu, sujet, titre, appareil, objectif ou réglages.',
                'robots' => 'noindex, follow',
            ],
            'query' => $query,
            'searched' => $terms !== [] || !$filter->isEmpty(),
            // Les albums n'ont pas de données EXIF : on ne les propose que pour une recherche sans filtre
            'albums' => $filter->isEmpty() ? Album::search($terms, 12) : [],
            'photos' => $photos,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filter' => $filter,
            'facets' => $facets,
            'scripts' => [asset('js/mosaic.js'), asset('js/search.js')],
        ]);
    }

    /** Fil chronologique de toutes les photos, avec archives par année et par mois de prise de vue. */
    public function stream(?string $year = null, ?string $month = null): void
    {
        $y = $year !== null ? (int) $year : null;
        $m = $month !== null ? (int) $month : null;
        if ($m !== null && ($m < 1 || $m > 12)) {
            $this->notFound();
            return;
        }
        $total = Photo::countStream($y, $m);
        if ($y !== null && $total === 0) {
            $this->notFound();
            return;
        }
        $pages = max(1, (int) ceil($total / self::SEARCH_PHOTO_LIMIT));
        $page = min($pages, max(1, (int) (is_string($_GET['page'] ?? null) ? $_GET['page'] : 1)));
        $title = $y === null ? 'Toutes les photos' : ($m !== null ? ucfirst(MONTHS_FR[$m - 1]) . ' ' . $y : 'Photos de ' . $y);

        $this->render('public/stream', [
            'meta' => [
                'title' => $title . ($page > 1 ? ' — page ' . $page : ''),
                'description' => 'Toutes les photos de Guillaume Lecomte, de la plus récente à la plus ancienne, classées par année et par mois.',
            ],
            'photos' => Photo::stream($y, $m, self::SEARCH_PHOTO_LIMIT, ($page - 1) * self::SEARCH_PHOTO_LIMIT),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'year' => $y,
            'month' => $m,
            'archive' => Photo::archive(),
            'scripts' => [asset('js/mosaic.js')],
        ]);
    }

    /** Photos les plus vues ou les plus aimées, façon « Explore ». */
    public function popular(): void
    {
        $by = ($_GET['tri'] ?? '') === 'aimees' ? 'aimees' : 'vues';
        $this->render('public/popular', [
            'meta' => [
                'title' => $by === 'aimees' ? 'Les photos les plus aimées' : 'Les photos les plus vues',
                'description' => 'Les photos de Guillaume Lecomte les plus regardées et les plus aimées par les visiteurs.',
            ],
            'by' => $by,
            'photos' => Photo::popular($by, self::SEARCH_PHOTO_LIMIT),
            'scripts' => [asset('js/mosaic.js')],
        ]);
    }

    /** Nuage de mots-clés. */
    public function tags(): void
    {
        $cloud = Tag::cloud();
        $max = max([1, ...array_column($cloud, 'n')]);
        $this->render('public/tags', [
            'meta' => ['title' => 'Mots-clés', 'description' => 'Tous les mots-clés des photos de Guillaume Lecomte : lieux, sujets, avions, monuments…'],
            'cloud' => $cloud,
            'max' => $max,
        ]);
    }

    /** Photos d'un mot-clé. */
    public function tag(string $slug): void
    {
        $tag = Tag::findBySlug($slug);
        $total = $tag !== null ? Photo::countForTag((int) $tag['id']) : 0;
        if ($tag === null || $total === 0) {
            $this->notFound();
            return;
        }
        $pages = max(1, (int) ceil($total / self::SEARCH_PHOTO_LIMIT));
        $page = min($pages, max(1, (int) (is_string($_GET['page'] ?? null) ? $_GET['page'] : 1)));
        $this->render('public/tag', [
            'meta' => [
                'title' => $tag['name'] . ($page > 1 ? ' — page ' . $page : ''),
                'description' => $total . ' photo' . ($total > 1 ? 's' : '') . ' de Guillaume Lecomte avec le mot-clé « ' . $tag['name'] . ' ».',
            ],
            'tag' => $tag,
            'photos' => Photo::forTag((int) $tag['id'], self::SEARCH_PHOTO_LIMIT, ($page - 1) * self::SEARCH_PHOTO_LIMIT),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'scripts' => [asset('js/mosaic.js')],
        ]);
    }

    /** Flux RSS des derniers albums publiés. */
    public function rss(): void
    {
        $albums = Album::latestPublished(20);
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $date = static fn (?string $d): string => date(DATE_RSS, $d && ($t = strtotime($d)) !== false ? $t : time());
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">' . "\n<channel>\n";
        echo '  <title>Guillaume Lecomte — Photographe</title>' . "\n";
        echo '  <link>' . e(absolute_url('/')) . "</link>\n";
        echo '  <atom:link href="' . e(absolute_url('/rss.xml')) . '" rel="self" type="application/rss+xml"/>' . "\n";
        echo "  <description>Les nouveaux albums de Guillaume Lecomte : paysages, patrimoine, aviation et vues du ciel.</description>\n";
        echo "  <language>fr-FR</language>\n";
        if ($albums) {
            echo '  <lastBuildDate>' . $date((string) $albums[0]['published_at']) . "</lastBuildDate>\n";
        }
        foreach ($albums as $a) {
            $url = absolute_url('/' . $a['theme_slug'] . '/' . $a['slug']);
            $image = $a['cover_key'] ? absolute_url(media_url((string) $a['cover_key'], 1280, 'jpg')) : null;
            $meta = implode(' · ', array_filter([(string) $a['location'], format_date_fr($a['shot_on']), $a['photo_count'] . ' photos']));
            $html = ($image ? '<p><a href="' . e($url) . '"><img src="' . e($image) . '" alt="' . e($a['title']) . '" width="1280"></a></p>' : '')
                . '<p><em>' . e($meta) . '</em></p>'
                . ($a['description'] ? '<p>' . nl2br(e((string) $a['description'])) . '</p>' : '');
            echo "  <item>\n";
            echo '    <title>' . e($a['title']) . "</title>\n";
            echo '    <link>' . e($url) . "</link>\n";
            echo '    <guid isPermaLink="true">' . e($url) . "</guid>\n";
            echo '    <category>' . e($a['theme_title']) . "</category>\n";
            echo '    <pubDate>' . $date((string) $a['published_at']) . "</pubDate>\n";
            echo '    <description><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $html) . "]]></description>\n";
            if ($image) {
                echo '    <media:content url="' . e($image) . '" medium="image" type="image/jpeg"/>' . "\n";
            }
            echo "  </item>\n";
        }
        echo "</channel>\n</rss>";
    }

    /** Téléchargement d'une photo en 1280 px, si l'option est activée dans Contenus. */
    public function download(string $id): void
    {
        $photo = Photo::find((int) $id);
        $file = $photo !== null ? ROOT . '/public/' . media_path((string) $photo['file_key'], 1280, 'jpg') : null;
        if ($photo === null || $photo['album_status'] !== 'published' || Setting::get('downloads_enabled', '0') !== '1' || !is_file((string) $file)) {
            $this->notFound();
            return;
        }
        $name = Slug::make(photo_label((string) $photo['title'], (string) $photo['album_title'])) . '-guillaume-lecomte-' . (int) $photo['id'] . '.jpg';
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize((string) $file));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: public, max-age=86400');
        header('X-Robots-Tag: noindex');
        readfile((string) $file);
    }

    /** Données d'une photo chargées par la visionneuse : photos à proximité. */
    public function photoApi(string $id): void
    {
        $photo = Photo::find((int) $id);
        if ($photo === null || $photo['album_status'] !== 'published') {
            json_response(['error' => 'Photo introuvable'], 404);
        }
        header('Cache-Control: no-cache');
        json_response([
            'views' => (int) $photo['views'],
            'likes' => (int) $photo['likes'],
            'comments' => array_map(static fn (array $c): array => [
                'name' => (string) $c['author_name'],
                'message' => (string) $c['message'],
                'date' => format_date_fr((string) $c['created_at']),
            ], Comment::approvedForPhoto((int) $photo['id'])),
            'tags' => array_map(static fn (array $t): array => [
                'name' => (string) $t['name'],
                'url' => '/mots-cles/' . $t['slug'],
            ], Tag::forPhoto((int) $photo['id'])),
            'nearby' => array_map(static fn (array $p): array => [
                'title' => photo_label((string) $p['title'], (string) $p['album_title']),
                'album' => (string) $p['album_title'],
                'distance' => Geo::distanceLabel((float) $p['distance_km']),
                'thumb' => media_url((string) $p['file_key'], 480, 'webp'),
                'thumbJpg' => media_url((string) $p['file_key'], 480, 'jpg'),
                'url' => '/' . $p['theme_slug'] . '/' . $p['album_slug'] . '/photo/' . $p['id'],
            ], Photo::nearby($photo, 10.0, 6)),
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
        $entries = [['loc' => '/'], ['loc' => '/photos'], ['loc' => '/presentation'], ['loc' => '/carte'], ['loc' => '/contact']];
        foreach (Theme::visible() as $t) {
            $entries[] = ['loc' => '/' . $t['slug'], 'lastmod' => (string) $t['updated_at']];
        }
        $cloud = Tag::cloud();
        if ($cloud) {
            $entries[] = ['loc' => '/mots-cles'];
            foreach ($cloud as $tag) {
                $entries[] = ['loc' => '/mots-cles/' . $tag['slug']];
            }
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
