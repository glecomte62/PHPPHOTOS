<?php

class Slug
{
    /** Common accented-character substitutions for reliable ASCII conversion */
    private static array $TRANSLIT = [
        'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','æ'=>'ae',
        'ç'=>'c',
        'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
        'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
        'ñ'=>'n',
        'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o',
        'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
        'ý'=>'y','ÿ'=>'y',
        'œ'=>'oe','ß'=>'ss',
    ];

    public static function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, self::$TRANSLIT);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
