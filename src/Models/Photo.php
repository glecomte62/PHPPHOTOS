<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;
use App\Services\Geo;
use App\Services\PhotoFilter;
use App\Services\SearchQuery;

final class Photo
{
    private const SELECT = 'SELECT p.*, a.slug AS album_slug, a.title AS album_title, a.status AS album_status, a.location AS album_location,
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
    public static function search(array $terms, PhotoFilter $filter, int $limit, int $offset = 0): array
    {
        if ($terms === [] && $filter->isEmpty()) {
            return [];
        }
        [$where, $params] = self::searchWhere($terms, $filter);
        $score = [];
        $scoreParams = [];
        foreach ($terms as $term) {
            $like = SearchQuery::likePattern($term);
            $score[] = '(p.title LIKE ?) * 3 + (COALESCE(p.caption, \'\') LIKE ?) * 2';
            array_push($scoreParams, $like, $like);
        }
        // Sans texte, les photos les plus récentes d'abord
        $order = $score !== []
            ? '(' . implode(' + ', $score) . ') DESC, a.published_at DESC, p.position, p.id'
            : 'p.taken_at IS NULL, p.taken_at DESC, p.id DESC';
        return Database::all(
            self::SELECT . ' WHERE ' . $where . ' ORDER BY ' . $order
            . ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, (int) $offset),
            [...$params, ...$scoreParams]
        );
    }

    /** @param list<string> $terms */
    public static function countSearch(array $terms, PhotoFilter $filter): int
    {
        if ($terms === [] && $filter->isEmpty()) {
            return 0;
        }
        [$where, $params] = self::searchWhere($terms, $filter);
        return (int) Database::value(
            'SELECT COUNT(*) FROM photos p JOIN albums a ON a.id = p.album_id JOIN themes t ON t.id = a.theme_id WHERE ' . $where,
            $params
        );
    }

    /**
     * Valeurs proposées dans les filtres, avec leur nombre de photos publiées.
     *
     * @return array{camera: list<array{value: string, n: int}>, lens: list<array{value: string, n: int}>,
     *               year: list<array{value: int, n: int}>, theme: list<array{value: string, label: string, n: int}>}
     */
    public static function facets(): array
    {
        $from = " FROM photos p JOIN albums a ON a.id = p.album_id JOIN themes t ON t.id = a.theme_id WHERE a.status = 'published'";
        $column = static fn (string $expr, string $order): array => array_map(
            static fn (array $r): array => ['value' => $r['value'], 'n' => (int) $r['n']],
            Database::all("SELECT $expr AS value, COUNT(*) AS n$from AND $expr IS NOT NULL AND $expr <> '' GROUP BY value ORDER BY $order")
        );
        return [
            'camera' => $column('p.camera', 'n DESC, value'),
            'lens' => $column('p.lens', 'n DESC, value'),
            'year' => array_map(
                static fn (array $r): array => ['value' => (int) $r['value'], 'n' => $r['n']],
                $column('YEAR(p.taken_at)', 'value DESC')
            ),
            'theme' => array_map(
                static fn (array $r): array => ['value' => $r['slug'], 'label' => $r['title'], 'n' => (int) $r['n']],
                Database::all("SELECT t.slug, t.title, COUNT(*) AS n$from GROUP BY t.id, t.slug, t.title, t.position ORDER BY t.position, t.id")
            ),
        ];
    }

    public static function addView(int $id): void
    {
        Database::run('UPDATE photos SET views = views + 1 WHERE id = ?', [$id]);
    }

    /**
     * Ajoute ou retire le « j'aime » d'un navigateur, puis renvoie le nombre de « j'aime » de la photo.
     */
    public static function setLike(int $id, string $voter, bool $like): int
    {
        return (int) Database::transaction(static function () use ($id, $voter, $like): int {
            if ($like) {
                Database::run('INSERT IGNORE INTO photo_likes (photo_id, voter) VALUES (?, ?)', [$id, $voter]);
            } else {
                Database::run('DELETE FROM photo_likes WHERE photo_id = ? AND voter = ?', [$id, $voter]);
            }
            Database::run('UPDATE photos SET likes = (SELECT COUNT(*) FROM photo_likes WHERE photo_id = ?) WHERE id = ?', [$id, $id]);
            return (int) Database::value('SELECT likes FROM photos WHERE id = ?', [$id]);
        });
    }

    /** Photos publiées les plus vues (« vues ») ou les plus aimées (« aimees »). @return list<array<string, mixed>> */
    public static function popular(string $by, int $limit): array
    {
        $column = $by === 'aimees' ? 'p.likes' : 'p.views';
        return Database::all(
            self::SELECT . " WHERE a.status = 'published' AND $column > 0 ORDER BY $column DESC, p.likes + p.views DESC, p.id DESC LIMIT " . (int) $limit
        );
    }

    /** @return array{views: int, likes: int} totaux des albums publiés */
    public static function totals(): array
    {
        $row = Database::one("SELECT COALESCE(SUM(p.views), 0) AS views, COALESCE(SUM(p.likes), 0) AS likes FROM photos p JOIN albums a ON a.id = p.album_id WHERE a.status = 'published'");
        return ['views' => (int) ($row['views'] ?? 0), 'likes' => (int) ($row['likes'] ?? 0)];
    }

    /** Photos publiées portant un mot-clé, les plus récentes d'abord. @return list<array<string, mixed>> */
    public static function forTag(int $tagId, int $limit, int $offset = 0): array
    {
        return Database::all(
            self::SELECT . " JOIN photo_tags pt ON pt.photo_id = p.id WHERE pt.tag_id = ? AND a.status = 'published'
             ORDER BY p.taken_at IS NULL, p.taken_at DESC, p.id DESC LIMIT " . (int) $limit . ' OFFSET ' . max(0, $offset),
            [$tagId]
        );
    }

    public static function countForTag(int $tagId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM photos p JOIN albums a ON a.id = p.album_id JOIN photo_tags pt ON pt.photo_id = p.id
             WHERE pt.tag_id = ? AND a.status = 'published'",
            [$tagId]
        );
    }

    /**
     * Nombre de photos publiées par année et par mois de prise de vue (les plus récentes d'abord).
     *
     * @return list<array{year: int, month: int, n: int}>
     */
    public static function archive(): array
    {
        return array_map(
            static fn (array $r): array => ['year' => (int) $r['y'], 'month' => (int) $r['m'], 'n' => (int) $r['n']],
            Database::all(
                "SELECT YEAR(p.taken_at) AS y, MONTH(p.taken_at) AS m, COUNT(*) AS n
                 FROM photos p JOIN albums a ON a.id = p.album_id
                 WHERE a.status = 'published' AND p.taken_at IS NOT NULL
                 GROUP BY y, m ORDER BY y DESC, m DESC"
            )
        );
    }

    /**
     * Fil chronologique : photos publiées, des plus récentes aux plus anciennes, éventuellement
     * limitées à une année ou à un mois de prise de vue.
     *
     * @return list<array<string, mixed>>
     */
    public static function stream(?int $year, ?int $month, int $limit, int $offset = 0): array
    {
        [$where, $params] = self::streamWhere($year, $month);
        return Database::all(
            self::SELECT . " WHERE $where ORDER BY p.taken_at IS NULL, p.taken_at DESC, p.id DESC LIMIT " . (int) $limit . ' OFFSET ' . max(0, $offset),
            $params
        );
    }

    public static function countStream(?int $year, ?int $month): int
    {
        [$where, $params] = self::streamWhere($year, $month);
        return (int) Database::value("SELECT COUNT(*) FROM photos p JOIN albums a ON a.id = p.album_id WHERE $where", $params);
    }

    /** @return array{0: string, 1: list<int>} */
    private static function streamWhere(?int $year, ?int $month): array
    {
        $where = "a.status = 'published'";
        $params = [];
        if ($year !== null) {
            $where .= ' AND YEAR(p.taken_at) = ?';
            $params[] = $year;
            if ($month !== null) {
                $where .= ' AND MONTH(p.taken_at) = ?';
                $params[] = $month;
            }
        }
        return [$where, $params];
    }

    /**
     * Photos publiées d'autres albums prises à moins de $km de la photo, les plus proches d'abord.
     *
     * @param array<string, mixed> $photo
     * @return list<array<string, mixed>> lignes complétées d'une clé « distance_km »
     */
    public static function nearby(array $photo, float $km, int $limit): array
    {
        if ($photo['lat'] === null || $photo['lng'] === null) {
            return [];
        }
        $lat = (float) $photo['lat'];
        $lng = (float) $photo['lng'];
        $box = Geo::boundingBox($lat, $lng, $km);
        $rows = Database::all(
            self::SELECT . " WHERE a.status = 'published' AND p.album_id <> ? AND p.lat BETWEEN ? AND ? AND p.lng BETWEEN ? AND ?",
            [(int) $photo['album_id'], $box['minLat'], $box['maxLat'], $box['minLng'], $box['maxLng']]
        );
        foreach ($rows as &$row) {
            $row['distance_km'] = Geo::distanceKm($lat, $lng, (float) $row['lat'], (float) $row['lng']);
        }
        unset($row);
        $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['distance_km'] <= $km));
        usort($rows, static fn (array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']);
        // Deux photos par album au plus, pour varier les suggestions
        $picked = [];
        $perAlbum = [];
        foreach ($rows as $row) {
            $albumId = (int) $row['album_id'];
            if (($perAlbum[$albumId] ?? 0) < 2) {
                $perAlbum[$albumId] = ($perAlbum[$albumId] ?? 0) + 1;
                $picked[] = $row;
            }
            if (count($picked) === $limit) {
                break;
            }
        }
        return $picked;
    }

    /**
     * @param list<string> $terms
     * @return array{0: string, 1: list<mixed>}
     */
    private static function searchWhere(array $terms, PhotoFilter $filter): array
    {
        $where = ["a.status = 'published'"];
        $params = [];
        foreach ($terms as $term) {
            $where[] = "CONCAT_WS(' ', p.title, p.caption, a.title, a.location, t.title,
                (SELECT GROUP_CONCAT(tg.name SEPARATOR ' ') FROM photo_tags ptg JOIN tags tg ON tg.id = ptg.tag_id WHERE ptg.photo_id = p.id)) LIKE ?";
            $params[] = SearchQuery::likePattern($term);
        }
        [$filterSql, $filterParams] = $filter->sql();
        if ($filterSql !== '') {
            $where[] = $filterSql;
            array_push($params, ...$filterParams);
        }
        return [implode(' AND ', $where), $params];
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
        $albumText = static fn (array $row): string => $fold($row['album_title'] . ' ' . $row['album_location']);

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
