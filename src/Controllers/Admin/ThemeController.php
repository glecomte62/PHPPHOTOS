<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Theme;

final class ThemeController extends AdminController
{
    public function index(): void
    {
        $this->page('admin/themes/index', ['title' => 'Thèmes', 'themes' => Theme::all()]);
    }

    public function create(): void
    {
        $this->form(['id' => null, 'title' => '', 'slug' => '', 'intro' => '', 'featured' => 0]);
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => null] + $data, $errors, 422);
            return;
        }
        Theme::create($data);
        flash('success', 'Thème créé.');
        redirect('/admin/themes');
    }

    public function edit(int $id): void
    {
        $theme = Theme::find($id) ?? $this->abort404('Thème introuvable.');
        $this->form($theme);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();
        Theme::find($id) ?? $this->abort404('Thème introuvable.');
        $data = $this->input();
        if ($errors = $this->validate($data)) {
            $this->form(['id' => $id] + $data, $errors, 422);
            return;
        }
        Theme::update($id, $data);
        flash('success', 'Thème enregistré.');
        redirect('/admin/themes');
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        try {
            Theme::delete($id);
            flash('success', 'Thème supprimé.');
        } catch (\DomainException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/themes');
    }

    public function reorder(): void
    {
        $this->verifyCsrf();
        Theme::reorder(array_map('intval', (array) ($this->jsonBody()['ids'] ?? [])));
        json_response(['ok' => true]);
    }

    /** @param array<string, mixed> $theme @param array<string, string> $errors */
    private function form(array $theme, array $errors = [], int $status = 200): void
    {
        $this->page('admin/themes/form', [
            'title' => $theme['id'] === null ? 'Nouveau thème' : 'Modifier le thème',
            'theme' => $theme,
            'errors' => $errors,
        ], $status);
    }

    /** @return array{title: string, slug: string, intro: string, featured: bool} */
    private function input(): array
    {
        return [
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'intro' => trim((string) ($_POST['intro'] ?? '')),
            'featured' => isset($_POST['featured']),
        ];
    }

    /** @return array<string, string> */
    private function validate(array $data): array
    {
        $errors = [];
        if ($data['title'] === '' || mb_strlen($data['title']) > 150) {
            $errors['title'] = 'Titre obligatoire (150 caractères maximum).';
        }
        return $errors;
    }
}
