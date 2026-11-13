<?php
declare(strict_types=1);

namespace Tests\Services;

use App\Services\ImageException;
use App\Services\ImageProcessor;
use PHPUnit\Framework\TestCase;

final class ImageProcessorTest extends TestCase
{
    private const FIX = __DIR__ . '/../fixtures/';
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/img-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testCreatesAllVariantsWithoutUpscaling(): void
    {
        $size = (new ImageProcessor())->process(self::FIX . 'exif-full.jpg', $this->dir, 'abc', 1);

        self::assertSame(['width' => 600, 'height' => 400], $size);
        foreach ([480, 1280, 2560] as $w) {
            self::assertFileExists("{$this->dir}/abc-$w.webp");
            self::assertFileExists("{$this->dir}/abc-$w.jpg");
        }
        self::assertSame([480, 320], array_slice(getimagesize("{$this->dir}/abc-480.jpg"), 0, 2));
        self::assertSame([600, 400], array_slice(getimagesize("{$this->dir}/abc-1280.jpg"), 0, 2));
        self::assertSame(IMAGETYPE_WEBP, getimagesize("{$this->dir}/abc-480.webp")[2]);
    }

    public function testOutputHasNoExif(): void
    {
        (new ImageProcessor())->process(self::FIX . 'exif-full.jpg', $this->dir, 'abc', 1);
        $exif = @exif_read_data("{$this->dir}/abc-1280.jpg") ?: [];
        self::assertArrayNotHasKey('GPSLatitude', $exif);
        self::assertArrayNotHasKey('Model', $exif);
    }

    public function testAppliesOrientation(): void
    {
        $size = (new ImageProcessor())->process(self::FIX . 'exif-rotated.jpg', $this->dir, 'rot', 6);

        self::assertSame(['width' => 400, 'height' => 600], $size);
        $img = imagecreatefromjpeg("{$this->dir}/rot-1280.jpg");
        $top = imagecolorsforindex($img, imagecolorat($img, 200, 100));
        self::assertGreaterThan($top['red'], $top['blue'], 'la moitié bleue doit être en haut après rotation de 90° horaire');
    }

    public function testRejectsNonJpeg(): void
    {
        $this->expectException(ImageException::class);
        (new ImageProcessor())->process(self::FIX . 'not-a-jpeg.png', $this->dir, 'x', 1);
    }

    public function testRejectsTooManyPixels(): void
    {
        $this->expectException(ImageException::class);
        (new ImageProcessor(maxPixels: 1000))->process(self::FIX . 'exif-full.jpg', $this->dir, 'x', 1);
    }

    public function testDeleteVariants(): void
    {
        (new ImageProcessor())->process(self::FIX . 'no-exif.jpg', $this->dir, 'del', 1);
        ImageProcessor::deleteVariants($this->dir, 'del');
        self::assertSame([], glob($this->dir . '/del-*'));
    }
}
