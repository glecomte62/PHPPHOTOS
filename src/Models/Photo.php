<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\SearchQuery;

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

    /**
     * Photos des albums publiés dont le titre, la légende, l'album, le lieu ou la galerie contiennent
     * tous les mots cherchés (collation insensible aux accents et à la casse). Les correspondances
     * dans le titre puis dans la légende remontent en premier.
     *
     * @param list<string> $terms
     * @return list<array<string, mixed>>
     */
    public static function search(array $terms, int $limit): array
    {
        if ($terms === []) {
            return [];
        }
        $where = [];
        $score = [];
        $whereParams = [];
        $scoreParams = [];
        foreach ($terms as $term) {
            $like = SearchQuery::likePattern($term);
            $where[] = "CONCAT_WS(' ', p.title, p.caption, a.title, a.location, t.title) LIKE ?";
            $whereParams[] = $like;
            $score[] = '(p.title LIKE ?) * 3 + (COALESCE(p.caption, \'\') LIKE ?) * 2';
            array_push($scoreParams, $like, $like);
        }
        return Database::all(
            self::SELECT . " WHERE a.status = 'published' AND " . implode(' AND ', $where)
            . ' ORDER BY (' . implode(' + ', $score) . ') DESC, a.published_at DESC, p.position, p.id LIMIT ' . (int) $limit,
            [...$whereParams, ...$scoreParams]
        );
    }

    /** @return list<array<string, mixed>> */
    public static function latestInTheme(int $themeId, int $limit): array
    {
        return Database::all(
            self::SELECT . " WHERE a.theme_id = ? AND a.status = 'published' ORDER BY a.published_at DESC, p.position, p.id LIMIT " . (int) $limit,
            [$themeId]
        );
    }

    /**
     * Photo de nuit : pose d'une demi-seconde ou plus (l'heure de prise de vue n'est pas fiable,
     * l'appareil restant à l'heure française en voyage), ou titre/légende qui l'annonce.
     */
    private const NIGHT = "((p.shutter NOT LIKE '1/%' AND CAST(p.shutter AS DECIMAL(10,2)) >= 0.5)
        OR CONCAT_WS(' ', p.title, p.caption) REGEXP 'nuit|nocturne|illumin|minuit|heure bleue')";

    /**
     * Photos du diaporama d'accueil : des vues en paysage tirées au hasard dans les albums publiés,
     * une seule par album pour varier les sujets. Y figurent toujours $nightCount photos de nuit et
     * une photo de chaque lieu de $places (cherché dans le titre et le lieu de l'album, sans tenir
     * compte des accents ni de la casse) ; le reste est complété au hasard. La photo $first (la photo
     * d'accueil) ouvre le diaporama et compte pour son album.
     *
     * @param ?array<string, mixed> $first
     * @param list<string> $places
     * @return list<array<string, mixed>>
     */
    public static function heroReel(int $limit, ?array $first = null, array $places = [], int $nightCount = 0): array
    {
        $excludeId = $first ? (int) $first['id'] : 0;
        $base = self::SELECT . " WHERE a.status = 'published' AND p.width >= p.height * 1.3 AND p.id <> ?";
        $rows = Database::all($base . ' ORDER BY RAND() LIMIT 300', [$excludeId]);
        $night = $nightCount > 0 ? Database::all($base . ' AND ' . self::NIGHT . ' ORDER BY RAND() LIMIT 100', [$excludeId]) : [];

        $fold = static function (string $s): string {
            $decomposed = class_exists(\Normalizer::class) ? \Normalizer::normalize($s, \Normalizer::FORM_D) : false;
            return mb_strtolower(is_string($decomposed) ? (string) preg_replace('/\p{Mn}/u', '', $decomposed) : $s);
        };
        $albumText = static fn (array $row): string => $fold($row['album_title'] . ' ' . $row['location']);

        /** @var array<int, array<string, mixed>> $picked une photo par album */
        $picked = $first ? [(int) $first['album_id'] => $first] : [];
        $take = static function (array $candidates, callable $accept) use (&$picked, $limit): void {
            foreach ($candidates as $row) {
                $albumId = (int) $row['album_id'];
                if (count($picked) < $limit && !isset($picked[$albumId]) && $accept($row)) {
                    $picked[$albumId] = $row;
                    return;
                }
            }
        };

        for ($i = 0; $i < $nightCount; $i++) {
            $take($night, static fn (): bool => true);
        }
        foreach ($places as $place) {
            $needle = $fold(trim($place));
            $alreadyThere = array_filter($picked, static fn (array $row): bool => str_contains($albumText($row), $needle));
            if ($needle !== '' && $alreadyThere === []) {
                $take($rows, static fn (array $row): bool => str_contains($albumText($row), $needle));
            }
        }
        foreach ($rows as $row) {
            $take([$row], static fn (): bool => true);
        }

        if ($first) {
            unset($picked[(int) $first['album_id']]);
        }
        $reel = array_values($picked);
        shuffle($reel);
        return $first ? [$first, ...$reel] : $reel;
    }
}
