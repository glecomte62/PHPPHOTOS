<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\ExifReader;
use PHPUnit\Framework\TestCase;

final class ExifReaderTest extends TestCase
{
    private const FIX = __DIR__ . '/../fixtures/';

    public function testReadsFullExif(): void
    {
        $exif = (new ExifReader())->read(self::FIX . 'exif-full.jpg');

        self::assertSame('2025-06-14 10:32:05', $exif['taken_at']);
        self::assertSame('NIKON D810', $exif['camera']);
        self::assertSame('24.0-120.0 mm f/4.0', $exif['lens']);
        self::assertSame('35 mm', $exif['focal_length']);
        self::assertSame('f/8', $exif['aperture']);
        self::assertSame('1/500 s', $exif['shutter']);
        self::assertSame(200, $exif['iso']);
        self::assertEqualsWithDelta(50.2915, $exif['lat'], 0.0001);
        self::assertEqualsWithDelta(2.7775, $exif['lng'], 0.0001);
        self::assertSame(1, $exif['orientation']);
    }

    public function testReadsOrientation(): void
    {
        self::assertSame(6, (new ExifReader())->read(self::FIX . 'exif-rotated.jpg')['orientation']);
    }

    public function testNoExifGivesNulls(): void
    {
        $exif = (new ExifReader())->read(self::FIX . 'no-exif.jpg');
        self::assertNull($exif['taken_at']);
        self::assertNull($exif['camera']);
        self::assertNull($exif['lat']);
        self::assertSame(1, $exif['orientation']);
    }

    public function testFormattingHelpers(): void
    {
        self::assertSame('SONY NEX-VG10E', ExifReader::camera('SONY', 'NEX-VG10E'));
        self::assertSame('Canon EOS R5', ExifReader::camera('Canon', 'Canon EOS R5'));
        self::assertSame('2 s', ExifReader::shutter(2.0));
        self::assertSame('1/60 s', ExifReader::shutter(1 / 60));
        self::assertSame('f/5.6', ExifReader::aperture(5.6));
        self::assertEqualsWithDelta(-33.8, ExifReader::gps(['33/1', '48/1', '0/1'], 'S'), 0.0001);
        self::assertNull(ExifReader::gps(['0/1', '0/1', '0/1'], 'N'));
    }

    public function testShutterDecimalBetweenPointThreeAndOne(): void
    {
        self::assertSame('0.4 s', ExifReader::shutter(0.4));
        self::assertSame('1/4 s', ExifReader::shutter(0.25));
    }

    public function testGpsRejectsOutOfRange(): void
    {
        self::assertNull(ExifReader::gps(['200/1', '0/1', '0/1'], 'E'));
        self::assertEqualsWithDelta(170.0, ExifReader::gps(['170/1', '0/1', '0/1'], 'E'), 0.0001);
    }

    public function testRejectsInvalidDate(): void
    {
        $m = new \ReflectionMethod(ExifReader::class, 'date');
        self::assertNull($m->invoke(null, '2025:13:45 25:61:61'));
        self::assertSame('2025-06-14 10:32:05', $m->invoke(null, '2025:06:14 10:32:05'));
    }

    public function testScrubsInvalidUtf8(): void
    {
        $camera = ExifReader::camera("\xFF\xFEABC", null);

        self::assertNotNull($camera);
        self::assertTrue(mb_check_encoding($camera, 'UTF-8'));
        self::assertStringContainsString('ABC', $camera);
    }
}
