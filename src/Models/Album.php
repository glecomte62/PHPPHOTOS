<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\Slug;

final class Album
{
    private const SELECT = "SELECT a.*, t.slug AS theme_slug, t.title AS theme_title,
        COALESCE(
          (SELECT p.file_key FROM photos p WHERE p.id = a.cover_photo_id),
          (SELECT p.file_key FROM photos p WHERE p.album_id = a.id ORDER BY p.position, p.id LIMIT 1)
        ) AS cover_key,
        (SELECT COUNT(*) FROM photos p WHERE p.album_id = a.id) AS photo_count
        FROM albums a JOIN themes t ON t.id = a.theme_id";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE a.id = ?', [$id]);
    }

    public static function findBySlugs(string $themeSlug, string $albumSlug, bool $includeDrafts = false): ?array
    {
        $sql = self::SELECT . ' WHERE t.slug = ? AND a.slug = ?' . ($includeDrafts ? '' : " AND a.status = 'published'");
        return Database::one($sql, [$themeSlug, $albumSlug]);
    }

    /** @return list<array<string, mixed>> */
    public static function forTheme(int $themeId, bool $publishedOnly = true): array
    {
        $sql = self::SELECT . ' WHERE a.theme_id = ?' . ($publishedOnly ? " AND a.status = 'published'" : '') . ' ORDER BY a.position, a.id';
        return Database::all($sql, [$themeId]);
    }

    /** @return list<array<string, mixed>> */
    public static function latestPublished(int $limit = 6): array
    {
        return Database::all(self::SELECT . " WHERE a.status = 'published' ORDER BY a.published_at DESC, a.id DESC LIMIT " . (int) $limit);
    }

    /** @return list<array<string, mixed>> */
    public static function allPublished(): array
    {
        return Database::all(self::SELECT . " WHERE a.status = 'published' ORDER BY t.position, a.position, a.id");
    }

    /** @return list<array<string, mixed>> */
    public static function allForAdmin(): array
    {
        return Database::all(self::SELECT . ' ORDER BY t.position, t.id, a.position, a.id');
    }

    /** @return list<array<string, mixed>> */
    public static function recentForAdmin(int $limit = 8): array
    {
        return Database::all(self::SELECT . ' ORDER BY a.updated_at DESC, a.id DESC LIMIT ' . (int) $limit);
    }

    /** @param array{theme_id: int, title: string, slug: string, description: string, shot_on: string, location: string, status: string} $data */
    public static function create(array $data): int
    {
        $published = $data['status'] === 'published';
        return Database::insert('albums', [
            'theme_id' => $data['theme_id'],
            'slug' => self::uniqueSlug($data['theme_id'], $data['title']),
            'title' => $data['title'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'shot_on' => $data['shot_on'] !== '' ? $data['shot_on'] : null,
            'location' => $data['location'] !== '' ? $data['location'] : null,
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? date('Y-m-d H:i:s') : null,
            'position' => (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM albums WHERE theme_id = ?', [$data['theme_id']]),
        ]);
    }

    /** @param array{theme_id: int, title: string, slug: string, description: string, shot_on: string, location: string, status: string} $data */
    public static function update(int $id, array $data): void
    {
        $current = self::find($id) ?? throw new \DomainException('Album introuvable.');
        $fields = [
            'theme_id' => $data['theme_id'],
            'title' => $data['title'],
            'slug' => self::uniqueSlug($data['theme_id'], $data['slug'] !== '' ? $data['slug'] : $data['title'], $id),
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'shot_on' => $data['shot_on'] !== '' ? $data['shot_on'] : null,
            'location' => $data['location'] !== '' ? $data['location'] : null,
            'status' => $data['status'] === 'published' ? 'published' : 'draft',
        ];
        if ($fields['status'] === 'published' && $current['published_at'] === null) {
            $fields['published_at'] = date('Y-m-d H:i:s');
        }
        Database::update('albums', $id, $fields);
    }

    /** Supprime l'album et ses photos en base. @return list<string> clés des fichiers à supprimer */
    public static function delete(int $id): array
    {
        $keys = array_column(Database::all('SELECT file_key FROM photos WHERE album_id = ?', [$id]), 'file_key');
        Database::run('DELETE FROM albums WHERE id = ?', [$id]);
        return $keys;
    }

    /** @param list<int|string> $ids */
    public static function reorder(array $ids): void
    {
        Database::transaction(static function () use ($ids): void {
            foreach (array_values($ids) as $i => $id) {
                Database::run('UPDATE albums SET position = ? WHERE id = ?', [$i + 1, (int) $id]);
            }
        });
    }

    public static function setCover(int $albumId, ?int $photoId): void
    {
        Database::run('UPDATE albums SET cover_photo_id = ? WHERE id = ?', [$photoId, $albumId]);
    }

    public static function touch(int $albumId): void
    {
        Database::run('UPDATE albums SET updated_at = NOW() WHERE id = ?', [$albumId]);
    }

    public static function uniqueSlug(int $themeId, string $text, ?int $exceptId = null): string
    {
        return Slug::unique(
            $text,
            static fn (string $s): bool => (bool) Database::value(
                'SELECT 1 FROM albums WHERE theme_id = ? AND slug = ? AND id <> ?',
                [$themeId, $s, $exceptId ?? 0]
            )
        );
    }
}
