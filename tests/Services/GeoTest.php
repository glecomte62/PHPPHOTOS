<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\Geo;
use PHPUnit\Framework\TestCase;

final class GeoTest extends TestCase
{
    public function testDistanceBetweenTwoKnownPlaces(): void
    {
        // Beffroi d'Arras -> abbaye du Mont-Saint-Éloi : environ 8,6 km
        $km = Geo::distanceKm(50.2911, 2.7775, 50.3497, 2.6941);
        self::assertEqualsWithDelta(8.6, $km, 0.4);
        self::assertSame(0.0, Geo::distanceKm(50.0, 2.0, 50.0, 2.0));
    }

    public function testBoundingBoxContainsTheRadius(): void
    {
        $box = Geo::boundingBox(50.0, 2.0, 10.0);

        self::assertLessThan(50.0, $box['minLat']);
        self::assertGreaterThan(50.0, $box['maxLat']);
        // 10 km au nord et à l'est restent dans la boîte
        self::assertLessThanOrEqual($box['maxLat'], 50.0 + 10 / 111.2);
        self::assertLessThanOrEqual($box['maxLng'], 2.0 + 10 / (111.2 * cos(deg2rad(50.0))));
        // La boîte en longitude est plus large qu'en latitude à cette latitude
        self::assertGreaterThan($box['maxLat'] - $box['minLat'], $box['maxLng'] - $box['minLng']);
    }

    public function testDistanceLabel(): void
    {
        self::assertSame('à moins de 100 m', Geo::distanceLabel(0.04));
        self::assertSame('à 350 m', Geo::distanceLabel(0.347));
        self::assertSame('à 2,4 km', Geo::distanceLabel(2.43));
        self::assertSame('à 12 km', Geo::distanceLabel(12.4));
    }
}
