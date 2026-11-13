<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Album;
use App\Models\Photo;
use App\Models\Setting;
use App\Models\Theme;
use App\Services\ImageProcessor;

final class AlbumController extends AdminController
{
    public function index(): void
    {
        $albums = Album::allForAdmin();
        $groups = [];
        foreach (Theme::all() as $theme) {
            $groups[] = [
                'theme' => $theme,
                'albums' => array_values(array_filter($albums, static fn (array $a): bool => (int) $a['theme_id'] === (int) $theme['id'])),
            ];
        }
        $this->page('admin/albums/index', ['title' => 'Albums', 'groups' => $groups]);
    }

    public function create(): void
    {
        $themes = Theme::all();
        if ($themes === []) {
            flash('error', 'Créez d\'abord un thème.');
            redirect('/admin/themes/new');
        }
        $this->form([
            'id' => null, 'theme_id' => (int) ($_GET['theme'] ?? $themes[0]['id']), 'title' => '', 'slug' => '',
            'description' => '', 'shot_on' => '', 'location' => '', 'status' => 'draft',
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => null] + $data, $errors, 422);
            return;
        }
        $id = Album::create($data);
        flash('success', 'Album créé : ajoutez vos photos.');
        redirect('/admin/albums/' . $id . '/photos');
    }

    public function edit(int $id): void
    {
        $album = Album::find($id) ?? $this->abort404('Album introuvable.');
        $this->form($album);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        Album::find($id) ?? $this->abort404('Album introuvable.');
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => $id] + $data, $errors, 422);
            return;
        }
        Album::update($id, $data);
        flash('success', 'Album enregistré.');
        redirect('/admin/albums/' . $id . '/photos');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        Album::find($id) ?? $this->abort404('Album introuvable.');
        $heroId = (int) Setting::get('hero_photo_id', '0');
        $photoIds = array_map(static fn (array $p): int => (int) $p['id'], Photo::forAlbum($id));
        foreach (Album::delete($id) as $key) {
            ImageProcessor::deleteVariants(media_dir($key), $key);
        }
        if (in_array($heroId, $photoIds, true)) {
            Setting::set('hero_photo_id', null);
        }
        flash('success', 'Album et photos supprimés.');
        redirect('/admin/albums');
    }

    public function reorder(): void
    {
        $this->verifyCsrf();
        Album::reorder(array_map('intval', (array) ($this->jsonBody()['ids'] ?? [])));
        json_response(['ok' => true]);
    }

    public function photos(int $id): void
    {
        $album = Album::find($id) ?? $this->abort404('Album introuvable.');
        $this->page('admin/albums/photos', [
            'title' => $album['title'],
            'album' => $album,
            'photos' => Photo::forAlbum($id),
            'albums' => Album::allForAdmin(),
            'heroId' => (int) Setting::get('hero_photo_id', '0'),
            'scripts' => [asset('js/admin-upload.js')],
        ]);
    }

    /** @param array<string, mixed> $album @param array<string, string> $errors */
    private function form(array $album, array $errors = [], int $status = 200): void
    {
        $this->page('admin/albums/form', [
            'title' => $album['id'] === null ? 'Nouvel album' : 'Infos de l\'album',
            'album' => $album,
            'themes' => Theme::all(),
            'errors' => $errors,
        ], $status);
    }

    /** @return array{theme_id: int, title: string, slug: string, description: string, shot_on: string, location: string, status: string} */
    private function input(): array
    {
        return [
            'theme_id' => (int) ($_POST['theme_id'] ?? 0),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'shot_on' => trim((string) ($_POST['shot_on'] ?? '')),
            'location' => trim((string) ($_POST['location'] ?? '')),
            'status' => ($_POST['status'] ?? '') === 'published' ? 'published' : 'draft',
        ];
    }

    /** @return array<string, string> */
    private function validate(array $data): array
    {
        $errors = [];
        if (Theme::find($data['theme_id']) === null) {
            $errors['theme_id'] = 'Choisissez un thème.';
        }
        if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
            $errors['title'] = 'Titre obligatoire (150 caractères maximum).';
        }
        if ($data['shot_on'] !== '' && \DateTimeImmutable::createFromFormat('!Y-m-d', $data['shot_on']) === false) {
            $errors['shot_on'] = 'Date invalide.';
        }
        if (mb_strlen($data['location']) > 150) {
            $errors['location'] = '150 caractères maximum.';
        }
        return $errors;
    }
}
