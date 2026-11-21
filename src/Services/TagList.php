<?php
declare(strict_types=1);

namespace App\Services;

/** Mots-clés saisis dans l'admin sous la forme « street art, Belgique, village abandonné ». */
final class TagList
{
    public const MAX_LENGTH = 60;
    public const MAX_TAGS = 20;

    /** @return list<string> mots-clés nettoyés, sans doublon (casse et accents ignorés) */
    public static function parse(string $input): array
    {
        $tags = [];
        foreach (explode(',', $input) as $raw) {
            $tag = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $raw)), 0, self::MAX_LENGTH);
            if ($tag === '' || !preg_match('/[\p{L}\p{N}]/u', $tag)) {
                continue;
            }
            $slug = self::slug($tag);
            if (!isset($tags[$slug])) {
                $tags[$slug] = $tag;
            }
            if (count($tags) === self::MAX_TAGS) {
                break;
            }
        }
        return array_values($tags);
    }

    /** Forme utilisée dans l'adresse /mots-cles/{slug} et pour reconnaître un même mot-clé. */
    public static function slug(string $tag): string
    {
        return Slug::make($tag);
    }

    /** @param list<string> $tags */
    public static function join(array $tags): string
    {
        return implode(', ', $tags);
    }
}
