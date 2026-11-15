<?php
declare(strict_types=1);

namespace App\Services;

/** Découpe une recherche saisie par un visiteur en mots utilisables dans une requête SQL LIKE. */
final class SearchQuery
{
    public const MAX_LENGTH = 100;
    public const MAX_TERMS = 6;

    private const STOP_WORDS = [
        'le', 'la', 'les', 'l', 'de', 'du', 'des', 'd', 'un', 'une', 'et', 'ou', 'a', 'à', 'au', 'aux',
        'en', 'sur', 'sous', 'dans', 'par', 'pour', 'avec', 'the', 'of', 'and',
    ];

    /** Texte de recherche nettoyé, tel qu'on le réaffiche dans le champ. */
    public static function normalize(mixed $input): string
    {
        if (!is_string($input)) {
            return '';
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', $input));
        return mb_substr($text, 0, self::MAX_LENGTH);
    }

    /** @return list<string> mots en minuscules, sans mots vides ni doublons */
    public static function terms(mixed $input): array
    {
        $text = mb_strtolower(self::normalize($input));
        $text = str_replace(['’', "'"], ' ', $text);
        $words = preg_split('/[^\p{L}\p{N}\-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach ($words as $word) {
            $word = trim($word, '-');
            if ($word === '' || in_array($word, self::STOP_WORDS, true) || in_array($word, $terms, true)) {
                continue;
            }
            $terms[] = $word;
            if (count($terms) === self::MAX_TERMS) {
                break;
            }
        }
        return $terms;
    }

    /** Motif LIKE « contient », avec les caractères spéciaux échappés. */
    public static function likePattern(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
