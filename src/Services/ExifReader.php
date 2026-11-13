<?php
declare(strict_types=1);

namespace App\Services;

final class ExifReader
{
    /**
     * @return array{taken_at: ?string, camera: ?string, lens: ?string, focal_length: ?string,
     *               aperture: ?string, shutter: ?string, iso: ?int, lat: ?float, lng: ?float, orientation: int}
     */
    public function read(string $path): array
    {
        $data = @exif_read_data($path, null, false);
        if (!is_array($data)) {
            $data = [];
        }
        $focal = self::rational($data['FocalLength'] ?? null);
        $fnumber = self::rational($data['FNumber'] ?? null);
        $orientation = (int) ($data['Orientation'] ?? 1);

        return [
            'taken_at' => self::date($data['DateTimeOriginal'] ?? $data['DateTime'] ?? null),
            'camera' => self::camera($data['Make'] ?? null, $data['Model'] ?? null),
            'lens' => self::str($data['LensModel'] ?? $data['UndefinedTag:0xA434'] ?? null),
            'focal_length' => $focal ? (int) round($focal) . ' mm' : null,
            'aperture' => $fnumber ? self::aperture($fnumber) : null,
            'shutter' => self::shutter(self::rational($data['ExposureTime'] ?? null)),
            'iso' => self::iso($data['ISOSpeedRatings'] ?? $data['PhotographicSensitivity'] ?? null),
            'lat' => self::gps($data['GPSLatitude'] ?? null, (string) ($data['GPSLatitudeRef'] ?? 'N')),
            'lng' => self::gps($data['GPSLongitude'] ?? null, (string) ($data['GPSLongitudeRef'] ?? 'E')),
            'orientation' => $orientation >= 1 && $orientation <= 8 ? $orientation : 1,
        ];
    }

    public static function camera(mixed $make, mixed $model): ?string
    {
        $make = self::str($make);
        $model = self::str($model);
        if ($model === null) {
            return $make;
        }
        if ($make === null) {
            return $model;
        }
        $brand = strtolower((string) strtok($make, ' '));
        return str_contains(strtolower($model), $brand) ? $model : $make . ' ' . $model;
    }

    public static function shutter(?float $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }
        return $seconds >= 1 ? self::trimNumber($seconds) . ' s' : '1/' . (int) round(1 / $seconds) . ' s';
    }

    public static function aperture(float $f): string
    {
        return 'f/' . self::trimNumber($f);
    }

    /** @param mixed $parts tableau de 3 rationnels (degrés, minutes, secondes) */
    public static function gps(mixed $parts, string $ref): ?float
    {
        if (!is_array($parts) || count($parts) < 3) {
            return null;
        }
        $deg = self::rational($parts[0]);
        $min = self::rational($parts[1]);
        $sec = self::rational($parts[2]);
        if ($deg === null || $min === null || $sec === null) {
            return null;
        }
        $value = $deg + $min / 60 + $sec / 3600;
        if ($value == 0.0) {
            return null;
        }
        return round(in_array(strtoupper($ref), ['S', 'W'], true) ? -$value : $value, 7);
    }

    private static function rational(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && preg_match('#^(-?\d+(?:\.\d+)?)(?:/(\d+))?$#', trim($value), $m)) {
            if (isset($m[2])) {
                return (int) $m[2] === 0 ? null : (float) $m[1] / (int) $m[2];
            }
            return (float) $m[1];
        }
        return null;
    }

    private static function date(mixed $value): ?string
    {
        $value = self::str($value);
        if ($value === null || str_starts_with($value, '0000')) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $value);
        return $d ? $d->format('Y-m-d H:i:s') : null;
    }

    private static function iso(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function str(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim(str_replace("\0", '', $value));
        return $value === '' ? null : $value;
    }

    private static function trimNumber(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }
}
