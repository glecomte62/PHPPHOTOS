<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\Slug;

final class Theme
{
    private const SELECT = "SELECT t.*,
        COALESCE(
          (SELECT p.file_key FROM photos p JOIN albums a ON a.id = p.album_id
            WHERE p.id = t.cover_photo_id AND a.theme_id = t.id AND a.status = 'published'),
          (SELECT p.file_key FROM photos p JOIN albums a ON a.id = p.album_id
            WHERE a.theme_id = t.id AND a.status = 'published'
            ORDER BY a.position, a.id, p.position, p.id LIMIT 1)
        ) AS cover_key,
        (SELECT COUNT(*) FROM albums a WHERE a.theme_id = t.id AND a.status = 'published') AS published_albums,
        (SELECT COUNT(*) FROM albums a WHERE a.theme_id = t.id) AS total_albums
        FROM themes t";

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Database::all(self::SELECT . ' ORDER BY t.position, t.id');
    }

    /** Thèmes ayant au moins un album publié. @return list<array<string, mixed>> */
    public static function visible(): array
    {
        return Database::all('SELECT * FROM (' . self::SELECT . ') x WHERE x.published_albums > 0 ORDER BY x.position, x.id');
    }

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE t.id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::one(self::SELECT . ' WHERE t.slug = ?', [$slug]);
    }

    public static function featured(): ?array
    {
        return Database::one(self::SELECT . ' WHERE t.featured = 1 LIMIT 1');
    }

    /** @param array{title: string, intro: string, featured: bool} $data */
    public static function create(array $data): int
    {
        return Database::transaction(static function () use ($data): int {
            if ($data['featured']) {
                Database::run('UPDATE themes SET featured = 0');
            }
            return Database::insert('themes', [
                'slug' => self::uniqueSlug($data['title']),
                'title' => $data['title'],
                'intro' => $data['intro'] !== '' ? $data['intro'] : null,
                'featured' => $data['featured'] ? 1 : 0,
                'position' => (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM themes'),
            ]);
        });
    }

    /** @param array{title: string, slug: string, intro: string, featured: bool} $data */
    public static function update(int $id, array $data): void
    {
        Database::transaction(static function () use ($id, $data): void {
            if ($data['featured']) {
                Database::run('UPDATE themes SET featured = 0 WHERE id <> ?', [$id]);
            }
            Database::update('themes', $id, [
                'title' => $data['title'],
                'slug' => self::uniqueSlug($data['slug'] !== '' ? $data['slug'] : $data['title'], $id),
                'intro' => $data['intro'] !== '' ? $data['intro'] : null,
                'featured' => $data['featured'] ? 1 : 0,
            ]);
        });
    }

    public static function delete(int $id): void
    {
        if ((int) Database::value('SELECT COUNT(*) FROM albums WHERE theme_id = ?', [$id]) > 0) {
            throw new \DomainException('Ce thème contient encore des albums : déplacez-les ou supprimez-les d\'abord.');
        }
        Database::run('DELETE FROM themes WHERE id = ?', [$id]);
    }

    /** @param list<int|string> $ids */
    public static function reorder(array $ids): void
    {
        Database::transaction(static function () use ($ids): void {
            foreach (array_values($ids) as $i => $id) {
                Database::run('UPDATE themes SET position = ? WHERE id = ?', [$i + 1, (int) $id]);
            }
        });
    }

    public static function setCover(int $id, int $photoId): void
    {
        Database::run('UPDATE themes SET cover_photo_id = ? WHERE id = ?', [$photoId, $id]);
    }

    public static function uniqueSlug(string $text, ?int $exceptId = null): string
    {
        return Slug::unique(
            $text,
            static fn (string $s): bool => (bool) Database::value('SELECT 1 FROM themes WHERE slug = ? AND id <> ?', [$s, $exceptId ?? 0]),
            true
        );
    }
}
