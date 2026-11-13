<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Page;

/** Les 2 images propres à la page Présentation (hors albums). */
final class PresentationImages
{
    public const DIR = '/public/assets/img/presentation';
    public const WIDTHS = [1280, 2560];
    public const SLOTS = ['banner', 'portrait'];

    public static function url(string $key, int $width, string $ext = 'jpg'): string
    {
        return '/assets/img/presentation/' . $key . '-' . $width . '.' . $ext;
    }

    /** Traite la nouvelle image, met à jour la page et supprime l'ancienne. Retourne la nouvelle clé. */
    public static function replace(string $slot, string $source): string
    {
        if (!in_array($slot, self::SLOTS, true)) {
            throw new \InvalidArgumentException("Emplacement inconnu : $slot");
        }
        $dir = ROOT . self::DIR;
        $key = $slot . '-' . bin2hex(random_bytes(4));
        $exif = (new ExifReader())->read($source);
        (new ImageProcessor())->process($source, $dir, $key, $exif['orientation'], self::WIDTHS);

        $column = $slot . '_image';
        $old = Page::find('presentation')[$column] ?? null;
        try {
            Page::update('presentation', [$column => $key]);
        } catch (\Throwable $e) {
            ImageProcessor::deleteVariants($dir, $key, self::WIDTHS);
            throw $e;
        }
        if (is_string($old) && $old !== '') {
            ImageProcessor::deleteVariants($dir, $old, self::WIDTHS);
        }
        return $key;
    }
}
