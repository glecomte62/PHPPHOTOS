<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\Database;

final class Setting
{
    /** @var array<string, ?string>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$cache ??= array_column(Database::all('SELECT `key`, `value` FROM settings'), 'value', 'key');
        return self::$cache[$key] ?? $default;
    }

    /** Lieux toujours présents dans le diaporama d'accueil. @return list<string> */
    public static function heroReelPlaces(): array
    {
        $raw = (string) self::get('hero_reel_places', 'Arras, Paris');
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $p): bool => $p !== ''));
    }

    /** Nombre de photos de nuit toujours présentes dans le diaporama d'accueil. */
    public static function heroReelNight(): int
    {
        return max(0, min(8, (int) self::get('hero_reel_night', '2')));
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value]
        );
        self::$cache = null;
    }
}
