<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\TagList;

final class Tag
{
    /** @return list<array{id: int, slug: string, name: string}> */
    public static function forPhoto(int $photoId): array
    {
        return Database::all(
            'SELECT t.id, t.slug, t.name FROM tags t JOIN photo_tags pt ON pt.tag_id = t.id WHERE pt.photo_id = ? ORDER BY t.name',
            [$photoId]
        );
    }

    /**
     * Mots-clés de plusieurs photos en une requête.
     *
     * @param list<int> $photoIds
     * @return array<int, list<array{slug: string, name: string}>> photo_id => mots-clés
     */
    public static function forPhotos(array $photoIds): array
    {
        if ($photoIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($photoIds), '?'));
        $map = [];
        foreach (Database::all(
            "SELECT pt.photo_id, t.slug, t.name FROM tags t JOIN photo_tags pt ON pt.tag_id = t.id WHERE pt.photo_id IN ($marks) ORDER BY t.name",
            array_map('intval', $photoIds)
        ) as $row) {
            $map[(int) $row['photo_id']][] = ['slug' => $row['slug'], 'name' => $row['name']];
        }
        return $map;
    }

    /** Remplace les mots-clés d'une photo. @param list<string> $names */
    public static function setForPhoto(int $photoId, array $names): void
    {
        Database::transaction(static function () use ($photoId, $names): void {
            Database::run('DELETE FROM photo_tags WHERE photo_id = ?', [$photoId]);
            foreach ($names as $name) {
                Database::run('INSERT IGNORE INTO photo_tags (photo_id, tag_id) VALUES (?, ?)', [$photoId, self::idFor($name)]);
            }
        });
        self::purge();
    }

    /** Ajoute des mots-clés à toutes les photos d'un album (sans retirer les autres). @param list<string> $names */
    public static function addToAlbum(int $albumId, array $names): int
    {
        $photoIds = array_map('intval', array_column(Database::all('SELECT id FROM photos WHERE album_id = ?', [$albumId]), 'id'));
        Database::transaction(static function () use ($photoIds, $names): void {
            foreach ($names as $name) {
                $tagId = self::idFor($name);
                foreach ($photoIds as $photoId) {
                    Database::run('INSERT IGNORE INTO photo_tags (photo_id, tag_id) VALUES (?, ?)', [$photoId, $tagId]);
                }
            }
        });
        return count($photoIds);
    }

    /** Retire un mot-clé de toutes les photos d'un album. */
    public static function removeFromAlbum(int $albumId, string $name): void
    {
        Database::run(
            'DELETE pt FROM photo_tags pt JOIN photos p ON p.id = pt.photo_id JOIN tags t ON t.id = pt.tag_id WHERE p.album_id = ? AND t.slug = ?',
            [$albumId, TagList::slug($name)]
        );
        self::purge();
    }

    /**
     * Mots-clés présents sur toutes les photos d'un album (pour l'admin).
     *
     * @return list<string>
     */
    public static function commonToAlbum(int $albumId): array
    {
        return array_column(Database::all(
            'SELECT t.name FROM tags t JOIN photo_tags pt ON pt.tag_id = t.id JOIN photos p ON p.id = pt.photo_id
             WHERE p.album_id = ? GROUP BY t.id, t.name
             HAVING COUNT(*) = (SELECT COUNT(*) FROM photos WHERE album_id = ?) ORDER BY t.name',
            [$albumId, $albumId]
        ), 'name');
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::one('SELECT id, slug, name FROM tags WHERE slug = ?', [$slug]);
    }

    /**
     * Mots-clés utilisés par des photos publiées, avec leur nombre de photos.
     *
     * @return list<array{slug: string, name: string, n: int}>
     */
    public static function cloud(): array
    {
        return array_map(
            static fn (array $r): array => ['slug' => $r['slug'], 'name' => $r['name'], 'n' => (int) $r['n']],
            Database::all(
                "SELECT t.slug, t.name, COUNT(*) AS n FROM tags t
                 JOIN photo_tags pt ON pt.tag_id = t.id JOIN photos p ON p.id = pt.photo_id JOIN albums a ON a.id = p.album_id
                 WHERE a.status = 'published' GROUP BY t.id, t.slug, t.name ORDER BY t.name"
            )
        );
    }

    /** Identifiant du mot-clé, créé au besoin (le premier nom saisi est gardé). */
    private static function idFor(string $name): int
    {
        $slug = TagList::slug($name);
        Database::run('INSERT IGNORE INTO tags (slug, name) VALUES (?, ?)', [$slug, mb_substr($name, 0, TagList::MAX_LENGTH)]);
        return (int) Database::value('SELECT id FROM tags WHERE slug = ?', [$slug]);
    }

    /** Supprime les mots-clés qui ne sont plus utilisés. */
    private static function purge(): void
    {
        Database::run('DELETE t FROM tags t LEFT JOIN photo_tags pt ON pt.tag_id = t.id WHERE pt.tag_id IS NULL');
    }
}
