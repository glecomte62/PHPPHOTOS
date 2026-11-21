<?php
declare(strict_types=1);

namespace App\Services;

final class ImageProcessor
{
    public const WIDTHS = [480, 1280, 2560];
    private const CONVERT_ATTEMPTS = 3;

    public function __construct(
        private int $maxPixels = 60_000_000,
        private int $webpQuality = 82,
        private int $jpegQuality = 85,
        private ?int $memoryBudget = null,
        private bool $requireSrgb = false,
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
        if (!preg_match('/^[a-z0-9-]+$/', $key)) {
            throw new \InvalidArgumentException('Clé de fichier invalide.');
        }
        $info = @getimagesize($source);
        if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
            throw new ImageException('Le fichier n\'est pas une image JPEG valide.');
        }
        if ($info[0] * $info[1] > $this->maxPixels) {
            throw new ImageException('Image trop grande (' . intdiv($this->maxPixels, 1_000_000) . ' mégapixels maximum).');
        }
        $budget = $this->memoryBudget ?? self::availableMemory();
        if ($budget !== null) {
            $needed = $info[0] * $info[1] * 5 + 32 * 1024 * 1024;
            if ($needed > $budget) {
                $maxMpx = max(0, intdiv($budget - 32 * 1024 * 1024, 5 * 1_000_000));
                throw new ImageException('Image trop lourde pour la mémoire du serveur (' . $maxMpx . ' Mpx maximum).');
            }
        }
        ini_set('gd.jpeg_ignore_warning', '1');
        // GD ignore les profils couleur : une photo ProPhoto ou Adobe RGB serait affichée délavée.
        $srgb = self::toSrgb($source, max($widths));
        if ($srgb === null && $this->requireSrgb && self::needsConversion($source)) {
            throw new ImageException('Conversion du profil couleur en sRGB impossible.');
        }
        try {
            $img = @imagecreatefromjpeg($srgb ?? $source);
        } finally {
            if ($srgb !== null) {
                @unlink($srgb);
            }
        }
        if ($img === false) {
            throw new ImageException('Impossible de lire l\'image.');
        }
        $sw = imagesx($img);
        $sh = imagesy($img);
        // Orientations 5 à 8 : l'image finale a la largeur et la hauteur inversées.
        // Les dimensions annoncées restent celles de l'original, même si la copie sRGB est réduite.
        $swapped = $orientation >= 5 && $orientation <= 8;
        $w = $swapped ? $info[1] : $info[0];
        $h = $swapped ? $info[0] : $info[1];

        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new ImageException('Dossier de destination inaccessible.');
        }

        $written = [];
        try {
            foreach ($widths as $target) {
                $tw = min($target, $w);
                $th = max(1, (int) round($h * $tw / $w));
                // Redimensionner d'abord (sans rotation), puis orienter la petite image.
                $cw = $swapped ? $th : $tw;
                $ch = $swapped ? $tw : $th;
                $canvas = imagecreatetruecolor($cw, $ch);
                imagecopyresampled($canvas, $img, 0, 0, 0, 0, $cw, $ch, $sw, $sh);
                $dst = $this->orient($canvas, $orientation);
                $base = $destDir . '/' . $key . '-' . $target;

                $written[] = $base . '.webp';
                if (!imagewebp($dst, $base . '.webp', $this->webpQuality)) {
                    throw new ImageException('Écriture WebP impossible.');
                }

                imageinterlace($dst, true);
                $written[] = $base . '.jpg';
                if (!imagejpeg($dst, $base . '.jpg', $this->jpegQuality)) {
                    throw new ImageException('Écriture JPEG impossible.');
                }
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

    /** Description du profil couleur ICC intégré au JPEG (ex. « ProPhoto RGB »), ou null s'il n'y en a pas. */
    public static function iccDescription(string $jpeg): ?string
    {
        $fh = @fopen($jpeg, 'rb');
        if ($fh === false) {
            return null;
        }
        $chunks = [];
        try {
            if (fread($fh, 2) !== "\xFF\xD8") {
                return null;
            }
            while (!feof($fh)) {
                $marker = fread($fh, 2);
                if (strlen($marker) < 2 || $marker[0] !== "\xFF") {
                    break;
                }
                $code = ord($marker[1]);
                if ($code === 0xD9 || $code === 0xDA) {
                    break; // fin d'image ou début des données compressées
                }
                if ($code === 0x01 || ($code >= 0xD0 && $code <= 0xD7)) {
                    continue; // marqueurs sans longueur
                }
                $len = unpack('n', (string) fread($fh, 2))[1] ?? 0;
                if ($len < 2) {
                    break;
                }
                $data = $len > 2 ? (string) fread($fh, $len - 2) : '';
                if ($code === 0xE2 && str_starts_with($data, "ICC_PROFILE\0") && strlen($data) > 14) {
                    $chunks[ord($data[12])] = substr($data, 14);
                }
            }
        } finally {
            fclose($fh);
        }
        if ($chunks === []) {
            return null;
        }
        ksort($chunks);
        return self::profileName(implode('', $chunks));
    }

    /** Commande ImageMagick disponible (convert v6 ou magick v7), ou null. */
    public static function convertBinary(): ?string
    {
        foreach (['/usr/bin/convert', '/usr/local/bin/convert', '/opt/homebrew/bin/magick', '/usr/local/bin/magick'] as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }
        return null;
    }

    /**
     * Copie temporaire convertie en sRGB si le JPEG embarque un autre profil (ProPhoto, Adobe RGB…).
     * Null si aucune conversion n'est utile ou possible : l'image est alors traitée telle quelle.
     */
    private static function needsConversion(string $source): bool
    {
        $profile = self::iccDescription($source);
        return $profile !== null && stripos($profile, 'srgb') === false;
    }

    /**
     * La copie est réduite pour que son plus petit côté fasse au moins $minSide pixels : assez pour
     * toutes les variantes, sans charger 45 mégapixels en mémoire (le décodage JPEG est lui-même réduit).
     */
    private static function toSrgb(string $source, int $minSide): ?string
    {
        if (!self::needsConversion($source)) {
            return null;
        }
        $profile = (string) self::iccDescription($source);
        $bin = self::convertBinary();
        $icc = dirname(__DIR__, 2) . '/resources/icc/sRGB2014.icc';
        if ($bin === null || !function_exists('exec') || !is_file($icc)) {
            error_log("ImageProcessor : profil « $profile » non converti en sRGB (ImageMagick indisponible).");
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'srgb');
        if ($tmp === false) {
            return null;
        }
        $side = $minSide . 'x' . $minSide;
        $cmd = escapeshellarg($bin) . ' -limit memory 256MiB -limit map 512MiB -define ' . escapeshellarg('jpeg:size=' . $side) . ' '
            . escapeshellarg($source) . ' -profile ' . escapeshellarg($icc)
            . ' -resize ' . escapeshellarg($side . '^>') . ' -quality 96 ' . escapeshellarg('jpeg:' . $tmp) . ' 2>&1';
        // La limite mémoire du compte (LVE) tue souvent la première conversion (code 137) ; la suivante passe.
        for ($attempt = 1; $attempt <= self::CONVERT_ATTEMPTS; $attempt++) {
            $output = [];
            exec($cmd, $output, $code);
            if ($code !== 137) {
                break;
            }
            if ($attempt < self::CONVERT_ATTEMPTS) {
                gc_collect_cycles();
                sleep(1);
            }
        }
        $info = $code === 0 ? @getimagesize($tmp) : false;
        if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
            error_log("ImageProcessor : conversion sRGB échouée ($code) : " . implode(' ', $output));
            @unlink($tmp);
            return null;
        }
        return $tmp;
    }

    /** Nom lisible d'un profil ICC (balise « desc », en v2 comme en v4). */
    private static function profileName(string $icc): ?string
    {
        if (strlen($icc) < 132) {
            return null;
        }
        $count = unpack('N', $icc, 128)[1];
        for ($i = 0; $i < min($count, 100); $i++) {
            $entry = 132 + $i * 12;
            if (strlen($icc) < $entry + 12) {
                break;
            }
            ['sig' => $sig, 'off' => $off, 'size' => $size] = unpack('a4sig/Noff/Nsize', $icc, $entry);
            if ($sig !== 'desc') {
                continue;
            }
            $tag = substr($icc, $off, $size);
            if (str_starts_with($tag, 'desc') && strlen($tag) >= 12) {
                $n = unpack('N', $tag, 8)[1];
                return trim(rtrim(substr($tag, 12, $n), "\0")) ?: null;
            }
            if (str_starts_with($tag, 'mluc') && strlen($tag) >= 28) {
                ['len' => $len, 'at' => $at] = unpack('Nlen/Nat', $tag, 20);
                $text = mb_convert_encoding(substr($tag, $at, $len), 'UTF-8', 'UTF-16BE');
                return trim($text) ?: null;
            }
            return null;
        }
        return null;
    }

    /** Convertit une valeur php.ini ('512M', '1G', '128K', '-1') en octets (-1 = illimité). */
    public static function parseBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $n = (int) $value;
        return match (strtoupper(substr($value, -1))) {
            'G' => $n * 1024 ** 3,
            'M' => $n * 1024 ** 2,
            'K' => $n * 1024,
            default => $n,
        };
    }

    /** Mémoire encore disponible, ou null si illimitée. */
    private static function availableMemory(): ?int
    {
        $limit = self::parseBytes((string) ini_get('memory_limit'));
        return $limit < 0 ? null : $limit - memory_get_usage();
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
            3 => $this->rotate($img, 180),
            4 => $this->flip($img, IMG_FLIP_VERTICAL),
            5 => $this->flip($this->rotate($img, -90), IMG_FLIP_HORIZONTAL),
            6 => $this->rotate($img, -90),
            7 => $this->flip($this->rotate($img, 90), IMG_FLIP_HORIZONTAL),
            8 => $this->rotate($img, 90),
            default => $img,
        };
    }

    private function rotate(\GdImage $img, int $angle): \GdImage
    {
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated === false) {
            throw new ImageException('Rotation de l\'image impossible.');
        }
        return $rotated;
    }

    private function flip(\GdImage $img, int $mode): \GdImage
    {
        imageflip($img, $mode);
        return $img;
    }
}
