<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Page;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\HtmlSanitizer;
use App\Services\ImageException;
use App\Services\PresentationImages;

final class ContentController extends AdminController
{
    public function edit(): void
    {
        $heroId = (int) Setting::get('hero_photo_id', '0');
        $page = Page::find('presentation') ?? throw new \RuntimeException('Page « presentation » absente : lancez php bin/migrate.php');
        $page['body_editable'] = HtmlSanitizer::toEditable((string) $page['body_html']);
        $this->page('admin/content', [
            'title' => 'Contenus',
            'tagline' => (string) Setting::get('site_tagline', ''),
            'skyIntro' => (string) Setting::get('sky_intro', ''),
            'reelPlaces' => implode(', ', Setting::heroReelPlaces()),
            'reelNight' => Setting::heroReelNight(),
            'hero' => $heroId > 0 ? Photo::find($heroId) : null,
            'page' => $page,
        ]);
    }

    public function update(): void
    {
        $this->rejectOversizedPost();
        $this->verifyCsrf();
        Setting::set('site_tagline', mb_substr(trim((string) ($_POST['site_tagline'] ?? '')), 0, 150));
        Setting::set('sky_intro', trim((string) ($_POST['sky_intro'] ?? '')));
        Setting::set('hero_reel_places', mb_substr(trim((string) ($_POST['hero_reel_places'] ?? '')), 0, 300));
        Setting::set('hero_reel_night', (string) max(0, min(8, (int) ($_POST['hero_reel_night'] ?? 0))));

        $title = trim((string) ($_POST['title'] ?? ''));
        $lead = mb_substr(trim((string) ($_POST['lead'] ?? '')), 0, 300);
        $pilot = trim((string) ($_POST['pilot_info'] ?? ''));
        Page::update('presentation', [
            'title' => $title !== '' ? mb_substr($title, 0, 190) : 'Présentation',
            'lead' => $lead !== '' ? $lead : null,
            'body_html' => HtmlSanitizer::clean(HtmlSanitizer::paragraphs((string) ($_POST['body_html'] ?? ''))),
            'pilot_info' => $pilot !== '' ? $pilot : null,
        ]);

        $errors = [];
        foreach (PresentationImages::SLOTS as $slot) {
            $file = $_FILES[$slot] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'L\'envoi de l\'image « ' . $slot . ' » a échoué.';
                continue;
            }
            try {
                PresentationImages::replace($slot, $file['tmp_name']);
            } catch (ImageException $e) {
                $errors[] = $e->getMessage();
            } finally {
                @unlink($file['tmp_name']);
            }
        }

        $errors ? flash('error', implode(' ', $errors)) : flash('success', 'Contenus enregistrés.');
        redirect('/admin/content');
    }
}
