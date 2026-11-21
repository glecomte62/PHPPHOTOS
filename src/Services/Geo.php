<?php
declare(strict_types=1);

namespace App\Services;

/** Calculs de distance pour les « photos à proximité ». */
final class Geo
{
    private const EARTH_KM = 6371.0;
    private const KM_PER_DEGREE = 111.2;

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * self::EARTH_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * Rectangle qui contient le cercle de rayon $km : sert de premier tri en SQL avant le calcul exact.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public static function boundingBox(float $lat, float $lng, float $km): array
    {
        $dLat = $km / self::KM_PER_DEGREE;
        $dLng = $km / (self::KM_PER_DEGREE * max(0.01, cos(deg2rad($lat))));
        return ['minLat' => $lat - $dLat, 'maxLat' => $lat + $dLat, 'minLng' => $lng - $dLng, 'maxLng' => $lng + $dLng];
    }

    public static function distanceLabel(float $km): string
    {
        if ($km < 0.1) {
            return 'à moins de 100 m';
        }
        if ($km < 1) {
            return 'à ' . (int) (round($km * 20) * 50) . ' m';
        }
        if ($km < 10) {
            return 'à ' . str_replace('.', ',', (string) round($km, 1)) . ' km';
        }
        return 'à ' . (int) round($km) . ' km';
    }
}
