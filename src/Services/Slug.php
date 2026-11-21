<?php
declare(strict_types=1);

namespace App\Services;

final class Slug
{
    /** Premiers segments d'URL utilisés par le site : aucun thème ne peut les prendre. */
    public const RESERVED = ['admin', 'assets', 'media', 'carte', 'carte.json', 'presentation', 'contact', 'sitemap.xml', 'robots.txt', 'recherche', 'opensearch.xml',
        'photos', 'rss.xml', 'api', 'telecharger', 'mots-cles', 'populaires'];

    private const MAP = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
        'ÿ' => 'y', 'ý' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ];

    public static function make(string $text): string
    {
        $s = strtr(mb_strtolower($text, 'UTF-8'), self::MAP);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim(substr(trim($s, '-'), 0, 80), '-');
        return $s === '' ? 'sans-titre' : $s;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }

    /** @param callable(string): bool $exists */
    public static function unique(string $text, callable $exists, bool $checkReserved = false): string
    {
        $base = self::make($text);
        $slug = $base;
        $i = 2;
        while (($checkReserved && self::isReserved($slug)) || $exists($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
