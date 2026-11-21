<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

/** Commentaires des visiteurs sur les photos : publiés seulement après validation dans l'admin. */
final class Comment
{
    private const SELECT = 'SELECT c.*, p.title AS photo_title, p.file_key, a.title AS album_title, a.slug AS album_slug, t.slug AS theme_slug
        FROM comments c JOIN photos p ON p.id = c.photo_id JOIN albums a ON a.id = p.album_id JOIN themes t ON t.id = a.theme_id';

    public static function create(int $photoId, string $name, string $message, ?string $ipHash): int
    {
        return Database::insert('comments', [
            'photo_id' => $photoId,
            'author_name' => $name,
            'message' => $message,
            'ip_hash' => $ipHash,
        ]);
    }

    /** @return list<array{author_name: string, message: string, created_at: string}> */
    public static function approvedForPhoto(int $photoId): array
    {
        return Database::all(
            "SELECT author_name, message, created_at FROM comments WHERE photo_id = ? AND status = 'approved' ORDER BY created_at, id",
            [$photoId]
        );
    }

    /** @return list<array<string, mixed>> */
    public static function pending(): array
    {
        return Database::all(self::SELECT . " WHERE c.status = 'pending' ORDER BY c.created_at");
    }

    /** @return list<array<string, mixed>> */
    public static function recentApproved(int $limit): array
    {
        return Database::all(self::SELECT . " WHERE c.status = 'approved' ORDER BY c.approved_at DESC, c.id DESC LIMIT " . (int) $limit);
    }

    public static function countPending(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
    }

    public static function approve(int $id): void
    {
        Database::run("UPDATE comments SET status = 'approved', approved_at = NOW() WHERE id = ?", [$id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM comments WHERE id = ?', [$id]);
    }
}
