<?php
declare(strict_types=1);

namespace App\Services;

final class ImageProcessor
{
    public const WIDTHS = [480, 1280, 2560];

    public function __construct(
        private int $maxPixels = 60_000_000,
        private int $webpQuality = 82,
        private int $jpegQuality = 85,
    ) {
    }

    /**
     * Crée {key}-{w}.webp et {key}-{w}.jpg dans $destDir pour chaque largeur (sans agrandir).
     * Les fichiers produits ne contiennent aucune métadonnée EXIF.
     *
     * @param list<int> $widths
     * @return array{width: int, height: int} dimensions de l'image une fois orientée
     */
    public function process(string $source, string $destDir, string $key, int $orientation = 1, array $widths = self::WIDTHS): array
    {
        $info = @getimagesize($source);
        if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
            throw new ImageException('Le fichier n\'est pas une image JPEG valide.');
        }
        if ($info[0] * $info[1] > $this->maxPixels) {
            throw new ImageException('Image trop grande (' . intdiv($this->maxPixels, 1_000_000) . ' mégapixels maximum).');
        }
        ini_set('gd.jpeg_ignore_warning', '1');
        $img = @imagecreatefromjpeg($source);
        if ($img === false) {
            throw new ImageException('Impossible de lire l\'image.');
        }
        $img = $this->orient($img, $orientation);
        $w = imagesx($img);
        $h = imagesy($img);

        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new ImageException('Dossier de destination inaccessible.');
        }

        $written = [];
        try {
            foreach ($widths as $target) {
                $tw = min($target, $w);
                $th = max(1, (int) round($h * $tw / $w));
                $dst = imagecreatetruecolor($tw, $th);
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
                $base = $destDir . '/' . $key . '-' . $target;

                if (!imagewebp($dst, $base . '.webp', $this->webpQuality)) {
                    throw new ImageException('Écriture WebP impossible.');
                }
                $written[] = $base . '.webp';

                imageinterlace($dst, true);
                if (!imagejpeg($dst, $base . '.jpg', $this->jpegQuality)) {
                    throw new ImageException('Écriture JPEG impossible.');
                }
                $written[] = $base . '.jpg';
                $dst = null;
            }
        } catch (\Throwable $e) {
            foreach ($written as $file) {
                @unlink($file);
            }
            throw $e;
        }

        return ['width' => $w, 'height' => $h];
    }

    /** @param list<int> $widths */
    public static function deleteVariants(string $dir, string $key, array $widths = self::WIDTHS): void
    {
        foreach ($widths as $w) {
            foreach (['webp', 'jpg'] as $ext) {
                $file = "$dir/$key-$w.$ext";
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function orient(\GdImage $img, int $orientation): \GdImage
    {
        // imagerotate : angle positif = sens anti-horaire
        return match ($orientation) {
            2 => $this->flip($img, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($img, 180, 0),
            4 => $this->flip($img, IMG_FLIP_VERTICAL),
            5 => $this->flip(imagerotate($img, -90, 0), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($img, -90, 0),
            7 => $this->flip(imagerotate($img, 90, 0), IMG_FLIP_HORIZONTAL),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    private function flip(\GdImage $img, int $mode): \GdImage
    {
        imageflip($img, $mode);
        return $img;
    }
}
