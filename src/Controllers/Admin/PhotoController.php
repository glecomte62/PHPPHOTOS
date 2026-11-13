<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\ExifReader;
use App\Services\ImageException;
use App\Services\ImageProcessor;

final class PhotoController extends AdminController
{
    private const MAX_BYTES = 48 * 1024 * 1024;

    public function upload(int $albumId): void
    {
        $this->verifyCsrf();
        $album = Album::find($albumId) ?? $this->abort404('Album introuvable.');

        $file = $_FILES['photo'] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            json_response(['error' => 'Fichier absent ou trop lourd (48 Mo maximum).'], 413);
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            json_response(['error' => self::uploadError((int) $file['error'])], 400);
        }
        if ($file['size'] > self::MAX_BYTES) {
            json_response(['error' => 'Fichier trop lourd (48 Mo maximum).'], 413);
        }
        if ((new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) !== 'image/jpeg') {
            json_response(['error' => 'Seuls les fichiers JPEG sont acceptés.'], 415);
        }

        $key = bin2hex(random_bytes(12));
        $dir = media_dir($key);
        $exif = (new ExifReader())->read($file['tmp_name']);

        try {
            $size = (new ImageProcessor())->process($file['tmp_name'], $dir, $key, $exif['orientation']);
        } catch (ImageException $e) {
            @unlink($file['tmp_name']);
            json_response(['error' => $e->getMessage()], 422);
        }

        try {
            $id = Photo::create([
                'album_id' => $albumId,
                'file_key' => $key,
                'title' => self::titleFromFilename((string) $file['name']),
                'width' => $size['width'],
                'height' => $size['height'],
                'taken_at' => $exif['taken_at'],
                'camera' => $exif['camera'],
                'lens' => $exif['lens'],
                'focal_length' => $exif['focal_length'],
                'aperture' => $exif['aperture'],
                'shutter' => $exif['shutter'],
                'iso' => $exif['iso'],
                'lat' => $exif['lat'],
                'lng' => $exif['lng'],
            ]);
        } catch (\Throwable $e) {
            ImageProcessor::deleteVariants($dir, $key);
            throw $e;
        } finally {
            @unlink($file['tmp_name']);
        }

        json_response([
            'id' => $id,
            'html' => $this->view->partial('admin/partials/photo-card', [
                'photo' => Photo::find($id),
                'album' => Album::find($albumId),
                'albums' => Album::allForAdmin(),
                'heroId' => (int) Setting::get('hero_photo_id', '0'),
            ]),
        ], 201);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $caption = trim((string) ($_POST['caption'] ?? ''));
        Photo::update($id, [
            'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190),
            'caption' => $caption !== '' ? $caption : null,
        ]);
        $this->done($photo, 'Photo enregistrée.');
    }

    public function gps(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $lat = str_replace(',', '.', trim((string) ($_POST['lat'] ?? '')));
        $lng = str_replace(',', '.', trim((string) ($_POST['lng'] ?? '')));
        if ($lat === '' && $lng === '') {
            Photo::update($id, ['lat' => null, 'lng' => null]);
            $this->done($photo, 'Position GPS retirée.');
        }
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            $this->done($photo, 'Coordonnées GPS invalides (ex. 50.2915 et 2.7775).', true);
        }
        Photo::update($id, ['lat' => (float) $lat, 'lng' => (float) $lng]);
        $this->done($photo, 'Position GPS enregistrée.');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $key = Photo::delete($id);
        if ($key !== null) {
            ImageProcessor::deleteVariants(media_dir($key), $key);
        }
        if ((int) Setting::get('hero_photo_id', '0') === $id) {
            Setting::set('hero_photo_id', null);
        }
        $this->done($photo, 'Photo supprimée.');
    }

    public function move(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        $target = Album::find((int) ($_POST['album_id'] ?? 0));
        if ($target === null) {
            $this->done($photo, 'Album de destination introuvable.', true);
        }
        Photo::move($id, (int) $target['id']);
        $this->done($photo, 'Photo déplacée vers « ' . $target['title'] . ' ».');
    }

    public function cover(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        Album::setCover((int) $photo['album_id'], $id);
        $this->done($photo, 'Couverture de l\'album mise à jour.');
    }

    public function themeCover(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        Theme::setCover((int) $photo['theme_id'], $id);
        $this->done($photo, 'Couverture du thème mise à jour.');
    }

    public function hero(int $id): void
    {
        $this->verifyCsrf();
        $photo = Photo::find($id) ?? $this->abort404('Photo introuvable.');
        Setting::set('hero_photo_id', (string) $id);
        $this->done($photo, 'Photo d\'ouverture de l\'accueil mise à jour.');
    }

    public function reorder(int $albumId): void
    {
        $this->verifyCsrf();
        Photo::reorder($albumId, array_map('intval', (array) ($this->jsonBody()['ids'] ?? [])));
        json_response(['ok' => true]);
    }

    /** @param array<string, mixed> $photo */
    private function done(array $photo, string $message, bool $error = false): never
    {
        if ($this->wantsJson()) {
            json_response($error ? ['error' => $message] : ['ok' => true], $error ? 422 : 200);
        }
        flash($error ? 'error' : 'success', $message);
        redirect('/admin/albums/' . (int) $photo['album_id'] . '/photos#photo-' . (int) $photo['id']);
    }

    private static function titleFromFilename(string $name): string
    {
        $base = (string) pathinfo($name, PATHINFO_FILENAME);
        return mb_substr(trim((string) preg_replace('/[_\-]+/', ' ', $base)), 0, 190);
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop lourd (48 Mo maximum).',
            UPLOAD_ERR_PARTIAL => 'Envoi interrompu, réessayez.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier reçu.',
            default => 'Erreur serveur lors de l\'envoi (code ' . $code . ').',
        };
    }
}
