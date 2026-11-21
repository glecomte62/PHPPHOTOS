<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\PhotoFilter;
use PHPUnit\Framework\TestCase;

final class PhotoFilterTest extends TestCase
{
    private const ALLOWED = [
        'camera' => ['NIKON D850', 'NIKON D610'],
        'lens' => ['24.0-120.0 mm f/4.0'],
        'year' => [2016, 2025],
        'theme' => ['aviation', 'villes-patrimoine'],
    ];

    public function testKeepsOnlyKnownValues(): void
    {
        $f = PhotoFilter::fromInput([
            'appareil' => 'NIKON D850', 'objectif' => 'Leica 50', 'focale' => 'tele', 'ouverture' => 'f/99',
            'iso' => 'haute', 'annee' => '2025', 'theme' => 'inconnu',
        ], self::ALLOWED);

        self::assertSame(['appareil' => 'NIKON D850', 'focale' => 'tele', 'iso' => 'haute', 'annee' => '2025'], $f->values());
        self::assertFalse($f->isEmpty());
    }

    public function testIgnoresNonStringInput(): void
    {
        $f = PhotoFilter::fromInput(['appareil' => ['NIKON D850'], 'annee' => '2025abc'], self::ALLOWED);

        self::assertTrue($f->isEmpty());
        self::assertSame([], $f->values());
        self::assertSame(['', []], $f->sql());
    }

    public function testBuildsSqlConditionsWithParameters(): void
    {
        $f = PhotoFilter::fromInput([
            'appareil' => 'NIKON D610', 'objectif' => '24.0-120.0 mm f/4.0', 'focale' => 'grand-angle',
            'ouverture' => 'grande', 'iso' => 'basse', 'annee' => '2016', 'theme' => 'aviation',
        ], self::ALLOWED);

        [$sql, $params] = $f->sql();

        self::assertStringContainsString('p.camera = ?', $sql);
        self::assertStringContainsString('p.lens = ?', $sql);
        self::assertStringContainsString('YEAR(p.taken_at) = ?', $sql);
        self::assertStringContainsString('t.slug = ?', $sql);
        self::assertStringContainsString('<= 24', $sql);
        self::assertStringContainsString('<= 2.8', $sql);
        self::assertStringContainsString('p.iso <= 200', $sql);
        self::assertSame(['NIKON D610', '24.0-120.0 mm f/4.0', 2016, 'aviation'], $params);
    }

    public function testRangeBoundsDoNotOverlap(): void
    {
        foreach (['focale' => PhotoFilter::FOCALS, 'ouverture' => PhotoFilter::APERTURES, 'iso' => PhotoFilter::ISOS] as $ranges) {
            $previousMax = null;
            foreach ($ranges as $range) {
                if ($previousMax !== null) {
                    self::assertSame($previousMax, $range['min'], 'chaque tranche commence où la précédente s\'arrête');
                }
                $previousMax = $range['max'];
            }
        }
    }

    public function testQueryStringKeepsFiltersAndText(): void
    {
        $f = PhotoFilter::fromInput(['appareil' => 'NIKON D850', 'annee' => '2025'], self::ALLOWED);

        self::assertSame('/recherche?q=avion&appareil=NIKON+D850&annee=2025&page=2', $f->url('avion', 2));
        self::assertSame('/recherche?appareil=NIKON+D850&annee=2025', $f->url('', 1));
        self::assertSame('/recherche?q=avion&annee=2025', $f->url('avion', 1, without: 'appareil'));
    }

    public function testReadableCameraAndLensLabels(): void
    {
        self::assertSame('Nikon D850', PhotoFilter::cameraLabel('NIKON D850'));
        self::assertSame('Canon MG6300 series Network', PhotoFilter::cameraLabel('Canon MG6300 series Network'));
        self::assertSame('24-120 mm f/4', PhotoFilter::lensLabel('24.0-120.0 mm f/4.0'));
        self::assertSame('18-200 mm f/3.5-5.6', PhotoFilter::lensLabel('18.0-200.0 mm f/3.5-5.6'));
        self::assertSame('50 mm f/1.8', PhotoFilter::lensLabel('50.0 mm f/1.8'));
        self::assertSame('AF-S Nikkor', PhotoFilter::lensLabel('AF-S Nikkor'));
    }

    public function testActiveFilterLabelsNameTheSetting(): void
    {
        self::assertSame('Nikon D850', PhotoFilter::label('appareil', 'NIKON D850'));
        self::assertSame('Grand-angle (≤ 24 mm)', PhotoFilter::label('focale', 'grand-angle'));
        self::assertSame('Ouverture grande (≤ f/2.8)', PhotoFilter::label('ouverture', 'grande'));
        self::assertSame('ISO haute (1000–3200)', PhotoFilter::label('iso', 'haute'));
        self::assertSame('2016', PhotoFilter::label('annee', '2016'));
    }
}
