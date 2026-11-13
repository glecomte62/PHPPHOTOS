<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;

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
}
