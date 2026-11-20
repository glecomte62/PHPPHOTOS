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

    public function testReadsEmbeddedIccProfileDescription(): void
    {
        self::assertSame('ROMM RGB: ISO 22028-2:2013', ImageProcessor::iccDescription(self::FIX . 'prophoto.jpg'));
        self::assertNull(ImageProcessor::iccDescription(self::FIX . 'no-exif.jpg'));
    }

    public function testConvertsWideGamutPhotosToSrgb(): void
    {
        if (ImageProcessor::convertBinary() === null) {
            self::markTestSkipped('ImageMagick absent');
        }
        (new ImageProcessor())->process(self::FIX . 'prophoto.jpg', $this->dir, 'pp', 1);

        $img = imagecreatefromjpeg("{$this->dir}/pp-480.jpg");
        $rgb = imagecolorat($img, 8, 8);
        // Sans conversion, le rouge resterait vers 150 (couleurs ternes) ; en sRGB il dépasse 200.
        self::assertGreaterThan(195, ($rgb >> 16) & 0xFF);
        self::assertLessThan(105, ($rgb >> 8) & 0xFF);
    }

    public function testLargeWideGamutPhotoKeepsOriginalDimensions(): void
    {
        $bin = ImageProcessor::convertBinary();
        if ($bin === null) {
            self::markTestSkipped('ImageMagick absent');
        }
        mkdir($this->dir, 0775, true);
        $big = $this->dir . '/big-src.jpg';
        // Agrandit la mire en gardant son profil ProPhoto
        exec(escapeshellarg($bin) . ' ' . escapeshellarg(self::FIX . 'prophoto.jpg') . ' -resize 6000x3000! ' . escapeshellarg($big));

        $size = (new ImageProcessor(requireSrgb: true))->process($big, $this->dir, 'big', 1);

        self::assertSame(['width' => 6000, 'height' => 3000], $size);
        self::assertSame([2560, 1280], array_slice(getimagesize("{$this->dir}/big-2560.jpg"), 0, 2));
        $rgb = imagecolorat(imagecreatefromjpeg("{$this->dir}/big-480.jpg"), 100, 100);
        self::assertGreaterThan(195, ($rgb >> 16) & 0xFF);
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

    public function testRejectsInvalidKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ImageProcessor())->process(self::FIX . 'no-exif.jpg', $this->dir, '../evil', 1);
    }

    public function testAcceptsHyphenatedKey(): void
    {
        (new ImageProcessor())->process(self::FIX . 'no-exif.jpg', $this->dir, 'banner-1a2b3c4d', 1);
        self::assertFileExists("{$this->dir}/banner-1a2b3c4d-480.jpg");
    }

    public function testParseBytes(): void
    {
        self::assertSame(512 * 1024 * 1024, ImageProcessor::parseBytes('512M'));
        self::assertSame(1024 ** 3, ImageProcessor::parseBytes('1G'));
        self::assertSame(128 * 1024, ImageProcessor::parseBytes('128K'));
        self::assertSame(-1, ImageProcessor::parseBytes('-1'));
        self::assertSame(2048, ImageProcessor::parseBytes('2048'));
    }

    public function testRejectsImageExceedingMemoryBudget(): void
    {
        $processor = new ImageProcessor(memoryBudget: 33 * 1024 * 1024);

        $this->expectException(ImageException::class);
        $this->expectExceptionMessage('trop lourde pour la mémoire');
        $processor->process(self::FIX . 'exif-full.jpg', $this->dir, 'big', 1);
    }
}
