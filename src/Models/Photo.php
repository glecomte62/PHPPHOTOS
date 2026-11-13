<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Photo
{
    private const SELECT = 'SELECT p.*, a.slug AS album_slug, a.title AS album_title, a.status AS album_status,
        a.theme_id, t.slug AS theme_slug
        FROM photos p JOIN albums a ON a.id = p.album_id JOIN themes t ON t.id = a.theme_id';

    private const EDITABLE = ['title', 'caption', 'lat', 'lng'];

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE p.id = ?', [$id]);
    }

    /**
     * Photo déjà présente dans l'album : même empreinte de fichier, ou, pour les photos importées
     * avant le calcul d'empreinte, même titre d'origine et même date de prise de vue.
     */
    public static function findDuplicate(int $albumId, string $hash, string $title, ?string $takenAt): ?array
    {
        return Database::one(
            'SELECT id FROM photos WHERE album_id = ? AND (file_hash = ?
               OR (file_hash IS NULL AND ? IS NOT NULL AND taken_at = ? AND title = ?)) LIMIT 1',
            [$albumId, $hash, $takenAt, $takenAt, $title]
        );
    }

    /** @return list<array<string, mixed>> */
    public static function forAlbum(int $albumId): array
    {
        return Database::all(self::SELECT . ' WHERE p.album_id = ? ORDER BY p.position, p.id', [$albumId]);
    }

    /** @param array<string, mixed> $data colonnes de la table photos (hors id/position) */
    public static function create(array $data): int
    {
        $data['position'] = (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM photos WHERE album_id = ?', [$data['album_id']]);
        $id = Database::insert('photos', $data);
        Album::touch((int) $data['album_id']);
        return $id;
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        Database::update('photos', $id, array_intersect_key($data, array_flip(self::EDITABLE)));
    }

    /** @return string|null clé des fichiers à supprimer */
    public static function delete(int $id): ?string
    {
        $photo = self::find($id);
        if ($photo === null) {
            return null;
        }
        Database::transaction(static function () use ($id): void {
            Database::run('UPDATE albums SET cover_photo_id = NULL WHERE cover_photo_id = ?', [$id]);
            Database::run('UPDATE themes SET cover_photo_id = NULL WHERE cover_photo_id = ?', [$id]);
            Database::run('DELETE FROM photos WHERE id = ?', [$id]);
        });
        return (string) $photo['file_key'];
    }

    /** @param list<int|string> $ids */
    public static function reorder(int $albumId, array $ids): void
    {
        Database::transaction(static function () use ($albumId, $ids): void {
            foreach (array_values($ids) as $i => $id) {
                Database::run('UPDATE photos SET position = ? WHERE id = ? AND album_id = ?', [$i + 1, (int) $id, $albumId]);
            }
        });
    }

    public static function move(int $id, int $toAlbumId): void
    {
        Database::transaction(static function () use ($id, $toAlbumId): void {
            Database::run('UPDATE albums SET cover_photo_id = NULL WHERE cover_photo_id = ?', [$id]);
            Database::run('UPDATE themes SET cover_photo_id = NULL WHERE cover_photo_id = ?', [$id]);
            $position = (int) Database::value('SELECT COALESCE(MAX(position), 0) + 1 FROM photos WHERE album_id = ?', [$toAlbumId]);
            Database::run('UPDATE photos SET album_id = ?, position = ? WHERE id = ?', [$toAlbumId, $position, $id]);
        });
    }

    /** Photos géolocalisées des albums publiés. @return list<array<string, mixed>> */
    public static function geotagged(): array
    {
        return Database::all(self::SELECT . " WHERE a.status = 'published' AND p.lat IS NOT NULL AND p.lng IS NOT NULL ORDER BY p.id");
    }

    /** @return list<array<string, mixed>> */
    public static function latestInTheme(int $themeId, int $limit): array
    {
        return Database::all(
            self::SELECT . " WHERE a.theme_id = ? AND a.status = 'published' ORDER BY a.published_at DESC, p.position, p.id LIMIT " . (int) $limit,
            [$themeId]
        );
    }
}
