<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\Auth;

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
            'meta' => ['image' => $hero ? media_path((string) $hero['file_key'], 1280, 'jpg') : null],
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
            'styles' => [self::LEAFLET_CSS],
            'scripts' => [self::LEAFLET_JS, asset('js/mosaic.js'), asset('js/viewer.js')],
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
}
