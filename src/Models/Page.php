<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Page
{
    private const EDITABLE = ['title', 'lead', 'body_html', 'pilot_info', 'banner_image', 'portrait_image'];

    public static function find(string $slug): ?array
    {
        return Database::one('SELECT * FROM pages WHERE slug = ?', [$slug]);
    }

    /** @param array<string, mixed> $data */
    public static function update(string $slug, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(self::EDITABLE));
        if ($fields === []) {
            return;
        }
        $set = implode(', ', array_map(static fn ($c) => "`$c` = :$c", array_keys($fields)));
        Database::run("UPDATE pages SET $set WHERE slug = :__slug", $fields + ['__slug' => $slug]);
    }
}
